<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Http;

use JsonException;
use Kurusa\InstagramScraper\Config\InstagramProxy;
use Kurusa\InstagramScraper\Config\InstagramScraperConfig;
use Kurusa\InstagramScraper\Exceptions\InstagramSessionExpiredException;
use RuntimeException;

final readonly class InstagramFollowersClient
{
    private const string GRAPHQL_URL = 'https://www.instagram.com/graphql/query/';

    private const string FOLLOWERS_QUERY_HASH = '37479f2b8209594dde7facb0d904896a';

    private const int PAGE_SIZE = 50;

    private const int TIMEOUT_SECONDS = 30;

    private const int REQUEST_DELAY_MICROSECONDS = 500000;

    private const int MAX_ATTEMPTS = 6;

    private const int RETRY_BASE_DELAY_SECONDS = 3;

    private const int RETRY_MAX_DELAY_SECONDS = 45;

    public function __construct(
        private InstagramScraperConfig $instagramScraperConfig,
        private CurlHttpClient $curlHttpClient,
    )
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchFollowersPage(
        string $targetUserId,
        ?string $cursor = null,
    ): array
    {
        if ($this->instagramScraperConfig->sessionCookies === null) {
            throw new RuntimeException('Instagram session cookies are required to fetch followers.');
        }

        $variables = [
            'id' => $targetUserId,
            'include_reel' => false,
            'fetch_mutual' => false,
            'first' => self::PAGE_SIZE,
        ];

        if ($cursor !== null && $cursor !== '') {
            $variables['after'] = $cursor;
        }

        try {
            $encodedVariables = json_encode($variables, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Could not encode Instagram followers query variables.', previous: $exception);
        }

        $url = self::GRAPHQL_URL . '?' . http_build_query(
            [
                'query_hash' => self::FOLLOWERS_QUERY_HASH,
                'variables' => $encodedVariables,
            ],
            '',
            '&',
            PHP_QUERY_RFC3986,
        );

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
        $sessionCookies = $this->instagramScraperConfig->sessionCookies;

        $headers = [
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
            $headers['x-ig-www-claim'] = $sessionCookies->wwwClaim;
        }

        if ($sessionCookies->webSessionId !== null && $sessionCookies->webSessionId !== '') {
            $headers['x-web-session-id'] = $sessionCookies->webSessionId;
        }

        $response = $this->curlHttpClient->send(
            method: 'GET',
            url: $url,
            headers: $headers,
            timeoutSeconds: self::TIMEOUT_SECONDS,
            proxy: InstagramProxy::pickRandom($this->instagramScraperConfig->proxies),
            cookieHeader: $sessionCookies->toCookieHeader(),
        );

        if ($response->statusCode === 401 && $this->responseRequiresLogin($response->body)) {
            throw new InstagramSessionExpiredException(
                'Instagram session expired — refresh cookies (INSTAGRAM_SESSION_ID / INSTAGRAM_SESSION_CSRF_TOKEN).',
            );
        }

        if (!$response->isSuccessful()) {
            $message = $this->responseErrorMessage($response->body);

            throw new RuntimeException(sprintf(
                'Instagram followers endpoint returned HTTP %d%s.',
                $response->statusCode,
                $message === null ? '' : ': ' . $message,
            ));
        }

        if ($response->body === '') {
            throw new RuntimeException('Instagram followers endpoint returned an empty response.');
        }

        return $this->decodeResponseBody($response->body);
    }

    private function isRetryable(RuntimeException $exception): bool
    {
        if ($exception instanceof InstagramSessionExpiredException) {
            return false;
        }

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

    private function responseRequiresLogin(string $body): bool
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        return is_array($decoded) && (bool) ($decoded['require_login'] ?? false);
    }
}
