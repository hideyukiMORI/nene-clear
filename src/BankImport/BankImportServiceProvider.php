<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Http\ClockInterface;
use Nene2\Http\JsonResponseFactory;
use NeneClear\Audit\AuditRecorder;
use NeneClear\Audit\AuditRecorderInterface;
use NeneClear\Audit\PdoAuditEventRepository;
use NeneClear\Http\ServiceResolver;
use NeneClear\I18n\LocalizedProblemDetailsFactory;
use NeneClear\Security\Encryptor;
use Psr\Container\ContainerInterface;

/**
 * Wires bank CSV import: account profiles, import batches, transactions,
 * the import/reverse use cases, the route registrar and exception handlers.
 */
final readonly class BankImportServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                BankAccountRepositoryInterface::class,
                static fn (ContainerInterface $c): BankAccountRepositoryInterface => new PdoBankAccountRepository(
                    ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
                    ServiceResolver::get($c, Encryptor::class),
                ),
            )
            ->set(
                BankImportBatchRepositoryInterface::class,
                static fn (ContainerInterface $c): BankImportBatchRepositoryInterface => new PdoBankImportBatchRepository(
                    ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
                ),
            )
            ->set(
                BankTransactionRepositoryInterface::class,
                static fn (ContainerInterface $c): BankTransactionRepositoryInterface => new PdoBankTransactionRepository(
                    ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
                ),
            )
            ->set(
                ImportBankCsvUseCaseInterface::class,
                static fn (ContainerInterface $c): ImportBankCsvUseCaseInterface => new ImportBankCsvUseCase(
                    ServiceResolver::get($c, DatabaseTransactionManagerInterface::class),
                    static fn (DatabaseQueryExecutorInterface $tx): BankAccountRepositoryInterface => new PdoBankAccountRepository($tx, ServiceResolver::get($c, Encryptor::class)),
                    static fn (DatabaseQueryExecutorInterface $tx): BankImportBatchRepositoryInterface => new PdoBankImportBatchRepository($tx),
                    static fn (DatabaseQueryExecutorInterface $tx): BankTransactionRepositoryInterface => new PdoBankTransactionRepository($tx),
                    static fn (DatabaseQueryExecutorInterface $tx): AuditRecorderInterface => new AuditRecorder(new PdoAuditEventRepository($tx)),
                    new BankCsvParser(),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                ReverseBankImportBatchUseCaseInterface::class,
                static fn (ContainerInterface $c): ReverseBankImportBatchUseCaseInterface => new ReverseBankImportBatchUseCase(
                    ServiceResolver::get($c, DatabaseTransactionManagerInterface::class),
                    static fn (DatabaseQueryExecutorInterface $tx): BankImportBatchRepositoryInterface => new PdoBankImportBatchRepository($tx),
                    static fn (DatabaseQueryExecutorInterface $tx): BankTransactionRepositoryInterface => new PdoBankTransactionRepository($tx),
                    static fn (DatabaseQueryExecutorInterface $tx): AuditRecorderInterface => new AuditRecorder(new PdoAuditEventRepository($tx)),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                BankImportRouteRegistrar::class,
                static fn (ContainerInterface $c): BankImportRouteRegistrar => new BankImportRouteRegistrar(
                    new ImportBankCsvHandler(
                        ServiceResolver::get($c, ImportBankCsvUseCaseInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new ListBankImportBatchesHandler(
                        ServiceResolver::get($c, BankImportBatchRepositoryInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new ReverseBankImportBatchHandler(
                        ServiceResolver::get($c, ReverseBankImportBatchUseCaseInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new ListBankTransactionsHandler(
                        ServiceResolver::get($c, BankTransactionRepositoryInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new ListUnmatchedTransactionsHandler(
                        ServiceResolver::get($c, BankTransactionRepositoryInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new GetBankTransactionByIdHandler(
                        ServiceResolver::get($c, BankTransactionRepositoryInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                ),
            )
            ->set(
                DuplicateBankImportExceptionHandler::class,
                static fn (ContainerInterface $c): DuplicateBankImportExceptionHandler => new DuplicateBankImportExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            )
            ->set(
                BankAccountNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): BankAccountNotFoundExceptionHandler => new BankAccountNotFoundExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            )
            ->set(
                InvalidBankCsvExceptionHandler::class,
                static fn (ContainerInterface $c): InvalidBankCsvExceptionHandler => new InvalidBankCsvExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            )
            ->set(
                BankTransactionNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): BankTransactionNotFoundExceptionHandler => new BankTransactionNotFoundExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            )
            ->set(
                BankImportBatchNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): BankImportBatchNotFoundExceptionHandler => new BankImportBatchNotFoundExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            )
            ->set(
                BankImportBatchAlreadyReversedExceptionHandler::class,
                static fn (ContainerInterface $c): BankImportBatchAlreadyReversedExceptionHandler => new BankImportBatchAlreadyReversedExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            )
            ->set(
                BankImportBatchHasMatchedLinesExceptionHandler::class,
                static fn (ContainerInterface $c): BankImportBatchHasMatchedLinesExceptionHandler => new BankImportBatchHasMatchedLinesExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            );
    }
}
