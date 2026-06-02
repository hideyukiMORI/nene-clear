<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSoftDeleteToOrganizations extends AbstractMigration
{
    public function change(): void
    {
        // Auditable history is never hard-deleted: tenants are retired via
        // soft delete so audit events keep referencing a resolvable organization.
        $this->table('organizations')
            ->addColumn('is_deleted', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->update();
    }
}
