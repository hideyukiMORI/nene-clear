# Invoice Upstream HTTP Client — Implementation Requirements

**Status: pending** — blocking real-world reconciliation and dunning.
Currently all Invoice API calls go to `FakeInvoiceUpstreamClient` (in-memory, tests only).

This document lists everything needed to replace the fake with a real HTTP client
(`InvoiceUpstreamHttpClient`), and what `nene-invoice` must implement first.

See also: [`invoice-upstream-contract.md`](./invoice-upstream-contract.md) (binding spec).

---

## Prerequisites — NeNe Invoice must build these first

These endpoints **do not exist in `nene-invoice` yet**.
Open an Issue in `nene-invoice` linking to `invoice-upstream-contract.md`.

### Read API (blocking Phase 1 reconciliation)

| # | Endpoint | Contract ref | Notes |
|---|---|---|---|
| R1 | `GET /api/invoices` | §2.1 | Filters: `status`, `overdue`, `client_id`, `due_before`, `due_after`, `outstanding_gt`, `limit`, `offset`; NENE2 `items/limit/offset` envelope |
| R2 | `GET /api/invoices/{id}` | §2.2 | Includes `outstanding_cents` + `payments[]` history |
| R3 | `GET /api/clients/{id}` | §2.3 | `client_id`, `contact_name`, `recipient_email`; optional but needed for dunning |

### Write API (blocking confirmed reconciliation)

| # | Endpoint | Contract ref | Notes |
|---|---|---|---|
| W1 | `POST /api/invoices/{id}/payments` | §3.1 | Idempotent on `idempotency_key`; `paid_at` = bank value date (never overwrite); rejects over-allocation with 422 `payment-exceeds-outstanding` |
| W2 | `POST /api/invoices/{id}/payments/{paymentId}/void` | §3.2 | Void-with-audit, idempotent; restores outstanding |

### Auth & errors (blocking all calls)

| # | Requirement | Contract ref |
|---|---|---|
| A1 | Service bearer token per org (`NENE_INVOICE_BEARER_TOKEN`) | §5 |
| A2 | RFC 9457 Problem Details for all errors | §5 |
| A3 | Error types: `payment-exceeds-outstanding`, `invoice-not-found`, `payment-not-found`, `unauthorized`, `insufficient-scope` | §5 |
| A4 | OpenAPI spec published; `operationId` + field names stable | §5 |
| A5 | Tenant scoping: service token scoped to one org; cross-tenant → 403 | §4 |

---

## What needs to be built in NeNe Clear

### A. `InvoiceUpstreamHttpClient` (new file)

`src/InvoiceUpstream/InvoiceUpstreamHttpClient.php`

Implements `InvoiceUpstreamClientInterface`. Uses PHP's `file_get_contents` / cURL /
PSR-18 HTTP client to call Invoice API.

#### Constructor inputs
```php
public function __construct(
    private string $baseUrl,         // from ClearSettings::upstreamBaseUrl
    private string $bearerToken,     // from env var named in ClearSettings::upstreamTokenRef
)
```

#### Method implementations

| Method | HTTP call | Error mapping |
|---|---|---|
| `listInvoices(orgId, statuses)` | `GET /api/invoices?status[]=...` | 5xx/network → `UpstreamInvoiceUnavailableException` |
| `getInvoice(orgId, invoiceId)` | `GET /api/invoices/{invoiceId}` | 404 → `UpstreamInvoiceNotFoundException`; 5xx → unavailable |
| `createPayment(...)` | `POST /api/invoices/{invoiceId}/payments` | 404 invoice → `UpstreamInvoiceNotFoundException`; 422 `payment-exceeds-outstanding` → `AllocationExceedsOutstandingException`; 5xx → unavailable |
| `voidPayment(...)` | `POST /api/invoices/{invoiceId}/payments/{paymentId}/void` | 404 → not found; 5xx → unavailable |
| `getClient(orgId, clientId)` | `GET /api/clients/{clientId}` | 404 → `UpstreamInvoiceNotFoundException`; 5xx → unavailable |

#### HTTP requirements
- `Authorization: Bearer {token}` on every request
- `Content-Type: application/json` on POST
- `Idempotency-Key: {idempotencyKey}` header on writes (W1, W2)
- Connect timeout: 5s; read timeout: 10s
- On non-2xx: decode Problem Details `type` field to map to domain exception

### B. `ApplicationFactory` wiring update

```php
// When ClearSettings has a non-empty upstreamBaseUrl and token ref:
$bearerToken = getenv($clearSettings->upstreamTokenRef) ?: '';
$upstream = $bearerToken !== '' && $clearSettings->upstreamBaseUrl !== ''
    ? new InvoiceUpstreamHttpClient($clearSettings->upstreamBaseUrl, $bearerToken)
    : ($invoiceClient ?? new FakeInvoiceUpstreamClient());
```

The `$invoiceClient` param (used by tests) still takes precedence.

### C. Contract tests

`tests/Contract/InvoiceUpstreamContractTest.php`

- Reads `NENE_INVOICE_API_BASE_URL` / `NENE_INVOICE_BEARER_TOKEN` from env
- Skips (`markTestSkipped`) if env vars are not set (CI passes without Invoice API)
- When set: calls real Invoice API and asserts responses match `InvoiceUpstreamClientInterface` expectations
- Tests: listInvoices (issued), getInvoice (existing + missing → 404), createPayment (idempotent), voidPayment

### D. `.env` / `ClearSettings` — already covered
- `NENE_INVOICE_API_BASE_URL` → `ClearSettings.upstreamBaseUrl`
- `NENE_INVOICE_BEARER_TOKEN` → env var named by `ClearSettings.upstreamTokenRef`
- Both are already in `.env.example`

---

## Implementation order

1. **nene-invoice team** implements R1–R3, W1–W2, A1–A5 (external dependency)
2. **nene-clear** builds `InvoiceUpstreamHttpClient` + `ApplicationFactory` wiring once Invoice API is live
3. **nene-clear** writes contract tests that skip in CI but pass against real Invoice

---

## Estimated scope (nene-clear side only)

| File | New / Changed | Notes |
|---|---|---|
| `src/InvoiceUpstream/InvoiceUpstreamHttpClient.php` | New | ~120 lines; HTTP + error mapping |
| `src/Http/ApplicationFactory.php` | Changed | Swap fake → real when env configured |
| `tests/Contract/InvoiceUpstreamContractTest.php` | New | Skip if no env vars |

No DB migrations, no entity changes, no new capabilities needed.
