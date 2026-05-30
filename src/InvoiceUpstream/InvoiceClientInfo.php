<?php

declare(strict_types=1);

namespace NeneClear\InvoiceUpstream;

final readonly class InvoiceClientInfo
{
    public function __construct(
        public int $clientId,
        public string $contactName,
        public string $recipientEmail,
    ) {
    }
}
