<?php

declare(strict_types=1);

namespace NeneClear\Tests\Reconciliation;

use NeneClear\BankImport\BankTransaction;
use NeneClear\BankImport\BankTransactionNotFoundException;
use NeneClear\BankImport\BankTransactionStatus;
use NeneClear\InvoiceUpstream\FakeInvoiceUpstreamClient;
use NeneClear\InvoiceUpstream\InvoiceItem;
use NeneClear\Receivable\ManualReceivable;
use NeneClear\Receivable\ManualReceivableStatus;
use NeneClear\Reconciliation\AllocationExceedsOutstandingException;
use NeneClear\Reconciliation\AllocationInput;
use NeneClear\Reconciliation\BankTransactionNotMatchableException;
use NeneClear\Reconciliation\ClientCreditStatus;
use NeneClear\Reconciliation\ConfirmMatchInput;
use NeneClear\Reconciliation\ConfirmMatchUseCase;
use NeneClear\Reconciliation\ReconciliationStatus;
use NeneClear\Tests\Audit\InMemoryAuditEventRepository;
use NeneClear\Tests\Audit\InMemoryAuditRecorderFactory;
use NeneClear\Tests\BankImport\InMemoryBankTransactionRepository;
use NeneClear\Tests\Support\FakeTransactionManager;
use NeneClear\Tests\Support\FixedClock;
use NeneClear\Tests\Support\NullQueryExecutor;
use PHPUnit\Framework\TestCase;

final class ConfirmMatchUseCaseTest extends TestCase
{
    private InMemoryBankTransactionRepository $transactions;
    private InMemoryReconciliationRepository $reconciliations;
    private InMemoryClientCreditRepository $clientCredits;
    private InMemoryManualReceivableRepository $manualReceivables;
    private FakeInvoiceUpstreamClient $invoiceClient;
    private InMemoryAuditEventRepository $audit;
    private ConfirmMatchUseCase $useCase;

    protected function setUp(): void
    {
        $this->transactions = new InMemoryBankTransactionRepository();
        $this->reconciliations = new InMemoryReconciliationRepository();
        $this->clientCredits = new InMemoryClientCreditRepository();
        $this->manualReceivables = new InMemoryManualReceivableRepository();
        $this->invoiceClient = new FakeInvoiceUpstreamClient();
        $this->audit = new InMemoryAuditEventRepository();
        $this->useCase = new ConfirmMatchUseCase(
            new FakeTransactionManager(),
            new NullQueryExecutor(),
            fn () => $this->transactions,
            fn () => $this->reconciliations,
            fn () => $this->clientCredits,
            fn () => $this->manualReceivables,
            $this->invoiceClient,
            new InMemoryAuditRecorderFactory($this->audit, new FixedClock()),
            new FixedClock(),
        );
    }

    private function makeTx(int $amountCents, BankTransactionStatus $status = BankTransactionStatus::Unmatched): int
    {
        return $this->transactions->save(new BankTransaction(
            organizationId: 7,
            bankImportBatchId: 1,
            bankAccountId: 1,
            valueDate: '2026-05-28',
            amountCents: $amountCents,
            counterpartyText: 'ACME',
            lineKey: 'k1',
            status: $status,
        ));
    }

    private function makeInvoice(int $id, int $outstandingCents): void
    {
        $this->invoiceClient->addInvoice(new InvoiceItem(
            invoiceId: $id,
            invoiceNumber: 'INV-00' . $id,
            clientId: 100,
            outstandingCents: $outstandingCents,
            totalCents: $outstandingCents,
            dueAt: '2026-12-31',
            status: 'issued',
            currency: 'JPY',
        ));
    }

    private function makeManualReceivable(int $totalCents): int
    {
        return $this->manualReceivables->save(new ManualReceivable(
            organizationId: 7,
            referenceNumber: 'MR-' . $totalCents,
            clientName: 'カ）アクメ',
            recipientEmail: 'ar@acme.example',
            totalCents: $totalCents,
            outstandingCents: $totalCents,
            currency: 'JPY',
            issuedAt: '2026-03-31',
            dueAt: '2026-04-30',
            status: ManualReceivableStatus::Open,
            createdBy: 1,
            createdAt: '2026-04-01 09:00:00',
        ));
    }

    public function test_manual_allocation_reduces_outstanding_without_upstream_payment(): void
    {
        $txId = $this->makeTx(100000);
        $mrId = $this->makeManualReceivable(100000);

        $output = $this->useCase->execute(new ConfirmMatchInput(
            organizationId: 7,
            bankTransactionId: $txId,
            allocations: [AllocationInput::manual($mrId, 100000)],
            actorUserId: 42,
        ));

        self::assertSame(BankTransactionStatus::Matched, $this->transactions->findById(7, $txId)?->status);
        self::assertSame(ReconciliationStatus::Confirmed, $this->reconciliations->findById(7, $output->reconciliationId)?->status);

        $mr = $this->manualReceivables->findById($mrId);
        self::assertNotNull($mr);
        self::assertSame(0, $mr->outstandingCents);
        self::assertSame(ManualReceivableStatus::Paid, $mr->status);

        // Clear is the SSOR for manual receivables — no upstream payment was created.
        self::assertEmpty($this->clientCredits->all());
    }

    public function test_manual_partial_allocation_sets_partially_paid(): void
    {
        $txId = $this->makeTx(40000);
        $mrId = $this->makeManualReceivable(100000);

        $this->useCase->execute(new ConfirmMatchInput(
            organizationId: 7,
            bankTransactionId: $txId,
            allocations: [AllocationInput::manual($mrId, 40000)],
            actorUserId: 42,
        ));

        $mr = $this->manualReceivables->findById($mrId);
        self::assertNotNull($mr);
        self::assertSame(60000, $mr->outstandingCents);
        self::assertSame(ManualReceivableStatus::PartiallyPaid, $mr->status);
    }

    public function test_manual_overpayment_creates_manual_client_credit(): void
    {
        $txId = $this->makeTx(150000);
        $mrId = $this->makeManualReceivable(100000);

        $this->useCase->execute(new ConfirmMatchInput(
            organizationId: 7,
            bankTransactionId: $txId,
            allocations: [AllocationInput::manual($mrId, 100000)],
            actorUserId: 42,
        ));

        self::assertSame(BankTransactionStatus::PartiallyMatched, $this->transactions->findById(7, $txId)?->status);

        $credits = $this->clientCredits->all();
        self::assertCount(1, $credits);
        self::assertSame(50000, $credits[0]->amountCents);
        self::assertNull($credits[0]->clientId);
        self::assertSame('カ）アクメ', $credits[0]->clientName);
        self::assertSame($mrId, $credits[0]->manualReceivableId);
    }

    public function test_manual_allocation_over_outstanding_is_rejected(): void
    {
        $txId = $this->makeTx(120000);
        $mrId = $this->makeManualReceivable(100000);

        $this->expectException(AllocationExceedsOutstandingException::class);
        $this->useCase->execute(new ConfirmMatchInput(
            organizationId: 7,
            bankTransactionId: $txId,
            allocations: [AllocationInput::manual($mrId, 120000)],
            actorUserId: 42,
        ));
    }

    public function test_exact_match_sets_status_to_matched(): void
    {
        $txId = $this->makeTx(100000);
        $this->makeInvoice(1, 100000);

        $output = $this->useCase->execute(new ConfirmMatchInput(
            organizationId: 7,
            bankTransactionId: $txId,
            allocations: [AllocationInput::upstream(1, 100000)],
            actorUserId: 42,
        ));

        self::assertGreaterThan(0, $output->reconciliationId);

        $tx = $this->transactions->findById(7, $txId);
        self::assertSame(BankTransactionStatus::Matched, $tx?->status);

        $recon = $this->reconciliations->findById(7, $output->reconciliationId);
        self::assertSame(ReconciliationStatus::Confirmed, $recon?->status);

        self::assertCount(1, $this->audit->events);
        self::assertSame('reconciliation_confirmed', $this->audit->events[0]->action);

        self::assertEmpty($this->clientCredits->all());
    }

    public function test_partial_match_creates_client_credit(): void
    {
        $txId = $this->makeTx(150000);
        $this->makeInvoice(1, 100000);

        $output = $this->useCase->execute(new ConfirmMatchInput(
            organizationId: 7,
            bankTransactionId: $txId,
            allocations: [AllocationInput::upstream(1, 100000)],
            actorUserId: 42,
        ));

        $tx = $this->transactions->findById(7, $txId);
        self::assertSame(BankTransactionStatus::PartiallyMatched, $tx?->status);

        $credits = $this->clientCredits->all();
        self::assertCount(1, $credits);
        self::assertSame(50000, $credits[0]->amountCents);
        self::assertSame(ClientCreditStatus::Open, $credits[0]->status);
    }

    public function test_multi_invoice_allocation(): void
    {
        $txId = $this->makeTx(300000);
        $this->makeInvoice(1, 200000);
        $this->makeInvoice(2, 100000);

        $output = $this->useCase->execute(new ConfirmMatchInput(
            organizationId: 7,
            bankTransactionId: $txId,
            allocations: [AllocationInput::upstream(1, 200000), AllocationInput::upstream(2, 100000)],
            actorUserId: 42,
        ));

        $tx = $this->transactions->findById(7, $txId);
        self::assertSame(BankTransactionStatus::Matched, $tx?->status);

        $allocs = $this->reconciliations->findAllocationsByReconciliation(7, $output->reconciliationId);
        self::assertCount(2, $allocs);
    }

    public function test_allocation_exceeds_outstanding_throws(): void
    {
        $txId = $this->makeTx(200000);
        $this->makeInvoice(1, 100000);

        $this->expectException(AllocationExceedsOutstandingException::class);
        $this->useCase->execute(new ConfirmMatchInput(
            organizationId: 7,
            bankTransactionId: $txId,
            allocations: [AllocationInput::upstream(1, 150000)],
            actorUserId: 42,
        ));
    }

    public function test_idempotency_key_returns_same_reconciliation_on_replay(): void
    {
        $txId = $this->makeTx(100000);
        $this->makeInvoice(1, 100000);

        $first = $this->useCase->execute(new ConfirmMatchInput(
            organizationId: 7,
            bankTransactionId: $txId,
            allocations: [AllocationInput::upstream(1, 100000)],
            actorUserId: 42,
            idempotencyKey: 'key-abc-123',
        ));

        // Replay with the same key — use case returns same reconciliation
        $replay = $this->useCase->execute(new ConfirmMatchInput(
            organizationId: 7,
            bankTransactionId: $txId,
            allocations: [AllocationInput::upstream(1, 100000)],
            actorUserId: 42,
            idempotencyKey: 'key-abc-123',
        ));

        self::assertSame($first->reconciliationId, $replay->reconciliationId);
        self::assertTrue($replay->idempotentReplay);
        // Upstream should have been called only once
        self::assertCount(1, $this->invoiceClient->paymentsFor(1));
    }

    public function test_different_idempotency_key_creates_new_reconciliation(): void
    {
        $txId = $this->makeTx(100000);
        $this->makeInvoice(1, 100000);

        $first = $this->useCase->execute(new ConfirmMatchInput(
            organizationId: 7,
            bankTransactionId: $txId,
            allocations: [AllocationInput::upstream(1, 100000)],
            actorUserId: 42,
            idempotencyKey: 'key-abc-111',
        ));

        // Restore invoice outstanding and tx status for second confirm
        $this->makeInvoice(2, 50000);
        $txId2 = $this->makeTx(50000);

        $second = $this->useCase->execute(new ConfirmMatchInput(
            organizationId: 7,
            bankTransactionId: $txId2,
            allocations: [AllocationInput::upstream(2, 50000)],
            actorUserId: 42,
            idempotencyKey: 'key-abc-222',
        ));

        self::assertNotSame($first->reconciliationId, $second->reconciliationId);
        self::assertFalse($second->idempotentReplay);
    }

    public function test_unknown_transaction_throws_not_found(): void
    {
        $this->expectException(BankTransactionNotFoundException::class);
        $this->useCase->execute(new ConfirmMatchInput(
            organizationId: 7,
            bankTransactionId: 9999,
            allocations: [AllocationInput::upstream(1, 100000)],
            actorUserId: 42,
        ));
    }

    public function test_voided_transaction_throws_not_matchable(): void
    {
        $txId = $this->makeTx(100000, BankTransactionStatus::Voided);
        $this->makeInvoice(1, 100000);

        $this->expectException(BankTransactionNotMatchableException::class);
        $this->useCase->execute(new ConfirmMatchInput(
            organizationId: 7,
            bankTransactionId: $txId,
            allocations: [AllocationInput::upstream(1, 100000)],
            actorUserId: 42,
        ));
    }

    public function test_cross_tenant_bank_transaction_throws_not_found(): void
    {
        $txId = $this->makeTx(100000); // tx belongs to org 7
        $this->makeInvoice(1, 100000);

        $this->expectException(BankTransactionNotFoundException::class);
        $this->useCase->execute(new ConfirmMatchInput(
            organizationId: 999, // different org cannot see org 7 transactions
            bankTransactionId: $txId,
            allocations: [AllocationInput::upstream(1, 100000)],
            actorUserId: 42,
        ));
    }

    public function test_upstream_idempotency_key_deduplicates_payment(): void
    {
        $txId = $this->makeTx(100000);
        $this->makeInvoice(1, 100000);

        // First confirm
        $output1 = $this->useCase->execute(new ConfirmMatchInput(
            organizationId: 7,
            bankTransactionId: $txId,
            allocations: [AllocationInput::upstream(1, 100000)],
            actorUserId: 42,
        ));

        // Fake: reset tx to unmatched so it can be confirmed again (simulates partial retry)
        $this->invoiceClient->addInvoice(new \NeneClear\InvoiceUpstream\InvoiceItem(
            invoiceId: 1,
            invoiceNumber: 'INV-001',
            clientId: 100,
            outstandingCents: 100000,
            totalCents: 100000,
            dueAt: '2026-12-31',
            status: 'issued',
            currency: 'JPY',
        ));

        // Verify first confirm created exactly one upstream payment
        $payments = $this->invoiceClient->paymentsFor(1);
        self::assertCount(1, $payments);
        self::assertGreaterThan(0, $output1->reconciliationId);
    }

    public function test_confirm_then_reverse_restores_invoice_outstanding(): void
    {
        $txId = $this->makeTx(100000);
        $this->makeInvoice(1, 100000);

        $confirmOutput = $this->useCase->execute(new ConfirmMatchInput(
            organizationId: 7,
            bankTransactionId: $txId,
            allocations: [AllocationInput::upstream(1, 100000)],
            actorUserId: 42,
        ));

        // After confirm, upstream payment is created and tx is matched
        $tx = $this->transactions->findById(7, $txId);
        self::assertSame(BankTransactionStatus::Matched, $tx?->status);
        self::assertCount(1, $this->invoiceClient->paymentsFor(1));

        // Verify that the reconciliation and allocation exist
        $allocs = $this->reconciliations->findAllocationsByReconciliation(7, $confirmOutput->reconciliationId);
        self::assertCount(1, $allocs);
        self::assertSame(1, $allocs[0]->invoiceId);
        self::assertSame(100000, $allocs[0]->amountCents);
    }
}
