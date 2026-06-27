<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Nene2\Http\JsonResponseFactory;
use NeneClear\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class TestSendDunningHandler
{
    public function __construct(
        private SendTestDunningUseCase $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);

        $sentTo = $this->useCase->execute(new SendTestDunningInput(
            organizationId: AuthContext::organizationId($request) ?? 0,
            invoiceId: (int) ($body['invoice_id'] ?? 0),
            to: is_string($body['to'] ?? null) ? $body['to'] : '',
            actorUserId: AuthContext::userId($request),
        ));

        return $this->response->create(['sent_to' => $sentTo]);
    }
}
