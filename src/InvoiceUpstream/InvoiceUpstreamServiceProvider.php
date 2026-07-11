<?php

declare(strict_types=1);

namespace NeneClear\InvoiceUpstream;

use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Http\JsonResponseFactory;
use NeneClear\Http\ServiceResolver;
use NeneClear\I18n\LocalizedProblemDetailsFactory;
use NeneClear\Tenancy\CurrentOrganization;
use Psr\Container\ContainerInterface;

/**
 * Wires the read-only Invoice upstream surface (listing invoices) and its
 * degraded-mode exception handlers. The upstream client itself is bound at the
 * infrastructure boundary because its selection depends on runtime config.
 */
final readonly class InvoiceUpstreamServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                InvoiceUpstreamRouteRegistrar::class,
                static fn (ContainerInterface $c): InvoiceUpstreamRouteRegistrar => new InvoiceUpstreamRouteRegistrar(
                    new ListUpstreamInvoicesHandler(
                        ServiceResolver::get($c, InvoiceUpstreamClientInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                        ServiceResolver::get($c, CurrentOrganization::class),
                    ),
                ),
            )
            ->set(
                UpstreamInvoiceUnavailableExceptionHandler::class,
                static fn (ContainerInterface $c): UpstreamInvoiceUnavailableExceptionHandler => new UpstreamInvoiceUnavailableExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            )
            ->set(
                UpstreamInvoiceNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): UpstreamInvoiceNotFoundExceptionHandler => new UpstreamInvoiceNotFoundExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            )
            ->set(
                UpstreamClientNotFoundExceptionHandler::class,
                static fn (ContainerInterface $c): UpstreamClientNotFoundExceptionHandler => new UpstreamClientNotFoundExceptionHandler(
                    ServiceResolver::get($c, LocalizedProblemDetailsFactory::class),
                ),
            );
    }
}
