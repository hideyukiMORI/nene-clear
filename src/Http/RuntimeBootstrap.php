<?php

declare(strict_types=1);

namespace NeneClear\Http;

use Dotenv\Dotenv;
use Nene2\Config\AppConfig;
use Nene2\Config\ConfigException;
use Nene2\Config\ConfigLoader;
use Nene2\Database\DatabaseConnectionFactoryInterface;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Database\PdoDatabaseTransactionManager;
use Nene2\Database\Preflight\DefaultDatabaseCandidateInspector;
use NeneClear\Auth\AuthServiceProvider;
use NeneClear\Database\AdapterAwareQueryExecutor;
use NeneClear\Database\AdapterAwareTransactionManager;
use NeneClear\Database\ApplicationDatabaseIdentity;
use NeneClear\Database\CandidateProfiles;
use NeneClear\Database\MigrationVersions;
use NeneClear\InvoiceUpstream\DemoInvoiceUpstreamFixture;
use NeneClear\InvoiceUpstream\FakeInvoiceUpstreamClient;
use NeneClear\InvoiceUpstream\InvoiceUpstreamClientInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Composition root for the HTTP runtime (#294): loads configuration through
 * the framework {@see ConfigLoader} → {@see AppConfig} (fleet standard —
 * invoice/deal/vault shape), resolves the JWT secret via
 * {@see AuthServiceProvider::resolveJwtSecretFromConfig()} (fail-close), and
 * assembles {@see ApplicationFactory::create()}. The front controller only
 * builds the PSR-7 request and emits the response.
 *
 * Resilience contract (unchanged from the previous inline wiring): when the
 * database is unconfigured/unreachable or no JWT secret resolves, the app
 * still boots and serves the public/health surface — admin routes are simply
 * not mounted (see ApplicationFactory).
 */
final readonly class RuntimeBootstrap
{
    /**
     * Product defaults layered under the environment (ConfigLoader semantics):
     * Clear's dev default is a zero-config SQLite file, and its Problem
     * Details live under the product domain. `DB_NAME` is completed per
     * adapter in {@see configDefaults()}.
     *
     * @var array<string, string>
     */
    private const array CONFIG_DEFAULTS = [
        'APP_NAME' => 'NeNe Clear',
        'PROBLEM_DETAILS_BASE_URL' => 'https://nene-clear.dev/problems/',
        'DB_ADAPTER' => 'sqlite',
        'DB_USER' => 'nene_clear',
    ];

    /**
     * Conservative values that make {@see ConfigLoader::load()} always succeed
     * when the environment carries malformed values (bad DB_PORT, non-boolean
     * APP_DEBUG, …). Only used for the degraded retry: the database is then
     * treated as unavailable, so the app serves the public/health surface only
     * — the same resilience the previous inline wiring provided.
     *
     * @var array<string, string>
     */
    private const array DEGRADED_CONFIG = [
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'DATABASE_URL' => '',
        'DB_ADAPTER' => 'sqlite',
        'DB_NAME' => ':memory:',
        'DB_PORT' => '1',
        'DEMO_MODE' => '',
        'DEMO_TTL_HOURS' => '3',
        'DEMO_MAX_ORGS' => '200',
        'DEMO_SLUG_ATTEMPTS' => '5',
        'DEMO_SLUG_PREFIX' => 'demo-',
        'NENE2_LOCAL_JWT_SECRET' => '',
        'NENE2_ALLOW_DEV_SECRET' => '',
    ];

    /**
     * Builds the fully-wired PSR-15 application for a project root.
     *
     * @param array<string, string> $envOverrides Test seam: values layered over
     *        the environment exactly like {@see ConfigLoader::load()} overrides.
     */
    public static function application(string $projectRoot, array $envOverrides = []): RequestHandlerInterface
    {
        $env = static fn (string $key, string $default = ''): string => (string) ($envOverrides[$key] ?? $_ENV[$key] ?? (getenv($key) ?: $default));

        // Load .env before anything reads the environment (idempotent — the
        // ConfigLoader safeLoad below never overwrites what is already set).
        if (is_file($projectRoot . '/.env')) {
            Dotenv::createImmutable($projectRoot)->safeLoad();
        }

        $loader = new ConfigLoader($projectRoot, self::configDefaults($projectRoot, $env));

        // Resilience contract: malformed configuration must not take /health
        // down. On a ConfigException, retry with conservative values and treat
        // the database as unavailable (→ public/health surface only), exactly
        // like the previous inline wiring's guarded DB construction.
        $degraded = false;
        try {
            $config = $loader->load($envOverrides);
        } catch (ConfigException) {
            $degraded = true;
            $config = $loader->load(array_merge(self::DEGRADED_CONFIG, ['APP_NAME' => 'NeNe Clear']));
        }

        // Transitional fallback (#285, one release): the pre-fleet env name
        // still resolves when the canonical NENE2_LOCAL_JWT_SECRET is unset.
        // Probed only after the first load() so the value can come from .env.
        if (!$degraded && ($config->localJwtSecret ?? '') === '') {
            $legacySecret = $env('NENE_CLEAR_JWT_SECRET');
            if ($legacySecret !== '') {
                $config = $loader->load(array_merge($envOverrides, ['NENE2_LOCAL_JWT_SECRET' => $legacySecret]));
            }
        }

        // Fleet-standard fail-close secret resolution (#285): null keeps the
        // admin surface unmounted (health-only branch in ApplicationFactory).
        $jwtSecret = AuthServiceProvider::resolveJwtSecretFromConfig($config);

        // Resilient DB wiring: if the database is unconfigured or unreachable,
        // the app still boots and serves /health. The executor and transaction
        // manager share one connection factory so transactional() can open its
        // own connection (#122).
        $adapter = trim($config->database->adapter) !== '' ? trim($config->database->adapter) : 'sqlite';
        $connectionFactory = $degraded ? null : self::connectionFactory($config);

        // Decorate so INSERT … RETURNING id is used on PostgreSQL
        // (PDO::lastInsertId() returns 0 there). No-op for mysql/sqlite.
        $query = $connectionFactory !== null
            ? new AdapterAwareQueryExecutor(new PdoDatabaseQueryExecutor($connectionFactory), $adapter)
            : null;
        $transactionManager = $connectionFactory !== null
            ? new AdapterAwareTransactionManager(new PdoDatabaseTransactionManager($connectionFactory), $adapter)
            : null;

        $smtpHost = $env('SMTP_HOST') ?: null;
        $invoiceApiBaseUrl = $env('NENE_INVOICE_API_BASE_URL') ?: null;

        // Candidate-database preflight (#183): candidates come only from the
        // env allowlist (NENE_CLEAR_DB_CANDIDATE_*), never the request body.
        $candidateInspector = new DefaultDatabaseCandidateInspector(
            applicationMigrationVersions: MigrationVersions::fromDirectory($projectRoot . '/database/migrations'),
            // This app's Phinx ledger is `phinxlog` (phinx.php default_migration_table),
            // not the inspector's `phinx_log` default — required for schema recognition.
            ledgerTable: 'phinxlog',
            applicationIdentity: ApplicationDatabaseIdentity::identity(),
        );

        $appVersion = is_file($projectRoot . '/VERSION') ? trim((string) file_get_contents($projectRoot . '/VERSION')) : '';

        return ApplicationFactory::create(
            debug: $config->debug,
            allowedOrigins: [],
            query: $query,
            transactionManager: $transactionManager,
            jwtSecret: $jwtSecret,
            invoiceClient: self::demoUpstreamFixture($env, $invoiceApiBaseUrl),
            smtpHost: $smtpHost,
            smtpPort: (int) $env('SMTP_PORT', '1025'),
            smtpUsername: $env('SMTP_USERNAME'),
            smtpPassword: $env('SMTP_PASSWORD'),
            smtpFromAddress: $env('SMTP_FROM_ADDRESS', 'noreply@nene-clear.dev'),
            smtpFromName: $env('SMTP_FROM_NAME', 'NeNe Clear'),
            invoiceApiBaseUrl: $invoiceApiBaseUrl,
            invoiceBearerToken: $env('NENE_INVOICE_BEARER_TOKEN'),
            // Absolute base for invitation e-mail links. Same-origin in production;
            // the dev frontend (Vite) runs on a separate port, so default to it locally.
            appBaseUrl: $env('NENE_CLEAR_APP_URL', 'http://localhost:5383'),
            // Optional encryption-at-rest key (base64 of 32 bytes). When unset,
            // sensitive fields are stored as-is (#192/#195).
            encryptionKey: $env('NENE_CLEAR_ENCRYPTION_KEY'),
            machineApiKey: $config->machineApiKey,
            appVersion: $appVersion !== '' ? $appVersion : null,
            databaseCandidateInspector: $candidateInspector,
            databaseCandidateProfiles: CandidateProfiles::fromEnv(array_merge($_ENV, $envOverrides)),
            // Strict opt-in parsing (a DEMO_MODE typo fails closed) is owned by
            // ConfigLoader; the framework defaults (3 h / 200 orgs) apply when unset.
            demoConfig: $config->demo,
        );
    }

    /**
     * Completes the product defaults with the adapter-dependent database name
     * and port. `DB_ADAPTER` may itself come from `.env` (loaded above), so
     * this runs after the dotenv load.
     *
     * @param callable(string, string=): string $env
     *
     * @return array<string, string>
     */
    private static function configDefaults(string $projectRoot, callable $env): array
    {
        $adapter = strtolower(trim($env('DB_ADAPTER', 'sqlite'))) ?: 'sqlite';

        return array_merge(self::CONFIG_DEFAULTS, match ($adapter) {
            'mysql' => ['DB_NAME' => 'nene_clear'],
            'pgsql' => ['DB_NAME' => 'nene_clear', 'DB_PORT' => '5432'],
            default => ['DB_NAME' => $projectRoot . '/database/nene_clear.sqlite3'],
        });
    }

    private static function connectionFactory(AppConfig $config): ?DatabaseConnectionFactoryInterface
    {
        try {
            // ConfigLoader already parsed and validated the database values
            // (adapter-aware: sqlite skips host/user/charset validation).
            return new PdoConnectionFactory($config->database);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Demo upstream fixture (#260): when NENE_CLEAR_DEMO_UPSTREAM=1 and no real
     * upstream is configured, pre-populate the standalone fake client with
     * T-relative demo invoices/clients so upstream match suggestions and live
     * dunning sends work in a standalone demo. Off by default; a configured
     * real upstream always wins.
     *
     * @param callable(string, string=): string $env
     */
    private static function demoUpstreamFixture(callable $env, ?string $invoiceApiBaseUrl): ?InvoiceUpstreamClientInterface
    {
        if ($env('NENE_CLEAR_DEMO_UPSTREAM') !== '1' || $invoiceApiBaseUrl !== null) {
            return null;
        }

        $client = new FakeInvoiceUpstreamClient();
        DemoInvoiceUpstreamFixture::populate($client, new \DateTimeImmutable('today'));

        return $client;
    }
}
