<?php

declare(strict_types=1);

namespace NeneClear\Organization;

use RuntimeException;

final class OrganizationAlreadyExistsException extends RuntimeException
{
    public function __construct(public readonly string $slug)
    {
        parent::__construct(sprintf('An organization with slug "%s" already exists.', $slug));
    }
}
