<?php

declare(strict_types=1);

namespace NeneClear\Auth;

use Nene2\Error\ProblemDetailsResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Enforces per-route capabilities (ADR 0006) after authentication.
 *
 * Runs after {@see \Nene2\Auth\BearerTokenMiddleware}, which sets the decoded
 * claims on the `nene2.auth.claims` request attribute. A request whose path
 * starts with a protected prefix requires the mapped {@see Capability}; the
 * authenticated user's `role` claim must grant it, otherwise 403.
 *
 * Paths that match no prefix are not capability-gated here (any authenticated
 * user passes); public paths are already excluded upstream.
 */
final readonly class CapabilityMiddleware implements MiddlewareInterface
{
    /**
     * @param array<string, Capability> $prefixCapabilities path prefix => required capability
     */
    public function __construct(
        private ProblemDetailsResponseFactory $problemDetails,
        private array $prefixCapabilities,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $required = $this->requiredCapability($request->getUri()->getPath() ?: '/');

        if ($required === null) {
            return $handler->handle($request);
        }

        $claims = (array) $request->getAttribute('nene2.auth.claims', []);
        $roleValue = $claims['role'] ?? null;
        $role = is_string($roleValue) ? Role::tryFrom($roleValue) : null;

        if ($role === null || !$role->has($required)) {
            return $this->problemDetails->create(
                $request,
                'insufficient-capability',
                'Insufficient Capability',
                403,
                'Your role lacks the capability required for this action.',
            );
        }

        return $handler->handle($request);
    }

    private function requiredCapability(string $path): ?Capability
    {
        foreach ($this->prefixCapabilities as $prefix => $capability) {
            if (str_starts_with($path, $prefix)) {
                return $capability;
            }
        }

        return null;
    }
}
