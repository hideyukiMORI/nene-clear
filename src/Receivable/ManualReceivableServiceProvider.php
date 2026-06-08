<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use NeneClear\Http\ServiceResolver;
use Psr\Container\ContainerInterface;

/**
 * Wires the manual-receivables domain (ADR 0014). Foundation only: the
 * repository. CRUD use cases, CSV import, and route registrars land in later
 * Issues and register here.
 */
final readonly class ManualReceivableServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->set(
            ManualReceivableRepositoryInterface::class,
            static fn (ContainerInterface $c): ManualReceivableRepositoryInterface => new PdoManualReceivableRepository(
                ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
            ),
        );
    }
}
