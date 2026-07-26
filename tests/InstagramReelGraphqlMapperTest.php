<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Tests;

use Kurusa\InstagramScraper\Mappers\InstagramReelGraphqlMapper;
use PHPUnit\Framework\TestCase;

final class InstagramReelGraphqlMapperTest extends TestCase
{
    public function testMapsLoggedOutDetailMedia(): void
    {
        $media = [
            'pk' => '3949324184798665211',
            'code' => 'DbO0WvxyJn7',
            'taken_at' => 1785016199,
            'caption' => ['text' => 'Caption'],
            'like_count' => 3736,
            'comment_count' => 28,
            'video_versions' => [
                ['url' => 'https://cdn.example/reel.mp4'],
            ],
            'image_versions2' => [
                'candidates' => [
                    ['url' => 'https://cdn.example/thumbnail.jpg'],
                ],
            ],
            'video_dash_manifest' => '<MPD mediaPresentationDuration="PT31.299999S"></MPD>',
        ];

        $result = (new InstagramReelGraphqlMapper())->fromMedia($media);

        self::assertNotNull($result);
        self::assertSame('DbO0WvxyJn7', $result->shortcode);
        self::assertSame('3949324184798665211', $result->instagramMediaPk);
        self::assertSame(1785016199, $result->takenAt);
        self::assertSame('Caption', $result->captionText);
        self::assertSame(3736, $result->likeCount);
        self::assertSame(28, $result->commentCount);
        self::assertSame('https://cdn.example/reel.mp4', $result->videoUrl);
        self::assertSame('https://cdn.example/thumbnail.jpg', $result->thumbnailUrl);
        self::assertSame(31.299999, $result->videoDurationSeconds);
        self::assertNull($result->playCount);
        self::assertSame($media, $result->rawData);
    }

    public function testReturnsNullWithoutShortcode(): void
    {
        self::assertNull((new InstagramReelGraphqlMapper())->fromMedia([]));
    }
}
