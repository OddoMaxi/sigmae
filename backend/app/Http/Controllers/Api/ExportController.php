<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExportService;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function __construct(private ExportService $exportService) {}

    public function passeports(Request $request)
    {
        $request->validate(['format' => 'sometimes|in:xlsx,pdf']);
        return $this->exportService->exportPasseports($request->all(), $request->format ?? 'xlsx');
    }

    public function lots(Request $request)
    {
        $request->validate(['format' => 'sometimes|in:xlsx,pdf']);
        return $this->exportService->exportLots($request->all(), $request->format ?? 'xlsx');
    }

    public function anomalies(Request $request)
    {
        $request->validate(['format' => 'sometimes|in:xlsx,pdf']);
        return $this->exportService->exportAnomalies($request->all(), $request->format ?? 'xlsx');
    }
}
