<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper;

use Kurusa\InstagramScraper\Config\InstagramScraperConfig;
use Kurusa\InstagramScraper\DTO\InstagramProfileReelShortcodesPageData;
use Kurusa\InstagramScraper\DTO\InstagramSourceReelData;
use Kurusa\InstagramScraper\Http\InstagramGraphqlClient;
use Kurusa\InstagramScraper\Http\InstagramReelPageClient;
use Kurusa\InstagramScraper\Mappers\InstagramProfileReelShortcodesPageMapper;
use Kurusa\InstagramScraper\Mappers\InstagramProfileReelsPageMapper;
use Kurusa\InstagramScraper\Services\FetchInstagramReelService;

final readonly class InstagramScraper
{
    private InstagramGraphqlClient $instagramGraphqlClient;

    private InstagramProfileReelShortcodesPageMapper $instagramProfileReelShortcodesPageMapper;

    private FetchInstagramReelService $fetchInstagramReelService;

    public function __construct(
        public InstagramScraperConfig $config,
    )
    {
        $this->instagramGraphqlClient = new InstagramGraphqlClient($config);
        $this->instagramProfileReelShortcodesPageMapper = new InstagramProfileReelShortcodesPageMapper();
        $this->fetchInstagramReelService = new FetchInstagramReelService(
            instagramReelPageClient: new InstagramReelPageClient($config),
            instagramProfileReelsPageMapper: new InstagramProfileReelsPageMapper(),
        );
    }

    public function fetchProfileReelShortcodesPage(
        string $targetUserId,
        ?string $cursor = null,
    ): InstagramProfileReelShortcodesPageData
    {
        $graphqlResponse = $this
            ->instagramGraphqlClient
            ->fetchProfileReelsPage(
                targetUserId: $targetUserId,
                cursor: $cursor,
            );

        return $this
            ->instagramProfileReelShortcodesPageMapper
            ->fromGraphqlResponse($graphqlResponse);
    }

    public function fetchReelByShortcode(string $shortcode): ?InstagramSourceReelData
    {
        return $this
            ->fetchInstagramReelService
            ->fetchByShortcode($shortcode);
    }
}
