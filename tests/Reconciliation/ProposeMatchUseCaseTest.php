<?php

declare(strict_types=1);

namespace NeneClear\Tests\Reconciliation;

use NeneClear\BankImport\BankTransaction;
use NeneClear\BankImport\BankTransactionNotFoundException;
use NeneClear\BankImport\BankTransactionStatus;
use NeneClear\InvoiceUpstream\FakeInvoiceUpstreamClient;
use NeneClear\InvoiceUpstream\InvoiceItem;
use NeneClear\Reconciliation\ProposeMatchInput;
use NeneClear\Reconciliation\ProposeMatchUseCase;
use NeneClear\Tests\BankImport\InMemoryBankTransactionRepository;
use NeneClear\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;

final class ProposeMatchUseCaseTest extends TestCase
{
    private InMemoryBankTransactionRepository $transactions;
    private FakeInvoiceUpstreamClient $invoiceClient;
    private ProposeMatchUseCase $useCase;

    protected function setUp(): void
    {
        $this->transactions = new InMemoryBankTransactionRepository();
        $this->invoiceClient = new FakeInvoiceUpstreamClient();
        $this->useCase = new ProposeMatchUseCase(
            $this->transactions,
            $this->invoiceClient,
            new FixedClock('2026-05-30T09:00:00+00:00'),
        );
    }

    private function makeTx(int $amountCents, string $counterpartyText = 'ACME'): int
    {
        return $this->transactions->save(new BankTransaction(
            organizationId: 7,
            bankImportBatchId: 1,
            bankAccountId: 1,
            valueDate: '2026-05-28',
            amountCents: $amountCents,
            counterpartyText: $counterpartyText,
            lineKey: 'k1',
            status: BankTransactionStatus::Unmatched,
        ));
    }

    private function makeInvoice(int $id, string $number, int $outstandingCents, string $dueAt = '2026-12-31'): InvoiceItem
    {
        return new InvoiceItem(
            invoiceId: $id,
            invoiceNumber: $number,
            clientId: 100,
            outstandingCents: $outstandingCents,
            totalCents: $outstandingCents,
            dueAt: $dueAt,
            status: 'issued',
            currency: 'JPY',
        );
    }

    public function test_exact_amount_match_scores_highest(): void
    {
        $txId = $this->makeTx(100000);
        $this->invoiceClient->addInvoice($this->makeInvoice(1, 'INV-001', 100000)); // exact
        $this->invoiceClient->addInvoice($this->makeInvoice(2, 'INV-002', 50000));  // no match

        $output = $this->useCase->execute(new ProposeMatchInput(7, $txId));

        self::assertCount(1, $output->suggestions);
        self::assertSame(1, $output->suggestions[0]->invoiceId);
        self::assertSame(0.5, $output->suggestions[0]->score);
        self::assertStringContainsString('exact amount match', $output->suggestions[0]->reason);
    }

    public function test_invoice_number_in_counterparty_adds_score(): void
    {
        $txId = $this->makeTx(200000, 'Payment for INV-999 ACME');
        $this->invoiceClient->addInvoice($this->makeInvoice(1, 'INV-999', 150000));

        $output = $this->useCase->execute(new ProposeMatchInput(7, $txId));

        self::assertCount(1, $output->suggestions);
        self::assertSame(0.3, $output->suggestions[0]->score);
        self::assertStringContainsString('invoice number in counterparty', $output->suggestions[0]->reason);
    }

    public function test_exact_amount_and_invoice_number_combine(): void
    {
        $txId = $this->makeTx(100000, 'INV-001 payment');
        $this->invoiceClient->addInvoice($this->makeInvoice(1, 'INV-001', 100000));

        $output = $this->useCase->execute(new ProposeMatchInput(7, $txId));

        self::assertCount(1, $output->suggestions);
        self::assertSame(0.8, $output->suggestions[0]->score);
    }

    public function test_due_soon_adds_score(): void
    {
        $txId = $this->makeTx(100000);
        // Due in 10 days from fixed clock (2026-05-30)
        $this->invoiceClient->addInvoice($this->makeInvoice(1, 'INV-001', 100000, '2026-06-09'));

        $output = $this->useCase->execute(new ProposeMatchInput(7, $txId));

        self::assertSame(0.7, $output->suggestions[0]->score); // 0.5 + 0.2
        self::assertStringContainsString('due soon', $output->suggestions[0]->reason);
    }

    public function test_suggestions_sorted_by_score_descending(): void
    {
        $txId = $this->makeTx(100000, 'INV-002');
        $this->invoiceClient->addInvoice($this->makeInvoice(1, 'INV-001', 100000));       // score 0.5
        $this->invoiceClient->addInvoice($this->makeInvoice(2, 'INV-002', 50000));        // score 0.3
        $this->invoiceClient->addInvoice($this->makeInvoice(3, 'INV-002', 100000));       // score 0.5+0.3=0.8

        $output = $this->useCase->execute(new ProposeMatchInput(7, $txId));

        self::assertGreaterThanOrEqual($output->suggestions[0]->score, 1.0);
        self::assertSame(3, $output->suggestions[0]->invoiceId); // highest score first
    }

    public function test_unknown_transaction_throws_not_found(): void
    {
        $this->expectException(BankTransactionNotFoundException::class);
        $this->useCase->execute(new ProposeMatchInput(7, 9999));
    }

    public function test_no_matching_invoices_returns_empty_suggestions(): void
    {
        $txId = $this->makeTx(100000, 'RANDOM');
        $this->invoiceClient->addInvoice($this->makeInvoice(1, 'INV-001', 50000));

        $output = $this->useCase->execute(new ProposeMatchInput(7, $txId));

        self::assertEmpty($output->suggestions);
    }
}
