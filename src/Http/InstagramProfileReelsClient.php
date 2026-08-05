<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Http;

use JsonException;
use Kurusa\InstagramScraper\Config\InstagramProxy;
use Kurusa\InstagramScraper\Config\InstagramScraperConfig;
use RuntimeException;

final readonly class InstagramProfileReelsClient
{
    private const string GRAPHQL_URL = 'https://www.instagram.com/graphql/query';

    private const string PROFILE_REELS_DOC_ID = '26909206778772295';

    private const string PROFILE_REELS_CURSOR_KEY = 'max_id';

    private const int TIMEOUT_SECONDS = 20;

    private const int REQUEST_DELAY_MICROSECONDS = 500000;

    public function __construct(
        private InstagramScraperConfig $instagramScraperConfig,
        private CurlHttpClient $curlHttpClient,
    )
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchProfileReelsPage(
        string $targetUserId,
        ?string $cursor = null,
    ): array
    {
        $variables = [
            'page_size' => 12,
            'target_user_id' => $targetUserId,
        ];

        if ($cursor !== null && $cursor !== '') {
            $variables[self::PROFILE_REELS_CURSOR_KEY] = $cursor;
        }

        return $this->postGraphql(
            documentId: self::PROFILE_REELS_DOC_ID,
            variables: $variables,
        );
    }

    /**
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    private function postGraphql(
        string $documentId,
        array $variables,
    ): array
    {
        if (self::REQUEST_DELAY_MICROSECONDS > 0) {
            usleep(self::REQUEST_DELAY_MICROSECONDS);
        }

        $requestBody = http_build_query(
            [
                'variables' => json_encode([
                    'data' => $variables,
                ], JSON_THROW_ON_ERROR),
                'doc_id' => $documentId,
            ],
            '',
            '&',
            PHP_QUERY_RFC3986,
        );

        $response = $this->curlHttpClient->send(
            method: 'POST',
            url: self::GRAPHQL_URL,
            headers: [
                'content-type' => 'application/x-www-form-urlencoded',
                'x-csrftoken' => $this->instagramScraperConfig->graphqlCsrfToken,
                'x-ig-app-id' => $this->instagramScraperConfig->graphqlAppId,
            ],
            body: $requestBody,
            timeoutSeconds: self::TIMEOUT_SECONDS,
            proxy: InstagramProxy::pickRandom($this->instagramScraperConfig->proxies),
        );

        if (!$response->isSuccessful()) {
            $message = $this->responseErrorMessage($response->body);

            throw new RuntimeException(sprintf(
                'Instagram profile GraphQL returned HTTP %d%s.',
                $response->statusCode,
                $message === null ? '' : ': ' . $message,
            ));
        }

        if ($response->body === '') {
            throw new RuntimeException('Instagram profile GraphQL returned an empty response.');
        }

        return $this->decodeResponseBody($response->body);
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
                'Instagram profile GraphQL returned invalid JSON.',
                previous: $exception,
            );
        }

        if (!is_array($decodedResponse)) {
            throw new RuntimeException('Instagram profile GraphQL returned an invalid response.');
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
