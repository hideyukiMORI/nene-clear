<?php

declare(strict_types=1);

namespace NeneClear\Tests\Database;

use NeneClear\Database\MigrationVersions;
use PHPUnit\Framework\TestCase;

final class MigrationVersionsTest extends TestCase
{
    public function test_reads_version_ids_from_the_real_migrations_directory(): void
    {
        $versions = MigrationVersions::fromDirectory(dirname(__DIR__, 2) . '/database/migrations');

        self::assertNotEmpty($versions);
        self::assertContains('20260530120000', $versions, 'first shipped migration must be known');
        self::assertContains('20260703000000', $versions, 'identity-marker migration must be known');

        foreach ($versions as $version) {
            self::assertMatchesRegularExpression('/^\d{14}$/', $version);
        }

        $sorted = $versions;
        sort($sorted, SORT_STRING);
        self::assertSame($sorted, $versions, 'versions are returned ascending');
    }

    public function test_returns_an_empty_list_for_a_missing_directory(): void
    {
        self::assertSame([], MigrationVersions::fromDirectory('/nonexistent/path'));
    }
}
