<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

/**
 * What a scheduled run decided about one candidate invoice (#400 §9).
 *
 * Every candidate the run looked at produces exactly one of these, including the
 * ones it did nothing about. A `--dry-run` that printed only the sends would hide
 * the more useful half of the answer: an operator turning the feature on first
 * needs to know *why* an invoice they expected to see was passed over.
 */
enum ScheduledDunningOutcome: string
{
    /** Sent, or — under `--dry-run` — would have been sent. */
    case Sent = 'sent';

    /** Not yet far enough past due to reach the first threshold (or not due at all). */
    case BelowThreshold = 'below_threshold';

    /** The invoice carries no due date, so "days past due" is undefined (#400 §5). */
    case NoDueDate = 'no_due_date';

    /** Reached the `final` threshold: held for an operator to send by hand (#400 §5). */
    case AwaitingApproval = 'awaiting_approval';

    /** An operator paused dunning for this invoice. */
    case Paused = 'paused';

    /** Inside `dunning_min_interval_days` since the last notice. */
    case TooFrequent = 'too_frequent';

    /** Upstream says it is settled, or nothing is outstanding. */
    case AlreadyPaid = 'already_paid';

    /** The per-run cap was reached; the next run picks this up. */
    case CapReached = 'cap_reached';

    /** The send threw something unexpected. The run continues (#400 §9). */
    case Failed = 'failed';
}
