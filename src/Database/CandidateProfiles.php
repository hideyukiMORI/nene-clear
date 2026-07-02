<?php

declare(strict_types=1);

namespace NeneClear\Database;

use Nene2\Config\DatabaseConfig;
use Nene2\Database\PdoConnectionFactory;
use Nene2\Database\Preflight\CandidateProfile;

/**
 * Builds the candidate-database profiles for `POST /machine/database/preflight`
 * from the application's own environment (issue #183).
 *
 * Candidates are declared as env allowlist entries — connection details are
 * never accepted from the request body (SSRF prevention, NENE2#1419):
 *
 *     NENE_CLEAR_DB_CANDIDATE_<ID>_ADAPTER=sqlite|mysql|pgsql
 *     NENE_CLEAR_DB_CANDIDATE_<ID>_NAME=…        # database name / sqlite path
 *     NENE_CLEAR_DB_CANDIDATE_<ID>_HOST=…        # mysql/pgsql (default 127.0.0.1)
 *     NENE_CLEAR_DB_CANDIDATE_<ID>_PORT=…        # default 3306 / 5432
 *     NENE_CLEAR_DB_CANDIDATE_<ID>_USER=…        # SELECT-only credential recommended
 *     NENE_CLEAR_DB_CANDIDATE_<ID>_PASSWORD=…
 *     NENE_CLEAR_DB_CANDIDATE_<ID>_CHARSET=…     # default utf8mb4 (ignored by pgsql)
 *     NENE_CLEAR_DB_CANDIDATE_<ID>_MULTI_TENANT= # "true" for multi-tenant deployments
 *
 * The caller references the candidate as the lowercased `<ID>` segment.
 * A malformed entry is skipped (never fails the boot) — the candidate is then
 * simply unknown to the endpoint (422).
 */
final readonly class CandidateProfiles
{
    private const PREFIX = 'NENE_CLEAR_DB_CANDIDATE_';

    /**
     * @param array<array-key, mixed> $env Typically `$_ENV` at the config boundary.
     *
     * @return array<string, CandidateProfile> keyed by candidate id
     */
    public static function fromEnv(array $env): array
    {
        $profiles = [];

        foreach (self::candidateKeys($env) as $key) {
            $key = (string) $key; // PHP array keys coerce numeric-looking ids to int
            $value = static fn (string $suffix, string $default = ''): string => is_string($env[self::PREFIX . $key . '_' . $suffix] ?? null)
                ? (string) $env[self::PREFIX . $key . '_' . $suffix]
                : $default;

            $adapter = $value('ADAPTER', 'sqlite');
            $id = strtolower($key);

            try {
                $config = match ($adapter) {
                    'mysql', 'pgsql' => new DatabaseConfig(
                        url: null,
                        environment: 'candidate',
                        adapter: $adapter,
                        host: $value('HOST', '127.0.0.1'),
                        port: (int) $value('PORT', $adapter === 'mysql' ? '3306' : '5432'),
                        name: $value('NAME'),
                        user: $value('USER'),
                        password: $value('PASSWORD'),
                        charset: $value('CHARSET', $adapter === 'mysql' ? 'utf8mb4' : 'utf8'),
                    ),
                    'sqlite' => DatabaseConfig::sqlite($value('NAME'), 'candidate'),
                    default => null,
                };
            } catch (\Throwable) {
                continue; // malformed entry — skip, never fail the boot
            }

            if ($config === null || $id === '') {
                continue;
            }

            $profiles[$id] = new CandidateProfile(
                id: $id,
                connectionFactory: new PdoConnectionFactory($config),
                multiTenant: $value('MULTI_TENANT') === 'true',
            );
        }

        return $profiles;
    }

    /**
     * @param array<array-key, mixed> $env
     *
     * @return list<string> distinct `<ID>` segments that declare an adapter or name
     */
    private static function candidateKeys(array $env): array
    {
        $keys = [];
        foreach (array_keys($env) as $envKey) {
            if (!is_string($envKey) || !str_starts_with($envKey, self::PREFIX)) {
                continue;
            }
            if (preg_match('/^' . self::PREFIX . '([A-Z0-9_]+)_(ADAPTER|NAME)$/', $envKey, $matches) === 1) {
                $keys[$matches[1]] = true;
            }
        }

        return array_keys($keys);
    }
}
