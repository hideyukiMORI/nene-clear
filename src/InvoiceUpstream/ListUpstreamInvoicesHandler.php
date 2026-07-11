<?php

declare(strict_types=1);

namespace NeneClear\InvoiceUpstream;

use Nene2\Http\JsonResponseFactory;
use NeneClear\Tenancy\CurrentOrganization;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListUpstreamInvoicesHandler
{
    public function __construct(
        private InvoiceUpstreamClientInterface $invoiceClient,
        private JsonResponseFactory $response,
        private CurrentOrganization $organization,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = $this->organization->id();
        $q = (array) $request->getQueryParams();

        $statusParam = $q['status'] ?? null;
        if (is_string($statusParam) && $statusParam !== '') {
            $statuses = explode(',', $statusParam);
        } else {
            $statuses = ['issued', 'partially_paid', 'overdue'];
        }

        $invoices = $this->invoiceClient->listInvoices($organizationId, $statuses);

        $items = array_map(static fn (InvoiceItem $i): array => [
            'invoice_id' => $i->invoiceId,
            'invoice_number' => $i->invoiceNumber,
            'client_id' => $i->clientId,
            'outstanding_cents' => $i->outstandingCents,
            'total_cents' => $i->totalCents,
            'due_at' => $i->dueAt,
            'status' => $i->status,
            'currency' => $i->currency,
        ], $invoices);

        return $this->response->create(['items' => $items, 'total' => count($items)]);
    }
}
