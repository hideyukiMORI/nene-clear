<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditRecorderInterface;
use NeneClear\BankImport\BankTransactionNotFoundException;
use NeneClear\BankImport\BankTransactionRepositoryInterface;
use NeneClear\BankImport\BankTransactionStatus;
use NeneClear\InvoiceUpstream\InvoiceUpstreamClientInterface;

final readonly class ConfirmMatchUseCase implements ConfirmMatchUseCaseInterface
{
    /**
     * @param DatabaseQueryExecutorInterface $reader executor for pre-transaction reads (before upstream calls)
     * @param Closure(DatabaseQueryExecutorInterface): BankTransactionRepositoryInterface $transactions
     * @param Closure(DatabaseQueryExecutorInterface): ReconciliationRepositoryInterface $reconciliations
     * @param Closure(DatabaseQueryExecutorInterface): ClientCreditRepositoryInterface $clientCredits
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface $auditRecorder
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private DatabaseQueryExecutorInterface $reader,
        private Closure $transactions,
        private Closure $reconciliations,
        private Closure $clientCredits,
        private InvoiceUpstreamClientInterface $invoiceClient,
        private Closure $auditRecorder,
        private ClockInterface $clock,
    ) {
    }

    public function execute(ConfirmMatchInput $input): ConfirmMatchOutput
    {
        // Idempotency: if a key was provided and a reconciliation with that key already
        // exists for this organization, return it without re-processing (and without
        // re-issuing upstream payments).
        if ($input->idempotencyKey !== null) {
            $existing = ($this->reconciliations)($this->reader)->findByIdempotencyKey($input->organizationId, $input->idempotencyKey);
            if ($existing !== null && $existing->id !== null) {
                return new ConfirmMatchOutput(reconciliationId: $existing->id, idempotentReplay: true);
            }
        }

        $tx = ($this->transactions)($this->reader)->findById($input->organizationId, $input->bankTransactionId);

        if ($tx === null) {
            throw new BankTransactionNotFoundException($input->bankTransactionId);
        }

        if ($tx->status !== BankTransactionStatus::Unmatched && $tx->status !== BankTransactionStatus::PartiallyMatched) {
            throw new BankTransactionNotMatchableException($input->bankTransactionId, $tx->status->value);
        }

        $now = $this->clock->now()->format('Y-m-d H:i:s');

        // Validate outstanding and call upstream for each allocation. Upstream payment
        // creation is an external side effect and must happen OUTSIDE the database
        // transaction (it cannot be rolled back); the local writes below are atomic.
        $paymentsCreated = [];
        foreach ($input->allocations as $allocation) {
            $invoice = $this->invoiceClient->getInvoice($input->organizationId, $allocation->invoiceId);

            if ($allocation->amountCents > $invoice->outstandingCents) {
                throw new AllocationExceedsOutstandingException($allocation->invoiceId, $invoice->outstandingCents);
            }

            $externalRef = sprintf('clear:recon:pending:%s', bin2hex(random_bytes(8)));
            $idempotencyKey = sprintf('clear:recon:confirm:%d:%d:%s', $input->bankTransactionId, $allocation->invoiceId, bin2hex(random_bytes(8)));

            $payment = $this->invoiceClient->createPayment(
                organizationId: $input->organizationId,
                invoiceId: $allocation->invoiceId,
                amountCents: $allocation->amountCents,
                paidAt: $tx->valueDate,
                externalReference: $externalRef,
                idempotencyKey: $idempotencyKey,
            );

            $paymentsCreated[] = [
                'allocation' => $allocation,
                'payment' => $payment,
                'externalRef' => $externalRef,
                'invoiceOutstandingBefore' => $invoice->outstandingCents,
                'clientId' => $invoice->clientId,
            ];
        }

        // Atomic local writes + audit: the reconciliation, its allocations, the bank
        // transaction status, any overpayment credit, and the audit record commit (or
        // roll back) together so a confirmed match can never lack its audit (Issue #122).
        return $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $ex) use ($input, $tx, $now, $paymentsCreated): ConfirmMatchOutput {
                $reconciliations = ($this->reconciliations)($ex);
                $transactions = ($this->transactions)($ex);
                $clientCredits = ($this->clientCredits)($ex);
                $auditRecorder = ($this->auditRecorder)($ex);

                $reconciliationId = $reconciliations->save(new Reconciliation(
                    organizationId: $input->organizationId,
                    bankTransactionId: $input->bankTransactionId,
                    status: ReconciliationStatus::Confirmed,
                    confirmedBy: $input->actorUserId,
                    confirmedAt: $now,
                    reasonCode: $input->reasonCode,
                    idempotencyKey: $input->idempotencyKey,
                ));

                $totalAllocated = 0;
                foreach ($paymentsCreated as $entry) {
                    /** @var AllocationInput $allocation */
                    $allocation = $entry['allocation'];
                    $payment = $entry['payment'];
                    $externalRef = sprintf('clear:recon:%d:%d', $reconciliationId, $allocation->invoiceId);

                    $reconciliations->saveAllocation(new ReconciliationAllocation(
                        organizationId: $input->organizationId,
                        reconciliationId: $reconciliationId,
                        invoiceId: $allocation->invoiceId,
                        amountCents: $allocation->amountCents,
                        paymentId: $payment->paymentId,
                        externalReference: $externalRef,
                    ));

                    $totalAllocated += $allocation->amountCents;
                }

                $remainder = $tx->amountCents - $totalAllocated;
                $newStatus = $remainder <= 0 ? BankTransactionStatus::Matched : BankTransactionStatus::PartiallyMatched;
                $transactions->updateStatusById($input->organizationId, $input->bankTransactionId, $newStatus);

                if ($remainder > 0) {
                    $clientCredits->save(new ClientCredit(
                        organizationId: $input->organizationId,
                        clientId: $paymentsCreated[0]['clientId'],
                        amountCents: $remainder,
                        remainingCents: $remainder,
                        status: ClientCreditStatus::Open,
                        sourceBankTransactionId: $input->bankTransactionId,
                        reconciliationId: $reconciliationId,
                        createdBy: $input->actorUserId,
                        createdAt: $now,
                    ));
                }

                $auditRecorder->record(
                    $input->organizationId,
                    $input->actorUserId,
                    $now,
                    'reconciliation_confirmed',
                    'payment_reconciliation',
                    $reconciliationId,
                    [
                        'before' => [
                            'bank_transaction_status' => $tx->status->value,
                            'bank_transaction_amount_cents' => $tx->amountCents,
                            'allocations' => array_map(
                                static fn (array $e): array => [
                                    'invoice_id' => $e['allocation']->invoiceId,
                                    'invoice_outstanding_cents' => $e['invoiceOutstandingBefore'],
                                ],
                                $paymentsCreated,
                            ),
                        ],
                        'after' => [
                            'payment_reconciliation_id' => $reconciliationId,
                            'bank_transaction_status' => $newStatus->value,
                            'total_allocated_cents' => $totalAllocated,
                            'remainder_cents' => $remainder,
                            'allocations' => array_map(
                                static fn (array $e): array => [
                                    'invoice_id' => $e['allocation']->invoiceId,
                                    'amount_cents' => $e['allocation']->amountCents,
                                    'payment_id' => $e['payment']->paymentId,
                                ],
                                $paymentsCreated,
                            ),
                        ],
                    ],
                );

                return new ConfirmMatchOutput(reconciliationId: $reconciliationId);
            },
        );
    }
}
