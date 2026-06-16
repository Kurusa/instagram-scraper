<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Mappers;

use Kurusa\InstagramScraper\DTO\InstagramProfileReelShortcodesPageData;

final readonly class InstagramProfileReelShortcodesPageMapper
{
    public function fromGraphqlResponse(?array $response): InstagramProfileReelShortcodesPageData
    {
        $edges = $response['data']['xdt_api__v1__clips__user__connection_v2']['edges'] ?? [];
        $pageInfo = $response['data']['xdt_api__v1__clips__user__connection_v2']['page_info'] ?? [];

        $shortcodes = [];

        if (is_array($edges)) {
            foreach ($edges as $edge) {
                $shortcode = $edge['node']['media']['code'] ?? null;

                if (!is_string($shortcode) || $shortcode === '') {
                    continue;
                }

                $shortcodes[] = $shortcode;
            }
        }

        return new InstagramProfileReelShortcodesPageData(
            shortcodes: array_values(array_unique($shortcodes)),
            endCursor: is_string($pageInfo['end_cursor'] ?? null) ? $pageInfo['end_cursor'] : null,
            hasNextPage: (bool)($pageInfo['has_next_page'] ?? false),
        );
    }
}
