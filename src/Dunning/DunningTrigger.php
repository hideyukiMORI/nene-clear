<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

/**
 * What initiated a dunning send (#400 §7, `terminology.md` — Scheduled dunning).
 *
 * Written into the `dunning_sent` audit event's `metadata` on **both** paths, so
 * an unattended send is distinguishable from one an operator performed. The
 * scheduled path records `actor_id = 0` — this repo's existing "no human actor"
 * value — and `actor_id` alone cannot carry the distinction, because a failed
 * login records 0 as well. `trigger` is what separates those inside that shared
 * value.
 *
 * Rows written before #400 carry no `trigger` at all. Absence means "unknown,
 * predates the feature" and MUST NOT be read as either value.
 */
enum DunningTrigger: string
{
    case Manual = 'manual';
    case Scheduled = 'scheduled';
}
