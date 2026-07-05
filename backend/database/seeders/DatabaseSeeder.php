<?php

namespace Database\Seeders;

use App\Models\Ambassade;
use App\Models\Pays;
use App\Models\Transporteur;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Pays ──────────────────────────────────────────────────────────────
        $paysData = [
            ['code_iso' => 'FR', 'nom' => 'France',       'capitale' => 'Paris'],
            ['code_iso' => 'US', 'nom' => 'États-Unis',   'capitale' => 'Washington D.C.'],
            ['code_iso' => 'BE', 'nom' => 'Belgique',     'capitale' => 'Bruxelles'],
            ['code_iso' => 'MA', 'nom' => 'Maroc',        'capitale' => 'Rabat'],
            ['code_iso' => 'SN', 'nom' => 'Sénégal',      'capitale' => 'Dakar'],
            ['code_iso' => 'DE', 'nom' => 'Allemagne',    'capitale' => 'Berlin'],
            ['code_iso' => 'CN', 'nom' => 'Chine',        'capitale' => 'Pékin'],
            ['code_iso' => 'GN', 'nom' => 'Guinée',       'capitale' => 'Conakry'],
        ];

        $paysMap = [];
        foreach ($paysData as $p) {
            $pays = Pays::firstOrCreate(['code_iso' => $p['code_iso']], $p);
            $paysMap[$p['code_iso']] = $pays->id;
        }

        // ── Ambassades ────────────────────────────────────────────────────────
        $ambassades = [
            ['code' => 'FR-PAR', 'nom' => 'Ambassade de Guinée', 'pays' => 'France',     'pays_id' => $paysMap['FR'], 'ville' => 'Paris',           'email_contact' => 'ambassade@guinee-paris.fr',        'responsable' => 'M. Amadou Diallo'],
            ['code' => 'US-WAS', 'nom' => 'Ambassade de Guinée', 'pays' => 'États-Unis', 'pays_id' => $paysMap['US'], 'ville' => 'Washington D.C.', 'email_contact' => 'info@guineeusa.org',               'responsable' => 'Mme Fatoumata Bah'],
            ['code' => 'BE-BRU', 'nom' => 'Ambassade de Guinée', 'pays' => 'Belgique',   'pays_id' => $paysMap['BE'], 'ville' => 'Bruxelles',       'email_contact' => 'contact@guinee-belgique.be',       'responsable' => 'M. Ibrahima Sow'],
            ['code' => 'MA-RBA', 'nom' => 'Ambassade de Guinée', 'pays' => 'Maroc',      'pays_id' => $paysMap['MA'], 'ville' => 'Rabat',           'email_contact' => 'guinee-rabat@diplomatie.gov.gn',   'responsable' => 'Mme Mariama Camara'],
            ['code' => 'SN-DKR', 'nom' => 'Ambassade de Guinée', 'pays' => 'Sénégal',    'pays_id' => $paysMap['SN'], 'ville' => 'Dakar',           'email_contact' => 'guinee-dakar@diplomatie.gov.gn',   'responsable' => 'M. Ousmane Barry'],
            ['code' => 'DE-BER', 'nom' => 'Ambassade de Guinée', 'pays' => 'Allemagne',  'pays_id' => $paysMap['DE'], 'ville' => 'Berlin',          'email_contact' => 'guinee-berlin@diplomatie.gov.gn',  'responsable' => 'M. Alpha Condé Jr'],
            ['code' => 'CN-BEJ', 'nom' => 'Ambassade de Guinée', 'pays' => 'Chine',      'pays_id' => $paysMap['CN'], 'ville' => 'Pékin',           'email_contact' => 'guinee-beijing@diplomatie.gov.gn', 'responsable' => 'Mme Kadiatou Touré'],
        ];

        foreach ($ambassades as $a) {
            Ambassade::updateOrCreate(['code' => $a['code']], $a);
        }

        $transporteurs = [
            ['nom' => 'DHL Express',                'contact' => 'Service courrier diplomatique', 'telephone' => '+224 622 000 001', 'email' => 'diplomatique@dhl.com'],
            ['nom' => 'FedEx Diplomatic',           'contact' => 'Relations gouvernementales',    'telephone' => '+224 622 000 002', 'email' => 'gov@fedex.com'],
            ['nom' => 'Courrier Diplomatique MAE',  'contact' => 'Valise diplomatique officielle','telephone' => '+224 622 000 003', 'email' => 'valise@mae.gov.gn'],
            ['nom' => 'TNT Government Services',    'contact' => 'Services institutionnels',      'telephone' => '+224 622 000 004', 'email' => 'gov@tnt.com'],
        ];

        foreach ($transporteurs as $t) {
            Transporteur::firstOrCreate(['nom' => $t['nom']], $t);
        }

        $paris = Ambassade::where('code', 'FR-PAR')->first();
        $dakar = Ambassade::where('code', 'SN-DKR')->first();

        User::firstOrCreate(['email' => 'admin@mae.gov.gn'], [
            'name' => 'Administrateur SGP', 'password' => Hash::make('Admin@2025!'),
            'role' => 'admin_mae', 'is_active' => true,
        ]);
        User::firstOrCreate(['email' => 'gestionnaire@mae.gov.gn'], [
            'name' => 'Mamadou Kouyaté', 'password' => Hash::make('Gest@2025!'),
            'role' => 'gestionnaire', 'is_active' => true,
        ]);
        User::firstOrCreate(['email' => 'superviseur@mae.gov.gn'], [
            'name' => 'Aminata Sylla', 'password' => Hash::make('Super@2025!'),
            'role' => 'superviseur', 'is_active' => true,
        ]);
        User::firstOrCreate(['email' => 'agent.paris@diplomatie.gov.gn'], [
            'name' => 'Sékou Diallo', 'password' => Hash::make('Agent@2025!'),
            'role' => 'agent_ambassade', 'ambassade_id' => $paris?->id, 'is_active' => true,
        ]);
        User::firstOrCreate(['email' => 'agent.dakar@diplomatie.gov.gn'], [
            'name' => 'Aïssatou Bah', 'password' => Hash::make('Agent@2025!'),
            'role' => 'agent_ambassade', 'ambassade_id' => $dakar?->id, 'is_active' => true,
        ]);

        $this->command->info('✓ Données insérées. Admin : admin@mae.gov.gn / Admin@2025!');

        $this->call(RolesPermissionsSeeder::class);
        $this->call(AuthSeeder::class);
    }
}
