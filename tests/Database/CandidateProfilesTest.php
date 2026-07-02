<?php

declare(strict_types=1);

namespace NeneClear\Tests\Database;

use NeneClear\Database\CandidateProfiles;
use PHPUnit\Framework\TestCase;

final class CandidateProfilesTest extends TestCase
{
    public function test_builds_profiles_from_the_env_allowlist(): void
    {
        $profiles = CandidateProfiles::fromEnv([
            'NENE_CLEAR_DB_CANDIDATE_LEGACY_ADAPTER' => 'mysql',
            'NENE_CLEAR_DB_CANDIDATE_LEGACY_HOST' => 'db.internal',
            'NENE_CLEAR_DB_CANDIDATE_LEGACY_NAME' => 'nene_clear_old',
            'NENE_CLEAR_DB_CANDIDATE_LEGACY_USER' => 'readonly',
            'NENE_CLEAR_DB_CANDIDATE_LEGACY_PASSWORD' => 'secret',
            'NENE_CLEAR_DB_CANDIDATE_RESTORE_2026_ADAPTER' => 'sqlite',
            'NENE_CLEAR_DB_CANDIDATE_RESTORE_2026_NAME' => '/tmp/restore.sqlite3',
            'NENE_CLEAR_DB_CANDIDATE_RESTORE_2026_MULTI_TENANT' => 'true',
            'UNRELATED_KEY' => 'ignored',
        ]);

        self::assertSame(['legacy', 'restore_2026'], array_keys($profiles));
        self::assertSame('legacy', $profiles['legacy']->id);
        self::assertFalse($profiles['legacy']->multiTenant);
        self::assertTrue($profiles['restore_2026']->multiTenant);
    }

    public function test_skips_malformed_entries_instead_of_failing_the_boot(): void
    {
        $profiles = CandidateProfiles::fromEnv([
            // mysql candidate without a database name → invalid DatabaseConfig
            'NENE_CLEAR_DB_CANDIDATE_BROKEN_ADAPTER' => 'mysql',
            'NENE_CLEAR_DB_CANDIDATE_OK_ADAPTER' => 'sqlite',
            'NENE_CLEAR_DB_CANDIDATE_OK_NAME' => ':memory:',
        ]);

        self::assertSame(['ok'], array_keys($profiles));
    }

    public function test_returns_no_profiles_when_nothing_is_declared(): void
    {
        self::assertSame([], CandidateProfiles::fromEnv(['DB_ADAPTER' => 'sqlite']));
    }
}
