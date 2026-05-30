<?php

declare(strict_types=1);

namespace NeneClear\Tests\BankImport;

use NeneClear\BankImport\BankImportBatch;
use NeneClear\BankImport\BankImportBatchAlreadyReversedException;
use NeneClear\BankImport\BankImportBatchHasMatchedLinesException;
use NeneClear\BankImport\BankImportBatchNotFoundException;
use NeneClear\BankImport\BankImportBatchStatus;
use NeneClear\BankImport\BankTransaction;
use NeneClear\BankImport\BankTransactionStatus;
use NeneClear\BankImport\ReverseBankImportBatchInput;
use NeneClear\BankImport\ReverseBankImportBatchUseCase;
use NeneClear\Tests\Audit\InMemoryAuditEventRepository;
use NeneClear\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;

final class ReverseBankImportBatchUseCaseTest extends TestCase
{
    private InMemoryBankImportBatchRepository $batches;
    private InMemoryBankTransactionRepository $transactions;
    private InMemoryAuditEventRepository $audit;
    private ReverseBankImportBatchUseCase $useCase;

    protected function setUp(): void
    {
        $this->batches = new InMemoryBankImportBatchRepository();
        $this->transactions = new InMemoryBankTransactionRepository();
        $this->audit = new InMemoryAuditEventRepository();
        $this->useCase = new ReverseBankImportBatchUseCase(
            $this->batches,
            $this->transactions,
            $this->audit,
            new FixedClock(),
        );
    }

    private function makeBatch(int $orgId = 7, BankImportBatchStatus $status = BankImportBatchStatus::Imported): int
    {
        return $this->batches->save(new BankImportBatch(
            organizationId: $orgId,
            bankAccountId: 1,
            fileHash: bin2hex(random_bytes(4)),
            sourceFilename: 'test.csv',
            rowCount: 2,
            status: $status,
            importedBy: 42,
            importedAt: '2026-05-31 09:00:00',
        ));
    }

    private function makeTransaction(int $batchId, BankTransactionStatus $status = BankTransactionStatus::Unmatched): int
    {
        return $this->transactions->save(new BankTransaction(
            organizationId: 7,
            bankImportBatchId: $batchId,
            bankAccountId: 1,
            valueDate: '2026-04-20',
            amountCents: 100000,
            counterpartyText: 'ACME',
            lineKey: bin2hex(random_bytes(4)),
            status: $status,
        ));
    }

    private function input(int $batchId, int $orgId = 7): ReverseBankImportBatchInput
    {
        return new ReverseBankImportBatchInput(
            organizationId: $orgId,
            batchId: $batchId,
            actorUserId: 42,
            reversalReason: 'test reversal',
        );
    }

    public function test_reverse_voids_unmatched_lines_and_marks_batch_reversed(): void
    {
        $batchId = $this->makeBatch();
        $txId1 = $this->makeTransaction($batchId);
        $txId2 = $this->makeTransaction($batchId);

        $output = $this->useCase->execute($this->input($batchId));

        self::assertSame($batchId, $output->batchId);
        self::assertSame(2, $output->rowsVoided);

        $batch = $this->batches->findById($batchId);
        self::assertNotNull($batch);
        self::assertSame(BankImportBatchStatus::Reversed, $batch->status);
        self::assertNotNull($batch->reversedAt);
        self::assertSame('test reversal', $batch->reversalReason);

        self::assertSame(BankTransactionStatus::Voided, $this->transactions->findById(7, $txId1)?->status);
        self::assertSame(BankTransactionStatus::Voided, $this->transactions->findById(7, $txId2)?->status);

        self::assertCount(1, $this->audit->events);
        self::assertSame('bank_import_batch_reversed', $this->audit->events[0]->eventType);
    }

    public function test_unknown_or_cross_tenant_batch_throws_not_found(): void
    {
        $this->makeBatch(orgId: 7);

        $this->expectException(BankImportBatchNotFoundException::class);
        $this->useCase->execute($this->input(batchId: 999, orgId: 7));
    }

    public function test_cross_tenant_batch_throws_not_found(): void
    {
        $batchId = $this->makeBatch(orgId: 7);

        $this->expectException(BankImportBatchNotFoundException::class);
        $this->useCase->execute($this->input(batchId: $batchId, orgId: 999));
    }

    public function test_already_reversed_batch_throws_conflict(): void
    {
        $batchId = $this->makeBatch(status: BankImportBatchStatus::Reversed);

        $this->expectException(BankImportBatchAlreadyReversedException::class);
        $this->useCase->execute($this->input($batchId));
    }

    public function test_batch_with_matched_line_throws_conflict(): void
    {
        $batchId = $this->makeBatch();
        $this->makeTransaction($batchId, BankTransactionStatus::Unmatched);
        $this->makeTransaction($batchId, BankTransactionStatus::Matched);

        $this->expectException(BankImportBatchHasMatchedLinesException::class);
        $this->useCase->execute($this->input($batchId));
    }

    public function test_batch_with_partially_matched_line_throws_conflict(): void
    {
        $batchId = $this->makeBatch();
        $this->makeTransaction($batchId, BankTransactionStatus::PartiallyMatched);

        $this->expectException(BankImportBatchHasMatchedLinesException::class);
        $this->useCase->execute($this->input($batchId));
    }

    public function test_voided_lines_are_skipped_in_void_count(): void
    {
        $batchId = $this->makeBatch();
        $this->makeTransaction($batchId, BankTransactionStatus::Voided);
        $this->makeTransaction($batchId, BankTransactionStatus::Unmatched);

        $output = $this->useCase->execute($this->input($batchId));

        self::assertSame(1, $output->rowsVoided);
    }
}
