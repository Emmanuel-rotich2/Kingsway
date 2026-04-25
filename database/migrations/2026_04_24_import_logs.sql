-- ============================================================
-- Import Logs — tracks every bulk data import
-- 2026-04-24
-- ============================================================

CREATE TABLE IF NOT EXISTS import_logs (
    id              INT          NOT NULL AUTO_INCREMENT,
    import_type     VARCHAR(50)  NOT NULL COMMENT 'students|staff|fee_payments|exam_results|…',
    import_category VARCHAR(50)  DEFAULT NULL COMMENT 'students|staff|financial|academic|inventory',
    original_filename VARCHAR(255) DEFAULT NULL,
    total_rows      INT          NOT NULL DEFAULT 0,
    success_rows    INT          NOT NULL DEFAULT 0,
    error_rows      INT          NOT NULL DEFAULT 0,
    skipped_rows    INT          NOT NULL DEFAULT 0,
    status          ENUM('preview','completed','partial','failed') NOT NULL DEFAULT 'preview',
    error_details   JSON         DEFAULT NULL COMMENT 'Array of {row, field, message} objects',
    imported_by     INT UNSIGNED DEFAULT NULL,
    notes           TEXT         DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at    TIMESTAMP    NULL,

    PRIMARY KEY (id),
    KEY idx_import_type  (import_type),
    KEY idx_status       (status),
    KEY idx_imported_by  (imported_by),
    KEY idx_created_at   (created_at),

    CONSTRAINT fk_import_logs_user
        FOREIGN KEY (imported_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
