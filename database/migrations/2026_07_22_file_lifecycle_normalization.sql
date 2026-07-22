-- Kingsway file lifecycle normalization
-- Date: 2026-07-22
-- Scope: permanent public school-document tokens and download audit metadata.

DELIMITER $$

DROP PROCEDURE IF EXISTS kw_add_column_if_missing $$
CREATE PROCEDURE kw_add_column_if_missing(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = p_table
          AND column_name = p_column
    ) THEN
        SET @sql = CONCAT(
            'ALTER TABLE `', p_table,
            '` ADD COLUMN `', p_column, '` ',
            p_definition
        );
        PREPARE statement FROM @sql;
        EXECUTE statement;
        DEALLOCATE PREPARE statement;
    END IF;
END $$

DROP PROCEDURE IF EXISTS kw_add_index_if_missing $$
CREATE PROCEDURE kw_add_index_if_missing(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = p_table
          AND index_name = p_index
    ) THEN
        SET @sql = CONCAT(
            'ALTER TABLE `', p_table,
            '` ADD ', p_definition
        );
        PREPARE statement FROM @sql;
        EXECUTE statement;
        DEALLOCATE PREPARE statement;
    END IF;
END $$

CALL kw_add_column_if_missing(
    'page_downloads',
    'storage_filename',
    'VARCHAR(255) NULL AFTER `description`'
) $$

CALL kw_add_column_if_missing(
    'page_downloads',
    'public_token',
    'CHAR(64) NULL AFTER `storage_filename`'
) $$

CALL kw_add_column_if_missing(
    'page_downloads',
    'original_filename',
    'VARCHAR(255) NULL AFTER `public_token`'
) $$

CALL kw_add_column_if_missing(
    'page_downloads',
    'mime_type',
    'VARCHAR(150) NULL AFTER `original_filename`'
) $$

CALL kw_add_column_if_missing(
    'page_downloads',
    'file_size_bytes',
    'BIGINT UNSIGNED NULL AFTER `mime_type`'
) $$

CALL kw_add_column_if_missing(
    'page_downloads',
    'token_created_at',
    'DATETIME NULL AFTER `file_size_bytes`'
) $$

CALL kw_add_column_if_missing(
    'page_downloads',
    'token_revoked_at',
    'DATETIME NULL AFTER `token_created_at`'
) $$

CALL kw_add_column_if_missing(
    'page_downloads',
    'download_count',
    'BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `token_revoked_at`'
) $$

CALL kw_add_column_if_missing(
    'page_downloads',
    'last_downloaded_at',
    'DATETIME NULL AFTER `download_count`'
) $$

CALL kw_add_column_if_missing(
    'page_downloads',
    'created_by',
    'BIGINT UNSIGNED NULL AFTER `last_downloaded_at`'
) $$

CALL kw_add_column_if_missing(
    'page_downloads',
    'updated_by',
    'BIGINT UNSIGNED NULL AFTER `created_by`'
) $$

CALL kw_add_index_if_missing(
    'page_downloads',
    'uq_page_downloads_public_token',
    'UNIQUE KEY `uq_page_downloads_public_token` (`public_token`)'
) $$

CALL kw_add_index_if_missing(
    'page_downloads',
    'idx_page_downloads_active_token',
    'KEY `idx_page_downloads_active_token` (`is_active`, `token_revoked_at`)'
) $$

-- Migrate old path-style file_url values into storage_filename.
UPDATE page_downloads
SET storage_filename = SUBSTRING_INDEX(
        REPLACE(file_url, '\\', '/'),
        '/',
        -1
    )
WHERE (storage_filename IS NULL OR storage_filename = '')
  AND file_url IS NOT NULL
  AND file_url <> '' $$

-- Generate one stable public token for every managed document.
UPDATE page_downloads
SET public_token = LOWER(HEX(RANDOM_BYTES(32))),
    token_created_at = COALESCE(token_created_at, NOW())
WHERE storage_filename IS NOT NULL
  AND storage_filename <> ''
  AND (public_token IS NULL OR public_token = '') $$

DROP PROCEDURE IF EXISTS kw_add_column_if_missing $$
DROP PROCEDURE IF EXISTS kw_add_index_if_missing $$

DELIMITER ;
