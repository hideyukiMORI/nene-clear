-- Schema snapshot (reference). Source of truth: database/migrations/.
-- Immutable audit trail (compliance §6). Append-only.
CREATE TABLE audit_events (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organization_id  BIGINT UNSIGNED NOT NULL,
    event_type       VARCHAR(64) NOT NULL,
    actor_user_id    BIGINT UNSIGNED NOT NULL,
    occurred_at      DATETIME NOT NULL,
    payload_json     TEXT NOT NULL,
    PRIMARY KEY (id),
    KEY idx_audit_events_org_type (organization_id, event_type)
);
