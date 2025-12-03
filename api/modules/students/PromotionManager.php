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
        string $remarks = null
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

            // Update current enrollment status
            $this->updateEnrollmentPromotionStatus(
                $currentEnrollment['id'],
                'promoted',
                $performedBy,
                $remarks
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

            // Record in student_promotions table (legacy compatibility)
            $this->recordPromotion([
                'student_id' => $studentId,
                'from_class' => $currentEnrollment['class_id'],
                'to_class' => $toClassId,
                'from_enrollment_id' => $currentEnrollment['id'],
                'to_enrollment_id' => $newEnrollmentId,
                'academic_year_id' => $toYearId,
                'promotion_date' => date('Y-m-d'),
                'promoted_by' => $performedBy,
                'remarks' => $remarks
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
            'batch_name' => "Manual Promotion - " . date('Y-m-d H:i:s'),
            'academic_year_from' => $fromYearId,
            'academic_year_to' => $toYearId,
            'promotion_type' => 'multiple_students',
            'total_students' => count($studentIds),
            'initiated_by' => $performedBy
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
                    $remarks
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
            'students_promoted' => $results['promoted'],
            'students_retained' => 0,
            'students_graduated' => 0,
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
                'batch_name' => "Class Promotion - " . $this->getClassName($fromClassId, $fromStreamId),
                'academic_year_from' => $fromYearId,
                'academic_year_to' => $toYearId,
                'promotion_type' => 'entire_class',
                'total_students' => count($students),
                'initiated_by' => $performedBy
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
                        $remarks
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
                'students_promoted' => $results['promoted'],
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
                'batch_name' => "Bulk School Promotion {$fromYearId} -> {$toYearId}",
                'academic_year_from' => $fromYearId,
                'academic_year_to' => $toYearId,
                'promotion_type' => 'bulk_school',
                'total_students' => 0, // Will update later
                'initiated_by' => $performedBy
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
                'total_students' => $overallResults['total_students'],
                'students_promoted' => $overallResults['promoted'],
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
            $batchId = $this->createPromotionBatch([
                'batch_name' => "Grade 9 Graduation - " . $this->yearManager->getAcademicYear($academicYearId)['year_code'],
                'academic_year_from' => $academicYearId,
                'academic_year_to' => null,
                'promotion_type' => 'graduation',
                'total_students' => count($students),
                'initiated_by' => $performedBy
            ]);

            foreach ($students as $student) {
                try {
                    // Get enrollment
                    $enrollment = $this->getCurrentEnrollment($student['student_id'], $academicYearId);

                    // Update enrollment to graduated
                    $this->updateEnrollmentPromotionStatus(
                        $enrollment['id'],
                        'graduated',
                        $performedBy,
                        'Completed Grade 9'
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
                'students_graduated' => $results['graduated'],
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
        int $performedBy,
        string $remarks = null
    ): bool {
        $sql = "UPDATE class_enrollments 
                SET promotion_status = ?,
                    promoted_by = ?,
                    promotion_remarks = ?,
                    promoted_at = NOW()
                WHERE id = ?";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$status, $performedBy, $remarks, $enrollmentId]);
    }

    private function recordPromotion(array $data): int
    {
        $sql = "INSERT INTO student_promotions (
            student_id, from_class, to_class, from_enrollment_id,
            to_enrollment_id, academic_year_id, promotion_date,
            promoted_by, remarks
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['student_id'],
            $data['from_class'],
            $data['to_class'],
            $data['from_enrollment_id'],
            $data['to_enrollment_id'],
            $data['academic_year_id'],
            $data['promotion_date'],
            $data['promoted_by'],
            $data['remarks']
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
        $sql = "INSERT INTO promotion_batches (
            batch_name, academic_year_from, academic_year_to,
            promotion_type, total_students, initiated_by, status
        ) VALUES (?, ?, ?, ?, ?, ?, 'in_progress')";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['batch_name'],
            $data['academic_year_from'],
            $data['academic_year_to'] ?? null,
            $data['promotion_type'],
            $data['total_students'],
            $data['initiated_by']
        ]);

        return $this->db->lastInsertId();
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
