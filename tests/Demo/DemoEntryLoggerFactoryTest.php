<?php

declare(strict_types=1);

namespace NeneClear\Tests\Demo;

use NeneClear\Demo\DemoEntryLoggerFactory;
use PHPUnit\Framework\TestCase;

final class DemoEntryLoggerFactoryTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir() . '/' . uniqid('clear-entry-log-', true);
        mkdir($this->varDir, 0775, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->varDir . '/demo-entry.log');
        @rmdir($this->varDir);
    }

    public function test_writes_a_json_line_to_the_var_file(): void
    {
        $logger = (new DemoEntryLoggerFactory())->create($this->varDir);

        $logger->info('Demo entry recorded.', [
            'template' => 'standard',
            'referer' => 'https://www.facebook.com/',
            'utm_source' => 'facebook',
            'utm_medium' => 'social',
            'utm_campaign' => 'launch',
        ]);

        $path = $this->varDir . '/demo-entry.log';
        self::assertFileExists($path);

        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($path))));
        self::assertCount(1, $lines);

        $record = json_decode($lines[0], true);
        self::assertIsArray($record);
        self::assertSame('Demo entry recorded.', $record['message']);
        self::assertSame('INFO', $record['level_name']);
        self::assertSame([
            'template' => 'standard',
            'referer' => 'https://www.facebook.com/',
            'utm_source' => 'facebook',
            'utm_medium' => 'social',
            'utm_campaign' => 'launch',
        ], $record['context']);
    }

    public function test_falls_back_to_error_log_when_the_file_cannot_be_opened(): void
    {
        // A regular file in place of the var dir: fopen()'ing a path *through*
        // it fails with ENOTDIR regardless of filesystem permissions/uid, so
        // this failure mode is deterministic even when tests run as root
        // (where a chmod-based unwritable-directory test would not fail).
        $blocker = $this->varDir . '/blocker-not-a-directory';
        file_put_contents($blocker, '');

        $errorLogFile = $this->varDir . '/fallback-error.log';
        $previous = ini_set('error_log', $errorLogFile);

        try {
            $logger = (new DemoEntryLoggerFactory())->create($blocker);
            $logger->info('Demo entry recorded.', ['template' => 'standard']);
        } finally {
            ini_set('error_log', $previous === false ? '' : $previous);
        }

        self::assertFileDoesNotExist($blocker . '/demo-entry.log');
        self::assertFileExists($errorLogFile);

        $fallbackContents = (string) file_get_contents($errorLogFile);
        self::assertStringContainsString('Demo entry recorded.', $fallbackContents);

        @unlink($blocker);
        @unlink($errorLogFile);
    }
}
