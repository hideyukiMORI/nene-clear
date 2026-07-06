<?php

declare(strict_types=1);

namespace NeneClear\Export;

use Nene2\Export\CsvWriter;
use NeneClear\Auth\AuthContext;
use NeneClear\BankImport\BankTransactionRepositoryInterface;
use NeneClear\Reconciliation\ReconciliationFilter;
use NeneClear\Reconciliation\ReconciliationRepositoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class ExportReconciliationsCsvHandler
{
    private const int BATCH = 1000;

    public function __construct(
        private ReconciliationRepositoryInterface $reconciliations,
        private BankTransactionRepositoryInterface $transactions,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = AuthContext::organizationId($request) ?? 0;
        $filter = ReconciliationFilter::fromQueryParams($request->getQueryParams());

        $rows = [];
        $offset = 0;
        do {
            $batch = $this->reconciliations->findByOrganization($organizationId, $filter, self::BATCH, $offset);
            foreach ($batch as $recon) {
                $tx = $this->transactions->findById($organizationId, $recon->bankTransactionId);

                $allocations = $this->reconciliations->findAllocationsByReconciliation($organizationId, $recon->id ?? 0);
                if (empty($allocations)) {
                    $rows[] = [
                        $recon->id ?? '',
                        '',
                        $recon->status->value,
                        '',
                        '',
                        '',
                        $recon->bankTransactionId,
                        $tx !== null ? $tx->valueDate : '',
                        $tx !== null ? $tx->amountCents : '',
                        $tx !== null ? $tx->counterpartyText : '',
                        $recon->confirmedAt,
                        $recon->confirmedBy,
                        $recon->reversedAt ?? '',
                        $recon->reversalReason ?? '',
                        '',
                        '',
                    ];
                } else {
                    foreach ($allocations as $alloc) {
                        $rows[] = [
                            $recon->id ?? '',
                            $alloc->id ?? '',
                            $recon->status->value,
                            $alloc->invoiceId,
                            $alloc->amountCents,
                            $alloc->externalReference ?? '',
                            $recon->bankTransactionId,
                            $tx !== null ? $tx->valueDate : '',
                            $tx !== null ? $tx->amountCents : '',
                            $tx !== null ? $tx->counterpartyText : '',
                            $recon->confirmedAt,
                            $recon->confirmedBy,
                            $recon->reversedAt ?? '',
                            $recon->reversalReason ?? '',
                            $alloc->source->value,
                            $alloc->manualReceivableId ?? '',
                        ];
                    }
                }
            }
            $offset += self::BATCH;
        } while (count($batch) === self::BATCH);

        return $this->csvResponse(
            $this->render(
                ['reconciliation_id', 'allocation_id', 'status', 'invoice_id', 'amount_cents',
                    'external_reference', 'bank_transaction_id', 'value_date', 'bank_amount_cents',
                    'counterparty_text', 'confirmed_at', 'confirmed_by', 'reversed_at', 'reversal_reason',
                    'source', 'manual_receivable_id'],
                $rows,
            ),
            'reconciliations.csv',
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
