<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Http;

use JsonException;
use Kurusa\InstagramScraper\Config\InstagramProxy;
use Kurusa\InstagramScraper\Config\InstagramScraperConfig;
use RuntimeException;

final readonly class InstagramGraphqlClient
{
    private const string GRAPHQL_URL = 'https://www.instagram.com/graphql/query';

    private const string PROFILE_REELS_DOC_ID = '26909206778772295';

    private const string PROFILE_REELS_CURSOR_KEY = 'max_id';

    private const int TIMEOUT_SECONDS = 20;

    private const int REQUEST_DELAY_MICROSECONDS = 500000;

    public function __construct(
        private InstagramScraperConfig $instagramScraperConfig,
    )
    {
    }

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

        $curlResponse = $this->postWithCurl($requestBody);

        if ($curlResponse['status_code'] < 200 || $curlResponse['status_code'] >= 300) {
            $message = $this->responseErrorMessage($curlResponse['body']);

            throw new RuntimeException(sprintf(
                'Instagram profile GraphQL returned HTTP %d%s.',
                $curlResponse['status_code'],
                $message === null ? '' : ': ' . $message,
            ));
        }

        if ($curlResponse['body'] === '') {
            throw new RuntimeException('Instagram profile GraphQL returned an empty response.');
        }

        return $this->decodeResponseBody($curlResponse['body']);
    }

    private function postWithCurl(string $requestBody): array
    {
        $curlHandle = curl_init();

        if ($curlHandle === false) {
            throw new RuntimeException('Could not initialize cURL.');
        }

        $proxyOptions = InstagramProxy::pickRandom($this->instagramScraperConfig->proxies)?->curlOptions() ?? [];

        $requestHeaders = [
            'content-type' => 'application/x-www-form-urlencoded',
            'x-csrftoken' => $this->instagramScraperConfig->graphqlCsrfToken,
            'x-ig-app-id' => $this->instagramScraperConfig->graphqlAppId,
        ];

        curl_setopt_array($curlHandle, $proxyOptions + [
                CURLOPT_URL => self::GRAPHQL_URL,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $requestBody,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_HTTPHEADER => array_map(
                    static fn(string $name, string $value): string => $name . ': ' . $value,
                    array_keys($requestHeaders),
                    array_values($requestHeaders),
                ),
            ]);

        $startedAt = microtime(true);
        $responseBody = curl_exec($curlHandle);
        $durationSeconds = microtime(true) - $startedAt;

        $statusCode = (int)curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curlHandle);

        $responseBodyString = is_string($responseBody) ? $responseBody : null;

        $this
            ->instagramScraperConfig
            ->requestLogger
            ?->logHttpInteraction(
                method: 'POST',
                url: self::GRAPHQL_URL,
                requestHeaders: $requestHeaders,
                requestBody: $requestBody,
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
