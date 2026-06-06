<?php

declare(strict_types=1);

namespace NeneClear\Audit;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Http\JsonResponseFactory;
use NeneClear\Http\ServiceResolver;
use Psr\Container\ContainerInterface;

/**
 * Wires the append-only audit trail. The repository is shared by every
 * state-changing use case across domains (compliance §6).
 */
final readonly class AuditServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                AuditEventRepositoryInterface::class,
                static fn (ContainerInterface $c): AuditEventRepositoryInterface => new PdoAuditEventRepository(
                    ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
                ),
            )
            // Non-transactional recorder (e.g. login auditing). Transactional use
            // cases build their own recorder from the transaction executor instead.
            ->set(
                AuditRecorderInterface::class,
                static fn (ContainerInterface $c): AuditRecorderInterface => new AuditRecorder(
                    ServiceResolver::get($c, AuditEventRepositoryInterface::class),
                ),
            )
            ->set(
                AuditRouteRegistrar::class,
                static fn (ContainerInterface $c): AuditRouteRegistrar => new AuditRouteRegistrar(
                    new ListAuditEventsHandler(
                        ServiceResolver::get($c, AuditEventRepositoryInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                ),
            );
    }
}
