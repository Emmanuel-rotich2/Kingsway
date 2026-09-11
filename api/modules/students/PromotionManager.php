<?php
namespace App\API\Modules\students;

use PDO;
use Exception;
use App\API\Modules\academic\AcademicYearManager;

/**
 * Promotion Manager
 * 
 * Handles all student promotion operations:
 * 1. Single student promotion
 * 2. Multiple students promotion
 * 3. Entire class promotion (with teacher/room assignment)
 * 4. Multiple classes bulk promotion
 * 5. Grade 9 graduation to alumni
 */
class PromotionManager
{
    private PDO $db;
    private AcademicYearManager $yearManager;

    public function __construct(PDO $db, AcademicYearManager $yearManager)
    {
        $this->db = $db;
        $this->yearManager = $yearManager;
    }

    /**
     * SCENARIO 1: Promote single student
     * Creates new enrollment record in target year/class
     */
    public function promoteSingleStudent(
        int $studentId,
        int $toClassId,
        int $toStreamId,
        int $fromYearId,
        int $toYearId,
        int $performedBy,
        string $remarks = null,
        int $batchId = 0
    ): array {
        $this->db->beginTransaction();

        try {
            // Verify student exists and is not transferred
            $student = $this->getStudentStatus($studentId);
            if (!$student) {
                throw new Exception("Student not found");
            }
            if ($student['status'] === 'transferred') {
                throw new Exception("Cannot promote transferred student");
            }

            // Get current enrollment
            $currentEnrollment = $this->getCurrentEnrollment($studentId, $fromYearId);
            if (!$currentEnrollment) {
                throw new Exception("Student has no enrollment for the current academic year");
            }

            // Check if already promoted
            if (in_array($currentEnrollment['enrollment_status'], ['completed', 'transferred', 'graduated'])) {
                throw new Exception("Student already {$currentEnrollment['enrollment_status']}");
            }

            // Verify target class exists
            $this->verifyClassStream($toClassId, $toStreamId);

            // Create a single-student batch when no batch context provided
            if ($batchId === 0) {
                $batchId = $this->createPromotionBatch([
                    'batch_scope' => 'Single student promotion',
                    'academic_year_from' => $fromYearId,
                    'academic_year_to' => $toYearId,
                    'batch_type' => 'manual',
                    'total_students_processed' => 1,
                    'created_by' => $performedBy
                ]);
            }

            // Update current enrollment status (set destination class/stream)
            $this->updateEnrollmentPromotionStatus(
                $currentEnrollment['id'],
                'promoted',
                $toClassId,
                $toStreamId
            );

            // Create new enrollment for next year
            $newEnrollmentId = $this->createEnrollment([
                'student_id' => $studentId,
                'academic_year_id' => $toYearId,
                'class_id' => $toClassId,
                'stream_id' => $toStreamId,
                'enrollment_status' => 'pending',
                'enrollment_date' => date('Y-m-d')
            ]);

            // Record in student_promotions table
            $this->recordPromotion([
                'batch_id' => $batchId,
                'student_id' => $studentId,
                'current_class_id' => $currentEnrollment['class_id'],
                'current_stream_id' => $currentEnrollment['stream_id'],
                'promoted_to_class_id' => $toClassId,
                'promoted_to_stream_id' => $toStreamId,
                'from_enrollment_id' => $currentEnrollment['id'],
                'to_enrollment_id' => $newEnrollmentId,
                'from_academic_year_id' => $fromYearId,
                'to_academic_year_id' => $toYearId,
                'promotion_reason' => $remarks
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'student_id' => $studentId,
                'from_enrollment' => $currentEnrollment['id'],
                'to_enrollment' => $newEnrollmentId,
                'message' => 'Student promoted successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * SCENARIO 2: Promote multiple students to same class
     */
    public function promoteMultipleStudents(
        array $studentIds,
        int $toClassId,
        int $toStreamId,
        int $fromYearId,
        int $toYearId,
        int $performedBy,
        string $remarks = null
    ): array {
        $results = [
            'total' => count($studentIds),
            'promoted' => 0,
            'failed' => 0,
            'errors' => []
        ];

        // Create batch record
        $batchId = $this->createPromotionBatch([
            'batch_scope' => "Manual Promotion - " . date('Y-m-d H:i:s'),
            'academic_year_from' => $fromYearId,
            'academic_year_to' => $toYearId,
            'batch_type' => 'manual',
            'total_students_processed' => count($studentIds),
            'created_by' => $performedBy
        ]);

        foreach ($studentIds as $studentId) {
            try {
                $this->promoteSingleStudent(
                    $studentId,
                    $toClassId,
                    $toStreamId,
                    $fromYearId,
                    $toYearId,
                    $performedBy,
                    $remarks,
                    $batchId
                );
                $results['promoted']++;
            } catch (Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'student_id' => $studentId,
                    'error' => 'An internal error occurred.'
                ];
            }
        }

        // Update batch statistics
        $this->updatePromotionBatch($batchId, [
            'total_promoted' => $results['promoted'],
            'total_rejected' => $results['failed'],
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s')
        ]);

        return $results;
    }

    /**
     * SCENARIO 3: Promote entire class with teacher/room assignment
     */
    public function promoteEntireClass(
        int $fromClassId,
        int $fromStreamId,
        int $toClassId,
        int $toStreamId,
        int $fromYearId,
        int $toYearId,
        int $performedBy,
        int $teacherId = null,
        string $classRoom = null,
        string $remarks = null
    ): array {
        $this->db->beginTransaction();

        try {
            // Get all students in the class (excluding transferred)
            $students = $this->getClassStudents($fromClassId, $fromStreamId, $fromYearId);

            if (empty($students)) {
                throw new Exception("No students found in the specified class");
            }

            // Create class assignment for target class in new year
            if ($teacherId || $classRoom) {
                $this->createClassYearAssignment([
                    'class_id' => $toClassId,
                    'stream_id' => $toStreamId,
                    'academic_year_id' => $toYearId,
                    'class_teacher_id' => $teacherId,
                    'classroom' => $classRoom,
                    'status' => 'active'
                ]);
            }

            // Create batch record
            $batchId = $this->createPromotionBatch([
                'batch_scope' => "Class Promotion - " . $this->getClassName($fromClassId, $fromStreamId),
                'academic_year_from' => $fromYearId,
                'academic_year_to' => $toYearId,
                'batch_type' => 'single_class',
                'total_students_processed' => count($students),
                'created_by' => $performedBy
            ]);

            $results = [
                'total' => count($students),
                'promoted' => 0,
                'failed' => 0,
                'errors' => []
            ];

            // Promote each student
            foreach ($students as $student) {
                try {
                    $this->promoteSingleStudent(
                        $student['student_id'],
                        $toClassId,
                        $toStreamId,
                        $fromYearId,
                        $toYearId,
                        $performedBy,
                        $remarks,
                        $batchId
                    );
                    $results['promoted']++;
                } catch (Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'student_id' => $student['student_id'],
                        'error' => 'An internal error occurred.'
                    ];
                }
            }

            // Update batch
            $this->updatePromotionBatch($batchId, [
                'total_promoted' => $results['promoted'],
                'total_rejected' => $results['failed'],
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s')
            ]);

            $this->db->commit();
            return $results;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * SCENARIO 4: Bulk promote multiple classes
     * Example: Promote all Grades 1-8 at end of year
     */
    public function promoteMultipleClasses(
        array $classMap,
        int $fromYearId,
        int $toYearId,
        int $performedBy,
        string $remarks = null
    ): array {
        /**
         * $classMap format:
         * [
         *   ['from_class' => 1, 'from_stream' => 1, 'to_class' => 2, 'to_stream' => 1, 'teacher_id' => 5, 'classroom' => 'A1'],
         *   ['from_class' => 2, 'from_stream' => 1, 'to_class' => 3, 'to_stream' => 1, 'teacher_id' => 6, 'classroom' => 'A2'],
         *   ...
         * ]
         */

        $this->db->beginTransaction();

        try {
            $overallResults = [
                'classes_processed' => 0,
                'total_students' => 0,
                'promoted' => 0,
                'failed' => 0,
                'class_results' => []
            ];

            // Create master batch
            $batchId = $this->createPromotionBatch([
                'batch_scope' => "Bulk School Promotion {$fromYearId} -> {$toYearId}",
                'academic_year_from' => $fromYearId,
                'academic_year_to' => $toYearId,
                'batch_type' => 'bulk_grade',
                'total_students_processed' => 0, // Will update later
                'created_by' => $performedBy
            ]);

            foreach ($classMap as $mapping) {
                $classResult = $this->promoteEntireClass(
                    $mapping['from_class'],
                    $mapping['from_stream'],
                    $mapping['to_class'],
                    $mapping['to_stream'],
                    $fromYearId,
                    $toYearId,
                    $performedBy,
                    $mapping['teacher_id'] ?? null,
                    $mapping['classroom'] ?? null,
                    $remarks
                );

                $overallResults['classes_processed']++;
                $overallResults['total_students'] += $classResult['total'];
                $overallResults['promoted'] += $classResult['promoted'];
                $overallResults['failed'] += $classResult['failed'];
                $overallResults['class_results'][] = [
                    'class' => $this->getClassName($mapping['from_class'], $mapping['from_stream']),
                    'result' => $classResult
                ];
            }

            // Update master batch
            $this->updatePromotionBatch($batchId, [
                'total_students_processed' => $overallResults['total_students'],
                'total_promoted' => $overallResults['promoted'],
                'total_rejected' => $overallResults['failed'],
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s')
            ]);

            $this->db->commit();
            return $overallResults;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * SCENARIO 5: Graduate Grade 9 students to alumni
     */
    public function graduateGrade9Students(
        int $classId,
        int $streamId,
        int $academicYearId,
        int $performedBy,
        array $graduationData = []
    ): array {
        $this->db->beginTransaction();

        try {
            // Verify this is Grade 9
            $className = $this->getClassName($classId, $streamId);
            if (strpos($className, 'Grade 9') === false && strpos($className, '9') === false) {
                throw new Exception("This function is only for Grade 9 students");
            }

            // Get all Grade 9 students
            $students = $this->getClassStudents($classId, $streamId, $academicYearId);

            if (empty($students)) {
                throw new Exception("No students found in Grade 9");
            }

            $results = [
                'total' => count($students),
                'graduated' => 0,
                'failed' => 0,
                'errors' => []
            ];

            // Create batch
            $yearCode = $this->yearManager->getAcademicYear($academicYearId)['year_code'] ?? $academicYearId;
            $batchId = $this->createPromotionBatch([
                'batch_scope' => "Grade 9 Graduation - {$yearCode}",
                'academic_year_from' => $academicYearId,
                'academic_year_to' => $academicYearId,
                'batch_type' => 'bulk_grade',
                'total_students_processed' => count($students),
                'created_by' => $performedBy
            ]);

            foreach ($students as $student) {
                try {
                    // Get enrollment
                    $enrollment = $this->getCurrentEnrollment($student['student_id'], $academicYearId);

                    // Update enrollment to graduated
                    $this->updateEnrollmentPromotionStatus(
                        $enrollment['id'],
                        'graduated'
                    );

                    // Move to alumni table
                    $this->moveToAlumni([
                        'student_id' => $student['student_id'],
                        'graduation_date' => $graduationData['graduation_date'] ?? date('Y-m-d'),
                        'final_class_id' => $classId,
                        'final_stream_id' => $streamId,
                        'academic_year_id' => $academicYearId,
                        'final_average' => $enrollment['term3_average'] ?? $enrollment['overall_average'] ?? null,
                        'awards' => $graduationData['awards'][$student['student_id']] ?? null,
                        'honors' => $graduationData['honors'][$student['student_id']] ?? null,
                        'next_destination' => $graduationData['next_destination'][$student['student_id']] ?? null
                    ]);

                    $results['graduated']++;

                } catch (Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'student_id' => $student['student_id'],
                        'error' => 'An internal error occurred.'
                    ];
                }
            }

            // Update batch
            $this->updatePromotionBatch($batchId, [
                'total_promoted' => $results['graduated'],
                'total_rejected' => $results['failed'],
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s')
            ]);

            $this->db->commit();
            return $results;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getPromotionMeta(): array
    {
        $years = $this->db->query("
            SELECT id, year_code, year_name, is_current
            FROM academic_years
            ORDER BY is_current DESC, year_code DESC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $classes = $this->db->query("
            SELECT id, name
            FROM classes
            ORDER BY name ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $streams = $this->db->query("
            SELECT aycs.id, ayc.class_id, sm.name AS stream_name
            FROM academic_year_class_streams aycs
            JOIN streams sm ON sm.id = aycs.stream_id
            JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
            WHERE aycs.status = 'active'
            ORDER BY sm.name ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $terms = $this->db->query("
            SELECT ayt.id, t.name
            FROM academic_year_terms ayt
            JOIN terms t ON t.id = ayt.term_id
            ORDER BY ayt.opening_date ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'academic_years' => $years,
            'classes' => $classes,
            'streams' => $streams,
            'terms' => $terms,
            'promotion_rules' => ['promote_all', 'promote_passed', 'repeat_failed', 'custom'],
            'statuses' => ['pending_approval', 'approved', 'rejected', 'transferred', 'retained', 'graduated'],
        ];
    }

    public function getPromotionCandidates(array $filters = []): array
    {
        $fromYearId = !empty($filters['from_academic_year_id']) ? (int)$filters['from_academic_year_id'] : null;
        $fromClassId = !empty($filters['from_class_id']) ? (int)$filters['from_class_id'] : null;
        $fromStreamId = !empty($filters['from_stream_id']) ? (int)$filters['from_stream_id'] : null;
        $search = !empty($filters['search']) ? trim((string)$filters['search']) : '';

        $sql = "
            SELECT
                s.id,
                s.admission_no,
                CONCAT_WS(' ', per.first_name, per.middle_name, per.last_name) AS full_name,
                c.name AS current_class,
                sm.name AS current_stream,
                sae.academic_year_class_stream_id AS stream_id,
                ay.year_code AS current_year,
                s.status AS student_status
            FROM students s
            JOIN persons per ON per.id = s.person_id
            LEFT JOIN student_academic_enrollments sae 
                ON sae.student_id = s.id AND sae.enrollment_status = 'active'
            LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
            LEFT JOIN streams sm ON sm.id = aycs.stream_id
            LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
            LEFT JOIN classes c ON c.id = ayc.class_id
            LEFT JOIN academic_years ay ON ay.id = sae.academic_year_id
            WHERE s.status = 'active'
        ";
        $bindings = [];

        if ($fromClassId) {
            $sql .= " AND c.id = ?";
            $bindings[] = $fromClassId;
        }
        if ($fromStreamId) {
            $sql .= " AND aycs.stream_id = ?";
            $bindings[] = $fromStreamId;
        }
        if ($fromYearId) {
            $sql .= " AND ay.id = ?";
            $bindings[] = $fromYearId;
        }
        if ($search !== '') {
            $sql .= " AND (s.admission_no LIKE ? OR per.first_name LIKE ? OR per.last_name LIKE ?)";
            $term = '%' . $search . '%';
            array_push($bindings, $term, $term, $term);
        }

        $sql .= " ORDER BY per.first_name, per.last_name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function executePromotionV2(array $data, int $performedBy): array
    {
        $fromYearId = !empty($data['from_academic_year_id']) ? (int)$data['from_academic_year_id'] : 0;
        $toYearId = !empty($data['to_academic_year_id']) ? (int)$data['to_academic_year_id'] : 0;
        $fromTermId = !empty($data['from_term_id']) ? (int)$data['from_term_id'] : 0;
        $toClassId = !empty($data['to_class_id']) ? (int)$data['to_class_id'] : 0;
        $toStreamId = !empty($data['to_stream_id']) ? (int)$data['to_stream_id'] : 0;
        $students = !empty($data['students']) ? (array)$data['students'] : [];
        $notes = $data['notes'] ?? null;

        if (!$fromYearId || !$toYearId || !$students) {
            throw new Exception('Required fields: from_academic_year_id, to_academic_year_id, students');
        }

        $fromYear = $this->getYearValueFromId($fromYearId);
        $toYear = $this->getYearValueFromId($toYearId);
        if (!$fromYear || !$toYear) {
            throw new Exception('Selected academic years do not contain valid YEAR values');
        }

        if (!$fromTermId) {
            $fromTermId = $this->getCurrentTermId($fromYear);
        }
        if (!$fromTermId) {
            throw new Exception('Current academic term could not be resolved');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO promotion_batches
                    (from_academic_year, to_academic_year, batch_type, batch_scope, status, created_by, notes)
                VALUES (?, ?, 'manual', 'Manual Promotion V2', 'in_progress', ?, ?)
            ");
            $stmt->execute([$fromYear, $toYear, $performedBy, $notes]);
            $batchId = (int)$this->db->lastInsertId();

            $promoted = 0;
            $retained = 0;
            $processed = 0;

            foreach ($students as $studentData) {
                if (!is_array($studentData) || empty($studentData['student_id'])) {
                    continue;
                }

                $studentId = (int)$studentData['student_id'];
                $finalAction = $studentData['final_action'] ?? 'promote';
                $studentNotes = $studentData['notes'] ?? null;

                $enrollment = $this->getCurrentEnrollment($studentId, $fromYearId);
                if (!$enrollment) {
                    continue;
                }

                $targetClassId = $toClassId ?: (int)$enrollment['class_id'];
                $targetStreamId = $toStreamId ?: (int)$enrollment['stream_id'];
                $toEnrollmentId = null;

                if ($finalAction === 'promote') {
                    $toEnrollmentId = $this->getCurrentEnrollment($studentId, $toYearId)['id'] ?? null;

                    if ($toEnrollmentId) {
                        // Update existing enrollment's stream
                        $aycsId = $this->resolveAcademicYearClassStreamId(
                            $targetClassId, $targetStreamId, $toYearId
                        );
                        $stmt = $this->db->prepare("
                            UPDATE student_academic_enrollments
                            SET academic_year_class_stream_id = ?, enrollment_status = 'active'
                            WHERE id = ?
                        ");
                        $stmt->execute([$aycsId, $toEnrollmentId]);
                    } else {
                        $toEnrollmentId = $this->createEnrollment([
                            'student_id' => $studentId,
                            'academic_year_id' => $toYearId,
                            'class_id' => $targetClassId,
                            'stream_id' => $targetStreamId,
                            'enrollment_status' => 'active',
                            'enrollment_date' => date('Y-m-d'),
                        ]);
                    }

                    $this->updateEnrollmentPromotionStatus($enrollment['id'], 'promoted');
                    $promoted++;
                } else {
                    $this->updateEnrollmentPromotionStatus($enrollment['id'], 'retained');
                    $retained++;
                }

                $stmt = $this->db->prepare("
                    INSERT INTO student_transitions (
                        student_id, from_student_academic_enrollment_id, to_student_academic_enrollment_id,
                        academic_year_id, transition_type, reason, decided_by, decided_at
                    ) VALUES (?, ?, ?, ?, 'promotion', ?, ?, NOW())
                ");
                $stmt->execute([
                    $studentId,
                    $enrollment['id'],
                    $toEnrollmentId,
                    $toYearId,
                    $studentNotes,
                    $performedBy,
                ]);
                $processed++;
            }

            $stmt = $this->db->prepare("
                UPDATE promotion_batches
                SET status = 'completed',
                    total_students_processed = ?,
                    total_promoted = ?,
                    total_rejected = ?,
                    completed_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$processed, $promoted, $retained, $batchId]);

            $this->db->commit();
            return [
                'message' => "Promotion completed successfully. {$promoted} promoted, {$retained} retained.",
                'batch_id' => $batchId,
                'processed' => $processed,
                'promoted' => $promoted,
                'retained' => $retained,
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    // ==================== HELPER METHODS ====================

    private function getStudentStatus(int $studentId): ?array
    {
        $stmt = $this->db->prepare("SELECT id, status FROM students WHERE id = ?");
        $stmt->execute([$studentId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    private function getCurrentEnrollment(int $studentId, int $yearId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT sae.*, ayc.class_id, aycs.stream_id
            FROM student_academic_enrollments sae
            LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
            LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
            WHERE sae.student_id = ? AND sae.academic_year_id = ?
            AND sae.enrollment_status IN ('active')
            LIMIT 1
        ");
        $stmt->execute([$studentId, $yearId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    private function verifyClassStream(int $classId, int $streamId): bool
    {
        $stmt = $this->db->prepare("
            SELECT aycs.id FROM academic_year_class_streams aycs
            JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
            WHERE ayc.class_id = ? AND aycs.id = ?
        ");
        $stmt->execute([$classId, $streamId]);

        if (!$stmt->fetch()) {
            throw new Exception("Invalid class/stream combination");
        }
        return true;
    }

    private function createEnrollment(array $data): int
    {
        // Resolve academic_year_class_stream_id from class + stream
        $aycsId = $this->resolveAcademicYearClassStreamId(
            $data['class_id'],
            $data['stream_id'],
            $data['academic_year_id']
        );

        $sql = "INSERT INTO student_academic_enrollments (
            student_id, academic_year_id, academic_year_class_stream_id,
            enrollment_status, enrolled_on
        ) VALUES (?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['student_id'],
            $data['academic_year_id'],
            $aycsId,
            $data['enrollment_status'] ?? 'active',
            $data['enrollment_date'] ?? date('Y-m-d')
        ]);

        return $this->db->lastInsertId();
    }

    private function updateEnrollmentPromotionStatus(
        int $enrollmentId,
        string $status,
        int $toClassId = 0,
        int $toStreamId = 0
    ): bool {
        // Update enrollment status — promotion details go to student_promotions table
        $newStatus = 'active';
        if ($status === 'graduated') {
            $newStatus = 'completed';
        } elseif ($status === 'promoted') {
            $newStatus = 'completed';
        }

        $sql = "UPDATE student_academic_enrollments
                SET enrollment_status = ?
                WHERE id = ?";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$newStatus, $enrollmentId]);
    }

    private function recordPromotion(array $data): int
    {
        // The legacy `student_promotions` table does not exist. The canonical
        // transition log is `student_transitions` (manual id), which is what
        // vw_current_enrollments joins for promotion status.
        $fromYear = $this->getYearValueFromId($data['from_academic_year_id'] ?? 0);
        $termId   = $this->getCurrentTermId($fromYear ?: (int)date('Y'));

        $stmt = $this->db->prepare("
            INSERT INTO student_transitions (
                student_id, from_student_academic_enrollment_id, to_student_academic_enrollment_id,
                academic_year_id, transition_type, reason, decided_by, decided_at
            ) VALUES (?, ?, ?, ?, 'promotion', ?, ?, ?)
        ");
        $stmt->execute([
            $data['student_id'],
            $data['from_enrollment_id'] ?? null,
            $data['to_enrollment_id'] ?? null,
            $data['to_academic_year_id'] ?? $data['from_academic_year_id'] ?? null,
            $data['promotion_reason'] ?? null,
            $data['approved_by'] ?? null,
            date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function getClassStudents(int $classId, int $streamId, int $yearId): array
    {
        $stmt = $this->db->prepare("
            SELECT sae.*, s.status as student_status, ayc.class_id, aycs.stream_id
            FROM student_academic_enrollments sae
            JOIN students s ON sae.student_id = s.id
            JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
            JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
            WHERE ayc.class_id = ?
            AND aycs.stream_id = ?
            AND sae.academic_year_id = ?
            AND sae.enrollment_status = 'active'
            AND s.status != 'transferred'
        ");
        $stmt->execute([$classId, $streamId, $yearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getClassName(int $classId, int $streamId): string
    {
        $stmt = $this->db->prepare("
            SELECT CONCAT(c.name, ' ', sm.name) as full_name
            FROM classes c
            JOIN academic_year_classes ayc ON ayc.class_id = c.id
            JOIN academic_year_class_streams aycs ON aycs.academic_year_class_id = ayc.id
            JOIN streams sm ON sm.id = aycs.stream_id
            WHERE c.id = ? AND aycs.id = ?
            LIMIT 1
        ");
        $stmt->execute([$classId, $streamId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['full_name'] : "Unknown Class";
    }

    private function createClassYearAssignment(array $data): int
    {
        // Ensure academic_year_classes entry exists
        $ayc = $this->resolveOrCreateAcademicYearClass(
            $data['class_id'],
            $data['academic_year_id'],
            $data['status'] ?? 'active'
        );

        // Ensure academic_year_class_streams entry exists
        $aycs = $this->resolveAcademicYearClassStreamId(
            $data['class_id'],
            $data['stream_id'],
            $data['academic_year_id']
        );

        if ($aycs > 0) {
            $assignments = [];
            $values = [];
            if (!empty($data['class_teacher_id'])) {
                $assignments[] = "class_teacher_id = ?";
                $values[] = $data['class_teacher_id'];
            }
            if (!empty($data['classroom'])) {
                $roomId = is_numeric($data['classroom'])
                    ? (int) $data['classroom']
                    : $this->resolveRoomId($data['classroom']);
                if ($roomId > 0) {
                    $assignments[] = "room_id = ?";
                    $values[] = $roomId;
                }
            }
            if (!empty($assignments)) {
                $values[] = $aycs;
                $stmt = $this->db->prepare(
                    "UPDATE academic_year_class_streams SET " . implode(', ', $assignments) . " WHERE id = ?"
                );
                $stmt->execute($values);
            }
        }

        return $aycs ?: 0;
    }

    private function resolveRoomId(string $roomName): int
    {
        $stmt = $this->db->prepare("
            SELECT id FROM rooms WHERE name = ? LIMIT 1
        ");
        $stmt->execute([$roomName]);
        $roomId = $stmt->fetchColumn();
        return $roomId ? (int) $roomId : 0;
    }

    private function resolveOrCreateAcademicYearClass(int $classId, int $yearId, string $status = 'active'): int
    {
        $stmt = $this->db->prepare("
            SELECT id FROM academic_year_classes
            WHERE class_id = ? AND academic_year_id = ?
            LIMIT 1
        ");
        $stmt->execute([$classId, $yearId]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            return (int)$existing;
        }

        $stmt = $this->db->prepare("
            INSERT INTO academic_year_classes (class_id, academic_year_id, status)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$classId, $yearId, $status]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Resolve or create an academic_year_class_streams entry from class_id, stream_id, and year_id.
     * Returns the academic_year_class_streams.id.
     */
    private function resolveAcademicYearClassStreamId(int $classId, int $streamId, int $yearId): int
    {
        $aycId = $this->resolveOrCreateAcademicYearClass($classId, $yearId);

        $stmt = $this->db->prepare("
            SELECT id FROM academic_year_class_streams
            WHERE academic_year_class_id = ? AND stream_id = ?
            LIMIT 1
        ");
        $stmt->execute([$aycId, $streamId]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            return (int)$existing;
        }

        $stmt = $this->db->prepare("
            INSERT INTO academic_year_class_streams (academic_year_class_id, stream_id, status)
            VALUES (?, ?, 'active')
        ");
        $stmt->execute([$aycId, $streamId]);
        return (int)$this->db->lastInsertId();
    }

    private function createPromotionBatch(array $data): int
    {
        $fromYear = $this->getYearValueFromId($data['academic_year_from'] ?? 0);
        $toYear   = $this->getYearValueFromId($data['academic_year_to'] ?? 0);

        $sql = "INSERT INTO promotion_batches (
            batch_scope, from_academic_year, to_academic_year,
            batch_type, total_students_processed, created_by, status
        ) VALUES (?, ?, ?, ?, ?, ?, 'in_progress')";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['batch_scope'] ?? null,
            $fromYear ?: $data['academic_year_from'],
            $toYear   ?: $data['academic_year_to'] ?? $fromYear,
            $data['batch_type'],
            $data['total_students_processed'] ?? 0,
            $data['created_by']
        ]);

        return $this->db->lastInsertId();
    }

    /** Resolve a 4-digit YEAR value from an academic_years.id */
    private function getYearValueFromId(int $yearId): ?int
    {
        if ($yearId <= 0) return null;
        $stmt = $this->db->prepare(
            "SELECT CAST(SUBSTRING(year_code, 1, 4) AS UNSIGNED) AS yr FROM academic_years WHERE id = ?"
        );
        $stmt->execute([$yearId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['yr'] : null;
    }

    /** Get the current/last-completed term id for a given calendar year */
    private function getCurrentTermId(int $calYear): int
    {
        $stmt = $this->db->prepare(
            "SELECT ayt.id FROM academic_year_terms ayt
             JOIN academic_years ay ON ay.id = ayt.academic_year_id
             WHERE CAST(SUBSTRING(ay.year_code, 1, 4) AS UNSIGNED) = ?
             ORDER BY FIELD(ayt.status,'current','completed','upcoming'),
                       (SELECT code FROM terms WHERE id = ayt.term_id) DESC
             LIMIT 1"
        );
        $stmt->execute([$calYear]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : 1; // fallback to 1 if none found
    }

    private function updatePromotionBatch(int $batchId, array $data): bool
    {
        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            $fields[] = "{$key} = ?";
            $values[] = $value;
        }

        $values[] = $batchId;

        $sql = "UPDATE promotion_batches SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    private function moveToAlumni(array $data): int
    {
        // The legacy `alumni` table does not exist; graduations are recorded as
        // a 'graduation' transition and the student record is marked graduated.
        $stmt = $this->db->prepare("
            UPDATE students SET status = 'graduated', updated_at = NOW() WHERE id = ?
        ");
        $stmt->execute([$data['student_id']]);

        $awards = !empty($data['awards']) ? ' Awards: ' . $data['awards'] : '';
        $destination = !empty($data['next_destination']) ? ' Destination: ' . $data['next_destination'] : '';

        $stmt = $this->db->prepare("
            INSERT INTO student_transitions (
                student_id, from_student_academic_enrollment_id, to_student_academic_enrollment_id,
                academic_year_id, transition_type, reason, decided_by, decided_at
            ) VALUES (?, ?, NULL, ?, 'graduation', ?, ?, NOW())
        ");
        $stmt->execute([
            $data['student_id'],
            null,
            $data['academic_year_id'],
            'Graduated on ' . ($data['graduation_date'] ?? date('Y-m-d')) . $awards . $destination,
            null,
        ]);

        return (int) $this->db->lastInsertId();
    }
}
