<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Dashboard\DashboardCalculator;
use App\Http\Controllers\Controller;
use App\Models\Investment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(
        private readonly DashboardCalculator $calculator,
    ) {}

    public function json(Request $request): JsonResponse
    {
        $user = $request->user();
        $investments = Investment::forUser($user->id)
            ->with(['assetType', 'latestPrice.provider'])
            ->get();

        return response()->json([
            'data' => [
                'exported_at' => now()->toIso8601String(),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'base_currency' => $user->base_currency,
                ],
                'summary' => $this->calculator->summary($user->id, $user->base_currency),
                'investments' => $investments->map(fn (Investment $i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'asset_type' => $i->assetType->code,
                    'isin' => $i->isin,
                    'symbol' => $i->symbol,
                    'quantity' => (float) $i->quantity,
                    'unit' => $i->unit,
                    'purchase_price' => $i->purchase_price !== null ? (float) $i->purchase_price : null,
                    'purchase_date' => $i->purchase_date,
                    'manual_value' => $i->manual_value !== null ? (float) $i->manual_value : null,
                    'currency' => $i->currency,
                    'status' => $i->status,
                    'notes' => $i->notes,
                    'created_at' => $i->created_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $user = $request->user();
        $investments = Investment::forUser($user->id)
            ->with(['assetType'])
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="patrimoine_' . now()->format('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($investments) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel
            fputcsv($out, ['Name', 'Type', 'ISIN', 'Symbol', 'Quantity', 'Unit', 'Purchase price', 'Currency', 'Manual value', 'Status', 'Purchase date']);

            $sanitize = fn ($val) => is_string($val) && preg_match('/^[=+\-@]/', $val)
                ? "'" . $val
                : ($val ?? '');

            foreach ($investments as $i) {
                fputcsv($out, [
                    $sanitize($i->name),
                    $sanitize($i->assetType->label),
                    $sanitize($i->isin ?? ''),
                    $sanitize($i->symbol ?? ''),
                    $i->quantity,
                    $i->unit,
                    $i->purchase_price ?? '',
                    $i->currency,
                    $i->manual_value ?? '',
                    $i->status,
                    $i->purchase_date ?? '',
                ]);
            }
            fclose($out);
        }, 200, $headers);
    }
}
