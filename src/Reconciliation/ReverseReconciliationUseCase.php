<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditRecorderInterface;
use NeneClear\BankImport\BankTransactionRepositoryInterface;
use NeneClear\BankImport\BankTransactionStatus;
use NeneClear\InvoiceUpstream\InvoiceUpstreamClientInterface;

final readonly class ReverseReconciliationUseCase implements ReverseReconciliationUseCaseInterface
{
    /**
     * @param DatabaseQueryExecutorInterface $reader executor for pre-transaction reads (before upstream calls)
     * @param Closure(DatabaseQueryExecutorInterface): ReconciliationRepositoryInterface $reconciliations
     * @param Closure(DatabaseQueryExecutorInterface): ClientCreditRepositoryInterface $clientCredits
     * @param Closure(DatabaseQueryExecutorInterface): BankTransactionRepositoryInterface $transactions
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface $auditRecorder
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private DatabaseQueryExecutorInterface $reader,
        private Closure $reconciliations,
        private Closure $clientCredits,
        private Closure $transactions,
        private InvoiceUpstreamClientInterface $invoiceClient,
        private Closure $auditRecorder,
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
        foreach ($allocations as $allocation) {
            if ($allocation->paymentId !== null) {
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
                $auditRecorder = ($this->auditRecorder)($ex);

                $reconciliations->reverseById($input->reconciliationId, $now, $input->reversalReason);
                $transactions->updateStatusById($input->organizationId, $reconciliation->bankTransactionId, BankTransactionStatus::Unmatched);
                $clientCredits->voidByReconciliation($input->reconciliationId);

                $auditRecorder->record(
                    $input->organizationId,
                    $input->actorUserId,
                    $now,
                    'reconciliation_reversed',
                    'payment_reconciliation',
                    $input->reconciliationId,
                    [
                        'payment_reconciliation_id' => $input->reconciliationId,
                        'before' => [
                            'status' => 'confirmed',
                            'bank_transaction_id' => $reconciliation->bankTransactionId,
                            'confirmed_at' => $reconciliation->confirmedAt,
                            'allocations' => array_map(
                                static fn (ReconciliationAllocation $a): array => [
                                    'invoice_id' => $a->invoiceId,
                                    'amount_cents' => $a->amountCents,
                                    'payment_id' => $a->paymentId,
                                ],
                                $allocations,
                            ),
                        ],
                        'after' => [
                            'status' => 'reversed',
                            'bank_transaction_status' => 'unmatched',
                            'reversal_reason' => $input->reversalReason,
                        ],
                    ],
                );

                return new ReverseReconciliationOutput(reconciliationId: $input->reconciliationId);
            },
        );
    }
}
