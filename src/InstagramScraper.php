<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper;

use Kurusa\InstagramScraper\Config\InstagramScraperConfig;
use Kurusa\InstagramScraper\DTO\InstagramProfileReelsPageData;
use Kurusa\InstagramScraper\DTO\InstagramSourceReelData;
use Kurusa\InstagramScraper\Http\InstagramGraphqlClient;
use Kurusa\InstagramScraper\Http\InstagramReelPageClient;
use Kurusa\InstagramScraper\Mappers\InstagramProfileReelsGraphqlMapper;
use Kurusa\InstagramScraper\Mappers\InstagramProfileReelsPageMapper;
use Kurusa\InstagramScraper\Services\FetchInstagramReelService;

final readonly class InstagramScraper
{
    private InstagramGraphqlClient $instagramGraphqlClient;

    private InstagramProfileReelsGraphqlMapper $instagramProfileReelsGraphqlMapper;

    private FetchInstagramReelService $fetchInstagramReelService;

    public function __construct(public InstagramScraperConfig $instagramScraperConfig)
    {
        $this->instagramGraphqlClient = new InstagramGraphqlClient($instagramScraperConfig);
        $this->instagramProfileReelsGraphqlMapper = new InstagramProfileReelsGraphqlMapper();
        $this->fetchInstagramReelService = new FetchInstagramReelService(
            instagramReelPageClient: new InstagramReelPageClient($instagramScraperConfig),
            instagramProfileReelsPageMapper: new InstagramProfileReelsPageMapper(),
        );
    }

    public function fetchProfileReelsPage(
        string $targetUserId,
        ?string $cursor = null,
    ): InstagramProfileReelsPageData
    {
        $graphqlResponse = $this
            ->instagramGraphqlClient
            ->fetchProfileReelsPage(
                targetUserId: $targetUserId,
                cursor: $cursor,
            );

        return $this
            ->instagramProfileReelsGraphqlMapper
            ->fromGraphqlResponse($graphqlResponse);
    }

    public function fetchReelByShortcode(string $shortcode): ?InstagramSourceReelData
    {
        return $this
            ->fetchInstagramReelService
            ->fetchByShortcode($shortcode);
    }
}
