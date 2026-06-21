<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Strategy\InvestmentStrategyService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateInvestmentStrategyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvestmentStrategyController extends Controller
{
    public function __construct(
        private readonly InvestmentStrategyService $service,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->get($request->user()->id),
        ]);
    }

    public function update(UpdateInvestmentStrategyRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->replace(
                $request->user()->id,
                $request->validated('allocations'),
            ),
        ]);
    }
}
