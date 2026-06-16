<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\DTO;

final readonly class InstagramProfileReelShortcodesPageData
{
    public function __construct(
        /** @var string[] $shortcodes */
        public array $shortcodes,

        public ?string $endCursor,
        public bool $hasNextPage,
    )
    {
    }
}
