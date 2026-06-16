<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Config;

final readonly class InstagramScraperConfig
{
    public function __construct(
        public string $graphqlUrl,
        public string $graphqlCsrfToken,
        public string $graphqlAppId,
        public string $userAgent,
        public int $graphqlTimeout,
        public int $requestDelayMicroseconds,
        public string $profileReelsDocId,
        public int $profileReelsPageSize,
        public string $profileReelsCursorKey,
        public string $mediaShortcodeDocId,
    )
    {
    }
}
