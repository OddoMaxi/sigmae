<?php

namespace App\Services;

use App\Models\Passeport;
use App\Models\Lot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    public function addPasseportsToLot(Lot $lot, array $passeportIds): array
    {
        if (! $lot->isBrouillon()) {
            throw ValidationException::withMessages([
                'lot' => 'Seul un lot en brouillon peut être modifié.',
            ]);
        }

        // 1. Passeports avec un statut autre que EN_STOCK
        $statutsInvalides = Passeport::whereIn('id', $passeportIds)
            ->where('statut', '!=', 'en_stock')
            ->select('id', 'numero', 'statut')
            ->get();

        if ($statutsInvalides->isNotEmpty()) {
            $details = $statutsInvalides
                ->map(fn ($p) => "{$p->numero} ({$p->statut})")
                ->implode(', ');

            throw ValidationException::withMessages([
                'passeports' => "Ces passeports ne sont pas disponibles (statut non EN_STOCK) : {$details}",
            ]);
        }

        // 2. Passeports déjà présents dans un autre lot brouillon ou validé
        $lotsActifsIds = Lot::whereIn('statut', [Lot::STATUT_BROUILLON, Lot::STATUT_VALIDE])
            ->where('id', '!=', $lot->id)
            ->pluck('id');

        if ($lotsActifsIds->isNotEmpty()) {
            $dejaEnLot = DB::table('lot_passeports')
                ->join('passeports', 'passeports.id', '=', 'lot_passeports.passeport_id')
                ->join('lots', 'lots.id', '=', 'lot_passeports.lot_id')
                ->whereIn('lot_passeports.passeport_id', $passeportIds)
                ->whereIn('lot_passeports.lot_id', $lotsActifsIds)
                ->select('passeports.numero', 'lots.reference as lot_reference')
                ->get();

            if ($dejaEnLot->isNotEmpty()) {
                $details = $dejaEnLot
                    ->map(fn ($r) => "{$r->numero} (lot {$r->lot_reference})")
                    ->implode(', ');

                throw ValidationException::withMessages([
                    'passeports' => "Ces passeports appartiennent déjà à un lot actif : {$details}",
                ]);
            }
        }

        DB::transaction(function () use ($lot, $passeportIds) {
            foreach ($passeportIds as $id) {
                $lot->passeports()->syncWithoutDetaching([$id => ['statut_reception' => 'en_attente']]);
            }
            Passeport::whereIn('id', $passeportIds)->update(['statut' => 'en_lot', 'lot_id' => $lot->id]);
        });

        return ['added' => count($passeportIds)];
    }

    public function removePasseportFromLot(Lot $lot, Passeport $passeport): void
    {
        if (! $lot->isBrouillon()) {
            throw ValidationException::withMessages([
                'lot' => 'Impossible de modifier un lot non brouillon.',
            ]);
        }

        DB::transaction(function () use ($lot, $passeport) {
            $lot->passeports()->detach($passeport->id);
            $passeport->update(['statut' => 'en_stock', 'lot_id' => null]);
        });
    }

    public function getStockStats(): array
    {
        $counts = Passeport::selectRaw('statut, COUNT(*) as total')
            ->groupBy('statut')
            ->pluck('total', 'statut');

        return [
            'enrolee'            => (int) ($counts['enrolee']            ?? 0),
            'imprime'            => (int) ($counts['imprime']            ?? 0),
            'recu_mae'           => (int) ($counts['recu_mae']           ?? 0),
            'en_stock'           => (int) ($counts['en_stock']           ?? 0),
            'en_lot'             => (int) ($counts['en_lot']             ?? 0),
            'expedie'            => (int) ($counts['expedie']            ?? 0),
            'en_transit'         => (int) ($counts['en_transit']         ?? 0),
            'recu_ambassade'     => (int) ($counts['recu_ambassade']     ?? 0),
            'disponible_retrait' => (int) ($counts['disponible_retrait'] ?? 0),
            'remis_citoyen'      => (int) ($counts['remis_citoyen']      ?? 0) + (int) ($counts['livre'] ?? 0),
            'anomalie'           => (int) ($counts['anomalie']           ?? 0),
            'total'              => Passeport::count(),
        ];
    }
}
