-- Schema snapshot (reference). Source of truth: database/migrations/.
-- Registered company bank account + CSV import profile.
CREATE TABLE bank_accounts (
    id                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organization_id          BIGINT UNSIGNED NOT NULL,
    bank_name                VARCHAR(255) NOT NULL,
    bank_branch              VARCHAR(255) NOT NULL DEFAULT '',
    account_type             VARCHAR(32) NOT NULL,
    account_number           VARCHAR(64) NOT NULL,
    csv_encoding             VARCHAR(32) NOT NULL DEFAULT 'utf8',
    csv_date_format          VARCHAR(32) NOT NULL,
    csv_date_column          INT NOT NULL,
    csv_amount_column        INT NOT NULL,
    csv_counterparty_column  INT NOT NULL,
    csv_header_rows          INT NOT NULL DEFAULT 1,
    is_deleted               TINYINT(1) NOT NULL DEFAULT 0,
    deleted_at               DATETIME NULL,
    created_at               DATETIME NULL,
    updated_at               DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_bank_accounts_organization_id (organization_id)
);
