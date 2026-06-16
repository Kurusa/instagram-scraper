<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Http;

use JsonException;
use Kurusa\InstagramScraper\Config\InstagramScraperConfig;
use RuntimeException;

final readonly class InstagramGraphqlClient
{
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
            'include_feed_video' => true,
            'page_size' => $this->config->profileReelsPageSize,
            'target_user_id' => $targetUserId,
        ];

        if ($cursor !== null && $cursor !== '' && $this->config->profileReelsCursorKey !== '') {
            $variables[$this->config->profileReelsCursorKey] = $cursor;
        }

        return $this->postGraphql(
            documentId: $this->config->profileReelsDocId,
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
        if ($this->config->requestDelayMicroseconds > 0) {
            usleep($this->config->requestDelayMicroseconds);
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

        $curlResponse = $this->postWithCurl($requestBody);

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

    private function postWithCurl(string $requestBody): array
    {
        $curlHandle = curl_init();

        if ($curlHandle === false) {
            throw new RuntimeException('Could not initialize cURL.');
        }

        curl_setopt_array($curlHandle, [
            CURLOPT_URL => $this->config->graphqlUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $requestBody,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => $this->config->graphqlTimeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER => [
                'content-type: application/x-www-form-urlencoded',
                'x-csrftoken: ' . $this->config->graphqlCsrfToken,
                'x-ig-app-id: ' . $this->config->graphqlAppId,
            ],
        ]);

        $responseBody = curl_exec($curlHandle);
        $statusCode = (int)curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curlHandle);

        curl_close($curlHandle);

        if ($responseBody === false) {
            throw new RuntimeException('Instagram cURL request failed: ' . $curlError);
        }

        return [
            'status_code' => $statusCode,
            'body' => $responseBody,
        ];
    }
}
