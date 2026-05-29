<?php

declare(strict_types=1);

namespace NeneClear\Organization;

final readonly class CreateOrganizationOutput
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
    ) {
    }
}
