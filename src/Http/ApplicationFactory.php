<?php

declare(strict_types=1);

namespace NeneClear\Http;

use LogicException;
use Nene2\Auth\TokenVerifierInterface;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Database\Preflight\CandidateProfile;
use Nene2\Database\Preflight\DatabaseCandidateInspector;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\Error\DomainExceptionHandlerInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\ClockInterface;
use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RuntimeApplicationFactory;
use Nene2\Http\UtcClock;
use NeneClear\Auth\AuthServiceProvider;
use NeneClear\Auth\JwtTokenService;
use NeneClear\Auth\MfaChallengeTokens;
use NeneClear\Auth\TokenIssuerInterface;
use NeneClear\Dunning\DunningMailerInterface;
use NeneClear\Dunning\LogOnlyDunningMailer;
use NeneClear\Dunning\SmtpDunningMailer;
use NeneClear\I18n\LocalizedProblemDetailsFactory;
use NeneClear\I18n\MessageCatalog;
use NeneClear\InvoiceUpstream\FakeInvoiceUpstreamClient;
use NeneClear\InvoiceUpstream\InvoiceUpstreamClientInterface;
use NeneClear\InvoiceUpstream\InvoiceUpstreamHttpClient;
use NeneClear\Security\Encryptor;
use NeneClear\User\InvitationLinkBuilder;
use NeneClear\User\InvitationMailerInterface;
use NeneClear\User\LogOnlyInvitationMailer;
use NeneClear\User\SmtpInvitationMailer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Builds the NeNe Clear HTTP application on top of the NENE2 runtime.
 *
 * Wiring follows NENE2 `dependency-injection.md`: framework + config services
 * are registered on a {@see ContainerBuilder} at this config boundary, and each
 * domain is wired by its own {@see \Nene2\DependencyInjection\ServiceProviderInterface}
 * via {@see ApplicationServiceProvider}. This factory only assembles the
 * container and hands the resolved route registrars, exception handlers and
 * auth middleware to {@see RuntimeApplicationFactory}.
 *
 * The framework baseline pipeline (error handling, request id, security headers,
 * CORS, request size limit) and the built-in `/health` route are always present.
 * Authenticated/admin routes are wired only when **both** a database query
 * executor and a JWT secret are provided, so `/health` never depends on a
 * database. See docs/development/nene2-compliance.md.
 */
final class ApplicationFactory
{
    /**
     * @param list<string> $allowedOrigins CORS allowlist; empty disables CORS (set explicitly in production).
     * @param string|null $machineApiKey When set, auth-gates the NENE2 machine surface
     *        (`GET /machine/health`) via `X-NENE2-API-Key`. Suite update tracking pairs this
     *        value as `NENE_SUITE_APP_NENE_CLEAR_MACHINE_KEY` (issue #182).
     * @param string|null $appVersion This application's own release version (the repo `VERSION`
     *        file), surfaced as `version` on the auth-gated `GET /machine/health` — distinct from
     *        the framework version. Null omits the field (consumers treat it as unknown).
     * @param DatabaseCandidateInspector|null $databaseCandidateInspector Opt-in candidate-database
     *        preflight (issue #183): when provided, registers the auth-gated
     *        `POST /machine/database/preflight`. Null keeps the endpoint absent (404).
     * @param array<string, CandidateProfile> $databaseCandidateProfiles Env-declared candidates the
     *        preflight may inspect, keyed by id ({@see \NeneClear\Database\CandidateProfiles}).
     */
    public static function create(
        bool $debug = false,
        array $allowedOrigins = [],
        ?DatabaseQueryExecutorInterface $query = null,
        ?DatabaseTransactionManagerInterface $transactionManager = null,
        ?string $jwtSecret = null,
        ?InvoiceUpstreamClientInterface $invoiceClient = null,
        ?string $smtpHost = null,
        int $smtpPort = 1025,
        string $smtpUsername = '',
        string $smtpPassword = '',
        string $smtpFromAddress = 'noreply@nene-clear.dev',
        string $smtpFromName = 'NeNe Clear',
        ?string $invoiceApiBaseUrl = null,
        string $invoiceBearerToken = '',
        string $appBaseUrl = '',
        ?InvitationMailerInterface $invitationMailer = null,
        string $encryptionKey = '',
        ?string $machineApiKey = null,
        ?string $appVersion = null,
        ?DatabaseCandidateInspector $databaseCandidateInspector = null,
        array $databaseCandidateProfiles = [],
    ): RequestHandlerInterface {
        $psr17 = new Psr17Factory();

        // Public/health-only surface: no database or JWT secret → no admin routes.
        // The machine surface stays available so deployment tooling can read the
        // app version even before the database is configured.
        if ($query === null || $jwtSecret === null || $jwtSecret === '') {
            return (new RuntimeApplicationFactory(
                responseFactory: $psr17,
                streamFactory: $psr17,
                machineApiKey: $machineApiKey,
                debug: $debug,
                allowedOrigins: $allowedOrigins,
                appVersion: $appVersion,
                databaseCandidateInspector: $databaseCandidateInspector,
                databaseCandidateProfiles: $databaseCandidateProfiles,
            ))->create();
        }

        // Admin routes mutate state, so they require a transaction manager to keep
        // each use case's business writes + audit record atomic (see Issue #122).
        if ($transactionManager === null) {
            throw new LogicException('A DatabaseTransactionManagerInterface is required when the database is configured.');
        }

        $container = self::buildContainer(
            $query,
            $transactionManager,
            $jwtSecret,
            $psr17,
            self::resolveInvoiceClient($invoiceClient, $invoiceApiBaseUrl, $invoiceBearerToken),
            self::resolveMailer($smtpHost, $smtpPort, $smtpUsername, $smtpPassword, $smtpFromAddress, $smtpFromName),
            $invitationMailer ?? self::resolveInvitationMailer($smtpHost, $smtpPort, $smtpUsername, $smtpPassword, $smtpFromAddress, $smtpFromName),
            $appBaseUrl,
            $encryptionKey,
        );

        $routeRegistrars = $container->get(ApplicationServiceProvider::ROUTE_REGISTRARS);
        $exceptionHandlers = $container->get(ApplicationServiceProvider::EXCEPTION_HANDLERS);
        $authMiddleware = $container->get(AuthServiceProvider::AUTH_MIDDLEWARE);

        if (!is_array($routeRegistrars) || !is_array($exceptionHandlers) || !is_array($authMiddleware)) {
            throw new LogicException('Application service aggregates are misconfigured.');
        }

        /** @var list<callable(\Nene2\Routing\Router): void> $routeRegistrars */
        /** @var list<DomainExceptionHandlerInterface> $exceptionHandlers */
        /** @var list<MiddlewareInterface> $authMiddleware */

        return (new RuntimeApplicationFactory(
            responseFactory: $psr17,
            streamFactory: $psr17,
            machineApiKey: $machineApiKey,
            domainExceptionHandlers: $exceptionHandlers,
            routeRegistrars: $routeRegistrars,
            authMiddleware: $authMiddleware,
            debug: $debug,
            allowedOrigins: $allowedOrigins,
            appVersion: $appVersion,
            databaseCandidateInspector: $databaseCandidateInspector,
            databaseCandidateProfiles: $databaseCandidateProfiles,
        ))->create();
    }

    /**
     * Builds and returns the fully-wired DI container, without the HTTP runtime.
     *
     * This is the composition root reused by non-HTTP entry points (e.g. the
     * `tools/create-admin.php` bootstrap CLI) so they resolve the exact same
     * use cases the HTTP app does, instead of hand-wiring a partial container
     * that would drift. Domain services are lazy factories, so only what the
     * caller resolves is constructed.
     */
    public static function container(
        DatabaseQueryExecutorInterface $query,
        DatabaseTransactionManagerInterface $transactionManager,
        string $jwtSecret,
        ?InvoiceUpstreamClientInterface $invoiceClient = null,
        ?string $smtpHost = null,
        int $smtpPort = 1025,
        string $smtpUsername = '',
        string $smtpPassword = '',
        string $smtpFromAddress = 'noreply@nene-clear.dev',
        string $smtpFromName = 'NeNe Clear',
        ?string $invoiceApiBaseUrl = null,
        string $invoiceBearerToken = '',
        string $appBaseUrl = '',
        ?InvitationMailerInterface $invitationMailer = null,
        string $encryptionKey = '',
    ): ContainerInterface {
        return self::buildContainer(
            $query,
            $transactionManager,
            $jwtSecret,
            new Psr17Factory(),
            self::resolveInvoiceClient($invoiceClient, $invoiceApiBaseUrl, $invoiceBearerToken),
            self::resolveMailer($smtpHost, $smtpPort, $smtpUsername, $smtpPassword, $smtpFromAddress, $smtpFromName),
            $invitationMailer ?? self::resolveInvitationMailer($smtpHost, $smtpPort, $smtpUsername, $smtpPassword, $smtpFromAddress, $smtpFromName),
            $appBaseUrl,
            $encryptionKey,
        );
    }

    /**
     * Registers framework + config-derived services, then every domain provider.
     */
    private static function buildContainer(
        DatabaseQueryExecutorInterface $query,
        DatabaseTransactionManagerInterface $transactionManager,
        string $jwtSecret,
        Psr17Factory $psr17,
        InvoiceUpstreamClientInterface $invoiceClient,
        DunningMailerInterface $mailer,
        InvitationMailerInterface $invitationMailer,
        string $appBaseUrl,
        string $encryptionKey,
    ): ContainerInterface {
        $langDir = dirname(__DIR__, 2) . '/lang';

        return (new ContainerBuilder())
            ->set(Psr17Factory::class, static fn (ContainerInterface $c): Psr17Factory => $psr17)
            ->set(ResponseFactoryInterface::class, static fn (ContainerInterface $c): ResponseFactoryInterface => $psr17)
            ->set(StreamFactoryInterface::class, static fn (ContainerInterface $c): StreamFactoryInterface => $psr17)
            ->set(JsonResponseFactory::class, static fn (ContainerInterface $c): JsonResponseFactory => new JsonResponseFactory($psr17, $psr17))
            ->set(
                ProblemDetailsResponseFactory::class,
                static fn (ContainerInterface $c): ProblemDetailsResponseFactory => new ProblemDetailsResponseFactory(
                    $psr17,
                    $psr17,
                    'https://nene-clear.dev/problems/',
                ),
            )
            ->set(MessageCatalog::class, static fn (ContainerInterface $c): MessageCatalog => new MessageCatalog($langDir))
            ->set(
                LocalizedProblemDetailsFactory::class,
                static fn (ContainerInterface $c): LocalizedProblemDetailsFactory => new LocalizedProblemDetailsFactory(
                    ServiceResolver::get($c, MessageCatalog::class),
                    ServiceResolver::get($c, ProblemDetailsResponseFactory::class),
                ),
            )
            ->set(DatabaseQueryExecutorInterface::class, static fn (ContainerInterface $c): DatabaseQueryExecutorInterface => $query)
            ->set(DatabaseTransactionManagerInterface::class, static fn (ContainerInterface $c): DatabaseTransactionManagerInterface => $transactionManager)
            ->set(ClockInterface::class, static fn (ContainerInterface $c): ClockInterface => new UtcClock())
            ->set(JwtTokenService::class, static fn (ContainerInterface $c): JwtTokenService => new JwtTokenService($jwtSecret))
            ->set(TokenIssuerInterface::class, static fn (ContainerInterface $c): TokenIssuerInterface => ServiceResolver::get($c, JwtTokenService::class))
            ->set(TokenVerifierInterface::class, static fn (ContainerInterface $c): TokenVerifierInterface => ServiceResolver::get($c, JwtTokenService::class))
            ->set(MfaChallengeTokens::class, static fn (ContainerInterface $c): MfaChallengeTokens => new MfaChallengeTokens($jwtSecret))
            ->set(InvoiceUpstreamClientInterface::class, static fn (ContainerInterface $c): InvoiceUpstreamClientInterface => $invoiceClient)
            ->set(DunningMailerInterface::class, static fn (ContainerInterface $c): DunningMailerInterface => $mailer)
            ->set(InvitationMailerInterface::class, static fn (ContainerInterface $c): InvitationMailerInterface => $invitationMailer)
            ->set(InvitationLinkBuilder::class, static fn (ContainerInterface $c): InvitationLinkBuilder => new InvitationLinkBuilder($appBaseUrl))
            ->set(Encryptor::class, static fn (ContainerInterface $c): Encryptor => new Encryptor($encryptionKey !== '' ? $encryptionKey : null))
            ->addProvider(new ApplicationServiceProvider())
            ->build();
    }

    private static function resolveInvoiceClient(
        ?InvoiceUpstreamClientInterface $override,
        ?string $invoiceApiBaseUrl,
        string $invoiceBearerToken,
    ): InvoiceUpstreamClientInterface {
        if ($override !== null) {
            return $override;
        }

        if ($invoiceApiBaseUrl !== null && $invoiceApiBaseUrl !== '' && $invoiceBearerToken !== '') {
            return new InvoiceUpstreamHttpClient($invoiceApiBaseUrl, $invoiceBearerToken);
        }

        return new FakeInvoiceUpstreamClient();
    }

    private static function resolveMailer(
        ?string $smtpHost,
        int $smtpPort,
        string $smtpUsername,
        string $smtpPassword,
        string $smtpFromAddress,
        string $smtpFromName,
    ): DunningMailerInterface {
        if ($smtpHost !== null && $smtpHost !== '') {
            return new SmtpDunningMailer($smtpHost, $smtpPort, $smtpFromAddress, $smtpFromName, $smtpUsername, $smtpPassword);
        }

        return new LogOnlyDunningMailer();
    }

    private static function resolveInvitationMailer(
        ?string $smtpHost,
        int $smtpPort,
        string $smtpUsername,
        string $smtpPassword,
        string $smtpFromAddress,
        string $smtpFromName,
    ): InvitationMailerInterface {
        if ($smtpHost !== null && $smtpHost !== '') {
            return new SmtpInvitationMailer($smtpHost, $smtpPort, $smtpFromAddress, $smtpFromName, $smtpUsername, $smtpPassword);
        }

        return new LogOnlyInvitationMailer();
    }
}
