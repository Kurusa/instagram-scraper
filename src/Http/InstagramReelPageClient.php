<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Http;

use Kurusa\InstagramScraper\Config\InstagramScraperConfig;
use RuntimeException;

final readonly class InstagramReelPageClient
{
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

        curl_setopt_array($curlHandle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
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
                'user-agent: ' . $this->config->userAgent,
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
