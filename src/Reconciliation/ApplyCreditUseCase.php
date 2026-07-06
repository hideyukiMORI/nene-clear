<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use Closure;
use Nene2\Audit\AuditEvent;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use NeneClear\InvoiceUpstream\InvoiceUpstreamClientInterface;

final readonly class ApplyCreditUseCase implements ApplyCreditUseCaseInterface
{
    /**
     * @param DatabaseQueryExecutorInterface $reader executor for pre-transaction reads (before upstream calls)
     * @param Closure(DatabaseQueryExecutorInterface): ClientCreditRepositoryInterface $clientCredits
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private DatabaseQueryExecutorInterface $reader,
        private Closure $clientCredits,
        private InvoiceUpstreamClientInterface $invoiceClient,
        private AuditRecorderFactoryInterface $auditFactory,
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
                $auditRecorder = $this->auditFactory->forExecutor($ex);

                $updated = $clientCredits->applyAmount($input->organizationId, $input->creditId, $input->amountCents);

                $auditRecorder->record(new AuditEvent(
                    action: 'client_credit_applied',
                    entityType: 'client_credit',
                    entityId: $input->creditId,
                    actorId: $input->actorUserId,
                    organizationId: $input->organizationId,
                    occurredAt: $credit->createdAt,
                    before: [
                        'remaining_cents' => $credit->remainingCents,
                        'status' => $credit->status->value,
                    ],
                    after: [
                        'remaining_cents' => $updated->remainingCents,
                        'status' => $updated->status->value,
                    ],
                    metadata: [
                        'client_credit_id' => $input->creditId,
                        'invoice_id' => $input->invoiceId,
                        'amount_cents' => $input->amountCents,
                    ],
                ));

                return new ApplyCreditOutput(credit: $updated);
            },
        );
    }
}
