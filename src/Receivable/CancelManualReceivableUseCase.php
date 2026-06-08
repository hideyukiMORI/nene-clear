<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditRecorderInterface;

final readonly class CancelManualReceivableUseCase implements CancelManualReceivableUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): ManualReceivableRepositoryInterface $receivables
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface $auditRecorder
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $receivables,
        private Closure $auditRecorder,
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

                ($this->auditRecorder)($tx)->record(
                    $existing->organizationId,
                    $actorUserId,
                    $now,
                    'manual_receivable_cancelled',
                    'manual_receivable',
                    $existing->id,
                    [
                        'before' => ['status' => $existing->status->value],
                        'after' => ['status' => $cancelled->status->value],
                    ],
                );

                return $cancelled;
            },
        );
    }
}
