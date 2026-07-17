import type { ReactNode } from 'react'

interface PageHeadProps {
  title: string
  sub?: string
  /** Optional affordance rendered next to the title (e.g. a glossary InfoDot). */
  info?: ReactNode
  actions?: ReactNode
  plain?: boolean
}

export function PageHead({ title, sub, info, actions, plain = false }: PageHeadProps) {
  return (
    <div className={plain ? 'page-head plain' : 'page-head'}>
      <div>
        <h1 className="page-head-title">{title}{info}</h1>
        {sub && <p>{sub}</p>}
      </div>
      {actions && <div className="wrapw">{actions}</div>}
    </div>
  )
}
