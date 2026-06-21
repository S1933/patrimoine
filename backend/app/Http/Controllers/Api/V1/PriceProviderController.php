<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PriceProviderResource;
use App\Models\PriceProvider;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PriceProviderController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PriceProviderResource::collection(
            PriceProvider::where('is_active', true)->orderBy('priority')->get()
        );
    }
}
