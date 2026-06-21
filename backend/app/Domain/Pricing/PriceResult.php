<?php

namespace App\Domain\Pricing;

/**
 * Normalised result of a price fetch from an external provider.
 */
final class PriceResult
{
    public function __construct(
        public readonly float $price,
        public readonly string $currency,
        public readonly \DateTimeInterface $fetchedAt,
        public readonly string $source,
        public readonly string $status = 'success',
        public readonly ?string $errorMessage = null,
        public readonly ?array $rawPayload = null,
    ) {}

    public static function success(
        float $price,
        string $currency,
        string $source,
        ?array $rawPayload = null,
    ): self {
        return new self(
            price: $price,
            currency: $currency,
            fetchedAt: now(),
            source: $source,
            status: 'success',
            rawPayload: $rawPayload,
        );
    }

    public static function fallback(
        float $price,
        string $currency,
        string $source,
        string $errorMessage,
    ): self {
        return new self(
            price: $price,
            currency: $currency,
            fetchedAt: now(),
            source: $source,
            status: 'fallback',
            errorMessage: $errorMessage,
        );
    }

    public static function error(string $source, string $errorMessage): self
    {
        return new self(
            price: 0,
            currency: 'EUR',
            fetchedAt: now(),
            source: $source,
            status: 'error',
            errorMessage: $errorMessage,
        );
    }

    public function isError(): bool
    {
        return $this->status === 'error';
    }
}
