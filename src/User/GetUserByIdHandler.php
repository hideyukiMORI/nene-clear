<?php

declare(strict_types=1);

namespace NeneClear\User;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneClear\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class GetUserByIdHandler
{
    public function __construct(
        private GetUserByIdUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = (int) ($params['id'] ?? 0);

        $user = $this->useCase->execute($id, AuthContext::organizationId($request));

        return $this->response->create(UserResponse::toArray($user));
    }
}
