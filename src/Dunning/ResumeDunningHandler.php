<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneClear\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ResumeDunningHandler
{
    public function __construct(
        private ResumeDunningUseCase $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = AuthContext::organizationId($request) ?? 0;
        $actorUserId = AuthContext::userId($request);
        $params = (array) $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $invoiceId = (int) ($params['invoiceId'] ?? 0);

        if ($invoiceId <= 0) {
            throw new ValidationException([new ValidationError('invoiceId', 'invoiceId must be a positive integer.', 'invalid')]);
        }

        $this->useCase->execute($organizationId, $invoiceId, $actorUserId);

        return $this->response->create([], 204);
    }
}
