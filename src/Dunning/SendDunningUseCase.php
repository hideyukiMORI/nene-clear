<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Closure;
use DateInterval;
use DateTimeImmutable;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditRecorderInterface;
use NeneClear\ClearSettings\ClearSettingsRepositoryInterface;
use NeneClear\InvoiceUpstream\InvoiceUpstreamClientInterface;

final readonly class SendDunningUseCase implements SendDunningUseCaseInterface
{
    private const int DEFAULT_MIN_INTERVAL_DAYS = 7;

    /**
     * @param DatabaseQueryExecutorInterface $reader executor for pre-transaction reads
     * @param Closure(DatabaseQueryExecutorInterface): DunningNoticeRepositoryInterface $notices
     * @param Closure(DatabaseQueryExecutorInterface): ClearSettingsRepositoryInterface $clearSettings
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface $auditRecorder
     * @param ?Closure(DatabaseQueryExecutorInterface): DunningPauseRepositoryInterface $pauses
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private DatabaseQueryExecutorInterface $reader,
        private Closure $notices,
        private Closure $clearSettings,
        private InvoiceUpstreamClientInterface $invoiceClient,
        private DunningMailerInterface $mailer,
        private Closure $auditRecorder,
        private ClockInterface $clock,
        private DunningMessageRenderer $renderer,
        private ?Closure $pauses = null,
    ) {
    }

    public function execute(SendDunningInput $input): SendDunningOutput
    {
        $invoice = $this->invoiceClient->getInvoice($input->organizationId, $input->invoiceId);

        if (!in_array($invoice->status, ['issued', 'partially_paid', 'overdue'], true) || $invoice->outstandingCents <= 0) {
            throw new InvoiceAlreadyPaidException($input->invoiceId, $invoice->status);
        }

        $pause = ($this->pauses)?->__invoke($this->reader)->findActiveByInvoice($input->organizationId, $input->invoiceId);
        if ($pause !== null) {
            throw new DunningPausedException($input->invoiceId, $pause->pausedReason);
        }

        $settings = ($this->clearSettings)($this->reader)->findByOrganization($input->organizationId);
        $minIntervalDays = $settings !== null ? $settings->dunningMinIntervalDays : self::DEFAULT_MIN_INTERVAL_DAYS;

        $lastNotice = ($this->notices)($this->reader)->findLastByInvoice($input->organizationId, $input->invoiceId);
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
        $subject = $this->renderer->subject($input->stage, $invoice->invoiceNumber);
        $body = $this->renderer->body($input->stage, $client->contactName, $invoice->invoiceNumber, $invoice->dueAt, $invoice->outstandingCents);

        $notice = new DunningNotice(
            organizationId: $input->organizationId,
            invoiceId: $input->invoiceId,
            invoiceNumber: $invoice->invoiceNumber,
            clientId: $invoice->clientId,
            recipientEmail: $client->recipientEmail,
            outstandingCents: $invoice->outstandingCents,
            dueAt: $invoice->dueAt,
            channel: $this->mailer->channel(),
            templateVersion: DunningMessageRenderer::TEMPLATE_VERSION,
            sentBy: $input->actorUserId,
            sentAt: $nowStr,
        );

        // Record the notice and its audit event atomically first (Issue #122), then
        // send the email. The mailer is an external side effect kept OUTSIDE the
        // transaction: recording before sending means a delivery failure leaves an
        // honest "attempted" trail rather than an un-recordable already-sent email.
        $noticeId = $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $ex) use ($input, $invoice, $client, $notice, $nowStr, $lastNotice): int {
                $notices = ($this->notices)($ex);
                $auditRecorder = ($this->auditRecorder)($ex);

                $noticeId = $notices->save($notice);

                $auditRecorder->record(
                    $input->organizationId,
                    $input->actorUserId,
                    $nowStr,
                    'dunning_sent',
                    'dunning_notice',
                    $noticeId,
                    [
                        'dunning_notice_id' => $noticeId,
                        'invoice_id' => $input->invoiceId,
                        'before' => [
                            'invoice_status' => $invoice->status,
                            'invoice_outstanding_cents' => $invoice->outstandingCents,
                            'previous_dunning_sent_at' => $lastNotice?->sentAt,
                        ],
                        'after' => [
                            'invoice_number' => $invoice->invoiceNumber,
                            'recipient_email' => $client->recipientEmail,
                            'outstanding_at_send_cents' => $invoice->outstandingCents,
                            'channel' => $this->mailer->channel(),
                            'template_version' => DunningMessageRenderer::TEMPLATE_VERSION,
                        ],
                    ],
                );

                return $noticeId;
            },
        );

        $this->mailer->send(new DunningMailPayload(
            to: $client->recipientEmail,
            subject: $subject,
            body: $body,
            organizationId: $input->organizationId,
            invoiceId: $input->invoiceId,
            dunningNoticeId: $noticeId,
        ));

        return new SendDunningOutput(dunningNoticeId: $noticeId);
    }
}
