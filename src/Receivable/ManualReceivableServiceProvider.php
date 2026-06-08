<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

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
use Psr\Container\ContainerInterface;

/**
 * Wires the manual-receivables domain (ADR 0014): repository, CRUD use cases,
 * route registrar, and domain exception handlers. CSV import (Issue 3) and
 * reconciliation/dunning support (Issues 4–5) register here later.
 */
final readonly class ManualReceivableServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                ManualReceivableRepositoryInterface::class,
                static fn (ContainerInterface $c): ManualReceivableRepositoryInterface => new PdoManualReceivableRepository(
                    ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
                ),
            )
            ->set(
                ListManualReceivablesUseCaseInterface::class,
                static fn (ContainerInterface $c): ListManualReceivablesUseCaseInterface => new ListManualReceivablesUseCase(
                    ServiceResolver::get($c, ManualReceivableRepositoryInterface::class),
                ),
            )
            ->set(
                GetManualReceivableByIdUseCaseInterface::class,
                static fn (ContainerInterface $c): GetManualReceivableByIdUseCaseInterface => new GetManualReceivableByIdUseCase(
                    ServiceResolver::get($c, ManualReceivableRepositoryInterface::class),
                ),
            )
            ->set(
                CreateManualReceivableUseCaseInterface::class,
                static fn (ContainerInterface $c): CreateManualReceivableUseCaseInterface => new CreateManualReceivableUseCase(
                    ServiceResolver::get($c, DatabaseTransactionManagerInterface::class),
                    static fn (DatabaseQueryExecutorInterface $tx): ManualReceivableRepositoryInterface => new PdoManualReceivableRepository($tx),
                    static fn (DatabaseQueryExecutorInterface $tx): AuditRecorderInterface => new AuditRecorder(new PdoAuditEventRepository($tx)),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                UpdateManualReceivableUseCaseInterface::class,
                static fn (ContainerInterface $c): UpdateManualReceivableUseCaseInterface => new UpdateManualReceivableUseCase(
                    ServiceResolver::get($c, DatabaseTransactionManagerInterface::class),
                    static fn (DatabaseQueryExecutorInterface $tx): ManualReceivableRepositoryInterface => new PdoManualReceivableRepository($tx),
                    static fn (DatabaseQueryExecutorInterface $tx): AuditRecorderInterface => new AuditRecorder(new PdoAuditEventRepository($tx)),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                CancelManualReceivableUseCaseInterface::class,
                static fn (ContainerInterface $c): CancelManualReceivableUseCaseInterface => new CancelManualReceivableUseCase(
                    ServiceResolver::get($c, DatabaseTransactionManagerInterface::class),
                    static fn (DatabaseQueryExecutorInterface $tx): ManualReceivableRepositoryInterface => new PdoManualReceivableRepository($tx),
                    static fn (DatabaseQueryExecutorInterface $tx): AuditRecorderInterface => new AuditRecorder(new PdoAuditEventRepository($tx)),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                ImportManualReceivablesUseCaseInterface::class,
                static fn (ContainerInterface $c): ImportManualReceivablesUseCaseInterface => new ImportManualReceivablesUseCase(
                    new ManualReceivableCsvParser(),
                    ServiceResolver::get($c, CreateManualReceivableUseCaseInterface::class),
                ),
            )
            ->set(
                ManualReceivableRouteRegistrar::class,
                static fn (ContainerInterface $c): ManualReceivableRouteRegistrar => new ManualReceivableRouteRegistrar(
                    new ListManualReceivablesHandler(
                        ServiceResolver::get($c, ListManualReceivablesUseCaseInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new CreateManualReceivableHandler(
                        ServiceResolver::get($c, CreateManualReceivableUseCaseInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new GetManualReceivableHandler(
                        ServiceResolver::get($c, GetManualReceivableByIdUseCaseInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new UpdateManualReceivableHandler(
                        ServiceResolver::get($c, UpdateManualReceivableUseCaseInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new CancelManualReceivableHandler(
                        ServiceResolver::get($c, CancelManualReceivableUseCaseInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new ImportManualReceivablesHandler(
                        ServiceResolver::get($c, ImportManualReceivablesUseCaseInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                ),
            )
            ->set(
                ManualReceivableNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): ManualReceivableNotFoundExceptionHandler => new ManualReceivableNotFoundExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            )
            ->set(
                ManualReceivableAlreadyExistsExceptionHandler::class,
                static fn (ContainerInterface $c): ManualReceivableAlreadyExistsExceptionHandler => new ManualReceivableAlreadyExistsExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            )
            ->set(
                ManualReceivableCancelledExceptionHandler::class,
                static fn (ContainerInterface $c): ManualReceivableCancelledExceptionHandler => new ManualReceivableCancelledExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            );
    }
}
