<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Mappers;

use Kurusa\InstagramScraper\DTO\InstagramProfileReelsPageData;
use Kurusa\InstagramScraper\DTO\InstagramSourceReelData;

final readonly class InstagramProfileReelsPageMapper
{
    public function fromGraphqlResponse(?array $response): InstagramProfileReelsPageData
    {
        $reelsFeed = $this->findReelsFeed($response ?? []);

        if ($reelsFeed === null) {
            return new InstagramProfileReelsPageData(
                reels: [],
                endCursor: null,
                hasNextPage: false,
            );
        }

        $edges = $reelsFeed['edges'] ?? [];

        if (!is_array($edges)) {
            $edges = [];
        }

        $reels = [];

        foreach ($edges as $edge) {
            if (!is_array($edge)) {
                continue;
            }

            $node = $edge['node'] ?? null;

            if (!is_array($node)) {
                continue;
            }

            $shortcode = $node['code'] ?? null;

            if (!is_string($shortcode) || $shortcode === '') {
                continue;
            }

            $reels[] = new InstagramSourceReelData(
                shortcode: $shortcode,
                instagramMediaPk: $this->nullableString($node['pk'] ?? null),
                takenAt: $this->nullableInt($node['taken_at'] ?? null),
                captionText: $this->nullableString($node['caption']['text'] ?? null),
                likeCount: $this->nullableInt($node['like_count'] ?? null),
                commentCount: $this->nullableInt($node['comment_count'] ?? null),
                videoUrl: $this->nullableString($node['video_versions'][0]['url'] ?? null),
                thumbnailUrl: $this->nullableString($node['image_versions2']['candidates'][0]['url'] ?? null),
                videoDurationSeconds: $this->videoDurationSecondsFromDashManifest($node['video_dash_manifest'] ?? null),
                playCount: $this->nullableInt($node['play_count'] ?? null),
                rawData: $node,
            );
        }

        $pageInfo = $reelsFeed['page_info'] ?? [];

        if (!is_array($pageInfo)) {
            $pageInfo = [];
        }

        return new InstagramProfileReelsPageData(
            reels: $reels,
            endCursor: $this->nullableString($pageInfo['end_cursor'] ?? null),
            hasNextPage: ($pageInfo['has_next_page'] ?? false) === true,
        );
    }

    private function findReelsFeed(array $data): ?array
    {
        $reelsFeed = $data['xig_logged_out_reels_feed'] ?? null;

        if (is_array($reelsFeed)) {
            return $reelsFeed;
        }

        foreach ($data as $value) {
            if (!is_array($value)) {
                continue;
            }

            $reelsFeed = $this->findReelsFeed($value);

            if ($reelsFeed !== null) {
                return $reelsFeed;
            }
        }

        return null;
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
