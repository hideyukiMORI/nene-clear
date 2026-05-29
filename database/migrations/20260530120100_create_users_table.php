<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUsersTable extends AbstractMigration
{
    public function change(): void
    {
        // Default Phinx `id` (auto-increment integer PK) for portability across
        // MySQL (Tier A) and SQLite (tests/local).
        $this->table('users')
            ->addColumn('organization_id', 'integer', ['null' => true])
            ->addColumn('email', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('role', 'string', ['limit' => 32, 'null' => false])
            ->addColumn('status', 'string', ['limit' => 32, 'null' => false, 'default' => 'active'])
            ->addColumn('password_hash', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('is_deleted', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->addTimestamps()
            ->addIndex(['organization_id', 'email'], ['unique' => true])
            ->addIndex(['organization_id'])
            ->create();
    }
}
