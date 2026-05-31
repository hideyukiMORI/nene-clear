type IconSize = 'sm' | 'md' | 'lg'

interface IconProps {
  name: string
  size?: IconSize
  className?: string
}

const sizeClass: Record<IconSize, string> = {
  sm: 'ic ic-sm',
  md: 'ic',
  lg: 'ic ic-lg',
}

export function Icon({ name, size = 'md', className }: IconProps) {
  return (
    <svg className={[sizeClass[size], className].filter(Boolean).join(' ')}>
      <use href={`#i-${name}`} />
    </svg>
  )
}
