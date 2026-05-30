# Current Work

Last updated: 2026-05-30

## Status

**Phase 1 — Complete.**
**Phase 2 — Complete (dunning functional; professional review gate not yet obtained).**
**Infrastructure — Complete (docker-compose + Mailpit; SmtpDunningMailer; InvoiceUpstreamHttpClient).**
**nene-invoice upstream — All R1–R3, W1–W2, A1–A5 implemented (PR #141 on nene-invoice). Ready for real connection.**

126 tests (5 skipped) / 473 assertions, PHPStan level 8 clean.
5 skipped = contract tests that auto-activate once `NENE_INVOICE_API_BASE_URL` is set.

## Pending PRs (not yet merged to main)

| PR | Branch | 内容 |
| --- | --- | --- |
| #62 | `fix/upstream-not-found-handlers` | `UpstreamClientNotFoundException` + handlers + client-not-found type |
| #63 | `feat/i18n-message-catalog` | i18n 基盤 (lang/ja.php, lang/en.php, MessageCatalog, LocalizedProblemDetailsFactory) |
| #64 | `fix/multi-tenant-org-scoping` | 全 bare-ID 書込/読取クエリに `organization_id` フィルター追加 |
| #65 | `fix/audit-before-after` | 全 audit event payload に before/after スナップショット追加 |

## What is running

| Domain | Endpoints | Auth |
| --- | --- | --- |
| Auth | `POST /admin/auth/login`, `GET /admin/auth/me` | JWT |
| Organization | CRUD `/admin/organizations` | `manage_organizations` |
| User | CRUD `/admin/users` | `manage_users` |
| ClearSettings | `GET/PUT /admin/clear-settings`, `POST /admin/clear-settings/test-upstream` | `manage_clear_settings` |
| Bank import | `POST /admin/bank-import-batches` (CSV), `GET` list, `POST /{id}/reverse` | `view_reconciliation` / `manage_reconciliation` |
| Bank transactions | `GET /admin/bank-transactions` (filters), `GET /{id}`, `GET /unmatched` | `view_reconciliation` |
| Reconciliation | `POST /propose`, `POST /confirm`, `GET` list/by-id, `POST /{id}/reverse` | `view_reconciliation` / `manage_reconciliation` |
| Client credits | `GET /admin/client-credits`, `POST /{id}/apply` | `view_reconciliation` / `manage_reconciliation` |
| Dunning | `POST /admin/dunning-notices`, `GET` list/by-id | `send_dunning` / `view_reconciliation` |

## Infrastructure

| Component | Status | Notes |
| --- | --- | --- |
| Docker | ✅ `docker-compose.yml` | MySQL 8.4 (port 3310) + Mailpit (SMTP 1025, web UI 8025) |
| SMTP mailer | ✅ `SmtpDunningMailer` | Activated when `SMTP_HOST` env var is set; falls back to `LogOnlyDunningMailer` |
| Invoice upstream | ✅ `InvoiceUpstreamHttpClient` | Activated when `NENE_INVOICE_API_BASE_URL` + `NENE_INVOICE_BEARER_TOKEN` are set; falls back to `FakeInvoiceUpstreamClient` |
| Contract tests | ✅ 6 tests (auto-skip) | Run against real Invoice API by setting env vars |

## Open risks / TODO backlog

| # | Issue | 優先度 | 対応可否 |
| --- | --- | --- | --- |
| [#66](https://github.com/hideyukiMORI/nene-clear/issues/66) | cURL error detail がログに露出 | 中 | ✅ コードで対応可 |
| [#67](https://github.com/hideyukiMORI/nene-clear/issues/67) | SMTP 認証情報を DSN に埋め込み | 中 | ✅ コードで対応可 |
| [#68](https://github.com/hideyukiMORI/nene-clear/issues/68) | テストカバレッジ不足（取消フロー/テナント分離/冪等性） | 中 | ✅ コードで対応可 |
| [#69](https://github.com/hideyukiMORI/nene-clear/issues/69) | 消込エンドポイントの冪等性未実装 | 高 | ✅ コードで対応可 |
| [#70](https://github.com/hideyukiMORI/nene-clear/issues/70) | システム概要書/操作説明書 未作成（電子帳簿保存法 §3.4） | 高 | ✅ ドキュメントで対応可 |
| [#71](https://github.com/hideyukiMORI/nene-clear/issues/71) | 専門家サインオフ未取得（税理士/弁護士） | 最高 | ❌ 外部アクション待ち |

## Next steps

1. **#66** → cURL error masking
2. **#67** → SMTP DSN credential separation
3. **#68** → missing test coverage
4. **#69** → reconciliation idempotency (schema + code)
5. **#70** → operator guide document
6. **#71** → professional sign-off (human action, external)
7. **Admin UI** — frontend for reconciliation + dunning workflow (Phase 3)
