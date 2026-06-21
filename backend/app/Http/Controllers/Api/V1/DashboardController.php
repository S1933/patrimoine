<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Dashboard\DashboardCalculator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardCalculator $calculator,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $this->calculator->summary($user->id, $user->base_currency),
        ]);
    }

    public function allocation(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->calculator->allocation($request->user()->id),
        ]);
    }

    public function breakdown(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->calculator->breakdown($request->user()->id),
        ]);
    }

    public function countryAllocation(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->calculator->countryAllocation($request->user()->id),
        ]);
    }

    public function geography(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->calculator->geographyAllocation($request->user()->id),
        ]);
    }

    public function sectorAllocation(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->calculator->sectorAllocation($request->user()->id),
        ]);
    }

    public function performance(Request $request): JsonResponse
    {
        $range = $request->string('range')->toString() ?: 'all';
        $allowed = ['1m', '3m', '6m', '1y', 'all'];
        if (! in_array($range, $allowed, true)) {
            $range = 'all';
        }

        return response()->json([
            'data' => $this->calculator->performance($request->user()->id, $range),
        ]);
    }
}
