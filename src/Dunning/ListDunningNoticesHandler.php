<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use Nene2\Http\PaginationResponse;
use NeneClear\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListDunningNoticesHandler
{
    public function __construct(
        private DunningNoticeRepositoryInterface $notices,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = AuthContext::organizationId($request) ?? 0;
        $page = PaginationQueryParser::parse($request, 50, 200);
        $q = $request->getQueryParams();
        $str = static fn (string $k): ?string => isset($q[$k]) && is_string($q[$k]) && $q[$k] !== '' ? $q[$k] : null;
        $int = static fn (string $k): ?int => isset($q[$k]) && is_numeric($q[$k]) ? (int) $q[$k] : null;

        $filter = new DunningNoticeFilter(
            invoiceNumber: $str('invoice_number'),
            recipientEmail: $str('recipient_email'),
            outstandingMinCents: $int('outstanding_min_cents'),
            outstandingMaxCents: $int('outstanding_max_cents'),
            sentFrom: $str('sent_from'),
            sentTo: $str('sent_to'),
            sentBy: $int('sent_by'),
            sortColumn: $str('sort_by') ?? 'sent_at',
            sortDirection: $str('sort_dir') ?? 'desc',
        );

        return $this->response->create((new PaginationResponse(
            items: array_map(
                DunningNoticeResponse::toArray(...),
                $this->notices->findByOrganization($organizationId, $filter, $page->limit, $page->offset),
            ),
            limit: $page->limit,
            offset: $page->offset,
            total: $this->notices->countByOrganization($organizationId, $filter),
        ))->toArray());
    }
}
