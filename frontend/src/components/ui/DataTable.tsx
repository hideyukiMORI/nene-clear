import type { ReactNode } from 'react'
import { Icon } from './Icon'

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
  emptyText?: string
}

/**
 * Renders a single full-width <tr> for loading / error / empty table states,
 * or null when the table has rows to show. Place inside <tbody> before the
 * data rows. Replaces the repeated colSpan/centered-cell boilerplate.
 */
export function TableStateRow({ colSpan, loading, error, empty, emptyText = 'データなし' }: TableStateRowProps) {
  let content: ReactNode = null
  let className = 'muted'
  if (loading) content = '読み込み中…'
  else if (error) { content = error; className = '' }
  else if (empty) content = emptyText
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
  return (
    <div className="tbl-foot">
      <span>{offset + 1}〜{Math.min(offset + pageSize, itemCount)} 件 / {itemCount} 件</span>
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
