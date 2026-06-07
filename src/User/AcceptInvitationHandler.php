<?php

declare(strict_types=1);

namespace NeneClear\User;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Public endpoint: consumes an invitation token and sets the invitee's
 * password, activating the account. The caller then logs in normally.
 */
final readonly class AcceptInvitationHandler
{
    private const int MIN_PASSWORD_LENGTH = 8;

    public function __construct(
        private AcceptInvitationUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = JsonRequestBodyParser::parse($request);

        $token = is_string($body['token'] ?? null) ? (string) $body['token'] : '';
        $password = is_string($body['password'] ?? null) ? (string) $body['password'] : '';

        $errors = [];
        if ($token === '') {
            $errors[] = new ValidationError('token', 'A token is required.', 'required');
        }
        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors[] = new ValidationError('password', 'Password must be at least 8 characters.', 'too_short');
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $user = $this->useCase->execute(new AcceptInvitationInput($token, $password));

        return $this->response->create(UserResponse::toArray($user));
    }
}
