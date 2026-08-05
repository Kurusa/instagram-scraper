<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Http;

use Kurusa\InstagramScraper\Config\InstagramProxy;
use Kurusa\InstagramScraper\Config\InstagramScraperConfig;

final readonly class InstagramReelPageClient
{
    private const string USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:150.0) Gecko/20100101 Firefox/150.0';

    private const int TIMEOUT_SECONDS = 30;

    public function __construct(
        private InstagramScraperConfig $config,
        private CurlHttpClient $curlHttpClient,
    )
    {
    }

    public function fetchHtmlByShortcode(string $shortcode): ?string
    {
        $url = 'https://www.instagram.com/reels/' . $shortcode . '/';

        $response = $this->curlHttpClient->send(
            method: 'GET',
            url: $url,
            headers: [
                'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'accept-language' => 'en-US,en;q=0.9',
                'referer' => 'https://www.instagram.com/',
                'sec-fetch-dest' => 'document',
                'sec-fetch-mode' => 'navigate',
                'sec-fetch-site' => 'same-origin',
                'sec-fetch-user' => '?1',
                'upgrade-insecure-requests' => '1',
                'user-agent' => self::USER_AGENT,
            ],
            timeoutSeconds: self::TIMEOUT_SECONDS,
            proxy: InstagramProxy::pickRandom($this->config->proxies),
            followLocation: true,
        );

        if ($response->body === '' || !$response->isSuccessful()) {
            return null;
        }

        return $response->body;
    }
}
