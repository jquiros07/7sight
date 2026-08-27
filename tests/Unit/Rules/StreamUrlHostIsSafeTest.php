<?php

namespace Tests\Unit\Rules;

use App\Rules\StreamUrlHostIsSafe;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StreamUrlHostIsSafeTest extends TestCase
{
    #[DataProvider('safeUrls')]
    public function test_it_allows_safe_hosts(string $url): void
    {
        $failed = false;
        (new StreamUrlHostIsSafe)->validate('stream_url', $url, function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed, "Expected {$url} to pass validation.");
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function safeUrls(): array
    {
        return [
            'private class A' => ['rtsp://10.0.0.5:554/stream1'],
            'private class B' => ['rtsp://172.16.0.5:554/stream1'],
            'private class C' => ['rtsp://192.168.1.10:554/stream1'],
            'with credentials' => ['rtsp://user:pass@192.168.1.10:554/stream1'],
            'public IP' => ['rtsp://8.8.8.8:554/stream1'],
        ];
    }

    #[DataProvider('dangerousUrls')]
    public function test_it_blocks_dangerous_hosts(string $url): void
    {
        $failed = false;
        (new StreamUrlHostIsSafe)->validate('stream_url', $url, function () use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed, "Expected {$url} to fail validation.");
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function dangerousUrls(): array
    {
        return [
            'loopback' => ['rtsp://127.0.0.1:554/stream1'],
            'loopback host name' => ['rtsp://localhost:554/stream1'],
            'link-local / cloud metadata' => ['rtsp://169.254.169.254:554/stream1'],
            'unspecified' => ['rtsp://0.0.0.0:554/stream1'],
            'ipv6 loopback' => ['rtsp://[::1]:554/stream1'],
            'ipv6 link-local' => ['rtsp://[fe80::1]:554/stream1'],
            'broadcast' => ['rtsp://255.255.255.255:554/stream1'],
            'unresolvable hostname' => ['rtsp://this-host-does-not-exist.invalid:554/stream1'],
            'missing host' => ['rtsp:///stream1'],
        ];
    }
}
