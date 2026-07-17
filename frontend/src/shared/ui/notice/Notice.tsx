import type { ReactNode } from 'react'
import { Icon } from '@/shared/ui/icon'

export type NoticeVariant = 'ok' | 'warn' | 'bad' | 'info'

const iconName: Record<NoticeVariant, string> = {
  ok: 'check',
  warn: 'alert',
  bad: 'alert',
  info: 'info',
}

interface NoticeProps {
  variant: NoticeVariant
  children: ReactNode
  className?: string
}

export function Notice({ variant, children, className }: NoticeProps) {
  return (
    <div className={['notice', variant, className].filter(Boolean).join(' ')}>
      <Icon name={iconName[variant]} />
      <span>{children}</span>
    </div>
  )
}
