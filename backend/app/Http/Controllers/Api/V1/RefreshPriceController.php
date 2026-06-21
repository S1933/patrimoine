<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Pricing\FetchInvestmentPrice;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InvestmentResource;
use App\Models\Investment;
use Illuminate\Http\JsonResponse;

class RefreshPriceController extends Controller
{
    public function __construct(
        private readonly FetchInvestmentPrice $fetchPrice,
    ) {}

    public function __invoke(Investment $investment): JsonResponse
    {
        $this->authorize('update', $investment);

        $result = $this->fetchPrice->execute($investment);

        $investment->refresh();
        $investment->load(['assetType', 'latestPrice.provider']);

        return (new InvestmentResource($investment))
            ->additional([
                'meta' => [
                    'pricing_status' => $result->status,
                    'pricing_source' => $result->source,
                    'pricing_error' => $result->errorMessage,
                    'pricing_fetched_at' => $result->fetchedAt->toIso8601String(),
                ],
            ])
            ->response()
            ->setStatusCode($result->isError() ? 502 : 200);
    }
}
