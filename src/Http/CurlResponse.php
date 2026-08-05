<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Http;

final readonly class CurlResponse
{
    public function __construct(
        public int $statusCode,
        public string $body,
        public float $durationSeconds,
    )
    {
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
}
