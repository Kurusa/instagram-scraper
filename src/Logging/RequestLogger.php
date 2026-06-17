<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Logging;

interface RequestLogger
{
    /**
     * @param array<string, string> $requestHeaders
     */
    public function logHttpInteraction(
        string $method,
        string $url,
        array $requestHeaders,
        ?string $requestBody,
        ?int $statusCode,
        ?string $responseBody,
        float $durationSeconds,
        ?string $error,
    ): void;
}
