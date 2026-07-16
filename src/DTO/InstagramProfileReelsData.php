<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\DTO;

final readonly class InstagramProfileReelsData
{
    public function __construct(
        /** @var InstagramReelData[] $reels */
        public array $reels,

        public ?string $endCursor,
        public bool $hasNextPage,
    )
    {
    }
}
