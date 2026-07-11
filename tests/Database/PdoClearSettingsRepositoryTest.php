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
