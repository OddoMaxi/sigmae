<?php

namespace App\Exports;

use App\Models\Passeport;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PasseportsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle
{
    private bool $stockMode;

    public function __construct(private array $filters = [])
    {
        $this->stockMode = ($filters['mode'] ?? null) === 'stock';
    }

    public function title(): string
    {
        return $this->stockMode ? 'Stock Central MAE' : 'Passeports';
    }

    public function query()
    {
        $query = Passeport::with([
                'paysDestination:id,nom,code_iso',
                'ambassadeDestination:id,nom,code,ville',
                'lot:id,reference',
            ]);

        if ($this->stockMode) {
            $statuts = $this->filters['statuts'] ?? ['en_stock', 'en_lot', 'expedie', 'anomalie'];
            $query->whereIn('statut', $statuts);
        }

        $query
            ->when($this->filters['statut']       ?? null, fn ($q) => $q->where('statut', $this->filters['statut']))
            ->when($this->filters['ambassade_id'] ?? null, fn ($q) => $q->where('ambassade_destination_id', $this->filters['ambassade_id']))
            ->when($this->filters['pays_id']      ?? null, fn ($q) => $q->where('pays_destination_id', $this->filters['pays_id']))
            ->when($this->filters['date_from']    ?? null, fn ($q) => $q->whereDate('date_reception_mae', '>=', $this->filters['date_from']))
            ->when($this->filters['date_to']      ?? null, fn ($q) => $q->whereDate('date_reception_mae', '<=', $this->filters['date_to']))
            ->when($this->filters['search']       ?? null, fn ($q) => $q->search($this->filters['search']));

        return $query->orderByDesc('created_at');
    }

    public function headings(): array
    {
        $base = [
            'N° Passeport',
            'Réf. Demande',
            'Nom',
            'Prénom',
            'Date naissance',
            'Email citoyen',
            'Téléphone',
            'Statut',
        ];

        $stockCols = [
            'Pays destination',
            'Ambassade destination',
            'Ville ambassade',
            'Date réception MAE',
            'Lot',
            'Date création',
        ];

        $legacyCols = [
            'Lot',
            'Ambassade (lot)',
            'Date réception MAE',
            'Date livraison',
        ];

        return $this->stockMode
            ? array_merge($base, $stockCols)
            : array_merge($base, $legacyCols);
    }

    public function map($passeport): array
    {
        $base = [
            $passeport->numero,
            $passeport->reference_demande,
            $passeport->nom_titulaire,
            $passeport->prenom_titulaire,
            $passeport->date_naissance?->format('d/m/Y'),
            $passeport->email_citoyen,
            $passeport->telephone,
            $passeport->statut_label ?? $passeport->statut,
        ];

        if ($this->stockMode) {
            return array_merge($base, [
                $passeport->paysDestination?->nom,
                $passeport->ambassadeDestination?->nom,
                $passeport->ambassadeDestination?->ville,
                $passeport->date_reception_mae?->format('d/m/Y'),
                $passeport->lot?->reference,
                $passeport->created_at?->format('d/m/Y H:i'),
            ]);
        }

        return array_merge($base, [
            $passeport->lot?->reference,
            $passeport->lot?->ambassade?->nom,
            $passeport->received_at?->format('d/m/Y H:i'),
            $passeport->delivered_at?->format('d/m/Y H:i'),
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
