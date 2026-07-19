-- ============================================================================
-- Fix: create the missing lesson_observations table
-- ============================================================================
-- The Academic API exposes GET /api/academic/lesson-observations-list and
-- POST /api/academic/lesson-observations-create, and the intern-teacher
-- analytics service queries this table — but the table was never created,
-- producing SQLSTATE 42S02 (Base table or view not found) -> HTTP 500.
--
-- Columns are derived from the live queries:
--   * api/modules/academic/AcademicAPI.php::getLessonObservations /
--     createLessonObservation  -> teacher_id, observer_id, learning_area_id,
--                                  class_id, observation_date, strengths,
--                                  areas_for_improvement, recommendations,
--                                  rating, status
--   * api/services/InternTeacherAnalyticsService.php -> intern_id, stream_id,
--                                  subject_id, feedback
--
-- NOTE: InternTeacherAnalyticsService also LEFT JOINs `subjects`, a table that
-- does NOT exist in this schema (subjects live in `learning_areas`). That is a
-- separate pre-existing defect in the analytics query, not fixed here.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS lesson_observations (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    teacher_id              INT UNSIGNED NOT NULL,
    observer_id             INT UNSIGNED NOT NULL,
    intern_id               INT UNSIGNED NULL,
    learning_area_id        INT UNSIGNED NULL,
    subject_id              INT UNSIGNED NULL,
    class_id                INT UNSIGNED NOT NULL,
    stream_id               INT UNSIGNED NULL,
    observation_date        DATE NOT NULL,
    strengths               JSON NULL,
    areas_for_improvement   JSON NULL,
    recommendations         JSON NULL,
    feedback                TEXT NULL,
    rating                  DECIMAL(3,2) NULL,
    status                  VARCHAR(30) NOT NULL DEFAULT 'scheduled'
                                    COMMENT 'scheduled | completed | cancelled',
    created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_teacher (teacher_id),
    KEY idx_observer (observer_id),
    KEY idx_class (class_id),
    KEY idx_intern (intern_id),
    KEY idx_date (observation_date),
    CONSTRAINT fk_lo_teacher FOREIGN KEY (teacher_id) REFERENCES staff (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_lo_observer FOREIGN KEY (observer_id) REFERENCES staff (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_lo_class FOREIGN KEY (class_id) REFERENCES classes (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_lo_stream FOREIGN KEY (stream_id) REFERENCES class_streams (id)
        ON DELETE SET NULL,
    CONSTRAINT fk_lo_intern FOREIGN KEY (intern_id) REFERENCES staff (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
