<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\DTO;

final readonly class InstagramSourceReelData
{
    public function __construct(
        public string $shortcode,
        public ?string $instagramMediaPk,
        public ?int $takenAt,
        public ?string $captionText,
        public ?int $likeCount,
        public ?int $commentCount,
        public ?string $videoUrl,
        public ?string $thumbnailUrl,
        public ?float $videoDurationSeconds,

        public array $rawData,
    )
    {
    }
}
