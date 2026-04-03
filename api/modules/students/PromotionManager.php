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
            if ($currentEnrollment['promotion_status'] !== 'pending') {
                throw new Exception("Student already {$currentEnrollment['promotion_status']}");
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
                'enrollment_status' => 'enrolled',
                'enrollment_date' => date('Y-m-d'),
                'promotion_status' => 'pending'
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
                    'error' => $e->getMessage()
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
                        'error' => $e->getMessage()
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
                        'error' => $e->getMessage()
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
            SELECT * FROM class_enrollments 
            WHERE student_id = ? AND academic_year_id = ? 
            AND enrollment_status IN ('enrolled', 'active')
            LIMIT 1
        ");
        $stmt->execute([$studentId, $yearId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    private function verifyClassStream(int $classId, int $streamId): bool
    {
        $stmt = $this->db->prepare("
            SELECT cs.id FROM class_streams cs
            JOIN classes c ON cs.class_id = c.id
            WHERE c.id = ? AND cs.id = ?
        ");
        $stmt->execute([$classId, $streamId]);

        if (!$stmt->fetch()) {
            throw new Exception("Invalid class/stream combination");
        }
        return true;
    }

    private function createEnrollment(array $data): int
    {
        $sql = "INSERT INTO class_enrollments (
            student_id, academic_year_id, class_id, stream_id,
            enrollment_status, enrollment_date, promotion_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['student_id'],
            $data['academic_year_id'],
            $data['class_id'],
            $data['stream_id'],
            $data['enrollment_status'],
            $data['enrollment_date'],
            $data['promotion_status']
        ]);

        return $this->db->lastInsertId();
    }

    private function updateEnrollmentPromotionStatus(
        int $enrollmentId,
        string $status,
        int $toClassId = 0,
        int $toStreamId = 0
    ): bool {
        $sql = "UPDATE class_enrollments
                SET promotion_status = ?,
                    promoted_to_class_id = NULLIF(?, 0),
                    promoted_to_stream_id = NULLIF(?, 0),
                    promotion_date = CURDATE()
                WHERE id = ?";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$status, $toClassId, $toStreamId, $enrollmentId]);
    }

    private function recordPromotion(array $data): int
    {
        // Resolve YEAR(4) values from academic_year IDs
        $fromYear = $this->getYearValueFromId($data['from_academic_year_id'] ?? 0);
        $toYear   = $this->getYearValueFromId($data['to_academic_year_id'] ?? 0);
        $termId   = $this->getCurrentTermId($fromYear ?: (int)date('Y'));

        $sql = "INSERT INTO student_promotions (
            batch_id, student_id,
            current_class_id, current_stream_id,
            promoted_to_class_id, promoted_to_stream_id,
            from_enrollment_id, to_enrollment_id,
            from_academic_year_id, to_academic_year_id,
            from_academic_year, to_academic_year,
            from_term_id, promotion_status, promotion_reason
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_approval', ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['batch_id'],
            $data['student_id'],
            $data['current_class_id'],
            $data['current_stream_id'],
            $data['promoted_to_class_id'] ?? null,
            $data['promoted_to_stream_id'] ?? null,
            $data['from_enrollment_id'] ?? null,
            $data['to_enrollment_id'] ?? null,
            $data['from_academic_year_id'] ?? null,
            $data['to_academic_year_id'] ?? null,
            $fromYear,
            $toYear,
            $termId,
            $data['promotion_reason'] ?? null
        ]);

        return $this->db->lastInsertId();
    }

    private function getClassStudents(int $classId, int $streamId, int $yearId): array
    {
        $stmt = $this->db->prepare("
            SELECT ce.*, s.status as student_status
            FROM class_enrollments ce
            JOIN students s ON ce.student_id = s.id
            WHERE ce.class_id = ? 
            AND ce.stream_id = ? 
            AND ce.academic_year_id = ?
            AND ce.enrollment_status IN ('enrolled', 'active')
            AND ce.promotion_status = 'pending'
            AND s.status != 'transferred'
        ");
        $stmt->execute([$classId, $streamId, $yearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getClassName(int $classId, int $streamId): string
    {
        $stmt = $this->db->prepare("
            SELECT CONCAT(c.name, ' ', cs.name) as full_name
            FROM classes c
            JOIN class_streams cs ON c.id = cs.class_id
            WHERE c.id = ? AND cs.id = ?
        ");
        $stmt->execute([$classId, $streamId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['full_name'] : "Unknown Class";
    }

    private function createClassYearAssignment(array $data): int
    {
        $sql = "INSERT INTO class_year_assignments (
            class_id, stream_id, academic_year_id, class_teacher_id,
            classroom, capacity, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            class_teacher_id = VALUES(class_teacher_id),
            classroom = VALUES(classroom)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['class_id'],
            $data['stream_id'],
            $data['academic_year_id'],
            $data['class_teacher_id'] ?? null,
            $data['classroom'] ?? null,
            $data['capacity'] ?? null,
            $data['status'] ?? 'active'
        ]);

        return $this->db->lastInsertId() ?: $this->getClassYearAssignmentId(
            $data['class_id'],
            $data['stream_id'],
            $data['academic_year_id']
        );
    }

    private function getClassYearAssignmentId(int $classId, int $streamId, int $yearId): int
    {
        $stmt = $this->db->prepare("
            SELECT id FROM class_year_assignments
            WHERE class_id = ? AND stream_id = ? AND academic_year_id = ?
        ");
        $stmt->execute([$classId, $streamId, $yearId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['id'] : 0;
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
            "SELECT id FROM academic_terms
             WHERE year = ?
             ORDER BY FIELD(status,'current','completed','upcoming'), term_number DESC
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
        $sql = "INSERT INTO alumni (
            student_id, graduation_date, final_class_id, final_stream_id,
            academic_year_id, final_average, awards, honors, next_destination
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['student_id'],
            $data['graduation_date'],
            $data['final_class_id'],
            $data['final_stream_id'],
            $data['academic_year_id'],
            $data['final_average'],
            $data['awards'],
            $data['honors'],
            $data['next_destination']
        ]);

        return $this->db->lastInsertId();
    }
}
