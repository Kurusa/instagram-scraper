<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Services;

use JsonException;
use Kurusa\InstagramScraper\DTO\InstagramSourceReelData;
use Kurusa\InstagramScraper\Http\InstagramReelPageClient;
use Kurusa\InstagramScraper\Mappers\InstagramProfileReelsPageMapper;

final readonly class FetchInstagramReelService
{
    private const string REEL_PAYLOAD_MARKER = 'video_dash_manifest';

    private const string EMBEDDED_JSON_PATTERN = '/<script type="application\/json"[^>]*\bdata-sjs\b[^>]*>(.+?)<\/script>/s';

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

        $decodedJson = $this->findReelPayload($html);

        if ($decodedJson === null) {
            return null;
        }

        $instagramProfileReelsPageData = $this
            ->instagramProfileReelsPageMapper
            ->fromGraphqlResponse($decodedJson);

        foreach ($instagramProfileReelsPageData->reels as $instagramSourceReelData) {
            if ($instagramSourceReelData->shortcode === $shortcode) {
                return $instagramSourceReelData;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findReelPayload(string $html): ?array
    {
        if (preg_match_all(self::EMBEDDED_JSON_PATTERN, $html, $matches) === false) {
            return null;
        }

        foreach ($matches[1] as $jsonBlob) {
            if (!str_contains($jsonBlob, self::REEL_PAYLOAD_MARKER)) {
                continue;
            }

            try {
                $decodedJson = json_decode($jsonBlob, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }

            if (is_array($decodedJson)) {
                return $decodedJson;
            }
        }

        return null;
    }
}
