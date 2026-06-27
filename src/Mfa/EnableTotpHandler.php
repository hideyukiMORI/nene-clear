<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneClear\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class EnableTotpHandler
{
    public function __construct(
        private EnableTotpUseCase $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $code = self::code($request);
        $recoveryCodes = $this->useCase->execute(
            AuthContext::userId($request),
            AuthContext::organizationId($request) ?? 0,
            $code,
        );

        return $this->response->create(['recovery_codes' => $recoveryCodes]);
    }

    private static function code(ServerRequestInterface $request): string
    {
        $body = (array) $request->getParsedBody();
        $code = is_string($body['code'] ?? null) ? trim($body['code']) : '';
        if ($code === '') {
            throw new ValidationException([new ValidationError('code', 'A verification code is required.', 'required')]);
        }

        return $code;
    }
}
