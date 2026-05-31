<?php

declare(strict_types=1);

namespace NeneClear\Export;

use NeneClear\Auth\AuthContext;
use NeneClear\Reconciliation\ClientCreditRepositoryInterface;
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
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = AuthContext::organizationId($request) ?? 0;

        $rows = [];
        $offset = 0;
        do {
            $batch = $this->credits->findByOrganization($organizationId, self::BATCH, $offset);
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

        $csv = $this->buildCsv(
            ['client_credit_id', 'client_id', 'status', 'amount_cents', 'remaining_cents',
                'source_bank_transaction_id', 'reconciliation_id', 'created_at', 'created_by'],
            $rows,
        );

        return $this->csvResponse($csv, 'client-credits.csv');
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
