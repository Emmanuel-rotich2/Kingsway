-- =============================================================================
-- Migration: 2026_04_01_missing_transport_tables.sql
-- Purpose:   Add 5 transport-module tables missing from the main schema and
--            extend route_schedules with columns expected by PHP code.
--
-- Tables created:
--   student_transport_assignments  — per-student monthly route assignment
--   student_transport_payments     — transport fee payment records
--   transport_assignments          — simple route<->student assignment log
--   transport_schedules            — vehicle/route/driver daily schedules
--   driver_attendance              — daily driver attendance records
--
-- Tables altered:
--   route_schedules — adds vehicle_id, driver_id, pickup_time, dropoff_time, notes
--
-- Conventions match the existing schema: INT UNSIGNED ids, utf8mb4_unicode_ci.
-- Safe to re-run: CREATE TABLE IF NOT EXISTS / ADD COLUMN IF NOT EXISTS throughout.
-- MySQL 8 compatible.
-- =============================================================================

CREATE TABLE IF NOT EXISTS student_transport_assignments (
  id              INT UNSIGNED    NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_id      INT UNSIGNED    NOT NULL,
  route_id        INT UNSIGNED    NOT NULL,
  stop_id         INT UNSIGNED    DEFAULT NULL,
  month           TINYINT UNSIGNED NOT NULL COMMENT '1-12',
  year            YEAR            NOT NULL,
  expected_amount DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  status          ENUM('active','withdrawn') NOT NULL DEFAULT 'active',
  created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id)         ON DELETE CASCADE,
  FOREIGN KEY (route_id)   REFERENCES transport_routes(id) ON DELETE CASCADE,
  FOREIGN KEY (stop_id)    REFERENCES transport_stops(id)  ON DELETE SET NULL,
  INDEX idx_student_month_year (student_id, month, year),
  INDEX idx_route_month_year   (route_id,   month, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_transport_payments (
  id             INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_id     INT UNSIGNED  NOT NULL,
  route_id       INT UNSIGNED  DEFAULT NULL,
  month          TINYINT UNSIGNED NOT NULL COMMENT '1-12',
  year           YEAR          NOT NULL,
  amount         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  payment_date   DATE          DEFAULT NULL,
  payment_method VARCHAR(50)   NOT NULL DEFAULT 'cash',
  transaction_id VARCHAR(100)  DEFAULT NULL,
  status         ENUM('pending','confirmed','reversed') NOT NULL DEFAULT 'pending',
  created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  INDEX idx_student_month_year (student_id, month, year),
  INDEX idx_status             (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transport_assignments (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  route_id   INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  status     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (route_id)   REFERENCES transport_routes(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id)         ON DELETE CASCADE,
  INDEX idx_student_id          (student_id),
  INDEX idx_route_student_status (route_id, student_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transport_schedules (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  vehicle_id  INT UNSIGNED DEFAULT NULL,
  route_id    INT UNSIGNED DEFAULT NULL,
  driver_id   INT UNSIGNED DEFAULT NULL,
  date        DATE         DEFAULT NULL,
  pickup_time TIME         DEFAULT NULL,
  term_id     INT UNSIGNED DEFAULT NULL,
  status      ENUM('active','cancelled') NOT NULL DEFAULT 'active',
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_vehicle_id  (vehicle_id),
  INDEX idx_route_id    (route_id),
  INDEX idx_driver_date (driver_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS driver_attendance (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  driver_id       INT UNSIGNED NOT NULL,
  attendance_date DATE         NOT NULL,
  status          ENUM('present','absent','leave') NOT NULL DEFAULT 'present',
  created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_driver_date (driver_id, attendance_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE route_schedules
  ADD COLUMN IF NOT EXISTS vehicle_id   INT UNSIGNED AFTER departure_time,
  ADD COLUMN IF NOT EXISTS driver_id    INT UNSIGNED AFTER vehicle_id,
  ADD COLUMN IF NOT EXISTS pickup_time  TIME         AFTER driver_id,
  ADD COLUMN IF NOT EXISTS dropoff_time TIME         AFTER pickup_time,
  ADD COLUMN IF NOT EXISTS notes        TEXT         AFTER dropoff_time;

-- Verify all 5 new tables were created successfully
SELECT TABLE_NAME
FROM   INFORMATION_SCHEMA.TABLES
WHERE  TABLE_SCHEMA = DATABASE()
AND    TABLE_NAME IN (
         'student_transport_assignments',
         'student_transport_payments',
         'transport_assignments',
         'transport_schedules',
         'driver_attendance'
       );
