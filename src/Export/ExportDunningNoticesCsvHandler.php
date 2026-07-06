<?php

declare(strict_types=1);

namespace NeneClear\Export;

use Nene2\Export\CsvWriter;
use NeneClear\Auth\AuthContext;
use NeneClear\Dunning\DunningNoticeFilter;
use NeneClear\Dunning\DunningNoticeRepositoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class ExportDunningNoticesCsvHandler
{
    private const int BATCH = 1000;

    public function __construct(
        private DunningNoticeRepositoryInterface $notices,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = AuthContext::organizationId($request) ?? 0;

        $filter = DunningNoticeFilter::fromQueryParams($request->getQueryParams());
        $rows = [];
        $offset = 0;
        do {
            $batch = $this->notices->findByOrganization($organizationId, $filter, self::BATCH, $offset);
            foreach ($batch as $notice) {
                $rows[] = [
                    $notice->id ?? '',
                    $notice->invoiceId,
                    $notice->invoiceNumber,
                    $notice->clientId,
                    $notice->recipientEmail,
                    $notice->outstandingCents,
                    $notice->dueAt,
                    $notice->channel,
                    $notice->templateVersion,
                    $notice->sentBy,
                    $notice->sentAt,
                ];
            }
            $offset += self::BATCH;
        } while (count($batch) === self::BATCH);

        return $this->csvResponse(
            $this->render(
                ['dunning_notice_id', 'invoice_id', 'invoice_number', 'client_id', 'recipient_email',
                    'outstanding_at_send_cents', 'due_at', 'channel', 'template_version', 'sent_by', 'sent_at'],
                $rows,
            ),
            'dunning-notices.csv',
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
