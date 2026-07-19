-- ============================================================================
-- Stage 5 — Academic Year / Term / Class Capacity & Future Cohort Projection
-- ============================================================================
-- Implements Admission Stage 5: period-aware, cohort-aware capacity projection.
--
-- Design notes (ground truth from live KingsWayAcademy DB):
--   * classes.academic_year      is YEAR(4)  (e.g. 2026)
--   * academic_terms.academic_year_id is FK -> academic_years.id
--   * students.stream_id -> class_streams.id ; class_streams.class_id -> classes.id
--   * school_levels.id (PP=5,LP=2,UP=3,JSS=4) is the level hierarchy used for the
--     fallback progression when academic_class_progression has no explicit row.
--   * Real placement table is admission_placements (application_id UNIQUE). The
--     spec's "admissions_class_placement" is therefore folded into the existing
--     placement table rather than created as a parallel duplicate.
--   * Existing capacity engine is sp_check_class_space_availability(app_id,user_id).
--     This Stage 5 service supersedes it with period/cohort awareness.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1) Configurable class progression map (source_class_id -> target_class_id).
--    Keyed per-class, NOT by string parsing, per the Stage 5 spec.
--    Seeded from the canonical linear chain:
--      Playgroup -> PP1 -> PP2 -> Grade1 -> Grade2 -> Grade3 -> Grade4 ->
--      Grade5 -> Grade6 -> Grade7 -> Grade8 -> Grade9
--    Class IDs (verified): 5,12,13,6,7,2,8,9,1,10,11,4
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS academic_class_progression (
    id                              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_class_id                 INT UNSIGNED NOT NULL,
    target_class_id                 INT UNSIGNED NOT NULL,
    progression_type                VARCHAR(40) NOT NULL DEFAULT 'standard'
                                        COMMENT 'standard | repeating | manual',
    effective_from_academic_year_id INT UNSIGNED NULL,
    active                          TINYINT(1) NOT NULL DEFAULT 1,
    created_by                      INT UNSIGNED NULL,
    created_at                      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_src_tgt (source_class_id, target_class_id),
    KEY idx_target (target_class_id),
    KEY idx_active (active),
    CONSTRAINT fk_acp_source FOREIGN KEY (source_class_id) REFERENCES classes (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_acp_target FOREIGN KEY (target_class_id) REFERENCES classes (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the default progression chain (idempotent).
INSERT IGNORE INTO academic_class_progression
    (source_class_id, target_class_id, progression_type, active)
VALUES
    (5, 12, 'standard', 1),   -- Playgroup  -> PP1
    (12, 13, 'standard', 1),  -- PP1        -> PP2
    (13, 6, 'standard', 1),   -- PP2        -> Grade1
    (6, 7, 'standard', 1),    -- Grade1     -> Grade2
    (7, 2, 'standard', 1),    -- Grade2     -> Grade3
    (2, 8, 'standard', 1),    -- Grade3     -> Grade4
    (8, 9, 'standard', 1),    -- Grade4     -> Grade5
    (9, 1, 'standard', 1),    -- Grade5     -> Grade6
    (1, 10, 'standard', 1),   -- Grade6     -> Grade7
    (10, 11, 'standard', 1),  -- Grade7     -> Grade8
    (11, 4, 'standard', 1);   -- Grade8     -> Grade9 (Grade9 -> completion, no row)

-- ----------------------------------------------------------------------------
-- 2) Capacity reservations made against future classes/streams by approved
--    (but not-yet-enrolled) applicants. One reservation per application.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS academic_capacity_reservations (
    id                          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id              INT UNSIGNED NULL,
    academic_year_id            INT UNSIGNED NULL,
    term_id                     INT UNSIGNED NULL,
    class_id                    INT UNSIGNED NOT NULL,
    stream_id                   INT UNSIGNED NULL,
    reservation_status          ENUM('provisional','confirmed','converted',
                                     'expired','released','cancelled')
                                    NOT NULL DEFAULT 'provisional',
    reserved_at                 DATETIME NULL,
    expires_at                  DATETIME NULL,
    released_at                 DATETIME NULL,
    converted_to_enrollment_at  DATETIME NULL,
    created_by                  INT UNSIGNED NULL,
    created_at                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_application (application_id),
    KEY idx_class (class_id),
    KEY idx_status (reservation_status),
    KEY idx_year (academic_year_id),
    CONSTRAINT fk_acr_application FOREIGN KEY (application_id)
        REFERENCES admission_applications (id) ON DELETE SET NULL,
    CONSTRAINT fk_acr_year FOREIGN KEY (academic_year_id)
        REFERENCES academic_years (id) ON DELETE SET NULL,
    CONSTRAINT fk_acr_term FOREIGN KEY (term_id)
        REFERENCES academic_terms (id) ON DELETE SET NULL,
    CONSTRAINT fk_acr_class FOREIGN KEY (class_id)
        REFERENCES classes (id) ON DELETE CASCADE,
    CONSTRAINT fk_acr_stream FOREIGN KEY (stream_id)
        REFERENCES class_streams (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3) Capacity status configuration (thresholds are DB-driven, not hardcoded).
--    Available : remaining capacity > available_pct_threshold % of capacity
--    Limited   : remaining 1..available_pct_threshold %  (and > limited_pct)
--    Full      : remaining == 0
--    Over      : projected_occupancy > capacity
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS academic_capacity_config (
    id                          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    academic_year_id            INT UNSIGNED NULL,
    available_pct_threshold     DECIMAL(5,2) NOT NULL DEFAULT 20.00
        COMMENT 'remaining % of capacity above which status = available',
    limited_pct_threshold       DECIMAL(5,2) NOT NULL DEFAULT 1.00
        COMMENT 'remaining % at or below which status = limited/full',
    created_at                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_year (academic_year_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO academic_capacity_config
    (academic_year_id, available_pct_threshold, limited_pct_threshold)
VALUES (NULL, 20.00, 1.00);

SET FOREIGN_KEY_CHECKS = 1;
