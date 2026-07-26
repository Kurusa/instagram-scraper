<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Mappers;

use Kurusa\InstagramScraper\DTO\InstagramReelData;

final readonly class InstagramReelGraphqlMapper
{
    /**
     * @param array<string, mixed> $media
     */
    public function fromMedia(array $media): ?InstagramReelData
    {
        $shortcode = $media['code'] ?? null;

        if (!is_string($shortcode) || $shortcode === '') {
            return null;
        }

        return new InstagramReelData(
            shortcode: $shortcode,
            instagramMediaPk: $this->nullableString($media['pk'] ?? null),
            takenAt: $this->nullableInt($media['taken_at'] ?? null),
            captionText: $this->nullableString($media['caption']['text'] ?? null),
            likeCount: $this->nullableInt($media['like_count'] ?? null),
            commentCount: $this->nullableInt($media['comment_count'] ?? null),
            videoUrl: $this->nullableString($media['video_versions'][0]['url'] ?? null),
            thumbnailUrl: $this->nullableString($media['image_versions2']['candidates'][0]['url'] ?? null),
            videoDurationSeconds: $this->videoDurationSecondsFromDashManifest(
                $media['video_dash_manifest'] ?? null,
            ),
            playCount: $this->nullableInt(
                $media['play_count']
                ?? $media['view_count']
                ?? $media['video_view_count']
                ?? null,
            ),
            rawData: $media,
        );
    }

    private function nullableInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int)$value;
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function videoDurationSecondsFromDashManifest(mixed $manifestXml): ?float
    {
        if (!is_string($manifestXml) || $manifestXml === '') {
            return null;
        }

        if (preg_match('/mediaPresentationDuration="PT([\d.]+)S"/', $manifestXml, $matches) !== 1) {
            return null;
        }

        return (float)$matches[1];
    }
}
