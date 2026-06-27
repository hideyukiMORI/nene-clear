<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateTotpTables extends AbstractMigration
{
    /**
     * TOTP MFA storage (#195): the per-user secret (encrypted at rest), the
     * consumed time steps (replay protection), and the one-time recovery codes
     * (stored hashed). See `NENE2 howto/totp-authentication` and ADR 0025.
     */
    public function change(): void
    {
        $this->table('totp_secrets')
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('secret', 'string', ['limit' => 255, 'null' => false]) // encrypted ciphertext
            ->addColumn('is_enabled', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('failed_attempts', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('locked_until', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addIndex(['user_id'], ['unique' => true])
            ->create();

        $this->table('used_totp_steps')
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('time_step', 'integer', ['null' => false])
            ->addColumn('used_at', 'datetime', ['null' => false])
            ->addIndex(['user_id', 'time_step'], ['unique' => true])
            ->create();

        $this->table('recovery_codes')
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('code_hash', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('used_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addIndex(['user_id'])
            ->create();
    }
}
