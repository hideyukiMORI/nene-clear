import { useEffect, useId } from 'react'
import { useTranslation } from '@/shared/i18n/use-translation'
import { isMacPlatform } from './platform'
import { MOD, SHORTCUT_GROUPS, type ShortcutCombo, type ShortcutGroup } from './shortcuts-data'

export interface ShortcutsOverlayProps {
  onClose: () => void
}

function capLabel(cap: string, mac: boolean): string {
  if (cap === MOD) return mac ? '⌘' : 'Ctrl'
  return cap
}
const isWide = (label: string): boolean => label.length > 1

function Combo({ combo, mac }: { combo: ShortcutCombo; mac: boolean }) {
  return (
    <span className="sc-keys">
      {combo.caps.map((cap, i) => {
        const label = capLabel(cap, mac)
        return (
          <span key={`${cap}-${String(i)}`} className="sc-keycap-wrap">
            {i > 0 && combo.join === 'then' && <span className="sc-join">→</span>}
            {i > 0 && combo.join === 'plus' && <span className="sc-join">+</span>}
            <kbd className={isWide(label) ? 'kbd wide' : 'kbd'}>{label}</kbd>
          </span>
        )
      })}
    </span>
  )
}

function Group({ group, mac }: { group: ShortcutGroup; mac: boolean }) {
  return (
    <>
      <div className="sc-grp">
        {group.ja}
        <span className="sc-grp-en"> · {group.en}</span>
      </div>
      {group.rows.map((row) => (
        <div key={row.en} className="sc-row">
          <span className="lbl">
            {row.ja}
            <small>{row.en}</small>
          </span>
          <span className="sc-keys-row">
            {row.combos.map((combo, i) => (
              <Combo key={i} combo={combo} mac={mac} />
            ))}
          </span>
        </div>
      ))}
    </>
  )
}

export function ShortcutsOverlay({ onClose }: ShortcutsOverlayProps) {
  const { t } = useTranslation()
  const titleId = useId()
  const mac = isMacPlatform()
  const mid = Math.ceil(SHORTCUT_GROUPS.length / 2)
  const columns = [SHORTCUT_GROUPS.slice(0, mid), SHORTCUT_GROUPS.slice(mid)]

  useEffect(() => {
    const onKey = (e: KeyboardEvent): void => {
      if (e.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('keydown', onKey)
    }
  }, [onClose])

  return (
    <div className="modal-dim">
      <button
        type="button"
        aria-label={t('actions.close')}
        className="sc-backdrop"
        onClick={onClose}
      />
      <div className="sc-modal" role="dialog" aria-modal="true" aria-labelledby={titleId}>
        <div className="sc-head">
          <span className="sc-title" id={titleId}>
            <b>{t('shortcuts.title')}</b>
            <span>{t('shortcuts.titleEn')}</span>
          </span>
          <button type="button" className="sc-x" onClick={onClose}>
            <kbd className="kbd wide">Esc</kbd> {t('actions.close')}
          </button>
        </div>
        <div className="sc-body">
          {columns.map((groups, i) => (
            <div key={i} className="sc-col">
              {groups.map((group) => (
                <Group key={group.en} group={group} mac={mac} />
              ))}
            </div>
          ))}
        </div>
        <div className="sc-foot">
          <span>{t('shortcuts.footHint')}</span>
          <span>{mac ? t('shortcuts.footModMac') : t('shortcuts.footModOther')}</span>
        </div>
      </div>
    </div>
  )
}
