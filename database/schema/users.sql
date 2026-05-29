-- Schema snapshot (reference). Source of truth: database/migrations/.
-- Operator accounts. organization_id is NULL only for a cross-tenant superadmin.
CREATE TABLE users (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organization_id  BIGINT UNSIGNED NULL,
    email            VARCHAR(255) NOT NULL,
    role             VARCHAR(32) NOT NULL,
    status           VARCHAR(32) NOT NULL DEFAULT 'active',
    password_hash    VARCHAR(255) NOT NULL,
    is_deleted       TINYINT(1) NOT NULL DEFAULT 0,
    deleted_at       DATETIME NULL,
    created_at       DATETIME NULL,
    updated_at       DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_users_organization_email (organization_id, email),
    KEY idx_users_organization_id (organization_id)
);
