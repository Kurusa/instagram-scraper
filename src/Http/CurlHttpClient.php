<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Http;

use Kurusa\InstagramScraper\Config\InstagramProxy;
use Kurusa\InstagramScraper\Logging\RequestLogger;
use RuntimeException;

final readonly class CurlHttpClient
{
    public function __construct(
        private ?RequestLogger $requestLogger = null,
    )
    {
    }

    /**
     * @param array<string, string> $headers
     */
    public function send(
        string $method,
        string $url,
        array $headers,
        ?string $body = null,
        int $timeoutSeconds = 30,
        ?InstagramProxy $proxy = null,
        ?string $cookieHeader = null,
        bool $followLocation = false,
    ): CurlResponse
    {
        $curlHandle = curl_init();

        if ($curlHandle === false) {
            throw new RuntimeException('Could not initialize cURL.');
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => $followLocation,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER => array_map(
                static fn(string $name, string $value): string => $name . ': ' . $value,
                array_keys($headers),
                array_values($headers),
            ),
        ];

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        if ($cookieHeader !== null && $cookieHeader !== '') {
            $options[CURLOPT_COOKIE] = $cookieHeader;
        }

        $proxyOptions = $proxy?->curlOptions() ?? [];

        curl_setopt_array($curlHandle, $proxyOptions + $options);

        $startedAt = microtime(true);
        $responseBody = curl_exec($curlHandle);
        $durationSeconds = microtime(true) - $startedAt;

        $statusCode = (int) curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curlHandle);

        $loggedHeaders = $cookieHeader === null || $cookieHeader === ''
            ? $headers
            : $headers + ['cookie' => $cookieHeader];

        $this->requestLogger?->logHttpInteraction(
            method: $method,
            url: $url,
            requestHeaders: $loggedHeaders,
            requestBody: $body,
            statusCode: $statusCode > 0 ? $statusCode : null,
            responseBody: is_string($responseBody) ? $responseBody : null,
            durationSeconds: $durationSeconds,
            error: $curlError !== '' ? $curlError : null,
        );

        if ($responseBody === false) {
            throw new RuntimeException('Instagram cURL request failed: ' . $curlError);
        }

        return new CurlResponse(
            statusCode: $statusCode,
            body: $responseBody,
            durationSeconds: $durationSeconds,
        );
    }
}
