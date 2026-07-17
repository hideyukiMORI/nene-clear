import type { ReactNode } from 'react'

interface Tab {
  key: string
  label: ReactNode
}

interface TabsProps {
  tabs: Tab[]
  active: string
  onChange: (key: string) => void
  id?: string
}

export function Tabs({ tabs, active, onChange, id }: TabsProps) {
  return (
    <div className="tabs" id={id}>
      {tabs.map(tab => (
        <button
          key={tab.key}
          className={['tab', active === tab.key ? 'on' : ''].filter(Boolean).join(' ')}
          onClick={() => onChange(tab.key)}
        >
          {tab.label}
        </button>
      ))}
    </div>
  )
}
