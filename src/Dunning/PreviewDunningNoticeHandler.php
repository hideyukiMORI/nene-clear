<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Nene2\Http\JsonResponseFactory;
use NeneClear\Auth\AuthContext;
use NeneClear\InvoiceUpstream\InvoiceUpstreamClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Renders the dunning email that *would* be sent for an invoice — without
 * recording a notice or sending anything — so the operator can review the exact
 * subject + body before confirming (#194). Read-only (view_reconciliation).
 */
final readonly class PreviewDunningNoticeHandler
{
    public function __construct(
        private InvoiceUpstreamClientInterface $invoiceClient,
        private DunningMessageRenderer $renderer,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = AuthContext::organizationId($request) ?? 0;
        $query = $request->getQueryParams();
        $invoiceId = (int) ($query['invoice_id'] ?? 0);
        $stage = DunningStage::fromString(is_string($query['stage'] ?? null) ? $query['stage'] : null);

        $invoice = $this->invoiceClient->getInvoice($organizationId, $invoiceId);
        $client = $this->invoiceClient->getClient($organizationId, $invoice->clientId);

        return $this->response->create([
            'invoice_number' => $invoice->invoiceNumber,
            'recipient_email' => $client->recipientEmail,
            'stage' => $stage->value,
            'subject' => $this->renderer->subject($stage, $invoice->invoiceNumber),
            'body' => $this->renderer->body($stage, $client->contactName, $invoice->invoiceNumber, $invoice->dueAt, $invoice->outstandingCents),
            'template_version' => DunningMessageRenderer::TEMPLATE_VERSION,
        ]);
    }
}
