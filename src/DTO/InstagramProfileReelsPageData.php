<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\DTO;

final readonly class InstagramProfileReelsPageData
{
    public function __construct(
        /** @var InstagramSourceReelData[] $reels */
        public array $reels,
        public ?string $endCursor,
        public bool $hasNextPage,
    )
    {
    }
}
