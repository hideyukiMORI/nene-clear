import { Icon } from '@/shared/ui/icon'

/** One glossary line: a bookkeeping term and its plain-language definition. */
export interface GlossaryEntry {
  term: string
  def: string
}

interface InfoDotProps {
  /** Accessible name for the trigger (e.g. "Term definitions"). */
  label: string
  /** Glossary entries revealed in the tooltip. */
  entries: GlossaryEntry[]
}

/**
 * A small, focusable "?" affordance that reveals a glossary tooltip on hover or
 * keyboard focus. It is an **annotation only** — it never renames the term it
 * sits beside (the terminology registry is binding); it bridges non-accounting
 * users by defining bookkeeping terms (消込 / 突合 / 前受金 / 充当) inline.
 */
export function InfoDot({ label, entries }: InfoDotProps) {
  return (
    <span className="infodot">
      <button type="button" className="infodot-btn" aria-label={label}>
        <Icon decorative name="info" size="sm" />
      </button>
      <span className="infodot-pop" role="tooltip">
        <dl className="glossary">
          {entries.map(e => (
            <div key={e.term} className="glossary-row">
              <dt>{e.term}</dt>
              <dd>{e.def}</dd>
            </div>
          ))}
        </dl>
      </span>
    </span>
  )
}
