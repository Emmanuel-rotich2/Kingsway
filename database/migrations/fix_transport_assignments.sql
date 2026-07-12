-- Fix Transport Assignments for Pickup/Drop-off Point Tracking
-- Add missing columns to student_transport_assignments for proper transport operations

-- First, update the status column to include new transport statuses
ALTER TABLE student_transport_assignments
MODIFY COLUMN status ENUM('active', 'withdrawn', 'suspended', 'not_riding', 'transferred') DEFAULT 'active';

-- Then add new columns
ALTER TABLE student_transport_assignments
ADD COLUMN pickup_stop_id INT(10) UNSIGNED DEFAULT NULL AFTER stop_id,
ADD COLUMN dropoff_stop_id INT(10) UNSIGNED DEFAULT NULL AFTER pickup_stop_id,
ADD COLUMN pickup_time TIME DEFAULT NULL AFTER dropoff_stop_id,
ADD COLUMN dropoff_time TIME DEFAULT NULL AFTER pickup_time,
ADD COLUMN assignment_date DATE DEFAULT NULL AFTER dropoff_time,
ADD COLUMN assigned_by INT(10) UNSIGNED DEFAULT NULL AFTER assignment_date,
ADD COLUMN notes TEXT DEFAULT NULL AFTER assigned_by,
ADD INDEX idx_pickup_stop_id (pickup_stop_id),
ADD INDEX idx_dropoff_stop_id (dropoff_stop_id),
ADD INDEX idx_assigned_by (assigned_by),
ADD FOREIGN KEY (pickup_stop_id) REFERENCES transport_stops(id) ON DELETE SET NULL,
ADD FOREIGN KEY (dropoff_stop_id) REFERENCES transport_stops(id) ON DELETE SET NULL,
ADD FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL;
