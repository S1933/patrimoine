<?php

namespace App\Domain\Pricing;

enum AssetTypeCode: string
{
    case Stock = 'stock';
    case Etf = 'etf';
    case EtnCrypto = 'etn_crypto';
    case Crypto = 'crypto';
    case Gold = 'gold';
    case RealEstate = 'real_estate';
    case Cash = 'cash';
    case LivretA = 'livret_a';
    case Ldds = 'ldds';
    case Other = 'other';

    public function isExternallyPriced(): bool
    {
        return match ($this) {
            self::Stock, self::Etf, self::EtnCrypto, self::Crypto, self::Gold => true,
            self::RealEstate, self::Cash, self::LivretA, self::Ldds, self::Other => false,
        };
    }

    public function defaultProviderCode(): string
    {
        return match ($this) {
            self::Stock, self::Etf, self::EtnCrypto => 'twelve_data',
            self::Crypto => 'coingecko',
            self::Gold => 'goldapi',
            self::RealEstate, self::Cash, self::LivretA, self::Ldds, self::Other => 'manual',
        };
    }
}
