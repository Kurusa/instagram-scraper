<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Http;

use Kurusa\InstagramScraper\Config\InstagramProxy;
use Kurusa\InstagramScraper\Config\InstagramScraperConfig;
use RuntimeException;

final readonly class InstagramReelPageClient
{
    private const string USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:150.0) Gecko/20100101 Firefox/150.0';

    private const int TIMEOUT_SECONDS = 30;

    public function __construct(
        private InstagramScraperConfig $config,
    )
    {
    }

    public function fetchHtmlByShortcode(string $shortcode): ?string
    {
        $url = 'https://www.instagram.com/reels/' . $shortcode . '/';

        $curlHandle = curl_init($url);

        if ($curlHandle === false) {
            throw new RuntimeException('Could not initialize cURL.');
        }

        $proxyOptions = InstagramProxy::pickRandom($this->config->proxies)?->curlOptions() ?? [];

        $requestHeaders = [
            'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'accept-language' => 'en-US,en;q=0.9',
            'referer' => 'https://www.instagram.com/',
            'sec-fetch-dest' => 'document',
            'sec-fetch-mode' => 'navigate',
            'sec-fetch-site' => 'same-origin',
            'sec-fetch-user' => '?1',
            'upgrade-insecure-requests' => '1',
            'user-agent' => self::USER_AGENT,
        ];

        curl_setopt_array($curlHandle, $proxyOptions + [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER => array_map(
                static fn (string $name, string $value): string => $name . ': ' . $value,
                array_keys($requestHeaders),
                array_values($requestHeaders),
            ),
        ]);

        $startedAt = microtime(true);
        $responseBody = curl_exec($curlHandle);
        $durationSeconds = microtime(true) - $startedAt;

        $statusCode = (int)curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curlHandle);

        curl_close($curlHandle);

        $responseBodyString = is_string($responseBody) ? $responseBody : null;

        $this->config->requestLogger?->logHttpInteraction(
            method: 'GET',
            url: $url,
            requestHeaders: $requestHeaders,
            requestBody: null,
            statusCode: $statusCode > 0 ? $statusCode : null,
            responseBody: $responseBodyString,
            durationSeconds: $durationSeconds,
            error: $curlError !== '' ? $curlError : null,
        );

        if (!is_string($responseBody) || $responseBody === '') {
            return null;
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            return null;
        }

        return $responseBody;
    }
}
