/** Decorative inline key hint, e.g. on a "New" button: <KbdHint k="n" />. */
export function KbdHint({ k }: { k: string }) {
  return (
    <kbd className="kbd-hint" aria-hidden="true">
      {k}
    </kbd>
  )
}
