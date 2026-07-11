<?php

declare(strict_types=1);

namespace NeneClear\ClearSettings;

use Nene2\Http\JsonResponseFactory;
use NeneClear\InvoiceUpstream\InvoiceUpstreamClientInterface;
use NeneClear\InvoiceUpstream\UpstreamInvoiceUnavailableException;
use NeneClear\Tenancy\CurrentOrganization;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class TestUpstreamConnectionHandler
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

        try {
            $this->invoiceClient->listInvoices($organizationId, ['issued']);

            return $this->response->create(['reachable' => true]);
        } catch (UpstreamInvoiceUnavailableException $e) {
            return $this->response->create(['reachable' => false, 'detail' => $e->getMessage()]);
        }
    }
}
