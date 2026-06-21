<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Pricing\FetchInvestmentPrice;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreInvestmentRequest;
use App\Http\Requests\Api\V1\UpdateInvestmentRequest;
use App\Http\Resources\Api\V1\InvestmentResource;
use App\Models\Investment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class InvestmentController extends Controller
{
    public function __construct(
        private readonly FetchInvestmentPrice $fetchPrice,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = Investment::query()
            ->forUser($user->id)
            ->with(['assetType', 'latestPrice.provider']);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($typeId = $request->integer('type')) {
            $query->where('asset_type_id', $typeId);
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('isin', 'ilike', "%{$search}%")
                  ->orWhere('symbol', 'ilike', "%{$search}%");
            });
        }

        $sort = $request->string('sort')->toString() ?: 'created_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['name', 'created_at', 'quantity', 'status'];
        if (in_array($sort, $allowedSorts, true)) {
            $query->orderBy($sort, $direction);
        }

        $perPage = min($request->integer('per_page', 25), 100);

        return InvestmentResource::collection($query->paginate($perPage));
    }

    public function store(StoreInvestmentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $investment = Investment::create($data);
        $investment->load('assetType');
        $this->syncPriceIfNeeded($investment);
        $investment->load(['assetType', 'latestPrice.provider']);

        return (new InvestmentResource($investment))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Investment $investment): InvestmentResource
    {
        $this->authorize('view', $investment);
        $investment->load(['assetType', 'latestPrice.provider', 'prices' => fn ($q) => $q->limit(30), 'prices.provider']);

        return new InvestmentResource($investment);
    }

    public function update(UpdateInvestmentRequest $request, Investment $investment): InvestmentResource
    {
        $this->authorize('update', $investment);
        $investment->update($request->validated());
        $investment->load('assetType');
        $this->syncPriceIfNeeded($investment);
        $investment->load(['assetType', 'latestPrice.provider']);

        return new InvestmentResource($investment);
    }

    public function destroy(Request $request, Investment $investment): Response
    {
        $this->authorize('delete', $investment);
        $investment->delete();

        return response()->noContent();
    }

    private function syncPriceIfNeeded(Investment $investment): void
    {
        $needsMarketPrice = in_array($investment->assetType->code, ['stock', 'etf', 'etn_crypto'], true)
            && $investment->manual_value === null
            && (filled($investment->isin) || filled($investment->symbol));

        if (! $needsMarketPrice) {
            return;
        }

        try {
            $this->fetchPrice->execute($investment);
            $investment->refresh();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
