<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateSchedulerLocksTable extends AbstractMigration
{
    public function change(): void
    {
        // Mutual exclusion for unattended runs (#400). Generic on purpose — the
        // scope lives in `lock_key` (e.g. `dunning:42`), not in the table name,
        // so a later scheduled job does not have to borrow a dunning-named lock.
        //
        // `lock_key` is the primary key: taking the lock is a single INSERT that
        // either succeeds or violates the key. Reading first and then inserting
        // would reopen the check-then-act window this table exists to close.
        //
        // `expires_at` is the crash valve: a run killed mid-flight never releases
        // its lock, so an expired row may be reclaimed — by the same atomic
        // statement, never by a "clean up then insert" pair.
        $this->table('scheduler_locks', ['id' => false, 'primary_key' => ['lock_key']])
            ->addColumn('lock_key', 'string', ['limit' => 128, 'null' => false])
            ->addColumn('holder_token', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('acquired_at', 'datetime', ['null' => false])
            ->addColumn('expires_at', 'datetime', ['null' => false])
            ->addIndex(['expires_at'])
            ->create();
    }
}
