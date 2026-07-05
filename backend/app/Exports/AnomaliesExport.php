<?php

namespace App\Exports;

use App\Models\Anomalie;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AnomaliesExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private array $filters = []) {}

    public function query()
    {
        return Anomalie::with(['passeport', 'lot.ambassade', 'signalePar'])
            ->when($this->filters['statut'] ?? null, fn($q) => $q->where('statut', $this->filters['statut']))
            ->orderByDesc('created_at');
    }

    public function headings(): array
    {
        return [
            'Type', 'N° Passeport', 'Lot', 'Ambassade',
            'Description', 'Statut', 'Signalé par', 'Date signal', 'Résolu le',
        ];
    }

    public function map($anomalie): array
    {
        return [
            $anomalie->type,
            $anomalie->passeport?->numero,
            $anomalie->lot?->reference,
            $anomalie->lot?->ambassade?->nom,
            $anomalie->description,
            $anomalie->statut,
            $anomalie->signalePar?->name,
            $anomalie->created_at?->format('d/m/Y H:i'),
            $anomalie->resolu_at?->format('d/m/Y H:i'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
