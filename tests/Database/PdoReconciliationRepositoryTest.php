<?php

declare(strict_types=1);

namespace NeneClear\Tests\Database;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Testing\DatabaseTestKit;
use NeneClear\Reconciliation\ClientCredit;
use NeneClear\Reconciliation\ClientCreditStatus;
use NeneClear\Reconciliation\PdoClientCreditRepository;
use NeneClear\Reconciliation\PdoReconciliationRepository;
use NeneClear\Reconciliation\Reconciliation;
use NeneClear\Reconciliation\ReconciliationAllocation;
use NeneClear\Reconciliation\ReconciliationStatus;
use NeneClear\Tests\Support\SchemaFixture;
use PHPUnit\Framework\TestCase;

final class PdoReconciliationRepositoryTest extends TestCase
{
    private string $dbPath;
    private DatabaseQueryExecutorInterface $query;
    private PdoReconciliationRepository $reconRepo;
    private PdoClientCreditRepository $creditRepo;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-recon-', true) . '.sqlite';
        $this->query = DatabaseTestKit::sqlite($this->dbPath)->queryExecutor;
        SchemaFixture::createPaymentReconciliations($this->query);
        SchemaFixture::createReconciliationAllocations($this->query);
        SchemaFixture::createClientCredits($this->query);

        $this->reconRepo = new PdoReconciliationRepository($this->query);
        $this->creditRepo = new PdoClientCreditRepository($this->query);
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    private function makeReconciliation(int $orgId = 7, int $bankTxId = 1): Reconciliation
    {
        return new Reconciliation(
            organizationId: $orgId,
            bankTransactionId: $bankTxId,
            status: ReconciliationStatus::Confirmed,
            confirmedBy: 42,
            confirmedAt: '2026-05-31 09:00:00',
        );
    }

    public function test_save_and_findById(): void
    {
        $id = $this->reconRepo->save($this->makeReconciliation());
        $recon = $this->reconRepo->findById(7, $id);

        self::assertNotNull($recon);
        self::assertSame($id, $recon->id);
        self::assertSame(ReconciliationStatus::Confirmed, $recon->status);
        self::assertSame(1, $recon->bankTransactionId);
    }

    public function test_findById_cross_tenant_returns_null(): void
    {
        $id = $this->reconRepo->save($this->makeReconciliation(orgId: 7));

        self::assertNull($this->reconRepo->findById(999, $id));
    }

    public function test_save_allocation_and_find(): void
    {
        $reconId = $this->reconRepo->save($this->makeReconciliation());
        $this->reconRepo->saveAllocation(new ReconciliationAllocation(
            organizationId: 7,
            reconciliationId: $reconId,
            invoiceId: 10,
            amountCents: 100000,
            paymentId: 55,
            externalReference: 'clear:recon:1:10',
        ));

        $allocs = $this->reconRepo->findAllocationsByReconciliation(7, $reconId);
        self::assertCount(1, $allocs);
        self::assertSame(10, $allocs[0]->invoiceId);
        self::assertSame(100000, $allocs[0]->amountCents);
        self::assertSame(55, $allocs[0]->paymentId);
    }

    public function test_findAllocationsByReconciliation_cross_tenant_returns_empty(): void
    {
        $reconId = $this->reconRepo->save($this->makeReconciliation(orgId: 7));
        $this->reconRepo->saveAllocation(new ReconciliationAllocation(
            organizationId: 7,
            reconciliationId: $reconId,
            invoiceId: 10,
            amountCents: 100000,
            paymentId: 55,
            externalReference: 'clear:recon:1:10',
        ));

        // org 999 cannot see org 7 allocations
        $allocs = $this->reconRepo->findAllocationsByReconciliation(999, $reconId);
        self::assertCount(0, $allocs);

        // org 7 can see its own allocations
        $allocs = $this->reconRepo->findAllocationsByReconciliation(7, $reconId);
        self::assertCount(1, $allocs);
    }

    public function test_reverseById(): void
    {
        $id = $this->reconRepo->save($this->makeReconciliation());
        $this->reconRepo->reverseById($id, '2026-06-01 10:00:00', 'wrong entry');

        $recon = $this->reconRepo->findById(7, $id);
        self::assertNotNull($recon);
        self::assertSame(ReconciliationStatus::Reversed, $recon->status);
        self::assertSame('wrong entry', $recon->reversalReason);
    }

    public function test_findByOrganization_with_status_filter(): void
    {
        $id1 = $this->reconRepo->save($this->makeReconciliation(bankTxId: 1));
        $id2 = $this->reconRepo->save($this->makeReconciliation(bankTxId: 2));
        $this->reconRepo->reverseById($id2, '2026-06-01 10:00:00', 'reason');

        $confirmed = $this->reconRepo->findByOrganization(7, ReconciliationStatus::Confirmed, 50, 0);
        self::assertCount(1, $confirmed);
        self::assertSame($id1, $confirmed[0]->id);

        self::assertSame(2, $this->reconRepo->countByOrganization(7, null));
    }

    public function test_client_credit_save_and_void(): void
    {
        $credit = new ClientCredit(
            organizationId: 7,
            clientId: 100,
            amountCents: 50000,
            remainingCents: 50000,
            status: ClientCreditStatus::Open,
            sourceBankTransactionId: 1,
            reconciliationId: 1,
            createdBy: 42,
            createdAt: '2026-05-31 09:00:00',
        );

        $this->creditRepo->save($credit);

        $found = $this->creditRepo->findByReconciliation(7, 1);
        self::assertNotNull($found);
        self::assertSame(ClientCreditStatus::Open, $found->status);

        $this->creditRepo->voidByReconciliation(1);

        $found = $this->creditRepo->findByReconciliation(7, 1);
        self::assertSame(ClientCreditStatus::Voided, $found?->status);
    }
}
