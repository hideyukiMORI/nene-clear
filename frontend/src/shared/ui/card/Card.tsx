import type { ReactNode } from 'react'

interface CardProps { children: ReactNode; className?: string; style?: React.CSSProperties }

export function Card({ children, className, style }: CardProps) {
  return <div className={['card', className].filter(Boolean).join(' ')} style={style}>{children}</div>
}

interface CardHeadProps {
  children: ReactNode
  title?: ReactNode
  sub?: string
  actions?: ReactNode
}

export function CardHead({ children, title, sub, actions }: CardHeadProps) {
  if (title !== undefined) {
    return (
      <div className="card-head">
        <div>
          <h2>{title}</h2>
          {sub && <p>{sub}</p>}
        </div>
        {actions && <div>{actions}</div>}
      </div>
    )
  }
  return <div className="card-head">{children}</div>
}

export function CardBody({ children, className }: { children: ReactNode; className?: string }) {
  return <div className={['card-body', className].filter(Boolean).join(' ')}>{children}</div>
}

export function CardFoot({ children }: { children: ReactNode }) {
  return <div className="card-foot">{children}</div>
}
