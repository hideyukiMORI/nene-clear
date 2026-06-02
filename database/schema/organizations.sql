-- Schema snapshot (reference). Source of truth: database/migrations/.
-- Tenants (ADR 0006). Shown in portable form.
CREATE TABLE organizations (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug          VARCHAR(100) NOT NULL,
    name          VARCHAR(255) NOT NULL,
    is_deleted    TINYINT(1) NOT NULL DEFAULT 0,
    deleted_at    DATETIME NULL,
    created_at    DATETIME NULL,
    updated_at    DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_organizations_slug (slug)
);
