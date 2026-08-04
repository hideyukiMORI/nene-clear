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

    /**
     * Position in the escalation ladder, lowest first.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Initial => 0,
            self::Reminder => 1,
            self::Final => 2,
        };
    }

    /**
     * The highest stage reachable when `$lastSent` was the last stage actually
     * sent for an invoice — one step up, and `initial` when nothing has been sent
     * (#400 §5, "escalation never skips a stage").
     *
     * The ladder is walked one rung at a time on purpose: an invoice that sat
     * unattended past the `final` threshold must still receive `initial` and then
     * `reminder` before anything harsher, rather than opening with the last
     * message before the relationship changes.
     */
    public static function highestReachableAfter(?self $lastSent): self
    {
        return match ($lastSent) {
            null => self::Initial,
            self::Initial => self::Reminder,
            self::Reminder, self::Final => self::Final,
        };
    }
}
