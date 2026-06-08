import { Fragment, useEffect, useState, type MouseEvent as ReactMouseEvent, type ReactNode } from 'react'
import { useTranslation } from '@/hooks/useTranslation'
import { Icon } from '@/components/ui'
import { HELP_LABELS, HELP_SECTIONS, type Bi, type HelpBlock } from './help-content'

/**
 * Help page (design handoff): a light hero band that bleeds to the topbar and
 * content edges, a sticky numbered table of contents with scroll-spy, and
 * numbered/iconed sections built from rich blocks (steps, status flows, glossary
 * cards, notes with an icon, option cards, a keyboard grid, and an FAQ
 * accordion). Content lives in `help-content.ts` as ja/en pairs. Scrolling is
 * scoped to the app shell's `.scroll` container (the window does not scroll).
 * Distinct from the `?` shortcut overlay.
 */
export default function HelpPage() {
  const { locale } = useTranslation()
  const pick = (b: Bi): string => (locale === 'en' ? b.en : b.ja)
  const activeId = useScrollSpy()

  // Honour a deep link like /admin/help#disclaimer (the in-page ToC uses native
  // anchors; this covers arriving from elsewhere). Scroll the shell container,
  // not the window.
  useEffect(() => {
    const id = window.location.hash.slice(1)
    if (id === '') return
    const target = document.getElementById(id)
    const scroller = scrollerOf(target)
    if (target === null || scroller === null) return
    scroller.scrollTo({ top: offsetWithin(target, scroller) - 16 })
  }, [])

  return (
    <div className="help-page" id="help-top">
      <header className="help-hero">
        <div className="hero-grid" />
        <div className="hero-inner">
          <div className="eyebrow">
            <Icon name="help" />
            {pick(HELP_LABELS.eyebrow)}
          </div>
          <h1>{pick(HELP_LABELS.heroTitle)}</h1>
          <p>{pick(HELP_LABELS.heroLede)}</p>
          <div className="hero-meta">
            <span className="hero-chip">
              <Icon name="reconcile" />
              {pick(HELP_LABELS.chipReconcile)}
            </span>
            <span className="hero-chip">
              <Icon name="shield" />
              {pick(HELP_LABELS.chipSelfhost)}
            </span>
            <span className="hero-chip">
              <b>{pick(HELP_LABELS.chipUpdated)}</b>{' '}
              <span className="num">{HELP_LABELS.updatedDate}</span>
            </span>
          </div>
        </div>
      </header>

      <div className="help-layout">
        <nav className="help-toc" id="toc" aria-label={pick(HELP_LABELS.toc)} onClick={onTocClick}>
          <div className="toc-title">{pick(HELP_LABELS.toc)}</div>
          {HELP_SECTIONS.map((s, i) => (
            <a
              key={s.id}
              href={`#${s.id}`}
              className={[s.id === activeId ? 'active' : '', s.admin ? 'admin-only' : ''].filter(Boolean).join(' ')}
            >
              <span className="tn">{String(i + 1).padStart(2, '0')}</span>
              <span className="tt">{pick(s.title)}</span>
            </a>
          ))}
        </nav>

        <article className="help-article">
          {HELP_SECTIONS.map((s, i) => (
            <section key={s.id} id={s.id} className="hsec">
              <div className="hsec-head">
                <span className="hsec-no">{String(i + 1).padStart(2, '0')}</span>
                <h2>
                  {s.icon !== undefined && (
                    <span className="hsec-ic"><Icon name={s.icon} /></span>
                  )}
                  {pick(s.title)}
                  {s.admin === true && <span className="tag-admin">{pick(HELP_LABELS.adminBadge)}</span>}
                </h2>
              </div>
              {s.blocks.map((block, bi) => (
                <Block key={bi} block={block} pick={pick} />
              ))}
              <a className="backtop" href="#help-top" onClick={onBackToTop}>
                <Icon name="arrow-up" />
                {pick(HELP_LABELS.backToToc)}
              </a>
            </section>
          ))}

          <div className="help-foot">
            <div className="hf-ic"><Icon name="mail" /></div>
            <div>
              <div className="hf-t">{pick(HELP_LABELS.footTitle)}</div>
              <div className="hf-d"><Rich text={pick(HELP_LABELS.footDesc)} /></div>
            </div>
          </div>
        </article>
      </div>
    </div>
  )
}

/** The app shell's scrollable container that holds the routed page. */
function scrollerOf(el: Element | null): HTMLElement | null {
  return (el?.closest('.scroll') as HTMLElement | null) ?? null
}

/** Distance (px) from the scroll container's top to an element's top. */
function offsetWithin(el: HTMLElement, scroller: HTMLElement): number {
  return el.getBoundingClientRect().top - scroller.getBoundingClientRect().top + scroller.scrollTop
}

/** Smooth-scrolls the shell container to a ToC target (native anchors would jump). */
function onTocClick(e: ReactMouseEvent<HTMLElement>): void {
  const link = (e.target as HTMLElement).closest('a')
  if (link === null) return
  const id = link.getAttribute('href')?.slice(1) ?? ''
  const target = document.getElementById(id)
  const scroller = scrollerOf(link)
  if (target === null || scroller === null) return
  e.preventDefault()
  scroller.scrollTo({ top: offsetWithin(target, scroller) - 16, behavior: 'smooth' })
  // preventDefault stops the native hash jump, so reflect the section in the URL
  // ourselves — keeps deep-linking/bookmarking (and the e2e contract) intact.
  history.pushState(null, '', '#' + id)
}

/** Smooth-scrolls the shell container back to the top. */
function onBackToTop(e: ReactMouseEvent<HTMLAnchorElement>): void {
  const scroller = scrollerOf(e.currentTarget)
  if (scroller === null) return
  e.preventDefault()
  scroller.scrollTo({ top: 0, behavior: 'smooth' })
}

/** Highlights the ToC entry for the last section scrolled past the top band. */
function useScrollSpy(): string {
  const [activeId, setActiveId] = useState(HELP_SECTIONS[0]?.id ?? '')
  useEffect(() => {
    const scroller = scrollerOf(document.getElementById('help-top'))
    if (scroller === null) return
    const ids = HELP_SECTIONS.map((s) => s.id)
    const onScroll = (): void => {
      const probe = scroller.scrollTop + 100
      let current = ids[0] ?? ''
      for (const id of ids) {
        const el = document.getElementById(id)
        if (el === null) continue
        if (offsetWithin(el, scroller) <= probe) current = id
      }
      setActiveId(current)
    }
    scroller.addEventListener('scroll', onScroll, { passive: true })
    window.addEventListener('resize', onScroll)
    onScroll()
    return () => {
      scroller.removeEventListener('scroll', onScroll)
      window.removeEventListener('resize', onScroll)
    }
  }, [])
  return activeId
}

/** Renders `` `code` `` spans within a (possibly bold) text fragment. */
function renderCode(text: string): ReactNode[] {
  return text.split(/(`[^`]+`)/g).map((p, i) =>
    p.startsWith('`') && p.endsWith('`')
      ? <code key={i} className="tcode">{p.slice(1, -1)}</code>
      : <Fragment key={i}>{p}</Fragment>,
  )
}

/** Renders `**bold**` and `` `code` `` inline markup (code may nest in bold). */
function Rich({ text }: { text: string }): ReactNode {
  const parts = text.split(/(\*\*[^*]+\*\*)/g)
  return (
    <>
      {parts.map((p, i) =>
        p.startsWith('**') && p.endsWith('**')
          ? <b key={i}>{renderCode(p.slice(2, -2))}</b>
          : <Fragment key={i}>{renderCode(p)}</Fragment>,
      )}
    </>
  )
}

function Block({ block, pick }: { block: HelpBlock; pick: (b: Bi) => string }): ReactNode {
  switch (block.kind) {
    case 'lede':
      return <p className="lede"><Rich text={pick(block.text)} /></p>
    case 'subhead':
      return <div className="sub-h">{pick(block.text)}</div>
    case 'steps':
      return (
        <ol className="steps">
          {block.items.map((it, i) => (
            <li key={i}>
              <div className="st-t">{pick(it.title)}</div>
              <div className="st-d"><Rich text={pick(it.desc)} /></div>
            </li>
          ))}
        </ol>
      )
    case 'proc':
      return (
        <ol className="proc">
          {block.items.map((it, i) => <li key={i}><Rich text={pick(it)} /></li>)}
        </ol>
      )
    case 'flow':
      return (
        <div className="flow">
          {block.chips.map((c, i) => (
            <Fragment key={i}>
              {i > 0 && <span className="farrow">→</span>}
              <span className="fchip">{pick(c)}</span>
            </Fragment>
          ))}
          {block.branch !== undefined && <span className="fbranch">{pick(block.branch)}</span>}
        </div>
      )
    case 'terms':
      return (
        <dl className="terms">
          {block.items.map((it, i) => (
            <div key={i} className="term">
              <dt>{pick(it.term)}</dt>
              <dd><Rich text={pick(it.desc)} /></dd>
            </div>
          ))}
        </dl>
      )
    case 'deflist':
      return (
        <div className="deflist">
          {block.items.map((it, i) => (
            <div key={i} className="row">
              <div className="dl-t">{pick(it.term)}</div>
              <div className="dl-d"><Rich text={pick(it.desc)} /></div>
            </div>
          ))}
        </div>
      )
    case 'note':
      return (
        <div className={block.tone === 'warn' ? 'note warn' : 'note'}>
          <span className="note-ic"><Icon name={block.tone === 'warn' ? 'alert' : 'info'} /></span>
          <span>
            {block.title !== undefined && <span className="nt">{pick(block.title)}</span>}
            <Rich text={pick(block.text)} />
          </span>
        </div>
      )
    case 'options':
      return (
        <div className="opt3">
          {block.items.map((it, i) => (
            <div key={i} className="opt">
              <div className="opt-n">ROLE {String(i + 1).padStart(2, '0')}</div>
              <div className="opt-t">{pick(it.title)}</div>
              <div className="opt-d">{pick(it.desc)}</div>
            </div>
          ))}
        </div>
      )
    case 'keys':
      return (
        <>
          {block.groups.map((g, gi) => (
            <Fragment key={gi}>
              <div className="sub-h">{pick(g.heading)}</div>
              <div className="kgrid">
                {g.rows.map((r, ri) => (
                  <div key={ri} className="kr">
                    <span className="kl">{pick(r.label)}</span>
                    <span className="kk">
                      {r.caps.map((cap, ci) => (
                        <kbd key={ci} className={cap.length > 1 ? 'kbd wide' : 'kbd'}>{cap}</kbd>
                      ))}
                    </span>
                  </div>
                ))}
              </div>
            </Fragment>
          ))}
        </>
      )
    case 'faq':
      return (
        <div className="faq">
          {block.items.map((it, i) => (
            <details key={i} open={i === 0}>
              <summary>
                <span className="q">Q</span>
                <span className="qt">{pick(it.q)}</span>
                <span className="chev" aria-hidden="true"><Icon name="chev-d" /></span>
              </summary>
              <div className="fa-body"><Rich text={pick(it.a)} /></div>
            </details>
          ))}
        </div>
      )
  }
}
