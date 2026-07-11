<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Http\ClockInterface;
use Nene2\Http\JsonResponseFactory;
use NeneClear\BankImport\PdoBankAccountRepository;
use NeneClear\ClearSettings\ClearSettingsRepositoryInterface;
use NeneClear\ClearSettings\PdoClearSettingsRepository;
use NeneClear\Http\ServiceResolver;
use NeneClear\I18n\LocalizedProblemDetailsFactory;
use NeneClear\I18n\MessageCatalog;
use NeneClear\InvoiceUpstream\InvoiceUpstreamClientInterface;
use NeneClear\Security\Encryptor;
use NeneClear\Tenancy\CurrentOrganization;
use Psr\Container\ContainerInterface;

/**
 * Wires dunning: notices, pauses, the send/pause/resume use cases, the route
 * registrar and exception handlers. The mailer is bound at the infrastructure
 * boundary (SMTP vs log-only depends on runtime config).
 */
final readonly class DunningServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                DunningNoticeRepositoryInterface::class,
                static fn (ContainerInterface $c): DunningNoticeRepositoryInterface => new PdoDunningNoticeRepository(
                    ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
                ),
            )
            ->set(
                DunningPauseRepositoryInterface::class,
                static fn (ContainerInterface $c): DunningPauseRepositoryInterface => new PdoDunningPauseRepository(
                    ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
                ),
            )
            ->set(
                SendDunningUseCaseInterface::class,
                static fn (ContainerInterface $c): SendDunningUseCaseInterface => new SendDunningUseCase(
                    ServiceResolver::get($c, DatabaseTransactionManagerInterface::class),
                    ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
                    static fn (DatabaseQueryExecutorInterface $tx): DunningNoticeRepositoryInterface => new PdoDunningNoticeRepository($tx),
                    static fn (DatabaseQueryExecutorInterface $tx): ClearSettingsRepositoryInterface => new PdoClearSettingsRepository($tx, new PdoBankAccountRepository($tx, ServiceResolver::get($c, Encryptor::class))),
                    ServiceResolver::get($c, InvoiceUpstreamClientInterface::class),
                    ServiceResolver::get($c, DunningMailerInterface::class),
                    ServiceResolver::get($c, AuditRecorderFactoryInterface::class),
                    ServiceResolver::get($c, ClockInterface::class),
                    new DunningMessageRenderer(ServiceResolver::get($c, MessageCatalog::class)),
                    static fn (DatabaseQueryExecutorInterface $tx): DunningPauseRepositoryInterface => new PdoDunningPauseRepository($tx),
                ),
            )
            ->set(
                PauseDunningUseCase::class,
                static fn (ContainerInterface $c): PauseDunningUseCase => new PauseDunningUseCase(
                    ServiceResolver::get($c, DatabaseTransactionManagerInterface::class),
                    static fn (DatabaseQueryExecutorInterface $tx): DunningPauseRepositoryInterface => new PdoDunningPauseRepository($tx),
                    ServiceResolver::get($c, AuditRecorderFactoryInterface::class),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                ResumeDunningUseCase::class,
                static fn (ContainerInterface $c): ResumeDunningUseCase => new ResumeDunningUseCase(
                    ServiceResolver::get($c, DatabaseTransactionManagerInterface::class),
                    static fn (DatabaseQueryExecutorInterface $tx): DunningPauseRepositoryInterface => new PdoDunningPauseRepository($tx),
                    ServiceResolver::get($c, AuditRecorderFactoryInterface::class),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                DunningRouteRegistrar::class,
                static fn (ContainerInterface $c): DunningRouteRegistrar => new DunningRouteRegistrar(
                    new SendDunningHandler(
                        ServiceResolver::get($c, SendDunningUseCaseInterface::class),
                        ServiceResolver::get($c, DunningNoticeRepositoryInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                        ServiceResolver::get($c, CurrentOrganization::class),
                    ),
                    new PreviewDunningNoticeHandler(
                        ServiceResolver::get($c, InvoiceUpstreamClientInterface::class),
                        new DunningMessageRenderer(ServiceResolver::get($c, MessageCatalog::class)),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                        ServiceResolver::get($c, CurrentOrganization::class),
                    ),
                    new ListDunningNoticesHandler(
                        ServiceResolver::get($c, DunningNoticeRepositoryInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                        ServiceResolver::get($c, CurrentOrganization::class),
                    ),
                    new GetDunningNoticeByIdHandler(
                        ServiceResolver::get($c, DunningNoticeRepositoryInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                        ServiceResolver::get($c, CurrentOrganization::class),
                    ),
                    new PauseDunningHandler(
                        ServiceResolver::get($c, PauseDunningUseCase::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                        ServiceResolver::get($c, CurrentOrganization::class),
                    ),
                    new ResumeDunningHandler(
                        ServiceResolver::get($c, ResumeDunningUseCase::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                        ServiceResolver::get($c, CurrentOrganization::class),
                    ),
                    new ListDunningPausesHandler(
                        ServiceResolver::get($c, DunningPauseRepositoryInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                        ServiceResolver::get($c, CurrentOrganization::class),
                    ),
                    new TestSendDunningHandler(
                        new SendTestDunningUseCase(
                            ServiceResolver::get($c, InvoiceUpstreamClientInterface::class),
                            new DunningMessageRenderer(ServiceResolver::get($c, MessageCatalog::class)),
                            ServiceResolver::get($c, DunningMailerInterface::class),
                        ),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                        ServiceResolver::get($c, CurrentOrganization::class),
                    ),
                ),
            )
            ->set(
                InvoiceAlreadyPaidExceptionHandler::class,
                static fn (ContainerInterface $c): InvoiceAlreadyPaidExceptionHandler => new InvoiceAlreadyPaidExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            )
            ->set(
                DunningTooFrequentExceptionHandler::class,
                static fn (ContainerInterface $c): DunningTooFrequentExceptionHandler => new DunningTooFrequentExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            )
            ->set(
                DunningNoticeNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): DunningNoticeNotFoundExceptionHandler => new DunningNoticeNotFoundExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            )
            ->set(
                DunningPausedExceptionHandler::class,
                static fn (ContainerInterface $c): DunningPausedExceptionHandler => new DunningPausedExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            );
    }
}
