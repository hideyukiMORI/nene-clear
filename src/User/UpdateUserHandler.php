<?php

declare(strict_types=1);

namespace NeneClear\User;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneClear\Auth\AuthContext;
use NeneClear\Auth\Role;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class UpdateUserHandler
{
    public function __construct(
        private UpdateUserUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = (int) ($params['id'] ?? 0);

        $body = JsonRequestBodyParser::parse($request);

        $role = null;
        if (isset($body['role'])) {
            $role = Role::tryFrom((string) $body['role']);
            if ($role === null) {
                throw new ValidationException([new ValidationError('role', 'A valid role is required.', 'invalid')]);
            }
        }

        $status = null;
        if (isset($body['status'])) {
            $status = UserStatus::tryFrom((string) $body['status']);
            if ($status === null) {
                throw new ValidationException([new ValidationError('status', 'A valid status is required.', 'invalid')]);
            }
        }

        $user = $this->useCase->execute(new UpdateUserInput(
            id: $id,
            callerOrganizationId: AuthContext::organizationId($request),
            role: $role,
            status: $status,
            actorUserId: AuthContext::userId($request),
        ));

        return $this->response->create(UserResponse::toArray($user));
    }
}
