<?php

declare(strict_types=1);

namespace NeneClear\User;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneClear\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class DeleteUserHandler
{
    public function __construct(
        private DeleteUserUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = (int) ($params['id'] ?? 0);

        $this->useCase->execute($id, AuthContext::organizationId($request));

        return $this->response->createEmpty(204);
    }
}
