import { describe, it, expect } from 'vitest'
import { screen } from '@testing-library/react'
import { renderWithProviders } from '@tests/render'
import { InfoDot } from './InfoDot'

describe('InfoDot (glossary tooltip)', () => {
  it('exposes an accessible trigger and renders each glossary entry', () => {
    renderWithProviders(
      <InfoDot
        label="用語の説明"
        entries={[
          { term: '消込', def: '入金と請求を突き合わせる作業。' },
          { term: '突合', def: '入金明細と請求を照合すること。' },
        ]}
      />,
    )

    expect(screen.getByRole('button', { name: '用語の説明' })).toBeInTheDocument()
    // The tooltip content is in the DOM (revealed on hover/focus via CSS).
    expect(screen.getByText('消込')).toBeInTheDocument()
    expect(screen.getByText('入金と請求を突き合わせる作業。')).toBeInTheDocument()
    expect(screen.getByText('突合')).toBeInTheDocument()
    expect(screen.getByRole('tooltip')).toBeInTheDocument()
  })
})
