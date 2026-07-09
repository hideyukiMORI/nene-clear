<?php

declare(strict_types=1);

/**
 * Demo-org seeder (#260): drop and re-seed the fixed demo organization with
 * realistic, T-relative data in one command, so the hosted demo never goes
 * stale and can be reset at any time (rerun = reset).
 *
 * What it produces (all dates relative to the run date T):
 *  - the demo organization + fixed hand-out credentials (1 admin, 1 viewer)
 *    and a believable operator cast (active/invited, mixed roles);
 *  - 12 months of bank import batches (~280 deposits) with a spread of
 *    unmatched / partially_matched / matched / voided lines;
 *  - confirmed and reversed reconciliations with allocations (upstream +
 *    manual sources), overpayment client credits;
 *  - manual receivables (ADR 0014), including open ones that pair with the
 *    emitted live-import CSV for the name-mismatch matching showcase;
 *  - dunning history aligned with {@see \NeneClear\InvoiceUpstream\DemoInvoiceUpstreamFixture}
 *    invoice ids, so the live dunning screen (NENE_CLEAR_DEMO_UPSTREAM=1) and
 *    the seeded history tell one coherent story;
 *  - an audit trail mirroring the registered actions for all of the above;
 *  - `var/demo-bank-import.csv` — a T-relative bank CSV to import during the
 *    demo (contains the 名義ズレ exact-amount match showcase).
 *
 * DESTRUCTIVE: every row belonging to the demo organization (and the whole
 * `login_attempts` table, so stale lockouts never survive a reset) is deleted
 * first. The demo org's audit trail is part of the demo data and is reset with
 * it — do not point this tool at a database holding real records.
 *
 * Usage:
 *   php tools/seed-demo.php --force \
 *     --admin-password '…12+ chars…' --viewer-password '…12+ chars…'
 *   NENE_CLEAR_DEMO_ADMIN_PASSWORD=… NENE_CLEAR_DEMO_VIEWER_PASSWORD=… \
 *     php tools/seed-demo.php --force
 *
 * Database config comes from .env like the app (sqlite / mysql / pgsql).
 * Deterministic: the same T yields the same dataset (fixed mt_srand seed).
 *
 * Config-boundary entry point (docs/development/nene2-compliance.md §15): raw
 * env access and the DB wiring mirror public_html/index.php on purpose.
 */

use Dotenv\Dotenv;
use Nene2\Config\DatabaseConfig;
use Nene2\Database\DatabaseConnectionFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Database\PdoDatabaseTransactionManager;
use NeneClear\Auth\Role;
use NeneClear\Database\AdapterAwareQueryExecutor;
use NeneClear\Database\AdapterAwareTransactionManager;
use NeneClear\Http\ApplicationFactory;
use NeneClear\Http\ServiceResolver;
use NeneClear\InvoiceUpstream\DemoInvoiceUpstreamFixture;
use NeneClear\Organization\CreateOrganizationInput;
use NeneClear\Organization\CreateOrganizationUseCaseInterface;
use NeneClear\User\CreateUserInput;
use NeneClear\User\CreateUserUseCaseInterface;

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

// --- Parse arguments (--key value or --key=value) ------------------------------
$rawArgv = $_SERVER['argv'] ?? [];
$args = array_slice(is_array($rawArgv) ? array_values($rawArgv) : [], 1);
$count = count($args);
/** @var array<string, string> $opts */
$opts = [];
for ($i = 0; $i < $count; $i++) {
    $arg = (string) $args[$i];
    if ($arg === '-h' || $arg === '--help') {
        $lines = [
            'Drop and re-seed the demo organization with T-relative demo data.',
            '',
            'Usage:',
            '  php tools/seed-demo.php --force --admin-password PASS --viewer-password PASS',
            '',
            'Options:',
            '  --org-slug SLUG        Demo org slug (default: demo)',
            '  --org-name NAME        Demo org display name (default: デモ商事株式会社)',
            '  --admin-email EMAIL    Hand-out admin login (default: demo-admin@nene-clear.dev)',
            '  --viewer-email EMAIL   Hand-out viewer login (default: demo-viewer@nene-clear.dev)',
            '  --admin-password PASS  Admin password (default: NENE_CLEAR_DEMO_ADMIN_PASSWORD env)',
            '  --viewer-password PASS Viewer password (default: NENE_CLEAR_DEMO_VIEWER_PASSWORD env)',
            '  --force                Skip the interactive confirmation (required for cron)',
            '  -h, --help             Show this help',
        ];
        fwrite(STDOUT, implode(PHP_EOL, $lines) . PHP_EOL);
        exit(0);
    }
    if (!str_starts_with($arg, '--')) {
        $fail(sprintf('Unexpected argument: %s (see --help)', $arg));
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

$optOr = static function (string $key, string $default) use ($opts): string {
    $value = trim($opts[$key] ?? '');

    return $value !== '' ? $value : $default;
};

$orgSlug = $optOr('org-slug', 'demo');
$orgName = $optOr('org-name', 'デモ商事株式会社');
$adminEmail = $optOr('admin-email', 'demo-admin@nene-clear.dev');
$viewerEmail = $optOr('viewer-email', 'demo-viewer@nene-clear.dev');
$adminPassword = $opts['admin-password'] ?? ($env('NENE_CLEAR_DEMO_ADMIN_PASSWORD') ?: '');
$viewerPassword = $opts['viewer-password'] ?? ($env('NENE_CLEAR_DEMO_VIEWER_PASSWORD') ?: '');

foreach ([[$adminEmail, '--admin-email'], [$viewerEmail, '--viewer-email']] as [$email, $flag]) {
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $fail(sprintf('%s is not a valid email address (%s).', $email, $flag));
    }
}
if (strlen($adminPassword) < 12 || strlen($viewerPassword) < 12) {
    $fail('Demo passwords are required and must be at least 12 characters. '
        . 'Pass --admin-password/--viewer-password or set NENE_CLEAR_DEMO_ADMIN_PASSWORD / '
        . 'NENE_CLEAR_DEMO_VIEWER_PASSWORD in .env (keeps credentials stable across resets).');
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

try {
    $query->fetchOne('SELECT id FROM organizations LIMIT 1');
} catch (\Throwable $e) {
    $fail('Schema is missing or outdated — run `composer migrations:migrate` first. (' . $e->getMessage() . ')');
}

// --- Confirm the destructive reset ---------------------------------------------
if (!isset($opts['force'])) {
    if (!stream_isatty(STDIN)) {
        $fail('Refusing to reset without confirmation. Pass --force (e.g. from cron).');
    }
    fwrite(STDOUT, sprintf(
        "This DELETES every record of organization '%s' (including its audit trail)\n"
        . 'and reseeds it with fresh demo data. Type the org slug to continue: ',
        $orgSlug,
    ));
    $answer = trim((string) fgets(STDIN));
    if ($answer !== $orgSlug) {
        $fail('Aborted.');
    }
}

$today = new DateTimeImmutable('today');
mt_srand(20260712); // deterministic content; only T moves between runs

$d = static fn (DateTimeImmutable $x): string => $x->format('Y-m-d');
$days = static fn (int $offset): DateTimeImmutable => $today->modify(sprintf('%+d days', $offset));
/** Random business-hours timestamp on the given day. */
$at = static fn (DateTimeImmutable $day): string => $day->format('Y-m-d') . sprintf(' %02d:%02d:%02d', mt_rand(9, 17), mt_rand(0, 59), mt_rand(0, 59));
$json = static fn (array $value): string => (string) json_encode($value, JSON_UNESCAPED_UNICODE);

// --- 1) Delete the previous demo org, children first ---------------------------
$existing = $query->fetchOne('SELECT id FROM organizations WHERE slug = ?', [$orgSlug]);
$transactionManager->transactional(static function (DatabaseQueryExecutorInterface $ex) use ($existing): void {
    if (is_array($existing)) {
        $orgId = (int) $existing['id'];
        foreach ([
            'dunning_pauses', 'dunning_notices', 'reconciliation_allocations',
            'payment_reconciliations', 'client_credits', 'bank_transactions',
            'bank_import_batches', 'bank_accounts', 'manual_receivables',
            'clear_settings', 'audit_events', 'user_invitations',
        ] as $table) {
            $ex->execute(sprintf('DELETE FROM %s WHERE organization_id = ?', $table), [$orgId]);
        }
        foreach (['used_totp_steps', 'recovery_codes', 'totp_secrets'] as $table) {
            $ex->execute(
                sprintf('DELETE FROM %s WHERE user_id IN (SELECT id FROM users WHERE organization_id = ?)', $table),
                [$orgId],
            );
        }
        $ex->execute('DELETE FROM users WHERE organization_id = ?', [$orgId]);
        $ex->execute('DELETE FROM organizations WHERE id = ?', [$orgId]);
    }

    // Throttle state is keyed by identifier hash, not org — wipe it so a reset
    // also clears any demo-account lockout (login_attempts is audit-exempt).
    $ex->execute('DELETE FROM login_attempts');
});
fwrite(STDOUT, ($existing !== null ? "Deleted previous demo organization.\n" : "No previous demo organization found.\n"));

// --- 2) Recreate org + hand-out users via the app's own use cases --------------
$container = ApplicationFactory::container($query, $transactionManager, $env('NENE_CLEAR_JWT_SECRET'));

$organization = ServiceResolver::get($container, CreateOrganizationUseCaseInterface::class)
    ->execute(new CreateOrganizationInput(slug: $orgSlug, name: $orgName, actorUserId: 0));
$orgId = $organization->id;

$createUser = ServiceResolver::get($container, CreateUserUseCaseInterface::class);
$admin = $createUser->execute(new CreateUserInput(
    organizationId: $orgId,
    email: $adminEmail,
    role: Role::Admin,
    password: $adminPassword,
    actorUserId: 0,
));
$viewer = $createUser->execute(new CreateUserInput(
    organizationId: $orgId,
    email: $viewerEmail,
    role: Role::Viewer,
    password: $viewerPassword,
    actorUserId: 0,
));
$adminId = (int) ($admin->id ?? 0);
$viewerId = (int) ($viewer->id ?? 0);

// --- 3) Bulk demo data, one transaction ----------------------------------------
/** @var array<string, int> $counts */
$counts = [];
/** @var list<string> $openRefs */
$openRefs = [];

$transactionManager->transactional(static function (DatabaseQueryExecutorInterface $ex) use (
    $orgId,
    $adminId,
    $viewerId,
    $today,
    $d,
    $days,
    $at,
    $json,
    &$counts,
    &$openRefs,
): void {
    /** @var list<array{string, string, string, int, ?string, ?string, ?string}> $auditRows action, entityType, occurredAt, entityId, before, after, metadata */
    $auditRows = [];

    // Operator cast: realistic member list behind the two hand-out accounts.
    $cast = [
        ['tanaka@demo.example', 'admin', 'active'],
        ['suzuki@demo.example', 'member', 'active'],
        ['sato@demo.example', 'member', 'active'],
        ['takahashi@demo.example', 'viewer', 'active'],
        ['ito@demo.example', 'member', 'invited'],
        ['watanabe@demo.example', 'viewer', 'active'],
        ['yamamoto@demo.example', 'admin', 'active'],
        ['nakamura@demo.example', 'member', 'active'],
        ['kobayashi@demo.example', 'viewer', 'active'],
        ['kato@demo.example', 'member', 'invited'],
        ['yoshida@demo.example', 'member', 'active'],
        ['yamada@demo.example', 'viewer', 'active'],
        ['sasaki@demo.example', 'admin', 'active'],
        ['matsumoto@demo.example', 'member', 'active'],
        ['inoue@demo.example', 'viewer', 'invited'],
        ['kimura@demo.example', 'member', 'active'],
        ['hayashi@demo.example', 'member', 'active'],
    ];
    $memberIds = [];
    foreach ($cast as $i => [$email, $role, $status]) {
        $createdAt = $at($days(-200 + $i * 9));
        $userId = $ex->insert(
            'INSERT INTO users (organization_id, email, role, status, password_hash, is_deleted, created_at, updated_at)'
            . ' VALUES (?, ?, ?, ?, ?, 0, ?, ?)',
            [$orgId, $email, $role, $status, password_hash(bin2hex(random_bytes(12)), PASSWORD_BCRYPT), $createdAt, $createdAt],
        );
        if ($role !== 'viewer' && $status === 'active') {
            $memberIds[] = $userId;
        }
        $auditRows[] = ['user_created', 'user', $createdAt, $userId, null,
            $json(['user_id' => $userId, 'email' => $email, 'role' => $role, 'status' => $status]), null];
    }
    $counts['users'] = count($cast) + 2;
    $actors = [...$memberIds, $adminId];

    // Org settings (upstream config is display-only in the demo).
    $settingsAt = $at($days(-180));
    $ex->insert(
        'INSERT INTO clear_settings (organization_id, upstream_base_url, upstream_token_ref, dunning_min_interval_days, fiscal_year_end_month, created_at, updated_at)'
        . ' VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$orgId, 'https://invoice.example', 'NENE_INVOICE_BEARER_TOKEN', 7, 3, $settingsAt, $settingsAt],
    );
    $auditRows[] = ['clear_settings_updated', 'clear_settings', $settingsAt, $orgId,
        $json([]), $json(['upstream_base_url' => 'https://invoice.example', 'dunning_min_interval_days' => 7]), null];

    // Bank accounts (CSV profile: date,deposit,withdrawal,counterparty; header row).
    $accounts = [
        ['みずほ銀行', '渋谷支店', '1234567'],
        ['三菱UFJ銀行', '新宿支店', '7654321'],
        ['三井住友銀行', '本店営業部', '2468013'],
    ];
    $accountIds = [];
    foreach ($accounts as [$bank, $branch, $number]) {
        $createdAt = $at($days(-190));
        $accountIds[] = $ex->insert(
            'INSERT INTO bank_accounts (organization_id, bank_name, bank_branch, account_type, account_number,'
            . ' csv_encoding, csv_date_format, csv_date_column, csv_amount_column, csv_counterparty_column, csv_header_rows,'
            . ' is_deleted, created_at, updated_at)'
            . " VALUES (?, ?, ?, 'ordinary', ?, 'utf8', 'Y/m/d', 0, 1, 3, 1, 0, ?, ?)",
            [$orgId, $bank, $branch, $number, $createdAt, $createdAt],
        );
    }
    $counts['bank_accounts'] = count($accountIds);

    // Payers: katakana transfer name / registered client name / mail slug.
    $companies = [
        ['（カ）テストコーポレーション', '株式会社テストコーポレーション', 'testcorp'],
        ['カ）アクメ', 'アクメ株式会社', 'acme'],
        ['ヤマカワショウジ（カ', '山川商事株式会社', 'yamakawa'],
        ['グリーンフィールド（カ', 'グリーンフィールド株式会社', 'greenfield'],
        ['アオゾラケンセツ（カ', 'あおぞら建設株式会社', 'aozora'],
        ['（カ）スターリング', '株式会社スターリング', 'sterling'],
        ['フジサワデンキ（カ', '藤沢電機株式会社', 'fujisawa-denki'],
        ['ヤマダコウムテン（カ', '株式会社山田工務店', 'yamada-koumuten'],
        ['タカハシケンセツ（カ', '高橋建設株式会社', 'takahashi-kensetsu'],
        ['（カ）ホシノブッサン', '星野物産株式会社', 'hoshino'],
        ['ミナトロジスティクス（カ', 'ミナトロジスティクス株式会社', 'minato-logi'],
        ['カ）サクラフーズ', 'さくらフーズ株式会社', 'sakura-foods'],
        ['（カ）ワタナベセイサクショ', '渡辺製作所株式会社', 'watanabe-ss'],
        ['ヒガシヤマショウテン（カ', '東山商店株式会社', 'higashiyama'],
        ['カ）ニシムラウンユ', '西村運輸株式会社', 'nishimura-unyu'],
        ['（カ）オオタケミライ', 'オオタケミライ株式会社', 'otake-mirai'],
    ];
    $persons = ['ヤマダ タロウ', 'イトウ ユウコ', 'サトウ ケンイチ', 'タナカ ミホ', 'コバヤシ ジュン', 'ナカムラ アヤ'];

    // 12 months of imports. The batch 2 months back is reversed (its lines are
    // voided) to show the reversal trail.
    /** @var list<array{id: int, amount: int, date: DateTimeImmutable, company: int, status: string}> $matchable */
    $matchable = [];
    $txTotal = 0;
    $statusTally = ['unmatched' => 0, 'partially_matched' => 0, 'matched' => 0, 'voided' => 0];
    for ($m = 11; $m >= 0; $m--) {
        $monthStart = $today->modify(sprintf('-%d months', $m))->modify('first day of this month');
        $isCurrent = $m === 0;
        $lastDay = $isCurrent
            ? max(1, (int) $today->format('j') - 1)
            : (int) $monthStart->modify('last day of this month')->format('j');
        $importedDay = $isCurrent ? $today : $monthStart->modify('last day of this month')->modify('+3 days');
        $reversed = $m === 2;
        $rows = $isCurrent ? 24 : mt_rand(19, 29);
        $accountId = $accountIds[$m % 12 < 10 ? 0 : 1];
        $importedAt = $at($importedDay);
        $reversedAt = $reversed ? $at($importedDay->modify('+2 days')) : null;
        $importedBy = $actors[mt_rand(0, count($actors) - 1)];

        $batchId = $ex->insert(
            'INSERT INTO bank_import_batches (organization_id, bank_account_id, file_hash, source_filename, row_count,'
            . ' status, imported_by, imported_at, reversed_at, reversal_reason, created_at, updated_at)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $orgId, $accountId, hash('sha256', 'demo-batch-' . $m . '-' . $d($monthStart)),
                'bank_' . $monthStart->format('Ym') . '.csv', $rows,
                $reversed ? 'reversed' : 'imported', $importedBy, $importedAt,
                $reversedAt,
                $reversed ? '取込ファイル誤り（再取込済み）' : null,
                $importedAt, $importedAt,
            ],
        );
        $auditRows[] = ['bank_import', 'bank_import_batch', $importedAt, $batchId, null,
            $json(['source_filename' => 'bank_' . $monthStart->format('Ym') . '.csv', 'row_count' => $rows, 'bank_account_id' => $accountId]),
            $json(['bank_import_batch_id' => $batchId])];
        if ($reversedAt !== null) {
            $auditRows[] = ['bank_import_batch_reversed', 'bank_import_batch', $reversedAt, $batchId,
                $json(['status' => 'imported']), $json(['status' => 'reversed', 'reversal_reason' => '取込ファイル誤り（再取込済み）']), null];
        }

        for ($r = 0; $r < $rows; $r++) {
            $isPerson = mt_rand(1, 100) <= 12;
            $companyIdx = mt_rand(0, count($companies) - 1);
            $counterparty = $isPerson
                ? $persons[mt_rand(0, count($persons) - 1)]
                : $companies[$companyIdx][0];
            $yen = $isPerson ? mt_rand(8, 120) * 1000 : mt_rand(55, 2950) * 1000;
            $cents = $yen * 100;
            $valueDate = $monthStart->modify(sprintf('+%d days', mt_rand(0, max(0, $lastDay - 1))));

            if ($reversed) {
                $status = 'voided';
            } elseif ($isPerson) {
                // Personal transfers are the classic hard-to-match backlog:
                // they stay unmatched (a matched line always has a matching
                // reconciliation row below, and those are corporate only).
                $status = 'unmatched';
            } else {
                $roll = mt_rand(1, 100);
                $status = match (true) {
                    $m >= 3 => $roll <= 88 ? 'matched' : ($roll <= 93 ? 'partially_matched' : 'unmatched'),
                    $m >= 1 => $roll <= 60 ? 'matched' : ($roll <= 74 ? 'partially_matched' : 'unmatched'),
                    default => $roll <= 12 ? 'matched' : ($roll <= 20 ? 'partially_matched' : 'unmatched'),
                };
            }

            $txId = $ex->insert(
                'INSERT INTO bank_transactions (organization_id, bank_import_batch_id, bank_account_id, value_date,'
                . ' amount_cents, counterparty_text, line_key, status, created_at)'
                . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $orgId, $batchId, $accountId, $d($valueDate), $cents, $counterparty,
                    md5($batchId . '|' . $d($valueDate) . '|' . $cents . '|' . $counterparty . '|' . $r),
                    $status, $importedAt,
                ],
            );
            $txTotal++;
            $statusTally[$status]++;
            if ($status === 'matched' || $status === 'partially_matched') {
                $matchable[] = ['id' => $txId, 'amount' => $cents, 'date' => $valueDate, 'company' => $companyIdx, 'status' => $status];
            }
        }
    }
    $counts['bank_import_batches'] = 12;
    $counts['bank_transactions'] = $txTotal;

    // Manual receivables (ADR 0014). The first 11 are settled/partially settled
    // against real matched deposits below; the last 7 stay open — three of them
    // pair with the emitted live-import CSV (exact amount / name-in-counterparty
    // / overdue aging).
    $mrSettled = array_slice($matchable, 0, 11);
    $refSeq = 0;
    $mkRef = static function () use (&$refSeq): string {
        $refSeq++;

        return sprintf('MR-2026-%03d', $refSeq);
    };
    $manualAllocations = [];
    foreach ($mrSettled as $i => $tx) {
        $company = $companies[$tx['company']];
        $partial = $i >= 8; // last three stay partially paid
        $total = $partial ? $tx['amount'] + mt_rand(150, 900) * 1000 * 100 : $tx['amount'];
        $issuedAt = $tx['date']->modify(sprintf('-%d days', mt_rand(20, 35)));
        $createdAt = $at($issuedAt);
        $mrId = $ex->insert(
            'INSERT INTO manual_receivables (organization_id, reference_number, client_name, recipient_email, total_cents,'
            . ' outstanding_cents, currency, issued_at, due_at, status, created_by, created_at, updated_at, is_deleted)'
            . " VALUES (?, ?, ?, ?, ?, ?, 'JPY', ?, ?, ?, ?, ?, ?, 0)",
            [
                $orgId, $mkRef(), $company[1], 'billing@' . $company[2] . '.example', $total,
                $total - $tx['amount'], $d($issuedAt), $d($tx['date']->modify(sprintf('%+d days', mt_rand(-8, 6)))),
                $partial ? 'partially_paid' : 'paid', $adminId, $createdAt, $createdAt,
            ],
        );
        $auditRows[] = ['manual_receivable_created', 'manual_receivable', $createdAt, $mrId, null,
            $json(['reference_number' => sprintf('MR-2026-%03d', $refSeq), 'client_name' => $company[1], 'total_cents' => $total]),
            $json(['manual_receivable_id' => $mrId])];
        $manualAllocations[$tx['id']] = ['mr' => $mrId, 'amount' => $tx['amount']];
    }

    // Open manual receivables — the live matching targets. Client names are
    // chosen so the propose screen shows both signal kinds: exact amount only
    // (kanji name vs katakana transfer name = 名義ズレ) and name-in-counterparty.
    $openReceivables = [
        // ref-label, client_name, email slug, yen, due offset (days)
        ['株式会社山田工務店', 'yamada-koumuten', 423500, 10],  // CSV: ヤマダコウムテン（カ — exact amount
        ['テストコーポレーション', 'testcorp', 255000, 8],      // CSV: （カ）テストコーポレーション — name + amount
        ['高橋建設株式会社', 'takahashi-kensetsu', 341000, -15], // overdue aging row
        ['星野物産株式会社', 'hoshino', 748000, 18],
        ['渡辺製作所株式会社', 'watanabe-ss', 517000, 25],
        ['西村運輸株式会社', 'nishimura-unyu', 92400, 5],
        ['さくらフーズ株式会社', 'sakura-foods', 1265000, 30],
    ];
    foreach ($openReceivables as [$client, $slug, $yen, $dueOffset]) {
        $issuedAt = $days($dueOffset - 30);
        $createdAt = $at($issuedAt);
        $ref = $mkRef();
        $openRefs[] = $ref;
        $mrId = $ex->insert(
            'INSERT INTO manual_receivables (organization_id, reference_number, client_name, recipient_email, total_cents,'
            . ' outstanding_cents, currency, issued_at, due_at, status, created_by, created_at, updated_at, is_deleted)'
            . " VALUES (?, ?, ?, ?, ?, ?, 'JPY', ?, ?, 'open', ?, ?, ?, 0)",
            [
                $orgId, $ref, $client, 'billing@' . $slug . '.example', $yen * 100,
                $yen * 100, $d($issuedAt), $d($days($dueOffset)), $adminId, $createdAt, $createdAt,
            ],
        );
        $auditRows[] = ['manual_receivable_created', 'manual_receivable', $createdAt, $mrId, null,
            $json(['reference_number' => $ref, 'client_name' => $client, 'total_cents' => $yen * 100]),
            $json(['manual_receivable_id' => $mrId])];
    }
    $counts['manual_receivables'] = count($mrSettled) + count($openReceivables);

    // Reconciliations + allocations for every matchable corporate deposit.
    // Historical upstream invoice ids live in 2000–2055 (they are not resolvable
    // live — history only); manual allocations point at the receivables above.
    $upstreamSeq = 0;
    $creditRows = 0;
    $reconCount = 0;
    foreach ($matchable as $i => $tx) {
        $confirmedAt = $at($tx['date']->modify(sprintf('+%d days', mt_rand(1, 5))));
        $confirmedBy = $actors[mt_rand(0, count($actors) - 1)];
        $reconId = $ex->insert(
            'INSERT INTO payment_reconciliations (organization_id, bank_transaction_id, status, reason_code,'
            . ' confirmed_by, confirmed_at, reversed_at, reversal_reason, created_at, idempotency_key)'
            . " VALUES (?, ?, 'confirmed', NULL, ?, ?, NULL, NULL, ?, ?)",
            [$orgId, $tx['id'], $confirmedBy, $confirmedAt, $confirmedAt, 'seed-' . $tx['id']],
        );
        $reconCount++;

        $manual = $manualAllocations[$tx['id']] ?? null;
        $withCredit = $manual === null && $tx['status'] === 'matched' && mt_rand(1, 100) <= 16;
        $allocated = match (true) {
            $manual !== null => $manual['amount'],
            $tx['status'] === 'partially_matched' => (int) (round($tx['amount'] * mt_rand(45, 80) / 100 / 100000) * 100000),
            $withCredit => $tx['amount'] - mt_rand(5, 60) * 1000 * 100,
            default => $tx['amount'],
        };
        $invoiceId = $manual !== null ? $manual['mr'] : 2000 + ($upstreamSeq % 56);
        $upstreamSeq++;

        $ex->insert(
            'INSERT INTO reconciliation_allocations (organization_id, payment_reconciliation_id, invoice_id, amount_cents,'
            . ' payment_id, external_reference, created_at, source, manual_receivable_id)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $orgId, $reconId, $invoiceId, max($allocated, 100000),
                $manual !== null ? null : 5000 + $i,
                sprintf('clear:recon:%d:%d', $reconId, $invoiceId), $confirmedAt,
                $manual !== null ? 'manual' : 'invoice_upstream',
                $manual !== null ? $manual['mr'] : null,
            ],
        );
        $auditRows[] = ['reconciliation_confirmed', 'payment_reconciliation', $confirmedAt, $reconId,
            $json(['status' => 'unmatched']),
            $json(['status' => 'confirmed', 'amount_cents' => $tx['amount'], 'allocated_cents' => max($allocated, 100000)]),
            $json(['payment_reconciliation_id' => $reconId, 'bank_transaction_id' => $tx['id']])];

        if ($withCredit) {
            $creditAmount = $tx['amount'] - $allocated;
            $voided = mt_rand(1, 100) <= 25;
            $creditId = $ex->insert(
                'INSERT INTO client_credits (organization_id, client_id, amount_cents, remaining_cents, status,'
                . ' source_bank_transaction_id, reconciliation_id, created_by, created_at, source, manual_receivable_id, client_name)'
                . " VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'invoice_upstream', NULL, ?)",
                [
                    $orgId, 100 + $tx['company'], $creditAmount,
                    $voided ? $creditAmount : ($creditRows % 3 === 0 ? 0 : $creditAmount),
                    $voided ? 'voided' : 'open',
                    $tx['id'], $reconId, $confirmedBy, $confirmedAt, $companies[$tx['company']][1],
                ],
            );
            $creditRows++;
            $auditRows[] = ['client_credit_applied', 'client_credit', $confirmedAt, $creditId, null,
                $json(['amount_cents' => $creditAmount, 'status' => $voided ? 'voided' : 'open']),
                $json(['client_credit_id' => $creditId, 'bank_transaction_id' => $tx['id']])];
        }
    }
    $counts['payment_reconciliations'] = $reconCount;
    $counts['client_credits'] = $creditRows;

    // A few reversed reconciliations (wrong payer, later corrected).
    $reversedPool = array_slice($matchable, 20, 6);
    foreach ($reversedPool as $tx) {
        $confirmedAt = $at($tx['date']->modify('+1 days'));
        $reversedAt = $at($tx['date']->modify(sprintf('+%d days', mt_rand(3, 9))));
        $reconId = $ex->insert(
            'INSERT INTO payment_reconciliations (organization_id, bank_transaction_id, status, reason_code,'
            . ' confirmed_by, confirmed_at, reversed_at, reversal_reason, created_at, idempotency_key)'
            . " VALUES (?, ?, 'reversed', NULL, ?, ?, ?, ?, ?, ?)",
            [$orgId, $tx['id'], $adminId, $confirmedAt, $reversedAt, '入金者相違（別請求への充当誤り）', $confirmedAt, 'seed-rev-' . $tx['id']],
        );
        $auditRows[] = ['reconciliation_reversed', 'payment_reconciliation', $reversedAt, $reconId,
            $json(['status' => 'confirmed']), $json(['status' => 'reversed', 'reversal_reason' => '入金者相違（別請求への充当誤り）']),
            $json(['payment_reconciliation_id' => $reconId, 'bank_transaction_id' => $tx['id']])];
    }
    $counts['payment_reconciliations'] += count($reversedPool);

    // Dunning history. Historical invoices (ids 2000–2055) plus the three live
    // fixture invoices — INV-2026-057 got a notice 2 days ago (shows the
    // min-interval throttle), INV-2026-056/060 are overdue and freely dunnable.
    $dunningTargets = [];
    for ($i = 0; $i < 18; $i++) {
        $company = $companies[$i % count($companies)];
        $dunningTargets[] = [
            'invoice_id' => 2000 + $i,
            'number' => sprintf('INV-2026-%03d', $i + 1),
            'client_id' => 100 + ($i % count($companies)),
            'email' => 'billing@' . $company[2] . '.example',
            'outstanding' => mt_rand(90, 2200) * 1000 * 100,
            'stages' => mt_rand(1, 4),
            'lastSentOffset' => -mt_rand(6, 45),
        ];
    }
    $noticeCount = 0;
    foreach ($dunningTargets as $t) {
        $sentDay = $days($t['lastSentOffset']);
        $dueAt = $sentDay->modify(sprintf('-%d days', 10 + $t['stages'] * 12));
        for ($stage = $t['stages'] - 1; $stage >= 0; $stage--) {
            $sentAt = $at($sentDay->modify(sprintf('-%d days', $stage * mt_rand(8, 14))));
            $noticeId = $ex->insert(
                'INSERT INTO dunning_notices (organization_id, invoice_id, invoice_number, client_id, recipient_email,'
                . ' outstanding_cents, due_at, channel, sent_by, sent_at, created_at, template_version)'
                . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $orgId, $t['invoice_id'], $t['number'], $t['client_id'], $t['email'],
                    $t['outstanding'], $d($dueAt), mt_rand(1, 100) <= 80 ? 'email' : 'log',
                    $adminId, $sentAt, $sentAt, '1.0',
                ],
            );
            $noticeCount++;
            $auditRows[] = ['dunning_sent', 'dunning_notice', $sentAt, $noticeId,
                $json(['invoice_status' => 'issued', 'invoice_outstanding_cents' => $t['outstanding']]),
                $json(['invoice_number' => $t['number'], 'recipient_email' => $t['email'], 'outstanding_at_send_cents' => $t['outstanding'], 'channel' => 'email', 'template_version' => '1.0']),
                $json(['dunning_notice_id' => $noticeId, 'invoice_id' => $t['invoice_id']])];
        }
    }
    foreach (DemoInvoiceUpstreamFixture::rows() as [$invoiceId, $number, $clientId, , , $email, , $outstanding]) {
        $offset = match ($invoiceId) {
            2056 => -40,
            2057 => -2,
            2060 => -9,
            default => null,
        };
        if ($offset === null) {
            continue;
        }
        $sentAt = $at($days($offset));
        $noticeId = $ex->insert(
            'INSERT INTO dunning_notices (organization_id, invoice_id, invoice_number, client_id, recipient_email,'
            . ' outstanding_cents, due_at, channel, sent_by, sent_at, created_at, template_version)'
            . " VALUES (?, ?, ?, ?, ?, ?, ?, 'email', ?, ?, ?, '1.0')",
            [$orgId, $invoiceId, $number, $clientId, $email, $outstanding, $d($days($offset - 20)), $adminId, $sentAt, $sentAt],
        );
        $noticeCount++;
        $auditRows[] = ['dunning_sent', 'dunning_notice', $sentAt, $noticeId,
            $json(['invoice_status' => 'issued', 'invoice_outstanding_cents' => $outstanding]),
            $json(['invoice_number' => $number, 'recipient_email' => $email, 'outstanding_at_send_cents' => $outstanding, 'channel' => 'email', 'template_version' => '1.0']),
            $json(['dunning_notice_id' => $noticeId, 'invoice_id' => $invoiceId])];
    }
    $counts['dunning_notices'] = $noticeCount;

    // One active dunning pause on a live fixture invoice (INV-2026-059).
    $pausedAt = $at($days(-6));
    $ex->insert(
        'INSERT INTO dunning_pauses (organization_id, invoice_id, paused_by, paused_at, paused_reason, unpaused_by, unpaused_at)'
        . ' VALUES (?, 2059, ?, ?, ?, NULL, NULL)',
        [$orgId, $adminId, $pausedAt, '支払計画合意済み（分割・毎月末）'],
    );
    $auditRows[] = ['dunning_paused', 'invoice', $pausedAt, 2059, $json(['is_paused' => false]),
        $json(['is_paused' => true, 'paused_reason' => '支払計画合意済み（分割・毎月末）']), $json(['invoice_id' => 2059])];

    // Recent sign-ins so the audit page opens on believable activity.
    foreach ([[-1, $adminId], [-1, $viewerId], [-2, $adminId], [-3, $adminId], [-4, $viewerId]] as [$offset, $userId]) {
        $auditRows[] = ['login_succeeded', 'user', $at($days($offset)), $userId, null, $json(['user_id' => $userId]), null];
    }

    // Flush the audit trail in chronological order (append-only shape).
    usort($auditRows, static fn (array $a, array $b): int => strcmp($a[2], $b[2]));
    foreach ($auditRows as [$action, $entityType, $occurredAt, $entityId, $before, $after, $metadata]) {
        $ex->insert(
            'INSERT INTO audit_events (organization_id, action, actor_id, occurred_at, entity_type, entity_id,'
            . ' before_json, after_json, metadata_json)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$orgId, $action, $adminId, $occurredAt, $entityType, $entityId, $before, $after, $metadata],
        );
    }
    $counts['audit_events'] = count($auditRows);
    $counts['status:unmatched'] = $statusTally['unmatched'];
    $counts['status:partially_matched'] = $statusTally['partially_matched'];
    $counts['status:matched'] = $statusTally['matched'];
    $counts['status:voided'] = $statusTally['voided'];
});

// Backdate the org so it doesn't look created today.
$orgCreatedAt = $days(-210)->format('Y-m-d') . ' 09:12:00';
$query->execute('UPDATE organizations SET created_at = ? WHERE id = ?', [$orgCreatedAt, $orgId]);

// --- 4) Live-import CSV ---------------------------------------------------------
// Amounts are integer yen (the importer converts to cents, #261). Three rows pair
// with seeded receivables/invoices; the rest exercise no-match and the
// withdrawal filter.
$csvPath = $root . '/var/demo-bank-import.csv';
if (!is_dir($root . '/var')) {
    mkdir($root . '/var', 0775, true);
}
$slash = static fn (int $offset): string => $days($offset)->format('Y/m/d'); // the seeded account profiles parse Y/m/d
$csvRows = [
    ['取引日', '入金額', '出金額', '摘要'],
    [$slash(-2), '423500', '', 'ヤマダコウムテン（カ'],      // exact amount → MR 株式会社山田工務店 (名義ズレ)
    [$slash(-1), '255000', '', '（カ）テストコーポレーション'], // amount + name → MR テストコーポレーション
    [$slash(-2), '660000', '', 'ヤマカワショウジ（カ'],      // exact amount → upstream INV-2026-058
    [$slash(-1), '341000', '', 'タカハシケンセツ（カ'],      // exact amount → overdue MR 高橋建設株式会社
    [$slash(-3), '', '55000', 'ジドウヒキオトシ'],            // withdrawal — skipped by the importer
    [$slash(-1), '88000', '', 'フリコミ サトウ ケンイチ'],   // no candidate — manual work stays visible
    [$slash(-2), '12800', '', 'ヤマダ タロウ'],               // small personal deposit, no candidate
];
$csv = implode("\n", array_map(static fn (array $row): string => implode(',', $row), $csvRows)) . "\n";
file_put_contents($csvPath, $csv);

// --- 5) Summary -----------------------------------------------------------------
fwrite(STDOUT, PHP_EOL . sprintf('Seeded organization #%d (%s / %s), T = %s', $orgId, $orgSlug, $orgName, $d($today)) . PHP_EOL);
foreach ($counts as $label => $n) {
    fwrite(STDOUT, sprintf('  %-26s %d', $label, $n) . PHP_EOL);
}
fwrite(STDOUT, PHP_EOL . 'Hand-out credentials:' . PHP_EOL);
fwrite(STDOUT, sprintf('  admin : %s', $adminEmail) . PHP_EOL);
fwrite(STDOUT, sprintf('  viewer: %s', $viewerEmail) . PHP_EOL);
fwrite(STDOUT, PHP_EOL . 'Live-import CSV: ' . $csvPath . PHP_EOL);
fwrite(STDOUT, 'Open manual receivables: ' . implode(', ', $openRefs) . PHP_EOL);
fwrite(STDOUT, 'Set NENE_CLEAR_DEMO_UPSTREAM=1 in .env for live upstream suggestions + dunning sends (docs/demo.md).' . PHP_EOL);

exit(0);
