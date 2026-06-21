<?php

namespace App\Domain\Pricing;

use RuntimeException;

class ProviderUnavailableException extends RuntimeException
{
    public function __construct(string $provider, string $message = '')
    {
        parent::__construct("Provider [{$provider}] unavailable: {$message}");
    }
}
