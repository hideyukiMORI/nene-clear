<?php

declare(strict_types=1);

// Phinx tooling config. Migration tooling is the config boundary, so raw env
// access is allowed here (docs/development/nene2-compliance.md §15).
// `composer check` does not run migrations; tests create their own schema.

// Load .env — the same file public_html/index.php reads — so `composer
// migrations:migrate` targets the DB the app actually uses. Without this the
// getenv() calls below miss .env and fall back to sqlite, so a MySQL/pgsql
// setup migrates the wrong database and leaves the real one empty (#226).
// createUnsafeImmutable uses putenv() so the getenv() calls see it; Immutable
// means a real exported env var still wins.
require_once __DIR__ . '/vendor/autoload.php';
if (is_file(__DIR__ . '/.env')) {
    Dotenv\Dotenv::createUnsafeImmutable(__DIR__)->safeLoad();
}

$adapter = getenv('DB_ADAPTER') ?: 'sqlite';

$environment = match ($adapter) {
    'mysql' => [
        'adapter' => 'mysql',
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'name' => getenv('DB_NAME') ?: 'nene_clear',
        'user' => getenv('DB_USER') ?: 'nene_clear',
        'pass' => getenv('DB_PASSWORD') ?: 'nene_clear',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    ],
    // PostgreSQL: omit `charset` — Phinx/pgsql does not use it (encoding comes
    // from server_encoding). See NENE2 docs/howto/use-postgresql.md.
    'pgsql' => [
        'adapter' => 'pgsql',
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'name' => getenv('DB_NAME') ?: 'nene_clear',
        'user' => getenv('DB_USER') ?: 'nene_clear',
        'pass' => getenv('DB_PASSWORD') ?: 'nene_clear',
        'port' => (int) (getenv('DB_PORT') ?: 5432),
    ],
    default => [
        'adapter' => 'sqlite',
        'name' => getenv('DB_NAME') ?: __DIR__ . '/database/nene_clear',
    ],
};

return [
    'paths' => [
        'migrations' => __DIR__ . '/database/migrations',
        'seeds' => __DIR__ . '/database/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'local',
        'local' => $environment,
    ],
];
