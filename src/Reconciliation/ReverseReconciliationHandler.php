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

final readonly class ReverseReconciliationHandler
{
    public function __construct(
        private ReverseReconciliationUseCaseInterface $useCase,
        private ReconciliationRepositoryInterface $reconciliations,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $reconciliationId = (int) ($params['id'] ?? 0);

        $body = (array) $request->getParsedBody();
        $reversalReason = is_string($body['reversal_reason'] ?? null) ? trim($body['reversal_reason']) : '';

        if ($reversalReason === '') {
            throw new ValidationException([new ValidationError('reversal_reason', 'A reversal reason is required.', 'required')]);
        }

        $organizationId = AuthContext::organizationId($request) ?? 0;

        $this->useCase->execute(new ReverseReconciliationInput(
            organizationId: $organizationId,
            reconciliationId: $reconciliationId,
            actorUserId: AuthContext::userId($request),
            reversalReason: $reversalReason,
        ));

        $reconciliation = $this->reconciliations->findById($organizationId, $reconciliationId)
            ?? throw new ReconciliationNotFoundException($reconciliationId);
        $allocs = $this->reconciliations->findAllocationsByReconciliation($reconciliationId);

        return $this->response->create(ReconciliationResponse::toArray($reconciliation, $allocs));
    }
}
