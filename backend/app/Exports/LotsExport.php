<?php

namespace App\Exports;

use App\Models\Lot;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LotsExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private array $filters = []) {}

    public function query()
    {
        return Lot::with(['ambassade', 'transporteur'])->withCount('passeports')
            ->when($this->filters['statut']       ?? null, fn($q) => $q->where('statut', $this->filters['statut']))
            ->when($this->filters['ambassade_id'] ?? null, fn($q) => $q->where('ambassade_id', $this->filters['ambassade_id']))
            ->when($this->filters['date_from']    ?? null, fn($q) => $q->whereDate('date_expedition', '>=', $this->filters['date_from']))
            ->when($this->filters['date_to']      ?? null, fn($q) => $q->whereDate('date_expedition', '<=', $this->filters['date_to']))
            ->orderByDesc('created_at');
    }

    public function headings(): array
    {
        return [
            'Référence', 'Ambassade', 'Pays', 'Transporteur',
            'Statut', 'Nb Passeports', 'Date expédition', 'Réception prévue', 'Réception effective',
        ];
    }

    public function map($lot): array
    {
        return [
            $lot->reference,
            $lot->ambassade?->nom,
            $lot->ambassade?->pays,
            $lot->transporteur?->nom,
            $lot->statut,
            $lot->passeports_count,
            $lot->date_expedition?->format('d/m/Y'),
            $lot->date_reception_prevue?->format('d/m/Y'),
            $lot->date_reception_effective?->format('d/m/Y H:i'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
