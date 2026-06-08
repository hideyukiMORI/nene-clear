<?php

declare(strict_types=1);

namespace NeneClear\Http;

use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use NeneClear\Audit\AuditRouteRegistrar;
use NeneClear\Audit\AuditServiceProvider;
use NeneClear\Auth\AuthRouteRegistrar;
use NeneClear\Auth\AuthServiceProvider;
use NeneClear\Auth\InvalidCredentialsExceptionHandler;
use NeneClear\Auth\TooManyLoginAttemptsExceptionHandler;
use NeneClear\BankImport\BankAccountNotFoundExceptionHandler;
use NeneClear\BankImport\BankImportBatchAlreadyReversedExceptionHandler;
use NeneClear\BankImport\BankImportBatchHasMatchedLinesExceptionHandler;
use NeneClear\BankImport\BankImportBatchNotFoundExceptionHandler;
use NeneClear\BankImport\BankImportRouteRegistrar;
use NeneClear\BankImport\BankImportServiceProvider;
use NeneClear\BankImport\BankTransactionNotFoundExceptionHandler;
use NeneClear\BankImport\DuplicateBankImportExceptionHandler;
use NeneClear\BankImport\InvalidBankCsvExceptionHandler;
use NeneClear\ClearSettings\ClearSettingsRouteRegistrar;
use NeneClear\ClearSettings\ClearSettingsServiceProvider;
use NeneClear\Dunning\DunningNoticeNotFoundExceptionHandler;
use NeneClear\Dunning\DunningPausedExceptionHandler;
use NeneClear\Dunning\DunningRouteRegistrar;
use NeneClear\Dunning\DunningServiceProvider;
use NeneClear\Dunning\DunningTooFrequentExceptionHandler;
use NeneClear\Dunning\InvoiceAlreadyPaidExceptionHandler;
use NeneClear\Export\ExportRouteRegistrar;
use NeneClear\Export\ExportServiceProvider;
use NeneClear\InvoiceUpstream\InvoiceUpstreamRouteRegistrar;
use NeneClear\InvoiceUpstream\InvoiceUpstreamServiceProvider;
use NeneClear\InvoiceUpstream\UpstreamClientNotFoundExceptionHandler;
use NeneClear\InvoiceUpstream\UpstreamInvoiceNotFoundExceptionHandler;
use NeneClear\InvoiceUpstream\UpstreamInvoiceUnavailableExceptionHandler;
use NeneClear\Organization\OrganizationAlreadyExistsExceptionHandler;
use NeneClear\Organization\OrganizationNotFoundExceptionHandler;
use NeneClear\Organization\OrganizationRouteRegistrar;
use NeneClear\Organization\OrganizationServiceProvider;
use NeneClear\Receivable\ManualReceivableServiceProvider;
use NeneClear\Reconciliation\AllocationExceedsOutstandingExceptionHandler;
use NeneClear\Reconciliation\BankTransactionNotMatchableExceptionHandler;
use NeneClear\Reconciliation\ClientCreditNotFoundExceptionHandler;
use NeneClear\Reconciliation\CreditExceedsRemainingExceptionHandler;
use NeneClear\Reconciliation\ReconciliationAlreadyReversedExceptionHandler;
use NeneClear\Reconciliation\ReconciliationNotFoundExceptionHandler;
use NeneClear\Reconciliation\ReconciliationRouteRegistrar;
use NeneClear\Reconciliation\ReconciliationServiceProvider;
use NeneClear\User\InvitationExpiredExceptionHandler;
use NeneClear\User\InvitationInvalidExceptionHandler;
use NeneClear\User\InvitationRouteRegistrar;
use NeneClear\User\RoleNotAssignableExceptionHandler;
use NeneClear\User\UserAlreadyExistsExceptionHandler;
use NeneClear\User\UserNotFoundExceptionHandler;
use NeneClear\User\UserRouteRegistrar;
use NeneClear\User\UserServiceProvider;
use Psr\Container\ContainerInterface;

/**
 * Composition root: registers every domain service provider and exposes the
 * aggregate route-registrar and exception-handler lists the HTTP runtime needs
 * (mirrors NENE2's runtime/example provider pattern).
 */
final readonly class ApplicationServiceProvider implements ServiceProviderInterface
{
    /** Container key for the aggregated list of route registrar callables. */
    public const string ROUTE_REGISTRARS = 'nene_clear.route_registrars';

    /** Container key for the aggregated list of domain exception handlers. */
    public const string EXCEPTION_HANDLERS = 'nene_clear.exception_handlers';

    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->addProvider(new AuditServiceProvider())
            ->addProvider(new AuthServiceProvider())
            ->addProvider(new OrganizationServiceProvider())
            ->addProvider(new UserServiceProvider())
            ->addProvider(new BankImportServiceProvider())
            ->addProvider(new ClearSettingsServiceProvider())
            ->addProvider(new ManualReceivableServiceProvider())
            ->addProvider(new ReconciliationServiceProvider())
            ->addProvider(new InvoiceUpstreamServiceProvider())
            ->addProvider(new ExportServiceProvider())
            ->addProvider(new DunningServiceProvider());

        $builder
            ->set(self::ROUTE_REGISTRARS, static function (ContainerInterface $c): array {
                /** @var list<callable(\Nene2\Routing\Router): void> $registrars */
                $registrars = [
                    ServiceResolver::get($c, AuthRouteRegistrar::class),
                    ServiceResolver::get($c, OrganizationRouteRegistrar::class),
                    ServiceResolver::get($c, UserRouteRegistrar::class),
                    ServiceResolver::get($c, InvitationRouteRegistrar::class),
                    ServiceResolver::get($c, AuditRouteRegistrar::class),
                    ServiceResolver::get($c, BankImportRouteRegistrar::class),
                    ServiceResolver::get($c, ClearSettingsRouteRegistrar::class),
                    ServiceResolver::get($c, ReconciliationRouteRegistrar::class),
                    ServiceResolver::get($c, InvoiceUpstreamRouteRegistrar::class),
                    ServiceResolver::get($c, ExportRouteRegistrar::class),
                    ServiceResolver::get($c, DunningRouteRegistrar::class),
                ];

                return $registrars;
            })
            ->set(self::EXCEPTION_HANDLERS, static function (ContainerInterface $c): array {
                /** @var list<\Nene2\Error\DomainExceptionHandlerInterface> $handlers */
                $handlers = [
                    ServiceResolver::get($c, InvalidCredentialsExceptionHandler::class),
                    ServiceResolver::get($c, TooManyLoginAttemptsExceptionHandler::class),
                    ServiceResolver::get($c, OrganizationNotFoundExceptionHandler::class),
                    ServiceResolver::get($c, OrganizationAlreadyExistsExceptionHandler::class),
                    ServiceResolver::get($c, UserNotFoundExceptionHandler::class),
                    ServiceResolver::get($c, UserAlreadyExistsExceptionHandler::class),
                    ServiceResolver::get($c, InvitationInvalidExceptionHandler::class),
                    ServiceResolver::get($c, InvitationExpiredExceptionHandler::class),
                    ServiceResolver::get($c, RoleNotAssignableExceptionHandler::class),
                    ServiceResolver::get($c, DuplicateBankImportExceptionHandler::class),
                    ServiceResolver::get($c, BankAccountNotFoundExceptionHandler::class),
                    ServiceResolver::get($c, InvalidBankCsvExceptionHandler::class),
                    ServiceResolver::get($c, BankTransactionNotFoundExceptionHandler::class),
                    ServiceResolver::get($c, BankImportBatchNotFoundExceptionHandler::class),
                    ServiceResolver::get($c, BankImportBatchAlreadyReversedExceptionHandler::class),
                    ServiceResolver::get($c, BankImportBatchHasMatchedLinesExceptionHandler::class),
                    ServiceResolver::get($c, ReconciliationNotFoundExceptionHandler::class),
                    ServiceResolver::get($c, ReconciliationAlreadyReversedExceptionHandler::class),
                    ServiceResolver::get($c, ClientCreditNotFoundExceptionHandler::class),
                    ServiceResolver::get($c, CreditExceedsRemainingExceptionHandler::class),
                    ServiceResolver::get($c, AllocationExceedsOutstandingExceptionHandler::class),
                    ServiceResolver::get($c, BankTransactionNotMatchableExceptionHandler::class),
                    ServiceResolver::get($c, UpstreamInvoiceUnavailableExceptionHandler::class),
                    ServiceResolver::get($c, UpstreamInvoiceNotFoundExceptionHandler::class),
                    ServiceResolver::get($c, UpstreamClientNotFoundExceptionHandler::class),
                    ServiceResolver::get($c, InvoiceAlreadyPaidExceptionHandler::class),
                    ServiceResolver::get($c, DunningTooFrequentExceptionHandler::class),
                    ServiceResolver::get($c, DunningNoticeNotFoundExceptionHandler::class),
                    ServiceResolver::get($c, DunningPausedExceptionHandler::class),
                ];

                return $handlers;
            });
    }
}
