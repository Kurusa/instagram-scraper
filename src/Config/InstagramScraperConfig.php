<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Config;

final readonly class InstagramScraperConfig
{
    public function __construct(
        public string $graphqlCsrfToken,
        public string $graphqlAppId,
        public int $profileReelsPageSize,
        /** @var InstagramProxy[] */
        public array $proxies = [],
    )
    {
    }
}
