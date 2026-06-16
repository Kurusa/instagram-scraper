<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Config;

final readonly class InstagramScraperConfig
{
    public function __construct(
        public string $graphqlCsrfToken,
        public string $graphqlAppId,
        public string $userAgent,
        public int $profileReelsPageSize,
        public string $mediaShortcodeDocId,
        public string $graphqlUrl = 'https://www.instagram.com/graphql/query',
        public string $profileReelsDocId = '26909206778772295',
        public string $profileReelsCursorKey = 'max_id',
        public int $graphqlTimeout = 20,
        public int $requestDelayMicroseconds = 500000,
    )
    {
    }
}
