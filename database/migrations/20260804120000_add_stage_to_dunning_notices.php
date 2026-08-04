<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Record which escalation stage a dunning notice was sent at (#414).
 *
 * Until now the stage picked the template and was then discarded, so nothing in
 * the record said whether a customer had already received a `final` demand. That
 * is a gap in the trail for the manual path (ADR 0011 tone discipline) and a hard
 * blocker for scheduled dunning (#400 §5: a stage is only reachable once the
 * previous one has actually been sent).
 *
 * Rows that predate this column take the `initial` default. That default is a
 * storage requirement, NOT a claim about what was sent — those sends have no
 * recorded stage, and nothing may read them as "initial was sent". Back-filling
 * them would mean guessing which stage went out, which is precisely the guessing
 * #414 exists to stop.
 */
final class AddStageToDunningNotices extends AbstractMigration
{
    public function change(): void
    {
        $this->table('dunning_notices')
            ->addColumn('stage', 'string', ['limit' => 32, 'default' => 'initial', 'after' => 'template_version'])
            ->update();
    }
}
