<?php

declare(strict_types=1);

namespace NeneClear\Auth;

use Nene2\Auth\BearerTokenMiddleware;
use Nene2\Auth\TokenVerifierInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\ClockInterface;
use Nene2\Http\JsonResponseFactory;
use NeneClear\Audit\AuditEventRepositoryInterface;
use NeneClear\Http\ServiceResolver;
use NeneClear\I18n\LocalizedProblemDetailsFactory;
use NeneClear\User\UserRepositoryInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Wires authentication: login (use case, throttle, handler), the current-user
 * endpoint, auth exception handlers, and the bearer + capability middleware
 * stack (NENE2 `authentication-boundary.md`, ADR 0006/0008).
 */
final readonly class AuthServiceProvider implements ServiceProviderInterface
{
    /** Container key for the ordered authentication middleware stack. */
    public const string AUTH_MIDDLEWARE = 'nene_clear.auth_middleware';

    /**
     * Public paths that never require a bearer token.
     *
     * @var list<string>
     */
    private const array PUBLIC_PATHS = ['/', '/health', '/machine/health', '/admin/auth/login'];

    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                LoginThrottleInterface::class,
                static fn (ContainerInterface $c): LoginThrottleInterface => new PdoLoginThrottle(
                    ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                LoginUseCaseInterface::class,
                static fn (ContainerInterface $c): LoginUseCaseInterface => new LoginUseCase(
                    ServiceResolver::get($c, UserRepositoryInterface::class),
                    ServiceResolver::get($c, TokenIssuerInterface::class),
                    ServiceResolver::get($c, AuditEventRepositoryInterface::class),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                AuthRouteRegistrar::class,
                static fn (ContainerInterface $c): AuthRouteRegistrar => new AuthRouteRegistrar(
                    new LoginHandler(
                        ServiceResolver::get($c, LoginUseCaseInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                        ServiceResolver::get($c, LoginThrottleInterface::class),
                    ),
                    new GetCurrentUserHandler(
                        ServiceResolver::get($c, UserRepositoryInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                        ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                    ),
                ),
            )
            ->set(
                InvalidCredentialsExceptionHandler::class,
                static fn (ContainerInterface $c): InvalidCredentialsExceptionHandler => new InvalidCredentialsExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            )
            ->set(
                TooManyLoginAttemptsExceptionHandler::class,
                static fn (ContainerInterface $c): TooManyLoginAttemptsExceptionHandler => new TooManyLoginAttemptsExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            )
            ->set(
                self::AUTH_MIDDLEWARE,
                static function (ContainerInterface $c): array {
                    // Bearer verification uses the framework (non-localized) Problem
                    // Details factory; capability checks use the localized one.
                    $bearer = new BearerTokenMiddleware(
                        ServiceResolver::get($c, ProblemDetailsResponseFactory::class),
                        ServiceResolver::get($c, TokenVerifierInterface::class),
                        excludedPaths: self::PUBLIC_PATHS,
                    );

                    $capabilities = new CapabilityMiddleware(
                        ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                        [
                            '/admin/organizations' => CapabilityRule::same(Capability::ManageOrganizations),
                            '/admin/users' => CapabilityRule::same(Capability::ManageUsers),
                            '/admin/audit-events' => CapabilityRule::same(Capability::ManageUsers),
                            '/admin/bank-import-batches' => new CapabilityRule(read: Capability::ViewReconciliation, write: Capability::ManageReconciliation),
                            '/admin/bank-transactions' => new CapabilityRule(read: Capability::ViewReconciliation, write: Capability::ManageReconciliation),
                            '/admin/reconciliations' => new CapabilityRule(read: Capability::ViewReconciliation, write: Capability::ManageReconciliation),
                            '/admin/client-credits' => new CapabilityRule(read: Capability::ViewReconciliation, write: Capability::ManageReconciliation),
                            '/admin/clear-settings' => CapabilityRule::same(Capability::ManageClearSettings),
                            '/admin/dunning-notices' => new CapabilityRule(read: Capability::ViewReconciliation, write: Capability::SendDunning),
                            '/admin/invoices' => CapabilityRule::same(Capability::ViewReconciliation),
                            '/admin/export' => CapabilityRule::same(Capability::ViewReconciliation),
                            '/admin/dunning-pauses' => new CapabilityRule(read: Capability::ViewReconciliation, write: Capability::SendDunning),
                        ],
                    );

                    /** @var list<MiddlewareInterface> $stack */
                    $stack = [$bearer, $capabilities];

                    return $stack;
                },
            );
    }
}
