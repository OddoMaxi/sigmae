<?php

namespace App\Services;

use App\Models\Passeport;
use App\Models\Lot;
use App\Models\Anomalie;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PasseportsExport;
use App\Exports\LotsExport;
use App\Exports\AnomaliesExport;

class ExportService
{
    public function exportPasseports(array $filters = [], string $format = 'xlsx')
    {
        $export   = new PasseportsExport($filters);
        $basename = ($filters['mode'] ?? null) === 'stock' ? 'stock_central' : 'passeports';

        return $format === 'pdf'
            ? $this->toPdf($export, $basename)
            : Excel::download($export, $basename . '_' . now()->format('Ymd_His') . '.xlsx');
    }

    public function exportLots(array $filters = [], string $format = 'xlsx')
    {
        $export = new LotsExport($filters);

        return $format === 'pdf'
            ? $this->toPdf($export, 'lots')
            : Excel::download($export, 'lots_' . now()->format('Ymd_His') . '.xlsx');
    }

    public function exportAnomalies(array $filters = [], string $format = 'xlsx')
    {
        $export = new AnomaliesExport($filters);

        return $format === 'pdf'
            ? $this->toPdf($export, 'anomalies')
            : Excel::download($export, 'anomalies_' . now()->format('Ymd_His') . '.xlsx');
    }

    private function toPdf($export, string $name)
    {
        return Excel::download($export, $name . '_' . now()->format('Ymd_His') . '.pdf', \Maatwebsite\Excel\Excel::DOMPDF);
    }
}
