<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUserInvitationsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('user_invitations')
            ->addColumn('organization_id', 'integer', ['null' => true, 'default' => null])
            ->addColumn('user_id', 'integer', ['null' => false])
            ->addColumn('token_hash', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('expires_at', 'datetime', ['null' => false])
            ->addColumn('accepted_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['token_hash'], ['unique' => true, 'name' => 'uq_user_invitations_token_hash'])
            ->addIndex(['user_id'], ['name' => 'idx_user_invitations_user'])
            ->create();
    }
}
