import { describe, it, expect, vi } from 'vitest'
import { screen, fireEvent } from '@testing-library/react'
import { renderWithProviders } from '@/test/render'
import { nextSort, Pager, TableStateRow, SortableTh, type SortState } from './DataTable'

describe('nextSort', () => {
  it('starts a new column ascending', () => {
    const cur: SortState = { by: 'value_date', dir: 'desc' }
    expect(nextSort(cur, 'amount_cents')).toEqual({ by: 'amount_cents', dir: 'asc' })
  })

  it('flips direction when the same column is re-selected', () => {
    expect(nextSort({ by: 'amount_cents', dir: 'asc' }, 'amount_cents')).toEqual({ by: 'amount_cents', dir: 'desc' })
    expect(nextSort({ by: 'amount_cents', dir: 'desc' }, 'amount_cents')).toEqual({ by: 'amount_cents', dir: 'asc' })
  })
})

describe('Pager', () => {
  it('renders nothing when everything fits on one page', () => {
    const { container } = renderWithProviders(
      <Pager offset={0} pageSize={50} total={50} onOffsetChange={vi.fn()} />,
    )
    expect(container).toBeEmptyDOMElement()
  })

  it('shows the current/total page and disables prev on the first page', () => {
    const onOffsetChange = vi.fn()
    renderWithProviders(<Pager offset={0} pageSize={50} total={120} onOffsetChange={onOffsetChange} />)

    expect(screen.getByText('1 / 3')).toBeInTheDocument() // ceil(120/50) = 3 pages
    expect(screen.getByRole('button', { name: '前のページ' })).toBeDisabled()

    fireEvent.click(screen.getByRole('button', { name: '次のページ' }))
    expect(onOffsetChange).toHaveBeenCalledWith(50) // offset + pageSize
  })

  it('disables next on the last page and pages back by one page size', () => {
    const onOffsetChange = vi.fn()
    renderWithProviders(<Pager offset={100} pageSize={50} total={120} onOffsetChange={onOffsetChange} />)

    expect(screen.getByText('3 / 3')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: '次のページ' })).toBeDisabled()

    fireEvent.click(screen.getByRole('button', { name: '前のページ' }))
    expect(onOffsetChange).toHaveBeenCalledWith(50) // max(0, 100 - 50)
  })
})

describe('TableStateRow', () => {
  function renderRow(props: Parameters<typeof TableStateRow>[0]) {
    return renderWithProviders(
      <table><tbody><TableStateRow {...props} /></tbody></table>,
    )
  }

  it('shows the loading label while loading', () => {
    renderRow({ colSpan: 3, loading: true })
    expect(screen.getByText('読み込み中…')).toBeInTheDocument()
  })

  it('shows the (default) empty label when empty', () => {
    renderRow({ colSpan: 3, empty: true })
    expect(screen.getByText('データがありません。')).toBeInTheDocument()
  })

  it('renders nothing when the table has rows (not loading/empty/error)', () => {
    const { container } = renderRow({ colSpan: 3 })
    // Only the wrapping table/tbody exist; no state <tr> was emitted.
    expect(container.querySelector('td')).toBeNull()
  })
})

describe('SortableTh', () => {
  function renderTh(sort: SortState, onSort = vi.fn()) {
    renderWithProviders(
      <table><thead><tr>
        <SortableTh column="amount_cents" sort={sort} onSort={onSort}>金額</SortableTh>
      </tr></thead></table>,
    )
    return onSort
  }

  it('reflects the active sort direction via aria-sort', () => {
    renderTh({ by: 'amount_cents', dir: 'asc' })
    expect(screen.getByRole('columnheader')).toHaveAttribute('aria-sort', 'ascending')
  })

  it('has no aria-sort when it is not the active column', () => {
    renderTh({ by: 'value_date', dir: 'asc' })
    expect(screen.getByRole('columnheader')).not.toHaveAttribute('aria-sort')
  })

  it('calls onSort with its column when clicked', () => {
    const onSort = renderTh({ by: 'value_date', dir: 'asc' })
    fireEvent.click(screen.getByRole('columnheader'))
    expect(onSort).toHaveBeenCalledWith('amount_cents')
  })
})
