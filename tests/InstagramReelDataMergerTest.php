<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Tests;

use Kurusa\InstagramScraper\DTO\InstagramReelData;
use Kurusa\InstagramScraper\Mappers\InstagramReelDataMerger;
use PHPUnit\Framework\TestCase;

final class InstagramReelDataMergerTest extends TestCase
{
    public function testMergesProfileMetricsIntoDetailData(): void
    {
        $source = $this->reel(
            shortcode: 'DbO0WvxyJn7',
            instagramMediaPk: '3949324184798665211',
            likeCount: 3000,
            thumbnailUrl: 'https://cdn.example/profile-thumbnail.jpg',
            playCount: 62330,
            rawData: ['play_count' => 62330],
        );
        $detail = $this->reel(
            shortcode: 'DbO0WvxyJn7',
            instagramMediaPk: '3949324184798665211',
            takenAt: 1785016199,
            captionText: 'Caption',
            likeCount: 3497,
            commentCount: 28,
            videoUrl: 'https://cdn.example/reel.mp4',
            thumbnailUrl: 'https://cdn.example/detail-thumbnail.jpg',
            videoDurationSeconds: 31.299999,
            rawData: ['code' => 'DbO0WvxyJn7'],
        );

        $result = (new InstagramReelDataMerger())->merge($source, $detail);

        self::assertSame(62330, $result->playCount);
        self::assertSame(62330, $result->rawData['play_count']);
        self::assertSame(1785016199, $result->takenAt);
        self::assertSame('Caption', $result->captionText);
        self::assertSame(3497, $result->likeCount);
        self::assertSame(28, $result->commentCount);
        self::assertSame('https://cdn.example/reel.mp4', $result->videoUrl);
        self::assertSame('https://cdn.example/detail-thumbnail.jpg', $result->thumbnailUrl);
        self::assertSame(31.299999, $result->videoDurationSeconds);
    }

    public function testFreshProfileLookupTakesPriorityOverStoredPlayCount(): void
    {
        $source = $this->reel(
            shortcode: 'DbO0WvxyJn7',
            playCount: 60000,
        );
        $detail = $this->reel(shortcode: 'DbO0WvxyJn7');
        $profile = $this->reel(
            shortcode: 'DbO0WvxyJn7',
            playCount: 62330,
        );

        $result = (new InstagramReelDataMerger())->merge(
            source: $source,
            detail: $detail,
            profile: $profile,
        );

        self::assertSame(62330, $result->playCount);
        self::assertSame(62330, $result->rawData['play_count']);
    }

    private function reel(
        string $shortcode,
        ?string $instagramMediaPk = null,
        ?int $takenAt = null,
        ?string $captionText = null,
        ?int $likeCount = null,
        ?int $commentCount = null,
        ?string $videoUrl = null,
        ?string $thumbnailUrl = null,
        ?float $videoDurationSeconds = null,
        ?int $playCount = null,
        array $rawData = [],
    ): InstagramReelData {
        return new InstagramReelData(
            shortcode: $shortcode,
            instagramMediaPk: $instagramMediaPk,
            takenAt: $takenAt,
            captionText: $captionText,
            likeCount: $likeCount,
            commentCount: $commentCount,
            videoUrl: $videoUrl,
            thumbnailUrl: $thumbnailUrl,
            videoDurationSeconds: $videoDurationSeconds,
            playCount: $playCount,
            rawData: $rawData,
        );
    }
}
