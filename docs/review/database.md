# Database Self-Review

Use for migrations, repositories, and schema changes.

Source policies: `docs/development/backend-standards.md`, `docs/development/naming-conventions.md`.

## Checklist

- [ ] Migration file name follows `YYYYMMDDHHMMSS_snake_description.php`.
- [ ] Table names plural snake_case; money columns use `*_cents` integer.
- [ ] Foreign keys named `{entity}_id`.
- [ ] SQL only in `Pdo*Repository` classes.
- [ ] Schema snapshot updated under `database/schema/` when applicable.
- [ ] Soft delete columns consistent (`is_deleted`, `deleted_at`) unless ADR says otherwise.
- [ ] Repository tests use SQLite in-memory PDO.
- [ ] Rollback considered for destructive migrations.
