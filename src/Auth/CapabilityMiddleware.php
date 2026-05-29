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
 * starts with a protected prefix requires the mapped {@see CapabilityRule}: the
 * rule's read capability for safe methods (GET/HEAD/OPTIONS), its write
 * capability otherwise. The authenticated user's `role` claim must grant it,
 * otherwise 403.
 *
 * Paths that match no prefix are not capability-gated here (any authenticated
 * user passes); public paths are already excluded upstream.
 */
final readonly class CapabilityMiddleware implements MiddlewareInterface
{
    /** @var list<string> */
    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * @param array<string, CapabilityRule> $prefixRules path prefix => required capabilities
     */
    public function __construct(
        private ProblemDetailsResponseFactory $problemDetails,
        private array $prefixRules,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $rule = $this->ruleForPath($request->getUri()->getPath() ?: '/');

        if ($rule === null) {
            return $handler->handle($request);
        }

        $required = in_array(strtoupper($request->getMethod()), self::SAFE_METHODS, true)
            ? $rule->read
            : $rule->write;

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

    private function ruleForPath(string $path): ?CapabilityRule
    {
        foreach ($this->prefixRules as $prefix => $rule) {
            if (str_starts_with($path, $prefix)) {
                return $rule;
            }
        }

        return null;
    }
}
