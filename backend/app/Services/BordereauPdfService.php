<?php

namespace App\Services;

use App\Models\Lot;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class BordereauPdfService
{
    public function __construct(private QrCodeService $qrService) {}

    public function generate(Lot $lot): string
    {
        $lot->load(['ambassade', 'transporteur', 'passeports', 'createdBy']);

        $qrDataUri = 'data:image/png;base64,' . base64_encode(
            $this->qrService->generateQrPng($lot->qr_token)
        );

        $pdf = Pdf::loadView('pdf.bordereau', [
            'lot'      => $lot,
            'qrDataUri'=> $qrDataUri,
        ])->setPaper('A4');

        $path = "bordereaux/lot_{$lot->id}_{$lot->reference}.pdf";
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }
}
