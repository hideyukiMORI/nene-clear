-- Schema snapshot (reference). Source of truth: database/migrations/.
-- Imported deposit lines — immutable evidence (compliance §3.1 / ADR 0012).
CREATE TABLE bank_transactions (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organization_id       BIGINT UNSIGNED NOT NULL,
    bank_import_batch_id  BIGINT UNSIGNED NOT NULL,
    bank_account_id       BIGINT UNSIGNED NOT NULL,
    value_date            DATE NOT NULL,
    amount_cents          BIGINT NOT NULL,
    counterparty_text     VARCHAR(255) NOT NULL DEFAULT '',
    line_key              VARCHAR(64) NOT NULL,
    status                VARCHAR(32) NOT NULL DEFAULT 'unmatched',
    created_at            DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_bank_transactions_org_status (organization_id, status),
    KEY idx_bank_transactions_batch (bank_import_batch_id),
    KEY idx_bank_transactions_value_date (value_date),
    KEY idx_bank_transactions_org_counterparty (organization_id, counterparty_text)
);
