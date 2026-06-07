<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddFiscalYearEndMonthToClearSettings extends AbstractMigration
{
    public function change(): void
    {
        // Fiscal year-end month (決算月), 1–12. Nullable: null = unset.
        $this->table('clear_settings')
            ->addColumn('fiscal_year_end_month', 'integer', ['null' => true, 'default' => null])
            ->update();
    }
}
