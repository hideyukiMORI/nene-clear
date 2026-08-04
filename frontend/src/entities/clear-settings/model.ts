// Domain models for the org's Clear settings and its bank accounts.
//
// A2 F2: the shapes are the OpenAPI contract types (`api-types.ts`), not
// hand-written mirrors. Still snake_case; the camelCase model + mapper are a
// later step (fsd-a2-entities.md §4.1). `BankAccount` is co-located here (not a
// sibling slice) because it is only ever used within settings + the CSV column
// mapping, which keeps ClearSettings free of a sibling-entity import
// (design §6.1, hub Q2).
//
// The scheduled-dunning fields (#400 §6) come along for free now: they are in
// the contract, so they are in the type. No UI edits them yet — the settings
// screen echoes them back untouched on save, because PUT /admin/clear-settings
// is a FULL REPLACE (#284): a field left out of the body is reset to its
// default, not preserved. Editing controls arrive with the A2 F4 rework.

import type { AccountTypeDto, BankAccountDto, ClearSettingsDto } from './api-types'

export type AccountType = AccountTypeDto

export type ClearSettings = ClearSettingsDto

export type BankAccount = BankAccountDto

/**
 * A bank account as the settings form holds it.
 *
 * The contract splits the two directions — `BankAccount` (returned, always
 * carries `bank_account_id`) and `BankAccountInput` (sent, no id). The form sits
 * between them: rows loaded from the server have an id, a row the operator just
 * added does not yet. The hand-written type this replaces expressed that by
 * marking the id optional on the single shape, which quietly also made *server*
 * responses look like they might omit it.
 *
 * Modelled here rather than fixed in the spec: which of the two the settings
 * screen should really use is a UI question, and A2 F4 reworks that screen.
 */
export type BankAccountDraft = Omit<BankAccount, 'bank_account_id'> & { bank_account_id?: number }
