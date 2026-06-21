<?php

namespace App\Infrastructure\Pricing\OpenFigi;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class OpenFigiInstrumentResolver
{
    private const BASE_URL = 'https://api.openfigi.com/v3';
    private const CACHE_TTL = 86400;

    public function __construct(
        private readonly ?string $apiKey = null,
    ) {}

    public function resolveIsin(string $isin): ?array
    {
        $isin = strtoupper(trim($isin));
        if ($isin === '') {
            return null;
        }

        return Cache::remember("openfigi:isin:{$isin}", self::CACHE_TTL, function () use ($isin) {
            try {
                $request = Http::timeout(15)
                    ->retry(2, 1000)
                    ->acceptJson()
                    ->asJson();

                if ($this->apiKey) {
                    $request = $request->withHeaders(['X-OPENFIGI-APIKEY' => $this->apiKey]);
                }

                $response = $request->post(self::BASE_URL.'/mapping', [[
                    'idType' => 'ID_ISIN',
                    'idValue' => $isin,
                ]]);
            } catch (\Throwable $e) {
                Log::warning('OpenFIGI HTTP error', ['isin' => $isin, 'error' => $e->getMessage()]);

                return null;
            }

            if (! $response->successful()) {
                Log::warning('OpenFIGI non-2xx', ['isin' => $isin, 'status' => $response->status(), 'body' => $response->body()]);

                return null;
            }

            $payload = $response->json();
            $row = $payload[0]['data'][0] ?? null;

            if (! is_array($row)) {
                return null;
            }

            return [
                'isin' => $isin,
                'figi' => $row['figi'] ?? null,
                'ticker' => $row['ticker'] ?? null,
                'name' => $row['name'] ?? null,
                'exchCode' => $row['exchCode'] ?? null,
                'marketSector' => $row['marketSector'] ?? null,
                'securityType' => $row['securityType'] ?? null,
                'securityDescription' => $row['securityDescription'] ?? null,
            ];
        });
    }
}
