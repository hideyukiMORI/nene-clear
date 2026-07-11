<?php

declare(strict_types=1);

namespace NeneClear\Export;

use Nene2\Export\CsvWriter;
use NeneClear\Reconciliation\ClientCreditFilter;
use NeneClear\Reconciliation\ClientCreditRepositoryInterface;
use NeneClear\Tenancy\CurrentOrganization;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class ExportClientCreditsCsvHandler
{
    private const int BATCH = 1000;

    public function __construct(
        private ClientCreditRepositoryInterface $credits,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private CurrentOrganization $organization,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = $this->organization->id();

        $filter = ClientCreditFilter::fromQueryParams($request->getQueryParams());
        $rows = [];
        $offset = 0;
        do {
            $batch = $this->credits->findByOrganization($organizationId, $filter, self::BATCH, $offset);
            foreach ($batch as $credit) {
                $rows[] = [
                    $credit->id ?? '',
                    $credit->clientId,
                    $credit->status->value,
                    $credit->amountCents,
                    $credit->remainingCents,
                    $credit->sourceBankTransactionId,
                    $credit->reconciliationId,
                    $credit->createdAt,
                    $credit->createdBy,
                ];
            }
            $offset += self::BATCH;
        } while (count($batch) === self::BATCH);

        return $this->csvResponse(
            $this->render(
                ['client_credit_id', 'client_id', 'status', 'amount_cents', 'remaining_cents',
                    'source_bank_transaction_id', 'reconciliation_id', 'created_at', 'created_by'],
                $rows,
            ),
            'client-credits.csv',
        );
    }

    /**
     * Renders rows to a CSV string via the framework writer, which neutralises
     * formula injection in string cells and prepends a UTF-8 BOM by default.
     *
     * @param list<string> $headers
     * @param iterable<list<string|int|float|bool|null>> $rows
     */
    private function render(array $headers, iterable $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        assert($handle !== false);
        (new CsvWriter($handle, $headers))->writeAll($rows);
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content !== false ? $content : '';
    }

    private function csvResponse(string $content, string $filename): ResponseInterface
    {
        return $this->responseFactory->createResponse(200)
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withBody($this->streamFactory->createStream($content));
    }
}
