<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Auth\TotpAuthenticator as Rfc6238Totp;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Http\ClockInterface;
use Nene2\Http\JsonResponseFactory;
use NeneClear\Http\ServiceResolver;
use NeneClear\I18n\LocalizedProblemDetailsFactory;
use NeneClear\Security\Encryptor;
use NeneClear\User\UserRepositoryInterface;
use Psr\Container\ContainerInterface;

/**
 * Wires self-service TOTP MFA enrolment (#195): the framework RFC 6238
 * primitive (#292), the encrypted
 * secret / used-step / recovery-code stores, the authenticator (verify with
 * lockout + replay), the setup/enable/status/disable use cases + handlers, and
 * the TOTP exception → Problem Details handlers.
 *
 * Code verification uses repositories on the main executor (its failure/replay
 * writes auto-commit), while each use case's business writes + audit run in a
 * transaction.
 */
final readonly class MfaServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(Rfc6238Totp::class, static fn (ContainerInterface $c): Rfc6238Totp => new Rfc6238Totp())
            ->set(RecoveryCodeService::class, static fn (ContainerInterface $c): RecoveryCodeService => new RecoveryCodeService())
            ->set(
                TotpAuthenticator::class,
                static fn (ContainerInterface $c): TotpAuthenticator => new TotpAuthenticator(
                    new PdoTotpSecretRepository(
                        ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
                        ServiceResolver::get($c, Encryptor::class),
                    ),
                    new PdoUsedTotpStepRepository(ServiceResolver::get($c, DatabaseQueryExecutorInterface::class)),
                    ServiceResolver::get($c, Rfc6238Totp::class),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                SetupTotpUseCase::class,
                static fn (ContainerInterface $c): SetupTotpUseCase => new SetupTotpUseCase(
                    ServiceResolver::get($c, DatabaseTransactionManagerInterface::class),
                    static fn (DatabaseQueryExecutorInterface $ex): TotpSecretRepositoryInterface => new PdoTotpSecretRepository($ex, ServiceResolver::get($c, Encryptor::class)),
                    static fn (DatabaseQueryExecutorInterface $ex): UsedTotpStepRepositoryInterface => new PdoUsedTotpStepRepository($ex),
                    static fn (DatabaseQueryExecutorInterface $ex): RecoveryCodeRepositoryInterface => new PdoRecoveryCodeRepository($ex),
                    ServiceResolver::get($c, UserRepositoryInterface::class),
                    ServiceResolver::get($c, Rfc6238Totp::class),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                EnableTotpUseCase::class,
                static fn (ContainerInterface $c): EnableTotpUseCase => new EnableTotpUseCase(
                    ServiceResolver::get($c, TotpAuthenticator::class),
                    ServiceResolver::get($c, DatabaseTransactionManagerInterface::class),
                    static fn (DatabaseQueryExecutorInterface $ex): TotpSecretRepositoryInterface => new PdoTotpSecretRepository($ex, ServiceResolver::get($c, Encryptor::class)),
                    static fn (DatabaseQueryExecutorInterface $ex): RecoveryCodeRepositoryInterface => new PdoRecoveryCodeRepository($ex),
                    ServiceResolver::get($c, AuditRecorderFactoryInterface::class),
                    ServiceResolver::get($c, RecoveryCodeService::class),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                DisableTotpUseCase::class,
                static fn (ContainerInterface $c): DisableTotpUseCase => new DisableTotpUseCase(
                    ServiceResolver::get($c, TotpAuthenticator::class),
                    ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
                    ServiceResolver::get($c, DatabaseTransactionManagerInterface::class),
                    static fn (DatabaseQueryExecutorInterface $ex): TotpSecretRepositoryInterface => new PdoTotpSecretRepository($ex, ServiceResolver::get($c, Encryptor::class)),
                    static fn (DatabaseQueryExecutorInterface $ex): UsedTotpStepRepositoryInterface => new PdoUsedTotpStepRepository($ex),
                    static fn (DatabaseQueryExecutorInterface $ex): RecoveryCodeRepositoryInterface => new PdoRecoveryCodeRepository($ex),
                    ServiceResolver::get($c, AuditRecorderFactoryInterface::class),
                    ServiceResolver::get($c, RecoveryCodeService::class),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                MfaRouteRegistrar::class,
                static fn (ContainerInterface $c): MfaRouteRegistrar => new MfaRouteRegistrar(
                    new SetupTotpHandler(
                        ServiceResolver::get($c, SetupTotpUseCase::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new EnableTotpHandler(
                        ServiceResolver::get($c, EnableTotpUseCase::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new GetTotpStatusHandler(
                        ServiceResolver::get($c, TotpAuthenticator::class),
                        new PdoRecoveryCodeRepository(ServiceResolver::get($c, DatabaseQueryExecutorInterface::class)),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new DisableTotpHandler(
                        ServiceResolver::get($c, DisableTotpUseCase::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                ),
            )
            ->set(
                TotpInvalidCodeExceptionHandler::class,
                static fn (ContainerInterface $c): TotpInvalidCodeExceptionHandler => new TotpInvalidCodeExceptionHandler(ServiceResolver::get($c, LocalizedProblemDetailsFactory::class)),
            )
            ->set(
                TotpLockedExceptionHandler::class,
                static fn (ContainerInterface $c): TotpLockedExceptionHandler => new TotpLockedExceptionHandler(ServiceResolver::get($c, LocalizedProblemDetailsFactory::class)),
            )
            ->set(
                TotpNotEnabledExceptionHandler::class,
                static fn (ContainerInterface $c): TotpNotEnabledExceptionHandler => new TotpNotEnabledExceptionHandler(ServiceResolver::get($c, LocalizedProblemDetailsFactory::class)),
            )
            ->set(
                TotpAlreadyEnabledExceptionHandler::class,
                static fn (ContainerInterface $c): TotpAlreadyEnabledExceptionHandler => new TotpAlreadyEnabledExceptionHandler(ServiceResolver::get($c, LocalizedProblemDetailsFactory::class)),
            );
    }
}
