<?php

declare(strict_types=1);

namespace NeneClear\Demo;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Demo\CountingDemoCapacityGuard;
use Nene2\Demo\DemoConfig;
use Nene2\Demo\DemoRouteRegistrar;
use Nene2\Demo\StartDisposableDemoHandler;
use Nene2\DependencyInjection\ContainerBuilder;
use Nene2\DependencyInjection\ServiceProviderInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\ClockInterface;
use NeneClear\Auth\TokenIssuerInterface;
use NeneClear\Http\ServiceResolver;
use NeneClear\Organization\CreateOrganizationUseCaseInterface;
use NeneClear\User\CreateUserUseCaseInterface;
use NeneClear\User\UserRepositoryInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;

/**
 * Wires the disposable-demo domain as a `Nene2\Demo` consumer (#275): the
 * product concretes (provisioner / seeder / seater / reaper), the
 * creation-time capacity guard (per-IP file-backed throttle + instance-wide
 * org ceiling), and the framework handler + route registrar. Everything is
 * reused from existing providers — no auth code is added; the seater mints a
 * token through the same {@see TokenIssuerInterface} a login uses.
 *
 * The registrar is registered unconditionally: {@see StartDisposableDemoHandler}
 * answers 404 while {@see DemoConfig::$demoMode} is off (fail-close).
 */
final readonly class DemoServiceProvider implements ServiceProviderInterface
{
    /**
     * Demo starts allowed per client network per window. Deliberately generous
     * (invoice's field-tested value, its #612): a "client" is really one
     * office/carrier NAT, and runaway abuse stays bounded by the instance-wide
     * org ceiling plus the sweep cron.
     */
    public const int THROTTLE_LIMIT = 30;
    public const int THROTTLE_WINDOW_SECONDS = 3600;

    public function register(ContainerBuilder $builder): void
    {
        $builder
            ->set(
                DemoOrgProvisioner::class,
                static fn (ContainerInterface $c): DemoOrgProvisioner => new DemoOrgProvisioner(
                    ServiceResolver::get($c, CreateOrganizationUseCaseInterface::class),
                    ServiceResolver::get($c, CreateUserUseCaseInterface::class),
                ),
            )
            ->set(
                DemoDataSeeder::class,
                static fn (ContainerInterface $c): DemoDataSeeder => new DemoDataSeeder(
                    ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
                    ServiceResolver::get($c, ClockInterface::class),
                ),
            )
            ->set(
                DemoSessionSeater::class,
                static fn (ContainerInterface $c): DemoSessionSeater => new DemoSessionSeater(
                    ServiceResolver::get($c, UserRepositoryInterface::class),
                    ServiceResolver::get($c, TokenIssuerInterface::class),
                    ServiceResolver::get($c, Psr17Factory::class),
                ),
            )
            ->set(
                DemoOrgReaper::class,
                static fn (ContainerInterface $c): DemoOrgReaper => new DemoOrgReaper(
                    ServiceResolver::get($c, DatabaseQueryExecutorInterface::class),
                ),
            )
            ->set(
                CountingDemoCapacityGuard::class,
                static function (ContainerInterface $c): CountingDemoCapacityGuard {
                    $config = ServiceResolver::get($c, DemoConfig::class);
                    $query = ServiceResolver::get($c, DatabaseQueryExecutorInterface::class);

                    return new CountingDemoCapacityGuard(
                        demoOrgCount: static function () use ($query, $config): int {
                            // ESCAPE '|' — a backslash escape char is itself escaped
                            // differently by MySQL string literals vs SQLite (#277).
                            $row = $query->fetchOne(
                                "SELECT COUNT(*) AS n FROM organizations WHERE slug LIKE ? ESCAPE '|'",
                                [str_replace(['|', '%', '_'], ['||', '|%', '|_'], $config->slugPrefix) . '%'],
                            );

                            return is_array($row) ? (int) $row['n'] : 0;
                        },
                        config: $config,
                        throttleStorage: new FileRateLimitStorage(dirname(__DIR__, 2) . '/var', ServiceResolver::get($c, ClockInterface::class)),
                        throttleLimit: self::THROTTLE_LIMIT,
                        throttleWindowSeconds: self::THROTTLE_WINDOW_SECONDS,
                        clock: ServiceResolver::get($c, ClockInterface::class),
                    );
                },
            )
            ->set(
                StartDisposableDemoHandler::class,
                static fn (ContainerInterface $c): StartDisposableDemoHandler => new StartDisposableDemoHandler(
                    config: ServiceResolver::get($c, DemoConfig::class),
                    capacityGuard: ServiceResolver::get($c, CountingDemoCapacityGuard::class),
                    provisioner: ServiceResolver::get($c, DemoOrgProvisioner::class),
                    seeder: ServiceResolver::get($c, DemoDataSeeder::class),
                    seater: ServiceResolver::get($c, DemoSessionSeater::class),
                    problemDetails: ServiceResolver::get($c, ProblemDetailsResponseFactory::class),
                    templateKeyClass: DemoTemplate::class,
                ),
            )
            ->set(
                DemoRouteRegistrar::class,
                static fn (ContainerInterface $c): DemoRouteRegistrar => new DemoRouteRegistrar(
                    ServiceResolver::get($c, StartDisposableDemoHandler::class),
                ),
            );
    }
}
