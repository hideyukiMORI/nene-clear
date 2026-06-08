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
use NeneClear\Receivable\ManualReceivable;
use NeneClear\Receivable\ManualReceivableCancelledException;
use NeneClear\Receivable\ManualReceivableNotFoundException;
use NeneClear\Receivable\ManualReceivableRepositoryInterface;
use NeneClear\Receivable\ManualReceivableStatus;
use NeneClear\Receivable\ReceivableSource;

final readonly class ConfirmMatchUseCase implements ConfirmMatchUseCaseInterface
{
    /**
     * @param DatabaseQueryExecutorInterface $reader executor for pre-transaction reads (before upstream calls)
     * @param Closure(DatabaseQueryExecutorInterface): BankTransactionRepositoryInterface $transactions
     * @param Closure(DatabaseQueryExecutorInterface): ReconciliationRepositoryInterface $reconciliations
     * @param Closure(DatabaseQueryExecutorInterface): ClientCreditRepositoryInterface $clientCredits
     * @param Closure(DatabaseQueryExecutorInterface): ManualReceivableRepositoryInterface $manualReceivables
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface $auditRecorder
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private DatabaseQueryExecutorInterface $reader,
        private Closure $transactions,
        private Closure $reconciliations,
        private Closure $clientCredits,
        private Closure $manualReceivables,
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

        // Validate outstanding per allocation. For UPSTREAM, the invoice payment is an
        // external side effect created here (OUTSIDE the db transaction, since it cannot
        // be rolled back). For MANUAL, Clear is the system of record — no upstream call;
        // the balance is adjusted in the atomic block below.
        $prepared = [];
        foreach ($input->allocations as $allocation) {
            if ($allocation->source === ReceivableSource::Manual) {
                $manualId = (int) $allocation->manualReceivableId;
                $receivable = ($this->manualReceivables)($this->reader)->findById($manualId);
                if ($receivable === null || $receivable->organizationId !== $input->organizationId) {
                    throw new ManualReceivableNotFoundException($manualId);
                }
                if ($receivable->status === ManualReceivableStatus::Cancelled) {
                    throw new ManualReceivableCancelledException($manualId);
                }
                if ($allocation->amountCents > $receivable->outstandingCents) {
                    throw new AllocationExceedsOutstandingException($manualId, $receivable->outstandingCents);
                }

                $prepared[] = [
                    'allocation' => $allocation,
                    'source' => ReceivableSource::Manual,
                    'payment' => null,
                    'outstandingBefore' => $receivable->outstandingCents,
                    'clientId' => null,
                    'clientName' => $receivable->clientName,
                    'manualReceivableId' => $manualId,
                ];

                continue;
            }

            $invoiceId = (int) $allocation->invoiceId;
            $invoice = $this->invoiceClient->getInvoice($input->organizationId, $invoiceId);

            if ($allocation->amountCents > $invoice->outstandingCents) {
                throw new AllocationExceedsOutstandingException($invoiceId, $invoice->outstandingCents);
            }

            $externalRef = sprintf('clear:recon:pending:%s', bin2hex(random_bytes(8)));
            $idempotencyKey = sprintf('clear:recon:confirm:%d:%d:%s', $input->bankTransactionId, $invoiceId, bin2hex(random_bytes(8)));

            $payment = $this->invoiceClient->createPayment(
                organizationId: $input->organizationId,
                invoiceId: $invoiceId,
                amountCents: $allocation->amountCents,
                paidAt: $tx->valueDate,
                externalReference: $externalRef,
                idempotencyKey: $idempotencyKey,
            );

            $prepared[] = [
                'allocation' => $allocation,
                'source' => ReceivableSource::InvoiceUpstream,
                'payment' => $payment,
                'outstandingBefore' => $invoice->outstandingCents,
                'clientId' => $invoice->clientId,
                'clientName' => null,
                'manualReceivableId' => null,
            ];
        }

        // Atomic local writes + audit: the reconciliation, its allocations, the bank
        // transaction status, any manual-receivable balance update, any overpayment
        // credit, and the audit record commit (or roll back) together (Issue #122).
        return $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $ex) use ($input, $tx, $now, $prepared): ConfirmMatchOutput {
                $reconciliations = ($this->reconciliations)($ex);
                $transactions = ($this->transactions)($ex);
                $clientCredits = ($this->clientCredits)($ex);
                $manualReceivables = ($this->manualReceivables)($ex);
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
                foreach ($prepared as $entry) {
                    /** @var AllocationInput $allocation */
                    $allocation = $entry['allocation'];

                    if ($entry['source'] === ReceivableSource::Manual) {
                        $reconciliations->saveAllocation(new ReconciliationAllocation(
                            organizationId: $input->organizationId,
                            reconciliationId: $reconciliationId,
                            invoiceId: null,
                            amountCents: $allocation->amountCents,
                            source: ReceivableSource::Manual,
                            manualReceivableId: $entry['manualReceivableId'],
                        ));
                        $this->applyManualBalance($manualReceivables, (int) $entry['manualReceivableId'], -$allocation->amountCents, $now);
                    } else {
                        $payment = $entry['payment'];
                        $externalRef = sprintf('clear:recon:%d:%d', $reconciliationId, (int) $allocation->invoiceId);
                        $reconciliations->saveAllocation(new ReconciliationAllocation(
                            organizationId: $input->organizationId,
                            reconciliationId: $reconciliationId,
                            invoiceId: $allocation->invoiceId,
                            amountCents: $allocation->amountCents,
                            paymentId: $payment->paymentId,
                            externalReference: $externalRef,
                        ));
                    }

                    $totalAllocated += $allocation->amountCents;
                }

                $remainder = $tx->amountCents - $totalAllocated;
                $newStatus = $remainder <= 0 ? BankTransactionStatus::Matched : BankTransactionStatus::PartiallyMatched;
                $transactions->updateStatusById($input->organizationId, $input->bankTransactionId, $newStatus);

                if ($remainder > 0) {
                    // The overpayment is attributed to the first allocation's payer; for a
                    // manual receivable that is a client_name (no upstream client_id).
                    $first = $prepared[0];
                    $clientCredits->save(new ClientCredit(
                        organizationId: $input->organizationId,
                        clientId: $first['clientId'],
                        amountCents: $remainder,
                        remainingCents: $remainder,
                        status: ClientCreditStatus::Open,
                        sourceBankTransactionId: $input->bankTransactionId,
                        reconciliationId: $reconciliationId,
                        createdBy: $input->actorUserId,
                        createdAt: $now,
                        source: $first['source'],
                        manualReceivableId: $first['manualReceivableId'],
                        clientName: $first['clientName'],
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
                            'allocations' => array_map(self::auditTarget(...), $prepared),
                        ],
                        'after' => [
                            'payment_reconciliation_id' => $reconciliationId,
                            'bank_transaction_status' => $newStatus->value,
                            'total_allocated_cents' => $totalAllocated,
                            'remainder_cents' => $remainder,
                            'allocations' => array_map(
                                static function (array $e): array {
                                    /** @var AllocationInput $a */
                                    $a = $e['allocation'];

                                    return [
                                        'source' => $e['source']->value,
                                        'invoice_id' => $a->invoiceId,
                                        'manual_receivable_id' => $e['manualReceivableId'],
                                        'amount_cents' => $a->amountCents,
                                        'payment_id' => $e['payment']?->paymentId,
                                    ];
                                },
                                $prepared,
                            ),
                        ],
                    ],
                );

                return new ConfirmMatchOutput(reconciliationId: $reconciliationId);
            },
        );
    }

    /**
     * Adjusts a manual receivable's outstanding by `$delta` (negative to apply a
     * payment, positive to restore on reversal) and recomputes its status. Clear
     * owns this balance (ADR 0014).
     */
    private function applyManualBalance(ManualReceivableRepositoryInterface $repo, int $id, int $delta, string $now): void
    {
        $receivable = $repo->findById($id);
        if ($receivable === null) {
            return;
        }

        $outstanding = max(0, $receivable->outstandingCents + $delta);
        $status = match (true) {
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

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private static function auditTarget(array $entry): array
    {
        /** @var AllocationInput $allocation */
        $allocation = $entry['allocation'];

        return [
            'source' => $entry['source']->value,
            'invoice_id' => $allocation->invoiceId,
            'manual_receivable_id' => $entry['manualReceivableId'],
            'outstanding_before_cents' => $entry['outstandingBefore'],
        ];
    }
}
