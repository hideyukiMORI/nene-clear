<?php

declare(strict_types=1);

namespace NeneClear\Tests\Support;

use LogicException;
use Nene2\Database\DatabaseQueryExecutorInterface;

/**
 * A query executor that throws on every call.
 *
 * Used by {@see FakeTransactionManager} for unit tests whose repository factories
 * ignore the executor (they return in-memory doubles). If a unit test ever reaches
 * a real SQL call through this executor, that is a wiring mistake and should fail
 * loudly rather than hit a database.
 *
 * @phpstan-import-type SqlParameters from DatabaseQueryExecutorInterface
 * @phpstan-import-type SqlRow from DatabaseQueryExecutorInterface
 */
final class NullQueryExecutor implements DatabaseQueryExecutorInterface
{
    public function execute(string $sql, array $parameters = []): int
    {
        throw new LogicException('NullQueryExecutor cannot run SQL; unit-test repository factories must ignore the executor.');
    }

    public function insert(string $sql, array $parameters = []): int
    {
        throw new LogicException('NullQueryExecutor cannot run SQL; unit-test repository factories must ignore the executor.');
    }

    public function lastInsertId(): int
    {
        throw new LogicException('NullQueryExecutor cannot run SQL; unit-test repository factories must ignore the executor.');
    }

    public function fetchOne(string $sql, array $parameters = []): ?array
    {
        throw new LogicException('NullQueryExecutor cannot run SQL; unit-test repository factories must ignore the executor.');
    }

    public function fetchAll(string $sql, array $parameters = []): array
    {
        throw new LogicException('NullQueryExecutor cannot run SQL; unit-test repository factories must ignore the executor.');
    }
}
