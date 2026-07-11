<?php

declare(strict_types=1);

namespace NeneClear\Auth;

use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Audit\AuditRecorderInterface;
use Nene2\Auth\BearerTokenMiddleware;
use Nene2\Auth\GuardedJwtSecretResolver;
use Nene2\Auth\JwtSecretException;
use Nene2\Auth\TokenVerifierInterface;
use Nene2\Config\AppConfig;
use Nene2\Config\AppEnvironment;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\ClockInterface;
use Nene2\Http\JsonResponseFactory;
use NeneClear\ClearSettings\ClearSettingsRepositoryInterface;
use NeneClear\Http\ServiceResolver;
use NeneClear\I18n\LocalizedProblemDetailsFactory;
use NeneClear\Mfa\PdoRecoveryCodeRepository;
use NeneClear\Mfa\RecoveryCodeRepositoryInterface;
use NeneClear\Mfa\RecoveryCodeService;
use NeneClear\Mfa\TotpAuthenticator;
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
     * Development-only fallback secret, used **only** in local/test when
     * NENE2_LOCAL_JWT_SECRET is unset and the operator opted in via
     * NENE2_ALLOW_DEV_SECRET (#285). Production must set its own secret — see
     * {@see GuardedJwtSecretResolver}, which hard-fails the dev path in
     * production. This constant is public in the OSS repository, so signing
     * real tokens with it would be a full auth bypass.
     */
    private const string DEFAULT_DEV_SECRET = 'nene-clear-dev-secret';

    /**
     * Fleet-standard JWT-secret resolution (#285), fail-close preserved: an
     * unresolvable secret returns null so the composition root keeps the admin
     * surface unmounted (health-only branch in ApplicationFactory) instead of
     * booting with a predictable signing key. The development path requires an
     * explicit NENE2_ALLOW_DEV_SECRET opt-in and is never honoured in
     * production ({@see GuardedJwtSecretResolver}).
     */
    /**
     * {@see resolveJwtSecret()} over typed config — the fleet-standard
     * `GuardedJwtSecretResolver::fromConfig()` path used by the composition
     * root ({@see \NeneClear\Http\RuntimeBootstrap}).
     */
    public static function resolveJwtSecretFromConfig(AppConfig $config): ?string
    {
        try {
            return GuardedJwtSecretResolver::fromConfig($config, self::DEFAULT_DEV_SECRET);
        } catch (JwtSecretException) {
            return null;
        }
    }

    public static function resolveJwtSecret(
        string $configuredSecret,
        AppEnvironment $environment,
        bool $allowDevSecret,
    ): ?string {
        try {
            return (new GuardedJwtSecretResolver(
                configuredSecret: $configuredSecret,
                environment: $environment,
                allowDevSecret: $allowDevSecret,
                devSecret: self::DEFAULT_DEV_SECRET,
            ))->resolve();
        } catch (JwtSecretException) {
            return null;
        }
    }

    /**
     * Public paths that never require a bearer token.
     *
     * @var list<string>
     */
    private const array PUBLIC_PATHS = [
        '/', '/health', '/machine/health', '/admin/auth/login',
        // Second leg of MFA login — the user still has no session at this point.
        '/admin/auth/login/mfa',
        // Invitee has no session yet; the invitation token is the credential.
        '/admin/auth/invitation', '/admin/auth/invitation/accept',
        // Disposable-demo start (#275): creates the session — no bearer exists yet.
        // Exact-match blocklist: each DemoTemplate case needs its path listed.
        '/demo/standard',
    ];

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
                    ServiceResolver::get($c, SessionTokens::class),
                    ServiceResolver::get($c, AuditRecorderInterface::class),
                    ServiceResolver::get($c, ClockInterface::class),
                    ServiceResolver::get($c, TotpAuthenticator::class),
                    ServiceResolver::get($c, MfaChallengeTokens::class),
                ),
            )
            ->set(
                VerifyMfaLoginUseCase::class,
                static fn (ContainerInterface $c): VerifyMfaLoginUseCase => new VerifyMfaLoginUseCase(
                    ServiceResolver::get($c, MfaChallengeTokens::class),
                    ServiceResolver::get($c, UserRepositoryInterface::class),
                    ServiceResolver::get($c, TotpAuthenticator::class),
                    ServiceResolver::get($c, RecoveryCodeService::class),
                    ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
                    ServiceResolver::get($c, SessionTokens::class),
                    ServiceResolver::get($c, DatabaseTransactionManagerInterface::class),
                    static fn (DatabaseQueryExecutorInterface $ex): RecoveryCodeRepositoryInterface => new PdoRecoveryCodeRepository($ex),
                    ServiceResolver::get($c, AuditRecorderFactoryInterface::class),
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
                        ServiceResolver::get($c, ClearSettingsRepositoryInterface::class),
                    ),
                    new VerifyMfaLoginHandler(
                        ServiceResolver::get($c, VerifyMfaLoginUseCase::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
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
                MfaChallengeInvalidExceptionHandler::class,
                static fn (ContainerInterface $c): MfaChallengeInvalidExceptionHandler => new MfaChallengeInvalidExceptionHandler(
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
                            '/admin/manual-receivables' => new CapabilityRule(read: Capability::ViewReconciliation, write: Capability::ManageReconciliation),
                            '/admin/manual-receivable-imports' => new CapabilityRule(read: Capability::ViewReconciliation, write: Capability::ManageReconciliation),
                            '/admin/clear-settings' => CapabilityRule::same(Capability::ManageClearSettings),
                            '/admin/dunning-notices' => new CapabilityRule(read: Capability::ViewReconciliation, write: Capability::SendDunning),
                            '/admin/upstream/invoices' => CapabilityRule::same(Capability::ViewReconciliation),
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
