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
    )
    {
    }
}
