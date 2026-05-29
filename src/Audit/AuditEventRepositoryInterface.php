<?php

declare(strict_types=1);

namespace NeneClear\Audit;

interface AuditEventRepositoryInterface
{
    public function record(AuditEvent $event): int;
}
