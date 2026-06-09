<?php

declare(strict_types=1);

namespace NeneClear\Database;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;

/**
 * Wraps a framework {@see DatabaseTransactionManagerInterface} so the executor
 * handed to each transactional callback is decorated with
 * {@see AdapterAwareQueryExecutor}.
 *
 * Repositories instantiated inside `transactional()` receive the transaction's
 * executor (NENE2 `DatabaseTransactionManagerInterface`). Without this wrapper
 * those in-transaction inserts would use the bare framework executor and fall
 * back to `lastInsertId()`, which returns 0 on PostgreSQL. Decorating here keeps
 * the `RETURNING id` behaviour consistent between container-scoped and
 * transaction-scoped executors.
 */
final readonly class AdapterAwareTransactionManager implements DatabaseTransactionManagerInterface
{
    public function __construct(
        private DatabaseTransactionManagerInterface $inner,
        private string $adapter,
    ) {
    }

    public function transactional(callable $callback): mixed
    {
        return $this->inner->transactional(
            fn (DatabaseQueryExecutorInterface $tx): mixed
                => $callback(new AdapterAwareQueryExecutor($tx, $this->adapter)),
        );
    }
}
