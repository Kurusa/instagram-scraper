<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Http;

use RuntimeException;

final readonly class InstagramReelPageClient
{
    private const string USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:150.0) Gecko/20100101 Firefox/150.0';

    private const int TIMEOUT_SECONDS = 30;

    public function fetchHtmlByShortcode(string $shortcode): ?string
    {
        $curlHandle = curl_init('https://www.instagram.com/reels/' . $shortcode . '/');

        if ($curlHandle === false) {
            throw new RuntimeException('Could not initialize cURL.');
        }

        curl_setopt_array($curlHandle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER => [
                'accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'accept-language: en-US,en;q=0.9',
                'referer: https://www.instagram.com/',
                'sec-fetch-dest: document',
                'sec-fetch-mode: navigate',
                'sec-fetch-site: same-origin',
                'sec-fetch-user: ?1',
                'upgrade-insecure-requests: 1',
                'user-agent: ' . self::USER_AGENT,
            ],
        ]);

        $responseBody = curl_exec($curlHandle);
        $statusCode = (int)curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);

        curl_close($curlHandle);

        if (!is_string($responseBody) || $responseBody === '') {
            return null;
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            return null;
        }

        return $responseBody;
    }
}
