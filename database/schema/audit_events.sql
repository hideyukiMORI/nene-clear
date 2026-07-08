-- Schema snapshot (reference). Source of truth: database/migrations/.
-- Immutable audit trail (compliance §6). Append-only.
-- Framework-canonical shape (Nene2\Audit\AuditTableConfig::canonical(), stage 2
-- of the audit adoption — Issue #258); Clear keeps integer ids and NOT NULL
-- actor/organization (actor_id 0 = system).
CREATE TABLE audit_events (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organization_id  BIGINT UNSIGNED NOT NULL,
    action           VARCHAR(64) NOT NULL,
    entity_type      VARCHAR(64) NOT NULL DEFAULT '',
    entity_id        BIGINT UNSIGNED NULL DEFAULT NULL,
    actor_id         BIGINT UNSIGNED NOT NULL,
    occurred_at      DATETIME NOT NULL,
    before_json      TEXT NULL DEFAULT NULL,
    after_json       TEXT NULL DEFAULT NULL,
    metadata_json    TEXT NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_audit_events_org_action (organization_id, action),
    KEY idx_audit_events_entity (entity_type, entity_id)
);
