<?php

declare(strict_types=1);

namespace NeneClear\User;

interface AcceptInvitationUseCaseInterface
{
    /**
     * Consume an invitation: set the user's password and activate the account.
     *
     * @throws InvitationInvalidException unknown / already-accepted token
     * @throws InvitationExpiredException token past its expiry
     */
    public function execute(AcceptInvitationInput $input): User;
}
