<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Nene2\Http\JsonResponseFactory;
use NeneClear\Auth\AuthContext;
use NeneClear\Tenancy\CurrentOrganization;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class TestSendDunningHandler
{
    public function __construct(
        private SendTestDunningUseCase $useCase,
        private JsonResponseFactory $response,
        private CurrentOrganization $organization,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);

        $sentTo = $this->useCase->execute(new SendTestDunningInput(
            organizationId: $this->organization->id(),
            invoiceId: (int) ($body['invoice_id'] ?? 0),
            to: is_string($body['to'] ?? null) ? $body['to'] : '',
            actorUserId: AuthContext::userId($request),
            stage: DunningStage::fromString(is_string($body['stage'] ?? null) ? $body['stage'] : null),
        ));

        return $this->response->create(['sent_to' => $sentTo]);
    }
}
