<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Config;

final readonly class InstagramProxy
{
    public function __construct(
        public string $ip,
        public int $port,
        public ?string $user = null,
        public ?string $password = null,
    )
    {
    }

    /**
     * @param InstagramProxy[] $proxies
     */
    public static function pickRandom(array $proxies): ?self
    {
        if ($proxies === []) {
            return null;
        }

        return $proxies[array_rand($proxies)];
    }

    /**
     * @return array<int, mixed>
     */
    public function curlOptions(): array
    {
        $options = [
            CURLOPT_PROXY => $this->ip . ':' . $this->port,
        ];

        if ($this->user !== null) {
            $options[CURLOPT_PROXYUSERPWD] = $this->user . ':' . ($this->password ?? '');
        }

        return $options;
    }
}
