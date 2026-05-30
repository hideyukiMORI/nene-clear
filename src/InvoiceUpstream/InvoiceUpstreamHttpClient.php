<?php

declare(strict_types=1);

namespace NeneClear\InvoiceUpstream;

use NeneClear\Reconciliation\AllocationExceedsOutstandingException;

/**
 * Real HTTP implementation of InvoiceUpstreamClientInterface.
 * Targets the binding contract in docs/integrations/invoice-upstream-contract.md.
 *
 * Use when NENE_INVOICE_API_BASE_URL + NENE_INVOICE_BEARER_TOKEN are set.
 * Falls back to FakeInvoiceUpstreamClient when env vars are absent.
 */
final readonly class InvoiceUpstreamHttpClient implements InvoiceUpstreamClientInterface
{
    private const int CONNECT_TIMEOUT = 5;
    private const int READ_TIMEOUT = 10;

    public function __construct(
        private string $baseUrl,
        private string $bearerToken,
    ) {
    }

    public function listInvoices(int $organizationId, array $statuses): array
    {
        $query = http_build_query(['status' => $statuses, 'limit' => 200, 'offset' => 0]);
        $body = $this->request('GET', '/api/invoices?' . $query);

        return array_values(array_map(
            static fn (array $item): InvoiceItem => new InvoiceItem(
                invoiceId: (int) $item['invoice_id'],
                invoiceNumber: (string) $item['invoice_number'],
                clientId: (int) $item['client_id'],
                outstandingCents: (int) $item['outstanding_cents'],
                totalCents: (int) $item['total_cents'],
                dueAt: (string) $item['due_at'],
                status: (string) $item['status'],
                currency: (string) ($item['currency'] ?? 'JPY'),
            ),
            (array) ($body['items'] ?? []),
        ));
    }

    public function getInvoice(int $organizationId, int $invoiceId): InvoiceItem
    {
        $body = $this->request('GET', '/api/invoices/' . $invoiceId, resourceId: $invoiceId);

        return new InvoiceItem(
            invoiceId: (int) $body['invoice_id'],
            invoiceNumber: (string) $body['invoice_number'],
            clientId: (int) $body['client_id'],
            outstandingCents: (int) $body['outstanding_cents'],
            totalCents: (int) $body['total_cents'],
            dueAt: (string) $body['due_at'],
            status: (string) $body['status'],
            currency: (string) ($body['currency'] ?? 'JPY'),
        );
    }

    public function createPayment(
        int $organizationId,
        int $invoiceId,
        int $amountCents,
        string $paidAt,
        string $externalReference,
        string $idempotencyKey,
    ): InvoicePaymentCreated {
        $body = $this->request(
            'POST',
            '/api/invoices/' . $invoiceId . '/payments',
            [
                'amount_cents' => $amountCents,
                'paid_at' => $paidAt,
                'method' => 'bank_transfer',
                'external_reference' => $externalReference,
                'idempotency_key' => $idempotencyKey,
            ],
            idempotencyKey: $idempotencyKey,
            resourceId: $invoiceId,
        );

        return new InvoicePaymentCreated(
            paymentId: (int) $body['payment_id'],
            invoiceId: $invoiceId,
            amountCents: (int) $body['amount_cents'],
            paidAt: (string) $body['paid_at'],
            externalReference: (string) $body['external_reference'],
        );
    }

    public function voidPayment(
        int $organizationId,
        int $invoiceId,
        int $paymentId,
        string $reason,
        string $idempotencyKey,
    ): void {
        $this->request(
            'POST',
            '/api/invoices/' . $invoiceId . '/payments/' . $paymentId . '/void',
            ['reason' => $reason, 'idempotency_key' => $idempotencyKey],
            idempotencyKey: $idempotencyKey,
        );
    }

    public function getClient(int $organizationId, int $clientId): InvoiceClientInfo
    {
        $body = $this->request('GET', '/api/clients/' . $clientId, resourceId: $clientId);

        return new InvoiceClientInfo(
            clientId: (int) $body['client_id'],
            contactName: (string) $body['contact_name'],
            recipientEmail: (string) $body['recipient_email'],
        );
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    private function request(
        string $method,
        string $path,
        ?array $payload = null,
        string $idempotencyKey = '',
        int $resourceId = 0,
    ): array {
        $ch = \curl_init(rtrim($this->baseUrl, '/') . $path);

        if ($ch === false) {
            throw new UpstreamInvoiceUnavailableException('Failed to initialise cURL handle.');
        }

        // Build as a clean list<string> (no conditional appends that infer
        // optional offsets, which some curl stubs reject for CURLOPT_HTTPHEADER).
        $headers = array_values(array_filter([
            'Authorization: Bearer ' . $this->bearerToken,
            'Accept: application/json',
            $payload !== null ? 'Content-Type: application/json' : null,
            $idempotencyKey !== '' ? 'Idempotency-Key: ' . $idempotencyKey : null,
        ], static fn (?string $h): bool => $h !== null));

        \curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::READ_TIMEOUT,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        if ($payload !== null) {
            \curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
        }

        $raw = \curl_exec($ch);
        $curlError = \curl_error($ch);
        $statusCode = (int) \curl_getinfo($ch, CURLINFO_HTTP_CODE);
        \curl_close($ch);

        if ($raw === false || $curlError !== '') {
            throw new UpstreamInvoiceUnavailableException('Invoice API unreachable: ' . $curlError);
        }

        $rawStr = (string) $raw;

        if ($statusCode === 0) {
            throw new UpstreamInvoiceUnavailableException('Invoice API did not respond.');
        }

        if ($statusCode >= 500) {
            throw new UpstreamInvoiceUnavailableException(sprintf('Invoice API returned %d.', $statusCode));
        }

        $body = json_decode($rawStr, true);
        $body = is_array($body) ? $body : [];

        if ($statusCode === 404) {
            $type = (string) ($body['type'] ?? '');
            if (str_ends_with($type, 'client-not-found')) {
                throw new UpstreamClientNotFoundException($resourceId);
            }
            throw new UpstreamInvoiceNotFoundException($resourceId);
        }

        if ($statusCode === 422) {
            $type = (string) ($body['type'] ?? '');
            if (str_contains($type, 'payment-exceeds-outstanding')) {
                throw new AllocationExceedsOutstandingException(
                    $resourceId,
                    (int) ($body['outstanding_cents'] ?? 0),
                );
            }

            // Intentionally not forwarding $body['detail'] — upstream error internals must not leak to callers.
            throw new UpstreamInvoiceUnavailableException(sprintf('Invoice API returned 422 with unexpected type: %s', $type));
        }

        if ($statusCode >= 400) {
            throw new UpstreamInvoiceUnavailableException(sprintf('Invoice API returned %d.', $statusCode));
        }

        return $body;
    }
}
