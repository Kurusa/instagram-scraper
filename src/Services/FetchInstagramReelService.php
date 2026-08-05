<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Services;

use Kurusa\InstagramScraper\DTO\InstagramReelData;
use Kurusa\InstagramScraper\Http\InstagramProfileReelsClient;
use Kurusa\InstagramScraper\Http\InstagramReelDetailClient;
use Kurusa\InstagramScraper\Mappers\InstagramProfileReelsGraphqlMapper;
use Kurusa\InstagramScraper\Mappers\InstagramReelDataMerger;
use Kurusa\InstagramScraper\Mappers\InstagramReelGraphqlMapper;

final readonly class FetchInstagramReelService
{
    public function __construct(
        private InstagramReelDetailClient $instagramReelDetailClient,
        private InstagramReelGraphqlMapper $instagramReelGraphqlMapper,
        private InstagramProfileReelsClient $instagramProfileReelsClient,
        private InstagramProfileReelsGraphqlMapper $instagramProfileReelsGraphqlMapper,
        private InstagramReelDataMerger $instagramReelDataMerger,
        private int $profileReelLookupMaxPages,
    )
    {
    }

    public function fetch(InstagramReelData $sourceReel): ?InstagramReelData
    {
        $media = $this->instagramReelDetailClient->fetchMediaByShortcode(
            shortcode: $sourceReel->shortcode,
            instagramMediaPk: $sourceReel->instagramMediaPk,
        );

        if ($media === null) {
            return null;
        }

        $instagramReelData = $this->instagramReelGraphqlMapper->fromMedia($media);

        if ($instagramReelData?->shortcode !== $sourceReel->shortcode) {
            return null;
        }

        $hasProfileMetrics = array_key_exists('play_count', $sourceReel->rawData);
        $profileReel = !$hasProfileMetrics && $instagramReelData->playCount === null
            ? $this->findReelInOwnerProfile(
                shortcode: $sourceReel->shortcode,
                ownerId: $this->ownerIdFromMedia($media),
            )
            : null;

        return $this->instagramReelDataMerger->merge(
            source: $sourceReel,
            detail: $instagramReelData,
            profile: $profileReel,
        );
    }

    /**
     * @param array<string, mixed> $media
     */
    private function ownerIdFromMedia(array $media): ?string
    {
        $ownerId = $media['user']['pk'] ?? $media['user']['id'] ?? null;

        if (is_int($ownerId)) {
            return (string) $ownerId;
        }

        return is_string($ownerId) && $ownerId !== ''
            ? $ownerId
            : null;
    }

    private function findReelInOwnerProfile(
        string $shortcode,
        ?string $ownerId,
    ): ?InstagramReelData {
        if ($ownerId === null || $this->profileReelLookupMaxPages < 1) {
            return null;
        }

        $cursor = null;

        for ($page = 1; $page <= $this->profileReelLookupMaxPages; $page++) {
            $graphqlResponse = $this->instagramProfileReelsClient->fetchProfileReelsPage(
                targetUserId: $ownerId,
                cursor: $cursor,
            );

            $profilePage = $this
                ->instagramProfileReelsGraphqlMapper
                ->fromGraphqlResponse($graphqlResponse);

            foreach ($profilePage->reels as $profileReel) {
                if ($profileReel->shortcode === $shortcode) {
                    return $profileReel;
                }
            }

            if (!$profilePage->hasNextPage || $profilePage->endCursor === null) {
                return null;
            }

            $cursor = $profilePage->endCursor;
        }

        return null;
    }
}
