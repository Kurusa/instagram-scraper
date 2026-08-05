<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Http;

use Kurusa\InstagramScraper\Config\InstagramProxy;
use Kurusa\InstagramScraper\Config\InstagramScraperConfig;

final readonly class InstagramReelDetailClient
{
    public function __construct(
        private InstagramScraperConfig $config,
        private InstagramPythonBridge $pythonBridge,
    )
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchMediaByShortcode(
        string $shortcode,
        ?string $instagramMediaPk = null,
    ): ?array
    {
        $result = $this->pythonBridge->execute([
            'shortcode' => $shortcode,
            'media_id' => $instagramMediaPk,
            'app_id' => $this->config->graphqlAppId,
            'impersonate' => $this->config->browserImpersonation,
            'request_timeout_seconds' => $this->config->pythonRequestTimeoutSeconds,
            'session_ttl_seconds' => $this->config->anonymousSessionTtlSeconds,
            'proxy' => $this->proxyPayload(),
        ]);

        if (($result['ok'] ?? false) !== true) {
            return null;
        }

        $media = $result['media'] ?? null;

        return is_array($media) ? $media : null;
    }

    /**
     * @return array{host: string, port: int, username: ?string, password: ?string}|null
     */
    private function proxyPayload(): ?array
    {
        $proxy = InstagramProxy::pickRandom($this->config->proxies);

        if ($proxy === null) {
            return null;
        }

        return [
            'host' => $proxy->ip,
            'port' => $proxy->port,
            'username' => $proxy->user,
            'password' => $proxy->password,
        ];
    }
}
