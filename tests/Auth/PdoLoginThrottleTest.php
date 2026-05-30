<?php

declare(strict_types=1);

namespace NeneClear\Tests\Auth;

use DateTimeImmutable;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\ClockInterface;
use Nene2\Testing\DatabaseTestKit;
use NeneClear\Auth\PdoLoginThrottle;
use NeneClear\Tests\Support\SchemaFixture;
use PHPUnit\Framework\TestCase;

/** A clock the test can advance, to exercise window/lock expiry. */
final class MutableClock implements ClockInterface
{
    private DateTimeImmutable $now;

    public function __construct(string $start = '2026-05-30T09:00:00+00:00')
    {
        $this->now = new DateTimeImmutable($start);
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(int $seconds): void
    {
        $this->now = $this->now->modify("+{$seconds} seconds");
    }
}

final class PdoLoginThrottleTest extends TestCase
{
    private string $dbPath;
    private DatabaseQueryExecutorInterface $query;
    private MutableClock $clock;
    private PdoLoginThrottle $throttle;

    private const string ID = 'attacker@x.example|203.0.113.9';

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-throttle-', true) . '.sqlite';
        $this->query = DatabaseTestKit::sqlite($this->dbPath)->queryExecutor;
        SchemaFixture::createLoginAttempts($this->query);
        $this->clock = new MutableClock();
        $this->throttle = new PdoLoginThrottle($this->query, $this->clock);
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    public function test_not_locked_initially(): void
    {
        self::assertSame(0, $this->throttle->secondsUntilUnlocked(self::ID));
    }

    public function test_locks_after_five_failures(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->throttle->recordFailure(self::ID);
            self::assertSame(0, $this->throttle->secondsUntilUnlocked(self::ID), "not locked after " . ($i + 1));
        }

        $this->throttle->recordFailure(self::ID); // 5th → lock

        self::assertGreaterThan(0, $this->throttle->secondsUntilUnlocked(self::ID));
    }

    public function test_success_clears_attempts(): void
    {
        $this->throttle->recordFailure(self::ID);
        $this->throttle->recordFailure(self::ID);
        $this->throttle->clear(self::ID);

        // After clear, five fresh failures are needed to lock again.
        for ($i = 0; $i < 4; $i++) {
            $this->throttle->recordFailure(self::ID);
        }
        self::assertSame(0, $this->throttle->secondsUntilUnlocked(self::ID));
    }

    public function test_window_expiry_resets_counter(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->throttle->recordFailure(self::ID);
        }

        // Wait past the 15-minute window, then a failure starts a fresh window.
        $this->clock->advance(901);
        $this->throttle->recordFailure(self::ID);

        self::assertSame(0, $this->throttle->secondsUntilUnlocked(self::ID));
    }

    public function test_lock_expires_after_lock_window(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->throttle->recordFailure(self::ID);
        }
        self::assertGreaterThan(0, $this->throttle->secondsUntilUnlocked(self::ID));

        $this->clock->advance(901); // past the 15-minute lock

        self::assertSame(0, $this->throttle->secondsUntilUnlocked(self::ID));
    }

    public function test_distinct_identifiers_are_independent(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->throttle->recordFailure('victim@x.example|203.0.113.9');
        }

        // A different identifier (different IP) is unaffected.
        self::assertSame(0, $this->throttle->secondsUntilUnlocked('victim@x.example|198.51.100.2'));
    }
}
