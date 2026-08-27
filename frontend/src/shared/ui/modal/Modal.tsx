import type { ReactNode } from 'react'
import { useEffect, useRef } from 'react'
import { Icon } from '@/shared/ui/icon'

interface ModalProps {
  open: boolean
  onClose: () => void
  title: string
  sub?: string
  children: ReactNode
  footer: ReactNode
  size?: 'narrow' | 'default' | 'wide'
  /**
   * When provided, the dialog renders as a <form> and submits on Enter — make
   * the primary footer button `type="submit"`. Omit for modals where Enter
   * should not confirm.
   */
  onSubmit?: () => void
}

const FOCUSABLE =
  'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

export function Modal({ open, onClose, title, sub, children, footer, size = 'default', onSubmit }: ModalProps) {
  const dialogRef = useRef<HTMLElement | null>(null)

  // Escape closes (independent of focus, so it works from anywhere).
  useEffect(() => {
    if (!open) return
    const handle = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose() }
    document.addEventListener('keydown', handle)
    return () => document.removeEventListener('keydown', handle)
  }, [open, onClose])

  // Move focus into the dialog on open — so keyboard users can type/Tab/Enter
  // immediately and the global j/k/Enter list shortcuts stop firing on <body> —
  // and restore it to the trigger on close. Prefers an element marked
  // [data-autofocus], else the first focusable element.
  useEffect(() => {
    if (!open) return
    const dialog = dialogRef.current
    if (dialog === null) return
    const previouslyFocused = document.activeElement as HTMLElement | null

    const target =
      dialog.querySelector<HTMLElement>('[data-autofocus]')
      ?? dialog.querySelector<HTMLElement>('.modal-body input, .modal-body select, .modal-body textarea')
      ?? dialog.querySelector<HTMLElement>(FOCUSABLE)
    target?.focus()

    return () => {
      if (previouslyFocused !== null && typeof previouslyFocused.focus === 'function') {
        previouslyFocused.focus()
      }
    }
  }, [open])

  if (!open) return null

  const modalClass = size === 'narrow' ? 'modal narrow' : size === 'wide' ? 'modal wide' : 'modal'

  // Trap Tab within the dialog so focus never escapes to the page behind it.
  function onKeyDown(e: React.KeyboardEvent) {
    if (e.key !== 'Tab') return
    const dialog = dialogRef.current
    if (dialog === null) return
    const focusables = Array.from(dialog.querySelectorAll<HTMLElement>(FOCUSABLE))
      .filter(el => el.offsetParent !== null || el === document.activeElement)
    if (focusables.length === 0) return
    const first = focusables[0]
    const last = focusables[focusables.length - 1]
    const active = document.activeElement
    if (e.shiftKey && active === first) {
      e.preventDefault()
      last.focus()
    } else if (!e.shiftKey && active === last) {
      e.preventDefault()
      first.focus()
    }
  }

  const setRef = (el: HTMLElement | null) => { dialogRef.current = el }

  const inner = (
    <>
      <div className="modal-head">
        <div>
          <h3>{title}</h3>
          {sub && <p>{sub}</p>}
        </div>
        <button type="button" className="x" onClick={onClose}><Icon decorative name="x" /></button>
      </div>
      <div className="modal-body">{children}</div>
      <div className="modal-foot">{footer}</div>
    </>
  )

  return (
    <div className="modal-scrim" onClick={e => { if (e.target === e.currentTarget) onClose() }}>
      {onSubmit
        ? (
          <form ref={setRef} className={modalClass} role="dialog" aria-modal="true" aria-label={title} onKeyDown={onKeyDown} onSubmit={e => { e.preventDefault(); onSubmit() }}>
            {inner}
          </form>
        )
        : (
          <div ref={setRef} className={modalClass} role="dialog" aria-modal="true" aria-label={title} onKeyDown={onKeyDown}>
            {inner}
          </div>
        )}
    </div>
  )
}
