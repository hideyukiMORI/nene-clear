<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

/**
 * Immutable record of one sent dunning notice (督促通知). Append-only; never
 * updated or deleted (compliance §4.7 / ADR 0012).
 */
final readonly class DunningNotice
{
    public function __construct(
        public int $organizationId,
        public int $invoiceId,
        public string $invoiceNumber,
        public int $clientId,
        public string $recipientEmail,
        public int $outstandingCents,
        public string $dueAt,
        public string $channel,
        public string $templateVersion,
        public int $sentBy,
        public string $sentAt,
        public ?int $id = null,
    ) {
    }
}
