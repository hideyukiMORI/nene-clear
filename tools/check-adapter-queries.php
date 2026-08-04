<?php

declare(strict_types=1);

/**
 * Adapter query smoke (#417). Run against a freshly migrated database to catch
 * SQL that only works on one adapter.
 *
 * Why this exists: `composer check` runs PHPUnit on **SQLite**, and the CI
 * migration jobs only apply **DDL**. Between them, no adapter-specific *query*
 * error is caught. Two real bugs found on 2026-08-04 in a single afternoon, both
 * with a fully green SQLite suite:
 *
 *   - `WHERE is_dunning_schedule_enabled = 1`
 *     pgsql: operator does not exist: boolean = integer
 *   - binding a PHP `false` into a boolean column (PDO renders it as '')
 *     mysql: Incorrect integer value: '' for column 'is_dunning_weekdays_only'
 *     pgsql: invalid input syntax for type boolean: ""
 *
 * ⚠️ **This is a representative sample, not coverage.** It exercises the shapes
 * that differ between adapters — boolean predicates and binds, LIMIT/OFFSET,
 * date comparison, COUNT, and insert-id retrieval — through the real repository
 * classes, on the real migrated schema. It does **not** run the test suite on
 * three adapters; a query this script does not touch is still unverified outside
 * SQLite. Treat a green run as "the known-dangerous shapes work here", nothing
 * more. Widening it is cheap: add a case below.
 *
 * Usage (DB_* env selects the target, same variables as the app):
 *   DB_ADAPTER=mysql DB_HOST=127.0.0.1 DB_PORT=3306 … php tools/check-adapter-queries.php
 *
 * Exit codes: 0 all cases passed, 1 could not connect, 2 at least one case failed.
 */

use Nene2\Config\DatabaseConfig;
use Nene2\Database\DatabaseConnectionFactoryInterface;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use NeneClear\BankImport\PdoBankAccountRepository;
use NeneClear\ClearSettings\ClearSettings;
use NeneClear\ClearSettings\DunningSchedule;
use NeneClear\ClearSettings\PdoClearSettingsRepository;
use NeneClear\Database\AdapterAwareQueryExecutor;
use NeneClear\Dunning\DunningNotice;
use NeneClear\Dunning\DunningNoticeFilter;
use NeneClear\Dunning\DunningStage;
use NeneClear\Dunning\PdoDunningNoticeRepository;
use NeneClear\Scheduler\PdoSchedulerLock;
use NeneClear\Security\Encryptor;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, 'This script must be run from the command line.' . PHP_EOL);
    exit(1);
}

$env = static fn (string $key, string $default = ''): string => (string) ($_ENV[$key] ?? getenv($key) ?: $default);
$root = dirname(__DIR__);
$adapter = $env('DB_ADAPTER', 'sqlite');

/** A tenant id this app will never allocate, so a stray row cannot collide with real data. */
const SMOKE_ORG = 999_000_001;

$connectionFactory = (static function () use ($env, $adapter, $root): ?DatabaseConnectionFactoryInterface {
    try {
        $config = match ($adapter) {
            'mysql' => new DatabaseConfig(
                url: $env('DATABASE_URL') ?: null,
                environment: $env('DB_ENV', 'test'),
                adapter: 'mysql',
                host: $env('DB_HOST', '127.0.0.1'),
                port: (int) $env('DB_PORT', '3306'),
                name: $env('DB_NAME', 'nene_clear'),
                user: $env('DB_USER', 'nene_clear'),
                password: $env('DB_PASSWORD'),
                charset: $env('DB_CHARSET', 'utf8mb4'),
            ),
            'pgsql' => new DatabaseConfig(
                url: $env('DATABASE_URL') ?: null,
                environment: $env('DB_ENV', 'test'),
                adapter: 'pgsql',
                host: $env('DB_HOST', '127.0.0.1'),
                port: (int) $env('DB_PORT', '5432'),
                name: $env('DB_NAME', 'nene_clear'),
                user: $env('DB_USER', 'nene_clear'),
                password: $env('DB_PASSWORD'),
                charset: $env('DB_CHARSET', 'utf8'),
            ),
            default => DatabaseConfig::sqlite($env('DB_NAME') ?: $root . '/database/nene_clear.sqlite3'),
        };

        return new PdoConnectionFactory($config);
    } catch (Throwable) {
        return null;
    }
})();

if ($connectionFactory === null) {
    fwrite(STDERR, 'Database is not configured or unreachable. Check DB_ADAPTER / DB_*.' . PHP_EOL);
    exit(1);
}

$query = new AdapterAwareQueryExecutor(new PdoDatabaseQueryExecutor($connectionFactory), $adapter);
$settings = new PdoClearSettingsRepository($query, new PdoBankAccountRepository($query, new Encryptor(null)));
$notices = new PdoDunningNoticeRepository($query);
$lock = new PdoSchedulerLock($query);

/** @var list<array{string, callable(): void}> $cases */
$cases = [
    // The bug: `WHERE <bool_col> = 1` is a type error on PostgreSQL, where Phinx
    // creates a real boolean. The bare predicate is the portable form.
    ['boolean predicate in WHERE', static function () use ($settings): void {
        $settings->findOrganizationIdsWithScheduledDunning();
    }],

    // The bug: PDO renders a bound `false` as '', which SQLite accepts and the
    // other two reject. Both branches of the upsert bind booleans, so both run.
    ['boolean bind — INSERT then UPDATE', static function () use ($settings): void {
        $write = static fn (bool $enabled, bool $weekdaysOnly, int $cap): ClearSettings => new ClearSettings(
            organizationId: SMOKE_ORG,
            upstreamBaseUrl: 'https://smoke.invalid',
            upstreamTokenRef: 'SMOKE',
            dunningMinIntervalDays: 7,
            dunningSchedule: new DunningSchedule(isEnabled: $enabled, isWeekdaysOnly: $weekdaysOnly, maxPerRun: $cap),
        );

        $settings->save($write(true, false, 25));   // INSERT
        $settings->save($write(false, true, 3));    // UPDATE, both flags flipped

        $back = $settings->findByOrganization(SMOKE_ORG);

        if ($back === null || $back->dunningSchedule->isEnabled !== false || $back->dunningSchedule->maxPerRun !== 3) {
            throw new RuntimeException('booleans did not round-trip');
        }
    }],

    // Insert-id retrieval differs (lastInsertId vs RETURNING); pagination and
    // COUNT(*) casing differ too. Exercised through the repository that ships.
    ['insert id, LIMIT/OFFSET, COUNT(*)', static function () use ($notices): void {
        $id = $notices->save(new DunningNotice(
            organizationId: SMOKE_ORG,
            invoiceId: 1,
            invoiceNumber: 'SMOKE-001',
            clientId: 1,
            recipientEmail: 'smoke@invalid.example',
            outstandingCents: 1000,
            dueAt: '2026-01-31',
            channel: 'log',
            templateVersion: '1.0',
            stage: DunningStage::Reminder,
            sentBy: 0,
            sentAt: '2026-02-01 09:00:00',
        ));

        if ($id <= 0) {
            throw new RuntimeException('save() did not return an insert id');
        }

        $notices->findByOrganization(SMOKE_ORG, new DunningNoticeFilter(), 10, 0);
        $notices->countByOrganization(SMOKE_ORG, new DunningNoticeFilter());

        if ($notices->findById(SMOKE_ORG, $id)?->stage !== DunningStage::Reminder) {
            throw new RuntimeException('enum column did not round-trip');
        }
    }],

    // Date/datetime comparison in a WHERE, plus ORDER BY on a date column.
    ['date range filter', static function () use ($notices): void {
        $notices->findByOrganization(
            SMOKE_ORG,
            new DunningNoticeFilter(sentFrom: '2026-01-01', sentTo: '2026-12-31'),
            10,
            0,
        );
    }],

    // The scheduler's mutual exclusion is a single atomic upsert — the statement
    // most likely to be written in one adapter's dialect.
    ['scheduler lock acquire/release', static function () use ($lock): void {
        $now = new DateTimeImmutable('2026-02-01 09:00:00');

        if (!$lock->acquire('smoke:' . SMOKE_ORG, 'token-a', 60, $now)) {
            throw new RuntimeException('could not take a free lock');
        }

        if ($lock->acquire('smoke:' . SMOKE_ORG, 'token-b', 60, $now)) {
            throw new RuntimeException('a held lock was handed out twice');
        }

        $lock->release('smoke:' . SMOKE_ORG, 'token-a');
    }],
];

fwrite(STDOUT, sprintf('Adapter query smoke — %s' . PHP_EOL, $adapter));

$failed = 0;

foreach ($cases as [$name, $case]) {
    try {
        $case();
        fwrite(STDOUT, sprintf("  ok    %s\n", $name));
    } catch (Throwable $e) {
        ++$failed;
        fwrite(STDOUT, sprintf("  FAIL  %s\n        %s\n", $name, str_replace("\n", ' ', $e->getPrevious()?->getMessage() ?? $e->getMessage())));
    }
}

// Best-effort cleanup. A failure here is not the smoke's verdict — the CI
// database is thrown away after the job, and a local run against a dev database
// only leaves rows under the reserved SMOKE_ORG id.
try {
    $query->execute('DELETE FROM dunning_notices WHERE organization_id = ?', [SMOKE_ORG]);
    $query->execute('DELETE FROM clear_settings WHERE organization_id = ?', [SMOKE_ORG]);
    $query->execute('DELETE FROM scheduler_locks WHERE lock_key = ?', ['smoke:' . SMOKE_ORG]);
} catch (Throwable $e) {
    fwrite(STDERR, 'warning: smoke cleanup failed: ' . $e->getMessage() . PHP_EOL);
}

if ($failed > 0) {
    fwrite(STDERR, sprintf('%d case(s) failed on %s.' . PHP_EOL, $failed, $adapter));

    exit(2);
}

fwrite(STDOUT, sprintf('All %d case(s) passed on %s.' . PHP_EOL, count($cases), $adapter));

exit(0);
