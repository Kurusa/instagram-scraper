<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Mappers;

use Kurusa\InstagramScraper\DTO\InstagramProfileReelsData;
use Kurusa\InstagramScraper\DTO\InstagramReelData;

final readonly class InstagramProfileReelsGraphqlMapper
{
    public function fromGraphqlResponse(array $response): InstagramProfileReelsData
    {
        $connection = $response['data']['xdt_api__v1__clips__user__connection_v2'] ?? [];

        $edges = $connection['edges'] ?? [];
        $pageInfo = $connection['page_info'] ?? [];

        $reels = [];

        if (is_array($edges)) {
            foreach ($edges as $edge) {
                $media = $edge['node']['media'] ?? null;

                if (!is_array($media)) {
                    continue;
                }

                $shortcode = $media['code'] ?? null;

                if (!is_string($shortcode) || $shortcode === '') {
                    continue;
                }

                $reels[] = new InstagramReelData(
                    shortcode: $shortcode,
                    instagramMediaPk: $media['pk'] ?? null,
                    takenAt: null,
                    captionText: null,
                    likeCount: $this->nullableInt($media['like_count'] ?? null),
                    commentCount: $this->nullableInt($media['comment_count'] ?? null),
                    videoUrl: null,
                    thumbnailUrl: $media['image_versions2']['candidates'][0]['url'] ?? null,
                    videoDurationSeconds: null,
                    playCount: $this->nullableInt($media['play_count'] ?? null),
                    rawData: $media,
                );
            }
        }

        return new InstagramProfileReelsData(
            reels: $reels,
            endCursor: is_string($pageInfo['end_cursor'] ?? null) ? $pageInfo['end_cursor'] : null,
            hasNextPage: (bool)($pageInfo['has_next_page'] ?? false),
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
}
