<?php

declare(strict_types=1);

namespace NeneClear\Tenancy;

use Nene2\Http\RequestScopedHolder;
use NeneClear\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Populates the request-scoped organization holder from the verified bearer
 * token's `org` claim (#300). Runs after {@see \Nene2\Auth\BearerTokenMiddleware}
 * (claims must be verified) and before the capability middleware.
 *
 * Users belong to exactly one organization, so the signed claim is
 * authoritative — it cannot be spoofed by a header, which also keeps a
 * disposable-demo session locked to its own throwaway org. When the token
 * carries no `org` claim (cross-tenant superadmin) or the route is public,
 * the holder stays unset and org-scoped code fails closed downstream
 * ({@see HolderCurrentOrganization} → 403).
 */
final readonly class OrgScopeMiddleware implements MiddlewareInterface
{
    /**
     * @param RequestScopedHolder<int> $orgIdHolder
     */
    public function __construct(
        private RequestScopedHolder $orgIdHolder,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $organizationId = AuthContext::organizationId($request);

        if ($organizationId !== null) {
            $this->orgIdHolder->set($organizationId);
        }

        return $handler->handle($request);
    }
}
