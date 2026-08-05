<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Mappers;

use Kurusa\InstagramScraper\DTO\InstagramReelData;

final readonly class InstagramReelDataMerger
{
    public function merge(
        InstagramReelData $source,
        InstagramReelData $detail,
        ?InstagramReelData $profile = null,
    ): InstagramReelData
    {
        $playCount = $profile?->playCount
            ?? $source->playCount
            ?? $detail->playCount;

        $rawData = $detail->rawData;

        if ($playCount !== null) {
            $rawData['play_count'] = $playCount;
        }

        return new InstagramReelData(
            shortcode: $detail->shortcode,
            instagramMediaPk: $detail->instagramMediaPk
            ?? $profile?->instagramMediaPk
            ?? $source->instagramMediaPk,
            takenAt: $detail->takenAt
            ?? $profile?->takenAt
            ?? $source->takenAt,
            captionText: $detail->captionText
            ?? $profile?->captionText
            ?? $source->captionText,
            likeCount: $detail->likeCount
            ?? $profile?->likeCount
            ?? $source->likeCount,
            commentCount: $detail->commentCount
            ?? $profile?->commentCount
            ?? $source->commentCount,
            videoUrl: $detail->videoUrl
            ?? $profile?->videoUrl
            ?? $source->videoUrl,
            thumbnailUrl: $detail->thumbnailUrl
            ?? $profile?->thumbnailUrl
            ?? $source->thumbnailUrl,
            videoDurationSeconds: $detail->videoDurationSeconds
            ?? $profile?->videoDurationSeconds
            ?? $source->videoDurationSeconds,
            playCount: $playCount,
            rawData: $rawData,
        );
    }
}
