<?php

declare(strict_types=1);

namespace NeneClear\Tenancy;

use RuntimeException;

/**
 * The current request carries no organization scope but reached org-scoped
 * code. Thrown by {@see HolderCurrentOrganization} (fail-close) and rendered
 * as **403 `organization-not-resolved`** — the invoice OrgGuard "inconsistent
 * token" semantics: a cross-tenant superadmin (`org = null`) token must be an
 * explicit deny on the data plane, never an implicit empty org-0 view.
 */
final class MissingOrganizationScopeException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The request carries no organization scope.');
    }
}
