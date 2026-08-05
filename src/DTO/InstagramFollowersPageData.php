<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\DTO;

final readonly class InstagramFollowersPageData
{
    public function __construct(
        /** @var InstagramFollowerData[] $followers */
        public array $followers,
        public ?string $nextMaxId,
        public bool $hasMore,
    )
    {
    }
}
