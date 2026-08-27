/**
 * Fails if the built stylesheet does not contain nene2-ui's sentinel class.
 *
 * Why this check exists. Tailwind v4's content detection does not walk
 * `node_modules`, and every class the kit ships lives in its `dist/`. Drop the
 * `@source` line from `src/shared/ui/theme/index.css` and the build stays green
 * while generating NONE of them. Measured on nene-vault 2026-08-23 (#387):
 * 47,075 bytes of CSS instead of 59,597, with every gap, focus ring and
 * disabled treatment missing. Type-check passed. Every test passed — jsdom does
 * not compute styles, so no test can see it. On screen the only symptom is
 * "the kit does not seem to do anything".
 *
 * This is an absence test, so it needs a positive control: a check that can only
 * ever report "not found" is indistinguishable from a check that is not looking.
 * The control here is structural rather than a second fixture. The sentinel
 * resolves to `padding: 0px`, is applied to nothing, and exists in the kit's
 * `dist` and nowhere else — so it can reach our CSS only by way of Tailwind
 * having scanned the kit. If Tailwind emitted it, it scanned the kit; if it did
 * not, it did not. A missing `dist/` and an empty `dist/` are reported as their
 * own failures and never as "class absent", so "no stylesheet at all" can never
 * be mistaken for "the @source line is fine".
 *
 * Verified against a mutation before being trusted: removing the `@source` line
 * turns this check red, and restoring it turns it green (2026-08-27, #440).
 */
import { readFileSync, readdirSync } from 'node:fs'
import { join } from 'node:path'
import { SOURCE_PROBE_CLASS } from '@hideyukimori/nene2-ui'

// The backend-served build. `vite.config.ts` sets `outDir: '../public_html/assets'`
// and Vite appends its default `assetsDir: 'assets'`, so the stylesheet lands one
// level deeper than the outDir reads — `public_html/assets/assets/`. (The e2e and
// measurement builds pass their own --outDir on purpose and are not checked here.)
const assets = join(import.meta.dirname, '..', '..', 'public_html', 'assets', 'assets')

let files
try {
  files = readdirSync(assets).filter((f) => f.endsWith('.css'))
} catch {
  console.error(`source-probe: ${assets} not found — run \`npm run build\` first.`)
  process.exit(1)
}

if (files.length === 0) {
  console.error('source-probe: no stylesheet in public_html/assets — run `npm run build` first.')
  process.exit(1)
}

const missing = files.filter(
  (f) => !readFileSync(join(assets, f), 'utf8').includes(`.${SOURCE_PROBE_CLASS}`),
)

if (missing.length === files.length) {
  console.error(
    `source-probe: .${SOURCE_PROBE_CLASS} is in no stylesheet (${files.join(', ')}).\n` +
      "  @hideyukimori/nene2-ui is not in Tailwind's @source, so none of its classes were\n" +
      '  generated. Restore the @source line in src/shared/ui/theme/index.css.',
  )
  process.exit(1)
}

console.log(
  `source-probe: OK (.${SOURCE_PROBE_CLASS} present in ${files.length - missing.length}/${files.length})`,
)
