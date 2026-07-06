<?php

declare(strict_types=1);

namespace NeneClear\Audit;

use Nene2\Audit\AuditPayloadMode;
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
 * (`Nene2\Audit`, ADR 0014) without re-migrating (stage ①).
 *
 * The write side is the framework's transaction-atomic
 * {@see AuditRecorderFactoryInterface::forExecutor()} plus
 * {@see PdoAuditEventRepository}, pointed at Clear's existing `audit_events`
 * table via {@see AuditTableConfig} in SinglePayload mode — physical column
 * names (`event_type`, `actor_user_id`, `payload_json`) are absorbed by config,
 * so no column is renamed. A non-transactional {@see AuditRecorderInterface} is
 * also provided for the login auditing path, which carries no business mutation.
 *
 * The read side stays product-owned ({@see AuditReadRepositoryInterface}) because
 * it keeps tenant scoping and Clear's `actor_user_id` sort, which the framework
 * read contract deliberately omits.
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
     * Points the framework audit module at Clear's existing `audit_events` table
     * (ADR 0014) — the single seam for stage-① adoption. SinglePayload mode folds
     * `before`/`after`/`metadata` into the existing `payload_json` column; the
     * `event_type` / `actor_user_id` physical names are kept (no rename), so the
     * binding terminology and API contract are unchanged.
     *
     * Public so integration tests can build a real framework recorder against the
     * same mapping.
     */
    public static function tableConfig(): AuditTableConfig
    {
        return new AuditTableConfig(
            table: 'audit_events',
            mode: AuditPayloadMode::SinglePayload,
            idColumn: 'id',
            actionColumn: 'event_type',
            entityTypeColumn: 'entity_type',
            entityIdColumn: 'entity_id',
            actorColumn: 'actor_user_id',
            organizationColumn: 'organization_id',
            occurredAtColumn: 'occurred_at',
            metadataColumn: null,
            beforeColumn: null,
            afterColumn: null,
            payloadColumn: 'payload_json',
            idIsAutoIncrement: true,
        );
    }
}
