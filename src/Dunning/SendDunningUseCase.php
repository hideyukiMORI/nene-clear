<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use DateInterval;
use DateTimeImmutable;
use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditEvent;
use NeneClear\Audit\AuditEventRepositoryInterface;
use NeneClear\ClearSettings\ClearSettingsRepositoryInterface;
use NeneClear\I18n\Locale;
use NeneClear\I18n\MessageCatalog;
use NeneClear\InvoiceUpstream\InvoiceUpstreamClientInterface;

final readonly class SendDunningUseCase implements SendDunningUseCaseInterface
{
    private const int DEFAULT_MIN_INTERVAL_DAYS = 7;
    private const string CHANNEL = 'log';

    public function __construct(
        private DunningNoticeRepositoryInterface $notices,
        private ClearSettingsRepositoryInterface $clearSettings,
        private InvoiceUpstreamClientInterface $invoiceClient,
        private DunningMailerInterface $mailer,
        private AuditEventRepositoryInterface $auditEvents,
        private ClockInterface $clock,
        private MessageCatalog $catalog,
    ) {
    }

    public function execute(SendDunningInput $input): SendDunningOutput
    {
        $invoice = $this->invoiceClient->getInvoice($input->organizationId, $input->invoiceId);

        if (!in_array($invoice->status, ['issued', 'partially_paid'], true) || $invoice->outstandingCents <= 0) {
            throw new InvoiceAlreadyPaidException($input->invoiceId, $invoice->status);
        }

        $settings = $this->clearSettings->findByOrganization($input->organizationId);
        $minIntervalDays = $settings !== null ? $settings->dunningMinIntervalDays : self::DEFAULT_MIN_INTERVAL_DAYS;

        $lastNotice = $this->notices->findLastByInvoice($input->organizationId, $input->invoiceId);
        if ($lastNotice !== null) {
            $lastSentAt = new DateTimeImmutable($lastNotice->sentAt);
            $nextAllowed = $lastSentAt->add(new DateInterval('P' . $minIntervalDays . 'D'));
            $now = $this->clock->now();
            if ($now < $nextAllowed) {
                throw new DunningTooFrequentException($nextAllowed->format('Y-m-d H:i:s'));
            }
        }

        $client = $this->invoiceClient->getClient($input->organizationId, $invoice->clientId);

        $nowStr = $this->clock->now()->format('Y-m-d H:i:s');
        $subject = sprintf(
            $this->catalog->get('dunning_email.subject', Locale::Ja),
            $invoice->invoiceNumber,
        );
        $body = sprintf(
            $this->catalog->get('dunning_email.body', Locale::Ja),
            $client->contactName,
            $invoice->invoiceNumber,
            $invoice->dueAt,
            number_format((int) ($invoice->outstandingCents / 100)),
            $invoice->dueAt,
            number_format((int) ($invoice->outstandingCents / 100)),
        );

        $notice = new DunningNotice(
            organizationId: $input->organizationId,
            invoiceId: $input->invoiceId,
            invoiceNumber: $invoice->invoiceNumber,
            clientId: $invoice->clientId,
            recipientEmail: $client->recipientEmail,
            outstandingCents: $invoice->outstandingCents,
            dueAt: $invoice->dueAt,
            channel: self::CHANNEL,
            sentBy: $input->actorUserId,
            sentAt: $nowStr,
        );

        $noticeId = $this->notices->save($notice);

        $this->mailer->send(new DunningMailPayload(
            to: $client->recipientEmail,
            subject: $subject,
            body: $body,
            organizationId: $input->organizationId,
            invoiceId: $input->invoiceId,
            dunningNoticeId: $noticeId,
        ));

        $this->auditEvents->record(new AuditEvent(
            organizationId: $input->organizationId,
            eventType: 'dunning_sent',
            actorUserId: $input->actorUserId,
            occurredAt: $nowStr,
            payload: [
                'dunning_notice_id' => $noticeId,
                'invoice_id' => $input->invoiceId,
                'invoice_number' => $invoice->invoiceNumber,
                'recipient_email' => $client->recipientEmail,
                'outstanding_cents' => $invoice->outstandingCents,
                'channel' => self::CHANNEL,
            ],
        ));

        return new SendDunningOutput(dunningNoticeId: $noticeId);
    }
}
