<?php

declare(strict_types=1);

namespace NeneClear\User;

interface GetInvitationUseCaseInterface
{
    /**
     * Validate a raw invitation token and return the invitee's e-mail.
     *
     * @throws InvitationInvalidException unknown / already-accepted token
     * @throws InvitationExpiredException token past its expiry
     */
    public function execute(string $rawToken): string;
}
