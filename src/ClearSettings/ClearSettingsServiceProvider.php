<?php

declare(strict_types=1);

namespace NeneClear\ClearSettings;

use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Http\ClockInterface;
use Nene2\Http\JsonResponseFactory;
use NeneClear\BankImport\BankAccountRepositoryInterface;
use NeneClear\BankImport\PdoBankAccountRepository;
use NeneClear\Http\ServiceResolver;
use NeneClear\InvoiceUpstream\InvoiceUpstreamClientInterface;
use NeneClear\Security\Encryptor;
use NeneClear\Tenancy\CurrentOrganization;
use Psr\Container\ContainerInterface;

/**
 * Wires per-organization Clear settings (upstream config, dunning interval,
 * bank-account profiles) and the upstream-connection test endpoint.
 */
final readonly class ClearSettingsServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                ClearSettingsRepositoryInterface::class,
                static fn (ContainerInterface $c): ClearSettingsRepositoryInterface => new PdoClearSettingsRepository(
                    ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
                    ServiceResolver::get($c, BankAccountRepositoryInterface::class),
                ),
            )
            ->set(
                UpdateClearSettingsUseCaseInterface::class,
                static fn (ContainerInterface $c): UpdateClearSettingsUseCaseInterface => new UpdateClearSettingsUseCase(
                    ServiceResolver::get($c, DatabaseTransactionManagerInterface::class),
                    static fn (DatabaseQueryExecutorInterface $tx): ClearSettingsRepositoryInterface => new PdoClearSettingsRepository($tx, new PdoBankAccountRepository($tx, ServiceResolver::get($c, Encryptor::class))),
                    static fn (DatabaseQueryExecutorInterface $tx): BankAccountRepositoryInterface => new PdoBankAccountRepository($tx, ServiceResolver::get($c, Encryptor::class)),
                    ServiceResolver::get($c, AuditRecorderFactoryInterface::class),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                ClearSettingsRouteRegistrar::class,
                static fn (ContainerInterface $c): ClearSettingsRouteRegistrar => new ClearSettingsRouteRegistrar(
                    new GetClearSettingsHandler(
                        ServiceResolver::get($c, ClearSettingsRepositoryInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                        ServiceResolver::get($c, CurrentOrganization::class),
                    ),
                    new UpdateClearSettingsHandler(
                        ServiceResolver::get($c, UpdateClearSettingsUseCaseInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                        ServiceResolver::get($c, CurrentOrganization::class),
                    ),
                    new TestUpstreamConnectionHandler(
                        ServiceResolver::get($c, InvoiceUpstreamClientInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                        ServiceResolver::get($c, CurrentOrganization::class),
                    ),
                ),
            );
    }
}
