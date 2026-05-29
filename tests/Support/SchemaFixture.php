<?php

declare(strict_types=1);

namespace NeneClear\Tests\Support;

use Nene2\Database\DatabaseQueryExecutorInterface;

/**
 * Creates SQLite test schema inline (the NENE2 test-database strategy: each test
 * builds its own schema). Mirrors the Phinx migrations under database/migrations/.
 */
final class SchemaFixture
{
    public static function createOrganizations(DatabaseQueryExecutorInterface $query): void
    {
        $query->execute(
            'CREATE TABLE organizations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                created_at TEXT,
                updated_at TEXT
            )'
        );
    }

    public static function createUsers(DatabaseQueryExecutorInterface $query): void
    {
        $query->execute(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                organization_id INTEGER,
                email TEXT NOT NULL,
                role TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT \'active\',
                password_hash TEXT NOT NULL,
                is_deleted INTEGER NOT NULL DEFAULT 0,
                deleted_at TEXT,
                created_at TEXT,
                updated_at TEXT
            )'
        );
    }
}
