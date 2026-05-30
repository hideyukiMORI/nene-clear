<?php

declare(strict_types=1);

namespace NeneClear\Http;

use Nene2\Auth\BearerTokenMiddleware;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RuntimeApplicationFactory;
use Nene2\Http\UtcClock;
use NeneClear\Audit\PdoAuditEventRepository;
use NeneClear\Auth\AuthRouteRegistrar;
use NeneClear\Auth\Capability;
use NeneClear\Auth\CapabilityMiddleware;
use NeneClear\Auth\CapabilityRule;
use NeneClear\Auth\GetCurrentUserHandler;
use NeneClear\Auth\InvalidCredentialsExceptionHandler;
use NeneClear\Auth\JwtTokenService;
use NeneClear\Auth\LoginHandler;
use NeneClear\Auth\LoginUseCase;
use NeneClear\BankImport\BankAccountNotFoundExceptionHandler;
use NeneClear\BankImport\BankCsvParser;
use NeneClear\BankImport\BankImportBatchAlreadyReversedExceptionHandler;
use NeneClear\BankImport\BankImportBatchHasMatchedLinesExceptionHandler;
use NeneClear\BankImport\BankImportBatchNotFoundExceptionHandler;
use NeneClear\BankImport\BankImportRouteRegistrar;
use NeneClear\BankImport\BankTransactionNotFoundExceptionHandler;
use NeneClear\BankImport\DuplicateBankImportExceptionHandler;
use NeneClear\BankImport\GetBankTransactionByIdHandler;
use NeneClear\BankImport\ImportBankCsvHandler;
use NeneClear\BankImport\ImportBankCsvUseCase;
use NeneClear\BankImport\InvalidBankCsvExceptionHandler;
use NeneClear\BankImport\ListBankImportBatchesHandler;
use NeneClear\BankImport\ListBankTransactionsHandler;
use NeneClear\BankImport\ListUnmatchedTransactionsHandler;
use NeneClear\BankImport\PdoBankAccountRepository;
use NeneClear\BankImport\PdoBankImportBatchRepository;
use NeneClear\BankImport\PdoBankTransactionRepository;
use NeneClear\BankImport\ReverseBankImportBatchHandler;
use NeneClear\BankImport\ReverseBankImportBatchUseCase;
use NeneClear\ClearSettings\ClearSettingsRouteRegistrar;
use NeneClear\ClearSettings\GetClearSettingsHandler;
use NeneClear\ClearSettings\PdoClearSettingsRepository;
use NeneClear\ClearSettings\TestUpstreamConnectionHandler;
use NeneClear\ClearSettings\UpdateClearSettingsHandler;
use NeneClear\Dunning\DunningNoticeNotFoundExceptionHandler;
use NeneClear\Dunning\DunningRouteRegistrar;
use NeneClear\Dunning\DunningTooFrequentExceptionHandler;
use NeneClear\Dunning\GetDunningNoticeByIdHandler;
use NeneClear\Dunning\InvoiceAlreadyPaidExceptionHandler;
use NeneClear\Dunning\ListDunningNoticesHandler;
use NeneClear\Dunning\LogOnlyDunningMailer;
use NeneClear\Dunning\PdoDunningNoticeRepository;
use NeneClear\Dunning\SendDunningHandler;
use NeneClear\Dunning\SendDunningUseCase;
use NeneClear\InvoiceUpstream\FakeInvoiceUpstreamClient;
use NeneClear\InvoiceUpstream\InvoiceUpstreamClientInterface;
use NeneClear\InvoiceUpstream\UpstreamInvoiceUnavailableExceptionHandler;
use NeneClear\Organization\CreateOrganizationHandler;
use NeneClear\Organization\CreateOrganizationUseCase;
use NeneClear\Organization\DeleteOrganizationHandler;
use NeneClear\Organization\DeleteOrganizationUseCase;
use NeneClear\Organization\GetOrganizationByIdHandler;
use NeneClear\Organization\GetOrganizationByIdUseCase;
use NeneClear\Organization\ListOrganizationsHandler;
use NeneClear\Organization\ListOrganizationsUseCase;
use NeneClear\Organization\OrganizationAlreadyExistsExceptionHandler;
use NeneClear\Organization\OrganizationNotFoundExceptionHandler;
use NeneClear\Organization\OrganizationRouteRegistrar;
use NeneClear\Organization\PdoOrganizationRepository;
use NeneClear\Reconciliation\AllocationExceedsOutstandingExceptionHandler;
use NeneClear\Reconciliation\ApplyCreditHandler;
use NeneClear\Reconciliation\ApplyCreditUseCase;
use NeneClear\Reconciliation\BankTransactionNotMatchableExceptionHandler;
use NeneClear\Reconciliation\ClientCreditNotFoundExceptionHandler;
use NeneClear\Reconciliation\ConfirmMatchHandler;
use NeneClear\Reconciliation\ConfirmMatchUseCase;
use NeneClear\Reconciliation\CreditExceedsRemainingExceptionHandler;
use NeneClear\Reconciliation\GetReconciliationByIdHandler;
use NeneClear\Reconciliation\ListClientCreditsHandler;
use NeneClear\Reconciliation\ListReconciliationsHandler;
use NeneClear\Reconciliation\PdoClientCreditRepository;
use NeneClear\Reconciliation\PdoReconciliationRepository;
use NeneClear\Reconciliation\ProposeMatchHandler;
use NeneClear\Reconciliation\ProposeMatchUseCase;
use NeneClear\Reconciliation\ReconciliationAlreadyReversedExceptionHandler;
use NeneClear\Reconciliation\ReconciliationNotFoundExceptionHandler;
use NeneClear\Reconciliation\ReconciliationRouteRegistrar;
use NeneClear\Reconciliation\ReverseReconciliationHandler;
use NeneClear\Reconciliation\ReverseReconciliationUseCase;
use NeneClear\User\CreateUserHandler;
use NeneClear\User\CreateUserUseCase;
use NeneClear\User\DeleteUserHandler;
use NeneClear\User\DeleteUserUseCase;
use NeneClear\User\GetUserByIdHandler;
use NeneClear\User\GetUserByIdUseCase;
use NeneClear\User\ListUsersHandler;
use NeneClear\User\ListUsersUseCase;
use NeneClear\User\PdoUserRepository;
use NeneClear\User\UpdateUserHandler;
use NeneClear\User\UpdateUserUseCase;
use NeneClear\User\UserAlreadyExistsExceptionHandler;
use NeneClear\User\UserNotFoundExceptionHandler;
use NeneClear\User\UserRouteRegistrar;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Builds the NeNe Clear HTTP application on top of the NENE2 runtime.
 *
 * The framework baseline pipeline (error handling, request id, security headers,
 * CORS, request size limit) and the built-in `/health` route are always present.
 *
 * Authenticated/admin routes are wired only when **both** a database query
 * executor and a JWT secret are provided. Without them the app still serves the
 * public/health surface, so `/health` never depends on a database. See
 * docs/development/nene2-compliance.md.
 */
final class ApplicationFactory
{
    /**
     * Public paths that never require a bearer token.
     *
     * @var list<string>
     */
    private const array PUBLIC_PATHS = ['/', '/health', '/machine/health', '/admin/auth/login'];

    /**
     * @param list<string> $allowedOrigins CORS allowlist; empty disables CORS (set explicitly in production).
     */
    public static function create(
        bool $debug = false,
        array $allowedOrigins = [],
        ?DatabaseQueryExecutorInterface $query = null,
        ?string $jwtSecret = null,
        ?InvoiceUpstreamClientInterface $invoiceClient = null,
    ): RequestHandlerInterface {
        $psr17 = new Psr17Factory();
        $json = new JsonResponseFactory($psr17, $psr17);
        $problemDetails = new ProblemDetailsResponseFactory($psr17, $psr17, 'https://nene-clear.dev/problems/');

        $routeRegistrars = [];
        $domainExceptionHandlers = [];
        $authMiddleware = null;

        if ($query !== null && $jwtSecret !== null && $jwtSecret !== '') {
            $jwt = new JwtTokenService($jwtSecret);
            $users = new PdoUserRepository($query);
            $organizations = new PdoOrganizationRepository($query);

            $routeRegistrars[] = new AuthRouteRegistrar(
                new LoginHandler(new LoginUseCase($users, $jwt), $json),
                new GetCurrentUserHandler($users, $json, $problemDetails),
            );
            $routeRegistrars[] = new OrganizationRouteRegistrar(
                new ListOrganizationsHandler(new ListOrganizationsUseCase($organizations), $json),
                new CreateOrganizationHandler(new CreateOrganizationUseCase($organizations), $json),
                new GetOrganizationByIdHandler(new GetOrganizationByIdUseCase($organizations), $json),
                new DeleteOrganizationHandler(new DeleteOrganizationUseCase($organizations), $json),
            );
            $routeRegistrars[] = new UserRouteRegistrar(
                new ListUsersHandler(new ListUsersUseCase($users), $json),
                new CreateUserHandler(new CreateUserUseCase($users), $json),
                new GetUserByIdHandler(new GetUserByIdUseCase($users), $json),
                new UpdateUserHandler(new UpdateUserUseCase($users), $json),
                new DeleteUserHandler(new DeleteUserUseCase($users), $json),
            );

            $audit = new PdoAuditEventRepository($query);
            $bankAccounts = new PdoBankAccountRepository($query);
            $batches = new PdoBankImportBatchRepository($query);
            $transactions = new PdoBankTransactionRepository($query);
            $clock = new UtcClock();
            $importUseCase = new ImportBankCsvUseCase(
                $bankAccounts,
                $batches,
                $transactions,
                $audit,
                new BankCsvParser(),
                $clock,
            );
            $routeRegistrars[] = new BankImportRouteRegistrar(
                new ImportBankCsvHandler($importUseCase, $json),
                new ListBankImportBatchesHandler($batches, $json),
                new ReverseBankImportBatchHandler(
                    new ReverseBankImportBatchUseCase($batches, $transactions, $audit, $clock),
                    $json,
                ),
                new ListBankTransactionsHandler($transactions, $json),
                new ListUnmatchedTransactionsHandler($transactions, $json),
                new GetBankTransactionByIdHandler($transactions, $json),
            );

            $upstream = $invoiceClient ?? new FakeInvoiceUpstreamClient();
            $clearSettings = new PdoClearSettingsRepository($query, $bankAccounts);
            $routeRegistrars[] = new ClearSettingsRouteRegistrar(
                new GetClearSettingsHandler($clearSettings, $json),
                new UpdateClearSettingsHandler($clearSettings, $bankAccounts, $json),
                new TestUpstreamConnectionHandler($upstream, $json),
            );

            $reconRepo = new PdoReconciliationRepository($query);
            $creditRepo = new PdoClientCreditRepository($query);
            $routeRegistrars[] = new ReconciliationRouteRegistrar(
                new ProposeMatchHandler(new ProposeMatchUseCase($transactions, $upstream, $clock), $json),
                new ConfirmMatchHandler(new ConfirmMatchUseCase($transactions, $reconRepo, $creditRepo, $upstream, $audit, $clock), $reconRepo, $json),
                new ListReconciliationsHandler($reconRepo, $json),
                new GetReconciliationByIdHandler($reconRepo, $json),
                new ReverseReconciliationHandler(new ReverseReconciliationUseCase($reconRepo, $creditRepo, $transactions, $upstream, $audit, $clock), $reconRepo, $json),
                new ListClientCreditsHandler($creditRepo, $json),
                new ApplyCreditHandler(new ApplyCreditUseCase($creditRepo, $upstream, $audit), $json),
            );

            $domainExceptionHandlers[] = new InvalidCredentialsExceptionHandler($problemDetails);
            $domainExceptionHandlers[] = new OrganizationNotFoundExceptionHandler($problemDetails);
            $domainExceptionHandlers[] = new OrganizationAlreadyExistsExceptionHandler($problemDetails);
            $domainExceptionHandlers[] = new UserNotFoundExceptionHandler($problemDetails);
            $domainExceptionHandlers[] = new UserAlreadyExistsExceptionHandler($problemDetails);
            $domainExceptionHandlers[] = new DuplicateBankImportExceptionHandler($problemDetails);
            $domainExceptionHandlers[] = new BankAccountNotFoundExceptionHandler($problemDetails);
            $domainExceptionHandlers[] = new InvalidBankCsvExceptionHandler($problemDetails);
            $domainExceptionHandlers[] = new BankTransactionNotFoundExceptionHandler($problemDetails);
            $domainExceptionHandlers[] = new BankImportBatchNotFoundExceptionHandler($problemDetails);
            $domainExceptionHandlers[] = new BankImportBatchAlreadyReversedExceptionHandler($problemDetails);
            $domainExceptionHandlers[] = new BankImportBatchHasMatchedLinesExceptionHandler($problemDetails);
            $domainExceptionHandlers[] = new ReconciliationNotFoundExceptionHandler($problemDetails);
            $domainExceptionHandlers[] = new ReconciliationAlreadyReversedExceptionHandler($problemDetails);
            $domainExceptionHandlers[] = new ClientCreditNotFoundExceptionHandler($problemDetails);
            $domainExceptionHandlers[] = new CreditExceedsRemainingExceptionHandler($problemDetails);
            $domainExceptionHandlers[] = new AllocationExceedsOutstandingExceptionHandler($problemDetails);
            $domainExceptionHandlers[] = new BankTransactionNotMatchableExceptionHandler($problemDetails);
            $domainExceptionHandlers[] = new UpstreamInvoiceUnavailableExceptionHandler($problemDetails);
            $domainExceptionHandlers[] = new InvoiceAlreadyPaidExceptionHandler($problemDetails);
            $domainExceptionHandlers[] = new DunningTooFrequentExceptionHandler($problemDetails);
            $domainExceptionHandlers[] = new DunningNoticeNotFoundExceptionHandler($problemDetails);

            $dunningNotices = new PdoDunningNoticeRepository($query);
            $routeRegistrars[] = new DunningRouteRegistrar(
                new SendDunningHandler(new SendDunningUseCase($dunningNotices, $clearSettings, $upstream, new LogOnlyDunningMailer(), $audit, $clock), $dunningNotices, $json),
                new ListDunningNoticesHandler($dunningNotices, $json),
                new GetDunningNoticeByIdHandler($dunningNotices, $json),
            );

            $authMiddleware = [
                new BearerTokenMiddleware($problemDetails, $jwt, excludedPaths: self::PUBLIC_PATHS),
                new CapabilityMiddleware($problemDetails, [
                    '/admin/organizations' => CapabilityRule::same(Capability::ManageOrganizations),
                    '/admin/users' => CapabilityRule::same(Capability::ManageUsers),
                    '/admin/bank-import-batches' => new CapabilityRule(read: Capability::ViewReconciliation, write: Capability::ManageReconciliation),
                    '/admin/bank-transactions' => new CapabilityRule(read: Capability::ViewReconciliation, write: Capability::ManageReconciliation),
                    '/admin/reconciliations' => new CapabilityRule(read: Capability::ViewReconciliation, write: Capability::ManageReconciliation),
                    '/admin/client-credits' => new CapabilityRule(read: Capability::ViewReconciliation, write: Capability::ManageReconciliation),
                    '/admin/clear-settings' => CapabilityRule::same(Capability::ManageClearSettings),
                    '/admin/dunning-notices' => new CapabilityRule(read: Capability::ViewReconciliation, write: Capability::SendDunning),
                ]),
            ];
        }

        return (new RuntimeApplicationFactory(
            responseFactory: $psr17,
            streamFactory: $psr17,
            domainExceptionHandlers: $domainExceptionHandlers,
            routeRegistrars: $routeRegistrars,
            authMiddleware: $authMiddleware,
            debug: $debug,
            allowedOrigins: $allowedOrigins,
        ))->create();
    }
}
