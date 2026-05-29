<?php

declare(strict_types=1);

namespace NeneClear\Organization;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class GetOrganizationByIdHandler
{
    public function __construct(
        private GetOrganizationByIdUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = (int) ($params['id'] ?? 0);

        $organization = $this->useCase->execute($id);

        return $this->response->create([
            'organization_id' => $organization->id,
            'slug' => $organization->slug,
            'name' => $organization->name,
        ]);
    }
}
