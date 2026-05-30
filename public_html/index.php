<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Nene2\Config\DatabaseConfig;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Http\ResponseEmitter;
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
// still boots and serves /health. Authenticated routes activate only when both
// the executor and a JWT secret are available (see ApplicationFactory).
$query = (static function () use ($env): ?DatabaseQueryExecutorInterface {
    try {
        $adapter = $env('DB_ADAPTER', 'sqlite');
        $config = $adapter === 'mysql'
            ? new DatabaseConfig(
                url: $env('DATABASE_URL') ?: null,
                environment: $env('DB_ENV', 'production'),
                adapter: 'mysql',
                host: $env('DB_HOST', '127.0.0.1'),
                port: (int) $env('DB_PORT', '3306'),
                name: $env('DB_NAME', 'nene_clear'),
                user: $env('DB_USER', 'nene_clear'),
                password: $env('DB_PASSWORD'),
                charset: $env('DB_CHARSET', 'utf8mb4'),
            )
            : DatabaseConfig::sqlite($env('DB_NAME') ?: dirname(__DIR__) . '/database/nene_clear.sqlite3');

        return new PdoDatabaseQueryExecutor(new PdoConnectionFactory($config));
    } catch (\Throwable) {
        return null;
    }
})();

$smtpHost = $env('SMTP_HOST') ?: null;
$invoiceApiBaseUrl = $env('NENE_INVOICE_API_BASE_URL') ?: null;

$application = ApplicationFactory::create(
    debug: $debug,
    allowedOrigins: [],
    query: $query,
    jwtSecret: $jwtSecret,
    smtpHost: $smtpHost,
    smtpPort: (int) $env('SMTP_PORT', '1025'),
    smtpUsername: $env('SMTP_USERNAME'),
    smtpPassword: $env('SMTP_PASSWORD'),
    smtpFromAddress: $env('SMTP_FROM_ADDRESS', 'noreply@nene-clear.dev'),
    smtpFromName: $env('SMTP_FROM_NAME', 'NeNe Clear'),
    invoiceApiBaseUrl: $invoiceApiBaseUrl,
    invoiceBearerToken: $env('NENE_INVOICE_BEARER_TOKEN'),
);

$psr17 = new Psr17Factory();
$request = (new ServerRequestCreator($psr17, $psr17, $psr17, $psr17))->fromGlobals();

// SPA fallback: serve the built index.html for browser navigation requests.
// API calls carry Accept: application/json and are handled by the PHP app.
// Static assets under /assets/ are served directly by the web server.
$path = $request->getUri()->getPath() ?: '/';
$acceptHeader = $request->getHeaderLine('Accept');
$wantHtml = str_contains($acceptHeader, 'text/html') || str_contains($acceptHeader, '*/*');
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
