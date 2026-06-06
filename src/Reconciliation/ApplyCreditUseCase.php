<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use NeneClear\Audit\AuditEvent;
use NeneClear\Audit\AuditEventRepositoryInterface;
use NeneClear\InvoiceUpstream\InvoiceUpstreamClientInterface;

final readonly class ApplyCreditUseCase implements ApplyCreditUseCaseInterface
{
    /**
     * @param DatabaseQueryExecutorInterface $reader executor for pre-transaction reads (before upstream calls)
     * @param Closure(DatabaseQueryExecutorInterface): ClientCreditRepositoryInterface $clientCredits
     * @param Closure(DatabaseQueryExecutorInterface): AuditEventRepositoryInterface $auditEvents
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private DatabaseQueryExecutorInterface $reader,
        private Closure $clientCredits,
        private InvoiceUpstreamClientInterface $invoiceClient,
        private Closure $auditEvents,
    ) {
    }

    public function execute(ApplyCreditInput $input): ApplyCreditOutput
    {
        $credit = ($this->clientCredits)($this->reader)->findById($input->organizationId, $input->creditId);

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

        // Upstream payment creation is an external side effect and runs OUTSIDE the
        // database transaction; the credit update + audit below are atomic (Issue #122).
        $this->invoiceClient->createPayment(
            organizationId: $input->organizationId,
            invoiceId: $input->invoiceId,
            amountCents: $input->amountCents,
            paidAt: $credit->createdAt,
            externalReference: $externalRef,
            idempotencyKey: $idempotencyKey,
        );

        return $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $ex) use ($input, $credit): ApplyCreditOutput {
                $clientCredits = ($this->clientCredits)($ex);
                $auditEvents = ($this->auditEvents)($ex);

                $updated = $clientCredits->applyAmount($input->organizationId, $input->creditId, $input->amountCents);

                $auditEvents->record(new AuditEvent(
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
            },
        );
    }
}
