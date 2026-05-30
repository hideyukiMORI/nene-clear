<?php

declare(strict_types=1);

namespace NeneClear\InvoiceUpstream;

interface InvoiceUpstreamClientInterface
{
    /**
     * @param list<string> $statuses e.g. ['issued', 'partially_paid']
     * @return list<InvoiceItem>
     */
    public function listInvoices(int $organizationId, array $statuses): array;

    public function getInvoice(int $organizationId, int $invoiceId): InvoiceItem;

    public function createPayment(
        int $organizationId,
        int $invoiceId,
        int $amountCents,
        string $paidAt,
        string $externalReference,
        string $idempotencyKey,
    ): InvoicePaymentCreated;

    public function voidPayment(
        int $organizationId,
        int $invoiceId,
        int $paymentId,
        string $reason,
        string $idempotencyKey,
    ): void;

    public function getClient(int $organizationId, int $clientId): InvoiceClientInfo;
}
