<?php

declare(strict_types=1);

namespace NeneClear\Tenancy;

use Nene2\Http\RequestScopedHolder;

/**
 * {@see CurrentOrganization} backed by the request-scoped holder populated by
 * {@see OrgScopeMiddleware}. Reading before the middleware ran — or when the
 * verified token carries no `org` claim — throws, so org-scoped code fails
 * closed (403) rather than silently crossing tenants or scoping to org 0.
 */
final readonly class HolderCurrentOrganization implements CurrentOrganization
{
    /**
     * @param RequestScopedHolder<int> $orgIdHolder
     */
    public function __construct(
        private RequestScopedHolder $orgIdHolder,
    ) {
    }

    public function id(): int
    {
        if (!$this->orgIdHolder->isSet()) {
            throw new MissingOrganizationScopeException();
        }

        return $this->orgIdHolder->get();
    }
}
