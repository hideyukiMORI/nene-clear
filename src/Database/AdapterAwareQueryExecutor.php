<?php

declare(strict_types=1);

namespace NeneClear\Database;

use Nene2\Database\DatabaseQueryExecutorInterface;

/**
 * Wraps a framework {@see DatabaseQueryExecutorInterface} so that
 * {@see insert()} returns the generated primary key on every supported adapter.
 *
 * On MySQL and SQLite the framework's {@see DatabaseQueryExecutorInterface::insert()}
 * (execute + `lastInsertId()`) works as-is. On PostgreSQL `PDO::lastInsertId()`
 * returns 0 without a sequence name, so this decorator appends `RETURNING id` to
 * the INSERT and reads the value back in the same round trip — the pattern
 * prescribed by NENE2 `docs/howto/use-postgresql.md`.
 *
 * Assumes the primary key column is named `id`, which holds for every table in
 * this codebase (Phinx default auto-increment `id`). All other methods delegate
 * verbatim to the wrapped executor.
 *
 * @phpstan-import-type SqlParameters from DatabaseQueryExecutorInterface
 * @phpstan-import-type SqlRow from DatabaseQueryExecutorInterface
 */
final readonly class AdapterAwareQueryExecutor implements DatabaseQueryExecutorInterface
{
    public function __construct(
        private DatabaseQueryExecutorInterface $inner,
        private string $adapter,
    ) {
    }

    public function execute(string $sql, array $parameters = []): int
    {
        return $this->inner->execute($sql, $parameters);
    }

    public function insert(string $sql, array $parameters = []): int
    {
        if ($this->adapter !== 'pgsql') {
            return $this->inner->insert($sql, $parameters);
        }

        $row = $this->inner->fetchOne($sql . ' RETURNING id', $parameters);

        return (int) ($row['id'] ?? 0);
    }

    public function lastInsertId(): int
    {
        return $this->inner->lastInsertId();
    }

    public function fetchOne(string $sql, array $parameters = []): ?array
    {
        return $this->inner->fetchOne($sql, $parameters);
    }

    public function fetchAll(string $sql, array $parameters = []): array
    {
        return $this->inner->fetchAll($sql, $parameters);
    }
}
