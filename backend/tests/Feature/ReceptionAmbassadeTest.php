<?php

namespace Tests\Feature;

use App\Models\Lot;
use App\Models\Passeport;
use App\Services\QrCodeService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ReceptionAmbassadeTest extends SgpTestCase
{
    private QrCodeService $qr;
    private string $validToken;
    private Lot $lot;
    private Passeport $p1;
    private Passeport $p2;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.qr_secret_key', 'reception_test_secret');
        Config::set('app.frontend_url', 'http://localhost:3000');
        Storage::fake('local');
        Mail::fake();

        $admin      = $this->makeAdminCentral();
        $this->lot  = $this->makeLot($admin, $this->ambassade, ['statut' => 'expedie']);
        $this->p1   = $this->makePasseport($this->ambassade, ['statut' => 'expedie']);
        $this->p2   = $this->makePasseport($this->ambassade, ['statut' => 'expedie']);

        $this->lot->passeports()->attach([$this->p1->id, $this->p2->id], ['statut_reception' => 'en_attente']);

        $this->qr         = app(QrCodeService::class);
        $this->validToken = $this->qr->generateToken($this->lot);
        $this->lot->update(['qr_token' => $this->validToken]);
    }

    // ── Scan public ──────────────────────────────────────────────────────────

    public function test_scan_public_retourne_resume_lot(): void
    {
        $this->getJson('/api/reception/scan/' . urlencode($this->validToken))
            ->assertOk()
            ->assertJsonFragment(['valid' => true, 'requires_auth' => true])
            ->assertJsonPath('lot.id', $this->lot->id)
            ->assertJsonPath('lot.nb_passeports', 2);
    }

    public function test_scan_public_token_inconnu(): void
    {
        $this->getJson('/api/reception/scan/badpayload.badsig')
            ->assertBadRequest()
            ->assertJsonFragment(['valid' => false]);
    }

    // ── Détail authentifié ────────────────────────────────────────────────────

    public function test_detail_retourne_passeports_a_agent_autorise(): void
    {
        $this->actingAsRole('agent_reception', $this->ambassade);

        $this->getJson('/api/reception/detail/' . urlencode($this->validToken))
            ->assertOk()
            ->assertJsonCount(2, 'passeports');
    }

    public function test_detail_refuse_a_agent_mauvaise_ambassade(): void
    {
        $this->actingAsRole('agent_reception', $this->autreAmbassade);

        $this->getJson('/api/reception/detail/' . urlencode($this->validToken))
            ->assertForbidden()
            ->assertJsonFragment(['error_code' => 'AMBASSADE_MISMATCH']);
    }

    public function test_detail_accessible_a_admin_central(): void
    {
        $this->actingAsRole('admin_central');

        $this->getJson('/api/reception/detail/' . urlencode($this->validToken))
            ->assertOk();
    }

    public function test_detail_sans_auth_refuse(): void
    {
        $this->getJson('/api/reception/detail/' . urlencode($this->validToken))
            ->assertUnauthorized();
    }

    // ── Confirmation batch ────────────────────────────────────────────────────

    public function test_confirmation_totale_passe_lot_a_recu(): void
    {
        $this->actingAsRole('agent_reception', $this->ambassade);

        $this->postJson('/api/reception/lot/' . urlencode($this->validToken), [
            'confirmations' => [
                ['passeport_id' => $this->p1->id, 'statut' => 'confirme'],
                ['passeport_id' => $this->p2->id, 'statut' => 'confirme'],
            ],
        ])->assertOk()
            ->assertJsonFragment(['lot_statut' => 'recu'])
            ->assertJsonFragment(['confirmes' => 2]);

        $this->assertEquals('recu', $this->lot->fresh()->statut);
    }

    public function test_confirmation_partielle_passe_lot_a_recu_partiel(): void
    {
        $this->actingAsRole('agent_reception', $this->ambassade);

        $this->postJson('/api/reception/lot/' . urlencode($this->validToken), [
            'confirmations' => [
                ['passeport_id' => $this->p1->id, 'statut' => 'confirme'],
                ['passeport_id' => $this->p2->id, 'statut' => 'manquant'],
            ],
        ])->assertOk()
            ->assertJsonFragment(['lot_statut' => 'recu_partiel']);
    }

    public function test_confirmation_enregistre_anomalie_dans_pivot(): void
    {
        $this->actingAsRole('agent_reception', $this->ambassade);

        $this->postJson('/api/reception/lot/' . urlencode($this->validToken), [
            'confirmations' => [
                ['passeport_id' => $this->p1->id, 'statut' => 'anomalie', 'notes' => 'Passeport abîmé'],
                ['passeport_id' => $this->p2->id, 'statut' => 'confirme'],
            ],
        ])->assertOk();

        $pivot = $this->lot->passeports()->where('passeports.id', $this->p1->id)
            ->withPivot('statut_reception', 'notes')->first();

        $this->assertEquals('anomalie', $pivot->pivot->statut_reception);
        $this->assertEquals('Passeport abîmé', $pivot->pivot->notes);
    }

    public function test_confirmation_avec_option_disponible_retrait_envoie_email(): void
    {
        $this->actingAsRole('agent_reception', $this->ambassade);

        $this->postJson('/api/reception/lot/' . urlencode($this->validToken), [
            'confirmations' => [
                ['passeport_id' => $this->p1->id, 'statut' => 'confirme'],
                ['passeport_id' => $this->p2->id, 'statut' => 'confirme'],
            ],
            'aller_disponible_retrait' => true,
        ])->assertOk();

        // Emails envoyés aux citoyens (2 passeports avec email)
        Mail::assertSentCount(2);
    }

    public function test_confirmation_passeport_hors_lot_rejete(): void
    {
        $this->actingAsRole('agent_reception', $this->ambassade);

        $autrePasseport = $this->makePasseport($this->ambassade);

        $this->postJson('/api/reception/lot/' . urlencode($this->validToken), [
            'confirmations' => [
                ['passeport_id' => $autrePasseport->id, 'statut' => 'confirme'],
            ],
        ])->assertUnprocessable()
            ->assertJsonFragment(['error_code' => 'PASSEPORT_NOT_IN_LOT']);
    }

    public function test_double_confirmation_lot_deja_recu_rejete(): void
    {
        $this->actingAsRole('agent_reception', $this->ambassade);
        $this->lot->update(['statut' => 'recu']);

        $this->postJson('/api/reception/lot/' . urlencode($this->validToken), [
            'confirmations' => [
                ['passeport_id' => $this->p1->id, 'statut' => 'confirme'],
            ],
        ])->assertUnprocessable();
    }

    // ── Passeport manquant ────────────────────────────────────────────────────

    public function test_signalement_passeport_manquant(): void
    {
        $this->actingAsRole('agent_reception', $this->ambassade);

        $this->postJson("/api/reception/lot-detail/{$this->lot->id}/passeport/{$this->p1->id}/manquant", [
            'note' => 'Absent du colis à l\'ouverture',
        ])->assertStatus(201);

        $pivot = $this->lot->passeports()->where('passeports.id', $this->p1->id)
            ->withPivot('statut_reception')->first();
        $this->assertEquals('manquant', $pivot->pivot->statut_reception);
    }

    // ── Commentaire ambassade ─────────────────────────────────────────────────

    public function test_commentaire_ambassade_enregistre(): void
    {
        $this->actingAsRole('agent_reception', $this->ambassade);

        $this->postJson("/api/reception/lot/{$this->lot->id}/commentaire", [
            'commentaire' => 'Lot reçu en bon état, légère humidité sur le carton.',
        ])->assertOk();

        $this->assertDatabaseHas('lots', [
            'id'                    => $this->lot->id,
            'commentaire_ambassade' => 'Lot reçu en bon état, légère humidité sur le carton.',
        ]);
    }
}
