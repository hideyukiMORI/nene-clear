<?php

declare(strict_types=1);

namespace NeneClear\User;

final readonly class InvitationMailPayload
{
    public function __construct(
        public int $userId,
        public ?int $organizationId,
        public string $to,
        public string $subject,
        public string $body,
        public string $acceptUrl,
    ) {
    }
}
