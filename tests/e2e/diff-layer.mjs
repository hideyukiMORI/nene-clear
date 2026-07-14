// Compare before/ vs after/ computed-style fingerprints and screenshots.
// Emits a machine diff (JSON) + human summary to stdout.
import { readFileSync, readdirSync, existsSync, statSync } from 'node:fs'
import { resolve } from 'node:path'

const ROOT = process.env.CMP_OUT || '/tmp/nene-clear-cmp'
const A = resolve(ROOT, 'before'), B = resolve(ROOT, 'after')
const files = readdirSync(A).filter((f) => f.endsWith('.computed.json'))
let totalEls = 0, changedEls = 0
const propCounts = {}
const samples = []
const perScenario = []
const pngDiff = []

for (const f of files) {
  const scenario = f.replace('.computed.json', '')
  const before = JSON.parse(readFileSync(resolve(A, f), 'utf8'))
  const after = existsSync(resolve(B, f)) ? JSON.parse(readFileSync(resolve(B, f), 'utf8')) : []
  const byI = new Map(after.map((r) => [r.i, r]))
  let sChanged = 0
  if (before.length !== after.length) {
    samples.push({ scenario, note: `element count differs: before=${before.length} after=${after.length}` })
  }
  for (const rb of before) {
    totalEls++
    const ra = byI.get(rb.i)
    if (!ra) { sChanged++; changedEls++; samples.push({ scenario, i: rb.i, tag: rb.tag, cls: rb.cls, note: 'element missing in after' }); continue }
    let elChanged = false
    for (const p of Object.keys(rb.s)) {
      if (rb.s[p] !== ra.s[p]) {
        elChanged = true
        propCounts[p] = (propCounts[p] || 0) + 1
        if (samples.length < 60) samples.push({ scenario, i: rb.i, tag: rb.tag, cls: rb.cls.slice(0, 40), prop: p, before: rb.s[p], after: ra.s[p] })
      }
    }
    // layout rect diff (>1px)
    for (const k of ['x', 'y', 'w', 'h']) {
      if (Math.abs((rb.rect[k] ?? 0) - (ra.rect[k] ?? 0)) > 1) {
        elChanged = true
        propCounts[`rect.${k}`] = (propCounts[`rect.${k}`] || 0) + 1
        if (samples.length < 60) samples.push({ scenario, i: rb.i, tag: rb.tag, cls: rb.cls.slice(0, 40), prop: `rect.${k}`, before: rb.rect[k], after: ra.rect[k] })
      }
    }
    if (elChanged) { sChanged++; changedEls++ }
  }
  // screenshot byte comparison
  const pa = resolve(A, `${scenario}.png`), pb = resolve(B, `${scenario}.png`)
  if (existsSync(pa) && existsSync(pb)) {
    const ba = readFileSync(pa), bb = readFileSync(pb)
    const identical = ba.length === bb.length && ba.equals(bb)
    pngDiff.push({ scenario, identical, beforeBytes: ba.length, afterBytes: bb.length })
  }
  perScenario.push({ scenario, elements: before.length, changed: sChanged })
}

const report = {
  totals: { scenarios: files.length, elements: totalEls, changedElements: changedEls },
  propertyDiffCounts: propCounts,
  perScenario,
  screenshots: pngDiff,
  samples,
}
console.log(JSON.stringify(report, null, 2))
console.error('\n=== SUMMARY ===')
console.error(`scenarios: ${files.length}  elements compared: ${totalEls}  changed elements: ${changedEls}`)
console.error(`property diffs by key: ${Object.keys(propCounts).length === 0 ? '(none)' : ''}`)
for (const [k, v] of Object.entries(propCounts).sort((a, b) => b[1] - a[1])) console.error(`  ${k}: ${v}`)
const pngChanged = pngDiff.filter((p) => !p.identical)
console.error(`screenshots byte-identical: ${pngDiff.length - pngChanged.length}/${pngDiff.length}`)
for (const p of pngChanged) console.error(`  DIFF png: ${p.scenario} (${p.beforeBytes} -> ${p.afterBytes} bytes)`)
