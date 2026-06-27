<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

use Nene2\Http\JsonResponseFactory;
use NeneClear\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class GetTotpStatusHandler
{
    public function __construct(
        private TotpAuthenticator $authenticator,
        private RecoveryCodeRepositoryInterface $recovery,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $userId = AuthContext::userId($request);
        $enabled = $this->authenticator->isEnabled($userId);

        return $this->response->create([
            'enabled' => $enabled,
            'recovery_codes_remaining' => $enabled ? $this->recovery->countUnused($userId) : 0,
        ]);
    }
}
