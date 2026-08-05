<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Config;

use Kurusa\InstagramScraper\Logging\RequestLogger;

final readonly class InstagramScraperConfig
{
    public function __construct(
        public string $graphqlCsrfToken,
        public string $graphqlAppId,
        /** @var InstagramProxy[] */
        public array $proxies = [],
        public ?RequestLogger $requestLogger = null,
        public string $pythonExecutable = 'python3',
        public ?string $pythonClientScriptPath = null,
        public string $browserImpersonation = 'chrome',
        public int $pythonRequestTimeoutSeconds = 45,
        public int $anonymousSessionTtlSeconds = 300,
        public int $profileReelLookupMaxPages = 30,
        public ?InstagramSessionCookies $sessionCookies = null,
    )
    {
    }
}
