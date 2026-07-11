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

final readonly class PauseDunningHandler
{
    public function __construct(
        private PauseDunningUseCase $useCase,
        private JsonResponseFactory $response,
        private CurrentOrganization $organization,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = $this->organization->id();
        $actorUserId = AuthContext::userId($request);
        $body = (array) $request->getParsedBody();

        $invoiceIdRaw = $body['invoice_id'] ?? null;
        $invoiceId = is_numeric($invoiceIdRaw) ? (int) $invoiceIdRaw : 0;
        if ($invoiceId <= 0) {
            throw new ValidationException([new ValidationError('invoice_id', 'invoice_id must be a positive integer.', 'invalid')]);
        }

        $reason = is_string($body['reason'] ?? null) ? trim($body['reason']) : '';
        if ($reason === '') {
            throw new ValidationException([new ValidationError('reason', 'reason is required.', 'required')]);
        }

        $pause = $this->useCase->execute($organizationId, $invoiceId, $actorUserId, $reason);

        return $this->response->create($this->toArray($pause), 201);
    }

    /** @return array<string, mixed> */
    private function toArray(DunningPause $pause): array
    {
        return [
            'dunning_pause_id' => $pause->id,
            'invoice_id' => $pause->invoiceId,
            'paused_by' => $pause->pausedBy,
            'paused_at' => $pause->pausedAt,
            'paused_reason' => $pause->pausedReason,
        ];
    }
}
