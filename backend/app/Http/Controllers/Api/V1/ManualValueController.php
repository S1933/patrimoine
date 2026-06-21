<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ManualValueRequest;
use App\Http\Resources\Api\V1\InvestmentResource;
use App\Models\Investment;
use Illuminate\Http\JsonResponse;

class ManualValueController extends Controller
{
    public function __invoke(ManualValueRequest $request, Investment $investment): JsonResponse
    {
        $this->authorize('update', $investment);

        $investment->update([
            'manual_value' => $request->input('value'),
            'manual_value_updated_at' => now(),
            'currency' => $request->input('currency', $investment->currency),
        ]);

        return (new InvestmentResource($investment->load(['assetType', 'latestPrice.provider'])))
            ->response()
            ->setStatusCode(200);
    }
}
