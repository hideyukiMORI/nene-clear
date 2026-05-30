<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

final readonly class DunningMailPayload
{
    public function __construct(
        public string $to,
        public string $subject,
        public string $body,
        public int $organizationId,
        public int $invoiceId,
        public int $dunningNoticeId,
    ) {
    }
}
