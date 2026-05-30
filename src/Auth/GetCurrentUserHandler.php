<?php

declare(strict_types=1);

namespace NeneClear\Auth;

use NeneClear\I18n\LocalizedProblemDetailsFactory;
use Nene2\Http\JsonResponseFactory;
use NeneClear\User\UserRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class GetCurrentUserHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private JsonResponseFactory $response,
        private LocalizedProblemDetailsFactory $problemDetails,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $claims = (array) $request->getAttribute('nene2.auth.claims', []);
        $sub = $claims['sub'] ?? null;
        $userId = is_int($sub) ? $sub : (is_numeric($sub) ? (int) $sub : 0);

        $user = $userId > 0 ? $this->users->findById($userId) : null;

        if ($user === null) {
            return $this->problemDetails->create($request, 'unauthorized', 401);
        }

        return $this->response->create([
            'user_id' => $user->id,
            'organization_id' => $user->organizationId,
            'email' => $user->email,
            'role' => $user->role->value,
            'status' => $user->status->value,
        ]);
    }
}
