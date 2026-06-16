<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Services;

use JsonException;
use Kurusa\InstagramScraper\DTO\InstagramSourceReelData;
use Kurusa\InstagramScraper\Http\InstagramReelPageClient;
use Kurusa\InstagramScraper\Mappers\InstagramProfileReelsPageMapper;

final readonly class FetchInstagramReelService
{
    public function __construct(
        private InstagramReelPageClient $instagramReelPageClient,
        private InstagramProfileReelsPageMapper $instagramProfileReelsPageMapper,
    )
    {
    }

    public function fetchByShortcode(string $shortcode): ?InstagramSourceReelData
    {
        $html = $this->instagramReelPageClient->fetchHtmlByShortcode($shortcode);

        if ($html === null) {
            return null;
        }

        foreach ($this->extractEmbeddedJsonBlobs($html) as $decodedJson) {
            $instagramProfileReelsPageData = $this
                ->instagramProfileReelsPageMapper
                ->fromGraphqlResponse($decodedJson);

            foreach ($instagramProfileReelsPageData->reels as $instagramSourceReelData) {
                if ($instagramSourceReelData->shortcode === $shortcode) {
                    return $instagramSourceReelData;
                }
            }
        }

        return null;
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    private function extractEmbeddedJsonBlobs(string $html): iterable
    {
        $pattern = '/<script type="application\/json"[^>]*\bdata-sjs\b[^>]*>(.+?)<\/script>/s';

        if (preg_match_all($pattern, $html, $matches) === false) {
            return;
        }

        foreach ($matches[1] as $jsonBlob) {
            try {
                $decodedJson = json_decode($jsonBlob, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }

            if (is_array($decodedJson)) {
                yield $decodedJson;
            }
        }
    }
}
