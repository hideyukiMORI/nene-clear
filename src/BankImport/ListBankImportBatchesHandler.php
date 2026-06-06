<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use Nene2\Http\PaginationResponse;
use NeneClear\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListBankImportBatchesHandler
{
    public function __construct(
        private BankImportBatchRepositoryInterface $batches,
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

        $statusParam = $q['status'] ?? null;
        $status = is_string($statusParam) ? BankImportBatchStatus::tryFrom($statusParam) : null;

        $filter = new BankImportBatchFilter(
            sourceFilename: $str('source_filename'),
            status: $status,
            rowCountMin: $int('row_count_min'),
            rowCountMax: $int('row_count_max'),
            importedFrom: $str('imported_from'),
            importedTo: $str('imported_to'),
            sortColumn: $str('sort_by') ?? 'id',
            sortDirection: $str('sort_dir') ?? 'desc',
        );

        return $this->response->create((new PaginationResponse(
            items: array_map(
                BankImportBatchResponse::toArray(...),
                $this->batches->findByOrganization($organizationId, $filter, $page->limit, $page->offset),
            ),
            limit: $page->limit,
            offset: $page->offset,
            total: $this->batches->countByOrganization($organizationId, $filter),
        ))->toArray());
    }
}
