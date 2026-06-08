<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditRecorderInterface;

final readonly class CreateManualReceivableUseCase implements CreateManualReceivableUseCaseInterface
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

    public function execute(CreateManualReceivableInput $input): ManualReceivable
    {
        // Dedupe check, insert, and audit commit together so a created receivable
        // can never lack its audit event (Issue #122). A new receivable starts
        // fully open: outstanding equals total (no allocations exist yet).
        return $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $tx) use ($input): ManualReceivable {
                $receivables = ($this->receivables)($tx);

                if ($receivables->findByReferenceNumber($input->organizationId, $input->referenceNumber) !== null) {
                    throw new ManualReceivableAlreadyExistsException($input->referenceNumber);
                }

                $now = $this->clock->now()->format('Y-m-d H:i:s');
                $id = $receivables->save(new ManualReceivable(
                    organizationId: $input->organizationId,
                    referenceNumber: $input->referenceNumber,
                    clientName: $input->clientName,
                    recipientEmail: $input->recipientEmail,
                    totalCents: $input->totalCents,
                    outstandingCents: $input->totalCents,
                    currency: $input->currency,
                    issuedAt: $input->issuedAt,
                    dueAt: $input->dueAt,
                    status: ManualReceivableStatus::Open,
                    createdBy: $input->actorUserId,
                    createdAt: $now,
                    updatedAt: $now,
                ));

                $created = $receivables->findById($id);
                if ($created === null) {
                    throw new ManualReceivableNotFoundException($id);
                }

                ($this->auditRecorder)($tx)->record(
                    $input->organizationId,
                    $input->actorUserId,
                    $now,
                    'manual_receivable_created',
                    'manual_receivable',
                    $created->id,
                    ['after' => ManualReceivableResponse::toArray($created)],
                );

                return $created;
            },
        );
    }
}
