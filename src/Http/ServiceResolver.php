<?php

declare(strict_types=1);

namespace NeneClear\Http;

use LogicException;
use Psr\Container\ContainerInterface;

/**
 * Type-safe accessor for PSR-11 container services used inside service
 * providers (NENE2 `dependency-injection.md`).
 *
 * `ContainerInterface::get()` returns `mixed`; this narrows the result to the
 * requested class/interface so provider factories stay strictly typed without
 * repeating an `instanceof` guard at every call site. A misconfigured binding
 * is a wiring defect, surfaced as a {@see LogicException}.
 */
final class ServiceResolver
{
    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    public static function get(ContainerInterface $container, string $id): object
    {
        $service = $container->get($id);

        if (!$service instanceof $id) {
            throw new LogicException(sprintf('Service "%s" is misconfigured.', $id));
        }

        return $service;
    }
}
