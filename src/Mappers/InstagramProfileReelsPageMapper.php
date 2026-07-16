<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Mappers;

use Kurusa\InstagramScraper\DTO\InstagramProfileReelsData;
use Kurusa\InstagramScraper\DTO\InstagramReelData;

final readonly class InstagramProfileReelsPageMapper
{
    public function fromGraphqlResponse(array $response): InstagramProfileReelsData
    {
        $reelsFeed = $this->findReelsFeed($response);

        if ($reelsFeed === null) {
            return new InstagramProfileReelsData(
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

            $reels[] = new InstagramReelData(
                shortcode: $shortcode,
                instagramMediaPk: $node['pk'] ?? null,
                takenAt: $this->nullableInt($node['taken_at'] ?? null),
                captionText: $node['caption']['text'] ?? null,
                likeCount: $this->nullableInt($node['like_count'] ?? null),
                commentCount: $this->nullableInt($node['comment_count'] ?? null),
                videoUrl: $node['video_versions'][0]['url'] ?? null,
                thumbnailUrl: $node['image_versions2']['candidates'][0]['url'] ?? null,
                videoDurationSeconds: $this->videoDurationSecondsFromDashManifest($node['video_dash_manifest'] ?? null),
                playCount: $this->nullableInt($node['play_count'] ?? null),
                rawData: $node,
            );
        }

        $pageInfo = $reelsFeed['page_info'] ?? [];

        if (!is_array($pageInfo)) {
            $pageInfo = [];
        }

        return new InstagramProfileReelsData(
            reels: $reels,
            endCursor: $pageInfo['end_cursor'] ?? null,
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
