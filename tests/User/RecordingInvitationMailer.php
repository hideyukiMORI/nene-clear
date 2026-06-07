<?php

declare(strict_types=1);

namespace NeneClear\Tests\User;

use NeneClear\User\InvitationMailerInterface;
use NeneClear\User\InvitationMailPayload;

/** Captures sent invitation payloads so tests can assert on / replay them. */
final class RecordingInvitationMailer implements InvitationMailerInterface
{
    /** @var list<InvitationMailPayload> */
    public array $sent = [];

    public function send(InvitationMailPayload $payload): void
    {
        $this->sent[] = $payload;
    }
}
