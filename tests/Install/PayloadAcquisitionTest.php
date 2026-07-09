<?php

declare(strict_types=1);

namespace NeneClear\Tests\Install;

use NeneClear\Install\PayloadAcquisition;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

final class PayloadAcquisitionTest extends TestCase
{
    private string $work;

    protected function setUp(): void
    {
        $this->work = sys_get_temp_dir() . '/payload-acq-' . bin2hex(random_bytes(6));
        mkdir($this->work . '/var', 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->work);
    }

    /** @param array<string, string> $files name => contents */
    private function makeZip(array $files): string
    {
        $path = $this->work . '/payload.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        return $path;
    }

    private function removeTree(string $dir): void
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
            is_dir($path) ? $this->removeTree($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    public function test_allow_list_covers_the_shipped_tree(): void
    {
        // The canonical release contents (what tools/build-release.sh stages)
        // must be allowed, or the official ZIP would be rejected.
        foreach (['public_html', 'vendor', 'src', 'database', 'lang', 'tools', 'var', 'composer.json', 'composer.lock', 'phinx.php', 'VERSION', 'README.md', 'LICENSE', '.env.example'] as $shipped) {
            self::assertContains($shipped, PayloadAcquisition::ALLOWED_TOP);
        }

        // When a locally built release ZIP exists, verify every effective
        // top-level entry is allowed (integration-grade sync check).
        $zips = glob(dirname(__DIR__, 2) . '/build/release/nene-clear-*.zip') ?: [];
        if ($zips === []) {
            return;
        }

        $zip = new ZipArchive();
        self::assertTrue($zip->open($zips[0]));
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = (string) $zip->getNameIndex($i);
        }
        $zip->close();

        $wrapper = PayloadAcquisition::wrapperDirectory($entries);
        foreach ($entries as $entry) {
            $effective = $wrapper === null
                ? PayloadAcquisition::topSegment($entry)
                : PayloadAcquisition::topSegment(substr(ltrim($entry, '/'), strlen($wrapper) + 1));
            if ($effective === '') {
                continue;
            }
            self::assertContains($effective, PayloadAcquisition::ALLOWED_TOP, 'release ZIP entry not allowed: ' . $entry);
        }
    }

    public function test_zip_slip_entries_are_detected(): void
    {
        self::assertTrue(PayloadAcquisition::entryEscapesRoot('../evil.php'));
        self::assertTrue(PayloadAcquisition::entryEscapesRoot('src/../../evil.php'));
        self::assertTrue(PayloadAcquisition::entryEscapesRoot('/etc/passwd'));
        self::assertTrue(PayloadAcquisition::entryEscapesRoot('C:\\windows\\evil'));
        self::assertFalse(PayloadAcquisition::entryEscapesRoot('src/App.php'));
        self::assertFalse(PayloadAcquisition::entryEscapesRoot('vendor/autoload.php'));
    }

    public function test_wrapper_directory_detection(): void
    {
        self::assertSame('nene-clear', PayloadAcquisition::wrapperDirectory([
            'nene-clear/src/App.php', 'nene-clear/composer.json', 'nene-clear/vendor/autoload.php',
        ]));
        // A flat ZIP (top-level file) has no wrapper.
        self::assertNull(PayloadAcquisition::wrapperDirectory(['composer.json', 'src/App.php']));
        // Multiple top directories → no wrapper.
        self::assertNull(PayloadAcquisition::wrapperDirectory(['src/App.php', 'vendor/autoload.php']));
    }

    public function test_blank_and_malformed_hashes_refuse_extraction(): void
    {
        $zip = $this->makeZip(['composer.json' => '{}']);

        try {
            PayloadAcquisition::verifyAndExtract($zip, '', $this->work);
            self::fail('blank hash must refuse');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('SHA-256', $e->getMessage());
        }

        $this->expectException(RuntimeException::class);
        PayloadAcquisition::verifyAndExtract($zip, 'zz', $this->work);
    }

    public function test_mismatched_hash_refuses_and_writes_nothing(): void
    {
        $zip = $this->makeZip(['composer.json' => '{}', 'src/App.php' => '<?php']);

        try {
            PayloadAcquisition::verifyAndExtract($zip, str_repeat('0', 64), $this->work);
            self::fail('mismatched hash must refuse');
        } catch (RuntimeException) {
        }

        self::assertFileDoesNotExist($this->work . '/composer.json');
        self::assertDirectoryDoesNotExist($this->work . '/src');
    }

    public function test_disallowed_top_level_entry_is_rejected_before_extraction(): void
    {
        $zip = $this->makeZip(['composer.json' => '{}', 'evil-dir/x.php' => '<?php']);
        $hash = (string) hash_file('sha256', $zip);

        try {
            PayloadAcquisition::verifyAndExtract($zip, $hash, $this->work);
            self::fail('disallowed entry must refuse');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('evil-dir', $e->getMessage());
        }

        self::assertFileDoesNotExist($this->work . '/composer.json');
    }

    public function test_flat_zip_extracts_into_root(): void
    {
        $zip = $this->makeZip([
            'composer.json' => '{"name":"x"}',
            'src/App.php' => '<?php // app',
            'vendor/autoload.php' => '<?php // autoload',
        ]);
        PayloadAcquisition::verifyAndExtract($zip, (string) hash_file('sha256', $zip), $this->work);

        self::assertSame('{"name":"x"}', file_get_contents($this->work . '/composer.json'));
        self::assertFileExists($this->work . '/vendor/autoload.php');
        self::assertSame([], glob($this->work . '/var/payload-extract-*') ?: []);
    }

    public function test_wrapped_zip_extracts_with_the_wrapper_stripped_and_merges_existing_dirs(): void
    {
        // public_html already exists (install.php runs from it) — must merge, not fail.
        mkdir($this->work . '/public_html', 0775, true);
        file_put_contents($this->work . '/public_html/install.php', '<?php // running installer');

        $zip = $this->makeZip([
            'nene-clear/composer.json' => '{"name":"wrapped"}',
            'nene-clear/public_html/index.php' => '<?php // front controller',
            'nene-clear/vendor/autoload.php' => '<?php // autoload',
        ]);
        PayloadAcquisition::verifyAndExtract($zip, (string) hash_file('sha256', $zip), $this->work);

        self::assertSame('{"name":"wrapped"}', file_get_contents($this->work . '/composer.json'));
        self::assertFileExists($this->work . '/public_html/index.php');
        self::assertFileExists($this->work . '/public_html/install.php'); // merge preserved the running installer
        self::assertFileExists($this->work . '/vendor/autoload.php');
        self::assertDirectoryDoesNotExist($this->work . '/nene-clear');
    }

    public function test_wrapped_zip_with_disallowed_inner_entry_is_rejected(): void
    {
        $zip = $this->makeZip([
            'nene-clear/composer.json' => '{}',
            'nene-clear/evil/x.php' => '<?php',
        ]);

        $this->expectException(RuntimeException::class);
        PayloadAcquisition::verifyAndExtract($zip, (string) hash_file('sha256', $zip), $this->work);
    }
}
