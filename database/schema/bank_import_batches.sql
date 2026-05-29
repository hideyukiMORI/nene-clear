-- Schema snapshot (reference). Source of truth: database/migrations/.
-- CSV import provenance (電子帳簿保存法 / ADR 0012). Unique file_hash per org.
CREATE TABLE bank_import_batches (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organization_id  BIGINT UNSIGNED NOT NULL,
    bank_account_id  BIGINT UNSIGNED NOT NULL,
    file_hash        VARCHAR(64) NOT NULL,
    source_filename  VARCHAR(255) NOT NULL,
    row_count        INT NOT NULL DEFAULT 0,
    status           VARCHAR(32) NOT NULL DEFAULT 'imported',
    imported_by      BIGINT UNSIGNED NOT NULL,
    imported_at      DATETIME NOT NULL,
    reversed_at      DATETIME NULL,
    reversal_reason  VARCHAR(255) NULL,
    created_at       DATETIME NULL,
    updated_at       DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_bank_import_batches_org_file_hash (organization_id, file_hash),
    KEY idx_bank_import_batches_organization_id (organization_id)
);
