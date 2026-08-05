<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Mappers;

use Kurusa\InstagramScraper\DTO\InstagramFollowerData;
use Kurusa\InstagramScraper\DTO\InstagramFollowersPageData;

final readonly class InstagramFollowersMapper
{
    /**
     * @param array<string, mixed> $response
     */
    public function fromResponse(array $response): InstagramFollowersPageData
    {
        $edgeFollowedBy = ($response['data']['user']['edge_followed_by'] ?? null);

        if (!is_array($edgeFollowedBy)) {
            return new InstagramFollowersPageData(
                followers: [],
                nextMaxId: null,
                hasMore: false,
            );
        }

        $edges = $edgeFollowedBy['edges'] ?? [];
        $followers = [];

        if (is_array($edges)) {
            foreach ($edges as $edge) {
                $node = is_array($edge) ? ($edge['node'] ?? null) : null;

                if (!is_array($node)) {
                    continue;
                }

                $igUserId = $node['id'] ?? null;
                $username = $node['username'] ?? null;

                if (!is_string($igUserId) && !is_int($igUserId)) {
                    continue;
                }

                if (!is_string($username) || $username === '') {
                    continue;
                }

                $followers[] = new InstagramFollowerData(
                    igUserId: (string)$igUserId,
                    username: $username,
                    fullName: is_string($node['full_name'] ?? null) ? $node['full_name'] : null,
                    profilePicUrl: is_string($node['profile_pic_url'] ?? null) ? $node['profile_pic_url'] : null,
                    isPrivate: (bool)($node['is_private'] ?? false),
                    isVerified: (bool)($node['is_verified'] ?? false),
                );
            }
        }

        $pageInfo = $edgeFollowedBy['page_info'] ?? [];
        $endCursor = is_array($pageInfo) ? ($pageInfo['end_cursor'] ?? null) : null;
        $hasNextPage = is_array($pageInfo) && (bool)($pageInfo['has_next_page'] ?? false);

        return new InstagramFollowersPageData(
            followers: $followers,
            nextMaxId: is_string($endCursor) && $endCursor !== '' ? $endCursor : null,
            hasMore: $hasNextPage,
        );
    }
}
