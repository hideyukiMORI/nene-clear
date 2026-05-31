import type { ReactNode } from 'react'
import { Icon } from './Icon'
import { useTranslation } from '@/hooks/useTranslation'
import type { MessageKey } from '@/locales'

interface DataTableProps {
  children: ReactNode
  className?: string
}

export function DataTable({ children, className }: DataTableProps) {
  return (
    <div className="tbl-wrap">
      <table className={['tbl', className].filter(Boolean).join(' ')}>
        {children}
      </table>
    </div>
  )
}

interface TableStateRowProps {
  colSpan: number
  loading?: boolean
  error?: string
  empty?: boolean
  /** Message key for the empty state; defaults to common.noData. */
  emptyKey?: MessageKey
}

/**
 * Renders a single full-width <tr> for loading / error / empty table states,
 * or null when the table has rows to show. Place inside <tbody> before the
 * data rows. All fixed text is localized via the message catalog.
 */
export function TableStateRow({ colSpan, loading, error, empty, emptyKey = 'common.noData' }: TableStateRowProps) {
  const { t } = useTranslation()
  let content: ReactNode = null
  let className = 'muted'
  if (loading) content = t('common.loading')
  else if (error) { content = error; className = '' }
  else if (empty) content = t(emptyKey)
  if (content === null) return null
  return (
    <tr>
      <td colSpan={colSpan} className={className} style={{ textAlign: 'center', padding: '20px 14px', color: error ? 'var(--bad)' : undefined }}>
        {content}
      </td>
    </tr>
  )
}

interface PagerProps {
  current: number
  total: number
  onPrev: () => void
  onNext: () => void
  itemCount: number
  pageSize: number
  offset: number
}

export function Pager({ current, total, onPrev, onNext, itemCount, pageSize, offset }: PagerProps) {
  const { t } = useTranslation()
  return (
    <div className="tbl-foot">
      <span>{t('common.pagination.showing', { from: offset + 1, to: Math.min(offset + pageSize, itemCount), total: itemCount })}</span>
      <div className="pager">
        <button onClick={onPrev} disabled={current === 1}>
          <Icon name="chev-l" size="sm" />
        </button>
        <span className="cur">{current} / {total}</span>
        <button onClick={onNext} disabled={current >= total}>
          <Icon name="chev-r" size="sm" />
        </button>
      </div>
    </div>
  )
}
