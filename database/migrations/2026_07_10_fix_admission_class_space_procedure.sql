-- Rebuild class-space availability check for the live students/streams schema.
-- students stores stream_id; class capacity must be counted via class_streams.class_id.

DROP PROCEDURE IF EXISTS sp_check_class_space_availability;

DELIMITER $$
CREATE PROCEDURE sp_check_class_space_availability(
    IN p_application_id INT,
    IN p_user_id INT
)
BEGIN
    DECLARE v_grade_applying_for VARCHAR(50);
    DECLARE v_academic_year YEAR;
    DECLARE v_grade_name VARCHAR(50);
    DECLARE v_target_class_id INT;
    DECLARE v_class_capacity INT DEFAULT 0;
    DECLARE v_current_student_count INT DEFAULT 0;
    DECLARE v_available_spaces INT DEFAULT 0;
    DECLARE v_space_available BOOLEAN DEFAULT FALSE;
    DECLARE v_space_message TEXT DEFAULT 'No class found for the applied grade and academic year';
    DECLARE v_requires_assessment BOOLEAN DEFAULT FALSE;

    SELECT grade_applying_for, academic_year
      INTO v_grade_applying_for, v_academic_year
      FROM admission_applications
     WHERE id = p_application_id;

    SET v_grade_name = TRIM(REPLACE(REPLACE(v_grade_applying_for, 'Grade', 'Grade '), '  ', ' '));

    SELECT c.id, c.capacity
      INTO v_target_class_id, v_class_capacity
      FROM classes c
     WHERE c.name COLLATE utf8mb4_general_ci = v_grade_name COLLATE utf8mb4_general_ci
       AND c.academic_year = v_academic_year
       AND c.status = 'active'
     LIMIT 1;

    SET v_requires_assessment = (v_target_class_id IS NOT NULL AND v_target_class_id >= 8);

    IF v_target_class_id IS NOT NULL THEN
        SELECT COUNT(*)
          INTO v_current_student_count
          FROM students s
          INNER JOIN class_streams cs ON cs.id = s.stream_id
         WHERE cs.class_id = v_target_class_id
           AND s.status = 'active';

        SET v_available_spaces = GREATEST(v_class_capacity - v_current_student_count, 0);
        SET v_space_available = v_available_spaces > 0;

        IF v_space_available THEN
            SET v_space_message = CONCAT('Class space available: ', v_available_spaces, ' slots out of ', v_class_capacity, ' total capacity.');
        ELSE
            SET v_space_message = CONCAT('No space available. Class is at capacity (', v_current_student_count, '/', v_class_capacity, ').');
        END IF;
    END IF;

    SELECT
        v_space_available AS space_available,
        v_available_spaces AS available_spaces,
        v_space_message AS space_message,
        v_target_class_id AS class_id,
        v_class_capacity AS capacity,
        v_current_student_count AS current_count,
        v_academic_year AS academic_year_id,
        v_requires_assessment AS requires_assessment;
END$$
DELIMITER ;
