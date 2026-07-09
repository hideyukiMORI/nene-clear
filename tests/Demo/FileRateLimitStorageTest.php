<?php

declare(strict_types=1);

namespace NeneClear\Tests\Demo;

use NeneClear\Demo\FileRateLimitStorage;
use NeneClear\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;

final class FileRateLimitStorageTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/' . uniqid('clear-throttle-', true);
        mkdir($this->baseDir, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->baseDir . '/rate-limits/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->baseDir . '/rate-limits');
        @rmdir($this->baseDir);
    }

    public function test_counts_hits_within_the_window_across_instances(): void
    {
        $clock = new FixedClock('2026-07-10T09:00:00+00:00');

        // A fresh instance per hit — the shared-hosting reality (one process per request).
        $first = (new FileRateLimitStorage($this->baseDir, $clock))->hit('ip:1.2.3.4', 3600);
        $second = (new FileRateLimitStorage($this->baseDir, $clock))->hit('ip:1.2.3.4', 3600);
        $third = (new FileRateLimitStorage($this->baseDir, $clock))->hit('ip:1.2.3.4', 3600);

        self::assertSame(1, $first['count']);
        self::assertSame(2, $second['count']);
        self::assertSame(3, $third['count']);
        self::assertSame($first['reset_at'], $third['reset_at']);
    }

    public function test_keys_are_isolated(): void
    {
        $clock = new FixedClock('2026-07-10T09:00:00+00:00');
        $storage = new FileRateLimitStorage($this->baseDir, $clock);

        $storage->hit('ip:1.2.3.4', 3600);
        $other = $storage->hit('ip:5.6.7.8', 3600);

        self::assertSame(1, $other['count']);
    }

    public function test_expired_window_starts_fresh(): void
    {
        $storage = new FileRateLimitStorage($this->baseDir, new FixedClock('2026-07-10T09:00:00+00:00'));
        $storage->hit('ip:1.2.3.4', 3600);
        $storage->hit('ip:1.2.3.4', 3600);

        $later = new FileRateLimitStorage($this->baseDir, new FixedClock('2026-07-10T10:00:01+00:00'));
        $fresh = $later->hit('ip:1.2.3.4', 3600);

        self::assertSame(1, $fresh['count']);
    }
}
