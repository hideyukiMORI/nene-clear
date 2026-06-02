<?php

declare(strict_types=1);

namespace NeneClear\Organization;

final readonly class CreateOrganizationInput
{
    public function __construct(
        public string $slug,
        public string $name,
        public int $actorUserId,
    ) {
    }
}
