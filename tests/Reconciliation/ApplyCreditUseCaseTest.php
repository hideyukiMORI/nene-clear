<?php

declare(strict_types=1);

namespace NeneClear\Tests\Reconciliation;

use NeneClear\InvoiceUpstream\FakeInvoiceUpstreamClient;
use NeneClear\InvoiceUpstream\InvoiceItem;
use NeneClear\Reconciliation\ApplyCreditInput;
use NeneClear\Reconciliation\ApplyCreditUseCase;
use NeneClear\Reconciliation\ClientCredit;
use NeneClear\Reconciliation\ClientCreditNotFoundException;
use NeneClear\Reconciliation\ClientCreditStatus;
use NeneClear\Reconciliation\CreditExceedsRemainingException;
use NeneClear\Tests\Audit\InMemoryAuditEventRepository;
use NeneClear\Tests\Audit\InMemoryAuditRecorderFactory;
use NeneClear\Tests\Support\FakeTransactionManager;
use NeneClear\Tests\Support\FixedClock;
use NeneClear\Tests\Support\NullQueryExecutor;
use PHPUnit\Framework\TestCase;

final class ApplyCreditUseCaseTest extends TestCase
{
    private InMemoryClientCreditRepository $clientCredits;
    private FakeInvoiceUpstreamClient $invoiceClient;
    private InMemoryAuditEventRepository $audit;
    private ApplyCreditUseCase $useCase;

    protected function setUp(): void
    {
        $this->clientCredits = new InMemoryClientCreditRepository();
        $this->invoiceClient = new FakeInvoiceUpstreamClient();
        $this->audit = new InMemoryAuditEventRepository();
        $this->useCase = new ApplyCreditUseCase(
            new FakeTransactionManager(),
            new NullQueryExecutor(),
            fn () => $this->clientCredits,
            $this->invoiceClient,
            new InMemoryAuditRecorderFactory($this->audit, new FixedClock()),
        );
    }

    private function makeCredit(int $amountCents, int $orgId = 7): int
    {
        return $this->clientCredits->save(new ClientCredit(
            organizationId: $orgId,
            clientId: 100,
            amountCents: $amountCents,
            remainingCents: $amountCents,
            status: ClientCreditStatus::Open,
            sourceBankTransactionId: 1,
            reconciliationId: 1,
            createdBy: 42,
            createdAt: '2026-05-30 09:00:00',
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

    private function input(int $creditId, int $invoiceId, int $amountCents, int $orgId = 7): ApplyCreditInput
    {
        return new ApplyCreditInput(
            organizationId: $orgId,
            creditId: $creditId,
            invoiceId: $invoiceId,
            amountCents: $amountCents,
            actorUserId: 42,
        );
    }

    public function test_full_apply_voids_credit(): void
    {
        $creditId = $this->makeCredit(50000);
        $this->addInvoice(1, 100000);

        $output = $this->useCase->execute($this->input($creditId, 1, 50000));

        self::assertSame(0, $output->credit->remainingCents);
        self::assertSame(ClientCreditStatus::Voided, $output->credit->status);

        $payments = $this->invoiceClient->paymentsFor(1);
        self::assertCount(1, $payments);
        self::assertSame(50000, $payments[0]['amountCents']);

        self::assertCount(1, $this->audit->events);
        self::assertSame('client_credit_applied', $this->audit->events[0]->action);
    }

    public function test_partial_apply_reduces_remaining(): void
    {
        $creditId = $this->makeCredit(100000);
        $this->addInvoice(1, 200000);

        $output = $this->useCase->execute($this->input($creditId, 1, 30000));

        self::assertSame(70000, $output->credit->remainingCents);
        self::assertSame(ClientCreditStatus::Open, $output->credit->status);
    }

    public function test_exceeds_remaining_throws(): void
    {
        $creditId = $this->makeCredit(50000);
        $this->addInvoice(1, 200000);

        $this->expectException(CreditExceedsRemainingException::class);
        $this->useCase->execute($this->input($creditId, 1, 80000));
    }

    public function test_unknown_credit_throws_not_found(): void
    {
        $this->expectException(ClientCreditNotFoundException::class);
        $this->useCase->execute($this->input(9999, 1, 10000));
    }

    public function test_cross_tenant_credit_throws_not_found(): void
    {
        $creditId = $this->makeCredit(50000, orgId: 7);
        $this->addInvoice(1, 100000);

        $this->expectException(ClientCreditNotFoundException::class);
        $this->useCase->execute($this->input($creditId, 1, 50000, orgId: 999));
    }
}
