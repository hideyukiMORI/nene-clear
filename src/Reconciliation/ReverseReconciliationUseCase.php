<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditEvent;
use NeneClear\Audit\AuditEventRepositoryInterface;
use NeneClear\BankImport\BankTransactionRepositoryInterface;
use NeneClear\BankImport\BankTransactionStatus;
use NeneClear\InvoiceUpstream\InvoiceUpstreamClientInterface;

final readonly class ReverseReconciliationUseCase implements ReverseReconciliationUseCaseInterface
{
    public function __construct(
        private ReconciliationRepositoryInterface $reconciliations,
        private ClientCreditRepositoryInterface $clientCredits,
        private BankTransactionRepositoryInterface $transactions,
        private InvoiceUpstreamClientInterface $invoiceClient,
        private AuditEventRepositoryInterface $auditEvents,
        private ClockInterface $clock,
    ) {
    }

    public function execute(ReverseReconciliationInput $input): ReverseReconciliationOutput
    {
        $reconciliation = $this->reconciliations->findById($input->organizationId, $input->reconciliationId);

        if ($reconciliation === null) {
            throw new ReconciliationNotFoundException($input->reconciliationId);
        }

        if ($reconciliation->status !== ReconciliationStatus::Confirmed) {
            throw new ReconciliationAlreadyReversedException($input->reconciliationId);
        }

        $allocations = $this->reconciliations->findAllocationsByReconciliation($input->organizationId, $input->reconciliationId);
        $now = $this->clock->now()->format('Y-m-d H:i:s');

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

        $this->reconciliations->reverseById($input->reconciliationId, $now, $input->reversalReason);
        $this->transactions->updateStatusById($input->organizationId, $reconciliation->bankTransactionId, BankTransactionStatus::Unmatched);
        $this->clientCredits->voidByReconciliation($input->reconciliationId);

        $this->auditEvents->record(new AuditEvent(
            organizationId: $input->organizationId,
            eventType: 'reconciliation_reversed',
            actorUserId: $input->actorUserId,
            occurredAt: $now,
            payload: [
                'payment_reconciliation_id' => $input->reconciliationId,
                'bank_transaction_id' => $reconciliation->bankTransactionId,
                'reversal_reason' => $input->reversalReason,
            ],
        ));

        return new ReverseReconciliationOutput(reconciliationId: $input->reconciliationId);
    }
}
