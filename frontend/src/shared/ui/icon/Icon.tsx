import type { CSSProperties } from 'react'

type IconSize = 'sm' | 'md' | 'lg'

interface IconProps {
  name: string
  size?: IconSize
  className?: string
  style?: CSSProperties
}

const sizeClass: Record<IconSize, string> = {
  sm: 'ic ic-sm',
  md: 'ic',
  lg: 'ic ic-lg',
}

export function Icon({ name, size = 'md', className, style }: IconProps) {
  return (
    <svg className={[sizeClass[size], className].filter(Boolean).join(' ')} style={style}>
      <use href={`#i-${name}`} />
    </svg>
  )
}
