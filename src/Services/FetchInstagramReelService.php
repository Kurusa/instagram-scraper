<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Services;

use Kurusa\InstagramScraper\DTO\InstagramReelData;
use Kurusa\InstagramScraper\Http\InstagramReelGraphqlClient;
use Kurusa\InstagramScraper\Mappers\InstagramReelGraphqlMapper;

final readonly class FetchInstagramReelService
{
    public function __construct(
        private InstagramReelGraphqlClient $instagramReelGraphqlClient,
        private InstagramReelGraphqlMapper $instagramReelGraphqlMapper,
    )
    {
    }

    public function fetchByShortcode(
        string $shortcode,
        ?string $instagramMediaPk = null,
    ): ?InstagramReelData
    {
        $media = $this->instagramReelGraphqlClient->fetchMediaByShortcode(
            shortcode: $shortcode,
            instagramMediaPk: $instagramMediaPk,
        );

        if ($media === null) {
            return null;
        }

        $instagramReelData = $this->instagramReelGraphqlMapper->fromMedia($media);

        return $instagramReelData?->shortcode === $shortcode
            ? $instagramReelData
            : null;
    }
}
