# Database Self-Review

Use for migrations, repositories, and schema changes.

Source policies: `docs/development/backend-standards.md`, `docs/development/naming-conventions.md`.

## Checklist

- [ ] Migration file name follows `YYYYMMDDHHMMSS_snake_description.php`.
- [ ] Table names plural snake_case; money columns use `*_cents` integer.

> `cents` = the currency's **minor unit**, not 1/100 of the display amount.
> **JPY has zero decimal places (ISO 4217), so `*_cents` stores whole yen — never multiply by 100.**
> Example: ¥1,500 is stored as `1500`. A value like `116480` means ¥116,480, not ¥1,164.80.

- [ ] Foreign keys named `{entity}_id`.
- [ ] SQL only in `Pdo*Repository` classes.
- [ ] Schema snapshot updated under `database/schema/` when applicable.
- [ ] Soft delete columns consistent (`is_deleted`, `deleted_at`) unless ADR says otherwise.
- [ ] Repository tests use SQLite in-memory PDO.
- [ ] Rollback considered for destructive migrations.
