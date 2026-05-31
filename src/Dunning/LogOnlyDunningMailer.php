<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

final readonly class LogOnlyDunningMailer implements DunningMailerInterface
{
    public function channel(): string
    {
        return 'log';
    }

    public function send(DunningMailPayload $payload): void
    {
        error_log(sprintf(
            '[DunningNotice #%d] org=%d invoice=%d to=%s subject=%s',
            $payload->dunningNoticeId,
            $payload->organizationId,
            $payload->invoiceId,
            $payload->to,
            $payload->subject,
        ));
    }
}
