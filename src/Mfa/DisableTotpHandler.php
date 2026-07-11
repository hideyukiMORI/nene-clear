<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneClear\Auth\AuthContext;
use NeneClear\Tenancy\CurrentOrganization;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class DisableTotpHandler
{
    public function __construct(
        private DisableTotpUseCase $useCase,
        private JsonResponseFactory $response,
        private CurrentOrganization $organization,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $code = is_string($body['code'] ?? null) ? trim($body['code']) : '';
        if ($code === '') {
            throw new ValidationException([new ValidationError('code', 'A verification or recovery code is required.', 'required')]);
        }

        $this->useCase->execute(
            AuthContext::userId($request),
            $this->organization->id(),
            $code,
        );

        return $this->response->create(['disabled' => true]);
    }
}
