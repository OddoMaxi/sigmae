<?php

namespace App\Imports;

use App\Models\Ambassade;
use App\Models\Passeport;
use App\Models\Pays;
use App\Models\TrackingEvent;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Concerns\WithChunkReading;

/*
 * Format CSV/Excel attendu (en-têtes en ligne 1) :
 *
 * numero_passeport | reference_demande | nom | prenom | date_naissance
 * email | telephone | pays_destination | ambassade_destination
 * date_impression | date_reception_mae | statut
 *
 * - pays_destination     : code ISO 2 lettres (ex: FR, SN, BE)
 * - ambassade_destination: code ambassade (ex: FR-PAR, SN-DKR)
 * - statut               : imprime | recu_mae | en_stock (défaut: imprime)
 *
 * Les doublons (numero ou reference_demande) sont ignorés et comptés.
 */
class PasseportsImport implements ToCollection, WithHeadingRow, WithValidation, SkipsOnFailure, WithLimit, WithChunkReading
{
    use SkipsFailures;

    private int $rowCount    = 0;
    private int $skippedCount = 0;

    // Cache pour éviter N+1 sur pays et ambassades
    private ?Collection $paysCache     = null;
    private ?Collection $ambassadeCache = null;

    public function limit(): int   { return 1000; }
    public function chunkSize(): int { return 100; }

    public function collection(Collection $rows): void
    {
        $this->paysCache     ??= Pays::all()->keyBy('code_iso');
        $this->ambassadeCache ??= Ambassade::all()->keyBy('code');

        foreach ($rows as $row) {
            $numero = trim($row['numero_passeport'] ?? '');
            $ref    = trim($row['reference_demande'] ?? '');

            if (empty($numero)) {
                $this->skippedCount++;
                continue;
            }

            // Anti-doublon : numero ou reference_demande déjà présents
            $existsByNumero = Passeport::where('numero', $numero)->exists();
            $existsByRef    = $ref && Passeport::where('reference_demande', $ref)->exists();

            if ($existsByNumero || $existsByRef) {
                $this->skippedCount++;
                continue;
            }

            // Résolution des FKs depuis les codes
            $paysId     = null;
            $ambassadeId = null;

            if (! empty($row['pays_destination'])) {
                $paysId = $this->paysCache->get(strtoupper(trim($row['pays_destination'])))?->id;
            }

            if (! empty($row['ambassade_destination'])) {
                $ambassadeId = $this->ambassadeCache->get(strtoupper(trim($row['ambassade_destination'])))?->id;
            }

            $statut = in_array($row['statut'] ?? '', ['imprime', 'recu_mae', 'en_stock'])
                ? $row['statut']
                : Passeport::STATUT_IMPRIME;

            $passeport = Passeport::create([
                'numero'                   => $numero,
                'reference_demande'        => $ref ?: null,
                'nom_titulaire'            => trim($row['nom'] ?? ''),
                'prenom_titulaire'         => trim($row['prenom'] ?? ''),
                'date_naissance'           => $this->parseDate($row['date_naissance'] ?? null),
                'email_citoyen'            => trim($row['email'] ?? '') ?: null,
                'telephone'                => trim($row['telephone'] ?? '') ?: null,
                'pays_destination_id'      => $paysId,
                'ambassade_destination_id' => $ambassadeId,
                'date_impression'          => $this->parseDate($row['date_impression'] ?? null),
                'date_reception_mae'       => $this->parseDate($row['date_reception_mae'] ?? null),
                'statut'                   => $statut,
                'received_at'              => in_array($statut, ['recu_mae', 'en_stock']) ? now() : null,
            ]);

            TrackingEvent::record($passeport, 'created', 'Importé depuis fichier.', [
                'import_statut' => $statut,
            ]);

            $this->rowCount++;
        }
    }

    public function rules(): array
    {
        return [
            'numero_passeport' => 'required|string|max:50',
            'nom'              => 'required|string|max:100',
            'prenom'           => 'required|string|max:100',
            'email'            => 'nullable|email',
        ];
    }

    public function getRowCount(): int    { return $this->rowCount; }
    public function getSkippedCount(): int { return $this->skippedCount; }

    private function parseDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
