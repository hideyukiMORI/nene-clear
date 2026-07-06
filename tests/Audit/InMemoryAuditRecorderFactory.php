<?php

declare(strict_types=1);

namespace NeneClear\Tests\Audit;

use Nene2\Audit\AuditEventRepositoryInterface;
use Nene2\Audit\AuditRecorder;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Audit\AuditRecorderInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\ClockInterface;

/**
 * {@see AuditRecorderFactoryInterface} double for unit tests.
 *
 * Ignores the transaction executor (unit-test repositories are in-memory) and
 * hands out a framework {@see AuditRecorder} bound to the supplied repository,
 * so use cases exercise the real recorder while events land in an
 * {@see InMemoryAuditEventRepository} (or a throwing one for atomicity tests).
 */
final readonly class InMemoryAuditRecorderFactory implements AuditRecorderFactoryInterface
{
    public function __construct(
        private AuditEventRepositoryInterface $repository,
        private ClockInterface $clock,
    ) {
    }

    public function forExecutor(DatabaseQueryExecutorInterface $executor): AuditRecorderInterface
    {
        return new AuditRecorder($this->repository, $this->clock);
    }
}
