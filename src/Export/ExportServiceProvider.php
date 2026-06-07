<?php

declare(strict_types=1);

namespace NeneClear\Export;

use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use NeneClear\BankImport\BankTransactionRepositoryInterface;
use NeneClear\Dunning\DunningNoticeRepositoryInterface;
use NeneClear\Http\ServiceResolver;
use NeneClear\Reconciliation\ClientCreditRepositoryInterface;
use NeneClear\Reconciliation\ReconciliationRepositoryInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Wires the CSV export endpoints (reconciliations, client credits, bank
 * transactions, dunning notices). Streams are built with the framework PSR-17
 * factories.
 */
final readonly class ExportServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->set(
            ExportRouteRegistrar::class,
            static fn (ContainerInterface $c): ExportRouteRegistrar => new ExportRouteRegistrar(
                new ExportReconciliationsCsvHandler(
                    ServiceResolver::get($c, ReconciliationRepositoryInterface::class),
                    ServiceResolver::get($c, BankTransactionRepositoryInterface::class),
                    ServiceResolver::get($c, ResponseFactoryInterface::class),
                    ServiceResolver::get($c, StreamFactoryInterface::class),
                ),
                new ExportClientCreditsCsvHandler(
                    ServiceResolver::get($c, ClientCreditRepositoryInterface::class),
                    ServiceResolver::get($c, ResponseFactoryInterface::class),
                    ServiceResolver::get($c, StreamFactoryInterface::class),
                ),
                new ExportBankTransactionsCsvHandler(
                    ServiceResolver::get($c, BankTransactionRepositoryInterface::class),
                    ServiceResolver::get($c, ResponseFactoryInterface::class),
                    ServiceResolver::get($c, StreamFactoryInterface::class),
                ),
                new ExportDunningNoticesCsvHandler(
                    ServiceResolver::get($c, DunningNoticeRepositoryInterface::class),
                    ServiceResolver::get($c, ResponseFactoryInterface::class),
                    ServiceResolver::get($c, StreamFactoryInterface::class),
                ),
            ),
        );
    }
}
