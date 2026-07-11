<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneClear\Tenancy\CurrentOrganization;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class GetManualReceivableHandler
{
    public function __construct(
        private GetManualReceivableByIdUseCaseInterface $useCase,
        private JsonResponseFactory $response,
        private CurrentOrganization $organization,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = (int) ($params['id'] ?? 0);

        $receivable = $this->useCase->execute($id, $this->organization->id());

        return $this->response->create(ManualReceivableResponse::toArray($receivable));
    }
}
