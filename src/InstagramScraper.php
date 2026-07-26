<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper;

use Kurusa\InstagramScraper\Config\InstagramScraperConfig;
use Kurusa\InstagramScraper\DTO\InstagramProfileReelsData;
use Kurusa\InstagramScraper\DTO\InstagramReelData;
use Kurusa\InstagramScraper\Http\InstagramGraphqlClient;
use Kurusa\InstagramScraper\Http\InstagramReelGraphqlClient;
use Kurusa\InstagramScraper\Mappers\InstagramProfileReelsGraphqlMapper;
use Kurusa\InstagramScraper\Mappers\InstagramReelDataMerger;
use Kurusa\InstagramScraper\Mappers\InstagramReelGraphqlMapper;
use Kurusa\InstagramScraper\Services\FetchInstagramReelService;

final readonly class InstagramScraper
{
    private InstagramGraphqlClient $instagramGraphqlClient;

    private InstagramProfileReelsGraphqlMapper $instagramProfileReelsGraphqlMapper;

    private FetchInstagramReelService $fetchInstagramReelService;

    private InstagramReelGraphqlClient $instagramReelGraphqlClient;

    public function __construct(public InstagramScraperConfig $instagramScraperConfig)
    {
        $this->instagramGraphqlClient = new InstagramGraphqlClient($instagramScraperConfig);
        $this->instagramProfileReelsGraphqlMapper = new InstagramProfileReelsGraphqlMapper();
        $this->instagramReelGraphqlClient = new InstagramReelGraphqlClient($instagramScraperConfig);
        $this->fetchInstagramReelService = new FetchInstagramReelService(
            instagramReelGraphqlClient: $this->instagramReelGraphqlClient,
            instagramReelGraphqlMapper: new InstagramReelGraphqlMapper(),
            instagramGraphqlClient: $this->instagramGraphqlClient,
            instagramProfileReelsGraphqlMapper: $this->instagramProfileReelsGraphqlMapper,
            instagramReelDataMerger: new InstagramReelDataMerger(),
            profileReelLookupMaxPages: $instagramScraperConfig->profileReelLookupMaxPages,
        );
    }

    public function fetchProfileReelsPage(
        string $targetUserId,
        ?string $cursor = null,
    ): InstagramProfileReelsData
    {
        $graphqlResponse = $this
            ->instagramGraphqlClient
            ->fetchProfileReelsPage(
                targetUserId: $targetUserId,
                cursor: $cursor,
            );

        return $this
            ->instagramProfileReelsGraphqlMapper
            ->fromGraphqlResponse($graphqlResponse ?? []);
    }

    public function fetchReel(InstagramReelData $sourceReel): ?InstagramReelData
    {
        return $this
            ->fetchInstagramReelService
            ->fetch($sourceReel);
    }

    /**
     * @return array{id: string, username: string}|null
     */
    public function fetchProfileByUsername(string $username): ?array
    {
        return $this
            ->instagramReelGraphqlClient
            ->fetchProfileByUsername($username);
    }

    /**
     * @deprecated Pass the available profile data to fetchReel() when possible.
     */
    public function fetchReelByShortcode(
        string $shortcode,
        ?string $instagramMediaPk = null,
    ): ?InstagramReelData
    {
        return $this->fetchReel(new InstagramReelData(
            shortcode: $shortcode,
            instagramMediaPk: $instagramMediaPk,
            takenAt: null,
            captionText: null,
            likeCount: null,
            commentCount: null,
            videoUrl: null,
            thumbnailUrl: null,
            videoDurationSeconds: null,
            playCount: null,
            rawData: [],
        ));
    }
}
