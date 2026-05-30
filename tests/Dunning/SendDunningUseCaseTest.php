<?php

declare(strict_types=1);

namespace NeneClear\Tests\Dunning;

use NeneClear\ClearSettings\ClearSettings;
use NeneClear\Dunning\DunningTooFrequentException;
use NeneClear\Dunning\InvoiceAlreadyPaidException;
use NeneClear\Dunning\SendDunningInput;
use NeneClear\Dunning\SendDunningUseCase;
use NeneClear\I18n\MessageCatalog;
use NeneClear\InvoiceUpstream\FakeInvoiceUpstreamClient;
use NeneClear\InvoiceUpstream\InvoiceClientInfo;
use NeneClear\InvoiceUpstream\InvoiceItem;
use NeneClear\InvoiceUpstream\UpstreamInvoiceNotFoundException;
use NeneClear\Tests\Audit\InMemoryAuditEventRepository;
use NeneClear\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;

final class SendDunningUseCaseTest extends TestCase
{
    private InMemoryDunningNoticeRepository $notices;
    private InMemoryClearSettingsRepository $clearSettings;
    private FakeInvoiceUpstreamClient $invoiceClient;
    private SpyDunningMailer $mailer;
    private InMemoryAuditEventRepository $audit;
    private SendDunningUseCase $useCase;

    protected function setUp(): void
    {
        $this->notices = new InMemoryDunningNoticeRepository();
        $this->clearSettings = new InMemoryClearSettingsRepository();
        $this->invoiceClient = new FakeInvoiceUpstreamClient();
        $this->mailer = new SpyDunningMailer();
        $this->audit = new InMemoryAuditEventRepository();
        $this->useCase = new SendDunningUseCase(
            $this->notices,
            $this->clearSettings,
            $this->invoiceClient,
            $this->mailer,
            $this->audit,
            new FixedClock('2026-05-31T09:00:00+00:00'),
            new MessageCatalog(dirname(__DIR__, 2) . '/lang'),
        );
    }

    private function addInvoice(int $id, string $status = 'issued', int $outstandingCents = 110000): void
    {
        $this->invoiceClient->addInvoice(new InvoiceItem(
            invoiceId: $id,
            invoiceNumber: 'INV-00' . $id,
            clientId: 100,
            outstandingCents: $outstandingCents,
            totalCents: 110000,
            dueAt: '2026-04-30',
            status: $status,
            currency: 'JPY',
        ));
    }

    private function addClient(): void
    {
        $this->invoiceClient->addClient(new InvoiceClientInfo(
            clientId: 100,
            contactName: 'ACME Corp',
            recipientEmail: 'accounts@acme.example',
        ));
    }

    private function input(int $invoiceId = 1): SendDunningInput
    {
        return new SendDunningInput(organizationId: 7, invoiceId: $invoiceId, actorUserId: 42);
    }

    public function test_happy_path_records_notice_and_logs_mail(): void
    {
        $this->addInvoice(1);
        $this->addClient();

        $output = $this->useCase->execute($this->input());

        self::assertGreaterThan(0, $output->dunningNoticeId);

        $notice = $this->notices->findById(7, $output->dunningNoticeId);
        self::assertNotNull($notice);
        self::assertSame(1, $notice->invoiceId);
        self::assertSame('accounts@acme.example', $notice->recipientEmail);
        self::assertSame(110000, $notice->outstandingCents);
        self::assertSame('log', $notice->channel);

        self::assertCount(1, $this->mailer->sent);
        self::assertSame('accounts@acme.example', $this->mailer->sent[0]->to);
        self::assertStringContainsString('INV-001', $this->mailer->sent[0]->subject);

        self::assertCount(1, $this->audit->events);
        self::assertSame('dunning_sent', $this->audit->events[0]->eventType);
    }

    public function test_paid_invoice_throws(): void
    {
        $this->addInvoice(1, status: 'paid');
        $this->addClient();

        $this->expectException(InvoiceAlreadyPaidException::class);
        $this->useCase->execute($this->input());
    }

    public function test_zero_outstanding_throws(): void
    {
        $this->addInvoice(1, outstandingCents: 0);
        $this->addClient();

        $this->expectException(InvoiceAlreadyPaidException::class);
        $this->useCase->execute($this->input());
    }

    public function test_too_frequent_within_default_interval_throws(): void
    {
        $this->addInvoice(1);
        $this->addClient();

        $this->useCase->execute($this->input()); // first send at 2026-05-31

        // Second send on same day — within 7-day default interval
        $this->expectException(DunningTooFrequentException::class);
        $this->useCase->execute($this->input());
    }

    public function test_too_frequent_exception_contains_next_allowed_at(): void
    {
        $this->addInvoice(1);
        $this->addClient();
        $this->useCase->execute($this->input());

        try {
            $this->useCase->execute($this->input());
            self::fail('Expected DunningTooFrequentException');
        } catch (DunningTooFrequentException $e) {
            self::assertSame('2026-06-07 09:00:00', $e->nextAllowedAt);
        }
    }

    public function test_custom_min_interval_from_settings(): void
    {
        $this->clearSettings->save(new ClearSettings(7, '', '', 3));
        $this->addInvoice(1);
        $this->addClient();

        $this->useCase->execute($this->input());

        // 3-day interval — same day still too frequent
        $this->expectException(DunningTooFrequentException::class);
        $this->useCase->execute($this->input());
    }

    public function test_unknown_invoice_throws_upstream_not_found(): void
    {
        $this->expectException(UpstreamInvoiceNotFoundException::class);
        $this->useCase->execute($this->input(invoiceId: 9999));
    }
}
