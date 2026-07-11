<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use Nene2\Routing\Router;
use NeneClear\Auth\AuthContext;
use NeneClear\Tenancy\CurrentOrganization;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class UpdateManualReceivableHandler
{
    public function __construct(
        private UpdateManualReceivableUseCaseInterface $useCase,
        private JsonResponseFactory $response,
        private CurrentOrganization $organization,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getAttribute(Router::PARAMETERS_ATTRIBUTE, []);
        $id = (int) ($params['id'] ?? 0);
        $fields = ManualReceivableValidator::validate(JsonRequestBodyParser::parse($request));

        $receivable = $this->useCase->execute(new UpdateManualReceivableInput(
            id: $id,
            callerOrganizationId: $this->organization->id(),
            referenceNumber: $fields['reference_number'],
            clientName: $fields['client_name'],
            recipientEmail: $fields['recipient_email'],
            totalCents: $fields['total_cents'],
            currency: $fields['currency'],
            issuedAt: $fields['issued_at'],
            dueAt: $fields['due_at'],
            actorUserId: AuthContext::userId($request),
        ));

        return $this->response->create(ManualReceivableResponse::toArray($receivable));
    }
}
