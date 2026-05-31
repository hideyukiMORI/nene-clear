import type { ReactNode, ButtonHTMLAttributes } from 'react'

export type ButtonVariant = 'primary' | 'ghost' | 'ghost-danger' | 'ghost-warn' | 'danger' | 'warn' | 'link'

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant
  size?: 'sm' | 'md'
  children: ReactNode
}

const variantClass: Record<ButtonVariant, string> = {
  primary: 'btn btn-primary',
  ghost: 'btn btn-ghost',
  'ghost-danger': 'btn btn-ghost-danger',
  'ghost-warn': 'btn btn-ghost-warn',
  danger: 'btn btn-danger',
  warn: 'btn btn-warn',
  link: 'btn-link',
}

export function Button({ variant = 'primary', size, children, className, ...rest }: ButtonProps) {
  const cls = [variantClass[variant], size === 'sm' ? 'btn-sm' : '', className]
    .filter(Boolean)
    .join(' ')
  return (
    <button className={cls} {...rest}>
      {children}
    </button>
  )
}
