<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper;

use Kurusa\InstagramScraper\Config\InstagramScraperConfig;
use Kurusa\InstagramScraper\DTO\InstagramFollowersPageData;
use Kurusa\InstagramScraper\DTO\InstagramProfileReelsData;
use Kurusa\InstagramScraper\DTO\InstagramReelData;
use Kurusa\InstagramScraper\Http\CurlHttpClient;
use Kurusa\InstagramScraper\Http\InstagramFollowersClient;
use Kurusa\InstagramScraper\Http\InstagramProfileReelsClient;
use Kurusa\InstagramScraper\Http\InstagramProfileResolver;
use Kurusa\InstagramScraper\Http\InstagramPythonBridge;
use Kurusa\InstagramScraper\Http\InstagramReelDetailClient;
use Kurusa\InstagramScraper\Mappers\InstagramFollowersMapper;
use Kurusa\InstagramScraper\Mappers\InstagramProfileReelsGraphqlMapper;
use Kurusa\InstagramScraper\Mappers\InstagramReelDataMerger;
use Kurusa\InstagramScraper\Mappers\InstagramReelGraphqlMapper;
use Kurusa\InstagramScraper\Services\FetchInstagramReelService;

final readonly class InstagramScraper
{
    private InstagramProfileReelsClient $instagramProfileReelsClient;

    private InstagramProfileReelsGraphqlMapper $instagramProfileReelsGraphqlMapper;

    private InstagramFollowersClient $instagramFollowersClient;

    private InstagramFollowersMapper $instagramFollowersMapper;

    private InstagramProfileResolver $instagramProfileResolver;

    private FetchInstagramReelService $fetchInstagramReelService;

    public function __construct(public InstagramScraperConfig $instagramScraperConfig)
    {
        $curlHttpClient = new CurlHttpClient($instagramScraperConfig->requestLogger);
        $pythonBridge = new InstagramPythonBridge($instagramScraperConfig);

        $this->instagramProfileReelsClient = new InstagramProfileReelsClient(
            instagramScraperConfig: $instagramScraperConfig,
            curlHttpClient: $curlHttpClient,
        );
        $this->instagramProfileReelsGraphqlMapper = new InstagramProfileReelsGraphqlMapper();
        $this->instagramFollowersClient = new InstagramFollowersClient(
            instagramScraperConfig: $instagramScraperConfig,
            curlHttpClient: $curlHttpClient,
        );
        $this->instagramFollowersMapper = new InstagramFollowersMapper();
        $this->instagramProfileResolver = new InstagramProfileResolver(
            config: $instagramScraperConfig,
            pythonBridge: $pythonBridge,
        );
        $this->fetchInstagramReelService = new FetchInstagramReelService(
            instagramReelDetailClient: new InstagramReelDetailClient(
                config: $instagramScraperConfig,
                pythonBridge: $pythonBridge,
            ),
            instagramReelGraphqlMapper: new InstagramReelGraphqlMapper(),
            instagramProfileReelsClient: $this->instagramProfileReelsClient,
            instagramProfileReelsGraphqlMapper: $this->instagramProfileReelsGraphqlMapper,
            instagramReelDataMerger: new InstagramReelDataMerger(),
            profileReelLookupMaxPages: $instagramScraperConfig->profileReelLookupMaxPages,
        );
    }

    public function fetchFollowersPage(
        string $targetUserId,
        ?string $cursor = null,
    ): InstagramFollowersPageData
    {
        $response = $this
            ->instagramFollowersClient
            ->fetchFollowersPage(
                targetUserId: $targetUserId,
                cursor: $cursor,
            );

        return $this
            ->instagramFollowersMapper
            ->fromResponse($response);
    }

    public function fetchProfileReelsPage(
        string $targetUserId,
        ?string $cursor = null,
    ): InstagramProfileReelsData
    {
        $graphqlResponse = $this
            ->instagramProfileReelsClient
            ->fetchProfileReelsPage(
                targetUserId: $targetUserId,
                cursor: $cursor,
            );

        return $this
            ->instagramProfileReelsGraphqlMapper
            ->fromGraphqlResponse($graphqlResponse);
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
            ->instagramProfileResolver
            ->fetchProfileByUsername($username);
    }
}
