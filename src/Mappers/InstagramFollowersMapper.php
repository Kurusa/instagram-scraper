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
        $users = $response['users'] ?? [];
        $followers = [];

        if (is_array($users)) {
            foreach ($users as $user) {
                if (!is_array($user)) {
                    continue;
                }

                $igUserId = $user['pk'] ?? $user['pk_id'] ?? $user['id'] ?? null;
                $username = $user['username'] ?? null;

                if (!is_string($igUserId) && !is_int($igUserId)) {
                    continue;
                }

                if (!is_string($username) || $username === '') {
                    continue;
                }

                $followers[] = new InstagramFollowerData(
                    igUserId: (string) $igUserId,
                    username: $username,
                    fullName: is_string($user['full_name'] ?? null) ? $user['full_name'] : null,
                    profilePicUrl: is_string($user['profile_pic_url'] ?? null) ? $user['profile_pic_url'] : null,
                    isPrivate: (bool) ($user['is_private'] ?? false),
                    isVerified: (bool) ($user['is_verified'] ?? false),
                );
            }
        }

        $nextMaxId = $response['next_max_id'] ?? null;

        return new InstagramFollowersPageData(
            followers: $followers,
            nextMaxId: is_string($nextMaxId) && $nextMaxId !== '' ? $nextMaxId : null,
            hasMore: (bool) ($response['has_more'] ?? false),
        );
    }
}
