// Freshness gate for the generated OpenAPI types (#317 Phase 2).
// Regenerates schema.gen.ts into a temp file and fails if it differs from the
// committed one — i.e. someone edited docs/openapi/openapi.yaml without running
//   npm run codegen
// A green type-check only proves the committed file compiles, not that it is
// current; the drift can sit unnoticed for weeks because nothing compares the
// output. Deterministic regeneration, compared, mismatch = FAIL — the same
// regen-diff shape the OpenAPI validator applies on the backend.
// Local and hermetic: openapi-typescript is a pinned devDependency (7.13.0) run
// through `openapi-typescript` on PATH, so no network is touched here.
import { execFileSync } from 'node:child_process'
import { mkdtempSync, readFileSync, rmSync } from 'node:fs'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const dirname = path.dirname(fileURLToPath(import.meta.url))
const SPEC = path.resolve(dirname, '../../docs/openapi/openapi.yaml')
const COMMITTED = path.resolve(dirname, '../src/shared/api/schema.gen.ts')
const BIN = path.resolve(dirname, '../node_modules/.bin/openapi-typescript')

const tmp = mkdtempSync(path.join(tmpdir(), 'nene-clear-codegen-'))
const fresh = path.join(tmp, 'schema.gen.ts')

try {
  execFileSync(BIN, [SPEC, '-o', fresh], { stdio: 'pipe' })

  if (readFileSync(fresh, 'utf8') !== readFileSync(COMMITTED, 'utf8')) {
    console.error(
      'schema.gen.ts is stale: docs/openapi/openapi.yaml has changed since it was generated.\n' +
        'Run `npm run codegen` and commit the result.',
    )
    process.exit(1)
  }
} finally {
  rmSync(tmp, { recursive: true, force: true })
}
