<?php

declare(strict_types=1);

namespace NeneClear\Auth;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** POST /admin/auth/login/mfa — completes a login challenged for a second factor. */
final readonly class VerifyMfaLoginHandler
{
    public function __construct(
        private VerifyMfaLoginUseCase $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = JsonRequestBodyParser::parse($request);

        $errors = [];
        $mfaToken = trim((string) ($body['mfa_token'] ?? ''));
        $code = trim((string) ($body['code'] ?? ''));

        if ($mfaToken === '') {
            $errors[] = new ValidationError('mfa_token', 'An MFA token is required.', 'required');
        }
        if ($code === '') {
            $errors[] = new ValidationError('code', 'A verification or recovery code is required.', 'required');
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $output = $this->useCase->execute($mfaToken, $code);

        return $this->response->create([
            'token' => $output->token,
            'user' => [
                'user_id' => $output->userId,
                'email' => $output->email,
                'role' => $output->role,
                'organization_id' => $output->organizationId,
            ],
        ]);
    }
}
