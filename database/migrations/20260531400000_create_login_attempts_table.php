<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateLoginAttemptsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('login_attempts')
            ->addColumn('identifier_hash', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('attempt_count', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('window_started_at', 'datetime', ['null' => false])
            ->addColumn('locked_until', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['identifier_hash'], ['unique' => true, 'name' => 'uq_login_attempts_identifier'])
            ->create();
    }
}
