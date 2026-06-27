<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

use Nene2\Http\JsonResponseFactory;
use NeneClear\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class SetupTotpHandler
{
    public function __construct(
        private SetupTotpUseCase $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->response->create($this->useCase->execute(AuthContext::userId($request)));
    }
}
