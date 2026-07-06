<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

use Closure;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;

final readonly class UpdateManualReceivableUseCase implements UpdateManualReceivableUseCaseInterface
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

    public function execute(UpdateManualReceivableInput $input): ManualReceivable
    {
        // Read, update, and audit share one transaction so before/after always
        // matches what was persisted (Issue #122).
        return $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $tx) use ($input): ManualReceivable {
                $receivables = ($this->receivables)($tx);

                $existing = $receivables->findById($input->id);
                if ($existing === null || $existing->organizationId !== $input->callerOrganizationId) {
                    throw new ManualReceivableNotFoundException($input->id);
                }
                if ($existing->status === ManualReceivableStatus::Cancelled) {
                    throw new ManualReceivableCancelledException($input->id);
                }

                // A reference_number change must keep the per-tenant dedupe invariant.
                if ($input->referenceNumber !== $existing->referenceNumber) {
                    $clash = $receivables->findByReferenceNumber($input->callerOrganizationId, $input->referenceNumber);
                    if ($clash !== null && $clash->id !== $existing->id) {
                        throw new ManualReceivableAlreadyExistsException($input->referenceNumber);
                    }
                }

                // No allocations exist yet (Issue 4 adds reconciliation), so a manual
                // receivable's outstanding always equals its total here.
                $now = $this->clock->now()->format('Y-m-d H:i:s');
                $updated = new ManualReceivable(
                    organizationId: $existing->organizationId,
                    referenceNumber: $input->referenceNumber,
                    clientName: $input->clientName,
                    recipientEmail: $input->recipientEmail,
                    totalCents: $input->totalCents,
                    outstandingCents: $input->totalCents,
                    currency: $input->currency,
                    issuedAt: $input->issuedAt,
                    dueAt: $input->dueAt,
                    status: $existing->status,
                    createdBy: $existing->createdBy,
                    createdAt: $existing->createdAt,
                    updatedAt: $now,
                    id: $existing->id,
                );

                $receivables->update($updated);

                $this->auditFactory->forExecutor($tx)->record(new AuditEvent(
                    action: 'manual_receivable_updated',
                    entityType: 'manual_receivable',
                    entityId: $existing->id,
                    actorId: $input->actorUserId,
                    organizationId: $existing->organizationId,
                    occurredAt: $now,
                    before: ManualReceivableResponse::toArray($existing),
                    after: ManualReceivableResponse::toArray($updated),
                ));

                return $updated;
            },
        );
    }
}
