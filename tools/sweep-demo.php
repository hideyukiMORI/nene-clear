<?php

declare(strict_types=1);

/**
 * Disposable-demo sweep (`Nene2\Demo` consumer, #275): expires demo orgs past
 * their TTL and reaps overflow beyond DEMO_MAX_ORGS. Thin by design — org
 * selection here (the product owns its schema), TTL/overflow policy in the
 * framework {@see \Nene2\Demo\DisposableDemoSweeper}, teardown in
 * {@see \NeneClear\Demo\DemoOrgReaper}. Also prunes stale per-IP rate-limit
 * state files. Run hourly from cron.
 *
 * Only orgs whose slug carries the demo prefix (`demo-…`) are ever selected;
 * the fixed showcase org (slug `demo`, no hyphen) and real tenants are
 * untouched.
 *
 * Usage: php tools/sweep-demo.php
 * Config-boundary entry point: DB wiring mirrors public_html/index.php.
 */

use Dotenv\Dotenv;
use Nene2\Config\DatabaseConfig;
use Nene2\Database\DatabaseConnectionFactoryInterface;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Demo\DemoConfig;
use Nene2\Demo\DemoOrgRecord;
use Nene2\Demo\DisposableDemoSweeper;
use Nene2\Http\UtcClock;
use NeneClear\Database\AdapterAwareQueryExecutor;
use NeneClear\Demo\DemoOrgReaper;
use NeneClear\Demo\DemoServiceProvider;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, 'This script must be run from the command line.' . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__);

if (is_file($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

$env = static fn (string $key, string $default = ''): string => (string) ($_ENV[$key] ?? getenv($key) ?: $default);

$adapter = $env('DB_ADAPTER', 'sqlite');
$connectionFactory = (static function () use ($env, $adapter, $root): ?DatabaseConnectionFactoryInterface {
    try {
        $config = match ($adapter) {
            'mysql' => new DatabaseConfig(
                url: $env('DATABASE_URL') ?: null,
                environment: $env('DB_ENV', 'production'),
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
                environment: $env('DB_ENV', 'production'),
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
    } catch (\Throwable) {
        return null;
    }
})();

if ($connectionFactory === null) {
    fwrite(STDERR, 'Database is not configured or unreachable. Check .env (DB_ADAPTER, DB_*).' . PHP_EOL);
    exit(1);
}

$query = new AdapterAwareQueryExecutor(new PdoDatabaseQueryExecutor($connectionFactory), $adapter);

$config = new DemoConfig(
    demoMode: true, // irrelevant to sweeping; the constructor validates the knobs below
    ttlHours: (int) ($env('DEMO_TTL_HOURS') ?: '3'),
    maxOrgs: (int) ($env('DEMO_MAX_ORGS') ?: '200'),
);

// ESCAPE '|' — a backslash escape char is parsed differently by MySQL string
// literals vs SQLite (#277); '|' needs no literal-level escaping anywhere.
$likePrefix = str_replace(['|', '%', '_'], ['||', '|%', '|_'], $config->slugPrefix) . '%';
$rows = $query->fetchAll(
    "SELECT id, created_at FROM organizations WHERE slug LIKE ? ESCAPE '|'",
    [$likePrefix],
);

$records = [];
foreach ($rows as $row) {
    $records[] = new DemoOrgRecord(
        orgId: (int) $row['id'],
        // created_at is written in UTC (MySQL CURRENT_TIMESTAMP on a UTC
        // server; SQLite CURRENT_TIMESTAMP is always UTC). Parse it as UTC
        // explicitly: on a host whose default timezone is ahead of UTC
        // (production runs Asia/Tokyo) a bare parse would read every fresh
        // org as hours old and expire it on the spot (#280, deal #72).
        createdAt: new DateTimeImmutable((string) $row['created_at'], new DateTimeZone('UTC')),
    );
}

$report = (new DisposableDemoSweeper($config, new DemoOrgReaper($query), new UtcClock()))->sweep($records);

// Prune stale per-IP throttle state (fully expired windows only).
$pruned = 0;
$cutoff = time() - DemoServiceProvider::THROTTLE_WINDOW_SECONDS * 2;
foreach (glob($root . '/var/rate-limits/*.json') ?: [] as $file) {
    $mtime = @filemtime($file);
    if ($mtime !== false && $mtime < $cutoff && @unlink($file)) {
        $pruned++;
    }
}

fwrite(STDOUT, sprintf(
    "demo sweep: %d org(s) total, %d expired, %d overflow, %d reaped, %d throttle file(s) pruned\n",
    count($records),
    count($report->expiredOrgIds),
    count($report->overflowOrgIds),
    count($report->reapedOrgIds),
    $pruned,
));

exit(0);
