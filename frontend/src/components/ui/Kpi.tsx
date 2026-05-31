import type { ReactNode } from 'react'

type KpiAccent = 'default' | 'accent' | 'warn' | 'bad'

interface KpiProps {
  icon: ReactNode
  label: string
  value: ReactNode
  sub?: ReactNode
  accent?: KpiAccent
}

const accentClass: Record<KpiAccent, string> = {
  default: 'kpi',
  accent: 'kpi accent',
  warn: 'kpi warn',
  bad: 'kpi badn',
}

export function Kpi({ icon, label, value, sub, accent = 'default' }: KpiProps) {
  return (
    <div className={accentClass[accent]}>
      <div className="kpi-top">
        <span className="kpi-ic">{icon}</span>
        {label}
      </div>
      <div className="num">{value}</div>
      {sub && <div className="sub">{sub}</div>}
    </div>
  )
}

export function KpiGrid({ children }: { children: ReactNode }) {
  return <div className="kpi-grid">{children}</div>
}
