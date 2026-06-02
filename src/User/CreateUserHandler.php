<?php

declare(strict_types=1);

namespace NeneClear\User;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneClear\Auth\AuthContext;
use NeneClear\Auth\Role;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class CreateUserHandler
{
    public function __construct(
        private CreateUserUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = JsonRequestBodyParser::parse($request);

        $errors = [];
        $email = trim((string) ($body['email'] ?? ''));
        $role = Role::tryFrom((string) ($body['role'] ?? ''));

        if ($email === '') {
            $errors[] = new ValidationError('email', 'Email is required.', 'required');
        }

        if ($role === null) {
            $errors[] = new ValidationError('role', 'A valid role is required.', 'invalid');
        }

        if ($errors !== [] || $role === null) {
            throw new ValidationException($errors);
        }

        $rawPassword = $body['password'] ?? null;
        $password = is_string($rawPassword) && $rawPassword !== '' ? $rawPassword : null;

        $user = $this->useCase->execute(new CreateUserInput(
            organizationId: AuthContext::organizationId($request),
            email: $email,
            role: $role,
            password: $password,
            actorUserId: AuthContext::userId($request),
        ));

        return $this->response->create(UserResponse::toArray($user), 201, ['Location' => '/admin/users/' . $user->id]);
    }
}
