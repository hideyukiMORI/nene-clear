<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneClear\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ApplyCreditHandler
{
    public function __construct(
        private ApplyCreditUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $creditId = (int) ($params['id'] ?? 0);

        $body = (array) $request->getParsedBody();
        $errors = [];

        $invoiceId = is_numeric($body['invoice_id'] ?? null) ? (int) $body['invoice_id'] : 0;
        if ($invoiceId <= 0) {
            $errors[] = new ValidationError('invoice_id', 'A valid invoice id is required.', 'required');
        }

        $amountCents = is_numeric($body['amount_cents'] ?? null) ? (int) $body['amount_cents'] : 0;
        if ($amountCents <= 0) {
            $errors[] = new ValidationError('amount_cents', 'amount_cents must be a positive integer.', 'invalid');
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $output = $this->useCase->execute(new ApplyCreditInput(
            organizationId: AuthContext::organizationId($request) ?? 0,
            creditId: $creditId,
            invoiceId: $invoiceId,
            amountCents: $amountCents,
            actorUserId: AuthContext::userId($request),
        ));

        return $this->response->create(ClientCreditResponse::toArray($output->credit));
    }
}
