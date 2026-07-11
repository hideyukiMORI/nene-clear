<?php

declare(strict_types=1);

namespace NeneClear\Tests\Install;

use PHPUnit\Framework\TestCase;

/**
 * Pins the installer self-delete behaviour (#306 / audit finding (j) —
 * deal/vault/invoice shape): a completed install must remove `install.php`
 * on success, fall back to the manual-delete notice when the unlink fails,
 * and — as defense in depth — the re-install guard must refuse any re-run
 * with HTTP 403 even if the file is put back.
 *
 * Two layers:
 *  1. Rendering (always runs): the CLI pattern export proves the completion
 *     screen branches on the self-delete result — no live DB or server needed.
 *  2. End-to-end (skips unless the installer's own requirement extensions are
 *     present, like the Invoice contract tests): drives the real wizard over
 *     the PHP built-in server against a throwaway SQLite database, asserts the
 *     installer copy deletes itself, then re-uploads it and asserts the guard
 *     answers 403.
 */
final class InstallerSelfDeleteTest extends TestCase
{
    private const REQUIRED_EXTENSIONS = ['pdo', 'pdo_mysql', 'pdo_sqlite', 'mbstring', 'openssl', 'json', 'curl'];

    private string $work = '';

    /** @var array<int, resource> */
    private array $procs = [];

    protected function tearDown(): void
    {
        foreach ($this->procs as $proc) {
            @proc_terminate($proc);
            @proc_close($proc);
        }
        $this->procs = [];

        if ($this->work !== '' && is_dir($this->work)) {
            self::removeTree($this->work);
        }
        $this->work = '';
    }

    /** The exported completion pattern reflects a successful self-delete. */
    public function testCompletionScreenReportsSelfDeleteOnSuccess(): void
    {
        $patterns = $this->exportPatterns();

        $html = $patterns['09-complete'];
        self::assertStringContainsString('install.php は自動的に削除されました', $html);
        // The "delete install.php first" step is dropped once it is gone.
        self::assertStringNotContainsString('を削除する</b>', $html);
        self::assertStringContainsString('管理画面にログイン', $html);
    }

    /** When the unlink fails the screen keeps the manual-delete instruction. */
    public function testCompletionScreenFallsBackToManualDeleteWhenUnlinkFails(): void
    {
        $patterns = $this->exportPatterns();

        $html = $patterns['09-complete-manual'];
        self::assertStringContainsString('自動削除に失敗しました', $html);
        self::assertStringContainsString('を削除する</b>', $html);
    }

    public function testWizardSelfDeletesOnSuccessAndGuardRefusesReRun(): void
    {
        foreach (self::REQUIRED_EXTENSIONS as $ext) {
            if (!extension_loaded($ext)) {
                self::markTestSkipped("installer requirement extension '{$ext}' is not loaded");
            }
        }

        [$root, $installer] = $this->stageInstaller();
        [$proc, $base] = $this->startServer($root);
        $this->procs[] = $proc;

        // Step 1 — database: SQLite, applies the schema and writes .env (PRG).
        $step1 = $this->post($base . '/install.php?step=1', ['db_adapter' => 'sqlite']);
        self::assertContains($step1['status'], [302, 303], 'DB step should PRG-redirect to the admin step: ' . $step1['body']);
        self::assertFileExists($root . '/.env', 'the database step must write .env');

        // Step 2 — admin: creates the org + admin, marks installed, self-deletes.
        $step2 = $this->post($base . '/install.php?step=2', [
            'tenant_mode' => 'single',
            'org_name' => '株式会社ねね商事',
            'org_slug' => 'nene-shoji',
            'admin_email' => 'admin@nene-shoji.co.jp',
            'admin_password' => 'correct horse battery',
        ]);

        self::assertSame(200, $step2['status'], $step2['body']);
        self::assertStringContainsString('インストール完了', $step2['body']);
        self::assertStringContainsString('install.php は自動的に削除されました', $step2['body']);
        self::assertFileDoesNotExist($installer, 'install.php must remove itself after a successful install');
        self::assertFileExists($root . '/var/.installed', 'the completed marker must remain as the re-install guard');

        // Belt and suspenders: an operator re-uploads install.php — the marker
        // (+ provisioned DB) guard must still refuse with 403.
        copy($this->sourceInstaller(), $installer);
        $reRun = $this->get($base . '/install.php');
        self::assertSame(403, $reRun['status'], $reRun['body']);
        self::assertStringContainsString('インストール済みです', $reRun['body']);
    }

    // -- helpers -----------------------------------------------------------

    private function sourceInstaller(): string
    {
        return dirname(__DIR__, 2) . '/public_html/install.php';
    }

    /**
     * Runs `install.php --export-patterns` and returns each pattern's HTML.
     *
     * @return array<string, string>
     */
    private function exportPatterns(): array
    {
        $out = sys_get_temp_dir() . '/' . uniqid('clear-installer-patterns-', true);
        mkdir($out, 0775, true);

        $proc = proc_open(
            [PHP_BINARY, $this->sourceInstaller(), '--export-patterns', $out],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($proc);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($proc), 'pattern export failed: ' . $stderr);

        $patterns = [];
        foreach (glob($out . '/*.html') ?: [] as $file) {
            $patterns[basename($file, '.html')] = (string) file_get_contents($file);
        }
        self::removeTree($out);

        self::assertArrayHasKey('09-complete', $patterns);
        self::assertArrayHasKey('09-complete-manual', $patterns);

        return $patterns;
    }

    /**
     * Builds a throwaway repo root: a real copy of install.php, a symlinked
     * vendor/ + migrations (so the app classes and schema resolve) and writable
     * var/ + database/ for the marker, .env and SQLite file.
     *
     * @return array{0: string, 1: string} [root, installerPath]
     */
    private function stageInstaller(): array
    {
        $this->work = sys_get_temp_dir() . '/' . uniqid('clear-installer-', true);
        $realRoot = dirname(__DIR__, 2);

        mkdir($this->work . '/public_html', 0775, true);
        mkdir($this->work . '/var', 0775, true);
        mkdir($this->work . '/database', 0775, true);

        $installer = $this->work . '/public_html/install.php';
        copy($this->sourceInstaller(), $installer);
        symlink($realRoot . '/vendor', $this->work . '/vendor');
        symlink($realRoot . '/database/migrations', $this->work . '/database/migrations');

        return [$this->work, $installer];
    }

    /**
     * Starts the PHP built-in server on a free port and waits for it to answer.
     *
     * @return array{0: resource, 1: string} [process, baseUrl]
     */
    private function startServer(string $root): array
    {
        $port = $this->freePort();
        $base = 'http://127.0.0.1:' . $port;

        $proc = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public_html'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $root,
        );
        self::assertIsResource($proc);
        // Non-blocking pipes so a chatty server never deadlocks the test.
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        for ($i = 0; $i < 100; $i++) {
            $probe = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if (is_resource($probe)) {
                fclose($probe);

                return [$proc, $base];
            }
            usleep(50_000);
        }

        self::fail('PHP built-in server did not start on port ' . $port);
    }

    private function freePort(): int
    {
        $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        self::assertIsResource($sock);
        $name = (string) stream_socket_get_name($sock, false);
        fclose($sock);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }

    /** @return array{status: int, body: string} */
    private function get(string $url): array
    {
        return $this->request('GET', $url, null);
    }

    /**
     * @param array<string, string> $fields
     * @return array{status: int, body: string}
     */
    private function post(string $url, array $fields): array
    {
        return $this->request('POST', $url, http_build_query($fields));
    }

    /** @return array{status: int, body: string} */
    private function request(string $method, string $url, ?string $body): array
    {
        $ch = curl_init($url);
        self::assertNotFalse($ch);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // assert redirects explicitly
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string) $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        }
        $response = curl_exec($ch);
        self::assertNotFalse($response, 'HTTP request failed: ' . curl_error($ch));
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => (string) $response];
    }

    private static function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            self::removeTree($path . '/' . $entry);
        }
        @rmdir($path);
    }
}
