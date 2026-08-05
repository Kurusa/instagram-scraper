<?php

declare(strict_types=1);

namespace Kurusa\InstagramScraper\Tests;

use Kurusa\InstagramScraper\Config\InstagramScraperConfig;
use Kurusa\InstagramScraper\Http\InstagramPythonBridge;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class InstagramPythonBridgeTest extends TestCase
{
    public function testItContinuesWaitingWhenStreamSelectIsInterruptedByASignal(): void
    {
        if (
            !function_exists('pcntl_fork')
            || !function_exists('pcntl_alarm')
            || !function_exists('stream_socket_pair')
        ) {
            self::markTestSkipped('PCNTL and socket pairs are required for the EINTR regression test.');
        }

        $streams = stream_socket_pair(
            STREAM_PF_UNIX,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP,
        );

        self::assertIsArray($streams);

        $childPid = pcntl_fork();

        self::assertNotSame(-1, $childPid);

        if ($childPid === 0) {
            fclose($streams[0]);
            usleep(1_500_000);
            fwrite($streams[1], "ready\n");
            fclose($streams[1]);

            exit(0);
        }

        fclose($streams[1]);
        pcntl_async_signals(true);
        pcntl_signal(SIGALRM, static function (): void {});
        pcntl_alarm(1);

        try {
            $bridge = new InstagramPythonBridge(
                new InstagramScraperConfig(
                    graphqlCsrfToken: 'csrf',
                    graphqlAppId: 'app',
                    pythonRequestTimeoutSeconds: 3,
                ),
            );

            $waitForResponse = new ReflectionMethod($bridge, 'waitForResponse');
            $waitForResponse->invoke($bridge, $streams[0]);

            self::assertSame('ready', trim((string) fgets($streams[0])));
        } finally {
            pcntl_alarm(0);
            fclose($streams[0]);
            pcntl_waitpid($childPid, $status);
        }
    }
}
