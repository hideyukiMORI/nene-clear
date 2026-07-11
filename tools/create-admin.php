<?php

declare(strict_types=1);

/**
 * Bootstrap CLI: create the first organization + admin user on a fresh install.
 *
 * A fresh database has no accounts, and every user-creating endpoint requires an
 * already-authenticated admin — a chicken-and-egg. This one-off, audited command
 * breaks it by reusing the same domain use cases the HTTP app uses, so bcrypt
 * hashing, email/slug uniqueness, the atomic write+audit, and the registered
 * roles/status all behave identically. Run it once after
 * `composer migrations:migrate`.
 *
 * Usage:
 *   php tools/create-admin.php --org-name "ACME Inc" --email admin@acme.example
 *   php tools/create-admin.php --org-name "ACME Inc" --org-slug acme \
 *       --email admin@acme.example --password '…'
 *   ADMIN_PASSWORD='…' php tools/create-admin.php --org-name "ACME" --email …
 *   php tools/create-admin.php --role superadmin --email root@example  # no org
 *
 * The password comes from --password, else the ADMIN_PASSWORD env var, else an
 * interactive hidden prompt. Database config is read from .env, like the app.
 *
 * Config-boundary entry point (docs/development/nene2-compliance.md §15): raw env
 * access and the DB wiring below mirror public_html/index.php on purpose.
 */

use Dotenv\Dotenv;
use Nene2\Config\DatabaseConfig;
use Nene2\Database\DatabaseConnectionFactoryInterface;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Database\PdoDatabaseTransactionManager;
use NeneClear\Auth\Role;
use NeneClear\Database\AdapterAwareQueryExecutor;
use NeneClear\Database\AdapterAwareTransactionManager;
use NeneClear\Http\ApplicationFactory;
use NeneClear\Http\ServiceResolver;
use NeneClear\Organization\CreateOrganizationInput;
use NeneClear\Organization\CreateOrganizationUseCaseInterface;
use NeneClear\Organization\OrganizationAlreadyExistsException;
use NeneClear\User\CreateUserInput;
use NeneClear\User\CreateUserUseCaseInterface;
use NeneClear\User\UserAlreadyExistsException;
use NeneClear\User\UserRepositoryInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, 'This script must be run from the command line.' . PHP_EOL);
    exit(1);
}

$root = dirname(__DIR__);

$fail = static function (string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

$usage = static function (int $code): never {
    $lines = [
        'Create the first organization + admin user for a fresh NeNe Clear install.',
        '',
        'Usage:',
        '  php tools/create-admin.php --org-name NAME --email EMAIL [options]',
        '',
        'Options:',
        '  --org-name NAME   Organization display name (required unless --role superadmin)',
        '  --org-slug SLUG   URL-safe org id (default: derived from --org-name)',
        '  --email EMAIL     Admin login email (required)',
        '  --password PASS   Admin password (default: ADMIN_PASSWORD env, else hidden prompt)',
        '  --role ROLE       admin (default) | superadmin | member | viewer',
        '  -h, --help        Show this help',
    ];
    fwrite($code === 0 ? STDOUT : STDERR, implode(PHP_EOL, $lines) . PHP_EOL);
    exit($code);
};

$slugify = static function (string $name): string {
    $slug = (string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name));

    return trim($slug, '-');
};

$promptPassword = static function () use ($fail): string {
    if (!stream_isatty(STDIN)) {
        $fail('No --password given and no interactive terminal to prompt. Set --password or ADMIN_PASSWORD.');
    }

    fwrite(STDOUT, 'Admin password: ');
    $sttyState = shell_exec('stty -g');
    shell_exec('stty -echo');
    $input = fgets(STDIN);
    if (is_string($sttyState)) {
        shell_exec('stty ' . trim($sttyState));
    }
    fwrite(STDOUT, PHP_EOL);

    return is_string($input) ? trim($input) : '';
};

// --- Parse arguments (--key value or --key=value) ------------------------------
$rawArgv = $_SERVER['argv'] ?? [];
$args = array_slice(is_array($rawArgv) ? array_values($rawArgv) : [], 1);
$count = count($args);
/** @var array<string, string> $opts */
$opts = [];
for ($i = 0; $i < $count; $i++) {
    $arg = (string) $args[$i];
    if ($arg === '-h' || $arg === '--help') {
        $usage(0);
    }
    if (!str_starts_with($arg, '--')) {
        fwrite(STDERR, sprintf('Unexpected argument: %s', $arg) . PHP_EOL);
        $usage(1);
    }
    $body = substr($arg, 2);
    if (str_contains($body, '=')) {
        [$key, $value] = explode('=', $body, 2);
    } else {
        $key = $body;
        $next = $i + 1 < $count ? (string) $args[$i + 1] : '';
        if ($next !== '' && !str_starts_with($next, '--')) {
            $value = $next;
            $i++;
        } else {
            $value = '';
        }
    }
    $opts[$key] = $value;
}

// --- Load .env and resolve inputs ---------------------------------------------
if (is_file($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

$env = static fn (string $key, string $default = ''): string => (string) ($_ENV[$key] ?? getenv($key) ?: $default);

$role = Role::tryFrom($opts['role'] ?? 'admin');
if ($role === null) {
    $fail('Invalid --role. Valid values: superadmin, admin, member, viewer.');
}

$email = trim($opts['email'] ?? '');
if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $fail('A valid --email is required.');
}

$isSuperadmin = $role === Role::Superadmin;
$orgName = trim($opts['org-name'] ?? '');
$orgSlug = trim($opts['org-slug'] ?? '');

if (!$isSuperadmin) {
    if ($orgName === '') {
        $fail('--org-name is required (omit only with --role superadmin).');
    }
    if ($orgSlug === '') {
        $orgSlug = $slugify($orgName);
    }
    if ($orgSlug === '') {
        $fail('Could not derive a slug from --org-name; pass --org-slug explicitly (a-z, 0-9, hyphen).');
    }
}

$password = $opts['password'] ?? ($env('ADMIN_PASSWORD') ?: null);
if ($password === null || $password === '') {
    $password = $promptPassword();
}
if (strlen($password) < 12) {
    $fail('Password must be at least 12 characters.');
}

// --- Database wiring (mirrors public_html/index.php) ---------------------------
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
    $fail('Database is not configured or unreachable. Check .env (DB_ADAPTER, DB_*).');
}

$query = new AdapterAwareQueryExecutor(new PdoDatabaseQueryExecutor($connectionFactory), $adapter);
$transactionManager = new AdapterAwareTransactionManager(new PdoDatabaseTransactionManager($connectionFactory), $adapter);

// --- Create org + admin via the same use cases the HTTP app uses ---------------
$container = ApplicationFactory::container($query, $transactionManager, $env('NENE2_LOCAL_JWT_SECRET') ?: $env('NENE_CLEAR_JWT_SECRET'));

// Fail before creating an organization if the email is taken, so a re-run does
// not leave an orphan organization behind (the create-user step guards it too).
if (ServiceResolver::get($container, UserRepositoryInterface::class)->existsByEmail($email)) {
    $fail(sprintf("A user with email '%s' already exists.", $email));
}

$organizationId = null;
if (!$isSuperadmin) {
    try {
        $organization = ServiceResolver::get($container, CreateOrganizationUseCaseInterface::class)
            ->execute(new CreateOrganizationInput(slug: $orgSlug, name: $orgName, actorUserId: 0));
        $organizationId = $organization->id;
        fwrite(STDOUT, sprintf('Created organization #%d (%s / %s)', $organization->id, $organization->slug, $organization->name) . PHP_EOL);
    } catch (OrganizationAlreadyExistsException) {
        $fail(sprintf("An organization with slug '%s' already exists; pass a different --org-slug.", $orgSlug));
    }
}

try {
    $user = ServiceResolver::get($container, CreateUserUseCaseInterface::class)
        ->execute(new CreateUserInput(
            organizationId: $organizationId,
            email: $email,
            role: $role,
            password: $password,
            actorUserId: 0,
        ));
} catch (UserAlreadyExistsException) {
    $fail(sprintf("A user with email '%s' already exists.", $email));
}

fwrite(STDOUT, sprintf(
    'Created %s user #%d <%s>%s. You can now log in to the admin UI.',
    $role->value,
    $user->id ?? 0,
    $user->email,
    $organizationId !== null ? sprintf(' in organization #%d', $organizationId) : '',
) . PHP_EOL);

exit(0);
