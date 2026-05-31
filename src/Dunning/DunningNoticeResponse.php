<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

final readonly class DunningNoticeResponse
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(DunningNotice $notice): array
    {
        return [
            'dunning_notice_id' => $notice->id,
            'organization_id' => $notice->organizationId,
            'invoice_id' => $notice->invoiceId,
            'invoice_number' => $notice->invoiceNumber,
            'client_id' => $notice->clientId,
            'recipient_email' => $notice->recipientEmail,
            'outstanding_at_send_cents' => $notice->outstandingCents,
            'due_at' => $notice->dueAt,
            'channel' => $notice->channel,
            'template_version' => $notice->templateVersion,
            'sent_by' => $notice->sentBy,
            'sent_at' => $notice->sentAt,
        ];
    }
}
