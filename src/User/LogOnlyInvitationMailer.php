<?php

declare(strict_types=1);

namespace NeneClear\User;

use Psr\Log\LoggerInterface;

/**
 * Fallback mailer used when no SMTP host is configured. The invitation link is
 * written to the PSR-3 logger so the onboarding flow is exercisable in dev
 * (and visible in Mailpit once SMTP is configured).
 */
final readonly class LogOnlyInvitationMailer implements InvitationMailerInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function send(InvitationMailPayload $payload): void
    {
        $this->logger->info('User invitation delivered to the log channel (no SMTP configured).', [
            'userId' => $payload->userId,
            'organizationId' => $payload->organizationId,
            'to' => $payload->to,
            'subject' => $payload->subject,
            'body' => $payload->body,
        ]);
    }
}
