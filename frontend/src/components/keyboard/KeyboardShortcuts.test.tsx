import { describe, it, expect, beforeEach } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { MemoryRouter, Routes, Route, useLocation } from 'react-router-dom'
import { KeyboardShortcuts } from './KeyboardShortcuts'

function LocationProbe() {
  const location = useLocation()
  return <div data-testid="loc">{location.pathname}</div>
}

function renderAt(path: string) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <input data-kbd="search" aria-label="search" />
      <KeyboardShortcuts />
      <Routes>
        <Route path="*" element={<LocationProbe />} />
      </Routes>
    </MemoryRouter>,
  )
}

const loc = () => screen.getByTestId('loc').textContent

describe('KeyboardShortcuts', () => {
  beforeEach(() => {
    document.body.focus()
  })

  it('g → d navigates to the dashboard', () => {
    renderAt('/admin/reconciliation')
    fireEvent.keyDown(document, { key: 'g' })
    fireEvent.keyDown(document, { key: 'd' })
    expect(loc()).toBe('/admin')
  })

  it('g → r navigates to reconciliation', () => {
    renderAt('/admin')
    fireEvent.keyDown(document, { key: 'g' })
    fireEvent.keyDown(document, { key: 'r' })
    expect(loc()).toBe('/admin/reconciliation')
  })

  it('? opens the cheat-sheet overlay and Esc closes it', () => {
    renderAt('/admin')
    fireEvent.keyDown(document, { key: '?' })
    expect(screen.getByRole('dialog')).toBeInTheDocument()
    fireEvent.keyDown(document, { key: 'Escape' })
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('Ctrl+K opens the palette; j then Enter goes to the 2nd command', () => {
    renderAt('/admin')
    fireEvent.keyDown(document, { key: 'k', ctrlKey: true })
    expect(screen.getByRole('listbox')).toBeInTheDocument()
    // Cursor starts at 0 (dashboard); j → 1 (reconciliation); Enter navigates.
    fireEvent.keyDown(document, { key: 'j' })
    fireEvent.keyDown(document, { key: 'Enter' })
    expect(loc()).toBe('/admin/reconciliation')
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument()
  })

  it('palette closes on Esc', () => {
    renderAt('/admin')
    fireEvent.keyDown(document, { key: 'k', ctrlKey: true })
    expect(screen.getByRole('listbox')).toBeInTheDocument()
    fireEvent.keyDown(document, { key: 'Escape' })
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument()
  })

  it('Esc blurs a focused field, but not while composing (IME)', () => {
    renderAt('/admin')
    const input = screen.getByLabelText('search')
    input.focus()
    expect(document.activeElement).toBe(input)

    // IME composition active → Esc must not steal the cancel.
    fireEvent.keyDown(input, { key: 'Escape', isComposing: true })
    expect(document.activeElement).toBe(input)

    // Not composing → Esc blurs.
    fireEvent.keyDown(input, { key: 'Escape' })
    expect(document.activeElement).not.toBe(input)
  })

  it('single keys are ignored while typing in a field', () => {
    renderAt('/admin/reconciliation')
    const input = screen.getByLabelText('search')
    input.focus()
    fireEvent.keyDown(input, { key: 'g' })
    fireEvent.keyDown(input, { key: 'd' })
    expect(loc()).toBe('/admin/reconciliation')
  })

  it('IME keydown (keyCode 229) does not start a g-sequence', () => {
    renderAt('/admin/reconciliation')
    fireEvent.keyDown(document, { key: 'g', keyCode: 229 })
    fireEvent.keyDown(document, { key: 'd' })
    expect(loc()).toBe('/admin/reconciliation')
  })

  it('u returns to the list from a detail view but not from a /new form', () => {
    const { unmount } = renderAt('/admin/users/42')
    fireEvent.keyDown(document, { key: 'u' })
    expect(loc()).toBe('/admin/users')
    unmount()

    renderAt('/admin/users/new')
    fireEvent.keyDown(document, { key: 'u' })
    expect(loc()).toBe('/admin/users/new')
  })
})
