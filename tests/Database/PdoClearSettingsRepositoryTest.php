<?php

declare(strict_types=1);

namespace NeneClear\Tests\Database;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Testing\DatabaseTestKit;
use NeneClear\BankImport\PdoBankAccountRepository;
use NeneClear\ClearSettings\ClearSettings;
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
     * Saving unrelated settings must not disturb the dunning schedule (#400).
     *
     * `PUT /admin/clear-settings` is a full replace (#284) and its request body
     * cannot carry the schedule columns yet, so the repository deliberately
     * leaves them out of the UPDATE/INSERT. Without that, an operator who
     * enables scheduled dunning and later edits a bank account would silently
     * turn it back off — with no error and no audit trace of the change. This
     * test is the guard: adding the columns to the write path must fail here
     * until the settings API and UI can carry them.
     */
    public function testSavingUnrelatedSettingsLeavesTheDunningScheduleIntact(): void
    {
        $repo = new PdoClearSettingsRepository($this->real, new PdoBankAccountRepository($this->real));

        $repo->save(new ClearSettings(
            organizationId: 42,
            upstreamBaseUrl: 'https://invoice.example',
            upstreamTokenRef: 'NENE_INVOICE_BEARER_TOKEN',
            dunningMinIntervalDays: 7,
        ));

        // The operator turns the schedule on (today: by hand; later: via the settings UI).
        $this->real->execute(
            'UPDATE clear_settings SET is_dunning_schedule_enabled = 1, dunning_max_per_run = 25, '
            . 'dunning_window_start_hour = 10 WHERE organization_id = ?',
            [42],
        );

        // …then saves an unrelated change. The entity carries default schedule values.
        $repo->save(new ClearSettings(
            organizationId: 42,
            upstreamBaseUrl: 'https://invoice.example/v2',
            upstreamTokenRef: 'NENE_INVOICE_BEARER_TOKEN',
            dunningMinIntervalDays: 10,
        ));

        $reloaded = $repo->findByOrganization(42);

        self::assertNotNull($reloaded);
        self::assertSame('https://invoice.example/v2', $reloaded->upstreamBaseUrl, 'the intended change must land');
        self::assertSame(10, $reloaded->dunningMinIntervalDays);
        self::assertTrue($reloaded->dunningSchedule->isEnabled, 'the schedule must survive an unrelated save');
        self::assertSame(25, $reloaded->dunningSchedule->maxPerRun);
        self::assertSame(10, $reloaded->dunningSchedule->windowStartHour);
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
