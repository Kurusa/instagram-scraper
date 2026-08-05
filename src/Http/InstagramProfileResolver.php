<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Http;

use Kurusa\InstagramScraper\Config\InstagramProxy;
use Kurusa\InstagramScraper\Config\InstagramScraperConfig;

final readonly class InstagramProfileResolver
{
    public function __construct(
        private InstagramScraperConfig $config,
        private InstagramPythonBridge $pythonBridge,
    )
    {
    }

    /**
     * @return array{id: string, username: string}|null
     */
    public function fetchProfileByUsername(string $username): ?array
    {
        $result = $this->pythonBridge->execute([
            'action' => 'resolve_profile',
            'username' => $username,
            'app_id' => $this->config->graphqlAppId,
            'impersonate' => $this->config->browserImpersonation,
            'request_timeout_seconds' => $this->config->pythonRequestTimeoutSeconds,
            'session_ttl_seconds' => $this->config->anonymousSessionTtlSeconds,
            'proxy' => $this->proxyPayload(),
        ]);

        if (($result['ok'] ?? false) !== true) {
            return null;
        }

        $profile = $result['profile'] ?? null;

        if (
            !is_array($profile)
            || !is_string($profile['id'] ?? null)
            || !is_string($profile['username'] ?? null)
        ) {
            return null;
        }

        return [
            'id' => $profile['id'],
            'username' => $profile['username'],
        ];
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
