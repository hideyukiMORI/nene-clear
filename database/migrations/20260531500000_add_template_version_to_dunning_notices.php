<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddTemplateVersionToDunningNotices extends AbstractMigration
{
    public function change(): void
    {
        $this->table('dunning_notices')
            ->addColumn('template_version', 'string', ['limit' => 32, 'default' => '1.0', 'after' => 'channel'])
            ->update();
    }
}
