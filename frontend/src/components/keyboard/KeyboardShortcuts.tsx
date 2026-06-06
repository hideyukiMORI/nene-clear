import { useEffect, useRef, useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { CommandPalette } from './CommandPalette'
import { SHOW_SHORTCUTS_EVENT } from './overlay-control'
import { ShortcutsOverlay } from './ShortcutsOverlay'
import { KBD_LIST_EVENT, type RowCursorAction } from './use-row-cursor'

function emitListAction(action: RowCursorAction): void {
  document.dispatchEvent(new CustomEvent(KBD_LIST_EVENT, { detail: { action } }))
}

const G_TIMEOUT_MS = 1200

/** Layer 1 — g→key navigation targets (nene-clear routes). */
const GOTO: Record<string, string> = {
  d: '/admin',
  r: '/admin/reconciliation',
  b: '/admin/bank-transactions',
  i: '/admin/bank-import',
  c: '/admin/client-credits',
  n: '/admin/dunning',
  u: '/admin/users',
  s: '/admin/settings',
  a: '/admin/audit-log',
}

/**
 * Layer 2 — `n` (new) per current list route. nene-clear has no create pages
 * yet (all routes are flat lists), so this stays empty; `n` is a no-op until a
 * create route exists.
 */
const NEW_ROUTE: Record<string, string> = {}

/**
 * Parent list roots for `u` (back to list, from detail views only). nene-clear
 * has no detail routes yet, so `u` is effectively a no-op; kept for when detail
 * pages land.
 */
const LIST_ROOTS = [
  '/admin/reconciliation',
  '/admin/bank-transactions',
  '/admin/client-credits',
  '/admin/dunning',
  '/admin/users',
  '/admin/audit-log',
]

/** Back to list only from a detail view — never from a /new or /edit form. */
function parentListOf(path: string): string | null {
  if (path.endsWith('/new') || path.endsWith('/edit')) return null
  return LIST_ROOTS.find((root) => path.startsWith(`${root}/`)) ?? null
}

function isEditableTarget(target: EventTarget | null): boolean {
  return (
    target instanceof HTMLElement &&
    (target.isContentEditable || target.matches('input, textarea, select'))
  )
}

/**
 * Global keyboard dispatcher. Mount once inside the authenticated shell (never on
 * login). Order of handling (A→E) is load-bearing — keep it.
 */
export function KeyboardShortcuts() {
  const navigate = useNavigate()
  const location = useLocation()
  const [overlayOpen, setOverlayOpen] = useState(false)
  const [paletteOpen, setPaletteOpen] = useState(false)
  const [pendingG, setPendingG] = useState(false)

  const overlayRef = useRef(false)
  const paletteRef = useRef(false)
  const pendingRef = useRef(false)
  const pathRef = useRef(location.pathname)
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  useEffect(() => {
    overlayRef.current = overlayOpen
  }, [overlayOpen])
  useEffect(() => {
    paletteRef.current = paletteOpen
  }, [paletteOpen])
  useEffect(() => {
    const open = (): void => {
      setOverlayOpen(true)
    }
    document.addEventListener(SHOW_SHORTCUTS_EVENT, open)
    return () => {
      document.removeEventListener(SHOW_SHORTCUTS_EVENT, open)
    }
  }, [])
  useEffect(() => {
    pendingRef.current = pendingG
  }, [pendingG])
  useEffect(() => {
    pathRef.current = location.pathname
  }, [location.pathname])

  useEffect(() => {
    const clearPending = (): void => {
      if (timerRef.current !== null) {
        clearTimeout(timerRef.current)
        timerRef.current = null
      }
      pendingRef.current = false
      setPendingG(false)
    }

    const onKeydown = (e: KeyboardEvent): void => {
      // A — submit chord is always handled, even in fields / IME.
      if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
        const form = e.target instanceof HTMLElement ? e.target.closest('form') : null
        if (form !== null) {
          e.preventDefault()
          form.requestSubmit()
        }
        return
      }

      // ⌘K / Ctrl+K — toggle command palette (before the modifier guard; works in fields).
      if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) {
        e.preventDefault()
        clearPending()
        setOverlayOpen(false)
        setPaletteOpen((open) => !open)
        return
      }

      // Palette open → its own listener owns the keyboard.
      if (paletteRef.current) return

      if (e.key === 'Escape') {
        if (overlayRef.current) setOverlayOpen(false)
        else if (pendingRef.current) clearPending()
        else if (!e.isComposing && isEditableTarget(e.target)) {
          ;(e.target as HTMLElement).blur()
        }
        return
      }

      // B — IME / editable-field guard. (keyCode 229 = IME composition in flight.)
      if (e.isComposing || e.keyCode === 229) return
      if (isEditableTarget(e.target)) return

      // C — modifier+single-key belongs to the OS/browser.
      if (e.metaKey || e.ctrlKey || e.altKey) return

      if (e.key === '?') {
        e.preventDefault()
        clearPending()
        setOverlayOpen((open) => !open)
        return
      }
      if (overlayRef.current) return

      // D — g-prefix sequence.
      if (pendingRef.current) {
        const dest = GOTO[e.key]
        clearPending()
        if (dest !== undefined) {
          e.preventDefault()
          void navigate(dest)
        }
        return
      }
      if (e.key === 'g') {
        e.preventDefault()
        pendingRef.current = true
        setPendingG(true)
        if (timerRef.current !== null) clearTimeout(timerRef.current)
        timerRef.current = setTimeout(clearPending, G_TIMEOUT_MS)
        return
      }

      // E — single-key actions.
      if (e.key === '/') {
        const target =
          document.querySelector('[data-kbd="search"]') ??
          document.querySelector(
            'form input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), form textarea, form select',
          )
        if (target instanceof HTMLElement) {
          e.preventDefault()
          target.focus()
        }
        return
      }
      if (e.key === 'n') {
        const dest = NEW_ROUTE[pathRef.current]
        if (dest !== undefined) {
          e.preventDefault()
          void navigate(dest)
        }
        return
      }
      if (e.key === 'u') {
        const list = parentListOf(pathRef.current)
        if (list !== null) {
          e.preventDefault()
          void navigate(list)
        }
        return
      }
      if (e.key === 'j' || e.key === 'k') {
        e.preventDefault()
        emitListAction(e.key === 'j' ? 'down' : 'up')
        return
      }
      if (e.key === 'o') {
        emitListAction('open')
        return
      }
      if (e.key === 'Enter' && (e.target === document.body || e.target === null)) {
        emitListAction('open')
      }
    }

    document.addEventListener('keydown', onKeydown)
    return () => {
      document.removeEventListener('keydown', onKeydown)
      if (timerRef.current !== null) clearTimeout(timerRef.current)
    }
  }, [navigate])

  return (
    <>
      {pendingG && (
        <div className="kbd-gind" role="status" aria-live="polite">
          g…
        </div>
      )}
      {overlayOpen && <ShortcutsOverlay onClose={() => setOverlayOpen(false)} />}
      {paletteOpen && <CommandPalette onClose={() => setPaletteOpen(false)} />}
    </>
  )
}
