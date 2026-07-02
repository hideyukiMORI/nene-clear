<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Nene2\Config\DatabaseConfig;
use Nene2\Database\DatabaseConnectionFactoryInterface;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Database\PdoDatabaseTransactionManager;
use Nene2\Http\ResponseEmitter;
use NeneClear\Database\AdapterAwareQueryExecutor;
use NeneClear\Database\AdapterAwareTransactionManager;
use NeneClear\Http\ApplicationFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);

// Config boundary: raw env access stays here, not in application code.
if (is_file($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

$env = static fn (string $key, string $default = ''): string => (string) ($_ENV[$key] ?? getenv($key) ?: $default);

$debug = $env('APP_DEBUG', 'false') === 'true';
$jwtSecret = $env('NENE_CLEAR_JWT_SECRET') ?: null;

// Resilient DB wiring: if the database is unconfigured or unreachable, the app
// still boots and serves /health. Authenticated routes activate only when the
// executor, the transaction manager, and a JWT secret are all available (see
// ApplicationFactory). The executor and the transaction manager share one
// connection factory so transactional() can open its own connection (Issue #122).
$adapter = $env('DB_ADAPTER', 'sqlite');

$connectionFactory = (static function () use ($env, $adapter): ?DatabaseConnectionFactoryInterface {
    try {
        // `charset` is intentionally omitted for pgsql — the DSN ignores it and
        // PostgreSQL derives the client encoding from server_encoding (UTF-8 by
        // default). See NENE2 docs/howto/use-postgresql.md.
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
                // Non-empty to satisfy DatabaseConfig validation; the pgsql DSN
                // ignores it (encoding comes from server_encoding, UTF-8).
                charset: $env('DB_CHARSET', 'utf8'),
            ),
            default => DatabaseConfig::sqlite($env('DB_NAME') ?: dirname(__DIR__) . '/database/nene_clear.sqlite3'),
        };

        return new PdoConnectionFactory($config);
    } catch (\Throwable) {
        return null;
    }
})();

// Decorate so INSERT … RETURNING id is used on PostgreSQL (PDO::lastInsertId()
// returns 0 there). No-op for mysql/sqlite. See NeneClear\Database decorators.
$query = $connectionFactory !== null
    ? new AdapterAwareQueryExecutor(new PdoDatabaseQueryExecutor($connectionFactory), $adapter)
    : null;
$transactionManager = $connectionFactory !== null
    ? new AdapterAwareTransactionManager(new PdoDatabaseTransactionManager($connectionFactory), $adapter)
    : null;

$smtpHost = $env('SMTP_HOST') ?: null;
$invoiceApiBaseUrl = $env('NENE_INVOICE_API_BASE_URL') ?: null;

// Machine surface (issue #182): the repo VERSION file is this app's release
// version, surfaced on the auth-gated GET /machine/health so deployment tooling
// (e.g. NeNe Suite update tracking) can read the installed version.
$machineApiKey = $env('NENE2_MACHINE_API_KEY') ?: null;
$appVersion = is_file($root . '/VERSION') ? trim((string) file_get_contents($root . '/VERSION')) : '';

$application = ApplicationFactory::create(
    debug: $debug,
    allowedOrigins: [],
    query: $query,
    transactionManager: $transactionManager,
    jwtSecret: $jwtSecret,
    smtpHost: $smtpHost,
    smtpPort: (int) $env('SMTP_PORT', '1025'),
    smtpUsername: $env('SMTP_USERNAME'),
    smtpPassword: $env('SMTP_PASSWORD'),
    smtpFromAddress: $env('SMTP_FROM_ADDRESS', 'noreply@nene-clear.dev'),
    smtpFromName: $env('SMTP_FROM_NAME', 'NeNe Clear'),
    invoiceApiBaseUrl: $invoiceApiBaseUrl,
    invoiceBearerToken: $env('NENE_INVOICE_BEARER_TOKEN'),
    // Absolute base for invitation e-mail links. Same-origin in production; the
    // dev frontend (Vite) runs on a separate port, so default to it locally.
    appBaseUrl: $env('NENE_CLEAR_APP_URL', 'http://localhost:5383'),
    // Optional encryption-at-rest key (base64 of 32 bytes). When unset, sensitive
    // fields are stored as-is; when set, they are encrypted on write (#192/#195).
    encryptionKey: $env('NENE_CLEAR_ENCRYPTION_KEY'),
    machineApiKey: $machineApiKey,
    appVersion: $appVersion !== '' ? $appVersion : null,
);

$psr17 = new Psr17Factory();
$request = (new ServerRequestCreator($psr17, $psr17, $psr17, $psr17))->fromGlobals();

// SPA fallback: serve the built index.html for browser navigation requests.
// Browsers send Accept: text/html,... — API clients send Accept: application/json
// or Accept: */* only. We only intercept when text/html is explicit.
$path = $request->getUri()->getPath() ?: '/';
$acceptHeader = $request->getHeaderLine('Accept');
$wantHtml = str_contains($acceptHeader, 'text/html')
    && !str_contains($acceptHeader, 'application/json');
$isSpaRoute = $wantHtml
    && $request->getMethod() === 'GET'
    && !str_starts_with($path, '/health')
    && !str_starts_with($path, '/machine')
    && !str_starts_with($path, '/assets/');

if ($isSpaRoute) {
    $spaIndex = __DIR__ . '/assets/index.html';
    if (is_file($spaIndex)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($spaIndex);
        exit;
    }
}

$response = $application->handle($request);

(new ResponseEmitter())->emit($response);
