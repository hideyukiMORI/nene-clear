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
        /** Snapshot of the invoice due date; null when the invoice had no due date. */
        public ?string $dueAt,
        public string $channel,
        public string $templateVersion,
        /**
         * Escalation stage this notice was sent at (#414).
         *
         * ⚠️ Rows written before #414 have no recorded stage and take the column
         * default, so this reads `initial` for them. That is the storage default,
         * NOT evidence that `initial` was sent — the operator may have chosen any
         * stage. Do not treat a pre-#414 row as proof of which stage went out; the
         * `dunning_sent` audit event carries `stage` only from #414 onward, and its
         * absence there is the reliable marker.
         */
        public DunningStage $stage,
        public int $sentBy,
        public string $sentAt,
        public ?int $id = null,
    ) {
    }
}
