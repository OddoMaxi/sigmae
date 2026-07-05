<?php

namespace Tests\Feature;

use App\Models\Lot;
use App\Models\Passeport;
use App\Models\Transporteur;
use App\Services\QrCodeService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class LotTest extends SgpTestCase
{
    // ── Création ──────────────────────────────────────────────────────────────

    public function test_agent_logistique_peut_creer_un_lot(): void
    {
        $user = $this->actingAsRole('agent_logistique');

        $this->postJson('/api/lots', [
            'ambassade_id'    => $this->ambassade->id,
            'transporteur_id' => $this->transporteur->id,
        ])->assertCreated()
            ->assertJsonFragment(['statut' => 'brouillon']);
    }

    public function test_agent_reception_ne_peut_pas_creer_un_lot(): void
    {
        $this->actingAsRole('agent_reception', $this->ambassade);

        $this->postJson('/api/lots', [
            'ambassade_id'    => $this->ambassade->id,
            'transporteur_id' => $this->transporteur->id,
        ])->assertForbidden();
    }

    public function test_auditeur_ne_peut_pas_creer_un_lot(): void
    {
        $this->actingAsRole('auditeur');

        $this->postJson('/api/lots', [
            'ambassade_id'    => $this->ambassade->id,
            'transporteur_id' => $this->transporteur->id,
        ])->assertForbidden();
    }

    public function test_creation_lot_sans_transporteur_echoue(): void
    {
        $this->actingAsRole('agent_logistique');

        $this->postJson('/api/lots', [
            'ambassade_id' => $this->ambassade->id,
        ])->assertUnprocessable();
    }

    public function test_creation_lot_sans_ambassade_echoue(): void
    {
        $this->actingAsRole('agent_logistique');

        $this->postJson('/api/lots', [
            'transporteur_id' => $this->transporteur->id,
        ])->assertUnprocessable();
    }

    // ── Règle une seule ambassade par lot ─────────────────────────────────────

    public function test_un_lot_est_cree_pour_une_seule_ambassade(): void
    {
        $user = $this->actingAsRole('agent_logistique');

        $response = $this->postJson('/api/lots', [
            'ambassade_id'    => $this->ambassade->id,
            'transporteur_id' => $this->transporteur->id,
        ])->assertCreated();

        // Le lot doit être lié uniquement à cette ambassade
        $this->assertDatabaseHas('lots', [
            'id'           => $response->json('id'),
            'ambassade_id' => $this->ambassade->id,
        ]);

        // Impossible d'ajouter un passeport d'une autre ambassade
        $passeportAutreAmb = $this->makePasseport($this->autreAmbassade);
        $this->postJson("/api/lots/{$response->json('id')}/passeports", [
            'passeport_ids' => [$passeportAutreAmb->id],
        ])->assertUnprocessable();
    }

    // ── Transporteur inactif ──────────────────────────────────────────────────

    public function test_expediton_impossible_avec_transporteur_inactif(): void
    {
        $user = $this->actingAsRole('responsable_cellule');
        $this->transporteur->update(['is_active' => false]);

        $lot = $this->makeLot($user, $this->ambassade, ['statut' => 'valide']);
        $this->makePasseport($this->ambassade)->update(['lot_id' => null]);

        $this->postJson("/api/lots/{$lot->id}/expedier")
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => fn ($v) => str_contains($v, 'inactif')]);
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_validation_lot_brouillon_vide_echoue(): void
    {
        $user = $this->actingAsRole('responsable_cellule');
        $lot  = $this->makeLot($user, $this->ambassade);

        $this->postJson("/api/lots/{$lot->id}/valider")
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => fn ($v) => str_contains($v, 'passeport')]);
    }

    public function test_validation_lot_avec_passeports_reussit(): void
    {
        $user = $this->actingAsRole('responsable_cellule');
        $lot  = $this->makeLot($user, $this->ambassade);
        $p    = $this->makePasseport($this->ambassade);

        $lot->passeports()->attach($p->id, ['statut_reception' => 'en_attente']);
        $p->update(['statut' => 'en_lot', 'lot_id' => $lot->id]);

        $this->postJson("/api/lots/{$lot->id}/valider")
            ->assertOk()
            ->assertJsonFragment(['statut' => 'valide']);
    }

    // ── Expédition + QR ──────────────────────────────────────────────────────

    public function test_expedition_genere_qr_token_et_bordereau(): void
    {
        Config::set('app.qr_secret_key', 'test_secret_key_for_tests');
        Config::set('app.frontend_url', 'http://localhost:3000');
        Storage::fake('local');
        Mail::fake();

        $user = $this->actingAsRole('agent_logistique');
        $lot  = $this->makeLot($user, $this->ambassade, ['statut' => 'valide']);
        $p    = $this->makePasseport($this->ambassade, ['statut' => 'en_lot']);

        $lot->passeports()->attach($p->id, ['statut_reception' => 'en_attente']);

        $this->postJson("/api/lots/{$lot->id}/expedier")
            ->assertOk()
            ->assertJsonFragment(['statut' => 'expedie']);

        $lot->refresh();
        $this->assertNotNull($lot->qr_token, 'QR token doit être généré');
        $this->assertNotNull($lot->bordereau_path, 'Bordereau PDF doit être généré');

        // Vérifier que le token est valide
        $qrService = app(QrCodeService::class);
        $payload   = $qrService->verifyToken($lot->qr_token);
        $this->assertNotNull($payload);
        $this->assertEquals($lot->id, $payload['lot_id']);
        $this->assertEquals($this->ambassade->id, $payload['amb']);
    }

    public function test_passeports_passent_a_expedie_lors_expedition(): void
    {
        Config::set('app.qr_secret_key', 'test_secret');
        Config::set('app.frontend_url', 'http://localhost:3000');
        Storage::fake('local');

        $user = $this->actingAsRole('agent_logistique');
        $lot  = $this->makeLot($user, $this->ambassade, ['statut' => 'valide']);
        $p    = $this->makePasseport($this->ambassade, ['statut' => 'en_lot']);

        $lot->passeports()->attach($p->id, ['statut_reception' => 'en_attente']);

        $this->postJson("/api/lots/{$lot->id}/expedier")->assertOk();

        $this->assertEquals('expedie', $p->fresh()->statut);
    }
}
