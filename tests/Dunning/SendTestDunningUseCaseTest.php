<?php

declare(strict_types=1);

namespace NeneClear\Tests\Dunning;

use Nene2\Validation\ValidationException;
use NeneClear\Dunning\DunningMessageRenderer;
use NeneClear\Dunning\SendTestDunningInput;
use NeneClear\Dunning\SendTestDunningUseCase;
use NeneClear\I18n\MessageCatalog;
use NeneClear\InvoiceUpstream\FakeInvoiceUpstreamClient;
use NeneClear\InvoiceUpstream\InvoiceClientInfo;
use NeneClear\InvoiceUpstream\InvoiceItem;
use PHPUnit\Framework\TestCase;

final class SendTestDunningUseCaseTest extends TestCase
{
    private SpyDunningMailer $mailer;
    private SendTestDunningUseCase $useCase;

    protected function setUp(): void
    {
        $invoiceClient = new FakeInvoiceUpstreamClient();
        $invoiceClient->addInvoice(new InvoiceItem(1, 'INV-001', 100, 110000, 110000, '2026-04-30', 'issued', 'JPY'));
        $invoiceClient->addClient(new InvoiceClientInfo(100, 'ACME Corp', 'accounts@acme.example'));

        $this->mailer = new SpyDunningMailer();
        $this->useCase = new SendTestDunningUseCase(
            $invoiceClient,
            new DunningMessageRenderer(new MessageCatalog(dirname(__DIR__, 2) . '/lang')),
            $this->mailer,
        );
    }

    public function testSendsToTheGivenAddressMarkedAsTestWithoutRecording(): void
    {
        $sentTo = $this->useCase->execute(new SendTestDunningInput(
            organizationId: 7,
            invoiceId: 1,
            to: 'me@operator.example',
            actorUserId: 1,
        ));

        self::assertSame('me@operator.example', $sentTo);
        self::assertCount(1, $this->mailer->sent);

        $payload = $this->mailer->sent[0];
        self::assertSame('me@operator.example', $payload->to); // not the client's address
        self::assertStringStartsWith('【テスト送信】', $payload->subject);
        self::assertStringContainsString('INV-001', $payload->subject);
        self::assertStringContainsString('ACME Corp', $payload->body);
    }

    public function testRejectsAnInvalidRecipient(): void
    {
        $this->expectException(ValidationException::class);

        $this->useCase->execute(new SendTestDunningInput(7, 1, 'not-an-email', 1));
    }
}
