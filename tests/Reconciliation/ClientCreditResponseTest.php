<?php

declare(strict_types=1);

namespace NeneClear\Tests\Reconciliation;

use NeneClear\Receivable\ReceivableSource;
use NeneClear\Reconciliation\ClientCredit;
use NeneClear\Reconciliation\ClientCreditResponse;
use NeneClear\Reconciliation\ClientCreditStatus;
use PHPUnit\Framework\TestCase;

final class ClientCreditResponseTest extends TestCase
{
    public function test_manual_source_credit_exposes_client_name_and_null_client_id(): void
    {
        // A credit from a manual receivable (ADR 0014) has no upstream client_id;
        // the payer is carried in client_name. Before #341 the response dropped
        // client_name, so the UI rendered "#null" with no way to show the payer.
        $credit = new ClientCredit(
            organizationId: 1,
            clientId: null,
            amountCents: 5000,
            remainingCents: 5000,
            status: ClientCreditStatus::Open,
            sourceBankTransactionId: 42,
            reconciliationId: 7,
            createdBy: 3,
            createdAt: '2026-07-16 10:00:00',
            id: 99,
            source: ReceivableSource::Manual,
            manualReceivableId: 55,
            clientName: '山田商店',
        );

        $out = ClientCreditResponse::toArray($credit);

        self::assertNull($out['client_id']);
        self::assertSame('山田商店', $out['client_name']);
        self::assertSame('manual', $out['source']);
        self::assertSame(55, $out['manual_receivable_id']);
    }

    public function test_upstream_source_credit_has_client_id_and_null_client_name(): void
    {
        $credit = new ClientCredit(
            organizationId: 1,
            clientId: 123,
            amountCents: 5000,
            remainingCents: 5000,
            status: ClientCreditStatus::Open,
            sourceBankTransactionId: 42,
            reconciliationId: 7,
            createdBy: 3,
            createdAt: '2026-07-16 10:00:00',
            id: 100,
            source: ReceivableSource::InvoiceUpstream,
            manualReceivableId: null,
            clientName: null,
        );

        $out = ClientCreditResponse::toArray($credit);

        self::assertSame(123, $out['client_id']);
        self::assertNull($out['client_name']);
        self::assertSame('invoice_upstream', $out['source']);
        self::assertNull($out['manual_receivable_id']);
    }
}
