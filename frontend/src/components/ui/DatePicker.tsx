import { useEffect, useRef, useState } from 'react'
import { Icon } from './Icon'

/**
 * Type-first date field (YYYY/MM/DD) with a calendar popover — ports the design
 * pack's date picker (design 04). Controlled: `value` is a `YYYY/MM/DD` string
 * (or ''), `onChange` fires on valid pick or typed input.
 */
interface DatePickerProps {
  value: string
  onChange: (value: string) => void
  placeholder?: string
  ariaLabel?: string
}

const WD = ['日', '月', '火', '水', '木', '金', '土']
const pad = (n: number) => String(n).padStart(2, '0')
const fmt = (y: number, m: number, d: number) => `${y}/${pad(m)}/${pad(d)}`

function parse(v: string): { y: number; mo: number; d: number; dt: Date } | null {
  const m = (v || '').match(/^(\d{4})[/-](\d{1,2})[/-](\d{1,2})$/)
  if (!m) return null
  const y = +m[1], mo = +m[2], d = +m[3]
  const dt = new Date(y, mo - 1, d)
  if (dt.getFullYear() !== y || dt.getMonth() !== mo - 1 || dt.getDate() !== d) return null
  return { y, mo, d, dt }
}

function autoformat(raw: string): string {
  const s = (raw || '').replace(/\D/g, '').slice(0, 8)
  let o = s.slice(0, 4)
  if (s.length > 4) o += '/' + s.slice(4, 6)
  if (s.length > 6) o += '/' + s.slice(6, 8)
  return o
}

export function DatePicker({ value, onChange, placeholder = 'YYYY/MM/DD', ariaLabel }: DatePickerProps) {
  const fieldRef = useRef<HTMLDivElement>(null)
  const [open, setOpen] = useState(false)
  const [pos, setPos] = useState<{ top: number; left: number }>({ top: 0, left: 0 })
  const now = new Date()
  const seed = parse(value)
  const [view, setView] = useState({ y: seed ? seed.y : now.getFullYear(), m: seed ? seed.mo : now.getMonth() + 1 })

  useEffect(() => {
    if (!open) return
    function onDocClick(e: MouseEvent) {
      if (!fieldRef.current?.contains(e.target as Node)) setOpen(false)
    }
    function onKey(e: KeyboardEvent) { if (e.key === 'Escape') setOpen(false) }
    document.addEventListener('mousedown', onDocClick)
    document.addEventListener('keydown', onKey)
    return () => { document.removeEventListener('mousedown', onDocClick); document.removeEventListener('keydown', onKey) }
  }, [open])

  function openPopover() {
    const input = fieldRef.current?.querySelector('input')
    const r = input?.getBoundingClientRect()
    if (r) setPos({ top: r.bottom + 6, left: Math.min(r.left, window.innerWidth - 256) })
    const p = parse(value)
    if (p) setView({ y: p.y, m: p.mo })
    setOpen((o) => !o)
  }

  function pick(d: number) {
    onChange(fmt(view.y, view.m, d))
    setOpen(false)
  }

  function shiftMonth(delta: number) {
    setView((v) => {
      let m = v.m + delta, y = v.y
      if (m < 1) { m = 12; y-- }
      if (m > 12) { m = 1; y++ }
      return { y, m }
    })
  }

  const invalid = value !== '' && !parse(value)
  const sel = parse(value)
  const t = new Date()
  const first = new Date(view.y, view.m - 1, 1).getDay()
  const days = new Date(view.y, view.m, 0).getDate()

  return (
    <div className="datefield" ref={fieldRef}>
      <input
        className={['inp', 'dateinput', invalid ? 'invalid' : ''].filter(Boolean).join(' ')}
        type="text"
        inputMode="numeric"
        autoComplete="off"
        maxLength={10}
        placeholder={placeholder}
        aria-label={ariaLabel}
        value={value}
        onChange={(e) => onChange(autoformat(e.target.value))}
      />
      <button type="button" className="date-btn" tabIndex={-1} aria-label="カレンダーを開く" onClick={openPopover}>
        <Icon name="calendar" size="sm" />
      </button>

      {open && (
        <div className="dp-pop" style={{ top: pos.top, left: pos.left }}>
          <div className="dp-head">
            <button type="button" className="dp-nav" aria-label="前の月" onClick={() => shiftMonth(-1)}><Icon name="chev-l" size="sm" /></button>
            <span className="dp-title">{view.y}年 {pad(view.m)}月</span>
            <button type="button" className="dp-nav" aria-label="次の月" onClick={() => shiftMonth(1)}><Icon name="chev-r" size="sm" /></button>
          </div>
          <div className="dp-wd">{WD.map((w, i) => <span key={w} className={i === 0 ? 'sun' : i === 6 ? 'sat' : ''}>{w}</span>)}</div>
          <div className="dp-grid">
            {Array.from({ length: first }).map((_, i) => <span key={`e${i}`} className="dp-day empty" />)}
            {Array.from({ length: days }).map((_, i) => {
              const d = i + 1
              const isT = view.y === t.getFullYear() && view.m === t.getMonth() + 1 && d === t.getDate()
              const isS = !!sel && sel.y === view.y && sel.mo === view.m && sel.d === d
              return (
                <button key={d} type="button" className={['dp-day', isT ? 'today' : '', isS ? 'sel' : ''].filter(Boolean).join(' ')} onClick={() => pick(d)}>{d}</button>
              )
            })}
          </div>
          <div className="dp-foot">
            <button type="button" className="dp-today" onClick={() => { onChange(fmt(t.getFullYear(), t.getMonth() + 1, t.getDate())); setOpen(false) }}>
              <Icon name="calendar" size="sm" />今日
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
