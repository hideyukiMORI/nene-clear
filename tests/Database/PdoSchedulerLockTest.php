<?php

declare(strict_types=1);

namespace NeneClear\Tests\Database;

use DateTimeImmutable;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Testing\DatabaseTestKit;
use NeneClear\Scheduler\PdoSchedulerLock;
use NeneClear\Tests\Support\SchemaFixture;
use PHPUnit\Framework\TestCase;

final class PdoSchedulerLockTest extends TestCase
{
    private string $dbPath;
    private DatabaseQueryExecutorInterface $query;
    private PdoSchedulerLock $lock;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('scheduler-lock-', true) . '.sqlite';
        $this->query = DatabaseTestKit::sqlite($this->dbPath)->queryExecutor;
        SchemaFixture::createSchedulerLocks($this->query);
        $this->lock = new PdoSchedulerLock($this->query);
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    /**
     * The point of the lock: an overlapping cron tick must find the door shut.
     * The caller treats that as normal operation (exit 0), not as an error.
     */
    public function testSecondRunCannotTakeALockThatIsHeld(): void
    {
        $now = new DateTimeImmutable('2026-07-29 10:00:00');

        self::assertTrue($this->lock->acquire('dunning:42', 'run-one', 900, $now));
        self::assertFalse($this->lock->acquire('dunning:42', 'run-two', 900, $now->modify('+1 second')));
    }

    public function testDifferentKeysDoNotBlockEachOther(): void
    {
        $now = new DateTimeImmutable('2026-07-29 10:00:00');

        self::assertTrue($this->lock->acquire('dunning:42', 'run-one', 900, $now));
        self::assertTrue($this->lock->acquire('dunning:43', 'run-two', 900, $now));
    }

    /**
     * A run killed mid-flight never releases its lock. Without expiry the job
     * would be blocked forever by a process that no longer exists.
     */
    public function testAnExpiredLockCanBeReclaimed(): void
    {
        $now = new DateTimeImmutable('2026-07-29 10:00:00');

        self::assertTrue($this->lock->acquire('dunning:42', 'crashed-run', 900, $now));

        // 14 minutes later the 15-minute lock is still live.
        self::assertFalse($this->lock->acquire('dunning:42', 'next-run', 900, $now->modify('+14 minutes')));

        // Past the TTL it may be taken over.
        self::assertTrue($this->lock->acquire('dunning:42', 'next-run', 900, $now->modify('+16 minutes')));
    }

    /**
     * Two runs racing for the *same expired* lock: exactly one may win. The
     * reclaim is a single conditional UPDATE, so the loser's condition no longer
     * matches once the winner has moved `expires_at` forward.
     */
    public function testOnlyOneRunWinsAContestedReclaim(): void
    {
        $now = new DateTimeImmutable('2026-07-29 10:00:00');
        $this->lock->acquire('dunning:42', 'crashed-run', 900, $now);

        $later = $now->modify('+16 minutes');
        $first = $this->lock->acquire('dunning:42', 'run-a', 900, $later);
        $second = $this->lock->acquire('dunning:42', 'run-b', 900, $later);

        self::assertTrue($first);
        self::assertFalse($second, 'a reclaimed lock must not be handed to a second run');

        $row = $this->query->fetchOne('SELECT holder_token FROM scheduler_locks WHERE lock_key = ?', ['dunning:42']);
        self::assertNotNull($row);
        self::assertSame('run-a', $row['holder_token']);
    }

    public function testReleaseLetsTheNextRunTakeIt(): void
    {
        $now = new DateTimeImmutable('2026-07-29 10:00:00');
        $this->lock->acquire('dunning:42', 'run-one', 900, $now);

        $this->lock->release('dunning:42', 'run-one');

        self::assertTrue($this->lock->acquire('dunning:42', 'run-two', 900, $now->modify('+1 second')));
    }

    /**
     * A run that overran its TTL must not release the lock its successor is
     * legitimately holding — hence the holder check on release.
     */
    public function testReleaseByANonHolderDoesNothing(): void
    {
        $now = new DateTimeImmutable('2026-07-29 10:00:00');
        $this->lock->acquire('dunning:42', 'run-one', 900, $now);

        $this->lock->release('dunning:42', 'someone-else');

        self::assertFalse($this->lock->acquire('dunning:42', 'run-two', 900, $now->modify('+1 second')));
    }
}
