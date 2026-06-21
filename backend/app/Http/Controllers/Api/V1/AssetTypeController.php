<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AssetTypeResource;
use App\Models\AssetType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AssetTypeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AssetTypeResource::collection(
            AssetType::orderBy('id')->get()
        );
    }
}
