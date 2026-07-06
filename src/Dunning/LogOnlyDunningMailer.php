<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Psr\Log\LoggerInterface;

/**
 * Fallback mailer used when no SMTP host is configured. The dunning notice is
 * written to the PSR-3 logger so the flow is exercisable in dev without an SMTP
 * server, correlated to the request like every other framework log record.
 */
final readonly class LogOnlyDunningMailer implements DunningMailerInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function channel(): string
    {
        return 'log';
    }

    public function send(DunningMailPayload $payload): void
    {
        $this->logger->info('Dunning notice delivered to the log channel (no SMTP configured).', [
            'dunningNoticeId' => $payload->dunningNoticeId,
            'organizationId' => $payload->organizationId,
            'invoiceId' => $payload->invoiceId,
            'to' => $payload->to,
            'subject' => $payload->subject,
        ]);
    }
}
