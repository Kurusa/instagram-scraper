<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\DTO;

final readonly class InstagramFollowerData
{
    public function __construct(
        public string $igUserId,
        public string $username,
        public ?string $fullName,
        public ?string $profilePicUrl,
        public bool $isPrivate,
        public bool $isVerified,
    )
    {
    }
}
