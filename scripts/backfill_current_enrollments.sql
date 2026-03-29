-- Backfill current academic-year class assignments and enrollments
-- Safe to run multiple times (uses UPSERT semantics)

START TRANSACTION;

SET @current_year_id := (
    SELECT ay.id
    FROM academic_years ay
    WHERE ay.is_current = 1 OR ay.status = 'active'
    ORDER BY ay.is_current DESC, ay.start_date DESC, ay.id DESC
    LIMIT 1
);

-- 1) Ensure class/year assignments exist for active streams
INSERT INTO class_year_assignments (
    academic_year_id,
    class_id,
    stream_id,
    teacher_id,
    room_number,
    capacity,
    current_enrollment,
    status,
    created_at,
    updated_at
)
SELECT
    @current_year_id,
    cs.class_id,
    cs.id,
    cs.teacher_id,
    NULL,
    COALESCE(NULLIF(cs.capacity, 0), 40),
    0,
    'active',
    NOW(),
    NOW()
FROM class_streams cs
WHERE cs.status = 'active'
  AND @current_year_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    teacher_id = COALESCE(VALUES(teacher_id), class_year_assignments.teacher_id),
    capacity = COALESCE(VALUES(capacity), class_year_assignments.capacity),
    status = CASE WHEN class_year_assignments.status = 'completed' THEN 'completed' ELSE 'active' END,
    updated_at = NOW();

-- 2) Ensure each student has an enrollment in the current academic year
-- NOTE: class_assignment_id is populated in a later UPDATE to avoid trigger conflict.
INSERT INTO class_enrollments (
    student_id,
    academic_year_id,
    class_id,
    stream_id,
    class_assignment_id,
    enrollment_date,
    enrollment_status,
    promotion_status,
    special_notes,
    created_at,
    updated_at
)
SELECT
    s.id,
    @current_year_id,
    cs.class_id,
    s.stream_id,
    NULL,
    COALESCE(s.admission_date, CURDATE()),
    CASE
        WHEN s.status = 'active' THEN 'active'
        WHEN s.status IN ('inactive', 'suspended') THEN 'withdrawn'
        WHEN s.status = 'graduated' THEN 'graduated'
        WHEN s.status = 'transferred' THEN 'transferred'
        ELSE 'pending'
    END AS enrollment_status,
    CASE
        WHEN s.status = 'graduated' THEN 'graduated'
        WHEN s.status = 'transferred' THEN 'transferred'
        ELSE 'pending'
    END AS promotion_status,
    'Auto-backfilled during module implementation cycle',
    NOW(),
    NOW()
FROM students s
JOIN class_streams cs ON cs.id = s.stream_id
WHERE s.stream_id IS NOT NULL
  AND @current_year_id IS NOT NULL
ON DUPLICATE KEY UPDATE
    class_id = VALUES(class_id),
    stream_id = VALUES(stream_id),
    enrollment_status = VALUES(enrollment_status),
    updated_at = NOW();

-- 3) Link enrollments to class/year assignment rows
UPDATE class_enrollments ce
JOIN class_year_assignments cya
  ON cya.academic_year_id = ce.academic_year_id
 AND cya.class_id = ce.class_id
 AND cya.stream_id = ce.stream_id
SET ce.class_assignment_id = cya.id,
    ce.updated_at = NOW()
WHERE ce.academic_year_id = @current_year_id
  AND (ce.class_assignment_id IS NULL OR ce.class_assignment_id != cya.id);

-- 4) Refresh assignment enrollment counts
UPDATE class_year_assignments cya
LEFT JOIN (
    SELECT class_assignment_id, COUNT(*) AS cnt
    FROM class_enrollments
    WHERE academic_year_id = @current_year_id
      AND enrollment_status IN ('pending', 'active')
    GROUP BY class_assignment_id
) x ON x.class_assignment_id = cya.id
SET cya.current_enrollment = COALESCE(x.cnt, 0),
    cya.updated_at = NOW()
WHERE cya.academic_year_id = @current_year_id;

-- 5) Refresh stream current_students from active students table
UPDATE class_streams cs
LEFT JOIN (
    SELECT stream_id, COUNT(*) AS cnt
    FROM students
    WHERE status = 'active'
      AND stream_id IS NOT NULL
    GROUP BY stream_id
) s ON s.stream_id = cs.id
SET cs.current_students = COALESCE(s.cnt, 0),
    cs.updated_at = NOW();

COMMIT;

SELECT
    @current_year_id AS current_year_id,
    (SELECT COUNT(*) FROM class_year_assignments WHERE academic_year_id = @current_year_id) AS assignments_in_current_year,
    (SELECT COUNT(*) FROM class_enrollments WHERE academic_year_id = @current_year_id) AS enrollments_in_current_year,
    (SELECT COUNT(*) FROM class_enrollments WHERE academic_year_id = @current_year_id AND enrollment_status IN ('pending', 'active')) AS open_enrollments_in_current_year;
