<?php

declare(strict_types=1);

namespace NeneClear\Tests\Support;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;

/**
 * Transaction manager double for unit tests.
 *
 * Runs the unit of work inline (no real transaction) and passes the supplied
 * executor to the callback. Unit-test use cases build their repositories from
 * in-memory doubles that ignore the executor, so a {@see NullQueryExecutor} is
 * passed by default. Atomicity (real rollback) is covered by integration tests
 * that use the framework's PdoDatabaseTransactionManager.
 */
final readonly class FakeTransactionManager implements DatabaseTransactionManagerInterface
{
    public function __construct(
        private DatabaseQueryExecutorInterface $executor = new NullQueryExecutor(),
    ) {
    }

    public function transactional(callable $callback): mixed
    {
        return $callback($this->executor);
    }
}
