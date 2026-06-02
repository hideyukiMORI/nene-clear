<?php

declare(strict_types=1);

namespace NeneClear\User;

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
 * Wires the User (operator account) domain: repository, use cases, route
 * registrar and domain exception handlers.
 */
final readonly class UserServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                UserRepositoryInterface::class,
                static fn (ContainerInterface $c): UserRepositoryInterface => new PdoUserRepository(
                    ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
                ),
            )
            ->set(
                ListUsersUseCaseInterface::class,
                static fn (ContainerInterface $c): ListUsersUseCaseInterface => new ListUsersUseCase(
                    ServiceResolver::get($c, UserRepositoryInterface::class),
                ),
            )
            ->set(
                GetUserByIdUseCaseInterface::class,
                static fn (ContainerInterface $c): GetUserByIdUseCaseInterface => new GetUserByIdUseCase(
                    ServiceResolver::get($c, UserRepositoryInterface::class),
                ),
            )
            ->set(
                CreateUserUseCaseInterface::class,
                static fn (ContainerInterface $c): CreateUserUseCaseInterface => new CreateUserUseCase(
                    ServiceResolver::get($c, UserRepositoryInterface::class),
                    ServiceResolver::get($c, AuditEventRepositoryInterface::class),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                UpdateUserUseCaseInterface::class,
                static fn (ContainerInterface $c): UpdateUserUseCaseInterface => new UpdateUserUseCase(
                    ServiceResolver::get($c, UserRepositoryInterface::class),
                    ServiceResolver::get($c, AuditEventRepositoryInterface::class),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                DeleteUserUseCaseInterface::class,
                static fn (ContainerInterface $c): DeleteUserUseCaseInterface => new DeleteUserUseCase(
                    ServiceResolver::get($c, UserRepositoryInterface::class),
                    ServiceResolver::get($c, AuditEventRepositoryInterface::class),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                UserRouteRegistrar::class,
                static fn (ContainerInterface $c): UserRouteRegistrar => new UserRouteRegistrar(
                    new ListUsersHandler(
                        ServiceResolver::get($c, ListUsersUseCaseInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new CreateUserHandler(
                        ServiceResolver::get($c, CreateUserUseCaseInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new GetUserByIdHandler(
                        ServiceResolver::get($c, GetUserByIdUseCaseInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new UpdateUserHandler(
                        ServiceResolver::get($c, UpdateUserUseCaseInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new DeleteUserHandler(
                        ServiceResolver::get($c, DeleteUserUseCaseInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                ),
            )
            ->set(
                UserNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): UserNotFoundExceptionHandler => new UserNotFoundExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            )
            ->set(
                UserAlreadyExistsExceptionHandler::class,
                static fn (ContainerInterface $c): UserAlreadyExistsExceptionHandler => new UserAlreadyExistsExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            )
            ->set(
                RoleNotAssignableExceptionHandler::class,
                static fn (ContainerInterface $c): RoleNotAssignableExceptionHandler => new RoleNotAssignableExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            );
    }
}
