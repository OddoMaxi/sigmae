<?php

namespace Tests\Feature;

use App\Models\Anomalie;
use App\Models\Lot;
use App\Models\Passeport;

class AnomalieTest extends SgpTestCase
{
    // ── Déclaration ───────────────────────────────────────────────────────────

    public function test_agent_reception_peut_declarer_anomalie(): void
    {
        $this->actingAsRole('agent_reception', $this->ambassade);

        $admin = $this->makeAdminCentral();
        $lot   = $this->makeLot($admin, $this->ambassade, ['statut' => 'expedie']);
        $p     = $this->makePasseport($this->ambassade);

        $this->postJson('/api/anomalies', [
            'type'        => 'passeport_manquant',
            'lot_id'      => $lot->id,
            'passeport_id'=> $p->id,
            'description' => 'Passeport absent du lot à réception.',
        ])->assertCreated()
            ->assertJsonFragment(['type' => 'passeport_manquant']);
    }

    public function test_auditeur_ne_peut_pas_declarer_anomalie(): void
    {
        $this->actingAsRole('auditeur');

        $admin = $this->makeAdminCentral();
        $lot   = $this->makeLot($admin, $this->ambassade);

        $this->postJson('/api/anomalies', [
            'type'       => 'lot_endommage',
            'lot_id'     => $lot->id,
            'description'=> 'Carton abîmé.',
        ])->assertForbidden();
    }

    public function test_anomalie_sans_description_echoue(): void
    {
        $this->actingAsRole('agent_reception', $this->ambassade);

        $admin = $this->makeAdminCentral();
        $lot   = $this->makeLot($admin, $this->ambassade);

        $this->postJson('/api/anomalies', [
            'type'   => 'qr_invalide',
            'lot_id' => $lot->id,
        ])->assertUnprocessable();
    }

    public function test_anomalie_sans_type_echoue(): void
    {
        $this->actingAsRole('agent_reception', $this->ambassade);

        $admin = $this->makeAdminCentral();
        $lot   = $this->makeLot($admin, $this->ambassade);

        $this->postJson('/api/anomalies', [
            'lot_id'     => $lot->id,
            'description'=> 'Problème non catégorisé.',
        ])->assertUnprocessable();
    }

    // ── Résolution ────────────────────────────────────────────────────────────

    public function test_responsable_peut_resoudre_anomalie(): void
    {
        $this->actingAsRole('responsable_cellule');

        $admin = $this->makeAdminCentral();
        $lot   = $this->makeLot($admin, $this->ambassade);

        $anomalie = Anomalie::create([
            'type'       => 'passeport_manquant',
            'lot_id'     => $lot->id,
            'description'=> 'Passeport absent.',
            'statut'     => 'ouvert',
            'signale_by' => $admin->id,
        ]);

        $this->postJson("/api/anomalies/{$anomalie->id}/resoudre", [
            'note' => 'Passeport retrouvé dans l\'envoi suivant.',
        ])->assertOk()
            ->assertJsonFragment(['statut' => 'resolu']);

        $this->assertDatabaseHas('anomalies', [
            'id'     => $anomalie->id,
            'statut' => 'resolu',
        ]);
    }

    public function test_agent_reception_ne_peut_pas_resoudre_anomalie(): void
    {
        $admin    = $this->makeAdminCentral();
        $lot      = $this->makeLot($admin, $this->ambassade);
        $anomalie = Anomalie::create([
            'type'       => 'lot_endommage',
            'lot_id'     => $lot->id,
            'description'=> 'Carton mouillé.',
            'statut'     => 'ouvert',
            'signale_by' => $admin->id,
        ]);

        $this->actingAsRole('agent_reception', $this->ambassade);

        $this->postJson("/api/anomalies/{$anomalie->id}/resoudre")
            ->assertForbidden();
    }

    // ── Filtres ───────────────────────────────────────────────────────────────

    public function test_liste_anomalies_filtrables_par_statut(): void
    {
        $this->actingAsRole('admin_central');

        $admin = $this->makeAdminCentral();
        $lot   = $this->makeLot($admin, $this->ambassade);

        Anomalie::create(['type' => 'passeport_manquant', 'lot_id' => $lot->id, 'description' => 'Test', 'statut' => 'ouvert',  'signale_by' => $admin->id]);
        Anomalie::create(['type' => 'lot_endommage',      'lot_id' => $lot->id, 'description' => 'Test', 'statut' => 'resolu',  'signale_by' => $admin->id]);

        $ouvertes = $this->getJson('/api/anomalies?statut=ouvert')->json();
        foreach ($ouvertes['data'] as $a) {
            $this->assertEquals('ouvert', $a['statut']);
        }
    }
}
