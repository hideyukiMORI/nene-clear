interface LogoMarkProps {
  /** Rendered height in px. Width is derived from the logo's aspect ratio. */
  height?: number
  className?: string
}

/**
 * NeNe Clear brand mark — two ledger bars (white long / blue short) plus a
 * reconciliation check. Designed for navy backgrounds (sidebar, login aside).
 * Aspect ratio 1.8:1 (viewBox 360×200); width scales with height.
 */
export function LogoMark({ height = 26, className }: LogoMarkProps) {
  return (
    <svg
      className={className}
      height={height}
      width={height * 1.8}
      viewBox="420 290 360 200"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
      style={{ flex: 'none' }}
    >
      <rect x="430" y="300" width="340" height="60" rx="16" fill="#ffffff" />
      <rect x="430" y="410" width="210" height="60" rx="16" fill="#7fb0e6" />
      <path d="M690 440l40 40 70-80" stroke="#ffffff" strokeWidth="34" fill="none" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  )
}
