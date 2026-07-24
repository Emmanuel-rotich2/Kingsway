-- Staff Type ID Migration
-- Migrate staff table to use integer staff_type_id consistently
-- This ensures proper data type handling for staff_type_id

-- Step 1: Check current staff_type_id column type
-- If it's not already integer, we need to migrate

-- Step 2: Create backup of existing data
CREATE TABLE IF NOT EXISTS staff_backup_20260723 AS SELECT * FROM staff;

-- Step 3: Update staff_type_id to integer values where they might be strings
UPDATE staff SET staff_type_id = 1 WHERE staff_type_id = 'Teaching' OR staff_type_id = 'teaching' OR staff_type_id = '1';
UPDATE staff SET staff_type_id = 2 WHERE staff_type_id = 'Non-Teaching' OR staff_type_id = 'non-teaching' OR staff_type_id = '2';
UPDATE staff SET staff_type_id = 3 WHERE staff_type_id = 'Admin' OR staff_type_id = 'admin' OR staff_type_id = '3';

-- Step 4: Set default values for NULL staff_type_id
UPDATE staff SET staff_type_id = 2 WHERE staff_type_id IS NULL;

-- Step 5: Ensure staff_type_id is integer (if using SQLite/PostgreSQL)
-- For SQLite, we need to recreate the table to change column type properly
-- For MySQL/PostgreSQL, we can use ALTER TABLE

-- This script provides the basic data migration
-- Column type changes should be done based on your specific database engine

-- Step 6: Verify the migration
SELECT COUNT(*) as total_staff, 
       COUNT(CASE WHEN staff_type_id = 1 THEN 1 END) as teaching_count,
       COUNT(CASE WHEN staff_type_id = 2 THEN 1 END) as non_teaching_count,
       COUNT(CASE WHEN staff_type_id = 3 THEN 1 END) as admin_count
FROM staff;

-- Staff Type Mapping:
-- 1 = Teaching Staff
-- 2 = Non-Teaching Staff  
-- 3 = Admin Staff