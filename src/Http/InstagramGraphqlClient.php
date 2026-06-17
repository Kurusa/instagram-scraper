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

    private const int MAX_PROXY_ATTEMPTS = 5;

    private const int REQUEST_DELAY_MICROSECONDS = 500000;

    public function __construct(
        private InstagramScraperConfig $config,
    )
    {
    }

    public function fetchProfileReelsPage(
        string $targetUserId,
        ?string $cursor = null,
    ): ?array
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
            variables: [
                'data' => $variables,
            ],
        );
    }

    private function postGraphql(
        string $documentId,
        array $variables,
    ): ?array
    {
        if (self::REQUEST_DELAY_MICROSECONDS > 0) {
            usleep(self::REQUEST_DELAY_MICROSECONDS);
        }

        $requestBody = http_build_query(
            [
                'variables' => json_encode($variables, JSON_THROW_ON_ERROR),
                'doc_id' => $documentId,
            ],
            '',
            '&',
            PHP_QUERY_RFC3986,
        );

        $curlResponse = $this->postWithCurlUsingProxyRetries($requestBody);

        if ($curlResponse['status_code'] === 302) {
            return null;
        }

        if ($curlResponse['status_code'] === 401) {
            return null;
        }

        if ($curlResponse['status_code'] === 429) {
            return null;
        }

        if ($curlResponse['status_code'] < 200 || $curlResponse['status_code'] >= 300) {
            return null;
        }

        if ($curlResponse['body'] === '') {
            return null;
        }

        return $this->decodeResponseBody($curlResponse['body']);
    }

    private function postWithCurlUsingProxyRetries(string $requestBody): array
    {
        if ($this->config->proxies === []) {
            return $this->postWithCurl(
                requestBody: $requestBody,
                proxy: null,
            );
        }

        $proxies = $this->config->proxies;
        shuffle($proxies);

        $attempts = min(self::MAX_PROXY_ATTEMPTS, count($proxies));
        $lastException = null;

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            try {
                return $this->postWithCurl(
                    requestBody: $requestBody,
                    proxy: $proxies[$attempt],
                );
            } catch (RuntimeException $exception) {
                $lastException = $exception;
            }
        }

        throw new RuntimeException(
            message: 'Instagram cURL request failed after trying ' . $attempts . ' proxies.',
            previous: $lastException,
        );
    }

    private function postWithCurl(
        string $requestBody,
        ?InstagramProxy $proxy,
    ): array
    {
        $curlHandle = curl_init();

        if ($curlHandle === false) {
            throw new RuntimeException('Could not initialize cURL.');
        }

        $proxyOptions = $proxy?->curlOptions() ?? [];

        $requestHeaders = [
            'content-type' => 'application/x-www-form-urlencoded',
            'x-csrftoken' => $this->config->graphqlCsrfToken,
            'x-ig-app-id' => $this->config->graphqlAppId,
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

        curl_close($curlHandle);

        $responseBodyString = is_string($responseBody) ? $responseBody : null;

        $this->config->requestLogger?->logHttpInteraction(
            method: 'POST',
            url: self::GRAPHQL_URL,
            requestHeaders: $requestHeaders,
            requestBody: $requestBody,
            statusCode: $statusCode > 0 ? $statusCode : null,
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

    private function decodeResponseBody(string $body): ?array
    {
        try {
            $decodedResponse = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($decodedResponse)) {
            return null;
        }

        return $decodedResponse;
    }
}
