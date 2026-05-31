<?php

declare(strict_types=1);

namespace NeneClear\Export;

use NeneClear\Auth\AuthContext;
use NeneClear\BankImport\BankTransactionRepositoryInterface;
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

        $rows = [];
        $offset = 0;
        do {
            $batch = $this->reconciliations->findByOrganization($organizationId, null, self::BATCH, $offset);
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
                        ];
                    }
                }
            }
            $offset += self::BATCH;
        } while (count($batch) === self::BATCH);

        $csv = $this->buildCsv(
            ['reconciliation_id', 'allocation_id', 'status', 'invoice_id', 'amount_cents',
                'external_reference', 'bank_transaction_id', 'value_date', 'bank_amount_cents',
                'counterparty_text', 'confirmed_at', 'confirmed_by', 'reversed_at', 'reversal_reason'],
            $rows,
        );

        return $this->csvResponse($csv, 'reconciliations.csv');
    }

    /**
     * @param list<string> $headers
     * @param list<list<mixed>> $rows
     */
    private function buildCsv(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        assert($handle !== false);
        // UTF-8 BOM for Excel compatibility
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
