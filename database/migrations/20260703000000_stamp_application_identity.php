<?php

declare(strict_types=1);

use NeneClear\Database\ApplicationDatabaseIdentity;
use Phinx\Migration\AbstractMigration;

/**
 * Stamps this database with NeNe Clear's application identity marker
 * (`nene2_app_identity`, NENE2#1420) so a candidate-database preflight can
 * verify "is this database mine?" (issue #183). Running this migration on an
 * existing database is the backfill; fresh installs get it as part of the
 * normal migrate. Idempotent: the single marker row is replaced.
 *
 * The table shape mirrors `Nene2\Database\Preflight\ApplicationIdentityMarker`
 * (VARCHAR(190) columns, no primary key). The marker class itself is not used
 * here because it opens its own transaction, which conflicts with the
 * transaction Phinx already holds around a migration.
 */
final class StampApplicationIdentity extends AbstractMigration
{
    private const TABLE = 'nene2_app_identity';

    public function up(): void
    {
        if (!$this->hasTable(self::TABLE)) {
            $this->table(self::TABLE, ['id' => false])
                ->addColumn('application_id', 'string', ['limit' => 190, 'null' => false])
                ->addColumn('tenant_id', 'string', ['limit' => 190, 'null' => true])
                ->create();
        }

        $this->execute(sprintf('DELETE FROM %s', self::TABLE));
        $this->table(self::TABLE)
            ->insert([
                ['application_id' => ApplicationDatabaseIdentity::APPLICATION_ID, 'tenant_id' => null],
            ])
            ->saveData();
    }

    public function down(): void
    {
        if ($this->hasTable(self::TABLE)) {
            $this->table(self::TABLE)->drop()->save();
        }
    }
}
