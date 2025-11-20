
-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Nov 14, 2025 at 05:36 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `KingsWayAcademy`
--
DROP DATABASE IF EXISTS `KingsWayAcademy`;
CREATE DATABASE IF NOT EXISTS `KingsWayAcademy` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `KingsWayAcademy`;

DELIMITER $$
--
-- Procedures
--
DROP PROCEDURE IF EXISTS `sp_add_custom_stream`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_add_custom_stream` (IN `p_class_id` INT UNSIGNED, IN `p_stream_name` VARCHAR(50), IN `p_capacity` INT, IN `p_teacher_id` INT UNSIGNED)   BEGIN
DECLARE v_default_stream_id INT UNSIGNED;
DECLARE v_class_name VARCHAR(50);
DECLARE v_error_msg VARCHAR(255);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Add stream failed';
END;

IF EXISTS (
  SELECT 1
  FROM class_streams
  WHERE class_id = p_class_id
    AND stream_name = p_stream_name
) THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Stream name already exists for this class';
END IF;

SELECT name INTO v_class_name
FROM classes
WHERE id = p_class_id;
IF v_class_name IS NULL THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Class not found';
END IF;

INSERT INTO class_streams (
    class_id,
    stream_name,
    capacity,
    teacher_id,
    status
  )
VALUES (
    p_class_id,
    p_stream_name,
    p_capacity,
    p_teacher_id,
    'active'
  );


UPDATE class_streams
SET status = 'inactive'
WHERE class_id = p_class_id
  AND stream_name = v_class_name
  AND status = 'active'
  AND p_stream_name != v_class_name;

END$$

DROP PROCEDURE IF EXISTS `sp_advance_workflow_stage`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_advance_workflow_stage` (IN `p_instance_id` INT UNSIGNED, IN `p_to_stage` VARCHAR(50), IN `p_action` VARCHAR(100), IN `p_user_id` INT UNSIGNED, IN `p_remarks` TEXT, IN `p_data_json` JSON)   BEGIN
    DECLARE v_current_stage VARCHAR(50);
    DECLARE v_allowed_transitions JSON;
    DECLARE v_workflow_id INT UNSIGNED;
    
    
    SELECT wi.current_stage, wi.workflow_id
    INTO v_current_stage, v_workflow_id
    FROM workflow_instances wi
    WHERE wi.id = p_instance_id;
    
    
    SELECT ws.allowed_transitions 
    INTO v_allowed_transitions
    FROM workflow_stages ws
    WHERE ws.workflow_id = v_workflow_id 
    AND ws.code = v_current_stage;
    
    IF NOT JSON_CONTAINS(v_allowed_transitions, JSON_ARRAY(p_to_stage)) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Invalid workflow transition';
    END IF;
    
    
    INSERT INTO workflow_stage_history (
        instance_id, from_stage, to_stage, action_taken,
        processed_by, remarks, data_json
    ) VALUES (
        p_instance_id, v_current_stage, p_to_stage, p_action,
        p_user_id, p_remarks, p_data_json
    );
    
    
    UPDATE workflow_instances 
    SET current_stage = p_to_stage,
        completed_at = CASE 
            WHEN p_to_stage IN ('completed', 'cancelled') THEN CURRENT_TIMESTAMP
            ELSE NULL
        END
    WHERE id = p_instance_id;
    
    
    INSERT INTO workflow_notifications (
        instance_id, notification_type, user_id, title, message
    )
    SELECT 
        p_instance_id,
        'stage_entry',
        u.id,
        CONCAT('Action Required: ', ws.name),
        CONCAT('Please process ', wd.name, ' - Stage: ', ws.name)
    FROM workflow_stages ws
    JOIN workflow_definitions wd ON ws.workflow_id = wd.id
    JOIN users u ON JSON_CONTAINS(JSON_EXTRACT(wd.config_json, '$.notify_roles'), JSON_QUOTE(u.role))
    WHERE ws.workflow_id = v_workflow_id 
    AND ws.code = p_to_stage;
END$$

DROP PROCEDURE IF EXISTS `sp_allocate_payment`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_allocate_payment` (IN `p_transaction_id` INT UNSIGNED, IN `p_fee_structure_id` INT UNSIGNED, IN `p_amount` DECIMAL(10,2), IN `p_academic_term_id` INT UNSIGNED, IN `p_allocated_by` INT UNSIGNED, IN `p_notes` TEXT)   BEGIN
DECLARE v_available_amount DECIMAL(10, 2);
DECLARE v_total_allocated DECIMAL(10, 2);

SELECT amount INTO v_available_amount
FROM financial_transactions
WHERE id = p_transaction_id;

SELECT COALESCE(SUM(amount), 0) INTO v_total_allocated
FROM payment_allocations
WHERE transaction_id = p_transaction_id;

IF (v_total_allocated + p_amount) > v_available_amount THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Allocation amount exceeds available transaction amount';
END IF;

INSERT INTO payment_allocations (
    transaction_id,
    fee_structure_id,
    amount,
    academic_term_id,
    allocated_by,
    notes
  )
VALUES (
    p_transaction_id,
    p_fee_structure_id,
    p_amount,
    p_academic_term_id,
    p_allocated_by,
    p_notes
  );
END$$

DROP PROCEDURE IF EXISTS `sp_apply_fee_discount`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_apply_fee_discount` (IN `p_student_id` INT UNSIGNED, IN `p_obligation_id` INT UNSIGNED, IN `p_discount_type` VARCHAR(50), IN `p_discount_value` DECIMAL(10,2), IN `p_reason` TEXT, IN `p_approved_by` INT UNSIGNED)   BEGIN
DECLARE v_discount_percentage DECIMAL(5, 2);
START TRANSACTION;

IF p_discount_type = 'percentage' THEN
SELECT (amount_due * p_discount_value / 100) INTO v_discount_percentage
FROM student_fee_obligations
WHERE id = p_obligation_id;
ELSE
SET v_discount_percentage = p_discount_value;
END IF;

INSERT INTO fee_discounts_waivers (
    student_id,
    student_fee_obligation_id,
    discount_type,
    discount_value,
    discount_percentage,
    reason,
    academic_year,
    approved_by,
    approved_date,
    status
  )
SELECT p_student_id,
  p_obligation_id,
  p_discount_type,
  p_discount_value,
  v_discount_percentage,
  p_reason,
  academic_year,
  p_approved_by,
  NOW(),
  'active'
FROM student_fee_obligations
WHERE id = p_obligation_id;

UPDATE student_fee_obligations
SET amount_waived = amount_waived + v_discount_percentage,
  status = IF(
    (amount_paid + amount_waived) >= amount_due,
    'paid',
    'partial'
  )
WHERE id = p_obligation_id;
COMMIT;
END$$

DROP PROCEDURE IF EXISTS `sp_apply_staff_loan`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_apply_staff_loan` (IN `p_staff_id` INT, IN `p_loan_type` VARCHAR(100), IN `p_principal_amount` DECIMAL(12,2), IN `p_agreed_monthly_deduction` DECIMAL(12,2), OUT `p_loan_id` INT)   BEGIN
    DECLARE v_staff_exists INT DEFAULT 0;
    DECLARE v_basic_salary DECIMAL(12,2);
    DECLARE v_active_loans INT DEFAULT 0;
    DECLARE v_max_loan DECIMAL(12,2);
    DECLARE v_max_deduction DECIMAL(12,2);
    
    
    SELECT COUNT(*), COALESCE(salary, 0) 
    INTO v_staff_exists, v_basic_salary
    FROM staff 
    WHERE id = p_staff_id AND status = 'active';
    
    IF v_staff_exists = 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Staff member not found or not active';
    END IF;
    
    
    SELECT COUNT(*) INTO v_active_loans
    FROM staff_loans
    WHERE staff_id = p_staff_id 
        AND status = 'active'
        AND balance_remaining > 0;
    
    IF v_active_loans > 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Staff has an active loan. Please clear existing loan before applying for a new one.';
    END IF;
    
    
    SET v_max_loan = v_basic_salary * 3;
    
    IF p_principal_amount > v_max_loan THEN
        SET @error_msg = CONCAT('Loan amount exceeds maximum allowed (3x basic salary = KES ', v_max_loan, ')');
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Loan amount exceeds maximum allowed';
    END IF;
    
    IF p_principal_amount <= 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Loan amount must be greater than zero';
    END IF;
    
    
    SET v_max_deduction = v_basic_salary / 3;
    
    IF p_agreed_monthly_deduction > v_max_deduction THEN
        SET @error_msg = CONCAT('Monthly deduction exceeds maximum allowed (1/3 of basic salary = KES ', v_max_deduction, ')');
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Monthly deduction exceeds maximum allowed';
    END IF;
    
    IF p_agreed_monthly_deduction <= 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Monthly deduction must be greater than zero';
    END IF;
    
    
    IF CEIL(p_principal_amount / p_agreed_monthly_deduction) < 3 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Loan repayment period too short. Minimum 3 months required.';
    END IF;
    
    IF CEIL(p_principal_amount / p_agreed_monthly_deduction) > 36 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Loan repayment period too long. Maximum 36 months allowed.';
    END IF;
    
    
    INSERT INTO staff_loans (
        staff_id, 
        loan_type, 
        principal_amount, 
        loan_date, 
        agreed_monthly_deduction, 
        balance_remaining, 
        status,
        created_at
    )
    VALUES (
        p_staff_id,
        p_loan_type,
        p_principal_amount,
        CURDATE(),
        p_agreed_monthly_deduction,
        p_principal_amount, 
        'suspended', 
        NOW()
    );
    
    SET p_loan_id = LAST_INSERT_ID();
    
    
    
END$$

DROP PROCEDURE IF EXISTS `sp_approve_class_promotion`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_approve_class_promotion` (IN `p_batch_id` INT UNSIGNED, IN `p_class_id` INT UNSIGNED, IN `p_stream_id` INT UNSIGNED, IN `p_approved_by` INT UNSIGNED, IN `p_notes` TEXT)   BEGIN
DECLARE v_error_msg VARCHAR(255);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Class approval failed';
END;

UPDATE student_promotions sp
  INNER JOIN class_promotion_queue cpq ON sp.batch_id = cpq.batch_id
SET sp.promotion_status = 'approved',
  sp.approved_by = p_approved_by,
  sp.approval_date = NOW(),
  sp.approval_notes = p_notes
WHERE sp.batch_id = p_batch_id
  AND sp.promoted_to_class_id = p_class_id
  AND sp.promoted_to_stream_id = p_stream_id
  AND sp.promotion_status = 'pending_approval'
  AND cpq.batch_id = p_batch_id
  AND cpq.class_id = p_class_id
  AND cpq.stream_id = p_stream_id;

INSERT INTO student_registrations (
    student_id,
    stream_id,
    academic_year,
    status,
    registration_date
  )
SELECT sp.student_id,
  sp.promoted_to_stream_id,
  sp.to_academic_year,
  'active',
  NOW()
FROM student_promotions sp
WHERE sp.batch_id = p_batch_id
  AND sp.promoted_to_class_id = p_class_id
  AND sp.promoted_to_stream_id = p_stream_id
  AND sp.promotion_status = 'approved'
  AND NOT EXISTS (
    SELECT 1
    FROM student_registrations
    WHERE student_id = sp.student_id
      AND stream_id = sp.promoted_to_stream_id
      AND academic_year = sp.to_academic_year
  ) ON DUPLICATE KEY
UPDATE status = 'active';

UPDATE students s
  INNER JOIN student_promotions sp ON s.id = sp.student_id
SET s.stream_id = sp.promoted_to_stream_id
WHERE sp.batch_id = p_batch_id
  AND sp.promoted_to_class_id = p_class_id
  AND sp.promoted_to_stream_id = p_stream_id
  AND sp.promotion_status = 'approved';

UPDATE class_promotion_queue
SET approval_status = 'approved',
  approved_count = (
    SELECT COUNT(*)
    FROM student_promotions
    WHERE batch_id = p_batch_id
      AND promoted_to_class_id = p_class_id
      AND promoted_to_stream_id = p_stream_id
      AND promotion_status = 'approved'
  ),
  reviewed_at = NOW()
WHERE batch_id = p_batch_id
  AND class_id = p_class_id
  AND stream_id = p_stream_id;
END$$

DROP PROCEDURE IF EXISTS `sp_approve_student_promotion`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_approve_student_promotion` (IN `p_promotion_id` INT UNSIGNED, IN `p_approved_by` INT UNSIGNED, IN `p_notes` TEXT)   BEGIN
DECLARE v_batch_id INT UNSIGNED;
DECLARE v_promoted_class_id INT UNSIGNED;
DECLARE v_promoted_stream_id INT UNSIGNED;
DECLARE v_to_academic_year YEAR;
DECLARE v_error_msg VARCHAR(255);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Student approval failed';
END;

SELECT batch_id,
  promoted_to_class_id,
  promoted_to_stream_id,
  to_academic_year INTO v_batch_id,
  v_promoted_class_id,
  v_promoted_stream_id,
  v_to_academic_year
FROM student_promotions
WHERE id = p_promotion_id;

UPDATE student_promotions
SET promotion_status = 'approved',
  approved_by = p_approved_by,
  approval_date = NOW(),
  approval_notes = p_notes
WHERE id = p_promotion_id;

INSERT INTO student_registrations (
    student_id,
    stream_id,
    academic_year,
    status,
    registration_date
  )
SELECT student_id,
  v_promoted_stream_id,
  v_to_academic_year,
  'active',
  NOW()
FROM student_promotions
WHERE id = p_promotion_id ON DUPLICATE KEY
UPDATE status = 'active';

UPDATE students s
  INNER JOIN student_promotions sp ON s.id = sp.student_id
SET s.stream_id = v_promoted_stream_id
WHERE sp.id = p_promotion_id;

UPDATE class_promotion_queue
SET approved_count = (
    SELECT COUNT(*)
    FROM student_promotions
    WHERE batch_id = v_batch_id
      AND promoted_to_class_id = v_promoted_class_id
      AND promoted_to_stream_id = v_promoted_stream_id
      AND promotion_status = 'approved'
  ),
  pending_count = (
    SELECT COUNT(*)
    FROM student_promotions
    WHERE batch_id = v_batch_id
      AND promoted_to_class_id = v_promoted_class_id
      AND promoted_to_stream_id = v_promoted_stream_id
      AND promotion_status = 'pending_approval'
  )
WHERE batch_id = v_batch_id
  AND class_id = v_promoted_class_id
  AND stream_id = v_promoted_stream_id;
END$$

DROP PROCEDURE IF EXISTS `sp_assess_learner_competency`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_assess_learner_competency` (IN `p_student_id` INT UNSIGNED, IN `p_competency_id` INT UNSIGNED, IN `p_academic_year` YEAR, IN `p_term_id` INT UNSIGNED, IN `p_performance_level_id` INT UNSIGNED, IN `p_evidence` TEXT, IN `p_teacher_notes` TEXT, IN `p_assessed_by` INT UNSIGNED, IN `p_assessed_date` DATE)   BEGIN
INSERT INTO learner_competencies (
    student_id,
    competency_id,
    academic_year,
    term_id,
    performance_level_id,
    evidence,
    teacher_notes,
    assessed_by,
    assessed_date
  )
VALUES (
    p_student_id,
    p_competency_id,
    p_academic_year,
    p_term_id,
    p_performance_level_id,
    p_evidence,
    p_teacher_notes,
    p_assessed_by,
    p_assessed_date
  ) ON DUPLICATE KEY
UPDATE performance_level_id = p_performance_level_id,
  evidence = p_evidence,
  teacher_notes = p_teacher_notes,
  assessed_by = p_assessed_by,
  assessed_date = p_assessed_date,
  updated_at = NOW();
END$$

DROP PROCEDURE IF EXISTS `sp_assign_staff_type_and_category`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_assign_staff_type_and_category` (IN `p_staff_id` INT UNSIGNED, IN `p_staff_type_id` INT UNSIGNED, IN `p_staff_category_id` INT UNSIGNED)   BEGIN
UPDATE staff
SET staff_type_id = p_staff_type_id,
  staff_category_id = p_staff_category_id,
  updated_at = NOW()
WHERE id = p_staff_id;
INSERT INTO system_events (event_type, event_data)
VALUES (
    'staff_type_assigned',
    JSON_OBJECT(
      'staff_id',
      p_staff_id,
      'type_id',
      p_staff_type_id,
      'category_id',
      p_staff_category_id
    )
  );
END$$

DROP PROCEDURE IF EXISTS `sp_auto_generate_onboarding_tasks`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_auto_generate_onboarding_tasks` (IN `p_staff_id` INT, IN `p_onboarding_id` INT)   BEGIN
    DECLARE v_staff_type_id INT;
    DECLARE v_department_id INT;
    
    SELECT staff_type_id, department_id INTO v_staff_type_id, v_department_id
    FROM staff WHERE id = p_staff_id;
    
    
    INSERT INTO onboarding_tasks (onboarding_id, task_name, description, category, priority, sequence, due_date, status, department_id)
    VALUES
    (p_onboarding_id, 'Submit Personal Information', 'Complete personal information form', 'documentation', 'high', 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'pending', v_department_id),
    (p_onboarding_id, 'Document Submission', 'Submit ID, certificates, bank details', 'documentation', 'high', 2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'pending', v_department_id),
    (p_onboarding_id, 'Policy Orientation', 'Review school policies and procedures', 'orientation', 'high', 3, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'pending', v_department_id),
    (p_onboarding_id, 'Department Introduction', 'Meet department head and team', 'orientation', 'medium', 4, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'pending', v_department_id),
    (p_onboarding_id, 'System Access Request', 'Complete system access form', 'system_setup', 'high', 5, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'pending', v_department_id);
    
    
    IF v_staff_type_id = 1 THEN
        INSERT INTO onboarding_tasks (onboarding_id, task_name, description, category, priority, sequence, due_date, status, department_id)
        VALUES
        (p_onboarding_id, 'Curriculum Review', 'Review subject curriculum and schemes of work', 'training', 'high', 6, DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'pending', v_department_id),
        (p_onboarding_id, 'Classroom Assignment', 'Receive classroom keys and resources', 'setup', 'medium', 7, DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'pending', v_department_id);
    ELSE
        
        INSERT INTO onboarding_tasks (onboarding_id, task_name, description, category, priority, sequence, due_date, status, department_id)
        VALUES
        (p_onboarding_id, 'Administrative Software Training', 'Complete training on school management system', 'training', 'high', 6, DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'pending', v_department_id);
    END IF;
END$$

DROP PROCEDURE IF EXISTS `sp_auto_rollover_fee_structures`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_auto_rollover_fee_structures` (IN `p_source_year` YEAR, IN `p_target_year` YEAR, IN `p_executed_by` INT, OUT `p_structures_copied` INT, OUT `p_rollover_log_id` INT)   BEGIN
    DECLARE v_error_message TEXT DEFAULT NULL;
    DECLARE v_rollover_log_id INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1 v_error_message = MESSAGE_TEXT;
        
        UPDATE fee_structure_rollover_log
        SET rollover_status = 'failed',
            error_message = v_error_message
        WHERE id = v_rollover_log_id;
        
        ROLLBACK;
        
        SET p_structures_copied = 0;
        SET p_rollover_log_id = v_rollover_log_id;
    END;
    
    START TRANSACTION;
    
    
    INSERT INTO fee_structure_rollover_log (
        source_academic_year,
        target_academic_year,
        executed_by,
        rollover_status
    ) VALUES (
        p_source_year,
        p_target_year,
        p_executed_by,
        'in_progress'
    );
    
    SET v_rollover_log_id = LAST_INSERT_ID();
    
    
    INSERT INTO fee_structures_detailed (
        level_id,
        academic_year,
        term_id,
        student_type_id,
        fee_type_id,
        amount,
        due_date,
        description,
        status,
        is_auto_rollover,
        copied_from_id,
        rollover_notes,
        created_by
    )
    SELECT 
        fsd.level_id,
        p_target_year as academic_year,
        (SELECT at_target.id 
         FROM academic_terms at_target 
         WHERE at_target.year = p_target_year 
         AND at_target.term_number = at_source.term_number 
         LIMIT 1) as term_id,
        fsd.student_type_id,
        fsd.fee_type_id,
        fsd.amount,
        DATE_ADD(fsd.due_date, INTERVAL (p_target_year - p_source_year) YEAR) as due_date,
        CONCAT('Auto-rolled from ', p_source_year, ': ', COALESCE(fsd.description, '')) as description,
        'pending_review' as status,
        TRUE as is_auto_rollover,
        fsd.id as copied_from_id,
        CONCAT('Automatically copied from academic year ', p_source_year, ' on ', NOW()) as rollover_notes,
        p_executed_by as created_by
    FROM fee_structures_detailed fsd
    JOIN academic_terms at_source ON fsd.term_id = at_source.id
    WHERE fsd.academic_year = p_source_year
    AND fsd.status = 'active'
    AND NOT EXISTS (
        SELECT 1 FROM fee_structures_detailed fsd2
        WHERE fsd2.academic_year = p_target_year
        AND fsd2.level_id = fsd.level_id
        AND fsd2.fee_type_id = fsd.fee_type_id
        AND fsd2.student_type_id = fsd.student_type_id
    );
    
    SET p_structures_copied = ROW_COUNT();
    
    UPDATE fee_structure_rollover_log
    SET structures_copied = p_structures_copied,
        rollover_status = 'completed'
    WHERE id = v_rollover_log_id;
    
    INSERT INTO fee_structure_change_log (
        fee_structure_detail_id,
        changed_by,
        change_type,
        change_notes
    )
    SELECT 
        id,
        p_executed_by,
        'rollover',
        CONCAT('Rolled over from year ', p_source_year, ' to ', p_target_year)
    FROM fee_structures_detailed
    WHERE academic_year = p_target_year
    AND is_auto_rollover = TRUE
    AND copied_from_id IS NOT NULL;
    
    COMMIT;
    
    SET p_rollover_log_id = v_rollover_log_id;
END$$

DROP PROCEDURE IF EXISTS `sp_broadcast_notification`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_broadcast_notification` (IN `p_title` VARCHAR(255), IN `p_message` TEXT, IN `p_notification_type` VARCHAR(50), IN `p_target_user_ids` VARCHAR(1000), IN `p_priority` VARCHAR(20), IN `p_created_by` INT UNSIGNED)   BEGIN
DECLARE v_error_msg VARCHAR(255);
DECLARE v_notification_id INT UNSIGNED;
DECLARE v_user_id INT UNSIGNED;
DECLARE v_temp_id VARCHAR(20);
DECLARE v_remaining VARCHAR(1000);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = v_error_msg;
END;

IF p_target_user_ids IS NULL
OR p_target_user_ids = '' THEN
SET p_target_user_ids = '0';

END IF;

SET v_remaining = CONCAT(p_target_user_ids, ',');
WHILE LOCATE(',', v_remaining) > 0 DO
SET v_temp_id = TRIM(SUBSTRING_INDEX(v_remaining, ',', 1));
SET v_user_id = CAST(v_temp_id AS UNSIGNED);
INSERT INTO notifications (
    user_id,
    title,
    message,
    notification_type,
    priority,
    status,
    created_by,
    created_at
  )
VALUES (
    NULLIF(v_user_id, 0),
    p_title,
    p_message,
    p_notification_type,
    p_priority,
    'unread',
    p_created_by,
    NOW()
  );
SET v_remaining = SUBSTRING(v_remaining, LOCATE(',', v_remaining) + 1);
END WHILE;
INSERT INTO system_events (event_type, event_data, created_at)
VALUES (
    'notification_broadcast',
    JSON_OBJECT(
      'title',
      p_title,
      'type',
      p_notification_type,
      'created_by',
      p_created_by
    ),
    NOW()
  );
END$$

DROP PROCEDURE IF EXISTS `sp_bulk_inventory_reconciliation`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_bulk_inventory_reconciliation` (IN `p_count_id` INT)   BEGIN
UPDATE inventory_items i
  JOIN inventory_count_items ci ON i.id = ci.item_id
  AND ci.count_id = p_count_id
SET i.current_quantity = ci.actual_quantity;
END$$

DROP PROCEDURE IF EXISTS `sp_bulk_mark_staff_attendance`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_bulk_mark_staff_attendance` (IN `p_department_id` INT, IN `p_date` DATE, IN `p_status` VARCHAR(50), IN `p_marked_by` INT)   BEGIN
INSERT IGNORE INTO staff_attendance (staff_id, date, status, marked_by)
SELECT id,
  p_date,
  p_status,
  p_marked_by
FROM staff
WHERE department_id = p_department_id
  AND status = 'active';
END$$

DROP PROCEDURE IF EXISTS `sp_bulk_mark_student_attendance`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_bulk_mark_student_attendance` (IN `p_class_id` INT, IN `p_date` DATE, IN `p_status` VARCHAR(50), IN `p_marked_by` INT)   BEGIN
INSERT IGNORE INTO student_attendance (student_id, date, status, class_id, marked_by)
SELECT id,
  p_date,
  p_status,
  p_class_id,
  p_marked_by
FROM students
WHERE stream_id = p_class_id
  AND status = 'active';
END$$

DROP PROCEDURE IF EXISTS `sp_bulk_payroll_calculation`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_bulk_payroll_calculation` (IN `p_payroll_month` INT, IN `p_payroll_year` INT)   BEGIN
INSERT INTO staff_payroll (
    staff_id,
    payroll_month,
    payroll_year,
    basic_salary,
    allowances,
    deductions,
    net_salary,
    status,
    payroll_period
  )
SELECT id,
  p_payroll_month,
  p_payroll_year,
  salary,
  0,
  0,
  salary,
  'pending',
  CONCAT(
    p_payroll_year,
    '-',
    LPAD(p_payroll_month, 2, '0')
  )
FROM staff
WHERE status = 'active';
END$$

DROP PROCEDURE IF EXISTS `sp_bulk_transport_assignment`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_bulk_transport_assignment` (IN `p_route_id` INT, IN `p_direction` VARCHAR(50))   BEGIN
UPDATE transport_vehicle_routes
SET status = 'active'
WHERE route_id = p_route_id
  AND direction = p_direction;
END$$

DROP PROCEDURE IF EXISTS `sp_calculate_annual_scores`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_calculate_annual_scores` (IN `p_academic_year` YEAR(4), IN `p_term1_weight` DECIMAL(3,2), IN `p_term2_weight` DECIMAL(3,2), IN `p_term3_weight` DECIMAL(3,2))   BEGIN
DECLARE done INT DEFAULT FALSE;
DECLARE v_student_id INT UNSIGNED;
DECLARE v_grade_level_id INT UNSIGNED;
DECLARE v_term1_score DECIMAL(5, 2);
DECLARE v_term1_grade VARCHAR(4);
DECLARE v_term2_score DECIMAL(5, 2);
DECLARE v_term2_grade VARCHAR(4);
DECLARE v_term3_score DECIMAL(5, 2);
DECLARE v_term3_grade VARCHAR(4);
DECLARE v_annual_score DECIMAL(5, 2);
DECLARE v_annual_percentage DECIMAL(5, 2);
DECLARE v_annual_grade VARCHAR(4);
DECLARE v_annual_points DECIMAL(5, 1);
DECLARE v_annual_rank INT;
DECLARE v_grade_total INT;
DECLARE v_grade_percentile DECIMAL(5, 2);
DECLARE v_avg_formative DECIMAL(5, 2);
DECLARE v_avg_summative DECIMAL(5, 2);
DECLARE v_pathway_classification VARCHAR(20);
DECLARE v_insights_summary JSON;
DECLARE student_cur CURSOR FOR
SELECT DISTINCT s.id,
  s.grade_level_id
FROM students s
WHERE s.status = 'active';
DECLARE CONTINUE HANDLER FOR NOT FOUND
SET done = TRUE;
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Error calculating annual scores';
END;
START TRANSACTION;
OPEN student_cur;
annual_loop: LOOP FETCH student_cur INTO v_student_id,
v_grade_level_id;
IF done THEN LEAVE annual_loop;
END IF;

SELECT COALESCE(tc.avg_overall_percentage, 0),
  COALESCE(tc.avg_overall_grade, 'BE2') INTO v_term1_score,

  --
  DROP TABLE IF EXISTS `communication_templates`;
  CREATE TABLE IF NOT EXISTS `communication_templates` (
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `template_type` enum('internal_message','sms','email','announcement') NOT NULL DEFAULT 'sms',
    `category` varchar(100) DEFAULT NULL,
    `subject` varchar(255) DEFAULT NULL,
    `template_body` longtext NOT NULL,
    `variables_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variables_json`)),
    `example_output` text DEFAULT NULL,
    `created_by` int(10) UNSIGNED NOT NULL,
    `status` enum('active','inactive','archived') NOT NULL DEFAULT 'active',
    `usage_count` int(11) NOT NULL DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `template_type` (`template_type`),
    KEY `category` (`category`),
    KEY `created_by` (`created_by`),
    KEY `idx_status` (`status`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

  -- RELATIONSHIPS FOR TABLE `communication_templates`:
  --   `created_by`
  --       `staff` -> `id`

  -- Truncate table before insert `communication_templates`

  TRUNCATE TABLE `communication_templates`;
  -- --------------------------------------------------------

  -- Parent Portal Messaging (Inbox/Outbox)
  DROP TABLE IF EXISTS `parent_portal_messages`;
  CREATE TABLE IF NOT EXISTS `parent_portal_messages` (
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_id` int(10) UNSIGNED NOT NULL,
    `student_id` int(10) UNSIGNED DEFAULT NULL,
    `sender_type` enum('parent','school','staff','admin') NOT NULL,
    `sender_id` int(10) UNSIGNED NOT NULL,
    `recipient_type` enum('parent','school','staff','admin') NOT NULL,
    `recipient_id` int(10) UNSIGNED NOT NULL,
    `subject` varchar(255) NOT NULL,
    `body` text NOT NULL,
    `status` enum('sent','read','archived','deleted') NOT NULL DEFAULT 'sent',
    `is_reply` tinyint(1) NOT NULL DEFAULT 0,
    `reply_to_id` int(10) UNSIGNED DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `parent_id` (`parent_id`),
    KEY `student_id` (`student_id`),
    KEY `sender_id` (`sender_id`),
    KEY `recipient_id` (`recipient_id`),
    KEY `reply_to_id` (`reply_to_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

  -- External Inbound Messages (SMS, Email, etc)
  DROP TABLE IF EXISTS `external_inbound_messages`;
  CREATE TABLE IF NOT EXISTS `external_inbound_messages` (
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `source_type` enum('sms','email','web','other') NOT NULL,
    `source_address` varchar(255) NOT NULL,
    `received_at` datetime NOT NULL,
    `linked_user_id` int(10) UNSIGNED DEFAULT NULL,
    `linked_parent_id` int(10) UNSIGNED DEFAULT NULL,
    `linked_student_id` int(10) UNSIGNED DEFAULT NULL,
    `subject` varchar(255) DEFAULT NULL,
    `body` text NOT NULL,
    `status` enum('pending','processed','archived','error') NOT NULL DEFAULT 'pending',
    `processing_notes` text DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `linked_user_id` (`linked_user_id`),
    KEY `linked_parent_id` (`linked_parent_id`),
    KEY `linked_student_id` (`linked_student_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

  -- Forums and Threads (for bulletin boards, discussions)
  DROP TABLE IF EXISTS `forum_threads`;
  CREATE TABLE IF NOT EXISTS `forum_threads` (
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` varchar(255) NOT NULL,
    `created_by` int(10) UNSIGNED NOT NULL,
    `forum_type` enum('general','class','staff','parent','custom') NOT NULL DEFAULT 'general',
    `status` enum('open','closed','archived') NOT NULL DEFAULT 'open',
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `created_by` (`created_by`),
    KEY `forum_type` (`forum_type`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

  DROP TABLE IF EXISTS `forum_posts`;
  CREATE TABLE IF NOT EXISTS `forum_posts` (
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `thread_id` int(10) UNSIGNED NOT NULL,
    `author_id` int(10) UNSIGNED NOT NULL,
    `author_type` enum('student','staff','parent','admin') NOT NULL,
    `body` text NOT NULL,
    `reply_to_id` int(10) UNSIGNED DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `thread_id` (`thread_id`),
    KEY `author_id` (`author_id`),
    KEY `reply_to_id` (`reply_to_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

  -- Contact Directory (for institution, staff, parents, students)
  DROP TABLE IF EXISTS `contact_directory`;
  CREATE TABLE IF NOT EXISTS `contact_directory` (
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `contact_type` enum('staff','student','parent','department','external') NOT NULL,
    `linked_id` int(10) UNSIGNED DEFAULT NULL,
    `name` varchar(255) NOT NULL,
    `email` varchar(255) DEFAULT NULL,
    `phone` varchar(50) DEFAULT NULL,
    `department` varchar(100) DEFAULT NULL,
    `role` varchar(100) DEFAULT NULL,
    `notes` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `contact_type` (`contact_type`),
    KEY `linked_id` (`linked_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

  -- Workflow Integration: Communication Approval/Escalation
  DROP TABLE IF EXISTS `communication_workflow_instances`;
  CREATE TABLE IF NOT EXISTS `communication_workflow_instances` (
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `communication_id` int(10) UNSIGNED NOT NULL,
    `workflow_code` varchar(50) NOT NULL,
    `current_stage` varchar(50) NOT NULL,
    `status` enum('active','completed','cancelled','escalated') NOT NULL DEFAULT 'active',
    `initiated_by` int(10) UNSIGNED NOT NULL,
    `initiated_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `completed_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `communication_id` (`communication_id`),
    KEY `workflow_code` (`workflow_code`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

  -- Trigger: Auto-log communication workflow initiation
  DELIMITER $$
  DROP TRIGGER IF EXISTS trg_auto_start_comm_workflow$$
  CREATE TRIGGER trg_auto_start_comm_workflow
  AFTER INSERT ON communications
  FOR EACH ROW
  BEGIN
    IF NEW.status = 'scheduled' OR NEW.status = 'sent' THEN
      INSERT INTO communication_workflow_instances (communication_id, workflow_code, current_stage, status, initiated_by, initiated_at)
      VALUES (NEW.id, 'communication_approval', 'initiated', 'active', NEW.sender_id, NOW());
    END IF;
  END$$
  DELIMITER ;
    v_avg_summative,
    v_pathway_classification,
    v_insights_summary,
    NOW()
  ) ON DUPLICATE KEY
UPDATE term1_weight = p_term1_weight,
  term1_score = v_term1_score,
  term1_grade = v_term1_grade,
  term2_weight = p_term2_weight,
  term2_score = v_term2_score,
  term2_grade = v_term2_grade,
  term3_weight = p_term3_weight,
  term3_score = v_term3_score,
  term3_grade = v_term3_grade,
  annual_score = v_annual_score,
  annual_percentage = v_annual_percentage,
  annual_grade = v_annual_grade,
  annual_points = v_annual_points,
  annual_rank = v_annual_rank,
  grade_total_students = v_grade_total,
  grade_percentile = v_grade_percentile,
  avg_formative_percentage = v_avg_formative,
  avg_summative_percentage = v_avg_summative,
  pathway_classification = v_pathway_classification,
  insights_summary = v_insights_summary,
  calculated_at = NOW(),
  updated_at = NOW();
END LOOP;
CLOSE student_cur;
COMMIT;
END$$

DROP PROCEDURE IF EXISTS `sp_calculate_kpi_achievement_score`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_calculate_kpi_achievement_score` (IN `p_staff_id` INT UNSIGNED, IN `p_academic_year` INT, OUT `p_achievement_score` DECIMAL(5,2))   BEGIN
DECLARE v_weighted_sum DECIMAL(10, 2);
DECLARE v_weight_total DECIMAL(5, 2);
SELECT COALESCE(
    SUM(
      (
        CASE
          WHEN kt.target_value > 0 THEN (ka.achieved_value / kt.target_value)
          ELSE 0
        END
      ) * kt.weight_percentage / 100
    ),
    0
  ),
  COALESCE(SUM(kt.weight_percentage), 0) INTO v_weighted_sum,
  v_weight_total
FROM kpi_targets kt
  LEFT JOIN kpi_achievements ka ON kt.staff_id = ka.staff_id
  AND ka.kpi_definition_id = kt.kpi_definition_id
  AND ka.academic_year = kt.academic_year
WHERE kt.staff_id = p_staff_id
  AND kt.academic_year = p_academic_year
  AND kt.is_active = 1;
IF v_weight_total > 0 THEN
SET p_achievement_score = ROUND((v_weighted_sum / v_weight_total) * 100, 2);
ELSE
SET p_achievement_score = 0;
END IF;
END$$

DROP PROCEDURE IF EXISTS `sp_calculate_nhif_contribution`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_calculate_nhif_contribution` (IN `p_gross_salary` DECIMAL(12,2), IN `p_financial_year` INT, OUT `p_nhif_amount` DECIMAL(12,2))   BEGIN
DECLARE v_nhif_rate DECIMAL(5, 2);
SELECT CAST(config_value AS DECIMAL(5, 2)) INTO v_nhif_rate
FROM payroll_configurations
WHERE config_key = 'NHIF_RATE'
  AND financial_year = p_financial_year
  AND is_active = 1;
SET v_nhif_rate = COALESCE(v_nhif_rate, 2.75);
SET p_nhif_amount = (p_gross_salary * v_nhif_rate / 100);
END$$

DROP PROCEDURE IF EXISTS `sp_calculate_nssf_contribution`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_calculate_nssf_contribution` (IN `p_gross_salary` DECIMAL(12,2), IN `p_financial_year` INT, OUT `p_nssf_amount` DECIMAL(12,2))   BEGIN
DECLARE v_nssf_rate DECIMAL(5, 2);
DECLARE v_nssf_max DECIMAL(12, 2);
SELECT CAST(config_value AS DECIMAL(5, 2)) INTO v_nssf_rate
FROM payroll_configurations
WHERE config_key = 'NSSF_RATE'
  AND financial_year = p_financial_year
  AND is_active = 1;
SELECT CAST(config_value AS DECIMAL(12, 2)) INTO v_nssf_max
FROM payroll_configurations
WHERE config_key = 'NSSF_MAX_CONTRIBUTION'
  AND financial_year = p_financial_year
  AND is_active = 1;
SET v_nssf_rate = COALESCE(v_nssf_rate, 6);
SET v_nssf_max = COALESCE(v_nssf_max, 18000);
SET p_nssf_amount = LEAST((p_gross_salary * v_nssf_rate / 100), v_nssf_max);
END$$

DROP PROCEDURE IF EXISTS `sp_calculate_paye_tax`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_calculate_paye_tax` (IN `p_gross_salary` DECIMAL(12,2), IN `p_financial_year` INT, OUT `p_tax_amount` DECIMAL(12,2))   BEGIN
DECLARE v_taxable_income DECIMAL(12, 2);
DECLARE v_tax DECIMAL(12, 2) DEFAULT 0;
DECLARE v_relief DECIMAL(12, 2);
DECLARE v_cursor_rate DECIMAL(5, 2);
DECLARE v_cursor_min DECIMAL(12, 2);
DECLARE v_cursor_max DECIMAL(12, 2);
DECLARE v_cursor_relief DECIMAL(12, 2);
DECLARE done INT DEFAULT FALSE;
DECLARE tax_cursor CURSOR FOR
SELECT min_income,
  max_income,
  tax_rate,
  relief_amount
FROM tax_brackets
WHERE financial_year = p_financial_year
  AND is_active = 1
ORDER BY min_income;
DECLARE CONTINUE HANDLER FOR NOT FOUND
SET done = TRUE;
SET v_taxable_income = p_gross_salary;
SET v_relief = 0;
OPEN tax_cursor;
tax_loop: LOOP FETCH tax_cursor INTO v_cursor_min,
v_cursor_max,
v_cursor_rate,
v_cursor_relief;
IF done THEN LEAVE tax_loop;
END IF;
IF v_taxable_income > v_cursor_min THEN IF v_taxable_income <= v_cursor_max THEN
SET v_tax = v_tax + (
    (v_taxable_income - v_cursor_min) * v_cursor_rate / 100
  );
ELSE
SET v_tax = v_tax + (
    (v_cursor_max - v_cursor_min) * v_cursor_rate / 100
  );
END IF;
END IF;
END LOOP;
CLOSE tax_cursor;
SELECT COALESCE(relief_amount, 0) INTO v_relief
FROM tax_brackets
WHERE financial_year = p_financial_year
  AND min_income = 0
  AND is_active = 1
LIMIT 1;
SET p_tax_amount = GREATEST(v_tax - v_relief, 0);
END$$

DROP PROCEDURE IF EXISTS `sp_calculate_payroll_for_staff`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_calculate_payroll_for_staff` (IN `p_staff_id` INT UNSIGNED, IN `p_month` INT, IN `p_year` INT)   BEGIN
DECLARE v_basic_salary DECIMAL(12, 2);
DECLARE v_allowances DECIMAL(12, 2) DEFAULT 0;
DECLARE v_gross_salary DECIMAL(12, 2);
DECLARE v_paye_tax DECIMAL(12, 2) DEFAULT 0;
DECLARE v_nssf DECIMAL(12, 2) DEFAULT 0;
DECLARE v_nhif DECIMAL(12, 2) DEFAULT 0;
DECLARE v_loan_deduction DECIMAL(12, 2) DEFAULT 0;
DECLARE v_other_deductions DECIMAL(12, 2) DEFAULT 0;
DECLARE v_net_salary DECIMAL(12, 2);
DECLARE v_duplicate INT;
SELECT id INTO v_duplicate
FROM payslips
WHERE staff_id = p_staff_id
  AND payroll_month = p_month
  AND payroll_year = p_year;
IF v_duplicate IS NOT NULL THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Payslip already exists for this period';
END IF;
SELECT salary INTO v_basic_salary
FROM staff
WHERE id = p_staff_id
  AND status = 'active';
IF v_basic_salary IS NULL THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Staff not found or inactive';
END IF;
SELECT COALESCE(SUM(amount), 0) INTO v_allowances
FROM staff_allowances
WHERE staff_id = p_staff_id
  AND effective_date <= LAST_DAY(
    STR_TO_DATE(
      CONCAT(p_year, '-', LPAD(p_month, 2, '0'), '-01'),
      '%Y-%m-%d'
    )
  );
SET v_gross_salary = v_basic_salary + v_allowances;
CALL sp_calculate_paye_tax(v_gross_salary, p_year, v_paye_tax);
CALL sp_calculate_nssf_contribution(v_gross_salary, p_year, v_nssf);
CALL sp_calculate_nhif_contribution(v_gross_salary, p_year, v_nhif);
SELECT COALESCE(SUM(agreed_monthly_deduction), 0) INTO v_loan_deduction
FROM staff_loans
WHERE staff_id = p_staff_id
  AND status = 'active';
SELECT COALESCE(SUM(amount), 0) INTO v_other_deductions
FROM staff_deductions
WHERE staff_id = p_staff_id
  AND effective_date <= LAST_DAY(
    STR_TO_DATE(
      CONCAT(p_year, '-', LPAD(p_month, 2, '0'), '-01'),
      '%Y-%m-%d'
    )
  );
SET v_net_salary = v_gross_salary - (
    v_paye_tax + v_nssf + v_nhif + v_loan_deduction + v_other_deductions
  );
INSERT INTO payslips (
    staff_id,
    payroll_month,
    payroll_year,
    basic_salary,
    allowances_total,
    gross_salary,
    paye_tax,
    nssf_contribution,
    nhif_contribution,
    loan_deduction,
    other_deductions_total,
    net_salary,
    payslip_status
  )
VALUES (
    p_staff_id,
    p_month,
    p_year,
    v_basic_salary,
    v_allowances,
    v_gross_salary,
    v_paye_tax,
    v_nssf,
    v_nhif,
    v_loan_deduction,
    v_other_deductions,
    v_net_salary,
    'draft'
  );
INSERT INTO system_events (event_type, event_data)
VALUES (
    'payslip_calculated',
    JSON_OBJECT(
      'staff_id',
      p_staff_id,
      'month',
      p_month,
      'year',
      p_year,
      'net_salary',
      v_net_salary
    )
  );
END$$

DROP PROCEDURE IF EXISTS `sp_calculate_staff_full_payroll`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_calculate_staff_full_payroll` (IN `p_staff_id` INT, IN `p_month` INT, IN `p_year` INT, OUT `p_payroll_id` INT, OUT `p_net_salary` DECIMAL(10,2))   BEGIN
    DECLARE v_basic_salary DECIMAL(10,2);
    DECLARE v_gross_salary DECIMAL(10,2);
    DECLARE v_allowances DECIMAL(10,2);
    DECLARE v_nssf DECIMAL(10,2);
    DECLARE v_nhif DECIMAL(10,2);
    DECLARE v_paye DECIMAL(10,2);
    DECLARE v_total_deductions DECIMAL(10,2);
    DECLARE v_other_deductions DECIMAL(10,2);
    
    SELECT basic_salary INTO v_basic_salary FROM staff WHERE id = p_staff_id;
    
    SELECT COALESCE(SUM(amount), 0) INTO v_allowances
    FROM staff_allowances WHERE staff_id = p_staff_id AND is_active = 1;
    
    SET v_gross_salary = v_basic_salary + v_allowances;
    
    CALL sp_calculate_nssf_contribution(v_gross_salary, v_nssf);
    CALL sp_calculate_nhif_contribution(v_gross_salary, v_nhif);
    CALL sp_calculate_paye_tax(v_gross_salary, v_nssf, v_nhif, v_paye);
    
    SELECT COALESCE(SUM(amount), 0) INTO v_other_deductions
    FROM staff_deductions 
    WHERE staff_id = p_staff_id AND is_active = 1
      AND (end_date IS NULL OR end_date >= CURDATE());
    
    SET v_total_deductions = v_nssf + v_nhif + v_paye + v_other_deductions;
    SET p_net_salary = v_gross_salary - v_total_deductions;
    
    INSERT INTO staff_payroll (
        staff_id, payroll_month, payroll_year, basic_salary, gross_salary,
        nssf_deduction, nhif_deduction, paye_tax, other_deductions,
        total_deductions, net_salary, status, created_at
    ) VALUES (
        p_staff_id, p_month, p_year, v_basic_salary, v_gross_salary,
        v_nssf, v_nhif, v_paye, v_other_deductions,
        v_total_deductions, p_net_salary, 'pending', NOW()
    );
    
    SET p_payroll_id = LAST_INSERT_ID();
END$$

DROP PROCEDURE IF EXISTS `sp_calculate_staff_leave_balance`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_calculate_staff_leave_balance` (IN `p_staff_id` INT, IN `p_leave_type` VARCHAR(20), OUT `p_entitled_days` INT, OUT `p_used_days` INT, OUT `p_available_days` INT)   BEGIN
    DECLARE v_default_days INT DEFAULT 0;
    
    SELECT default_days_per_year INTO v_default_days
    FROM leave_types
    WHERE code = p_leave_type AND is_active = 1;
    
    SET p_entitled_days = IFNULL(v_default_days, 0);
    
    SELECT COALESCE(SUM(days_requested), 0) INTO p_used_days
    FROM staff_leaves
    WHERE staff_id = p_staff_id
      AND leave_type = p_leave_type
      AND status IN ('approved', 'taken')
      AND YEAR(start_date) = YEAR(CURDATE());
    
    SET p_available_days = p_entitled_days - p_used_days;
END$$

DROP PROCEDURE IF EXISTS `sp_calculate_student_fees`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_calculate_student_fees` (IN `p_student_id` INT UNSIGNED, IN `p_academic_year` YEAR, IN `p_term_id` INT UNSIGNED)   BEGIN
SELECT SUM(fsd.amount) as total_fees_due,
  COUNT(fsd.id) as number_of_fees,
  GROUP_CONCAT(ft.name SEPARATOR ', ') as fee_types
FROM fee_structures_detailed fsd
  JOIN fee_types ft ON fsd.fee_type_id = ft.id
  JOIN students s ON TRUE
  JOIN student_types st ON s.student_type_id = st.id
WHERE fsd.level_id = (
    SELECT level_id
    FROM class_streams
    WHERE id = s.stream_id
    LIMIT 1
  )
  AND fsd.academic_year = p_academic_year
  AND fsd.term_id = p_term_id
  AND fsd.student_type_id = s.student_type_id
  AND s.id = p_student_id;
END$$

DROP PROCEDURE IF EXISTS `sp_calculate_term_subject_score`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_calculate_term_subject_score` (IN `p_student_id` INT UNSIGNED, IN `p_term_id` INT UNSIGNED, IN `p_subject_id` INT UNSIGNED)   BEGIN
DECLARE v_formative_total DECIMAL(8, 2);
DECLARE v_formative_max DECIMAL(8, 2);
DECLARE v_formative_count INT;
DECLARE v_formative_pct DECIMAL(5, 2);
DECLARE v_formative_grade VARCHAR(4);
DECLARE v_summative_total DECIMAL(8, 2);
DECLARE v_summative_max DECIMAL(8, 2);
DECLARE v_summative_count INT;
DECLARE v_summative_pct DECIMAL(5, 2);
DECLARE v_summative_grade VARCHAR(4);
DECLARE v_overall_score DECIMAL(8, 2);
DECLARE v_overall_pct DECIMAL(5, 2);
DECLARE v_overall_grade VARCHAR(4);
DECLARE v_overall_points DECIMAL(3, 1);
DECLARE v_total_count INT;
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Error calculating term subject score';
END;
START TRANSACTION;

SELECT COALESCE(SUM(ar.marks_obtained), 0),
  COALESCE(SUM(a.max_marks), 0),
  COUNT(ar.id) INTO v_formative_total,
  v_formative_max,
  v_formative_count
FROM assessment_results ar
  JOIN assessments a ON ar.assessment_id = a.id
  LEFT JOIN assessment_types ast ON a.assessment_type_id = ast.id
WHERE ar.student_id = p_student_id
  AND a.term_id = p_term_id
  AND a.subject_id = p_subject_id
  AND (
    ast.is_formative = 1
    OR a.assessment_type_id IS NULL
  );

IF v_formative_max > 0 THEN
SET v_formative_pct = ROUND((v_formative_total / v_formative_max) * 100, 2);
ELSE
SET v_formative_pct = 0;
END IF;

SET v_formative_grade = calculate_grade(v_formative_pct);

SELECT COALESCE(SUM(ar.marks_obtained), 0),
  COALESCE(SUM(a.max_marks), 0),
  COUNT(ar.id) INTO v_summative_total,
  v_summative_max,
  v_summative_count
FROM assessment_results ar
  JOIN assessments a ON ar.assessment_id = a.id
  LEFT JOIN assessment_types ast ON a.assessment_type_id = ast.id
WHERE ar.student_id = p_student_id
  AND a.term_id = p_term_id
  AND a.subject_id = p_subject_id
  AND ast.is_summative = 1;

IF v_summative_max > 0 THEN
SET v_summative_pct = ROUND((v_summative_total / v_summative_max) * 100, 2);
ELSE
SET v_summative_pct = 0;
END IF;

SET v_summative_grade = calculate_grade(v_summative_pct);

SET v_total_count = v_formative_count + v_summative_count;
IF v_total_count > 0 THEN
SET v_overall_pct = ROUND(
    (v_formative_pct * 0.4) + (v_summative_pct * 0.6),
    2
  );
SET v_overall_score = ROUND(
    (v_formative_total * 0.4) + (v_summative_total * 0.6),
    2
  );
ELSE
SET v_overall_pct = 0;
SET v_overall_score = 0;
END IF;

SET v_overall_grade = calculate_grade(v_overall_pct);
SET v_overall_points = calculate_points(v_overall_pct);

INSERT INTO term_subject_scores (
    student_id,
    term_id,
    subject_id,
    formative_total,
    formative_max,
    formative_percentage,
    formative_grade,
    formative_count,
    summative_total,
    summative_max,
    summative_percentage,
    summative_grade,
    summative_count,
    overall_score,
    overall_percentage,
    overall_grade,
    overall_points,
    assessment_count,
    calculated_at
  )
VALUES (
    p_student_id,
    p_term_id,
    p_subject_id,
    v_formative_total,
    v_formative_max,
    v_formative_pct,
    v_formative_grade,
    v_formative_count,
    v_summative_total,
    v_summative_max,
    v_summative_pct,
    v_summative_grade,
    v_summative_count,
    v_overall_score,
    v_overall_pct,
    v_overall_grade,
    v_overall_points,
    v_total_count,
    NOW()
  ) ON DUPLICATE KEY
UPDATE formative_total = v_formative_total,
  formative_max = v_formative_max,
  formative_percentage = v_formative_pct,
  formative_grade = v_formative_grade,
  formative_count = v_formative_count,
  summative_total = v_summative_total,
  summative_max = v_summative_max,
  summative_percentage = v_summative_pct,
  summative_grade = v_summative_grade,
  summative_count = v_summative_count,
  overall_score = v_overall_score,
  overall_percentage = v_overall_pct,
  overall_grade = v_overall_grade,
  overall_points = v_overall_points,
  assessment_count = v_total_count,
  calculated_at = NOW(),
  updated_at = NOW();
COMMIT;
END$$

DROP PROCEDURE IF EXISTS `sp_carryover_fee_balance`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_carryover_fee_balance` (`p_student_id` INT UNSIGNED, `p_from_year` INT, `p_to_year` INT, OUT `p_result_message` VARCHAR(500))  MODIFIES SQL DATA COMMENT 'Intelligently carryover fee balance between academic years based on prior status' BEGIN
DECLARE p_year_closing_balance DECIMAL(12, 2) DEFAULT 0;
DECLARE p_action_taken VARCHAR(50) DEFAULT 'fresh_bill';
DECLARE p_student_count INT DEFAULT 0;

SELECT COUNT(*) INTO p_student_count
FROM students
WHERE id = p_student_id;
IF p_student_count = 0 THEN
SET p_result_message = CONCAT('ERROR: Student ID ', p_student_id, ' not found');
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = p_result_message;
END IF;

SELECT COALESCE(SUM(balance), 0) INTO p_year_closing_balance
FROM student_fee_obligations
WHERE student_id = p_student_id
  AND academic_year = p_from_year;

IF p_year_closing_balance = 0 THEN 
SET p_action_taken = 'fresh_bill';
SET p_result_message = CONCAT(
    'SUCCESS: Student ',
    p_student_id,
    ' cleared previous year - fresh billing for year ',
    p_to_year
  );
ELSEIF p_year_closing_balance > 0 THEN 
SET p_action_taken = 'add_to_current';

UPDATE student_fee_obligations
SET previous_year_balance = p_year_closing_balance,
  amount_due = amount_due + p_year_closing_balance,
  balance = balance + p_year_closing_balance,
  updated_at = NOW()
WHERE student_id = p_student_id
  AND academic_year = p_to_year;
SET p_result_message = CONCAT(
    'SUCCESS: Added previous year balance of ',
    p_year_closing_balance,
    ' to student ',
    p_student_id,
    ' for year ',
    p_to_year
  );
ELSE 
SET p_action_taken = 'deduct_from_current';

UPDATE student_fee_obligations
SET previous_year_balance = p_year_closing_balance,
  amount_due = GREATEST(0, amount_due + p_year_closing_balance),
  balance = balance + p_year_closing_balance,
  updated_at = NOW()
WHERE student_id = p_student_id
  AND academic_year = p_to_year;
SET p_result_message = CONCAT(
    'SUCCESS: Applied surplus of ',
    ABS(p_year_closing_balance),
    ' against new year fees for student ',
    p_student_id
  );
END IF;

INSERT INTO student_fee_carryover (
    student_id,
    academic_year,
    term_id,
    previous_balance,
    surplus_amount,
    action_taken,
    notes
  )
VALUES (
    p_student_id,
    p_to_year,
    NULL,
    CASE
      WHEN p_year_closing_balance > 0 THEN p_year_closing_balance
      ELSE 0
    END,
    CASE
      WHEN p_year_closing_balance < 0 THEN ABS(p_year_closing_balance)
      ELSE 0
    END,
    p_action_taken,
    CONCAT(
      'Year transition from ',
      p_from_year,
      ' to ',
      p_to_year,
      ' - ',
      p_result_message
    )
  );

INSERT INTO fee_transition_history (
    student_id,
    from_academic_year,
    to_academic_year,
    balance_action,
    amount_transferred,
    previous_balance,
    created_by
  )
VALUES (
    p_student_id,
    p_from_year,
    p_to_year,
    p_action_taken,
    p_year_closing_balance,
    p_year_closing_balance,
    1
  );
END$$

DROP PROCEDURE IF EXISTS `sp_check_form_permission`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_check_form_permission` (IN `p_user_id` INT UNSIGNED, IN `p_form_code` VARCHAR(50), IN `p_action` VARCHAR(50), OUT `p_has_permission` INT)   BEGIN
DECLARE v_role_id INT UNSIGNED;
DECLARE v_allowed_actions JSON;
SELECT role_id INTO v_role_id
FROM users
WHERE id = p_user_id;
SELECT rfp.allowed_actions INTO v_allowed_actions
FROM role_form_permissions rfp
  JOIN form_permissions fp ON rfp.form_permission_id = fp.id
WHERE rfp.role_id = v_role_id
  AND fp.form_code = p_form_code;
IF v_allowed_actions IS NOT NULL THEN IF JSON_CONTAINS(v_allowed_actions, JSON_QUOTE(p_action)) THEN
SET p_has_permission = 1;
ELSE
SET p_has_permission = 0;
END IF;
ELSE
SET p_has_permission = 0;
END IF;
END$$

DROP PROCEDURE IF EXISTS `sp_check_record_permission`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_check_record_permission` (IN `p_user_id` INT UNSIGNED, IN `p_table_name` VARCHAR(100), IN `p_record_id` INT UNSIGNED, IN `p_action` VARCHAR(50), OUT `p_has_permission` INT)   BEGIN
DECLARE v_permission_count INT;
SELECT COUNT(*) INTO v_permission_count
FROM record_permissions
WHERE user_id = p_user_id
  AND table_name = p_table_name
  AND record_id = p_record_id
  AND permission_type = p_action
  AND (
    expiry_date IS NULL
    OR expiry_date > NOW()
  );
SET p_has_permission = CASE
    WHEN v_permission_count > 0 THEN 1
    ELSE 0
  END;
END$$

DROP PROCEDURE IF EXISTS `sp_cleanup_expired_blocks`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cleanup_expired_blocks` ()   BEGIN
    DELETE FROM blocked_ips 
    WHERE expires_at IS NOT NULL 
    AND expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY);
    
    SELECT ROW_COUNT() as deleted_records;
END$$

DROP PROCEDURE IF EXISTS `sp_cleanup_failed_attempts`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cleanup_failed_attempts` ()   BEGIN
    DELETE FROM failed_auth_attempts 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
    
    SELECT ROW_COUNT() as deleted_records;
END$$

DROP PROCEDURE IF EXISTS `sp_compare_to_benchmark`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_compare_to_benchmark` (IN `p_class_id` INT UNSIGNED, IN `p_subject_id` INT UNSIGNED, IN `p_term_id` INT UNSIGNED)   BEGIN
DECLARE v_academic_year YEAR(4);
DECLARE v_grade_level_id INT UNSIGNED;
DECLARE v_class_avg DECIMAL(5, 2);
DECLARE v_benchmark_target DECIMAL(5, 2);
DECLARE v_variance DECIMAL(5, 2);

SELECT at.year,
  c.level_id INTO v_academic_year,
  v_grade_level_id
FROM academic_terms at
  JOIN classes c ON c.id = p_class_id
WHERE at.id = p_term_id;

SELECT AVG(tss.overall_percentage) INTO v_class_avg
FROM term_subject_scores tss
  JOIN students s ON tss.student_id = s.id
WHERE s.class_id = p_class_id
  AND tss.subject_id = p_subject_id
  AND tss.term_id = p_term_id;

SELECT target_percentage INTO v_benchmark_target
FROM assessment_benchmarks
WHERE academic_year = v_academic_year
  AND grade_level_id = v_grade_level_id
  AND subject_id = p_subject_id
  AND benchmark_type = 'grade'
LIMIT 1;
IF v_benchmark_target IS NOT NULL THEN
SET v_variance = ROUND(v_class_avg - v_benchmark_target, 2);
END IF;
SELECT p_class_id AS class_id,
  p_subject_id AS subject_id,
  v_class_avg AS class_average,
  v_benchmark_target AS benchmark_target,
  v_variance AS variance,
  IF(v_variance >= 0, 'Exceeds', 'Below') AS performance_status;
END$$

DROP PROCEDURE IF EXISTS `sp_compile_exam_results`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_compile_exam_results` (IN `p_term_id` INT UNSIGNED, IN `p_exam_type` VARCHAR(50))   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_student_id INT UNSIGNED;
    DECLARE v_subject_id INT UNSIGNED;
    
    
    DECLARE result_cursor CURSOR FOR
        SELECT DISTINCT ar.student_id, a.subject_id
        FROM assessment_results ar
        JOIN assessments a ON ar.assessment_id = a.id
        WHERE a.term_id = p_term_id
        AND a.title LIKE CONCAT('%', p_exam_type, '%')
        AND a.status = 'approved';
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    START TRANSACTION;
    
    OPEN result_cursor;
    
    compile_loop: LOOP
        FETCH result_cursor INTO v_student_id, v_subject_id;
        
        IF done THEN
            LEAVE compile_loop;
        END IF;
        
        
        CALL sp_calculate_term_subject_score(v_student_id, p_term_id, v_subject_id);
    END LOOP;
    
    CLOSE result_cursor;
    
    
    CALL sp_consolidate_term_scores(p_term_id, YEAR(NOW()));
    
    COMMIT;
    
    
    SELECT 
        p_term_id AS term_id,
        p_exam_type AS exam_type,
        COUNT(DISTINCT student_id) AS students_processed,
        COUNT(DISTINCT subject_id) AS subjects_processed
    FROM term_subject_scores
    WHERE term_id = p_term_id;
END$$

DROP PROCEDURE IF EXISTS `sp_complete_maintenance`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_complete_maintenance` (IN `p_maintenance_id` INT UNSIGNED, IN `p_service_provider` VARCHAR(100), IN `p_cost` DECIMAL(10,2), IN `p_description` TEXT, IN `p_findings` TEXT, IN `p_actions_taken` TEXT, IN `p_maintenance_staff_id` INT UNSIGNED, IN `p_next_service_date` DATE)   BEGIN
DECLARE v_error_msg VARCHAR(255);
DECLARE v_equipment_id INT UNSIGNED;
DECLARE v_status VARCHAR(50);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = v_error_msg;
END;
SELECT equipment_id,
  status INTO v_equipment_id,
  v_status
FROM equipment_maintenance
WHERE id = p_maintenance_id;
IF v_equipment_id IS NULL THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Maintenance record not found';
END IF;
INSERT INTO maintenance_logs (
    maintenance_schedule_id,
    equipment_id,
    maintenance_date,
    service_provider,
    cost,
    description,
    findings,
    actions_taken,
    status,
    maintenance_staff_id,
    next_service_date,
    created_at
  )
VALUES (
    p_maintenance_id,
    v_equipment_id,
    CURDATE(),
    p_service_provider,
    p_cost,
    p_description,
    p_findings,
    p_actions_taken,
    'completed',
    p_maintenance_staff_id,
    p_next_service_date,
    NOW()
  );
UPDATE equipment_maintenance
SET status = 'completed',
  last_maintenance_date = CURDATE(),
  next_maintenance_date = COALESCE(
    p_next_service_date,
    DATE_ADD(CURDATE(), INTERVAL 3 MONTH)
  ),
  updated_at = NOW()
WHERE id = p_maintenance_id;
INSERT INTO system_events (event_type, event_data, created_at)
VALUES (
    'maintenance_completed',
    JSON_OBJECT('equipment_id', v_equipment_id, 'cost', p_cost),
    NOW()
  );
END$$

DROP PROCEDURE IF EXISTS `sp_complete_promotion_batch`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_complete_promotion_batch` (IN `p_batch_id` INT UNSIGNED)   BEGIN
DECLARE v_error_msg VARCHAR(255);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Batch completion failed';
END;

UPDATE promotion_batches
SET status = 'completed',
  total_promoted = (
    SELECT COUNT(*)
    FROM student_promotions
    WHERE batch_id = p_batch_id
      AND promotion_status = 'approved'
  ),
  total_rejected = (
    SELECT COUNT(*)
    FROM student_promotions
    WHERE batch_id = p_batch_id
      AND promotion_status = 'rejected'
  ),
  total_pending_approval = (
    SELECT COUNT(*)
    FROM student_promotions
    WHERE batch_id = p_batch_id
      AND promotion_status = 'pending_approval'
  ),
  completed_at = NOW()
WHERE id = p_batch_id;
END$$

DROP PROCEDURE IF EXISTS `sp_consolidate_term_scores`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_consolidate_term_scores` (IN `p_term_id` INT UNSIGNED, IN `p_academic_year` YEAR(4))   BEGIN
DECLARE done INT DEFAULT FALSE;
DECLARE v_student_id INT UNSIGNED;
DECLARE v_class_id INT UNSIGNED;
DECLARE v_total_subjects INT;
DECLARE v_avg_percentage DECIMAL(5, 2);
DECLARE v_avg_grade VARCHAR(4);
DECLARE v_performance_json JSON;
DECLARE v_class_position INT;
DECLARE v_class_total INT;
DECLARE v_percentile DECIMAL(5, 2);
DECLARE v_points_total DECIMAL(5, 1);
DECLARE student_cur CURSOR FOR
SELECT DISTINCT s.id,
  s.class_id
FROM students s
WHERE s.status = 'active';
DECLARE CONTINUE HANDLER FOR NOT FOUND
SET done = TRUE;
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Error consolidating term scores';
END;
START TRANSACTION;
OPEN student_cur;
consolidate_loop:LOOP FETCH student_cur INTO v_student_id,
v_class_id;
IF done THEN LEAVE consolidate_loop;
END IF;

SELECT COUNT(DISTINCT subject_id) INTO v_total_subjects
FROM term_subject_scores
WHERE student_id = v_student_id
  AND term_id = p_term_id;
IF v_total_subjects > 0 THEN 
SELECT ROUND(AVG(overall_percentage), 2),
  (
    SELECT DISTINCT overall_grade
    FROM term_subject_scores
    WHERE student_id = v_student_id
      AND term_id = p_term_id
    ORDER BY overall_percentage DESC
    LIMIT 1
  ) INTO v_avg_percentage,
  v_avg_grade
FROM term_subject_scores
WHERE student_id = v_student_id
  AND term_id = p_term_id;

SELECT JSON_OBJECTAGG(
    (
      SELECT name
      FROM curriculum_units
      WHERE id = tss.subject_id
    ),
    tss.overall_grade
  ) INTO v_performance_json
FROM term_subject_scores tss
WHERE tss.student_id = v_student_id
  AND tss.term_id = p_term_id;

SELECT COALESCE(SUM(overall_points), 0) INTO v_points_total
FROM term_subject_scores
WHERE student_id = v_student_id
  AND term_id = p_term_id;

SELECT COUNT(*) + 1,
  (
    SELECT COUNT(DISTINCT s.id)
    FROM students s
    WHERE s.class_id = v_class_id
      AND s.status = 'active'
  ) INTO v_class_position,
  v_class_total
FROM term_consolidations tc
  JOIN students s ON tc.student_id = s.id
WHERE tc.term_id = p_term_id
  AND s.class_id = v_class_id
  AND tc.avg_overall_percentage > v_avg_percentage;

IF v_class_total > 0 THEN
SET v_percentile = ROUND(
    (
      (v_class_total - v_class_position + 1) / v_class_total
    ) * 100,
    2
  );
ELSE
SET v_percentile = 0;
END IF;

INSERT INTO term_consolidations (
    student_id,
    term_id,
    academic_year,
    total_subjects,
    total_assessed_subjects,
    avg_overall_percentage,
    avg_overall_grade,
    performance_summary,
    class_position,
    class_total,
    percentile,
    points_total,
    consolidated_at
  )
VALUES (
    v_student_id,
    p_term_id,
    p_academic_year,
    v_total_subjects,
    v_total_subjects,
    v_avg_percentage,
    v_avg_grade,
    v_performance_json,
    v_class_position,
    v_class_total,
    v_percentile,
    v_points_total,
    NOW()
  ) ON DUPLICATE KEY
UPDATE total_subjects = v_total_subjects,
  total_assessed_subjects = v_total_subjects,
  avg_overall_percentage = v_avg_percentage,
  avg_overall_grade = v_avg_grade,
  performance_summary = v_performance_json,
  class_position = v_class_position,
  class_total = v_class_total,
  percentile = v_percentile,
  points_total = v_points_total,
  consolidated_at = NOW(),
  updated_at = NOW();
END IF;
END LOOP;
CLOSE student_cur;
COMMIT;
END$$

DROP PROCEDURE IF EXISTS `sp_create_arrears_record`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_create_arrears_record` (IN `p_student_id` INT UNSIGNED, IN `p_academic_year` YEAR, IN `p_term_id` INT UNSIGNED)   BEGIN
DECLARE v_total_arrears DECIMAL(10, 2);

SELECT COALESCE(SUM(balance), 0) INTO v_total_arrears
FROM student_fee_obligations
WHERE student_id = p_student_id
  AND academic_year = p_academic_year
  AND term_id = p_term_id
  AND status IN ('pending', 'partial');
IF v_total_arrears > 0 THEN
INSERT INTO student_arrears (
    student_id,
    academic_year,
    term_id,
    total_arrears,
    arrears_date,
    arrears_status
  )
VALUES (
    p_student_id,
    p_academic_year,
    p_term_id,
    v_total_arrears,
    CURDATE(),
    'current'
  ) ON DUPLICATE KEY
UPDATE total_arrears = v_total_arrears,
  arrears_status = IF(
    DATEDIFF(CURDATE(), arrears_date) > 30,
    'overdue',
    'current'
  );
END IF;
END$$

DROP PROCEDURE IF EXISTS `sp_create_arrears_settlement_plan`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_create_arrears_settlement_plan` (IN `p_student_id` INT UNSIGNED, IN `p_arrears_id` INT UNSIGNED, IN `p_installments` INT, IN `p_first_payment_date` DATE, IN `p_created_by` INT UNSIGNED, IN `p_approved_by` INT UNSIGNED)   BEGIN
DECLARE v_total_amount DECIMAL(10, 2);
DECLARE v_installment_amount DECIMAL(10, 2);
DECLARE v_final_payment_date DATE;

SELECT total_arrears INTO v_total_amount
FROM student_arrears
WHERE id = p_arrears_id;

SET v_installment_amount = ROUND(v_total_amount / p_installments, 2);
SET v_final_payment_date = DATE_ADD(
    p_first_payment_date,
    INTERVAL (p_installments - 1) MONTH
  );

INSERT INTO arrears_settlement_plans (
    student_id,
    arrears_id,
    total_amount,
    installments,
    installment_amount,
    first_payment_date,
    final_payment_date,
    created_by,
    approved_by,
    approved_date,
    status
  )
VALUES (
    p_student_id,
    p_arrears_id,
    v_total_amount,
    p_installments,
    v_installment_amount,
    p_first_payment_date,
    v_final_payment_date,
    p_created_by,
    p_approved_by,
    NOW(),
    'active'
  );

UPDATE student_arrears
SET settlement_plan_id = LAST_INSERT_ID()
WHERE id = p_arrears_id;
END$$

DROP PROCEDURE IF EXISTS `sp_create_exam_schedule`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_create_exam_schedule` (IN `p_term_id` INT UNSIGNED, IN `p_exam_type` VARCHAR(50), IN `p_start_date` DATE, IN `p_end_date` DATE, IN `p_created_by` INT UNSIGNED)   BEGIN
    DECLARE v_exam_id INT UNSIGNED;
    DECLARE v_current_date DATE;
    DECLARE v_time_slot VARCHAR(20);
    DECLARE done INT DEFAULT FALSE;
    
    
    DECLARE v_class_id INT UNSIGNED;
    DECLARE v_subject_id INT UNSIGNED;
    
    
    DECLARE subject_cursor CURSOR FOR
        SELECT DISTINCT c.id AS class_id, cu.id AS subject_id
        FROM classes c
        CROSS JOIN curriculum_units cu
        WHERE cu.grade_level_id = c.level_id
        AND cu.status = 'active'
        ORDER BY c.id, cu.id;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    START TRANSACTION;
    
    SET v_current_date = p_start_date;
    SET v_time_slot = '08:00-11:00'; 
    
    OPEN subject_cursor;
    
    schedule_loop: LOOP
        FETCH subject_cursor INTO v_class_id, v_subject_id;
        
        IF done THEN
            LEAVE schedule_loop;
        END IF;
        
        
        INSERT INTO exam_schedules (
            term_id,
            class_id,
            subject_id,
            exam_type,
            exam_date,
            time_slot,
            duration_minutes,
            created_by,
            status
        ) VALUES (
            p_term_id,
            v_class_id,
            v_subject_id,
            p_exam_type,
            v_current_date,
            v_time_slot,
            120, 
            p_created_by,
            'scheduled'
        );
        
        
        IF v_time_slot = '08:00-11:00' THEN
            SET v_time_slot = '11:30-14:30';
        ELSE
            SET v_time_slot = '08:00-11:00';
            SET v_current_date = DATE_ADD(v_current_date, INTERVAL 1 DAY);
            
            
            WHILE DAYOFWEEK(v_current_date) IN (1, 7) DO
                SET v_current_date = DATE_ADD(v_current_date, INTERVAL 1 DAY);
            END WHILE;
            
            
            IF v_current_date > p_end_date THEN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Insufficient time to schedule all exams within date range';
            END IF;
        END IF;
    END LOOP;
    
    CLOSE subject_cursor;
    COMMIT;
    
    
    SELECT COUNT(*) AS schedules_created
    FROM exam_schedules
    WHERE term_id = p_term_id
    AND exam_type = p_exam_type;
END$$

DROP PROCEDURE IF EXISTS `sp_create_promotion_batch`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_create_promotion_batch` (IN `p_from_year` YEAR, IN `p_to_year` YEAR, IN `p_batch_type` VARCHAR(50), IN `p_batch_scope` VARCHAR(255), IN `p_created_by` INT UNSIGNED, IN `p_notes` TEXT, OUT `p_batch_id` INT UNSIGNED)   BEGIN
DECLARE v_error_msg VARCHAR(255);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = v_error_msg;
END;

INSERT INTO promotion_batches (
    from_academic_year,
    to_academic_year,
    batch_type,
    batch_scope,
    created_by,
    notes,
    status
  )
VALUES (
    p_from_year,
    p_to_year,
    p_batch_type,
    p_batch_scope,
    p_created_by,
    p_notes,
    'pending'
  );
SET p_batch_id = LAST_INSERT_ID();
END$$

DROP PROCEDURE IF EXISTS `sp_create_user_session`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_create_user_session` (IN `p_user_id` INT UNSIGNED, IN `p_session_token` VARCHAR(255), IN `p_ip_address` VARCHAR(45), IN `p_user_agent` VARCHAR(255))   BEGIN
INSERT INTO user_sessions (
    user_id,
    session_token,
    ip_address,
    user_agent,
    login_time,
    last_activity,
    session_status
  )
VALUES (
    p_user_id,
    p_session_token,
    p_ip_address,
    p_user_agent,
    NOW(),
    NOW(),
    'active'
  );
INSERT INTO system_events (event_type, event_data)
VALUES (
    'user_session_created',
    JSON_OBJECT('user_id', p_user_id, 'ip', p_ip_address)
  );
END$$

DROP PROCEDURE IF EXISTS `sp_end_user_session`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_end_user_session` (IN `p_session_token` VARCHAR(255))   BEGIN
DECLARE v_user_id INT UNSIGNED;
SELECT user_id INTO v_user_id
FROM user_sessions
WHERE session_token = p_session_token;
UPDATE user_sessions
SET session_status = 'logged_out',
  logout_time = NOW()
WHERE session_token = p_session_token;
INSERT INTO system_events (event_type, event_data)
VALUES (
    'user_session_ended',
    JSON_OBJECT('user_id', v_user_id)
  );
END$$

DROP PROCEDURE IF EXISTS `sp_ensure_class_streams`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_ensure_class_streams` (IN `p_class_id` INT UNSIGNED)   BEGIN
DECLARE v_stream_count INT;
DECLARE v_active_custom_streams INT;
DECLARE v_class_name VARCHAR(50);
DECLARE v_class_capacity INT;
DECLARE v_teacher_id INT UNSIGNED;
DECLARE v_default_stream_id INT UNSIGNED;
DECLARE v_error_msg VARCHAR(255);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = v_error_msg;
END;

SELECT name,
  capacity,
  teacher_id INTO v_class_name,
  v_class_capacity,
  v_teacher_id
FROM classes
WHERE id = p_class_id;
IF v_class_name IS NULL THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Class not found';
END IF;

SELECT COUNT(*) INTO v_stream_count
FROM class_streams
WHERE class_id = p_class_id;

IF v_stream_count = 0 THEN
INSERT INTO class_streams (
    class_id,
    stream_name,
    capacity,
    teacher_id,
    status
  )
VALUES (
    p_class_id,
    v_class_name,
    v_class_capacity,
    v_teacher_id,
    'active'
  );
END IF;

IF v_stream_count > 0 THEN 
SELECT COUNT(*) INTO v_active_custom_streams
FROM class_streams cs
  INNER JOIN classes c ON cs.class_id = c.id
WHERE cs.class_id = p_class_id
  AND cs.status = 'active'
  AND cs.stream_name != c.name;


IF v_active_custom_streams > 0 THEN
UPDATE class_streams cs
  INNER JOIN classes c ON cs.class_id = c.id
SET cs.status = 'inactive'
WHERE cs.class_id = p_class_id
  AND cs.stream_name = c.name
  AND cs.status = 'active';
END IF;

IF v_active_custom_streams = 0 THEN
UPDATE class_streams cs
  INNER JOIN classes c ON cs.class_id = c.id
SET cs.status = 'active'
WHERE cs.class_id = p_class_id
  AND cs.stream_name = c.name
  AND cs.status = 'inactive';
END IF;
END IF;
END$$

DROP PROCEDURE IF EXISTS `sp_generate_api_token`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_generate_api_token` (IN `p_user_id` INT UNSIGNED, IN `p_token_name` VARCHAR(100), IN `p_scope` JSON, IN `p_days_valid` INT, OUT `p_token_id` INT UNSIGNED)   BEGIN
DECLARE v_token_hash VARCHAR(255);
DECLARE v_expiry_date DATETIME;
SET v_token_hash = SHA2(CONCAT(p_user_id, UUID(), UNIX_TIMESTAMP()), 256);
SET v_expiry_date = CASE
    WHEN p_days_valid > 0 THEN DATE_ADD(NOW(), INTERVAL p_days_valid DAY)
    ELSE NULL
  END;
INSERT INTO api_tokens (
    user_id,
    token_hash,
    token_name,
    scope,
    created_date,
    expiry_date,
    is_active
  )
VALUES (
    p_user_id,
    v_token_hash,
    p_token_name,
    p_scope,
    NOW(),
    v_expiry_date,
    1
  );
SET p_token_id = LAST_INSERT_ID();
INSERT INTO system_events (event_type, event_data)
VALUES (
    'api_token_created',
    JSON_OBJECT('user_id', p_user_id, 'token_id', p_token_id)
  );
END$$

DROP PROCEDURE IF EXISTS `sp_generate_comment`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_generate_comment` (IN `p_grade` VARCHAR(4), OUT `p_comment` VARCHAR(255))   BEGIN
SET p_comment = CASE
    p_grade
    WHEN 'EE1' THEN 'Outstanding performance. Exceeds expectations.'
    WHEN 'EE2' THEN 'Excellent work. Exceeds expectations.'
    WHEN 'ME1' THEN 'Good job. Meets expectations.'
    WHEN 'ME2' THEN 'Satisfactory. Meets expectations.'
    WHEN 'AE1' THEN 'Fair effort. Approaching expectations.'
    WHEN 'AE2' THEN 'Needs improvement. Approaching expectations.'
    WHEN 'BE1' THEN 'Below expectations. Significant improvement needed.'
    WHEN 'BE2' THEN 'Far below expectations. Urgent intervention required.'
    ELSE 'No comment.'
  END;
END$$

DROP PROCEDURE IF EXISTS `sp_generate_p9_form`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_generate_p9_form` (IN `p_staff_id` INT, IN `p_year` INT)   BEGIN
    DECLARE v_staff_exists INT DEFAULT 0;
    
    
    SELECT COUNT(*) INTO v_staff_exists FROM staff WHERE id = p_staff_id;
    
    IF v_staff_exists = 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Staff member not found';
    END IF;
    
    
    SELECT 
        p_staff_id AS staff_id,
        p_year AS tax_year,
        s.staff_no AS staff_number,
        s.kra_pin AS pin_number,
        CONCAT(s.first_name, ' ', s.last_name) AS full_name,
        s.email,
        s.phone,
        
        COALESCE(SUM(ps.basic_salary), 0) AS total_basic_salary,
        COALESCE(SUM(ps.allowances_total), 0) AS total_allowances,
        COALESCE(SUM(ps.gross_salary), 0) AS total_gross_salary,
        COALESCE(SUM(ps.nssf_contribution), 0) AS total_nssf,
        COALESCE(SUM(ps.nhif_contribution), 0) AS total_nhif,
        COALESCE(SUM(ps.paye_tax), 0) AS total_paye_tax,
        COALESCE(SUM(ps.other_deductions_total), 0) AS total_other_deductions,
        COALESCE(SUM(ps.net_salary), 0) AS total_net_salary,
        
        2400 * 12 AS annual_personal_relief,
        
        COALESCE(SUM(ps.gross_salary), 0) - COALESCE(SUM(ps.nssf_contribution), 0) AS taxable_income,
        
        COALESCE(SUM(ps.paye_tax), 0) + (2400 * 12) AS tax_before_relief,
        COUNT(ps.id) AS months_paid,
        MIN(ps.payment_date) AS first_payment_date,
        MAX(ps.payment_date) AS last_payment_date
    FROM staff s
    LEFT JOIN payslips ps ON s.id = ps.staff_id 
        AND ps.payroll_year = p_year 
        AND ps.payslip_status = 'paid'
    WHERE s.id = p_staff_id
    GROUP BY s.id;
    
    
    SELECT 
        ps.payroll_month AS month_number,
        DATE_FORMAT(CONCAT(ps.payroll_year, '-', LPAD(ps.payroll_month, 2, '0'), '-01'), '%B') AS month_name,
        ps.basic_salary,
        ps.allowances_total AS housing_allowance,
        0 AS commuter_allowance, 
        ps.gross_salary,
        ps.nssf_contribution AS nssf_employee,
        ps.nhif_contribution,
        ps.paye_tax,
        2400 AS personal_relief,
        ps.net_salary,
        ps.payment_date
    FROM payslips ps
    WHERE ps.staff_id = p_staff_id 
        AND ps.payroll_year = p_year
        AND ps.payslip_status = 'paid'
    ORDER BY ps.payroll_month;
    
END$$

DROP PROCEDURE IF EXISTS `sp_generate_performance_rating`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_generate_performance_rating` (IN `p_staff_id` INT UNSIGNED, IN `p_rating_period` VARCHAR(20), IN `p_supervisor_id` INT UNSIGNED, IN `p_supervisor_rating` VARCHAR(20), IN `p_comments` TEXT)   BEGIN
DECLARE v_achievement_score DECIMAL(5, 2);
DECLARE v_overall_rating VARCHAR(20);
DECLARE v_academic_year INT;
SET v_academic_year = YEAR(CURDATE());
CALL sp_calculate_kpi_achievement_score(p_staff_id, v_academic_year, v_achievement_score);
SET v_overall_rating = CASE
    WHEN v_achievement_score >= 90 THEN 'Excellent'
    WHEN v_achievement_score >= 75 THEN 'Good'
    WHEN v_achievement_score >= 60 THEN 'Average'
    WHEN v_achievement_score >= 45 THEN 'Below Average'
    ELSE 'Poor'
  END;
INSERT INTO performance_ratings (
    staff_id,
    rating_period,
    overall_rating,
    kpi_achievement_score,
    supervisor_rating,
    supervisor_id,
    rated_date,
    comments
  )
VALUES (
    p_staff_id,
    p_rating_period,
    v_overall_rating,
    v_achievement_score,
    p_supervisor_rating,
    p_supervisor_id,
    CURDATE(),
    p_comments
  );
INSERT INTO system_events (event_type, event_data)
VALUES (
    'performance_rating_created',
    JSON_OBJECT(
      'staff_id',
      p_staff_id,
      'rating',
      v_overall_rating,
      'score',
      v_achievement_score
    )
  );
END$$

DROP PROCEDURE IF EXISTS `sp_generate_school_year_report`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_generate_school_year_report` (IN `p_student_id` INT UNSIGNED, IN `p_academic_year` YEAR)   BEGIN
DECLARE v_attendance_percentage DECIMAL(5, 2);
DECLARE v_total_classes INT;
DECLARE v_classes_present INT;

SELECT ROUND(
    SUM(
      CASE
        WHEN status = 'present' THEN 1
        ELSE 0
      END
    ) * 100 / COUNT(*),
    2
  ),
  COUNT(*),
  SUM(
    CASE
      WHEN status = 'present' THEN 1
      ELSE 0
    END
  ) INTO v_attendance_percentage,
  v_total_classes,
  v_classes_present
FROM student_attendance
WHERE student_id = p_student_id
  AND YEAR(attendance_date) = p_academic_year;

SELECT 'School Year Report' as report_type,
  s.admission_no,
  CONCAT(s.first_name, ' ', s.last_name) as student_name,
  s.date_of_birth,
  s.gender,
  cs.class_name as current_class,
  p_academic_year as academic_year,
  v_attendance_percentage as attendance_percentage,
  v_total_classes as total_school_days,
  v_classes_present as days_present,
  (
    SELECT COUNT(*)
    FROM learner_competencies
    WHERE student_id = p_student_id
      AND academic_year = p_academic_year
  ) as competencies_assessed,
  (
    SELECT COUNT(*)
    FROM learner_values_acquisition
    WHERE student_id = p_student_id
      AND academic_year = p_academic_year
  ) as values_demonstrated,
  (
    SELECT COALESCE(SUM(hours_contributed), 0)
    FROM learner_csl_participation
    WHERE student_id = p_student_id
      AND academic_year = p_academic_year
  ) as csl_hours,
  (
    SELECT conduct_rating
    FROM conduct_tracking
    WHERE student_id = p_student_id
      AND academic_year = p_academic_year
    LIMIT 1
  ) as conduct_rating
FROM students s
  JOIN class_streams cs ON s.stream_id = cs.id
WHERE s.id = p_student_id;
END$$

DROP PROCEDURE IF EXISTS `sp_generate_student_report`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_generate_student_report` (IN `p_student_id` INT UNSIGNED, IN `p_term_id` INT UNSIGNED)   BEGIN
  
  SELECT
    s.admission_number,
    s.first_name,
    s.last_name,
    gl.name AS grade_level,
    c.name AS class_name,
    at.name AS academic_term,
    
    COUNT(DISTINCT ca.id) AS total_assessments,
    AVG(ca.overall_score) AS average_score,
    
    COUNT(DISTINCT sa.id) AS total_school_days,
    COUNT(DISTINCT CASE WHEN sa.status = 'present' THEN sa.id END) AS days_present,
    
    tr.name AS transport_route,
    rs.name AS pickup_point,
    
    calculate_term_fees(p_student_id, p_term_id) AS term_fees,
    COALESCE(SUM(fp.amount), 0) AS fees_paid,
    calculate_term_fees(p_student_id, p_term_id) - COALESCE(SUM(fp.amount), 0) AS balance
  FROM students s
  JOIN grade_levels gl ON s.grade_level_id = gl.id
  JOIN classes c ON s.class_id = c.id
  JOIN academic_terms at ON at.id = p_term_id
  LEFT JOIN cbc_assessments ca ON s.id = ca.student_id AND ca.term_id = p_term_id
  LEFT JOIN student_attendance sa ON s.id = sa.student_id AND sa.term_id = p_term_id
  LEFT JOIN student_transport st ON s.id = st.student_id AND st.academic_term_id = p_term_id
  LEFT JOIN transport_routes tr ON st.route_id = tr.id
  LEFT JOIN route_stops rs ON st.stop_id = rs.id
  LEFT JOIN student_fee_balances sfb ON s.id = sfb.student_id AND sfb.academic_term_id = p_term_id
  LEFT JOIN fee_payments fp ON sfb.id = fp.fee_balance_id
  WHERE s.id = p_student_id
  GROUP BY s.id;
END$$

DROP PROCEDURE IF EXISTS `sp_get_assessment_trends`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_assessment_trends` (IN `p_student_id` INT UNSIGNED, IN `p_subject_id` INT UNSIGNED)   BEGIN
SELECT at.year,
  at.term_number,
  at.name AS term_name,
  cu.name AS subject_name,
  tss.overall_percentage,
  tss.overall_grade,
  tss.formative_percentage,
  tss.summative_percentage,
  tss.assessment_count
FROM term_subject_scores tss
  JOIN academic_terms at ON tss.term_id = at.id
  JOIN curriculum_units cu ON tss.subject_id = cu.id
WHERE tss.student_id = p_student_id
  AND (
    p_subject_id = 0
    OR tss.subject_id = p_subject_id
  )
ORDER BY at.year DESC,
  at.term_number DESC;
END$$

DROP PROCEDURE IF EXISTS `sp_get_class_fee_schedule`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_class_fee_schedule` (IN `p_level_id` INT UNSIGNED, IN `p_academic_year` YEAR, IN `p_term_id` INT UNSIGNED)   BEGIN
SELECT st.code as student_type,
  st.name as student_type_name,
  ft.name as fee_name,
  ft.code as fee_code,
  fsd.amount,
  fsd.due_date
FROM fee_structures_detailed fsd
  JOIN student_types st ON fsd.student_type_id = st.id
  JOIN fee_types ft ON fsd.fee_type_id = ft.id
WHERE fsd.level_id = p_level_id
  AND fsd.academic_year = p_academic_year
  AND fsd.term_id = p_term_id
ORDER BY st.code,
  ft.name;
END$$

DROP PROCEDURE IF EXISTS `sp_get_competency_report`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_competency_report` (IN `p_student_id` INT UNSIGNED, IN `p_academic_year` YEAR)   BEGIN
SELECT cc.code,
  cc.name,
  lc.term_id,
  plc.level,
  plc.name as performance_level,
  lc.evidence,
  lc.teacher_notes,
  lc.assessed_date
FROM learner_competencies lc
  JOIN core_competencies cc ON lc.competency_id = cc.id
  LEFT JOIN performance_levels_cbc plc ON lc.performance_level_id = plc.id
WHERE lc.student_id = p_student_id
  AND lc.academic_year = p_academic_year
ORDER BY cc.sort_order,
  lc.term_id;
END$$

DROP PROCEDURE IF EXISTS `sp_get_fee_breakdown_for_review`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_fee_breakdown_for_review` (IN `p_academic_year` YEAR, IN `p_level_id` INT)   BEGIN
    SELECT 
        ft.name as fee_type,
        ft.category,
        at.term_number,
        at.name as term_name,
        fsd.amount,
        fsd.status,
        fsd.is_auto_rollover,
        fsd.reviewed_by,
        u_reviewer.username as reviewer_name,
        fsd.reviewed_at,
        fsd.approved_by,
        u_approver.username as approver_name,
        fsd.approved_at,
        prev_fsd.amount as previous_year_amount,
        (fsd.amount - COALESCE(prev_fsd.amount, 0)) as amount_change,
        CASE 
            WHEN prev_fsd.amount IS NULL THEN NULL
            WHEN prev_fsd.amount = 0 THEN NULL
            ELSE ROUND(((fsd.amount - prev_fsd.amount) / prev_fsd.amount) * 100, 2)
        END as percent_change
    FROM fee_structures_detailed fsd
    JOIN fee_types ft ON fsd.fee_type_id = ft.id
    JOIN academic_terms at ON fsd.term_id = at.id
    LEFT JOIN users u_reviewer ON fsd.reviewed_by = u_reviewer.id
    LEFT JOIN users u_approver ON fsd.approved_by = u_approver.id
    LEFT JOIN fee_structures_detailed prev_fsd ON fsd.copied_from_id = prev_fsd.id
    WHERE fsd.academic_year = p_academic_year
    AND fsd.level_id = p_level_id
    ORDER BY ft.category, ft.name, at.term_number;
END$$

DROP PROCEDURE IF EXISTS `sp_get_fee_collection_rate`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_fee_collection_rate` (IN `p_academic_year` YEAR, IN `p_term_id` INT UNSIGNED)   BEGIN
SELECT sl.name as level,
  COUNT(DISTINCT sfo.student_id) as total_students,
  SUM(sfo.amount_due) as total_fees_due,
  SUM(sfo.amount_paid) as total_fees_paid,
  SUM(sfo.amount_waived) as total_fees_waived,
  ROUND(
    SUM(sfo.amount_paid) / SUM(sfo.amount_due) * 100,
    2
  ) as collection_rate_percent,
  COUNT(
    DISTINCT CASE
      WHEN sfo.status = 'paid' THEN sfo.student_id
    END
  ) as students_fully_paid,
  COUNT(
    DISTINCT CASE
      WHEN sfo.status = 'partial' THEN sfo.student_id
    END
  ) as students_partial_payment,
  COUNT(
    DISTINCT CASE
      WHEN sfo.status = 'pending' THEN sfo.student_id
    END
  ) as students_no_payment
FROM student_fee_obligations sfo
  JOIN students s ON sfo.student_id = s.id
  JOIN class_streams cs ON s.stream_id = cs.id
  JOIN school_levels sl ON cs.level_id = sl.id
WHERE sfo.academic_year = p_academic_year
  AND sfo.term_id = p_term_id
GROUP BY sl.id
ORDER BY sl.id;
END$$

DROP PROCEDURE IF EXISTS `sp_get_outstanding_fees_report`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_outstanding_fees_report` (IN `p_level_id` INT UNSIGNED, IN `p_academic_year` YEAR, IN `p_term_id` INT UNSIGNED)   BEGIN
SELECT s.admission_no,
  CONCAT(s.first_name, ' ', s.last_name) as student_name,
  st.name as student_type,
  COUNT(sfo.id) as number_of_pending_fees,
  COALESCE(SUM(sfo.balance), 0) as total_outstanding,
  COALESCE(SUM(sfo.amount_paid), 0) as total_paid,
  COALESCE(SUM(sfo.amount_waived), 0) as total_waived,
  sa.arrears_status,
  sa.days_overdue
FROM students s
  JOIN class_streams cs ON s.stream_id = cs.id
  JOIN student_types st ON s.student_type_id = st.id
  LEFT JOIN student_fee_obligations sfo ON s.id = sfo.student_id
  AND sfo.academic_year = p_academic_year
  AND sfo.term_id = p_term_id
  AND sfo.status IN ('pending', 'partial')
  LEFT JOIN student_arrears sa ON s.id = sa.student_id
  AND sa.academic_year = p_academic_year
  AND sa.term_id = p_term_id
WHERE cs.level_id = p_level_id
GROUP BY s.id
HAVING total_outstanding > 0
ORDER BY total_outstanding DESC;
END$$

DROP PROCEDURE IF EXISTS `sp_get_payments_by_admission`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_payments_by_admission` (IN `p_admission_number` VARCHAR(50), IN `p_limit` INT)   BEGIN
    SELECT 
        payment_source,
        source_id,
        reference_code,
        student_id,
        admission_number,
        student_name,
        amount,
        transaction_date,
        contact,
        status,
        created_at
    FROM vw_payment_tracking
    WHERE admission_number = p_admission_number
    ORDER BY transaction_date DESC
    LIMIT p_limit;
END$$

DROP PROCEDURE IF EXISTS `sp_get_staff_kpi_summary`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_staff_kpi_summary` (IN `p_staff_id` INT UNSIGNED, IN `p_academic_year` INT)   BEGIN
SELECT kt.id,
  kd.kpi_code,
  kd.kpi_name,
  kd.measurement_unit,
  kt.target_value,
  kt.weight_percentage,
  COUNT(ka.id) AS achievement_count,
  AVG(ka.achieved_value) AS average_achievement,
  CASE
    WHEN kt.target_value > 0 THEN ROUND(
      (AVG(ka.achieved_value) / kt.target_value) * 100,
      2
    )
    ELSE 0
  END AS achievement_percentage
FROM kpi_targets kt
  JOIN kpi_definitions kd ON kt.kpi_definition_id = kd.id
  LEFT JOIN kpi_achievements ka ON kt.staff_id = ka.staff_id
  AND ka.kpi_definition_id = kd.id
  AND ka.academic_year = kt.academic_year
WHERE kt.staff_id = p_staff_id
  AND kt.academic_year = p_academic_year
  AND kt.is_active = 1
GROUP BY kt.id,
  kd.id;
END$$

DROP PROCEDURE IF EXISTS `sp_get_values_summary`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_values_summary` (IN `p_student_id` INT UNSIGNED, IN `p_academic_year` YEAR)   BEGIN
SELECT cv.code,
  cv.name,
  COUNT(*) as demonstration_count,
  GROUP_CONCAT(
    CONCAT(
      '[',
      DATE_FORMAT(lva.incident_date, '%Y-%m-%d'),
      '] ',
      lva.evidence
    ) SEPARATOR '; '
  ) as evidences,
  MAX(lva.incident_date) as last_demonstrated
FROM learner_values_acquisition lva
  JOIN core_values cv ON lva.value_id = cv.id
WHERE lva.student_id = p_student_id
  AND lva.academic_year = p_academic_year
GROUP BY cv.id,
  cv.code,
  cv.name
ORDER BY cv.sort_order;
END$$

DROP PROCEDURE IF EXISTS `sp_implement_discipline_action`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_implement_discipline_action` (IN `p_case_id` INT UNSIGNED, IN `p_action_type` ENUM('warning','detention','suspension','expulsion','counseling'), IN `p_action_details` JSON, IN `p_implemented_by` INT UNSIGNED)   BEGIN
    DECLARE v_student_id INT UNSIGNED;
    
    START TRANSACTION;
    
    
    SELECT student_id INTO v_student_id
    FROM student_discipline
    WHERE id = p_case_id;
    
    
    CASE p_action_type
        WHEN 'suspension' THEN
            
            UPDATE students
            SET status = 'suspended',
                suspension_start = JSON_UNQUOTE(JSON_EXTRACT(p_action_details, '$.start_date')),
                suspension_end = JSON_UNQUOTE(JSON_EXTRACT(p_action_details, '$.end_date'))
            WHERE id = v_student_id;
            
        WHEN 'expulsion' THEN
            
            UPDATE students
            SET status = 'expelled',
                exit_date = CURDATE(),
                exit_reason = 'Disciplinary expulsion'
            WHERE id = v_student_id;
            
        WHEN 'counseling' THEN
            
            INSERT INTO counseling_sessions (
                student_id,
                session_date,
                counselor_id,
                session_type,
                status
            ) VALUES (
                v_student_id,
                JSON_UNQUOTE(JSON_EXTRACT(p_action_details, '$.first_session_date')),
                JSON_UNQUOTE(JSON_EXTRACT(p_action_details, '$.counselor_id')),
                'disciplinary',
                'scheduled'
            );
    END CASE;
    
    
    UPDATE student_discipline
    SET action_taken = p_action_type,
        action_details = p_action_details,
        action_date = NOW(),
        implemented_by = p_implemented_by,
        status = 'action_implemented'
    WHERE id = p_case_id;
    
    
    CALL sp_send_sms_to_parents(
        JSON_ARRAY(v_student_id),
        CONCAT('Discipline action: ', p_action_type, '. Details sent via email.'),
        p_implemented_by
    );
    
    COMMIT;
END$$

DROP PROCEDURE IF EXISTS `sp_issue_allocation`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_issue_allocation` (IN `p_allocation_id` INT UNSIGNED, IN `p_issued_by` INT UNSIGNED)   BEGIN
DECLARE v_error_msg VARCHAR(255);
DECLARE v_item_id INT UNSIGNED;
DECLARE v_allocated_quantity INT;
DECLARE v_status VARCHAR(50);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = v_error_msg;
END;
SELECT status,
  item_id,
  allocated_quantity INTO v_status,
  v_item_id,
  v_allocated_quantity
FROM inventory_allocations
WHERE id = p_allocation_id;
IF v_item_id IS NULL THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Allocation not found';
END IF;
IF v_status != 'allocated' THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Allocation cannot be issued (status must be allocated)';
END IF;
UPDATE inventory_allocations
SET status = 'issued',
  issued_by = p_issued_by,
  issued_at = NOW(),
  updated_at = NOW()
WHERE id = p_allocation_id;
INSERT INTO inventory_transactions (
    item_id,
    transaction_type,
    quantity,
    transaction_date,
    created_at,
    reference_type,
    reference_id,
    notes
  )
VALUES (
    v_item_id,
    'out',
    v_allocated_quantity,
    CURDATE(),
    NOW(),
    'allocation',
    p_allocation_id,
    'Allocation issued'
  );
INSERT INTO system_events (event_type, event_data, created_at)
VALUES (
    'allocation_issued',
    JSON_OBJECT('allocation_id', p_allocation_id),
    NOW()
  );
END$$

DROP PROCEDURE IF EXISTS `sp_moderate_marks`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_moderate_marks` (IN `p_assessment_id` INT UNSIGNED, IN `p_moderated_by` INT UNSIGNED)   BEGIN
    DECLARE v_class_avg DECIMAL(5,2);
    DECLARE v_expected_avg DECIMAL(5,2) DEFAULT 65.00; 
    DECLARE v_adjustment_factor DECIMAL(5,4);
    DECLARE v_max_marks DECIMAL(6,2);
    
    START TRANSACTION;
    
    
    SELECT max_marks INTO v_max_marks
    FROM assessments
    WHERE id = p_assessment_id;
    
    
    SELECT AVG(marks_obtained) INTO v_class_avg
    FROM assessment_results
    WHERE assessment_id = p_assessment_id;
    
    
    IF v_class_avg < 50 OR v_class_avg > 80 THEN
        SET v_adjustment_factor = v_expected_avg / v_class_avg;
        
        
        IF v_adjustment_factor > 1.15 THEN
            SET v_adjustment_factor = 1.15; 
        ELSEIF v_adjustment_factor < 0.85 THEN
            SET v_adjustment_factor = 0.85; 
        END IF;
        
        
        UPDATE assessment_results
        SET marks_obtained = LEAST(
                ROUND(marks_obtained * v_adjustment_factor, 2),
                v_max_marks
            ),
            grade = calculate_grade(LEAST(
                ROUND((marks_obtained * v_adjustment_factor / v_max_marks) * 100, 2),
                100.00
            )),
            points = calculate_points(LEAST(
                ROUND((marks_obtained * v_adjustment_factor / v_max_marks) * 100, 2),
                100.00
            ))
        WHERE assessment_id = p_assessment_id;
        
        
        INSERT INTO assessment_history (
            assessment_result_id,
            student_id,
            assessment_id,
            old_marks,
            new_marks,
            change_reason,
            changed_by
        )
        SELECT 
            id,
            student_id,
            assessment_id,
            marks_obtained / v_adjustment_factor,
            marks_obtained,
            CONCAT('Moderation applied: factor ', v_adjustment_factor),
            p_moderated_by
        FROM assessment_results
        WHERE assessment_id = p_assessment_id;
    END IF;
    
    
    UPDATE assessments
    SET status = 'approved',
        approved_by = p_moderated_by,
        approved_at = NOW()
    WHERE id = p_assessment_id;
    
    COMMIT;
    
    
    SELECT 
        v_class_avg AS original_average,
        AVG(marks_obtained) AS moderated_average,
        v_adjustment_factor AS adjustment_factor,
        COUNT(*) AS students_moderated
    FROM assessment_results
    WHERE assessment_id = p_assessment_id;
END$$

DROP PROCEDURE IF EXISTS `sp_process_monthly_payroll`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_process_monthly_payroll` (IN `p_month` INT, IN `p_year` INT)   BEGIN
DECLARE done INT DEFAULT FALSE;
DECLARE v_staff_id INT UNSIGNED;
DECLARE v_success_count INT DEFAULT 0;
DECLARE v_error_count INT DEFAULT 0;
DECLARE staff_cursor CURSOR FOR
SELECT id
FROM staff
WHERE status = 'active';
DECLARE CONTINUE HANDLER FOR NOT FOUND
SET done = TRUE;
OPEN staff_cursor;
staff_loop: LOOP FETCH staff_cursor INTO v_staff_id;
IF done THEN LEAVE staff_loop;
END IF;
BEGIN
DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN
SET v_error_count = v_error_count + 1;
END;
CALL sp_calculate_payroll_for_staff(v_staff_id, p_month, p_year);
SET v_success_count = v_success_count + 1;
END;
END LOOP;
CLOSE staff_cursor;
INSERT INTO system_events (event_type, event_data)
VALUES (
    'payroll_processing_complete',
    JSON_OBJECT(
      'month',
      p_month,
      'year',
      p_year,
      'success',
      v_success_count,
      'errors',
      v_error_count
    )
  );
END$$

DROP PROCEDURE IF EXISTS `sp_process_requisition`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_process_requisition` (IN `p_requisition_id` INT UNSIGNED, IN `p_action` VARCHAR(50), IN `p_approved_by` INT UNSIGNED, IN `p_rejection_reason` TEXT)   BEGIN
    DECLARE v_error_msg VARCHAR(255);
    DECLARE v_current_status VARCHAR(50);
    DECLARE v_requisition_number VARCHAR(50);

    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_error_msg;
    END;

    
    SELECT status, requisition_number
    INTO v_current_status, v_requisition_number
    FROM inventory_requisitions
    WHERE id = p_requisition_id;

    IF v_requisition_number IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Requisition not found';
    END IF;

    
    CASE p_action
        WHEN 'submit' THEN
            UPDATE inventory_requisitions
            SET status = 'pending_approval',
                updated_at = NOW()
            WHERE id = p_requisition_id
              AND status = 'draft';

        WHEN 'approve' THEN
            IF p_approved_by IS NULL THEN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Approver ID required';
            END IF;

            UPDATE inventory_requisitions
            SET status = 'approved',
                approved_by = p_approved_by,
                approved_at = NOW(),
                updated_at = NOW()
            WHERE id = p_requisition_id
              AND status IN ('draft', 'pending_approval');

        WHEN 'reject' THEN
            IF p_rejection_reason IS NULL THEN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Rejection reason required';
            END IF;

            UPDATE inventory_requisitions
            SET status = 'rejected',
                rejection_reason = p_rejection_reason,
                approved_by = p_approved_by,
                approved_at = NOW(),
                updated_at = NOW()
            WHERE id = p_requisition_id
              AND status IN ('draft', 'pending_approval');

        WHEN 'cancel' THEN
            UPDATE inventory_requisitions
            SET status = 'cancelled',
                updated_at = NOW()
            WHERE id = p_requisition_id
              AND status != 'fulfilled';

        ELSE
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Invalid action';
    END CASE;

    
    INSERT INTO system_events (event_type, event_data, created_at)
    VALUES (
        CONCAT('requisition_', p_action),
        JSON_OBJECT(
            'requisition_id', p_requisition_id,
            'action', p_action
        ),
        NOW()
    );
END$$

DROP PROCEDURE IF EXISTS `sp_process_staff_payroll`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_process_staff_payroll` (IN `p_month` INT, IN `p_year` INT)   BEGIN
DECLARE done INT DEFAULT FALSE;
DECLARE v_staff_id INT UNSIGNED;
DECLARE v_basic_salary DECIMAL(10, 2);
DECLARE v_allowances DECIMAL(10, 2);
DECLARE v_deductions DECIMAL(10, 2);

DECLARE staff_cur CURSOR FOR
SELECT id,
  basic_salary
FROM staff
WHERE status = 'active';
DECLARE CONTINUE HANDLER FOR NOT FOUND
SET done = TRUE;
START TRANSACTION;
OPEN staff_cur;
read_loop: LOOP FETCH staff_cur INTO v_staff_id,
v_basic_salary;
IF done THEN LEAVE read_loop;
END IF;

SELECT COALESCE(SUM(amount), 0) INTO v_allowances
FROM staff_allowances
WHERE staff_id = v_staff_id
  AND MONTH(effective_date) = p_month
  AND YEAR(effective_date) = p_year;

SELECT COALESCE(SUM(amount), 0) INTO v_deductions
FROM staff_deductions
WHERE staff_id = v_staff_id
  AND MONTH(effective_date) = p_month
  AND YEAR(effective_date) = p_year;

INSERT INTO staff_payrolls (
    staff_id,
    payroll_month,
    payroll_year,
    basic_salary,
    allowances,
    deductions,
    net_pay,
    created_at
  )
VALUES (
    v_staff_id,
    p_month,
    p_year,
    v_basic_salary,
    v_allowances,
    v_deductions,
    v_basic_salary + v_allowances - v_deductions,
    NOW()
  );
END LOOP;
CLOSE staff_cur;
COMMIT;
END$$

DROP PROCEDURE IF EXISTS `sp_process_staff_performance_review`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_process_staff_performance_review` (IN `p_review_id` INT, OUT `p_overall_score` DECIMAL(5,2), OUT `p_performance_grade` CHAR(1))   BEGIN
    DECLARE v_weighted_sum DECIMAL(10,2) DEFAULT 0;
    DECLARE v_total_weight DECIMAL(10,2) DEFAULT 0;
    
    SELECT 
        SUM(score * weight) / 100,
        SUM(weight)
    INTO v_weighted_sum, v_total_weight
    FROM performance_review_kpis
    WHERE review_id = p_review_id AND status = 'completed';
    
    SET p_overall_score = IFNULL(v_weighted_sum, 0);
    
    SET p_performance_grade = CASE
        WHEN p_overall_score >= 90 THEN 'A'
        WHEN p_overall_score >= 80 THEN 'B'
        WHEN p_overall_score >= 70 THEN 'C'
        WHEN p_overall_score >= 60 THEN 'D'
        ELSE 'E'
    END;
    
    UPDATE staff_performance_reviews
    SET overall_score = p_overall_score,
        performance_grade = p_performance_grade
    WHERE id = p_review_id;
END$$

DROP PROCEDURE IF EXISTS `sp_process_student_payment`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_process_student_payment` (IN `p_student_id` INT UNSIGNED, IN `p_parent_id` INT UNSIGNED, IN `p_amount_paid` DECIMAL(10,2), IN `p_payment_method` VARCHAR(50), IN `p_reference_no` VARCHAR(100), IN `p_receipt_no` VARCHAR(50), IN `p_received_by` INT UNSIGNED, IN `p_payment_date` DATETIME, IN `p_notes` TEXT)   BEGIN
  DECLARE v_payment_id INT UNSIGNED;
  DECLARE v_obligation_id INT UNSIGNED;
  DECLARE v_remaining_amount DECIMAL(10, 2);
  DECLARE v_academic_year YEAR(4);
  DECLARE v_term_id INT UNSIGNED;
  DECLARE v_current_year_code VARCHAR(20);
  
  DECLARE EXIT HANDLER FOR SQLEXCEPTION 
  BEGIN 
    ROLLBACK;
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Payment processing failed';
  END;

  START TRANSACTION;

  
  SELECT year_code, id INTO v_current_year_code, v_academic_year
  FROM academic_years 
  WHERE is_current = 1 
  LIMIT 1;

  
  SELECT id INTO v_term_id
  FROM academic_terms 
  WHERE year = v_current_year_code 
    AND status = 'current'
  ORDER BY term_number 
  LIMIT 1;

  
  IF v_term_id IS NULL THEN
    SELECT id INTO v_term_id
    FROM academic_terms 
    WHERE year = v_current_year_code
    ORDER BY term_number DESC
    LIMIT 1;
  END IF;

  
  INSERT INTO payment_transactions (
    student_id,
    parent_id,
    amount_paid,
    payment_date,
    payment_method,
    reference_no,
    receipt_no,
    received_by,
    status,
    academic_year,
    term_id,
    notes
  )
  VALUES (
    p_student_id,
    p_parent_id,
    p_amount_paid,
    p_payment_date,
    p_payment_method,
    p_reference_no,
    p_receipt_no,
    p_received_by,
    'confirmed',
    v_academic_year,
    v_term_id,
    p_notes
  );

  SET v_payment_id = LAST_INSERT_ID();
  SET v_remaining_amount = p_amount_paid;

  
  BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_balance DECIMAL(10, 2);
    DECLARE v_allocation_amount DECIMAL(10, 2);
    
    DECLARE obligation_cur CURSOR FOR
      SELECT id, balance
      FROM student_fee_obligations
      WHERE student_id = p_student_id
        AND status IN ('pending', 'partial')
      ORDER BY due_date ASC;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN obligation_cur;
    
    read_loop: LOOP
      FETCH obligation_cur INTO v_obligation_id, v_balance;
      
      IF done THEN 
        LEAVE read_loop;
      END IF;
      
      IF v_remaining_amount <= 0 THEN 
        LEAVE read_loop;
      END IF;

      
      SET v_allocation_amount = LEAST(v_remaining_amount, v_balance);

      INSERT INTO payment_allocations_detailed (
        payment_transaction_id,
        student_fee_obligation_id,
        amount_allocated,
        allocated_by
      )
      VALUES (
        v_payment_id,
        v_obligation_id,
        v_allocation_amount,
        p_received_by
      );

      
      UPDATE student_fee_obligations
      SET amount_paid = amount_paid + v_allocation_amount,
          balance = amount_due - (amount_paid + v_allocation_amount),
          status = IF(
            (amount_paid + v_allocation_amount) >= amount_due,
            'paid',
            'partial'
          )
      WHERE id = v_obligation_id;

      SET v_remaining_amount = v_remaining_amount - v_allocation_amount;
    END LOOP;

    CLOSE obligation_cur;
  END;

  
  UPDATE payment_transactions
  SET status = IF(
    v_remaining_amount = 0,
    'confirmed',
    'partial_allocated'
  )
  WHERE id = v_payment_id;

  
  CALL sp_refresh_student_payment_summary(p_student_id, v_academic_year, v_term_id);

  COMMIT;

  
  SELECT 
    v_payment_id as payment_id,
    p_amount_paid as amount_paid,
    v_academic_year as academic_year,
    v_term_id as term_id,
    v_remaining_amount as unallocated_amount;
END$$

DROP PROCEDURE IF EXISTS `sp_process_student_sponsorship`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_process_student_sponsorship` (`p_student_id` INT UNSIGNED, `p_is_sponsored` BOOLEAN, `p_sponsor_type` VARCHAR(20), `p_waiver_percentage` DECIMAL(5,2), `p_sponsor_name` VARCHAR(100), OUT `p_result_message` VARCHAR(500))  MODIFIES SQL DATA COMMENT 'Process student sponsorship: update student record and create/update fee waivers' BEGIN
DECLARE p_student_count INT DEFAULT 0;
DECLARE p_existing_waiver_id INT DEFAULT 0;

SELECT COUNT(*) INTO p_student_count
FROM students
WHERE id = p_student_id;
IF p_student_count = 0 THEN
SET p_result_message = CONCAT('ERROR: Student ID ', p_student_id, ' not found');
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = p_result_message;
END IF;

IF p_waiver_percentage < 0
OR p_waiver_percentage > 100 THEN
SET p_result_message = 'ERROR: Waiver percentage must be between 0 and 100';
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = p_result_message;
END IF;

UPDATE students
SET is_sponsored = p_is_sponsored,
  sponsor_type = CASE
    WHEN p_is_sponsored = TRUE THEN p_sponsor_type
    ELSE NULL
  END,
  sponsor_waiver_percentage = CASE
    WHEN p_is_sponsored = TRUE THEN p_waiver_percentage
    ELSE 0
  END,
  sponsor_name = CASE
    WHEN p_is_sponsored = TRUE THEN p_sponsor_name
    ELSE NULL
  END,
  updated_at = NOW()
WHERE id = p_student_id;

IF p_is_sponsored = TRUE THEN 
SELECT fe.id INTO p_existing_waiver_id
FROM fee_discounts_waivers fe
WHERE fe.student_id = p_student_id
  AND fe.waiver_type = 'sponsorship'
  AND YEAR(fe.created_at) = YEAR(NOW())
LIMIT 1;
IF p_existing_waiver_id IS NOT NULL THEN 
UPDATE fee_discounts_waivers
SET waiver_type = 'sponsorship',
  waiver_percentage = p_waiver_percentage,
  description = CONCAT(
    'Sponsorship by ',
    p_sponsor_name,
    ' - ',
    p_sponsor_type
  ),
  is_active = TRUE,
  updated_at = NOW()
WHERE id = p_existing_waiver_id;
SET p_result_message = CONCAT(
    'SUCCESS: Sponsorship updated for student ',
    p_student_id,
    ' with ',
    p_waiver_percentage,
    '% waiver'
  );
ELSE 
INSERT INTO fee_discounts_waivers (
    student_id,
    waiver_type,
    waiver_percentage,
    description,
    created_by,
    is_active
  )
VALUES (
    p_student_id,
    'sponsorship',
    p_waiver_percentage,
    CONCAT(
      'Sponsorship by ',
      p_sponsor_name,
      ' - ',
      p_sponsor_type
    ),
    1,
    TRUE
  );
SET p_result_message = CONCAT(
    'SUCCESS: Sponsorship activated for student ',
    p_student_id,
    ' with ',
    p_waiver_percentage,
    '% waiver'
  );
END IF;

UPDATE student_fee_obligations
SET is_sponsored = TRUE,
  sponsored_waiver_amount = amount_due * (p_waiver_percentage / 100),
  updated_at = NOW()
WHERE student_id = p_student_id
  AND payment_status != 'paid';
ELSE 
UPDATE fee_discounts_waivers
SET is_active = FALSE,
  updated_at = NOW()
WHERE student_id = p_student_id
  AND waiver_type = 'sponsorship';
UPDATE student_fee_obligations
SET is_sponsored = FALSE,
  sponsored_waiver_amount = 0,
  updated_at = NOW()
WHERE student_id = p_student_id;
SET p_result_message = CONCAT(
    'SUCCESS: Sponsorship deactivated for student ',
    p_student_id
  );
END IF;
END$$

DROP PROCEDURE IF EXISTS `sp_promote_bulk_students`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_promote_bulk_students` (IN `p_batch_id` INT UNSIGNED, IN `p_from_year` YEAR, IN `p_to_year` YEAR, IN `p_student_ids` JSON)   BEGIN
DECLARE v_batch_status VARCHAR(50);
DECLARE v_error_msg VARCHAR(255);
DECLARE v_index INT DEFAULT 0;
DECLARE v_student_count INT;
DECLARE v_student_id INT;
DECLARE v_current_class_id INT;
DECLARE v_current_stream_id INT;
DECLARE v_next_class_id INT;
DECLARE v_next_stream_id INT;
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
UPDATE promotion_batches
SET status = 'cancelled'
WHERE id = p_batch_id;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Bulk student promotion failed';
END;

SELECT status INTO v_batch_status
FROM promotion_batches
WHERE id = p_batch_id;
IF v_batch_status IS NULL THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Promotion batch not found';
END IF;
UPDATE promotion_batches
SET status = 'in_progress'
WHERE id = p_batch_id;

SET v_student_count = JSON_LENGTH(p_student_ids);
WHILE v_index < v_student_count DO
SET v_student_id = JSON_UNQUOTE(
    JSON_EXTRACT(p_student_ids, CONCAT('$[', v_index, ']'))
  );

SELECT s.stream_id,
  cs.class_id INTO v_current_stream_id,
  v_current_class_id
FROM students s
  INNER JOIN class_streams cs ON s.stream_id = cs.id
WHERE s.id = v_student_id;

SELECT c_next.id,
  cs_next.id INTO v_next_class_id,
  v_next_stream_id
FROM classes c
  INNER JOIN school_levels sl ON c.level_id = sl.id
  INNER JOIN classes c_next ON c_next.level_id = (sl.id + 1)
  AND c_next.academic_year = p_to_year
  INNER JOIN class_streams cs ON c.id = cs.class_id
  INNER JOIN class_streams cs_next ON cs_next.class_id = c_next.id
  AND cs_next.stream_name = cs.stream_name
WHERE c.id = v_current_class_id
LIMIT 1;

INSERT INTO student_promotions (
    batch_id,
    student_id,
    current_class_id,
    current_stream_id,
    promoted_to_class_id,
    promoted_to_stream_id,
    from_academic_year,
    to_academic_year,
    from_term_id,
    promotion_status
  )
SELECT p_batch_id,
  v_student_id,
  v_current_class_id,
  v_current_stream_id,
  v_next_class_id,
  v_next_stream_id,
  p_from_year,
  p_to_year,
  at.id,
  'pending_approval'
FROM academic_terms at
WHERE at.term_name = 'Term 3'
  AND at.academic_year = p_from_year ON DUPLICATE KEY
UPDATE promotion_status = 'pending_approval';
SET v_index = v_index + 1;
END WHILE;

INSERT INTO class_promotion_queue (
    batch_id,
    class_id,
    stream_id,
    total_in_class,
    approval_status
  )
SELECT DISTINCT p_batch_id,
  sp.promoted_to_class_id,
  sp.promoted_to_stream_id,
  COUNT(*),
  'pending'
FROM student_promotions sp
WHERE sp.batch_id = p_batch_id
GROUP BY sp.promoted_to_class_id,
  sp.promoted_to_stream_id ON DUPLICATE KEY
UPDATE total_in_class =
VALUES(total_in_class);

UPDATE promotion_batches
SET total_students_processed = (
    SELECT COUNT(*)
    FROM student_promotions
    WHERE batch_id = p_batch_id
  ),
  total_pending_approval = (
    SELECT COUNT(*)
    FROM student_promotions
    WHERE batch_id = p_batch_id
      AND promotion_status = 'pending_approval'
  )
WHERE id = p_batch_id;
END$$

DROP PROCEDURE IF EXISTS `sp_promote_by_grade_bulk`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_promote_by_grade_bulk` (IN `p_batch_id` INT UNSIGNED, IN `p_from_year` YEAR, IN `p_to_year` YEAR, IN `p_from_grade_id` INT UNSIGNED, IN `p_to_grade_id` INT UNSIGNED)   BEGIN
DECLARE v_batch_status VARCHAR(50);
DECLARE v_error_msg VARCHAR(255);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
UPDATE promotion_batches
SET status = 'cancelled'
WHERE id = p_batch_id;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Promotion failed';
END;

SELECT status INTO v_batch_status
FROM promotion_batches
WHERE id = p_batch_id;
IF v_batch_status IS NULL THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Promotion batch not found';
END IF;

UPDATE promotion_batches
SET status = 'in_progress'
WHERE id = p_batch_id;

INSERT INTO student_promotions (
    batch_id,
    student_id,
    current_class_id,
    current_stream_id,
    promoted_to_class_id,
    promoted_to_stream_id,
    from_academic_year,
    to_academic_year,
    from_term_id,
    promotion_status
  )
SELECT p_batch_id,
  s.id,
  c.id,
  cs.id,
  c_next.id,
  cs_next.id,
  p_from_year,
  p_to_year,
  at.id,
  'pending_approval'
FROM students s
  INNER JOIN class_streams cs ON s.stream_id = cs.id
  INNER JOIN classes c ON cs.class_id = c.id
  INNER JOIN school_levels sl ON c.level_id = sl.id
  INNER JOIN classes c_next ON c_next.level_id = p_to_grade_id
  AND c_next.academic_year = p_to_year
  INNER JOIN class_streams cs_next ON cs_next.class_id = c_next.id
  AND cs_next.stream_name = cs.stream_name
  INNER JOIN academic_terms at ON at.term_name = 'Term 3'
  AND at.academic_year = p_from_year
WHERE sl.id = p_from_grade_id
  AND c.academic_year = p_from_year
  AND s.status = 'active'
  AND NOT EXISTS (
    SELECT 1
    FROM student_promotions
    WHERE student_id = s.id
      AND from_academic_year = p_from_year
      AND to_academic_year = p_to_year
  ) ON DUPLICATE KEY
UPDATE promotion_status = 'pending_approval';

INSERT INTO class_promotion_queue (
    batch_id,
    class_id,
    stream_id,
    total_in_class,
    approval_status
  )
SELECT DISTINCT p_batch_id,
  c_next.id,
  cs_next.id,
  COUNT(sp.id),
  'pending'
FROM student_promotions sp
  INNER JOIN classes c_next ON sp.promoted_to_class_id = c_next.id
  INNER JOIN class_streams cs_next ON sp.promoted_to_stream_id = cs_next.id
WHERE sp.batch_id = p_batch_id
GROUP BY c_next.id,
  cs_next.id ON DUPLICATE KEY
UPDATE total_in_class =
VALUES(total_in_class);

UPDATE promotion_batches
SET total_students_processed = (
    SELECT COUNT(*)
    FROM student_promotions
    WHERE batch_id = p_batch_id
  ),
  total_pending_approval = (
    SELECT COUNT(*)
    FROM student_promotions
    WHERE batch_id = p_batch_id
      AND promotion_status = 'pending_approval'
  )
WHERE id = p_batch_id;
END$$

DROP PROCEDURE IF EXISTS `sp_promote_single_class`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_promote_single_class` (IN `p_batch_id` INT UNSIGNED, IN `p_from_year` YEAR, IN `p_to_year` YEAR, IN `p_current_class_id` INT UNSIGNED, IN `p_current_stream_id` INT UNSIGNED)   BEGIN
DECLARE v_batch_status VARCHAR(50);
DECLARE v_error_msg VARCHAR(255);
DECLARE v_next_level_id INT UNSIGNED;
DECLARE v_next_class_id INT UNSIGNED;
DECLARE v_next_stream_id INT UNSIGNED;
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
UPDATE promotion_batches
SET status = 'cancelled'
WHERE id = p_batch_id;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Single class promotion failed';
END;

SELECT status INTO v_batch_status
FROM promotion_batches
WHERE id = p_batch_id;
IF v_batch_status IS NULL THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Promotion batch not found';
END IF;

SELECT sl.id INTO v_next_level_id
FROM classes c
  INNER JOIN school_levels sl ON c.level_id = sl.id
WHERE c.id = p_current_class_id;

SELECT c_next.id INTO v_next_class_id
FROM classes c_next
  INNER JOIN class_streams cs_next ON c_next.id = cs_next.class_id
WHERE c_next.level_id = (v_next_level_id + 1)
  AND c_next.academic_year = p_to_year
  AND cs_next.stream_name = (
    SELECT stream_name
    FROM class_streams
    WHERE id = p_current_stream_id
  )
LIMIT 1;

SELECT id INTO v_next_stream_id
FROM class_streams
WHERE class_id = v_next_class_id
  AND stream_name = (
    SELECT stream_name
    FROM class_streams
    WHERE id = p_current_stream_id
  )
LIMIT 1;
IF v_next_class_id IS NULL THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Next class or stream not found for promotion';
END IF;

IF v_batch_status = 'pending' THEN
UPDATE promotion_batches
SET status = 'in_progress'
WHERE id = p_batch_id;
END IF;

INSERT INTO student_promotions (
    batch_id,
    student_id,
    current_class_id,
    current_stream_id,
    promoted_to_class_id,
    promoted_to_stream_id,
    from_academic_year,
    to_academic_year,
    from_term_id,
    promotion_status
  )
SELECT p_batch_id,
  s.id,
  p_current_class_id,
  p_current_stream_id,
  v_next_class_id,
  v_next_stream_id,
  p_from_year,
  p_to_year,
  at.id,
  'pending_approval'
FROM students s
  INNER JOIN academic_terms at ON at.term_name = 'Term 3'
  AND at.academic_year = p_from_year
WHERE s.stream_id = p_current_stream_id
  AND s.status = 'active'
  AND NOT EXISTS (
    SELECT 1
    FROM student_promotions
    WHERE student_id = s.id
      AND from_academic_year = p_from_year
      AND to_academic_year = p_to_year
  ) ON DUPLICATE KEY
UPDATE promotion_status = 'pending_approval';

INSERT INTO class_promotion_queue (
    batch_id,
    class_id,
    stream_id,
    total_in_class,
    approval_status
  )
VALUES (
    p_batch_id,
    v_next_class_id,
    v_next_stream_id,
    (
      SELECT COUNT(*)
      FROM student_promotions
      WHERE batch_id = p_batch_id
        AND promoted_to_class_id = v_next_class_id
    ),
    'pending'
  ) ON DUPLICATE KEY
UPDATE total_in_class =
VALUES(total_in_class);

UPDATE promotion_batches
SET total_students_processed = (
    SELECT COUNT(*)
    FROM student_promotions
    WHERE batch_id = p_batch_id
  ),
  total_pending_approval = (
    SELECT COUNT(*)
    FROM student_promotions
    WHERE batch_id = p_batch_id
      AND promotion_status = 'pending_approval'
  )
WHERE id = p_batch_id;
END$$

DROP PROCEDURE IF EXISTS `sp_record_assessment_change`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_record_assessment_change` (IN `p_assessment_result_id` INT UNSIGNED, IN `p_student_id` INT UNSIGNED, IN `p_assessment_id` INT UNSIGNED, IN `p_old_marks` DECIMAL(6,2), IN `p_new_marks` DECIMAL(6,2), IN `p_reason` VARCHAR(255), IN `p_changed_by` INT UNSIGNED)   BEGIN
DECLARE v_old_grade VARCHAR(4);
DECLARE v_new_grade VARCHAR(4);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Error recording assessment change';
END;

SET v_old_grade = calculate_grade(p_old_marks);
SET v_new_grade = calculate_grade(p_new_marks);

INSERT INTO assessment_history (
    assessment_result_id,
    student_id,
    assessment_id,
    old_marks,
    new_marks,
    old_grade,
    new_grade,
    change_reason,
    changed_by
  )
VALUES (
    p_assessment_result_id,
    p_student_id,
    p_assessment_id,
    p_old_marks,
    p_new_marks,
    v_old_grade,
    v_new_grade,
    p_reason,
    p_changed_by
  );

UPDATE assessment_results
SET marks_obtained = p_new_marks,
  grade = v_new_grade,
  points = calculate_points(p_new_marks),
  updated_at = NOW()
WHERE id = p_assessment_result_id;
END$$

DROP PROCEDURE IF EXISTS `sp_record_cash_payment`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_record_cash_payment` (IN `p_student_id` INT UNSIGNED, IN `p_amount` DECIMAL(10,2), IN `p_transaction_date` DATE, IN `p_details` TEXT)   BEGIN
  
  UPDATE student_fee_balances
  SET balance = balance - p_amount,
      last_updated = NOW()
  WHERE student_id = p_student_id
  ORDER BY academic_term_id DESC
  LIMIT 1;

  
  INSERT INTO school_transactions (
      student_id,
      source,
      reference,
      amount,
      transaction_date,
      status,
      details
  )
  VALUES (
      p_student_id,
      'cash',
      NULL,
      p_amount,
      p_transaction_date,
      'confirmed',
      JSON_OBJECT('details', p_details)
  );

  
  INSERT INTO system_events (event_type, event_data)
  VALUES (
      'cash_payment_recorded',
      JSON_OBJECT(
        'student_id', p_student_id,
        'amount', p_amount,
        'transaction_date', p_transaction_date,
        'details', p_details
      )
  );
END$$

DROP PROCEDURE IF EXISTS `sp_record_conduct`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_record_conduct` (IN `p_student_id` INT UNSIGNED, IN `p_academic_year` YEAR, IN `p_term_id` INT UNSIGNED, IN `p_conduct_rating` VARCHAR(50), IN `p_conduct_comments` TEXT, IN `p_behavior_incidents` JSON, IN `p_teacher_notes` TEXT, IN `p_recorded_by` INT UNSIGNED, IN `p_recorded_date` DATE)   BEGIN
INSERT INTO conduct_tracking (
    student_id,
    academic_year,
    term_id,
    conduct_rating,
    conduct_comments,
    behavior_incidents,
    teacher_notes,
    recorded_by,
    recorded_date
  )
VALUES (
    p_student_id,
    p_academic_year,
    p_term_id,
    p_conduct_rating,
    p_conduct_comments,
    p_behavior_incidents,
    p_teacher_notes,
    p_recorded_by,
    p_recorded_date
  ) ON DUPLICATE KEY
UPDATE conduct_rating = p_conduct_rating,
  conduct_comments = p_conduct_comments,
  behavior_incidents = p_behavior_incidents,
  teacher_notes = p_teacher_notes,
  updated_at = NOW();
END$$

DROP PROCEDURE IF EXISTS `sp_record_core_value`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_record_core_value` (IN `p_student_id` INT UNSIGNED, IN `p_value_id` INT UNSIGNED, IN `p_academic_year` YEAR, IN `p_term_id` INT UNSIGNED, IN `p_evidence` TEXT, IN `p_incident_date` DATE, IN `p_recorded_by` INT UNSIGNED)   BEGIN
INSERT INTO learner_values_acquisition (
    student_id,
    value_id,
    academic_year,
    term_id,
    evidence,
    incident_date,
    recorded_by
  )
VALUES (
    p_student_id,
    p_value_id,
    p_academic_year,
    p_term_id,
    p_evidence,
    p_incident_date,
    p_recorded_by
  );
END$$

DROP PROCEDURE IF EXISTS `sp_record_csl_participation`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_record_csl_participation` (IN `p_student_id` INT UNSIGNED, IN `p_csl_activity_id` INT UNSIGNED, IN `p_academic_year` YEAR, IN `p_hours_contributed` INT, IN `p_role` VARCHAR(100), IN `p_reflection` TEXT, IN `p_teacher_feedback` TEXT)   BEGIN
INSERT INTO learner_csl_participation (
    student_id,
    csl_activity_id,
    academic_year,
    hours_contributed,
    role,
    reflection,
    teacher_feedback,
    participation_status
  )
VALUES (
    p_student_id,
    p_csl_activity_id,
    p_academic_year,
    p_hours_contributed,
    p_role,
    p_reflection,
    p_teacher_feedback,
    'participated'
  ) ON DUPLICATE KEY
UPDATE hours_contributed = p_hours_contributed,
  role = p_role,
  reflection = p_reflection,
  teacher_feedback = p_teacher_feedback,
  updated_at = NOW();
END$$

DROP PROCEDURE IF EXISTS `sp_record_discipline_case`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_record_discipline_case` (IN `p_student_id` INT UNSIGNED, IN `p_incident_type` VARCHAR(100), IN `p_description` TEXT, IN `p_severity` ENUM('minor','moderate','serious','critical'), IN `p_reported_by` INT UNSIGNED, OUT `p_case_id` INT UNSIGNED)   BEGIN
    DECLARE v_student_name VARCHAR(255);
    DECLARE v_class_id INT UNSIGNED;
    DECLARE v_parent_phone VARCHAR(20);
    
    START TRANSACTION;
    
    
    SELECT CONCAT(first_name, ' ', last_name), class_id, parent_phone
    INTO v_student_name, v_class_id, v_parent_phone
    FROM students
    WHERE id = p_student_id;
    
    
    INSERT INTO student_discipline (
        student_id,
        incident_type,
        description,
        severity,
        incident_date,
        reported_by,
        status
    ) VALUES (
        p_student_id,
        p_incident_type,
        p_description,
        p_severity,
        NOW(),
        p_reported_by,
        'reported'
    );
    
    SET p_case_id = LAST_INSERT_ID();
    
    
    CALL sp_record_conduct(
        p_student_id,
        YEAR(NOW()),
        (SELECT id FROM academic_terms WHERE status = 'active' LIMIT 1),
        'needs_improvement',
        CONCAT('Discipline incident: ', p_incident_type),
        p_reported_by,
        CURDATE()
    );
    
    
    IF p_severity IN ('serious', 'critical') THEN
        CALL sp_send_sms_to_parents(
            JSON_ARRAY(p_student_id),
            CONCAT('URGENT: Discipline incident involving ', v_student_name, '. Please contact school.'),
            p_reported_by
        );
    END IF;
    
    COMMIT;
END$$

DROP PROCEDURE IF EXISTS `sp_record_food_consumption`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_record_food_consumption` (IN `p_consumption_date` DATE, IN `p_meal_plan_id` INT UNSIGNED, IN `p_inventory_item_id` INT UNSIGNED, IN `p_quantity_used` DECIMAL(10,2), IN `p_waste_quantity` DECIMAL(10,2), IN `p_recorded_by` INT UNSIGNED, IN `p_notes` TEXT)   BEGIN
DECLARE v_error_msg VARCHAR(255);
DECLARE v_quantity_planned DECIMAL(10, 2) DEFAULT 0;
DECLARE v_unit VARCHAR(20);
DECLARE v_cost_per_unit DECIMAL(10, 2) DEFAULT 0;
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = v_error_msg;
END;
SELECT unit,
  unit_cost INTO v_unit,
  v_cost_per_unit
FROM inventory_items
WHERE id = p_inventory_item_id;
IF v_unit IS NULL THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Inventory item not found';
END IF;
IF p_meal_plan_id IS NOT NULL THEN
SELECT COALESCE(prepared_quantity, 0) INTO v_quantity_planned
FROM meal_plans
WHERE id = p_meal_plan_id;
END IF;
INSERT INTO food_consumption_records (
    consumption_date,
    meal_plan_id,
    inventory_item_id,
    quantity_planned,
    quantity_used,
    unit,
    waste_quantity,
    cost_per_unit,
    total_cost,
    recorded_by,
    recorded_at,
    notes
  )
VALUES (
    p_consumption_date,
    p_meal_plan_id,
    p_inventory_item_id,
    v_quantity_planned,
    p_quantity_used,
    v_unit,
    p_waste_quantity,
    v_cost_per_unit,
    (p_quantity_used * v_cost_per_unit),
    p_recorded_by,
    NOW(),
    p_notes
  );
UPDATE inventory_items
SET current_quantity = current_quantity - (p_quantity_used + p_waste_quantity)
WHERE id = p_inventory_item_id;
INSERT INTO inventory_transactions (
    item_id,
    transaction_type,
    quantity,
    transaction_date,
    created_at,
    reference_type,
    reference_id,
    notes
  )
VALUES (
    p_inventory_item_id,
    'out',
    (p_quantity_used + p_waste_quantity),
    p_consumption_date,
    NOW(),
    'consumption',
    p_meal_plan_id,
    CONCAT(
      'Food consumption - Used: ',
      p_quantity_used,
      ', Waste: ',
      p_waste_quantity
    )
  );
INSERT INTO system_events (event_type, event_data, created_at)
VALUES (
    'food_consumed',
    JSON_OBJECT(
      'item_id',
      p_inventory_item_id,
      'quantity',
      p_quantity_used
    ),
    NOW()
  );
END$$

DROP PROCEDURE IF EXISTS `sp_record_login_attempt`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_record_login_attempt` (IN `p_username` VARCHAR(50), IN `p_ip_address` VARCHAR(45), IN `p_attempt_status` VARCHAR(20), IN `p_failure_reason` VARCHAR(255))   BEGIN
DECLARE v_failed_count INT;
DECLARE v_user_id INT UNSIGNED;
INSERT INTO user_login_attempts (
    username,
    ip_address,
    attempt_time,
    attempt_status,
    failure_reason
  )
VALUES (
    p_username,
    p_ip_address,
    NOW(),
    p_attempt_status,
    p_failure_reason
  );
IF p_attempt_status = 'failed' THEN
SELECT COUNT(*) INTO v_failed_count
FROM user_login_attempts
WHERE username = p_username
  AND ip_address = p_ip_address
  AND attempt_status = 'failed'
  AND attempt_time > DATE_SUB(NOW(), INTERVAL 30 MINUTE);
IF v_failed_count >= 5 THEN
SELECT id INTO v_user_id
FROM users
WHERE username = p_username;
IF v_user_id IS NOT NULL THEN
UPDATE users
SET status = 'suspended'
WHERE id = v_user_id;
INSERT INTO user_login_attempts (
    username,
    ip_address,
    attempt_time,
    attempt_status
  )
VALUES (p_username, p_ip_address, NOW(), 'locked');
END IF;
END IF;
END IF;
END$$

DROP PROCEDURE IF EXISTS `sp_record_pci_awareness`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_record_pci_awareness` (IN `p_student_id` INT UNSIGNED, IN `p_pci_id` INT UNSIGNED, IN `p_academic_year` YEAR, IN `p_term_id` INT UNSIGNED, IN `p_awareness_level` VARCHAR(50), IN `p_evidence` TEXT, IN `p_learning_activity` VARCHAR(255), IN `p_assessed_by` INT UNSIGNED, IN `p_assessed_date` DATE)   BEGIN
INSERT INTO learner_pci_awareness (
    student_id,
    pci_id,
    academic_year,
    term_id,
    awareness_level,
    evidence,
    learning_activity,
    assessed_by,
    assessed_date
  )
VALUES (
    p_student_id,
    p_pci_id,
    p_academic_year,
    p_term_id,
    p_awareness_level,
    p_evidence,
    p_learning_activity,
    p_assessed_by,
    p_assessed_date
  ) ON DUPLICATE KEY
UPDATE awareness_level = p_awareness_level,
  evidence = p_evidence,
  learning_activity = p_learning_activity,
  assessed_by = p_assessed_by,
  assessed_date = p_assessed_date;
END$$

DROP PROCEDURE IF EXISTS `sp_refresh_student_payment_summary`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_refresh_student_payment_summary` (IN `p_student_id` INT, IN `p_academic_year` YEAR, IN `p_term_id` INT)   BEGIN
    DECLARE v_fees_due DECIMAL(10,2);
    DECLARE v_total_paid DECIMAL(10,2);
    DECLARE v_payment_count INT;
    DECLARE v_balance DECIMAL(10,2);
    DECLARE v_cash_total DECIMAL(10,2);
    DECLARE v_mpesa_total DECIMAL(10,2);
    DECLARE v_bank_total DECIMAL(10,2);
    DECLARE v_last_payment DATETIME;
    
    SELECT COALESCE(SUM(sfo.amount_due), 0)
    INTO v_fees_due
    FROM student_fee_obligations sfo
    WHERE sfo.student_id = p_student_id
    AND sfo.academic_year = p_academic_year
    AND sfo.term_id = p_term_id;
    
    SELECT 
        COALESCE(SUM(pt.amount_paid), 0),
        COUNT(*),
        COALESCE(SUM(CASE WHEN pt.payment_method = 'cash' THEN pt.amount_paid ELSE 0 END), 0),
        COALESCE(SUM(CASE WHEN pt.payment_method = 'mpesa' THEN pt.amount_paid ELSE 0 END), 0),
        COALESCE(SUM(CASE WHEN pt.payment_method = 'bank_transfer' THEN pt.amount_paid ELSE 0 END), 0),
        MAX(pt.payment_date)
    INTO 
        v_total_paid,
        v_payment_count,
        v_cash_total,
        v_mpesa_total,
        v_bank_total,
        v_last_payment
    FROM payment_transactions pt
    WHERE pt.student_id = p_student_id
    AND pt.academic_year = p_academic_year
    AND pt.term_id = p_term_id
    AND pt.status = 'confirmed';
    
    SET v_balance = v_fees_due - v_total_paid;
    
    INSERT INTO student_payment_history_summary (
        student_id,
        academic_year,
        term_id,
        total_fees_due,
        total_paid,
        payment_count,
        balance,
        cash_payments,
        mpesa_payments,
        bank_transfers,
        last_payment_date
    ) VALUES (
        p_student_id,
        p_academic_year,
        p_term_id,
        v_fees_due,
        v_total_paid,
        v_payment_count,
        v_balance,
        v_cash_total,
        v_mpesa_total,
        v_bank_total,
        v_last_payment
    )
    ON DUPLICATE KEY UPDATE
        total_fees_due = v_fees_due,
        total_paid = v_total_paid,
        payment_count = v_payment_count,
        balance = v_balance,
        cash_payments = v_cash_total,
        mpesa_payments = v_mpesa_total,
        bank_transfers = v_bank_total,
        last_payment_date = v_last_payment;
END$$

DROP PROCEDURE IF EXISTS `sp_reject_class_promotion`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_reject_class_promotion` (IN `p_batch_id` INT UNSIGNED, IN `p_class_id` INT UNSIGNED, IN `p_stream_id` INT UNSIGNED, IN `p_rejection_reason` TEXT, IN `p_reviewed_by` INT UNSIGNED)   BEGIN
DECLARE v_error_msg VARCHAR(255);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Class rejection failed';
END;

UPDATE student_promotions
SET promotion_status = 'rejected',
  rejection_reason = p_rejection_reason,
  approved_by = p_reviewed_by,
  approval_date = NOW()
WHERE batch_id = p_batch_id
  AND promoted_to_class_id = p_class_id
  AND promoted_to_stream_id = p_stream_id
  AND promotion_status = 'pending_approval';

UPDATE class_promotion_queue
SET approval_status = 'rejected',
  rejected_count = (
    SELECT COUNT(*)
    FROM student_promotions
    WHERE batch_id = p_batch_id
      AND promoted_to_class_id = p_class_id
      AND promoted_to_stream_id = p_stream_id
      AND promotion_status = 'rejected'
  ),
  notes = p_rejection_reason,
  reviewed_at = NOW()
WHERE batch_id = p_batch_id
  AND class_id = p_class_id
  AND stream_id = p_stream_id;
END$$

DROP PROCEDURE IF EXISTS `sp_reject_student_promotion`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_reject_student_promotion` (IN `p_promotion_id` INT UNSIGNED, IN `p_rejection_reason` TEXT, IN `p_reviewed_by` INT UNSIGNED)   BEGIN
DECLARE v_batch_id INT UNSIGNED;
DECLARE v_promoted_class_id INT UNSIGNED;
DECLARE v_promoted_stream_id INT UNSIGNED;
DECLARE v_error_msg VARCHAR(255);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Student rejection failed';
END;

SELECT batch_id,
  promoted_to_class_id,
  promoted_to_stream_id INTO v_batch_id,
  v_promoted_class_id,
  v_promoted_stream_id
FROM student_promotions
WHERE id = p_promotion_id;

UPDATE student_promotions
SET promotion_status = 'rejected',
  rejection_reason = p_rejection_reason,
  approved_by = p_reviewed_by,
  approval_date = NOW()
WHERE id = p_promotion_id;

UPDATE class_promotion_queue
SET rejected_count = (
    SELECT COUNT(*)
    FROM student_promotions
    WHERE batch_id = v_batch_id
      AND promoted_to_class_id = v_promoted_class_id
      AND promoted_to_stream_id = v_promoted_stream_id
      AND promotion_status = 'rejected'
  ),
  pending_count = (
    SELECT COUNT(*)
    FROM student_promotions
    WHERE batch_id = v_batch_id
      AND promoted_to_class_id = v_promoted_class_id
      AND promoted_to_stream_id = v_promoted_stream_id
      AND promotion_status = 'pending_approval'
  )
WHERE batch_id = v_batch_id
  AND class_id = v_promoted_class_id
  AND stream_id = v_promoted_stream_id;
END$$

DROP PROCEDURE IF EXISTS `sp_remove_custom_stream`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_remove_custom_stream` (IN `p_stream_id` INT UNSIGNED)   BEGIN
DECLARE v_class_id INT UNSIGNED;
DECLARE v_stream_name VARCHAR(50);
DECLARE v_class_name VARCHAR(50);
DECLARE v_remaining_custom_streams INT;
DECLARE v_error_msg VARCHAR(255);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = v_error_msg;
END;

SELECT cs.class_id,
  cs.stream_name INTO v_class_id,
  v_stream_name
FROM class_streams cs
WHERE cs.id = p_stream_id;
IF v_class_id IS NULL THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Stream not found';
END IF;

SELECT c.name INTO v_class_name
FROM classes c
WHERE c.id = v_class_id;
IF v_stream_name = v_class_name THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Cannot delete default stream. Deactivate custom streams instead.';
END IF;

UPDATE class_streams
SET status = 'inactive'
WHERE id = p_stream_id;

SELECT COUNT(*) INTO v_remaining_custom_streams
FROM class_streams cs
WHERE cs.class_id = v_class_id
  AND cs.status = 'active'
  AND cs.stream_name != v_class_name;


IF v_remaining_custom_streams = 0 THEN
UPDATE class_streams
SET status = 'active'
WHERE class_id = v_class_id
  AND stream_name = v_class_name;
END IF;
END$$

DROP PROCEDURE IF EXISTS `sp_request_staff_advance`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_request_staff_advance` (IN `p_staff_id` INT, IN `p_amount` DECIMAL(12,2), IN `p_reason` TEXT, OUT `p_request_id` INT)   BEGIN
    DECLARE v_staff_exists INT DEFAULT 0;
    DECLARE v_pending_advances INT DEFAULT 0;
    DECLARE v_basic_salary DECIMAL(12,2);
    DECLARE v_max_advance DECIMAL(12,2);
    
    
    SELECT COUNT(*), COALESCE(salary, 0) 
    INTO v_staff_exists, v_basic_salary
    FROM staff 
    WHERE id = p_staff_id AND status = 'active';
    
    IF v_staff_exists = 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Staff member not found or not active';
    END IF;
    
    
    
    SELECT COUNT(*) INTO v_pending_advances
    FROM staff_deductions sd
    WHERE sd.staff_id = p_staff_id 
        AND sd.effective_date >= CURDATE()
        AND sd.effective_date <= DATE_ADD(CURDATE(), INTERVAL 2 MONTH);
    
    IF v_pending_advances > 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Staff has pending advance request. Please clear existing advance first.';
    END IF;
    
    
    SET v_max_advance = v_basic_salary * 0.5;
    
    IF p_amount > v_max_advance THEN
        SET @error_msg = CONCAT('Advance amount exceeds maximum allowed (50% of basic salary = KES ', v_max_advance, ')');
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Advance amount exceeds maximum allowed';
    END IF;
    
    IF p_amount <= 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Advance amount must be greater than zero';
    END IF;
    
    
    
    INSERT INTO staff_deductions (staff_id, amount, effective_date)
    VALUES (p_staff_id, p_amount, DATE_ADD(CURDATE(), INTERVAL 1 MONTH));
    
    SET p_request_id = LAST_INSERT_ID();
    
    
    
END$$

DROP PROCEDURE IF EXISTS `sp_return_allocation`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_return_allocation` (IN `p_allocation_id` INT UNSIGNED, IN `p_returned_quantity` INT, IN `p_returned_condition` VARCHAR(100))   BEGIN
DECLARE v_error_msg VARCHAR(255);
DECLARE v_allocated_quantity INT;
DECLARE v_item_id INT UNSIGNED;
DECLARE v_status VARCHAR(50);
DECLARE v_msg VARCHAR(255);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = v_error_msg;
END;
SELECT item_id,
  allocated_quantity,
  status INTO v_item_id,
  v_allocated_quantity,
  v_status
FROM inventory_allocations
WHERE id = p_allocation_id;
IF v_item_id IS NULL THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Allocation not found';
END IF;
IF p_returned_quantity > v_allocated_quantity THEN
    SET v_msg = CONCAT('Return quantity exceeds allocated (', v_allocated_quantity, ')');
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
END IF;

UPDATE inventory_allocations
SET returned_quantity = p_returned_quantity,
  returned_at = NOW(),
  status = IF(
    p_returned_quantity = v_allocated_quantity,
    'fully_returned',
    'partially_returned'
  ),
  updated_at = NOW()
WHERE id = p_allocation_id;
UPDATE inventory_items
SET current_quantity = current_quantity + p_returned_quantity
WHERE id = v_item_id;
INSERT INTO inventory_transactions (
    item_id,
    transaction_type,
    quantity,
    transaction_date,
    created_at,
    reference_type,
    reference_id,
    notes
  )
VALUES (
    v_item_id,
    'in',
    p_returned_quantity,
    CURDATE(),
    NOW(),
    'allocation_return',
    p_allocation_id,
    CONCAT(
      'Allocation returned - Condition: ',
      p_returned_condition
    )
  );
INSERT INTO system_events (event_type, event_data, created_at)
VALUES (
    'allocation_returned',
    JSON_OBJECT(
      'allocation_id',
      p_allocation_id,
      'quantity',
      p_returned_quantity
    ),
    NOW()
  );
END$$

DROP PROCEDURE IF EXISTS `sp_schedule_discipline_hearing`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_schedule_discipline_hearing` (IN `p_case_id` INT UNSIGNED, IN `p_hearing_date` DATETIME, IN `p_panel_members` JSON, IN `p_scheduled_by` INT UNSIGNED)   BEGIN
    DECLARE v_student_id INT UNSIGNED;
    
    START TRANSACTION;
    
    
    SELECT student_id INTO v_student_id
    FROM student_discipline
    WHERE id = p_case_id;
    
    
    INSERT INTO discipline_hearings (
        case_id,
        student_id,
        hearing_date,
        panel_members,
        scheduled_by,
        status
    ) VALUES (
        p_case_id,
        v_student_id,
        p_hearing_date,
        p_panel_members,
        p_scheduled_by,
        'scheduled'
    );
    
    
    UPDATE student_discipline
    SET status = 'hearing_scheduled'
    WHERE id = p_case_id;
    
    
    CALL sp_send_internal_message(
        p_panel_members,
        'Discipline Hearing Scheduled',
        CONCAT('You are scheduled for a discipline hearing on ', p_hearing_date),
        p_scheduled_by
    );
    
    COMMIT;
END$$

DROP PROCEDURE IF EXISTS `sp_schedule_maintenance`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_schedule_maintenance` (IN `p_equipment_id` INT UNSIGNED, IN `p_maintenance_type_id` INT UNSIGNED, IN `p_next_maintenance_date` DATE, IN `p_notes` TEXT)   BEGIN
DECLARE v_error_msg VARCHAR(255);
DECLARE v_equipment_exists INT DEFAULT 0;
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = v_error_msg;
END;
SELECT COUNT(*) INTO v_equipment_exists
FROM item_serials
WHERE id = p_equipment_id;
IF v_equipment_exists = 0 THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Equipment not found';
END IF;
INSERT INTO equipment_maintenance (
    equipment_id,
    maintenance_type_id,
    next_maintenance_date,
    status,
    notes,
    created_at,
    updated_at
  )
VALUES (
    p_equipment_id,
    p_maintenance_type_id,
    p_next_maintenance_date,
    'pending',
    p_notes,
    NOW(),
    NOW()
  );
INSERT INTO system_events (event_type, event_data, created_at)
VALUES (
    'maintenance_scheduled',
    JSON_OBJECT(
      'equipment_id',
      p_equipment_id,
      'next_date',
      p_next_maintenance_date
    ),
    NOW()
  );
END$$

DROP PROCEDURE IF EXISTS `sp_send_announcement`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_send_announcement` (IN `p_title` VARCHAR(255), IN `p_content` LONGTEXT, IN `p_audience_type` VARCHAR(50), IN `p_audience_json` JSON, IN `p_priority` VARCHAR(20), IN `p_published_by` INT UNSIGNED)   BEGIN
DECLARE v_error_msg VARCHAR(255);
DECLARE v_announcement_id INT UNSIGNED;
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = v_error_msg;
END;
INSERT INTO announcements_bulletin (
    title,
    content,
    audience_type,
    audience_data,
    priority,
    published_by,
    status,
    created_at,
    updated_at
  )
VALUES (
    p_title,
    p_content,
    p_audience_type,
    p_audience_json,
    p_priority,
    p_published_by,
    'published',
    NOW(),
    NOW()
  );
SET v_announcement_id = LAST_INSERT_ID();
INSERT INTO system_events (event_type, event_data, created_at)
VALUES (
    'announcement_published',
    JSON_OBJECT(
      'announcement_id',
      v_announcement_id,
      'title',
      p_title,
      'published_by',
      p_published_by
    ),
    NOW()
  );
SELECT v_announcement_id as announcement_id;
END$$

DROP PROCEDURE IF EXISTS `sp_send_external_email`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_send_external_email` (IN `p_institution_id` INT UNSIGNED, IN `p_recipient_email` VARCHAR(100), IN `p_subject` VARCHAR(255), IN `p_body` LONGTEXT, IN `p_email_type` VARCHAR(50), IN `p_attachment_json` JSON, IN `p_sent_by` INT UNSIGNED)   BEGIN
DECLARE v_error_msg VARCHAR(255);
DECLARE v_email_id INT UNSIGNED;
DECLARE v_institution_name VARCHAR(255);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = v_error_msg;
END;

SELECT name INTO v_institution_name
FROM external_institutions
WHERE id = p_institution_id;
IF v_institution_name IS NULL THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'External institution not found';
END IF;
INSERT INTO external_emails (
    institution_id,
    recipient_email,
    subject,
    body,
    email_type,
    attachments,
    status,
    sent_by,
    created_at
  )
VALUES (
    p_institution_id,
    p_recipient_email,
    p_subject,
    p_body,
    p_email_type,
    p_attachment_json,
    'pending',
    p_sent_by,
    NOW()
  );
SET v_email_id = LAST_INSERT_ID();
INSERT INTO system_events (event_type, event_data, created_at)
VALUES (
    'external_email_queued',
    JSON_OBJECT(
      'email_id',
      v_email_id,
      'institution_id',
      p_institution_id
    ),
    NOW()
  );
SELECT v_email_id as email_id;
END$$

DROP PROCEDURE IF EXISTS `sp_send_fee_reminder`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_send_fee_reminder` (IN `p_student_id` INT UNSIGNED, IN `p_academic_year` YEAR, IN `p_term_id` INT UNSIGNED, IN `p_reminder_type` VARCHAR(50))   BEGIN
DECLARE v_parent_id INT UNSIGNED;
DECLARE v_outstanding_amount DECIMAL(10, 2);

SELECT parent_id INTO v_parent_id
FROM students
WHERE id = p_student_id
LIMIT 1;

SELECT COALESCE(SUM(balance), 0) INTO v_outstanding_amount
FROM student_fee_obligations
WHERE student_id = p_student_id
  AND academic_year = p_academic_year
  AND term_id = p_term_id;

INSERT INTO fee_reminders (
    student_id,
    parent_id,
    academic_year,
    term_id,
    reminder_type,
    outstanding_amount,
    sent_date,
    delivery_method,
    status
  )
VALUES (
    p_student_id,
    v_parent_id,
    p_academic_year,
    p_term_id,
    p_reminder_type,
    v_outstanding_amount,
    NOW(),
    'sms',
    'sent'
  );
END$$

DROP PROCEDURE IF EXISTS `sp_send_fee_reminders`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_send_fee_reminders` ()   BEGIN
DECLARE done INT DEFAULT FALSE;
DECLARE v_student_id INT UNSIGNED;
DECLARE v_parent_id INT UNSIGNED;
DECLARE v_balance DECIMAL(10, 2);
DECLARE v_due_date DATE;

DECLARE fee_cur CURSOR FOR
SELECT sfb.student_id,
  s.parent_id,
  sfb.balance,
  fs.due_date
FROM student_fee_balances sfb
  JOIN students s ON sfb.student_id = s.id
  JOIN fee_structures fs ON sfb.fee_structure_id = fs.id
WHERE sfb.status IN ('unpaid', 'partial')
  AND fs.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY);
DECLARE CONTINUE HANDLER FOR NOT FOUND
SET done = TRUE;
OPEN fee_cur;
read_loop: LOOP FETCH fee_cur INTO v_student_id,
v_parent_id,
v_balance,
v_due_date;
IF done THEN LEAVE read_loop;
END IF;

INSERT INTO notifications (
    user_id,
    type,
    title,
    message,
    priority
  )
VALUES (
    v_parent_id,
    'fee_reminder',
    CONCAT(
      'Fee balance due: KES ',
      v_balance,
      ' by ',
      v_due_date
    ),
    CONCAT(
      'Your child''s fee balance of KES ',
      v_balance,
      ' is due on ',
      v_due_date
    ),
    'high'
  );
END LOOP;
CLOSE fee_cur;
END$$

DROP PROCEDURE IF EXISTS `sp_send_internal_message`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_send_internal_message` (IN `p_sender_id` INT UNSIGNED, IN `p_recipient_ids` VARCHAR(1000), IN `p_subject` VARCHAR(255), IN `p_body` LONGTEXT, IN `p_priority` VARCHAR(20), IN `p_message_type` VARCHAR(50), IN `p_conversation_id` INT UNSIGNED)   BEGIN
DECLARE v_error_msg VARCHAR(255);
DECLARE v_message_id INT UNSIGNED;
DECLARE v_conversation_id INT UNSIGNED;
DECLARE v_recipient_count INT;
DECLARE v_idx INT DEFAULT 1;
DECLARE v_recipient_id INT UNSIGNED;
DECLARE v_temp_id VARCHAR(20);
DECLARE v_remaining VARCHAR(1000);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = v_error_msg;
END;

IF p_conversation_id IS NULL THEN
INSERT INTO internal_conversations (
    title,
    conversation_type,
    created_by,
    created_at,
    updated_at
  )
VALUES (
    p_subject,
    p_message_type,
    p_sender_id,
    NOW(),
    NOW()
  );
SET v_conversation_id = LAST_INSERT_ID();
ELSE
SET v_conversation_id = p_conversation_id;
END IF;

INSERT INTO internal_messages (
    conversation_id,
    sender_id,
    subject,
    body,
    priority,
    message_type,
    created_at
  )
VALUES (
    v_conversation_id,
    p_sender_id,
    p_subject,
    p_body,
    p_priority,
    p_message_type,
    NOW()
  );
SET v_message_id = LAST_INSERT_ID();

INSERT IGNORE INTO conversation_participants (conversation_id, user_id, added_by, joined_at)
VALUES (
    v_conversation_id,
    p_sender_id,
    p_sender_id,
    NOW()
  );

SET v_remaining = CONCAT(p_recipient_ids, ',');
WHILE LOCATE(',', v_remaining) > 0 DO
SET v_temp_id = TRIM(SUBSTRING_INDEX(v_remaining, ',', 1));
SET v_recipient_id = CAST(v_temp_id AS UNSIGNED);
IF v_recipient_id > 0 THEN
INSERT IGNORE INTO conversation_participants (conversation_id, user_id, added_by, joined_at)
VALUES (
    v_conversation_id,
    v_recipient_id,
    p_sender_id,
    NOW()
  );
INSERT INTO message_read_status (message_id, recipient_id, status, created_at)
VALUES (v_message_id, v_recipient_id, 'unread', NOW());
UPDATE conversation_participants
SET unread_count = unread_count + 1
WHERE conversation_id = v_conversation_id
  AND user_id = v_recipient_id;
END IF;
SET v_remaining = SUBSTRING(v_remaining, LOCATE(',', v_remaining) + 1);
END WHILE;
INSERT INTO system_events (event_type, event_data, created_at)
VALUES (
    'internal_message_sent',
    JSON_OBJECT(
      'message_id',
      v_message_id,
      'sender_id',
      p_sender_id,
      'conversation_id',
      v_conversation_id
    ),
    NOW()
  );
END$$

DROP PROCEDURE IF EXISTS `sp_send_sms_to_parents`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_send_sms_to_parents` (IN `p_parent_ids` VARCHAR(1000), IN `p_message` TEXT, IN `p_template_id` INT UNSIGNED, IN `p_message_type` VARCHAR(50), IN `p_sent_by` INT UNSIGNED)   BEGIN
DECLARE v_error_msg VARCHAR(255);
DECLARE v_sms_id INT UNSIGNED;
DECLARE v_parent_id INT UNSIGNED;
DECLARE v_phone_number VARCHAR(20);
DECLARE v_preference_status VARCHAR(50);
DECLARE v_temp_id VARCHAR(20);
DECLARE v_remaining VARCHAR(1000);
DECLARE v_allowed_time INT;
DECLARE v_current_hour INT;
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = v_error_msg;
END;
SET v_remaining = CONCAT(p_parent_ids, ',');
SET v_current_hour = HOUR(NOW());
WHILE LOCATE(',', v_remaining) > 0 DO
SET v_temp_id = TRIM(SUBSTRING_INDEX(v_remaining, ',', 1));
SET v_parent_id = CAST(v_temp_id AS UNSIGNED);
IF v_parent_id > 0 THEN 
SELECT phone_number,
  status INTO v_phone_number,
  v_preference_status
FROM parent_communication_preferences
WHERE parent_id = v_parent_id;
IF v_phone_number IS NOT NULL
AND v_preference_status = 'active' THEN 
INSERT INTO sms_communications (
    parent_id,
    phone_number,
    message_content,
    message_type,
    template_id,
    status,
    sent_by,
    created_at
  )
VALUES (
    v_parent_id,
    v_phone_number,
    p_message,
    p_message_type,
    p_template_id,
    'pending',
    p_sent_by,
    NOW()
  );
SET v_sms_id = LAST_INSERT_ID();
INSERT INTO system_events (event_type, event_data, created_at)
VALUES (
    'sms_queued',
    JSON_OBJECT('sms_id', v_sms_id, 'parent_id', v_parent_id),
    NOW()
  );
END IF;
END IF;
SET v_remaining = SUBSTRING(v_remaining, LOCATE(',', v_remaining) + 1);
END WHILE;
END$$

DROP PROCEDURE IF EXISTS `sp_suspend_student_promotion`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_suspend_student_promotion` (IN `p_promotion_id` INT UNSIGNED, IN `p_suspension_type` VARCHAR(50), IN `p_suspension_reason` TEXT, IN `p_expected_return` DATE, IN `p_suspended_by` INT UNSIGNED)   BEGIN
DECLARE v_student_id INT UNSIGNED;
DECLARE v_to_academic_year YEAR;
DECLARE v_error_msg VARCHAR(255);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Suspension failed';
END;

SELECT student_id,
  to_academic_year INTO v_student_id,
  v_to_academic_year
FROM student_promotions
WHERE id = p_promotion_id;

UPDATE student_promotions
SET promotion_status = 'suspended'
WHERE id = p_promotion_id;

INSERT INTO student_suspensions (
    student_id,
    academic_year,
    suspension_type,
    reason,
    suspension_date,
    expected_return_date,
    suspended_by,
    status
  )
VALUES (
    v_student_id,
    v_to_academic_year,
    p_suspension_type,
    p_suspension_reason,
    NOW(),
    p_expected_return,
    p_suspended_by,
    'active'
  );
END$$

DROP PROCEDURE IF EXISTS `sp_track_student_behavior`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_track_student_behavior` (IN `p_student_id` INT UNSIGNED, IN `p_observation_date` DATE, IN `p_behavior_rating` ENUM('excellent','good','satisfactory','needs_improvement','poor'), IN `p_notes` TEXT, IN `p_recorded_by` INT UNSIGNED)   BEGIN
    DECLARE v_term_id INT UNSIGNED;
    DECLARE v_academic_year YEAR;
    
    
    SELECT id, year INTO v_term_id, v_academic_year
    FROM academic_terms
    WHERE status = 'active'
    LIMIT 1;
    
    
    INSERT INTO conduct_tracking (
        student_id,
        academic_year,
        term_id,
        conduct_rating,
        conduct_comments,
        teacher_notes,
        recorded_by,
        recorded_date
    ) VALUES (
        p_student_id,
        v_academic_year,
        v_term_id,
        p_behavior_rating,
        p_notes,
        p_notes,
        p_recorded_by,
        p_observation_date
    )
    ON DUPLICATE KEY UPDATE
        conduct_rating = p_behavior_rating,
        conduct_comments = CONCAT(conduct_comments, '\n', p_observation_date, ': ', p_notes),
        teacher_notes = CONCAT(teacher_notes, '\n', p_observation_date, ': ', p_notes),
        updated_at = NOW();
END$$

DROP PROCEDURE IF EXISTS `sp_transfer_student_promotion`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_transfer_student_promotion` (IN `p_promotion_id` INT UNSIGNED, IN `p_transfer_school` VARCHAR(255), IN `p_transfer_reason` TEXT, IN `p_processed_by` INT UNSIGNED)   BEGIN
DECLARE v_student_id INT UNSIGNED;
DECLARE v_error_msg VARCHAR(255);
DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN GET DIAGNOSTICS CONDITION 1 v_error_msg = MESSAGE_TEXT;
SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Transfer failed';
END;

SELECT student_id INTO v_student_id
FROM student_promotions
WHERE id = p_promotion_id;

UPDATE student_promotions
SET promotion_status = 'transferred',
  transfer_to_school = p_transfer_school,
  promotion_reason = p_transfer_reason,
  approved_by = p_processed_by,
  approval_date = NOW()
WHERE id = p_promotion_id;

UPDATE students
SET status = 'transferred'
WHERE id = v_student_id;
END$$

DROP PROCEDURE IF EXISTS `sp_transition_to_new_academic_year`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_transition_to_new_academic_year` (`p_from_year` INT, `p_to_year` INT, OUT `p_result_message` VARCHAR(500))  MODIFIES SQL DATA COMMENT 'Bulk carryover fee balances for all students transitioning to new academic year' BEGIN
DECLARE p_done BOOLEAN DEFAULT FALSE;
DECLARE p_student_id INT UNSIGNED;
DECLARE p_year_balance DECIMAL(12, 2);
DECLARE p_student_count INT DEFAULT 0;
DECLARE p_processed_count INT DEFAULT 0;
DECLARE p_temp_message VARCHAR(500);
DECLARE p_cursor CURSOR FOR
SELECT DISTINCT student_id
FROM student_fee_obligations
WHERE academic_year = p_from_year;
DECLARE CONTINUE HANDLER FOR NOT FOUND
SET p_done = TRUE;
SET p_processed_count = 0;
SET p_result_message = '';
OPEN p_cursor;
year_loop: LOOP FETCH p_cursor INTO p_student_id;
IF p_done THEN LEAVE year_loop;
END IF;

CALL sp_carryover_fee_balance(
  p_student_id,
  p_from_year,
  p_to_year,
  p_temp_message
);
SET p_processed_count = p_processed_count + 1;
END LOOP;
CLOSE p_cursor;
SET p_result_message = CONCAT(
    'SUCCESS: Year transition completed for ',
    p_processed_count,
    ' students from academic year ',
    p_from_year,
    ' to ',
    p_to_year
  );
END$$

DROP PROCEDURE IF EXISTS `sp_transition_to_new_term`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_transition_to_new_term` (`p_academic_year` INT, `p_from_term_id` INT UNSIGNED, `p_to_term_id` INT UNSIGNED, OUT `p_result_message` VARCHAR(500))  MODIFIES SQL DATA COMMENT 'Carryover fee balances from one term to next within same academic year' BEGIN
DECLARE p_done BOOLEAN DEFAULT FALSE;
DECLARE p_student_id INT UNSIGNED;
DECLARE p_term_closing_balance DECIMAL(12, 2);
DECLARE p_action_taken VARCHAR(50);
DECLARE p_student_count INT DEFAULT 0;
DECLARE p_cursor CURSOR FOR
SELECT DISTINCT student_id
FROM student_fee_obligations
WHERE academic_year = p_academic_year
  AND term_id = p_from_term_id;
DECLARE CONTINUE HANDLER FOR NOT FOUND
SET p_done = TRUE;
SET p_student_count = 0;
SET p_result_message = '';
OPEN p_cursor;
term_loop: LOOP FETCH p_cursor INTO p_student_id;
IF p_done THEN LEAVE term_loop;
END IF;

SELECT COALESCE(SUM(balance), 0) INTO p_term_closing_balance
FROM student_fee_obligations
WHERE student_id = p_student_id
  AND academic_year = p_academic_year
  AND term_id = p_from_term_id;

IF p_term_closing_balance = 0 THEN
SET p_action_taken = 'fresh_bill';
ELSEIF p_term_closing_balance > 0 THEN
SET p_action_taken = 'add_to_current';

UPDATE student_fee_obligations
SET previous_term_balance = p_term_closing_balance,
  amount_due = amount_due + p_term_closing_balance,
  balance = balance + p_term_closing_balance,
  updated_at = NOW()
WHERE student_id = p_student_id
  AND academic_year = p_academic_year
  AND term_id = p_to_term_id;
ELSE
SET p_action_taken = 'deduct_from_current';

UPDATE student_fee_obligations
SET previous_term_balance = p_term_closing_balance,
  amount_due = GREATEST(0, amount_due + p_term_closing_balance),
  balance = balance + p_term_closing_balance,
  updated_at = NOW()
WHERE student_id = p_student_id
  AND academic_year = p_academic_year
  AND term_id = p_to_term_id;
END IF;

INSERT INTO student_fee_carryover (
    student_id,
    academic_year,
    term_id,
    previous_balance,
    surplus_amount,
    action_taken,
    notes
  )
VALUES (
    p_student_id,
    p_academic_year,
    p_to_term_id,
    CASE
      WHEN p_term_closing_balance > 0 THEN p_term_closing_balance
      ELSE 0
    END,
    CASE
      WHEN p_term_closing_balance < 0 THEN ABS(p_term_closing_balance)
      ELSE 0
    END,
    p_action_taken,
    CONCAT(
      'Term transition from term ',
      p_from_term_id,
      ' to ',
      p_to_term_id
    )
  );

INSERT INTO fee_transition_history (
    student_id,
    from_academic_year,
    to_academic_year,
    from_term_id,
    to_term_id,
    balance_action,
    amount_transferred,
    created_by
  )
VALUES (
    p_student_id,
    p_academic_year,
    p_academic_year,
    p_from_term_id,
    p_to_term_id,
    p_action_taken,
    p_term_closing_balance,
    1
  );
SET p_student_count = p_student_count + 1;
END LOOP;
CLOSE p_cursor;
SET p_result_message = CONCAT(
    'SUCCESS: Term transition completed for ',
    p_student_count,
    ' students from term ',
    p_from_term_id,
    ' to ',
    p_to_term_id,
    ' in year ',
    p_academic_year
  );
END$$

DROP PROCEDURE IF EXISTS `sp_unlock_user_account`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_unlock_user_account` (IN `p_user_id` INT UNSIGNED, IN `p_unlocked_by` INT UNSIGNED, IN `p_unlock_reason` TEXT)   BEGIN
DECLARE v_locked_date DATETIME;
SELECT locked_date INTO v_locked_date
FROM user_login_attempts
WHERE username = (
    SELECT username
    FROM users
    WHERE id = p_user_id
  )
ORDER BY attempt_time DESC
LIMIT 1;
UPDATE users
SET status = 'active'
WHERE id = p_user_id;
INSERT INTO account_unlock_history (
    user_id,
    locked_date,
    unlocked_date,
    unlocked_by,
    unlock_reason
  )
VALUES (
    p_user_id,
    COALESCE(v_locked_date, NOW()),
    NOW(),
    p_unlocked_by,
    p_unlock_reason
  );
INSERT INTO system_events (event_type, event_data)
VALUES (
    'account_unlocked',
    JSON_OBJECT(
      'user_id',
      p_user_id,
      'unlocked_by',
      p_unlocked_by
    )
  );
END$$

DROP PROCEDURE IF EXISTS `sp_update_kpi_achievement`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_update_kpi_achievement` (IN `p_staff_id` INT UNSIGNED, IN `p_kpi_definition_id` INT UNSIGNED, IN `p_academic_year` INT, IN `p_achieved_value` DECIMAL(10,2), IN `p_recorded_by` INT UNSIGNED, IN `p_notes` TEXT)   BEGIN
DECLARE v_target_value DECIMAL(10, 2);
SELECT kt.target_value INTO v_target_value
FROM kpi_targets kt
WHERE kt.staff_id = p_staff_id
  AND kt.kpi_definition_id = p_kpi_definition_id
  AND kt.academic_year = p_academic_year
  AND kt.is_active = 1;
INSERT INTO kpi_achievements (
    staff_id,
    kpi_definition_id,
    academic_year,
    achieved_value,
    achievement_date,
    recorded_by,
    notes
  )
VALUES (
    p_staff_id,
    p_kpi_definition_id,
    p_academic_year,
    p_achieved_value,
    CURDATE(),
    p_recorded_by,
    p_notes
  );
INSERT INTO system_events (event_type, event_data)
VALUES (
    'kpi_achievement_recorded',
    JSON_OBJECT(
      'staff_id',
      p_staff_id,
      'kpi_id',
      p_kpi_definition_id,
      'achieved',
      p_achieved_value,
      'target',
      v_target_value
    )
  );
END$$

DROP PROCEDURE IF EXISTS `sp_validate_payment_request`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_validate_payment_request` (IN `p_admission_number` VARCHAR(50), IN `p_amount` DECIMAL(10,2), IN `p_transaction_ref` VARCHAR(100))   BEGIN
    DECLARE v_student_id INT UNSIGNED;
    DECLARE v_student_name VARCHAR(200);
    DECLARE v_student_status VARCHAR(50);
    DECLARE v_current_balance DECIMAL(10, 2);
    DECLARE v_duplicate_count INT;
    
    
    SELECT 
        s.id,
        CONCAT(s.first_name, ' ', s.last_name),
        s.status,
        COALESCE(sfb.balance, 0)
    INTO 
        v_student_id,
        v_student_name,
        v_student_status,
        v_current_balance
    FROM students s
    LEFT JOIN student_fee_balances sfb ON s.id = sfb.student_id
    WHERE s.admission_no = p_admission_number
    LIMIT 1;
    
    
    IF p_transaction_ref IS NOT NULL THEN
        SELECT COUNT(*) INTO v_duplicate_count
        FROM mpesa_transactions
        WHERE mpesa_code = p_transaction_ref;
    ELSE
        SET v_duplicate_count = 0;
    END IF;
    
    
    SELECT 
        CASE 
            WHEN v_student_id IS NULL THEN 'INVALID_ADMISSION'
            WHEN v_student_status NOT IN ('active', 'enrolled') THEN 'INACTIVE_STUDENT'
            WHEN v_duplicate_count > 0 THEN 'DUPLICATE_TRANSACTION'
            WHEN p_amount <= 0 THEN 'INVALID_AMOUNT'
            ELSE 'VALID'
        END AS validation_result,
        v_student_id AS student_id,
        p_admission_number AS admission_number,
        v_student_name AS student_name,
        v_student_status AS student_status,
        v_current_balance AS current_balance,
        p_amount AS payment_amount,
        v_duplicate_count AS duplicate_count;
END$$

DROP PROCEDURE IF EXISTS `sp_validate_staff_assignment`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_validate_staff_assignment` (IN `p_staff_id` INT, IN `p_class_stream_id` INT, IN `p_academic_year_id` INT, IN `p_role` VARCHAR(50), OUT `p_is_valid` BOOLEAN, OUT `p_error_message` VARCHAR(255))   sp_validate: BEGIN
    DECLARE v_existing_assignments INT;
    DECLARE v_max_workload INT DEFAULT 8;
    DECLARE v_current_workload INT;
    
    SET p_is_valid = TRUE;
    SET p_error_message = NULL;
    
    SELECT COUNT(*) INTO v_existing_assignments
    FROM staff_class_assignments
    WHERE staff_id = p_staff_id
      AND class_stream_id = p_class_stream_id
      AND academic_year_id = p_academic_year_id
      AND status = 'active';
    
    IF v_existing_assignments > 0 THEN
        SET p_is_valid = FALSE;
        SET p_error_message = 'Staff already assigned to this class';
        LEAVE sp_validate;
    END IF;
    
    IF p_role = 'class_teacher' THEN
        SELECT COUNT(*) INTO v_existing_assignments
        FROM staff_class_assignments
        WHERE class_stream_id = p_class_stream_id
          AND academic_year_id = p_academic_year_id
          AND role = 'class_teacher'
          AND status = 'active';
        
        IF v_existing_assignments > 0 THEN
            SET p_is_valid = FALSE;
            SET p_error_message = 'Class already has a class teacher';
            LEAVE sp_validate;
        END IF;
    END IF;
    
    SELECT COUNT(*) INTO v_current_workload
    FROM staff_class_assignments
    WHERE staff_id = p_staff_id
      AND academic_year_id = p_academic_year_id
      AND status = 'active';
    
    IF v_current_workload >= v_max_workload THEN
        SET p_is_valid = FALSE;
        SET p_error_message = CONCAT('Staff workload limit exceeded (max ', v_max_workload, ' classes)');
    END IF;
END$$

--
-- Functions
--
DROP FUNCTION IF EXISTS `calculate_class_average`$$
CREATE DEFINER=`root`@`localhost` FUNCTION `calculate_class_average` (`p_class_id` INT UNSIGNED, `p_subject_id` INT UNSIGNED, `p_term_id` INT UNSIGNED) RETURNS DECIMAL(10,2) DETERMINISTIC BEGIN
DECLARE avg_marks DECIMAL(10, 2);
SELECT AVG(ar.marks_obtained) INTO avg_marks
FROM assessment_results ar
  JOIN assessments a ON ar.assessment_id = a.id
  JOIN students s ON ar.student_id = s.id
  JOIN class_streams cs ON s.stream_id = cs.id
WHERE cs.class_id = p_class_id
  AND a.subject_id = p_subject_id
  AND a.term_id = p_term_id;
RETURN COALESCE(avg_marks, 0.00);
END$$

DROP FUNCTION IF EXISTS `calculate_grade`$$
CREATE DEFINER=`root`@`localhost` FUNCTION `calculate_grade` (`marks` DECIMAL(5,2)) RETURNS VARCHAR(4) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci DETERMINISTIC BEGIN RETURN CASE
  WHEN marks BETWEEN 90 AND 100 THEN 'EE1' 
  WHEN marks BETWEEN 75 AND 89 THEN 'EE2' 
  WHEN marks BETWEEN 58 AND 74 THEN 'ME1' 
  WHEN marks BETWEEN 41 AND 57 THEN 'ME2' 
  WHEN marks BETWEEN 31 AND 40 THEN 'AE1' 
  WHEN marks BETWEEN 21 AND 30 THEN 'AE2' 
  WHEN marks BETWEEN 11 AND 20 THEN 'BE1' 
  WHEN marks BETWEEN 1 AND 10 THEN 'BE2' 
  ELSE NULL
END;
END$$

DROP FUNCTION IF EXISTS `calculate_points`$$
CREATE DEFINER=`root`@`localhost` FUNCTION `calculate_points` (`marks` DECIMAL(5,2)) RETURNS DECIMAL(3,1) DETERMINISTIC BEGIN RETURN CASE
  WHEN marks BETWEEN 90 AND 100 THEN 4.0
  WHEN marks BETWEEN 75 AND 89 THEN 3.5
  WHEN marks BETWEEN 58 AND 74 THEN 3.0
  WHEN marks BETWEEN 41 AND 57 THEN 2.5
  WHEN marks BETWEEN 31 AND 40 THEN 2.0
  WHEN marks BETWEEN 21 AND 30 THEN 1.5
  WHEN marks BETWEEN 11 AND 20 THEN 1.0
  WHEN marks BETWEEN 1 AND 10 THEN 0.5
  ELSE 0.0
END;
END$$

DROP FUNCTION IF EXISTS `calculate_student_age`$$
CREATE DEFINER=`root`@`localhost` FUNCTION `calculate_student_age` (`date_of_birth` DATE) RETURNS INT(11) DETERMINISTIC BEGIN RETURN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE());
END$$

DROP FUNCTION IF EXISTS `calculate_term_fees`$$
CREATE DEFINER=`root`@`localhost` FUNCTION `calculate_term_fees` (`p_student_id` INT UNSIGNED, `p_term_id` INT UNSIGNED) RETURNS DECIMAL(10,2) READS SQL DATA BEGIN
DECLARE total_fees DECIMAL(10, 2);
SELECT COALESCE(SUM(fs.amount), 0) INTO total_fees
FROM fee_structures fs
  JOIN student_fee_balances sfb ON fs.id = sfb.fee_structure_id
WHERE sfb.student_id = p_student_id
  AND sfb.academic_term_id = p_term_id;
RETURN total_fees;
END$$

DROP FUNCTION IF EXISTS `fn_get_batch_approval_percentage`$$
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_get_batch_approval_percentage` (`p_batch_id` INT UNSIGNED) RETURNS DECIMAL(5,2) DETERMINISTIC READS SQL DATA BEGIN
DECLARE v_total INT;
DECLARE v_approved INT;
SELECT COUNT(*) INTO v_total
FROM student_promotions
WHERE batch_id = p_batch_id;
IF v_total = 0 THEN RETURN 0.00;
END IF;
SELECT COUNT(*) INTO v_approved
FROM student_promotions
WHERE batch_id = p_batch_id
  AND promotion_status = 'approved';
RETURN ROUND((v_approved / v_total) * 100, 2);
END$$

DROP FUNCTION IF EXISTS `fn_get_student_promotion_status`$$
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_get_student_promotion_status` (`p_student_id` INT UNSIGNED, `p_from_year` YEAR, `p_to_year` YEAR) RETURNS VARCHAR(50) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci DETERMINISTIC READS SQL DATA BEGIN
DECLARE v_status VARCHAR(50);
SELECT promotion_status INTO v_status
FROM student_promotions
WHERE student_id = p_student_id
  AND from_academic_year = p_from_year
  AND to_academic_year = p_to_year
LIMIT 1;
RETURN IFNULL(v_status, 'no_promotion');
END$$

DROP FUNCTION IF EXISTS `fn_is_student_suspended`$$
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_is_student_suspended` (`p_student_id` INT UNSIGNED, `p_academic_year` YEAR) RETURNS TINYINT(1) DETERMINISTIC READS SQL DATA BEGIN
DECLARE v_is_suspended TINYINT(1);
SELECT IF(COUNT(*) > 0, 1, 0) INTO v_is_suspended
FROM student_suspensions
WHERE student_id = p_student_id
  AND academic_year = p_academic_year
  AND status IN ('active', 'pending');
RETURN v_is_suspended;
END$$

DROP FUNCTION IF EXISTS `fn_notify_low_stock`$$
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_notify_low_stock` (`p_item_id` INT) RETURNS VARCHAR(255) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci DETERMINISTIC BEGIN
DECLARE v_message VARCHAR(255);
DECLARE v_qty INT;
DECLARE v_min INT;
SELECT current_quantity,
  minimum_quantity INTO v_qty,
  v_min
FROM inventory_items
WHERE id = p_item_id;
IF v_qty <= v_min THEN
SET v_message = CONCAT('Low stock alert for item ', p_item_id);
ELSE
SET v_message = '';
END IF;
RETURN v_message;
END$$

DROP FUNCTION IF EXISTS `fn_student_fee_due`$$
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_student_fee_due` (`p_student_id` INT, `p_term_id` INT) RETURNS DECIMAL(10,2) DETERMINISTIC BEGIN
DECLARE v_due DECIMAL(10, 2);
SELECT SUM(balance) INTO v_due
FROM student_fee_balances
WHERE student_id = p_student_id
  AND academic_term_id = p_term_id;
RETURN IFNULL(v_due, 0.00);
END$$

DROP FUNCTION IF EXISTS `fn_student_outstanding_balance`$$
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_student_outstanding_balance` (`p_student_id` INT) RETURNS DECIMAL(10,2) DETERMINISTIC BEGIN
DECLARE v_balance DECIMAL(10, 2);
SELECT SUM(balance) INTO v_balance
FROM student_fee_balances
WHERE student_id = p_student_id;
RETURN IFNULL(v_balance, 0.00);
END$$

DROP FUNCTION IF EXISTS `generate_student_number`$$
CREATE DEFINER=`root`@`localhost` FUNCTION `generate_student_number` (`class_code` VARCHAR(10), `admission_year` YEAR) RETURNS VARCHAR(20) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci DETERMINISTIC BEGIN
DECLARE next_number INT;
SELECT COALESCE(
    MAX(
      CAST(
        SUBSTRING_INDEX(admission_no, '/', -1) AS UNSIGNED
      )
    ),
    0
  ) + 1 INTO next_number
FROM students
WHERE admission_no LIKE CONCAT(class_code, '/', admission_year, '/%');
RETURN CONCAT(
  class_code,
  '/',
  admission_year,
  '/',
  LPAD(next_number, 4, '0')
);
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `academic_terms`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `academic_terms`;
CREATE TABLE IF NOT EXISTS `academic_terms` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `year` year(4) NOT NULL,
  `term_number` tinyint(4) NOT NULL,
  `status` enum('upcoming','current','completed') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_year_term` (`year`,`term_number`),
  KEY `idx_status_date` (`status`,`start_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `academic_terms`:
--

--
-- Truncate table before insert `academic_terms`
--

TRUNCATE TABLE `academic_terms`;
--
-- Triggers `academic_terms`
--
DROP TRIGGER IF EXISTS `trg_validate_academic_term`;
DELIMITER $$
CREATE TRIGGER `trg_validate_academic_term` BEFORE INSERT ON `academic_terms` FOR EACH ROW BEGIN 
    IF NEW.start_date >= NEW.end_date THEN 
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Term start date must be before end date';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `academic_years`
--
-- Creation: Nov 11, 2025 at 10:58 AM
--

DROP TABLE IF EXISTS `academic_years`;
CREATE TABLE IF NOT EXISTS `academic_years` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `year_code` varchar(20) NOT NULL COMMENT '2024/2025, 2025/2026',
  `year_name` varchar(100) NOT NULL COMMENT 'Academic Year 2024/2025',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `registration_start` date DEFAULT NULL COMMENT 'When enrollment opens',
  `registration_end` date DEFAULT NULL COMMENT 'When enrollment closes',
  `status` enum('planning','registration','active','closing','archived') NOT NULL DEFAULT 'planning',
  `is_current` tinyint(1) DEFAULT 0,
  `total_students` int(11) DEFAULT 0,
  `total_classes` int(11) DEFAULT 0,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `year_code` (`year_code`),
  KEY `idx_year_code` (`year_code`),
  KEY `idx_status` (`status`),
  KEY `idx_is_current` (`is_current`),
  KEY `idx_dates` (`start_date`,`end_date`),
  KEY `created_by` (`created_by`)
) ;

--
-- RELATIONSHIPS FOR TABLE `academic_years`:
--   `created_by`
--       `users` -> `id`
--

--
-- Truncate table before insert `academic_years`
--

TRUNCATE TABLE `academic_years`;
--
-- Dumping data for table `academic_years`
--

INSERT DELAYED IGNORE INTO `academic_years` (`id`, `year_code`, `year_name`, `start_date`, `end_date`, `registration_start`, `registration_end`, `status`, `is_current`, `total_students`, `total_classes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, '2025/2026', 'Academic Year 2025/2026', '2025-01-15', '2025-11-30', NULL, NULL, 'active', 1, 0, 0, NULL, '2025-11-11 10:58:58', '2025-11-11 10:58:58');

--
-- Triggers `academic_years`
--
DROP TRIGGER IF EXISTS `trg_complete_staff_assignments_on_year_end`;
DELIMITER $$
CREATE TRIGGER `trg_complete_staff_assignments_on_year_end` AFTER UPDATE ON `academic_years` FOR EACH ROW BEGIN
    IF NEW.status = 'archived' AND OLD.status != 'archived' THEN
        UPDATE staff_class_assignments
        SET status = 'completed',
            end_date = NEW.end_date
        WHERE academic_year_id = NEW.id
        AND status = 'active';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `academic_year_archives`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `academic_year_archives`;
CREATE TABLE IF NOT EXISTS `academic_year_archives` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `academic_year` year(4) NOT NULL,
  `status` enum('active','closing','archived','readonly') NOT NULL DEFAULT 'active',
  `total_students` int(11) DEFAULT 0,
  `promoted_count` int(11) DEFAULT 0,
  `retained_count` int(11) DEFAULT 0,
  `transferred_count` int(11) DEFAULT 0,
  `graduated_count` int(11) DEFAULT 0,
  `suspended_count` int(11) DEFAULT 0,
  `closure_initiated_by` int(10) UNSIGNED DEFAULT NULL,
  `closure_date` datetime DEFAULT NULL,
  `closure_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_academic_year` (`academic_year`),
  KEY `idx_status` (`status`),
  KEY `closure_initiated_by` (`closure_initiated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `academic_year_archives`:
--   `closure_initiated_by`
--       `users` -> `id`
--

--
-- Truncate table before insert `academic_year_archives`
--

TRUNCATE TABLE `academic_year_archives`;
-- --------------------------------------------------------

--
-- Table structure for table `account_unlock_history`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `account_unlock_history`;
CREATE TABLE IF NOT EXISTS `account_unlock_history` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `locked_reason` varchar(255) DEFAULT NULL,
  `locked_date` datetime NOT NULL,
  `unlocked_date` datetime NOT NULL,
  `unlocked_by` int(10) UNSIGNED DEFAULT NULL,
  `unlock_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_date` (`user_id`,`unlocked_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `account_unlock_history`:
--   `user_id`
--       `users` -> `id`
--

--
-- Truncate table before insert `account_unlock_history`
--

TRUNCATE TABLE `account_unlock_history`;
-- --------------------------------------------------------

--
-- Table structure for table `activities`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `activities`;
CREATE TABLE IF NOT EXISTS `activities` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('planned','ongoing','completed','cancelled') NOT NULL DEFAULT 'planned',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `activities`:
--   `category_id`
--       `activity_categories` -> `id`
--

--
-- Truncate table before insert `activities`
--

TRUNCATE TABLE `activities`;
-- --------------------------------------------------------

--
-- Table structure for table `activity_categories`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `activity_categories`;
CREATE TABLE IF NOT EXISTS `activity_categories` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `activity_categories`:
--

--
-- Truncate table before insert `activity_categories`
--

TRUNCATE TABLE `activity_categories`;
-- --------------------------------------------------------

--
-- Table structure for table `activity_participants`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `activity_participants`;
CREATE TABLE IF NOT EXISTS `activity_participants` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `activity_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_activity_student` (`activity_id`,`student_id`),
  KEY `student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `activity_participants`:
--   `activity_id`
--       `activities` -> `id`
--   `student_id`
--       `students` -> `id`
--

--
-- Truncate table before insert `activity_participants`
--

TRUNCATE TABLE `activity_participants`;
-- --------------------------------------------------------

--
-- Table structure for table `activity_resources`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `activity_resources`;
CREATE TABLE IF NOT EXISTS `activity_resources` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `activity_id` int(10) UNSIGNED NOT NULL,
  `resource_name` varchar(255) NOT NULL,
  `resource_type` varchar(50) DEFAULT NULL,
  `resource_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `activity_id` (`activity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `activity_resources`:
--   `activity_id`
--       `activities` -> `id`
--

--
-- Truncate table before insert `activity_resources`
--

TRUNCATE TABLE `activity_resources`;
-- --------------------------------------------------------

--
-- Table structure for table `admission_applications`
--
-- Creation: Nov 10, 2025 at 11:57 AM
--

DROP TABLE IF EXISTS `admission_applications`;
CREATE TABLE IF NOT EXISTS `admission_applications` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_no` varchar(20) NOT NULL,
  `applicant_name` varchar(100) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `grade_applying_for` enum('Playground','PP1','PP2','Grade1','Grade2','Grade3','Grade4','Grade5','Grade6','Grade7','Grade8','Grade9') NOT NULL,
  `education_level` enum('Pre-Primary','Lower-Primary','Upper-Primary','Junior-Secondary') GENERATED ALWAYS AS (case when `grade_applying_for` in ('Playground','PP1','PP2') then 'Pre-Primary' when `grade_applying_for` in ('Grade1','Grade2','Grade3') then 'Lower-Primary' when `grade_applying_for` in ('Grade4','Grade5','Grade6') then 'Upper-Primary' else 'Junior-Secondary' end) STORED,
  `academic_year` year(4) NOT NULL,
  `previous_school` varchar(255) DEFAULT NULL,
  `parent_id` int(10) UNSIGNED NOT NULL,
  `has_special_needs` tinyint(1) DEFAULT 0,
  `special_needs_details` text DEFAULT NULL,
  `status` enum('submitted','documents_pending','documents_verified','placement_offered','fees_pending','enrolled','cancelled') NOT NULL DEFAULT 'submitted',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_application_no` (`application_no`),
  KEY `idx_status_year` (`status`,`academic_year`),
  KEY `idx_education_level` (`education_level`),
  KEY `fk_admission_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `admission_applications`:
--   `parent_id`
--       `parents` -> `id`
--

--
-- Truncate table before insert `admission_applications`
--

TRUNCATE TABLE `admission_applications`;
-- --------------------------------------------------------

--
-- Table structure for table `admission_documents`
--
-- Creation: Nov 10, 2025 at 11:57 AM
--

DROP TABLE IF EXISTS `admission_documents`;
CREATE TABLE IF NOT EXISTS `admission_documents` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id` int(10) UNSIGNED NOT NULL,
  `document_type` enum('birth_certificate','immunization_card','progress_report','medical_records','passport_photo','nemis_upi','leaving_certificate','transfer_letter','behavior_report','other') NOT NULL,
  `document_path` varchar(255) NOT NULL,
  `is_mandatory` tinyint(1) NOT NULL DEFAULT 1,
  `verification_status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `verified_by` int(10) UNSIGNED DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_application_doc` (`application_id`,`document_type`),
  KEY `fk_doc_verifier` (`verified_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `admission_documents`:
--   `application_id`
--       `admission_applications` -> `id`
--   `verified_by`
--       `users` -> `id`
--

--
-- Truncate table before insert `admission_documents`
--

TRUNCATE TABLE `admission_documents`;
-- --------------------------------------------------------

--
-- Table structure for table `alumni`
--
-- Creation: Nov 11, 2025 at 10:58 AM
--

DROP TABLE IF EXISTS `alumni`;
CREATE TABLE IF NOT EXISTS `alumni` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `graduation_year` int(11) NOT NULL COMMENT 'Academic year graduated (2024/2025 = 2025)',
  `graduated_class_id` int(10) UNSIGNED NOT NULL COMMENT 'Last class (usually Grade 9)',
  `graduated_stream_id` int(10) UNSIGNED NOT NULL,
  `final_enrollment_id` int(10) UNSIGNED DEFAULT NULL,
  `final_average` decimal(5,2) DEFAULT NULL,
  `final_grade` varchar(4) DEFAULT NULL,
  `final_class_rank` int(11) DEFAULT NULL,
  `final_stream_rank` int(11) DEFAULT NULL,
  `overall_rank` int(11) DEFAULT NULL COMMENT 'Rank among all Grade 9 graduates that year',
  `awards` text DEFAULT NULL COMMENT 'JSON array of awards',
  `achievements` text DEFAULT NULL,
  `conduct_grade` enum('Excellent','Very Good','Good','Fair','Poor') DEFAULT NULL,
  `next_school` varchar(255) DEFAULT NULL COMMENT 'Secondary school joined',
  `career_interest` varchar(255) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `is_active_alumni` tinyint(1) DEFAULT 1,
  `last_contact_date` date DEFAULT NULL,
  `alumni_notes` text DEFAULT NULL,
  `graduation_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_graduation` (`student_id`,`graduation_year`),
  KEY `idx_student` (`student_id`),
  KEY `idx_graduation_year` (`graduation_year`),
  KEY `idx_class` (`graduated_class_id`),
  KEY `idx_enrollment` (`final_enrollment_id`),
  KEY `graduated_stream_id` (`graduated_stream_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Graduated students (Grade 9 completers) - Alumni management';

--
-- RELATIONSHIPS FOR TABLE `alumni`:
--   `student_id`
--       `students` -> `id`
--   `graduated_class_id`
--       `classes` -> `id`
--   `graduated_stream_id`
--       `class_streams` -> `id`
--   `final_enrollment_id`
--       `class_enrollments` -> `id`
--

--
-- Truncate table before insert `alumni`
--

TRUNCATE TABLE `alumni`;
-- --------------------------------------------------------

--
-- Table structure for table `announcements_bulletin`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `announcements_bulletin`;
CREATE TABLE IF NOT EXISTS `announcements_bulletin` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `announcement_type` enum('general','academic','administrative','event','emergency','maintenance') NOT NULL DEFAULT 'general',
  `priority` enum('low','normal','high','critical') NOT NULL DEFAULT 'normal',
  `target_audience` enum('all','staff','students','parents','specific') NOT NULL DEFAULT 'all',
  `audience_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`audience_json`)),
  `published_by` int(10) UNSIGNED NOT NULL,
  `status` enum('draft','scheduled','published','archived','expired') NOT NULL DEFAULT 'draft',
  `scheduled_at` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `view_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `published_by` (`published_by`),
  KEY `idx_status` (`status`),
  KEY `idx_announcement_type` (`announcement_type`),
  KEY `idx_published_at` (`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `announcements_bulletin`:
--   `published_by`
--       `staff` -> `id`
--

--
-- Truncate table before insert `announcements_bulletin`
--

TRUNCATE TABLE `announcements_bulletin`;
--
-- Triggers `announcements_bulletin`
--
DROP TRIGGER IF EXISTS `trg_auto_create_notification`;
DELIMITER $$
CREATE TRIGGER `trg_auto_create_notification` AFTER INSERT ON `announcements_bulletin` FOR EACH ROW BEGIN 
  IF NEW.target_audience = 'all'
  OR NEW.target_audience = 'staff' THEN
INSERT INTO notifications (
    user_id,
    title,
    message,
    notification_type,
    priority,
    status,
    created_by,
    created_at
  )
SELECT u.id,
  NEW.title,
  SUBSTRING(NEW.content, 1, 200),
  'announcement',
  NEW.priority,
  'unread',
  NEW.published_by,
  NOW()
FROM users u
WHERE u.status = 'active'
  AND u.id <> NEW.published_by;
END IF;
INSERT INTO system_events (event_type, event_data, created_at)
VALUES (
    'announcement_created',
    JSON_OBJECT(
      'announcement_id',
      NEW.id,
      'title',
      NEW.title,
      'audience',
      NEW.target_audience
    ),
    NOW()
  );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `announcement_views`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `announcement_views`;
CREATE TABLE IF NOT EXISTS `announcement_views` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `announcement_id` int(10) UNSIGNED NOT NULL,
  `viewer_id` int(10) UNSIGNED NOT NULL,
  `viewed_at` datetime NOT NULL DEFAULT current_timestamp(),
  `device_info` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_announcement_viewer` (`announcement_id`,`viewer_id`),
  KEY `viewer_id` (`viewer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `announcement_views`:
--   `announcement_id`
--       `announcements_bulletin` -> `id`
--   `viewer_id`
--       `users` -> `id`
--

--
-- Truncate table before insert `announcement_views`
--

TRUNCATE TABLE `announcement_views`;
-- --------------------------------------------------------

--
-- Table structure for table `annual_scores`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `annual_scores`;
CREATE TABLE IF NOT EXISTS `annual_scores` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `academic_year` year(4) NOT NULL,
  `term1_weight` decimal(3,2) DEFAULT 0.20,
  `term1_score` decimal(5,2) DEFAULT 0.00,
  `term1_grade` varchar(4) DEFAULT NULL,
  `term2_weight` decimal(3,2) DEFAULT 0.30,
  `term2_score` decimal(5,2) DEFAULT 0.00,
  `term2_grade` varchar(4) DEFAULT NULL,
  `term3_weight` decimal(3,2) DEFAULT 0.50,
  `term3_score` decimal(5,2) DEFAULT 0.00,
  `term3_grade` varchar(4) DEFAULT NULL,
  `annual_score` decimal(5,2) DEFAULT 0.00,
  `annual_percentage` decimal(5,2) DEFAULT 0.00,
  `annual_grade` varchar(4) DEFAULT NULL,
  `annual_points` decimal(5,1) DEFAULT 0.0,
  `annual_rank` int(11) DEFAULT NULL,
  `grade_total_students` int(11) DEFAULT NULL,
  `grade_percentile` decimal(5,2) DEFAULT NULL,
  `strengths` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Subjects/competencies where student excels (json array)' CHECK (json_valid(`strengths`)),
  `weaknesses` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Areas needing support (json array)' CHECK (json_valid(`weaknesses`)),
  `avg_formative_percentage` decimal(5,2) DEFAULT 0.00,
  `avg_summative_percentage` decimal(5,2) DEFAULT 0.00,
  `pathway_classification` enum('excelling','on_track','support_needed') DEFAULT 'on_track' COMMENT 'Student learning pathway based on performance patterns',
  `insights_summary` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Recommendations for parents/teachers on student pathways and next steps' CHECK (json_valid(`insights_summary`)),
  `calculated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student_year` (`student_id`,`academic_year`),
  KEY `idx_academic_year` (`academic_year`),
  KEY `idx_annual_grade` (`annual_grade`),
  KEY `idx_pathway_classification` (`pathway_classification`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `annual_scores`:
--   `student_id`
--       `students` -> `id`
--

--
-- Truncate table before insert `annual_scores`
--

TRUNCATE TABLE `annual_scores`;
-- --------------------------------------------------------

--
-- Table structure for table `api_tokens`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `api_tokens`;
CREATE TABLE IF NOT EXISTS `api_tokens` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `token_name` varchar(100) DEFAULT NULL,
  `scope` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`scope`)),
  `created_date` datetime NOT NULL,
  `last_used_date` datetime DEFAULT NULL,
  `expiry_date` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token` (`token_hash`),
  KEY `idx_user_active` (`user_id`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `api_tokens`:
--   `user_id`
--       `users` -> `id`
--

--
-- Truncate table before insert `api_tokens`
--

TRUNCATE TABLE `api_tokens`;
-- --------------------------------------------------------

--
-- Table structure for table `arrears_settlement_plans`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `arrears_settlement_plans`;
CREATE TABLE IF NOT EXISTS `arrears_settlement_plans` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `arrears_id` int(10) UNSIGNED NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `installments` int(11) NOT NULL DEFAULT 3,
  `installment_amount` decimal(10,2) NOT NULL,
  `first_payment_date` date NOT NULL,
  `final_payment_date` date NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `installments_paid` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','completed','defaulted','cancelled') NOT NULL DEFAULT 'active',
  `created_by` int(10) UNSIGNED NOT NULL,
  `approved_by` int(10) UNSIGNED NOT NULL,
  `approved_date` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_arrears` (`arrears_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_approved_by` (`approved_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `arrears_settlement_plans`:
--

--
-- Truncate table before insert `arrears_settlement_plans`
--

TRUNCATE TABLE `arrears_settlement_plans`;
--
-- Triggers `arrears_settlement_plans`
--
DROP TRIGGER IF EXISTS `trg_log_settlement_plan`;
DELIMITER $$
CREATE TRIGGER `trg_log_settlement_plan` AFTER INSERT ON `arrears_settlement_plans` FOR EACH ROW BEGIN
    INSERT INTO system_events (event_type, event_data, created_at)
    VALUES (
        'settlement_plan_created',
        JSON_OBJECT(
            'student_id', NEW.student_id,
            'plan_id', NEW.id,
            'amount', NEW.total_amount,
            'installments', NEW.installments,
            'approved_by', NEW.approved_by
        ),
        NOW()
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `assessments`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `assessments`;
CREATE TABLE IF NOT EXISTS `assessments` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `term_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `max_marks` decimal(6,2) NOT NULL,
  `assessment_date` date NOT NULL,
  `assigned_by` int(10) UNSIGNED NOT NULL,
  `status` enum('pending_submission','submitted','pending_approval','approved') NOT NULL DEFAULT 'pending_submission',
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assessment_type_id` int(10) UNSIGNED DEFAULT NULL,
  `learning_outcome_id` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `subject_id` (`subject_id`),
  KEY `term_id` (`term_id`),
  KEY `assessment_date` (`assessment_date`),
  KEY `assigned_by` (`assigned_by`),
  KEY `approved_by` (`approved_by`),
  KEY `status` (`status`),
  KEY `assessment_type_id` (`assessment_type_id`),
  KEY `learning_outcome_id` (`learning_outcome_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `assessments`:
--   `learning_outcome_id`
--       `learning_outcomes` -> `id`
--   `assessment_type_id`
--       `assessment_types` -> `id`
--

--
-- Truncate table before insert `assessments`
--

TRUNCATE TABLE `assessments`;
-- --------------------------------------------------------

--
-- Table structure for table `assessment_benchmarks`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `assessment_benchmarks`;
CREATE TABLE IF NOT EXISTS `assessment_benchmarks` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `academic_year` year(4) NOT NULL,
  `grade_level_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'NULL = overall benchmark',
  `benchmark_type` enum('class','grade','national') NOT NULL DEFAULT 'grade',
  `target_percentage` decimal(5,2) NOT NULL,
  `acceptable_range_min` decimal(5,2) NOT NULL,
  `acceptable_range_max` decimal(5,2) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_benchmark` (`academic_year`,`grade_level_id`,`subject_id`,`benchmark_type`),
  KEY `idx_benchmark_year_grade` (`academic_year`,`grade_level_id`),
  KEY `fk_ab_grade_level` (`grade_level_id`),
  KEY `fk_ab_subject` (`subject_id`),
  KEY `fk_ab_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `assessment_benchmarks`:
--   `created_by`
--       `users` -> `id`
--   `grade_level_id`
--       `school_levels` -> `id`
--   `subject_id`
--       `curriculum_units` -> `id`
--

--
-- Truncate table before insert `assessment_benchmarks`
--

TRUNCATE TABLE `assessment_benchmarks`;
-- --------------------------------------------------------

--
-- Table structure for table `assessment_history`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `assessment_history`;
CREATE TABLE IF NOT EXISTS `assessment_history` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `assessment_result_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `assessment_id` int(10) UNSIGNED NOT NULL,
  `old_marks` decimal(6,2) DEFAULT NULL,
  `new_marks` decimal(6,2) NOT NULL,
  `old_grade` varchar(4) DEFAULT NULL,
  `new_grade` varchar(4) DEFAULT NULL,
  `change_reason` varchar(255) DEFAULT NULL COMMENT 'correction, appeal, adjustment, etc',
  `changed_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student_history` (`student_id`,`created_at`),
  KEY `idx_assessment_history` (`assessment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `assessment_history`:
--   `assessment_id`
--       `assessments` -> `id`
--   `student_id`
--       `students` -> `id`
--

--
-- Truncate table before insert `assessment_history`
--

TRUNCATE TABLE `assessment_history`;
-- --------------------------------------------------------

--
-- Table structure for table `assessment_results`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `assessment_results`;
CREATE TABLE IF NOT EXISTS `assessment_results` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `assessment_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `marks_obtained` decimal(6,2) NOT NULL,
  `grade` varchar(4) DEFAULT NULL,
  `points` decimal(3,1) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `peer_feedback` text DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `is_submitted` tinyint(1) NOT NULL DEFAULT 0,
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `responder_type` enum('teacher','self','peer') NOT NULL DEFAULT 'teacher',
  `responder_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_assessment_student` (`assessment_id`,`student_id`),
  KEY `assessment_id` (`assessment_id`),
  KEY `student_id` (`student_id`),
  KEY `is_submitted` (`is_submitted`),
  KEY `is_approved` (`is_approved`),
  KEY `idx_responder` (`responder_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `assessment_results`:
--

--
-- Truncate table before insert `assessment_results`
--

TRUNCATE TABLE `assessment_results`;
-- --------------------------------------------------------

--
-- Table structure for table `assessment_rubrics`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `assessment_rubrics`;
CREATE TABLE IF NOT EXISTS `assessment_rubrics` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tool_id` int(10) UNSIGNED NOT NULL,
  `criteria_name` varchar(255) NOT NULL,
  `level_1_descriptor` varchar(500) DEFAULT NULL COMMENT 'Below Expectation',
  `level_2_descriptor` varchar(500) DEFAULT NULL COMMENT 'Approaching Expectation',
  `level_3_descriptor` varchar(500) DEFAULT NULL COMMENT 'Meeting Expectation',
  `level_4_descriptor` varchar(500) DEFAULT NULL COMMENT 'Exceeding Expectation',
  `points_per_level` int(11) DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tool` (`tool_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `assessment_rubrics`:
--   `tool_id`
--       `assessment_tools` -> `id`
--

--
-- Truncate table before insert `assessment_rubrics`
--

TRUNCATE TABLE `assessment_rubrics`;
-- --------------------------------------------------------

--
-- Table structure for table `assessment_tools`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `assessment_tools`;
CREATE TABLE IF NOT EXISTS `assessment_tools` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tool_name` varchar(255) NOT NULL,
  `tool_code` varchar(50) DEFAULT NULL COMMENT 'KNEC tool code',
  `description` text DEFAULT NULL,
  `assessment_type_id` int(10) UNSIGNED NOT NULL,
  `learning_area_id` int(10) UNSIGNED NOT NULL,
  `grade_level` varchar(20) DEFAULT NULL,
  `competencies_assessed` text DEFAULT NULL COMMENT 'JSON array of competency IDs',
  `file_url` varchar(500) DEFAULT NULL COMMENT 'Link to KNEC portal or file',
  `created_by` int(10) UNSIGNED NOT NULL,
  `status` enum('active','archived','deprecated') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tool_code` (`tool_code`),
  KEY `idx_assessment_type` (`assessment_type_id`),
  KEY `idx_learning_area` (`learning_area_id`),
  KEY `idx_grade` (`grade_level`),
  KEY `fk_at_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `assessment_tools`:
--   `assessment_type_id`
--       `assessment_type_classifications` -> `id`
--   `created_by`
--       `users` -> `id`
--   `learning_area_id`
--       `learning_areas` -> `id`
--

--
-- Truncate table before insert `assessment_tools`
--

TRUNCATE TABLE `assessment_tools`;
-- --------------------------------------------------------

--
-- Table structure for table `assessment_types`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `assessment_types`;
CREATE TABLE IF NOT EXISTS `assessment_types` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `is_formative` tinyint(1) NOT NULL DEFAULT 0,
  `is_summative` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `assessment_types`:
--

--
-- Truncate table before insert `assessment_types`
--

TRUNCATE TABLE `assessment_types`;
-- --------------------------------------------------------

--
-- Table structure for table `assessment_type_classifications`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `assessment_type_classifications`;
CREATE TABLE IF NOT EXISTS `assessment_type_classifications` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(10) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `is_national` tinyint(1) NOT NULL DEFAULT 0,
  `is_knec_managed` tinyint(1) NOT NULL DEFAULT 0,
  `knec_portal_code` varchar(50) DEFAULT NULL,
  `grade_applicable` varchar(50) DEFAULT NULL COMMENT 'comma-separated: PP1,PP2,G1,G2,G3,G4,G5,G6,G7,G8,G9',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `assessment_type_classifications`:
--

--
-- Truncate table before insert `assessment_type_classifications`
--

TRUNCATE TABLE `assessment_type_classifications`;
-- --------------------------------------------------------

--
-- Table structure for table `audit_trail`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `audit_trail`;
CREATE TABLE IF NOT EXISTS `audit_trail` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` int(10) UNSIGNED NOT NULL,
  `action` enum('insert','update','delete') NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_table_record` (`table_name`,`record_id`),
  KEY `idx_user_action` (`user_id`,`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `audit_trail`:
--   `user_id`
--       `users` -> `id`
--

--
-- Truncate table before insert `audit_trail`
--

TRUNCATE TABLE `audit_trail`;
-- --------------------------------------------------------

--
-- Table structure for table `auth_sessions`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `auth_sessions`;
CREATE TABLE IF NOT EXISTS `auth_sessions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token` (`token`),
  KEY `idx_user_session` (`user_id`,`expires_at`),
  KEY `idx_session_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `auth_sessions`:
--   `user_id`
--       `users` -> `id`
--

--
-- Truncate table before insert `auth_sessions`
--

TRUNCATE TABLE `auth_sessions`;
-- --------------------------------------------------------

--
-- Table structure for table `bank_transactions`
--
-- Creation: Nov 11, 2025 at 10:11 PM
--

DROP TABLE IF EXISTS `bank_transactions`;
CREATE TABLE IF NOT EXISTS `bank_transactions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `transaction_ref` varchar(100) NOT NULL,
  `student_id` int(10) UNSIGNED DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_date` datetime NOT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `narration` varchar(255) DEFAULT NULL,
  `webhook_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`webhook_data`)),
  `status` enum('pending','processed','failed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_transaction_ref` (`transaction_ref`),
  KEY `idx_student_date` (`student_id`,`transaction_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `bank_transactions`:
--   `student_id`
--       `students` -> `id`
--

--
-- Truncate table before insert `bank_transactions`
--

TRUNCATE TABLE `bank_transactions`;
--
-- Triggers `bank_transactions`
--
DROP TRIGGER IF EXISTS `trg_bank_payment_processed`;
DELIMITER $$
CREATE TRIGGER `trg_bank_payment_processed` AFTER INSERT ON `bank_transactions` FOR EACH ROW BEGIN 
    IF NEW.status = 'processed' AND NEW.student_id IS NOT NULL THEN
        UPDATE student_fee_balances
        SET balance = balance - NEW.amount,
            last_updated = NOW()
        WHERE student_id = NEW.student_id
        ORDER BY academic_term_id DESC
        LIMIT 1;
INSERT INTO school_transactions (
    student_id,
    source,
    reference,
    amount,
    transaction_date,
    status,
    details
  )
VALUES (
    NEW.student_id,
    'bank',
    NEW.transaction_ref,
    NEW.amount,
    NEW.transaction_date,
    'confirmed',
    JSON_OBJECT(
      'bank',
      NEW.bank_name,
      'account',
      NEW.account_number
    )
  );
END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `blocked_devices`
--
-- Creation: Nov 14, 2025 at 04:25 PM
--

DROP TABLE IF EXISTS `blocked_devices`;
CREATE TABLE IF NOT EXISTS `blocked_devices` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_agent_pattern` varchar(255) NOT NULL COMMENT 'Pattern to match against User-Agent header',
  `reason` varchar(255) NOT NULL COMMENT 'Why this device pattern was blocked',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'Admin user who blocked this pattern',
  PRIMARY KEY (`id`),
  KEY `idx_user_agent_pattern` (`user_agent_pattern`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Blocked device/user-agent patterns for security';

--
-- RELATIONSHIPS FOR TABLE `blocked_devices`:
--

--
-- Truncate table before insert `blocked_devices`
--

TRUNCATE TABLE `blocked_devices`;
-- --------------------------------------------------------

--
-- Table structure for table `blocked_ips`
--
-- Creation: Nov 14, 2025 at 04:25 PM
--

DROP TABLE IF EXISTS `blocked_ips`;
CREATE TABLE IF NOT EXISTS `blocked_ips` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL COMMENT 'IPv4 or IPv6 address',
  `reason` varchar(255) NOT NULL COMMENT 'Why this IP was blocked',
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'NULL = permanent block',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'Admin user who blocked this IP',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_blocked_ip` (`ip_address`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Blocked IP addresses for security';

--
-- RELATIONSHIPS FOR TABLE `blocked_ips`:
--

--
-- Truncate table before insert `blocked_ips`
--

TRUNCATE TABLE `blocked_ips`;
-- --------------------------------------------------------

--
-- Table structure for table `classes`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `classes`;
CREATE TABLE IF NOT EXISTS `classes` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `level_id` int(10) UNSIGNED NOT NULL,
  `teacher_id` int(10) UNSIGNED DEFAULT NULL,
  `capacity` int(11) NOT NULL DEFAULT 40,
  `room_number` varchar(20) DEFAULT NULL,
  `academic_year` year(4) NOT NULL,
  `status` enum('active','inactive','completed') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name_year` (`name`,`academic_year`),
  KEY `idx_level` (`level_id`),
  KEY `idx_teacher` (`teacher_id`),
  KEY `idx_status_year` (`status`,`academic_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `classes`:
--   `level_id`
--       `school_levels` -> `id`
--   `teacher_id`
--       `staff` -> `id`
--

--
-- Truncate table before insert `classes`
--

TRUNCATE TABLE `classes`;
--
-- Triggers `classes`
--
DROP TRIGGER IF EXISTS `trg_auto_create_default_stream`;
DELIMITER $$
CREATE TRIGGER `trg_auto_create_default_stream` AFTER INSERT ON `classes` FOR EACH ROW BEGIN
    DECLARE v_stream_count INT;
    DECLARE v_default_stream_name VARCHAR(50);
    
    SELECT COUNT(*) INTO v_stream_count
    FROM class_streams
    WHERE class_id = NEW.id;
    
    IF v_stream_count = 0 THEN
        SET v_default_stream_name = NEW.name;
        INSERT INTO class_streams (
            class_id,
            stream_name,
            capacity,
            teacher_id,
            status
        )
        VALUES (
            NEW.id,
            v_default_stream_name,
            NEW.capacity,
            NEW.teacher_id,
            'active'
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `class_enrollments`
--
-- Creation: Nov 11, 2025 at 10:58 AM
--

DROP TABLE IF EXISTS `class_enrollments`;
CREATE TABLE IF NOT EXISTS `class_enrollments` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `academic_year_id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `stream_id` int(10) UNSIGNED NOT NULL,
  `class_assignment_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Links to class_year_assignments',
  `enrollment_date` date NOT NULL,
  `enrollment_status` enum('pending','active','completed','withdrawn','transferred','graduated') DEFAULT 'active',
  `term1_average` decimal(5,2) DEFAULT NULL,
  `term2_average` decimal(5,2) DEFAULT NULL,
  `term3_average` decimal(5,2) DEFAULT NULL,
  `year_average` decimal(5,2) DEFAULT NULL,
  `overall_grade` varchar(4) DEFAULT NULL,
  `class_rank` int(11) DEFAULT NULL,
  `stream_rank` int(11) DEFAULT NULL,
  `days_present` int(11) DEFAULT 0,
  `days_absent` int(11) DEFAULT 0,
  `days_late` int(11) DEFAULT 0,
  `attendance_percentage` decimal(5,2) DEFAULT NULL,
  `teacher_comments` text DEFAULT NULL,
  `head_teacher_comments` text DEFAULT NULL,
  `special_notes` text DEFAULT NULL,
  `promoted_to_class_id` int(10) UNSIGNED DEFAULT NULL,
  `promoted_to_stream_id` int(10) UNSIGNED DEFAULT NULL,
  `promotion_status` enum('pending','promoted','retained','transferred','graduated','withdrawn') DEFAULT NULL,
  `promotion_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_year` (`student_id`,`academic_year_id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_academic_year` (`academic_year_id`),
  KEY `idx_class_stream` (`class_id`,`stream_id`),
  KEY `idx_assignment` (`class_assignment_id`),
  KEY `idx_enrollment_status` (`enrollment_status`),
  KEY `idx_promotion_status` (`promotion_status`),
  KEY `idx_year_student` (`academic_year_id`,`student_id`),
  KEY `stream_id` (`stream_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Student enrollment per academic year - one record per student per year';

--
-- RELATIONSHIPS FOR TABLE `class_enrollments`:
--   `student_id`
--       `students` -> `id`
--   `academic_year_id`
--       `academic_years` -> `id`
--   `class_id`
--       `classes` -> `id`
--   `stream_id`
--       `class_streams` -> `id`
--   `class_assignment_id`
--       `class_year_assignments` -> `id`
--

--
-- Truncate table before insert `class_enrollments`
--

TRUNCATE TABLE `class_enrollments`;
--
-- Triggers `class_enrollments`
--
DROP TRIGGER IF EXISTS `after_enrollment_delete`;
DELIMITER $$
CREATE TRIGGER `after_enrollment_delete` AFTER DELETE ON `class_enrollments` FOR EACH ROW BEGIN
    IF OLD.class_assignment_id IS NOT NULL THEN
        UPDATE class_year_assignments 
        SET current_enrollment = GREATEST(0, current_enrollment - 1) 
        WHERE id = OLD.class_assignment_id;
    END IF;
    
    UPDATE academic_years 
    SET total_students = (SELECT COUNT(*) FROM class_enrollments WHERE academic_year_id = OLD.academic_year_id)
    WHERE id = OLD.academic_year_id;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `after_enrollment_insert`;
DELIMITER $$
CREATE TRIGGER `after_enrollment_insert` AFTER INSERT ON `class_enrollments` FOR EACH ROW BEGIN
    
    IF NEW.class_assignment_id IS NOT NULL THEN
        UPDATE class_year_assignments 
        SET current_enrollment = current_enrollment + 1 
        WHERE id = NEW.class_assignment_id;
    END IF;
    
    
    UPDATE academic_years 
    SET total_students = (SELECT COUNT(*) FROM class_enrollments WHERE academic_year_id = NEW.academic_year_id)
    WHERE id = NEW.academic_year_id;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `after_enrollment_update`;
DELIMITER $$
CREATE TRIGGER `after_enrollment_update` AFTER UPDATE ON `class_enrollments` FOR EACH ROW BEGIN
    
    IF OLD.class_assignment_id != NEW.class_assignment_id THEN
        IF OLD.class_assignment_id IS NOT NULL THEN
            UPDATE class_year_assignments 
            SET current_enrollment = current_enrollment - 1 
            WHERE id = OLD.class_assignment_id;
        END IF;
        
        IF NEW.class_assignment_id IS NOT NULL THEN
            UPDATE class_year_assignments 
            SET current_enrollment = current_enrollment + 1 
            WHERE id = NEW.class_assignment_id;
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `class_promotion_queue`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `class_promotion_queue`;
CREATE TABLE IF NOT EXISTS `class_promotion_queue` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `stream_id` int(10) UNSIGNED NOT NULL,
  `approval_status` enum('pending','reviewing','approved','partially_approved','rejected','hold') NOT NULL DEFAULT 'pending',
  `total_in_class` int(11) DEFAULT 0,
  `approved_count` int(11) DEFAULT 0,
  `rejected_count` int(11) DEFAULT 0,
  `pending_count` int(11) DEFAULT 0,
  `assigned_to_user_id` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_batch_class_stream` (`batch_id`,`class_id`,`stream_id`),
  KEY `idx_approval_status` (`approval_status`),
  KEY `idx_class_id` (`class_id`),
  KEY `stream_id` (`stream_id`),
  KEY `assigned_to_user_id` (`assigned_to_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `class_promotion_queue`:
--   `batch_id`
--       `promotion_batches` -> `id`
--   `class_id`
--       `classes` -> `id`
--   `stream_id`
--       `class_streams` -> `id`
--   `assigned_to_user_id`
--       `users` -> `id`
--

--
-- Truncate table before insert `class_promotion_queue`
--

TRUNCATE TABLE `class_promotion_queue`;
-- --------------------------------------------------------

--
-- Table structure for table `class_schedules`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `class_schedules`;
CREATE TABLE IF NOT EXISTS `class_schedules` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_id` int(10) UNSIGNED NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `subject_id` int(10) UNSIGNED DEFAULT NULL,
  `teacher_id` int(10) UNSIGNED DEFAULT NULL,
  `room_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `subject_id` (`subject_id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `room_id` (`room_id`),
  KEY `idx_schedule_datetime` (`day_of_week`,`start_time`,`end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `class_schedules`:
--   `class_id`
--       `classes` -> `id`
--   `subject_id`
--       `curriculum_units` -> `id`
--   `teacher_id`
--       `staff` -> `id`
--   `room_id`
--       `rooms` -> `id`
--

--
-- Truncate table before insert `class_schedules`
--

TRUNCATE TABLE `class_schedules`;
-- --------------------------------------------------------

--
-- Table structure for table `class_streams`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `class_streams`;
CREATE TABLE IF NOT EXISTS `class_streams` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_id` int(10) UNSIGNED NOT NULL,
  `stream_name` varchar(50) NOT NULL,
  `capacity` int(11) NOT NULL,
  `teacher_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_class_stream` (`class_id`,`stream_name`),
  KEY `idx_teacher` (`teacher_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `class_streams`:
--   `class_id`
--       `classes` -> `id`
--   `teacher_id`
--       `staff` -> `id`
--

--
-- Truncate table before insert `class_streams`
--

TRUNCATE TABLE `class_streams`;
--
-- Triggers `class_streams`
--
DROP TRIGGER IF EXISTS `trg_manage_default_stream_on_delete`;
DELIMITER $$
CREATE TRIGGER `trg_manage_default_stream_on_delete` AFTER UPDATE ON `class_streams` FOR EACH ROW BEGIN
    DECLARE v_active_streams INT;
    DECLARE v_default_stream_id INT UNSIGNED;
    DECLARE v_class_name VARCHAR(50);
    
IF NEW.status = 'inactive'
AND OLD.status = 'active' THEN 
SELECT COUNT(*) INTO v_active_streams
FROM class_streams
WHERE class_id = NEW.class_id
  AND status = 'active'
  AND id != NEW.id;

IF v_active_streams = 0 THEN
SELECT cs.id INTO v_default_stream_id
FROM class_streams cs
  INNER JOIN classes c ON cs.class_id = c.id
WHERE cs.class_id = NEW.class_id
  AND cs.stream_name = c.name
  AND cs.status = 'inactive'
LIMIT 1;
IF v_default_stream_id IS NOT NULL THEN
UPDATE class_streams
SET status = 'active'
WHERE id = v_default_stream_id;
END IF;
END IF;
END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_manage_default_stream_on_insert`;
DELIMITER $$
CREATE TRIGGER `trg_manage_default_stream_on_insert` AFTER INSERT ON `class_streams` FOR EACH ROW BEGIN
    DECLARE v_total_streams INT;
    DECLARE v_default_stream_id INT UNSIGNED;
    DECLARE v_class_name VARCHAR(50);
    
    SELECT COUNT(*) INTO v_total_streams
    FROM class_streams
    WHERE class_id = NEW.class_id
      AND status = 'active';
    
    IF v_total_streams > 1 THEN 
        
        SELECT cs.id INTO v_default_stream_id
        FROM class_streams cs
        INNER JOIN classes c ON cs.class_id = c.id
        WHERE cs.class_id = NEW.class_id
          AND cs.stream_name = c.name
          AND cs.status = 'active'
        LIMIT 1;
        IF v_default_stream_id IS NOT NULL THEN
            UPDATE class_streams
            SET status = 'inactive'
            WHERE id = v_default_stream_id;
        END IF;
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_validate_class_capacity`;
DELIMITER $$
CREATE TRIGGER `trg_validate_class_capacity` BEFORE INSERT ON `class_streams` FOR EACH ROW BEGIN
    DECLARE current_count INT;
    SELECT COUNT(*) INTO current_count
    FROM students s
    WHERE s.stream_id = NEW.id;
    IF current_count >= NEW.capacity THEN 
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Class capacity exceeded';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `class_year_assignments`
--
-- Creation: Nov 11, 2025 at 10:58 AM
--

DROP TABLE IF EXISTS `class_year_assignments`;
CREATE TABLE IF NOT EXISTS `class_year_assignments` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `academic_year_id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL COMMENT 'References classes table',
  `stream_id` int(10) UNSIGNED NOT NULL COMMENT 'References class_streams table',
  `teacher_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Class teacher',
  `room_number` varchar(50) DEFAULT NULL,
  `capacity` int(11) DEFAULT 40,
  `current_enrollment` int(11) DEFAULT 0,
  `fee_structure_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Specific fees for this class this year',
  `status` enum('planning','active','completed') DEFAULT 'planning',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_class_stream_year` (`academic_year_id`,`class_id`,`stream_id`),
  KEY `idx_academic_year` (`academic_year_id`),
  KEY `idx_class_stream` (`class_id`,`stream_id`),
  KEY `idx_teacher` (`teacher_id`),
  KEY `idx_status` (`status`),
  KEY `stream_id` (`stream_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Class configuration per academic year - teacher, room, capacity';

--
-- RELATIONSHIPS FOR TABLE `class_year_assignments`:
--   `academic_year_id`
--       `academic_years` -> `id`
--   `class_id`
--       `classes` -> `id`
--   `stream_id`
--       `class_streams` -> `id`
--   `teacher_id`
--       `staff` -> `id`
--

--
-- Truncate table before insert `class_year_assignments`
--

TRUNCATE TABLE `class_year_assignments`;
-- --------------------------------------------------------

--
-- Table structure for table `communications`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `communications`;
CREATE TABLE IF NOT EXISTS `communications` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender_id` int(10) UNSIGNED DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `type` enum('email','sms','notification','internal') NOT NULL,
  `status` enum('draft','sent','scheduled','failed') NOT NULL DEFAULT 'draft',
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `template_id` int(10) UNSIGNED DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `idx_communication_dates` (`created_at`,`scheduled_at`),
  KEY `fk_comm_template` (`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `communications`:
--   `sender_id`
--       `users` -> `id`
--   `template_id`
--       `message_templates` -> `id`
--

--
-- Truncate table before insert `communications`
--

TRUNCATE TABLE `communications`;
-- --------------------------------------------------------

--
-- Table structure for table `communication_attachments`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `communication_attachments`;
CREATE TABLE IF NOT EXISTS `communication_attachments` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `communication_id` int(10) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `communication_id` (`communication_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `communication_attachments`:
--   `communication_id`
--       `communications` -> `id`
--

--
-- Truncate table before insert `communication_attachments`
--

TRUNCATE TABLE `communication_attachments`;
-- --------------------------------------------------------

--
-- Table structure for table `communication_groups`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `communication_groups`;
CREATE TABLE IF NOT EXISTS `communication_groups` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('staff','students','parents','custom') NOT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `communication_groups`:
--   `created_by`
--       `users` -> `id`
--

--
-- Truncate table before insert `communication_groups`
--

TRUNCATE TABLE `communication_groups`;
-- --------------------------------------------------------

--
-- Table structure for table `communication_logs`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `communication_logs`;
CREATE TABLE IF NOT EXISTS `communication_logs` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `communication_id` int(10) UNSIGNED NOT NULL,
  `recipient_id` int(10) UNSIGNED NOT NULL,
  `event_type` enum('queued','sent','delivered','failed','opened','clicked') NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_comm_recipient` (`communication_id`,`recipient_id`),
  KEY `idx_event_type` (`event_type`),
  KEY `recipient_id` (`recipient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `communication_logs`:
--   `communication_id`
--       `communications` -> `id`
--   `recipient_id`
--       `communication_recipients` -> `id`
--

--
-- Truncate table before insert `communication_logs`
--

TRUNCATE TABLE `communication_logs`;
-- --------------------------------------------------------

--
-- Table structure for table `communication_recipients`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `communication_recipients`;
CREATE TABLE IF NOT EXISTS `communication_recipients` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `communication_id` int(10) UNSIGNED NOT NULL,
  `recipient_id` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','delivered','failed') NOT NULL DEFAULT 'pending',
  `delivered_at` datetime DEFAULT NULL,
  `delivery_attempts` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `last_attempt_at` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL,
  `clicked_at` datetime DEFAULT NULL,
  `device_info` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `communication_id` (`communication_id`),
  KEY `recipient_id` (`recipient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `communication_recipients`:
--   `communication_id`
--       `communications` -> `id`
--   `recipient_id`
--       `users` -> `id`
--

--
-- Truncate table before insert `communication_recipients`
--

TRUNCATE TABLE `communication_recipients`;
-- --------------------------------------------------------

--

DROP TABLE IF EXISTS `communication_templates`;

-- Parent Portal Messaging (Inbox/Outbox)
DROP TABLE IF EXISTS `parent_portal_messages`;
CREATE TABLE IF NOT EXISTS `parent_portal_messages` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED DEFAULT NULL,
  `sender_type` enum('parent','school','staff','admin') NOT NULL,
  `sender_id` int(10) UNSIGNED NOT NULL,
  `recipient_type` enum('parent','school','staff','admin') NOT NULL,
  `recipient_id` int(10) UNSIGNED NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `status` enum('sent','read','archived','deleted') NOT NULL DEFAULT 'sent',
  `is_reply` tinyint(1) NOT NULL DEFAULT 0,
  `reply_to_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `student_id` (`student_id`),
  KEY `sender_id` (`sender_id`),
  KEY `recipient_id` (`recipient_id`),
  KEY `reply_to_id` (`reply_to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- External Inbound Messages (SMS, Email, etc)
DROP TABLE IF EXISTS `external_inbound_messages`;
CREATE TABLE IF NOT EXISTS `external_inbound_messages` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_type` enum('sms','email','web','other') NOT NULL,
  `source_address` varchar(255) NOT NULL,
  `received_at` datetime NOT NULL,
  `linked_user_id` int(10) UNSIGNED DEFAULT NULL,
  `linked_parent_id` int(10) UNSIGNED DEFAULT NULL,
  `linked_student_id` int(10) UNSIGNED DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `status` enum('pending','processed','archived','error') NOT NULL DEFAULT 'pending',
  `processing_notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `linked_user_id` (`linked_user_id`),
  KEY `linked_parent_id` (`linked_parent_id`),
  KEY `linked_student_id` (`linked_student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Forums and Threads (for bulletin boards, discussions)
DROP TABLE IF EXISTS `forum_threads`;
CREATE TABLE IF NOT EXISTS `forum_threads` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `forum_type` enum('general','class','staff','parent','custom') NOT NULL DEFAULT 'general',
  `status` enum('open','closed','archived') NOT NULL DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `forum_type` (`forum_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `forum_posts`;
CREATE TABLE IF NOT EXISTS `forum_posts` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `thread_id` int(10) UNSIGNED NOT NULL,
  `author_id` int(10) UNSIGNED NOT NULL,
  `author_type` enum('student','staff','parent','admin') NOT NULL,
  `body` text NOT NULL,
  `reply_to_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `thread_id` (`thread_id`),
  KEY `author_id` (`author_id`),
  KEY `reply_to_id` (`reply_to_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Contact Directory (for institution, staff, parents, students)
DROP TABLE IF EXISTS `contact_directory`;
CREATE TABLE IF NOT EXISTS `contact_directory` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `contact_type` enum('staff','student','parent','department','external') NOT NULL,
  `linked_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `role` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `contact_type` (`contact_type`),
  KEY `linked_id` (`linked_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Workflow Integration: Communication Approval/Escalation
DROP TABLE IF EXISTS `communication_workflow_instances`;
CREATE TABLE IF NOT EXISTS `communication_workflow_instances` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `communication_id` int(10) UNSIGNED NOT NULL,
  `workflow_code` varchar(50) NOT NULL,
  `current_stage` varchar(50) NOT NULL,
  `status` enum('active','completed','cancelled','escalated') NOT NULL DEFAULT 'active',
  `initiated_by` int(10) UNSIGNED NOT NULL,
  `initiated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `communication_id` (`communication_id`),
  KEY `workflow_code` (`workflow_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Trigger: Auto-log communication workflow initiation
DELIMITER $$
DROP TRIGGER IF EXISTS trg_auto_start_comm_workflow$$
CREATE TRIGGER trg_auto_start_comm_workflow
AFTER INSERT ON communications
FOR EACH ROW
BEGIN
  IF NEW.status = 'scheduled' OR NEW.status = 'sent' THEN
    INSERT INTO communication_workflow_instances (communication_id, workflow_code, current_stage, status, initiated_by, initiated_at)
    VALUES (NEW.id, 'communication_approval', 'initiated', 'active', NEW.sender_id, NOW());
  END IF;
END$$
DELIMITER ;
--
-- RELATIONSHIPS FOR TABLE `communication_templates`:
--   `created_by`
--       `staff` -> `id`
--

--
-- Truncate table before insert `communication_templates`
--

TRUNCATE TABLE `communication_templates`;
-- --------------------------------------------------------

--
-- Table structure for table `conduct_tracking`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `conduct_tracking`;
CREATE TABLE IF NOT EXISTS `conduct_tracking` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `academic_year` year(4) NOT NULL,
  `term_id` int(10) UNSIGNED NOT NULL,
  `conduct_rating` enum('excellent','good','satisfactory','needs_improvement','poor') NOT NULL DEFAULT 'good',
  `conduct_comments` text DEFAULT NULL,
  `behavior_incidents` text DEFAULT NULL COMMENT 'JSON array of incidents',
  `teacher_notes` text DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED NOT NULL,
  `recorded_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_conduct` (`student_id`,`academic_year`,`term_id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_rating` (`conduct_rating`),
  KEY `fk_ct_term` (`term_id`),
  KEY `fk_ct_recorded_by` (`recorded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `conduct_tracking`:
--   `recorded_by`
--       `users` -> `id`
--   `student_id`
--       `students` -> `id`
--   `term_id`
--       `academic_terms` -> `id`
--

--
-- Truncate table before insert `conduct_tracking`
--

TRUNCATE TABLE `conduct_tracking`;
--
-- Triggers `conduct_tracking`
--
DROP TRIGGER IF EXISTS `trg_log_conduct_recording`;
DELIMITER $$
CREATE TRIGGER `trg_log_conduct_recording` AFTER INSERT ON `conduct_tracking` FOR EACH ROW BEGIN
INSERT INTO system_events (event_type, event_data, created_at)
VALUES (
    'conduct_recorded',
    JSON_OBJECT(
      'student_id',
      NEW.student_id,
      'term_id',
      NEW.term_id,
      'conduct_rating',
      NEW.conduct_rating
    ),
    NOW()
  );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `conversation_participants`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `conversation_participants`;
CREATE TABLE IF NOT EXISTS `conversation_participants` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` int(10) UNSIGNED NOT NULL,
  `participant_id` int(10) UNSIGNED NOT NULL,
  `unread_count` int(11) NOT NULL DEFAULT 0,
  `last_read_at` datetime DEFAULT NULL,
  `is_muted` tinyint(1) NOT NULL DEFAULT 0,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `left_at` datetime DEFAULT NULL,
  `role` enum('participant','moderator','admin') NOT NULL DEFAULT 'participant',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_conversation_participant` (`conversation_id`,`participant_id`),
  KEY `participant_id` (`participant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `conversation_participants`:
--   `conversation_id`
--       `internal_conversations` -> `id`
--   `participant_id`
--       `staff` -> `id`
--

--
-- Truncate table before insert `conversation_participants`
--

TRUNCATE TABLE `conversation_participants`;
-- --------------------------------------------------------

--
-- Table structure for table `core_competencies`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `core_competencies`;
CREATE TABLE IF NOT EXISTS `core_competencies` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `grade_range` varchar(50) DEFAULT NULL COMMENT 'PP1-9 applicable grades',
  `learning_outcomes` text DEFAULT NULL,
  `assessment_criteria` text DEFAULT NULL COMMENT 'JSON array of assessment points',
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `core_competencies`:
--

--
-- Truncate table before insert `core_competencies`
--

TRUNCATE TABLE `core_competencies`;
-- --------------------------------------------------------

--
-- Table structure for table `core_values`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `core_values`;
CREATE TABLE IF NOT EXISTS `core_values` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `behavioral_indicators` text DEFAULT NULL COMMENT 'JSON array of indicators',
  `grade_range` varchar(50) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `core_values`:
--

--
-- Truncate table before insert `core_values`
--

TRUNCATE TABLE `core_values`;
-- --------------------------------------------------------

--
-- Table structure for table `csl_activities`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `csl_activities`;
CREATE TABLE IF NOT EXISTS `csl_activities` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `activity_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `activity_date` date NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `beneficiary` varchar(255) DEFAULT NULL COMMENT 'Who/what benefits from activity',
  `impact_area` varchar(100) DEFAULT NULL COMMENT 'environment, health, community, education',
  `total_hours` int(11) DEFAULT 0,
  `organized_by` int(10) UNSIGNED NOT NULL,
  `status` enum('planned','ongoing','completed','cancelled') NOT NULL DEFAULT 'planned',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activity_date` (`activity_date`),
  KEY `idx_status` (`status`),
  KEY `fk_csl_organized_by` (`organized_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `csl_activities`:
--   `organized_by`
--       `users` -> `id`
--

--
-- Truncate table before insert `csl_activities`
--

TRUNCATE TABLE `csl_activities`;
-- --------------------------------------------------------

--
-- Table structure for table `curriculum_units`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `curriculum_units`;
CREATE TABLE IF NOT EXISTS `curriculum_units` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `learning_area_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `learning_outcomes` text DEFAULT NULL,
  `suggested_resources` text DEFAULT NULL,
  `duration` int(11) NOT NULL COMMENT 'Duration in hours',
  `order_sequence` int(11) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_learning_area_order` (`learning_area_id`,`order_sequence`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `curriculum_units`:
--   `learning_area_id`
--       `learning_areas` -> `id`
--

--
-- Truncate table before insert `curriculum_units`
--

TRUNCATE TABLE `curriculum_units`;
-- --------------------------------------------------------

--
-- Table structure for table `daily_meal_allocations`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `daily_meal_allocations`;
CREATE TABLE IF NOT EXISTS `daily_meal_allocations` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `allocation_date` date NOT NULL,
  `boarding_house_id` int(10) UNSIGNED DEFAULT NULL,
  `class_id` int(10) UNSIGNED DEFAULT NULL,
  `student_count` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_meal_allocation` (`allocation_date`,`boarding_house_id`,`class_id`),
  KEY `idx_date_house` (`allocation_date`,`boarding_house_id`),
  KEY `class_id` (`class_id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `daily_meal_allocations`:
--   `class_id`
--       `classes` -> `id`
--   `created_by`
--       `staff` -> `id`
--

--
-- Truncate table before insert `daily_meal_allocations`
--

TRUNCATE TABLE `daily_meal_allocations`;
-- --------------------------------------------------------

--
-- Table structure for table `deduction_types`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `deduction_types`;
CREATE TABLE IF NOT EXISTS `deduction_types` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `default_rate` decimal(5,2) DEFAULT NULL,
  `is_mandatory` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `deduction_types`:
--

--
-- Truncate table before insert `deduction_types`
--

TRUNCATE TABLE `deduction_types`;
-- --------------------------------------------------------

--
-- Table structure for table `departments`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `departments`;
CREATE TABLE IF NOT EXISTS `departments` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `head_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_head` (`head_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `departments`:
--   `head_id`
--       `staff` -> `id`
--

--
-- Truncate table before insert `departments`
--

TRUNCATE TABLE `departments`;
-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `drivers`;
CREATE TABLE IF NOT EXISTS `drivers` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_license_number` (`license_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `drivers`:
--

--
-- Truncate table before insert `drivers`
--

TRUNCATE TABLE `drivers`;
-- --------------------------------------------------------

--
-- Table structure for table `equipment_maintenance`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `equipment_maintenance`;
CREATE TABLE IF NOT EXISTS `equipment_maintenance` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `equipment_id` int(10) UNSIGNED NOT NULL,
  `maintenance_type_id` int(10) UNSIGNED NOT NULL,
  `last_maintenance_date` date DEFAULT NULL,
  `next_maintenance_date` date NOT NULL,
  `status` enum('pending','scheduled','in_progress','completed','cancelled','overdue') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_equipment_status` (`equipment_id`,`status`),
  KEY `maintenance_type_id` (`maintenance_type_id`),
  KEY `idx_next_date` (`next_maintenance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `equipment_maintenance`:
--   `equipment_id`
--       `item_serials` -> `id`
--   `maintenance_type_id`
--       `equipment_maintenance_types` -> `id`
--

--
-- Truncate table before insert `equipment_maintenance`
--

TRUNCATE TABLE `equipment_maintenance`;
--
-- Triggers `equipment_maintenance`
--
DROP TRIGGER IF EXISTS `trg_check_maintenance_overdue`;
DELIMITER $$
CREATE TRIGGER `trg_check_maintenance_overdue` BEFORE UPDATE ON `equipment_maintenance` FOR EACH ROW BEGIN 
    IF NEW.status = 'pending' AND NEW.next_maintenance_date < CURDATE() THEN
        SET NEW.status = 'overdue';
        INSERT INTO system_events (event_type, event_data, created_at)
        VALUES (
            'maintenance_overdue',
    JSON_OBJECT(
      'equipment_id',
      NEW.equipment_id,
      'maintenance_type_id',
      NEW.maintenance_type_id
    ),
    NOW()
  );
END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `equipment_maintenance_types`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `equipment_maintenance_types`;
CREATE TABLE IF NOT EXISTS `equipment_maintenance_types` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `frequency_days` int(11) DEFAULT NULL,
  `estimated_cost` decimal(10,2) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `equipment_maintenance_types`:
--

--
-- Truncate table before insert `equipment_maintenance_types`
--

TRUNCATE TABLE `equipment_maintenance_types`;
-- --------------------------------------------------------

--
-- Table structure for table `exam_schedules`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `exam_schedules`;
CREATE TABLE IF NOT EXISTS `exam_schedules` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `exam_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room_id` int(10) UNSIGNED DEFAULT NULL,
  `invigilator_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `subject_id` (`subject_id`),
  KEY `room_id` (`room_id`),
  KEY `invigilator_id` (`invigilator_id`),
  KEY `idx_exam_schedule_datetime` (`exam_date`,`start_time`,`end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `exam_schedules`:
--   `class_id`
--       `classes` -> `id`
--   `subject_id`
--       `curriculum_units` -> `id`
--   `room_id`
--       `rooms` -> `id`
--   `invigilator_id`
--       `staff` -> `id`
--

--
-- Truncate table before insert `exam_schedules`
--

TRUNCATE TABLE `exam_schedules`;
-- --------------------------------------------------------

--
-- Table structure for table `external_emails`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `external_emails`;
CREATE TABLE IF NOT EXISTS `external_emails` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `institution_id` int(10) UNSIGNED DEFAULT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `email_body` longtext NOT NULL,
  `template_id` int(10) UNSIGNED DEFAULT NULL,
  `email_type` enum('inquiry','report','application','information','request','other') NOT NULL DEFAULT 'information',
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `status` enum('draft','queued','sent','delivered','failed','bounced') NOT NULL DEFAULT 'draft',
  `sent_by` int(10) UNSIGNED NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `bounced_at` datetime DEFAULT NULL,
  `failure_reason` text DEFAULT NULL,
  `external_reference_id` varchar(255) DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL,
  `clicked_links` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `institution_id` (`institution_id`),
  KEY `idx_status` (`status`),
  KEY `idx_email_type` (`email_type`),
  KEY `idx_recipient_email` (`recipient_email`),
  KEY `sent_by` (`sent_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `external_emails`:
--   `institution_id`
--       `external_institutions` -> `id`
--   `sent_by`
--       `staff` -> `id`
--

--
-- Truncate table before insert `external_emails`
--

TRUNCATE TABLE `external_emails`;
--
-- Triggers `external_emails`
--
DROP TRIGGER IF EXISTS `trg_log_email_delivery`;
DELIMITER $$
CREATE TRIGGER `trg_log_email_delivery` AFTER UPDATE ON `external_emails` FOR EACH ROW BEGIN IF OLD.status <> NEW.status THEN
INSERT INTO system_events (event_type, event_data, created_at)
VALUES (
    'email_status_changed',
    JSON_OBJECT(
      'email_id',
      NEW.id,
      'old_status',
      OLD.status,
      'new_status',
      NEW.status,
      'institution_id',
      NEW.institution_id
    ),
    NOW()
  );
IF NEW.status = 'failed' THEN
UPDATE external_institutions
SET last_failed_email = NOW()
WHERE id = NEW.institution_id;
END IF;
END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--

DROP TABLE IF EXISTS `external_institutions`;
CREATE TABLE IF NOT EXISTS `external_institutions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `institution_type` enum('government','ngo','school','university','supplier','other') NOT NULL DEFAULT 'other',
  `email_addresses` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`email_addresses`)),
  `phone_numbers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`phone_numbers`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
--
--
-- Table structure for table `activity_schedules`
--
-- Creation: Nov 14, 2025 at 04:30 PM
--

DROP TABLE IF EXISTS `activity_schedules`;
CREATE TABLE IF NOT EXISTS `activity_schedules` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_id` int(10) UNSIGNED DEFAULT NULL,
  `staff_id` int(10) UNSIGNED DEFAULT NULL,
  `activity_id` int(10) UNSIGNED DEFAULT NULL,
  `venue_id` int(10) UNSIGNED DEFAULT NULL,
  `day_of_week` varchar(16) DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `term_id` int(10) UNSIGNED DEFAULT NULL,
  `academic_year` year DEFAULT NULL,
  `status` varchar(32) DEFAULT 'scheduled',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_class_id` (`class_id`),
  KEY `idx_staff_id` (`staff_id`),
  KEY `idx_activity_id` (`activity_id`),
  KEY `idx_venue_id` (`venue_id`),
  KEY `idx_day_of_week` (`day_of_week`),
  KEY `idx_term_year` (`term_id`, `academic_year`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `activity_schedules`:
--   `class_id` -> `classes`.`id`
--   `staff_id` -> `staff`.`id`
--   `activity_id` -> `activities`.`id`
--   `venue_id` -> `rooms`.`id`
--

--
-- Truncate table before insert `activity_schedules`
--

TRUNCATE TABLE `activity_schedules`;
-- --------------------------------------------------------
-- RELATIONSHIPS FOR TABLE `external_institutions`:
-- Truncate table before insert `external_institutions`
--

TRUNCATE TABLE `external_institutions`;
-- --------------------------------------------------------

--
-- Table structure for table `failed_auth_attempts`
--
-- Creation: Nov 14, 2025 at 04:25 PM
--

DROP TABLE IF EXISTS `failed_auth_attempts`;
CREATE TABLE IF NOT EXISTS `failed_auth_attempts` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `reason` varchar(255) NOT NULL COMMENT 'Why authentication failed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ip_created` (`ip_address`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Failed authentication attempts for auto-blocking';

--
-- RELATIONSHIPS FOR TABLE `failed_auth_attempts`:
--

--
-- Truncate table before insert `failed_auth_attempts`
--

TRUNCATE TABLE `failed_auth_attempts`;
-- --------------------------------------------------------

--
-- Table structure for table `fee_discounts_waivers`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `fee_discounts_waivers`;
CREATE TABLE IF NOT EXISTS `fee_discounts_waivers` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `student_fee_obligation_id` int(10) UNSIGNED DEFAULT NULL,
  `discount_type` enum('percentage','fixed_amount','full_waiver','merit','need_based','sibling','other') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `discount_percentage` decimal(5,2) DEFAULT NULL,
  `reason` text NOT NULL,
  `academic_year` year(4) NOT NULL,
  `term_id` int(10) UNSIGNED DEFAULT NULL,
  `approved_by` int(10) UNSIGNED NOT NULL,
  `approved_date` datetime NOT NULL,
  `status` enum('active','expired','cancelled') NOT NULL DEFAULT 'active',
  `valid_until` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student_year` (`student_id`,`academic_year`),
  KEY `idx_obligation` (`student_fee_obligation_id`),
  KEY `idx_type` (`discount_type`),
  KEY `idx_status` (`status`),
  KEY `idx_approved_by` (`approved_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `fee_discounts_waivers`:
--

--
-- Truncate table before insert `fee_discounts_waivers`
--

TRUNCATE TABLE `fee_discounts_waivers`;
--
-- Triggers `fee_discounts_waivers`
--
DROP TRIGGER IF EXISTS `trg_log_discount_waiver`;
DELIMITER $$
CREATE TRIGGER `trg_log_discount_waiver` AFTER INSERT ON `fee_discounts_waivers` FOR EACH ROW BEGIN
    INSERT INTO system_events (event_type, event_data, created_at)
    VALUES (
        'discount_applied',
        JSON_OBJECT(
            'student_id', NEW.student_id,
            'type', NEW.discount_type,
            'value', NEW.discount_value,
            'reason', NEW.reason,
            'approved_by', NEW.approved_by
        ),
        NOW()
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `fee_reminders`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `fee_reminders`;
CREATE TABLE IF NOT EXISTS `fee_reminders` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `academic_year` year(4) NOT NULL,
  `term_id` int(10) UNSIGNED NOT NULL,
  `reminder_type` enum('pre_due','due_date','overdue','arrears','settlement_plan') NOT NULL,
  `outstanding_amount` decimal(10,2) NOT NULL,
  `sent_date` datetime NOT NULL,
  `delivery_method` enum('sms','email','both','manual') NOT NULL,
  `status` enum('sent','bounced','acknowledged') NOT NULL DEFAULT 'sent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student_term` (`student_id`,`academic_year`,`term_id`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_type` (`reminder_type`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `fee_reminders`:
--

--
-- Truncate table before insert `fee_reminders`
--

TRUNCATE TABLE `fee_reminders`;
-- --------------------------------------------------------

--
-- Table structure for table `fee_structures`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `fee_structures`;
CREATE TABLE IF NOT EXISTS `fee_structures` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `due_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `fee_structures`:
--

--
-- Truncate table before insert `fee_structures`
--

TRUNCATE TABLE `fee_structures`;
-- --------------------------------------------------------

--
-- Table structure for table `fee_structures_detailed`
--
-- Creation: Nov 12, 2025 at 01:07 PM
--

DROP TABLE IF EXISTS `fee_structures_detailed`;
CREATE TABLE IF NOT EXISTS `fee_structures_detailed` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `level_id` int(10) UNSIGNED NOT NULL,
  `academic_year` year(4) NOT NULL,
  `term_id` int(10) UNSIGNED NOT NULL,
  `student_type_id` int(10) UNSIGNED NOT NULL,
  `fee_type_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('draft','pending_review','reviewed','approved','active','archived') DEFAULT 'draft',
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `activated_at` datetime DEFAULT NULL,
  `is_auto_rollover` tinyint(1) DEFAULT 0,
  `copied_from_id` int(10) UNSIGNED DEFAULT NULL,
  `rollover_notes` text DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fee_structure` (`level_id`,`academic_year`,`term_id`,`student_type_id`,`fee_type_id`),
  KEY `idx_level_year_term` (`level_id`,`academic_year`,`term_id`),
  KEY `idx_student_type` (`student_type_id`),
  KEY `idx_fee_type` (`fee_type_id`),
  KEY `idx_term` (`term_id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_status` (`status`),
  KEY `idx_academic_year_status` (`academic_year`,`status`),
  KEY `idx_level_year` (`level_id`,`academic_year`),
  KEY `fk_reviewed_by` (`reviewed_by`),
  KEY `fk_approved_by` (`approved_by`),
  KEY `fk_copied_from` (`copied_from_id`),
  KEY `idx_fee_structure_year_level_type` (`academic_year`,`level_id`,`fee_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `fee_structures_detailed`:
--   `approved_by`
--       `users` -> `id`
--   `copied_from_id`
--       `fee_structures_detailed` -> `id`
--   `reviewed_by`
--       `users` -> `id`
--

--
-- Truncate table before insert `fee_structures_detailed`
--

TRUNCATE TABLE `fee_structures_detailed`;
-- --------------------------------------------------------

--
-- Table structure for table `fee_structure_change_log`
--
-- Creation: Nov 12, 2025 at 01:01 PM
--

DROP TABLE IF EXISTS `fee_structure_change_log`;
CREATE TABLE IF NOT EXISTS `fee_structure_change_log` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `fee_structure_detail_id` int(10) UNSIGNED NOT NULL,
  `changed_by` int(10) UNSIGNED DEFAULT NULL,
  `change_type` enum('created','updated','reviewed','approved','activated','archived','rollover') NOT NULL,
  `old_amount` decimal(10,2) DEFAULT NULL,
  `new_amount` decimal(10,2) DEFAULT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `change_notes` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fee_structure` (`fee_structure_detail_id`),
  KEY `idx_change_type` (`change_type`),
  KEY `idx_changed_by` (`changed_by`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- RELATIONSHIPS FOR TABLE `fee_structure_change_log`:
--

--
-- Truncate table before insert `fee_structure_change_log`
--

TRUNCATE TABLE `fee_structure_change_log`;
-- --------------------------------------------------------

--
-- Table structure for table `fee_structure_rollover_log`
--
-- Creation: Nov 12, 2025 at 12:59 PM
--

DROP TABLE IF EXISTS `fee_structure_rollover_log`;
CREATE TABLE IF NOT EXISTS `fee_structure_rollover_log` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_academic_year` year(4) NOT NULL,
  `target_academic_year` year(4) NOT NULL,
  `rollover_date` datetime DEFAULT current_timestamp(),
  `executed_by` int(10) UNSIGNED DEFAULT NULL,
  `structures_copied` int(11) DEFAULT 0,
  `notification_sent` tinyint(1) DEFAULT 0,
  `notification_sent_at` datetime DEFAULT NULL,
  `notification_recipients` text DEFAULT NULL,
  `rollover_status` enum('pending','in_progress','completed','failed') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_rollover_executor` (`executed_by`),
  KEY `idx_target_year` (`target_academic_year`),
  KEY `idx_source_year` (`source_academic_year`),
  KEY `idx_rollover_status` (`rollover_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- RELATIONSHIPS FOR TABLE `fee_structure_rollover_log`:
--   `executed_by`
--       `users` -> `id`
--

--
-- Truncate table before insert `fee_structure_rollover_log`
--

TRUNCATE TABLE `fee_structure_rollover_log`;
-- --------------------------------------------------------

--
-- Table structure for table `fee_structure_rollover_schedule`
--
-- Creation: Nov 12, 2025 at 01:01 PM
--

DROP TABLE IF EXISTS `fee_structure_rollover_schedule`;
CREATE TABLE IF NOT EXISTS `fee_structure_rollover_schedule` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `academic_year_id` int(10) UNSIGNED NOT NULL,
  `scheduled_date` date NOT NULL,
  `review_deadline` date DEFAULT NULL,
  `executed` tinyint(1) DEFAULT 0,
  `executed_at` datetime DEFAULT NULL,
  `notification_days_before` int(11) DEFAULT 7,
  `reminder_sent` tinyint(1) DEFAULT 0,
  `reminder_sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_year_schedule` (`academic_year_id`),
  KEY `idx_scheduled_date` (`scheduled_date`),
  KEY `idx_executed` (`executed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- RELATIONSHIPS FOR TABLE `fee_structure_rollover_schedule`:
--

--
-- Truncate table before insert `fee_structure_rollover_schedule`
--

TRUNCATE TABLE `fee_structure_rollover_schedule`;
-- --------------------------------------------------------

--
-- Table structure for table `fee_transition_history`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `fee_transition_history`;
CREATE TABLE IF NOT EXISTS `fee_transition_history` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `from_academic_year` int(11) NOT NULL,
  `to_academic_year` int(11) NOT NULL,
  `from_term_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'For term transitions',
  `to_term_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'For term transitions',
  `balance_action` enum('fresh_bill','add_to_current','deduct_from_current','manual_adjustment') NOT NULL,
  `amount_transferred` decimal(12,2) DEFAULT 0.00 COMMENT 'Amount transferred/adjusted',
  `previous_balance` decimal(12,2) DEFAULT 0.00 COMMENT 'Balance before adjustment',
  `new_balance` decimal(12,2) DEFAULT 0.00 COMMENT 'Balance after adjustment',
  `created_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'User ID of person who triggered transition',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_year_transition` (`from_academic_year`,`to_academic_year`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `fee_transition_history`:
--   `student_id`
--       `students` -> `id`
--

--
-- Truncate table before insert `fee_transition_history`
--

TRUNCATE TABLE `fee_transition_history`;
-- --------------------------------------------------------

--
-- Table structure for table `fee_types`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `fee_types`;
CREATE TABLE IF NOT EXISTS `fee_types` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` enum('tuition','boarding','activity','infrastructure','other') NOT NULL,
  `is_mandatory` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_code` (`code`),
  KEY `idx_category` (`category`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `fee_types`:
--

--
-- Truncate table before insert `fee_types`
--

TRUNCATE TABLE `fee_types`;
-- --------------------------------------------------------

--
-- Table structure for table `financial_periods`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `financial_periods`;
CREATE TABLE IF NOT EXISTS `financial_periods` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','closed') NOT NULL DEFAULT 'active',
  `closed_by` int(10) UNSIGNED DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_date_range` (`start_date`,`end_date`),
  KEY `idx_status` (`status`),
  KEY `closed_by` (`closed_by`),
  KEY `idx_financial_period_status` (`status`,`start_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `financial_periods`:
--   `closed_by`
--       `users` -> `id`
--

--
-- Truncate table before insert `financial_periods`
--

TRUNCATE TABLE `financial_periods`;
-- --------------------------------------------------------

--
-- Table structure for table `financial_transactions`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `financial_transactions`;
CREATE TABLE IF NOT EXISTS `financial_transactions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `transaction_date` datetime NOT NULL,
  `processed_by` int(10) UNSIGNED DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `reconciliation_status` enum('pending','reconciled','disputed') NOT NULL DEFAULT 'pending',
  `reconciled_by` int(10) UNSIGNED DEFAULT NULL,
  `reconciled_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `processed_by` (`processed_by`),
  KEY `reconciled_by` (`reconciled_by`),
  KEY `idx_transaction_date` (`transaction_date`),
  KEY `idx_reconciliation_status` (`reconciliation_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `financial_transactions`:
--   `processed_by`
--       `users` -> `id`
--   `reconciled_by`
--       `users` -> `id`
--

--
-- Truncate table before insert `financial_transactions`
--

TRUNCATE TABLE `financial_transactions`;
--
-- Triggers `financial_transactions`
--
DROP TRIGGER IF EXISTS `trg_emit_payment_event`;
DELIMITER $$
CREATE TRIGGER `trg_emit_payment_event` AFTER INSERT ON `financial_transactions` FOR EACH ROW BEGIN
    INSERT INTO system_events (event_type, event_data, created_at)
    VALUES (
        'payment_received',
        JSON_OBJECT(
            'transaction_id', NEW.id,
            'amount', NEW.amount,
            'payment_method', NEW.payment_method,
            'student_id', (
                SELECT student_id
                FROM school_transactions
                WHERE reference_no = NEW.reference_no
                LIMIT 1
            )
        ), 
        NOW()
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `food_consumption_records`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `food_consumption_records`;
CREATE TABLE IF NOT EXISTS `food_consumption_records` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `consumption_date` date NOT NULL,
  `meal_plan_id` int(10) UNSIGNED DEFAULT NULL,
  `inventory_item_id` int(10) UNSIGNED NOT NULL,
  `quantity_planned` decimal(10,2) NOT NULL,
  `quantity_used` decimal(10,2) DEFAULT 0.00,
  `unit` varchar(20) NOT NULL,
  `waste_quantity` decimal(10,2) DEFAULT 0.00,
  `cost_per_unit` decimal(10,2) DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED NOT NULL,
  `recorded_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_consumption_date` (`consumption_date`),
  KEY `meal_plan_id` (`meal_plan_id`),
  KEY `inventory_item_id` (`inventory_item_id`),
  KEY `recorded_by` (`recorded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `food_consumption_records`:
--   `meal_plan_id`
--       `meal_plans` -> `id`
--   `inventory_item_id`
--       `inventory_items` -> `id`
--   `recorded_by`
--       `staff` -> `id`
--

--
-- Truncate table before insert `food_consumption_records`
--

TRUNCATE TABLE `food_consumption_records`;
--
-- Triggers `food_consumption_records`
--
DROP TRIGGER IF EXISTS `trg_check_meal_plan_completion`;
DELIMITER $$
CREATE TRIGGER `trg_check_meal_plan_completion` AFTER INSERT ON `food_consumption_records` FOR EACH ROW BEGIN
    DECLARE v_plan_id INT UNSIGNED;
    DECLARE v_recorded_count INT;
    DECLARE v_expected_count INT;
    SET v_plan_id = NEW.meal_plan_id;
IF v_plan_id IS NOT NULL THEN
SELECT COUNT(DISTINCT inventory_item_id) INTO v_recorded_count
FROM food_consumption_records
WHERE meal_plan_id = v_plan_id;
SELECT COUNT(*) INTO v_expected_count
FROM menu_item_ingredients
WHERE menu_item_id = (
    SELECT menu_item_id
    FROM meal_plans
    WHERE id = v_plan_id
  );
IF v_recorded_count = v_expected_count
AND v_recorded_count > 0 THEN
UPDATE meal_plans
SET status = 'served',
  updated_at = NOW()
WHERE id = v_plan_id;
END IF;
END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_log_food_consumption`;
DELIMITER $$
CREATE TRIGGER `trg_log_food_consumption` AFTER INSERT ON `food_consumption_records` FOR EACH ROW BEGIN
    INSERT INTO audit_trail (
        user_id,
        action,
        table_name,
    record_id,
    new_values,
    created_at
  )
VALUES (
    NEW.recorded_by,
    'INSERT',
    'food_consumption_records',
    NEW.id,
    JSON_OBJECT(
      'item_id',
      NEW.inventory_item_id,
      'quantity_used',
      NEW.quantity_used,
      'consumption_date',
      NEW.consumption_date
    ),
    NOW()
  );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `form_permissions`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `form_permissions`;
CREATE TABLE IF NOT EXISTS `form_permissions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `form_code` varchar(50) NOT NULL,
  `form_name` varchar(100) NOT NULL,
  `form_description` text DEFAULT NULL,
  `module_name` varchar(50) DEFAULT NULL,
  `actions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`actions`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`form_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `form_permissions`:
--

--
-- Truncate table before insert `form_permissions`
--

TRUNCATE TABLE `form_permissions`;
-- --------------------------------------------------------

--
-- Table structure for table `grade_rules`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `grade_rules`;
CREATE TABLE IF NOT EXISTS `grade_rules` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `scale_id` int(10) UNSIGNED NOT NULL,
  `grade_code` varchar(4) NOT NULL,
  `grade_name` varchar(50) NOT NULL,
  `min_mark` decimal(5,2) NOT NULL,
  `max_mark` decimal(5,2) NOT NULL,
  `grade_points` decimal(3,1) NOT NULL,
  `performance_level` varchar(50) NOT NULL COMMENT 'Exceeding, Meeting, Approaching, Below Expectation',
  `description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_scale_grade` (`scale_id`,`grade_code`),
  KEY `idx_scale_marks` (`scale_id`,`min_mark`,`max_mark`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `grade_rules`:
--   `scale_id`
--       `grading_scales` -> `id`
--

--
-- Truncate table before insert `grade_rules`
--

TRUNCATE TABLE `grade_rules`;
-- --------------------------------------------------------

--
-- Table structure for table `grading_comments`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `grading_comments`;
CREATE TABLE IF NOT EXISTS `grading_comments` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `grade_code` varchar(4) NOT NULL,
  `comment` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_grade_code` (`grade_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `grading_comments`:
--

--
-- Truncate table before insert `grading_comments`
--

TRUNCATE TABLE `grading_comments`;
-- --------------------------------------------------------

--
-- Table structure for table `grading_scales`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `grading_scales`;
CREATE TABLE IF NOT EXISTS `grading_scales` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `min_mark` decimal(5,2) NOT NULL DEFAULT 0.00,
  `max_mark` decimal(5,2) NOT NULL DEFAULT 100.00,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `grading_scales`:
--

--
-- Truncate table before insert `grading_scales`
--

TRUNCATE TABLE `grading_scales`;
-- --------------------------------------------------------

--
-- Table structure for table `group_members`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `group_members`;
CREATE TABLE IF NOT EXISTS `group_members` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `added_by` int(10) UNSIGNED NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_group_user` (`group_id`,`user_id`),
  KEY `user_id` (`user_id`),
  KEY `added_by` (`added_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `group_members`:
--   `group_id`
--       `communication_groups` -> `id`
--   `user_id`
--       `users` -> `id`
--   `added_by`
--       `users` -> `id`
--

--
-- Truncate table before insert `group_members`
--

TRUNCATE TABLE `group_members`;
-- --------------------------------------------------------

--
-- Table structure for table `ieps`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `ieps`;
CREATE TABLE IF NOT EXISTS `ieps` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `academic_year` year(4) NOT NULL,
  `iep_type` varchar(50) DEFAULT NULL COMMENT 'gifted, special_needs, intervention, etc',
  `special_needs_category` varchar(100) DEFAULT NULL,
  `goals_summary` text NOT NULL,
  `strategies` text DEFAULT NULL,
  `accommodations` text DEFAULT NULL,
  `progress_monitoring_plan` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_date` date DEFAULT NULL,
  `status` enum('draft','active','completed','archived') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student_year` (`student_id`,`academic_year`),
  KEY `idx_status` (`status`),
  KEY `fk_iep_created_by` (`created_by`),
  KEY `fk_iep_approved_by` (`approved_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `ieps`:
--   `approved_by`
--       `users` -> `id`
--   `created_by`
--       `users` -> `id`
--   `student_id`
--       `students` -> `id`
--

--
-- Truncate table before insert `ieps`
--

TRUNCATE TABLE `ieps`;
-- --------------------------------------------------------

--
-- Table structure for table `internal_conversations`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `internal_conversations`;
CREATE TABLE IF NOT EXISTS `internal_conversations` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `conversation_type` enum('one_on_one','group','department','broadcast') NOT NULL DEFAULT 'one_on_one',
  `created_by` int(10) UNSIGNED NOT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT 0,
  `last_message_at` datetime DEFAULT NULL,
  `last_message_by` int(10) UNSIGNED DEFAULT NULL,
  `participant_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_conversation_type` (`conversation_type`),
  KEY `idx_last_message` (`last_message_at`),
  KEY `internal_conversations_ibfk_2` (`last_message_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `internal_conversations`:
--   `created_by`
--       `staff` -> `id`
--   `last_message_by`
--       `staff` -> `id`
--

--
-- Truncate table before insert `internal_conversations`
--

TRUNCATE TABLE `internal_conversations`;
-- --------------------------------------------------------

--

CREATE OR REPLACE VIEW vw_master_schedule AS
SELECT 'class' AS schedule_type, cs.id AS schedule_id, cs.class_id, cs.teacher_id, cs.room_id, cs.day_of_week, cs.start_time, cs.end_time, cs.term_id, cs.academic_year, NULL AS exam_id, NULL AS activity_id
UNION ALL

SELECT 'activity', actsch.id, actsch.class_id, actsch.staff_id, actsch.venue_id, actsch.day_of_week, actsch.start_time, actsch.end_time, actsch.term_id, actsch.academic_year, NULL, actsch.activity_id
  FROM activity_schedules actsch;

-- Teacher schedule view
CREATE OR REPLACE VIEW vw_teacher_schedule AS
SELECT * FROM vw_master_schedule WHERE teacher_id IS NOT NULL;

-- Room schedule view
CREATE OR REPLACE VIEW vw_room_schedule AS
SELECT * FROM vw_master_schedule WHERE room_id IS NOT NULL;

-- Student schedule view (by class membership)
CREATE OR REPLACE VIEW vw_student_schedule AS
SELECT s.id AS student_id, ms.*
  FROM students s
  JOIN vw_master_schedule ms ON s.class_id = ms.class_id;

-- 2. PROCEDURES

-- Detect schedule conflicts (across teachers, rooms, students)
DELIMITER $$
DROP PROCEDURE IF EXISTS sp_detect_schedule_conflicts$$
CREATE PROCEDURE sp_detect_schedule_conflicts(IN p_start DATETIME, IN p_end DATETIME, IN p_room_id INT, IN p_teacher_id INT)
BEGIN
  -- Room conflict
  SELECT 'room' AS conflict_type, id, 'class_schedules' AS table_name FROM class_schedules
    WHERE room_id = p_room_id AND (
      (start_time < p_end AND end_time > p_start)
    )
  UNION ALL
  SELECT 'room', id, 'exam_schedules' FROM exam_schedules
    WHERE room_id = p_room_id AND (
      (start_time < p_end AND end_time > p_start)
    );
  -- Teacher conflict
  SELECT 'teacher', id, 'class_schedules' FROM class_schedules
    WHERE teacher_id = p_teacher_id AND (
      (start_time < p_end AND end_time > p_start)
    )
  UNION ALL
  SELECT 'teacher', id, 'exam_schedules' FROM exam_schedules
    WHERE invigilator_id = p_teacher_id AND (
      (start_time < p_end AND end_time > p_start)
    );
END$$
DELIMITER ;

-- Suggest optimal slot (find next available slot for a room/teacher)
DELIMITER $$
DROP PROCEDURE IF EXISTS sp_suggest_optimal_slot$$
CREATE PROCEDURE sp_suggest_optimal_slot(IN p_room_id INT, IN p_teacher_id INT, IN p_term_id INT, IN p_academic_year YEAR)
BEGIN
  -- Example: Suggest first free slot between 8am-5pm, Mon-Fri
  DECLARE v_day INT DEFAULT 1;
  DECLARE v_found INT DEFAULT 0;
  DECLARE v_start TIME;
  DECLARE v_end TIME;
  WHILE v_day <= 5 AND v_found = 0 DO
    SET v_start = '08:00:00';
    WHILE v_start < '17:00:00' AND v_found = 0 DO
      SET v_end = ADDTIME(v_start, '01:00:00');
      -- Check for conflicts
      CALL sp_detect_schedule_conflicts(CONCAT('2025-01-0', v_day, ' ', v_start), CONCAT('2025-01-0', v_day, ' ', v_end), p_room_id, p_teacher_id);
      -- If no rows returned, slot is free (pseudo, actual implementation may need a temp table or OUT param)
      -- For brevity, just output candidate slots
      SELECT v_day AS day_of_week, v_start AS start_time, v_end AS end_time;
      SET v_start = ADDTIME(v_start, '01:00:00');
    END WHILE;
    SET v_day = v_day + 1;
  END WHILE;
END$$
DELIMITER ;

-- Generate master schedule (aggregate all schedules for a term/year)
DELIMITER $$
DROP PROCEDURE IF EXISTS sp_generate_master_schedule$$
CREATE PROCEDURE sp_generate_master_schedule(IN p_term_id INT, IN p_academic_year YEAR)
BEGIN
  SELECT * FROM vw_master_schedule WHERE term_id = p_term_id AND academic_year = p_academic_year;
END$$
DELIMITER ;

-- Validate schedule compliance (e.g., min/max hours, required breaks)
DELIMITER $$
DROP PROCEDURE IF EXISTS sp_validate_schedule_compliance$$
CREATE PROCEDURE sp_validate_schedule_compliance(IN p_class_id INT, IN p_term_id INT, IN p_academic_year YEAR)
BEGIN
  -- Example: Check total scheduled hours per week
  SELECT class_id, SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time))/60 AS total_hours
    FROM class_schedules
    WHERE class_id = p_class_id AND term_id = p_term_id AND academic_year = p_academic_year
    GROUP BY class_id;
  -- Add more compliance checks as needed
END$$
DELIMITER ;

-- 3. TRIGGERS

-- Prevent double-booking in class_schedules
DELIMITER $$
DROP TRIGGER IF EXISTS trg_prevent_class_schedule_conflict$$
CREATE TRIGGER trg_prevent_class_schedule_conflict
BEFORE INSERT ON class_schedules
FOR EACH ROW
BEGIN
  IF EXISTS (
    SELECT 1 FROM class_schedules
      WHERE room_id = NEW.room_id
        AND day_of_week = NEW.day_of_week
        AND ((start_time < NEW.end_time AND end_time > NEW.start_time))
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Room is already booked for this time slot.';
  END IF;
  IF EXISTS (
    SELECT 1 FROM class_schedules
      WHERE teacher_id = NEW.teacher_id
        AND day_of_week = NEW.day_of_week
        AND ((start_time < NEW.end_time AND end_time > NEW.start_time))
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Teacher is already booked for this time slot.';
  END IF;
END$$
DELIMITER ;

-- Table structure for table `internal_messages`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `internal_messages`;
CREATE TABLE IF NOT EXISTS `internal_messages` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` int(10) UNSIGNED DEFAULT NULL,
  `sender_id` int(10) UNSIGNED NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message_body` longtext NOT NULL,
  `message_type` enum('personal','group','announcement') NOT NULL DEFAULT 'personal',
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `status` enum('draft','sent','read','archived','deleted') NOT NULL DEFAULT 'sent',
  `is_edited` tinyint(1) NOT NULL DEFAULT 0,
  `last_edited_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_conversation` (`conversation_id`),
  KEY `sender_id` (`sender_id`),
  KEY `idx_status` (`status`),
  KEY `idx_message_type` (`message_type`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `internal_messages`:
--   `sender_id`
--       `staff` -> `id`
--

--
-- Truncate table before insert `internal_messages`
--

TRUNCATE TABLE `internal_messages`;
-- --------------------------------------------------------

--
-- Table structure for table `inventory_adjustments`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `inventory_adjustments`;
CREATE TABLE IF NOT EXISTS `inventory_adjustments` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_id` int(10) UNSIGNED NOT NULL,
  `quantity_change` int(11) NOT NULL,
  `reason` enum('count_adjustment','damage','loss','found','other') NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `adjusted_by` int(10) UNSIGNED NOT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_item_date` (`item_id`,`created_at`),
  KEY `adjusted_by` (`adjusted_by`),
  KEY `approved_by` (`approved_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `inventory_adjustments`:
--   `item_id`
--       `inventory_items` -> `id`
--   `adjusted_by`
--       `staff` -> `id`
--   `approved_by`
--       `staff` -> `id`
--

--
-- Truncate table before insert `inventory_adjustments`
--

TRUNCATE TABLE `inventory_adjustments`;
-- --------------------------------------------------------

--
-- Table structure for table `inventory_allocations`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `inventory_allocations`;
CREATE TABLE IF NOT EXISTS `inventory_allocations` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `allocation_number` varchar(50) NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `allocated_quantity` int(11) NOT NULL,
  `allocated_to_department_id` int(10) UNSIGNED DEFAULT NULL,
  `allocated_to_event` varchar(100) DEFAULT NULL,
  `allocated_to_class_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('allocated','issued','partially_returned','fully_returned','expired','cancelled') NOT NULL DEFAULT 'allocated',
  `allocation_date` date NOT NULL,
  `expected_return_date` date DEFAULT NULL,
  `allocated_by` int(10) UNSIGNED NOT NULL,
  `issued_by` int(10) UNSIGNED DEFAULT NULL,
  `issued_at` datetime DEFAULT NULL,
  `returned_quantity` int(11) DEFAULT 0,
  `returned_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_allocation_number` (`allocation_number`),
  KEY `idx_item_status` (`item_id`,`status`),
  KEY `idx_department` (`allocated_to_department_id`),
  KEY `idx_class` (`allocated_to_class_id`),
  KEY `idx_dates` (`allocation_date`,`expected_return_date`),
  KEY `allocated_by` (`allocated_by`),
  KEY `issued_by` (`issued_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `inventory_allocations`:
--   `item_id`
--       `inventory_items` -> `id`
--   `allocated_to_department_id`
--       `inventory_departments` -> `id`
--   `allocated_to_class_id`
--       `classes` -> `id`
--   `allocated_by`
--       `staff` -> `id`
--   `issued_by`
--       `staff` -> `id`
--

--
-- Truncate table before insert `inventory_allocations`
--

TRUNCATE TABLE `inventory_allocations`;
--
-- Triggers `inventory_allocations`
--
DROP TRIGGER IF EXISTS `trg_audit_allocation_insert`;
DELIMITER $$
CREATE TRIGGER `trg_audit_allocation_insert` AFTER INSERT ON `inventory_allocations` FOR EACH ROW BEGIN
INSERT INTO audit_trail (
    user_id,
    action,
    table_name,
    record_id,
    new_values,
    created_at
  )
VALUES (
    NEW.allocated_by,
    'INSERT',
    'inventory_allocations',
    NEW.id,
    JSON_OBJECT(
      'allocation_number',
      NEW.allocation_number,
      'item_id',
      NEW.item_id,
      'quantity',
      NEW.allocated_quantity
    ),
    NOW()
  );
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_check_allocation_expiry`;
DELIMITER $$
CREATE TRIGGER `trg_check_allocation_expiry` BEFORE UPDATE ON `inventory_allocations` FOR EACH ROW BEGIN 
IF NEW.status = 'allocated'
  AND NEW.expected_return_date < CURDATE() THEN
INSERT INTO system_events (event_type, event_data, created_at)
VALUES (
    'allocation_expired',
    JSON_OBJECT(
      'allocation_id',
      NEW.id,
      'allocation_number',
      NEW.allocation_number
    ),
    NOW()
  );
END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_log_allocation_status`;
DELIMITER $$
CREATE TRIGGER `trg_log_allocation_status` AFTER UPDATE ON `inventory_allocations` FOR EACH ROW BEGIN 
    IF NEW.status != OLD.status THEN
        INSERT INTO audit_trail (
            user_id,
            action,
            table_name,
            record_id,
            old_values,
            new_values,
            created_at
        )
        VALUES (
            NULL,
            'UPDATE',
            'inventory_allocations',
            NEW.id,
            JSON_OBJECT('status', OLD.status),
            JSON_OBJECT(
                'status', NEW.status,
                'allocation_number', NEW.allocation_number
            ),
            NOW()
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_categories`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `inventory_categories`;
CREATE TABLE IF NOT EXISTS `inventory_categories` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `inventory_categories`:
--   `parent_id`
--       `inventory_categories` -> `id`
--

--
-- Truncate table before insert `inventory_categories`
--

TRUNCATE TABLE `inventory_categories`;
-- --------------------------------------------------------

--
-- Table structure for table `inventory_counts`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `inventory_counts`;
CREATE TABLE IF NOT EXISTS `inventory_counts` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `count_date` date NOT NULL,
  `status` enum('draft','in_progress','completed','cancelled') NOT NULL DEFAULT 'draft',
  `counted_by` int(10) UNSIGNED NOT NULL,
  `verified_by` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status_date` (`status`,`count_date`),
  KEY `counted_by` (`counted_by`),
  KEY `verified_by` (`verified_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `inventory_counts`:
--   `counted_by`
--       `staff` -> `id`
--   `verified_by`
--       `staff` -> `id`
--

--
-- Truncate table before insert `inventory_counts`
--

TRUNCATE TABLE `inventory_counts`;
-- --------------------------------------------------------

--
-- Table structure for table `inventory_count_items`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `inventory_count_items`;
CREATE TABLE IF NOT EXISTS `inventory_count_items` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `count_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `expected_quantity` int(11) NOT NULL,
  `actual_quantity` int(11) DEFAULT NULL,
  `difference` int(11) GENERATED ALWAYS AS (`actual_quantity` - `expected_quantity`) STORED,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_count_item` (`count_id`,`item_id`),
  KEY `item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `inventory_count_items`:
--   `count_id`
--       `inventory_counts` -> `id`
--   `item_id`
--       `inventory_items` -> `id`
--

--
-- Truncate table before insert `inventory_count_items`
--

TRUNCATE TABLE `inventory_count_items`;
-- --------------------------------------------------------

--
-- Table structure for table `inventory_departments`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `inventory_departments`;
CREATE TABLE IF NOT EXISTS `inventory_departments` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `department_head_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_status` (`status`),
  KEY `department_head_id` (`department_head_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `inventory_departments`:
--   `department_head_id`
--       `staff` -> `id`
--

--
-- Truncate table before insert `inventory_departments`
--

TRUNCATE TABLE `inventory_departments`;
-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `inventory_items`;
CREATE TABLE IF NOT EXISTS `inventory_items` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) NOT NULL,
  `barcode` varchar(50) DEFAULT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(20) NOT NULL,
  `minimum_quantity` int(11) NOT NULL DEFAULT 0,
  `current_quantity` int(11) NOT NULL DEFAULT 0,
  `unit_cost` decimal(10,2) NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive','out_of_stock') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `reorder_level` int(11) NOT NULL DEFAULT 0,
  `expiry_date` date DEFAULT NULL,
  `batch_tracking` tinyint(1) NOT NULL DEFAULT 0,
  `serial_tracking` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  UNIQUE KEY `uk_barcode` (`barcode`),
  UNIQUE KEY `uk_sku` (`sku`),
  KEY `idx_category` (`category_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `inventory_items`:
--   `category_id`
--       `inventory_categories` -> `id`
--

--
-- Truncate table before insert `inventory_items`
--

TRUNCATE TABLE `inventory_items`;
--
-- Triggers `inventory_items`
--
DROP TRIGGER IF EXISTS `trg_audit_inventory_insert`;
DELIMITER $$
CREATE TRIGGER `trg_audit_inventory_insert` AFTER INSERT ON `inventory_items` FOR EACH ROW BEGIN
    INSERT INTO audit_trail (
        user_id,
        action,
        table_name,
        record_id,
        new_values,
        created_at
    )
    VALUES (
        NULL,
        'INSERT',
        'inventory_items',
        NEW.id,
        JSON_OBJECT(
            'name', NEW.name,
            'code', NEW.code,
            'current_quantity', NEW.current_quantity,
            'status', NEW.status
        ),
        NOW()
    );
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_emit_low_stock_event`;
DELIMITER $$
CREATE TRIGGER `trg_emit_low_stock_event` AFTER UPDATE ON `inventory_items` FOR EACH ROW BEGIN 
    IF NEW.current_quantity <= NEW.minimum_quantity
       AND OLD.current_quantity > NEW.minimum_quantity THEN
        INSERT INTO system_events (event_type, event_data, created_at)
        VALUES (
            'inventory_low_stock',
            JSON_OBJECT(
                'item_id', NEW.id,
                'item_name', NEW.name,
                'quantity', NEW.current_quantity
            ),
            NOW()
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_requisitions`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `inventory_requisitions`;
CREATE TABLE IF NOT EXISTS `inventory_requisitions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `requisition_number` varchar(50) NOT NULL,
  `department_id` int(10) UNSIGNED NOT NULL,
  `requisition_date` date NOT NULL,
  `required_date` date NOT NULL,
  `status` enum('draft','submitted','pending_approval','approved','rejected','partially_fulfilled','fulfilled','cancelled') NOT NULL DEFAULT 'draft',
  `priority` enum('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  `reason` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `fulfilled_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_requisition_number` (`requisition_number`),
  KEY `idx_department_date` (`department_id`,`requisition_date`),
  KEY `idx_status` (`status`),
  KEY `idx_required_date` (`required_date`),
  KEY `created_by` (`created_by`),
  KEY `approved_by` (`approved_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `inventory_requisitions`:
--   `department_id`
--       `inventory_departments` -> `id`
--   `created_by`
--       `staff` -> `id`
--   `approved_by`
--       `staff` -> `id`
--

--
-- Truncate table before insert `inventory_requisitions`
--

TRUNCATE TABLE `inventory_requisitions`;
--
-- Triggers `inventory_requisitions`
--
DROP TRIGGER IF EXISTS `trg_log_requisition_status`;
DELIMITER $$
CREATE TRIGGER `trg_log_requisition_status` AFTER UPDATE ON `inventory_requisitions` FOR EACH ROW BEGIN 
    IF NEW.status != OLD.status THEN
        INSERT INTO audit_trail (
            user_id,
            action,
            table_name,
            record_id,
            old_values,
            new_values,
            created_at
        )
        VALUES (
            NULL,
            'UPDATE',
            'inventory_requisitions',
            NEW.id,
            JSON_OBJECT('status', OLD.status),
            JSON_OBJECT(
                'status', NEW.status,
                'requisition_number', NEW.requisition_number
            ),
            NOW()
        );
        
        INSERT INTO system_events (event_type, event_data, created_at)
        VALUES (
            CONCAT('requisition_', NEW.status),
            JSON_OBJECT(
                'requisition_id', NEW.id,
                'requisition_number', NEW.requisition_number
            ),
            NOW()
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transactions`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `inventory_transactions`;
CREATE TABLE IF NOT EXISTS `inventory_transactions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_id` int(10) UNSIGNED NOT NULL,
  `batch_id` int(10) UNSIGNED DEFAULT NULL,
  `serial_id` int(10) UNSIGNED DEFAULT NULL,
  `transaction_type` enum('in','out') NOT NULL,
  `quantity` int(11) NOT NULL,
  `transaction_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reference_type` enum('purchase','sale','adjustment','transfer') NOT NULL,
  `reference_id` int(10) UNSIGNED DEFAULT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`),
  KEY `batch_id` (`batch_id`),
  KEY `serial_id` (`serial_id`),
  KEY `idx_item_transaction_date` (`item_id`,`transaction_date`,`transaction_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `inventory_transactions`:
--   `item_id`
--       `inventory_items` -> `id`
--   `batch_id`
--       `item_batches` -> `id`
--   `serial_id`
--       `item_serials` -> `id`
--

--
-- Truncate table before insert `inventory_transactions`
--

TRUNCATE TABLE `inventory_transactions`;
-- --------------------------------------------------------

--
-- Table structure for table `item_batches`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `item_batches`;
CREATE TABLE IF NOT EXISTS `item_batches` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_id` int(10) UNSIGNED NOT NULL,
  `batch_number` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  `manufacturing_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `cost_price` decimal(10,2) NOT NULL,
  `supplier_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('active','expired','depleted') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_batch_number` (`batch_number`),
  KEY `idx_item_status` (`item_id`,`status`),
  KEY `supplier_id` (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `item_batches`:
--   `item_id`
--       `inventory_items` -> `id`
--   `supplier_id`
--       `suppliers` -> `id`
--

--
-- Truncate table before insert `item_batches`
--

TRUNCATE TABLE `item_batches`;
-- --------------------------------------------------------

--
-- Table structure for table `item_serials`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `item_serials`;
CREATE TABLE IF NOT EXISTS `item_serials` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `item_id` int(10) UNSIGNED NOT NULL,
  `serial_number` varchar(100) NOT NULL,
  `batch_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('in_stock','sold','defective') NOT NULL DEFAULT 'in_stock',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_serial_number` (`serial_number`),
  KEY `idx_item_status` (`item_id`,`status`),
  KEY `batch_id` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `item_serials`:
--   `item_id`
--       `inventory_items` -> `id`
--   `batch_id`
--       `item_batches` -> `id`
--

--
-- Truncate table before insert `item_serials`
--

TRUNCATE TABLE `item_serials`;
-- --------------------------------------------------------

--
-- Table structure for table `kpi_achievements`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `kpi_achievements`;
CREATE TABLE IF NOT EXISTS `kpi_achievements` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `kpi_definition_id` int(10) UNSIGNED NOT NULL,
  `academic_year` int(11) NOT NULL,
  `achieved_value` decimal(10,2) NOT NULL,
  `achievement_date` date NOT NULL,
  `recorded_by` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approval_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staff_year` (`staff_id`,`academic_year`),
  KEY `idx_kpi` (`kpi_definition_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `kpi_achievements`:
--   `kpi_definition_id`
--       `kpi_definitions` -> `id`
--   `staff_id`
--       `staff` -> `id`
--

--
-- Truncate table before insert `kpi_achievements`
--

TRUNCATE TABLE `kpi_achievements`;
-- --------------------------------------------------------

--
-- Table structure for table `kpi_definitions`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `kpi_definitions`;
CREATE TABLE IF NOT EXISTS `kpi_definitions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_category_id` int(10) UNSIGNED NOT NULL,
  `kpi_code` varchar(50) NOT NULL,
  `kpi_name` varchar(100) NOT NULL,
  `kpi_description` text DEFAULT NULL,
  `measurement_unit` varchar(50) DEFAULT NULL,
  `target_type` enum('individual','team','department') NOT NULL DEFAULT 'individual',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code_category` (`kpi_code`,`staff_category_id`),
  KEY `idx_category` (`staff_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `kpi_definitions`:
--   `staff_category_id`
--       `staff_categories` -> `id`
--

--
-- Truncate table before insert `kpi_definitions`
--

TRUNCATE TABLE `kpi_definitions`;
-- --------------------------------------------------------

--
-- Table structure for table `kpi_targets`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `kpi_targets`;
CREATE TABLE IF NOT EXISTS `kpi_targets` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `kpi_definition_id` int(10) UNSIGNED NOT NULL,
  `academic_year` int(11) NOT NULL,
  `target_value` decimal(10,2) NOT NULL,
  `weight_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `set_by` int(10) UNSIGNED DEFAULT NULL,
  `set_date` datetime NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staff_year` (`staff_id`,`academic_year`),
  KEY `idx_kpi` (`kpi_definition_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `kpi_targets`:
--   `kpi_definition_id`
--       `kpi_definitions` -> `id`
--   `staff_id`
--       `staff` -> `id`
--

--
-- Truncate table before insert `kpi_targets`
--

TRUNCATE TABLE `kpi_targets`;
-- --------------------------------------------------------

--
-- Table structure for table `learner_competencies`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `learner_competencies`;
CREATE TABLE IF NOT EXISTS `learner_competencies` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `competency_id` int(10) UNSIGNED NOT NULL,
  `academic_year` year(4) NOT NULL,
  `term_id` int(10) UNSIGNED NOT NULL,
  `performance_level_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK to performance_levels_cbc',
  `evidence` text DEFAULT NULL,
  `teacher_notes` text DEFAULT NULL,
  `assessed_by` int(10) UNSIGNED NOT NULL,
  `assessed_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_learner_competency` (`student_id`,`competency_id`,`academic_year`,`term_id`),
  KEY `idx_student_year` (`student_id`,`academic_year`),
  KEY `idx_competency` (`competency_id`),
  KEY `idx_performance_level` (`performance_level_id`),
  KEY `fk_lc_term` (`term_id`),
  KEY `fk_lc_assessed_by` (`assessed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `learner_competencies`:
--   `assessed_by`
--       `users` -> `id`
--   `competency_id`
--       `core_competencies` -> `id`
--   `performance_level_id`
--       `performance_levels_cbc` -> `id`
--   `student_id`
--       `students` -> `id`
--   `term_id`
--       `academic_terms` -> `id`
--

--
-- Truncate table before insert `learner_competencies`
--

TRUNCATE TABLE `learner_competencies`;
--
-- Triggers `learner_competencies`
--
DROP TRIGGER IF EXISTS `trg_log_competency_assessment`;
DELIMITER $$
CREATE TRIGGER `trg_log_competency_assessment` AFTER INSERT ON `learner_competencies` FOR EACH ROW BEGIN
INSERT INTO system_events (event_type, event_data, created_at)
VALUES (
    'competency_assessed',
    JSON_OBJECT(
      'student_id',
      NEW.student_id,
      'competency_id',
      NEW.competency_id,
      'level',
      (
        SELECT level
        FROM performance_levels_cbc
        WHERE id = NEW.performance_level_id
      )
    ),
    NOW()
  );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `learner_csl_participation`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `learner_csl_participation`;
CREATE TABLE IF NOT EXISTS `learner_csl_participation` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `csl_activity_id` int(10) UNSIGNED NOT NULL,
  `academic_year` year(4) NOT NULL,
  `hours_contributed` int(11) DEFAULT 0,
  `role` varchar(100) DEFAULT NULL COMMENT 'leader, participant, supporter, etc',
  `reflection` text DEFAULT NULL COMMENT 'Learners reflection on experience',
  `teacher_feedback` text DEFAULT NULL,
  `participation_status` enum('participated','completed','pending','excused') NOT NULL DEFAULT 'participated',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student_activity` (`student_id`,`csl_activity_id`,`academic_year`),
  KEY `idx_student_year` (`student_id`,`academic_year`),
  KEY `idx_activity` (`csl_activity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `learner_csl_participation`:
--   `csl_activity_id`
--       `csl_activities` -> `id`
--   `student_id`
--       `students` -> `id`
--

--
-- Truncate table before insert `learner_csl_participation`
--

TRUNCATE TABLE `learner_csl_participation`;
--
-- Triggers `learner_csl_participation`
--
DROP TRIGGER IF EXISTS `trg_log_csl_participation`;
DELIMITER $$
CREATE TRIGGER `trg_log_csl_participation` AFTER INSERT ON `learner_csl_participation` FOR EACH ROW BEGIN
INSERT INTO system_events (event_type, event_data, created_at)
VALUES (
    'csl_participation',
    JSON_OBJECT(
      'student_id',
      NEW.student_id,
      'activity_id',
      NEW.csl_activity_id,
      'hours',
      NEW.hours_contributed
    ),
    NOW()
  );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `learner_pci_awareness`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `learner_pci_awareness`;
CREATE TABLE IF NOT EXISTS `learner_pci_awareness` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `pci_id` int(10) UNSIGNED NOT NULL,
  `academic_year` year(4) NOT NULL,
  `term_id` int(10) UNSIGNED NOT NULL,
  `awareness_level` enum('unaware','aware','engaged','advocating') NOT NULL DEFAULT 'aware',
  `evidence` text DEFAULT NULL COMMENT 'Project, discussion, action taken',
  `learning_activity` varchar(255) DEFAULT NULL,
  `assessed_by` int(10) UNSIGNED NOT NULL,
  `assessed_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student_pci` (`student_id`,`pci_id`,`academic_year`,`term_id`),
  KEY `idx_student_year` (`student_id`,`academic_year`),
  KEY `idx_awareness` (`awareness_level`),
  KEY `fk_lpa_pci` (`pci_id`),
  KEY `fk_lpa_term` (`term_id`),
  KEY `fk_lpa_assessed_by` (`assessed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `learner_pci_awareness`:
--   `assessed_by`
--       `users` -> `id`
--   `pci_id`
--       `pcis` -> `id`
--   `student_id`
--       `students` -> `id`
--   `term_id`
--       `academic_terms` -> `id`
--

--
-- Truncate table before insert `learner_pci_awareness`
--

TRUNCATE TABLE `learner_pci_awareness`;
--
-- Triggers `learner_pci_awareness`
--
DROP TRIGGER IF EXISTS `trg_log_pci_awareness`;
DELIMITER $$
CREATE TRIGGER `trg_log_pci_awareness` AFTER INSERT ON `learner_pci_awareness` FOR EACH ROW BEGIN
INSERT INTO system_events (event_type, event_data, created_at)
VALUES (
    'pci_awareness',
    JSON_OBJECT(
      'student_id',
      NEW.student_id,
      'pci_id',
      NEW.pci_id,
      'awareness_level',
      NEW.awareness_level
    ),
    NOW()
  );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `learner_values_acquisition`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `learner_values_acquisition`;
CREATE TABLE IF NOT EXISTS `learner_values_acquisition` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `value_id` int(10) UNSIGNED NOT NULL,
  `academic_year` year(4) NOT NULL,
  `term_id` int(10) UNSIGNED NOT NULL,
  `evidence` text NOT NULL COMMENT 'Demonstrates value in action',
  `incident_date` date NOT NULL,
  `recorded_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student_value` (`student_id`,`value_id`,`academic_year`),
  KEY `idx_term` (`term_id`),
  KEY `idx_value` (`value_id`),
  KEY `fk_lva_recorded_by` (`recorded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `learner_values_acquisition`:
--   `recorded_by`
--       `users` -> `id`
--   `student_id`
--       `students` -> `id`
--   `term_id`
--       `academic_terms` -> `id`
--   `value_id`
--       `core_values` -> `id`
--

--
-- Truncate table before insert `learner_values_acquisition`
--

TRUNCATE TABLE `learner_values_acquisition`;


CREATE TABLE IF NOT EXISTS `user_roles` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (role_id) REFERENCES roles(id)
);


-- --------------------------------------------------------
-- Table structure for table `student_transport_assignments`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `student_transport_assignments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT UNSIGNED NOT NULL,
  `route_id` INT UNSIGNED NOT NULL,
  `stop_id` INT UNSIGNED NOT NULL,
  `month` TINYINT NOT NULL,
  `year` SMALLINT NOT NULL,
  `status` ENUM('active','inactive','withdrawn') NOT NULL DEFAULT 'active',
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_student_month_year` (`student_id`, `month`, `year`),
  KEY `idx_route` (`route_id`),
  KEY `idx_stop` (`stop_id`),
  CONSTRAINT `fk_sta_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`),
  CONSTRAINT `fk_sta_route` FOREIGN KEY (`route_id`) REFERENCES `transport_routes`(`id`),
  CONSTRAINT `fk_sta_stop` FOREIGN KEY (`stop_id`) REFERENCES `transport_stops`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Table structure for table `student_transport_payments`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `student_transport_payments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT UNSIGNED NOT NULL,
  `admission_no` VARCHAR(20) NOT NULL,
  `month` TINYINT NOT NULL,
  `year` SMALLINT NOT NULL,
  `amount_paid` DECIMAL(10,2) NOT NULL,
  `payment_date` DATE NOT NULL,
  `transaction_ref` VARCHAR(100),
  `paybill` VARCHAR(20),
  `status` ENUM('paid','pending','arrears','reversed') NOT NULL DEFAULT 'pending',
  `arrears` DECIMAL(10,2) DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_student_month_year` (`student_id`, `month`, `year`),
  KEY `idx_admission_no` (`admission_no`),
  CONSTRAINT `fk_stp_student` FOREIGN KEY (`student_id`) REFERENCES `students`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- View for student transport status
-- --------------------------------------------------------
DROP VIEW IF EXISTS `vw_student_transport_status`;
CREATE OR REPLACE VIEW `vw_student_transport_status` AS
SELECT
  s.id AS student_id,
  s.admission_no,
  s.first_name,
  s.last_name,
  sta.route_id,
  tr.name AS route_name,
  sta.stop_id,
  ts.name AS stop_name,
  sta.month,
  sta.year,
  sta.status AS assignment_status,
  stp.amount_paid,
  stp.status AS payment_status,
  stp.arrears
FROM students s
LEFT JOIN student_transport_assignments sta
  ON s.id = sta.student_id AND sta.month = MONTH(CURDATE()) AND sta.year = YEAR(CURDATE())
LEFT JOIN transport_routes tr ON sta.route_id = tr.id
LEFT JOIN transport_stops ts ON sta.stop_id = ts.id
LEFT JOIN student_transport_payments stp
  ON s.id = stp.student_id AND stp.month = MONTH(CURDATE()) AND stp.year = YEAR(CURDATE());

-- --------------------------------------------------------
-- Procedures for transport assignment and payment
-- --------------------------------------------------------
DELIMITER $$
DROP PROCEDURE IF EXISTS `sp_assign_student_transport`$$
CREATE PROCEDURE `sp_assign_student_transport` (
  IN p_student_id INT UNSIGNED,
  IN p_route_id INT UNSIGNED,
  IN p_stop_id INT UNSIGNED,
  IN p_month TINYINT,
  IN p_year SMALLINT
)
BEGIN
  INSERT INTO student_transport_assignments (student_id, route_id, stop_id, month, year, status)
  VALUES (p_student_id, p_route_id, p_stop_id, p_month, p_year, 'active')
  ON DUPLICATE KEY UPDATE route_id = p_route_id, stop_id = p_stop_id, status = 'active', updated_at = CURRENT_TIMESTAMP;
END$$

DROP PROCEDURE IF EXISTS `sp_record_transport_payment`$$
CREATE PROCEDURE `sp_record_transport_payment` (
  IN p_student_id INT UNSIGNED,
  IN p_admission_no VARCHAR(20),
  IN p_month TINYINT,
  IN p_year SMALLINT,
  IN p_amount_paid DECIMAL(10,2),
  IN p_payment_date DATE,
  IN p_transaction_ref VARCHAR(100),
  IN p_paybill VARCHAR(20)
)
BEGIN
  INSERT INTO student_transport_payments (student_id, admission_no, month, year, amount_paid, payment_date, transaction_ref, paybill, status)
  VALUES (p_student_id, p_admission_no, p_month, p_year, p_amount_paid, p_payment_date, p_transaction_ref, p_paybill, 'paid')
  ON DUPLICATE KEY UPDATE amount_paid = p_amount_paid, payment_date = p_payment_date, transaction_ref = p_transaction_ref, paybill = p_paybill, status = 'paid', updated_at = CURRENT_TIMESTAMP;
END$$

DROP PROCEDURE IF EXISTS `sp_check_student_transport_status`$$
CREATE PROCEDURE `sp_check_student_transport_status` (
  IN p_student_id INT UNSIGNED,
  IN p_month TINYINT,
  IN p_year SMALLINT
)
BEGIN
  SELECT s.id AS student_id, s.admission_no, s.first_name, s.last_name,
         sta.route_id, tr.name AS route_name, sta.stop_id, ts.name AS stop_name,
         stp.amount_paid, stp.status AS payment_status, stp.arrears
  FROM students s
  LEFT JOIN student_transport_assignments sta
    ON s.id = sta.student_id AND sta.month = p_month AND sta.year = p_year
  LEFT JOIN transport_routes tr ON sta.route_id = tr.id
  LEFT JOIN transport_stops ts ON sta.stop_id = ts.id
  LEFT JOIN student_transport_payments stp
    ON s.id = stp.student_id AND stp.month = p_month AND stp.year = p_year
  WHERE s.id = p_student_id;
END$$
DELIMITER ;

-- --------------------------------------------------------
-- Triggers for transport assignment/payment
-- --------------------------------------------------------
DELIMITER $$
DROP TRIGGER IF EXISTS `trg_update_transport_arrears`$$
CREATE TRIGGER `trg_update_transport_arrears` AFTER INSERT ON student_transport_payments
FOR EACH ROW BEGIN
  IF NEW.status = 'arrears' THEN
    INSERT INTO notifications (user_id, type, title, message, priority, created_at)
    VALUES (
      (SELECT user_id FROM students WHERE id = NEW.student_id),
      'info',
      'Transport Payment Arrears',
      CONCAT('Transport payment for ', NEW.month, '/', NEW.year, ' is in arrears.'),
      'high',
      NOW()
    );
  END IF;
END$$

DROP TRIGGER IF EXISTS `trg_notify_transport_assignment`$$
CREATE TRIGGER `trg_notify_transport_assignment` AFTER INSERT ON student_transport_assignments
FOR EACH ROW BEGIN
  INSERT INTO notifications (user_id, type, title, message, priority, created_at)
  VALUES (
    (SELECT user_id FROM students WHERE id = NEW.student_id),
    'info',
    'Transport Assignment',
    CONCAT('You have been assigned to route ', NEW.route_id, ', stop ', NEW.stop_id, ' for ', NEW.month, '/', NEW.year),
    'normal',
    NOW()
  );
END$$
DELIMITER ;


--
-- Triggers `learner_values_acquisition`
--


DROP TRIGGER IF EXISTS `trg_log_value_demonstration`;
DELIMITER $$
CREATE TRIGGER `trg_log_value_demonstration` AFTER INSERT ON `learner_values_acquisition` FOR EACH ROW BEGIN
INSERT INTO system_events (event_type, event_data, created_at)
VALUES (
    'value_demonstrated',
    JSON_OBJECT(
      'student_id',
      NEW.student_id,
      'value_id',
      NEW.value_id,
      'incident_date',
      NEW.incident_date
    ),
    NOW()
  );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `learning_areas`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `learning_areas`;
CREATE TABLE IF NOT EXISTS `learning_areas` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `learning_areas`:
--

--
-- Truncate table before insert `learning_areas`
--

TRUNCATE TABLE `learning_areas`;
-- --------------------------------------------------------

--
-- Table structure for table `learning_outcomes`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `learning_outcomes`;
CREATE TABLE IF NOT EXISTS `learning_outcomes` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `learning_area_id` int(10) UNSIGNED NOT NULL,
  `outcome` text NOT NULL,
  `grade_level` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `learning_area_id` (`learning_area_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `learning_outcomes`:
--

--
-- Truncate table before insert `learning_outcomes`
--

TRUNCATE TABLE `learning_outcomes`;
-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--
-- Creation: Nov 11, 2025 at 12:23 PM
--

DROP TABLE IF EXISTS `leave_types`;
CREATE TABLE IF NOT EXISTS `leave_types` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `days_allowed` int(11) DEFAULT NULL COMMENT 'Annual entitlement (NULL = unlimited)',
  `requires_approval` tinyint(1) DEFAULT 1,
  `is_paid` tinyint(1) DEFAULT 1,
  `applicable_to` enum('all','teaching','non_teaching','administration') DEFAULT 'all',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_leave_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `leave_types`:
--

--
-- Truncate table before insert `leave_types`
--

TRUNCATE TABLE `leave_types`;
--
-- Dumping data for table `leave_types`
--

INSERT DELAYED IGNORE INTO `leave_types` (`id`, `code`, `name`, `description`, `days_allowed`, `requires_approval`, `is_paid`, `applicable_to`, `status`, `created_at`) VALUES
(1, 'ANNUAL', 'Annual Leave', 'Regular annual leave entitlement', 30, 1, 1, 'all', 'active', '2025-11-11 12:23:10'),
(2, 'SICK', 'Sick Leave', 'Medical leave with doctor\'s note', 30, 1, 1, 'all', 'active', '2025-11-11 12:23:10'),
(3, 'MATERNITY', 'Maternity Leave', 'Maternity leave for female staff', 90, 1, 1, 'all', 'active', '2025-11-11 12:23:10'),
(4, 'PATERNITY', 'Paternity Leave', 'Paternity leave for male staff', 14, 1, 1, 'all', 'active', '2025-11-11 12:23:10'),
(5, 'COMPASSIONATE', 'Compassionate Leave', 'Leave for family emergencies', 7, 1, 1, 'all', 'active', '2025-11-11 12:23:10'),
(6, 'STUDY', 'Study Leave', 'Leave for educational purposes', NULL, 1, 0, 'all', 'active', '2025-11-11 12:23:10'),
(7, 'UNPAID', 'Unpaid Leave', 'Leave without pay', NULL, 1, 0, 'all', 'active', '2025-11-11 12:23:10'),
(8, 'SABBATICAL', 'Sabbatical Leave', 'Extended leave for research/rest', NULL, 1, 0, 'teaching', 'active', '2025-11-11 12:23:10');

-- --------------------------------------------------------

--
-- Table structure for table `lesson_plans`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `lesson_plans`;
CREATE TABLE IF NOT EXISTS `lesson_plans` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` int(10) UNSIGNED NOT NULL,
  `learning_area_id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `unit_id` int(10) UNSIGNED NOT NULL,
  `topic` varchar(255) NOT NULL,
  `subtopic` varchar(255) DEFAULT NULL,
  `objectives` text NOT NULL,
  `resources` text DEFAULT NULL,
  `activities` text NOT NULL,
  `assessment` text DEFAULT NULL,
  `homework` text DEFAULT NULL,
  `lesson_date` date NOT NULL,
  `duration` int(11) NOT NULL COMMENT 'Duration in minutes',
  `status` enum('draft','submitted','approved','completed') NOT NULL DEFAULT 'draft',
  `remarks` text DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_teacher_learning_area` (`teacher_id`,`learning_area_id`),
  KEY `idx_class_date` (`class_id`,`lesson_date`),
  KEY `idx_unit_status` (`unit_id`,`status`),
  KEY `idx_approval` (`approved_by`,`approved_at`),
  KEY `learning_area_id` (`learning_area_id`),
  KEY `idx_lesson_plan_dates` (`lesson_date`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `lesson_plans`:
--   `teacher_id`
--       `staff` -> `id`
--   `learning_area_id`
--       `learning_areas` -> `id`
--   `class_id`
--       `classes` -> `id`
--   `unit_id`
--       `curriculum_units` -> `id`
--   `approved_by`
--       `staff` -> `id`
--

--
-- Truncate table before insert `lesson_plans`
--

TRUNCATE TABLE `lesson_plans`;
-- --------------------------------------------------------

--
-- Table structure for table `maintenance_logs`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `maintenance_logs`;
CREATE TABLE IF NOT EXISTS `maintenance_logs` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `maintenance_schedule_id` int(10) UNSIGNED NOT NULL,
  `equipment_id` int(10) UNSIGNED NOT NULL,
  `maintenance_date` date NOT NULL,
  `service_provider` varchar(100) DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `description` text NOT NULL,
  `findings` text DEFAULT NULL,
  `actions_taken` text DEFAULT NULL,
  `status` enum('completed','in_progress','pending','cancelled') NOT NULL DEFAULT 'completed',
  `maintenance_staff_id` int(10) UNSIGNED DEFAULT NULL,
  `next_service_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_equipment_date` (`equipment_id`,`maintenance_date`),
  KEY `maintenance_schedule_id` (`maintenance_schedule_id`),
  KEY `maintenance_staff_id` (`maintenance_staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `maintenance_logs`:
--   `maintenance_schedule_id`
--       `equipment_maintenance` -> `id`
--   `equipment_id`
--       `item_serials` -> `id`
--   `maintenance_staff_id`
--       `staff` -> `id`
--

--
-- Truncate table before insert `maintenance_logs`
--

TRUNCATE TABLE `maintenance_logs`;
-- --------------------------------------------------------

--
-- Table structure for table `meal_plans`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `meal_plans`;
CREATE TABLE IF NOT EXISTS `meal_plans` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `plan_date` date NOT NULL,
  `meal_type` enum('breakfast','lunch','dinner','snack') NOT NULL,
  `menu_item_id` int(10) UNSIGNED DEFAULT NULL,
  `planned_servings` int(11) NOT NULL,
  `prepared_quantity` int(11) DEFAULT 0,
  `actual_servings` int(11) DEFAULT 0,
  `status` enum('planned','prepared','served','cancelled') NOT NULL DEFAULT 'planned',
  `prepared_by` int(10) UNSIGNED DEFAULT NULL,
  `prepared_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_meal_plan` (`plan_date`,`meal_type`,`menu_item_id`),
  KEY `idx_plan_date` (`plan_date`),
  KEY `menu_item_id` (`menu_item_id`),
  KEY `prepared_by` (`prepared_by`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `meal_plans`:
--   `menu_item_id`
--       `menu_items` -> `id`
--   `prepared_by`
--       `staff` -> `id`
--   `created_by`
--       `staff` -> `id`
--

--
-- Truncate table before insert `meal_plans`
--

TRUNCATE TABLE `meal_plans`;
-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `menu_items`;
CREATE TABLE IF NOT EXISTS `menu_items` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `meal_type` enum('breakfast','lunch','dinner','snack') NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_meal_type` (`meal_type`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `menu_items`:
--

--
-- Truncate table before insert `menu_items`
--

TRUNCATE TABLE `menu_items`;
-- --------------------------------------------------------

--
-- Table structure for table `menu_item_ingredients`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `menu_item_ingredients`;
CREATE TABLE IF NOT EXISTS `menu_item_ingredients` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `menu_item_id` int(10) UNSIGNED NOT NULL,
  `inventory_item_id` int(10) UNSIGNED NOT NULL,
  `quantity_per_portion` decimal(10,2) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_menu_ingredient` (`menu_item_id`,`inventory_item_id`),
  KEY `inventory_item_id` (`inventory_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `menu_item_ingredients`:
--   `menu_item_id`
--       `menu_items` -> `id`
--   `inventory_item_id`
--       `inventory_items` -> `id`
--

--
-- Truncate table before insert `menu_item_ingredients`
--

TRUNCATE TABLE `menu_item_ingredients`;
-- --------------------------------------------------------

--
-- Table structure for table `message_read_status`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `message_read_status`;
CREATE TABLE IF NOT EXISTS `message_read_status` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` int(10) UNSIGNED NOT NULL,
  `recipient_id` int(10) UNSIGNED NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_message_recipient` (`message_id`,`recipient_id`),
  KEY `recipient_id` (`recipient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `message_read_status`:
--   `message_id`
--       `internal_messages` -> `id`
--   `recipient_id`
--       `staff` -> `id`
--

--
-- Truncate table before insert `message_read_status`
--

TRUNCATE TABLE `message_read_status`;
-- --------------------------------------------------------

--
-- Table structure for table `message_templates`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `message_templates`;
CREATE TABLE IF NOT EXISTS `message_templates` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `type` enum('email','sms','notification') NOT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `category` varchar(50) DEFAULT NULL,
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variables`)),
  `last_used_at` datetime DEFAULT NULL,
  `use_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `status` enum('active','inactive','archived') NOT NULL DEFAULT 'active',
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_category_type` (`category`,`type`),
  KEY `fk_template_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `message_templates`:
--   `category_id`
--       `template_categories` -> `id`
--   `created_by`
--       `users` -> `id`
--

--
-- Truncate table before insert `message_templates`
--

TRUNCATE TABLE `message_templates`;
-- --------------------------------------------------------

--
-- Table structure for table `mpesa_transactions`
--
-- Creation: Nov 11, 2025 at 10:11 PM
--

DROP TABLE IF EXISTS `mpesa_transactions`;
CREATE TABLE IF NOT EXISTS `mpesa_transactions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `mpesa_code` varchar(50) NOT NULL,
  `student_id` int(10) UNSIGNED DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_date` datetime NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `status` enum('pending','processed','failed') NOT NULL DEFAULT 'pending',
  `raw_callback` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`raw_callback`)),
  `checkout_request_id` varchar(100) DEFAULT NULL,
  `webhook_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`webhook_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mpesa_code` (`mpesa_code`),
  KEY `idx_student_date` (`student_id`,`transaction_date`),
  KEY `idx_checkout_request` (`checkout_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `mpesa_transactions`:
--   `student_id`
--       `students` -> `id`
--

--
-- Truncate table before insert `mpesa_transactions`
--

TRUNCATE TABLE `mpesa_transactions`;
--
-- Triggers `mpesa_transactions`
--
DROP TRIGGER IF EXISTS `trg_mpesa_payment_processed`;
DELIMITER $$
CREATE TRIGGER `trg_mpesa_payment_processed` AFTER INSERT ON `mpesa_transactions` FOR EACH ROW BEGIN IF NEW.status = 'processed'
  AND NEW.student_id IS NOT NULL THEN
UPDATE student_fee_balances
SET balance = balance - NEW.amount,
  last_updated = NOW()
WHERE student_id = NEW.student_id
ORDER BY academic_term_id DESC
LIMIT 1;
INSERT INTO school_transactions (
    student_id,
    source,
    reference,
    amount,
    transaction_date,
    status,
    details
  )
VALUES (
    NEW.student_id,
    'mpesa',
    NEW.mpesa_code,
    NEW.amount,
    NEW.transaction_date,
    'confirmed',
    NEW.raw_callback
  );
END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `read_status` enum('unread','read') NOT NULL DEFAULT 'unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `notifications`:
--   `user_id`
--       `users` -> `id`
--

--
-- Truncate table before insert `notifications`
--

TRUNCATE TABLE `notifications`;
-- --------------------------------------------------------

--
-- Table structure for table `onboarding_tasks`
--
-- Creation: Nov 11, 2025 at 02:04 PM
--

DROP TABLE IF EXISTS `onboarding_tasks`;
CREATE TABLE IF NOT EXISTS `onboarding_tasks` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `onboarding_id` int(10) UNSIGNED NOT NULL,
  `task_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `department_id` int(10) UNSIGNED DEFAULT NULL,
  `assigned_to` int(10) UNSIGNED DEFAULT NULL,
  `due_date` date NOT NULL,
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `sequence` int(11) DEFAULT NULL,
  `status` enum('pending','in_progress','completed','blocked','skipped') DEFAULT 'pending',
  `completed_date` datetime DEFAULT NULL,
  `completed_by` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_onboarding_status` (`onboarding_id`,`status`),
  KEY `fk_task_department` (`department_id`),
  KEY `fk_task_assignee` (`assigned_to`),
  KEY `idx_onboarding_tasks_status` (`onboarding_id`,`status`),
  KEY `idx_onboarding_tasks_due_date` (`due_date`,`status`),
  KEY `idx_onboarding_tasks_category` (`category`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `onboarding_tasks`:
--   `assigned_to`
--       `users` -> `id`
--   `department_id`
--       `departments` -> `id`
--   `onboarding_id`
--       `staff_onboarding` -> `id`
--

--
-- Truncate table before insert `onboarding_tasks`
--

TRUNCATE TABLE `onboarding_tasks`;
--
-- Triggers `onboarding_tasks`
--
DROP TRIGGER IF EXISTS `trg_update_onboarding_progress`;
DELIMITER $$
CREATE TRIGGER `trg_update_onboarding_progress` AFTER UPDATE ON `onboarding_tasks` FOR EACH ROW BEGIN
    DECLARE v_total_tasks INT;
    DECLARE v_completed_tasks INT;
    DECLARE v_progress_percent INT;
    
    IF NEW.status != OLD.status THEN
        SELECT COUNT(*), SUM(CASE WHEN status IN ('completed', 'skipped') THEN 1 ELSE 0 END)
        INTO v_total_tasks, v_completed_tasks
        FROM onboarding_tasks
        WHERE onboarding_id = NEW.onboarding_id;
        
        SET v_progress_percent = ROUND((v_completed_tasks / v_total_tasks) * 100);
        
        UPDATE staff_onboarding
        SET progress_percent = v_progress_percent,
            status = CASE 
                WHEN v_progress_percent = 100 THEN 'completed'
                WHEN v_progress_percent > 0 THEN 'in_progress'
                ELSE 'pending'
            END
        WHERE id = NEW.onboarding_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `parents`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `parents`;
CREATE TABLE IF NOT EXISTS `parents` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `phone_1` varchar(20) NOT NULL,
  `phone_2` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_phone` (`phone_1`),
  KEY `idx_email` (`email`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `parents`:
--

--
-- Truncate table before insert `parents`
--

TRUNCATE TABLE `parents`;
-- --------------------------------------------------------

--
-- Table structure for table `parent_communication_preferences`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `parent_communication_preferences`;
CREATE TABLE IF NOT EXISTS `parent_communication_preferences` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) UNSIGNED NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 1,
  `sms_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `email_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `whatsapp_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `sms_do_not_disturb_start` time DEFAULT NULL,
  `sms_do_not_disturb_end` time DEFAULT NULL,
  `preferred_language` varchar(20) DEFAULT 'en',
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `idx_phone` (`phone_number`),
  KEY `idx_sms_enabled` (`sms_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `parent_communication_preferences`:
--   `parent_id`
--       `parents` -> `id`
--

--
-- Truncate table before insert `parent_communication_preferences`
--

TRUNCATE TABLE `parent_communication_preferences`;
-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `used` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_email_token` (`email`,`token`),
  KEY `idx_expiry` (`expires_at`,`used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `password_resets`:
--

--
-- Truncate table before insert `password_resets`
--

TRUNCATE TABLE `password_resets`;
-- --------------------------------------------------------

--
-- Table structure for table `payment_allocations`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `payment_allocations`;
CREATE TABLE IF NOT EXISTS `payment_allocations` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_id` int(10) UNSIGNED NOT NULL,
  `fee_structure_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_payment` (`payment_id`),
  KEY `idx_fee_structure` (`fee_structure_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `payment_allocations`:
--   `payment_id`
--       `school_transactions` -> `id`
--   `fee_structure_id`
--       `fee_structures` -> `id`
--

--
-- Truncate table before insert `payment_allocations`
--

TRUNCATE TABLE `payment_allocations`;
-- --------------------------------------------------------

--
-- Table structure for table `payment_allocations_detailed`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `payment_allocations_detailed`;
CREATE TABLE IF NOT EXISTS `payment_allocations_detailed` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_transaction_id` int(10) UNSIGNED NOT NULL,
  `student_fee_obligation_id` int(10) UNSIGNED NOT NULL,
  `amount_allocated` decimal(10,2) NOT NULL,
  `allocation_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `allocated_by` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payment` (`payment_transaction_id`),
  KEY `idx_obligation` (`student_fee_obligation_id`),
  KEY `idx_allocated_by` (`allocated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `payment_allocations_detailed`:
--

--
-- Truncate table before insert `payment_allocations_detailed`
--

TRUNCATE TABLE `payment_allocations_detailed`;
--
-- Triggers `payment_allocations_detailed`
--
DROP TRIGGER IF EXISTS `trg_update_obligation_on_payment`;
DELIMITER $$
CREATE TRIGGER `trg_update_obligation_on_payment` AFTER INSERT ON `payment_allocations_detailed` FOR EACH ROW BEGIN
    DECLARE v_total_paid DECIMAL(10, 2);
    DECLARE v_total_waived DECIMAL(10, 2);
    DECLARE v_amount_due DECIMAL(10, 2);

    SELECT amount_paid, amount_waived, amount_due 
    INTO v_total_paid, v_total_waived, v_amount_due
    FROM student_fee_obligations
    WHERE id = NEW.student_fee_obligation_id;

    UPDATE student_fee_obligations
    SET status = CASE
        WHEN (v_total_paid + v_total_waived) >= v_amount_due THEN 'paid'
        WHEN (v_total_paid + v_total_waived) > 0 THEN 'partial'
        ELSE 'pending'
    END
    WHERE id = NEW.student_fee_obligation_id;

    INSERT INTO system_events (event_type, event_data, created_at)
    VALUES (
        'payment_allocated',
        JSON_OBJECT(
            'obligation_id', NEW.student_fee_obligation_id,
            'amount', NEW.amount_allocated,
            'payment_id', NEW.payment_transaction_id
        ),
        NOW()
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `payment_reconciliations`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `payment_reconciliations`;
CREATE TABLE IF NOT EXISTS `payment_reconciliations` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `transaction_id` int(10) UNSIGNED NOT NULL,
  `reconciled_by` int(10) UNSIGNED NOT NULL,
  `reconciled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `bank_statement_ref` varchar(100) NOT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_transaction` (`transaction_id`),
  KEY `idx_reconciled_by` (`reconciled_by`),
  KEY `idx_reconciliation_date` (`reconciled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `payment_reconciliations`:
--   `transaction_id`
--       `school_transactions` -> `id`
--   `reconciled_by`
--       `users` -> `id`
--

--
-- Truncate table before insert `payment_reconciliations`
--

TRUNCATE TABLE `payment_reconciliations`;
-- --------------------------------------------------------

--
-- Table structure for table `payment_transactions`
--
-- Creation: Nov 12, 2025 at 01:07 PM
--

DROP TABLE IF EXISTS `payment_transactions`;
CREATE TABLE IF NOT EXISTS `payment_transactions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `academic_year` year(4) DEFAULT NULL,
  `term_id` int(10) UNSIGNED DEFAULT NULL,
  `term_allocation` decimal(10,2) DEFAULT NULL,
  `fee_structure_detail_id` int(10) UNSIGNED DEFAULT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_date` datetime NOT NULL,
  `payment_method` enum('cash','bank_transfer','mpesa','cheque','other') NOT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `receipt_no` varchar(50) DEFAULT NULL,
  `received_by` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('pending','confirmed','failed','reversed') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student_date` (`student_id`,`payment_date`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_reference` (`reference_no`),
  KEY `idx_receipt` (`receipt_no`),
  KEY `idx_status` (`status`),
  KEY `idx_method` (`payment_method`),
  KEY `idx_received_by` (`received_by`),
  KEY `idx_payment_year_term` (`academic_year`,`term_id`),
  KEY `idx_payment_student_year` (`student_id`,`academic_year`),
  KEY `idx_payment_student_term` (`student_id`,`term_id`),
  KEY `fk_payment_term` (`term_id`),
  KEY `fk_payment_fee_structure` (`fee_structure_detail_id`),
  KEY `idx_payment_confirmed` (`status`,`academic_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `payment_transactions`:
--   `fee_structure_detail_id`
--       `fee_structures_detailed` -> `id`
--   `term_id`
--       `academic_terms` -> `id`
--

--
-- Truncate table before insert `payment_transactions`
--

TRUNCATE TABLE `payment_transactions`;
--
-- Triggers `payment_transactions`
--
DROP TRIGGER IF EXISTS `trg_log_payment_transaction`;
DELIMITER $$
CREATE TRIGGER `trg_log_payment_transaction` AFTER INSERT ON `payment_transactions` FOR EACH ROW BEGIN
    INSERT INTO system_events (event_type, event_data, created_at)
    VALUES (
        'payment_received',
        JSON_OBJECT(
            'payment_id', NEW.id,
            'student_id', NEW.student_id,
            'parent_id', NEW.parent_id,
            'amount', NEW.amount_paid,
            'method', NEW.payment_method,
            'reference', NEW.reference_no,
            'receipt', NEW.receipt_no
        ),
        NOW()
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `payment_webhooks_log`
--
-- Creation: Nov 11, 2025 at 10:11 PM
--

DROP TABLE IF EXISTS `payment_webhooks_log`;
CREATE TABLE IF NOT EXISTS `payment_webhooks_log` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `source` enum('mpesa_stk','mpesa_c2b_validation','mpesa_c2b_confirmation','kcb_bank','generic_bank') NOT NULL,
  `webhook_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`webhook_data`)),
  `status` enum('received','validated','processed','failed') DEFAULT 'received',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_source_created` (`source`,`created_at`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log for all payment webhooks (M-Pesa and Bank)';

--
-- RELATIONSHIPS FOR TABLE `payment_webhooks_log`:
--

--
-- Truncate table before insert `payment_webhooks_log`
--

TRUNCATE TABLE `payment_webhooks_log`;
-- --------------------------------------------------------

--
-- Table structure for table `payroll_configurations`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `payroll_configurations`;
CREATE TABLE IF NOT EXISTS `payroll_configurations` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL,
  `config_value` text NOT NULL,
  `financial_year` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_key_year` (`config_key`,`financial_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `payroll_configurations`:
--

--
-- Truncate table before insert `payroll_configurations`
--

TRUNCATE TABLE `payroll_configurations`;
-- --------------------------------------------------------

--
-- Table structure for table `payslips`
--
-- Creation: Nov 11, 2025 at 02:41 PM
--

DROP TABLE IF EXISTS `payslips`;
CREATE TABLE IF NOT EXISTS `payslips` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `payroll_month` int(11) NOT NULL,
  `payroll_year` int(11) NOT NULL,
  `basic_salary` decimal(12,2) NOT NULL,
  `allowances_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gross_salary` decimal(12,2) NOT NULL,
  `paye_tax` decimal(12,2) NOT NULL DEFAULT 0.00,
  `nssf_contribution` decimal(12,2) NOT NULL DEFAULT 0.00,
  `nhif_contribution` decimal(12,2) NOT NULL DEFAULT 0.00,
  `loan_deduction` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_deductions_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_salary` decimal(12,2) NOT NULL,
  `payment_method` enum('bank','cash','check','mobile_money') DEFAULT 'bank',
  `payment_date` date DEFAULT NULL,
  `payslip_status` enum('draft','approved','paid','cancelled') NOT NULL DEFAULT 'draft',
  `signed_by` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_staff_month_year` (`staff_id`,`payroll_month`,`payroll_year`),
  KEY `idx_period` (`payroll_month`,`payroll_year`),
  KEY `idx_status` (`payslip_status`),
  KEY `idx_payslips_staff_period` (`staff_id`,`payroll_year`,`payroll_month`),
  KEY `idx_payslips_status` (`payslip_status`,`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `payslips`:
--   `staff_id`
--       `staff` -> `id`
--

--
-- Truncate table before insert `payslips`
--

TRUNCATE TABLE `payslips`;
-- --------------------------------------------------------

--
-- Table structure for table `pcis`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `pcis`;
CREATE TABLE IF NOT EXISTS `pcis` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `topic_code` varchar(20) NOT NULL,
  `topic_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL COMMENT 'environmental, social, health, political, etc',
  `learning_area_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Primary learning area',
  `grade_applicable` varchar(50) DEFAULT NULL COMMENT 'applicable grades',
  `learning_resources` text DEFAULT NULL COMMENT 'URL links to resources',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_topic_code` (`topic_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `pcis`:
--

--
-- Truncate table before insert `pcis`
--

TRUNCATE TABLE `pcis`;
-- --------------------------------------------------------

--
-- Table structure for table `performance_levels_cbc`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `performance_levels_cbc`;
CREATE TABLE IF NOT EXISTS `performance_levels_cbc` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `level` int(11) NOT NULL,
  `code` varchar(10) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `mark_range_min` decimal(5,2) NOT NULL,
  `mark_range_max` decimal(5,2) NOT NULL,
  `feedback_template` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `performance_levels_cbc`:
--

--
-- Truncate table before insert `performance_levels_cbc`
--

TRUNCATE TABLE `performance_levels_cbc`;
-- --------------------------------------------------------

--
-- Table structure for table `performance_ratings`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `performance_ratings`;
CREATE TABLE IF NOT EXISTS `performance_ratings` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `rating_period` varchar(20) NOT NULL,
  `overall_rating` varchar(20) NOT NULL,
  `kpi_achievement_score` decimal(5,2) DEFAULT NULL,
  `supervisor_rating` varchar(20) DEFAULT NULL,
  `supervisor_id` int(10) UNSIGNED DEFAULT NULL,
  `rated_date` date NOT NULL,
  `comments` text DEFAULT NULL,
  `is_final` tinyint(1) NOT NULL DEFAULT 0,
  `signed_by` int(10) UNSIGNED DEFAULT NULL,
  `signed_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staff_period` (`staff_id`,`rating_period`),
  KEY `idx_supervisor` (`supervisor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `performance_ratings`:
--   `staff_id`
--       `staff` -> `id`
--

--
-- Truncate table before insert `performance_ratings`
--

TRUNCATE TABLE `performance_ratings`;
-- --------------------------------------------------------

--
-- Table structure for table `performance_review_kpis`
--
-- Creation: Nov 11, 2025 at 02:04 PM
--

DROP TABLE IF EXISTS `performance_review_kpis`;
CREATE TABLE IF NOT EXISTS `performance_review_kpis` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `review_id` int(10) UNSIGNED NOT NULL,
  `kpi_template_id` int(10) UNSIGNED NOT NULL,
  `weight` decimal(5,2) DEFAULT 0.00,
  `target_value` decimal(10,2) DEFAULT NULL,
  `actual_value` decimal(10,2) DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL COMMENT 'Achievement percentage',
  `comments` text DEFAULT NULL,
  `status` enum('pending','in_progress','completed','not_applicable') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_review_kpi` (`review_id`),
  KEY `idx_performance_kpis_review` (`review_id`,`status`),
  KEY `idx_performance_kpis_template` (`kpi_template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `performance_review_kpis`:
--   `review_id`
--       `staff_performance_reviews` -> `id`
--   `kpi_template_id`
--       `staff_kpi_templates` -> `id`
--

--
-- Truncate table before insert `performance_review_kpis`
--

TRUNCATE TABLE `performance_review_kpis`;
-- --------------------------------------------------------

--
-- Table structure for table `permission_delegations`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `permission_delegations`;
CREATE TABLE IF NOT EXISTS `permission_delegations` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `delegated_from_user_id` int(10) UNSIGNED NOT NULL,
  `delegated_to_user_id` int(10) UNSIGNED NOT NULL,
  `form_permission_id` int(10) UNSIGNED NOT NULL,
  `delegation_start_date` date NOT NULL,
  `delegation_end_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approval_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_from_user` (`delegated_from_user_id`),
  KEY `idx_to_user` (`delegated_to_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `permission_delegations`:
--   `delegated_from_user_id`
--       `users` -> `id`
--   `delegated_to_user_id`
--       `users` -> `id`
--

--
-- Truncate table before insert `permission_delegations`
--

TRUNCATE TABLE `permission_delegations`;
-- --------------------------------------------------------

--
-- Table structure for table `portfolios`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `portfolios`;
CREATE TABLE IF NOT EXISTS `portfolios` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `academic_year` year(4) NOT NULL,
  `portfolio_type` enum('physical','digital') NOT NULL DEFAULT 'digital',
  `title` varchar(255) DEFAULT NULL,
  `theme` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_date` date NOT NULL,
  `last_updated` date DEFAULT NULL,
  `status` enum('active','archived','submitted') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_portfolio` (`student_id`,`academic_year`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `portfolios`:
--   `student_id`
--       `students` -> `id`
--

--
-- Truncate table before insert `portfolios`
--

TRUNCATE TABLE `portfolios`;
--
-- Triggers `portfolios`
--
DROP TRIGGER IF EXISTS `trg_create_portfolio_on_promotion`;
DELIMITER $$
CREATE TRIGGER `trg_create_portfolio_on_promotion` AFTER INSERT ON `portfolios` FOR EACH ROW BEGIN IF NEW.status = 'active' THEN
INSERT INTO system_events (event_type, event_data, created_at)
VALUES (
    'portfolio_created',
    JSON_OBJECT(
      'portfolio_id',
      NEW.id,
      'student_id',
      NEW.student_id,
      'year',
      NEW.academic_year
    ),
    NOW()
  );
END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `portfolio_artifacts`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `portfolio_artifacts`;
CREATE TABLE IF NOT EXISTS `portfolio_artifacts` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `portfolio_id` int(10) UNSIGNED NOT NULL,
  `artifact_title` varchar(255) NOT NULL,
  `artifact_type` enum('assignment','project','photo','video','document','reflection','other') NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `upload_date` date NOT NULL,
  `learner_reflection` text DEFAULT NULL,
  `teacher_feedback` text DEFAULT NULL,
  `competency_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Associated competency',
  `value_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Associated value',
  `rating` decimal(3,1) DEFAULT NULL COMMENT '0-5 stars',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_portfolio` (`portfolio_id`),
  KEY `idx_competency` (`competency_id`),
  KEY `idx_value` (`value_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `portfolio_artifacts`:
--   `competency_id`
--       `core_competencies` -> `id`
--   `portfolio_id`
--       `portfolios` -> `id`
--   `value_id`
--       `core_values` -> `id`
--

--
-- Truncate table before insert `portfolio_artifacts`
--

TRUNCATE TABLE `portfolio_artifacts`;
-- --------------------------------------------------------

--
-- Table structure for table `promotion_batches`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `promotion_batches`;
CREATE TABLE IF NOT EXISTS `promotion_batches` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `from_academic_year` year(4) NOT NULL,
  `to_academic_year` year(4) NOT NULL,
  `batch_type` enum('bulk_grade','bulk_class','single_class','manual') NOT NULL,
  `batch_scope` varchar(255) DEFAULT NULL COMMENT 'e.g., Grade 1-8, Class Grade1Yellow, Individual',
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `total_students_processed` int(11) DEFAULT 0,
  `total_promoted` int(11) DEFAULT 0,
  `total_pending_approval` int(11) DEFAULT 0,
  `total_rejected` int(11) DEFAULT 0,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_academic_years` (`from_academic_year`,`to_academic_year`),
  KEY `idx_batch_type` (`batch_type`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `promotion_batches`:
--   `created_by`
--       `users` -> `id`
--

--
-- Truncate table before insert `promotion_batches`
--

TRUNCATE TABLE `promotion_batches`;
-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `purchase_orders`;
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `supplier_id` int(10) UNSIGNED NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `order_date` date NOT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_terms` text DEFAULT NULL,
  `status` enum('draft','pending','approved','ordered','received','cancelled') NOT NULL DEFAULT 'draft',
  `remarks` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_number` (`order_number`),
  KEY `idx_supplier` (`supplier_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_approved_by` (`approved_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `purchase_orders`:
--   `supplier_id`
--       `suppliers` -> `id`
--   `created_by`
--       `staff` -> `id`
--   `approved_by`
--       `staff` -> `id`
--

--
-- Truncate table before insert `purchase_orders`
--

TRUNCATE TABLE `purchase_orders`;
-- --------------------------------------------------------

--
-- Table structure for table `record_permissions`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `record_permissions`;
CREATE TABLE IF NOT EXISTS `record_permissions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `role_id` int(10) UNSIGNED DEFAULT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` int(10) UNSIGNED NOT NULL,
  `permission_type` varchar(50) NOT NULL,
  `granted_date` datetime NOT NULL,
  `granted_by` int(10) UNSIGNED DEFAULT NULL,
  `expiry_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_table` (`user_id`,`table_name`,`record_id`),
  KEY `idx_role_table` (`role_id`,`table_name`,`record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `record_permissions`:
--   `role_id`
--       `roles` -> `id`
--   `user_id`
--       `users` -> `id`
--

--
-- Truncate table before insert `record_permissions`
--

TRUNCATE TABLE `record_permissions`;
-- --------------------------------------------------------

--
-- Table structure for table `requisition_items`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `requisition_items`;
CREATE TABLE IF NOT EXISTS `requisition_items` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `requisition_id` int(10) UNSIGNED NOT NULL,
  `item_id` int(10) UNSIGNED NOT NULL,
  `requested_quantity` int(11) NOT NULL,
  `unit` varchar(20) NOT NULL,
  `approved_quantity` int(11) DEFAULT NULL,
  `fulfilled_quantity` int(11) DEFAULT 0,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_requisition_item` (`requisition_id`,`item_id`),
  KEY `item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `requisition_items`:
--   `requisition_id`
--       `inventory_requisitions` -> `id`
--   `item_id`
--       `inventory_items` -> `id`
--

--
-- Truncate table before insert `requisition_items`
--

TRUNCATE TABLE `requisition_items`;
-- --------------------------------------------------------

--
-- Table structure for table `roles`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`permissions`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `roles`:
--

--
-- Truncate table before insert `roles`
--

TRUNCATE TABLE `roles`;
-- --------------------------------------------------------

--
-- Table structure for table `role_form_permissions`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `role_form_permissions`;
CREATE TABLE IF NOT EXISTS `role_form_permissions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` int(10) UNSIGNED NOT NULL,
  `form_permission_id` int(10) UNSIGNED NOT NULL,
  `allowed_actions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`allowed_actions`)),
  `can_delegate` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_form` (`role_id`,`form_permission_id`),
  KEY `idx_role` (`role_id`),
  KEY `fk_role_form_perm_form` (`form_permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `role_form_permissions`:
--   `form_permission_id`
--       `form_permissions` -> `id`
--   `role_id`
--       `roles` -> `id`
--

--
-- Truncate table before insert `role_form_permissions`
--

TRUNCATE TABLE `role_form_permissions`;
-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `rooms`;
CREATE TABLE IF NOT EXISTS `rooms` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `code` varchar(20) NOT NULL,
  `type` enum('classroom','lab','office','other') NOT NULL,
  `capacity` int(11) NOT NULL,
  `building` varchar(50) DEFAULT NULL,
  `floor` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `rooms`:
--

--
-- Truncate table before insert `rooms`
--

TRUNCATE TABLE `rooms`;
-- --------------------------------------------------------

--
-- Table structure for table `route_schedules`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `route_schedules`;
CREATE TABLE IF NOT EXISTS `route_schedules` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `route_id` int(10) UNSIGNED NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `direction` enum('pickup','dropoff') NOT NULL,
  `departure_time` time NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_route_day` (`route_id`,`day_of_week`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `route_schedules`:
--   `route_id`
--       `transport_routes` -> `id`
--

--
-- Truncate table before insert `route_schedules`
--

TRUNCATE TABLE `route_schedules`;
-- --------------------------------------------------------

--
-- Table structure for table `route_stops`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `route_stops`;
CREATE TABLE IF NOT EXISTS `route_stops` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `route_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `location` varchar(255) NOT NULL,
  `sequence` int(11) NOT NULL,
  `morning_time` time NOT NULL,
  `afternoon_time` time NOT NULL,
  `max_students` int(11) NOT NULL DEFAULT 0,
  `current_students` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_route_sequence` (`route_id`,`sequence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `route_stops`:
--   `route_id`
--       `transport_routes` -> `id`
--

--
-- Truncate table before insert `route_stops`
--

TRUNCATE TABLE `route_stops`;
-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `schedules`;
CREATE TABLE IF NOT EXISTS `schedules` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `schedules`:
--

--
-- Truncate table before insert `schedules`
--

TRUNCATE TABLE `schedules`;
-- --------------------------------------------------------

--
-- Table structure for table `schedule_changes`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `schedule_changes`;
CREATE TABLE IF NOT EXISTS `schedule_changes` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `schedule_type` enum('class','exam') NOT NULL,
  `schedule_id` int(10) UNSIGNED NOT NULL,
  `change_type` enum('reschedule','cancel','room_change','teacher_change') NOT NULL,
  `old_value` varchar(255) DEFAULT NULL,
  `new_value` varchar(255) DEFAULT NULL,
  `changed_by` int(10) UNSIGNED DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `changed_by` (`changed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `schedule_changes`:
--   `changed_by`
--       `users` -> `id`
--

--
-- Truncate table before insert `schedule_changes`
--

TRUNCATE TABLE `schedule_changes`;
-- --------------------------------------------------------

--
-- Table structure for table `school_configuration`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `school_configuration`;
CREATE TABLE IF NOT EXISTS `school_configuration` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_name` varchar(255) NOT NULL,
  `school_code` varchar(50) DEFAULT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `favicon_url` varchar(500) DEFAULT NULL,
  `motto` varchar(500) DEFAULT NULL,
  `vision` text DEFAULT NULL,
  `mission` text DEFAULT NULL,
  `core_values` text DEFAULT NULL,
  `about_us` text DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `alternative_phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `facebook_url` varchar(255) DEFAULT NULL,
  `twitter_url` varchar(255) DEFAULT NULL,
  `instagram_url` varchar(255) DEFAULT NULL,
  `linkedin_url` varchar(255) DEFAULT NULL,
  `youtube_url` varchar(255) DEFAULT NULL,
  `established_year` year(4) DEFAULT NULL,
  `principal_name` varchar(255) DEFAULT NULL,
  `principal_message` text DEFAULT NULL,
  `academic_calendar_url` varchar(500) DEFAULT NULL,
  `prospectus_url` varchar(500) DEFAULT NULL,
  `student_handbook_url` varchar(500) DEFAULT NULL,
  `timezone` varchar(50) DEFAULT 'Africa/Nairobi',
  `currency` varchar(10) DEFAULT 'KES',
  `language` varchar(10) DEFAULT 'en',
  `date_format` varchar(20) DEFAULT 'Y-m-d',
  `time_format` varchar(20) DEFAULT 'H:i:s',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`is_active`),
  KEY `fk_school_config_created_by` (`created_by`),
  KEY `fk_school_config_updated_by` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- RELATIONSHIPS FOR TABLE `school_configuration`:
--   `updated_by`
--       `users` -> `id`
--

--
-- Truncate table before insert `school_configuration`
--

TRUNCATE TABLE `school_configuration`;
-- --------------------------------------------------------

--
-- Table structure for table `school_levels`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `school_levels`;
CREATE TABLE IF NOT EXISTS `school_levels` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `code` varchar(10) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `school_levels`:
--

--
-- Truncate table before insert `school_levels`
--

TRUNCATE TABLE `school_levels`;
-- --------------------------------------------------------

--
-- Table structure for table `school_transactions`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `school_transactions`;
CREATE TABLE IF NOT EXISTS `school_transactions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED DEFAULT NULL,
  `financial_period_id` int(10) UNSIGNED DEFAULT NULL,
  `source` enum('bank','mpesa','cash','other') NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_date` datetime NOT NULL,
  `status` enum('pending','confirmed','failed') NOT NULL DEFAULT 'pending',
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student_source_date` (`student_id`,`source`,`transaction_date`),
  KEY `financial_period_id` (`financial_period_id`),
  KEY `idx_transaction_date` (`transaction_date`),
  KEY `idx_student_period_status` (`student_id`,`financial_period_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `school_transactions`:
--   `student_id`
--       `students` -> `id`
--   `financial_period_id`
--       `financial_periods` -> `id`
--

--
-- Truncate table before insert `school_transactions`
--

TRUNCATE TABLE `school_transactions`;
--
-- Triggers `school_transactions`
--
DROP TRIGGER IF EXISTS `trg_assign_financial_period`;
DELIMITER $$
CREATE TRIGGER `trg_assign_financial_period` AFTER INSERT ON `school_transactions` FOR EACH ROW BEGIN 
    CALL sp_assign_financial_period(NEW.id);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `sms_communications`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `sms_communications`;
CREATE TABLE IF NOT EXISTS `sms_communications` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `student_id` int(10) UNSIGNED DEFAULT NULL,
  `recipient_phone` varchar(20) NOT NULL,
  `message_body` text NOT NULL,
  `template_id` int(10) UNSIGNED DEFAULT NULL,
  `sms_type` enum('academic','fees','attendance','event','emergency','general','report_card') NOT NULL DEFAULT 'general',
  `status` enum('pending','queued','sent','delivered','failed') NOT NULL DEFAULT 'pending',
  `sent_by` int(10) UNSIGNED NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `failure_reason` text DEFAULT NULL,
  `external_reference_id` varchar(255) DEFAULT NULL,
  `character_count` int(11) DEFAULT NULL,
  `sms_parts` int(11) DEFAULT 1,
  `cost` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `student_id` (`student_id`),
  KEY `idx_status` (`status`),
  KEY `idx_sms_type` (`sms_type`),
  KEY `idx_recipient` (`recipient_phone`),
  KEY `sent_by` (`sent_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `sms_communications`:
--   `parent_id`
--       `parents` -> `id`
--   `student_id`
--       `students` -> `id`
--   `sent_by`
--       `staff` -> `id`
--

--
-- Truncate table before insert `sms_communications`
--

TRUNCATE TABLE `sms_communications`;
--
-- Triggers `sms_communications`
--
DROP TRIGGER IF EXISTS `trg_log_sms_delivery`;
DELIMITER $$
CREATE TRIGGER `trg_log_sms_delivery` AFTER UPDATE ON `sms_communications` FOR EACH ROW BEGIN
DECLARE v_status_changed BOOLEAN DEFAULT FALSE;
IF OLD.status <> NEW.status THEN
SET v_status_changed = TRUE;
INSERT INTO system_events (event_type, event_data, created_at)
VALUES (
    'sms_status_changed',
    JSON_OBJECT(
      'sms_id',
      NEW.id,
      'old_status',
      OLD.status,
      'new_status',
      NEW.status,
      'parent_id',
      NEW.parent_id
    ),
    NOW()
  );
END IF;
IF NEW.status = 'delivered'
AND OLD.status <> 'delivered' THEN
UPDATE parent_communication_preferences
SET last_sms_date = NOW()
WHERE parent_id = NEW.parent_id;
END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `staff`;
CREATE TABLE IF NOT EXISTS `staff` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_type_id` int(10) UNSIGNED DEFAULT NULL,
  `staff_category_id` int(10) UNSIGNED DEFAULT NULL,
  `staff_no` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `department_id` int(10) UNSIGNED NOT NULL,
  `supervisor_id` int(10) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `position` varchar(100) NOT NULL,
  `employment_date` date NOT NULL,
  `contract_type` enum('permanent','contract','temporary') NOT NULL DEFAULT 'permanent',
  `nssf_no` varchar(30) DEFAULT NULL,
  `kra_pin` varchar(30) DEFAULT NULL,
  `nhif_no` varchar(30) DEFAULT NULL,
  `bank_account` varchar(50) DEFAULT NULL,
  `salary` decimal(12,2) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `marital_status` enum('single','married','divorced','widowed') DEFAULT NULL,
  `tsc_no` varchar(30) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `profile_pic_url` varchar(255) DEFAULT NULL,
  `documents_folder` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','on_leave') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_staff_no` (`staff_no`),
  KEY `idx_department` (`department_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_staff_type` (`staff_type_id`),
  KEY `idx_staff_category` (`staff_category_id`),
  KEY `idx_supervisor` (`supervisor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `staff`:
--   `staff_category_id`
--       `staff_categories` -> `id`
--   `staff_type_id`
--       `staff_types` -> `id`
--   `department_id`
--       `departments` -> `id`
--   `user_id`
--       `users` -> `id`
--

--
-- Truncate table before insert `staff`
--

TRUNCATE TABLE `staff`;
-- --------------------------------------------------------

--
-- Table structure for table `staff_allowances`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `staff_allowances`;
CREATE TABLE IF NOT EXISTS `staff_allowances` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `effective_date` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `staff_allowances`:
--   `staff_id`
--       `staff` -> `id`
--

--
-- Truncate table before insert `staff_allowances`
--

TRUNCATE TABLE `staff_allowances`;
-- --------------------------------------------------------

--
-- Table structure for table `staff_attendance`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `staff_attendance`;
CREATE TABLE IF NOT EXISTS `staff_attendance` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent','late') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `marked_by` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_staff_date` (`staff_id`,`date`),
  KEY `marked_by` (`marked_by`),
  KEY `idx_staff_date_status` (`staff_id`,`date`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `staff_attendance`:
--   `staff_id`
--       `staff` -> `id`
--   `marked_by`
--       `users` -> `id`
--

--
-- Truncate table before insert `staff_attendance`
--

TRUNCATE TABLE `staff_attendance`;
-- --------------------------------------------------------

--
-- Table structure for table `staff_categories`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `staff_categories`;
CREATE TABLE IF NOT EXISTS `staff_categories` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_type_id` int(10) UNSIGNED NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `kpi_applicable` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staff_type` (`staff_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `staff_categories`:
--   `staff_type_id`
--       `staff_types` -> `id`
--

--
-- Truncate table before insert `staff_categories`
--

TRUNCATE TABLE `staff_categories`;
-- --------------------------------------------------------

--
-- Table structure for table `staff_class_assignments`
--
-- Creation: Nov 11, 2025 at 02:04 PM
--

DROP TABLE IF EXISTS `staff_class_assignments`;
CREATE TABLE IF NOT EXISTS `staff_class_assignments` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `class_stream_id` int(10) UNSIGNED DEFAULT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `stream_id` int(10) UNSIGNED DEFAULT NULL,
  `academic_year_id` int(10) UNSIGNED NOT NULL,
  `role` enum('class_teacher','subject_teacher','assistant_teacher','head_of_department') NOT NULL DEFAULT 'subject_teacher',
  `subject_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Learning area/subject taught',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','completed','transferred','terminated') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_staff_class_year` (`staff_id`,`class_id`,`stream_id`,`academic_year_id`,`role`,`subject_id`),
  KEY `idx_staff_year` (`staff_id`,`academic_year_id`),
  KEY `idx_class_year` (`class_id`,`stream_id`,`academic_year_id`),
  KEY `idx_status` (`status`),
  KEY `idx_role` (`role`),
  KEY `fk_assignment_stream` (`stream_id`),
  KEY `fk_assignment_year` (`academic_year_id`),
  KEY `fk_assignment_creator` (`created_by`),
  KEY `idx_staff_assignments_active` (`staff_id`,`academic_year_id`,`status`),
  KEY `idx_staff_assignments_class_stream` (`class_stream_id`,`academic_year_id`,`role`,`status`),
  KEY `idx_staff_assignments_subject` (`subject_id`,`academic_year_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Staff class assignments per academic year - who teaches what, when';

--
-- RELATIONSHIPS FOR TABLE `staff_class_assignments`:
--   `class_id`
--       `classes` -> `id`
--   `created_by`
--       `users` -> `id`
--   `staff_id`
--       `staff` -> `id`
--   `stream_id`
--       `class_streams` -> `id`
--   `subject_id`
--       `learning_areas` -> `id`
--   `academic_year_id`
--       `academic_years` -> `id`
--

--
-- Truncate table before insert `staff_class_assignments`
--

TRUNCATE TABLE `staff_class_assignments`;
-- --------------------------------------------------------

--
-- Table structure for table `staff_contracts`
--
-- Creation: Nov 11, 2025 at 12:27 PM
--

DROP TABLE IF EXISTS `staff_contracts`;
CREATE TABLE IF NOT EXISTS `staff_contracts` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `contract_type` enum('permanent','temporary','contract','internship','probation') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL COMMENT 'NULL for permanent',
  `salary` decimal(12,2) NOT NULL,
  `allowances` decimal(12,2) DEFAULT 0.00,
  `terms` text DEFAULT NULL,
  `contract_document_url` varchar(255) DEFAULT NULL,
  `status` enum('active','completed','terminated','renewed') DEFAULT 'active',
  `termination_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_contract_staff_active` (`staff_id`,`status`),
  KEY `idx_contract_dates` (`start_date`,`end_date`),
  KEY `fk_contract_creator` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `staff_contracts`:
--   `created_by`
--       `users` -> `id`
--   `staff_id`
--       `staff` -> `id`
--

--
-- Truncate table before insert `staff_contracts`
--

TRUNCATE TABLE `staff_contracts`;
-- --------------------------------------------------------

--
-- Table structure for table `staff_deductions`
--
-- Creation: Nov 11, 2025 at 02:41 PM
--

DROP TABLE IF EXISTS `staff_deductions`;
CREATE TABLE IF NOT EXISTS `staff_deductions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `effective_date` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  KEY `idx_staff_deductions_effective` (`staff_id`,`effective_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `staff_deductions`:
--   `staff_id`
--       `staff` -> `id`
--

--
-- Truncate table before insert `staff_deductions`
--

TRUNCATE TABLE `staff_deductions`;
-- --------------------------------------------------------

--
-- Table structure for table `staff_experience`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `staff_experience`;
CREATE TABLE IF NOT EXISTS `staff_experience` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `organization` varchar(255) NOT NULL,
  `position` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `responsibilities` text DEFAULT NULL,
  `document_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staff` (`staff_id`),
  KEY `idx_dates` (`start_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `staff_experience`:
--   `staff_id`
--       `staff` -> `id`
--

--
-- Truncate table before insert `staff_experience`
--

TRUNCATE TABLE `staff_experience`;
-- --------------------------------------------------------

--
-- Table structure for table `staff_kpi_templates`
--
-- Creation: Nov 11, 2025 at 12:27 PM
--

DROP TABLE IF EXISTS `staff_kpi_templates`;
CREATE TABLE IF NOT EXISTS `staff_kpi_templates` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_category_id` int(10) UNSIGNED NOT NULL,
  `kpi_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `measurement_criteria` text DEFAULT NULL,
  `target_value` decimal(10,2) DEFAULT NULL,
  `weight_percentage` decimal(5,2) DEFAULT NULL COMMENT 'Weight in overall performance',
  `evaluation_period` enum('term','semester','annual') DEFAULT 'term',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_kpi_category` (`staff_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `staff_kpi_templates`:
--   `staff_category_id`
--       `staff_categories` -> `id`
--

--
-- Truncate table before insert `staff_kpi_templates`
--

TRUNCATE TABLE `staff_kpi_templates`;
-- --------------------------------------------------------

--
-- Table structure for table `staff_leaves`
--
-- Creation: Nov 11, 2025 at 02:04 PM
--

DROP TABLE IF EXISTS `staff_leaves`;
CREATE TABLE IF NOT EXISTS `staff_leaves` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `leave_type_id` int(10) UNSIGNED NOT NULL,
  `leave_type` varchar(50) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days_requested` int(11) NOT NULL,
  `reason` text NOT NULL,
  `relief_staff_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Replacement teacher',
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `attachments_folder` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staff_dates` (`staff_id`,`start_date`,`end_date`),
  KEY `idx_status` (`status`),
  KEY `idx_leave_type` (`leave_type_id`),
  KEY `fk_leave_relief` (`relief_staff_id`),
  KEY `fk_leave_approver` (`approved_by`),
  KEY `idx_staff_leaves_staff_type` (`staff_id`,`leave_type_id`,`status`),
  KEY `idx_staff_leaves_dates` (`start_date`,`end_date`),
  KEY `idx_staff_leaves_status_year` (`status`,`start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Staff leave applications and tracking';

--
-- RELATIONSHIPS FOR TABLE `staff_leaves`:
--   `approved_by`
--       `users` -> `id`
--   `relief_staff_id`
--       `staff` -> `id`
--   `staff_id`
--       `staff` -> `id`
--   `leave_type_id`
--       `leave_types` -> `id`
--

--
-- Truncate table before insert `staff_leaves`
--

TRUNCATE TABLE `staff_leaves`;
--
-- Triggers `staff_leaves`
--
DROP TRIGGER IF EXISTS `trg_check_leave_overlap`;
DELIMITER $$
CREATE TRIGGER `trg_check_leave_overlap` BEFORE INSERT ON `staff_leaves` FOR EACH ROW BEGIN
    DECLARE overlap_count INT;
    
    SELECT COUNT(*) INTO overlap_count
    FROM staff_leaves
    WHERE staff_id = NEW.staff_id
    AND status IN ('pending', 'approved')
    AND (
        (NEW.start_date BETWEEN start_date AND end_date)
        OR (NEW.end_date BETWEEN start_date AND end_date)
        OR (start_date BETWEEN NEW.start_date AND NEW.end_date)
    );
    
    IF overlap_count > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Leave dates overlap with existing leave application';
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_update_leave_balance`;
DELIMITER $$
CREATE TRIGGER `trg_update_leave_balance` AFTER UPDATE ON `staff_leaves` FOR EACH ROW BEGIN
    IF NEW.status = 'approved' AND OLD.status = 'pending' THEN
        
        INSERT INTO workflow_notifications (
            workflow_instance_id,
            user_id,
            notification_type,
            title,
            message,
            created_at
        )
        SELECT 
            wi.id,
            NEW.staff_id,
            'leave_approved',
            'Leave Request Approved',
            CONCAT('Your leave request from ', NEW.start_date, ' to ', NEW.end_date, ' has been approved'),
            NOW()
        FROM workflow_instances wi
        WHERE wi.entity_type = 'leave_request' 
        AND wi.entity_id = NEW.id
        LIMIT 1;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `staff_loans`
--
-- Creation: Nov 11, 2025 at 02:41 PM
--

DROP TABLE IF EXISTS `staff_loans`;
CREATE TABLE IF NOT EXISTS `staff_loans` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `loan_type` varchar(50) NOT NULL,
  `principal_amount` decimal(12,2) NOT NULL,
  `loan_date` date NOT NULL,
  `agreed_monthly_deduction` decimal(10,2) NOT NULL,
  `balance_remaining` decimal(12,2) NOT NULL,
  `status` enum('active','paid_off','defaulted','suspended') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staff` (`staff_id`),
  KEY `idx_status` (`status`),
  KEY `idx_staff_loans_status` (`staff_id`,`status`,`balance_remaining`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `staff_loans`:
--   `staff_id`
--       `staff` -> `id`
--

--
-- Truncate table before insert `staff_loans`
--

TRUNCATE TABLE `staff_loans`;
-- --------------------------------------------------------

--
-- Table structure for table `staff_onboarding`
--
-- Creation: Nov 11, 2025 at 02:04 PM
--

DROP TABLE IF EXISTS `staff_onboarding`;
CREATE TABLE IF NOT EXISTS `staff_onboarding` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `mentor_id` int(10) UNSIGNED DEFAULT NULL,
  `start_date` date NOT NULL,
  `target_completion` date NOT NULL,
  `expected_end_date` date DEFAULT NULL,
  `actual_completion` date DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `status` enum('pending','in_progress','completed','extended','terminated') DEFAULT 'pending',
  `progress_percent` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_active_onboarding` (`staff_id`,`status`),
  KEY `fk_onboarding_mentor` (`mentor_id`),
  KEY `idx_staff_onboarding_status` (`staff_id`,`status`),
  KEY `idx_staff_onboarding_dates` (`start_date`,`expected_end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `staff_onboarding`:
--   `mentor_id`
--       `staff` -> `id`
--   `staff_id`
--       `staff` -> `id`
--

--
-- Truncate table before insert `staff_onboarding`
--

TRUNCATE TABLE `staff_onboarding`;
-- --------------------------------------------------------

--
-- Table structure for table `staff_payroll`
--
-- Creation: Nov 11, 2025 at 02:04 PM
--

DROP TABLE IF EXISTS `staff_payroll`;
CREATE TABLE IF NOT EXISTS `staff_payroll` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `payroll_month` int(11) NOT NULL,
  `payroll_year` int(11) NOT NULL,
  `basic_salary` decimal(10,2) NOT NULL,
  `gross_salary` decimal(10,2) DEFAULT NULL,
  `nssf_deduction` decimal(10,2) DEFAULT NULL,
  `nhif_deduction` decimal(10,2) DEFAULT NULL,
  `paye_tax` decimal(10,2) DEFAULT NULL,
  `other_deductions` decimal(10,2) DEFAULT NULL,
  `total_deductions` decimal(10,2) DEFAULT NULL,
  `allowances` decimal(10,2) NOT NULL,
  `deductions` decimal(10,2) NOT NULL,
  `net_salary` decimal(10,2) NOT NULL,
  `status` varchar(50) NOT NULL,
  `payment_date` datetime DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `payroll_period` varchar(7) NOT NULL COMMENT 'Format: YYYY-MM',
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  KEY `idx_payroll_period` (`payroll_period`),
  KEY `idx_staff_payroll_period` (`staff_id`,`payroll_period`,`status`),
  KEY `idx_staff_payroll_status` (`status`,`payroll_period`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `staff_payroll`:
--   `staff_id`
--       `staff` -> `id`
--

--
-- Truncate table before insert `staff_payroll`
--

TRUNCATE TABLE `staff_payroll`;
--
-- Triggers `staff_payroll`
--
DROP TRIGGER IF EXISTS `trg_validate_payroll_payment`;
DELIMITER $$
CREATE TRIGGER `trg_validate_payroll_payment` BEFORE UPDATE ON `staff_payroll` FOR EACH ROW BEGIN
    IF NEW.status = 'paid' AND OLD.status != 'paid' THEN
        IF NEW.net_salary <= 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot process payment: Net salary must be positive';
        END IF;
        
        
        IF NEW.payment_date IS NULL THEN
            SET NEW.payment_date = NOW();
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `staff_performance_reviews`
--
-- Creation: Nov 11, 2025 at 02:04 PM
--

DROP TABLE IF EXISTS `staff_performance_reviews`;
CREATE TABLE IF NOT EXISTS `staff_performance_reviews` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `academic_year_id` int(10) UNSIGNED NOT NULL,
  `term_id` int(10) UNSIGNED DEFAULT NULL,
  `review_period` varchar(50) DEFAULT NULL,
  `review_type` enum('probation','annual','mid_year','special') DEFAULT 'annual',
  `reviewer_id` int(10) UNSIGNED NOT NULL,
  `review_date` date NOT NULL,
  `overall_score` decimal(5,2) DEFAULT NULL COMMENT 'Percentage score',
  `performance_grade` char(1) DEFAULT NULL,
  `overall_rating` enum('exceeding','meeting','approaching','below') DEFAULT NULL,
  `strengths` text DEFAULT NULL,
  `areas_for_improvement` text DEFAULT NULL,
  `recommendations` text DEFAULT NULL,
  `action_plan` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `status` enum('draft','submitted','approved','completed') DEFAULT 'draft',
  `completion_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_perf_staff_year` (`staff_id`,`academic_year_id`),
  KEY `idx_perf_reviewer` (`reviewer_id`),
  KEY `fk_perf_review_year` (`academic_year_id`),
  KEY `fk_perf_review_term` (`term_id`),
  KEY `idx_performance_reviews_staff_year` (`staff_id`,`academic_year_id`,`status`),
  KEY `idx_performance_reviews_status` (`status`,`review_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `staff_performance_reviews`:
--   `reviewer_id`
--       `users` -> `id`
--   `staff_id`
--       `staff` -> `id`
--   `term_id`
--       `academic_terms` -> `id`
--   `academic_year_id`
--       `academic_years` -> `id`
--

--
-- Truncate table before insert `staff_performance_reviews`
--

TRUNCATE TABLE `staff_performance_reviews`;
--
-- Triggers `staff_performance_reviews`
--
DROP TRIGGER IF EXISTS `trg_notify_performance_review_complete`;
DELIMITER $$
CREATE TRIGGER `trg_notify_performance_review_complete` AFTER UPDATE ON `staff_performance_reviews` FOR EACH ROW BEGIN
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        
        INSERT INTO workflow_notifications (
            user_id,
            notification_type,
            title,
            message,
            created_at
        ) VALUES (
            NEW.staff_id,
            'review_completed',
            'Performance Review Completed',
            CONCAT('Your ', NEW.review_type, ' performance review has been completed. Overall Score: ', NEW.overall_score, ', Grade: ', IFNULL(NEW.performance_grade, 'N/A')),
            NOW()
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `staff_qualifications`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `staff_qualifications`;
CREATE TABLE IF NOT EXISTS `staff_qualifications` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `qualification_type` enum('degree','diploma','certificate','other') NOT NULL,
  `title` varchar(255) NOT NULL,
  `institution` varchar(255) NOT NULL,
  `year_obtained` year(4) NOT NULL,
  `description` text DEFAULT NULL,
  `document_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staff` (`staff_id`),
  KEY `idx_year` (`year_obtained`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `staff_qualifications`:
--   `staff_id`
--       `staff` -> `id`
--

--
-- Truncate table before insert `staff_qualifications`
--

TRUNCATE TABLE `staff_qualifications`;
-- --------------------------------------------------------

--
-- Table structure for table `staff_types`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `staff_types`;
CREATE TABLE IF NOT EXISTS `staff_types` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `staff_types`:
--

--
-- Truncate table before insert `staff_types`
--

TRUNCATE TABLE `staff_types`;
-- --------------------------------------------------------

--
-- Table structure for table `storage_locations`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `storage_locations`;
CREATE TABLE IF NOT EXISTS `storage_locations` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `storage_locations`:
--   `parent_id`
--       `storage_locations` -> `id`
--

--
-- Truncate table before insert `storage_locations`
--

TRUNCATE TABLE `storage_locations`;
-- --------------------------------------------------------

--
-- Table structure for table `students`
--
-- Creation: Nov 11, 2025 at 08:48 AM
--

DROP TABLE IF EXISTS `students`;
CREATE TABLE IF NOT EXISTS `students` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `admission_no` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `stream_id` int(10) UNSIGNED NOT NULL,
  `student_type_id` int(10) UNSIGNED DEFAULT 1 COMMENT 'Day/Boarding/Weekly boarder - links to student_types',
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `admission_date` date NOT NULL,
  `nemis_number` varchar(50) DEFAULT NULL COMMENT 'National NEMIS registration number',
  `upi` varchar(50) DEFAULT NULL COMMENT 'Unique Personal Identifier (assigned at Grade 3)',
  `upi_status` enum('not_assigned','assigned','transferred','pending') NOT NULL DEFAULT 'not_assigned' COMMENT 'UPI assignment status',
  `status` enum('active','inactive','graduated','transferred','suspended') NOT NULL DEFAULT 'active',
  `photo_url` varchar(255) DEFAULT NULL,
  `qr_code_path` varchar(255) DEFAULT NULL COMMENT 'Path to generated QR code image',
  `is_sponsored` tinyint(1) DEFAULT 0 COMMENT 'Flag indicating if student is sponsored',
  `sponsor_name` varchar(100) DEFAULT NULL COMMENT 'Name of the sponsor/sponsoring organization',
  `sponsor_type` enum('partial','full','conditional') DEFAULT NULL COMMENT 'Type of sponsorship: partial (pays some fees), full (pays all fees), conditional (pays certain fee types only)',
  `sponsor_waiver_percentage` decimal(5,2) DEFAULT 0.00 COMMENT 'Percentage of fees sponsored (0-100)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `blood_group` varchar(10) DEFAULT NULL COMMENT 'Blood group (A+, B+, O+, AB+, A-, B-, O-, AB-)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admission_no` (`admission_no`),
  UNIQUE KEY `upi` (`upi`),
  UNIQUE KEY `uk_nemis` (`nemis_number`),
  KEY `idx_stream` (`stream_id`),
  KEY `idx_student_type` (`student_type_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_upi` (`upi`),
  KEY `idx_upi_status` (`upi_status`),
  KEY `idx_sponsored` (`is_sponsored`),
  KEY `idx_sponsor_type` (`sponsor_type`),
  KEY `idx_qr_code` (`qr_code_path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `students`:
--   `stream_id`
--       `class_streams` -> `id`
--   `user_id`
--       `users` -> `id`
--

--
-- Truncate table before insert `students`
--

TRUNCATE TABLE `students`;
--
-- Triggers `students`
--
DROP TRIGGER IF EXISTS `trg_auto_link_parent`;
DELIMITER $$
CREATE TRIGGER `trg_auto_link_parent` AFTER INSERT ON `students` FOR EACH ROW BEGIN IF NEW.user_id IS NOT NULL THEN
INSERT IGNORE INTO student_parents (student_id, parent_id)
VALUES (NEW.id, NEW.user_id);
END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_emit_student_status_event`;
DELIMITER $$
CREATE TRIGGER `trg_emit_student_status_event` AFTER UPDATE ON `students` FOR EACH ROW BEGIN IF NEW.status != OLD.status THEN
INSERT INTO system_events (event_type, event_data, created_at)
VALUES (
    'student_status_changed',
    JSON_OBJECT(
      'student_id',
      NEW.id,
      'old_status',
      OLD.status,
      'new_status',
      NEW.status
    ),
    NOW()
  );
END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_update_class_capacity`;
DELIMITER $$
CREATE TRIGGER `trg_update_class_capacity` AFTER INSERT ON `students` FOR EACH ROW BEGIN
UPDATE class_streams cs
SET current_students = (
    SELECT COUNT(*)
    FROM students s
    WHERE s.stream_id = cs.id
  )
WHERE cs.id = NEW.stream_id;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_update_student_status`;
DELIMITER $$
CREATE TRIGGER `trg_update_student_status` AFTER UPDATE ON `students` FOR EACH ROW BEGIN IF NEW.status IN ('graduated', 'transferred')
  AND OLD.status != NEW.status THEN
INSERT INTO notifications (
    user_id,
    type,
    title,
    message,
    priority,
    created_at
  )
VALUES (
    get_parent_user_id(NEW.id),
    'info',
    'Student Status Update',
    CONCAT('Your child status changed to ', NEW.status),
    'medium',
    NOW()
  );
END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `student_activities`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `student_activities`;
CREATE TABLE IF NOT EXISTS `student_activities` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `activity_id` int(10) UNSIGNED NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student_activity` (`student_id`,`activity_id`),
  KEY `activity_id` (`activity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `student_activities`:
--   `student_id`
--       `students` -> `id`
--   `activity_id`
--       `activities` -> `id`
--

--
-- Truncate table before insert `student_activities`
--

TRUNCATE TABLE `student_activities`;
-- --------------------------------------------------------

--
-- Table structure for table `student_arrears`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `student_arrears`;
CREATE TABLE IF NOT EXISTS `student_arrears` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `academic_year` year(4) NOT NULL,
  `term_id` int(10) UNSIGNED DEFAULT NULL,
  `total_arrears` decimal(10,2) NOT NULL,
  `arrears_date` date NOT NULL,
  `last_payment_date` date DEFAULT NULL,
  `arrears_status` enum('current','overdue','settled','written_off') NOT NULL DEFAULT 'current',
  `days_overdue` int(11) DEFAULT 0,
  `settlement_plan_id` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student_year` (`student_id`,`academic_year`),
  KEY `idx_status` (`arrears_status`),
  KEY `idx_days_overdue` (`days_overdue`),
  KEY `idx_term` (`term_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `student_arrears`:
--

--
-- Truncate table before insert `student_arrears`
--

TRUNCATE TABLE `student_arrears`;
--
-- Triggers `student_arrears`
--
DROP TRIGGER IF EXISTS `trg_log_arrears_creation`;
DELIMITER $$
CREATE TRIGGER `trg_log_arrears_creation` AFTER INSERT ON `student_arrears` FOR EACH ROW BEGIN
    INSERT INTO system_events (event_type, event_data, created_at)
    VALUES (
        'arrears_created',
        JSON_OBJECT(
            'student_id', NEW.student_id,
            'arrears_id', NEW.id,
            'amount', NEW.total_arrears,
            'academic_year', NEW.academic_year,
            'status', NEW.arrears_status
        ),
        NOW()
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `student_attendance`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `student_attendance`;
CREATE TABLE IF NOT EXISTS `student_attendance` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent','late') NOT NULL,
  `class_id` int(10) UNSIGNED DEFAULT NULL,
  `term_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `marked_by` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_student_date` (`student_id`,`date`),
  KEY `class_id` (`class_id`),
  KEY `term_id` (`term_id`),
  KEY `marked_by` (`marked_by`),
  KEY `idx_class_date_status` (`class_id`,`date`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `student_attendance`:
--   `student_id`
--       `students` -> `id`
--   `class_id`
--       `classes` -> `id`
--   `term_id`
--       `academic_terms` -> `id`
--   `marked_by`
--       `users` -> `id`
--

--
-- Truncate table before insert `student_attendance`
--

TRUNCATE TABLE `student_attendance`;
--
-- Triggers `student_attendance`
--
DROP TRIGGER IF EXISTS `trg_emit_attendance_event`;
DELIMITER $$
CREATE TRIGGER `trg_emit_attendance_event` AFTER INSERT ON `student_attendance` FOR EACH ROW BEGIN
INSERT INTO system_events (event_type, event_data, created_at)
VALUES (
    'attendance_marked',
    JSON_OBJECT(
      'student_id',
      NEW.student_id,
      'date',
      NEW.date,
      'status',
      NEW.status,
      'class_id',
      NEW.class_id,
      'term_id',
      NEW.term_id
    ),
    NOW()
  );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `student_discipline`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `student_discipline`;
CREATE TABLE IF NOT EXISTS `student_discipline` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `incident_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `severity` enum('low','medium','high') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `action_taken` text DEFAULT NULL,
  `resolved_by` int(10) UNSIGNED DEFAULT NULL,
  `resolution_date` date DEFAULT NULL,
  `status` enum('pending','resolved','escalated') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `resolved_by` (`resolved_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `student_discipline`:
--   `student_id`
--       `students` -> `id`
--   `resolved_by`
--       `staff` -> `id`
--

--
-- Truncate table before insert `student_discipline`
--

TRUNCATE TABLE `student_discipline`;
-- --------------------------------------------------------

--
-- Table structure for table `student_fee_balances`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `student_fee_balances`;
CREATE TABLE IF NOT EXISTS `student_fee_balances` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `fee_structure_id` int(10) UNSIGNED NOT NULL,
  `academic_term_id` int(10) UNSIGNED NOT NULL,
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student_term` (`student_id`,`academic_term_id`),
  KEY `fee_structure_id` (`fee_structure_id`),
  KEY `academic_term_id` (`academic_term_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `student_fee_balances`:
--   `student_id`
--       `students` -> `id`
--   `fee_structure_id`
--       `fee_structures` -> `id`
--   `academic_term_id`
--       `academic_terms` -> `id`
--

--
-- Truncate table before insert `student_fee_balances`
--

TRUNCATE TABLE `student_fee_balances`;
-- --------------------------------------------------------

--
-- Table structure for table `student_fee_carryover`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `student_fee_carryover`;
CREATE TABLE IF NOT EXISTS `student_fee_carryover` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `academic_year` int(11) NOT NULL COMMENT 'Academic year (e.g., 2024)',
  `term_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Term ID for term-level carryover, NULL for year-level',
  `previous_balance` decimal(12,2) DEFAULT 0.00 COMMENT 'Balance carried forward from previous period (positive = debt)',
  `surplus_amount` decimal(12,2) DEFAULT 0.00 COMMENT 'Surplus from previous period (positive = credit)',
  `action_taken` enum('fresh_bill','add_to_current','deduct_from_current','manual_adjustment') DEFAULT 'fresh_bill' COMMENT 'Action taken during carryover',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student_year_term` (`student_id`,`academic_year`,`term_id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_year` (`academic_year`),
  KEY `idx_term` (`term_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `student_fee_carryover`:
--   `student_id`
--       `students` -> `id`
--

--
-- Truncate table before insert `student_fee_carryover`
--

TRUNCATE TABLE `student_fee_carryover`;
-- --------------------------------------------------------

--
-- Table structure for table `student_fee_obligations`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `student_fee_obligations`;
CREATE TABLE IF NOT EXISTS `student_fee_obligations` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `academic_year` year(4) NOT NULL,
  `term_id` int(10) UNSIGNED NOT NULL,
  `fee_structure_detail_id` int(10) UNSIGNED NOT NULL,
  `amount_due` decimal(10,2) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount_waived` decimal(10,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(10,2) GENERATED ALWAYS AS (`amount_due` - `amount_paid` - `amount_waived`) STORED,
  `status` enum('pending','partial','paid','arrears') NOT NULL DEFAULT 'pending',
  `due_date` date DEFAULT NULL,
  `year_balance` decimal(12,2) DEFAULT 0.00 COMMENT 'Total balance for entire academic year (sum of all terms)',
  `term_balance` decimal(12,2) DEFAULT 0.00 COMMENT 'Balance for specific term',
  `previous_year_balance` decimal(12,2) DEFAULT 0.00 COMMENT 'Balance carried over from previous academic year',
  `previous_term_balance` decimal(12,2) DEFAULT 0.00 COMMENT 'Balance carried over from previous term',
  `is_sponsored` tinyint(1) DEFAULT 0 COMMENT 'Flag if this obligation is for a sponsored student',
  `sponsored_waiver_amount` decimal(12,2) DEFAULT 0.00 COMMENT 'Amount waived due to sponsorship',
  `payment_status` enum('pending','partial','paid','arrears','waived') NOT NULL DEFAULT 'pending' COMMENT 'Detailed payment status',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student_obligation` (`student_id`,`academic_year`,`term_id`,`fee_structure_detail_id`),
  KEY `idx_student_year_term` (`student_id`,`academic_year`,`term_id`),
  KEY `idx_status` (`status`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_balance` (`balance`),
  KEY `idx_year_balance` (`year_balance`),
  KEY `idx_term_balance` (`term_balance`),
  KEY `idx_is_sponsored` (`is_sponsored`),
  KEY `idx_due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `student_fee_obligations`:
--

--
-- Truncate table before insert `student_fee_obligations`
--

TRUNCATE TABLE `student_fee_obligations`;
--
-- Triggers `student_fee_obligations`
--
DROP TRIGGER IF EXISTS `trg_check_and_create_arrears`;
DELIMITER $$
CREATE TRIGGER `trg_check_and_create_arrears` AFTER UPDATE ON `student_fee_obligations` FOR EACH ROW BEGIN
    DECLARE v_arrears_exists INT;
    IF NEW.status IN ('pending', 'partial')
    AND NEW.due_date < CURDATE() THEN
        SELECT COUNT(*) INTO v_arrears_exists
        FROM student_arrears
        WHERE student_id = NEW.student_id
          AND academic_year = NEW.academic_year
          AND term_id = NEW.term_id;
        IF v_arrears_exists = 0 THEN 
            CALL sp_create_arrears_record(NEW.student_id, NEW.academic_year, NEW.term_id);
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `student_parents`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `student_parents`;
CREATE TABLE IF NOT EXISTS `student_parents` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student_parent` (`student_id`,`parent_id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `student_parents`:
--   `student_id`
--       `students` -> `id`
--   `parent_id`
--       `parents` -> `id`
--

--
-- Truncate table before insert `student_parents`
--

TRUNCATE TABLE `student_parents`;
-- --------------------------------------------------------

--
-- Table structure for table `student_payment_history_summary`
--
-- Creation: Nov 12, 2025 at 01:01 PM
--

DROP TABLE IF EXISTS `student_payment_history_summary`;
CREATE TABLE IF NOT EXISTS `student_payment_history_summary` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `academic_year` year(4) NOT NULL,
  `term_id` int(10) UNSIGNED NOT NULL,
  `total_fees_due` decimal(10,2) DEFAULT 0.00,
  `total_paid` decimal(10,2) DEFAULT 0.00,
  `payment_count` int(11) DEFAULT 0,
  `balance` decimal(10,2) DEFAULT 0.00,
  `cash_payments` decimal(10,2) DEFAULT 0.00,
  `mpesa_payments` decimal(10,2) DEFAULT 0.00,
  `bank_transfers` decimal(10,2) DEFAULT 0.00,
  `last_payment_date` datetime DEFAULT NULL,
  `last_updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_year_term` (`student_id`,`academic_year`,`term_id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_year_term` (`academic_year`,`term_id`),
  KEY `idx_balance` (`balance`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- RELATIONSHIPS FOR TABLE `student_payment_history_summary`:
--

--
-- Truncate table before insert `student_payment_history_summary`
--

TRUNCATE TABLE `student_payment_history_summary`;
-- --------------------------------------------------------

--
-- Table structure for table `student_promotions`
--
-- Creation: Nov 11, 2025 at 10:58 AM
--

DROP TABLE IF EXISTS `student_promotions`;
CREATE TABLE IF NOT EXISTS `student_promotions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id` int(10) UNSIGNED NOT NULL,
  `from_enrollment_id` int(10) UNSIGNED DEFAULT NULL,
  `to_enrollment_id` int(10) UNSIGNED DEFAULT NULL,
  `from_academic_year_id` int(10) UNSIGNED DEFAULT NULL,
  `to_academic_year_id` int(10) UNSIGNED DEFAULT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `current_class_id` int(10) UNSIGNED NOT NULL,
  `current_stream_id` int(10) UNSIGNED NOT NULL,
  `promoted_to_class_id` int(10) UNSIGNED DEFAULT NULL,
  `promoted_to_stream_id` int(10) UNSIGNED DEFAULT NULL,
  `from_academic_year` year(4) NOT NULL,
  `to_academic_year` year(4) NOT NULL,
  `from_term_id` int(10) UNSIGNED NOT NULL,
  `promotion_status` enum('pending_approval','approved','rejected','transferred','retained','graduated','suspended','on_hold') NOT NULL DEFAULT 'pending_approval',
  `overall_score` decimal(5,2) DEFAULT NULL,
  `final_grade` varchar(4) DEFAULT NULL,
  `promotion_reason` varchar(255) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `transfer_to_school` varchar(255) DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approval_date` datetime DEFAULT NULL,
  `approval_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_promotion_cycle` (`student_id`,`from_academic_year`,`to_academic_year`),
  KEY `idx_batch_id` (`batch_id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_promotion_status` (`promotion_status`),
  KEY `idx_academic_years` (`from_academic_year`,`to_academic_year`),
  KEY `idx_approval_date` (`approval_date`),
  KEY `current_class_id` (`current_class_id`),
  KEY `current_stream_id` (`current_stream_id`),
  KEY `promoted_to_class_id` (`promoted_to_class_id`),
  KEY `promoted_to_stream_id` (`promoted_to_stream_id`),
  KEY `from_term_id` (`from_term_id`),
  KEY `approved_by` (`approved_by`),
  KEY `idx_from_enrollment` (`from_enrollment_id`),
  KEY `idx_to_enrollment` (`to_enrollment_id`),
  KEY `idx_from_year` (`from_academic_year_id`),
  KEY `idx_to_year` (`to_academic_year_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `student_promotions`:
--   `from_enrollment_id`
--       `class_enrollments` -> `id`
--   `batch_id`
--       `promotion_batches` -> `id`
--   `student_id`
--       `students` -> `id`
--   `current_class_id`
--       `classes` -> `id`
--   `current_stream_id`
--       `class_streams` -> `id`
--   `promoted_to_class_id`
--       `classes` -> `id`
--   `promoted_to_stream_id`
--       `class_streams` -> `id`
--   `from_term_id`
--       `academic_terms` -> `id`
--   `approved_by`
--       `users` -> `id`
--

--
-- Truncate table before insert `student_promotions`
--

TRUNCATE TABLE `student_promotions`;
-- --------------------------------------------------------

--
-- Table structure for table `student_registrations`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `student_registrations`;
CREATE TABLE IF NOT EXISTS `student_registrations` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `class_id` int(10) UNSIGNED NOT NULL,
  `term_id` int(10) UNSIGNED NOT NULL,
  `registration_date` date NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student_class_term` (`student_id`,`class_id`,`term_id`),
  KEY `class_id` (`class_id`),
  KEY `term_id` (`term_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `student_registrations`:
--   `student_id`
--       `students` -> `id`
--   `class_id`
--       `classes` -> `id`
--   `term_id`
--       `academic_terms` -> `id`
--

--
-- Truncate table before insert `student_registrations`
--

TRUNCATE TABLE `student_registrations`;
-- --------------------------------------------------------

--
-- Table structure for table `student_suspensions`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `student_suspensions`;
CREATE TABLE IF NOT EXISTS `student_suspensions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `academic_year` year(4) NOT NULL,
  `suspension_type` enum('disciplinary','medical','financial','other') NOT NULL,
  `reason` text NOT NULL,
  `suspension_date` date NOT NULL,
  `expected_return_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `suspended_by` int(10) UNSIGNED NOT NULL,
  `status` enum('active','resolved','pending','appealed') NOT NULL DEFAULT 'active',
  `appeal_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_student_year` (`student_id`,`academic_year`),
  KEY `idx_status` (`status`),
  KEY `idx_suspension_date` (`suspension_date`),
  KEY `suspended_by` (`suspended_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `student_suspensions`:
--   `student_id`
--       `students` -> `id`
--   `suspended_by`
--       `users` -> `id`
--

--
-- Truncate table before insert `student_suspensions`
--

TRUNCATE TABLE `student_suspensions`;
-- --------------------------------------------------------

--
-- Table structure for table `student_types`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `student_types`;
CREATE TABLE IF NOT EXISTS `student_types` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_code` (`code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `student_types`:
--

--
-- Truncate table before insert `student_types`
--

TRUNCATE TABLE `student_types`;
-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `suppliers`:
--

--
-- Truncate table before insert `suppliers`
--

TRUNCATE TABLE `suppliers`;
-- --------------------------------------------------------

--
-- Table structure for table `system_events`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `system_events`;
CREATE TABLE IF NOT EXISTS `system_events` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_type` varchar(100) NOT NULL,
  `event_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`event_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `event_type` (`event_type`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `system_events`:
--

--
-- Truncate table before insert `system_events`
--

TRUNCATE TABLE `system_events`;
-- --------------------------------------------------------

--
-- Table structure for table `tax_brackets`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `tax_brackets`;
CREATE TABLE IF NOT EXISTS `tax_brackets` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `financial_year` int(11) NOT NULL,
  `min_income` decimal(12,2) NOT NULL,
  `max_income` decimal(12,2) NOT NULL,
  `tax_rate` decimal(5,2) NOT NULL,
  `relief_amount` decimal(12,2) DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_year_bracket` (`financial_year`,`min_income`,`max_income`),
  KEY `idx_year_income` (`financial_year`,`min_income`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `tax_brackets`:
--

--
-- Truncate table before insert `tax_brackets`
--

TRUNCATE TABLE `tax_brackets`;
-- --------------------------------------------------------

--
-- Table structure for table `tax_withholding_history`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `tax_withholding_history`;
CREATE TABLE IF NOT EXISTS `tax_withholding_history` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `financial_year` int(11) NOT NULL,
  `payroll_month` int(11) NOT NULL,
  `gross_income` decimal(12,2) NOT NULL,
  `tax_calculated` decimal(12,2) NOT NULL,
  `tax_withheld` decimal(12,2) NOT NULL,
  `cumulative_tax` decimal(12,2) NOT NULL,
  `kra_pin` varchar(50) DEFAULT NULL,
  `filed_with_kra` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_staff_year` (`staff_id`,`financial_year`),
  KEY `idx_kra_pin` (`kra_pin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `tax_withholding_history`:
--   `staff_id`
--       `staff` -> `id`
--

--
-- Truncate table before insert `tax_withholding_history`
--

TRUNCATE TABLE `tax_withholding_history`;
-- --------------------------------------------------------

--
-- Table structure for table `template_categories`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `template_categories`;
CREATE TABLE IF NOT EXISTS `template_categories` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `template_categories`:
--

--
-- Truncate table before insert `template_categories`
--

TRUNCATE TABLE `template_categories`;
-- --------------------------------------------------------

--
-- Table structure for table `term_consolidations`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `term_consolidations`;
CREATE TABLE IF NOT EXISTS `term_consolidations` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `term_id` int(10) UNSIGNED NOT NULL,
  `academic_year` year(4) NOT NULL,
  `total_subjects` int(11) DEFAULT 0,
  `total_assessed_subjects` int(11) DEFAULT 0,
  `avg_overall_percentage` decimal(5,2) DEFAULT 0.00,
  `avg_overall_grade` varchar(4) DEFAULT NULL,
  `performance_summary` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`performance_summary`)),
  `class_position` int(11) DEFAULT NULL,
  `class_total` int(11) DEFAULT NULL,
  `percentile` decimal(5,2) DEFAULT NULL,
  `points_total` decimal(5,1) DEFAULT 0.0,
  `best_subject_id` int(10) UNSIGNED DEFAULT NULL,
  `best_subject_grade` varchar(4) DEFAULT NULL,
  `worst_subject_id` int(10) UNSIGNED DEFAULT NULL,
  `worst_subject_grade` varchar(4) DEFAULT NULL,
  `consolidated_at` timestamp NULL DEFAULT NULL,
  `consolidated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student_term` (`student_id`,`term_id`),
  KEY `idx_academic_year` (`academic_year`),
  KEY `idx_class_position` (`class_position`),
  KEY `idx_avg_grade` (`avg_overall_grade`),
  KEY `fk_tc_term` (`term_id`),
  KEY `fk_tc_consolidated_by` (`consolidated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `term_consolidations`:
--   `consolidated_by`
--       `users` -> `id`
--   `student_id`
--       `students` -> `id`
--   `term_id`
--       `academic_terms` -> `id`
--

--
-- Truncate table before insert `term_consolidations`
--

TRUNCATE TABLE `term_consolidations`;
-- --------------------------------------------------------

--
-- Table structure for table `term_subject_scores`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `term_subject_scores`;
CREATE TABLE IF NOT EXISTS `term_subject_scores` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` int(10) UNSIGNED NOT NULL,
  `term_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `formative_total` decimal(8,2) DEFAULT 0.00,
  `formative_max` decimal(8,2) DEFAULT 0.00,
  `formative_percentage` decimal(5,2) DEFAULT 0.00,
  `formative_grade` varchar(4) DEFAULT NULL,
  `formative_count` int(11) DEFAULT 0,
  `summative_total` decimal(8,2) DEFAULT 0.00,
  `summative_max` decimal(8,2) DEFAULT 0.00,
  `summative_percentage` decimal(5,2) DEFAULT 0.00,
  `summative_grade` varchar(4) DEFAULT NULL,
  `summative_count` int(11) DEFAULT 0,
  `overall_score` decimal(8,2) DEFAULT 0.00,
  `overall_percentage` decimal(5,2) DEFAULT 0.00,
  `overall_grade` varchar(4) DEFAULT NULL,
  `overall_points` decimal(3,1) DEFAULT 0.0,
  `assessment_count` int(11) DEFAULT 0,
  `calculated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student_term_subject` (`student_id`,`term_id`,`subject_id`),
  KEY `idx_term_subject` (`term_id`,`subject_id`),
  KEY `idx_student_term` (`student_id`,`term_id`),
  KEY `idx_overall_grade` (`overall_grade`),
  KEY `fk_tss_subject` (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `term_subject_scores`:
--   `student_id`
--       `students` -> `id`
--   `subject_id`
--       `curriculum_units` -> `id`
--   `term_id`
--       `academic_terms` -> `id`
--

--
-- Truncate table before insert `term_subject_scores`
--

TRUNCATE TABLE `term_subject_scores`;
-- --------------------------------------------------------

--
-- Table structure for table `transport_routes`
--
-- Creation: Nov 09, 2025 at 11:15 PM
--

DROP TABLE IF EXISTS `transport_routes`;
CREATE TABLE IF NOT EXISTS `transport_routes` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `start_point` varchar(255) NOT NULL,
  `end_point` varchar(255) NOT NULL,
  `code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `distance` decimal(10,2) DEFAULT NULL,
  `estimated_time` time DEFAULT NULL,
  `fee` decimal(10,2) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `morning_departure` time NOT NULL,
  `afternoon_departure` time NOT NULL,
  `estimated_duration` int(11) NOT NULL COMMENT 'Duration in minutes',
  `max_capacity` int(11) NOT NULL DEFAULT 0,
  `current_capacity` int(11) NOT NULL DEFAULT 0,
  `last_active_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `transport_routes`:
--

--
-- Truncate table before insert `transport_routes`
--

TRUNCATE TABLE `transport_routes`;
-- --------------------------------------------------------

--
-- Table structure for table `transport_stops`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `transport_stops`;
CREATE TABLE IF NOT EXISTS `transport_stops` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `route_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `sequence` int(11) NOT NULL,
  `arrival_time` time DEFAULT NULL,
  `departure_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_route` (`route_id`),
  KEY `idx_sequence` (`sequence`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `transport_stops`:
--   `route_id`
--       `transport_routes` -> `id`
--

--
-- Truncate table before insert `transport_stops`
--

TRUNCATE TABLE `transport_stops`;
-- --------------------------------------------------------

--
-- Table structure for table `transport_vehicles`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `transport_vehicles`;
CREATE TABLE IF NOT EXISTS `transport_vehicles` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `registration_number` varchar(20) NOT NULL,
  `type` varchar(50) NOT NULL,
  `model` varchar(50) DEFAULT NULL,
  `make` varchar(50) DEFAULT NULL,
  `year` year(4) DEFAULT NULL,
  `capacity` int(11) NOT NULL,
  `driver_id` int(10) UNSIGNED DEFAULT NULL,
  `insurance_expiry` date DEFAULT NULL,
  `service_due_date` date DEFAULT NULL,
  `status` enum('active','maintenance','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_registration` (`registration_number`),
  KEY `idx_driver` (`driver_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `transport_vehicles`:
--   `driver_id`
--       `staff` -> `id`
--

--
-- Truncate table before insert `transport_vehicles`
--

TRUNCATE TABLE `transport_vehicles`;
-- --------------------------------------------------------

--
-- Table structure for table `transport_vehicle_routes`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `transport_vehicle_routes`;
CREATE TABLE IF NOT EXISTS `transport_vehicle_routes` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(10) UNSIGNED NOT NULL,
  `route_id` int(10) UNSIGNED NOT NULL,
  `direction` enum('pickup','dropoff') NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vehicle_route_direction` (`vehicle_id`,`route_id`,`direction`),
  KEY `idx_status` (`status`),
  KEY `route_id` (`route_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `transport_vehicle_routes`:
--   `vehicle_id`
--       `transport_vehicles` -> `id`
--   `route_id`
--       `transport_routes` -> `id`
--

--
-- Truncate table before insert `transport_vehicle_routes`
--

TRUNCATE TABLE `transport_vehicle_routes`;
-- --------------------------------------------------------

--
-- Table structure for table `unit_topics`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `unit_topics`;
CREATE TABLE IF NOT EXISTS `unit_topics` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `learning_outcomes` text DEFAULT NULL,
  `suggested_activities` text DEFAULT NULL,
  `duration` int(11) NOT NULL COMMENT 'Duration in hours',
  `order_sequence` int(11) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_unit_order` (`unit_id`,`order_sequence`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `unit_topics`:
--   `unit_id`
--       `curriculum_units` -> `id`
--

--
-- Truncate table before insert `unit_topics`
--

TRUNCATE TABLE `unit_topics`;
-- --------------------------------------------------------

--
-- Table structure for table `users`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `status` enum('active','inactive','suspended','pending') NOT NULL DEFAULT 'pending',
  `last_login` datetime DEFAULT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  UNIQUE KEY `uk_email` (`email`),
  KEY `idx_role_status` (`status`),
  KEY `idx_last_login` (`last_login`),
  KEY `idx_user_status_role` (`status`),
  KEY `fk_users_role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `users`:
--   `role_id`
--       `roles` -> `id`
--

--
-- Truncate table before insert `users`
--

TRUNCATE TABLE `users`;
--
-- Triggers `users`
--
DROP TRIGGER IF EXISTS `trg_after_password_change`;
DELIMITER $$
CREATE TRIGGER `trg_after_password_change` AFTER UPDATE ON `users` FOR EACH ROW BEGIN IF NEW.password != OLD.password THEN
UPDATE users
SET password_changed_at = CURRENT_TIMESTAMP
WHERE id = NEW.id;
END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_audit_delete`;
DELIMITER $$
CREATE TRIGGER `trg_audit_delete` BEFORE DELETE ON `users` FOR EACH ROW BEGIN
INSERT INTO audit_trail (
    user_id,
    action,
    table_name,
    record_id,
    old_values,
    created_at
  )
VALUES (
    OLD.id,
    'DELETE',
    'users',
    OLD.id,
    JSON_OBJECT(
      'username',
      OLD.username,
      'email',
      OLD.email,
      'role_id',
      OLD.role_id,
      'status',
      OLD.status
    ),
    NOW()
  );
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_audit_insert`;
DELIMITER $$
CREATE TRIGGER `trg_audit_insert` AFTER INSERT ON `users` FOR EACH ROW BEGIN
INSERT INTO audit_trail (
    user_id,
    action,
    table_name,
    record_id,
    new_values,
    created_at
  )
VALUES (
    NEW.id,
    'INSERT',
    'users',
    NEW.id,
    JSON_OBJECT(
      'username',
      NEW.username,
      'email',
      NEW.email,
      'role_id',
      NEW.role_id,
      'status',
      NEW.status
    ),
    NOW()
  );
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_audit_update`;
DELIMITER $$
CREATE TRIGGER `trg_audit_update` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
INSERT INTO audit_trail (
    user_id,
    action,
    table_name,
    record_id,
    old_values,
    new_values,
    created_at
  )
VALUES (
    NEW.id,
    'UPDATE',
    'users',
    NEW.id,
    JSON_OBJECT(
      'username',
      OLD.username,
      'email',
      OLD.email,
      'role_id',
      OLD.role_id,
      'status',
      OLD.status
    ),
    JSON_OBJECT(
      'username',
      NEW.username,
      'email',
      NEW.email,
      'role_id',
      NEW.role_id,
      'status',
      NEW.status
    ),
    NOW()
  );
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_prevent_user_delete`;
DELIMITER $$
CREATE TRIGGER `trg_prevent_user_delete` BEFORE DELETE ON `users` FOR EACH ROW BEGIN
DECLARE role_name VARCHAR(50);
SELECT name INTO role_name
FROM roles
WHERE id = OLD.role_id;
IF role_name = 'admin' THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Cannot delete admin users';
END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_validate_email`;
DELIMITER $$
CREATE TRIGGER `trg_validate_email` BEFORE INSERT ON `users` FOR EACH ROW BEGIN IF NEW.email NOT REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+.[A-Za-z]{2,}$' THEN SIGNAL SQLSTATE '45000'
SET MESSAGE_TEXT = 'Invalid email format';
END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `user_login_attempts`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `user_login_attempts`;
CREATE TABLE IF NOT EXISTS `user_login_attempts` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` datetime NOT NULL,
  `attempt_status` enum('success','failed','locked') NOT NULL,
  `failure_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_username_ip` (`username`,`ip_address`,`attempt_time`),
  KEY `idx_status` (`attempt_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `user_login_attempts`:
--

--
-- Truncate table before insert `user_login_attempts`
--

TRUNCATE TABLE `user_login_attempts`;
-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `user_permissions`;
CREATE TABLE IF NOT EXISTS `user_permissions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `permission_name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_permission` (`user_id`,`permission_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `user_permissions`:
--   `user_id`
--       `users` -> `id`
--

--
-- Truncate table before insert `user_permissions`
--

TRUNCATE TABLE `user_permissions`;
-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `user_roles`;
CREATE TABLE IF NOT EXISTS `user_roles` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_role` (`user_id`,`role_id`),
  KEY `role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `user_roles`:
--   `user_id`
--       `users` -> `id`
--   `role_id`
--       `roles` -> `id`
--

--
-- Truncate table before insert `user_roles`
--

TRUNCATE TABLE `user_roles`;
-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `login_time` datetime NOT NULL,
  `last_activity` datetime NOT NULL,
  `logout_time` datetime DEFAULT NULL,
  `session_status` enum('active','expired','logged_out') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token` (`session_token`),
  KEY `idx_user_status` (`user_id`,`session_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `user_sessions`:
--   `user_id`
--       `users` -> `id`
--

--
-- Truncate table before insert `user_sessions`
--

TRUNCATE TABLE `user_sessions`;
-- --------------------------------------------------------

--
-- Table structure for table `vehicle_fuel_logs`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `vehicle_fuel_logs`;
CREATE TABLE IF NOT EXISTS `vehicle_fuel_logs` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(10) UNSIGNED NOT NULL,
  `fill_date` date NOT NULL,
  `liters` decimal(10,2) NOT NULL,
  `cost_per_liter` decimal(10,2) NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `odometer_reading` int(10) UNSIGNED NOT NULL,
  `filled_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vehicle_date` (`vehicle_id`,`fill_date`),
  KEY `filled_by` (`filled_by`),
  KEY `idx_vehicle_fill_date` (`vehicle_id`,`fill_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `vehicle_fuel_logs`:
--   `vehicle_id`
--       `transport_vehicles` -> `id`
--   `filled_by`
--       `staff` -> `id`
--

--
-- Truncate table before insert `vehicle_fuel_logs`
--

TRUNCATE TABLE `vehicle_fuel_logs`;
-- --------------------------------------------------------

--
-- Table structure for table `vehicle_maintenance`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `vehicle_maintenance`;
CREATE TABLE IF NOT EXISTS `vehicle_maintenance` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(10) UNSIGNED NOT NULL,
  `maintenance_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `maintenance_type` enum('routine','repair','inspection','emergency') NOT NULL,
  `odometer_reading` int(10) UNSIGNED DEFAULT NULL,
  `next_maintenance_date` date DEFAULT NULL,
  `next_maintenance_reading` int(10) UNSIGNED DEFAULT NULL,
  `parts_replaced` text DEFAULT NULL,
  `mechanic_details` text DEFAULT NULL,
  `documents_folder` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vehicle_id` (`vehicle_id`),
  KEY `idx_vehicle_maintenance_date` (`vehicle_id`,`maintenance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `vehicle_maintenance`:
--   `vehicle_id`
--       `transport_vehicles` -> `id`
--

--
-- Truncate table before insert `vehicle_maintenance`
--

TRUNCATE TABLE `vehicle_maintenance`;
-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_active_allocations`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_active_allocations`;
CREATE TABLE IF NOT EXISTS `vw_active_allocations` (
`id` int(10) unsigned
,`allocation_number` varchar(50)
,`item_name` varchar(255)
,`item_code` varchar(50)
,`category` varchar(100)
,`allocated_quantity` int(11)
,`returned_quantity` int(11)
,`outstanding_quantity` bigint(12)
,`department` varchar(100)
,`allocated_to_event` varchar(100)
,`class_name` varchar(50)
,`status` enum('allocated','issued','partially_returned','fully_returned','expired','cancelled')
,`allocation_date` date
,`expected_return_date` date
,`allocated_by_first` varchar(50)
,`allocated_by_last` varchar(50)
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `vw_active_students_per_class`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `vw_active_students_per_class`;
CREATE TABLE IF NOT EXISTS `vw_active_students_per_class` (
  `class_id` int(10) UNSIGNED DEFAULT NULL,
  `class_name` varchar(50) DEFAULT NULL,
  `stream_id` int(10) UNSIGNED DEFAULT NULL,
  `stream_name` varchar(50) DEFAULT NULL,
  `active_students` bigint(21) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- RELATIONSHIPS FOR TABLE `vw_active_students_per_class`:
--

--
-- Truncate table before insert `vw_active_students_per_class`
--

TRUNCATE TABLE `vw_active_students_per_class`;
-- --------------------------------------------------------

--
-- Table structure for table `vw_all_school_payments`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `vw_all_school_payments`;
CREATE TABLE IF NOT EXISTS `vw_all_school_payments` (
  `source` varchar(5) DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `student_id` int(10) UNSIGNED DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `transaction_date` datetime DEFAULT NULL,
  `status` varchar(9) DEFAULT NULL,
  `details` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- RELATIONSHIPS FOR TABLE `vw_all_school_payments`:
--

--
-- Truncate table before insert `vw_all_school_payments`
--

TRUNCATE TABLE `vw_all_school_payments`;
-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_arrears_summary`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_arrears_summary`;
CREATE TABLE IF NOT EXISTS `vw_arrears_summary` (
`level` varchar(50)
,`level_code` varchar(10)
,`students_in_arrears` bigint(21)
,`total_arrears_amount` decimal(32,2)
,`average_arrears` decimal(14,6)
,`overdue_students` bigint(21)
,`overdue_more_than_30_days` bigint(21)
,`overdue_more_than_60_days` bigint(21)
,`settlement_plans_active` bigint(21)
,`amount_on_settlement_plans` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_class_rosters`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_class_rosters`;
CREATE TABLE IF NOT EXISTS `vw_class_rosters` (
`assignment_id` int(10) unsigned
,`year_code` varchar(20)
,`class_name` varchar(50)
,`stream_name` varchar(50)
,`class_stream` varchar(103)
,`teacher_id` int(10) unsigned
,`teacher_name` varchar(101)
,`room_number` varchar(50)
,`capacity` int(11)
,`current_enrollment` int(11)
,`available_slots` bigint(12)
,`occupancy_rate` decimal(16,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_collection_rate_by_class`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_collection_rate_by_class`;
CREATE TABLE IF NOT EXISTS `vw_collection_rate_by_class` (
`level_name` varchar(50)
,`level_code` varchar(10)
,`academic_term` varchar(50)
,`total_students` bigint(21)
,`total_fees_due` decimal(32,2)
,`total_fees_paid` decimal(32,2)
,`total_fees_waived` decimal(32,2)
,`collection_rate_percent` decimal(38,2)
,`students_paid_in_full` bigint(21)
,`students_partial_payment` bigint(21)
,`students_no_payment` bigint(21)
,`average_payment_per_student` decimal(11,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_currently_blocked_ips`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_currently_blocked_ips`;
CREATE TABLE IF NOT EXISTS `vw_currently_blocked_ips` (
`ip_address` varchar(45)
,`reason` varchar(255)
,`blocked_at` timestamp
,`expires_at` timestamp
,`block_status` varchar(40)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_current_enrollments`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_current_enrollments`;
CREATE TABLE IF NOT EXISTS `vw_current_enrollments` (
`enrollment_id` int(10) unsigned
,`student_id` int(10) unsigned
,`admission_no` varchar(20)
,`student_name` varchar(152)
,`gender` enum('male','female','other')
,`student_status` enum('active','inactive','graduated','transferred','suspended')
,`academic_year_id` int(10) unsigned
,`year_code` varchar(20)
,`is_current` tinyint(1)
,`class_id` int(10) unsigned
,`class_name` varchar(50)
,`stream_id` int(10) unsigned
,`stream_name` varchar(50)
,`class_stream` varchar(103)
,`teacher_id` int(10) unsigned
,`teacher_first_name` varchar(50)
,`teacher_last_name` varchar(50)
,`room_number` varchar(50)
,`enrollment_status` enum('pending','active','completed','withdrawn','transferred','graduated')
,`year_average` decimal(5,2)
,`overall_grade` varchar(4)
,`class_rank` int(11)
,`attendance_percentage` decimal(5,2)
,`promotion_status` enum('pending','promoted','retained','transferred','graduated','withdrawn')
,`enrollment_date` date
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_current_staff_assignments`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_current_staff_assignments`;
CREATE TABLE IF NOT EXISTS `vw_current_staff_assignments` (
`id` int(10) unsigned
,`staff_id` int(10) unsigned
,`staff_name` varchar(101)
,`staff_no` varchar(20)
,`staff_category` varchar(100)
,`class_id` int(10) unsigned
,`class_name` varchar(50)
,`stream_name` varchar(50)
,`role` enum('class_teacher','subject_teacher','assistant_teacher','head_of_department')
,`subject_name` varchar(100)
,`academic_year` varchar(100)
,`year_status` enum('planning','registration','active','closing','archived')
,`start_date` date
,`end_date` date
,`status` enum('active','completed','transferred','terminated')
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_failed_attempts_by_ip`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_failed_attempts_by_ip`;
CREATE TABLE IF NOT EXISTS `vw_failed_attempts_by_ip` (
`ip_address` varchar(45)
,`attempt_count` bigint(21)
,`last_attempt` timestamp
,`failure_reasons` mediumtext
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_fee_carryover_summary`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_fee_carryover_summary`;
CREATE TABLE IF NOT EXISTS `vw_fee_carryover_summary` (
`student_id` int(10) unsigned
,`admission_no` varchar(20)
,`student_name` varchar(101)
,`class_name` varchar(50)
,`academic_year` int(11)
,`term_id` int(10) unsigned
,`period_type` varchar(15)
,`previous_balance` decimal(12,2)
,`surplus_amount` decimal(12,2)
,`action_taken` enum('fresh_bill','add_to_current','deduct_from_current','manual_adjustment')
,`created_at` timestamp
,`notes` text
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_fee_collection_by_year`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_fee_collection_by_year`;
CREATE TABLE IF NOT EXISTS `vw_fee_collection_by_year` (
`academic_year` year(4)
,`total_students` bigint(21)
,`total_fees_due` decimal(32,2)
,`total_collected` decimal(32,2)
,`total_outstanding` decimal(32,2)
,`collection_rate_percent` decimal(38,2)
,`students_paid_full` decimal(22,0)
,`students_partial` decimal(22,0)
,`students_arrears` decimal(22,0)
,`students_pending` decimal(22,0)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_fee_schedule_by_class`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_fee_schedule_by_class`;
CREATE TABLE IF NOT EXISTS `vw_fee_schedule_by_class` (
`level_name` varchar(50)
,`level_code` varchar(10)
,`academic_term` varchar(50)
,`student_type` varchar(20)
,`student_type_name` varchar(100)
,`fee_name` varchar(100)
,`fee_category` enum('tuition','boarding','activity','infrastructure','other')
,`amount_due` decimal(10,2)
,`due_date` date
,`number_of_students` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_fee_structure_annual_summary`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_fee_structure_annual_summary`;
CREATE TABLE IF NOT EXISTS `vw_fee_structure_annual_summary` (
`academic_year` year(4)
,`level_name` varchar(50)
,`level_id` int(10) unsigned
,`fee_type` varchar(100)
,`fee_type_id` int(10) unsigned
,`fee_category` enum('tuition','boarding','activity','infrastructure','other')
,`term1_amount` decimal(32,2)
,`term2_amount` decimal(32,2)
,`term3_amount` decimal(32,2)
,`annual_total` decimal(32,2)
,`status` enum('draft','pending_review','reviewed','approved','active','archived')
,`is_auto_rollover` tinyint(1)
,`reviewed_by` int(10) unsigned
,`reviewer_name` varchar(50)
,`reviewed_at` datetime
,`approved_by` int(10) unsigned
,`approver_name` varchar(50)
,`approved_at` datetime
,`activated_at` datetime
,`copied_from_id` int(10) unsigned
,`copied_from_year` year(4)
,`structure_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_fee_transition_audit`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_fee_transition_audit`;
CREATE TABLE IF NOT EXISTS `vw_fee_transition_audit` (
`student_id` int(10) unsigned
,`admission_no` varchar(20)
,`student_name` varchar(101)
,`from_academic_year` int(11)
,`to_academic_year` int(11)
,`transition_type` varchar(34)
,`balance_action` enum('fresh_bill','add_to_current','deduct_from_current','manual_adjustment')
,`amount_transferred` decimal(12,2)
,`previous_balance` decimal(12,2)
,`new_balance` decimal(12,2)
,`created_at` timestamp
,`notes` text
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_fee_type_collection`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_fee_type_collection`;
CREATE TABLE IF NOT EXISTS `vw_fee_type_collection` (
`fee_type` varchar(100)
,`fee_code` varchar(20)
,`fee_category` enum('tuition','boarding','activity','infrastructure','other')
,`is_mandatory` tinyint(1)
,`total_due` decimal(32,2)
,`total_collected` decimal(32,2)
,`total_outstanding` decimal(32,2)
,`students_affected` bigint(21)
,`collection_rate_percent` decimal(38,2)
,`students_paid` bigint(21)
,`students_partial` bigint(21)
,`students_pending` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `vw_financial_period_summary`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `vw_financial_period_summary`;
CREATE TABLE IF NOT EXISTS `vw_financial_period_summary` (
  `period_id` int(10) UNSIGNED DEFAULT NULL,
  `period_name` varchar(100) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','closed') DEFAULT NULL,
  `total_transactions` bigint(21) DEFAULT NULL,
  `reconciled_transactions` bigint(21) DEFAULT NULL,
  `total_amount` decimal(32,2) DEFAULT NULL,
  `reconciled_amount` decimal(32,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- RELATIONSHIPS FOR TABLE `vw_financial_period_summary`:
--

--
-- Truncate table before insert `vw_financial_period_summary`
--

TRUNCATE TABLE `vw_financial_period_summary`;
-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_food_consumption_summary`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_food_consumption_summary`;
CREATE TABLE IF NOT EXISTS `vw_food_consumption_summary` (
`consumption_date` date
,`food_item` varchar(255)
,`code` varchar(50)
,`category` varchar(100)
,`unit` varchar(20)
,`total_quantity_planned` decimal(32,2)
,`total_quantity_used` decimal(32,2)
,`total_waste` decimal(32,2)
,`total_cost_used` decimal(32,2)
,`consumption_records` bigint(21)
,`recorded_by_first` varchar(50)
,`recorded_by_last` varchar(50)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_internal_conversations`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_internal_conversations`;
CREATE TABLE IF NOT EXISTS `vw_internal_conversations` (
`conversation_id` int(10) unsigned
,`title` varchar(255)
,`conversation_type` enum('one_on_one','group','department','broadcast')
,`created_by` int(10) unsigned
,`first_name` varchar(50)
,`last_name` varchar(50)
,`total_messages` bigint(21)
,`last_message_date` timestamp
,`high_priority_messages` bigint(21)
,`participant_count` bigint(21)
,`created_at` timestamp
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_inventory_health`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_inventory_health`;
CREATE TABLE IF NOT EXISTS `vw_inventory_health` (
`id` int(10) unsigned
,`name` varchar(255)
,`code` varchar(50)
,`category` varchar(100)
,`current_quantity` int(11)
,`minimum_quantity` int(11)
,`reorder_level` int(11)
,`stock_status` varchar(12)
,`expiry_status` varchar(13)
,`expiry_date` date
,`location` varchar(100)
,`unit_cost` decimal(10,2)
,`inventory_value` decimal(20,2)
,`status` enum('active','inactive','out_of_stock')
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `vw_inventory_low_stock`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `vw_inventory_low_stock`;
CREATE TABLE IF NOT EXISTS `vw_inventory_low_stock` (
  `id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `current_quantity` int(11) DEFAULT NULL,
  `minimum_quantity` int(11) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- RELATIONSHIPS FOR TABLE `vw_inventory_low_stock`:
--

--
-- Truncate table before insert `vw_inventory_low_stock`
--

TRUNCATE TABLE `vw_inventory_low_stock`;
-- --------------------------------------------------------

--
-- Table structure for table `vw_lesson_plan_summary`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `vw_lesson_plan_summary`;
CREATE TABLE IF NOT EXISTS `vw_lesson_plan_summary` (
  `id` int(10) UNSIGNED DEFAULT NULL,
  `teacher_id` int(10) UNSIGNED DEFAULT NULL,
  `learning_area_id` int(10) UNSIGNED DEFAULT NULL,
  `class_id` int(10) UNSIGNED DEFAULT NULL,
  `unit_id` int(10) UNSIGNED DEFAULT NULL,
  `topic` varchar(255) DEFAULT NULL,
  `subtopic` varchar(255) DEFAULT NULL,
  `objectives` text DEFAULT NULL,
  `resources` text DEFAULT NULL,
  `activities` text DEFAULT NULL,
  `assessment` text DEFAULT NULL,
  `homework` text DEFAULT NULL,
  `lesson_date` date DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `status` enum('draft','submitted','approved','completed') DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `teacher_name` varchar(101) DEFAULT NULL,
  `learning_area_name` varchar(100) DEFAULT NULL,
  `class_name` varchar(50) DEFAULT NULL,
  `unit_name` varchar(255) DEFAULT NULL,
  `topic_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- RELATIONSHIPS FOR TABLE `vw_lesson_plan_summary`:
--

--
-- Truncate table before insert `vw_lesson_plan_summary`
--

TRUNCATE TABLE `vw_lesson_plan_summary`;
-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_maintenance_schedule`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_maintenance_schedule`;
CREATE TABLE IF NOT EXISTS `vw_maintenance_schedule` (
`id` int(10) unsigned
,`serial_number` varchar(100)
,`equipment_name` varchar(255)
,`brand` varchar(100)
,`model` varchar(100)
,`maintenance_type` varchar(100)
,`status` enum('pending','scheduled','in_progress','completed','cancelled','overdue')
,`last_maintenance_date` date
,`next_maintenance_date` date
,`days_until_due` int(7)
,`urgency` varchar(8)
,`notes` text
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_outstanding_by_class`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_outstanding_by_class`;
CREATE TABLE IF NOT EXISTS `vw_outstanding_by_class` (
`level_name` varchar(50)
,`level_code` varchar(10)
,`academic_term` varchar(50)
,`students_with_arrears` bigint(21)
,`total_arrears` decimal(32,2)
,`average_arrears_per_student` decimal(14,6)
,`minimum_arrears` decimal(10,2)
,`maximum_arrears` decimal(10,2)
,`students_overdue_30_days` bigint(21)
,`students_overdue_60_days` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `vw_outstanding_fees`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `vw_outstanding_fees`;
CREATE TABLE IF NOT EXISTS `vw_outstanding_fees` (
  `student_id` int(10) UNSIGNED DEFAULT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `parent_first` varchar(50) DEFAULT NULL,
  `parent_last` varchar(50) DEFAULT NULL,
  `outstanding_balance` decimal(32,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- RELATIONSHIPS FOR TABLE `vw_outstanding_fees`:
--

--
-- Truncate table before insert `vw_outstanding_fees`
--

TRUNCATE TABLE `vw_outstanding_fees`;
-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_parent_payment_activity`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_parent_payment_activity`;
CREATE TABLE IF NOT EXISTS `vw_parent_payment_activity` (
`parent_id` int(10) unsigned
,`parent_name` varchar(101)
,`contact_number` varchar(20)
,`total_payments` bigint(21)
,`total_amount_paid` decimal(32,2)
,`number_of_children` bigint(21)
,`children` mediumtext
,`last_payment_date` datetime
,`payments_this_year` bigint(21)
,`average_payment` decimal(14,6)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_payment_tracking`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_payment_tracking`;
CREATE TABLE IF NOT EXISTS `vw_payment_tracking` (
`payment_source` varchar(5)
,`source_id` int(10) unsigned
,`reference_code` varchar(100)
,`student_id` int(10) unsigned
,`admission_number` varchar(20)
,`student_name` varchar(101)
,`amount` decimal(10,2)
,`transaction_date` datetime
,`contact` varchar(50)
,`status` varchar(9)
,`checkout_request_id` varchar(100)
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_pending_fee_structure_reviews`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_pending_fee_structure_reviews`;
CREATE TABLE IF NOT EXISTS `vw_pending_fee_structure_reviews` (
`academic_year` year(4)
,`level_name` varchar(50)
,`pending_structures` bigint(21)
,`oldest_pending_date` timestamp
,`days_pending` int(7)
,`start_date` date
,`days_until_start` int(7)
,`priority` varchar(6)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_pending_requisitions`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_pending_requisitions`;
CREATE TABLE IF NOT EXISTS `vw_pending_requisitions` (
`id` int(10) unsigned
,`requisition_number` varchar(50)
,`department` varchar(100)
,`status` enum('draft','submitted','pending_approval','approved','rejected','partially_fulfilled','fulfilled','cancelled')
,`priority` enum('low','normal','high','urgent')
,`requisition_date` date
,`required_date` date
,`item_count` bigint(21)
,`total_quantity_requested` decimal(32,0)
,`created_by_first` varchar(50)
,`created_by_last` varchar(50)
,`approved_by_first` varchar(50)
,`approved_by_last` varchar(50)
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_pending_sms`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_pending_sms`;
CREATE TABLE IF NOT EXISTS `vw_pending_sms` (
`sms_id` int(10) unsigned
,`parent_id` int(10) unsigned
,`parent_name` varchar(101)
,`recipient_phone` varchar(20)
,`message_body` text
,`sms_type` enum('academic','fees','attendance','event','emergency','general','report_card')
,`status` enum('pending','queued','sent','delivered','failed')
,`template_name` varchar(255)
,`sent_by_first` varchar(50)
,`sent_by_last` varchar(50)
,`created_at` timestamp
,`sent_at` datetime
,`delivered_at` datetime
,`hours_pending` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_requisition_fulfillment`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_requisition_fulfillment`;
CREATE TABLE IF NOT EXISTS `vw_requisition_fulfillment` (
`id` int(10) unsigned
,`requisition_number` varchar(50)
,`department` varchar(100)
,`item_id` int(10) unsigned
,`item_name` varchar(255)
,`requested_quantity` int(11)
,`unit` varchar(20)
,`approved_quantity` int(11)
,`fulfilled_quantity` int(11)
,`pending_quantity` bigint(12)
,`unit_cost` decimal(10,2)
,`total_cost` decimal(20,2)
,`status` enum('draft','submitted','pending_approval','approved','rejected','partially_fulfilled','fulfilled','cancelled')
,`priority` enum('low','normal','high','urgent')
,`required_date` date
,`days_remaining` int(7)
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_sent_emails`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_sent_emails`;
CREATE TABLE IF NOT EXISTS `vw_sent_emails` (
`email_id` int(10) unsigned
,`institution_id` int(10) unsigned
,`institution_name` varchar(255)
,`contact_person_name` varchar(255)
,`recipient_email` varchar(255)
,`subject` varchar(255)
,`email_type` enum('inquiry','report','application','information','request','other')
,`status` enum('draft','queued','sent','delivered','failed','bounced')
,`attempts` bigint(21)
,`last_attempt` timestamp
,`sent_by_first` varchar(50)
,`sent_by_last` varchar(50)
,`created_at` timestamp
,`delivery_status_text` varchar(22)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_sponsored_students_status`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_sponsored_students_status`;
CREATE TABLE IF NOT EXISTS `vw_sponsored_students_status` (
`id` int(10) unsigned
,`admission_no` varchar(20)
,`student_name` varchar(101)
,`student_type` varchar(100)
,`class_name` varchar(50)
,`is_sponsored` tinyint(1)
,`sponsor_name` varchar(100)
,`sponsor_type` enum('partial','full','conditional')
,`sponsor_waiver_percentage` decimal(5,2)
,`total_fees_due` decimal(32,2)
,`total_paid` decimal(32,2)
,`current_balance` decimal(32,2)
,`total_waived` decimal(34,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_staff_assignments_detailed`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_staff_assignments_detailed`;
CREATE TABLE IF NOT EXISTS `vw_staff_assignments_detailed` (
`id` int(10) unsigned
,`staff_id` int(10) unsigned
,`class_stream_id` int(10) unsigned
,`class_id` int(10) unsigned
,`stream_id` int(10) unsigned
,`academic_year_id` int(10) unsigned
,`role` enum('class_teacher','subject_teacher','assistant_teacher','head_of_department')
,`subject_id` int(10) unsigned
,`start_date` date
,`end_date` date
,`status` enum('active','completed','transferred','terminated')
,`notes` text
,`created_at` timestamp
,`created_by` int(10) unsigned
,`updated_at` timestamp
,`staff_no` varchar(20)
,`staff_name` varchar(101)
,`stream_name` varchar(50)
,`class_name` varchar(50)
,`subject_name` varchar(100)
,`academic_year` varchar(100)
,`total_assignments` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_staff_leave_balance`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_staff_leave_balance`;
CREATE TABLE IF NOT EXISTS `vw_staff_leave_balance` (
`staff_id` int(10) unsigned
,`staff_no` varchar(20)
,`staff_name` varchar(101)
,`leave_type` varchar(20)
,`leave_name` varchar(100)
,`annual_entitlement` int(11)
,`days_taken_this_year` decimal(32,0)
,`days_remaining` decimal(33,0)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_staff_leave_balances`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_staff_leave_balances`;
CREATE TABLE IF NOT EXISTS `vw_staff_leave_balances` (
`staff_id` int(10) unsigned
,`staff_no` varchar(20)
,`staff_name` varchar(101)
,`leave_type_id` int(10) unsigned
,`leave_type_name` varchar(100)
,`entitled_days` int(11)
,`used_days` decimal(32,0)
,`pending_days` decimal(32,0)
,`available_days` decimal(33,0)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_staff_loan_details`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_staff_loan_details`;
CREATE TABLE IF NOT EXISTS `vw_staff_loan_details` (
`loan_id` int(10) unsigned
,`staff_id` int(10) unsigned
,`staff_name` varchar(101)
,`staff_number` varchar(20)
,`loan_type` varchar(50)
,`principal_amount` decimal(12,2)
,`loan_date` date
,`agreed_monthly_deduction` decimal(10,2)
,`balance_remaining` decimal(12,2)
,`total_paid` decimal(13,2)
,`payment_progress_percent` decimal(19,2)
,`months_remaining` bigint(14)
,`status` enum('active','paid_off','defaulted','suspended')
,`status_description` varchar(30)
,`loan_created_at` timestamp
,`last_updated` timestamp
,`payments_made_count` bigint(21)
,`total_deducted` decimal(34,2)
,`expected_completion_date` date
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_staff_onboarding_progress`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_staff_onboarding_progress`;
CREATE TABLE IF NOT EXISTS `vw_staff_onboarding_progress` (
`onboarding_id` int(10) unsigned
,`staff_id` int(10) unsigned
,`staff_no` varchar(20)
,`staff_name` varchar(101)
,`position` varchar(100)
,`department` varchar(100)
,`status` enum('pending','in_progress','completed','extended','terminated')
,`start_date` date
,`expected_end_date` date
,`completion_date` date
,`mentor_name` varchar(101)
,`total_tasks` bigint(21)
,`completed_tasks` decimal(22,0)
,`inprogress_tasks` decimal(22,0)
,`pending_tasks` decimal(22,0)
,`skipped_tasks` decimal(22,0)
,`overdue_tasks` decimal(22,0)
,`progress_percent` int(11)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_staff_payroll_summary`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_staff_payroll_summary`;
CREATE TABLE IF NOT EXISTS `vw_staff_payroll_summary` (
`payslip_id` int(10) unsigned
,`staff_id` int(10) unsigned
,`staff_name` varchar(101)
,`staff_number` varchar(20)
,`payroll_month` int(11)
,`payroll_year` int(11)
,`period_display` varchar(69)
,`basic_salary` decimal(12,2)
,`allowances_total` decimal(12,2)
,`gross_salary` decimal(12,2)
,`paye_tax` decimal(12,2)
,`nssf_contribution` decimal(12,2)
,`nhif_contribution` decimal(12,2)
,`loan_deduction` decimal(12,2)
,`other_deductions_total` decimal(12,2)
,`total_deductions` decimal(16,2)
,`net_salary` decimal(12,2)
,`payment_method` enum('bank','cash','check','mobile_money')
,`payment_date` date
,`payslip_status` enum('draft','approved','paid','cancelled')
,`approved_by_name` varchar(101)
,`notes` text
,`created_at` timestamp
,`updated_at` timestamp
,`ytd_gross` decimal(34,2)
,`ytd_paye` decimal(34,2)
,`ytd_nssf` decimal(34,2)
,`ytd_nhif` decimal(34,2)
,`ytd_net` decimal(34,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_staff_performance_summary`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_staff_performance_summary`;
CREATE TABLE IF NOT EXISTS `vw_staff_performance_summary` (
`review_id` int(10) unsigned
,`staff_id` int(10) unsigned
,`staff_no` varchar(20)
,`staff_name` varchar(101)
,`position` varchar(100)
,`department` varchar(100)
,`academic_year` varchar(100)
,`review_period` varchar(50)
,`review_type` enum('probation','annual','mid_year','special')
,`status` enum('draft','submitted','approved','completed')
,`overall_score` decimal(5,2)
,`performance_grade` char(1)
,`total_kpis` bigint(21)
,`completed_kpis` decimal(22,0)
,`completion_percent` decimal(26,0)
,`review_date` date
,`completion_date` datetime
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_staff_workload`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_staff_workload`;
CREATE TABLE IF NOT EXISTS `vw_staff_workload` (
`staff_id` int(10) unsigned
,`staff_no` varchar(20)
,`staff_name` varchar(101)
,`category_name` varchar(100)
,`academic_year` varchar(100)
,`classes_assigned` bigint(21)
,`class_teacher_count` bigint(21)
,`subject_teacher_count` bigint(21)
,`classes` mediumtext
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_student_payment_history_multi_year`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_student_payment_history_multi_year`;
CREATE TABLE IF NOT EXISTS `vw_student_payment_history_multi_year` (
`student_id` int(10) unsigned
,`first_name` varchar(50)
,`last_name` varchar(50)
,`admission_no` varchar(20)
,`academic_year` year(4)
,`term_name` varchar(50)
,`term_number` tinyint(4)
,`payment_count` bigint(21)
,`total_paid` decimal(32,2)
,`first_payment_date` datetime
,`last_payment_date` datetime
,`cash_total` decimal(32,2)
,`mpesa_total` decimal(32,2)
,`bank_total` decimal(32,2)
,`amount_due` decimal(10,2)
,`balance` decimal(10,2)
,`fee_status` enum('pending','partial','paid','arrears')
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_student_payment_status`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_student_payment_status`;
CREATE TABLE IF NOT EXISTS `vw_student_payment_status` (
`admission_no` varchar(20)
,`student_name` varchar(101)
,`level` varchar(50)
,`student_type` varchar(100)
,`academic_term` varchar(50)
,`total_fees_due` decimal(32,2)
,`total_fees_paid` decimal(32,2)
,`total_fees_waived` decimal(32,2)
,`balance_outstanding` decimal(32,2)
,`payment_percentage` decimal(38,2)
,`payment_status` varchar(7)
,`last_payment_date` datetime
,`number_of_payments` bigint(21)
,`waivers_applied` bigint(21)
,`arrears_status` varchar(26)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_student_payment_status_enhanced`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_student_payment_status_enhanced`;
CREATE TABLE IF NOT EXISTS `vw_student_payment_status_enhanced` (
`id` int(10) unsigned
,`admission_no` varchar(20)
,`student_name` varchar(101)
,`student_type` varchar(100)
,`class_name` varchar(50)
,`level_name` varchar(50)
,`academic_year` int(4)
,`term_number` int(4)
,`total_due` decimal(32,2)
,`total_paid` decimal(32,2)
,`total_waived` decimal(32,2)
,`current_balance` decimal(32,2)
,`year_balance` decimal(12,2)
,`term_balance` decimal(12,2)
,`previous_year_balance` decimal(12,2)
,`previous_term_balance` decimal(12,2)
,`payment_status` enum('pending','partial','paid','arrears','waived')
,`is_sponsored` tinyint(1)
,`sponsor_waiver_percentage` decimal(5,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_unread_announcements`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_unread_announcements`;
CREATE TABLE IF NOT EXISTS `vw_unread_announcements` (
`announcement_id` int(10) unsigned
,`title` varchar(255)
,`content` longtext
,`priority` enum('low','normal','high','critical')
,`target_audience` enum('all','staff','students','parents','specific')
,`published_by_first` varchar(50)
,`published_by_last` varchar(50)
,`status` enum('draft','scheduled','published','archived','expired')
,`created_at` timestamp
,`updated_at` timestamp
,`total_views` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `vw_upcoming_activities`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `vw_upcoming_activities`;
CREATE TABLE IF NOT EXISTS `vw_upcoming_activities` (
  `id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- RELATIONSHIPS FOR TABLE `vw_upcoming_activities`:
--

--
-- Truncate table before insert `vw_upcoming_activities`
--

TRUNCATE TABLE `vw_upcoming_activities`;
-- --------------------------------------------------------

--
-- Table structure for table `vw_upcoming_class_schedules`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `vw_upcoming_class_schedules`;
CREATE TABLE IF NOT EXISTS `vw_upcoming_class_schedules` (
  `class_id` int(10) UNSIGNED DEFAULT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `teacher` varchar(50) DEFAULT NULL,
  `room` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- RELATIONSHIPS FOR TABLE `vw_upcoming_class_schedules`:
--

--
-- Truncate table before insert `vw_upcoming_class_schedules`
--

TRUNCATE TABLE `vw_upcoming_class_schedules`;
-- --------------------------------------------------------

--
-- Table structure for table `vw_upcoming_exam_schedules`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `vw_upcoming_exam_schedules`;
CREATE TABLE IF NOT EXISTS `vw_upcoming_exam_schedules` (
  `class_id` int(10) UNSIGNED DEFAULT NULL,
  `subject_id` int(10) UNSIGNED DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `exam_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `room` varchar(50) DEFAULT NULL,
  `invigilator` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- RELATIONSHIPS FOR TABLE `vw_upcoming_exam_schedules`:
--

--
-- Truncate table before insert `vw_upcoming_exam_schedules`
--

TRUNCATE TABLE `vw_upcoming_exam_schedules`;
-- --------------------------------------------------------

--
-- Table structure for table `vw_user_recent_communications`
--
-- Creation: Nov 09, 2025 at 11:16 PM
--

DROP TABLE IF EXISTS `vw_user_recent_communications`;
CREATE TABLE IF NOT EXISTS `vw_user_recent_communications` (
  `recipient_id` int(10) UNSIGNED DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `type` enum('email','sms','notification','internal') DEFAULT NULL,
  `status` enum('draft','sent','scheduled','failed') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- RELATIONSHIPS FOR TABLE `vw_user_recent_communications`:
--

--
-- Truncate table before insert `vw_user_recent_communications`
--

TRUNCATE TABLE `vw_user_recent_communications`;
-- --------------------------------------------------------

--
-- Table structure for table `workflow_definitions`
--
-- Creation: Nov 10, 2025 at 11:27 AM
--

DROP TABLE IF EXISTS `workflow_definitions`;
CREATE TABLE IF NOT EXISTS `workflow_definitions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` enum('academic','administrative','financial','student_affairs','staff_affairs','general') NOT NULL,
  `handler_class` varchar(255) NOT NULL COMMENT 'PHP class that handles this workflow',
  `config_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Workflow-specific configuration' CHECK (json_valid(`config_json`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_workflow_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `workflow_definitions`:
--

--
-- Truncate table before insert `workflow_definitions`
--

TRUNCATE TABLE `workflow_definitions`;
--
-- Dumping data for table `workflow_definitions`
--

INSERT DELAYED IGNORE INTO `workflow_definitions` (`id`, `code`, `name`, `description`, `category`, `handler_class`, `config_json`, `is_active`, `created_at`, `updated_at`) VALUES
(2, 'student_admission', 'Student Admission Process', 'Complete student admission process from application to enrollment', 'academic', 'App\\API\\Modules\\Admission\\AdmissionWorkflow', '{\"requires_documents\": true, \"requires_interview\": true, \"requires_placement\": true, \"notify_roles\": [\"admin\", \"registrar\", \"head_teacher\"], \"database_ready\": true, \"missing_objects\": []}', 1, '2025-11-10 11:27:56', '2025-11-10 11:27:56'),
(3, 'fee_payment', 'Fee Payment Process', 'Handle student fee payments and tracking', 'financial', 'App\\API\\Modules\\Finance\\FeePaymentWorkflow', '{\"allows_installments\": true, \"payment_methods\": [\"bank_transfer\", \"mpesa\", \"cash\", \"cheque\"], \"notify_roles\": [\"accountant\", \"class_teacher\"], \"database_ready\": true, \"missing_objects\": []}', 1, '2025-11-10 11:27:56', '2025-11-10 11:27:56'),
(4, 'attendance_management', 'Attendance Management', 'Daily student and staff attendance tracking', 'academic', 'App\\API\\Modules\\Attendance\\AttendanceWorkflow', '{\"supports_bulk\": true, \"auto_notifications\": true, \"notify_roles\": [\"class_teacher\", \"admin\"], \"database_ready\": true, \"missing_objects\": [], \"name_corrections\": {\"sp_bulk_mark_attendance\": \"sp_bulk_mark_student_attendance\"}}', 1, '2025-11-10 11:27:56', '2025-11-10 11:27:56'),
(5, 'inventory_management', 'Inventory Management', 'School inventory requisition and management', 'administrative', 'App\\API\\Modules\\Inventory\\InventoryWorkflow', '{\"supports_bulk\": true, \"requires_approval\": true, \"notify_roles\": [\"inventory_manager\", \"accountant\"], \"database_ready\": true, \"missing_objects\": []}', 1, '2025-11-10 11:27:56', '2025-11-10 11:27:56'),
(6, 'payroll_processing', 'Payroll Processing', 'Monthly staff payroll processing', 'financial', 'App\\API\\Modules\\Finance\\PayrollWorkflow', '{\"frequency\": \"monthly\", \"requires_approval\": true, \"notify_roles\": [\"accountant\", \"hr_manager\", \"director\"], \"database_ready\": true, \"missing_objects\": []}', 1, '2025-11-10 11:27:56', '2025-11-10 11:27:56'),
(7, 'examination_management', 'Examination Management', 'End-of-term examination process', 'academic', 'App\\API\\Modules\\Academic\\ExaminationWorkflow', '{\"supports_cbc\": true, \"requires_moderation\": true, \"notify_roles\": [\"head_teacher\", \"class_teacher\", \"subject_teacher\"], \"database_ready\": false, \"missing_objects\": [\"sp_create_exam_schedule\", \"sp_moderate_marks\", \"sp_compile_exam_results\"]}', 1, '2025-11-10 11:27:56', '2025-11-10 11:27:56'),
(8, 'student_promotion', 'Student Promotion', 'End-of-year student promotion process', 'academic', 'App\\API\\Modules\\Academic\\PromotionWorkflow', '{\"supports_bulk\": true, \"requires_approval\": true, \"notify_roles\": [\"head_teacher\", \"registrar\", \"class_teacher\"], \"database_ready\": true, \"missing_objects\": []}', 1, '2025-11-10 11:27:56', '2025-11-10 11:27:56'),
(9, 'discipline_management', 'Discipline Management', 'Student discipline case management', 'administrative', 'App\\API\\Modules\\Operations\\DisciplineWorkflow', '{\"requires_hearing\": true, \"supports_appeals\": true, \"notify_roles\": [\"head_teacher\", \"class_teacher\", \"parent\"], \"database_ready\": false, \"missing_objects\": [\"sp_record_discipline_case\", \"sp_schedule_discipline_hearing\", \"sp_implement_discipline_action\", \"sp_track_student_behavior\"]}', 1, '2025-11-10 11:27:56', '2025-11-10 11:27:56'),
(10, 'academic_assessment', 'Academic Assessment Process', 'Continuous assessment and competency tracking', 'academic', 'App\\API\\Modules\\Academic\\AssessmentWorkflow', '{\"supports_cbc\": true, \"database_ready\": true, \"missing_objects\": []}', 1, '2025-11-10 11:27:57', '2025-11-10 11:27:57'),
(11, 'report_generation', 'Report Generation Process', 'Generate reports', 'academic', 'App\\API\\Modules\\Reports\\ReportWorkflow', '{\"database_ready\": true, \"missing_objects\": []}', 1, '2025-11-10 11:27:57', '2025-11-10 11:27:57'),
(12, 'staff_onboarding', 'Staff Onboarding Process', 'New staff recruitment', 'staff_affairs', 'App\\API\\Modules\\Staff\\OnboardingWorkflow', '{\"database_ready\": true, \"missing_objects\": []}', 1, '2025-11-10 11:27:57', '2025-11-10 11:27:57'),
(13, 'communication_management', 'Communication Process', 'School communications', 'administrative', 'App\\API\\Modules\\Communications\\CommunicationWorkflow', '{\"database_ready\": true, \"missing_objects\": []}', 1, '2025-11-10 11:27:57', '2025-11-10 11:27:57'),
(14, 'extracurricular_activities', 'Activities Process', 'Clubs and activities', 'student_affairs', 'App\\API\\Modules\\Activities\\ActivitiesWorkflow', '{\"database_ready\": true, \"missing_objects\": []}', 1, '2025-11-10 11:27:57', '2025-11-10 11:27:57'),
(15, 'student_transfer', 'Transfer Process', 'Student transfers', 'student_affairs', 'App\\API\\Modules\\Students\\TransferWorkflow', '{\"database_ready\": true, \"missing_objects\": []}', 1, '2025-11-10 11:27:57', '2025-11-10 11:27:57'),
(16, 'library_management', 'Library Process', 'Library borrowing', 'academic', 'App\\API\\Modules\\Library\\LibraryWorkflow', '{\"database_ready\": true, \"missing_objects\": []}', 1, '2025-11-10 11:27:57', '2025-11-10 11:27:57'),
(17, 'transportation_management', 'Transport Process', 'Transport management', 'administrative', 'App\\API\\Modules\\Transport\\TransportWorkflow', '{\"database_ready\": true, \"missing_objects\": []}', 1, '2025-11-10 11:27:57', '2025-11-10 11:27:57'),
(18, 'health_wellness', 'Health Process', 'Health management', 'student_affairs', 'App\\API\\Modules\\Health\\HealthWorkflow', '{\"database_ready\": true, \"missing_objects\": []}', 1, '2025-11-10 11:27:57', '2025-11-10 11:27:57'),
(19, 'parent_teacher_conference', 'PT Conference Process', 'Parent meetings', 'student_affairs', 'App\\API\\Modules\\Academic\\PTConferenceWorkflow', '{\"database_ready\": true, \"missing_objects\": []}', 1, '2025-11-10 11:27:57', '2025-11-10 11:27:57'),
(20, 'alumni_management', 'Alumni Process', 'Alumni management', 'general', 'App\\API\\Modules\\Alumni\\AlumniWorkflow', '{\"database_ready\": true, \"missing_objects\": []}', 1, '2025-11-10 11:27:57', '2025-11-10 11:27:57'),
(21, 'event_management', 'Event Process', 'School events', 'general', 'App\\API\\Modules\\Events\\EventWorkflow', '{\"database_ready\": true, \"missing_objects\": []}', 1, '2025-11-10 11:27:57', '2025-11-10 11:27:57'),
(22, 'curriculum_planning', 'Curriculum Process', 'Curriculum planning', 'academic', 'App\\API\\Modules\\Academic\\CurriculumWorkflow', '{\"database_ready\": true, \"missing_objects\": []}', 1, '2025-11-10 11:27:57', '2025-11-10 11:27:57'),
(23, 'staff_evaluation', 'Staff Evaluation Process', 'Performance reviews', 'staff_affairs', 'App\\API\\Modules\\Staff\\EvaluationWorkflow', '{\"database_ready\": true, \"missing_objects\": []}', 1, '2025-11-10 11:27:57', '2025-11-10 11:27:57'),
(24, 'scholarship_management', 'Scholarship Process', 'Financial aid', 'financial', 'App\\API\\Modules\\Finance\\ScholarshipWorkflow', '{\"database_ready\": true, \"missing_objects\": []}', 1, '2025-11-10 11:27:57', '2025-11-10 11:27:57'),
(25, 'academic_year_transition', 'Year Transition Process', 'New academic year', 'academic', 'App\\API\\Modules\\Academic\\YearTransitionWorkflow', '{\"database_ready\": true, \"missing_objects\": []}', 1, '2025-11-10 11:27:57', '2025-11-10 11:27:57'),
(26, 'staff_leave_approval', 'Staff Leave Approval Process', 'Multi-stage leave request and approval process with balance validation', 'staff_affairs', 'App\\API\\Modules\\Staff\\LeaveWorkflow', '{\"requires_balance_check\": true, \"supports_delegation\": true, \"auto_notifications\": true, \"notify_roles\": [\"supervisor\", \"hr_manager\", \"director\"], \"database_ready\": true, \"procedures\": [\"sp_calculate_staff_leave_balance\"], \"triggers\": [\"trg_check_leave_overlap\", \"trg_update_leave_balance\"], \"views\": [\"vw_staff_leave_balances\"], \"tables\": [\"staff_leaves\", \"leave_types\"]}', 1, '2025-11-11 14:17:09', '2025-11-11 14:17:09'),
(27, 'staff_assignment_approval', 'Staff Assignment Approval Process', 'Multi-stage class assignment approval with workload validation', 'staff_affairs', 'App\\API\\Modules\\Staff\\AssignmentWorkflow', '{\"validates_workload\": true, \"max_workload\": 8, \"supports_bulk\": true, \"auto_notifications\": true, \"notify_roles\": [\"staff\", \"head_teacher\", \"subject_teacher\"], \"database_ready\": true, \"procedures\": [\"sp_validate_staff_assignment\"], \"triggers\": [\"trg_complete_staff_assignments_on_year_end\"], \"views\": [\"vw_staff_assignments_detailed\", \"vw_staff_workload\"], \"tables\": [\"staff_class_assignments\"]}', 1, '2025-11-11 14:17:09', '2025-11-11 14:17:09'),
(28, 'FEE_APPROVAL', 'Fee Structure Approval Process', 'Multi-stage approval process for fee structures from draft to activation', 'financial', 'App\\API\\Modules\\Finance\\FeeApprovalWorkflow', '{\"requires_approval\": true, \"auto_activate\": false, \"notify_roles\": [\"accountant\", \"head_teacher\", \"director\"], \"database_ready\": true, \"missing_objects\": []}', 1, '2025-11-12 01:15:56', '2025-11-12 01:15:56'),
(29, 'BUDGET_APPROVAL', 'Budget Approval Process', 'Multi-level budget approval from department to director', 'financial', 'App\\API\\Modules\\Finance\\BudgetApprovalWorkflow', '{\"requires_approval\": true, \"multi_level\": true, \"notify_roles\": [\"department_head\", \"accountant\", \"director\"], \"database_ready\": true, \"missing_objects\": []}', 1, '2025-11-12 01:15:56', '2025-11-12 01:15:56'),
(30, 'EXPENSE_APPROVAL', 'Expense Approval Process', 'Expense approval from submission to payment', 'financial', 'App\\API\\Modules\\Finance\\ExpenseApprovalWorkflow', '{\"requires_approval\": true, \"requires_payment\": true, \"notify_roles\": [\"accountant\", \"manager\", \"payee\"], \"database_ready\": true, \"missing_objects\": []}', 1, '2025-11-12 01:15:56', '2025-11-12 01:15:56'),
(31, 'PAYROLL_APPROVAL', 'Payroll Approval Process', 'Monthly payroll approval and disbursement', 'financial', 'App\\API\\Services\\Workflows\\PayrollApprovalWorkflow', '{\"frequency\": \"monthly\", \"requires_approval\": true, \"auto_disburse\": false, \"notify_roles\": [\"hr_manager\", \"accountant\", \"director\"], \"database_ready\": true, \"missing_objects\": []}', 1, '2025-11-12 01:15:56', '2025-11-12 01:15:56');

-- --------------------------------------------------------

--
-- Table structure for table `workflow_instances`
--
-- Creation: Nov 10, 2025 at 11:27 AM
--

DROP TABLE IF EXISTS `workflow_instances`;
CREATE TABLE IF NOT EXISTS `workflow_instances` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `workflow_id` int(10) UNSIGNED NOT NULL,
  `reference_type` varchar(50) NOT NULL COMMENT 'Entity type this workflow is for',
  `reference_id` int(10) UNSIGNED NOT NULL COMMENT 'ID of the entity',
  `current_stage` varchar(50) NOT NULL,
  `status` enum('pending','in_progress','completed','cancelled','error') NOT NULL DEFAULT 'pending',
  `started_by` int(10) UNSIGNED NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL,
  `data_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Instance-specific data' CHECK (json_valid(`data_json`)),
  PRIMARY KEY (`id`),
  KEY `idx_workflow_ref` (`workflow_id`,`reference_type`,`reference_id`),
  KEY `idx_workflow_status` (`workflow_id`,`status`),
  KEY `fk_workflow_starter` (`started_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `workflow_instances`:
--   `workflow_id`
--       `workflow_definitions` -> `id`
--   `started_by`
--       `users` -> `id`
--

--
-- Truncate table before insert `workflow_instances`
--

TRUNCATE TABLE `workflow_instances`;
-- --------------------------------------------------------

--
-- Table structure for table `workflow_notifications`
--
-- Creation: Nov 10, 2025 at 11:27 AM
--

DROP TABLE IF EXISTS `workflow_notifications`;
CREATE TABLE IF NOT EXISTS `workflow_notifications` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `instance_id` int(10) UNSIGNED NOT NULL,
  `notification_type` enum('stage_entry','stage_complete','stage_timeout','action_required','error') NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_workflow_notify` (`instance_id`,`notification_type`),
  KEY `idx_user_notifications` (`user_id`,`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `workflow_notifications`:
--   `instance_id`
--       `workflow_instances` -> `id`
--   `user_id`
--       `users` -> `id`
--

--
-- Truncate table before insert `workflow_notifications`
--

TRUNCATE TABLE `workflow_notifications`;
-- --------------------------------------------------------

--
-- Table structure for table `workflow_stages`
--
-- Creation: Nov 10, 2025 at 11:27 AM
--

DROP TABLE IF EXISTS `workflow_stages`;
CREATE TABLE IF NOT EXISTS `workflow_stages` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `workflow_id` int(10) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `sequence` int(11) NOT NULL COMMENT 'Order of stages',
  `required_role` varchar(50) DEFAULT NULL COMMENT 'Role required to process this stage',
  `allowed_transitions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Array of possible next stage codes' CHECK (json_valid(`allowed_transitions`)),
  `action_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Required actions config' CHECK (json_valid(`action_config`)),
  `timeout_hours` int(11) DEFAULT NULL COMMENT 'Stage timeout in hours',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_workflow_stage` (`workflow_id`,`code`),
  KEY `idx_workflow_sequence` (`workflow_id`,`sequence`)
) ENGINE=InnoDB AUTO_INCREMENT=163 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `workflow_stages`:
--   `workflow_id`
--       `workflow_definitions` -> `id`
--

--
-- Truncate table before insert `workflow_stages`
--

TRUNCATE TABLE `workflow_stages`;
--
-- Dumping data for table `workflow_stages`
--

INSERT DELAYED IGNORE INTO `workflow_stages` (`id`, `workflow_id`, `code`, `name`, `description`, `sequence`, `required_role`, `allowed_transitions`, `action_config`, `timeout_hours`, `is_active`) VALUES
(3, 2, 'application_submission', 'Application Submission', 'Initial application with basic details', 1, 'registrar', '[\"document_verification\", \"rejected\"]', '{\"required_fields\": [\"applicant_name\", \"date_of_birth\", \"grade_applying_for\", \"parent_details\"], \"validations\": {\"age_range\": [4, 16]}, \"tables\": [\"students\", \"parents\"], \"comment\": \"Direct INSERT into students table - no procedure needed\"}', NULL, 1),
(4, 2, 'document_verification', 'Document Verification', 'Verify submitted documents', 2, 'registrar', '[\"interview_scheduling\", \"rejected\"]', '{\"required_documents\": [\"birth_certificate\", \"previous_reports\", \"medical_records\"], \"tables\": [\"student_documents\"], \"comment\": \"Direct INSERT into student_documents table\"}', NULL, 1),
(5, 2, 'interview_scheduling', 'Interview Scheduling', 'Schedule admission interview', 3, 'registrar', '[\"interview_assessment\", \"cancelled\"]', '{\"required_fields\": [\"interview_date\", \"interview_time\", \"interviewer_id\"], \"tables\": [\"admission_interviews\"], \"comment\": \"Direct INSERT - no procedure needed\"}', NULL, 1),
(6, 2, 'interview_assessment', 'Interview Assessment', 'Conduct and assess interview', 4, 'head_teacher', '[\"placement_offer\", \"rejected\"]', '{\"assessment_criteria\": [\"academic_readiness\", \"social_skills\", \"language_proficiency\"], \"tables\": [\"admission_interviews\"], \"comment\": \"UPDATE admission_interviews with assessment results\"}', NULL, 1),
(7, 2, 'placement_offer', 'Placement Offer', 'Offer placement and fee structure', 5, 'head_teacher', '[\"fee_payment\", \"cancelled\"]', '{\"required_fields\": [\"assigned_class\", \"fee_structure\", \"reporting_date\"], \"procedures\": [\"sp_calculate_student_fees\"], \"tables\": [\"student_fee_obligations\", \"fee_structures\"], \"comment\": \"✅ Uses existing sp_calculate_student_fees\"}', NULL, 1),
(8, 2, 'fee_payment', 'Initial Fee Payment', 'Process initial fee payment', 6, 'accountant', '[\"enrollment\", \"cancelled\"]', '{\"minimum_payment_percent\": 50, \"payment_methods\": [\"bank_transfer\", \"mpesa\", \"cash\"], \"procedures\": [\"sp_process_student_payment\", \"sp_allocate_payment\"], \"tables\": [\"payment_transactions\", \"payment_allocations\", \"student_fee_balances\"], \"triggers\": [\"trg_log_payment_transaction\", \"trg_emit_payment_event\"], \"comment\": \"✅ All procedures exist: sp_process_student_payment, sp_allocate_payment. Triggers auto-fire.\"}', NULL, 1),
(9, 2, 'enrollment', 'Student Enrollment', 'Complete enrollment process', 7, 'registrar', '[\"completed\"]', '{\"required_actions\": [\"assign_admission_number\", \"create_student_record\", \"issue_id_card\"], \"functions\": [\"generate_student_number\"], \"tables\": [\"students\", \"student_profiles\"], \"comment\": \"✅ Uses existing generate_student_number function\"}', NULL, 1),
(10, 3, 'payment_initiation', 'Payment Initiation', 'Start payment process', 1, 'accountant', '[\"payment_verification\", \"cancelled\"]', '{\"required_fields\": [\"student_id\", \"amount\", \"payment_method\", \"term_id\"], \"procedures\": [\"sp_calculate_student_fees\"], \"tables\": [\"student_fee_obligations\", \"fee_structures\"], \"comment\": \"✅ Uses existing sp_calculate_student_fees\"}', NULL, 1),
(11, 3, 'payment_verification', 'Payment Verification', 'Verify payment details', 2, 'accountant', '[\"payment_recording\", \"payment_rejected\"]', '{\"verification_rules\": [\"amount_matches\", \"payment_reference_valid\", \"payment_method_active\"], \"tables\": [\"payment_transactions\"], \"comment\": \"Direct validation - no procedure needed\"}', NULL, 1),
(12, 3, 'payment_recording', 'Payment Recording', 'Record verified payment', 3, 'accountant', '[\"receipt_generation\", \"cancelled\"]', '{\"procedures\": [\"sp_process_student_payment\", \"sp_allocate_payment\", \"sp_apply_fee_discount\"], \"tables\": [\"payment_transactions\", \"payment_allocations\", \"student_fee_balances\"], \"triggers\": [\"trg_log_payment_transaction\", \"trg_emit_payment_event\", \"trg_update_obligation_on_payment\"], \"comment\": \"✅ All procedures exist. Triggers auto-log transactions and emit events.\"}', NULL, 1),
(13, 3, 'receipt_generation', 'Receipt Generation', 'Generate payment receipt', 4, 'accountant', '[\"completed\"]', '{\"procedures\": [\"sp_generate_student_report\"], \"tables\": [\"payment_transactions\"], \"comment\": \"✅ Uses existing sp_generate_student_report for receipt data\"}', NULL, 1),
(14, 4, 'attendance_collection', 'Attendance Collection', 'Collect attendance data', 1, 'class_teacher', '[\"attendance_verification\", \"cancelled\"]', '{\"required_fields\": [\"class_id\", \"date\", \"attendance_records\"], \"tables\": [\"attendance_records\", \"students\"], \"comment\": \"Collect attendance data from teachers\"}', NULL, 1),
(15, 4, 'attendance_verification', 'Attendance Verification', 'Verify attendance records', 2, 'class_teacher', '[\"attendance_recording\", \"attendance_collection\"]', '{\"validation_rules\": [\"all_students_marked\", \"valid_statuses\", \"no_duplicates\"], \"comment\": \"Validate attendance data before recording\"}', NULL, 1),
(16, 4, 'attendance_recording', 'Attendance Recording', 'Record verified attendance', 3, 'class_teacher', '[\"notification_dispatch\", \"completed\"]', '{\"procedures\": [\"sp_bulk_mark_student_attendance\"], \"tables\": [\"attendance_records\"], \"triggers\": [\"trg_emit_attendance_event\"], \"events\": [\"evt_process_attendance_summary\"], \"comment\": \"✅ CORRECTED: Uses sp_bulk_mark_student_attendance (not sp_bulk_mark_attendance). Trigger auto-fires.\"}', NULL, 1),
(17, 4, 'notification_dispatch', 'Notification Dispatch', 'Send absence notifications', 4, 'system', '[\"completed\"]', '{\"procedures\": [\"sp_send_sms_to_parents\", \"sp_send_announcement\"], \"tables\": [\"notifications\", \"sms_logs\"], \"triggers\": [\"trg_log_sms_delivery\"], \"comment\": \"✅ Uses existing sp_send_sms_to_parents and sp_send_announcement. Triggers auto-log delivery.\"}', NULL, 1),
(18, 4, 'report_generation', 'Report Generation', 'Generate attendance reports', 5, 'system', '[\"completed\"]', '{\"events\": [\"ev_daily_attendance_report\", \"evt_process_attendance_summary\"], \"procedures\": [\"sp_generate_student_report\"], \"comment\": \"✅ Scheduled events auto-run: ev_daily_attendance_report, evt_process_attendance_summary\"}', NULL, 1),
(19, 5, 'requisition_submission', 'Requisition Submission', 'Submit inventory requisition', 1, 'staff', '[\"requisition_review\", \"cancelled\"]', '{\"required_fields\": [\"items\", \"quantities\", \"justification\"], \"tables\": [\"inventory_requisitions\", \"inventory_items\"], \"comment\": \"Direct INSERT into inventory_requisitions\"}', NULL, 1),
(20, 5, 'requisition_review', 'Requisition Review', 'Review and approve requisition', 2, 'inventory_manager', '[\"requisition_processing\", \"rejected\"]', '{\"approval_rules\": [\"budget_available\", \"items_in_stock\", \"valid_justification\"], \"tables\": [\"inventory_requisitions\"], \"triggers\": [\"trg_log_requisition_status\"], \"comment\": \"✅ Trigger auto-logs status changes\"}', NULL, 1),
(21, 5, 'requisition_processing', 'Requisition Processing', 'Process approved requisition', 3, 'inventory_manager', '[\"item_allocation\", \"cancelled\"]', '{\"procedures\": [\"sp_process_requisition\"], \"tables\": [\"inventory_requisitions\", \"inventory_items\", \"inventory_transactions\"], \"triggers\": [\"trg_emit_low_stock_event\", \"trg_audit_inventory_update\"], \"comment\": \"✅ Uses existing sp_process_requisition. Triggers auto-emit low stock alerts and audit logs.\"}', NULL, 1),
(22, 5, 'item_allocation', 'Item Allocation', 'Allocate items to requester', 4, 'inventory_manager', '[\"reconciliation\", \"completed\"]', '{\"procedures\": [\"sp_issue_allocation\", \"sp_return_allocation\"], \"tables\": [\"inventory_allocations\"], \"triggers\": [\"trg_log_allocation_status\", \"trg_audit_allocation_insert\"], \"comment\": \"✅ Uses existing sp_issue_allocation and sp_return_allocation. Triggers auto-log allocation status.\"}', NULL, 1),
(23, 5, 'reconciliation', 'Inventory Reconciliation', 'Reconcile inventory records', 5, 'inventory_manager', '[\"completed\"]', '{\"procedures\": [\"sp_bulk_inventory_reconciliation\"], \"tables\": [\"inventory_items\", \"inventory_audit\"], \"comment\": \"✅ Uses existing sp_bulk_inventory_reconciliation\"}', NULL, 1),
(24, 6, 'payroll_initiation', 'Payroll Initiation', 'Start monthly payroll', 1, 'hr_manager', '[\"payroll_calculation\", \"cancelled\"]', '{\"required_fields\": [\"payroll_month\", \"payroll_year\"], \"tables\": [\"staff\", \"staff_payroll\"], \"comment\": \"Initialize payroll period\"}', NULL, 1),
(25, 6, 'payroll_calculation', 'Payroll Calculation', 'Calculate staff payroll', 2, 'accountant', '[\"payroll_verification\", \"payroll_initiation\"]', '{\"procedures\": [\"sp_calculate_payroll_for_staff\", \"sp_calculate_paye_tax\", \"sp_calculate_nssf_contribution\", \"sp_calculate_nhif_contribution\"], \"tables\": [\"staff_payroll\", \"staff_allowances\", \"staff_deductions\"], \"comment\": \"✅ Uses existing tax/deduction calculation procedures\"}', NULL, 1),
(26, 6, 'payroll_verification', 'Payroll Verification', 'Verify calculated payroll', 3, 'accountant', '[\"payroll_approval\", \"payroll_calculation\"]', '{\"validation_rules\": [\"all_staff_included\", \"calculations_correct\", \"statutory_deductions_valid\"], \"procedures\": [\"sp_get_staff_kpi_summary\"], \"tables\": [\"staff_payroll\"], \"comment\": \"✅ Uses existing sp_get_staff_kpi_summary for verification\"}', NULL, 1),
(27, 6, 'payroll_approval', 'Payroll Approval', 'Approve payroll for processing', 4, 'director', '[\"payroll_processing\", \"payroll_calculation\"]', '{\"approval_rules\": [\"budget_sufficient\", \"all_verified\", \"director_authorization\"], \"tables\": [\"staff_payroll\"], \"comment\": \"Director approval required\"}', NULL, 1),
(28, 6, 'payroll_processing', 'Payroll Processing', 'Process approved payroll', 5, 'accountant', '[\"completed\"]', '{\"procedures\": [\"sp_process_monthly_payroll\", \"sp_bulk_payroll_calculation\", \"sp_process_staff_payroll\"], \"tables\": [\"staff_payroll\", \"payment_transactions\"], \"events\": [\"evt_staff_appraisal_reminders\"], \"comment\": \"✅ Uses existing payroll procedures. Event auto-sends appraisal reminders.\"}', NULL, 1),
(29, 7, 'exam_planning', 'Examination Planning', 'Plan examination schedule', 1, 'head_teacher', '[\"schedule_creation\", \"cancelled\"]', '{\"required_fields\": [\"term_id\", \"exam_type\", \"start_date\", \"end_date\"], \"tables\": [\"academic_terms\", \"exam_schedules\"], \"comment\": \"❌ MISSING: sp_create_exam_schedule - needs complex timetable generation logic\"}', NULL, 1),
(30, 7, 'schedule_creation', 'Schedule Creation', 'Create exam timetable', 2, 'head_teacher', '[\"question_paper_submission\", \"exam_planning\"]', '{\"procedures\": [\"sp_create_exam_schedule\"], \"tables\": [\"exam_schedules\", \"classes\", \"curriculum_units\"], \"comment\": \"❌ MISSING: sp_create_exam_schedule procedure\"}', NULL, 1),
(31, 7, 'question_paper_submission', 'Question Paper Submission', 'Teachers submit question papers', 3, 'subject_teacher', '[\"exam_logistics\", \"schedule_creation\"]', '{\"required_fields\": [\"subject_id\", \"class_id\", \"paper_file\"], \"tables\": [\"exam_papers\", \"curriculum_units\"], \"comment\": \"✅ Direct INSERT into exam_papers table - no procedure needed\"}', NULL, 1),
(32, 7, 'exam_logistics', 'Examination Logistics', 'Prepare examination logistics', 4, 'head_teacher', '[\"exam_administration\", \"question_paper_submission\"]', '{\"required_fields\": [\"invigilators\", \"rooms\", \"materials\"], \"tables\": [\"exam_schedules\", \"staff\", \"classrooms\"], \"comment\": \"✅ Direct UPDATE exam_schedules - no procedure needed\"}', NULL, 1),
(33, 7, 'exam_administration', 'Examination Administration', 'Administer examinations', 5, 'invigilator', '[\"marking_assignment\", \"completed\"]', '{\"required_actions\": [\"mark_attendance\", \"collect_papers\", \"report_incidents\"], \"tables\": [\"attendance_records\", \"exam_incidents\"], \"procedures\": [\"sp_bulk_mark_student_attendance\"], \"comment\": \"✅ Uses existing sp_bulk_mark_student_attendance for exam attendance\"}', NULL, 1),
(34, 7, 'marking_assignment', 'Marking Assignment', 'Assign papers for marking', 6, 'head_teacher', '[\"marks_recording\", \"exam_administration\"]', '{\"required_fields\": [\"examiner_id\", \"papers_assigned\"], \"tables\": [\"exam_papers\", \"staff\"], \"comment\": \"✅ Direct UPDATE exam_papers - no procedure needed\"}', NULL, 1),
(35, 7, 'marks_recording', 'Marks Recording', 'Record examination marks', 7, 'subject_teacher', '[\"marks_verification\", \"marking_assignment\"]', '{\"required_fields\": [\"student_id\", \"marks\", \"exam_id\"], \"tables\": [\"assessment_results\", \"assessments\"], \"comment\": \"✅ Direct INSERT into assessment_results - no procedure needed\"}', NULL, 1),
(36, 7, 'marks_verification', 'Marks Verification', 'Verify recorded marks', 8, 'head_teacher', '[\"marks_moderation\", \"marks_recording\"]', '{\"validation_rules\": [\"all_students_marked\", \"marks_within_range\", \"no_duplicates\"], \"procedures\": [\"sp_record_assessment_change\"], \"tables\": [\"assessment_results\", \"assessment_history\"], \"comment\": \"✅ Uses existing sp_record_assessment_change for mark corrections\"}', NULL, 1),
(37, 7, 'marks_moderation', 'Marks Moderation', 'Moderate examination marks', 9, 'head_teacher', '[\"results_compilation\", \"marks_verification\"]', '{\"moderation_criteria\": [\"class_average\", \"grade_distribution\", \"cbc_standards\"], \"procedures\": [\"sp_moderate_marks\"], \"tables\": [\"assessment_results\", \"grade_rules\"], \"comment\": \"❌ MISSING: sp_moderate_marks - needs CBC moderation logic\"}', NULL, 1),
(38, 7, 'results_compilation', 'Results Compilation', 'Compile examination results', 10, 'system', '[\"results_approval\", \"marks_moderation\"]', '{\"procedures\": [\"sp_calculate_term_subject_score\", \"sp_consolidate_term_scores\", \"sp_compile_exam_results\"], \"tables\": [\"term_subject_scores\", \"term_consolidations\"], \"comment\": \"✅ Uses existing sp_calculate_term_subject_score and sp_consolidate_term_scores. ❌ MISSING: sp_compile_exam_results\"}', NULL, 1),
(39, 7, 'results_approval', 'Results Approval', 'Approve and publish results', 11, 'head_teacher', '[\"completed\"]', '{\"approval_rules\": [\"all_moderated\", \"all_compiled\", \"head_teacher_authorization\"], \"procedures\": [\"sp_generate_school_year_report\", \"sp_generate_student_report\"], \"tables\": [\"assessments\", \"term_consolidations\"], \"events\": [\"evt_process_academic_summary\"], \"comment\": \"✅ Uses existing procedures. Event auto-processes academic summary.\"}', NULL, 1),
(40, 8, 'promotion_planning', 'Promotion Planning', 'Plan class promotions', 1, 'head_teacher', '[\"student_assessment\", \"cancelled\"]', '{\"required_fields\": [\"academic_year\", \"promotion_criteria\"], \"procedures\": [\"sp_create_promotion_batch\"], \"tables\": [\"promotion_batches\", \"academic_years\"], \"comment\": \"✅ Uses existing sp_create_promotion_batch\"}', NULL, 1),
(41, 8, 'student_assessment', 'Student Assessment', 'Assess promotion eligibility', 2, 'class_teacher', '[\"promotion_recommendation\", \"promotion_planning\"]', '{\"assessment_criteria\": [\"academic_performance\", \"attendance_rate\", \"conduct\", \"fees_status\"], \"procedures\": [\"sp_calculate_annual_scores\"], \"functions\": [\"fn_get_student_promotion_status\", \"fn_student_fee_due\"], \"tables\": [\"annual_scores\", \"attendance_records\", \"student_fee_balances\"], \"comment\": \"✅ Uses existing sp_calculate_annual_scores and functions\"}', NULL, 1),
(42, 8, 'promotion_recommendation', 'Promotion Recommendation', 'Recommend students for promotion', 3, 'class_teacher', '[\"promotion_approval\", \"student_assessment\"]', '{\"required_fields\": [\"student_ids\", \"target_class\", \"recommendation\"], \"procedures\": [\"sp_promote_bulk_students\"], \"tables\": [\"student_promotions\"], \"comment\": \"✅ Uses existing sp_promote_bulk_students\"}', NULL, 1),
(43, 8, 'promotion_approval', 'Promotion Approval', 'Approve student promotions', 4, 'head_teacher', '[\"promotion_processing\", \"promotion_recommendation\"]', '{\"approval_rules\": [\"meets_criteria\", \"fees_cleared\", \"no_pending_issues\"], \"procedures\": [\"sp_approve_student_promotion\", \"sp_reject_student_promotion\", \"sp_suspend_student_promotion\"], \"tables\": [\"student_promotions\"], \"comment\": \"✅ Uses existing approval/rejection procedures\"}', NULL, 1),
(44, 8, 'promotion_processing', 'Promotion Processing', 'Process approved promotions', 5, 'registrar', '[\"completed\"]', '{\"procedures\": [\"sp_promote_by_grade_bulk\", \"sp_complete_promotion_batch\"], \"tables\": [\"students\", \"student_promotions\", \"classes\"], \"triggers\": [\"trg_create_portfolio_on_promotion\"], \"comment\": \"✅ Uses existing procedures. Trigger auto-creates student portfolios.\"}', NULL, 1),
(45, 9, 'incident_reporting', 'Incident Reporting', 'Report discipline incident', 1, 'staff', '[\"case_assessment\", \"dismissed\"]', '{\"required_fields\": [\"student_id\", \"incident_type\", \"description\", \"witnesses\"], \"procedures\": [\"sp_record_discipline_case\"], \"tables\": [\"student_discipline\", \"discipline_incidents\"], \"comment\": \"❌ MISSING: sp_record_discipline_case procedure. Can use direct INSERT to student_discipline table for now.\"}', NULL, 1),
(46, 9, 'case_assessment', 'Case Assessment', 'Assess severity of case', 2, 'class_teacher', '[\"informal_resolution\", \"formal_hearing\", \"dismissed\"]', '{\"assessment_criteria\": [\"severity\", \"frequency\", \"impact\", \"student_history\"], \"tables\": [\"student_discipline\", \"conduct_tracking\"], \"procedures\": [\"sp_record_conduct\"], \"comment\": \"✅ Uses existing sp_record_conduct. Direct UPDATE to student_discipline for assessment.\"}', NULL, 1),
(47, 9, 'informal_resolution', 'Informal Resolution', 'Resolve informally', 3, 'class_teacher', '[\"counseling\", \"closed\"]', '{\"resolution_methods\": [\"verbal_warning\", \"parent_meeting\", \"behavior_contract\"], \"tables\": [\"student_discipline\"], \"comment\": \"✅ Direct UPDATE to student_discipline.resolution_type\"}', NULL, 1),
(48, 9, 'formal_hearing', 'Formal Hearing', 'Schedule and conduct hearing', 4, 'head_teacher', '[\"decision_recording\", \"case_assessment\"]', '{\"required_fields\": [\"hearing_date\", \"panel_members\", \"evidence\"], \"procedures\": [\"sp_schedule_discipline_hearing\"], \"tables\": [\"student_discipline\", \"discipline_hearings\"], \"comment\": \"❌ MISSING: sp_schedule_discipline_hearing. Can use INSERT to discipline_hearings table.\"}', NULL, 1),
(49, 9, 'decision_recording', 'Decision Recording', 'Record hearing decision', 5, 'head_teacher', '[\"action_implementation\", \"appeal\"]', '{\"decision_types\": [\"no_action\", \"warning\", \"suspension\", \"expulsion\"], \"tables\": [\"student_discipline\"], \"comment\": \"✅ Direct UPDATE to student_discipline.decision\"}', NULL, 1),
(50, 9, 'action_implementation', 'Action Implementation', 'Implement disciplinary action', 6, 'head_teacher', '[\"progress_monitoring\", \"closed\"]', '{\"procedures\": [\"sp_implement_discipline_action\", \"sp_send_sms_to_parents\"], \"tables\": [\"student_discipline\", \"students\"], \"comment\": \"❌ MISSING: sp_implement_discipline_action. ✅ Uses existing sp_send_sms_to_parents for notifications.\"}', NULL, 1),
(51, 9, 'counseling', 'Counseling Sessions', 'Conduct counseling', 7, 'counselor', '[\"progress_monitoring\", \"closed\"]', '{\"required_fields\": [\"session_date\", \"session_notes\", \"next_session\"], \"tables\": [\"counseling_sessions\"], \"comment\": \"✅ Direct INSERT to counseling_sessions table\"}', NULL, 1),
(52, 9, 'progress_monitoring', 'Progress Monitoring', 'Monitor student progress', 8, 'class_teacher', '[\"closed\", \"action_implementation\"]', '{\"monitoring_criteria\": [\"behavior_improvement\", \"attendance\", \"academic_performance\"], \"procedures\": [\"sp_track_student_behavior\"], \"tables\": [\"student_discipline\", \"conduct_tracking\"], \"comment\": \"❌ MISSING: sp_track_student_behavior. Can use existing conduct_tracking table with direct INSERT/UPDATE.\"}', NULL, 1),
(53, 9, 'appeal', 'Appeal Process', 'Handle appeal', 9, 'director', '[\"decision_recording\", \"closed\"]', '{\"required_fields\": [\"appeal_reason\", \"new_evidence\"], \"tables\": [\"student_discipline\", \"discipline_appeals\"], \"comment\": \"✅ Direct INSERT to discipline_appeals table\"}', NULL, 1),
(54, 9, 'closed', 'Case Closed', 'Close discipline case', 10, 'head_teacher', '[\"completed\"]', '{\"required_actions\": [\"update_records\", \"notify_parties\", \"archive_documents\"], \"procedures\": [\"sp_send_sms_to_parents\"], \"tables\": [\"student_discipline\"], \"comment\": \"✅ Uses existing sp_send_sms_to_parents. Direct UPDATE to student_discipline.status = \\\"closed\\\"\"}', NULL, 1),
(55, 10, 'assessment_planning', 'Assessment Planning', 'Plan assessments', 1, 'teacher', '[\"assessment_administration\", \"cancelled\"]', '{\"tables\": [\"assessments\", \"curriculum_units\"]}', NULL, 1),
(56, 10, 'assessment_administration', 'Assessment Administration', 'Administer assessments', 2, 'teacher', '[\"marks_recording\", \"assessment_planning\"]', '{\"procedures\": [\"sp_bulk_mark_student_attendance\"]}', NULL, 1),
(57, 10, 'marks_recording', 'Marks Recording', 'Record marks', 3, 'teacher', '[\"competency_assessment\", \"marks_recording\"]', '{\"procedures\": [\"sp_record_assessment_change\"], \"tables\": [\"assessment_results\"]}', NULL, 1),
(58, 10, 'competency_assessment', 'Competency Assessment', 'Assess competencies', 4, 'teacher', '[\"score_calculation\", \"marks_recording\"]', '{\"procedures\": [\"sp_assess_learner_competency\", \"sp_record_core_value\"]}', NULL, 1),
(59, 10, 'score_calculation', 'Score Calculation', 'Calculate scores', 5, 'system', '[\"completed\"]', '{\"procedures\": [\"sp_calculate_term_subject_score\", \"sp_consolidate_term_scores\"]}', NULL, 1),
(60, 11, 'report_request', 'Report Request', 'Request report', 1, 'teacher', '[\"data_compilation\", \"cancelled\"]', '{\"tables\": [\"reports\"]}', NULL, 1),
(61, 11, 'data_compilation', 'Data Compilation', 'Compile data', 2, 'system', '[\"report_generation\"]', '{\"procedures\": [\"sp_generate_student_report\", \"sp_get_competency_report\"]}', NULL, 1),
(62, 11, 'report_generation', 'Report Generation', 'Generate report', 3, 'system', '[\"report_review\"]', '{\"procedures\": [\"sp_generate_comment\", \"sp_generate_performance_rating\"]}', NULL, 1),
(63, 11, 'report_review', 'Report Review', 'Review report', 4, 'head_teacher', '[\"report_distribution\"]', '{\"approval_required\": true}', NULL, 1),
(64, 11, 'report_distribution', 'Report Distribution', 'Distribute reports', 5, 'teacher', '[\"completed\"]', '{\"procedures\": [\"sp_send_sms_to_parents\", \"sp_send_announcement\"]}', NULL, 1),
(65, 12, 'application_submission', 'Application', 'Submit application', 1, 'applicant', '[\"application_review\", \"rejected\"]', '{\"tables\": [\"staff_applications\"]}', NULL, 1),
(66, 12, 'application_review', 'Review', 'Review application', 2, 'hr_manager', '[\"interview_scheduling\", \"rejected\"]', '{\"tables\": [\"staff_applications\"]}', NULL, 1),
(67, 12, 'interview_scheduling', 'Interview Scheduling', 'Schedule interview', 3, 'hr_manager', '[\"interview_conduct\", \"cancelled\"]', '{\"tables\": [\"staff_interviews\"]}', NULL, 1),
(68, 12, 'interview_conduct', 'Interview', 'Conduct interview', 4, 'head_teacher', '[\"offer_generation\", \"rejected\"]', '{\"tables\": [\"staff_interviews\"]}', NULL, 1),
(69, 12, 'offer_generation', 'Offer', 'Generate offer', 5, 'hr_manager', '[\"staff_registration\", \"cancelled\"]', '{\"tables\": [\"staff_offers\"]}', NULL, 1),
(70, 12, 'staff_registration', 'Registration', 'Register staff', 6, 'hr_manager', '[\"type_assignment\"]', '{\"tables\": [\"staff\", \"users\"]}', NULL, 1),
(71, 12, 'type_assignment', 'Type Assignment', 'Assign type', 7, 'hr_manager', '[\"completed\"]', '{\"procedures\": [\"sp_assign_staff_type_and_category\"]}', NULL, 1),
(72, 13, 'message_drafting', 'Drafting', 'Draft message', 1, 'admin', '[\"message_review\", \"cancelled\"]', '{\"tables\": [\"notifications\"]}', NULL, 1),
(73, 13, 'message_review', 'Review', 'Review message', 2, 'head_teacher', '[\"message_dispatch\", \"message_drafting\"]', '{\"approval_required\": true}', NULL, 1),
(74, 13, 'message_dispatch', 'Dispatch', 'Send message', 3, 'system', '[\"completed\"]', '{\"procedures\": [\"sp_send_announcement\", \"sp_send_sms_to_parents\", \"sp_send_internal_message\", \"sp_broadcast_notification\"]}', NULL, 1),
(75, 14, 'activity_planning', 'Planning', 'Plan activity', 1, 'activities_coordinator', '[\"student_registration\", \"cancelled\"]', '{\"tables\": [\"extracurricular_activities\"]}', NULL, 1),
(76, 14, 'student_registration', 'Registration', 'Register students', 2, 'teacher', '[\"activity_execution\"]', '{\"procedures\": [\"sp_record_csl_participation\"]}', NULL, 1),
(77, 14, 'activity_execution', 'Execution', 'Conduct activity', 3, 'teacher', '[\"activity_evaluation\"]', '{\"procedures\": [\"sp_bulk_mark_student_attendance\"]}', NULL, 1),
(78, 14, 'activity_evaluation', 'Evaluation', 'Evaluate', 4, 'teacher', '[\"completed\"]', '{\"procedures\": [\"sp_assess_learner_competency\"]}', NULL, 1),
(79, 15, 'transfer_request', 'Request', 'Request transfer', 1, 'registrar', '[\"clearance_check\", \"cancelled\"]', '{\"tables\": [\"student_transfers\"]}', NULL, 1),
(80, 15, 'clearance_check', 'Clearance', 'Check clearance', 2, 'accountant', '[\"transfer_approval\", \"fee_settlement\"]', '{\"procedures\": [\"sp_get_outstanding_fees_report\"]}', NULL, 1),
(81, 15, 'fee_settlement', 'Fee Settlement', 'Settle fees', 3, 'accountant', '[\"transfer_approval\"]', '{\"procedures\": [\"sp_process_student_payment\"]}', NULL, 1),
(82, 15, 'transfer_approval', 'Approval', 'Approve transfer', 4, 'head_teacher', '[\"document_preparation\"]', '{\"tables\": [\"student_transfers\"]}', NULL, 1),
(83, 15, 'document_preparation', 'Documents', 'Prepare documents', 5, 'registrar', '[\"transfer_completion\"]', '{\"procedures\": [\"sp_generate_student_report\"]}', NULL, 1),
(84, 15, 'transfer_completion', 'Completion', 'Complete transfer', 6, 'registrar', '[\"completed\"]', '{\"tables\": [\"students\"]}', NULL, 1),
(85, 16, 'book_request', 'Request', 'Request book', 1, 'student', '[\"availability_check\", \"cancelled\"]', '{\"tables\": [\"library_transactions\"]}', NULL, 1),
(86, 16, 'availability_check', 'Check', 'Check availability', 2, 'librarian', '[\"book_issue\"]', '{\"tables\": [\"library_books\"]}', NULL, 1),
(87, 16, 'book_issue', 'Issue', 'Issue book', 3, 'librarian', '[\"book_return\", \"completed\"]', '{\"tables\": [\"library_transactions\"]}', NULL, 1),
(88, 16, 'book_return', 'Return', 'Return book', 4, 'librarian', '[\"completed\"]', '{\"tables\": [\"library_transactions\"]}', NULL, 1),
(89, 17, 'route_planning', 'Planning', 'Plan routes', 1, 'transport_manager', '[\"student_assignment\"]', '{\"tables\": [\"transport_routes\"]}', NULL, 1),
(90, 17, 'student_assignment', 'Assignment', 'Assign students', 2, 'transport_manager', '[\"driver_assignment\"]', '{\"procedures\": [\"sp_bulk_transport_assignment\"]}', NULL, 1),
(91, 17, 'driver_assignment', 'Driver', 'Assign driver', 3, 'transport_manager', '[\"vehicle_maintenance\"]', '{\"tables\": [\"transport_vehicles\"]}', NULL, 1),
(92, 17, 'vehicle_maintenance', 'Maintenance', 'Schedule maintenance', 4, 'transport_manager', '[\"completed\"]', '{\"procedures\": [\"sp_schedule_maintenance\", \"sp_complete_maintenance\"]}', NULL, 1),
(93, 18, 'health_screening', 'Screening', 'Health screening', 1, 'nurse', '[\"record_creation\", \"medical_referral\"]', '{\"tables\": [\"student_health_records\"]}', NULL, 1),
(94, 18, 'record_creation', 'Record Creation', 'Create record', 2, 'nurse', '[\"wellness_monitoring\"]', '{\"tables\": [\"medical_history\"]}', NULL, 1),
(95, 18, 'wellness_monitoring', 'Monitoring', 'Monitor wellness', 3, 'nurse', '[\"completed\", \"medical_referral\"]', '{\"procedures\": [\"sp_record_food_consumption\"]}', NULL, 1),
(96, 18, 'medical_referral', 'Referral', 'Medical referral', 4, 'nurse', '[\"completed\"]', '{\"procedures\": [\"sp_send_sms_to_parents\"]}', NULL, 1),
(97, 19, 'conference_scheduling', 'Scheduling', 'Schedule meeting', 1, 'teacher', '[\"parent_notification\", \"cancelled\"]', '{\"tables\": [\"parent_teacher_meetings\"]}', NULL, 1),
(98, 19, 'parent_notification', 'Notification', 'Notify parent', 2, 'system', '[\"conference_conduct\"]', '{\"procedures\": [\"sp_send_sms_to_parents\"]}', NULL, 1),
(99, 19, 'conference_conduct', 'Conduct', 'Conduct meeting', 3, 'teacher', '[\"minutes_recording\"]', '{\"tables\": [\"parent_teacher_meetings\"]}', NULL, 1),
(100, 19, 'minutes_recording', 'Minutes', 'Record minutes', 4, 'teacher', '[\"completed\"]', '{\"tables\": [\"meeting_minutes\"]}', NULL, 1),
(101, 20, 'alumni_registration', 'Registration', 'Register alumni', 1, 'student', '[\"profile_verification\"]', '{\"tables\": [\"alumni\"]}', NULL, 1),
(102, 20, 'profile_verification', 'Verification', 'Verify profile', 2, 'admin', '[\"engagement_initiation\"]', '{\"tables\": [\"alumni\"]}', NULL, 1),
(103, 20, 'engagement_initiation', 'Engagement', 'Start engagement', 3, 'admin', '[\"completed\"]', '{\"procedures\": [\"sp_send_external_email\", \"sp_broadcast_notification\"]}', NULL, 1),
(104, 21, 'event_planning', 'Planning', 'Plan event', 1, 'events_coordinator', '[\"budget_approval\", \"cancelled\"]', '{\"tables\": [\"school_events\"]}', NULL, 1),
(105, 21, 'budget_approval', 'Budget', 'Approve budget', 2, 'head_teacher', '[\"resource_allocation\"]', '{\"tables\": [\"event_budgets\"]}', NULL, 1),
(106, 21, 'resource_allocation', 'Resources', 'Allocate resources', 3, 'events_coordinator', '[\"event_execution\"]', '{\"procedures\": [\"sp_process_requisition\"]}', NULL, 1),
(107, 21, 'event_execution', 'Execution', 'Execute event', 4, 'events_coordinator', '[\"event_evaluation\"]', '{\"procedures\": [\"sp_bulk_mark_student_attendance\"]}', NULL, 1),
(108, 21, 'event_evaluation', 'Evaluation', 'Evaluate event', 5, 'events_coordinator', '[\"completed\"]', '{\"tables\": [\"event_evaluations\"]}', NULL, 1),
(109, 22, 'curriculum_design', 'Design', 'Design curriculum', 1, 'head_teacher', '[\"subject_allocation\"]', '{\"tables\": [\"curriculum_units\"]}', NULL, 1),
(110, 22, 'subject_allocation', 'Allocation', 'Allocate subjects', 2, 'head_teacher', '[\"scheme_development\"]', '{\"tables\": [\"teacher_subjects\"]}', NULL, 1),
(111, 22, 'scheme_development', 'Schemes', 'Develop schemes', 3, 'teacher', '[\"curriculum_approval\"]', '{\"tables\": [\"schemes_of_work\"]}', NULL, 1),
(112, 22, 'curriculum_approval', 'Approval', 'Approve curriculum', 4, 'head_teacher', '[\"completed\"]', '{\"tables\": [\"curriculum_units\"]}', NULL, 1),
(113, 23, 'kpi_setting', 'KPI Setting', 'Set KPIs', 1, 'head_teacher', '[\"performance_monitoring\"]', '{\"tables\": [\"staff_kpis\"]}', NULL, 1),
(114, 23, 'performance_monitoring', 'Monitoring', 'Monitor performance', 2, 'head_teacher', '[\"performance_evaluation\"]', '{\"procedures\": [\"sp_calculate_kpi_achievement_score\", \"sp_update_kpi_achievement\"]}', NULL, 1),
(115, 23, 'performance_evaluation', 'Evaluation', 'Evaluate', 3, 'head_teacher', '[\"appraisal_meeting\"]', '{\"procedures\": [\"sp_get_staff_kpi_summary\", \"sp_compare_to_benchmark\"]}', NULL, 1),
(116, 23, 'appraisal_meeting', 'Appraisal', 'Appraisal meeting', 4, 'head_teacher', '[\"completed\"]', '{\"events\": [\"evt_staff_appraisal_reminders\"]}', NULL, 1),
(117, 24, 'application_submission', 'Application', 'Submit application', 1, 'parent', '[\"eligibility_assessment\", \"rejected\"]', '{\"tables\": [\"scholarship_applications\"]}', NULL, 1),
(118, 24, 'eligibility_assessment', 'Eligibility', 'Assess eligibility', 2, 'head_teacher', '[\"aid_approval\", \"rejected\"]', '{\"procedures\": [\"sp_get_outstanding_fees_report\"]}', NULL, 1),
(119, 24, 'aid_approval', 'Approval', 'Approve aid', 3, 'head_teacher', '[\"aid_processing\"]', '{\"tables\": [\"scholarship_applications\"]}', NULL, 1),
(120, 24, 'aid_processing', 'Processing', 'Process aid', 4, 'accountant', '[\"completed\"]', '{\"procedures\": [\"sp_apply_fee_discount\", \"sp_process_student_sponsorship\"]}', NULL, 1),
(121, 25, 'year_planning', 'Planning', 'Plan year', 1, 'head_teacher', '[\"term_setup\"]', '{\"tables\": [\"academic_years\"]}', NULL, 1),
(122, 25, 'term_setup', 'Terms', 'Setup terms', 2, 'admin', '[\"class_configuration\"]', '{\"tables\": [\"academic_terms\", \"holidays\"]}', NULL, 1),
(123, 25, 'class_configuration', 'Classes', 'Configure classes', 3, 'head_teacher', '[\"student_promotion\"]', '{\"procedures\": [\"sp_ensure_class_streams\", \"sp_add_custom_stream\"]}', NULL, 1),
(124, 25, 'student_promotion', 'Promotion', 'Promote students', 4, 'registrar', '[\"fee_structure_setup\"]', '{\"procedures\": [\"sp_transition_to_new_academic_year\", \"sp_promote_by_grade_bulk\"]}', NULL, 1),
(125, 25, 'fee_structure_setup', 'Fees', 'Setup fees', 5, 'accountant', '[\"system_activation\"]', '{\"procedures\": [\"sp_calculate_student_fees\", \"sp_create_arrears_record\"]}', NULL, 1),
(126, 25, 'system_activation', 'Activation', 'Activate system', 6, 'admin', '[\"completed\"]', '{\"procedures\": [\"sp_transition_to_new_term\"]}', NULL, 1),
(127, 26, 'leave_request', 'Leave Request Submission', 'Staff member submits leave request with dates and reason', 1, 'staff', '[\"supervisor_review\", \"cancelled\"]', '{\"required_fields\": [\"staff_id\", \"leave_type_id\", \"start_date\", \"end_date\", \"days_requested\", \"reason\"], \"validations\": [\"check_balance\", \"check_overlap\", \"check_working_days\"], \"procedures\": [\"sp_calculate_staff_leave_balance\"], \"triggers\": [\"trg_check_leave_overlap\"], \"tables\": [\"staff_leaves\", \"leave_types\"], \"comment\": \"Balance and overlap checked automatically by procedure and trigger\"}', 24, 1),
(128, 26, 'supervisor_review', 'Supervisor Review', 'Immediate supervisor reviews and recommends approval or rejection', 2, 'supervisor', '[\"hr_approval\", \"rejected\", \"leave_request\"]', '{\"required_fields\": [\"supervisor_notes\", \"relief_staff_id\"], \"views\": [\"vw_staff_leave_balances\"], \"comment\": \"Supervisor must assign relief staff\"}', 48, 1),
(129, 26, 'hr_approval', 'HR Manager Approval', 'HR manager verifies leave balance and approves', 3, 'hr_manager', '[\"director_approval\", \"approved\", \"rejected\", \"supervisor_review\"]', '{\"required_fields\": [\"hr_notes\"], \"procedures\": [\"sp_calculate_staff_leave_balance\"], \"views\": [\"vw_staff_leave_balances\"], \"approval_rules\": [\"sufficient_balance\", \"no_overlaps\", \"relief_assigned\"], \"escalation_rules\": {\"days_threshold\": 14, \"escalate_to\": \"director_approval\"}, \"comment\": \"Leaves > 14 days require director approval\"}', 24, 1),
(130, 26, 'director_approval', 'Director Approval', 'Director approval for extended leaves (> 14 days)', 4, 'director', '[\"approved\", \"rejected\", \"hr_approval\"]', '{\"required_fields\": [\"director_notes\"], \"approval_rules\": [\"extended_leave_justified\", \"business_continuity_ensured\"], \"comment\": \"Required only for leaves exceeding 14 days\"}', 24, 1),
(131, 26, 'approved', 'Leave Approved', 'Leave request approved and staff notified', 5, 'system', '[\"completed\"]', '{\"triggers\": [\"trg_update_leave_balance\"], \"notifications\": [\"staff\", \"supervisor\", \"relief_staff\", \"hr\"], \"comment\": \"Trigger auto-sends notification to staff\"}', NULL, 1),
(132, 26, 'rejected', 'Leave Rejected', 'Leave request rejected with reason', 6, 'system', '[\"cancelled\"]', '{\"required_fields\": [\"rejection_reason\"], \"notifications\": [\"staff\"], \"comment\": \"Staff notified of rejection reason\"}', NULL, 1),
(133, 27, 'assignment_request', 'Assignment Request', 'Request to assign staff to class/subject', 1, 'head_teacher', '[\"validation\", \"cancelled\"]', '{\"required_fields\": [\"staff_id\", \"class_stream_id\", \"academic_year_id\", \"role\", \"start_date\"], \"optional_fields\": [\"subject_id\", \"end_date\", \"notes\"], \"roles\": [\"class_teacher\", \"subject_teacher\", \"assistant_teacher\", \"head_of_department\"], \"tables\": [\"staff_class_assignments\"], \"comment\": \"Initiate staff assignment request\"}', NULL, 1),
(134, 27, 'validation', 'Assignment Validation', 'Validate assignment against workload and conflicts', 2, 'system', '[\"head_teacher_approval\", \"assignment_request\"]', '{\"procedures\": [\"sp_validate_staff_assignment\"], \"views\": [\"vw_staff_assignments_detailed\", \"vw_staff_workload\"], \"validation_rules\": [\"no_duplicate_assignment\", \"max_workload_not_exceeded\", \"one_class_teacher_per_class\"], \"max_workload\": 8, \"comment\": \"Procedure validates: duplicates, class_teacher limit, max 8 workload\"}', NULL, 1),
(135, 27, 'head_teacher_approval', 'Head Teacher Approval', 'Head teacher reviews and approves assignment', 3, 'head_teacher', '[\"notification\", \"rejected\", \"assignment_request\"]', '{\"required_fields\": [\"approval_notes\"], \"views\": [\"vw_staff_assignments_detailed\", \"vw_staff_workload\"], \"approval_rules\": [\"staff_qualified\", \"workload_balanced\", \"timetable_compatible\"], \"comment\": \"Final approval from head teacher\"}', 48, 1),
(136, 27, 'notification', 'Assignment Notification', 'Notify staff and update systems', 4, 'system', '[\"completed\"]', '{\"status_update\": \"active\", \"notifications\": [\"staff\", \"subject_teachers\", \"class_students\"], \"integrations\": [\"timetable\", \"class_register\", \"assessment_system\"], \"triggers\": [\"trg_complete_staff_assignments_on_year_end\"], \"comment\": \"Trigger auto-completes assignment at academic year end\"}', NULL, 1),
(137, 27, 'rejected', 'Assignment Rejected', 'Assignment request rejected', 5, 'system', '[\"cancelled\"]', '{\"required_fields\": [\"rejection_reason\"], \"notifications\": [\"requester\"], \"comment\": \"Assignment not approved\"}', NULL, 1),
(138, 28, 'draft', 'Draft', 'Fee structure created in draft state', 1, 'accountant', '[\"review\"]', '{\"required_fields\": [\"name\", \"amount\", \"academic_year\", \"fee_type_id\"], \"tables\": [\"fee_structures\"], \"comment\": \"Initial creation stage\"}', NULL, 1),
(139, 28, 'review', 'Finance Review', 'Under review by finance team', 2, 'accountant', '[\"approval\", \"rejected\"]', '{\"required_actions\": [\"verify_amounts\", \"check_fee_categories\", \"validate_term\"], \"tables\": [\"fee_structures\", \"fee_types\"], \"comment\": \"Finance team reviews fee structure\"}', NULL, 1),
(140, 28, 'approval', 'Director Approval', 'Pending director approval', 3, 'director', '[\"activation\", \"rejected\"]', '{\"required_actions\": [\"director_review\", \"final_approval\"], \"approval_rules\": [\"authorized_by_director\", \"meets_policy_guidelines\"], \"comment\": \"Director must approve before activation\"}', NULL, 1),
(141, 28, 'activation', 'Activation', 'Fee structure activated', 4, 'accountant', '[\"completed\"]', '{\"required_actions\": [\"activate_fee_structure\", \"notify_stakeholders\"], \"procedures\": [\"sp_calculate_student_fees\"], \"comment\": \"Fee structure becomes active and applicable\"}', NULL, 1),
(142, 28, 'rejected', 'Rejected', 'Fee structure rejected', 5, 'accountant', '[\"draft\"]', '{\"required_fields\": [\"rejection_reason\"], \"comment\": \"Rejected fee structure can be revised\"}', NULL, 1),
(143, 28, 'completed', 'Completed', 'Workflow completed', 6, 'system', '[]', '{\"final_state\": true, \"comment\": \"Fee structure fully approved and activated\"}', NULL, 1),
(144, 29, 'draft', 'Draft', 'Budget created in draft state', 1, 'accountant', '[\"departmental_review\"]', '{\"required_fields\": [\"name\", \"fiscal_year\", \"total_amount\", \"department_id\"], \"tables\": [\"budgets\", \"budget_items\"], \"comment\": \"Initial budget creation\"}', NULL, 1),
(145, 29, 'departmental_review', 'Departmental Review', 'Under departmental review', 2, 'department_head', '[\"finance_review\", \"rejected\"]', '{\"required_actions\": [\"verify_departmental_needs\", \"check_allocations\"], \"approval_rules\": [\"department_head_approval\"], \"comment\": \"Department head reviews and approves\"}', NULL, 1),
(146, 29, 'finance_review', 'Finance Review', 'Under finance team review', 3, 'accountant', '[\"director_approval\", \"rejected\"]', '{\"required_actions\": [\"verify_budget_calculations\", \"check_funds_availability\"], \"tables\": [\"budgets\", \"budget_items\"], \"comment\": \"Finance team validates budget\"}', NULL, 1),
(147, 29, 'director_approval', 'Director Approval', 'Pending director approval', 4, 'director', '[\"completed\", \"rejected\"]', '{\"required_actions\": [\"final_approval\", \"authorize_expenditure\"], \"approval_rules\": [\"director_authorization\", \"meets_financial_policy\"], \"comment\": \"Final approval by director\"}', NULL, 1),
(148, 29, 'rejected', 'Rejected', 'Budget rejected', 5, 'accountant', '[\"draft\"]', '{\"required_fields\": [\"rejection_reason\", \"rejection_stage\"], \"comment\": \"Budget sent back for revision\"}', NULL, 1),
(149, 29, 'completed', 'Approved', 'Budget approved', 6, 'system', '[]', '{\"final_state\": true, \"procedures\": [\"sp_track_expenditure\"], \"comment\": \"Budget fully approved and active\"}', NULL, 1),
(150, 30, 'submission', 'Submission', 'Expense submitted', 1, 'accountant', '[\"validation\"]', '{\"required_fields\": [\"description\", \"amount\", \"expense_category\", \"payee\"], \"tables\": [\"expenses\"], \"comment\": \"Initial expense submission\"}', NULL, 1),
(151, 30, 'validation', 'Finance Validation', 'Under finance validation', 2, 'accountant', '[\"approval\", \"rejected\"]', '{\"required_actions\": [\"verify_documentation\", \"check_budget\", \"validate_amount\"], \"tables\": [\"expenses\", \"budgets\"], \"comment\": \"Finance team validates expense claim\"}', NULL, 1),
(152, 30, 'approval', 'Manager Approval', 'Pending manager approval', 3, 'manager', '[\"payment\", \"rejected\"]', '{\"required_actions\": [\"manager_review\", \"authorize_payment\"], \"approval_rules\": [\"within_budget\", \"authorized_category\"], \"comment\": \"Manager approves for payment\"}', NULL, 1),
(153, 30, 'payment', 'Payment Processing', 'Processing payment', 4, 'accountant', '[\"completed\"]', '{\"required_actions\": [\"process_payment\", \"update_budget\"], \"procedures\": [\"sp_process_expense_payment\"], \"tables\": [\"expenses\", \"payment_transactions\"], \"comment\": \"Payment processed and recorded\"}', NULL, 1),
(154, 30, 'rejected', 'Rejected', 'Expense rejected', 5, 'accountant', '[\"submission\"]', '{\"required_fields\": [\"rejection_reason\"], \"comment\": \"Expense rejected, can be resubmitted\"}', NULL, 1),
(155, 30, 'completed', 'Completed', 'Expense paid', 6, 'system', '[]', '{\"final_state\": true, \"comment\": \"Expense fully processed and paid\"}', NULL, 1),
(156, 31, 'draft', 'Draft', 'Payroll draft created', 1, 'hr_manager', '[\"pending_approval\"]', '{\"required_fields\": [\"month\", \"year\", \"staff_count\"], \"procedures\": [\"sp_calculate_payroll_for_staff\"], \"tables\": [\"payrolls\", \"staff_payments\"], \"comment\": \"Payroll calculated and drafted\"}', NULL, 1),
(157, 31, 'pending_approval', 'Pending Approval', 'Awaiting approval', 2, 'director', '[\"approved\", \"rejected\"]', '{\"required_actions\": [\"verify_calculations\", \"check_budget\", \"authorize\"], \"approval_rules\": [\"director_authorization\", \"funds_available\"], \"comment\": \"Director reviews and approves payroll\"}', NULL, 1),
(158, 31, 'approved', 'Approved', 'Payroll approved', 3, 'accountant', '[\"processing\"]', '{\"required_actions\": [\"prepare_disbursement\", \"update_status\"], \"comment\": \"Approved and ready for processing\"}', NULL, 1),
(159, 31, 'processing', 'Processing', 'Disbursing payments', 4, 'system', '[\"completed\", \"partial\"]', '{\"procedures\": [\"sp_process_monthly_payroll\"], \"services\": [\"MpesaB2CService\", \"KcbFundsTransferService\"], \"tables\": [\"staff_payments\", \"payment_transactions\"], \"comment\": \"Payments being disbursed to staff\"}', NULL, 1),
(160, 31, 'partial', 'Partially Completed', 'Some payments failed', 5, 'accountant', '[\"processing\", \"completed\"]', '{\"required_actions\": [\"review_failed_payments\", \"retry_failed\"], \"comment\": \"Some disbursements failed, requires review\"}', NULL, 1),
(161, 31, 'rejected', 'Rejected', 'Payroll rejected', 6, 'hr_manager', '[\"draft\"]', '{\"required_fields\": [\"rejection_reason\"], \"comment\": \"Sent back for revision\"}', NULL, 1),
(162, 31, 'completed', 'Completed', 'Payroll completed', 7, 'system', '[]', '{\"final_state\": true, \"comment\": \"All payments successfully disbursed\"}', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `workflow_stage_history`
--
-- Creation: Nov 10, 2025 at 11:27 AM
--

DROP TABLE IF EXISTS `workflow_stage_history`;
CREATE TABLE IF NOT EXISTS `workflow_stage_history` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `instance_id` int(10) UNSIGNED NOT NULL,
  `from_stage` varchar(50) NOT NULL,
  `to_stage` varchar(50) NOT NULL,
  `action_taken` varchar(100) NOT NULL,
  `processed_by` int(10) UNSIGNED NOT NULL,
  `processed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `remarks` text DEFAULT NULL,
  `data_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Stage-specific data' CHECK (json_valid(`data_json`)),
  PRIMARY KEY (`id`),
  KEY `idx_instance_history` (`instance_id`),
  KEY `fk_workflow_processor` (`processed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `workflow_stage_history`:
--   `instance_id`
--       `workflow_instances` -> `id`
--   `processed_by`
--       `users` -> `id`
--

--
-- Truncate table before insert `workflow_stage_history`
--

TRUNCATE TABLE `workflow_stage_history`;
-- --------------------------------------------------------

--
-- Structure for view `vw_active_allocations` exported as a table
--
DROP TABLE IF EXISTS `vw_active_allocations`;
CREATE TABLE IF NOT EXISTS `vw_active_allocations`(
    `id` int(10) unsigned NOT NULL DEFAULT '0',
    `allocation_number` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
    `item_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `item_code` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `category` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `allocated_quantity` int(11) NOT NULL,
    `returned_quantity` int(11) DEFAULT '0',
    `outstanding_quantity` bigint(12) DEFAULT NULL,
    `department` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `allocated_to_event` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `class_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `status` enum('allocated','issued','partially_returned','fully_returned','expired','cancelled') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'allocated',
    `allocation_date` date NOT NULL,
    `expected_return_date` date DEFAULT NULL,
    `allocated_by_first` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `allocated_by_last` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT 'current_timestamp()'
);

-- --------------------------------------------------------

--
-- Structure for view `vw_arrears_summary` exported as a table
--
DROP TABLE IF EXISTS `vw_arrears_summary`;
CREATE TABLE IF NOT EXISTS `vw_arrears_summary`(
    `level` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
    `level_code` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
    `students_in_arrears` bigint(21) NOT NULL DEFAULT '0',
    `total_arrears_amount` decimal(32,2) DEFAULT NULL,
    `average_arrears` decimal(14,6) DEFAULT NULL,
    `overdue_students` bigint(21) NOT NULL DEFAULT '0',
    `overdue_more_than_30_days` bigint(21) NOT NULL DEFAULT '0',
    `overdue_more_than_60_days` bigint(21) NOT NULL DEFAULT '0',
    `settlement_plans_active` bigint(21) NOT NULL DEFAULT '0',
    `amount_on_settlement_plans` decimal(32,2) DEFAULT NULL
);

-- --------------------------------------------------------

--
-- Structure for view `vw_class_rosters` exported as a table
--
DROP TABLE IF EXISTS `vw_class_rosters`;
CREATE TABLE IF NOT EXISTS `vw_class_rosters`(
    `assignment_id` int(10) unsigned NOT NULL DEFAULT '0',
    `year_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '2024/2025, 2025/2026',
    `class_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
    `stream_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
    `class_stream` varchar(103) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
    `teacher_id` int(10) unsigned DEFAULT NULL COMMENT 'Class teacher',
    `teacher_name` varchar(101) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `room_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `capacity` int(11) DEFAULT '40',
    `current_enrollment` int(11) DEFAULT '0',
    `available_slots` bigint(12) DEFAULT NULL,
    `occupancy_rate` decimal(16,2) DEFAULT NULL
);

-- --------------------------------------------------------

--
-- Structure for view `vw_collection_rate_by_class` exported as a table
--
DROP TABLE IF EXISTS `vw_collection_rate_by_class`;
CREATE TABLE IF NOT EXISTS `vw_collection_rate_by_class`(
    `level_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
    `level_code` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
    `academic_term` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
    `total_students` bigint(21) NOT NULL DEFAULT '0',
    `total_fees_due` decimal(32,2) DEFAULT NULL,
    `total_fees_paid` decimal(32,2) DEFAULT NULL,
    `total_fees_waived` decimal(32,2) DEFAULT NULL,
    `collection_rate_percent` decimal(38,2) DEFAULT NULL,
    `students_paid_in_full` bigint(21) NOT NULL DEFAULT '0',
    `students_partial_payment` bigint(21) NOT NULL DEFAULT '0',
    `students_no_payment` bigint(21) NOT NULL DEFAULT '0',
    `average_payment_per_student` decimal(11,2) DEFAULT NULL
);

-- --------------------------------------------------------

--
-- Structure for view `vw_currently_blocked_ips` exported as a table
--
DROP TABLE IF EXISTS `vw_currently_blocked_ips`;
CREATE TABLE IF NOT EXISTS `vw_currently_blocked_ips`(
    `ip_address` varchar(45) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'IPv4 or IPv6 address',
    `reason` varchar(255) COLLATE utf8mb4_general_ci NOT NULL COMMENT 'Why this IP was blocked',
    `blocked_at` timestamp NOT NULL DEFAULT 'current_timestamp()',
    `expires_at` timestamp DEFAULT NULL COMMENT 'NULL = permanent block',
    `block_status` varchar(40) COLLATE utf8mb4_general_ci DEFAULT NULL
);

-- --------------------------------------------------------

--
-- Structure for view `vw_current_enrollments` exported as a table
--
DROP TABLE IF EXISTS `vw_current_enrollments`;
CREATE TABLE IF NOT EXISTS `vw_current_enrollments`(
    `enrollment_id` int(10) unsigned NOT NULL DEFAULT '0',
    `student_id` int(10) unsigned NOT NULL,
    `admission_no` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
    `student_name` varchar(152) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
    `gender` enum('male','female','other') COLLATE utf8mb4_general_ci NOT NULL,
    `student_status` enum('active','inactive','graduated','transferred','suspended') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
    `academic_year_id` int(10) unsigned NOT NULL DEFAULT '0',
    `year_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '2024/2025, 2025/2026',
    `is_current` tinyint(1) DEFAULT '0',
    `class_id` int(10) unsigned NOT NULL DEFAULT '0',
    `class_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
    `stream_id` int(10) unsigned NOT NULL DEFAULT '0',
    `stream_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
    `class_stream` varchar(103) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
    `teacher_id` int(10) unsigned DEFAULT NULL COMMENT 'Class teacher',
    `teacher_first_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `teacher_last_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `room_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `enrollment_status` enum('pending','active','completed','withdrawn','transferred','graduated') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
    `year_average` decimal(5,2) DEFAULT NULL,
    `overall_grade` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `class_rank` int(11) DEFAULT NULL,
    `attendance_percentage` decimal(5,2) DEFAULT NULL,
    `promotion_status` enum('pending','promoted','retained','transferred','graduated','withdrawn') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `enrollment_date` date NOT NULL
);

-- --------------------------------------------------------

--
-- Structure for view `vw_current_staff_assignments` exported as a table
--
DROP TABLE IF EXISTS `vw_current_staff_assignments`;
CREATE TABLE IF NOT EXISTS `vw_current_staff_assignments`(
    `id` int(10) unsigned NOT NULL DEFAULT '0',
    `staff_id` int(10) unsigned NOT NULL,
    `staff_name` varchar(101) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
    `staff_no` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
    `staff_category` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `class_id` int(10) unsigned NOT NULL,
    `class_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
    `stream_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `role` enum('class_teacher','subject_teacher','assistant_teacher','head_of_department') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'subject_teacher',
    `subject_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `academic_year` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Academic Year 2024/2025',
    `year_status` enum('planning','registration','active','closing','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'planning',
    `start_date` date NOT NULL,
    `end_date` date DEFAULT NULL,
    `status` enum('active','completed','transferred','terminated') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active'
);

-- --------------------------------------------------------

--
-- Structure for view `vw_failed_attempts_by_ip` exported as a table
--
DROP TABLE IF EXISTS `vw_failed_attempts_by_ip`;
CREATE TABLE IF NOT EXISTS `vw_failed_attempts_by_ip`(
    `ip_address` varchar(45) COLLATE utf8mb4_general_ci NOT NULL,
    `attempt_count` bigint(21) NOT NULL DEFAULT '0',
    `last_attempt` timestamp DEFAULT 'current_timestamp()',
    `failure_reasons` mediumtext COLLATE utf8mb4_general_ci DEFAULT NULL
);

-- --------------------------------------------------------

--
-- Structure for view `vw_fee_carryover_summary` exported as a table
--
DROP TABLE IF EXISTS `vw_fee_carryover_summary`;
CREATE TABLE IF NOT EXISTS `vw_fee_carryover_summary`(
    `student_id` int(10) unsigned NOT NULL,
    `admission_no` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
    `student_name` varchar(101) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
    `class_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `academic_year` int(11) NOT NULL COMMENT 'Academic year (e.g., 2024)',
    `term_id` int(10) unsigned DEFAULT NULL COMMENT 'Term ID for term-level carryover, NULL for year-level',
    `period_type` varchar(15) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `previous_balance` decimal(12,2) DEFAULT '0.00' COMMENT 'Balance carried forward from previous period (positive = debt)',
    `surplus_amount` decimal(12,2) DEFAULT '0.00' COMMENT 'Surplus from previous period (positive = credit)',
    `action_taken` enum('fresh_bill','add_to_current','deduct_from_current','manual_adjustment') COLLATE utf8mb4_general_ci DEFAULT 'fresh_bill' COMMENT 'Action taken during carryover',
    `created_at` timestamp NOT NULL DEFAULT 'current_timestamp()',
    `notes` text COLLATE utf8mb4_general_ci DEFAULT NULL
);

-- --------------------------------------------------------

--
-- Structure for view `vw_fee_collection_by_year` exported as a table
--
DROP TABLE IF EXISTS `vw_fee_collection_by_year`;
CREATE TABLE IF NOT EXISTS `vw_fee_collection_by_year`(
    `academic_year` year(4) NOT NULL,
    `total_students` bigint(21) NOT NULL DEFAULT '0',
    `total_fees_due` decimal(32,2) DEFAULT NULL,
    `total_collected` decimal(32,2) DEFAULT NULL,
    `total_outstanding` decimal(32,2) DEFAULT NULL,
    `collection_rate_percent` decimal(38,2) DEFAULT NULL,
    `students_paid_full` decimal(22,0) DEFAULT NULL,
    `students_partial` decimal(22,0) DEFAULT NULL,
    `students_arrears` decimal(22,0) DEFAULT NULL,
    `students_pending` decimal(22,0) DEFAULT NULL
);

-- --------------------------------------------------------

--
-- Structure for view `vw_fee_schedule_by_class` exported as a table
--
DROP TABLE IF EXISTS `vw_fee_schedule_by_class`;
CREATE TABLE IF NOT EXISTS `vw_fee_schedule_by_class`(
    `level_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
    `level_code` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
    `academic_term` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
    `student_type` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
    `student_type_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
    `fee_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
    `fee_category` enum('tuition','boarding','activity','infrastructure','other') COLLATE utf8mb4_general_ci NOT NULL,
    `amount_due` decimal(10,2) NOT NULL,
    `due_date` date DEFAULT NULL,
    `number_of_students` bigint(21) NOT NULL DEFAULT '0'
);

-- --------------------------------------------------------

--
-- Structure for view `vw_fee_structure_annual_summary` exported as a table
--
DROP TABLE IF EXISTS `vw_fee_structure_annual_summary`;
CREATE TABLE IF NOT EXISTS `vw_fee_structure_annual_summary`(
    `academic_year` year(4) NOT NULL,
    `level_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
    `level_id` int(10) unsigned NOT NULL DEFAULT '0',
    `fee_type` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
    `fee_type_id` int(10) unsigned NOT NULL DEFAULT '0',
    `fee_category` enum('tuition','boarding','activity','infrastructure','other') COLLATE utf8mb4_general_ci NOT NULL,
    `term1_amount` decimal(32,2) DEFAULT NULL,
    `term2_amount` decimal(32,2) DEFAULT NULL,
    `term3_amount` decimal(32,2) DEFAULT NULL,
    `annual_total` decimal(32,2) DEFAULT NULL,
    `status` enum('draft','pending_review','reviewed','approved','active','archived') COLLATE utf8mb4_general_ci DEFAULT 'draft',
    `is_auto_rollover` tinyint(1) DEFAULT '0',
    `reviewed_by` int(10) unsigned DEFAULT NULL,
    `reviewer_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `reviewed_at` datetime DEFAULT NULL,
    `approved_by` int(10) unsigned DEFAULT NULL,
    `approver_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `approved_at` datetime DEFAULT NULL,
    `activated_at` datetime DEFAULT NULL,
    `copied_from_id` int(10) unsigned DEFAULT NULL,
    `copied_from_year` year(4) DEFAULT NULL,
    `structure_count` bigint(21) NOT NULL DEFAULT '0'
);

-- --------------------------------------------------------

--
-- Structure for view `vw_fee_transition_audit` exported as a table
--
DROP TABLE IF EXISTS `vw_fee_transition_audit`;
CREATE TABLE IF NOT EXISTS `vw_fee_transition_audit`(
    `student_id` int(10) unsigned NOT NULL,
    `admission_no` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
    `student_name` varchar(101) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
    `from_academic_year` int(11) NOT NULL,
    `to_academic_year` int(11) NOT NULL,
    `transition_type` varchar(34) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `balance_action` enum('fresh_bill','add_to_current','deduct_from_current','manual_adjustment') COLLATE utf8mb4_general_ci NOT NULL,
    `amount_transferred` decimal(12,2) DEFAULT '0.00' COMMENT 'Amount transferred/adjusted',
    `previous_balance` decimal(12,2) DEFAULT '0.00' COMMENT 'Balance before adjustment',
    `new_balance` decimal(12,2) DEFAULT '0.00' COMMENT 'Balance after adjustment',
    `created_at` timestamp NOT NULL DEFAULT 'current_timestamp()',
    `notes` text COLLATE utf8mb4_general_ci DEFAULT NULL
);

-- --------------------------------------------------------

--
-- Structure for view `vw_fee_type_collection` exported as a table
--
DROP TABLE IF EXISTS `vw_fee_type_collection`;
CREATE TABLE IF NOT EXISTS `vw_fee_type_collection`(
    `fee_type` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
    `fee_code` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
    `fee_category` enum('tuition','boarding','activity','infrastructure','other') COLLATE utf8mb4_general_ci NOT NULL,
    `is_mandatory` tinyint(1) NOT NULL DEFAULT '1',
    `total_due` decimal(32,2) DEFAULT NULL,
    `total_collected` decimal(32,2) DEFAULT NULL,
    `total_outstanding` decimal(32,2) DEFAULT NULL,
    `students_affected` bigint(21) NOT NULL DEFAULT '0',
    `collection_rate_percent` decimal(38,2) DEFAULT NULL,
    `students_paid` bigint(21) NOT NULL DEFAULT '0',
    `students_partial` bigint(21) NOT NULL DEFAULT '0',
    `students_pending` bigint(21) NOT NULL DEFAULT '0'
);

-- --------------------------------------------------------

--
-- Structure for view `vw_food_consumption_summary` exported as a table
--
DROP TABLE IF EXISTS `vw_food_consumption_summary`;
CREATE TABLE IF NOT EXISTS `vw_food_consumption_summary`(
    `consumption_date` date NOT NULL,
    `food_item` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `code` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `category` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `unit` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
    `total_quantity_planned` decimal(32,2) DEFAULT NULL,
    `total_quantity_used` decimal(32,2) DEFAULT NULL,
    `total_waste` decimal(32,2) DEFAULT NULL,
    `total_cost_used` decimal(32,2) DEFAULT NULL,
    `consumption_records` bigint(21) NOT NULL DEFAULT '0',
    `recorded_by_first` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `recorded_by_last` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL
);

-- --------------------------------------------------------

--
-- Structure for view `vw_internal_conversations` exported as a table
--
DROP TABLE IF EXISTS `vw_internal_conversations`;
CREATE TABLE IF NOT EXISTS `vw_internal_conversations`(
    `conversation_id` int(10) unsigned NOT NULL DEFAULT '0',
    `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
    `conversation_type` enum('one_on_one','group','department','broadcast') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'one_on_one',
    `created_by` int(10) unsigned NOT NULL,
    `first_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `last_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `total_messages` bigint(21) NOT NULL DEFAULT '0',
    `last_message_date` timestamp DEFAULT 'current_timestamp()',
    `high_priority_messages` bigint(21) NOT NULL DEFAULT '0',
    `participant_count` bigint(21) NOT NULL DEFAULT '0',
    `created_at` timestamp NOT NULL DEFAULT 'current_timestamp()',
    `updated_at` timestamp NOT NULL DEFAULT 'current_timestamp()'
);

-- --------------------------------------------------------

--
-- Structure for view `vw_inventory_health` exported as a table
--
DROP TABLE IF EXISTS `vw_inventory_health`;
CREATE TABLE IF NOT EXISTS `vw_inventory_health`(
    `id` int(10) unsigned NOT NULL DEFAULT '0',
    `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
    `code` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
    `category` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `current_quantity` int(11) NOT NULL DEFAULT '0',
    `minimum_quantity` int(11) NOT NULL DEFAULT '0',
    `reorder_level` int(11) NOT NULL DEFAULT '0',
    `stock_status` varchar(12) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
    `expiry_status` varchar(13) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `expiry_date` date DEFAULT NULL,
    `location` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `unit_cost` decimal(10,2) NOT NULL,
    `inventory_value` decimal(20,2) NOT NULL DEFAULT '0.00',
    `status` enum('active','inactive','out_of_stock') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
    `updated_at` timestamp NOT NULL DEFAULT 'current_timestamp()'
);

-- --------------------------------------------------------

--
-- Structure for view `vw_maintenance_schedule` exported as a table
--
DROP TABLE IF EXISTS `vw_maintenance_schedule`;
CREATE TABLE IF NOT EXISTS `vw_maintenance_schedule`(
    `id` int(10) unsigned NOT NULL DEFAULT '0',
    `serial_number` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `equipment_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `brand` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `model` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `maintenance_type` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `status` enum('pending','scheduled','in_progress','completed','cancelled','overdue') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
    `last_maintenance_date` date DEFAULT NULL,
    `next_maintenance_date` date NOT NULL,
    `days_until_due` int(7) DEFAULT NULL,
    `urgency` varchar(8) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `notes` text COLLATE utf8mb4_general_ci DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT 'current_timestamp()'
);

-- --------------------------------------------------------

--
-- Structure for view `vw_outstanding_by_class` exported as a table
--
DROP TABLE IF EXISTS `vw_outstanding_by_class`;
CREATE TABLE IF NOT EXISTS `vw_outstanding_by_class`(
    `level_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
    `level_code` varchar(10) COLLATE utf8mb4_general_ci NOT NULL,
    `academic_term` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
    `students_with_arrears` bigint(21) NOT NULL DEFAULT '0',
    `total_arrears` decimal(32,2) DEFAULT NULL,
    `average_arrears_per_student` decimal(14,6) DEFAULT NULL,
    `minimum_arrears` decimal(10,2) DEFAULT NULL,
    `maximum_arrears` decimal(10,2) DEFAULT NULL,
    `students_overdue_30_days` bigint(21) NOT NULL DEFAULT '0',
    `students_overdue_60_days` bigint(21) NOT NULL DEFAULT '0'
);

-- --------------------------------------------------------

--
-- Structure for view `vw_parent_payment_activity` exported as a table
--
DROP TABLE IF EXISTS `vw_parent_payment_activity`;
CREATE TABLE IF NOT EXISTS `vw_parent_payment_activity`(
    `parent_id` int(10) unsigned NOT NULL DEFAULT '0',
    `parent_name` varchar(101) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
    `contact_number` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
    `total_payments` bigint(21) NOT NULL DEFAULT '0',
    `total_amount_paid` decimal(32,2) DEFAULT NULL,
    `number_of_children` bigint(21) NOT NULL DEFAULT '0',
    `children` mediumtext COLLATE utf8mb4_general_ci DEFAULT NULL,
    `last_payment_date` datetime DEFAULT NULL,
    `payments_this_year` bigint(21) NOT NULL DEFAULT '0',
    `average_payment` decimal(14,6) DEFAULT NULL
);

-- --------------------------------------------------------

--
-- Structure for view `vw_payment_tracking` exported as a table
--
DROP TABLE IF EXISTS `vw_payment_tracking`;
CREATE TABLE IF NOT EXISTS `vw_payment_tracking`(
    `payment_source` varchar(5) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
    `source_id` int(10) unsigned NOT NULL DEFAULT '0',
    `reference_code` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `student_id` int(10) unsigned DEFAULT NULL,
    `admission_number` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
    `student_name` varchar(101) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
    `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
    `transaction_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    `contact` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `status` varchar(9) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
    `checkout_request_id` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
);

-- --------------------------------------------------------

--
-- Structure for view `vw_pending_fee_structure_reviews` exported as a table
--
DROP TABLE IF EXISTS `vw_pending_fee_structure_reviews`;
CREATE TABLE IF NOT EXISTS `vw_pending_fee_structure_reviews`(
    `academic_year` year(4) NOT NULL,
    `level_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
    `pending_structures` bigint(21) NOT NULL DEFAULT '0',
    `oldest_pending_date` timestamp DEFAULT 'current_timestamp()',
    `days_pending` int(7) DEFAULT NULL,
    `start_date` date NOT NULL,
    `days_until_start` int(7) DEFAULT NULL,
    `priority` varchar(6) COLLATE utf8mb4_general_ci DEFAULT NULL
);

-- --------------------------------------------------------

--
-- Structure for view `vw_pending_requisitions` exported as a table
--
DROP TABLE IF EXISTS `vw_pending_requisitions`;
CREATE TABLE IF NOT EXISTS `vw_pending_requisitions`(
    `id` int(10) unsigned NOT NULL DEFAULT '0',
    `requisition_number` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
    `department` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `status` enum('draft','submitted','pending_approval','approved','rejected','partially_fulfilled','fulfilled','cancelled') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
    `priority` enum('low','normal','high','urgent') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'normal',
    `requisition_date` date NOT NULL,
    `required_date` date NOT NULL,
    `item_count` bigint(21) NOT NULL DEFAULT '0',
    `total_quantity_requested` decimal(32,0) DEFAULT NULL,
    `created_by_first` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `created_by_last` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `approved_by_first` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `approved_by_last` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT 'current_timestamp()'
);

-- --------------------------------------------------------

--
-- Structure for view `vw_pending_sms` exported as a table
--
DROP TABLE IF EXISTS `vw_pending_sms`;
CREATE TABLE IF NOT EXISTS `vw_pending_sms`(
    `sms_id` int(10) unsigned NOT NULL DEFAULT '0',
    `parent_id` int(10) unsigned DEFAULT NULL,
    `parent_name` varchar(101) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `recipient_phone` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
    `message_body` text COLLATE utf8mb4_general_ci NOT NULL,
    `sms_type` enum('academic','fees','attendance','event','emergency','general','report_card') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'general',
    `status` enum('pending','queued','sent','delivered','failed') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
    `template_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `sent_by_first` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `sent_by_last` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT 'current_timestamp()',
    `sent_at` datetime DEFAULT NULL,
    `delivered_at` datetime DEFAULT NULL,
    `hours_pending` bigint(21) DEFAULT NULL
);

-- --------------------------------------------------------

--
-- Structure for view `vw_requisition_fulfillment` exported as a table
--
DROP TABLE IF EXISTS `vw_requisition_fulfillment`;
CREATE TABLE IF NOT EXISTS `vw_requisition_fulfillment`(
    `id` int(10) unsigned NOT NULL DEFAULT '0',
    `requisition_number` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
    `department` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `item_id` int(10) unsigned DEFAULT '0',
    `item_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `requested_quantity` int(11) DEFAULT NULL,
    `unit` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `approved_quantity` int(11) DEFAULT NULL,
    `fulfilled_quantity` int(11) DEFAULT '0',
    `pending_quantity` bigint(12) DEFAULT NULL,
    `unit_cost` decimal(10,2) DEFAULT NULL,
    `total_cost` decimal(20,2) DEFAULT NULL,
    `status` enum('draft','submitted','pending_approval','approved','rejected','partially_fulfilled','fulfilled','cancelled') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
    `priority` enum('low','normal','high','urgent') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'normal',
    `required_date` date NOT NULL,
    `days_remaining` int(7) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT 'current_timestamp()'
);

-- --------------------------------------------------------

--
-- Structure for view `vw_sent_emails` exported as a table
--
DROP TABLE IF EXISTS `vw_sent_emails`;
CREATE TABLE IF NOT EXISTS `vw_sent_emails`(
    `email_id` int(10) unsigned NOT NULL DEFAULT '0',
    `institution_id` int(10) unsigned DEFAULT NULL,
    `institution_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `contact_person_name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `recipient_email` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
    `subject` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
    `email_type` enum('inquiry','report','application','information','request','other') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'information',
    `status` enum('draft','queued','sent','delivered','failed','bounced') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
    `attempts` bigint(21) NOT NULL DEFAULT '0',
    `last_attempt` timestamp DEFAULT 'current_timestamp()',
    `sent_by_first` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `sent_by_last` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT 'current_timestamp()',
    `delivery_status_text` varchar(22) COLLATE utf8mb4_general_ci NOT NULL DEFAULT ''
);

-- --------------------------------------------------------

--
-- Structure for view `vw_sponsored_students_status` exported as a table
--
DROP TABLE IF EXISTS `vw_sponsored_students_status`;
CREATE TABLE IF NOT EXISTS `vw_sponsored_students_status`(
    `id` int(10) unsigned NOT NULL DEFAULT '0',
    `admission_no` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
    `student_name` varchar(101) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
    `student_type` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `class_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `is_sponsored` tinyint(1) DEFAULT '0' COMMENT 'Flag indicating if student is sponsored',
    `sponsor_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Name of the sponsor/sponsoring organization',
    `sponsor_type` enum('partial','full','conditional') COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'Type of sponsorship: partial (pays some fees), full (pays all fees), conditional (pays certain fee types only)',
    `sponsor_waiver_percentage` decimal(5,2) DEFAULT '0.00' COMMENT 'Percentage of fees sponsored (0-100)',
    `total_fees_due` decimal(32,2) DEFAULT NULL,
    `total_paid` decimal(32,2) DEFAULT NULL,
    `current_balance` decimal(32,2) DEFAULT NULL,
    `total_waived` decimal(34,2) DEFAULT NULL
);

-- --------------------------------------------------------

--
-- Structure for view `vw_staff_assignments_detailed` exported as a table
--
DROP TABLE IF EXISTS `vw_staff_assignments_detailed`;
CREATE TABLE IF NOT EXISTS `vw_staff_assignments_detailed`(
    `id` int(10) unsigned DEFAULT '0',
    `staff_id` int(10) unsigned DEFAULT NULL,
    `class_stream_id` int(10) unsigned DEFAULT NULL,
    `class_id` int(10) unsigned DEFAULT NULL,
    `stream_id` int(10) unsigned DEFAULT NULL,
    `academic_year_id` int(10) unsigned DEFAULT NULL,
    `role` enum('class_teacher','subject_teacher','assistant_teacher','head_of_department') COLLATE utf8mb4_general_ci DEFAULT 'subject_teacher',
    `subject_id` int(10) unsigned DEFAULT NULL COMMENT 'Learning area/subject taught',
    `start_date` date DEFAULT NULL,
    `end_date` date DEFAULT NULL,
    `status` enum('active','completed','transferred','terminated') COLLATE utf8mb4_general_ci DEFAULT 'active',
    `notes` text COLLATE utf8mb4_general_ci DEFAULT NULL,
    `created_at` timestamp DEFAULT 'current_timestamp()',
    `created_by` int(10) unsigned DEFAULT NULL,
    `updated_at` timestamp DEFAULT 'current_timestamp()',
    `staff_no` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `staff_name` varchar(101) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `stream_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `class_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `subject_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `academic_year` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Academic Year 2024/2025',
    `total_assignments` bigint(21) NOT NULL DEFAULT '0'
);

-- --------------------------------------------------------

--
-- Structure for view `vw_staff_leave_balance` exported as a table
--
DROP TABLE IF EXISTS `vw_staff_leave_balance`;
CREATE TABLE IF NOT EXISTS `vw_staff_leave_balance`(
    `staff_id` int(10) unsigned NOT NULL DEFAULT '0',
    `staff_no` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
    `staff_name` varchar(101) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
    `leave_type` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
    `leave_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
    `annual_entitlement` int(11) DEFAULT NULL COMMENT 'Annual entitlement (NULL = unlimited)',
    `days_taken_this_year` decimal(32,0) DEFAULT NULL,
    `days_remaining` decimal(33,0) DEFAULT NULL
);

-- --------------------------------------------------------

--
-- Structure for view `vw_staff_leave_balances` exported as a table
--
DROP TABLE IF EXISTS `vw_staff_leave_balances`;
CREATE TABLE IF NOT EXISTS `vw_staff_leave_balances`(
    `staff_id` int(10) unsigned NOT NULL DEFAULT '0',
    `staff_no` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
    `staff_name` varchar(101) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
    `leave_type_id` int(10) unsigned NOT NULL DEFAULT '0',
    `leave_type_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
    `entitled_days` int(11) DEFAULT NULL COMMENT 'Annual entitlement (NULL = unlimited)',
    `used_days` decimal(32,0) DEFAULT NULL,
    `pending_days` decimal(32,0) DEFAULT NULL,
    `available_days` decimal(33,0) DEFAULT NULL
);

-- --------------------------------------------------------

--
-- Structure for view `vw_staff_loan_details` exported as a table
--
DROP TABLE IF EXISTS `vw_staff_loan_details`;
CREATE TABLE IF NOT EXISTS `vw_staff_loan_details`(
    `loan_id` int(10) unsigned NOT NULL DEFAULT '0',
    `staff_id` int(10) unsigned NOT NULL,
    `staff_name` varchar(101) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
    `staff_number` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
    `loan_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
    `principal_amount` decimal(12,2) NOT NULL,
    `loan_date` date NOT NULL,
    `agreed_monthly_deduction` decimal(10,2) NOT NULL,
    `balance_remaining` decimal(12,2) NOT NULL,
    `total_paid` decimal(13,2) NOT NULL DEFAULT '0.00',
    `payment_progress_percent` decimal(19,2) DEFAULT NULL,
    `months_remaining` bigint(14) DEFAULT NULL,
    `status` enum('active','paid_off','defaulted','suspended') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
    `status_description` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `loan_created_at` timestamp NOT NULL DEFAULT 'current_timestamp()',
    `last_updated` timestamp NOT NULL DEFAULT 'current_timestamp()',
    `payments_made_count` bigint(21) DEFAULT NULL,
    `total_deducted` decimal(34,2) DEFAULT NULL,
    `expected_completion_date` date DEFAULT NULL
);

-- --------------------------------------------------------

--
-- Structure for view `vw_staff_onboarding_progress` exported as a table
--
DROP TABLE IF EXISTS `vw_staff_onboarding_progress`;
CREATE TABLE IF NOT EXISTS `vw_staff_onboarding_progress`(
    `onboarding_id` int(10) unsigned NOT NULL DEFAULT '0',
    `staff_id` int(10) unsigned NOT NULL DEFAULT '0',
    `staff_no` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
    `staff_name` varchar(101) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
    `position` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
    `department` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `status` enum('pending','in_progress','completed','extended','terminated') COLLATE utf8mb4_general_ci DEFAULT 'pending',
    `start_date` date NOT NULL,
    `expected_end_date` date DEFAULT NULL,
    `completion_date` date DEFAULT NULL,
    `mentor_name` varchar(101) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `total_tasks` bigint(21) NOT NULL DEFAULT '0',
    `completed_tasks` decimal(22,0) DEFAULT NULL,
    `inprogress_tasks` decimal(22,0) DEFAULT NULL,
    `pending_tasks` decimal(22,0) DEFAULT NULL,
    `skipped_tasks` decimal(22,0) DEFAULT NULL,
    `overdue_tasks` decimal(22,0) DEFAULT NULL,
    `progress_percent` int(11) NOT NULL DEFAULT '0'
);

-- --------------------------------------------------------

--
-- Structure for view `vw_staff_payroll_summary` exported as a table
--
DROP TABLE IF EXISTS `vw_staff_payroll_summary`;
CREATE TABLE IF NOT EXISTS `vw_staff_payroll_summary`(
    `payslip_id` int(10) unsigned NOT NULL DEFAULT '0',
    `staff_id` int(10) unsigned NOT NULL,
    `staff_name` varchar(101) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
    `staff_number` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
    `payroll_month` int(11) NOT NULL,
    `payroll_year` int(11) NOT NULL,
    `period_display` varchar(69) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `basic_salary` decimal(12,2) NOT NULL,
    `allowances_total` decimal(12,2) NOT NULL DEFAULT '0.00',
    `gross_salary` decimal(12,2) NOT NULL,
    `paye_tax` decimal(12,2) NOT NULL DEFAULT '0.00',
    `nssf_contribution` decimal(12,2) NOT NULL DEFAULT '0.00',
    `nhif_contribution` decimal(12,2) NOT NULL DEFAULT '0.00',
    `loan_deduction` decimal(12,2) NOT NULL DEFAULT '0.00',
    `other_deductions_total` decimal(12,2) NOT NULL DEFAULT '0.00',
    `total_deductions` decimal(16,2) NOT NULL DEFAULT '0.00',
    `net_salary` decimal(12,2) NOT NULL,
    `payment_method` enum('bank','cash','check','mobile_money') COLLATE utf8mb4_general_ci DEFAULT 'bank',
    `payment_date` date DEFAULT NULL,
    `payslip_status` enum('draft','approved','paid','cancelled') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
    `approved_by_name` varchar(101) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `notes` text COLLATE utf8mb4_general_ci DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT 'current_timestamp()',
    `updated_at` timestamp NOT NULL DEFAULT 'current_timestamp()',
    `ytd_gross` decimal(34,2) DEFAULT NULL,
    `ytd_paye` decimal(34,2) DEFAULT NULL,
    `ytd_nssf` decimal(34,2) DEFAULT NULL,
    `ytd_nhif` decimal(34,2) DEFAULT NULL,
    `ytd_net` decimal(34,2) DEFAULT NULL
);

-- --------------------------------------------------------

--
-- Structure for view `vw_staff_performance_summary` exported as a table
--
DROP TABLE IF EXISTS `vw_staff_performance_summary`;
CREATE TABLE IF NOT EXISTS `vw_staff_performance_summary`(
    `review_id` int(10) unsigned NOT NULL DEFAULT '0',
    `staff_id` int(10) unsigned NOT NULL DEFAULT '0',
    `staff_no` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
    `staff_name` varchar(101) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
    `position` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
    `department` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `academic_year` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Academic Year 2024/2025',
    `review_period` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `review_type` enum('probation','annual','mid_year','special') COLLATE utf8mb4_general_ci DEFAULT 'annual',
    `status` enum('draft','submitted','approved','completed') COLLATE utf8mb4_general_ci DEFAULT 'draft',
    `overall_score` decimal(5,2) DEFAULT NULL COMMENT 'Percentage score',
    `performance_grade` char(1) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `total_kpis` bigint(21) NOT NULL DEFAULT '0',
    `completed_kpis` decimal(22,0) DEFAULT NULL,
    `completion_percent` decimal(26,0) DEFAULT NULL,
    `review_date` date NOT NULL,
    `completion_date` datetime DEFAULT NULL
);

-- --------------------------------------------------------

--
-- Structure for view `vw_staff_workload` exported as a table
--
DROP TABLE IF EXISTS `vw_staff_workload`;
CREATE TABLE IF NOT EXISTS `vw_staff_workload`(
    `staff_id` int(10) unsigned NOT NULL DEFAULT '0',
    `staff_no` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
    `staff_name` varchar(101) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
    `category_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `academic_year` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Academic Year 2024/2025',
    `classes_assigned` bigint(21) NOT NULL DEFAULT '0',
    `class_teacher_count` bigint(21) NOT NULL DEFAULT '0',
    `subject_teacher_count` bigint(21) NOT NULL DEFAULT '0',
    `classes` mediumtext COLLATE utf8mb4_general_ci DEFAULT NULL
);

-- --------------------------------------------------------

--
-- Structure for view `vw_student_payment_history_multi_year` exported as a table
--
DROP TABLE IF EXISTS `vw_student_payment_history_multi_year`;
CREATE TABLE IF NOT EXISTS `vw_student_payment_history_multi_year`(
    `student_id` int(10) unsigned NOT NULL DEFAULT '0',
    `first_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
    `last_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
    `admission_no` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
    `academic_year` year(4) DEFAULT NULL,
    `term_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `term_number` tinyint(4) DEFAULT NULL,
    `payment_count` bigint(21) NOT NULL DEFAULT '0',
    `total_paid` decimal(32,2) DEFAULT NULL,
    `first_payment_date` datetime DEFAULT NULL,
    `last_payment_date` datetime DEFAULT NULL,
    `cash_total` decimal(32,2) DEFAULT NULL,
    `mpesa_total` decimal(32,2) DEFAULT NULL,
    `bank_total` decimal(32,2) DEFAULT NULL,
    `amount_due` decimal(10,2) DEFAULT NULL,
    `balance` decimal(10,2) DEFAULT NULL,
    `fee_status` enum('pending','partial','paid','arrears') COLLATE utf8mb4_general_ci DEFAULT 'pending'
);

-- --------------------------------------------------------

--
-- Structure for view `vw_student_payment_status` exported as a table
--
DROP TABLE IF EXISTS `vw_student_payment_status`;
CREATE TABLE IF NOT EXISTS `vw_student_payment_status`(
    `admission_no` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
    `student_name` varchar(101) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
    `level` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
    `student_type` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
    `academic_term` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `total_fees_due` decimal(32,2) DEFAULT NULL,
    `total_fees_paid` decimal(32,2) DEFAULT NULL,
    `total_fees_waived` decimal(32,2) DEFAULT NULL,
    `balance_outstanding` decimal(32,2) DEFAULT NULL,
    `payment_percentage` decimal(38,2) DEFAULT NULL,
    `payment_status` varchar(7) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `last_payment_date` datetime DEFAULT NULL,
    `number_of_payments` bigint(21) NOT NULL DEFAULT '0',
    `waivers_applied` bigint(21) NOT NULL DEFAULT '0',
    `arrears_status` varchar(26) COLLATE utf8mb4_general_ci DEFAULT NULL
);

-- --------------------------------------------------------

--
-- Structure for view `vw_student_payment_status_enhanced` exported as a table
--
DROP TABLE IF EXISTS `vw_student_payment_status_enhanced`;
CREATE TABLE IF NOT EXISTS `vw_student_payment_status_enhanced`(
    `id` int(10) unsigned NOT NULL DEFAULT '0',
    `admission_no` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
    `student_name` varchar(101) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
    `student_type` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `class_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `level_name` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `academic_year` int(4) DEFAULT NULL,
    `term_number` int(4) DEFAULT NULL,
    `total_due` decimal(32,2) DEFAULT NULL,
    `total_paid` decimal(32,2) DEFAULT NULL,
    `total_waived` decimal(32,2) DEFAULT NULL,
    `current_balance` decimal(32,2) DEFAULT NULL,
    `year_balance` decimal(12,2) DEFAULT NULL,
    `term_balance` decimal(12,2) DEFAULT NULL,
    `previous_year_balance` decimal(12,2) DEFAULT NULL,
    `previous_term_balance` decimal(12,2) DEFAULT NULL,
    `payment_status` enum('pending','partial','paid','arrears','waived') COLLATE utf8mb4_general_ci DEFAULT 'pending' COMMENT 'Detailed payment status',
    `is_sponsored` tinyint(1) DEFAULT '0' COMMENT 'Flag indicating if student is sponsored',
    `sponsor_waiver_percentage` decimal(5,2) DEFAULT '0.00' COMMENT 'Percentage of fees sponsored (0-100)'
);

-- --------------------------------------------------------

--
-- Structure for view `vw_unread_announcements` exported as a table
--
DROP TABLE IF EXISTS `vw_unread_announcements`;
CREATE TABLE IF NOT EXISTS `vw_unread_announcements`(
    `announcement_id` int(10) unsigned NOT NULL DEFAULT '0',
    `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
    `content` longtext COLLATE utf8mb4_general_ci NOT NULL,
    `priority` enum('low','normal','high','critical') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'normal',
    `target_audience` enum('all','staff','students','parents','specific') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'all',
    `published_by_first` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `published_by_last` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
    `status` enum('draft','scheduled','published','archived','expired') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
    `created_at` timestamp NOT NULL DEFAULT 'current_timestamp()',
    `updated_at` timestamp NOT NULL DEFAULT 'current_timestamp()',
    `total_views` bigint(21) NOT NULL DEFAULT '0'
);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items` ADD FULLTEXT KEY `ftx_item_name_desc` (`name`,`description`);

--
-- Indexes for table `parents`
--
ALTER TABLE `parents` ADD FULLTEXT KEY `ftx_parent_names` (`first_name`,`last_name`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff` ADD FULLTEXT KEY `ftx_staff_names` (`first_name`,`last_name`,`position`);

--
-- Indexes for table `students`
--
ALTER TABLE `students` ADD FULLTEXT KEY `ftx_student_names` (`first_name`,`last_name`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `academic_years`
--
ALTER TABLE `academic_years`
  ADD CONSTRAINT `academic_years_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `academic_year_archives`
--
ALTER TABLE `academic_year_archives`
  ADD CONSTRAINT `academic_year_archives_ibfk_1` FOREIGN KEY (`closure_initiated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `account_unlock_history`
--
ALTER TABLE `account_unlock_history`
  ADD CONSTRAINT `fk_unlock_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `activities`
--
ALTER TABLE `activities`
  ADD CONSTRAINT `activities_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `activity_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `activity_participants`
--
ALTER TABLE `activity_participants`
  ADD CONSTRAINT `activity_participants_ibfk_1` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `activity_participants_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `activity_resources`
--
ALTER TABLE `activity_resources`
  ADD CONSTRAINT `activity_resources_ibfk_1` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `admission_applications`
--
ALTER TABLE `admission_applications`
  ADD CONSTRAINT `fk_admission_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`);

--
-- Constraints for table `admission_documents`
--
ALTER TABLE `admission_documents`
  ADD CONSTRAINT `fk_admission_doc_app` FOREIGN KEY (`application_id`) REFERENCES `admission_applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_doc_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `alumni`
--
ALTER TABLE `alumni`
  ADD CONSTRAINT `alumni_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alumni_ibfk_2` FOREIGN KEY (`graduated_class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alumni_ibfk_3` FOREIGN KEY (`graduated_stream_id`) REFERENCES `class_streams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alumni_ibfk_4` FOREIGN KEY (`final_enrollment_id`) REFERENCES `class_enrollments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `announcements_bulletin`
--
ALTER TABLE `announcements_bulletin`
  ADD CONSTRAINT `announcements_bulletin_ibfk_1` FOREIGN KEY (`published_by`) REFERENCES `staff` (`id`);

--
-- Constraints for table `announcement_views`
--
ALTER TABLE `announcement_views`
  ADD CONSTRAINT `announcement_views_ibfk_1` FOREIGN KEY (`announcement_id`) REFERENCES `announcements_bulletin` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcement_views_ibfk_2` FOREIGN KEY (`viewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `annual_scores`
--
ALTER TABLE `annual_scores`
  ADD CONSTRAINT `fk_as_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `api_tokens`
--
ALTER TABLE `api_tokens`
  ADD CONSTRAINT `fk_api_token_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessments`
--
ALTER TABLE `assessments`
  ADD CONSTRAINT `assessments_ibfk_outcome` FOREIGN KEY (`learning_outcome_id`) REFERENCES `learning_outcomes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `assessments_ibfk_type` FOREIGN KEY (`assessment_type_id`) REFERENCES `assessment_types` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `assessment_benchmarks`
--
ALTER TABLE `assessment_benchmarks`
  ADD CONSTRAINT `fk_ab_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_ab_grade_level` FOREIGN KEY (`grade_level_id`) REFERENCES `school_levels` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ab_subject` FOREIGN KEY (`subject_id`) REFERENCES `curriculum_units` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_history`
--
ALTER TABLE `assessment_history`
  ADD CONSTRAINT `fk_ah_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ah_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_rubrics`
--
ALTER TABLE `assessment_rubrics`
  ADD CONSTRAINT `fk_ar_tool` FOREIGN KEY (`tool_id`) REFERENCES `assessment_tools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assessment_tools`
--
ALTER TABLE `assessment_tools`
  ADD CONSTRAINT `fk_at_assessment_type` FOREIGN KEY (`assessment_type_id`) REFERENCES `assessment_type_classifications` (`id`),
  ADD CONSTRAINT `fk_at_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_at_learning_area` FOREIGN KEY (`learning_area_id`) REFERENCES `learning_areas` (`id`);

--
-- Constraints for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD CONSTRAINT `audit_trail_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `auth_sessions`
--
ALTER TABLE `auth_sessions`
  ADD CONSTRAINT `auth_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `bank_transactions`
--
ALTER TABLE `bank_transactions`
  ADD CONSTRAINT `bank_transactions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`level_id`) REFERENCES `school_levels` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `classes_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `class_enrollments`
--
ALTER TABLE `class_enrollments`
  ADD CONSTRAINT `class_enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_enrollments_ibfk_2` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_enrollments_ibfk_3` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_enrollments_ibfk_4` FOREIGN KEY (`stream_id`) REFERENCES `class_streams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_enrollments_ibfk_5` FOREIGN KEY (`class_assignment_id`) REFERENCES `class_year_assignments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `class_promotion_queue`
--
ALTER TABLE `class_promotion_queue`
  ADD CONSTRAINT `class_promotion_queue_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `promotion_batches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_promotion_queue_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `class_promotion_queue_ibfk_3` FOREIGN KEY (`stream_id`) REFERENCES `class_streams` (`id`),
  ADD CONSTRAINT `class_promotion_queue_ibfk_4` FOREIGN KEY (`assigned_to_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `class_schedules`
--
ALTER TABLE `class_schedules`
  ADD CONSTRAINT `class_schedules_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_schedules_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `curriculum_units` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `class_schedules_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `class_schedules_ibfk_4` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `class_streams`
--
ALTER TABLE `class_streams`
  ADD CONSTRAINT `class_streams_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `class_streams_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `class_year_assignments`
--
ALTER TABLE `class_year_assignments`
  ADD CONSTRAINT `class_year_assignments_ibfk_1` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_year_assignments_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_year_assignments_ibfk_3` FOREIGN KEY (`stream_id`) REFERENCES `class_streams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_year_assignments_ibfk_4` FOREIGN KEY (`teacher_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `communications`
--
ALTER TABLE `communications`
  ADD CONSTRAINT `communications_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_comm_template` FOREIGN KEY (`template_id`) REFERENCES `message_templates` (`id`);

--
-- Constraints for table `communication_attachments`
--
ALTER TABLE `communication_attachments`
  ADD CONSTRAINT `communication_attachments_ibfk_1` FOREIGN KEY (`communication_id`) REFERENCES `communications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `communication_groups`
--
ALTER TABLE `communication_groups`
  ADD CONSTRAINT `communication_groups_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `communication_logs`
--
ALTER TABLE `communication_logs`
  ADD CONSTRAINT `communication_logs_ibfk_1` FOREIGN KEY (`communication_id`) REFERENCES `communications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `communication_logs_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `communication_recipients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `communication_recipients`
--
ALTER TABLE `communication_recipients`
  ADD CONSTRAINT `communication_recipients_ibfk_1` FOREIGN KEY (`communication_id`) REFERENCES `communications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `communication_recipients_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `communication_templates`
--
ALTER TABLE `communication_templates`
  ADD CONSTRAINT `communication_templates_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `staff` (`id`);

--
-- Constraints for table `conduct_tracking`
--
ALTER TABLE `conduct_tracking`
  ADD CONSTRAINT `fk_ct_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_ct_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ct_term` FOREIGN KEY (`term_id`) REFERENCES `academic_terms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `conversation_participants`
--
ALTER TABLE `conversation_participants`
  ADD CONSTRAINT `conversation_participants_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `internal_conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversation_participants_ibfk_2` FOREIGN KEY (`participant_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `csl_activities`
--
ALTER TABLE `csl_activities`
  ADD CONSTRAINT `fk_csl_organized_by` FOREIGN KEY (`organized_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `curriculum_units`
--
ALTER TABLE `curriculum_units`
  ADD CONSTRAINT `curriculum_units_ibfk_1` FOREIGN KEY (`learning_area_id`) REFERENCES `learning_areas` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `daily_meal_allocations`
--
ALTER TABLE `daily_meal_allocations`
  ADD CONSTRAINT `daily_meal_allocations_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `daily_meal_allocations_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `staff` (`id`);

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `fk_department_head` FOREIGN KEY (`head_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `equipment_maintenance`
--
ALTER TABLE `equipment_maintenance`
  ADD CONSTRAINT `equipment_maintenance_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `item_serials` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `equipment_maintenance_ibfk_2` FOREIGN KEY (`maintenance_type_id`) REFERENCES `equipment_maintenance_types` (`id`);

--
-- Constraints for table `exam_schedules`
--
ALTER TABLE `exam_schedules`
  ADD CONSTRAINT `exam_schedules_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_schedules_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `curriculum_units` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_schedules_ibfk_3` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `exam_schedules_ibfk_4` FOREIGN KEY (`invigilator_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `external_emails`
--
ALTER TABLE `external_emails`
  ADD CONSTRAINT `external_emails_ibfk_1` FOREIGN KEY (`institution_id`) REFERENCES `external_institutions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `external_emails_ibfk_2` FOREIGN KEY (`sent_by`) REFERENCES `staff` (`id`);

--
-- Constraints for table `fee_structures_detailed`
--
ALTER TABLE `fee_structures_detailed`
  ADD CONSTRAINT `fk_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_copied_from` FOREIGN KEY (`copied_from_id`) REFERENCES `fee_structures_detailed` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `fee_structure_rollover_log`
--
ALTER TABLE `fee_structure_rollover_log`
  ADD CONSTRAINT `fk_rollover_executor` FOREIGN KEY (`executed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `fee_transition_history`
--
ALTER TABLE `fee_transition_history`
  ADD CONSTRAINT `fk_fth_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `financial_periods`
--
ALTER TABLE `financial_periods`
  ADD CONSTRAINT `financial_periods_ibfk_1` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `financial_transactions`
--
ALTER TABLE `financial_transactions`
  ADD CONSTRAINT `financial_transactions_ibfk_1` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `financial_transactions_ibfk_2` FOREIGN KEY (`reconciled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `food_consumption_records`
--
ALTER TABLE `food_consumption_records`
  ADD CONSTRAINT `food_consumption_records_ibfk_1` FOREIGN KEY (`meal_plan_id`) REFERENCES `meal_plans` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `food_consumption_records_ibfk_2` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `food_consumption_records_ibfk_3` FOREIGN KEY (`recorded_by`) REFERENCES `staff` (`id`);

--
-- Constraints for table `grade_rules`
--
ALTER TABLE `grade_rules`
  ADD CONSTRAINT `fk_grade_rules_scale` FOREIGN KEY (`scale_id`) REFERENCES `grading_scales` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `group_members`
--
ALTER TABLE `group_members`
  ADD CONSTRAINT `group_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `communication_groups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_members_ibfk_3` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `ieps`
--
ALTER TABLE `ieps`
  ADD CONSTRAINT `fk_iep_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_iep_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_iep_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `internal_conversations`
--
ALTER TABLE `internal_conversations`
  ADD CONSTRAINT `internal_conversations_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `staff` (`id`),
  ADD CONSTRAINT `internal_conversations_ibfk_2` FOREIGN KEY (`last_message_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `internal_messages`
--
ALTER TABLE `internal_messages`
  ADD CONSTRAINT `internal_messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `staff` (`id`);

--
-- Constraints for table `inventory_adjustments`
--
ALTER TABLE `inventory_adjustments`
  ADD CONSTRAINT `inventory_adjustments_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `inventory_adjustments_ibfk_2` FOREIGN KEY (`adjusted_by`) REFERENCES `staff` (`id`),
  ADD CONSTRAINT `inventory_adjustments_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `staff` (`id`);

--
-- Constraints for table `inventory_allocations`
--
ALTER TABLE `inventory_allocations`
  ADD CONSTRAINT `inventory_allocations_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `inventory_allocations_ibfk_2` FOREIGN KEY (`allocated_to_department_id`) REFERENCES `inventory_departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_allocations_ibfk_3` FOREIGN KEY (`allocated_to_class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_allocations_ibfk_4` FOREIGN KEY (`allocated_by`) REFERENCES `staff` (`id`),
  ADD CONSTRAINT `inventory_allocations_ibfk_5` FOREIGN KEY (`issued_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_categories`
--
ALTER TABLE `inventory_categories`
  ADD CONSTRAINT `inventory_categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `inventory_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `inventory_counts`
--
ALTER TABLE `inventory_counts`
  ADD CONSTRAINT `inventory_counts_ibfk_1` FOREIGN KEY (`counted_by`) REFERENCES `staff` (`id`),
  ADD CONSTRAINT `inventory_counts_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `staff` (`id`);

--
-- Constraints for table `inventory_count_items`
--
ALTER TABLE `inventory_count_items`
  ADD CONSTRAINT `inventory_count_items_ibfk_1` FOREIGN KEY (`count_id`) REFERENCES `inventory_counts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_count_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`);

--
-- Constraints for table `inventory_departments`
--
ALTER TABLE `inventory_departments`
  ADD CONSTRAINT `inventory_departments_ibfk_1` FOREIGN KEY (`department_head_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `inventory_categories` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `inventory_requisitions`
--
ALTER TABLE `inventory_requisitions`
  ADD CONSTRAINT `inventory_requisitions_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `inventory_departments` (`id`),
  ADD CONSTRAINT `inventory_requisitions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `staff` (`id`),
  ADD CONSTRAINT `inventory_requisitions_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `inventory_transactions_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_transactions_ibfk_2` FOREIGN KEY (`batch_id`) REFERENCES `item_batches` (`id`),
  ADD CONSTRAINT `inventory_transactions_ibfk_3` FOREIGN KEY (`serial_id`) REFERENCES `item_serials` (`id`);

--
-- Constraints for table `item_batches`
--
ALTER TABLE `item_batches`
  ADD CONSTRAINT `item_batches_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `item_batches_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `item_serials`
--
ALTER TABLE `item_serials`
  ADD CONSTRAINT `item_serials_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `item_serials_ibfk_2` FOREIGN KEY (`batch_id`) REFERENCES `item_batches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `kpi_achievements`
--
ALTER TABLE `kpi_achievements`
  ADD CONSTRAINT `fk_kpi_achievement_def` FOREIGN KEY (`kpi_definition_id`) REFERENCES `kpi_definitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_kpi_achievement_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `kpi_definitions`
--
ALTER TABLE `kpi_definitions`
  ADD CONSTRAINT `fk_kpi_category` FOREIGN KEY (`staff_category_id`) REFERENCES `staff_categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `kpi_targets`
--
ALTER TABLE `kpi_targets`
  ADD CONSTRAINT `fk_kpi_target_def` FOREIGN KEY (`kpi_definition_id`) REFERENCES `kpi_definitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_kpi_target_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `learner_competencies`
--
ALTER TABLE `learner_competencies`
  ADD CONSTRAINT `fk_lc_assessed_by` FOREIGN KEY (`assessed_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_lc_competency` FOREIGN KEY (`competency_id`) REFERENCES `core_competencies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lc_performance_level` FOREIGN KEY (`performance_level_id`) REFERENCES `performance_levels_cbc` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_lc_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lc_term` FOREIGN KEY (`term_id`) REFERENCES `academic_terms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `learner_csl_participation`
--
ALTER TABLE `learner_csl_participation`
  ADD CONSTRAINT `fk_lcp_activity` FOREIGN KEY (`csl_activity_id`) REFERENCES `csl_activities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lcp_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `learner_pci_awareness`
--
ALTER TABLE `learner_pci_awareness`
  ADD CONSTRAINT `fk_lpa_assessed_by` FOREIGN KEY (`assessed_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_lpa_pci` FOREIGN KEY (`pci_id`) REFERENCES `pcis` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lpa_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lpa_term` FOREIGN KEY (`term_id`) REFERENCES `academic_terms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `learner_values_acquisition`
--
ALTER TABLE `learner_values_acquisition`
  ADD CONSTRAINT `fk_lva_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_lva_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lva_term` FOREIGN KEY (`term_id`) REFERENCES `academic_terms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lva_value` FOREIGN KEY (`value_id`) REFERENCES `core_values` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lesson_plans`
--
ALTER TABLE `lesson_plans`
  ADD CONSTRAINT `lesson_plans_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `staff` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `lesson_plans_ibfk_2` FOREIGN KEY (`learning_area_id`) REFERENCES `learning_areas` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `lesson_plans_ibfk_3` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `lesson_plans_ibfk_4` FOREIGN KEY (`unit_id`) REFERENCES `curriculum_units` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `lesson_plans_ibfk_5` FOREIGN KEY (`approved_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `maintenance_logs`
--
ALTER TABLE `maintenance_logs`
  ADD CONSTRAINT `maintenance_logs_ibfk_1` FOREIGN KEY (`maintenance_schedule_id`) REFERENCES `equipment_maintenance` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maintenance_logs_ibfk_2` FOREIGN KEY (`equipment_id`) REFERENCES `item_serials` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maintenance_logs_ibfk_3` FOREIGN KEY (`maintenance_staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `meal_plans`
--
ALTER TABLE `meal_plans`
  ADD CONSTRAINT `meal_plans_ibfk_1` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `meal_plans_ibfk_2` FOREIGN KEY (`prepared_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `meal_plans_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `staff` (`id`);

--
-- Constraints for table `menu_item_ingredients`
--
ALTER TABLE `menu_item_ingredients`
  ADD CONSTRAINT `menu_item_ingredients_ibfk_1` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `menu_item_ingredients_ibfk_2` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`);

--
-- Constraints for table `message_read_status`
--
ALTER TABLE `message_read_status`
  ADD CONSTRAINT `message_read_status_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `internal_messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `message_read_status_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `message_templates`
--
ALTER TABLE `message_templates`
  ADD CONSTRAINT `fk_template_category` FOREIGN KEY (`category_id`) REFERENCES `template_categories` (`id`),
  ADD CONSTRAINT `message_templates_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `mpesa_transactions`
--
ALTER TABLE `mpesa_transactions`
  ADD CONSTRAINT `mpesa_transactions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `onboarding_tasks`
--
ALTER TABLE `onboarding_tasks`
  ADD CONSTRAINT `fk_task_assignee` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_task_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_task_onboarding` FOREIGN KEY (`onboarding_id`) REFERENCES `staff_onboarding` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `parent_communication_preferences`
--
ALTER TABLE `parent_communication_preferences`
  ADD CONSTRAINT `parent_communication_preferences_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_allocations`
--
ALTER TABLE `payment_allocations`
  ADD CONSTRAINT `payment_allocations_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `school_transactions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payment_allocations_ibfk_2` FOREIGN KEY (`fee_structure_id`) REFERENCES `fee_structures` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_reconciliations`
--
ALTER TABLE `payment_reconciliations`
  ADD CONSTRAINT `payment_reconciliations_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `school_transactions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payment_reconciliations_ibfk_2` FOREIGN KEY (`reconciled_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD CONSTRAINT `fk_payment_fee_structure` FOREIGN KEY (`fee_structure_detail_id`) REFERENCES `fee_structures_detailed` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_payment_term` FOREIGN KEY (`term_id`) REFERENCES `academic_terms` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payslips`
--
ALTER TABLE `payslips`
  ADD CONSTRAINT `fk_payslip_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `performance_ratings`
--
ALTER TABLE `performance_ratings`
  ADD CONSTRAINT `fk_perf_rating_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `performance_review_kpis`
--
ALTER TABLE `performance_review_kpis`
  ADD CONSTRAINT `fk_perf_kpi_review` FOREIGN KEY (`review_id`) REFERENCES `staff_performance_reviews` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_perf_kpi_template` FOREIGN KEY (`kpi_template_id`) REFERENCES `staff_kpi_templates` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `permission_delegations`
--
ALTER TABLE `permission_delegations`
  ADD CONSTRAINT `fk_deleg_from` FOREIGN KEY (`delegated_from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_deleg_to` FOREIGN KEY (`delegated_to_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `portfolios`
--
ALTER TABLE `portfolios`
  ADD CONSTRAINT `fk_port_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `portfolio_artifacts`
--
ALTER TABLE `portfolio_artifacts`
  ADD CONSTRAINT `fk_pa_competency` FOREIGN KEY (`competency_id`) REFERENCES `core_competencies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pa_portfolio` FOREIGN KEY (`portfolio_id`) REFERENCES `portfolios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pa_value` FOREIGN KEY (`value_id`) REFERENCES `core_values` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `promotion_batches`
--
ALTER TABLE `promotion_batches`
  ADD CONSTRAINT `promotion_batches_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `staff` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `purchase_orders_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `record_permissions`
--
ALTER TABLE `record_permissions`
  ADD CONSTRAINT `fk_rec_perm_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rec_perm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `requisition_items`
--
ALTER TABLE `requisition_items`
  ADD CONSTRAINT `requisition_items_ibfk_1` FOREIGN KEY (`requisition_id`) REFERENCES `inventory_requisitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `requisition_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`);

--
-- Constraints for table `role_form_permissions`
--
ALTER TABLE `role_form_permissions`
  ADD CONSTRAINT `fk_role_form_perm_form` FOREIGN KEY (`form_permission_id`) REFERENCES `form_permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_role_form_perm_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `route_schedules`
--
ALTER TABLE `route_schedules`
  ADD CONSTRAINT `route_schedules_ibfk_1` FOREIGN KEY (`route_id`) REFERENCES `transport_routes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `route_stops`
--
ALTER TABLE `route_stops`
  ADD CONSTRAINT `route_stops_ibfk_1` FOREIGN KEY (`route_id`) REFERENCES `transport_routes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `schedule_changes`
--
ALTER TABLE `schedule_changes`
  ADD CONSTRAINT `schedule_changes_ibfk_1` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `school_configuration`
--
ALTER TABLE `school_configuration`
  ADD CONSTRAINT `fk_school_config_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `school_transactions`
--
ALTER TABLE `school_transactions`
  ADD CONSTRAINT `school_transactions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `school_transactions_ibfk_2` FOREIGN KEY (`financial_period_id`) REFERENCES `financial_periods` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sms_communications`
--
ALTER TABLE `sms_communications`
  ADD CONSTRAINT `sms_communications_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sms_communications_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sms_communications_ibfk_3` FOREIGN KEY (`sent_by`) REFERENCES `staff` (`id`);

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `fk_staff_category` FOREIGN KEY (`staff_category_id`) REFERENCES `staff_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_staff_type` FOREIGN KEY (`staff_type_id`) REFERENCES `staff_types` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `staff_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `staff_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `staff_allowances`
--
ALTER TABLE `staff_allowances`
  ADD CONSTRAINT `staff_allowances_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  ADD CONSTRAINT `staff_attendance_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `staff_attendance_ibfk_2` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `staff_categories`
--
ALTER TABLE `staff_categories`
  ADD CONSTRAINT `fk_staff_category_type` FOREIGN KEY (`staff_type_id`) REFERENCES `staff_types` (`id`);

--
-- Constraints for table `staff_class_assignments`
--
ALTER TABLE `staff_class_assignments`
  ADD CONSTRAINT `fk_assignment_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assignment_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_assignment_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assignment_stream` FOREIGN KEY (`stream_id`) REFERENCES `class_streams` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_assignment_subject` FOREIGN KEY (`subject_id`) REFERENCES `learning_areas` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_assignment_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_contracts`
--
ALTER TABLE `staff_contracts`
  ADD CONSTRAINT `fk_contract_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_contract_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_deductions`
--
ALTER TABLE `staff_deductions`
  ADD CONSTRAINT `staff_deductions_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_experience`
--
ALTER TABLE `staff_experience`
  ADD CONSTRAINT `staff_experience_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `staff_kpi_templates`
--
ALTER TABLE `staff_kpi_templates`
  ADD CONSTRAINT `fk_kpi_template_category` FOREIGN KEY (`staff_category_id`) REFERENCES `staff_categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_leaves`
--
ALTER TABLE `staff_leaves`
  ADD CONSTRAINT `fk_leave_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_leave_relief` FOREIGN KEY (`relief_staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_leave_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_leave_type` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`);

--
-- Constraints for table `staff_loans`
--
ALTER TABLE `staff_loans`
  ADD CONSTRAINT `fk_loan_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_onboarding`
--
ALTER TABLE `staff_onboarding`
  ADD CONSTRAINT `fk_onboarding_mentor` FOREIGN KEY (`mentor_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_onboarding_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_payroll`
--
ALTER TABLE `staff_payroll`
  ADD CONSTRAINT `staff_payroll_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_performance_reviews`
--
ALTER TABLE `staff_performance_reviews`
  ADD CONSTRAINT `fk_perf_review_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_perf_review_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_perf_review_term` FOREIGN KEY (`term_id`) REFERENCES `academic_terms` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_perf_review_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_qualifications`
--
ALTER TABLE `staff_qualifications`
  ADD CONSTRAINT `staff_qualifications_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `storage_locations`
--
ALTER TABLE `storage_locations`
  ADD CONSTRAINT `storage_locations_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`stream_id`) REFERENCES `class_streams` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `student_activities`
--
ALTER TABLE `student_activities`
  ADD CONSTRAINT `student_activities_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_activities_ibfk_2` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_attendance`
--
ALTER TABLE `student_attendance`
  ADD CONSTRAINT `student_attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_attendance_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `student_attendance_ibfk_3` FOREIGN KEY (`term_id`) REFERENCES `academic_terms` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `student_attendance_ibfk_4` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `student_discipline`
--
ALTER TABLE `student_discipline`
  ADD CONSTRAINT `student_discipline_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_discipline_ibfk_2` FOREIGN KEY (`resolved_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `student_fee_balances`
--
ALTER TABLE `student_fee_balances`
  ADD CONSTRAINT `student_fee_balances_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_fee_balances_ibfk_2` FOREIGN KEY (`fee_structure_id`) REFERENCES `fee_structures` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_fee_balances_ibfk_3` FOREIGN KEY (`academic_term_id`) REFERENCES `academic_terms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_fee_carryover`
--
ALTER TABLE `student_fee_carryover`
  ADD CONSTRAINT `fk_sfc_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_parents`
--
ALTER TABLE `student_parents`
  ADD CONSTRAINT `student_parents_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_parents_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_promotions`
--
ALTER TABLE `student_promotions`
  ADD CONSTRAINT `fk_sp_from_enrollment` FOREIGN KEY (`from_enrollment_id`) REFERENCES `class_enrollments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `student_promotions_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `promotion_batches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_promotions_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_promotions_ibfk_3` FOREIGN KEY (`current_class_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `student_promotions_ibfk_4` FOREIGN KEY (`current_stream_id`) REFERENCES `class_streams` (`id`),
  ADD CONSTRAINT `student_promotions_ibfk_5` FOREIGN KEY (`promoted_to_class_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `student_promotions_ibfk_6` FOREIGN KEY (`promoted_to_stream_id`) REFERENCES `class_streams` (`id`),
  ADD CONSTRAINT `student_promotions_ibfk_7` FOREIGN KEY (`from_term_id`) REFERENCES `academic_terms` (`id`),
  ADD CONSTRAINT `student_promotions_ibfk_8` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `student_registrations`
--
ALTER TABLE `student_registrations`
  ADD CONSTRAINT `student_registrations_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_registrations_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_registrations_ibfk_3` FOREIGN KEY (`term_id`) REFERENCES `academic_terms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_suspensions`
--
ALTER TABLE `student_suspensions`
  ADD CONSTRAINT `student_suspensions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_suspensions_ibfk_2` FOREIGN KEY (`suspended_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `tax_withholding_history`
--
ALTER TABLE `tax_withholding_history`
  ADD CONSTRAINT `fk_tax_withholding_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `term_consolidations`
--
ALTER TABLE `term_consolidations`
  ADD CONSTRAINT `fk_tc_consolidated_by` FOREIGN KEY (`consolidated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tc_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tc_term` FOREIGN KEY (`term_id`) REFERENCES `academic_terms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `term_subject_scores`
--
ALTER TABLE `term_subject_scores`
  ADD CONSTRAINT `fk_tss_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tss_subject` FOREIGN KEY (`subject_id`) REFERENCES `curriculum_units` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tss_term` FOREIGN KEY (`term_id`) REFERENCES `academic_terms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transport_stops`
--
ALTER TABLE `transport_stops`
  ADD CONSTRAINT `transport_stops_ibfk_1` FOREIGN KEY (`route_id`) REFERENCES `transport_routes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `transport_vehicles`
--
ALTER TABLE `transport_vehicles`
  ADD CONSTRAINT `transport_vehicles_ibfk_1` FOREIGN KEY (`driver_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `transport_vehicle_routes`
--
ALTER TABLE `transport_vehicle_routes`
  ADD CONSTRAINT `transport_vehicle_routes_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `transport_vehicles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `transport_vehicle_routes_ibfk_2` FOREIGN KEY (`route_id`) REFERENCES `transport_routes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `unit_topics`
--
ALTER TABLE `unit_topics`
  ADD CONSTRAINT `unit_topics_ibfk_1` FOREIGN KEY (`unit_id`) REFERENCES `curriculum_units` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `fk_session_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vehicle_fuel_logs`
--
ALTER TABLE `vehicle_fuel_logs`
  ADD CONSTRAINT `vehicle_fuel_logs_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `transport_vehicles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vehicle_fuel_logs_ibfk_2` FOREIGN KEY (`filled_by`) REFERENCES `staff` (`id`);

--
-- Constraints for table `vehicle_maintenance`
--
ALTER TABLE `vehicle_maintenance`
  ADD CONSTRAINT `vehicle_maintenance_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `transport_vehicles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `workflow_instances`
--
ALTER TABLE `workflow_instances`
  ADD CONSTRAINT `fk_workflow_instance_def` FOREIGN KEY (`workflow_id`) REFERENCES `workflow_definitions` (`id`),
  ADD CONSTRAINT `fk_workflow_starter` FOREIGN KEY (`started_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `workflow_notifications`
--
ALTER TABLE `workflow_notifications`
  ADD CONSTRAINT `fk_workflow_notification_instance` FOREIGN KEY (`instance_id`) REFERENCES `workflow_instances` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_workflow_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `workflow_stages`
--
ALTER TABLE `workflow_stages`
  ADD CONSTRAINT `fk_workflow_stage_def` FOREIGN KEY (`workflow_id`) REFERENCES `workflow_definitions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `workflow_stage_history`
--
ALTER TABLE `workflow_stage_history`
  ADD CONSTRAINT `fk_workflow_history_instance` FOREIGN KEY (`instance_id`) REFERENCES `workflow_instances` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_workflow_processor` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`);


--
-- Metadata
--
USE `phpmyadmin`;

--
-- Metadata for table academic_terms
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table academic_years
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table academic_year_archives
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table account_unlock_history
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table activities
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table activity_categories
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table activity_participants
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table activity_resources
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table admission_applications
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table admission_documents
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table alumni
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table announcements_bulletin
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table announcement_views
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table annual_scores
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table api_tokens
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table arrears_settlement_plans
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table assessments
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table assessment_benchmarks
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table assessment_history
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table assessment_results
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table assessment_rubrics
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table assessment_tools
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table assessment_types
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table assessment_type_classifications
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table audit_trail
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table auth_sessions
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table bank_transactions
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table blocked_devices
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table blocked_ips
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table classes
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table class_enrollments
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table class_promotion_queue
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table class_schedules
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table class_streams
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table class_year_assignments
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table communications
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table communication_attachments
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table communication_groups
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table communication_logs
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table communication_recipients
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table communication_templates
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table conduct_tracking
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table conversation_participants
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table core_competencies
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table core_values
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table csl_activities
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table curriculum_units
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table daily_meal_allocations
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table deduction_types
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table departments
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table drivers
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table equipment_maintenance
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table equipment_maintenance_types
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table exam_schedules
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table external_emails
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table external_institutions
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table failed_auth_attempts
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table fee_discounts_waivers
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table fee_reminders
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table fee_structures
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table fee_structures_detailed
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table fee_structure_change_log
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table fee_structure_rollover_log
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table fee_structure_rollover_schedule
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table fee_transition_history
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table fee_types
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table financial_periods
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table financial_transactions
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table food_consumption_records
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table form_permissions
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table grade_rules
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table grading_comments
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table grading_scales
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table group_members
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table ieps
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table internal_conversations
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table internal_messages
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table inventory_adjustments
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table inventory_allocations
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table inventory_categories
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table inventory_counts
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table inventory_count_items
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table inventory_departments
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table inventory_items
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table inventory_requisitions
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table inventory_transactions
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table item_batches
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table item_serials
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table kpi_achievements
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table kpi_definitions
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table kpi_targets
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table learner_competencies
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table learner_csl_participation
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table learner_pci_awareness
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table learner_values_acquisition
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table learning_areas
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table learning_outcomes
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table leave_types
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table lesson_plans
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table maintenance_logs
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table meal_plans
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table menu_items
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table menu_item_ingredients
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table message_read_status
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table message_templates
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table mpesa_transactions
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table notifications
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table onboarding_tasks
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table parents
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table parent_communication_preferences
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table password_resets
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table payment_allocations
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table payment_allocations_detailed
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table payment_reconciliations
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table payment_transactions
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table payment_webhooks_log
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table payroll_configurations
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table payslips
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table pcis
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table performance_levels_cbc
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table performance_ratings
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table performance_review_kpis
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table permission_delegations
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table portfolios
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table portfolio_artifacts
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table promotion_batches
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table purchase_orders
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table record_permissions
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table requisition_items
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table roles
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table role_form_permissions
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table rooms
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table route_schedules
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table route_stops
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table schedules
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table schedule_changes
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table school_configuration
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table school_levels
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table school_transactions
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table sms_communications
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table staff
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table staff_allowances
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table staff_attendance
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table staff_categories
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table staff_class_assignments
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table staff_contracts
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table staff_deductions
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table staff_experience
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table staff_kpi_templates
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table staff_leaves
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table staff_loans
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table staff_onboarding
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table staff_payroll
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table staff_performance_reviews
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table staff_qualifications
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table staff_types
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table storage_locations
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table students
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table student_activities
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table student_arrears
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table student_attendance
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table student_discipline
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table student_fee_balances
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table student_fee_carryover
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table student_fee_obligations
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table student_parents
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table student_payment_history_summary
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table student_promotions
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table student_registrations
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table student_suspensions
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table student_types
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table suppliers
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table system_events
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table tax_brackets
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table tax_withholding_history
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table template_categories
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table term_consolidations
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table term_subject_scores
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table transport_routes
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table transport_stops
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table transport_vehicles
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table transport_vehicle_routes
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table unit_topics
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table users
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table user_login_attempts
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table user_permissions
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table user_roles
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table user_sessions
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vehicle_fuel_logs
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vehicle_maintenance
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_active_allocations
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_active_students_per_class
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_all_school_payments
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_arrears_summary
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_class_rosters
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_collection_rate_by_class
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_currently_blocked_ips
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_current_enrollments
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_current_staff_assignments
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_failed_attempts_by_ip
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_fee_carryover_summary
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_fee_collection_by_year
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_fee_schedule_by_class
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_fee_structure_annual_summary
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_fee_transition_audit
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_fee_type_collection
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_financial_period_summary
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_food_consumption_summary
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_internal_conversations
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_inventory_health
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_inventory_low_stock
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_lesson_plan_summary
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_maintenance_schedule
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_outstanding_by_class
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_outstanding_fees
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_parent_payment_activity
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_payment_tracking
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_pending_fee_structure_reviews
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_pending_requisitions
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_pending_sms
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_requisition_fulfillment
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_sent_emails
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_sponsored_students_status
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_staff_assignments_detailed
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_staff_leave_balance
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_staff_leave_balances
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_staff_loan_details
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_staff_onboarding_progress
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_staff_payroll_summary
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_staff_performance_summary
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_staff_workload
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_student_payment_history_multi_year
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_student_payment_status
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_student_payment_status_enhanced
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_unread_announcements
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_upcoming_activities
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_upcoming_class_schedules
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_upcoming_exam_schedules
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table vw_user_recent_communications
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table workflow_definitions
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table workflow_instances
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table workflow_notifications
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table workflow_stages
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for table workflow_stage_history
--

--
-- Truncate table before insert `pma__column_info`
--

TRUNCATE TABLE `pma__column_info`;
--
-- Truncate table before insert `pma__table_uiprefs`
--

TRUNCATE TABLE `pma__table_uiprefs`;
--
-- Truncate table before insert `pma__tracking`
--

TRUNCATE TABLE `pma__tracking`;
--
-- Metadata for database KingsWayAcademy
--

--
-- Truncate table before insert `pma__bookmark`
--

TRUNCATE TABLE `pma__bookmark`;
--
-- Truncate table before insert `pma__relation`
--

TRUNCATE TABLE `pma__relation`;
--
-- Truncate table before insert `pma__savedsearches`
--

TRUNCATE TABLE `pma__savedsearches`;
--
-- Truncate table before insert `pma__central_columns`
--

TRUNCATE TABLE `pma__central_columns`;
DELIMITER $$
--
-- Events
--
DROP EVENT IF EXISTS `evt_process_academic_summary`$$
CREATE DEFINER=`root`@`localhost` EVENT `evt_process_academic_summary` ON SCHEDULE EVERY 1 MONTH STARTS '2025-06-01 02:18:23' ON COMPLETION NOT PRESERVE ENABLE DO CALL sp_consolidate_term_scores()$$

DROP EVENT IF EXISTS `ev_weekly_maintenance_notify`$$
CREATE DEFINER=`root`@`localhost` EVENT `ev_weekly_maintenance_notify` ON SCHEDULE EVERY 1 WEEK STARTS '2025-11-15 07:00:00' ON COMPLETION NOT PRESERVE ENABLE DO CALL sp_schedule_maintenance()$$

DROP EVENT IF EXISTS `ev_term_end_values_summary`$$
CREATE DEFINER=`root`@`localhost` EVENT `ev_term_end_values_summary` ON SCHEDULE EVERY 1 QUARTER STARTS '2026-02-09 00:00:00' ON COMPLETION NOT PRESERVE ENABLE DO CALL sp_get_values_summary()$$

DROP EVENT IF EXISTS `evt_process_attendance_summary`$$
CREATE DEFINER=`root`@`localhost` EVENT `evt_process_attendance_summary` ON SCHEDULE EVERY 1 DAY STARTS '2025-06-01 02:18:23' ON COMPLETION NOT PRESERVE ENABLE DO CALL sp_bulk_mark_student_attendance()$$

DROP EVENT IF EXISTS `evt_staff_appraisal_reminders`$$
CREATE DEFINER=`root`@`localhost` EVENT `evt_staff_appraisal_reminders` ON SCHEDULE EVERY 1 MONTH STARTS '2025-06-01 02:18:23' ON COMPLETION NOT PRESERVE ENABLE DO CALL sp_calculate_kpi_achievement_score()$$

DROP EVENT IF EXISTS `evt_vehicle_maintenance_alerts`$$
CREATE DEFINER=`root`@`localhost` EVENT `evt_vehicle_maintenance_alerts` ON SCHEDULE EVERY 1 DAY STARTS '2025-06-01 02:18:23' ON COMPLETION NOT PRESERVE ENABLE DO CALL sp_schedule_maintenance()$$

DROP EVENT IF EXISTS `ev_daily_attendance_report`$$
CREATE DEFINER=`root`@`localhost` EVENT `ev_daily_attendance_report` ON SCHEDULE EVERY 1 DAY STARTS '2025-11-09 07:00:00' ON COMPLETION NOT PRESERVE ENABLE DO CALL sp_bulk_mark_student_attendance()$$

DROP EVENT IF EXISTS `ev_monthly_competency_report_reminder`$$
CREATE DEFINER=`root`@`localhost` EVENT `ev_monthly_competency_report_reminder` ON SCHEDULE EVERY 1 MONTH STARTS '2025-11-01 02:00:00' ON COMPLETION NOT PRESERVE ENABLE DO CALL sp_get_competency_report()$$

DROP EVENT IF EXISTS `ev_quarterly_csl_audit`$$
CREATE DEFINER=`root`@`localhost` EVENT `ev_quarterly_csl_audit` ON SCHEDULE EVERY 1 QUARTER STARTS '2026-02-09 00:00:00' ON COMPLETION NOT PRESERVE ENABLE DO CALL sp_record_csl_participation()$$

DROP EVENT IF EXISTS `ev_yearly_portfolio_archive_reminder`$$
CREATE DEFINER=`root`@`localhost` EVENT `ev_yearly_portfolio_archive_reminder` ON SCHEDULE EVERY 1 YEAR STARTS '2025-11-15 00:00:00' ON COMPLETION NOT PRESERVE ENABLE DO CALL sp_transition_to_new_academic_year()$$

DELIMITER ;
SET FOREIGN_KEY_CHECKS=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
