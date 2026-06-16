<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Mappers;

use Kurusa\InstagramScraper\DTO\InstagramProfileReelsPageData;
use Kurusa\InstagramScraper\DTO\InstagramSourceReelData;

final readonly class InstagramProfileReelsGraphqlMapper
{
    public function fromGraphqlResponse(?array $response): InstagramProfileReelsPageData
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

                $reels[] = new InstagramSourceReelData(
                    shortcode: $shortcode,
                    instagramMediaPk: $this->nullableString($media['pk'] ?? null),
                    takenAt: null,
                    captionText: null,
                    likeCount: $this->nullableInt($media['like_count'] ?? null),
                    commentCount: $this->nullableInt($media['comment_count'] ?? null),
                    videoUrl: null,
                    thumbnailUrl: $this->nullableString($media['image_versions2']['candidates'][0]['url'] ?? null),
                    videoDurationSeconds: null,
                    playCount: $this->nullableInt($media['play_count'] ?? null),
                    rawData: $media,
                );
            }
        }

        return new InstagramProfileReelsPageData(
            reels: $reels,
            endCursor: is_string($pageInfo['end_cursor'] ?? null) ? $pageInfo['end_cursor'] : null,
            hasNextPage: (bool)($pageInfo['has_next_page'] ?? false),
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
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
