// Domain model for a bank transaction.
//
// A2 F2: the shape is no longer hand-written — it is the OpenAPI contract type
// (`api-types.ts`). Still snake_case; the camelCase model + mapper are a later
// step (fsd-a2-entities.md §4.1). What changes is *where the truth lives*: a
// spec change now breaks the build instead of silently disagreeing with the
// server. This slice's hand-written mirror happened to match — which is exactly
// why removing it matters, because a mirror that matches today cannot tell you
// when it stops. See #424 for a slice where it had stopped, unnoticed.

import type { BankTransactionDto, BankTransactionStatusDto } from './api-types'

export type BankTransactionStatus = BankTransactionStatusDto

export type BankTransaction = BankTransactionDto
