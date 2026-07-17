import type { ReactNode } from 'react'
import { Icon } from '@/shared/ui/icon'
import { useTranslation } from '@/shared/i18n/use-translation'
import type { MessageKey } from '@/shared/i18n/messages'

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

export type SortDir = 'asc' | 'desc'
export interface SortState { by: string; dir: SortDir }

/** Toggle helper: same column flips direction; a new column starts ascending. */
export function nextSort(current: SortState, column: string): SortState {
  if (current.by === column) return { by: column, dir: current.dir === 'asc' ? 'desc' : 'asc' }
  return { by: column, dir: 'asc' }
}

interface SortableThProps {
  column: string
  sort: SortState
  onSort: (column: string) => void
  children: ReactNode
}

/** A sortable column header (design 04): click to sort, shows ↑/↓ via aria-sort. */
export function SortableTh({ column, sort, onSort, children }: SortableThProps) {
  const active = sort.by === column
  const ariaSort = active ? (sort.dir === 'asc' ? 'ascending' : 'descending') : undefined
  return (
    <th className="sortable" aria-sort={ariaSort} onClick={() => onSort(column)}>
      {children}<span className="sort-ic" />
    </th>
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
  /** Current zero-based row offset. */
  offset: number
  /** Page size (rows per page). */
  pageSize: number
  /** Total matching record count. */
  total: number
  /** Called with the new offset when the user pages. */
  onOffsetChange: (offset: number) => void
}

/**
 * Table footer pager. Derives the current/total page and prev/next offsets
 * internally, and renders nothing when everything fits on one page — callers
 * pass only the raw offset/pageSize/total and an offset setter.
 */
export function Pager({ offset, pageSize, total, onOffsetChange }: PagerProps) {
  const { t } = useTranslation()
  if (total <= pageSize) return null
  const current = Math.floor(offset / pageSize) + 1
  const totalPages = Math.ceil(total / pageSize)
  return (
    <div className="tbl-foot">
      <span>{t('common.pagination.showing', { from: offset + 1, to: Math.min(offset + pageSize, total), total })}</span>
      <div className="pager">
        <button aria-label={t('common.pagination.prev')} onClick={() => onOffsetChange(Math.max(0, offset - pageSize))} disabled={current === 1}>
          <Icon name="chev-l" size="sm" />
        </button>
        <span className="cur">{current} / {totalPages}</span>
        <button aria-label={t('common.pagination.next')} onClick={() => onOffsetChange(offset + pageSize)} disabled={current >= totalPages}>
          <Icon name="chev-r" size="sm" />
        </button>
      </div>
    </div>
  )
}
