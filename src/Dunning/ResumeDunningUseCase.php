<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditEvent;
use NeneClear\Audit\AuditEventRepositoryInterface;

final readonly class ResumeDunningUseCase
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): DunningPauseRepositoryInterface $pauses
     * @param Closure(DatabaseQueryExecutorInterface): AuditEventRepositoryInterface $auditEvents
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $pauses,
        private Closure $auditEvents,
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
                $auditEvents = ($this->auditEvents)($ex);

                $pauses->resumeByInvoice($organizationId, $invoiceId, $actorUserId, $now);

                $auditEvents->record(new AuditEvent(
                    organizationId: $organizationId,
                    eventType: 'dunning_resumed',
                    actorUserId: $actorUserId,
                    occurredAt: $now,
                    payload: [
                        'invoice_id' => $invoiceId,
                        'before' => ['is_paused' => true],
                        'after' => ['is_paused' => false],
                    ],
                ));
            },
        );
    }
}
