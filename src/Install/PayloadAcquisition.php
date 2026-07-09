<?php

declare(strict_types=1);

namespace NeneClear\Install;

use RuntimeException;
use ZipArchive;

/**
 * The web installer's payload acquisition — installing the application from a
 * manually uploaded release ZIP when `vendor/` is absent (a host that only
 * extracted `public_html/`, or a partial upload). Mirrors the proven
 * nene-invoice implementation (#271), extended for Clear's release layout.
 *
 * ⚠️ Runs BEFORE `vendor/` exists, so this class must stay dependency-free: no
 * NENE2 toolkit, no other `src/` classes, only the global `ZipArchive` and
 * `RuntimeException`. `public_html/install.php` loads it with a direct
 * `require_once` (not the Composer autoloader, which isn't available yet).
 *
 * Security invariants (validated before any extraction):
 *  - every entry is rejected if it escapes the root (`..`, absolute path, drive
 *    letter) — zip-slip;
 *  - every effective top-level entry must be in {@see self::ALLOWED_TOP} —
 *    rejects unrelated / hostile ZIPs;
 *  - the SHA-256 the operator pastes from the release page must match —
 *    integrity (timing-safe compare, checked before anything is unzipped).
 *
 * Layout: Clear's `tools/build-release.sh` wraps the tree in one `nene-clear/`
 * directory; a flat ZIP (invoice-style) is accepted too. Both are extracted to
 * a staging directory under `var/` first and then merge-moved into the root,
 * so a ZIP that fails validation writes nothing and the wrapper never lands in
 * the application root.
 *
 * This verifies the distributor SHA-256 only; signature verification is the
 * updater round's concern.
 */
final class PayloadAcquisition
{
    /**
     * The only effective top-level entries a NeNe Clear release ZIP may
     * contain. Kept in sync with what `tools/build-release.sh` ships (guarded
     * by {@see \NeneClear\Tests\Install\PayloadAcquisitionTest}) — a mismatch
     * here would reject the official ZIP.
     */
    public const array ALLOWED_TOP = [
        'src',
        'vendor',
        'database',
        'lang',
        'public_html',
        'tools',
        'var',
        'composer.json',
        'composer.lock',
        'phinx.php',
        'sec-router.php',
        'conformance.baseline.json',
        '.env.example',
        '.gitignore',
        'README.md',
        'LICENSE',
        'VERSION',
        'AGENTS.md',
        'CLAUDE.md',
    ];

    /** Upload ceiling; a host's effective limit (upload_max_filesize) may be smaller. */
    public const int MAX_UPLOAD_BYTES = 120 * 1024 * 1024;

    /**
     * Whether a ZIP entry name tries to write outside the extraction root
     * (zip-slip). Absolute paths, Windows drive letters and any `..` segment
     * all count as an escape (strict).
     */
    public static function entryEscapesRoot(string $entry): bool
    {
        $norm = str_replace('\\', '/', $entry);

        if ($norm === '' || $norm === '/') {
            return false;
        }

        if ($norm[0] === '/' || preg_match('#^[A-Za-z]:#', $norm) === 1) {
            return true;
        }

        foreach (explode('/', $norm) as $seg) {
            if ($seg === '..') {
                return true;
            }
        }

        return false;
    }

    /** The top-level (first) segment of a ZIP entry name. */
    public static function topSegment(string $entry): string
    {
        $norm = ltrim(str_replace('\\', '/', $entry), '/');
        $slash = strpos($norm, '/');

        return $slash === false ? $norm : substr($norm, 0, $slash);
    }

    /**
     * The single wrapper directory shared by every entry, or null when the ZIP
     * is flat (multiple top segments, or a top-level file).
     *
     * @param list<string> $entries
     */
    public static function wrapperDirectory(array $entries): ?string
    {
        $tops = [];
        foreach ($entries as $entry) {
            $top = self::topSegment($entry);
            if ($top === '') {
                continue;
            }
            $tops[$top] = true;
            // A top-level *file* (entry with no slash) means there is no wrapper.
            if (!str_contains(ltrim(str_replace('\\', '/', $entry), '/'), '/')) {
                return null;
            }
        }

        return count($tops) === 1 ? array_key_first($tops) : null;
    }

    /**
     * Verify the SHA-256, then extract. The hash is checked with a
     * constant-time comparison BEFORE anything is unzipped; a blank, malformed
     * or mismatched hash all refuse extraction.
     */
    public static function verifyAndExtract(string $zipPath, string $expectedHash, string $root): void
    {
        $expected = strtolower(trim($expectedHash));

        if ($expected === '') {
            throw new RuntimeException('公式配布元のリリースページに記載された SHA-256 を入力してください。');
        }

        if (preg_match('/^[0-9a-f]{64}$/', $expected) !== 1) {
            throw new RuntimeException('SHA-256 の形式が正しくありません（64 桁の 16 進数を入力してください）。');
        }

        $actual = hash_file('sha256', $zipPath);

        if ($actual === false) {
            throw new RuntimeException('アップロードされたファイルのハッシュを計算できませんでした。');
        }

        if (!hash_equals($expected, strtolower($actual))) {
            throw new RuntimeException('SHA-256 が一致しません。公式配布元からダウンロードした ZIP と、そのページに記載のハッシュを確認してください。');
        }

        self::extract($zipPath, $root);
    }

    /**
     * Validate every entry (zip-slip + effective top-level allowlist), extract
     * to a staging directory under `var/`, then merge-move into $root. A ZIP
     * that fails validation writes nothing.
     */
    public static function extract(string $zipPath, string $root): void
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('zip 拡張（ZipArchive）が有効ではありません。');
        }

        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('アップロードされた ZIP を開けませんでした。ファイルが壊れていないか確認してください。');
        }

        $staging = null;

        try {
            if ($zip->numFiles === 0) {
                throw new RuntimeException('ZIP が空です。公式配布元の ZIP をアップロードしてください。');
            }

            $entries = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);

                if ($entry === false) {
                    throw new RuntimeException('ZIP のエントリを読み取れませんでした。');
                }

                if (self::entryEscapesRoot($entry)) {
                    throw new RuntimeException('ZIP に不正なパス（zip-slip の疑い）が含まれています。展開を中止しました。');
                }

                $entries[] = $entry;
            }

            // A release ZIP may wrap everything in one directory (build-release.sh
            // ships nene-clear/…) or be flat. Validate the *effective* top level.
            $wrapper = self::wrapperDirectory($entries);
            foreach ($entries as $entry) {
                $effective = $wrapper === null
                    ? self::topSegment($entry)
                    : self::topSegment(substr(ltrim(str_replace('\\', '/', $entry), '/'), strlen($wrapper) + 1));

                if ($effective !== '' && !in_array($effective, self::ALLOWED_TOP, true)) {
                    throw new RuntimeException('想定外のエントリ「' . $effective . '」が ZIP に含まれています。NeNe Clear の配布 ZIP をアップロードしてください。');
                }
            }

            $staging = $root . '/var/payload-extract-' . bin2hex(random_bytes(8));
            if (!@mkdir($staging, 0755, true)) {
                throw new RuntimeException('展開用の一時ディレクトリを作成できませんでした。var/ の書き込み権限を確認してください。');
            }

            if ($zip->extractTo($staging) !== true) {
                throw new RuntimeException('ZIP の展開に失敗しました。書き込み権限とディスク容量を確認してください。');
            }

            self::mergeMove($wrapper === null ? $staging : $staging . '/' . $wrapper, $root);
        } finally {
            $zip->close();
            if ($staging !== null) {
                self::removeTree($staging);
            }
        }
    }

    /**
     * Recursively move $from's children into $to, merging into directories
     * that already exist (public_html/ holds the running install.php; var/
     * holds the staging itself). Files are replaced via rename().
     */
    private static function mergeMove(string $from, string $to): void
    {
        $children = @scandir($from);

        if ($children === false) {
            throw new RuntimeException('展開結果を読み取れませんでした。');
        }

        foreach ($children as $child) {
            if ($child === '.' || $child === '..') {
                continue;
            }

            $src = $from . '/' . $child;
            $dst = $to . '/' . $child;

            if (is_dir($src)) {
                if (!is_dir($dst) && !@mkdir($dst, 0755, true)) {
                    throw new RuntimeException('ディレクトリ「' . $child . '」を作成できませんでした。');
                }
                self::mergeMove($src, $dst);
                continue;
            }

            if (is_file($dst)) {
                @unlink($dst);
            }

            if (!@rename($src, $dst)) {
                throw new RuntimeException('ファイル「' . $child . '」を配置できませんでした。書き込み権限を確認してください。');
            }
        }
    }

    /** Best-effort recursive delete of the staging directory. */
    private static function removeTree(string $dir): void
    {
        $children = @scandir($dir);

        if ($children === false) {
            return;
        }

        foreach ($children as $child) {
            if ($child === '.' || $child === '..') {
                continue;
            }

            $path = $dir . '/' . $child;
            is_dir($path) ? self::removeTree($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
