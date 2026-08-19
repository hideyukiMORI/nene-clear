// FLEET CANON: `*_cents` is the currency's minor unit, not 1/100 of the display
// amount. JPY has zero decimal places (ISO 4217), so `*_cents` stores whole yen
// — never multiply by 100. ¥1,500 is stored as `1500`.
//
// CLEAR DOES NOT FOLLOW THAT CANON TODAY. Clear stores x100 values (see
// `src/BankImport/BankCsvParser.php`, which multiplies parsed yen by 100). That
// is a known deviation, not the standard — Clear's own glossary already lists
// "reading `_cents` as 1/100 yen" as a misuse. Correction is tracked separately
// as the fleet money-unit remediation (order 3 of: 0. define -> 1. deal ->
// 2. serve -> 3. clear; Clear runs in production, so it is a wave of its own);
// background in `_work/reports/2026-08-20-money-unit-archaeology.md`.
//
// DO NOT COPY THE "yen x 100, like everywhere else" WORDING BELOW. It states the
// deviation as if it were the standard, and "like everywhere else" invites it to
// spread.

// Domain models for manual receivables.
//
// A2 F2: the shape is the OpenAPI contract type (`api-types.ts`), not a
// hand-written mirror. Still snake_case; the camelCase model + mapper are a
// later step (fsd-a2-entities.md §4.1). What changes is *where the truth lives*:
// a spec change now breaks the build instead of silently disagreeing with the
// server.

import type { ManualReceivableDto, ManualReceivableImportResultDto } from './api-types'

/**
 * A receivable entered directly in Clear, not sourced from NeNe Invoice
 * (ADR 0014). A reconciliation reference — NOT an issued invoice / 適格請求書 /
 * tax original. `*_cents` are integer cents (yen × 100), like everywhere else.
 */
export type ManualReceivable = ManualReceivableDto

export type ManualReceivableImportResult = ManualReceivableImportResultDto
