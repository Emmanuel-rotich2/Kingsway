-- Transport Attendance and Incidents Tables for Driver Role
-- This migration creates tables for tracking passenger pickup/drop-off and incident reporting

-- Student Transport Attendance Table
-- Tracks pickup/drop-off status for transport passengers
CREATE TABLE IF NOT EXISTS student_transport_attendance (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT(10) UNSIGNED NOT NULL,
    route_id INT(10) UNSIGNED NOT NULL,
    vehicle_id INT(10) UNSIGNED DEFAULT NULL,
    attendance_date DATE NOT NULL,
    trip_session ENUM('morning_pickup', 'evening_dropoff', 'midday_trip', 'special_trip') NOT NULL DEFAULT 'morning_pickup',
    status ENUM('pending', 'picked_up', 'dropped_off', 'absent', 'excused', 'not_riding') NOT NULL DEFAULT 'pending',
    marked_time TIME DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    marked_by INT(10) UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_attendance (student_id, attendance_date, trip_session),
    KEY idx_student_id (student_id),
    KEY idx_route_id (route_id),
    KEY idx_vehicle_id (vehicle_id),
    KEY idx_attendance_date (attendance_date),
    KEY idx_status (status),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (route_id) REFERENCES transport_routes(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES transport_vehicles(id) ON DELETE SET NULL,
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Student Transport Incidents Table
-- Tracks transport-related incidents reported by drivers
CREATE TABLE IF NOT EXISTS student_transport_incidents (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT(10) UNSIGNED DEFAULT NULL,
    route_id INT(10) UNSIGNED DEFAULT NULL,
    vehicle_id INT(10) UNSIGNED DEFAULT NULL,
    incident_datetime DATETIME NOT NULL,
    incident_type ENUM('accident', 'late_pickup', 'late_dropoff', 'wrong_stop', 'behavior', 'medical', 'vehicle_breakdown', 'other') NOT NULL,
    description TEXT NOT NULL,
    action_taken TEXT DEFAULT NULL,
    escalated TINYINT(1) NOT NULL DEFAULT 0,
    escalated_to INT(10) UNSIGNED DEFAULT NULL,
    escalated_at TIMESTAMP NULL DEFAULT NULL,
    reported_by INT(10) UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_student_id (student_id),
    KEY idx_route_id (route_id),
    KEY idx_vehicle_id (vehicle_id),
    KEY idx_incident_datetime (incident_datetime),
    KEY idx_reported_by (reported_by),
    KEY idx_escalated (escalated),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
    FOREIGN KEY (route_id) REFERENCES transport_routes(id) ON DELETE SET NULL,
    FOREIGN KEY (vehicle_id) REFERENCES transport_vehicles(id) ON DELETE SET NULL,
    FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (escalated_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Student Transport Notes Table
-- Tracks transport-related notes for passengers (e.g., special instructions, safety alerts)
CREATE TABLE IF NOT EXISTS student_transport_notes (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT(10) UNSIGNED NOT NULL,
    note_type ENUM('pickup_instruction', 'dropoff_instruction', 'safety_alert', 'emergency_contact', 'behavior_note', 'other') NOT NULL DEFAULT 'other',
    note TEXT NOT NULL,
    visibility ENUM('public', 'driver_only', 'admin_only') NOT NULL DEFAULT 'public',
    priority ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
    resolved TINYINT(1) NOT NULL DEFAULT 0,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    resolved_by INT(10) UNSIGNED DEFAULT NULL,
    created_by INT(10) UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_student_id (student_id),
    KEY idx_note_type (note_type),
    KEY idx_visibility (visibility),
    KEY idx_priority (priority),
    KEY idx_resolved (resolved),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
