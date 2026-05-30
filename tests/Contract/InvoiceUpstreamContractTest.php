<?php

declare(strict_types=1);

namespace NeneClear\Tests\Contract;

use NeneClear\InvoiceUpstream\InvoiceUpstreamHttpClient;
use NeneClear\InvoiceUpstream\UpstreamClientNotFoundException;
use NeneClear\InvoiceUpstream\UpstreamInvoiceNotFoundException;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for the Invoice upstream API (docs/integrations/invoice-upstream-contract.md).
 *
 * These tests skip automatically when NENE_INVOICE_API_BASE_URL and
 * NENE_INVOICE_BEARER_TOKEN are not set, so CI passes without a running Invoice API.
 * Run manually against a live Invoice instance to verify the contract.
 *
 * Required env vars:
 *   NENE_INVOICE_API_BASE_URL   — e.g. http://localhost:8080
 *   NENE_INVOICE_BEARER_TOKEN   — service token scoped to the test org
 *   NENE_INVOICE_CONTRACT_ORG   — org id used for contract tests (default: 1)
 */
final class InvoiceUpstreamContractTest extends TestCase
{
    private InvoiceUpstreamHttpClient $client;
    private int $orgId;

    protected function setUp(): void
    {
        $baseUrl = getenv('NENE_INVOICE_API_BASE_URL') ?: '';
        $token = getenv('NENE_INVOICE_BEARER_TOKEN') ?: '';

        if ($baseUrl === '' || $token === '') {
            $this->markTestSkipped('NENE_INVOICE_API_BASE_URL / NENE_INVOICE_BEARER_TOKEN not set — skipping contract tests.');
        }

        $this->client = new InvoiceUpstreamHttpClient($baseUrl, $token);
        $this->orgId = (int) (getenv('NENE_INVOICE_CONTRACT_ORG') ?: '1');
    }

    public function test_list_invoices_returns_array(): void
    {
        $invoices = $this->client->listInvoices($this->orgId, ['issued', 'partially_paid']);

        // list<InvoiceItem> is always an array — just verify each item's structure
        foreach ($invoices as $invoice) {
            self::assertGreaterThan(0, $invoice->invoiceId);
            self::assertNotEmpty($invoice->invoiceNumber);
            self::assertGreaterThanOrEqual(0, $invoice->outstandingCents);
            self::assertGreaterThan(0, $invoice->totalCents);
            self::assertNotEmpty($invoice->dueAt);
            self::assertContains($invoice->status, ['issued', 'partially_paid', 'paid']);
            self::assertSame('JPY', $invoice->currency);
        }
    }

    public function test_get_invoice_returns_detail_for_existing(): void
    {
        $invoices = $this->client->listInvoices($this->orgId, ['issued', 'partially_paid', 'paid']);

        if (count($invoices) === 0) {
            $this->markTestSkipped('No invoices found in the test org — create at least one in nene-invoice.');
        }

        $detail = $this->client->getInvoice($this->orgId, $invoices[0]->invoiceId);

        self::assertSame($invoices[0]->invoiceId, $detail->invoiceId);
        self::assertSame($invoices[0]->invoiceNumber, $detail->invoiceNumber);
        self::assertGreaterThanOrEqual(0, $detail->outstandingCents);
    }

    public function test_get_invoice_throws_not_found_for_missing(): void
    {
        $this->expectException(UpstreamInvoiceNotFoundException::class);
        $this->client->getInvoice($this->orgId, PHP_INT_MAX);
    }

    public function test_get_client_returns_contact_info(): void
    {
        $invoices = $this->client->listInvoices($this->orgId, ['issued', 'partially_paid']);

        if (count($invoices) === 0) {
            $this->markTestSkipped('No open invoices in test org.');
        }

        $clientInfo = $this->client->getClient($this->orgId, $invoices[0]->clientId);

        self::assertSame($invoices[0]->clientId, $clientInfo->clientId);
        self::assertNotEmpty($clientInfo->contactName);
        self::assertStringContainsString('@', $clientInfo->recipientEmail);
    }

    public function test_get_client_throws_client_not_found_for_missing(): void
    {
        $this->expectException(UpstreamClientNotFoundException::class);
        $this->client->getClient($this->orgId, PHP_INT_MAX);
    }

    public function test_create_and_void_payment_idempotent(): void
    {
        $invoices = $this->client->listInvoices($this->orgId, ['issued', 'partially_paid']);

        if (count($invoices) === 0) {
            $this->markTestSkipped('No open invoices in test org.');
        }

        $invoice = $invoices[0];
        $amountCents = min(1, $invoice->outstandingCents); // pay 1 yen to avoid over-allocation
        if ($amountCents <= 0) {
            $this->markTestSkipped('Invoice has zero outstanding.');
        }

        $idempotencyKey = 'clear:contract-test:' . $invoice->invoiceId . ':' . time();
        $externalRef = 'clear:contract-test:' . $invoice->invoiceId;

        $payment = $this->client->createPayment(
            $this->orgId,
            $invoice->invoiceId,
            $amountCents,
            date('Y-m-d'),
            $externalRef,
            $idempotencyKey,
        );

        self::assertGreaterThan(0, $payment->paymentId);
        self::assertSame($invoice->invoiceId, $payment->invoiceId);
        self::assertSame($amountCents, $payment->amountCents);
        self::assertSame($externalRef, $payment->externalReference);

        // Idempotency: second call returns same payment (no error)
        $payment2 = $this->client->createPayment(
            $this->orgId,
            $invoice->invoiceId,
            $amountCents,
            date('Y-m-d'),
            $externalRef,
            $idempotencyKey,
        );
        self::assertSame($payment->paymentId, $payment2->paymentId);

        // Void the payment (cleanup)
        $this->client->voidPayment(
            $this->orgId,
            $invoice->invoiceId,
            $payment->paymentId,
            'contract test cleanup',
            $idempotencyKey . ':void',
        );

        // Idempotent void (no-op)
        $this->client->voidPayment(
            $this->orgId,
            $invoice->invoiceId,
            $payment->paymentId,
            'contract test cleanup',
            $idempotencyKey . ':void',
        );

        $this->addToAssertionCount(1); // void succeeded without exception
    }
}
