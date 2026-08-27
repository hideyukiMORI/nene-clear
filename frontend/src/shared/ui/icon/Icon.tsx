import type { CSSProperties, SVGProps } from 'react'

type IconSize = 'sm' | 'md' | 'lg'

interface IconBase {
  /** Sprite symbol id, without the `i-` prefix (see `index.html`). */
  name: string
  size?: IconSize
  className?: string
  style?: CSSProperties
}

/** The icon carries meaning of its own, so it needs a localized name. */
interface MeaningfulIcon extends IconBase {
  label: string
  decorative?: never
}

/** The icon repeats something already in the text, so it is hidden from assistive tech. */
interface DecorativeIcon extends IconBase {
  decorative: true
  label?: never
}

export type IconProps = MeaningfulIcon | DecorativeIcon

const sizeClass: Record<IconSize, string> = {
  sm: 'ic ic-sm',
  md: 'ic',
  lg: 'ic ic-lg',
}

/**
 * A sprite icon, plus the one thing an icon must always say: whether it is
 * content or decoration.
 *
 * `label` and `decorative` are mutually exclusive and one of them is required,
 * enforced by the type — the same contract as `@hideyukimori/nene2-ui`'s `Icon`.
 * An icon that renders without saying which it is leaves assistive technology to
 * guess, and the page looks entirely correct either way, which is why the gap
 * survives review. Across the fleet 101 of 222 inline `<svg>` elements are in
 * that state (kit measurement, 2026-08-23); before this type existed all 114 of
 * Clear's call sites were too.
 *
 * Every one of those 114 turned out to be decoration. That is not an assumption:
 * all 439 rendered instances were audited in a real browser across 13 screens
 * (2026-08-27, #440) by asking, per icon, whether its labelling ancestor already
 * carried text or an accessible name. 414 did; the remaining 25 sit beside a
 * label, a KPI caption, a feature description or a note body that names them.
 * **Not one icon was found alone inside an unnamed control** — so nothing here is
 * load-bearing for a screen reader, and `decorative` is a finding rather than a
 * default.
 *
 * The artwork stays local on purpose: the kit ships none and takes on no icon
 * library. Its `Icon` is the semantics around an icon, not the icon. Swapping to
 * it is held back only by size — the kit hardcodes `h-4/5/6` (16/20/24px) where
 * Clear draws 15/18/22px, and has no slot to hold that (fleet-tooling #494).
 */
export function Icon({ name, size = 'md', className, style, ...meaning }: IconProps) {
  // `focusable="false"` is for IE/Edge-legacy, which put SVG in the tab order;
  // harmless elsewhere and cheap insurance for a sprite used 114 times.
  const a11y: SVGProps<SVGSVGElement> =
    'label' in meaning && meaning.label !== undefined
      ? { role: 'img', 'aria-label': meaning.label }
      : { 'aria-hidden': true, focusable: 'false' }

  return (
    <svg className={[sizeClass[size], className].filter(Boolean).join(' ')} style={style} {...a11y}>
      <use href={`#i-${name}`} />
    </svg>
  )
}
