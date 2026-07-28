<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddDunningScheduleToClearSettings extends AbstractMigration
{
    public function change(): void
    {
        // Scheduled dunning (#400): the settings that decide *when* an unattended
        // run may send. What is sent, and every safety guard, stays in
        // SendDunningUseCase — the scheduler calls it, it does not re-implement it.
        //
        // Defaults are chosen so that applying this migration changes nothing:
        // the feature is off, and the remaining columns only describe what would
        // happen once an operator turns it on. Names are registered in
        // docs/explanation/terminology.md (§3, "Scheduled dunning").
        $this->table('clear_settings')
            ->addColumn('is_dunning_schedule_enabled', 'boolean', ['null' => false, 'default' => false])
            ->addColumn('dunning_initial_after_days', 'integer', ['null' => false, 'default' => 3])
            ->addColumn('dunning_reminder_after_days', 'integer', ['null' => false, 'default' => 14])
            ->addColumn('dunning_final_after_days', 'integer', ['null' => false, 'default' => 30])
            // Hour of day, 0–23, evaluated in the application timezone (see
            // dunning-scheduler-design.md §4). Not a timestamp: `_hour` per
            // naming-conventions.md §3.
            ->addColumn('dunning_window_start_hour', 'integer', ['null' => false, 'default' => 9])
            ->addColumn('dunning_window_end_hour', 'integer', ['null' => false, 'default' => 18])
            ->addColumn('is_dunning_weekdays_only', 'boolean', ['null' => false, 'default' => true])
            ->addColumn('dunning_max_per_run', 'integer', ['null' => false, 'default' => 50])
            ->update();
    }
}
