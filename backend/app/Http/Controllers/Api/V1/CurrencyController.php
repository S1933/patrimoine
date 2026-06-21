<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CurrencyController extends Controller
{
    private const CURRENCIES = [
        ['code' => 'EUR', 'label' => 'Euro', 'symbol' => '€'],
        ['code' => 'USD', 'label' => 'Dollar américain', 'symbol' => '$'],
        ['code' => 'GBP', 'label' => 'Livre sterling', 'symbol' => '£'],
        ['code' => 'CHF', 'label' => 'Franc suisse', 'symbol' => 'CHF'],
        ['code' => 'JPY', 'label' => 'Yen japonais', 'symbol' => '¥'],
        ['code' => 'CAD', 'label' => 'Dollar canadien', 'symbol' => '$'],
        ['code' => 'AUD', 'label' => 'Dollar australien', 'symbol' => '$'],
        ['code' => 'CNY', 'label' => 'Yuan chinois', 'symbol' => '¥'],
    ];

    public function index(): JsonResponse
    {
        return response()->json(['data' => self::CURRENCIES]);
    }
}
