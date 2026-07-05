<?php

namespace App\Services;

use App\Models\EmailLog;
use App\Models\Passeport;
use Illuminate\Support\Facades\Mail;
use App\Mail\PasseportDisponible;

class EmailNotificationService
{
    public function sendPasseportDisponible(Passeport $passeport): bool
    {
        if (! $passeport->email_citoyen) {
            return false;
        }

        try {
            Mail::to($passeport->email_citoyen)
                ->send(new PasseportDisponible($passeport));

            $passeport->update(['email_sent_at' => now()]);

            EmailLog::create([
                'passeport_id' => $passeport->id,
                'recipient'    => $passeport->email_citoyen,
                'sujet'        => 'Votre passeport est disponible',
                'statut'       => 'envoye',
                'sent_at'      => now(),
            ]);

            return true;
        } catch (\Throwable $e) {
            EmailLog::create([
                'passeport_id' => $passeport->id,
                'recipient'    => $passeport->email_citoyen,
                'sujet'        => 'Votre passeport est disponible',
                'statut'       => 'echec',
                'error_msg'    => $e->getMessage(),
                'sent_at'      => now(),
            ]);

            return false;
        }
    }
}
