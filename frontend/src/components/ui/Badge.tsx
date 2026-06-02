import type { ReactNode } from 'react'

export type BadgeVariant = 'ok' | 'warn' | 'bad' | 'info' | 'neut'

interface BadgeProps {
  variant: BadgeVariant
  dot?: boolean
  children: ReactNode
  className?: string
}

export function Badge({ variant, dot = false, children, className }: BadgeProps) {
  return (
    <span className={['badge', `b-${variant}`, className].filter(Boolean).join(' ')}>
      {dot && <span className="dotc" />}
      {children}
    </span>
  )
}
