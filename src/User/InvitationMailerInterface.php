<?php

declare(strict_types=1);

namespace NeneClear\User;

interface InvitationMailerInterface
{
    public function send(InvitationMailPayload $payload): void;
}
