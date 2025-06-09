-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jun 04, 2025 at 06:55 PM
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
CREATE DATABASE IF NOT EXISTS `KingsWayAcademy` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `KingsWayAcademy`;

DELIMITER $$
--
-- Procedures
--
DROP PROCEDURE IF EXISTS `sp_allocate_payment`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_allocate_payment` (IN `p_transaction_id` INT UNSIGNED, IN `p_fee_structure_id` INT UNSIGNED, IN `p_amount` DECIMAL(10,2), IN `p_academic_term_id` INT UNSIGNED, IN `p_allocated_by` INT UNSIGNED, IN `p_notes` TEXT)   BEGIN
    DECLARE v_available_amount DECIMAL(10,2);
    DECLARE v_total_allocated DECIMAL(10,2);
    
    -- Get transaction amount
    SELECT amount INTO v_available_amount
    FROM financial_transactions
    WHERE id = p_transaction_id;
    
    -- Get total allocated amount
    SELECT COALESCE(SUM(amount), 0) INTO v_total_allocated
    FROM payment_allocations
    WHERE transaction_id = p_transaction_id;
    
    -- Check if allocation is possible
    IF (v_total_allocated + p_amount) > v_available_amount THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Allocation amount exceeds available transaction amount';
    END IF;
    
    -- Insert allocation
    INSERT INTO payment_allocations (
        transaction_id,
        fee_structure_id,
        amount,
        academic_term_id,
        allocated_by,
        notes
    ) VALUES (
        p_transaction_id,
        p_fee_structure_id,
        p_amount,
        p_academic_term_id,
        p_allocated_by,
        p_notes
    );
    
    -- Update student fee balance
    UPDATE student_fee_balances
    SET balance = balance - p_amount,
        last_updated = NOW()
    WHERE student_id = (
        SELECT student_id 
        FROM financial_transactions 
        WHERE id = p_transaction_id
    )
    AND fee_structure_id = p_fee_structure_id
    AND academic_term_id = p_academic_term_id;
END$$

DROP PROCEDURE IF EXISTS `sp_assign_financial_period`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_assign_financial_period` (IN `p_transaction_id` INT UNSIGNED)   BEGIN
    DECLARE v_transaction_date DATE;
    DECLARE v_period_id INT UNSIGNED;
    
    -- Get transaction date
    SELECT transaction_date INTO v_transaction_date
    FROM school_transactions
    WHERE id = p_transaction_id;
    
    -- Find matching financial period
    SELECT id INTO v_period_id
    FROM financial_periods
    WHERE v_transaction_date BETWEEN start_date AND end_date
    AND status = 'active'
    LIMIT 1;
    
    -- Update transaction if period found
    IF v_period_id IS NOT NULL THEN
        UPDATE school_transactions
        SET financial_period_id = v_period_id
        WHERE id = p_transaction_id;
    END IF;
END$$

DROP PROCEDURE IF EXISTS `sp_bulk_mark_attendance`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_bulk_mark_attendance` (IN `p_class_id` INT UNSIGNED, IN `p_date` DATE, IN `p_status` VARCHAR(20), IN `p_marked_by` INT UNSIGNED)   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE student_id INT;
    DECLARE cur CURSOR FOR 
        SELECT s.id 
        FROM students s
        JOIN class_streams cs ON s.stream_id = cs.id
        WHERE cs.class_id = p_class_id
        AND s.status = 'active';
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    START TRANSACTION;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO student_id;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        INSERT INTO attendance (
            student_id,
            date,
            status,
            marked_by,
            created_at
        ) VALUES (
            student_id,
            p_date,
            p_status,
            p_marked_by,
            NOW()
        );
    END LOOP;
    
    CLOSE cur;
    COMMIT;
END$$

DROP PROCEDURE IF EXISTS `sp_generate_student_report`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_generate_student_report` (IN `p_student_id` INT UNSIGNED, IN `p_term_id` INT UNSIGNED)   BEGIN
    -- Get student details
    SELECT 
        s.admission_number,
        s.first_name,
        s.last_name,
        gl.name AS grade_level,
        c.name AS class_name,
        at.name AS academic_term,
        -- Academic Performance
        COUNT(DISTINCT ca.id) AS total_assessments,
        AVG(ca.overall_score) AS average_score,
        -- Attendance
        COUNT(DISTINCT sa.id) AS total_school_days,
        COUNT(DISTINCT CASE WHEN sa.status = 'present' THEN sa.id END) AS days_present,
        -- Transport
        tr.name AS transport_route,
        rs.name AS pickup_point,
        -- Fees
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

DROP PROCEDURE IF EXISTS `sp_process_staff_payroll`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_process_staff_payroll` (IN `p_month` INT, IN `p_year` INT)   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_staff_id INT UNSIGNED;
    DECLARE v_basic_salary DECIMAL(10,2);
    DECLARE v_allowances DECIMAL(10,2);
    DECLARE v_deductions DECIMAL(10,2);
    
    -- Cursor for staff
    DECLARE staff_cur CURSOR FOR 
        SELECT id, basic_salary 
        FROM staff 
        WHERE status = 'active';
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Start transaction
    START TRANSACTION;
    
    OPEN staff_cur;
    
    read_loop: LOOP
        FETCH staff_cur INTO v_staff_id, v_basic_salary;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Calculate allowances
        SELECT COALESCE(SUM(amount), 0) INTO v_allowances
        FROM staff_allowances
        WHERE staff_id = v_staff_id
        AND MONTH(effective_date) = p_month
        AND YEAR(effective_date) = p_year;
        
        -- Calculate deductions
        SELECT COALESCE(SUM(amount), 0) INTO v_deductions
        FROM staff_deductions
        WHERE staff_id = v_staff_id
        AND MONTH(effective_date) = p_month
        AND YEAR(effective_date) = p_year;
        
        -- Insert into payroll
        INSERT INTO staff_payroll (
            staff_id,
            payroll_month,
            payroll_year,
            basic_salary,
            allowances,
            deductions,
            net_salary,
            status
        ) VALUES (
            v_staff_id,
            p_month,
            p_year,
            v_basic_salary,
            v_allowances,
            v_deductions,
            v_basic_salary + v_allowances - v_deductions,
            'pending'
        );
    END LOOP;
    
    CLOSE staff_cur;
    
    COMMIT;
END$$

DROP PROCEDURE IF EXISTS `sp_record_cash_payment`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_record_cash_payment` (IN `p_student_id` INT UNSIGNED, IN `p_amount` DECIMAL(10,2), IN `p_transaction_date` DATETIME, IN `p_details` VARCHAR(255))   BEGIN
    UPDATE student_fee_balances
    SET balance = balance - p_amount, last_updated = NOW()
    WHERE student_id = p_student_id
    ORDER BY academic_term_id DESC
    LIMIT 1;
    INSERT INTO school_transactions (student_id, source, reference, amount, transaction_date, status, details)
    VALUES (p_student_id, 'cash', NULL, p_amount, p_transaction_date, 'confirmed', JSON_OBJECT('details', p_details));
END$$

DROP PROCEDURE IF EXISTS `sp_send_fee_reminders`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_send_fee_reminders` ()   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_student_id INT UNSIGNED;
    DECLARE v_parent_id INT UNSIGNED;
    DECLARE v_balance DECIMAL(10,2);
    DECLARE v_due_date DATE;
    
    -- Cursor for fee balances
    DECLARE fee_cur CURSOR FOR 
        SELECT 
            sfb.student_id,
            s.parent_id,
            sfb.balance,
            fs.due_date
        FROM student_fee_balances sfb
        JOIN students s ON sfb.student_id = s.id
        JOIN fee_structures fs ON sfb.fee_structure_id = fs.id
        WHERE sfb.status IN ('unpaid', 'partial')
        AND fs.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY);
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN fee_cur;
    
    read_loop: LOOP
        FETCH fee_cur INTO v_student_id, v_parent_id, v_balance, v_due_date;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Insert reminder notification
        INSERT INTO notifications (
            user_id,
            type,
            title,
            message,
            priority
        ) VALUES (
            v_parent_id,
            'fee_reminder',
            'Fee Payment Reminder',
            CONCAT('Your child''s fee balance of KES ', v_balance, ' is due on ', v_due_date),
            'high'
        );
    END LOOP;
    
    CLOSE fee_cur;
END$$

--
-- Functions
--
DROP FUNCTION IF EXISTS `calculate_class_average`$$
CREATE DEFINER=`root`@`localhost` FUNCTION `calculate_class_average` (`p_class_id` INT UNSIGNED, `p_subject_id` INT UNSIGNED, `p_term_id` INT UNSIGNED) RETURNS DECIMAL(5,2) READS SQL DATA BEGIN
    DECLARE avg_marks DECIMAL(5,2);
    
    SELECT AVG(ar.marks_obtained)
    INTO avg_marks
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
CREATE DEFINER=`root`@`localhost` FUNCTION `calculate_grade` (`marks` DECIMAL(5,2)) RETURNS CHAR(2) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci DETERMINISTIC BEGIN
    RETURN CASE
        WHEN marks >= 80 THEN 'A'
        WHEN marks >= 75 THEN 'A-'
        WHEN marks >= 70 THEN 'B+'
        WHEN marks >= 65 THEN 'B'
        WHEN marks >= 60 THEN 'B-'
        WHEN marks >= 55 THEN 'C+'
        WHEN marks >= 50 THEN 'C'
        WHEN marks >= 45 THEN 'C-'
        WHEN marks >= 40 THEN 'D+'
        WHEN marks >= 35 THEN 'D'
        WHEN marks >= 30 THEN 'D-'
        ELSE 'E'
    END;
END$$

DROP FUNCTION IF EXISTS `calculate_student_age`$$
CREATE DEFINER=`root`@`localhost` FUNCTION `calculate_student_age` (`date_of_birth` DATE) RETURNS INT(11) DETERMINISTIC BEGIN
    RETURN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE());
END$$

DROP FUNCTION IF EXISTS `calculate_term_fees`$$
CREATE DEFINER=`root`@`localhost` FUNCTION `calculate_term_fees` (`p_student_id` INT UNSIGNED, `p_term_id` INT UNSIGNED) RETURNS DECIMAL(10,2) READS SQL DATA BEGIN
    DECLARE total_fees DECIMAL(10,2);
    
    SELECT COALESCE(SUM(fs.amount), 0)
    INTO total_fees
    FROM fee_structures fs
    JOIN student_fee_balances sfb ON fs.id = sfb.fee_structure_id
    WHERE sfb.student_id = p_student_id
    AND sfb.academic_term_id = p_term_id;
    
    RETURN total_fees;
END$$

DROP FUNCTION IF EXISTS `generate_student_number`$$
CREATE DEFINER=`root`@`localhost` FUNCTION `generate_student_number` (`class_code` VARCHAR(10), `admission_year` YEAR) RETURNS VARCHAR(20) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci DETERMINISTIC BEGIN
    DECLARE next_number INT;
    
    SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(admission_no, '/', -1) AS UNSIGNED)), 0) + 1
    INTO next_number
    FROM students
    WHERE admission_no LIKE CONCAT(class_code, '/', admission_year, '/%');
    
    RETURN CONCAT(class_code, '/', admission_year, '/', LPAD(next_number, 4, '0'));
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `academic_terms`
--
-- Creation: May 31, 2025 at 11:18 PM
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
-- Table structure for table `activities`
--
-- Creation: May 31, 2025 at 11:18 PM
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
-- Creation: May 31, 2025 at 11:18 PM
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
-- Creation: May 31, 2025 at 11:18 PM
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
-- Creation: May 31, 2025 at 11:18 PM
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
-- Table structure for table `audit_trail`
--
-- Creation: May 31, 2025 at 11:18 PM
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
-- Creation: May 31, 2025 at 11:18 PM
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
-- Creation: Jun 04, 2025 at 08:01 AM
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
        SET balance = balance - NEW.amount, last_updated = NOW()
        WHERE student_id = NEW.student_id
        ORDER BY academic_term_id DESC
        LIMIT 1;
        INSERT INTO school_transactions (student_id, source, reference, amount, transaction_date, status, details)
        VALUES (NEW.student_id, 'bank', NEW.transaction_ref, NEW.amount, NEW.transaction_date, 'confirmed', JSON_OBJECT('bank', NEW.bank_name, 'account', NEW.account_number));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--
-- Creation: May 31, 2025 at 11:18 PM
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
-- --------------------------------------------------------

--
-- Table structure for table `class_schedules`
--
-- Creation: May 31, 2025 at 11:18 PM
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
-- Creation: May 31, 2025 at 11:18 PM
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
-- Table structure for table `communications`
--
-- Creation: Jun 04, 2025 at 03:36 PM
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
-- Creation: May 31, 2025 at 11:18 PM
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
-- Creation: Jun 04, 2025 at 03:36 PM
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
-- Creation: Jun 04, 2025 at 03:36 PM
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
-- Creation: Jun 04, 2025 at 03:36 PM
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
-- Table structure for table `curriculum_units`
--
-- Creation: May 31, 2025 at 11:18 PM
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
-- Table structure for table `departments`
--
-- Creation: May 31, 2025 at 11:18 PM
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
-- Creation: May 31, 2025 at 11:18 PM
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
-- Table structure for table `exam_schedules`
--
-- Creation: May 31, 2025 at 11:18 PM
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
-- Table structure for table `fee_structures`
--
-- Creation: May 31, 2025 at 11:18 PM
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
-- Table structure for table `financial_periods`
--
-- Creation: Jun 04, 2025 at 03:43 PM
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
-- Creation: Jun 04, 2025 at 04:17 PM
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
-- --------------------------------------------------------

--
-- Table structure for table `group_members`
--
-- Creation: Jun 04, 2025 at 03:36 PM
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
-- Table structure for table `inventory_adjustments`
--
-- Creation: Jun 04, 2025 at 03:36 PM
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
-- Table structure for table `inventory_categories`
--
-- Creation: May 31, 2025 at 11:18 PM
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
-- Creation: Jun 04, 2025 at 03:36 PM
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
-- Creation: Jun 04, 2025 at 03:36 PM
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
-- Table structure for table `inventory_items`
--
-- Creation: Jun 04, 2025 at 03:36 PM
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
DROP TRIGGER IF EXISTS `trg_inventory_low_stock`;
DELIMITER $$
CREATE TRIGGER `trg_inventory_low_stock` AFTER UPDATE ON `inventory_items` FOR EACH ROW BEGIN
    IF NEW.current_quantity <= NEW.minimum_quantity AND OLD.current_quantity > NEW.minimum_quantity THEN
        INSERT INTO notifications (user_id, type, title, message, priority, created_at)
        VALUES (1, 'inventory', 'Low Stock Alert', CONCAT('Item ', NEW.name, ' is low on stock.'), 'high', NOW());
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_transactions`
--
-- Creation: Jun 04, 2025 at 03:36 PM
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
  KEY `serial_id` (`serial_id`)
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
-- Creation: Jun 04, 2025 at 03:36 PM
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
-- Creation: Jun 04, 2025 at 03:36 PM
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
-- Table structure for table `learning_areas`
--
-- Creation: May 31, 2025 at 11:18 PM
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
-- Table structure for table `lesson_plans`
--
-- Creation: May 31, 2025 at 11:18 PM
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
-- Table structure for table `message_templates`
--
-- Creation: Jun 04, 2025 at 03:36 PM
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
-- Creation: Jun 04, 2025 at 08:01 AM
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mpesa_code` (`mpesa_code`),
  KEY `idx_student_date` (`student_id`,`transaction_date`)
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
CREATE TRIGGER `trg_mpesa_payment_processed` AFTER INSERT ON `mpesa_transactions` FOR EACH ROW BEGIN
    IF NEW.status = 'processed' AND NEW.student_id IS NOT NULL THEN
        UPDATE student_fee_balances
        SET balance = balance - NEW.amount, last_updated = NOW()
        WHERE student_id = NEW.student_id
        ORDER BY academic_term_id DESC
        LIMIT 1;
        INSERT INTO school_transactions (student_id, source, reference, amount, transaction_date, status, details)
        VALUES (NEW.student_id, 'mpesa', NEW.mpesa_code, NEW.amount, NEW.transaction_date, 'confirmed', NEW.raw_callback);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--
-- Creation: May 31, 2025 at 11:18 PM
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
-- Table structure for table `parents`
--
-- Creation: May 31, 2025 at 11:18 PM
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
-- Table structure for table `password_resets`
--
-- Creation: May 31, 2025 at 11:18 PM
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
-- Creation: Jun 04, 2025 at 03:43 PM
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
-- Table structure for table `payment_reconciliations`
--
-- Creation: Jun 04, 2025 at 03:43 PM
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
-- Table structure for table `purchase_orders`
--
-- Creation: May 31, 2025 at 11:18 PM
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
-- Table structure for table `roles`
--
-- Creation: May 31, 2025 at 11:18 PM
-- Last update: Jun 04, 2025 at 09:28 AM
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `roles`:
--

--
-- Truncate table before insert `roles`
--

TRUNCATE TABLE `roles`;
--
-- Dumping data for table `roles`
--

INSERT DELAYED IGNORE INTO `roles` (`id`, `name`, `description`, `permissions`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'System administrator with full access', '{\"all\":true}', '2025-06-04 09:28:53', '2025-06-04 09:28:53'),
(2, 'head_teacher', 'Head teacher with school-wide management permissions', '{\"manage_teachers\":true,\"manage_students\":true}', '2025-06-04 09:28:53', '2025-06-04 09:28:53'),
(3, 'director', 'School director with oversight permissions', '{\"view_reports\":true,\"manage_staff\":true}', '2025-06-04 09:28:53', '2025-06-04 09:28:53'),
(4, 'teacher', 'Class teacher with access to student and class management', '{\"manage_classes\":true,\"view_students\":true}', '2025-06-04 09:28:53', '2025-06-04 09:28:53'),
(5, 'accountant', 'Handles school finances and fee management', '{\"manage_fees\":true,\"view_financial_reports\":true}', '2025-06-04 09:28:53', '2025-06-04 09:28:53'),
(6, 'registrar', 'Manages student admissions and records', '{\"manage_admissions\":true,\"view_students\":true}', '2025-06-04 09:28:53', '2025-06-04 09:28:53'),
(7, 'games_teacher', 'Manages sports and co-curricular activities', '{\"manage_activities\":true,\"view_students\":true}', '2025-06-04 09:28:53', '2025-06-04 09:28:53');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--
-- Creation: May 31, 2025 at 11:18 PM
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
-- Creation: Jun 04, 2025 at 03:35 PM
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
-- Creation: Jun 04, 2025 at 03:35 PM
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
-- Creation: May 31, 2025 at 11:18 PM
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
-- Creation: May 31, 2025 at 11:18 PM
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
-- Table structure for table `school_levels`
--
-- Creation: May 31, 2025 at 11:18 PM
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
-- Creation: Jun 04, 2025 at 03:43 PM
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
  KEY `idx_transaction_date` (`transaction_date`)
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
-- Table structure for table `staff`
--
-- Creation: Jun 04, 2025 at 10:24 AM
-- Last update: Jun 04, 2025 at 10:24 AM
--

DROP TABLE IF EXISTS `staff`;
CREATE TABLE IF NOT EXISTS `staff` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_no` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `department_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `position` varchar(100) NOT NULL,
  `employment_date` date NOT NULL,
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
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `staff`:
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
-- Creation: May 31, 2025 at 11:18 PM
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
-- Creation: Jun 04, 2025 at 04:01 PM
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
  KEY `marked_by` (`marked_by`)
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
-- Table structure for table `staff_deductions`
--
-- Creation: May 31, 2025 at 11:18 PM
--

DROP TABLE IF EXISTS `staff_deductions`;
CREATE TABLE IF NOT EXISTS `staff_deductions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `effective_date` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`)
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
-- Creation: Jun 04, 2025 at 02:19 PM
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
-- Table structure for table `staff_payroll`
--
-- Creation: Jun 04, 2025 at 04:01 PM
--

DROP TABLE IF EXISTS `staff_payroll`;
CREATE TABLE IF NOT EXISTS `staff_payroll` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `payroll_month` int(11) NOT NULL,
  `payroll_year` int(11) NOT NULL,
  `basic_salary` decimal(10,2) NOT NULL,
  `allowances` decimal(10,2) NOT NULL,
  `deductions` decimal(10,2) NOT NULL,
  `net_salary` decimal(10,2) NOT NULL,
  `status` varchar(50) NOT NULL,
  `payroll_period` varchar(7) NOT NULL COMMENT 'Format: YYYY-MM',
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`),
  KEY `idx_payroll_period` (`payroll_period`)
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
-- --------------------------------------------------------

--
-- Table structure for table `staff_qualifications`
--
-- Creation: Jun 04, 2025 at 02:19 PM
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
-- Table structure for table `storage_locations`
--
-- Creation: May 31, 2025 at 11:18 PM
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
-- Creation: May 31, 2025 at 11:18 PM
--

DROP TABLE IF EXISTS `students`;
CREATE TABLE IF NOT EXISTS `students` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `admission_no` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `stream_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `admission_date` date NOT NULL,
  `status` enum('active','inactive','graduated','transferred','suspended') NOT NULL DEFAULT 'active',
  `photo_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admission_no` (`admission_no`),
  KEY `idx_stream` (`stream_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`)
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
CREATE TRIGGER `trg_auto_link_parent` AFTER INSERT ON `students` FOR EACH ROW BEGIN
    IF NEW.user_id IS NOT NULL THEN
        INSERT IGNORE INTO student_parents (student_id, parent_id)
        VALUES (NEW.id, NEW.user_id);
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
CREATE TRIGGER `trg_update_student_status` AFTER UPDATE ON `students` FOR EACH ROW BEGIN
    IF NEW.status IN ('graduated', 'transferred') AND OLD.status != NEW.status THEN
        INSERT INTO notifications (user_id, type, title, message, priority, created_at)
        VALUES (get_parent_user_id(NEW.id), 'info', 'Student Status Update', CONCAT('Your child status changed to ', NEW.status), 'medium', NOW());
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `student_activities`
--
-- Creation: May 31, 2025 at 11:18 PM
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
-- Table structure for table `student_attendance`
--
-- Creation: Jun 04, 2025 at 04:01 PM
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
  KEY `marked_by` (`marked_by`)
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
-- --------------------------------------------------------

--
-- Table structure for table `student_discipline`
--
-- Creation: Jun 04, 2025 at 04:01 PM
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
-- Creation: May 31, 2025 at 11:18 PM
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
-- Table structure for table `student_parents`
--
-- Creation: May 31, 2025 at 11:18 PM
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
-- Table structure for table `student_registrations`
--
-- Creation: May 31, 2025 at 11:18 PM
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
-- Table structure for table `suppliers`
--
-- Creation: May 31, 2025 at 11:18 PM
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
-- Table structure for table `system_logs`
--
-- Creation: May 31, 2025 at 11:18 PM
--

DROP TABLE IF EXISTS `system_logs`;
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(10) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_action` (`user_id`,`action`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- RELATIONSHIPS FOR TABLE `system_logs`:
--   `user_id`
--       `users` -> `id`
--

--
-- Truncate table before insert `system_logs`
--

TRUNCATE TABLE `system_logs`;
-- --------------------------------------------------------

--
-- Table structure for table `template_categories`
--
-- Creation: Jun 04, 2025 at 03:36 PM
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
-- Table structure for table `transport_routes`
--
-- Creation: Jun 04, 2025 at 03:35 PM
-- Last update: Jun 04, 2025 at 03:35 PM
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
-- Creation: May 31, 2025 at 11:18 PM
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
-- Creation: May 31, 2025 at 11:18 PM
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
-- Creation: May 31, 2025 at 11:18 PM
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
-- Creation: May 31, 2025 at 11:18 PM
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
-- Creation: Jun 04, 2025 at 10:24 AM
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
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
CREATE TRIGGER `trg_after_password_change` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    IF NEW.password != OLD.password THEN
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
            'username', OLD.username,
            'email', OLD.email,
            'role_id', OLD.role_id,
            'status', OLD.status
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
            'username', NEW.username,
            'email', NEW.email,
            'role_id', NEW.role_id,
            'status', NEW.status
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
            'username', OLD.username,
            'email', OLD.email,
            'role_id', OLD.role_id,
            'status', OLD.status
        ),
        JSON_OBJECT(
            'username', NEW.username,
            'email', NEW.email,
            'role_id', NEW.role_id,
            'status', NEW.status
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
    SELECT name INTO role_name FROM roles WHERE id = OLD.role_id;
    IF role_name = 'admin' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot delete admin users';
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `trg_validate_email`;
DELIMITER $$
CREATE TRIGGER `trg_validate_email` BEFORE INSERT ON `users` FOR EACH ROW BEGIN
    IF NEW.email NOT REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+.[A-Za-z]{2,}$' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Invalid email format';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--
-- Creation: May 31, 2025 at 11:18 PM
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
-- Creation: May 31, 2025 at 11:18 PM
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
-- Table structure for table `vehicle_fuel_logs`
--
-- Creation: Jun 04, 2025 at 03:35 PM
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
  KEY `filled_by` (`filled_by`)
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
-- Creation: Jun 04, 2025 at 03:35 PM
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
  KEY `vehicle_id` (`vehicle_id`)
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
-- Stand-in structure for view `vw_active_students_per_class`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_active_students_per_class`;
CREATE TABLE IF NOT EXISTS `vw_active_students_per_class` (
`class_id` int(10) unsigned
,`class_name` varchar(50)
,`stream_id` int(10) unsigned
,`stream_name` varchar(50)
,`active_students` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_all_school_payments`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_all_school_payments`;
CREATE TABLE IF NOT EXISTS `vw_all_school_payments` (
`source` varchar(5)
,`reference` varchar(100)
,`student_id` int(10) unsigned
,`amount` decimal(10,2)
,`transaction_date` datetime
,`status` varchar(9)
,`details` longtext
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_financial_period_summary`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_financial_period_summary`;
CREATE TABLE IF NOT EXISTS `vw_financial_period_summary` (
`period_id` int(10) unsigned
,`period_name` varchar(100)
,`start_date` date
,`end_date` date
,`status` enum('active','closed')
,`total_transactions` bigint(21)
,`reconciled_transactions` bigint(21)
,`total_amount` decimal(32,2)
,`reconciled_amount` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_inventory_low_stock`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_inventory_low_stock`;
CREATE TABLE IF NOT EXISTS `vw_inventory_low_stock` (
`id` int(10) unsigned
,`name` varchar(255)
,`current_quantity` int(11)
,`minimum_quantity` int(11)
,`category` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_lesson_plan_summary`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_lesson_plan_summary`;
CREATE TABLE IF NOT EXISTS `vw_lesson_plan_summary` (
`id` int(10) unsigned
,`teacher_id` int(10) unsigned
,`learning_area_id` int(10) unsigned
,`class_id` int(10) unsigned
,`unit_id` int(10) unsigned
,`topic` varchar(255)
,`subtopic` varchar(255)
,`objectives` text
,`resources` text
,`activities` text
,`assessment` text
,`homework` text
,`lesson_date` date
,`duration` int(11)
,`status` enum('draft','submitted','approved','completed')
,`remarks` text
,`approved_by` int(10) unsigned
,`approved_at` datetime
,`created_at` timestamp
,`updated_at` timestamp
,`teacher_name` varchar(101)
,`learning_area_name` varchar(100)
,`class_name` varchar(50)
,`unit_name` varchar(255)
,`topic_name` varchar(255)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_outstanding_fees`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_outstanding_fees`;
CREATE TABLE IF NOT EXISTS `vw_outstanding_fees` (
`student_id` int(10) unsigned
,`first_name` varchar(50)
,`last_name` varchar(50)
,`parent_id` int(10) unsigned
,`parent_first` varchar(50)
,`parent_last` varchar(50)
,`outstanding_balance` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_upcoming_activities`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_upcoming_activities`;
CREATE TABLE IF NOT EXISTS `vw_upcoming_activities` (
`id` int(10) unsigned
,`title` varchar(255)
,`start_date` date
,`end_date` date
,`category` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_upcoming_class_schedules`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_upcoming_class_schedules`;
CREATE TABLE IF NOT EXISTS `vw_upcoming_class_schedules` (
`class_id` int(10) unsigned
,`day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')
,`start_time` time
,`end_time` time
,`subject` varchar(255)
,`teacher` varchar(50)
,`room` varchar(50)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_upcoming_exam_schedules`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_upcoming_exam_schedules`;
CREATE TABLE IF NOT EXISTS `vw_upcoming_exam_schedules` (
`class_id` int(10) unsigned
,`subject_id` int(10) unsigned
,`subject` varchar(255)
,`exam_date` date
,`start_time` time
,`end_time` time
,`room` varchar(50)
,`invigilator` varchar(50)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_user_recent_communications`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_user_recent_communications`;
CREATE TABLE IF NOT EXISTS `vw_user_recent_communications` (
`recipient_id` int(10) unsigned
,`subject` varchar(255)
,`content` text
,`type` enum('email','sms','notification','internal')
,`status` enum('draft','sent','scheduled','failed')
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Structure for view `vw_active_students_per_class`
--
DROP TABLE IF EXISTS `vw_active_students_per_class`;

DROP VIEW IF EXISTS `vw_active_students_per_class`;
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_active_students_per_class`  AS SELECT `c`.`id` AS `class_id`, `c`.`name` AS `class_name`, `cs`.`id` AS `stream_id`, `cs`.`stream_name` AS `stream_name`, count(`s`.`id`) AS `active_students` FROM ((`classes` `c` join `class_streams` `cs` on(`c`.`id` = `cs`.`class_id`)) join `students` `s` on(`cs`.`id` = `s`.`stream_id`)) WHERE `s`.`status` = 'active' GROUP BY `c`.`id`, `cs`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_all_school_payments`
--
DROP TABLE IF EXISTS `vw_all_school_payments`;

DROP VIEW IF EXISTS `vw_all_school_payments`;
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_all_school_payments`  AS SELECT 'bank' AS `source`, `bt`.`transaction_ref` AS `reference`, `bt`.`student_id` AS `student_id`, `bt`.`amount` AS `amount`, `bt`.`transaction_date` AS `transaction_date`, `bt`.`status` AS `status`, `bt`.`narration` AS `details` FROM `bank_transactions` AS `bt`union all select 'mpesa' AS `mpesa`,`mt`.`mpesa_code` AS `mpesa_code`,`mt`.`student_id` AS `student_id`,`mt`.`amount` AS `amount`,`mt`.`transaction_date` AS `transaction_date`,`mt`.`status` AS `status`,`mt`.`raw_callback` AS `raw_callback` from `mpesa_transactions` `mt` union all select `st`.`source` AS `source`,`st`.`reference` AS `reference`,`st`.`student_id` AS `student_id`,`st`.`amount` AS `amount`,`st`.`transaction_date` AS `transaction_date`,`st`.`status` AS `status`,`st`.`details` AS `details` from `school_transactions` `st`  ;

-- --------------------------------------------------------

--
-- Structure for view `vw_financial_period_summary`
--
DROP TABLE IF EXISTS `vw_financial_period_summary`;

DROP VIEW IF EXISTS `vw_financial_period_summary`;
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_financial_period_summary`  AS SELECT `fp`.`id` AS `period_id`, `fp`.`name` AS `period_name`, `fp`.`start_date` AS `start_date`, `fp`.`end_date` AS `end_date`, `fp`.`status` AS `status`, count(distinct `st`.`id`) AS `total_transactions`, count(distinct `pr`.`id`) AS `reconciled_transactions`, sum(`st`.`amount`) AS `total_amount`, sum(case when `pr`.`id` is not null then `st`.`amount` else 0 end) AS `reconciled_amount` FROM ((`financial_periods` `fp` left join `school_transactions` `st` on(`fp`.`id` = `st`.`financial_period_id`)) left join `payment_reconciliations` `pr` on(`st`.`id` = `pr`.`transaction_id`)) GROUP BY `fp`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_inventory_low_stock`
--
DROP TABLE IF EXISTS `vw_inventory_low_stock`;

DROP VIEW IF EXISTS `vw_inventory_low_stock`;
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_inventory_low_stock`  AS SELECT `i`.`id` AS `id`, `i`.`name` AS `name`, `i`.`current_quantity` AS `current_quantity`, `i`.`minimum_quantity` AS `minimum_quantity`, `c`.`name` AS `category` FROM (`inventory_items` `i` join `inventory_categories` `c` on(`i`.`category_id` = `c`.`id`)) WHERE `i`.`current_quantity` <= `i`.`minimum_quantity` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_lesson_plan_summary`
--
DROP TABLE IF EXISTS `vw_lesson_plan_summary`;

DROP VIEW IF EXISTS `vw_lesson_plan_summary`;
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_lesson_plan_summary`  AS SELECT `lp`.`id` AS `id`, `lp`.`teacher_id` AS `teacher_id`, `lp`.`learning_area_id` AS `learning_area_id`, `lp`.`class_id` AS `class_id`, `lp`.`unit_id` AS `unit_id`, `lp`.`topic` AS `topic`, `lp`.`subtopic` AS `subtopic`, `lp`.`objectives` AS `objectives`, `lp`.`resources` AS `resources`, `lp`.`activities` AS `activities`, `lp`.`assessment` AS `assessment`, `lp`.`homework` AS `homework`, `lp`.`lesson_date` AS `lesson_date`, `lp`.`duration` AS `duration`, `lp`.`status` AS `status`, `lp`.`remarks` AS `remarks`, `lp`.`approved_by` AS `approved_by`, `lp`.`approved_at` AS `approved_at`, `lp`.`created_at` AS `created_at`, `lp`.`updated_at` AS `updated_at`, concat(`s`.`first_name`,' ',`s`.`last_name`) AS `teacher_name`, `la`.`name` AS `learning_area_name`, `c`.`name` AS `class_name`, `cu`.`name` AS `unit_name`, `ut`.`name` AS `topic_name` FROM (((((`lesson_plans` `lp` join `staff` `s` on(`lp`.`teacher_id` = `s`.`id`)) join `learning_areas` `la` on(`lp`.`learning_area_id` = `la`.`id`)) join `classes` `c` on(`lp`.`class_id` = `c`.`id`)) join `curriculum_units` `cu` on(`lp`.`unit_id` = `cu`.`id`)) left join `unit_topics` `ut` on(`cu`.`id` = `ut`.`unit_id`)) WHERE `lp`.`status` <> 'draft' ORDER BY `lp`.`lesson_date` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_outstanding_fees`
--
DROP TABLE IF EXISTS `vw_outstanding_fees`;

DROP VIEW IF EXISTS `vw_outstanding_fees`;
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_outstanding_fees`  AS SELECT `s`.`id` AS `student_id`, `s`.`first_name` AS `first_name`, `s`.`last_name` AS `last_name`, `p`.`id` AS `parent_id`, `p`.`first_name` AS `parent_first`, `p`.`last_name` AS `parent_last`, sum(`sfb`.`balance`) AS `outstanding_balance` FROM (((`students` `s` join `student_fee_balances` `sfb` on(`s`.`id` = `sfb`.`student_id`)) left join `student_parents` `sp` on(`s`.`id` = `sp`.`student_id`)) left join `parents` `p` on(`sp`.`parent_id` = `p`.`id`)) WHERE `sfb`.`balance` > 0 GROUP BY `s`.`id`, `p`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_upcoming_activities`
--
DROP TABLE IF EXISTS `vw_upcoming_activities`;

DROP VIEW IF EXISTS `vw_upcoming_activities`;
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_upcoming_activities`  AS SELECT `a`.`id` AS `id`, `a`.`title` AS `title`, `a`.`start_date` AS `start_date`, `a`.`end_date` AS `end_date`, `ac`.`name` AS `category` FROM (`activities` `a` left join `activity_categories` `ac` on(`a`.`category_id` = `ac`.`id`)) WHERE `a`.`start_date` between curdate() and curdate() + interval 30 day ORDER BY `a`.`start_date` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_upcoming_class_schedules`
--
DROP TABLE IF EXISTS `vw_upcoming_class_schedules`;

DROP VIEW IF EXISTS `vw_upcoming_class_schedules`;
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_upcoming_class_schedules`  AS SELECT `cs`.`class_id` AS `class_id`, `cs`.`day_of_week` AS `day_of_week`, `cs`.`start_time` AS `start_time`, `cs`.`end_time` AS `end_time`, `cu`.`name` AS `subject`, `s`.`first_name` AS `teacher`, `r`.`name` AS `room` FROM (((`class_schedules` `cs` left join `curriculum_units` `cu` on(`cs`.`subject_id` = `cu`.`id`)) left join `staff` `s` on(`cs`.`teacher_id` = `s`.`id`)) left join `rooms` `r` on(`cs`.`room_id` = `r`.`id`)) WHERE `cs`.`status` = 'active' AND `cs`.`start_time` >= curtime() ORDER BY `cs`.`class_id` ASC, `cs`.`day_of_week` ASC, `cs`.`start_time` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_upcoming_exam_schedules`
--
DROP TABLE IF EXISTS `vw_upcoming_exam_schedules`;

DROP VIEW IF EXISTS `vw_upcoming_exam_schedules`;
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_upcoming_exam_schedules`  AS SELECT `es`.`class_id` AS `class_id`, `es`.`subject_id` AS `subject_id`, `cu`.`name` AS `subject`, `es`.`exam_date` AS `exam_date`, `es`.`start_time` AS `start_time`, `es`.`end_time` AS `end_time`, `r`.`name` AS `room`, `s`.`first_name` AS `invigilator` FROM (((`exam_schedules` `es` left join `curriculum_units` `cu` on(`es`.`subject_id` = `cu`.`id`)) left join `rooms` `r` on(`es`.`room_id` = `r`.`id`)) left join `staff` `s` on(`es`.`invigilator_id` = `s`.`id`)) WHERE `es`.`status` = 'scheduled' AND `es`.`exam_date` >= curdate() ORDER BY `es`.`exam_date` ASC, `es`.`start_time` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_user_recent_communications`
--
DROP TABLE IF EXISTS `vw_user_recent_communications`;

DROP VIEW IF EXISTS `vw_user_recent_communications`;
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_user_recent_communications`  AS SELECT `cr`.`recipient_id` AS `recipient_id`, `c`.`subject` AS `subject`, `c`.`content` AS `content`, `c`.`type` AS `type`, `c`.`status` AS `status`, `c`.`created_at` AS `created_at` FROM (`communication_recipients` `cr` join `communications` `c` on(`cr`.`communication_id` = `c`.`id`)) WHERE `cr`.`status` = 'delivered' ORDER BY `c`.`created_at` DESC ;

--
-- Constraints for dumped tables
--

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
-- Constraints for table `curriculum_units`
--
ALTER TABLE `curriculum_units`
  ADD CONSTRAINT `curriculum_units_ibfk_1` FOREIGN KEY (`learning_area_id`) REFERENCES `learning_areas` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `fk_department_head` FOREIGN KEY (`head_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `exam_schedules`
--
ALTER TABLE `exam_schedules`
  ADD CONSTRAINT `exam_schedules_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_schedules_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `curriculum_units` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_schedules_ibfk_3` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `exam_schedules_ibfk_4` FOREIGN KEY (`invigilator_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

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
-- Constraints for table `group_members`
--
ALTER TABLE `group_members`
  ADD CONSTRAINT `group_members_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `communication_groups` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_members_ibfk_3` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `inventory_adjustments`
--
ALTER TABLE `inventory_adjustments`
  ADD CONSTRAINT `inventory_adjustments_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `inventory_adjustments_ibfk_2` FOREIGN KEY (`adjusted_by`) REFERENCES `staff` (`id`),
  ADD CONSTRAINT `inventory_adjustments_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `staff` (`id`);

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
-- Constraints for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `inventory_categories` (`id`) ON UPDATE CASCADE;

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
-- Constraints for table `lesson_plans`
--
ALTER TABLE `lesson_plans`
  ADD CONSTRAINT `lesson_plans_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `staff` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `lesson_plans_ibfk_2` FOREIGN KEY (`learning_area_id`) REFERENCES `learning_areas` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `lesson_plans_ibfk_3` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `lesson_plans_ibfk_4` FOREIGN KEY (`unit_id`) REFERENCES `curriculum_units` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `lesson_plans_ibfk_5` FOREIGN KEY (`approved_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

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
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `staff` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `purchase_orders_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `staff` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

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
-- Constraints for table `school_transactions`
--
ALTER TABLE `school_transactions`
  ADD CONSTRAINT `school_transactions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `school_transactions_ibfk_2` FOREIGN KEY (`financial_period_id`) REFERENCES `financial_periods` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
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
-- Constraints for table `staff_payroll`
--
ALTER TABLE `staff_payroll`
  ADD CONSTRAINT `staff_payroll_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `student_parents`
--
ALTER TABLE `student_parents`
  ADD CONSTRAINT `student_parents_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_parents_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_registrations`
--
ALTER TABLE `student_registrations`
  ADD CONSTRAINT `student_registrations_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_registrations_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_registrations_ibfk_3` FOREIGN KEY (`term_id`) REFERENCES `academic_terms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

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
-- Metadata for table system_logs
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
DROP EVENT IF EXISTS `evt_process_attendance_summary`$$
CREATE DEFINER=`root`@`localhost` EVENT `evt_process_attendance_summary` ON SCHEDULE EVERY 1 DAY STARTS '2025-06-01 02:18:23' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    INSERT INTO attendance_summaries (
        attendance_date,
        grade_level_id,
        class_id,
        total_students,
        present_count,
        absent_count,
        late_count
    )
    SELECT 
        sa.attendance_date,
        s.grade_level_id,
        s.class_id,
        COUNT(DISTINCT s.id) AS total_students,
        COUNT(DISTINCT CASE WHEN sa.status = 'present' THEN s.id END) AS present_count,
        COUNT(DISTINCT CASE WHEN sa.status = 'absent' THEN s.id END) AS absent_count,
        COUNT(DISTINCT CASE WHEN sa.status = 'late' THEN s.id END) AS late_count
    FROM students s
    LEFT JOIN student_attendance sa ON s.id = sa.student_id
    WHERE sa.attendance_date = CURDATE()
    GROUP BY sa.attendance_date, s.grade_level_id, s.class_id;
END$$

DROP EVENT IF EXISTS `evt_process_academic_summary`$$
CREATE DEFINER=`root`@`localhost` EVENT `evt_process_academic_summary` ON SCHEDULE EVERY 1 MONTH STARTS '2025-06-01 02:18:23' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    INSERT INTO academic_performance_summaries (
        academic_term_id,
        grade_level_id,
        class_id,
        assessment_month,
        assessment_year,
        total_students,
        average_score,
        highest_score,
        lowest_score,
        pass_count,
        fail_count
    )
    SELECT 
        ca.term_id,
        s.grade_level_id,
        s.class_id,
        MONTH(ca.assessment_date),
        YEAR(ca.assessment_date),
        COUNT(DISTINCT s.id) AS total_students,
        AVG(ca.overall_score) AS average_score,
        MAX(ca.overall_score) AS highest_score,
        MIN(ca.overall_score) AS lowest_score,
        COUNT(DISTINCT CASE WHEN ca.overall_score >= 50 THEN s.id END) AS pass_count,
        COUNT(DISTINCT CASE WHEN ca.overall_score < 50 THEN s.id END) AS fail_count
    FROM students s
    JOIN cbc_assessments ca ON s.id = ca.student_id
    WHERE 
        MONTH(ca.assessment_date) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)
        AND YEAR(ca.assessment_date) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH)
    GROUP BY ca.term_id, s.grade_level_id, s.class_id;
END$$

DROP EVENT IF EXISTS `evt_vehicle_maintenance_alerts`$$
CREATE DEFINER=`root`@`localhost` EVENT `evt_vehicle_maintenance_alerts` ON SCHEDULE EVERY 1 DAY STARTS '2025-06-01 02:18:23' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    INSERT INTO notifications (
        user_id,
        type,
        title,
        message,
        priority
    )
    SELECT 
        u.id AS user_id,
        'vehicle_maintenance',
        CONCAT('Vehicle Maintenance Due: ', v.registration_number),
        CONCAT('Vehicle ', v.registration_number, ' is due for maintenance on ', v.next_service_due),
        'high'
    FROM vehicles v
    CROSS JOIN users u
    WHERE v.next_service_due BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND u.role IN ('admin', 'transport_manager');
END$$

DROP EVENT IF EXISTS `evt_staff_appraisal_reminders`$$
CREATE DEFINER=`root`@`localhost` EVENT `evt_staff_appraisal_reminders` ON SCHEDULE EVERY 1 MONTH STARTS '2025-06-01 02:18:23' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    INSERT INTO notifications (
        user_id,
        type,
        title,
        message,
        priority
    )
    SELECT 
        s.user_id,
        'appraisal_reminder',
        'Staff Appraisal Due',
        CONCAT('Your staff appraisal for term ', at.name, ' is due by ', DATE_ADD(at.end_date, INTERVAL -14 DAY)),
        'medium'
    FROM staff s
    JOIN academic_terms at ON at.status = 'active'
    LEFT JOIN staff_appraisals sa ON s.id = sa.staff_id AND at.id = sa.academic_term_id
    WHERE sa.id IS NULL
    AND CURDATE() BETWEEN DATE_ADD(at.start_date, INTERVAL 1 MONTH) AND DATE_ADD(at.end_date, INTERVAL -14 DAY);
END$$

DELIMITER ;
SET FOREIGN_KEY_CHECKS=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
