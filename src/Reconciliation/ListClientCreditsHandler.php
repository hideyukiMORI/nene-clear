<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use Nene2\Http\PaginationResponse;
use NeneClear\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListClientCreditsHandler
{
    public function __construct(
        private ClientCreditRepositoryInterface $clientCredits,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = AuthContext::organizationId($request) ?? 0;
        $page = PaginationQueryParser::parse($request, 50, 200);
        $q = $request->getQueryParams();

        $int = static fn (string $key): ?int => isset($q[$key]) && is_numeric($q[$key]) ? (int) $q[$key] : null;
        $str = static fn (string $key): ?string => isset($q[$key]) && is_string($q[$key]) && $q[$key] !== '' ? $q[$key] : null;

        $statusParam = $q['status'] ?? null;
        $status = is_string($statusParam) ? ClientCreditStatus::tryFrom($statusParam) : null;

        $filter = new ClientCreditFilter(
            clientId: $int('client_id'),
            status: $status,
            amountMinCents: $int('amount_min_cents'),
            amountMaxCents: $int('amount_max_cents'),
            remainingMinCents: $int('remaining_min_cents'),
            remainingMaxCents: $int('remaining_max_cents'),
            createdFrom: $str('created_from'),
            createdTo: $str('created_to'),
            sortColumn: $str('sort_by') ?? 'id',
            sortDirection: $str('sort_dir') ?? 'desc',
        );

        return $this->response->create((new PaginationResponse(
            items: array_map(
                ClientCreditResponse::toArray(...),
                $this->clientCredits->findByOrganization($organizationId, $filter, $page->limit, $page->offset),
            ),
            limit: $page->limit,
            offset: $page->offset,
            total: $this->clientCredits->countByOrganization($organizationId, $filter),
        ))->toArray());
    }
}
