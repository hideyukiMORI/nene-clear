<?php

declare(strict_types=1);

namespace NeneClear\Database;

use Nene2\Database\Preflight\ApplicationIdentity;

/**
 * The stable identity NeNe Clear stamps into its own database and expects to
 * find again when preflighting a candidate database (issue #183, NENE2#1420).
 *
 * The application id identifies this database lineage as NeNe Clear's (vs any
 * other NENE2 application sharing the same ledger conventions). The tenant id
 * stays null: a Clear deployment keeps all of its organizations in one
 * database, so the database itself is not per-tenant.
 */
final readonly class ApplicationDatabaseIdentity
{
    public const APPLICATION_ID = 'nene-clear';

    public static function identity(): ApplicationIdentity
    {
        return new ApplicationIdentity(self::APPLICATION_ID, null);
    }
}
