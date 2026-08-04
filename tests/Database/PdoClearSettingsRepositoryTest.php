<?php

declare(strict_types=1);

namespace NeneClear\Tests\Database;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Testing\DatabaseTestKit;
use NeneClear\BankImport\PdoBankAccountRepository;
use NeneClear\ClearSettings\ClearSettings;
use NeneClear\ClearSettings\DunningSchedule;
use NeneClear\ClearSettings\PdoClearSettingsRepository;
use NeneClear\Tests\Support\SchemaFixture;
use PHPUnit\Framework\TestCase;

final class PdoClearSettingsRepositoryTest extends TestCase
{
    private string $dbPath;
    private DatabaseQueryExecutorInterface $real;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-settings-repo-', true) . '.sqlite';
        $this->real = DatabaseTestKit::sqlite($this->dbPath)->queryExecutor;
        SchemaFixture::createBankAccounts($this->real);
        SchemaFixture::createClearSettings($this->real);
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    /**
     * Re-saving unchanged settings must update in place, not duplicate-insert.
     *
     * On MySQL (without MYSQL_ATTR_FOUND_ROWS) a matched-but-unchanged UPDATE
     * reports 0 affected rows. The executor below reproduces that semantic on
     * top of SQLite so the regression is exercised in the normal test suite: a
     * repository that keys insert-vs-update off the affected-row count would
     * fall through to the INSERT and hit the organization_id primary key (#314).
     */
    public function testResavingUnchangedSettingsDoesNotDuplicateInsert(): void
    {
        $query = $this->mysqlAffectedRowsExecutor($this->real);
        $repo = new PdoClearSettingsRepository($query, new PdoBankAccountRepository($this->real));

        $settings = new ClearSettings(
            organizationId: 21,
            upstreamBaseUrl: 'https://invoice.example',
            upstreamTokenRef: 'NENE_INVOICE_BEARER_TOKEN',
            dunningMinIntervalDays: 7,
            fiscalYearEndMonth: 3,
        );

        $repo->save($settings);            // first save → INSERT
        $repo->save($settings);            // re-save identical → must UPDATE, not INSERT again

        $rows = $this->real->fetchAll('SELECT organization_id, dunning_min_interval_days FROM clear_settings');
        self::assertCount(1, $rows);
        self::assertSame(7, (int) $rows[0]['dunning_min_interval_days']);
    }

    /**
     * `save()` writes the whole row, dunning schedule included (#400).
     *
     * ⚠️ This **reverses** the guard #403 put here. That guard existed for one
     * stated reason: the settings API could not yet carry the schedule columns, so
     * a save built from a request body would have wiped whatever an operator had
     * set by hand. Its docblock said so explicitly — "adding the columns to the
     * write path must fail here **until the settings API and UI can carry them**".
     *
     * The API now carries them, so the condition is met and the guard is retired:
     * the repository persists exactly the entity it is handed, which is what makes
     * `PUT /admin/clear-settings` behave as the full replace it is documented to be
     * (#284). Leaving the columns out of the write path would instead mean the
     * endpoint could never turn scheduled dunning on at all.
     *
     * The corresponding risk moved rather than vanished — a caller that builds a
     * `ClearSettings` without the schedule now resets it. That is pinned from the
     * outside by
     * `ClearSettingsHttpTest::test_put_is_full_replace_so_an_omitted_field_is_reset_not_preserved`,
     * and the one screen that saves settings echoes the loaded values back.
     */
    public function testSaveWritesTheDunningScheduleItIsGiven(): void
    {
        $repo = new PdoClearSettingsRepository($this->real, new PdoBankAccountRepository($this->real));

        $repo->save(new ClearSettings(
            organizationId: 42,
            upstreamBaseUrl: 'https://invoice.example',
            upstreamTokenRef: 'NENE_INVOICE_BEARER_TOKEN',
            dunningMinIntervalDays: 7,
            dunningSchedule: new DunningSchedule(isEnabled: true, windowStartHour: 10, maxPerRun: 25),
        ));

        $inserted = $repo->findByOrganization(42);
        self::assertNotNull($inserted);
        self::assertTrue($inserted->dunningSchedule->isEnabled, 'INSERT must persist the schedule');
        self::assertSame(25, $inserted->dunningSchedule->maxPerRun);
        self::assertSame(10, $inserted->dunningSchedule->windowStartHour);

        // Same again on the UPDATE branch (the row now exists), and with the flag
        // going the other way — `false` is the value PDO renders as an empty string
        // if it is bound as a bool, which SQLite tolerates and MySQL/PostgreSQL do not.
        $repo->save(new ClearSettings(
            organizationId: 42,
            upstreamBaseUrl: 'https://invoice.example/v2',
            upstreamTokenRef: 'NENE_INVOICE_BEARER_TOKEN',
            dunningMinIntervalDays: 10,
            dunningSchedule: new DunningSchedule(isEnabled: false, maxPerRun: 3),
        ));

        $updated = $repo->findByOrganization(42);
        self::assertNotNull($updated);
        self::assertSame('https://invoice.example/v2', $updated->upstreamBaseUrl, 'the intended change must land');
        self::assertSame(10, $updated->dunningMinIntervalDays);
        self::assertFalse($updated->dunningSchedule->isEnabled, 'UPDATE must persist the schedule too');
        self::assertSame(3, $updated->dunningSchedule->maxPerRun);
    }

    public function testScheduleDefaultsAreReadBackForAnUntouchedOrganization(): void
    {
        $repo = new PdoClearSettingsRepository($this->real, new PdoBankAccountRepository($this->real));

        $repo->save(new ClearSettings(
            organizationId: 43,
            upstreamBaseUrl: '',
            upstreamTokenRef: '',
            dunningMinIntervalDays: 7,
        ));

        $schedule = $repo->findByOrganization(43)?->dunningSchedule;

        self::assertNotNull($schedule);
        self::assertFalse($schedule->isEnabled, 'scheduled dunning is off until explicitly enabled');
        self::assertSame(3, $schedule->initialAfterDays);
        self::assertSame(14, $schedule->reminderAfterDays);
        self::assertSame(30, $schedule->finalAfterDays);
        self::assertSame(9, $schedule->windowStartHour);
        self::assertSame(18, $schedule->windowEndHour);
        self::assertTrue($schedule->isWeekdaysOnly);
        self::assertSame(50, $schedule->maxPerRun);
    }

    /**
     * Wraps a real executor but reports 0 affected rows for every UPDATE, the
     * way MySQL reports a no-op UPDATE. The wrapped statement still runs, so the
     * existence check and the eventual INSERT observe real SQLite state.
     */
    private function mysqlAffectedRowsExecutor(DatabaseQueryExecutorInterface $inner): DatabaseQueryExecutorInterface
    {
        return new class ($inner) implements DatabaseQueryExecutorInterface {
            public function __construct(private DatabaseQueryExecutorInterface $inner)
            {
            }

            public function execute(string $sql, array $parameters = []): int
            {
                $affected = $this->inner->execute($sql, $parameters);

                return stripos(ltrim($sql), 'UPDATE') === 0 ? 0 : $affected;
            }

            public function insert(string $sql, array $parameters = []): int
            {
                return $this->inner->insert($sql, $parameters);
            }

            public function lastInsertId(): int
            {
                return $this->inner->lastInsertId();
            }

            public function fetchOne(string $sql, array $parameters = []): ?array
            {
                return $this->inner->fetchOne($sql, $parameters);
            }

            public function fetchAll(string $sql, array $parameters = []): array
            {
                return $this->inner->fetchAll($sql, $parameters);
            }
        };
    }
}
