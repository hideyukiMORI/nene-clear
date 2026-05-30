<?php

declare(strict_types=1);

namespace NeneClear\Tests\Reconciliation;

use NeneClear\BankImport\BankTransaction;
use NeneClear\BankImport\BankTransactionStatus;
use NeneClear\InvoiceUpstream\FakeInvoiceUpstreamClient;
use NeneClear\InvoiceUpstream\InvoiceItem;
use NeneClear\Reconciliation\AllocationInput;
use NeneClear\Reconciliation\ClientCreditStatus;
use NeneClear\Reconciliation\ConfirmMatchInput;
use NeneClear\Reconciliation\ConfirmMatchUseCase;
use NeneClear\Reconciliation\ReconciliationAlreadyReversedException;
use NeneClear\Reconciliation\ReconciliationNotFoundException;
use NeneClear\Reconciliation\ReconciliationStatus;
use NeneClear\Reconciliation\ReverseReconciliationInput;
use NeneClear\Reconciliation\ReverseReconciliationUseCase;
use NeneClear\Tests\Audit\InMemoryAuditEventRepository;
use NeneClear\Tests\BankImport\InMemoryBankTransactionRepository;
use NeneClear\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;

final class ReverseReconciliationUseCaseTest extends TestCase
{
    private InMemoryBankTransactionRepository $transactions;
    private InMemoryReconciliationRepository $reconciliations;
    private InMemoryClientCreditRepository $clientCredits;
    private FakeInvoiceUpstreamClient $invoiceClient;
    private InMemoryAuditEventRepository $audit;
    private ConfirmMatchUseCase $confirmUseCase;
    private ReverseReconciliationUseCase $reverseUseCase;

    protected function setUp(): void
    {
        $this->transactions = new InMemoryBankTransactionRepository();
        $this->reconciliations = new InMemoryReconciliationRepository();
        $this->clientCredits = new InMemoryClientCreditRepository();
        $this->invoiceClient = new FakeInvoiceUpstreamClient();
        $this->audit = new InMemoryAuditEventRepository();

        $clock = new FixedClock();
        $this->confirmUseCase = new ConfirmMatchUseCase(
            $this->transactions,
            $this->reconciliations,
            $this->clientCredits,
            $this->invoiceClient,
            $this->audit,
            $clock,
        );
        $this->reverseUseCase = new ReverseReconciliationUseCase(
            $this->reconciliations,
            $this->clientCredits,
            $this->transactions,
            $this->invoiceClient,
            $this->audit,
            $clock,
        );
    }

    private function makeTx(int $amountCents): int
    {
        return $this->transactions->save(new BankTransaction(
            organizationId: 7,
            bankImportBatchId: 1,
            bankAccountId: 1,
            valueDate: '2026-05-28',
            amountCents: $amountCents,
            counterpartyText: 'ACME',
            lineKey: 'k1',
            status: BankTransactionStatus::Unmatched,
        ));
    }

    private function addInvoice(int $id, int $outstandingCents): void
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

    private function reverseInput(int $reconciliationId, int $orgId = 7): ReverseReconciliationInput
    {
        return new ReverseReconciliationInput(
            organizationId: $orgId,
            reconciliationId: $reconciliationId,
            actorUserId: 42,
            reversalReason: 'test reversal',
        );
    }

    public function test_reverse_restores_bank_tx_to_unmatched_and_voids_upstream_payment(): void
    {
        $txId = $this->makeTx(100000);
        $this->addInvoice(1, 100000);
        $confirmOutput = $this->confirmUseCase->execute(new ConfirmMatchInput(
            organizationId: 7,
            bankTransactionId: $txId,
            allocations: [new AllocationInput(1, 100000)],
            actorUserId: 42,
        ));

        $reverseOutput = $this->reverseUseCase->execute($this->reverseInput($confirmOutput->reconciliationId));

        self::assertSame($confirmOutput->reconciliationId, $reverseOutput->reconciliationId);

        $tx = $this->transactions->findById(7, $txId);
        self::assertSame(BankTransactionStatus::Unmatched, $tx?->status);

        $recon = $this->reconciliations->findById(7, $confirmOutput->reconciliationId);
        self::assertNotNull($recon);
        self::assertSame(ReconciliationStatus::Reversed, $recon->status);
        self::assertNotNull($recon->reversedAt);

        $payments = $this->invoiceClient->paymentsFor(1);
        self::assertTrue($payments[0]['voided']);

        $auditTypes = array_column($this->audit->events, 'eventType');
        self::assertContains('reconciliation_reversed', $auditTypes);
    }

    public function test_reverse_voids_client_credit(): void
    {
        $txId = $this->makeTx(150000);
        $this->addInvoice(1, 100000);
        $confirmOutput = $this->confirmUseCase->execute(new ConfirmMatchInput(
            organizationId: 7,
            bankTransactionId: $txId,
            allocations: [new AllocationInput(1, 100000)],
            actorUserId: 42,
        ));

        $credit = $this->clientCredits->findByReconciliation($confirmOutput->reconciliationId);
        self::assertSame(ClientCreditStatus::Open, $credit?->status);

        $this->reverseUseCase->execute($this->reverseInput($confirmOutput->reconciliationId));

        $credit = $this->clientCredits->findByReconciliation($confirmOutput->reconciliationId);
        self::assertSame(ClientCreditStatus::Voided, $credit?->status);
    }

    public function test_unknown_reconciliation_throws_not_found(): void
    {
        $this->expectException(ReconciliationNotFoundException::class);
        $this->reverseUseCase->execute($this->reverseInput(9999));
    }

    public function test_cross_tenant_reconciliation_throws_not_found(): void
    {
        $txId = $this->makeTx(100000);
        $this->addInvoice(1, 100000);
        $output = $this->confirmUseCase->execute(new ConfirmMatchInput(
            organizationId: 7,
            bankTransactionId: $txId,
            allocations: [new AllocationInput(1, 100000)],
            actorUserId: 42,
        ));

        $this->expectException(ReconciliationNotFoundException::class);
        $this->reverseUseCase->execute($this->reverseInput($output->reconciliationId, orgId: 999));
    }

    public function test_double_reverse_throws_conflict(): void
    {
        $txId = $this->makeTx(100000);
        $this->addInvoice(1, 100000);
        $output = $this->confirmUseCase->execute(new ConfirmMatchInput(
            organizationId: 7,
            bankTransactionId: $txId,
            allocations: [new AllocationInput(1, 100000)],
            actorUserId: 42,
        ));

        $this->reverseUseCase->execute($this->reverseInput($output->reconciliationId));

        $this->expectException(ReconciliationAlreadyReversedException::class);
        $this->reverseUseCase->execute($this->reverseInput($output->reconciliationId));
    }
}
