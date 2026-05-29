<?php

declare(strict_types=1);

namespace NeneClear\Tests\Audit;

use NeneClear\Audit\AuditEvent;
use NeneClear\Audit\AuditEventRepositoryInterface;

final class InMemoryAuditEventRepository implements AuditEventRepositoryInterface
{
    /** @var list<AuditEvent> */
    public array $events = [];

    public function record(AuditEvent $event): int
    {
        $this->events[] = $event;

        return count($this->events);
    }
}
