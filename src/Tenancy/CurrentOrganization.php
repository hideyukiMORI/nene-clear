<?php

declare(strict_types=1);

namespace NeneClear\Tenancy;

/**
 * The organization id that scopes the current request (#300, deal `Tenancy/`
 * shape adapted to Clear's claim-only tenancy).
 *
 * Handlers on the org-scoped data plane receive this via constructor
 * injection instead of hand-reading the JWT claim — the scope value can only
 * come from the verified token, and forgetting it is a wiring error, not a
 * silent `?? 0` empty scope.
 */
interface CurrentOrganization
{
    /**
     * @throws MissingOrganizationScopeException when the request carries no
     *         organization scope (e.g. a cross-tenant superadmin token on an
     *         org-scoped surface) — fail-close, surfaced as 403.
     */
    public function id(): int;
}
