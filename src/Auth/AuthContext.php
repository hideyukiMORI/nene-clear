<?php

declare(strict_types=1);

namespace NeneClear\Auth;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Reads the authenticated principal from the JWT claims that
 * {@see \Nene2\Auth\BearerTokenMiddleware} placed on the request.
 */
final readonly class AuthContext
{
    /**
     * The caller's organization id, or null for a cross-tenant superadmin.
     */
    public static function organizationId(ServerRequestInterface $request): ?int
    {
        $claims = (array) $request->getAttribute('nene2.auth.claims', []);
        $org = $claims['org'] ?? null;

        return is_int($org) ? $org : (is_numeric($org) ? (int) $org : null);
    }

    public static function role(ServerRequestInterface $request): ?Role
    {
        $claims = (array) $request->getAttribute('nene2.auth.claims', []);
        $role = $claims['role'] ?? null;

        return is_string($role) ? Role::tryFrom($role) : null;
    }
}
