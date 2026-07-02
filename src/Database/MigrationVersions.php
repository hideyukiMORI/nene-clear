<?php

declare(strict_types=1);

namespace NeneClear\Database;

/**
 * Reads the migration version ids this application knows about from the Phinx
 * migrations directory. Fed to the candidate-database preflight inspector
 * (issue #183) so it can classify a candidate's ledger as
 * fresh / compatible / ahead / foreign / partial — with an empty list the
 * inspector can never return `safe` (`migration_versions_unknown`).
 */
final readonly class MigrationVersions
{
    /**
     * @return list<string> Phinx version ids (the leading 14-digit timestamp of
     *                      each migration filename), ascending.
     */
    public static function fromDirectory(string $directory): array
    {
        $files = glob($directory . '/*.php');
        if ($files === false) {
            return [];
        }

        $versions = [];
        foreach ($files as $file) {
            if (preg_match('/^(\d{14})_/', basename($file), $matches) === 1) {
                $versions[] = $matches[1];
            }
        }

        sort($versions, SORT_STRING);

        return $versions;
    }
}
