<?php

declare(strict_types=1);

/**
 * Scheduled dunning sweep (#400). One-shot, run from cron:
 *
 *   (every 15 min) cd /path/to/app && php tools/send-scheduled-dunning.php >> var/log/dunning-cron.log 2>&1
 *   — the crontab schedule field is written in the ops runbook; it cannot be shown
 *     verbatim inside a PHP block comment because it would close the comment.
 *
 * Thin by design: the cron line carries no policy. Whether it is inside the send
 * window, which organizations opted in, how many notices one run may send and
 * which escalation stage an invoice has earned all live in the database, where an
 * operator can see and change them — see {@see \NeneClear\Dunning\SendScheduledDunningUseCase}.
 * `tools/sweep-demo.php` is the in-repo precedent for the DB wiring below (#275).
 *
 * Usage:
 *   php tools/send-scheduled-dunning.php [--dry-run] [--organization=<id>]
 *
 *   --dry-run          print what would be sent and send nothing. This is the
 *                      acceptance instrument (§9): run it once before the feature
 *                      is enabled for real. It takes no lock, so it can never
 *                      suppress the scheduled run behind it.
 *   --organization=ID  restrict the sweep to one organization.
 *
 * Exit codes: 0 on a completed run (including "nothing to do" and "already
 * running" — an overlapping tick is normal operation, §8), 1 when the run could
 * not start at all, 2 when at least one candidate failed to send.
 */

use Dotenv\Dotenv;
use Nene2\Config\DatabaseConfig;
use Nene2\Database\DatabaseConnectionFactoryInterface;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Database\PdoDatabaseTransactionManager;
use NeneClear\Database\AdapterAwareQueryExecutor;
use NeneClear\Database\AdapterAwareTransactionManager;
use NeneClear\Dunning\SendScheduledDunningUseCase;
use NeneClear\Http\ApplicationFactory;
use NeneClear\Http\ServiceResolver;

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

$isDryRun = in_array('--dry-run', $argv, true);
$organizationId = null;

foreach ($argv as $arg) {
    if (str_starts_with((string) $arg, '--organization=')) {
        $organizationId = (int) substr((string) $arg, strlen('--organization='));
    }
}

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
$transactionManager = new AdapterAwareTransactionManager(new PdoDatabaseTransactionManager($connectionFactory), $adapter);

// The upstream and SMTP config MUST be passed here. Left out, ApplicationFactory
// falls back to an empty FakeInvoiceUpstreamClient and a log-only mailer — the
// sweep would then find no invoices and exit 0, i.e. report a clean run while
// sending nothing. A scheduled job that silently does nothing is worse than one
// that fails, because nobody goes looking. These values mirror
// {@see \NeneClear\Http\RuntimeBootstrap} so the cron path and the request path
// talk to the same Invoice deployment and the same mail server.
$invoiceApiBaseUrl = $env('NENE_INVOICE_API_BASE_URL') ?: null;

$container = ApplicationFactory::container(
    query: $query,
    transactionManager: $transactionManager,
    jwtSecret: $env('NENE2_LOCAL_JWT_SECRET') ?: $env('NENE_CLEAR_JWT_SECRET'),
    smtpHost: $env('SMTP_HOST') ?: null,
    smtpPort: (int) $env('SMTP_PORT', '1025'),
    smtpUsername: $env('SMTP_USERNAME'),
    smtpPassword: $env('SMTP_PASSWORD'),
    smtpFromAddress: $env('SMTP_FROM_ADDRESS', 'noreply@nene-clear.dev'),
    smtpFromName: $env('SMTP_FROM_NAME', 'NeNe Clear'),
    invoiceApiBaseUrl: $invoiceApiBaseUrl,
    invoiceBearerToken: $env('NENE_INVOICE_BEARER_TOKEN'),
);

if ($invoiceApiBaseUrl === null) {
    // Refuse rather than sweep against an empty stand-in. Without an upstream
    // there are no invoices to dun, and "0 candidates" would be indistinguishable
    // from a correctly configured quiet day.
    fwrite(STDERR, 'NENE_INVOICE_API_BASE_URL is not set; refusing to run so a misconfiguration cannot look like a quiet day.' . PHP_EOL);
    exit(1);
}

// Random, not sequential: the run id groups one sweep's notices together in the
// audit trail, and must not be guessable as "the run before this one".
$runId = bin2hex(random_bytes(8));

$report = ServiceResolver::get($container, SendScheduledDunningUseCase::class)
    ->execute($runId, $isDryRun, $organizationId);

$stamp = (new DateTimeImmutable())->format('Y-m-d H:i:sP');
$mode = $report->isDryRun ? 'DRY-RUN' : 'LIVE';

fwrite(STDOUT, sprintf(
    '[%s] dunning run %s (%s): %d candidate(s), %d sent%s' . PHP_EOL,
    $stamp,
    $runId,
    $mode,
    $report->candidateCount(),
    $report->sentCount(),
    $report->isDryRun ? ' (would send)' : '',
));

// Every organization that was passed over says why. A run that printed only its
// sends would leave an operator unable to tell "nothing was due" from "the window
// was closed" — the single most likely question after enabling this.
foreach ($report->skippedOrganizations as $skippedOrgId => $reason) {
    fwrite(STDOUT, sprintf('  org %d: skipped (%s)' . PHP_EOL, $skippedOrgId, $reason));
}

foreach ($report->decisions as $decision) {
    fwrite(STDOUT, sprintf(
        '  org %d  %-12s  %-16s  %-8s  %s' . PHP_EOL,
        $decision->organizationId,
        $decision->invoiceNumber,
        $decision->outcome->value,
        $decision->stage !== null ? $decision->stage->value : '-',
        $decision->detail ?? '',
    ));
}

$failures = $report->failures();

if ($failures !== []) {
    fwrite(STDERR, sprintf('%d candidate(s) failed to send; the run continued.' . PHP_EOL, count($failures)));

    exit(2);
}

// "Already running" is not a failure: an overlapping cron tick is expected (§8),
// so a run that only skipped organizations still exits 0.
exit(0);
