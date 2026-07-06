<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use Closure;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use NeneClear\BankImport\BankTransactionRepositoryInterface;
use NeneClear\BankImport\BankTransactionStatus;
use NeneClear\InvoiceUpstream\InvoiceUpstreamClientInterface;
use NeneClear\Receivable\ManualReceivable;
use NeneClear\Receivable\ManualReceivableRepositoryInterface;
use NeneClear\Receivable\ManualReceivableStatus;
use NeneClear\Receivable\ReceivableSource;

final readonly class ReverseReconciliationUseCase implements ReverseReconciliationUseCaseInterface
{
    /**
     * @param DatabaseQueryExecutorInterface $reader executor for pre-transaction reads (before upstream calls)
     * @param Closure(DatabaseQueryExecutorInterface): ReconciliationRepositoryInterface $reconciliations
     * @param Closure(DatabaseQueryExecutorInterface): ClientCreditRepositoryInterface $clientCredits
     * @param Closure(DatabaseQueryExecutorInterface): BankTransactionRepositoryInterface $transactions
     * @param Closure(DatabaseQueryExecutorInterface): ManualReceivableRepositoryInterface $manualReceivables
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private DatabaseQueryExecutorInterface $reader,
        private Closure $reconciliations,
        private Closure $clientCredits,
        private Closure $transactions,
        private Closure $manualReceivables,
        private InvoiceUpstreamClientInterface $invoiceClient,
        private AuditRecorderFactoryInterface $auditFactory,
        private ClockInterface $clock,
    ) {
    }

    public function execute(ReverseReconciliationInput $input): ReverseReconciliationOutput
    {
        $reconciliation = ($this->reconciliations)($this->reader)->findById($input->organizationId, $input->reconciliationId);

        if ($reconciliation === null) {
            throw new ReconciliationNotFoundException($input->reconciliationId);
        }

        if ($reconciliation->status !== ReconciliationStatus::Confirmed) {
            throw new ReconciliationAlreadyReversedException($input->reconciliationId);
        }

        $allocations = ($this->reconciliations)($this->reader)->findAllocationsByReconciliation($input->organizationId, $input->reconciliationId);
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        // Upstream payment voids are external side effects and run OUTSIDE the
        // database transaction; the local writes below are atomic (Issue #122).
        // Manual allocations have no upstream payment — their balance is restored
        // locally in the transaction.
        foreach ($allocations as $allocation) {
            if ($allocation->source === ReceivableSource::InvoiceUpstream && $allocation->paymentId !== null && $allocation->invoiceId !== null) {
                $idempotencyKey = sprintf('clear:recon:reverse:%d:%d', $input->reconciliationId, $allocation->invoiceId);
                $this->invoiceClient->voidPayment(
                    organizationId: $input->organizationId,
                    invoiceId: $allocation->invoiceId,
                    paymentId: $allocation->paymentId,
                    reason: $input->reversalReason,
                    idempotencyKey: $idempotencyKey,
                );
            }
        }

        return $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $ex) use ($input, $reconciliation, $allocations, $now): ReverseReconciliationOutput {
                $reconciliations = ($this->reconciliations)($ex);
                $transactions = ($this->transactions)($ex);
                $clientCredits = ($this->clientCredits)($ex);
                $manualReceivables = ($this->manualReceivables)($ex);
                $auditRecorder = $this->auditFactory->forExecutor($ex);

                $reconciliations->reverseById($input->reconciliationId, $now, $input->reversalReason);
                $transactions->updateStatusById($input->organizationId, $reconciliation->bankTransactionId, BankTransactionStatus::Unmatched);
                $clientCredits->voidByReconciliation($input->reconciliationId);

                // Restore each manual receivable's outstanding (Clear is its SSOR).
                foreach ($allocations as $allocation) {
                    if ($allocation->source === ReceivableSource::Manual && $allocation->manualReceivableId !== null) {
                        $this->restoreManualBalance($manualReceivables, $allocation->manualReceivableId, $allocation->amountCents, $now);
                    }
                }

                $auditRecorder->record(new AuditEvent(
                    action: 'reconciliation_reversed',
                    entityType: 'payment_reconciliation',
                    entityId: $input->reconciliationId,
                    actorId: $input->actorUserId,
                    organizationId: $input->organizationId,
                    occurredAt: $now,
                    before: [
                        'status' => 'confirmed',
                        'bank_transaction_id' => $reconciliation->bankTransactionId,
                        'confirmed_at' => $reconciliation->confirmedAt,
                        'allocations' => array_map(
                            static fn (ReconciliationAllocation $a): array => [
                                'source' => $a->source->value,
                                'invoice_id' => $a->invoiceId,
                                'manual_receivable_id' => $a->manualReceivableId,
                                'amount_cents' => $a->amountCents,
                                'payment_id' => $a->paymentId,
                            ],
                            $allocations,
                        ),
                    ],
                    after: [
                        'status' => 'reversed',
                        'bank_transaction_status' => 'unmatched',
                        'reversal_reason' => $input->reversalReason,
                    ],
                    metadata: ['payment_reconciliation_id' => $input->reconciliationId],
                ));

                return new ReverseReconciliationOutput(reconciliationId: $input->reconciliationId);
            },
        );
    }

    /**
     * Adds `$amountCents` back to a manual receivable's outstanding on reversal and
     * recomputes its status. A receivable cancelled after the match keeps its
     * cancelled status (only the balance is restored).
     */
    private function restoreManualBalance(ManualReceivableRepositoryInterface $repo, int $id, int $amountCents, string $now): void
    {
        $receivable = $repo->findById($id);
        if ($receivable === null) {
            return;
        }

        $outstanding = min($receivable->totalCents, $receivable->outstandingCents + $amountCents);
        $status = $receivable->status === ManualReceivableStatus::Cancelled
            ? ManualReceivableStatus::Cancelled
            : match (true) {
                $outstanding <= 0 => ManualReceivableStatus::Paid,
                $outstanding < $receivable->totalCents => ManualReceivableStatus::PartiallyPaid,
                default => ManualReceivableStatus::Open,
            };

        $repo->update(new ManualReceivable(
            organizationId: $receivable->organizationId,
            referenceNumber: $receivable->referenceNumber,
            clientName: $receivable->clientName,
            recipientEmail: $receivable->recipientEmail,
            totalCents: $receivable->totalCents,
            outstandingCents: $outstanding,
            currency: $receivable->currency,
            issuedAt: $receivable->issuedAt,
            dueAt: $receivable->dueAt,
            status: $status,
            createdBy: $receivable->createdBy,
            createdAt: $receivable->createdAt,
            updatedAt: $now,
            id: $receivable->id,
        ));
    }
}
