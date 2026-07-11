<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

use Nene2\Http\JsonRequestBodyParser;
use Nene2\Http\JsonResponseFactory;
use NeneClear\Auth\AuthContext;
use NeneClear\Tenancy\CurrentOrganization;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class CreateManualReceivableHandler
{
    public function __construct(
        private CreateManualReceivableUseCaseInterface $useCase,
        private JsonResponseFactory $response,
        private CurrentOrganization $organization,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $fields = ManualReceivableValidator::validate(JsonRequestBodyParser::parse($request));

        $receivable = $this->useCase->execute(new CreateManualReceivableInput(
            organizationId: $this->organization->id(),
            referenceNumber: $fields['reference_number'],
            clientName: $fields['client_name'],
            recipientEmail: $fields['recipient_email'],
            totalCents: $fields['total_cents'],
            currency: $fields['currency'],
            issuedAt: $fields['issued_at'],
            dueAt: $fields['due_at'],
            actorUserId: AuthContext::userId($request),
        ));

        return $this->response->create(
            ManualReceivableResponse::toArray($receivable),
            201,
            ['Location' => '/admin/manual-receivables/' . $receivable->id],
        );
    }
}
