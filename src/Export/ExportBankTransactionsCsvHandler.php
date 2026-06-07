<?php

declare(strict_types=1);

namespace NeneClear\Export;

use NeneClear\Auth\AuthContext;
use NeneClear\BankImport\BankTransactionFilter;
use NeneClear\BankImport\BankTransactionRepositoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class ExportBankTransactionsCsvHandler
{
    private const int BATCH = 1000;

    public function __construct(
        private BankTransactionRepositoryInterface $transactions,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = AuthContext::organizationId($request) ?? 0;
        $filter = BankTransactionFilter::fromQueryParams($request->getQueryParams());

        $rows = [];
        $offset = 0;
        do {
            $batch = $this->transactions->findByOrganization($organizationId, $filter, self::BATCH, $offset);
            foreach ($batch as $tx) {
                $rows[] = [
                    $tx->id ?? '',
                    $tx->bankImportBatchId,
                    $tx->valueDate,
                    $tx->amountCents,
                    $tx->counterpartyText,
                    $tx->status->value,
                    $tx->lineKey,
                ];
            }
            $offset += self::BATCH;
        } while (count($batch) === self::BATCH);

        $csv = $this->buildCsv(
            ['bank_transaction_id', 'bank_import_batch_id', 'value_date', 'amount_cents',
                'counterparty_text', 'status', 'line_key'],
            $rows,
        );

        return $this->csvResponse($csv, 'bank-transactions.csv');
    }

    /**
     * @param list<string> $headers
     * @param list<list<mixed>> $rows
     */
    private function buildCsv(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        assert($handle !== false);
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, array_map(static fn ($v): string => (string) $v, $row));
        }
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
