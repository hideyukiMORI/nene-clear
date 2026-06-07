<?php

declare(strict_types=1);

namespace NeneClear\User;

use Nene2\Http\JsonResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Public endpoint: validates an invitation token (from the e-mail link) and
 * returns the invitee's e-mail so the accept-invite page can show who is being
 * onboarded. Invalid/expired tokens surface as Problem Details.
 */
final readonly class GetInvitationHandler
{
    public function __construct(
        private GetInvitationUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $token = isset($params['token']) && is_string($params['token']) ? $params['token'] : '';

        if ($token === '') {
            throw new InvitationInvalidException();
        }

        $email = $this->useCase->execute($token);

        return $this->response->create(['email' => $email]);
    }
}
