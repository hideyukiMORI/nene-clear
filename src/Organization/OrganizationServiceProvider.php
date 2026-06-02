<?php

declare(strict_types=1);

namespace NeneClear\Organization;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Http\ClockInterface;
use Nene2\Http\JsonResponseFactory;
use NeneClear\Audit\AuditEventRepositoryInterface;
use NeneClear\Http\ServiceResolver;
use NeneClear\I18n\LocalizedProblemDetailsFactory;
use Psr\Container\ContainerInterface;

/**
 * Wires the Organization (tenant) domain: repository, use cases, HTTP route
 * registrar and domain exception handlers (NENE2 `dependency-injection.md`).
 */
final readonly class OrganizationServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                OrganizationRepositoryInterface::class,
                static fn (ContainerInterface $c): OrganizationRepositoryInterface => new PdoOrganizationRepository(
                    ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
                ),
            )
            ->set(
                ListOrganizationsUseCaseInterface::class,
                static fn (ContainerInterface $c): ListOrganizationsUseCaseInterface => new ListOrganizationsUseCase(
                    ServiceResolver::get($c, OrganizationRepositoryInterface::class),
                ),
            )
            ->set(
                GetOrganizationByIdUseCaseInterface::class,
                static fn (ContainerInterface $c): GetOrganizationByIdUseCaseInterface => new GetOrganizationByIdUseCase(
                    ServiceResolver::get($c, OrganizationRepositoryInterface::class),
                ),
            )
            ->set(
                CreateOrganizationUseCaseInterface::class,
                static fn (ContainerInterface $c): CreateOrganizationUseCaseInterface => new CreateOrganizationUseCase(
                    ServiceResolver::get($c, OrganizationRepositoryInterface::class),
                    ServiceResolver::get($c, AuditEventRepositoryInterface::class),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                DeleteOrganizationUseCaseInterface::class,
                static fn (ContainerInterface $c): DeleteOrganizationUseCaseInterface => new DeleteOrganizationUseCase(
                    ServiceResolver::get($c, OrganizationRepositoryInterface::class),
                    ServiceResolver::get($c, AuditEventRepositoryInterface::class),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                OrganizationRouteRegistrar::class,
                static fn (ContainerInterface $c): OrganizationRouteRegistrar => new OrganizationRouteRegistrar(
                    new ListOrganizationsHandler(
                        ServiceResolver::get($c, ListOrganizationsUseCaseInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new CreateOrganizationHandler(
                        ServiceResolver::get($c, CreateOrganizationUseCaseInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new GetOrganizationByIdHandler(
                        ServiceResolver::get($c, GetOrganizationByIdUseCaseInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new DeleteOrganizationHandler(
                        ServiceResolver::get($c, DeleteOrganizationUseCaseInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                ),
            )
            ->set(
                OrganizationNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): OrganizationNotFoundExceptionHandler => new OrganizationNotFoundExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            )
            ->set(
                OrganizationAlreadyExistsExceptionHandler::class,
                static fn (ContainerInterface $c): OrganizationAlreadyExistsExceptionHandler => new OrganizationAlreadyExistsExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            );
    }
}
