<?php

declare(strict_types=1);

/**
 * Tier A web installer for NeNe Clear on shared hosting (#232 PoC → #271 parity
 * with the proven nene-invoice installer).
 *
 * Config-boundary entry point (like public_html/index.php): raw superglobal /
 * env access and DB wiring live here on purpose. Walks a fresh operator
 * through a step wizard — requirements → database → admin → complete — with
 * PRG after the database step, field-level validation that preserves input
 * (#267), and a defense-in-depth re-install guard (marker + adapter-aware
 * database probe, re-checked immediately before the final mutation).
 *
 * Logic comes from the NENE2 installer toolkit (ServerRequirementChecker,
 * DatabaseSchemaApplier, EnvironmentWriter, ReInstallationGuard); the markup
 * is product-owned — the toolkit's neutral InstallerRenderer is documented as
 * replace-wholesale. Domain writes reuse the app's own use cases
 * (CreateOrganizationUseCase / CreateUserUseCase via
 * ApplicationFactory::container()) so password hashing, uniqueness and
 * auditing behave identically to the running app.
 *
 * When `vendor/` is absent the installer switches to the acquisition flow:
 * upload the official release ZIP, SHA-256 verified BEFORE extraction
 * (dependency-zero {@see \NeneClear\Install\PayloadAcquisition}, loaded via
 * require — Composer isn't available yet).
 *
 * CLI: `php public_html/install.php --export-patterns [dir]` renders every
 * screen/state to static HTML (the ClaudeDesign handoff source — one source of
 * truth, no hand-copied mockups).
 *
 * WARNING: NeNe Clear handles bank deposits and PII. Shared hosting is NOT
 * recommended for production data (roadmap Phase 3 / #193) — VPS + Docker is
 * the recommended target. This installer surfaces that warning but does not
 * block. DELETE this file immediately after a successful install.
 */

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\PdoDatabaseQueryExecutor;
use Nene2\Database\PdoDatabaseTransactionManager;
use Nene2\Install\DatabaseSchemaApplier;
use Nene2\Install\EnvironmentWriter;
use Nene2\Install\ProvisioningProbe;
use Nene2\Install\ReInstallationGuard;
use Nene2\Install\ServerRequirementChecker;
use Nene2\Install\ServerRequirements;
use NeneClear\Auth\Role;
use NeneClear\Database\AdapterAwareQueryExecutor;
use NeneClear\Database\AdapterAwareTransactionManager;
use NeneClear\Http\ApplicationFactory;
use NeneClear\Http\ServiceResolver;
use NeneClear\Install\PayloadAcquisition;
use NeneClear\Organization\CreateOrganizationInput;
use NeneClear\Organization\CreateOrganizationUseCaseInterface;
use NeneClear\User\CreateUserInput;
use NeneClear\User\CreateUserUseCaseInterface;
use Phinx\Config\Config as PhinxConfig;

$root = dirname(__DIR__);
$marker = $root . '/var/.installed';
$envFile = $root . '/.env';

// -------------------------------------------------------------------------
// Helpers (dependency-zero — usable in the pre-vendor acquisition flow too)
// -------------------------------------------------------------------------

/** HTML-escape. */
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/** Read a POST field as a trimmed string (L8-safe against mixed superglobals). */
function post(string $key): string
{
    return is_string($_POST[$key] ?? null) ? trim((string) $_POST[$key]) : '';
}

/** Read a POST field without trimming (passwords). */
function post_raw(string $key): string
{
    return is_string($_POST[$key] ?? null) ? (string) $_POST[$key] : '';
}

function slugify(string $name): string
{
    return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
}

/** 403 refusal shared by the entry guard and the pre-mutation re-check. */
function refuse_install(string $message): never
{
    http_response_code(403);
    echo render_installer_page([
        'view' => 'blocked',
        'blockedMessage' => $message,
    ]);
    exit;
}

/** SVG icons (static, trusted markup). */
function ico(string $name): string
{
    return match ($name) {
        'mark' => '<svg viewBox="0 0 42 42" fill="none"><rect x="4" y="10" width="34" height="24" rx="4" stroke="currentColor" stroke-width="2.6"/><path d="M4 18h34" stroke="currentColor" stroke-width="2.6"/><path d="M11 27h9" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/><path d="M26 26.5l3 3 5.5-6" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'check' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l4 4 8-9"/></svg>',
        'x' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M5 5l10 10M15 5L5 15"/></svg>',
        'arrow' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10h11M11 5l5 5-5 5"/></svg>',
        'back' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M16 10H5M9 5L4 10l5 5"/></svg>',
        'shield' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 2l5 2v3.5c0 3-2 5.3-5 6.5-3-1.2-5-3.5-5-6.5V4z"/></svg>',
        'server' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2.5" y="3" width="11" height="4.5" rx="1"/><rect x="2.5" y="8.5" width="11" height="4.5" rx="1"/><circle cx="5" cy="5.25" r=".6" fill="currentColor"/><circle cx="5" cy="10.75" r=".6" fill="currentColor"/></svg>',
        'oss' => '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 1.5l2 4.5 4.8.4-3.6 3.2 1.1 4.7L8 11.8 3.7 14.3l1.1-4.7L1.2 6.4 6 6z"/></svg>',
        'help' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7.5"/><path d="M7.8 7.7a2.2 2.2 0 0 1 4.3.6c0 1.5-2.1 1.9-2.1 3"/><path d="M10 14.2v.01"/></svg>',
        'eye' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 10s3-5.5 8-5.5S18 10 18 10s-3 5.5-8 5.5S2 10 2 10z"/><circle cx="10" cy="10" r="2.4"/></svg>',
        'warn' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 3l8 14H2z"/><path d="M10 8v4M10 14.5v.01"/></svg>',
        'trash' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 5.5h13M8 5.5V4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1.5M5.5 5.5l.7 10a1.5 1.5 0 0 0 1.5 1.4h4.6a1.5 1.5 0 0 0 1.5-1.4l.7-10"/></svg>',
        'login' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 4H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/><path d="M12 6l4 4-4 4M16 10H8"/></svg>',
        'upload' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 13v2.5a1.5 1.5 0 0 0 1.5 1.5h11a1.5 1.5 0 0 0 1.5-1.5V13"/><path d="M10 3v10M6 7l4-4 4 4"/></svg>',
        'org' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="6" height="10" rx="1"/><rect x="11" y="3" width="6" height="14" rx="1"/><path d="M5 10h2M5 13h2M13 6h2M13 9h2M13 12h2"/></svg>',
        'db' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><ellipse cx="10" cy="4.5" rx="6.5" ry="2.5"/><path d="M3.5 4.5v11c0 1.4 2.9 2.5 6.5 2.5s6.5-1.1 6.5-2.5v-11"/><path d="M3.5 10c0 1.4 2.9 2.5 6.5 2.5s6.5-1.1 6.5-2.5"/></svg>',
        default => '',
    };
}

// -------------------------------------------------------------------------
// Requirement checks
// -------------------------------------------------------------------------

/**
 * Server requirements for a normal install (toolkit checker; diagnostics only).
 *
 * @return list<array{label: string, detail: string, ok: bool, fix: string}>
 */
function requirement_checks(string $root): array
{
    $verdicts = (new ServerRequirementChecker())->check(new ServerRequirements(
        minPhpVersion: '8.4.0',
        requiredExtensions: ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'json', 'curl'],
        writablePaths: [$root . '/var', $root],
        requiredFiles: [$root . '/vendor/autoload.php'],
    ));

    $ok = static function (string $requirement, ?string $target = null) use ($verdicts): bool {
        foreach ($verdicts as $verdict) {
            if ($verdict->requirement !== $requirement) {
                continue;
            }
            if ($target !== null && $verdict->target !== $target) {
                continue;
            }
            if (!$verdict->satisfied) {
                return false;
            }
        }

        return true;
    };

    return [
        [
            'label' => 'PHP 8.4 以上',
            'detail' => '現在: ' . PHP_VERSION,
            'ok' => $ok(ServerRequirementChecker::REQUIREMENT_PHP),
            'fix' => 'サーバーのコントロールパネルで使用する PHP のバージョンを 8.4 以上に切り替えてください。',
        ],
        [
            'label' => 'PHP 拡張モジュール',
            'detail' => 'pdo / pdo_mysql / mbstring / openssl / json / curl',
            'ok' => $ok(ServerRequirementChecker::REQUIREMENT_EXTENSION),
            'fix' => '不足している拡張モジュールを有効化してください（ホスティングのサポートにご確認ください）。',
        ],
        [
            'label' => 'var/ ディレクトリへの書き込み権限',
            'detail' => 'インストール完了マーカーを保存します',
            'ok' => $ok(ServerRequirementChecker::REQUIREMENT_WRITABLE, $root . '/var'),
            'fix' => 'ファイルマネージャまたは FTP で <code>var/</code> フォルダのパーミッションを「書き込み可（755 または 775）」に変更してください。',
        ],
        [
            'label' => 'ルートディレクトリへの書き込み権限',
            'detail' => '.env ファイルを作成します',
            'ok' => $ok(ServerRequirementChecker::REQUIREMENT_WRITABLE, $root),
            'fix' => '展開先フォルダを一時的に書き込み可にしてください。インストール完了後は元の権限に戻して構いません。',
        ],
        [
            'label' => 'vendor/ ディレクトリ（依存一式）',
            'detail' => '依存ライブラリ',
            'ok' => $ok(ServerRequirementChecker::REQUIREMENT_FILE, $root . '/vendor/autoload.php'),
            'fix' => 'ZIP ファイルが完全に展開されているか確認してください。',
        ],
    ];
}

/**
 * Minimal requirements for the acquisition (upload) flow — vendor/ and DB are
 * not required here (vendor arrives via this very step).
 *
 * @return list<array{label: string, detail: string, ok: bool, fix: string}>
 */
function acquire_requirement_checks(string $root): array
{
    $zipOk = class_exists('ZipArchive');
    $varOk = (is_dir($root . '/var') || @mkdir($root . '/var', 0755, true)) && is_writable($root . '/var');

    return [
        [
            'label' => 'PHP 8.4 以上',
            'detail' => '現在: ' . PHP_VERSION,
            'ok' => version_compare(PHP_VERSION, '8.4.0', '>='),
            'fix' => 'サーバーのコントロールパネルで使用する PHP のバージョンを 8.4 以上に切り替えてください。',
        ],
        [
            'label' => 'zip 拡張モジュール（ZipArchive）',
            'detail' => $zipOk ? '利用可' : '利用不可',
            'ok' => $zipOk,
            'fix' => 'アップロードした ZIP を展開するには <code>zip</code> 拡張が必要です。ホスティングのサポートにご確認ください。',
        ],
        [
            'label' => 'var/ ディレクトリへの書き込み権限',
            'detail' => $varOk ? '書き込み可' : '書き込み不可',
            'ok' => $varOk,
            'fix' => 'ファイルマネージャまたは FTP で <code>var/</code> フォルダのパーミッションを「書き込み可（755 または 775）」に変更してください。',
        ],
        [
            'label' => 'ルートディレクトリへの書き込み権限',
            'detail' => 'アプリ本体を展開します',
            'ok' => is_writable($root),
            'fix' => '展開先フォルダ（public_html の 1 つ上）を一時的に書き込み可にしてください。展開後は元の権限に戻して構いません。',
        ],
    ];
}

// -------------------------------------------------------------------------
// Re-install guards
// -------------------------------------------------------------------------

/**
 * Defense in depth: even when the var/.installed marker is lost (ephemeral
 * var/), an already-provisioned database must not be re-set-up (.env
 * overwrite, second admin). Adapter-aware: probes the MySQL or SQLite
 * database the written .env points at.
 */
function database_already_provisioned(string $envFile): bool
{
    if (!is_file($envFile)) {
        return false;
    }

    $env = parse_ini_file($envFile) ?: [];
    $adapter = (string) ($env['DB_ADAPTER'] ?? '');

    try {
        if ($adapter === 'sqlite') {
            $path = (string) ($env['DB_NAME'] ?? '');
            if ($path === '' || !is_file($path)) {
                return false;
            }
            $pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } elseif ($adapter === 'mysql') {
            if (empty($env['DB_NAME'])) {
                return false;
            }
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                (string) ($env['DB_HOST'] ?? '127.0.0.1'),
                (string) ($env['DB_PORT'] ?? '3306'),
                (string) $env['DB_NAME'],
                (string) ($env['DB_CHARSET'] ?? 'utf8mb4'),
            );
            $pdo = new PDO($dsn, (string) ($env['DB_USER'] ?? ''), (string) ($env['DB_PASSWORD'] ?? ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 3,
            ]);
        } else {
            return false;
        }

        $stmt = $pdo->query('SELECT COUNT(*) FROM users');

        return $stmt !== false && (int) $stmt->fetchColumn() > 0;
    } catch (\Throwable) {
        // No env / unreachable DB / no schema yet → genuinely not provisioned.
        return false;
    }
}

// -------------------------------------------------------------------------
// Page renderer — a single function so the CLI pattern export renders the
// exact same markup the live installer serves (one source of truth).
// -------------------------------------------------------------------------

/**
 * @param array{
 *   view: string,
 *   checks?: list<array{label: string, detail: string, ok: bool, fix: string}>,
 *   reqErrors?: list<array{label: string, detail: string, ok: bool, fix: string}>,
 *   errors?: list<string>,
 *   fieldErrors?: array<string, string>,
 *   old?: array<string, string>,
 *   summary?: string,
 *   blockedMessage?: string
 * } $state
 */
function render_installer_page(array $state): string
{
    $view = $state['view'];
    $checks = $state['checks'] ?? [];
    $reqErrors = $state['reqErrors'] ?? [];
    $errors = $state['errors'] ?? [];
    $fieldErrors = $state['fieldErrors'] ?? [];
    $oldValues = $state['old'] ?? [];
    $summary = $state['summary'] ?? '';
    $blockedMessage = $state['blockedMessage'] ?? '';

    $old = static fn (string $k, string $default = ''): string => h($oldValues[$k] ?? $default);
    $hasError = $errors !== [] || $fieldErrors !== [];

    $tenantOld = ($oldValues['tenant_mode'] ?? 'single') === 'multi' ? 'multi' : 'single';
    $adapterOld = ($oldValues['db_adapter'] ?? 'mysql') === 'sqlite' ? 'sqlite' : 'mysql';

    // Host-name presets for the DB step chips (HETEML first — the known target).
    $hosts = [
        ['id' => 'heteml', 'label' => 'ヘテムル', 'host' => 'mysqlXXX.phy.heteml.lan', 'db' => '_nene_clear', 'user' => '_nene_clear', 'note' => '「データベース」→「データベース一覧」の「ホスト名（DB サーバー）」を使います。ユーザー名は DB 名と同じです。'],
        ['id' => 'sakura', 'label' => 'さくら', 'host' => 'mysqlXXX.db.sakura.ne.jp', 'db' => 'yourname_clear', 'user' => 'yourname', 'note' => '「データベース」→ 該当 DB の「データベースサーバ」欄がホスト名です。'],
        ['id' => 'xserver', 'label' => 'エックスサーバー', 'host' => 'mysqlXXXX.xserver.jp', 'db' => 'yourid_clear', 'user' => 'yourid_user', 'note' => 'サーバーパネル「MySQL 設定」→「MySQL ホスト名」を確認してください。'],
        ['id' => 'conoha', 'label' => 'ConoHa WING', 'host' => 'mysqlXXX.conoha.ne.jp', 'db' => 'yourname_clear', 'user' => 'yourname', 'note' => '「データベース」→ 対象 DB の「ホスト名」をコピーしてください。'],
        ['id' => 'other', 'label' => 'その他 / わからない', 'host' => 'localhost', 'db' => 'yourname_clear', 'user' => 'yourname', 'note' => '契約中のレンタルサーバー管理画面（コントロールパネル）の「データベース」欄で確認できます。'],
    ];

    // Stepper position (0=DB / 1=admin / 2=complete; requirements & acquire = 0).
    $stepIdx = match ($view) {
        'admin' => 1,
        'complete' => 2,
        default => 0,
    };
    $vsteps = [
        ['t' => 'データベース', 'd' => '接続情報の入力'],
        ['t' => '管理者設定', 'd' => '組織とアカウント作成'],
        ['t' => '完了', 'd' => 'セットアップ終了'],
    ];

    // ---- reusable fragments ----
    $reqList = static function (array $rows): string {
        $html = '<ul class="reqs">';
        foreach ($rows as $c) {
            $html .= '<li class="' . ($c['ok'] ? 'pass' : 'fail') . '">'
                . '<span class="ic">' . ($c['ok'] ? ico('check') : ico('x')) . '</span>'
                . '<div class="rq-body"><div class="rq-t">' . h($c['label']) . '</div>'
                . '<div class="rq-d">' . h($c['detail']) . '</div>'
                . (!$c['ok'] ? '<div class="rq-fix"><b>解決方法:</b> ' . $c['fix'] . '</div>' : '')
                . '</div></li>';
        }

        return $html . '</ul>';
    };
    $alert = static fn (string $kind, string $title, string $textHtml, string $detail = ''): string => '<div class="alert ' . $kind . '">' . ico($kind === 'ok' ? 'check' : 'warn')
        . '<div class="a-body"><div class="a-title">' . h($title) . '</div><div class="a-text">' . $textHtml . '</div>'
        . ($detail !== '' ? '<details><summary>技術的な詳細を表示</summary><div class="det">' . h($detail) . '</div></details>' : '')
        . '</div></div>';
    $fieldErr = static function (string $key, string $hint) use ($fieldErrors): string {
        if (isset($fieldErrors[$key])) {
            return '<p class="err-text">' . ico('warn') . h($fieldErrors[$key]) . '</p>';
        }

        return $hint !== '' ? '<p class="hint">' . $hint . '</p>' : '';
    };
    $inputClass = static fn (string $key): string => isset($fieldErrors[$key]) ? 'input is-error' : 'input';

    // ---- view body ----
    $body = '';

    if ($view === 'blocked') {
        $body = '<div class="iz-head">インストールできません</div>'
            . $alert('error', 'インストールがブロックされました', h($blockedMessage))
            . '<div class="sec-warn"><span class="sw-ico">' . ico('trash') . '</span><div>'
            . '<div class="sw-t">セキュリティ: install.php を削除してください</div>'
            . '<div class="sw-d">構成済みの環境に <code>install.php</code> を残すと、第三者に再セットアップされる恐れがあります。FTP またはファイルマネージャから<b>今すぐ削除</b>してください。</div>'
            . '</div></div>';
    } elseif ($view === 'acquire') {
        $body = '<div class="iz-head">アプリの取得（アップロード）</div>'
            . '<div class="iz-headsub">アプリ本体（<code>vendor/</code> など）がまだ展開されていません。<b>公式配布元からダウンロードした ZIP</b> をアップロードして展開します。</div>';
        if ($errors !== []) {
            $body .= $alert('error', 'アップロードを処理できませんでした', h(implode(' ', $errors)));
        }
        if ($reqErrors !== []) {
            $body .= $alert('error', '展開に必要な条件が不足しています', '以下を解消してから、ページを再読み込みしてください。');
        }
        $body .= $reqList($checks);
        if ($reqErrors === []) {
            $body .= '<form method="post" action="install.php" id="acquireForm" enctype="multipart/form-data">'
                . '<input type="hidden" name="action" value="acquire">'
                . '<div class="field"><label class="label">配布 ZIP ファイル<span class="req">*</span>'
                . '<span class="tip" tabindex="0">?<span class="tip-body">NeNe Clear の公式リリースから入手した <code>nene-clear-*.zip</code> を選んでください。他のファイルはアップロードしないでください。</span></span></label>'
                . '<label class="up-drop" id="upDrop" for="payloadFile">'
                . '<span class="ud-ic">' . ico('upload') . '</span>'
                . '<span class="ud-t">ZIP ファイルを選択</span>'
                . '<span class="ud-d">クリックして <code>nene-clear-*.zip</code> を選択（.zip のみ）</span>'
                . '<span class="ud-file" id="upFileName" hidden></span>'
                . '<input type="file" id="payloadFile" name="payload" accept=".zip,application/zip">'
                . '</label>'
                . '<p class="hint"><b>公式配布元から入手した ZIP のみを使用してください。</b>出所不明の ZIP はアップロードしないでください。</p></div>'
                . '<div class="field"><label class="label" for="expected_sha256">期待する SHA-256<span class="req">*</span>'
                . '<span class="tip" tabindex="0">?<span class="tip-body">公式リリースに記載されている ZIP の SHA-256（64 桁の 16 進数）を貼り付けてください。アップロードしたファイルのハッシュと照合し、一致した場合のみ展開します。</span></span></label>'
                . '<input id="expected_sha256" name="expected_sha256" class="input mono" value="' . $old('expected_sha256') . '" placeholder="例: 87ad1447…（64 桁の 16 進数）" autocomplete="off" spellcheck="false" required>'
                . '<p class="hint">展開の<b>前</b>にハッシュを照合します（不一致なら展開しません）。この段階では署名検証は行いません（配布元の SHA-256 照合のみ）。</p></div>'
                . '<div class="btn-row"><button type="submit" class="btn btn-primary btn-block">アップロードして展開' . ico('arrow') . '</button></div>'
                . '</form>';
        } else {
            $body .= '<div class="btn-row"><a class="btn btn-primary btn-block" href="install.php">再読み込みして再チェック</a></div>';
        }
    } elseif ($view === 'requirements') {
        $body = '<div class="iz-head">サーバー要件の確認</div>'
            . '<div class="iz-headsub">インストールを始める前に、サーバーが NeNe Clear の動作条件を満たしているか確認します。</div>';
        $body .= $reqErrors === []
            ? $alert('ok', 'すべての要件を満たしています', 'このサーバーでインストールを続行できます。')
            : $alert('error', '要件チェックに失敗しました', '以下を解消してから、ページを再読み込みしてください。解決後にセットアップを続行できます。');
        $body .= '<div class="alert warn">' . ico('warn') . '<div class="a-body"><div class="a-title">本番データを扱う場合の推奨環境</div>'
            . '<div class="a-text">NeNe Clear は銀行入金・取引先情報（PII）を扱います。本番運用は <b>VPS + Docker</b> を推奨します（共有ホスティングは非推奨）。運用時は <code>NENE_CLEAR_ENCRYPTION_KEY</code>（保存時暗号化）の設定も検討してください。</div></div></div>';
        $body .= $reqList($checks);
        $body .= '<div class="btn-row">'
            . ($reqErrors === []
                ? '<a class="btn btn-primary btn-block" href="install.php?step=1">セットアップを開始' . ico('arrow') . '</a>'
                : '<a class="btn btn-primary btn-block" href="install.php">再読み込みして再チェック</a>')
            . '</div>';
    } elseif ($view === 'database') {
        $body = '<div class="iz-head">データベースに接続</div>'
            . '<div class="iz-headsub">接続情報を入力してください。MySQL の値は契約中の<b>レンタルサーバー管理画面（コントロールパネル）の「データベース」欄</b>で確認できます。</div>';
        if ($errors !== []) {
            $body .= $alert(
                'error',
                'データベースに接続できませんでした',
                'ホスト名・ポート・ユーザー名・パスワードをご確認ください。共有サーバーではホスト名が <code>localhost</code> ではなく専用ホスト名のことが多いです。',
                implode("\n", $errors),
            );
        }

        $chips = '';
        foreach ($hosts as $hh) {
            $chips .= '<button type="button" class="host-chip" data-id="' . h($hh['id']) . '" data-host="' . h($hh['host']) . '" data-db="' . h($hh['db']) . '" data-user="' . h($hh['user']) . '" data-note="' . h($hh['note']) . '">' . h($hh['label']) . '</button>';
        }

        $mysqlHidden = $adapterOld === 'sqlite' ? ' hidden' : '';
        $sqliteHidden = $adapterOld === 'sqlite' ? '' : ' hidden';
        $mysqlSel = $adapterOld === 'mysql' ? ' selected' : '';
        $sqliteSel = $adapterOld === 'sqlite' ? ' selected' : '';

        $body .= '<form method="post" action="install.php?step=1" id="dbForm">'
            . '<div class="field"><label class="label" for="db_adapter">データベースの種類'
            . '<span class="tip" tabindex="0">?<span class="tip-body">通常は MySQL を選びます。SQLite はお試し・単一プロセス向けで、本番運用には推奨しません。</span></span></label>'
            . '<select id="db_adapter" name="db_adapter" class="select">'
            . '<option value="mysql"' . $mysqlSel . '>MySQL（推奨・共有ホスティング）</option>'
            . '<option value="sqlite"' . $sqliteSel . '>SQLite（お試し・単一ファイル）</option>'
            . '</select></div>'
            . '<div id="sqliteNote"' . $sqliteHidden . '>'
            . '<div class="alert warn">' . ico('warn') . '<div class="a-body"><div class="a-title">SQLite はお試し向けです</div>'
            . '<div class="a-text">データは <code>database/nene_clear.sqlite3</code> に保存されます。同時アクセスに弱いため（database is locked）、本番運用では MySQL を推奨します。</div></div></div></div>'
            . '<div id="mysqlFields"' . $mysqlHidden . '>'
            . '<div class="host-help"><div class="hh-q">' . ico('help') . 'お使いのレンタルサーバーは？</div>'
            . '<div class="hh-sub">選ぶと、ホスト名の<b>記入例</b>を自動入力します（実際の値はコントロールパネルでご確認ください）。</div>'
            . '<div class="host-chips" id="hostChips">' . $chips . '</div>'
            . '<button type="button" class="linkbtn cp-toggle" id="cpToggle">コントロールパネルのどこを見る？</button>'
            . '<div class="cp-diagram" id="cpDiagram" hidden>'
            . '<div class="cp-bar"><span class="dot"></span><span class="dot"></span><span class="dot"></span><span class="cp-url">https://cp.your-host.example/database</span></div>'
            . '<div class="cp-grid"><div class="cp-menu">'
            . '<div class="cp-mi"><span class="cp-bullet"></span>ドメイン</div><div class="cp-mi"><span class="cp-bullet"></span>メール</div>'
            . '<div class="cp-mi hot"><span class="cp-bullet"></span>データベース</div><div class="cp-mi"><span class="cp-bullet"></span>FTP</div><div class="cp-mi"><span class="cp-bullet"></span>SSL</div>'
            . '</div><div class="cp-body"><div class="cp-h">データベース情報</div><div class="cp-kv">'
            . '<span class="k">ホスト名</span><span class="v hl" id="cpHost">localhost</span>'
            . '<span class="k">データベース名</span><span class="v" id="cpDb">yourname_clear</span>'
            . '<span class="k">ユーザー名</span><span class="v" id="cpUser">yourname</span>'
            . '<span class="k">ポート</span><span class="v">3306</span>'
            . '</div><div class="cp-note" id="cpNote">契約中のレンタルサーバー管理画面（コントロールパネル）の「データベース」欄で確認できます。黄色の<b>ホスト名</b>を下のフォームにそのまま貼り付けてください。</div></div></div></div></div>'
            . '<div class="form-row2">'
            . '<div class="field"><label class="label" for="db_host">ホスト<span class="req">*</span>'
            . '<span class="tip" tabindex="0">?<span class="tip-body">データベースサーバーのアドレス。共有ホスティングでは <code>localhost</code> ではなく専用ホスト名（例 mysqlXXX.phy.heteml.lan）のことが多いです。</span></span></label>'
            . '<input id="db_host" name="db_host" class="input mono" value="' . $old('db_host', 'localhost') . '" placeholder="例: mysqlXXX.phy.heteml.lan"></div>'
            . '<div class="field"><label class="label" for="db_port">ポート<span class="req">*</span>'
            . '<span class="tip" tabindex="0">?<span class="tip-body">通常は MySQL 既定の <code>3306</code> のままで問題ありません。</span></span></label>'
            . '<input id="db_port" name="db_port" class="input mono" value="' . $old('db_port', '3306') . '"></div>'
            . '</div>'
            . '<div class="field"><label class="label" for="db_name">データベース名<span class="req">*</span>'
            . '<span class="tip" tabindex="0">?<span class="tip-body">コントロールパネルで作成済みのデータベース名。空のデータベースを指定してください（既存データには触れません）。</span></span></label>'
            . '<input id="db_name" name="db_name" class="input mono" value="' . $old('db_name') . '" placeholder="例: yourname_clear">'
            . '<p class="hint">事前に作成した<b>空のデータベース</b>を指定します。テーブルはこのインストーラが作成します。</p></div>'
            . '<div class="field"><label class="label" for="db_user">ユーザー名<span class="req">*</span>'
            . '<span class="tip" tabindex="0">?<span class="tip-body">そのデータベースにアクセスできる MySQL ユーザー名。コントロールパネルの DB 情報に記載されています。</span></span></label>'
            . '<input id="db_user" name="db_user" class="input mono" value="' . $old('db_user') . '" placeholder="例: yourname_clear"></div>'
            . '<div class="field"><label class="label" for="db_password">パスワード<span class="opt">（サーバーによっては任意）</span>'
            . '<span class="tip" tabindex="0">?<span class="tip-body">上記 MySQL ユーザーのパスワード。コントロールパネルで設定／確認できます。<b>NeNe Clear のログインパスワードとは別物</b>です。</span></span></label>'
            . '<div class="pw-wrap"><input id="db_password" name="db_password" class="input mono" type="password" value="' . $old('db_password') . '" placeholder="••••••••">'
            . '<button type="button" class="pw-eye" data-pw="db_password" tabindex="-1" aria-label="パスワード表示切替">' . ico('eye') . '</button></div>'
            . '<p class="hint">サーバーの DB ユーザーのパスワード。<b>NeNe Clear のログインパスワードとは別物</b>です。</p></div>'
            . '</div>'
            . '<div class="btn-row"><a class="btn btn-ghost btn-back" href="install.php" aria-label="戻る">' . ico('back') . '</a>'
            . '<button type="submit" class="btn btn-primary">接続テスト＆スキーマ適用' . ico('arrow') . '</button></div>'
            . '</form>';
    } elseif ($view === 'admin') {
        $isMulti = $tenantOld === 'multi';
        $body = '<div class="iz-head">組織と管理者アカウントを作成</div>'
            . '<div class="iz-headsub">最初にサインインする管理者アカウントを設定します。利用形態は後から組織を追加する場合のみ「複数組織」を選んでください。</div>';
        if ($errors !== []) {
            $body .= $alert('error', '入力内容を確認してください', h(implode(' ', $errors)));
        }

        $singleOn = $isMulti ? '' : ' on';
        $multiOn = $isMulti ? ' on' : '';
        $singleChecked = $isMulti ? '' : ' checked';
        $multiChecked = $isMulti ? ' checked' : '';
        $singleHidden = $isMulti ? ' hidden' : '';

        $body .= '<form method="post" action="install.php?step=2" id="adminForm">'
            . '<div class="tenant-sec"><div class="ts-h">' . ico('org') . '利用形態</div>'
            . '<label class="opt-card' . $singleOn . '" data-tenant="single"><input type="radio" name="tenant_mode" value="single"' . $singleChecked . '>'
            . '<div><div class="oc-t">単一組織（single）<span class="oc-badge">既定</span></div>'
            . '<div class="oc-d">1 つの会社／組織だけで使う一般的な構成。組織と管理者（admin）アカウントを作成します。</div></div></label>'
            . '<label class="opt-card' . $multiOn . '" data-tenant="multi"><input type="radio" name="tenant_mode" value="multi"' . $multiChecked . '>'
            . '<div><div class="oc-t">複数組織（multi）<span class="oc-badge">上級者向け</span></div>'
            . '<div class="oc-d">複数の組織（テナント）を 1 つのインストールで運用します。横断管理者（superadmin）を作成し、組織はログイン後の管理画面から追加します。</div></div></label>'
            . '</div>'
            . '<div id="singleFields"' . $singleHidden . '>'
            . '<div class="field"><label class="label" for="org_name">組織名（会社名）<span class="req">*</span>'
            . '<span class="tip" tabindex="0">?<span class="tip-body">督促状の差出人など、画面や帳票の表示に使われる組織の正式名称です。後から変更できます。</span></span></label>'
            . '<input id="org_name" name="org_name" class="' . $inputClass('org_name') . '" value="' . $old('org_name') . '" placeholder="例: 株式会社ねね商事">'
            . $fieldErr('org_name', '督促状の差出人などに表示されます（後から変更可）。')
            . '</div>'
            . '<div class="field"><label class="label" for="org_slug">組織スラッグ<span class="opt">（任意・英数字とハイフン）</span>'
            . '<span class="tip" tabindex="0">?<span class="tip-body">システム内部で組織を識別する短い英数字 ID。空欄なら組織名から自動生成します。</span></span></label>'
            . '<input id="org_slug" name="org_slug" class="' . $inputClass('org_slug') . ' mono" value="' . $old('org_slug') . '" placeholder="例: nene-shoji">'
            . $fieldErr('org_slug', '空欄で自動生成。小文字英数字とハイフンのみ。')
            . '</div></div>'
            . '<div class="field"><label class="label" for="admin_email">管理者メールアドレス<span class="req">*</span>'
            . '<span class="tip" tabindex="0">?<span class="tip-body">最初の管理者アカウントのログイン ID になります。運用担当者のメールを推奨します。</span></span></label>'
            . '<input id="admin_email" name="admin_email" type="email" class="' . $inputClass('admin_email') . '" value="' . $old('admin_email') . '" placeholder="例: admin@yourcompany.co.jp" required>'
            . $fieldErr('admin_email', 'このメールが<b>最初の管理者ログイン ID</b> になります。')
            . '</div>'
            . '<div class="field"><label class="label" for="admin_password">管理者パスワード<span class="opt">（12 文字以上）</span><span class="req">*</span>'
            . '<span class="tip" tabindex="0">?<span class="tip-body">12 文字以上。パスワードは安全にハッシュ化して保存され、元の文字列は保持されません。<b>DB 接続パスワードとは別物</b>です。</span></span></label>'
            . '<div class="pw-wrap"><input id="admin_password" name="admin_password" class="' . $inputClass('admin_password') . '" type="password" placeholder="12 文字以上" required minlength="12">'
            . '<button type="button" class="pw-eye" data-pw="admin_password" tabindex="-1" aria-label="パスワード表示切替">' . ico('eye') . '</button></div>'
            . $fieldErr('admin_password', '12 文字以上。<b>ハッシュ化して安全に保管</b>されます（元の文字列は保存されません）。')
            . '</div>'
            . '<div class="btn-row"><a class="btn btn-ghost btn-back" href="install.php?step=1" aria-label="戻る">' . ico('back') . '</a>'
            . '<button type="submit" class="btn btn-primary">インストールを実行' . ico('arrow') . '</button></div>'
            . '</form>';
    } else { // complete
        $body = '<div class="done-mark">' . ico('check') . '</div>'
            . '<div class="done-title">インストール完了</div>'
            . '<div class="done-sub">' . h($summary) . '</div>'
            . '<div class="sec-warn"><span class="sw-ico">' . ico('trash') . '</span><div>'
            . '<div class="sw-t">セキュリティ: 必ず install.php を削除してください</div>'
            . '<div class="sw-d">放置すると第三者に再セットアップされる恐れがあります。FTP またはファイルマネージャから <code>install.php</code> を<b>削除（またはリネーム）</b>してください。</div>'
            . '</div></div>'
            . '<div class="next-h">次のステップ</div>'
            . '<ol class="next-list">'
            . '<li><span class="nl-n">1</span><div><b><code>install.php</code> を削除する</b><div class="nl-d">最優先。サーバーからこのファイルを消します。</div></div></li>'
            . '<li><span class="nl-n">2</span><div><b>管理画面にログイン</b><div class="nl-d">先ほど設定した管理者メール・パスワードで。</div></div></li>'
            . '<li><span class="nl-n">3</span><div><b>銀行口座（CSV 取込プロファイル）を設定</b><div class="nl-d">設定画面で口座と CSV 列の対応を登録すると、入金 CSV の取込と消込を始められます。</div></div></li>'
            . '</ol>'
            . '<a class="btn btn-primary btn-block btn-lg" href="./">' . ico('login') . '管理画面にログイン</a>';
    }

    // ---- stepper fragments ----
    $vstepHtml = '';
    $hstepHtml = '';
    foreach ($vsteps as $i => $s) {
        $cls = $i === $stepIdx ? 'active' : ($i < $stepIdx ? 'done' : '');
        $vstepHtml .= '<li class="' . $cls . '"><div class="vs-rail"><span class="vs-dot">' . ($i < $stepIdx ? ico('check') : (string) ($i + 1)) . '</span><span class="vs-line"></span></div>'
            . '<div class="vs-body"><div class="vs-t">' . h($s['t']) . '</div><div class="vs-d">' . h($s['d']) . '</div></div></li>';
        $hstepHtml .= '<div class="hs ' . $cls . '">' . ($i + 1) . '. ' . h($s['t']) . '</div>';
    }

    $errFlag = $hasError ? '1' : '0';
    $viewAttr = h($view);
    $mark = ico('mark');
    $shield = ico('shield');
    $server = ico('server');
    $oss = ico('oss');
    $warnIco = ico('warn');
    $css = installer_css();

    return <<<HTML
    <!DOCTYPE html>
    <html lang="ja">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NeNe Clear — セットアップウィザード</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 42 42'%3E%3Crect width='42' height='42' rx='8' fill='%23132f54'/%3E%3Cpath d='M10 15h22M10 15v14a3 3 0 003 3h16a3 3 0 003-3V15' stroke='%23fff' fill='none' stroke-width='2.4'/%3E%3Cpath d='M15 26h7M25.5 25.5l2.5 2.5 4.5-5' stroke='%237fb2ff' fill='none' stroke-width='2.4' stroke-linecap='round'/%3E%3C/svg%3E">
    <style>{$css}</style>
    </head>
    <body data-view="{$viewAttr}" data-error="{$errFlag}">
    <div class="iz"><div class="iz-stage">
      <aside class="iz-aside">
        <div class="iz-bs-top">
          <span class="mono-mark">{$mark}</span>
          <div><div class="abt-name">NeNe Clear</div><div class="abt-sub">Setup Wizard</div></div>
        </div>
        <div class="iz-bs-mid">
          <h2>入金消込から督促まで、<br>確実に、ひと続きで。</h2>
          <p class="lead">銀行入金データと請求を正確に突合し、未収の取りこぼしを防ぐ。経理の現場のための堅実な消込・債権管理基盤です。3 ステップでセットアップが完了します。</p>
          <ul class="vstep">{$vstepHtml}</ul>
        </div>
        <div class="iz-bs-foot">
          <div class="iz-trust">
            <span class="tb">{$shield}銀行CSV自動突合</span>
            <span class="tb">{$server}セルフホスト</span>
            <span class="tb">{$oss}オープンソース（MIT）</span>
          </div>
          <div class="copy">© 2026 NeNe Clear — install.php</div>
        </div>
      </aside>
      <div class="iz-main">
        <div class="iz-form" id="izView">
          <div class="hstep">{$hstepHtml}</div>
          {$body}
        </div>
        <div class="iz-form iz-loading" id="izLoading" hidden>
          <div class="ld-h">インストールしています</div>
          <div class="ld-sub">接続の確認からテーブル作成までを順に実行しています。完了までこのページを開いたままにしてください。</div>
          <div class="ld-bar"><span id="ldBar"></span></div>
          <ul class="substeps" id="substeps"></ul>
          <div class="ld-warn">{$warnIco}このページを閉じたり、ボタンを二度押ししないでください。</div>
        </div>
      </div>
    </div></div>
    <script src="installer.js"></script>
    </body>
    </html>
    HTML;
}

/** The installer stylesheet (ClaudeDesign delivery 2026-07-10, #273). */
function installer_css(): string
{
    return <<<'CSS'
    :root{
      --font-sans:"Noto Sans JP",system-ui,-apple-system,"Hiragino Kaku Gothic ProN","Yu Gothic",sans-serif;
      --font-num:ui-monospace,"SFMono-Regular","Menlo","Consolas",monospace;
      /* navy ramp — NeNe Clear */
      --navy-900:#0a1c30;--navy-800:#0f2540;--navy-700:#16304f;--navy-600:#1e3a5f;
      --navy-500:#2c5282;--navy-400:#3d6ba3;--navy-100:#e5ecf5;--navy-50:#eef2f8;
      --bg:#eef1f6;--surface:#ffffff;--surface-sunk:#f6f8fb;
      --border:#dce3ec;--border-strong:#c4cedb;
      --fg:#19232f;--fg-muted:#5a6b80;--fg-subtle:#8595a8;--fg-faint:#9aa8ba;
      --brand:#1e3a5f;--brand-strong:#16304f;--brand-deep:#0a1c30;
      --brand-soft:#eef2f8;--on-brand:#ffffff;
      --ok:#1f6b4f;--ok-soft:#e6f1ec;--ok-line:#bcd9cc;
      --danger:#9c2c2c;--danger-soft:#f6e7e7;--danger-line:#e3bcbc;
      --warn:#8a5a16;--warn-soft:#f7eede;--warn-line:#e8d3a8;
      --side-accent:#7fb0e6;
      --radius:2px;--radius-sm:2px;
      --ring:0 0 0 3px rgba(44,82,130,.18);
      --ease:cubic-bezier(.2,.7,.3,1);
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    html,body{height:100%}
    body{font-family:var(--font-sans);background:var(--bg);color:var(--fg);line-height:1.55;-webkit-font-smoothing:antialiased;font-size:13px}
    code{font-family:var(--font-num);background:var(--navy-50);color:var(--brand-strong);padding:.06em .38em;border-radius:2px;font-size:.9em}
    b{font-weight:700}
    .iz{min-height:100vh}
    .iz-stage{display:grid;grid-template-columns:minmax(380px,0.9fr) 1.1fr;min-height:100vh}

    /* ============ ASIDE (navy panel — matches login-aside) ============ */
    .iz-aside{position:relative;overflow:hidden;color:#d4dde7;display:flex;flex-direction:column;justify-content:space-between;
      padding:48px 46px 38px;background:var(--navy-800)}
    .iz-aside::before{content:"";position:absolute;inset:0;pointer-events:none;opacity:.4;
      background-image:linear-gradient(rgba(255,255,255,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.05) 1px,transparent 1px);
      background-size:34px 34px;mask-image:linear-gradient(150deg,#000 6%,transparent 82%)}
    .iz-aside::after{content:"";position:absolute;inset:0;pointer-events:none;
      background:radial-gradient(120% 80% at 100% 0%,rgba(61,107,163,.38),transparent 60%),linear-gradient(180deg,rgba(255,255,255,.04),transparent 28%)}
    .iz-aside>*{position:relative;z-index:1}
    .iz-bs-top{display:flex;align-items:center;gap:13px}
    .mono-mark{width:38px;height:36px;flex:none;color:var(--side-accent)}
    .mono-mark svg{width:100%;height:100%;display:block}
    .abt-name{font-size:19px;font-weight:700;color:#fff;letter-spacing:.02em}
    .abt-sub{font-size:10px;color:#8fa6c4;letter-spacing:.16em;text-transform:uppercase;margin-top:2px}
    .iz-bs-mid{max-width:400px}
    .iz-bs-mid h2{font-size:25px;font-weight:700;line-height:1.5;color:#fff;text-wrap:balance;letter-spacing:.01em}
    .iz-bs-mid .lead{font-size:13px;color:#b9c6d6;margin-top:15px;line-height:1.85}

    /* vertical step tracker */
    .vstep{list-style:none;margin:32px 0 0}
    .vstep li{display:flex;gap:14px}
    .vs-rail{display:flex;flex-direction:column;align-items:center;flex:none}
    .vs-dot{width:30px;height:30px;border-radius:50%;display:grid;place-items:center;font-family:var(--font-num);font-size:13px;font-weight:600;
      background:rgba(255,255,255,.08);color:#a9bcd4;border:1px solid rgba(255,255,255,.18);transition:all .3s var(--ease)}
    .vs-dot svg{width:14px;height:14px}
    .vs-line{width:2px;flex:1;min-height:24px;background:rgba(255,255,255,.14);margin:4px 0;position:relative;overflow:hidden}
    .vstep li:last-child .vs-line{display:none}
    .vs-body{padding-top:4px;padding-bottom:20px}
    .vs-t{font-size:14px;font-weight:600;color:#c6d2e0}
    .vs-d{font-size:11.5px;color:#8093a8;margin-top:2px}
    .vstep li.active .vs-dot{background:var(--side-accent);color:var(--navy-900);border-color:var(--side-accent)}
    .vstep li.active .vs-t{color:#fff}
    .vstep li.done .vs-dot{background:rgba(127,176,230,.16);color:var(--side-accent);border-color:rgba(127,176,230,.4)}
    .vstep li.done .vs-line::after{content:"";position:absolute;inset:0;background:var(--side-accent);opacity:.65}

    /* trust badges */
    .iz-trust{display:flex;flex-wrap:wrap;gap:9px 16px}
    .tb{display:inline-flex;align-items:center;gap:7px;font-size:11px;color:#9db0c8}
    .tb svg{width:14px;height:14px;color:var(--side-accent);opacity:.9}
    .copy{font-size:10.5px;color:#6d819b;margin-top:15px;letter-spacing:.02em}

    /* ============ MAIN (white form face) ============ */
    .iz-main{display:flex;align-items:flex-start;justify-content:center;padding:60px 52px;overflow-y:auto;background:var(--surface)}
    .iz-form{width:100%;max-width:560px}
    .hstep{display:none;gap:6px;margin-bottom:26px}
    .hs{flex:1;text-align:center;font-size:11.5px;font-weight:600;padding:8px 4px;border-radius:2px;color:var(--fg-faint);background:var(--surface-sunk);border:1px solid var(--border)}
    .hs.active{background:var(--brand);color:var(--on-brand);border-color:var(--brand)}
    .hs.done{background:var(--navy-50);color:var(--brand-strong);border-color:var(--navy-100)}
    .hs.done::before{content:"✓ "}
    .iz-head{font-size:24px;font-weight:700;letter-spacing:.01em;color:var(--fg)}
    .iz-headsub{font-size:13px;color:var(--fg-muted);margin:10px 0 26px;line-height:1.85}

    /* alerts */
    .alert{display:flex;gap:12px;padding:14px 16px;border-radius:2px;margin-bottom:20px;font-size:13px;border:1px solid;border-left-width:3px}
    .alert>svg{width:18px;height:18px;flex:none;margin-top:2px}
    .alert.ok{background:var(--ok-soft);border-color:var(--ok-line);border-left-color:var(--ok);color:var(--ok)}
    .alert.error{background:var(--danger-soft);border-color:var(--danger-line);border-left-color:var(--danger);color:var(--danger)}
    .alert.warn{background:var(--warn-soft);border-color:var(--warn-line);border-left-color:var(--warn);color:var(--warn)}
    .a-title{font-weight:700}
    .a-text{margin-top:3px;color:inherit;opacity:.94;line-height:1.7}
    .alert details{margin-top:8px}
    .alert summary{cursor:pointer;font-size:12px;font-weight:600}
    .alert .det{font-family:var(--font-num);font-size:11.5px;white-space:pre-wrap;word-break:break-all;background:rgba(255,255,255,.6);border-radius:2px;padding:8px 10px;margin-top:6px}

    /* requirement rows */
    .reqs{list-style:none;margin:0 0 26px;border:1px solid var(--border);border-radius:2px;overflow:hidden}
    .reqs li{display:flex;gap:13px;padding:14px 16px;border-bottom:1px solid var(--border);background:var(--surface)}
    .reqs li:last-child{border-bottom:none}
    .reqs .ic{width:22px;height:22px;flex:none;border-radius:50%;display:grid;place-items:center;margin-top:2px}
    .reqs .ic svg{width:12px;height:12px}
    .reqs li.pass .ic{background:var(--ok-soft);color:var(--ok)}
    .reqs li.fail .ic{background:var(--danger-soft);color:var(--danger)}
    .rq-t{font-size:13.5px;font-weight:600;color:var(--fg)}
    .rq-d{font-size:12px;color:var(--fg-subtle);font-family:var(--font-num)}
    .rq-fix{font-size:12px;color:var(--danger);margin-top:6px;line-height:1.7}

    /* fields */
    .field{margin-bottom:18px}
    .label{display:flex;align-items:center;gap:6px;font-size:12.5px;font-weight:600;margin-bottom:7px;color:var(--fg-muted)}
    .req{color:var(--danger)}
    .opt{font-size:11px;font-weight:500;color:var(--fg-subtle)}
    .input,.select{width:100%;padding:10px 12px;font-size:14px;font-family:inherit;color:var(--fg);background:var(--surface);border:1px solid var(--border-strong);border-radius:2px;transition:border-color .15s,box-shadow .15s}
    .input:focus,.select:focus{outline:none;border-color:var(--navy-500);box-shadow:var(--ring)}
    .input.mono{font-family:var(--font-num);font-size:13.5px}
    .input.is-error{border-color:var(--danger);box-shadow:0 0 0 3px rgba(156,44,44,.15)}
    .select{appearance:none;padding-right:34px;
      background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'><path fill='%235a6b80' d='M1 1l5 5 5-5'/></svg>");
      background-repeat:no-repeat;background-position:right 12px center;background-size:11px}
    .hint{font-size:11.5px;color:var(--fg-subtle);margin-top:6px;line-height:1.65}
    .err-text{display:flex;align-items:center;gap:5px;font-size:12px;color:var(--danger);font-weight:600;margin-top:6px}
    .err-text svg{width:13px;height:13px}
    .form-row2{display:grid;grid-template-columns:1fr 140px;gap:14px}

    /* tooltip */
    .tip{position:relative;display:inline-grid;place-items:center;width:16px;height:16px;border-radius:50%;background:var(--surface-sunk);border:1px solid var(--border-strong);color:var(--fg-subtle);font-size:10.5px;font-weight:700;cursor:help}
    .tip-body{position:absolute;left:50%;bottom:calc(100% + 8px);transform:translateX(-50%) translateY(4px);width:260px;background:var(--navy-800);color:#dbe4ef;font-size:11.5px;font-weight:400;line-height:1.7;padding:10px 12px;border-radius:3px;opacity:0;pointer-events:none;transition:opacity .16s,transform .16s;z-index:10;box-shadow:0 8px 24px rgba(10,22,40,.3)}
    .tip:hover .tip-body,.tip:focus .tip-body{opacity:1;transform:translateX(-50%) translateY(0)}

    /* password eye */
    .pw-wrap{position:relative}
    .pw-wrap .input{padding-right:42px}
    .pw-eye{position:absolute;right:6px;top:50%;transform:translateY(-50%);width:30px;height:30px;display:grid;place-items:center;background:none;border:0;border-radius:2px;color:var(--fg-subtle);cursor:pointer;transition:background .12s,color .12s}
    .pw-eye:hover{background:var(--surface-sunk);color:var(--brand)}
    .pw-eye svg{width:17px;height:17px}

    /* buttons */
    .btn-row{display:flex;gap:10px;margin-top:28px}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 22px;font-size:14px;font-weight:700;font-family:inherit;border-radius:2px;border:1px solid transparent;cursor:pointer;text-decoration:none;transition:background .15s,border-color .15s,transform .15s,box-shadow .15s}
    .btn svg{width:16px;height:16px;transition:transform .18s var(--ease)}
    .btn-primary{background:var(--brand);color:var(--on-brand);flex:1;box-shadow:0 1px 2px rgba(10,22,40,.12)}
    .btn-primary:hover{background:var(--brand-strong);transform:translateY(-1px);box-shadow:0 6px 18px rgba(15,37,64,.22)}
    .btn-primary:hover svg{transform:translateX(3px)}
    .btn-primary:active{background:var(--brand-deep);transform:translateY(0)}
    .btn-ghost{background:var(--surface);border-color:var(--border-strong);color:var(--fg-muted)}
    .btn-ghost:hover{background:var(--surface-sunk);border-color:var(--fg-muted)}
    .btn-block{width:100%}
    .btn-lg{padding:14px 24px;font-size:15px}
    .btn-back{flex:none;padding:12px 14px}
    .btn-back:hover svg{transform:translateX(-3px)}

    /* host help / control panel diagram */
    .host-help{background:var(--surface-sunk);border:1px solid var(--border);border-radius:2px;padding:15px 16px;margin-bottom:20px}
    .hh-q{display:flex;align-items:center;gap:7px;font-size:13px;font-weight:700;color:var(--fg)}
    .hh-q svg{width:15px;height:15px;color:var(--brand)}
    .hh-sub{font-size:11.5px;color:var(--fg-subtle);margin:5px 0 11px}
    .host-chips{display:flex;flex-wrap:wrap;gap:7px}
    .host-chip{padding:6px 13px;font-size:12px;font-weight:600;font-family:inherit;background:var(--surface);border:1px solid var(--border-strong);border-radius:99px;color:var(--fg-muted);cursor:pointer;transition:all .15s var(--ease)}
    .host-chip:hover{border-color:var(--navy-400);color:var(--brand);background:var(--navy-50)}
    .host-chip.on{background:var(--brand);border-color:var(--brand);color:var(--on-brand);box-shadow:0 2px 8px rgba(30,58,95,.25)}
    .linkbtn{background:none;border:0;padding:0;font-family:inherit;font-size:12px;font-weight:600;color:var(--navy-500);cursor:pointer;margin-top:12px;display:inline-flex;align-items:center;gap:5px}
    .linkbtn:hover{color:var(--brand-strong)}
    .linkbtn svg{width:13px;height:13px;transition:transform .18s var(--ease)}
    .linkbtn.open svg{transform:rotate(180deg)}
    .cp-diagram{margin-top:12px;border:1px solid var(--border-strong);border-radius:2px;overflow:hidden;background:var(--surface);animation:izReveal .3s var(--ease)}
    .cp-bar{display:flex;align-items:center;gap:5px;padding:7px 12px;background:var(--surface-sunk);border-bottom:1px solid var(--border)}
    .cp-bar .dot{width:8px;height:8px;border-radius:50%;background:var(--border-strong)}
    .cp-url{font-family:var(--font-num);font-size:10.5px;color:var(--fg-faint);margin-left:8px}
    .cp-grid{display:grid;grid-template-columns:120px 1fr}
    .cp-menu{border-right:1px solid var(--border);padding:10px 0}
    .cp-mi{display:flex;align-items:center;gap:7px;font-size:11px;color:var(--fg-subtle);padding:6px 12px}
    .cp-mi.hot{background:var(--navy-50);color:var(--brand-strong);font-weight:700}
    .cp-bullet{width:5px;height:5px;border-radius:50%;background:currentColor;opacity:.5}
    .cp-body{padding:12px 16px}
    .cp-h{font-size:12px;font-weight:700;margin-bottom:8px}
    .cp-kv{display:grid;grid-template-columns:auto 1fr;gap:4px 14px;font-size:11.5px}
    .cp-kv .k{color:var(--fg-subtle)}
    .cp-kv .v{font-family:var(--font-num)}
    .cp-kv .v.hl{background:var(--warn-soft);border-radius:2px;padding:0 5px;font-weight:700;color:var(--warn)}
    .cp-note{font-size:11px;color:var(--fg-subtle);margin-top:9px;line-height:1.7}

    /* tenant option cards */
    .tenant-sec{margin-bottom:24px}
    .ts-h{display:flex;align-items:center;gap:7px;font-size:13px;font-weight:700;margin-bottom:11px;color:var(--fg)}
    .ts-h svg{width:15px;height:15px;color:var(--brand)}
    .opt-card{display:flex;gap:12px;align-items:flex-start;border:1px solid var(--border-strong);border-radius:2px;padding:14px 15px;margin-bottom:9px;cursor:pointer;background:var(--surface);transition:border-color .15s,box-shadow .15s,background .15s}
    .opt-card:hover{border-color:var(--navy-400)}
    .opt-card.on{border-color:var(--brand);box-shadow:inset 0 0 0 1px var(--brand);background:var(--navy-50)}
    .opt-card input{margin-top:4px;accent-color:var(--brand)}
    .oc-t{font-size:13.5px;font-weight:700;color:var(--fg)}
    .oc-badge{display:inline-block;font-size:10px;font-weight:700;padding:1px 8px;border-radius:99px;background:var(--navy-50);color:var(--brand-strong);border:1px solid var(--navy-100);margin-left:7px;vertical-align:1px}
    .oc-d{font-size:12px;color:var(--fg-subtle);margin-top:4px;line-height:1.7}

    /* upload dropzone */
    .up-drop{display:flex;flex-direction:column;align-items:center;gap:6px;border:2px dashed var(--border-strong);border-radius:3px;padding:30px 20px;cursor:pointer;text-align:center;background:var(--surface-sunk);transition:border-color .18s,background .18s}
    .up-drop:hover{border-color:var(--navy-400);background:var(--navy-50)}
    .up-drop.has-file{border-color:var(--brand);border-style:solid;background:var(--navy-50)}
    .ud-ic{width:32px;height:32px;color:var(--fg-subtle);transition:transform .2s var(--ease),color .2s}
    .ud-ic svg{width:100%;height:100%}
    .up-drop:hover .ud-ic{transform:translateY(-3px);color:var(--brand)}
    .up-drop.has-file .ud-ic{color:var(--brand)}
    .ud-t{font-size:13.5px;font-weight:700;color:var(--fg)}
    .ud-d{font-size:11.5px;color:var(--fg-subtle)}
    .ud-file{font-family:var(--font-num);font-size:11.5px;color:var(--brand-strong);font-weight:600;margin-top:6px;word-break:break-all;animation:izRise .3s var(--ease)}
    .up-drop input[type=file]{display:none}

    /* ============ LOADING ============ */
    .ld-h{font-size:22px;font-weight:700;color:var(--fg)}
    .ld-sub{font-size:13px;color:var(--fg-muted);margin:8px 0 22px;line-height:1.8}
    .ld-bar{height:6px;border-radius:99px;background:var(--surface-sunk);overflow:hidden;margin-bottom:22px;position:relative}
    .ld-bar span{display:block;height:100%;width:0;border-radius:99px;background:linear-gradient(90deg,var(--navy-500),var(--navy-400));transition:width .55s var(--ease);position:relative}
    .ld-bar span::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,255,255,.5),transparent);animation:izShimmer 1.4s linear infinite}
    .substeps{list-style:none}
    .substeps li{display:flex;align-items:center;gap:13px;padding:13px 0;border-bottom:1px solid var(--border);transition:opacity .3s}
    .substeps li:last-child{border-bottom:none}
    .ss-ic{width:22px;height:22px;flex:none;border-radius:50%;display:grid;place-items:center;background:var(--surface-sunk);color:var(--ok);transition:background .3s}
    .ss-ic svg{width:12px;height:12px}
    .ss-t{font-size:13.5px;font-weight:600;color:var(--fg)}
    .ss-d{font-size:11.5px;color:var(--fg-subtle)}
    .ss-meta{margin-left:auto;font-size:11px;font-weight:600;color:var(--fg-faint)}
    .ss-active .ss-ic{background:var(--navy-50)}
    .ss-active .ss-meta{color:var(--brand)}
    .ss-done .ss-ic{background:var(--ok-soft)}
    .ss-done .ss-ic svg{animation:izPop .35s var(--ease)}
    .ss-done .ss-meta{color:var(--ok)}
    .ss-pending{opacity:.45}
    .spinner{width:13px;height:13px;border:2px solid var(--border-strong);border-top-color:var(--brand);border-radius:50%;animation:spin .7s linear infinite;display:inline-block}
    @keyframes spin{to{transform:rotate(360deg)}}
    .ld-warn{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--warn);margin-top:20px}
    .ld-warn svg{width:14px;height:14px}

    /* ============ COMPLETE ============ */
    .done-mark{width:66px;height:66px;border-radius:50%;background:var(--ok-soft);color:var(--ok);display:grid;place-items:center;margin:8px 0 20px;position:relative;animation:izPop .45s var(--ease)}
    .done-mark::before{content:"";position:absolute;inset:0;border-radius:50%;border:2px solid var(--ok);opacity:0;animation:izRing 1s var(--ease) .2s}
    .done-mark svg{width:32px;height:32px}
    .done-mark svg path{stroke-dasharray:22;stroke-dashoffset:22;animation:izDraw .5s var(--ease) .3s forwards}
    .done-title{font-size:24px;font-weight:700;color:var(--fg)}
    .done-sub{font-size:13.5px;color:var(--fg-muted);margin:10px 0 24px;line-height:1.85}
    .sec-warn{display:flex;gap:13px;background:var(--danger-soft);border:1px solid var(--danger-line);border-left:3px solid var(--danger);border-radius:2px;padding:15px 17px;margin-bottom:24px}
    .sw-ico{width:20px;height:20px;flex:none;color:var(--danger);margin-top:2px}
    .sw-ico svg{width:100%;height:100%}
    .sw-t{font-size:13.5px;font-weight:700;color:var(--danger)}
    .sw-d{font-size:12.5px;color:#7a3535;margin-top:3px;line-height:1.7}
    .next-h{font-size:13px;font-weight:700;margin-bottom:10px;color:var(--fg)}
    .next-list{list-style:none;margin-bottom:26px}
    .next-list li{display:flex;gap:12px;padding:11px 0;border-bottom:1px solid var(--border);font-size:13px;color:var(--fg)}
    .next-list li:last-child{border-bottom:none}
    .nl-n{width:22px;height:22px;flex:none;border-radius:50%;background:var(--navy-50);color:var(--brand-strong);border:1px solid var(--navy-100);display:grid;place-items:center;font-family:var(--font-num);font-size:11.5px;font-weight:700}
    .nl-d{font-size:12px;color:var(--fg-subtle);margin-top:2px;font-weight:400}

    [hidden]{display:none !important}

    /* ============ MOTION ============ */
    @keyframes izRise{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
    @keyframes izReveal{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:none}}
    @keyframes izPop{0%{transform:scale(.5);opacity:0}60%{transform:scale(1.08)}100%{transform:scale(1);opacity:1}}
    @keyframes izRing{0%{opacity:.7;transform:scale(1)}100%{opacity:0;transform:scale(1.5)}}
    @keyframes izDraw{to{stroke-dashoffset:0}}
    @keyframes izShimmer{from{transform:translateX(-100%)}to{transform:translateX(100%)}}
    @keyframes izPulse{0%,100%{box-shadow:0 0 0 0 rgba(127,176,230,.5)}50%{box-shadow:0 0 0 7px rgba(127,176,230,0)}}
    @keyframes izAsideIn{from{opacity:0;transform:translateX(-14px)}to{opacity:1;transform:none}}

    @media (prefers-reduced-motion:no-preference){
      #izView>*{animation:izRise .5s var(--ease) backwards}
      #izView>*:nth-child(1){animation-delay:.04s}
      #izView>*:nth-child(2){animation-delay:.10s}
      #izView>*:nth-child(3){animation-delay:.16s}
      #izView>*:nth-child(4){animation-delay:.22s}
      #izView>*:nth-child(5){animation-delay:.28s}
      #izView>*:nth-child(6){animation-delay:.34s}
      #izView>*:nth-child(7){animation-delay:.40s}
      #izView>*:nth-child(8){animation-delay:.46s}
      #izView>*:nth-child(n+9){animation-delay:.5s}
      .iz-bs-top{animation:izAsideIn .55s var(--ease) both .05s}
      .iz-bs-mid{animation:izAsideIn .55s var(--ease) both .16s}
      .iz-bs-foot{animation:izAsideIn .55s var(--ease) both .28s}
      .vstep li.active .vs-dot{animation:izPulse 2.4s ease-in-out infinite 1s}
      .reqs li .ic svg{animation:izPop .4s var(--ease) both}
      .reqs li:nth-child(1) .ic svg{animation-delay:.30s}
      .reqs li:nth-child(2) .ic svg{animation-delay:.40s}
      .reqs li:nth-child(3) .ic svg{animation-delay:.50s}
      .reqs li:nth-child(4) .ic svg{animation-delay:.60s}
      .reqs li:nth-child(5) .ic svg{animation-delay:.70s}
      .reqs li:nth-child(6) .ic svg{animation-delay:.80s}
    }
    @media (prefers-reduced-motion:reduce){
      .done-mark,.done-mark svg path,.ss-done .ss-ic svg{animation:none}
      .done-mark svg path{stroke-dashoffset:0}
      .ld-bar span::after{display:none}
    }

    /* ============ RESPONSIVE ============ */
    @media (max-width:900px){
      .iz-stage{grid-template-columns:1fr}
      .iz-aside{padding:30px 26px 26px;flex-direction:row;align-items:center;justify-content:space-between;gap:16px}
      .iz-aside .iz-bs-mid,.iz-aside .iz-bs-foot{display:none}
      .iz-main{padding:34px 24px 52px}
      .hstep{display:flex}
    }
    @media (max-width:520px){.form-row2{grid-template-columns:1fr}.iz-head,.done-title{font-size:21px}.iz-main{padding:28px 20px 44px}}
    CSS;
}

// -------------------------------------------------------------------------
// CLI: pattern export for the design handoff (one source of truth)
// -------------------------------------------------------------------------

if (PHP_SAPI === 'cli') {
    $argvList = is_array($_SERVER['argv'] ?? null) ? array_values($_SERVER['argv']) : [];
    if (($argvList[1] ?? '') !== '--export-patterns') {
        fwrite(STDERR, "Usage: php public_html/install.php --export-patterns [output-dir]\n");
        exit(1);
    }
    $outDir = is_string($argvList[2] ?? null) && $argvList[2] !== '' ? $argvList[2] : $root . '/build/installer-patterns';
    if (!is_dir($outDir) && !mkdir($outDir, 0775, true)) {
        fwrite(STDERR, "Cannot create output dir: {$outDir}\n");
        exit(1);
    }

    $passChecks = [
        ['label' => 'PHP 8.4 以上', 'detail' => '現在: 8.4.23', 'ok' => true, 'fix' => 'サーバーのコントロールパネルで使用する PHP のバージョンを 8.4 以上に切り替えてください。'],
        ['label' => 'PHP 拡張モジュール', 'detail' => 'pdo / pdo_mysql / mbstring / openssl / json / curl', 'ok' => true, 'fix' => '不足している拡張モジュールを有効化してください。'],
        ['label' => 'var/ ディレクトリへの書き込み権限', 'detail' => 'インストール完了マーカーを保存します', 'ok' => true, 'fix' => ''],
        ['label' => 'ルートディレクトリへの書き込み権限', 'detail' => '.env ファイルを作成します', 'ok' => true, 'fix' => ''],
        ['label' => 'vendor/ ディレクトリ（依存一式）', 'detail' => '依存ライブラリ', 'ok' => true, 'fix' => ''],
    ];
    $failChecks = $passChecks;
    $failChecks[0] = ['label' => 'PHP 8.4 以上', 'detail' => '現在: 8.1.27', 'ok' => false, 'fix' => 'サーバーのコントロールパネルで使用する PHP のバージョンを 8.4 以上に切り替えてください。'];
    $failChecks[2] = ['label' => 'var/ ディレクトリへの書き込み権限', 'detail' => '書き込み不可', 'ok' => false, 'fix' => 'ファイルマネージャまたは FTP で <code>var/</code> フォルダのパーミッションを「書き込み可（755 または 775）」に変更してください。'];
    $acquireChecks = [
        ['label' => 'PHP 8.4 以上', 'detail' => '現在: 8.4.23', 'ok' => true, 'fix' => ''],
        ['label' => 'zip 拡張モジュール（ZipArchive）', 'detail' => '利用可', 'ok' => true, 'fix' => ''],
        ['label' => 'var/ ディレクトリへの書き込み権限', 'detail' => '書き込み可', 'ok' => true, 'fix' => ''],
        ['label' => 'ルートディレクトリへの書き込み権限', 'detail' => 'アプリ本体を展開します', 'ok' => true, 'fix' => ''],
    ];

    /** @var array<string, array<string, mixed>> $patterns */
    $patterns = [
        '01-requirements-pass' => ['view' => 'requirements', 'checks' => $passChecks, 'reqErrors' => []],
        '02-requirements-fail' => ['view' => 'requirements', 'checks' => $failChecks, 'reqErrors' => array_values(array_filter($failChecks, static fn (array $c): bool => !$c['ok']))],
        '03-database' => ['view' => 'database'],
        '04-database-error' => ['view' => 'database', 'errors' => ["DB 接続エラー: SQLSTATE[HY000] [1045] Access denied for user '_nene_clear'@'10.0.0.8' (using password: YES)"], 'old' => ['db_adapter' => 'mysql', 'db_host' => 'mysql401.phy.heteml.lan', 'db_port' => '3306', 'db_name' => '_nene_clear', 'db_user' => '_nene_clear']],
        '05-database-sqlite' => ['view' => 'database', 'old' => ['db_adapter' => 'sqlite']],
        '06-admin-single' => ['view' => 'admin'],
        '07-admin-multi' => ['view' => 'admin', 'old' => ['tenant_mode' => 'multi']],
        '08-admin-errors' => ['view' => 'admin', 'errors' => ['入力内容に誤りがあります。'], 'fieldErrors' => ['org_name' => '組織名を入力してください。', 'admin_email' => '有効なメールアドレスを入力してください。', 'admin_password' => 'パスワードは 12 文字以上にしてください。'], 'old' => ['org_name' => '', 'org_slug' => 'nene-shoji', 'admin_email' => 'admin@example', 'tenant_mode' => 'single']],
        '09-complete' => ['view' => 'complete', 'summary' => '組織「株式会社ねね商事」（#1）と管理者 admin@nene-shoji.co.jp を作成しました。'],
        '10-acquire' => ['view' => 'acquire', 'checks' => $acquireChecks, 'reqErrors' => []],
        '11-acquire-error' => ['view' => 'acquire', 'checks' => $acquireChecks, 'reqErrors' => [], 'errors' => ['SHA-256 が一致しません。公式配布元からダウンロードした ZIP と、そのページに記載のハッシュを確認してください。'], 'old' => ['expected_sha256' => '87ad144743f185bf19cbd15f678a54b65f8343e8dfc97ad93b5840972756bfd4']],
        '12-blocked' => ['view' => 'blocked', 'blockedMessage' => 'インストール済みです。install.php を削除してください。'],
    ];

    foreach ($patterns as $name => $state) {
        /** @var array{view: string} $state */
        file_put_contents($outDir . '/' . $name . '.html', render_installer_page($state));
        fwrite(STDOUT, "  {$name}.html\n");
    }
    copy(__DIR__ . '/installer.js', $outDir . '/installer.js');
    fwrite(STDOUT, "  installer.js\n");
    fwrite(STDOUT, "Exported to {$outDir}\n");
    exit(0);
}

// -------------------------------------------------------------------------
// Runtime flow
// -------------------------------------------------------------------------

$payloadPresent = is_file($root . '/vendor/autoload.php');
$method = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET';
$step = (int) (is_string($_GET['step'] ?? null) ? $_GET['step'] : '0');

/** @var list<string> $errors */
$errors = [];
/** @var array<string, string> $fieldErrors */
$fieldErrors = [];
$success = false;
$summary = '';

// Entry guards (both phases): completed-marker + provisioned-database probe.
if (is_file($marker)) {
    refuse_install('インストール済みです。install.php を削除してください。');
}
if (database_already_provisioned($envFile)) {
    refuse_install('既にプロビジョニング済みのデータベースが検出されました。再インストールはできません。install.php を削除してください。');
}

if (!$payloadPresent) {
    // ---- Acquisition flow (pre-vendor; dependency-zero) ----
    require_once $root . '/src/Install/PayloadAcquisition.php';

    $checks = acquire_requirement_checks($root);
    $reqErrors = array_values(array_filter($checks, static fn (array $c): bool => !$c['ok']));

    if ($method === 'POST' && post('action') === 'acquire' && $reqErrors === []) {
        try {
            if (!isset($_FILES['payload']) || !is_array($_FILES['payload'])) {
                throw new RuntimeException('ZIP ファイルが選択されていません。');
            }
            $err = (int) ($_FILES['payload']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($err !== UPLOAD_ERR_OK) {
                throw new RuntimeException(match ($err) {
                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'アップロードされたファイルがサーバーの上限を超えています（php.ini の upload_max_filesize / post_max_size をご確認ください）。',
                    UPLOAD_ERR_PARTIAL => 'アップロードが中断されました。もう一度お試しください。',
                    UPLOAD_ERR_NO_FILE => 'ZIP ファイルが選択されていません。',
                    default => 'ファイルのアップロードに失敗しました（コード: ' . $err . '）。',
                });
            }
            $size = (int) ($_FILES['payload']['size'] ?? 0);
            if ($size <= 0) {
                throw new RuntimeException('アップロードされたファイルが空です。');
            }
            if ($size > PayloadAcquisition::MAX_UPLOAD_BYTES) {
                throw new RuntimeException('ファイルサイズが上限（' . (int) (PayloadAcquisition::MAX_UPLOAD_BYTES / 1024 / 1024) . 'MB）を超えています。');
            }
            $origName = is_string($_FILES['payload']['name'] ?? null) ? (string) $_FILES['payload']['name'] : '';
            if (strtolower((string) pathinfo($origName, PATHINFO_EXTENSION)) !== 'zip') {
                throw new RuntimeException('.zip ファイルのみアップロードできます。');
            }
            $tmpName = is_string($_FILES['payload']['tmp_name'] ?? null) ? (string) $_FILES['payload']['tmp_name'] : '';
            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                throw new RuntimeException('アップロードされたファイルを検証できませんでした。');
            }
            $dest = $root . '/var/payload-upload-' . bin2hex(random_bytes(8)) . '.zip';
            if (!move_uploaded_file($tmpName, $dest)) {
                throw new RuntimeException('アップロードされたファイルを保存できませんでした。var/ の書き込み権限を確認してください。');
            }
            try {
                PayloadAcquisition::verifyAndExtract($dest, post('expected_sha256'), $root);
            } finally {
                @unlink($dest);
            }
            header('Location: install.php');
            exit;
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    } elseif (
        $method === 'POST'
        && $reqErrors === []
        && $_POST === []
        && (int) (is_string($_SERVER['CONTENT_LENGTH'] ?? null) ? $_SERVER['CONTENT_LENGTH'] : '0') > 0
    ) {
        // A POST body over post_max_size arrives with empty $_POST/$_FILES.
        $errors[] = 'アップロードがサーバーの上限（post_max_size / upload_max_filesize）を超えた可能性があります。ホスティングの PHP 設定をご確認ください。';
    }

    echo render_installer_page([
        'view' => 'acquire',
        'checks' => $checks,
        'reqErrors' => $reqErrors,
        'errors' => $errors,
        'old' => ['expected_sha256' => post('expected_sha256')],
    ]);
    exit;
}

// ---- Normal flow (vendor present) ----
require_once $root . '/vendor/autoload.php';

if (!is_dir($root . '/var')) {
    @mkdir($root . '/var', 0755, true);
}

$reinstallGuard = new ReInstallationGuard($marker, new class ($envFile) implements ProvisioningProbe {
    public function __construct(private readonly string $envFile)
    {
    }

    public function isProvisioned(): bool
    {
        return database_already_provisioned($this->envFile);
    }
});

$checks = requirement_checks($root);
$reqErrors = array_values(array_filter($checks, static fn (array $c): bool => !$c['ok']));

if ($method === 'POST' && $reqErrors === []) {
    if ($step === 1) {
        // ---- Database step: validate → connection test → schema → .env → PRG ----
        $adapter = post('db_adapter') === 'sqlite' ? 'sqlite' : 'mysql';
        $dbHost = post('db_host') !== '' ? post('db_host') : 'localhost';
        $dbPort = (int) (post('db_port') !== '' ? post('db_port') : '3306');
        $dbName = post('db_name');
        $dbUser = post('db_user');
        $dbPass = post_raw('db_password');
        $sqlitePath = $root . '/database/nene_clear.sqlite3';

        if ($adapter === 'mysql' && ($dbName === '' || $dbUser === '')) {
            $errors[] = 'データベース名とユーザー名は必須です。';
        }
        if ($adapter === 'mysql' && ($dbPort < 1 || $dbPort > 65535)) {
            $errors[] = 'ポート番号が正しくありません（1〜65535）。';
        }

        if ($errors === []) {
            try {
                if ($adapter === 'mysql') {
                    // Connection test first — credential failures get the friendly banner.
                    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
                    new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
                    $environment = ['adapter' => 'mysql', 'host' => $dbHost, 'port' => $dbPort, 'name' => $dbName, 'user' => $dbUser, 'pass' => $dbPass, 'charset' => 'utf8mb4'];
                } else {
                    // Phinx appends `.sqlite3`; hand it the suffix-less name so it
                    // resolves to the same file the app opens.
                    $environment = ['adapter' => 'sqlite', 'name' => substr($sqlitePath, 0, -8)];
                }

                (new DatabaseSchemaApplier())->apply(new PhinxConfig([
                    'paths' => ['migrations' => $root . '/database/migrations'],
                    'environments' => [
                        'default_migration_table' => 'phinxlog',
                        'default_environment' => 'install',
                        'install' => $environment,
                    ],
                    // Keep in sync with phinx.php (the only duplicated values).
                    'version_order' => 'creation',
                ]));

                $values = [
                    'APP_ENV' => 'production',
                    'APP_DEBUG' => 'false',
                    'APP_NAME' => 'NeNe Clear',
                    'DB_ADAPTER' => $adapter,
                ];
                if ($adapter === 'mysql') {
                    $values['DB_HOST'] = $dbHost;
                    $values['DB_PORT'] = (string) $dbPort;
                    $values['DB_NAME'] = $dbName;
                    $values['DB_USER'] = $dbUser;
                    $values['DB_PASSWORD'] = $dbPass;
                    $values['DB_CHARSET'] = 'utf8mb4';
                } else {
                    $values['DB_NAME'] = $sqlitePath;
                }
                $values['NENE_CLEAR_JWT_SECRET'] = EnvironmentWriter::generateSecret(32);

                (new EnvironmentWriter())->write($envFile, $values);

                header('Location: install.php?step=2');
                exit;
            } catch (PDOException $e) {
                $errors[] = 'DB 接続エラー: ' . $e->getMessage();
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
    } elseif ($step === 2) {
        // ---- Admin step: re-guard, validate with field errors, bootstrap ----
        $blockedReason = $reinstallGuard->blockedReason();
        if ($blockedReason !== null) {
            refuse_install($blockedReason === 'marker_present'
                ? 'インストール済みです。install.php を削除してください。'
                : '既にプロビジョニング済みのデータベースが検出されました。再インストールはできません。install.php を削除してください。');
        }

        $tenantMode = post('tenant_mode') === 'multi' ? 'multi' : 'single';
        $orgName = post('org_name');
        $orgSlug = post('org_slug');
        $email = post('admin_email');
        $password = post_raw('admin_password');

        if ($tenantMode === 'single' && $orgName === '') {
            $fieldErrors['org_name'] = '組織名を入力してください。';
        }
        if ($tenantMode === 'single' && $orgSlug !== '' && preg_match('/^[a-z0-9][a-z0-9-]*$/', $orgSlug) !== 1) {
            $fieldErrors['org_slug'] = 'スラッグは小文字英数字とハイフンのみ使えます。';
        }
        if ($email === '') {
            $fieldErrors['admin_email'] = 'メールアドレスを入力してください。';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $fieldErrors['admin_email'] = '有効なメールアドレスを入力してください。';
        }
        if ($password === '') {
            $fieldErrors['admin_password'] = 'パスワードを入力してください。';
        } elseif (strlen($password) < 12) {
            $fieldErrors['admin_password'] = 'パスワードは 12 文字以上にしてください。';
        }

        if ($fieldErrors !== []) {
            $errors[] = '入力内容に誤りがあります。';
        }

        if ($errors === []) {
            if (!is_file($envFile)) {
                $errors[] = '.env ファイルが見つかりません。ステップ 1 からやり直してください。';
            } else {
                try {
                    $env = parse_ini_file($envFile);
                    if ($env === false) {
                        throw new RuntimeException('.env ファイルを読み込めませんでした。');
                    }

                    $adapter = (string) ($env['DB_ADAPTER'] ?? 'mysql');
                    $config = $adapter === 'sqlite'
                        ? DatabaseConfig::sqlite((string) ($env['DB_NAME'] ?? $root . '/database/nene_clear.sqlite3'))
                        : new DatabaseConfig(
                            url: null,
                            environment: 'production',
                            adapter: 'mysql',
                            host: (string) ($env['DB_HOST'] ?? '127.0.0.1'),
                            port: (int) ($env['DB_PORT'] ?? 3306),
                            name: (string) ($env['DB_NAME'] ?? ''),
                            user: (string) ($env['DB_USER'] ?? ''),
                            password: (string) ($env['DB_PASSWORD'] ?? ''),
                            charset: 'utf8mb4',
                        );

                    $factory = new PdoConnectionFactory($config);
                    $query = new AdapterAwareQueryExecutor(new PdoDatabaseQueryExecutor($factory), $adapter);
                    $transactionManager = new AdapterAwareTransactionManager(new PdoDatabaseTransactionManager($factory), $adapter);
                    $container = ApplicationFactory::container($query, $transactionManager, (string) ($env['NENE_CLEAR_JWT_SECRET'] ?? ''));

                    if ($tenantMode === 'multi') {
                        ServiceResolver::get($container, CreateUserUseCaseInterface::class)->execute(
                            new CreateUserInput(organizationId: null, email: $email, role: Role::Superadmin, password: $password, actorUserId: 0),
                        );
                        $summary = '横断管理者（superadmin）' . $email . ' を作成しました。組織はログイン後に追加できます。';
                    } else {
                        // Japanese org names yield an empty slug — fall back to a
                        // stable default (invoice behavior) instead of failing.
                        $slug = $orgSlug !== '' ? $orgSlug : (slugify($orgName) !== '' ? slugify($orgName) : 'default');
                        $org = ServiceResolver::get($container, CreateOrganizationUseCaseInterface::class)->execute(
                            new CreateOrganizationInput(slug: $slug, name: $orgName, actorUserId: 0),
                        );
                        ServiceResolver::get($container, CreateUserUseCaseInterface::class)->execute(
                            new CreateUserInput(organizationId: $org->id, email: $email, role: Role::Admin, password: $password, actorUserId: 0),
                        );
                        $summary = '組織「' . $orgName . '」（#' . $org->id . '）と管理者 ' . $email . ' を作成しました。管理画面にログインして、銀行口座（CSV 取込プロファイル）の設定から始めましょう。';
                    }

                    $reinstallGuard->markInstalled(gmdate('c'));
                    $success = true;
                } catch (PDOException $e) {
                    $errors[] = 'データベースエラー: ' . $e->getMessage();
                } catch (\Throwable $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }
    }
}

// Decide the view. Failed requirements always block on the requirements page.
if ($reqErrors !== []) {
    $view = 'requirements';
} elseif ($success) {
    $view = 'complete';
} elseif ($step === 2) {
    $view = 'admin';
} elseif ($step === 1) {
    $view = 'database';
} else {
    $view = 'requirements';
}

// Preserve submitted values on re-render (#267).
$oldValues = [];
foreach (['db_adapter', 'db_host', 'db_port', 'db_name', 'db_user', 'db_password', 'tenant_mode', 'org_name', 'org_slug', 'admin_email'] as $key) {
    $value = $key === 'db_password' ? post_raw($key) : post($key);
    if ($value !== '') {
        $oldValues[$key] = $value;
    }
}

echo render_installer_page([
    'view' => $view,
    'checks' => $checks,
    'reqErrors' => $reqErrors,
    'errors' => $errors,
    'fieldErrors' => $fieldErrors,
    'old' => $oldValues,
    'summary' => $summary,
]);
