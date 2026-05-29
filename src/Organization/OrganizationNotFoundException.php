<?php

declare(strict_types=1);

namespace NeneClear\Organization;

use RuntimeException;

final class OrganizationNotFoundException extends RuntimeException
{
    public function __construct(public readonly int $id)
    {
        parent::__construct(sprintf('Organization %d was not found.', $id));
    }
}
