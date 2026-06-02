import type { ReactNode } from 'react'

interface FilterBarProps {
  children: ReactNode
  className?: string
}

export function FilterBar({ children, className }: FilterBarProps) {
  return (
    <div className={['filterbar', className].filter(Boolean).join(' ')}>
      {children}
    </div>
  )
}

interface FilterFieldProps {
  label: string
  children: ReactNode
}

export function FilterField({ label, children }: FilterFieldProps) {
  return (
    <div className="field">
      <label>{label}</label>
      {children}
    </div>
  )
}
