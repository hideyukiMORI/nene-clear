import { useEffect, useId, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from '@/hooks/useTranslation'
import type { MessageKey } from '@/locales'

interface Command {
  id: string
  labelKey: MessageKey
  path: string
  combo: string[]
}

const COMMANDS: Command[] = [
  { id: 'dashboard', labelKey: 'nav.dashboard', path: '/admin', combo: ['g', 'd'] },
  { id: 'reconciliation', labelKey: 'nav.reconciliation', path: '/admin/reconciliation', combo: ['g', 'r'] },
  { id: 'bankTransactions', labelKey: 'nav.bankTransactions', path: '/admin/bank-transactions', combo: ['g', 'b'] },
  { id: 'bankImport', labelKey: 'nav.bankImport', path: '/admin/bank-import', combo: ['g', 'i'] },
  { id: 'clientCredits', labelKey: 'nav.clientCredits', path: '/admin/client-credits', combo: ['g', 'c'] },
  { id: 'dunning', labelKey: 'nav.dunning', path: '/admin/dunning', combo: ['g', 'n'] },
  { id: 'users', labelKey: 'nav.users', path: '/admin/users', combo: ['g', 'u'] },
  { id: 'settings', labelKey: 'nav.settings', path: '/admin/settings', combo: ['g', 's'] },
  { id: 'audit', labelKey: 'nav.auditLog', path: '/admin/audit-log', combo: ['g', 'a'] },
]

export function CommandPalette({ onClose }: { onClose: () => void }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const titleId = useId()
  const [cursor, setCursor] = useState(0)
  const cursorRef = useRef(0)

  useEffect(() => {
    cursorRef.current = cursor
  }, [cursor])

  useEffect(() => {
    const run = (index: number): void => {
      const cmd = COMMANDS[index]
      if (cmd === undefined) return
      onClose()
      void navigate(cmd.path)
    }
    const onKey = (e: KeyboardEvent): void => {
      if (e.key === 'j' || e.key === 'ArrowDown') {
        e.preventDefault()
        setCursor((c) => Math.min(c + 1, COMMANDS.length - 1))
      } else if (e.key === 'k' || e.key === 'ArrowUp') {
        e.preventDefault()
        setCursor((c) => Math.max(c - 1, 0))
      } else if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault()
        run(cursorRef.current)
      } else if (e.key === 'Escape') {
        e.preventDefault()
        onClose()
      }
    }
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('keydown', onKey)
    }
  }, [navigate, onClose])

  useEffect(() => {
    const el = document.querySelector(`[data-cmdp-row="${String(cursor)}"]`)
    if (el instanceof HTMLElement && typeof el.scrollIntoView === 'function') {
      el.scrollIntoView({ block: 'nearest' })
    }
  }, [cursor])

  return (
    <div className="modal-dim">
      <button type="button" aria-label={t('actions.close')} className="sc-backdrop" onClick={onClose} />
      <div className="cmdp" role="dialog" aria-modal="true" aria-labelledby={titleId}>
        <div className="cmdp-head" id={titleId}>
          <b>{t('commandPalette.title')}</b>
          <span>{t('commandPalette.titleEn')}</span>
        </div>
        <ul className="cmdp-list" role="listbox" aria-label={t('commandPalette.title')}>
          {COMMANDS.map((cmd, i) => (
            <li key={cmd.id}>
              <button
                type="button"
                role="option"
                aria-selected={i === cursor}
                data-cmdp-row={i}
                className={`cmdp-row${i === cursor ? ' hl' : ''}`}
                onMouseEnter={() => {
                  setCursor(i)
                }}
                onClick={() => {
                  onClose()
                  void navigate(cmd.path)
                }}
              >
                <span className="cmdp-label">{t(cmd.labelKey)}</span>
                <span className="cmdp-keys">
                  {cmd.combo.map((cap, index) => (
                    <kbd key={`${cap}-${String(index)}`} className="kbd">
                      {cap}
                    </kbd>
                  ))}
                </span>
              </button>
            </li>
          ))}
        </ul>
        <div className="cmdp-foot">{t('commandPalette.hint')}</div>
      </div>
    </div>
  )
}
