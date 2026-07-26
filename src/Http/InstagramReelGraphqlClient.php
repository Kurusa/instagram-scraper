<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Http;

use JsonException;
use Kurusa\InstagramScraper\Config\InstagramProxy;
use Kurusa\InstagramScraper\Config\InstagramScraperConfig;
use RuntimeException;

final class InstagramReelGraphqlClient
{
    /** @var resource|null */
    private mixed $process = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    public function __construct(
        private readonly InstagramScraperConfig $config,
    )
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchMediaByShortcode(
        string $shortcode,
        ?string $instagramMediaPk = null,
    ): ?array
    {
        $proxy = InstagramProxy::pickRandom($this->config->proxies);

        $command = [
            'shortcode' => $shortcode,
            'media_id' => $instagramMediaPk,
            'app_id' => $this->config->graphqlAppId,
            'impersonate' => $this->config->browserImpersonation,
            'request_timeout_seconds' => $this->config->pythonRequestTimeoutSeconds,
            'session_ttl_seconds' => $this->config->anonymousSessionTtlSeconds,
            'proxy' => $proxy === null ? null : [
                'host' => $proxy->ip,
                'port' => $proxy->port,
                'username' => $proxy->user,
                'password' => $proxy->password,
            ],
        ];

        $result = $this->execute($command);
        $this->logInteractions($result['interactions'] ?? []);

        if (($result['ok'] ?? false) !== true) {
            return null;
        }

        $media = $result['media'] ?? null;

        return is_array($media) ? $media : null;
    }

    /**
     * @param array<string, mixed> $command
     * @return array<string, mixed>
     */
    private function execute(array $command): array
    {
        try {
            return $this->sendCommand($command);
        } catch (RuntimeException) {
            $this->stopProcess();

            return $this->sendCommand($command);
        }
    }

    /**
     * @param array<string, mixed> $command
     * @return array<string, mixed>
     */
    private function sendCommand(array $command): array
    {
        $this->ensureProcessStarted();

        try {
            $payload = json_encode($command, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new RuntimeException('Could not encode Instagram Python client command.', previous: $exception);
        }

        if (fwrite($this->pipes[0], $payload . "\n") === false) {
            throw new RuntimeException('Could not write to Instagram Python client.');
        }

        fflush($this->pipes[0]);

        $read = [$this->pipes[1]];
        $write = null;
        $except = null;
        $ready = stream_select(
            $read,
            $write,
            $except,
            $this->config->pythonRequestTimeoutSeconds + 5,
        );

        if ($ready !== 1) {
            throw new RuntimeException('Instagram Python client timed out.');
        }

        $responseLine = fgets($this->pipes[1]);

        if (!is_string($responseLine) || $responseLine === '') {
            $error = trim(stream_get_contents($this->pipes[2]));

            throw new RuntimeException(
                'Instagram Python client returned no response.'
                . ($error !== '' ? ' ' . $error : ''),
            );
        }

        try {
            $response = json_decode($responseLine, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Instagram Python client returned invalid JSON.', previous: $exception);
        }

        if (!is_array($response)) {
            throw new RuntimeException('Instagram Python client returned an invalid response.');
        }

        return $response;
    }

    private function ensureProcessStarted(): void
    {
        if (is_resource($this->process)) {
            $status = proc_get_status($this->process);

            if (($status['running'] ?? false) === true) {
                return;
            }

            $this->stopProcess();
        }

        if (!function_exists('proc_open')) {
            throw new RuntimeException('proc_open is required for the Instagram Python client.');
        }

        $scriptPath = $this->config->pythonClientScriptPath
            ?? dirname(__DIR__, 2) . '/python/instagram_reel_client.py';

        if (!is_file($scriptPath)) {
            throw new RuntimeException('Instagram Python client script not found: ' . $scriptPath);
        }

        $pipes = [];
        $process = proc_open(
            [$this->config->pythonExecutable, $scriptPath],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Could not start Instagram Python client.');
        }

        $this->process = $process;
        $this->pipes = $pipes;
        stream_set_blocking($this->pipes[2], false);
    }

    /**
     * @param mixed $interactions
     */
    private function logInteractions(mixed $interactions): void
    {
        if (!is_array($interactions) || $this->config->requestLogger === null) {
            return;
        }

        foreach ($interactions as $interaction) {
            if (!is_array($interaction)) {
                continue;
            }

            $headers = $interaction['request_headers'] ?? [];

            $this->config->requestLogger->logHttpInteraction(
                method: (string)($interaction['method'] ?? 'GET'),
                url: (string)($interaction['url'] ?? ''),
                requestHeaders: is_array($headers) ? $headers : [],
                requestBody: is_string($interaction['request_body'] ?? null)
                    ? $interaction['request_body']
                    : null,
                statusCode: is_int($interaction['status_code'] ?? null)
                    ? $interaction['status_code']
                    : null,
                responseBody: is_string($interaction['response_body'] ?? null)
                    ? $interaction['response_body']
                    : null,
                durationSeconds: is_numeric($interaction['duration_seconds'] ?? null)
                    ? (float)$interaction['duration_seconds']
                    : 0.0,
                error: is_string($interaction['error'] ?? null)
                    ? $interaction['error']
                    : null,
            );
        }
    }

    private function stopProcess(): void
    {
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        $this->pipes = [];

        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
        }

        $this->process = null;
    }

    public function __destruct()
    {
        $this->stopProcess();
    }
}
