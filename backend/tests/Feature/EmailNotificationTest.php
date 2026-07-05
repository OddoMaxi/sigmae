<?php

namespace Tests\Feature;

use App\Mail\PasseportDisponible;
use App\Models\EmailLog;
use App\Models\Passeport;
use App\Services\EmailNotificationService;
use Illuminate\Support\Facades\Mail;

class EmailNotificationTest extends SgpTestCase
{
    // ── Envoi email disponibilité passeport ───────────────────────────────────

    public function test_email_envoye_quand_passeport_disponible(): void
    {
        Mail::fake();

        $passeport = $this->makePasseport($this->ambassade, [
            'statut'         => 'disponible_retrait',
            'email_citoyen'  => 'citoyen@example.com',
            'disponible_at'  => now(),
        ]);

        $service = app(EmailNotificationService::class);
        $result  = $service->sendPasseportDisponible($passeport);

        $this->assertTrue($result);

        Mail::assertSent(PasseportDisponible::class, function ($mail) use ($passeport) {
            return $mail->hasTo($passeport->email_citoyen);
        });
    }

    public function test_email_log_cree_en_succes(): void
    {
        Mail::fake();

        $passeport = $this->makePasseport($this->ambassade, [
            'email_citoyen' => 'test@example.com',
            'disponible_at' => now(),
        ]);

        $service = app(EmailNotificationService::class);
        $service->sendPasseportDisponible($passeport);

        $this->assertDatabaseHas('email_logs', [
            'passeport_id' => $passeport->id,
            'recipient'    => 'test@example.com',
            'statut'       => 'envoye',
        ]);
    }

    public function test_email_log_echec_enregistre_erreur(): void
    {
        // Simuler l'échec d'envoi
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP connection failed'));

        $passeport = $this->makePasseport($this->ambassade, [
            'email_citoyen' => 'fail@example.com',
        ]);

        $service = app(EmailNotificationService::class);
        $result  = $service->sendPasseportDisponible($passeport);

        $this->assertFalse($result);

        $this->assertDatabaseHas('email_logs', [
            'passeport_id' => $passeport->id,
            'statut'       => 'echec',
        ]);
    }

    public function test_pas_email_si_pas_email_citoyen(): void
    {
        Mail::fake();

        $passeport = $this->makePasseport($this->ambassade, ['email_citoyen' => null]);

        $service = app(EmailNotificationService::class);
        $result  = $service->sendPasseportDisponible($passeport);

        $this->assertFalse($result);
        Mail::assertNothingSent();
    }

    // ── Email_sent_at mis à jour ──────────────────────────────────────────────

    public function test_email_sent_at_mis_a_jour_apres_envoi(): void
    {
        Mail::fake();

        $passeport = $this->makePasseport($this->ambassade, [
            'email_citoyen' => 'update@example.com',
            'disponible_at' => now(),
        ]);

        $this->assertNull($passeport->email_sent_at);

        $service = app(EmailNotificationService::class);
        $service->sendPasseportDisponible($passeport);

        $this->assertNotNull($passeport->fresh()->email_sent_at);
    }
}
