<?php

declare(strict_types=1);

namespace NeneClear\User;

/**
 * Fallback mailer used when no SMTP host is configured. The invitation link is
 * written to the error log so the onboarding flow is exercisable in dev
 * (and visible in Mailpit once SMTP is configured).
 */
final readonly class LogOnlyInvitationMailer implements InvitationMailerInterface
{
    public function send(InvitationMailPayload $payload): void
    {
        error_log(sprintf(
            '[UserInvitation user=%d org=%s] to=%s subject=%s body=%s',
            $payload->userId,
            $payload->organizationId ?? 'null',
            $payload->to,
            $payload->subject,
            $payload->body,
        ));
    }
}
