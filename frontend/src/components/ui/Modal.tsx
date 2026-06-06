import type { ReactNode } from 'react'
import { useEffect } from 'react'
import { Icon } from './Icon'

interface ModalProps {
  open: boolean
  onClose: () => void
  title: string
  sub?: string
  children: ReactNode
  footer: ReactNode
  size?: 'narrow' | 'default' | 'wide'
}

export function Modal({ open, onClose, title, sub, children, footer, size = 'default' }: ModalProps) {
  useEffect(() => {
    if (!open) return
    const handle = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose() }
    document.addEventListener('keydown', handle)
    return () => document.removeEventListener('keydown', handle)
  }, [open, onClose])

  if (!open) return null

  const modalClass = size === 'narrow' ? 'modal narrow' : size === 'wide' ? 'modal wide' : 'modal'

  return (
    <div className="modal-scrim" onClick={e => { if (e.target === e.currentTarget) onClose() }}>
      <div className={modalClass} role="dialog" aria-modal="true" aria-label={title}>
        <div className="modal-head">
          <div>
            <h3>{title}</h3>
            {sub && <p>{sub}</p>}
          </div>
          <button className="x" onClick={onClose}><Icon name="x" /></button>
        </div>
        <div className="modal-body">{children}</div>
        <div className="modal-foot">{footer}</div>
      </div>
    </div>
  )
}
