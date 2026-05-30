<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneClear\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class GetReconciliationByIdHandler
{
    public function __construct(
        private ReconciliationRepositoryInterface $reconciliations,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = (int) ($params['id'] ?? 0);
        $organizationId = AuthContext::organizationId($request) ?? 0;

        $reconciliation = $this->reconciliations->findById($organizationId, $id);

        if ($reconciliation === null) {
            throw new ReconciliationNotFoundException($id);
        }

        $allocs = $this->reconciliations->findAllocationsByReconciliation($id);

        return $this->response->create(ReconciliationResponse::toArray($reconciliation, $allocs));
    }
}
