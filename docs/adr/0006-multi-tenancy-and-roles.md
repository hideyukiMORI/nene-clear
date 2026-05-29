# ADR 0006: Adopt Multi-Tenancy and Role Hierarchy as Foundational

## Status

accepted

## Context

The early product docs assumed Phase 1 was single-tenant, with multi-tenancy
deferred ("`company_settings` singleton per organization (Phase 1 single-tenant;
multi-tenant adds `organization_id`)"). In practice the product must support, from
the foundation:

- agencies and hosting operators running **one install for multiple client
  organizations**, and
- a **role hierarchy** where a platform operator manages organizations and an
  organization administrator manages that organization's users.

Sibling product [NeNe Records](https://github.com/hideyukiMORI/nene-records)
already implements exactly this on NENE2 (per-request organization resolution,
`Role`/`Capability` enums, `CapabilityMiddleware`, `organization_id` on
tenant-scoped tables). Retrofitting tenancy later would touch every table,
repository, and route — far more costly than building tenant-aware from day one.

Alternatives considered:

1. **Single-tenant first, retrofit later** — rejected; a later `organization_id`
   migration across all reconciliation tables plus auth rework is high-risk and
   re-opens compliance-sensitive code (bank import integrity, audit, retention).
2. **Separate install per tenant only** — rejected; does not serve agencies and
   duplicates operations; the data model should still be tenant-aware.
3. **Multi-tenant foundation, mirroring NeNe Records** (chosen) — tenant-aware
   schema and middleware from the start; a single install may still run as one
   organization via the `single` resolution mode.

## Decision

NeNe Clear is **multi-tenant from the foundation**, adopting the NeNe Records
architecture.

### Tenancy

> The entity and capability examples below were corrected to NeNe Clear's
> domain (reconciliation & dunning) per [ADR 0009](./0009-separate-from-nene-invoice.md).
> The tenancy/role **decision** is unchanged.

- Every tenant-scoped table carries **`organization_id`** (`clear_settings`,
  `bank_import_batches`, `bank_transactions`, `payment_reconciliations`,
  `client_credits`, `dunning_notices`, `audit_events`, `users`).
- The **organization** (`organizations` table) is the tenant. Each organization
  is an independent **operator** running reconciliation and dunning, with its own
  `clear_settings` (Invoice upstream API URL/token, registered bank accounts,
  dunning defaults).
- Per-request **organization resolution** runs in middleware before authorization
  (mirroring `OrgResolverMiddleware`). Supported modes: **`single`** (default —
  one organization per install), `path` (`/{org-slug}/…`), `subdomain`, and
  `custom_domain`. The resolved organization id is held in a request-scoped holder
  and **every repository query is org-scoped**.
- Clear issues **no** statutory document numbers (those belong to `nene-invoice`).
  Import provenance is keyed per organization by `file_hash` + `bank_import_batch_id`.

### Roles and capabilities

A `Role` enum and a `Capability` enum, resolved per route by a capability
resolver and enforced by `CapabilityMiddleware` (mirroring NeNe Records):

| Role | Scope | Capabilities |
| --- | --- | --- |
| **`superadmin`** | Cross-tenant (platform operator) | All, **including `manage_organizations`**. `organization_id` may be `NULL`. |
| **`admin`** | Single organization | All **except** `manage_organizations` — manages the org's **users**, **Clear settings** (upstream config, bank accounts, dunning defaults), reconciliation, and dunning. |
| **`member`** | Single organization | Reconciliation operator — import bank CSV, confirm/reverse matches (`manage_reconciliation`), send dunning when granted (`send_dunning`). **Cannot** manage users or settings. |
| **`viewer`** | Single organization | Read-only (`view_reconciliation`) — matched/unmatched lists, dunning history. Optional; Phase 2+. |

Capabilities (reconciliation/dunning-specific; the set differs from NeNe
Records' content capabilities): `manage_organizations`, `manage_users`,
`manage_clear_settings`, `manage_reconciliation`, `view_reconciliation`,
`send_dunning`.

- **Superadmin manages organizations** (`/admin/organizations` — create, list,
  delete tenants).
- **Admin manages users** within the organization (`/admin/users`).
- Role and capability string values are registered in
  [`../explanation/terminology.md`](../explanation/terminology.md) (binding).

### Compliance interaction

- Each organization's bank transactions, reconciliation links, client credits,
  and dunning history are scoped to that organization; cross-tenant reads are
  prohibited. This does not relax any rule in
  [`../explanation/accounting-compliance.md`](../explanation/accounting-compliance.md)
  or [`../explanation/payment-reconciliation-dunning-compliance.md`](../explanation/payment-reconciliation-dunning-compliance.md) —
  immutability, retention, and audit apply **per organization**.

## Consequences

**Benefits**

- Serves agencies/hosting operators and single SMBs from one codebase; `single`
  mode keeps the simple case simple.
- Avoids a high-risk tenancy retrofit across compliance-sensitive billing tables.
- Consistent with the NeNe ecosystem; patterns and reviews transfer.

**Costs**

- Every repository query must be org-scoped — a standing review item
  (`docs/review/database.md`, `docs/review/middleware-security.md`).
- Auth, org resolution, and RBAC are part of the runtime foundation (Issue #4),
  enlarging it beyond a bare health endpoint.

**Follow-up**

- **PR-B (Issue #4 expanded):** runtime foundation — org resolution + JWT auth +
  RBAC wiring + `GET /health`.
- **PR-C+:** organization CRUD (superadmin) and user CRUD (admin).

## Related

- Reference implementation: NeNe Records `src/Organization/`, `src/Auth/` (Role, Capability, CapabilityResolver, CapabilityMiddleware), `src/Organization/Resolution/`
- Requirements: `docs/explanation/requirements.md`
- Domain model: `docs/explanation/domain-model.md`
- Terminology registry: `docs/explanation/terminology.md`
- Compliance: `docs/explanation/accounting-compliance.md`
- Issue: `#17`
- Supersedes: none
- Superseded by: none
