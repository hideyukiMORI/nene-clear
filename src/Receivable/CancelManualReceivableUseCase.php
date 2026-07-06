<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

use Closure;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;

final readonly class CancelManualReceivableUseCase implements CancelManualReceivableUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): ManualReceivableRepositoryInterface $receivables
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $receivables,
        private AuditRecorderFactoryInterface $auditFactory,
        private ClockInterface $clock,
    ) {
    }

    public function execute(int $id, int $callerOrganizationId, int $actorUserId): ManualReceivable
    {
        return $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $tx) use ($id, $callerOrganizationId, $actorUserId): ManualReceivable {
                $receivables = ($this->receivables)($tx);

                $existing = $receivables->findById($id);
                if ($existing === null || $existing->organizationId !== $callerOrganizationId) {
                    throw new ManualReceivableNotFoundException($id);
                }
                if ($existing->status === ManualReceivableStatus::Cancelled) {
                    throw new ManualReceivableCancelledException($id);
                }

                $now = $this->clock->now()->format('Y-m-d H:i:s');
                $cancelled = new ManualReceivable(
                    organizationId: $existing->organizationId,
                    referenceNumber: $existing->referenceNumber,
                    clientName: $existing->clientName,
                    recipientEmail: $existing->recipientEmail,
                    totalCents: $existing->totalCents,
                    outstandingCents: $existing->outstandingCents,
                    currency: $existing->currency,
                    issuedAt: $existing->issuedAt,
                    dueAt: $existing->dueAt,
                    status: ManualReceivableStatus::Cancelled,
                    createdBy: $existing->createdBy,
                    createdAt: $existing->createdAt,
                    updatedAt: $now,
                    id: $existing->id,
                );

                $receivables->update($cancelled);

                $this->auditFactory->forExecutor($tx)->record(new AuditEvent(
                    action: 'manual_receivable_cancelled',
                    entityType: 'manual_receivable',
                    entityId: $existing->id,
                    actorId: $actorUserId,
                    organizationId: $existing->organizationId,
                    occurredAt: $now,
                    before: ['status' => $existing->status->value],
                    after: ['status' => $cancelled->status->value],
                ));

                return $cancelled;
            },
        );
    }
}
