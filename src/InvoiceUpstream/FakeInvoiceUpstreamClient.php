<?php

declare(strict_types=1);

namespace NeneClear\InvoiceUpstream;

/**
 * In-memory implementation for tests. Add invoices with `addInvoice()` before
 * exercising use cases. Payments are tracked so `voidPayment` works correctly.
 */
final class FakeInvoiceUpstreamClient implements InvoiceUpstreamClientInterface
{
    /** @var array<int, InvoiceItem> keyed by invoiceId */
    private array $invoices = [];

    /** @var array<int, list<array{paymentId: int, amountCents: int, paidAt: string, externalReference: string, voided: bool}>> keyed by invoiceId */
    private array $payments = [];

    /** @var array<int, InvoiceClientInfo> keyed by clientId */
    private array $clients = [];

    private int $nextPaymentId = 1;

    private bool $unavailable = false;

    public function makeUnavailable(): void
    {
        $this->unavailable = true;
    }

    public function addClient(InvoiceClientInfo $client): void
    {
        $this->clients[$client->clientId] = $client;
    }

    public function addInvoice(InvoiceItem $invoice): void
    {
        $this->invoices[$invoice->invoiceId] = $invoice;
        $this->payments[$invoice->invoiceId] ??= [];
    }

    public function listInvoices(int $organizationId, array $statuses): array
    {
        if ($this->unavailable) {
            throw new UpstreamInvoiceUnavailableException();
        }

        return array_values(array_filter(
            $this->invoices,
            static fn (InvoiceItem $i): bool => in_array($i->status, $statuses, true),
        ));
    }

    public function getInvoice(int $organizationId, int $invoiceId): InvoiceItem
    {
        if ($this->unavailable) {
            throw new UpstreamInvoiceUnavailableException();
        }

        return $this->invoices[$invoiceId] ?? throw new UpstreamInvoiceNotFoundException($invoiceId);
    }

    public function createPayment(
        int $organizationId,
        int $invoiceId,
        int $amountCents,
        string $paidAt,
        string $externalReference,
        string $idempotencyKey,
    ): InvoicePaymentCreated {
        if ($this->unavailable) {
            throw new UpstreamInvoiceUnavailableException();
        }

        if (!isset($this->invoices[$invoiceId])) {
            throw new UpstreamInvoiceNotFoundException($invoiceId);
        }

        $paymentId = $this->nextPaymentId++;
        $this->payments[$invoiceId][] = [
            'paymentId' => $paymentId,
            'amountCents' => $amountCents,
            'paidAt' => $paidAt,
            'externalReference' => $externalReference,
            'voided' => false,
        ];

        $invoice = $this->invoices[$invoiceId];
        $this->invoices[$invoiceId] = new InvoiceItem(
            invoiceId: $invoice->invoiceId,
            invoiceNumber: $invoice->invoiceNumber,
            clientId: $invoice->clientId,
            outstandingCents: $invoice->outstandingCents - $amountCents,
            totalCents: $invoice->totalCents,
            dueAt: $invoice->dueAt,
            status: $invoice->outstandingCents - $amountCents <= 0 ? 'paid' : 'partially_paid',
            currency: $invoice->currency,
        );

        return new InvoicePaymentCreated(
            paymentId: $paymentId,
            invoiceId: $invoiceId,
            amountCents: $amountCents,
            paidAt: $paidAt,
            externalReference: $externalReference,
        );
    }

    public function voidPayment(
        int $organizationId,
        int $invoiceId,
        int $paymentId,
        string $reason,
        string $idempotencyKey,
    ): void {
        if ($this->unavailable) {
            throw new UpstreamInvoiceUnavailableException();
        }

        if (!isset($this->payments[$invoiceId])) {
            return;
        }

        foreach ($this->payments[$invoiceId] as $index => $payment) {
            if ($payment['paymentId'] === $paymentId && !$payment['voided']) {
                $this->payments[$invoiceId][$index]['voided'] = true;
                $invoice = $this->invoices[$invoiceId];
                $this->invoices[$invoiceId] = new InvoiceItem(
                    invoiceId: $invoice->invoiceId,
                    invoiceNumber: $invoice->invoiceNumber,
                    clientId: $invoice->clientId,
                    outstandingCents: $invoice->outstandingCents + $payment['amountCents'],
                    totalCents: $invoice->totalCents,
                    dueAt: $invoice->dueAt,
                    status: 'issued',
                    currency: $invoice->currency,
                );

                return;
            }
        }
    }

    public function getClient(int $organizationId, int $clientId): InvoiceClientInfo
    {
        if ($this->unavailable) {
            throw new UpstreamInvoiceUnavailableException();
        }

        return $this->clients[$clientId] ?? throw new UpstreamInvoiceNotFoundException($clientId);
    }

    /** @return list<array{paymentId: int, amountCents: int, paidAt: string, externalReference: string, voided: bool}> */
    public function paymentsFor(int $invoiceId): array
    {
        return $this->payments[$invoiceId] ?? [];
    }
}
