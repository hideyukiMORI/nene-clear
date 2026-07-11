<?php

declare(strict_types=1);

namespace NeneClear\Tenancy;

/**
 * {@see CurrentOrganization} pinned to a known organization id — for tests
 * and non-HTTP entry points that operate on an explicitly chosen tenant.
 */
final readonly class FixedOrganization implements CurrentOrganization
{
    public function __construct(private int $organizationId)
    {
    }

    public function id(): int
    {
        return $this->organizationId;
    }
}
