<?php

declare(strict_types=1);

namespace NeneClear\Audit;

use Nene2\Audit\AuditRecorder;
use Nene2\Audit\AuditRecorderFactory;
use Nene2\Audit\AuditRecorderFactoryInterface;
use Nene2\Audit\AuditRecorderInterface;
use Nene2\Audit\AuditTableConfig;
use Nene2\Audit\PdoAuditEventRepository;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Http\ClockInterface;
use Nene2\Http\JsonResponseFactory;
use NeneClear\Http\ServiceResolver;
use Psr\Container\ContainerInterface;

/**
 * Wires the append-only audit trail onto the framework audit module
 * (`Nene2\Audit`, ADR 0014).
 *
 * Stage 2 (Issue #258): `audit_events` is the framework-canonical table
 * ({@see AuditTableConfig::canonical()} — `action` / `actor_id` /
 * `before_json` / `after_json` / `metadata_json`), so the config seam carries
 * no name mapping any more. The write side is the framework's
 * transaction-atomic {@see AuditRecorderFactoryInterface::forExecutor()} plus
 * {@see PdoAuditEventRepository}; a non-transactional
 * {@see AuditRecorderInterface} is also provided for the login auditing path,
 * which carries no business mutation.
 *
 * The read side stays product-owned ({@see AuditReadRepositoryInterface})
 * because it keeps tenant scoping, Clear's `actor_id` sort, and inclusive
 * `DATE(occurred_at)` bounds, which the framework read contract deliberately
 * omits.
 */
final readonly class AuditServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                AuditTableConfig::class,
                static fn (): AuditTableConfig => self::tableConfig(),
            )
            // Transaction-atomic recorder factory: a mutating use case builds a
            // recorder bound to the transaction executor via forExecutor(), so the
            // audit row commits or rolls back with the business writes.
            ->set(
                AuditRecorderFactoryInterface::class,
                static fn (ContainerInterface $c): AuditRecorderFactoryInterface => new AuditRecorderFactory(
                    ServiceResolver::get($c, ClockInterface::class),
                    self::tableConfig(),
                ),
            )
            // Non-transactional recorder (login auditing carries no business mutation).
            ->set(
                AuditRecorderInterface::class,
                static fn (ContainerInterface $c): AuditRecorderInterface => new AuditRecorder(
                    new PdoAuditEventRepository(
                        ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
                        self::tableConfig(),
                    ),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                AuditReadRepositoryInterface::class,
                static fn (ContainerInterface $c): AuditReadRepositoryInterface => new PdoAuditReadRepository(
                    ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
                ),
            )
            ->set(
                AuditRouteRegistrar::class,
                static fn (ContainerInterface $c): AuditRouteRegistrar => new AuditRouteRegistrar(
                    new ListAuditEventsHandler(
                        ServiceResolver::get($c, AuditReadRepositoryInterface::class),
                        ServiceResolver::get($c, JsonResponseFactory::class),
                    ),
                ),
            );
    }

    /**
     * The framework-canonical mapping for Clear's `audit_events` table (ADR
     * 0014). Since the stage-2 migration (Issue #258) the physical schema *is*
     * the convergence target — before/after payload mode, a `metadata_json`
     * column, `action` / `actor_id` names, auto-increment integer id — so this
     * is {@see AuditTableConfig::canonical()} with no knob turned.
     *
     * Public so integration tests can build a real framework recorder against the
     * same mapping.
     */
    public static function tableConfig(): AuditTableConfig
    {
        return AuditTableConfig::canonical();
    }
}
