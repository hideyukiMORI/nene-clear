<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use NeneClear\Audit\AuditEvent;
use NeneClear\Audit\AuditEventRepositoryInterface;
use NeneClear\InvoiceUpstream\InvoiceUpstreamClientInterface;

final readonly class ApplyCreditUseCase implements ApplyCreditUseCaseInterface
{
    public function __construct(
        private ClientCreditRepositoryInterface $clientCredits,
        private InvoiceUpstreamClientInterface $invoiceClient,
        private AuditEventRepositoryInterface $auditEvents,
    ) {
    }

    public function execute(ApplyCreditInput $input): ApplyCreditOutput
    {
        $credit = $this->clientCredits->findById($input->organizationId, $input->creditId);

        if ($credit === null) {
            throw new ClientCreditNotFoundException($input->creditId);
        }

        if ($credit->status !== ClientCreditStatus::Open || $credit->remainingCents <= 0) {
            throw new CreditExceedsRemainingException(0);
        }

        if ($input->amountCents > $credit->remainingCents) {
            throw new CreditExceedsRemainingException($credit->remainingCents);
        }

        $externalRef = sprintf('clear:credit:%d:%d', $input->creditId, $input->invoiceId);
        $idempotencyKey = sprintf('clear:credit:apply:%d:%d', $input->creditId, $input->invoiceId);

        $this->invoiceClient->createPayment(
            organizationId: $input->organizationId,
            invoiceId: $input->invoiceId,
            amountCents: $input->amountCents,
            paidAt: $credit->createdAt,
            externalReference: $externalRef,
            idempotencyKey: $idempotencyKey,
        );

        $updated = $this->clientCredits->applyAmount($input->organizationId, $input->creditId, $input->amountCents);

        $this->auditEvents->record(new AuditEvent(
            organizationId: $input->organizationId,
            eventType: 'client_credit_applied',
            actorUserId: $input->actorUserId,
            occurredAt: $credit->createdAt,
            payload: [
                'client_credit_id' => $input->creditId,
                'invoice_id' => $input->invoiceId,
                'amount_cents' => $input->amountCents,
                'before' => [
                    'remaining_cents' => $credit->remainingCents,
                    'status' => $credit->status->value,
                ],
                'after' => [
                    'remaining_cents' => $updated->remainingCents,
                    'status' => $updated->status->value,
                ],
            ],
        ));

        return new ApplyCreditOutput(credit: $updated);
    }
}
