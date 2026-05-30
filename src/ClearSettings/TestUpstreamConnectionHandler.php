<?php

declare(strict_types=1);

namespace NeneClear\ClearSettings;

use Nene2\Http\JsonResponseFactory;
use NeneClear\Auth\AuthContext;
use NeneClear\InvoiceUpstream\InvoiceUpstreamClientInterface;
use NeneClear\InvoiceUpstream\UpstreamInvoiceUnavailableException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class TestUpstreamConnectionHandler
{
    public function __construct(
        private InvoiceUpstreamClientInterface $invoiceClient,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = AuthContext::organizationId($request) ?? 0;

        try {
            $this->invoiceClient->listInvoices($organizationId, ['issued']);

            return $this->response->create(['reachable' => true]);
        } catch (UpstreamInvoiceUnavailableException $e) {
            return $this->response->create(['reachable' => false, 'detail' => $e->getMessage()]);
        }
    }
}
