<?php

declare(strict_types=1);

namespace NeneClear\User;

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
                UserInvitationRepositoryInterface::class,
                static fn (ContainerInterface $c): UserInvitationRepositoryInterface => new PdoUserInvitationRepository(
                    ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
                ),
            )
            ->set(
                CreateUserUseCaseInterface::class,
                static fn (ContainerInterface $c): CreateUserUseCaseInterface => new CreateUserUseCase(
                    ServiceResolver::get($c, DatabaseTransactionManagerInterface::class),
                    static fn (DatabaseQueryExecutorInterface $tx): UserRepositoryInterface => new PdoUserRepository($tx),
                    static fn (DatabaseQueryExecutorInterface $tx): AuditRecorderInterface => new AuditRecorder(new PdoAuditEventRepository($tx)),
                    static fn (DatabaseQueryExecutorInterface $tx): UserInvitationRepositoryInterface => new PdoUserInvitationRepository($tx),
                    ServiceResolver::get($c, InvitationMailerInterface::class),
                    ServiceResolver::get($c, InvitationLinkBuilder::class),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                GetInvitationUseCaseInterface::class,
                static fn (ContainerInterface $c): GetInvitationUseCaseInterface => new GetInvitationUseCase(
                    ServiceResolver::get($c, UserInvitationRepositoryInterface::class),
                    ServiceResolver::get($c, UserRepositoryInterface::class),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                AcceptInvitationUseCaseInterface::class,
                static fn (ContainerInterface $c): AcceptInvitationUseCaseInterface => new AcceptInvitationUseCase(
                    ServiceResolver::get($c, DatabaseTransactionManagerInterface::class),
                    static fn (DatabaseQueryExecutorInterface $tx): UserRepositoryInterface => new PdoUserRepository($tx),
                    static fn (DatabaseQueryExecutorInterface $tx): UserInvitationRepositoryInterface => new PdoUserInvitationRepository($tx),
                    static fn (DatabaseQueryExecutorInterface $tx): AuditRecorderInterface => new AuditRecorder(new PdoAuditEventRepository($tx)),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                UpdateUserUseCaseInterface::class,
                static fn (ContainerInterface $c): UpdateUserUseCaseInterface => new UpdateUserUseCase(
                    ServiceResolver::get($c, DatabaseTransactionManagerInterface::class),
                    static fn (DatabaseQueryExecutorInterface $tx): UserRepositoryInterface => new PdoUserRepository($tx),
                    static fn (DatabaseQueryExecutorInterface $tx): AuditRecorderInterface => new AuditRecorder(new PdoAuditEventRepository($tx)),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                DeleteUserUseCaseInterface::class,
                static fn (ContainerInterface $c): DeleteUserUseCaseInterface => new DeleteUserUseCase(
                    ServiceResolver::get($c, DatabaseTransactionManagerInterface::class),
                    static fn (DatabaseQueryExecutorInterface $tx): UserRepositoryInterface => new PdoUserRepository($tx),
                    static fn (DatabaseQueryExecutorInterface $tx): AuditRecorderInterface => new AuditRecorder(new PdoAuditEventRepository($tx)),
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
                InvitationRouteRegistrar::class,
                static fn (ContainerInterface $c): InvitationRouteRegistrar => new InvitationRouteRegistrar(
                    new GetInvitationHandler(
                        ServiceResolver::get($c, GetInvitationUseCaseInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                    new AcceptInvitationHandler(
                        ServiceResolver::get($c, AcceptInvitationUseCaseInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                ),
            )
            ->set(
                InvitationInvalidExceptionHandler::class,
                static fn (ContainerInterface $c): InvitationInvalidExceptionHandler => new InvitationInvalidExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            )
            ->set(
                InvitationExpiredExceptionHandler::class,
                static fn (ContainerInterface $c): InvitationExpiredExceptionHandler => new InvitationExpiredExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
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
