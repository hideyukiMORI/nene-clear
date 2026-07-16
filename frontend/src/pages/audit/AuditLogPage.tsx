import { Fragment, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { listAuditEvents } from '@/api/endpoints'
import type { AuditEvent, AuditAction } from '@/types'
import { Icon, StatusBadge, Button, Card, DataTable, TableStateRow, Pager, FilterBar, FilterField, PageHead, DatePicker, SortableTh, nextSort } from '@/components/ui'
import type { StatusMeta, SortState } from '@/components/ui'
import { useTranslation } from '@/hooks/useTranslation'
import { useRowCursor } from '@/components/keyboard'
import { formatDateTime } from '@/shared/lib/format'

const PAGE = 20

// Every registered action (terminology §2) with its display label key and a
// badge tone: green for value-creating, red for reversing/deleting/failing.
const EVENT_META: Record<AuditAction, StatusMeta> = {
  bank_import:                 { v: 'info', labelKey: 'audit.event.bank_import' },
  bank_import_batch_reversed:  { v: 'bad',  labelKey: 'audit.event.bank_import_batch_reversed' },
  reconciliation_confirmed:    { v: 'ok',   labelKey: 'audit.event.reconciliation_confirmed' },
  reconciliation_reversed:     { v: 'bad',  labelKey: 'audit.event.reconciliation_reversed' },
  client_credit_applied:       { v: 'ok',   labelKey: 'audit.event.client_credit_applied' },
  manual_receivable_created:   { v: 'ok',   labelKey: 'audit.event.manual_receivable_created' },
  manual_receivable_updated:   { v: 'info', labelKey: 'audit.event.manual_receivable_updated' },
  manual_receivable_cancelled: { v: 'bad',  labelKey: 'audit.event.manual_receivable_cancelled' },
  dunning_sent:                { v: 'info', labelKey: 'audit.event.dunning_sent' },
  dunning_paused:              { v: 'warn', labelKey: 'audit.event.dunning_paused' },
  dunning_resumed:             { v: 'ok',   labelKey: 'audit.event.dunning_resumed' },
  user_created:                { v: 'ok',   labelKey: 'audit.event.user_created' },
  invitation_accepted:         { v: 'ok',   labelKey: 'audit.event.invitation_accepted' },
  user_updated:                { v: 'info', labelKey: 'audit.event.user_updated' },
  user_deleted:                { v: 'bad',  labelKey: 'audit.event.user_deleted' },
  organization_created:        { v: 'ok',   labelKey: 'audit.event.organization_created' },
  organization_deleted:        { v: 'bad',  labelKey: 'audit.event.organization_deleted' },
  login_succeeded:             { v: 'ok',   labelKey: 'audit.event.login_succeeded' },
  login_failed:                { v: 'bad',  labelKey: 'audit.event.login_failed' },
  clear_settings_updated:      { v: 'info', labelKey: 'audit.event.clear_settings_updated' },
  mfa_enabled:                 { v: 'ok',   labelKey: 'audit.event.mfa_enabled' },
  mfa_disabled:                { v: 'warn', labelKey: 'audit.event.mfa_disabled' },
}

const ACTIONS = Object.keys(EVENT_META) as AuditAction[]

/** Pretty-print one before/after/metadata block as stable JSON. */
function StateBlock({ label, value }: { label: string; value: unknown }) {
  return (
    <div className="audit-state">
      <div className="audit-state-label muted">{label}</div>
      <pre className="audit-json">{JSON.stringify(value, null, 2)}</pre>
    </div>
  )
}

function EventDetail({ event }: { event: AuditEvent }) {
  const { t } = useTranslation()
  const { before, after, metadata } = event

  if (before === null && after === null && metadata === null) {
    return <span className="muted">{t('audit.noChange')}</span>
  }

  return (
    <div className="audit-detail">
      {metadata !== null && <StateBlock label="—" value={metadata} />}
      {before !== null && <StateBlock label={t('audit.before')} value={before} />}
      {after !== null && <StateBlock label={t('audit.after')} value={after} />}
    </div>
  )
}

export default function AuditLogPage() {
  const { t } = useTranslation()
  const [action, setAction] = useState('')
  const [actor, setActor] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [applied, setApplied] = useState<{ action: string; actor: string; from: string; to: string; offset: number }>({ action: '', actor: '', from: '', to: '', offset: 0 })
  const [sort, setSort] = useState<SortState>({ by: 'occurred_at', dir: 'desc' })
  const [expanded, setExpanded] = useState<ReadonlySet<number>>(new Set())

  const auditQ = useQuery({
    queryKey: ['audit-events', applied, sort],
    queryFn: ({ signal }) => listAuditEvents({
      action: applied.action || undefined,
      actorId: applied.actor || undefined,
      occurredFrom: applied.from ? applied.from.replace(/\//g, '-') : undefined,
      occurredTo: applied.to ? applied.to.replace(/\//g, '-') : undefined,
      sortBy: sort.by,
      sortDir: sort.dir,
      limit: PAGE,
      offset: applied.offset,
    }, signal),
  })

  function search() { setApplied({ action, actor, from, to, offset: 0 }) }
  function clear() { setAction(''); setActor(''); setFrom(''); setTo(''); setApplied({ action: '', actor: '', from: '', to: '', offset: 0 }) }
  function goPage(off: number) { setApplied(p => ({ ...p, offset: off })) }
  function onSort(col: string) { setSort(s => nextSort(s, col)); setApplied(p => ({ ...p, offset: 0 })) }

  function toggle(id: number) {
    setExpanded(prev => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }

  function actorLabel(actorId: number): string {
    return actorId === 0
      ? t('audit.actor.system')
      : t('audit.actor.user', { id: actorId })
  }

  const total = auditQ.data?.total ?? 0

  // Row cursor (j/k); Enter/o toggles the cursored event's detail panel.
  const rows = auditQ.data?.items ?? []
  const cursor = useRowCursor(rows.length, (i) => {
    const event = rows[i]
    if (event) toggle(event.audit_event_id)
  })

  return (
    <>
      <PageHead title={t('audit.title')} sub={t('audit.subtitle')} />

      <FilterBar>
        <FilterField label={t('audit.filter.event')}>
          <select className="inp" value={action} onChange={e => setAction(e.target.value)}>
            <option value="">{t('audit.filter.all')}</option>
            {ACTIONS.map(a => (
              <option key={a} value={a}>{t(EVENT_META[a].labelKey)}</option>
            ))}
          </select>
        </FilterField>
        <FilterField label={t('audit.table.actor')}>
          <input className="inp tnum" data-kbd="search" type="number" placeholder="#" style={{ width: 110 }} value={actor} onChange={e => setActor(e.target.value)} />
        </FilterField>
        <FilterField label={t('audit.table.time')}>
          <div className="range-pair">
            <DatePicker value={from} onChange={setFrom} />
            <span className="tilde">〜</span>
            <DatePicker value={to} onChange={setTo} />
          </div>
        </FilterField>
        <div className="filter-actions">
          <span className="filter-count">{t('filter.count', { n: total })}</span>
          <Button variant="ghost" size="sm" onClick={clear}><Icon name="refresh" size="sm" />{t('filter.clear')}</Button>
          <Button variant="primary" size="sm" onClick={search}><Icon name="search" size="sm" />{t('common.search')}</Button>
        </div>
      </FilterBar>

      <Card>
        <DataTable>
          <thead>
            <tr>
              <SortableTh column="occurred_at" sort={sort} onSort={onSort}>{t('audit.table.time')}</SortableTh>
              <SortableTh column="action" sort={sort} onSort={onSort}>{t('audit.table.event')}</SortableTh>
              <SortableTh column="actor_id" sort={sort} onSort={onSort}>{t('audit.table.actor')}</SortableTh>
              <th>{t('audit.table.detail')}</th>
            </tr>
          </thead>
          <tbody>
            <TableStateRow colSpan={4} loading={auditQ.isLoading} empty={auditQ.data?.items.length === 0} emptyKey="audit.empty" />
            {auditQ.data?.items.map((event, index) => {
              const isOpen = expanded.has(event.audit_event_id)
              return (
                <Fragment key={event.audit_event_id}>
                  <tr data-kbd-row={index} className={cursor === index ? 'is-cursor' : undefined}>
                    <td className="muted">{formatDateTime(event.occurred_at)}</td>
                    <td><StatusBadge map={EVENT_META} value={event.action} dot fallback={event.action} /></td>
                    <td>{actorLabel(event.actor_id)}</td>
                    <td className="row-act">
                      <Button variant="ghost" size="sm" onClick={() => toggle(event.audit_event_id)}>
                        {isOpen ? t('audit.hideDetail') : t('audit.viewDetail')}
                      </Button>
                    </td>
                  </tr>
                  {isOpen && (
                    <tr className="audit-detail-row">
                      <td colSpan={4}><EventDetail event={event} /></td>
                    </tr>
                  )}
                </Fragment>
              )
            })}
          </tbody>
        </DataTable>
        <Pager offset={applied.offset} pageSize={PAGE} total={total} onOffsetChange={goPage} />
      </Card>
    </>
  )
}
