import { Fragment, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { listAuditEvents } from '@/api/endpoints'
import type { AuditEvent, AuditEventType } from '@/types'
import { Badge, Button, Card, DataTable, TableStateRow, Pager, FilterBar, FilterField, PageHead } from '@/components/ui'
import type { BadgeVariant } from '@/components/ui'
import { useTranslation } from '@/hooks/useTranslation'
import type { MessageKey } from '@/locales'
import { formatDateTime } from '@/utils/format'

const PAGE = 20

// Every registered event_type (terminology §2) with its display label key and a
// badge tone: green for value-creating, red for reversing/deleting/failing.
const EVENT_META: Record<AuditEventType, { v: BadgeVariant; labelKey: MessageKey }> = {
  bank_import:                 { v: 'info', labelKey: 'audit.event.bank_import' },
  bank_import_batch_reversed:  { v: 'bad',  labelKey: 'audit.event.bank_import_batch_reversed' },
  reconciliation_confirmed:    { v: 'ok',   labelKey: 'audit.event.reconciliation_confirmed' },
  reconciliation_reversed:     { v: 'bad',  labelKey: 'audit.event.reconciliation_reversed' },
  client_credit_applied:       { v: 'ok',   labelKey: 'audit.event.client_credit_applied' },
  dunning_sent:                { v: 'info', labelKey: 'audit.event.dunning_sent' },
  dunning_paused:              { v: 'warn', labelKey: 'audit.event.dunning_paused' },
  dunning_resumed:             { v: 'ok',   labelKey: 'audit.event.dunning_resumed' },
  user_created:                { v: 'ok',   labelKey: 'audit.event.user_created' },
  user_updated:                { v: 'info', labelKey: 'audit.event.user_updated' },
  user_deleted:                { v: 'bad',  labelKey: 'audit.event.user_deleted' },
  organization_created:        { v: 'ok',   labelKey: 'audit.event.organization_created' },
  organization_deleted:        { v: 'bad',  labelKey: 'audit.event.organization_deleted' },
  login_succeeded:             { v: 'ok',   labelKey: 'audit.event.login_succeeded' },
  login_failed:                { v: 'bad',  labelKey: 'audit.event.login_failed' },
}

const EVENT_TYPES = Object.keys(EVENT_META) as AuditEventType[]

/** Pretty-print one before/after block, or any payload value, as stable JSON. */
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
  const payload = event.payload
  const before = payload['before']
  const after = payload['after']
  // Context keys are everything in the payload that is not the before/after diff.
  const context = Object.fromEntries(
    Object.entries(payload).filter(([k]) => k !== 'before' && k !== 'after'),
  )
  const hasContext = Object.keys(context).length > 0

  if (before === undefined && after === undefined && !hasContext) {
    return <span className="muted">{t('audit.noChange')}</span>
  }

  return (
    <div className="audit-detail">
      {hasContext && <StateBlock label="—" value={context} />}
      {before !== undefined && <StateBlock label={t('audit.before')} value={before} />}
      {after !== undefined && <StateBlock label={t('audit.after')} value={after} />}
    </div>
  )
}

export default function AuditLogPage() {
  const { t } = useTranslation()
  const [eventType, setEventType] = useState('')
  const [applied, setApplied] = useState<{ eventType: string; offset: number }>({ eventType: '', offset: 0 })
  const [expanded, setExpanded] = useState<ReadonlySet<number>>(new Set())

  const auditQ = useQuery({
    queryKey: ['audit-events', applied],
    queryFn: ({ signal }) => listAuditEvents({
      eventType: applied.eventType || undefined,
      limit: PAGE,
      offset: applied.offset,
    }, signal),
  })

  function search() { setApplied({ eventType, offset: 0 }) }
  function goPage(off: number) { setApplied(p => ({ ...p, offset: off })) }

  function toggle(id: number) {
    setExpanded(prev => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }

  function actorLabel(actorUserId: number): string {
    return actorUserId === 0
      ? t('audit.actor.system')
      : t('audit.actor.user', { id: actorUserId })
  }

  const total = auditQ.data?.total ?? 0
  const currentPage = Math.floor(applied.offset / PAGE) + 1
  const totalPages = Math.ceil(total / PAGE)

  return (
    <>
      <PageHead title={t('audit.title')} sub={t('audit.subtitle')} />

      <FilterBar>
        <FilterField label={t('audit.filter.event')}>
          <select className="inp" value={eventType} onChange={e => setEventType(e.target.value)}>
            <option value="">{t('audit.filter.all')}</option>
            {EVENT_TYPES.map(type => (
              <option key={type} value={type}>{t(EVENT_META[type].labelKey)}</option>
            ))}
          </select>
        </FilterField>
        <Button variant="primary" onClick={search}>{t('common.search')}</Button>
      </FilterBar>

      <Card>
        <DataTable>
          <thead>
            <tr>
              <th>{t('audit.table.time')}</th>
              <th>{t('audit.table.event')}</th>
              <th>{t('audit.table.actor')}</th>
              <th>{t('audit.table.detail')}</th>
            </tr>
          </thead>
          <tbody>
            <TableStateRow colSpan={4} loading={auditQ.isLoading} empty={auditQ.data?.items.length === 0} emptyKey="audit.empty" />
            {auditQ.data?.items.map(event => {
              const meta = EVENT_META[event.event_type]
              const isOpen = expanded.has(event.audit_event_id)
              return (
                <Fragment key={event.audit_event_id}>
                  <tr>
                    <td className="muted">{formatDateTime(event.occurred_at)}</td>
                    <td><Badge variant={meta?.v ?? 'neut'} dot>{meta ? t(meta.labelKey) : event.event_type}</Badge></td>
                    <td>{actorLabel(event.actor_user_id)}</td>
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
        {total > PAGE && (
          <Pager
            current={currentPage}
            total={totalPages}
            onPrev={() => goPage(Math.max(0, applied.offset - PAGE))}
            onNext={() => goPage(applied.offset + PAGE)}
            itemCount={total}
            pageSize={PAGE}
            offset={applied.offset}
          />
        )}
      </Card>
    </>
  )
}
