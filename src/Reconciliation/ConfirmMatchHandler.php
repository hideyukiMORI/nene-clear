<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneClear\Auth\AuthContext;
use NeneClear\Receivable\ReceivableSource;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ConfirmMatchHandler
{
    public function __construct(
        private ConfirmMatchUseCaseInterface $useCase,
        private ReconciliationRepositoryInterface $reconciliations,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $errors = [];

        $bankTransactionId = isset($body['bank_transaction_id']) && is_numeric($body['bank_transaction_id'])
            ? (int) $body['bank_transaction_id']
            : 0;
        if ($bankTransactionId <= 0) {
            $errors[] = new ValidationError('bank_transaction_id', 'A valid bank transaction id is required.', 'required');
        }

        $rawAllocations = $body['allocations'] ?? null;
        if (!is_array($rawAllocations) || count($rawAllocations) === 0) {
            $errors[] = new ValidationError('allocations', 'At least one allocation is required.', 'required');
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $allocations = [];
        foreach ((array) $rawAllocations as $i => $raw) {
            if (!is_array($raw)) {
                throw new ValidationException([new ValidationError("allocations.$i", 'Each allocation must be an object.', 'invalid')]);
            }
            $amountCents = is_numeric($raw['amount_cents'] ?? null) ? (int) $raw['amount_cents'] : 0;
            if ($amountCents <= 0) {
                throw new ValidationException([new ValidationError("allocations.$i", 'amount_cents must be a positive integer.', 'invalid')]);
            }

            // Default source keeps existing (upstream-only) clients working unchanged.
            $source = is_string($raw['source'] ?? null) ? (string) $raw['source'] : ReceivableSource::InvoiceUpstream->value;
            if ($source === ReceivableSource::Manual->value) {
                $manualReceivableId = is_numeric($raw['manual_receivable_id'] ?? null) ? (int) $raw['manual_receivable_id'] : 0;
                if ($manualReceivableId <= 0) {
                    throw new ValidationException([new ValidationError("allocations.$i", 'manual_receivable_id must be a positive integer for a manual allocation.', 'invalid')]);
                }
                $allocations[] = AllocationInput::manual($manualReceivableId, $amountCents);
            } elseif ($source === ReceivableSource::InvoiceUpstream->value) {
                $invoiceId = is_numeric($raw['invoice_id'] ?? null) ? (int) $raw['invoice_id'] : 0;
                if ($invoiceId <= 0) {
                    throw new ValidationException([new ValidationError("allocations.$i", 'invoice_id must be a positive integer.', 'invalid')]);
                }
                $allocations[] = AllocationInput::upstream($invoiceId, $amountCents);
            } else {
                throw new ValidationException([new ValidationError("allocations.$i", 'source must be invoice_upstream or manual.', 'invalid')]);
            }
        }

        $reasonCode = isset($body['reason_code']) && is_string($body['reason_code']) ? $body['reason_code'] : null;
        $organizationId = AuthContext::organizationId($request) ?? 0;

        $idempotencyKey = $request->getHeaderLine('Idempotency-Key');
        $idempotencyKey = $idempotencyKey !== '' ? $idempotencyKey : null;

        $output = $this->useCase->execute(new ConfirmMatchInput(
            organizationId: $organizationId,
            bankTransactionId: $bankTransactionId,
            allocations: $allocations,
            actorUserId: AuthContext::userId($request),
            reasonCode: $reasonCode,
            idempotencyKey: $idempotencyKey,
        ));

        $reconciliation = $this->reconciliations->findById($organizationId, $output->reconciliationId)
            ?? throw new ReconciliationNotFoundException($output->reconciliationId);
        $allocs = $this->reconciliations->findAllocationsByReconciliation($organizationId, $output->reconciliationId);

        // 200 when returning a previously created reconciliation (idempotency replay), 201 for new creation.
        $statusCode = $output->idempotentReplay ? 200 : 201;
        $headers = $statusCode === 201 ? ['Location' => '/admin/reconciliations/' . $output->reconciliationId] : [];

        return $this->response->create(ReconciliationResponse::toArray($reconciliation, $allocs), $statusCode, $headers);
    }
}
