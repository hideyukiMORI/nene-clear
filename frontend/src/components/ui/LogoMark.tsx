interface LogoMarkProps {
  /** Rendered size in px (square). */
  height?: number
  className?: string
}

/**
 * NeNe Clear brand mark — a translucent rounded square frame (per the design
 * spec's `side-brand .logo-mark`) containing the ledger bars + reconciliation
 * check (per the brand logo). For navy backgrounds (sidebar, login aside).
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
      <rect x="3" y="3" width="58" height="58" rx="5" fill="#ffffff" fillOpacity="0.07" stroke="#ffffff" strokeOpacity="0.55" strokeWidth="1.6" />
      <rect x="16" y="22" width="32" height="6.5" rx="2.4" fill="#ffffff" />
      <rect x="16" y="33.5" width="20" height="6.5" rx="2.4" fill="#7fb0e6" />
      <path d="M39 36.5l3.5 3.5 6.5-7.5" stroke="#ffffff" strokeWidth="3.4" fill="none" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  )
}
