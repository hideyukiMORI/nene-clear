<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

/**
 * Escalation stage of a dunning notice. The operator selects it per send; the
 * renderer picks the matching template. Tone escalates initial → reminder →
 * final but stays within the self-collection boundary (ADR 0011, scope X10 — no
 * threats / coercion); the reminder/final wording is a conservative draft to be
 * confirmed by the ADR 0011 reviewer before production use.
 */
enum DunningStage: string
{
    case Initial = 'initial';
    case Reminder = 'reminder';
    case Final = 'final';

    public static function fromString(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Initial;
    }
}
