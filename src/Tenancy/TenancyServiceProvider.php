<?php

declare(strict_types=1);

namespace NeneClear\Tenancy;

use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Http\RequestScopedHolder;
use NeneClear\Http\ServiceResolver;
use NeneClear\I18n\LocalizedProblemDetailsFactory;
use Psr\Container\ContainerInterface;

/**
 * Wires the request organization scope (#300): the request-scoped holder, the
 * {@see CurrentOrganization} read side injected into org-scoped handlers, the
 * middleware that populates the holder from the verified `org` claim, and the
 * 403 mapping for org-less tokens on the data plane.
 */
final readonly class TenancyServiceProvider implements ServiceProviderInterface
{
    /** Container key for the request-scoped organization-id holder. */
    public const string ORG_ID_HOLDER = 'nene_clear.org_id_holder';

    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(self::ORG_ID_HOLDER, static fn (ContainerInterface $c): RequestScopedHolder => new RequestScopedHolder())
            ->set(
                CurrentOrganization::class,
                static function (ContainerInterface $c): CurrentOrganization {
                    $holder = $c->get(self::ORG_ID_HOLDER);
                    \assert($holder instanceof RequestScopedHolder);

                    /** @var RequestScopedHolder<int> $holder */
                    return new HolderCurrentOrganization($holder);
                },
            )
            ->set(
                OrgScopeMiddleware::class,
                static function (ContainerInterface $c): OrgScopeMiddleware {
                    $holder = $c->get(self::ORG_ID_HOLDER);
                    \assert($holder instanceof RequestScopedHolder);

                    /** @var RequestScopedHolder<int> $holder */
                    return new OrgScopeMiddleware($holder);
                },
            )
            ->set(
                MissingOrganizationScopeExceptionHandler::class,
                static fn (ContainerInterface $c): MissingOrganizationScopeExceptionHandler => new MissingOrganizationScopeExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            );
    }
}
