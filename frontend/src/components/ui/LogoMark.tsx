interface LogoMarkProps {
  /** Rendered size in px (square). */
  height?: number
  className?: string
}

/**
 * NeNe Clear brand mark — two ledger bars (white long / blue short) plus a
 * reconciliation check, composed in a square frame to match the design spec's
 * `side-brand .logo-mark` (square a-icon beside the wordmark). For navy
 * backgrounds (sidebar, login aside); matches the square favicon.
 */
export function LogoMark({ height = 26, className }: LogoMarkProps) {
  return (
    <svg
      className={className}
      width={height}
      height={height}
      viewBox="0 0 64 64"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
      style={{ flex: 'none' }}
    >
      <rect x="15" y="21" width="34" height="7" rx="2.6" fill="#ffffff" />
      <rect x="15" y="33" width="21" height="7" rx="2.6" fill="#7fb0e6" />
      <path d="M39 36l4 4 7-8" stroke="#ffffff" strokeWidth="3.8" fill="none" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  )
}
