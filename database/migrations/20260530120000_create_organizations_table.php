<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateOrganizationsTable extends AbstractMigration
{
    public function change(): void
    {
        // Default Phinx `id` (auto-increment integer PK) for portability across
        // MySQL (Tier A) and SQLite (tests/local).
        $this->table('organizations')
            ->addColumn('slug', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
            ->addTimestamps()
            ->addIndex(['slug'], ['unique' => true])
            ->create();
    }
}
