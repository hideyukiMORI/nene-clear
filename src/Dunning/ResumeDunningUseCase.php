<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Closure;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;

final readonly class ResumeDunningUseCase
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): DunningPauseRepositoryInterface $pauses
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $pauses,
        private AuditRecorderFactoryInterface $auditFactory,
        private ClockInterface $clock,
    ) {
    }

    public function execute(int $organizationId, int $invoiceId, int $actorUserId): void
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        // The resume update and its audit record commit (or roll back) together (Issue #122).
        $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $ex) use ($organizationId, $invoiceId, $actorUserId, $now): void {
                $pauses = ($this->pauses)($ex);
                $auditRecorder = $this->auditFactory->forExecutor($ex);

                $pauses->resumeByInvoice($organizationId, $invoiceId, $actorUserId, $now);

                $auditRecorder->record(new AuditEvent(
                    action: 'dunning_resumed',
                    entityType: 'invoice',
                    entityId: $invoiceId,
                    actorId: $actorUserId,
                    organizationId: $organizationId,
                    occurredAt: $now,
                    before: ['is_paused' => true],
                    after: ['is_paused' => false],
                    metadata: ['invoice_id' => $invoiceId],
                ));
            },
        );
    }
}
