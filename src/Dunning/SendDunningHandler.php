<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Nene2\Http\JsonResponseFactory;
use Nene2\Validation\ValidationError;
use Nene2\Validation\ValidationException;
use NeneClear\Auth\AuthContext;
use NeneClear\Tenancy\CurrentOrganization;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class SendDunningHandler
{
    public function __construct(
        private SendDunningUseCaseInterface $useCase,
        private DunningNoticeRepositoryInterface $notices,
        private JsonResponseFactory $response,
        private CurrentOrganization $organization,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $invoiceId = is_numeric($body['invoice_id'] ?? null) ? (int) $body['invoice_id'] : 0;

        if ($invoiceId <= 0) {
            throw new ValidationException([new ValidationError('invoice_id', 'A valid invoice id is required.', 'required')]);
        }

        $organizationId = $this->organization->id();
        $output = $this->useCase->execute(new SendDunningInput(
            organizationId: $organizationId,
            invoiceId: $invoiceId,
            actorUserId: AuthContext::userId($request),
            stage: DunningStage::fromString(is_string($body['stage'] ?? null) ? $body['stage'] : null),
        ));

        $notice = $this->notices->findById($organizationId, $output->dunningNoticeId)
            ?? throw new DunningNoticeNotFoundException($output->dunningNoticeId);

        return $this->response->create(DunningNoticeResponse::toArray($notice), 201);
    }
}
