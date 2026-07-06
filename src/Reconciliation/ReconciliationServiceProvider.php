<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Http\ClockInterface;
use Nene2\Http\JsonResponseFactory;
use NeneClear\BankImport\BankTransactionRepositoryInterface;
use NeneClear\BankImport\PdoBankTransactionRepository;
use NeneClear\Http\ServiceResolver;
use NeneClear\I18n\LocalizedProblemDetailsFactory;
use NeneClear\InvoiceUpstream\InvoiceUpstreamClientInterface;
use NeneClear\Receivable\ManualReceivableRepositoryInterface;
use NeneClear\Receivable\PdoManualReceivableRepository;
use Psr\Container\ContainerInterface;

/**
 * Wires reconciliation (match proposal/confirmation/reversal) and client
 * credit application, including allocation use cases and exception handlers.
 */
final readonly class ReconciliationServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                ReconciliationRepositoryInterface::class,
                static fn (ContainerInterface $c): ReconciliationRepositoryInterface => new PdoReconciliationRepository(
                    ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
                ),
            )
            ->set(
                ClientCreditRepositoryInterface::class,
                static fn (ContainerInterface $c): ClientCreditRepositoryInterface => new PdoClientCreditRepository(
                    ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
                ),
            )
            ->set(
                ProposeMatchUseCaseInterface::class,
                static fn (ContainerInterface $c): ProposeMatchUseCaseInterface => new ProposeMatchUseCase(
                    ServiceResolver::get($c, BankTransactionRepositoryInterface::class),
                    ServiceResolver::get($c, InvoiceUpstreamClientInterface::class),
                    ServiceResolver::get($c, ManualReceivableRepositoryInterface::class),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                ConfirmMatchUseCaseInterface::class,
                static fn (ContainerInterface $c): ConfirmMatchUseCaseInterface => new ConfirmMatchUseCase(
                    ServiceResolver::get($c, DatabaseTransactionManagerInterface::class),
                    ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
                    static fn (DatabaseQueryExecutorInterface $tx): BankTransactionRepositoryInterface => new PdoBankTransactionRepository($tx),
                    static fn (DatabaseQueryExecutorInterface $tx): ReconciliationRepositoryInterface => new PdoReconciliationRepository($tx),
                    static fn (DatabaseQueryExecutorInterface $tx): ClientCreditRepositoryInterface => new PdoClientCreditRepository($tx),
                    static fn (DatabaseQueryExecutorInterface $tx): ManualReceivableRepositoryInterface => new PdoManualReceivableRepository($tx),
                    ServiceResolver::get($c, InvoiceUpstreamClientInterface::class),
                    ServiceResolver::get($c, AuditRecorderFactoryInterface::class),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                ReverseReconciliationUseCaseInterface::class,
                static fn (ContainerInterface $c): ReverseReconciliationUseCaseInterface => new ReverseReconciliationUseCase(
                    ServiceResolver::get($c, DatabaseTransactionManagerInterface::class),
                    ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
                    static fn (DatabaseQueryExecutorInterface $tx): ReconciliationRepositoryInterface => new PdoReconciliationRepository($tx),
                    static fn (DatabaseQueryExecutorInterface $tx): ClientCreditRepositoryInterface => new PdoClientCreditRepository($tx),
                    static fn (DatabaseQueryExecutorInterface $tx): BankTransactionRepositoryInterface => new PdoBankTransactionRepository($tx),
                    static fn (DatabaseQueryExecutorInterface $tx): ManualReceivableRepositoryInterface => new PdoManualReceivableRepository($tx),
                    ServiceResolver::get($c, InvoiceUpstreamClientInterface::class),
                    ServiceResolver::get($c, AuditRecorderFactoryInterface::class),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                ApplyCreditUseCaseInterface::class,
                static fn (ContainerInterface $c): ApplyCreditUseCaseInterface => new ApplyCreditUseCase(
                    ServiceResolver::get($c, DatabaseTransactionManagerInterface::class),
                    ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
                    static fn (DatabaseQueryExecutorInterface $tx): ClientCreditRepositoryInterface => new PdoClientCreditRepository($tx),
                    ServiceResolver::get($c, InvoiceUpstreamClientInterface::class),
                    ServiceResolver::get($c, AuditRecorderFactoryInterface::class),
                ),
            )
            ->set(
                ReconciliationRouteRegistrar::class,
                static fn (ContainerInterface $c): ReconciliationRouteRegistrar => new ReconciliationRouteRegistrar(
                    new ProposeMatchHandler(
                        ServiceResolver::get($c, ProposeMatchUseCaseInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new ConfirmMatchHandler(
                        ServiceResolver::get($c, ConfirmMatchUseCaseInterface::class),
                        ServiceResolver::get($c, ReconciliationRepositoryInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new ListReconciliationsHandler(
                        ServiceResolver::get($c, ReconciliationRepositoryInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new GetReconciliationByIdHandler(
                        ServiceResolver::get($c, ReconciliationRepositoryInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new ReverseReconciliationHandler(
                        ServiceResolver::get($c, ReverseReconciliationUseCaseInterface::class),
                        ServiceResolver::get($c, ReconciliationRepositoryInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new ListClientCreditsHandler(
                        ServiceResolver::get($c, ClientCreditRepositoryInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new ApplyCreditHandler(
                        ServiceResolver::get($c, ApplyCreditUseCaseInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                ),
            )
            ->set(
                ReconciliationNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): ReconciliationNotFoundExceptionHandler => new ReconciliationNotFoundExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            )
            ->set(
                ReconciliationAlreadyReversedExceptionHandler::class,
                static fn (ContainerInterface $c): ReconciliationAlreadyReversedExceptionHandler => new ReconciliationAlreadyReversedExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            )
            ->set(
                ClientCreditNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): ClientCreditNotFoundExceptionHandler => new ClientCreditNotFoundExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            )
            ->set(
                CreditExceedsRemainingExceptionHandler::class,
                static fn (ContainerInterface $c): CreditExceedsRemainingExceptionHandler => new CreditExceedsRemainingExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            )
            ->set(
                AllocationExceedsOutstandingExceptionHandler::class,
                static fn (ContainerInterface $c): AllocationExceedsOutstandingExceptionHandler => new AllocationExceedsOutstandingExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            )
            ->set(
                BankTransactionNotMatchableExceptionHandler::class,
                static fn (ContainerInterface $c): BankTransactionNotMatchableExceptionHandler => new BankTransactionNotMatchableExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            );
    }
}
