<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Config;

final readonly class InstagramSessionCookies
{
    public function __construct(
        public string $sessionId,
        public string $dsUserId,
        public string $csrfToken,
        public ?string $wwwClaim = null,
        public ?string $webSessionId = null,
        public ?string $mid = null,
        public ?string $ig_did = null,
        public ?string $datr = null,
        public ?string $rur = null,
    )
    {
    }

    /**
     * @return array<string, string>
     */
    public function toCookieMap(): array
    {
        $cookies = [
            'sessionid' => $this->sessionId,
            'ds_user_id' => $this->dsUserId,
            'csrftoken' => $this->csrfToken,
        ];

        if ($this->mid !== null && $this->mid !== '') {
            $cookies['mid'] = $this->mid;
        }

        if ($this->ig_did !== null && $this->ig_did !== '') {
            $cookies['ig_did'] = $this->ig_did;
        }

        if ($this->datr !== null && $this->datr !== '') {
            $cookies['datr'] = $this->datr;
        }

        if ($this->rur !== null && $this->rur !== '') {
            $cookies['rur'] = $this->rur;
        }

        return $cookies;
    }

    public function toCookieHeader(): string
    {
        $parts = [];

        foreach ($this->toCookieMap() as $name => $value) {
            $parts[] = $name . '=' . $value;
        }

        return implode('; ', $parts);
    }
}
