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
        $user = $request->user();

        return response()->json([
            'data' => $this->calculator->allocation($user->id, $user->base_currency),
        ]);
    }

    public function breakdown(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $this->calculator->breakdown($user->id, $user->base_currency),
        ]);
    }

    public function countryAllocation(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $this->calculator->countryAllocation($user->id, $user->base_currency),
        ]);
    }

    public function geography(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $this->calculator->geographyAllocation($user->id, $user->base_currency),
        ]);
    }

    public function sectorAllocation(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $this->calculator->sectorAllocation($user->id, $user->base_currency),
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
