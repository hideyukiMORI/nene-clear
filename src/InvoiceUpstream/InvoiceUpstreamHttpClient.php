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
    /** Invoice contract caps `limit` at 100; sending more is a 422. */
    private const int PAGE_LIMIT = 100;
    /** Safety bound so a misbehaving upstream can't loop forever (≤ 10k invoices). */
    private const int MAX_PAGES = 100;

    public function __construct(
        private string $baseUrl,
        private string $bearerToken,
    ) {
    }

    public function listInvoices(int $organizationId, array $statuses): array
    {
        // The Invoice contract caps `limit` at 100 (more → 422), so page through
        // with `offset` until a short page returns. Reconciliation needs the full
        // set of open invoices, not just the first page.
        $invoices = [];
        $offset = 0;

        for ($page = 0; $page < self::MAX_PAGES; ++$page) {
            // Invoice's read filter expects `status` as a comma-joined string
            // (status=issued,partially_paid), parsed via explode(','). A PHP array
            // (status[]=…) is not a string there, so the filter is silently dropped
            // and drafts/paid leak in — send the comma form.
            $params = ['limit' => self::PAGE_LIMIT, 'offset' => $offset];
            if ($statuses !== []) {
                $params['status'] = implode(',', $statuses);
            }
            $query = http_build_query($params);
            $body = $this->request('GET', '/api/invoices?' . $query);
            $items = (array) ($body['items'] ?? []);

            foreach ($items as $item) {
                /** @var array<string, mixed> $item */
                $invoices[] = $this->hydrateInvoice($item);
            }

            if (count($items) < self::PAGE_LIMIT) {
                break;
            }
            $offset += self::PAGE_LIMIT;
        }

        return $invoices;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function hydrateInvoice(array $item): InvoiceItem
    {
        return new InvoiceItem(
            invoiceId: (int) $item['invoice_id'],
            invoiceNumber: (string) $item['invoice_number'],
            clientId: (int) $item['client_id'],
            outstandingCents: (int) $item['outstanding_cents'],
            totalCents: (int) $item['total_cents'],
            dueAt: isset($item['due_at']) ? (string) $item['due_at'] : null,
            status: (string) $item['status'],
            currency: (string) ($item['currency'] ?? 'JPY'),
        );
    }

    public function getInvoice(int $organizationId, int $invoiceId): InvoiceItem
    {
        $body = $this->request('GET', '/api/invoices/' . $invoiceId, resourceId: $invoiceId);

        return $this->hydrateInvoice($body);
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

        // Invoice returns RecordPaymentResult: the payment is nested under
        // `payment` (alongside `invoice` + `total_paid_cents`), per service-api.yaml.
        $payment = $body['payment'] ?? [];
        if (!is_array($payment)) {
            $payment = [];
        }

        return new InvoicePaymentCreated(
            paymentId: (int) ($payment['payment_id'] ?? 0),
            invoiceId: $invoiceId,
            amountCents: (int) ($payment['amount_cents'] ?? 0),
            paidAt: (string) ($payment['paid_at'] ?? ''),
            externalReference: (string) ($payment['external_reference'] ?? ''),
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
     * Request headers as a clean list<string>. The explicit return type keeps
     * the shape simple so it satisfies the strict curl extension stub
     * (CURLOPT_HTTPHEADER expects array<int, string>) in every environment.
     *
     * @return list<string>
     */
    private function buildHeaders(bool $hasJsonBody, string $idempotencyKey): array
    {
        $headers = [
            'Authorization: Bearer ' . $this->bearerToken,
            'Accept: application/json',
        ];

        if ($hasJsonBody) {
            $headers[] = 'Content-Type: application/json';
        }

        if ($idempotencyKey !== '') {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }

        return $headers;
    }

    /**
     * @param non-empty-string           $method  HTTP verb (GET/POST/…)
     * @param array<string, mixed>|null  $payload
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

        // Individual curl_setopt calls rather than curl_setopt_array: the latter's
        // precise array-shape stub varies between environments (CI's ext-curl stub
        // rejects shapes the local one accepts). Per-option calls take mixed values.
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, CURLOPT_HTTPHEADER, $this->buildHeaders($payload !== null, $idempotencyKey));
        \curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);
        \curl_setopt($ch, CURLOPT_TIMEOUT, self::READ_TIMEOUT);
        \curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        \curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

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
