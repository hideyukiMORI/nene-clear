<?php

declare(strict_types=1);

/**
 * Tier A web installer (PoC, #232) for NeNe Clear on shared hosting.
 *
 * Config-boundary entry point (like public_html/index.php): raw superglobal / env
 * access and DB wiring live here on purpose. Walks a fresh operator through
 * requirements → database → tenancy → migrate → first organization + admin,
 * reusing the same domain use cases the app uses (CreateOrganizationUseCase /
 * CreateUserUseCase via ApplicationFactory::container()) so password hashing,
 * uniqueness and auditing behave identically to the running app.
 *
 * PoC scope: assumes the application code is already present (git clone / uploaded
 * bundle). Downloading the release ZIP from GitHub is a later slice and needs a
 * release-build workflow first. Out of scope here: signature verification,
 * auto-update, Suite integration.
 *
 * WARNING: NeNe Clear handles bank deposits and PII. Shared hosting is NOT
 * recommended (ADR 0009 / #206) — VPS + Docker is the recommended target. This
 * installer surfaces that warning but does not block. DELETE this file (or deny
 * access to it) immediately after a successful install.
 */

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Database\PdoDatabaseTransactionManager;
use Nene2\Install\EnvironmentWriter;
use NeneClear\Auth\Role;
use NeneClear\Database\AdapterAwareQueryExecutor;
use NeneClear\Database\AdapterAwareTransactionManager;
use NeneClear\Http\ApplicationFactory;
use NeneClear\Http\ServiceResolver;
use NeneClear\Organization\CreateOrganizationInput;
use NeneClear\Organization\CreateOrganizationUseCaseInterface;
use NeneClear\User\CreateUserInput;
use NeneClear\User\CreateUserUseCaseInterface;
use Phinx\Config\Config as PhinxConfig;
use Phinx\Migration\Manager as PhinxManager;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

$root = dirname(__DIR__);

if (!is_file($root . '/vendor/autoload.php')) {
    render_page('依存関係が見つかりません', '<p class="err">'
        . '<code>vendor/</code> がありません。ローカルでは <code>composer install</code> を実行してください。'
        . '共有ホスティング向けの ZIP 取得はこの PoC の次スライスで対応します。</p>');
    exit;
}

require $root . '/vendor/autoload.php';

$marker = $root . '/var/.installed';
$envFile = $root . '/.env';

/** Read a POST field as a trimmed string (L8-safe against mixed superglobals). */
function post(string $key): string
{
    return is_string($_POST[$key] ?? null) ? trim((string) $_POST[$key]) : '';
}

/** HTML-escape. */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function slugify(string $name): string
{
    return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
}

function render_page(string $title, string $bodyHtml): void
{
    $t = e($title);
    echo <<<HTML
    <!doctype html><html lang="ja"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$t} — NeNe Clear インストーラ</title>
    <style>
      body{font-family:system-ui,sans-serif;max-width:640px;margin:2rem auto;padding:0 1rem;color:#14342b}
      h1{font-size:1.4rem} label{display:block;margin:.6rem 0 .2rem;font-weight:600}
      input,select{width:100%;padding:.5rem;border:1px solid #b7c9c0;border-radius:6px;box-sizing:border-box}
      fieldset{border:1px solid #cfe0d8;border-radius:8px;margin:1rem 0;padding:1rem}
      legend{font-weight:700;color:#0f6b4f} button{margin-top:1rem;padding:.7rem 1.2rem;background:#0f6b4f;color:#fff;border:0;border-radius:8px;font-size:1rem;cursor:pointer}
      .warn{background:#fff6e6;border:1px solid #f0c674;padding:.8rem;border-radius:8px}
      .err{background:#fdecea;border:1px solid #e6a6a1;padding:.8rem;border-radius:8px}
      .ok{background:#e7f6ee;border:1px solid #8fd0ac;padding:.8rem;border-radius:8px}
      ul{padding-left:1.2rem} code{background:#eef4f1;padding:.1rem .3rem;border-radius:4px}
    </style></head><body><h1>{$t}</h1>{$bodyHtml}</body></html>
    HTML;
}

/** @return list<array{label:string, ok:bool, fix:string}> */
function requirements(string $root): array
{
    return [
        ['label' => 'PHP 8.4 以上（現在 ' . PHP_VERSION . '）', 'ok' => version_compare((string) phpversion(), '8.4.0', '>='), 'fix' => 'PHP 8.4 系を有効にしてください。'],
        ['label' => 'PDO 拡張', 'ok' => extension_loaded('pdo'), 'fix' => 'pdo を有効化してください。'],
        ['label' => 'MySQL ドライバ (pdo_mysql)', 'ok' => extension_loaded('pdo_mysql'), 'fix' => '共有ホスティングで MySQL を使う場合は必須です。'],
        ['label' => 'mbstring 拡張', 'ok' => extension_loaded('mbstring'), 'fix' => 'mbstring を有効化してください。'],
        ['label' => 'ルートディレクトリが書込可（.env 生成用）', 'ok' => is_writable($root), 'fix' => 'サーバのパーミッションを確認してください。'],
    ];
}

// --- Guard: refuse to run on an already-configured install ---------------------
if (is_file($marker) || is_file($envFile)) {
    render_page('インストール済み', '<p class="ok">この環境は既に構成済みです。'
        . '再インストールするには <code>.env</code> と <code>var/.installed</code> を削除してください。</p>'
        . '<p class="warn">セキュリティのため、この <code>install.php</code> は削除してください。</p>');
    exit;
}

$method = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET';

if ($method === 'POST') {
    try {
        run_install($root, $envFile, $marker);
    } catch (\Throwable $e) {
        render_page('インストール失敗', '<p class="err">' . e($e->getMessage()) . '</p>'
            . '<p><a href="install.php">戻る</a></p>');
    }
    exit;
}

render_form();

// ------------------------------------------------------------------------------

function render_form(): void
{
    global $root;
    $reqRows = '';
    foreach (requirements($root) as $r) {
        $mark = $r['ok'] ? '✅' : '❌';
        $fix = $r['ok'] ? '' : ' <small>' . e($r['fix']) . '</small>';
        $reqRows .= '<li>' . $mark . ' ' . e($r['label']) . $fix . '</li>';
    }

    render_page('NeNe Clear をインストール', <<<HTML
    <p class="warn"><strong>注意:</strong> NeNe Clear は銀行入金・PII を扱います。共有ホスティングは推奨されません（VPS + Docker を推奨）。
    運用時は <code>NENE_CLEAR_ENCRYPTION_KEY</code> の設定も検討してください。</p>
    <fieldset><legend>1. 要件チェック</legend><ul>{$reqRows}</ul></fieldset>
    <form method="post" action="install.php">
      <fieldset><legend>2. データベース</legend>
        <label>アダプタ</label>
        <select name="db_adapter" onchange="document.getElementById('mysql').style.display=this.value==='mysql'?'block':'none'">
          <option value="mysql">MySQL（共有ホスティング）</option>
          <option value="sqlite">SQLite（お試し）</option>
        </select>
        <div id="mysql">
          <label>ホスト</label><input name="db_host" value="127.0.0.1">
          <label>ポート</label><input name="db_port" value="3306">
          <label>データベース名</label><input name="db_name" value="nene_clear">
          <label>ユーザー</label><input name="db_user" value="nene_clear">
          <label>パスワード</label><input name="db_password" type="password">
        </div>
      </fieldset>
      <fieldset><legend>3. テナント構成</legend>
        <label><input type="radio" name="tenant_mode" value="single" checked> シングルテナント（組織1つ＋管理者）</label>
        <label><input type="radio" name="tenant_mode" value="multi"> マルチテナント（横断superadmin・組織は後で追加）</label>
        <label>組織名（シングル時）</label><input name="org_name" value="My Company">
        <label>組織スラッグ（英数字・空なら組織名から生成）</label><input name="org_slug" placeholder="my-company">
      </fieldset>
      <fieldset><legend>4. 管理者アカウント</legend>
        <label>メールアドレス</label><input name="admin_email" type="email" required>
        <label>パスワード（12文字以上）</label><input name="admin_password" type="password" required>
      </fieldset>
      <button type="submit">インストールを実行</button>
    </form>
    HTML);
}

function run_install(string $root, string $envFile, string $marker): void
{
    $adapter = post('db_adapter') === 'sqlite' ? 'sqlite' : 'mysql';
    $tenantMode = post('tenant_mode') === 'multi' ? 'multi' : 'single';
    $email = post('admin_email');
    $password = post('admin_password');

    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('有効なメールアドレスを入力してください。');
    }
    if (strlen($password) < 12) {
        throw new RuntimeException('パスワードは12文字以上にしてください。');
    }

    // Database connection parameters (mysql from the form; sqlite path is fixed).
    $sqlitePath = $root . '/database/nene_clear.sqlite3';
    $db = $adapter === 'sqlite'
        ? ['adapter' => 'sqlite', 'name' => $sqlitePath]
        : [
            'adapter' => 'mysql',
            'host' => post('db_host') !== '' ? post('db_host') : '127.0.0.1',
            'port' => (int) (post('db_port') !== '' ? post('db_port') : '3306'),
            'name' => post('db_name') !== '' ? post('db_name') : 'nene_clear',
            'user' => post('db_user') !== '' ? post('db_user') : 'nene_clear',
            'pass' => post('db_password'),
            'charset' => 'utf8mb4',
        ];

    // 1) Migrate the schema into the target database (Phinx Manager, no shell).
    migrate($root, $adapter, $db, $sqlitePath);

    // 2) Persist config so the app connects to the same database.
    $jwtSecret = EnvironmentWriter::generateSecret(32);
    write_env($envFile, $adapter, $db, $sqlitePath, $jwtSecret);

    // 3) Create the first organization + admin via the app's own use cases.
    $summary = bootstrap_admin($adapter, $db, $sqlitePath, $jwtSecret, $tenantMode, $email, $password);

    // 4) Lock further installs.
    @mkdir($root . '/var', 0775, true);
    file_put_contents($marker, gmdate('c') . "\n");

    render_page('インストール完了', '<p class="ok">' . $summary . '</p>'
        . '<p class="warn"><strong>今すぐ <code>public_html/install.php</code> を削除</strong>してください（再実行・情報漏えい防止）。</p>'
        . '<p>管理 UI（Vite: <code>:5383</code> / 本番は同一オリジン）からログインできます。</p>');
}

/**
 * @param array<string, mixed> $db
 */
function migrate(string $root, string $adapter, array $db, string $sqlitePath): void
{
    // Phinx appends `.sqlite3`; hand it the name without the suffix so it resolves
    // to the same file the app opens (database/nene_clear.sqlite3).
    $environment = $adapter === 'sqlite'
        ? ['adapter' => 'sqlite', 'name' => substr($sqlitePath, 0, -8)]
        : $db;

    $config = new PhinxConfig([
        'paths' => ['migrations' => $root . '/database/migrations'],
        'environments' => [
            'default_migration_table' => 'phinxlog',
            'default_environment' => 'install',
            'install' => $environment,
        ],
    ]);

    $manager = new PhinxManager($config, new StringInput(''), new BufferedOutput());
    $manager->migrate('install');
}

/**
 * @param array<string, mixed> $db
 */
function write_env(string $envFile, string $adapter, array $db, string $sqlitePath, string $jwtSecret): void
{
    // .env は toolkit の EnvironmentWriter で原子書き込みする（chmod 0640 で fail-closed・
    // 値を \\ " $ escape・改行/NUL 拒否）。従来の生連結 + rename と違い、DB パスワードに
    // " $ # 空白・改行が含まれても .env が壊れず、同ホスト全ユーザからの読み取り（穴 #1）と
    // .env インジェクション（穴 #2）を塞ぐ。KEY 順序と名前は既存のまま維持する。
    $values = [
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'DB_ADAPTER' => $adapter,
    ];
    if ($adapter === 'mysql') {
        $values['DB_HOST'] = (string) $db['host'];
        $values['DB_PORT'] = (string) $db['port'];
        $values['DB_NAME'] = (string) $db['name'];
        $values['DB_USER'] = (string) $db['user'];
        $values['DB_PASSWORD'] = (string) $db['pass'];
        $values['DB_CHARSET'] = 'utf8mb4';
    } else {
        $values['DB_NAME'] = $sqlitePath;
    }
    $values['NENE_CLEAR_JWT_SECRET'] = $jwtSecret;

    (new EnvironmentWriter())->write($envFile, $values);
}

/**
 * @param array<string, mixed> $db
 */
function bootstrap_admin(
    string $adapter,
    array $db,
    string $sqlitePath,
    string $jwtSecret,
    string $tenantMode,
    string $email,
    string $password,
): string {
    $config = $adapter === 'sqlite'
        ? DatabaseConfig::sqlite($sqlitePath)
        : new DatabaseConfig(
            url: null,
            environment: 'production',
            adapter: 'mysql',
            host: (string) $db['host'],
            port: (int) $db['port'],
            name: (string) $db['name'],
            user: (string) $db['user'],
            password: (string) $db['pass'],
            charset: 'utf8mb4',
        );

    $factory = new PdoConnectionFactory($config);
    $query = new AdapterAwareQueryExecutor(new PdoDatabaseQueryExecutor($factory), $adapter);
    $transactionManager = new AdapterAwareTransactionManager(new PdoDatabaseTransactionManager($factory), $adapter);
    $container = ApplicationFactory::container($query, $transactionManager, $jwtSecret);

    if ($tenantMode === 'multi') {
        ServiceResolver::get($container, CreateUserUseCaseInterface::class)->execute(
            new CreateUserInput(organizationId: null, email: $email, role: Role::Superadmin, password: $password, actorUserId: 0),
        );

        return 'マルチテナントで横断管理者（superadmin）<' . e($email) . '> を作成しました。組織はログイン後に追加できます。';
    }

    $orgName = post('org_name') !== '' ? post('org_name') : 'My Company';
    $orgSlug = post('org_slug') !== '' ? post('org_slug') : slugify($orgName);
    if ($orgSlug === '') {
        throw new RuntimeException('組織スラッグを生成できませんでした。英数字の --org-slug を指定してください。');
    }

    $org = ServiceResolver::get($container, CreateOrganizationUseCaseInterface::class)->execute(
        new CreateOrganizationInput(slug: $orgSlug, name: $orgName, actorUserId: 0),
    );
    ServiceResolver::get($container, CreateUserUseCaseInterface::class)->execute(
        new CreateUserInput(organizationId: $org->id, email: $email, role: Role::Admin, password: $password, actorUserId: 0),
    );

    return '組織「' . e($orgName) . '」（#' . $org->id . '）と管理者 <' . e($email) . '> を作成しました。';
}
