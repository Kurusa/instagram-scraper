<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Http;

use JsonException;
use Kurusa\InstagramScraper\Config\InstagramProxy;
use Kurusa\InstagramScraper\Config\InstagramScraperConfig;
use RuntimeException;

final readonly class InstagramFollowersClient
{
    private const string FOLLOWERS_URL_TEMPLATE = 'https://www.instagram.com/api/v1/friendships/%s/followers/';

    private const int PAGE_SIZE = 25;

    private const int TIMEOUT_SECONDS = 30;

    private const int REQUEST_DELAY_MICROSECONDS = 500000;

    private const int MAX_ATTEMPTS = 6;

    private const int RETRY_BASE_DELAY_SECONDS = 3;

    private const int RETRY_MAX_DELAY_SECONDS = 45;

    public function __construct(
        private InstagramScraperConfig $instagramScraperConfig,
    )
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchFollowersPage(
        string $targetUserId,
        ?string $maxId = null,
        ?string $searchSurface = 'follow_list_page',
    ): array
    {
        if ($this->instagramScraperConfig->sessionCookies === null) {
            throw new RuntimeException('Instagram session cookies are required to fetch followers.');
        }

        $query = [
            'count' => self::PAGE_SIZE,
        ];

        if ($searchSurface !== null && $searchSurface !== '') {
            $query['search_surface'] = $searchSurface;
        }

        if ($maxId !== null && $maxId !== '') {
            $query['max_id'] = $maxId;
        }

        $url = sprintf(self::FOLLOWERS_URL_TEMPLATE, rawurlencode($targetUserId))
            . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        if (self::REQUEST_DELAY_MICROSECONDS > 0) {
            usleep(self::REQUEST_DELAY_MICROSECONDS);
        }

        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return $this->fetchOnce($url);
            } catch (RuntimeException $exception) {
                if ($attempt >= self::MAX_ATTEMPTS || !$this->isRetryable($exception)) {
                    throw $exception;
                }

                $delaySeconds = min(
                    self::RETRY_MAX_DELAY_SECONDS,
                    self::RETRY_BASE_DELAY_SECONDS * (2 ** ($attempt - 1)),
                );

                sleep($delaySeconds);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchOnce(string $url): array
    {
        $curlResponse = $this->getWithCurl($url);

        if ($curlResponse['status_code'] < 200 || $curlResponse['status_code'] >= 300) {
            $message = $this->responseErrorMessage($curlResponse['body']);

            throw new RuntimeException(sprintf(
                'Instagram followers endpoint returned HTTP %d%s.',
                $curlResponse['status_code'],
                $message === null ? '' : ': ' . $message,
            ));
        }

        if ($curlResponse['body'] === '') {
            throw new RuntimeException('Instagram followers endpoint returned an empty response.');
        }

        return $this->decodeResponseBody($curlResponse['body']);
    }

    private function isRetryable(RuntimeException $exception): bool
    {
        $message = $exception->getMessage();

        if (preg_match('/HTTP (\d{3})/', $message, $matches) === 1) {
            $statusCode = (int) $matches[1];

            return $statusCode === 429 || $statusCode === 408 || $statusCode >= 500;
        }

        $transientSignals = [
            'CONNECT tunnel failed',
            'Could not resolve host',
            'Connection refused',
            'Connection reset',
            'Connection closed',
            'Connection timed out',
            'Operation timed out',
            'Failed to connect',
            'Recv failure',
            'SSL_connect',
            'SSL_ERROR',
            'timed out',
            'empty response',
            'invalid JSON',
            'invalid response',
        ];

        foreach ($transientSignals as $signal) {
            if (stripos($message, $signal) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{status_code: int, body: string}
     */
    private function getWithCurl(string $url): array
    {
        $curlHandle = curl_init();

        if ($curlHandle === false) {
            throw new RuntimeException('Could not initialize cURL.');
        }

        $sessionCookies = $this->instagramScraperConfig->sessionCookies;
        $proxyOptions = InstagramProxy::pickRandom($this->instagramScraperConfig->proxies)?->curlOptions() ?? [];

        $requestHeaders = [
            'accept' => '*/*',
            'accept-language' => 'en-US,en;q=0.9',
            'x-csrftoken' => $sessionCookies->csrfToken,
            'x-ig-app-id' => $this->instagramScraperConfig->graphqlAppId,
            'x-requested-with' => 'XMLHttpRequest',
            'referer' => 'https://www.instagram.com/',
            'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 '
                . '(KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
        ];

        if ($sessionCookies->wwwClaim !== null && $sessionCookies->wwwClaim !== '') {
            $requestHeaders['x-ig-www-claim'] = $sessionCookies->wwwClaim;
        }

        if ($sessionCookies->webSessionId !== null && $sessionCookies->webSessionId !== '') {
            $requestHeaders['x-web-session-id'] = $sessionCookies->webSessionId;
        }

        curl_setopt_array($curlHandle, $proxyOptions + [
            CURLOPT_URL => $url,
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_COOKIE => $sessionCookies->toCookieHeader(),
            CURLOPT_HTTPHEADER => array_map(
                static fn(string $name, string $value): string => $name . ': ' . $value,
                array_keys($requestHeaders),
                array_values($requestHeaders),
            ),
        ]);

        $startedAt = microtime(true);
        $responseBody = curl_exec($curlHandle);
        $durationSeconds = microtime(true) - $startedAt;

        $statusCode = (int) curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curlHandle);

        $responseBodyString = is_string($responseBody) ? $responseBody : null;

        $this
            ->instagramScraperConfig
            ->requestLogger
            ?->logHttpInteraction(
                method: 'GET',
                url: $url,
                requestHeaders: $requestHeaders + ['cookie' => $sessionCookies->toCookieHeader()],
                requestBody: null,
                statusCode: $statusCode,
                responseBody: $responseBodyString,
                durationSeconds: $durationSeconds,
                error: $curlError !== '' ? $curlError : null,
            );

        if ($responseBody === false) {
            throw new RuntimeException('Instagram cURL request failed: ' . $curlError);
        }

        return [
            'status_code' => $statusCode,
            'body' => $responseBody,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponseBody(string $body): array
    {
        try {
            $decodedResponse = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Instagram followers endpoint returned invalid JSON.',
                previous: $exception,
            );
        }

        if (!is_array($decodedResponse)) {
            throw new RuntimeException('Instagram followers endpoint returned an invalid response.');
        }

        return $decodedResponse;
    }

    private function responseErrorMessage(string $body): ?string
    {
        try {
            $decodedResponse = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        $message = is_array($decodedResponse)
            ? ($decodedResponse['message'] ?? null)
            : null;

        return is_string($message) && $message !== ''
            ? $message
            : null;
    }
}
