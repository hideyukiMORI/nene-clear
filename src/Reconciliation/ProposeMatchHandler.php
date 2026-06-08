<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneClear\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ProposeMatchHandler
{
    public function __construct(
        private ProposeMatchUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $bankTransactionId = isset($body['bank_transaction_id']) && is_numeric($body['bank_transaction_id'])
            ? (int) $body['bank_transaction_id']
            : 0;

        if ($bankTransactionId <= 0) {
            throw new ValidationException([new ValidationError('bank_transaction_id', 'A valid bank transaction id is required.', 'required')]);
        }

        $output = $this->useCase->execute(new ProposeMatchInput(
            organizationId: AuthContext::organizationId($request) ?? 0,
            bankTransactionId: $bankTransactionId,
        ));

        return $this->response->create([
            'bank_transaction_id' => $output->bankTransactionId,
            'upstream_unavailable' => $output->upstreamUnavailable,
            'suggestions' => array_map(
                static fn (MatchSuggestion $s): array => [
                    'source' => $s->source->value,
                    'invoice_id' => $s->invoiceId,
                    'invoice_number' => $s->invoiceNumber,
                    'manual_receivable_id' => $s->manualReceivableId,
                    'reference_number' => $s->referenceNumber,
                    'amount_cents' => $s->amountCents,
                    'outstanding_cents' => $s->outstandingCents,
                    'score' => $s->score,
                    'reason' => $s->reason,
                ],
                $output->suggestions,
            ),
        ]);
    }
}
