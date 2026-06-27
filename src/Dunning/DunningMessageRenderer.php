<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use NeneClear\I18n\Locale;
use NeneClear\I18n\MessageCatalog;

/**
 * Renders the dunning email subject + body from the message catalog. Shared by
 * the real "send" path and the "preview" path so what the operator previews is
 * exactly what is sent (#194).
 */
final readonly class DunningMessageRenderer
{
    public const string TEMPLATE_VERSION = '1.0';

    public function __construct(
        private MessageCatalog $catalog,
    ) {
    }

    public function subject(DunningStage $stage, string $invoiceNumber): string
    {
        return sprintf($this->catalog->get($this->key($stage, 'subject'), Locale::Ja), $invoiceNumber);
    }

    public function testSubject(DunningStage $stage, string $invoiceNumber): string
    {
        return $this->catalog->get('dunning_email.test_prefix', Locale::Ja) . $this->subject($stage, $invoiceNumber);
    }

    public function body(DunningStage $stage, string $contactName, string $invoiceNumber, ?string $dueAt, int $outstandingCents): string
    {
        $dueAtLabel = $dueAt ?? '—';
        $amount = number_format((int) ($outstandingCents / 100));

        return sprintf(
            $this->catalog->get($this->key($stage, 'body'), Locale::Ja),
            $contactName,
            $invoiceNumber,
            $dueAtLabel,
            $amount,
            $dueAtLabel,
            $amount,
        );
    }

    /** Initial keeps the original keys; reminder/final use the staged keys. */
    private function key(DunningStage $stage, string $part): string
    {
        return $stage === DunningStage::Initial
            ? "dunning_email.$part"
            : "dunning_email.{$stage->value}.$part";
    }
}
