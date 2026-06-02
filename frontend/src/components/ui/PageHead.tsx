import type { ReactNode } from 'react'

interface PageHeadProps {
  title: string
  sub?: string
  actions?: ReactNode
  plain?: boolean
}

export function PageHead({ title, sub, actions, plain = false }: PageHeadProps) {
  return (
    <div className={plain ? 'page-head plain' : 'page-head'}>
      <div>
        <h1>{title}</h1>
        {sub && <p>{sub}</p>}
      </div>
      {actions && <div className="wrapw">{actions}</div>}
    </div>
  )
}
