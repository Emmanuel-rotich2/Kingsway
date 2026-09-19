<?php
namespace App\API\Modules\academic;

use App\API\Includes\WorkflowHandler;
use Exception;
use PDO;
use function App\API\Includes\formatResponse;
/**
 * Academic Year Transition Workflow - CBC-Compliant
 * 
 * Manages the critical transition from one academic year to the next.
 * Ensures continuity of CBC competency baselines and proper data archival.
 * 
 * Transition Checklist:
 * - Archive previous year data (reports, assessments, competencies)
 * - Promote all students to next grade
 * - Create new academic year structure (terms, classes, streams)
 * - Migrate competency baselines for continued tracking
 * - Setup new assessment cycles
 * - Initialize attendance registers
 * - Configure new academic calendar
 * 
 * CBC-Specific Considerations:
 * - Competency progression tracking across years
 * - Performance level trends analysis
 * - Learning outcomes continuity
 * - Promotion criteria based on CBC assessment
 * 
 * Workflow Stages:
 * 1. Prepare Calendar - Create academic calendar for new year
 * 2. Archive Data - Archive previous year records
 * 3. Execute Promotions - Promote students in bulk
 * 4. Setup New Year - Create classes, terms, structures
 * 5. Migrate Baselines - Transfer competency baselines
 * 6. Validate Readiness - Final checks before go-live
 */
class AcademicYearTransitionWorkflow extends WorkflowHandler
{

    public function __construct()
    {
        parent::__construct('academic_year_transition');
    }

    protected function getWorkflowDefinitionCode(): string
    {
        return 'academic_year_transition';
    }

    /** Return the starting year from 2026, 2026/2027, or a year record id. */
    private function yearStart($value): int
    {
        $text = trim((string) $value);
        if (preg_match('/^(\d{4})\/\d{4}$/', $text, $match)) {
            return (int) $match[1];
        }
        if (preg_match('/^(\d{4})$/', $text)) {
            return (int) $text;
        }
        return 0;
    }

    private function yearCode($value): string
    {
        $start = $this->yearStart($value);
        return $start > 0 ? $start . '/' . ($start + 1) : '';
    }

    /** Resolve an academic_years.id using the canonical YYYY/YYYY+1 code. */
    private function resolveYearIdFromCode($yearValue): int
    {
        $code = $this->yearCode($yearValue);
        $stmt = $this->db->prepare(
            "SELECT id FROM academic_years
             WHERE year_code = ? OR year_code = ?
             ORDER BY is_current DESC, id DESC LIMIT 1"
        );
        $stmt->execute([$code, (string) $yearValue]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /** Complete the setup stages performed by setupNewYear in one resumable action. */
    private function advanceNewYearSetupStages(int $instanceId, array $data, string $summary): void
    {
        $this->advanceStage($instanceId, 'configure_classes_streams', $summary, $data);
        $this->advanceStage($instanceId, 'configure_learning_areas', 'Learning areas, strands and substrands prepared', $data);
        $this->advanceStage($instanceId, 'configure_teachers', 'Class and subject teacher context prepared', $data);
        $this->advanceStage($instanceId, 'prepare_fee_structures', 'Fee structures copied as drafts', $data);
    }

    /**
     * Ensure a global terms row exists for the given term name/number.
     * Returns the terms.id.
     */
    private function ensureTermId(string $termName, int $termNumber): int
    {
        $code = 'T' . $termNumber;
        $stmt = $this->db->prepare("SELECT id FROM terms WHERE code = ? LIMIT 1");
        $stmt->execute([$code]);
        $id = (int) ($stmt->fetchColumn() ?: 0);
        if (!$id) {
            $stmt = $this->db->prepare("INSERT INTO terms (name, code) VALUES (?, ?)");
            $stmt->execute([$termName, $code]);
            $id = (int) $this->db->lastInsertId();
        }
        return $id;
    }

    /**
     * Reuse-or-create a streams row and link it to an academic year class.
     * Returns the academic_year_class_streams.id.
     */
    private function createStreamLink(int $aycId, array $stream): int
    {
        $name = trim((string) ($stream['name'] ?? ''));
        if ($name === '') {
            throw new Exception('Stream name is required');
        }
        $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 6));
        $capacity = (int) ($stream['capacity'] ?? 40);
        if ($capacity <= 0) {
            $capacity = 40;
        }

        $stmt = $this->db->prepare("SELECT id, capacity FROM streams WHERE name = ? LIMIT 1");
        $stmt->execute([$name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $streamId = $row ? (int) $row['id'] : 0;

        if (!$streamId) {
            $stmt = $this->db->prepare("INSERT INTO streams (name, code, capacity) VALUES (?, ?, ?)");
            $stmt->execute([$name, $code, $capacity]);
            $streamId = (int) $this->db->lastInsertId();
        } elseif ((int) $row['capacity'] !== $capacity) {
            $stmt = $this->db->prepare("UPDATE streams SET code = ?, capacity = ? WHERE id = ?");
            $stmt->execute([$code, $capacity, $streamId]);
        }

        $stmt = $this->db->prepare("
            SELECT id FROM academic_year_class_streams
            WHERE academic_year_class_id = ? AND stream_id = ?
            LIMIT 1
        ");
        $stmt->execute([$aycId, $streamId]);
        $aycsId = (int) ($stmt->fetchColumn() ?: 0);

        if (!$aycsId) {
            $stmt = $this->db->prepare("
                INSERT INTO academic_year_class_streams (academic_year_class_id, stream_id, room_id, class_teacher_id, status)
                VALUES (?, ?, NULL, NULL, 'active')
            ");
            $stmt->execute([$aycId, $streamId]);
            $aycsId = (int) $this->db->lastInsertId();
        }

        return $aycsId;
    }

    /**
     * Stage 1: Prepare academic calendar
     * 
     * @param array $calendar {
     *   @type int $from_year Previous academic year
     *   @type int $to_year New academic year
     *   @type string $year_start_date New year start date
     *   @type string $year_end_date New year end date
     *   @type array $terms Array of term definitions {
     *     @type string $term_name Term name (e.g., "Term 1")
     *     @type string $start_date Term start date
     *     @type string $end_date Term end date
     *     @type int $weeks Duration in weeks
     *   }
     *   @type array $holidays Public holidays and breaks
     *   @type string $transition_notes Notes about the transition
     * }
     * @return array Response with workflow instance
     */
    public function prepareCalendar(array $calendar): array
    {
        try {
            // Validation
            $required = ['from_year', 'to_year', 'year_start_date', 'year_end_date', 'terms'];
            foreach ($required as $field) {
                if (!isset($calendar[$field])) {
                    return formatResponse(false, null, "Missing required field: $field");
                }
            }

            $fromYear = $this->yearStart($calendar['from_year']);
            $toYear = $this->yearStart($calendar['to_year']);
            $fromYearCode = $this->yearCode($fromYear);
            $toYearCode = $this->yearCode($toYear);
            if (!$fromYear || !$toYear) {
                return formatResponse(false, null, 'Academic years must use YYYY or YYYY/YYYY+1 format.');
            }

            if (!is_array($calendar['terms']) || count($calendar['terms']) !== 3) {
                return formatResponse(false, null, 'Exactly three term date ranges are required.');
            }

            $parseDate = static function ($value, string $label): string {
                $value = trim((string) $value);
                $date = \DateTime::createFromFormat('!Y-m-d', $value);
                $errors = \DateTime::getLastErrors();
                if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                    throw new Exception("{$label} must be a valid YYYY-MM-DD date");
                }
                return $date->format('Y-m-d');
            };

            $yearStart = $parseDate($calendar['year_start_date'], 'Academic year opening date');
            $yearEnd = $parseDate($calendar['year_end_date'], 'Academic year closing date');
            if ($yearStart >= $yearEnd) {
                return formatResponse(false, null, 'Academic year opening date must be before its closing date.');
            }

            $calendar['terms'] = array_values($calendar['terms']);
            $previousTermEnd = null;
            foreach ($calendar['terms'] as $index => &$term) {
                $termStart = $parseDate($term['start_date'] ?? $term['opening_date'] ?? '', 'Term ' . ($index + 1) . ' opening date');
                $termEnd = $parseDate($term['end_date'] ?? $term['closing_date'] ?? '', 'Term ' . ($index + 1) . ' closing date');
                if ($termStart >= $termEnd) {
                    return formatResponse(false, null, 'Term ' . ($index + 1) . ' opening date must be before its closing date.');
                }
                if ($termStart < $yearStart || $termEnd > $yearEnd) {
                    return formatResponse(false, null, 'Every term must fall within the academic year dates.');
                }
                if ($previousTermEnd !== null && $termStart <= $previousTermEnd) {
                    return formatResponse(false, null, 'Term dates must be chronological and must not overlap.');
                }
                $hasHalfTerm = filter_var(
                    $term['has_half_term'] ?? (($term['half_term_start'] ?? '') !== '' || ($term['half_term_end'] ?? '') !== ''),
                    FILTER_VALIDATE_BOOLEAN
                );
                $halfTermStart = null;
                $halfTermEnd = null;
                if ($hasHalfTerm) {
                    if (empty($term['half_term_start']) || empty($term['half_term_end'])) {
                        return formatResponse(false, null, 'Term ' . ($index + 1) . ' half-term opening and closing dates are required when a half-term break is enabled.');
                    }
                    $halfTermStart = $parseDate($term['half_term_start'], 'Term ' . ($index + 1) . ' half-term opening date');
                    $halfTermEnd = $parseDate($term['half_term_end'], 'Term ' . ($index + 1) . ' half-term closing date');
                    if ($halfTermStart < $termStart || $halfTermEnd > $termEnd || $halfTermStart > $halfTermEnd) {
                        return formatResponse(false, null, 'Term ' . ($index + 1) . ' half-term dates must fall within the term and be chronological.');
                    }
                }
                $term['start_date'] = $termStart;
                $term['end_date'] = $termEnd;
                $term['has_half_term'] = $hasHalfTerm;
                $term['half_term_start'] = $halfTermStart;
                $term['half_term_end'] = $halfTermEnd;
                $previousTermEnd = $termEnd;
            }
            unset($term);
            $calendar['year_start_date'] = $yearStart;
            $calendar['year_end_date'] = $yearEnd;

            if ($toYear !== $fromYear + 1) {
                return formatResponse(false, null, 'New year must be exactly one year after previous year');
            }

            $this->db->beginTransaction();

            // Check if academic year already exists
            $checkStmt = $this->db->prepare(
                "SELECT COUNT(*) as count FROM academic_years WHERE year_code = :year"
            );
            $checkStmt->execute(['year' => $toYearCode]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ((int) $existing['count'] > 0) {
                $this->db->rollBack();
                return formatResponse(false, null, "Academic year {$toYearCode} already exists");
            }

            // Create academic year record
            $yearStmt = $this->db->prepare(
                "INSERT INTO academic_years (year_code, year_name, start_date, end_date, status, is_current)
                 VALUES (:year, :name, :start_date, :end_date, 'planning', 0)"
            );
            $yearStmt->execute([
                'year' => $toYearCode,
                'name' => 'Academic Year ' . $toYearCode,
                'start_date' => $calendar['year_start_date'],
                'end_date' => $calendar['year_end_date'],
            ]);
            $academicYearId = (int) $this->db->lastInsertId();

            // Create academic year terms for new year
            $termIds = [];
            foreach ($calendar['terms'] as $index => $term) {
                $termNumber = $index + 1;
                $termId = $this->ensureTermId(
                    (string) ($term['term_name'] ?? ('Term ' . $termNumber)),
                    $termNumber
                );

                $termStmt = $this->db->prepare(
                    "INSERT INTO academic_year_terms
                        (academic_year_id, term_id, opening_date, half_term_start, half_term_end, closing_date, status)
                     VALUES (:year_id, :term_id, :start_date, :half_term_start, :half_term_end, :end_date, 'upcoming')"
                );
                $termStmt->execute([
                    'year_id' => $academicYearId,
                    'term_id' => $termId,
                    'start_date' => $term['start_date'],
                    'half_term_start' => $term['half_term_start'],
                    'half_term_end' => $term['half_term_end'],
                    'end_date' => $term['end_date'],
                ]);
                $aytId = (int) $this->db->lastInsertId();
                $termIds[] = $aytId;
            }

            // Auto-generate the term calendar (weeks, school days, half-term
            // holidays) from the government-issued term dates. Explicit week
            // counts (e.g. 14/14/10) are honored when supplied per term.
            $weekCounts = [];
            foreach ($calendar['terms'] as $index => $term) {
                if (isset($term['weeks']) && (int) $term['weeks'] > 0) {
                    $weekCounts[$index + 1] = (int) $term['weeks'];
                }
            }
            $calendarService = new AcademicCalendarService($this->db);
            $calendarService->generateYearCalendar($academicYearId, $weekCounts);

            // Prepare workflow data
            $workflowData = [
                'from_year' => $fromYear,
                'to_year' => $toYear,
                'from_year_code' => $fromYearCode,
                'to_year_code' => $toYearCode,
                'academic_year_id' => $academicYearId,
                'year_start_date' => $yearStart,
                'year_end_date' => $yearEnd,
                'terms' => $calendar['terms'],
                'term_ids' => $termIds,
                'holidays' => $calendar['holidays'] ?? [],
                'transition_notes' => $calendar['transition_notes'] ?? '',
                'archive_summary' => [],
                'promotion_summary' => [],
                'new_classes' => [],
                'migrated_baselines' => [],
                'validation_results' => [],
            ];

            // Start workflow
            $instance = $this->startWorkflow(
                $this->getWorkflowDefinitionCode(),
                $toYear,
                $workflowData
            );

            // The create form supplies and validates the first three setup
            // stages in one atomic operation. Persist those completed stages
            // explicitly so the resumable workflow opens at calendar
            // generation rather than reverting to a legacy stage code.
            $this->advanceStage($instance, 'create_next_year', 'Next academic year identified', $workflowData);
            $this->advanceStage($instance, 'enter_year_term_dates', 'Year, term and half-term dates recorded', $workflowData);
            $this->advanceStage($instance, 'generate_calendar', 'Calendar generated from supplied dates', $workflowData);

            $this->db->commit();

            return formatResponse(true, [
                'instance_id' => $instance,
                'workflow_data' => $workflowData,
                'terms_created' => count($termIds),
            ], 'Academic calendar prepared successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    /**
     * Stage 2: Archive previous year data
     * 
     * Archives assessment results, reports, and other historical data.
     * 
     * @param int $instance_id Workflow instance ID
     * @param array $archive_options {
     *   @type bool $archive_assessments Archive assessment data
     *   @type bool $archive_attendance Archive attendance records
     *   @type bool $archive_reports Archive student reports
     *   @type bool $archive_competencies Archive competency assessments
     *   @type string $archive_location Storage location/path
     * }
     * @return array Response with archive summary
     */
    public function archiveData(int $instance_id, array $archive_options = []): array
    {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];
            $fromYear = $this->yearStart($data['from_year'] ?? 0);
            $fromYearId = $this->resolveYearIdFromCode($fromYear);
            if (!$fromYearId) {
                throw new Exception('Source academic year could not be resolved');
            }

            $archiveAssessments = $archive_options['archive_assessments'] ?? true;
            $archiveAttendance = $archive_options['archive_attendance'] ?? true;
            $archiveReports = $archive_options['archive_reports'] ?? true;
            $archiveCompetencies = $archive_options['archive_competencies'] ?? true;

            $archiveSummary = [
                'archived_at' => date('Y-m-d H:i:s'),
                'archived_by' => $this->user_id,
                'year_archived' => $fromYear,
            ];

            // Count records to be archived
            if ($archiveAssessments) {
                $assessStmt = $this->db->prepare(
                    "SELECT COUNT(*) as count FROM assessment_results ar
                    INNER JOIN assessments a ON ar.assessment_id = a.id
                    INNER JOIN academic_year_terms ayt ON a.academic_year_term_id = ayt.id
                    INNER JOIN academic_years ay ON ay.id = ayt.academic_year_id
                    WHERE ay.id = :year_id"
                );
                $assessStmt->execute(['year_id' => $fromYearId]);
                $assessCount = $assessStmt->fetch(PDO::FETCH_ASSOC);
                $archiveSummary['assessments_archived'] = (int) $assessCount['count'];
            }

            if ($archiveAttendance) {
                $attendStmt = $this->db->prepare(
                    "SELECT COUNT(*) as count FROM student_attendance
                    WHERE YEAR(date) = :year"
                );
                $attendStmt->execute(['year' => $fromYear]);
                $attendCount = $attendStmt->fetch(PDO::FETCH_ASSOC);
                $archiveSummary['attendance_records_archived'] = (int) $attendCount['count'];
            }

            if ($archiveCompetencies) {
                $compStmt = $this->db->prepare(
                    "SELECT COUNT(*) as count FROM learner_competencies
                    WHERE academic_year = :year"
                );
                $compStmt->execute(['year' => $fromYear]);
                $compCount = $compStmt->fetch(PDO::FETCH_ASSOC);
                $archiveSummary['competency_records_archived'] = (int) $compCount['count'];
            }

            // Year-scoped operational rows remain queryable for history.  The
            // archive record is finalized only during validateReadiness(), after
            // promotions and finance reconciliation have passed.
            $archiveSummary['mode'] = 'prepared_for_final_archive';

            $data['archive_summary'] = $archiveSummary;

            $this->advanceStage(
                $instance_id,
                'archive_previous_year',
                "Archived data for year {$fromYear}",
                $data
            );

            return formatResponse(true, $archiveSummary, 'Data archived successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Stage 3: Execute bulk promotions
     * 
     * Promotes all eligible students to next grade level.
     * 
     * @param int $instance_id Workflow instance ID
     * @param array $promotion_config {
     *   @type array $grade_mappings Array of from_grade → to_grade mappings
     *   @type bool $auto_promote_lower_primary Auto-promote PP1-Grade 2
     *   @type float $min_score_threshold Minimum score for promotion (optional)
     * }
     * @return array Response with promotion summary
     */
    public function executePromotions(int $instance_id, array $promotion_config = []): array
    {
        // Stream placement is an administrator decision and must be resumable;
        // never auto-place learners into the first or matching stream here.
        return $this->getPromotionCandidates($instance_id);

        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];
            $fromYear = $this->yearStart($data['from_year'] ?? 0);
            $toYear = $this->yearStart($data['to_year'] ?? 0);
            $fromYearId = $this->resolveYearIdFromCode($fromYear);
            $toYearId = (int) ($data['academic_year_id'] ?? 0);
            if (!$toYearId) $toYearId = $this->resolveYearIdFromCode($toYear);
            if (!$fromYearId || !$toYearId) {
                throw new Exception('Both source and target academic years are required');
            }

            $this->db->beginTransaction();

            $promotionSummary = [
                'total_students' => 0,
                'promoted' => 0,
                'retained' => 0,
                'graduated' => 0,
                'by_grade' => [],
                'enrollments_created' => 0,
                'obligations_generated' => 0,
            ];
            $rows = $this->db->prepare(
                "SELECT sae.id AS source_enrollment_id, sae.student_id,
                        sae.academic_year_class_stream_id AS source_stream_id,
                        ayc.class_id AS source_class_id, c.name AS source_class_name,
                        s.student_type_id, st.name AS student_type_name,
                        srcStream.name AS stream_name
                 FROM student_academic_enrollments sae
                 JOIN academic_year_class_streams srcStreamRow ON srcStreamRow.id = sae.academic_year_class_stream_id
                 JOIN streams srcStream ON srcStream.id = srcStreamRow.stream_id
                 JOIN academic_year_classes ayc ON ayc.id = srcStreamRow.academic_year_class_id
                 JOIN classes c ON c.id = ayc.class_id
                 JOIN students s ON s.id = sae.student_id
                 LEFT JOIN student_types st ON st.id = s.student_type_id
                 WHERE sae.academic_year_id = ? AND sae.enrollment_status IN ('pending','active')
                   AND s.status = 'active'
                 ORDER BY sae.id"
            );
            $rows->execute([$fromYearId]);
            foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $promotionSummary['total_students']++;
                $targetClassId = $this->resolveNextClassId((int) $row['source_class_id']);
                if (!$targetClassId) {
                    $this->db->prepare("UPDATE student_academic_enrollments SET enrollment_status = 'graduated' WHERE id = ?")
                        ->execute([(int) $row['source_enrollment_id']]);
                    $promotionSummary['graduated']++;
                    continue;
                }

                $targetStream = $this->db->prepare(
                    "SELECT aycs.id FROM academic_year_class_streams aycs
                     JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                     JOIN streams targetStream ON targetStream.id = aycs.stream_id
                     WHERE ayc.academic_year_id = ? AND ayc.class_id = ?
                       AND LOWER(targetStream.name) = LOWER(?) LIMIT 1"
                );
                $targetStream->execute([$toYearId, $targetClassId, $row['stream_name']]);
                $targetStreamId = (int) ($targetStream->fetchColumn() ?: 0);
                if (!$targetStreamId) {
                    $targetStream = $this->db->prepare(
                        "SELECT aycs.id FROM academic_year_class_streams aycs
                         JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                         WHERE ayc.academic_year_id = ? AND ayc.class_id = ? ORDER BY aycs.id LIMIT 1"
                    );
                    $targetStream->execute([$toYearId, $targetClassId]);
                    $targetStreamId = (int) ($targetStream->fetchColumn() ?: 0);
                }
                if (!$targetStreamId) throw new Exception('Target class has no configured stream for student ' . $row['student_id']);

                $existing = $this->db->prepare(
                    "SELECT id FROM student_academic_enrollments WHERE student_id = ? AND academic_year_id = ? LIMIT 1"
                );
                $existing->execute([(int) $row['student_id'], $toYearId]);
                $targetEnrollmentId = (int) ($existing->fetchColumn() ?: 0);
                if (!$targetEnrollmentId) {
                    $this->db->prepare(
                        "INSERT INTO student_academic_enrollments
                            (student_id, academic_year_id, academic_year_class_stream_id, enrolled_on, enrollment_status)
                         VALUES (?, ?, ?, CURDATE(), 'active')"
                    )->execute([(int) $row['student_id'], $toYearId, $targetStreamId]);
                    $targetEnrollmentId = (int) $this->db->lastInsertId();
                    $promotionSummary['enrollments_created']++;
                }

                $this->db->prepare("UPDATE student_academic_enrollments SET enrollment_status = 'completed' WHERE id = ?")
                    ->execute([(int) $row['source_enrollment_id']]);
                $transition = $this->db->prepare(
                    "SELECT id FROM student_transitions WHERE student_id = ? AND academic_year_id = ?
                     AND from_student_academic_enrollment_id = ? LIMIT 1"
                );
                $transition->execute([(int) $row['student_id'], $toYearId, (int) $row['source_enrollment_id']]);
                if (!$transition->fetchColumn()) {
                    $this->db->prepare(
                        "INSERT INTO student_transitions
                            (student_id, from_student_academic_enrollment_id, to_student_academic_enrollment_id,
                             academic_year_id, transition_type, reason, decided_by, decided_at, executed_at)
                         VALUES (?, ?, ?, ?, 'promotion', 'Automatic academic-year rollover', ?, NOW(), NOW())"
                    )->execute([
                        (int) $row['student_id'],
                        (int) $row['source_enrollment_id'], $targetEnrollmentId, $toYearId, $this->user_id
                    ]);
                }

                $promotionSummary['promoted']++;
                $promotionSummary['by_grade'][$row['source_class_name']]['total'] =
                    ($promotionSummary['by_grade'][$row['source_class_name']]['total'] ?? 0) + 1;
                $promotionSummary['by_grade'][$row['source_class_name']]['status'] = 'promoted';
                $promotionSummary['by_grade'][$row['source_class_name']]['student_type'] = $row['student_type_name'];

                // The onboarding procedure is the single billing authority.
                $out = null;
                $call = $this->db->prepare("CALL sp_onboard_student_enrollment(?, ?, @rollover_obligations)");
                $call->execute([$targetEnrollmentId, $this->user_id]);
                while ($call->nextRowset()) {}
                $outStmt = $this->db->query("SELECT @rollover_obligations");
                if ($outStmt) $promotionSummary['obligations_generated'] += (int) ($outStmt->fetchColumn() ?: 0);
            }

            $data['promotion_summary'] = $promotionSummary;

            $this->advanceStage(
                $instance_id,
                'assign_target_streams',
                "Promoted {$promotionSummary['promoted']} students, {$promotionSummary['graduated']} graduated",
                $data
            );

            $this->db->commit();

            return formatResponse(true, $promotionSummary, 'Bulk promotions executed successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    /**
     * Return the resumable promotion assignment board.  No learner is moved by
     * this method; the administrator chooses a target stream for each learner.
     */
    public function getPromotionCandidates(int $instance_id): array
    {
        $instance = $this->getWorkflowInstance($instance_id);
        if (!$instance) return formatResponse(false, null, 'Workflow instance not found');
        $data = json_decode($instance['data_json'], true) ?: [];
        $fromYearId = $this->resolveYearIdFromCode($data['from_year'] ?? 0);
        $toYearId = (int) ($data['academic_year_id'] ?? 0);
        if (!$fromYearId || !$toYearId) return formatResponse(false, null, 'Transition years are not prepared');

        $stmt = $this->db->prepare(
            "SELECT sae.id AS source_enrollment_id, s.id AS student_id,
                    s.admission_no, CONCAT(p.first_name, ' ', p.last_name) AS student_name,
                    c.id AS source_class_id, c.name AS source_class_name,
                    srcStream.name AS source_stream_name,
                    targetClass.id AS target_class_id, targetClass.name AS target_class_name,
                    targetEnrollment.id AS target_enrollment_id,
                    targetEnrollment.academic_year_class_stream_id AS target_stream_id
             FROM student_academic_enrollments sae
             JOIN students s ON s.id = sae.student_id
             JOIN persons p ON p.id = s.person_id
             JOIN academic_year_class_streams srcAycs ON srcAycs.id = sae.academic_year_class_stream_id
             JOIN streams srcStream ON srcStream.id = srcAycs.stream_id
             JOIN academic_year_classes srcAyc ON srcAyc.id = srcAycs.academic_year_class_id
             JOIN classes c ON c.id = srcAyc.class_id
             LEFT JOIN academic_class_progression prog
               ON prog.source_class_id = c.id AND prog.active = 1
             LEFT JOIN classes targetClass ON targetClass.id = prog.target_class_id
             LEFT JOIN student_academic_enrollments targetEnrollment
               ON targetEnrollment.student_id = s.id AND targetEnrollment.academic_year_id = ?
             WHERE sae.academic_year_id = ? AND sae.enrollment_status IN ('pending','active')
               AND s.status = 'active'
             ORDER BY c.id, p.last_name, p.first_name"
        );
        $stmt->execute([$toYearId, $fromYearId]);
        $candidates = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $targetClassId = (int) ($row['target_class_id'] ?? 0);
            $streams = [];
            if ($targetClassId) {
                $streamStmt = $this->db->prepare(
                    "SELECT aycs.id AS target_stream_id, streams.name AS stream_name
                     FROM academic_year_class_streams aycs
                     JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                     JOIN streams ON streams.id = aycs.stream_id
                     WHERE ayc.academic_year_id = ? AND ayc.class_id = ?
                     ORDER BY streams.name"
                );
                $streamStmt->execute([$toYearId, $targetClassId]);
                $streams = $streamStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            $row['target_streams'] = $streams;
            $row['assigned'] = !$targetClassId || (int) ($row['target_enrollment_id'] ?? 0) > 0;
            $candidates[] = $row;
        }
        $data['promotion_board_last_loaded_at'] = date('Y-m-d H:i:s');
        $this->db->prepare("UPDATE workflow_instances SET data_json = ? WHERE id = ?")
            ->execute([json_encode($data), $instance_id]);
        return formatResponse(true, [
            'from_year' => $data['from_year_code'] ?? $data['from_year'],
            'to_year' => $data['to_year_code'] ?? $data['to_year'],
            'candidates' => $candidates,
            'total' => count($candidates),
            'assigned' => count(array_filter($candidates, static function ($candidate) { return $candidate['assigned']; })),
        ], 'Promotion assignment board loaded');
    }

    /** Complete a canonical non-mutating gate after its dedicated setup page has been used. */
    public function completeCanonicalStage(int $instance_id, string $stageCode, string $notes = ''): array
    {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) return formatResponse(false, null, 'Workflow instance not found');
            $current = $instance['current_stage_code'] ?? $instance['current_stage'] ?? '';
            if ($current !== $stageCode) {
                return formatResponse(false, null, "Stage {$stageCode} cannot be completed while the workflow is at {$current}.");
            }
            $data = json_decode($instance['data_json'] ?? '{}', true) ?: [];
            $next = [
                'approve_fee_structures' => 'configure_operational_context',
                'configure_operational_context' => 'current_year_readiness',
                'current_year_readiness' => 'close_current_year_terms',
                'close_current_year_terms' => 'review_promotion_candidates',
            ][$stageCode] ?? null;
            if (!$next) return formatResponse(false, null, 'This stage has a dedicated workflow action.');

            if ($stageCode === 'approve_fee_structures') {
                $stmt = $this->db->prepare(
                    "SELECT COUNT(*) FROM academic_year_fee_schedules
                     WHERE academic_year_id = ? AND status IN ('draft','pending_review')"
                );
                $stmt->execute([(int) ($data['academic_year_id'] ?? 0)]);
                if ((int) $stmt->fetchColumn() > 0) {
                    return formatResponse(false, null, 'All target-year fee structures must be approved before continuing.');
                }
            }
            if ($stageCode === 'close_current_year_terms') {
                $sourceId = $this->resolveYearIdFromCode($data['from_year_code'] ?? $data['from_year'] ?? 0);
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM academic_year_terms WHERE academic_year_id = ? AND status <> 'closed'");
                $stmt->execute([$sourceId]);
                if ((int) $stmt->fetchColumn() > 0) {
                    return formatResponse(false, null, 'Current-year readiness cannot be completed until all outgoing terms are closed.');
                }
            }
            $data['canonical_stage_notes'][$stageCode] = ['notes' => $notes, 'completed_at' => date('Y-m-d H:i:s')];
            $this->advanceStage($instance_id, $next, $notes ?: "Completed {$stageCode}", $data);
            return formatResponse(true, ['current_stage' => $next], 'Stage completed');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /** Save one or many administrator stream assignments. */
    public function assignPromotionStreams(int $instance_id, array $assignments): array
    {
        $instance = $this->getWorkflowInstance($instance_id);
        if (!$instance) return formatResponse(false, null, 'Workflow instance not found');
        if (!$assignments) return formatResponse(false, null, 'At least one learner stream assignment is required');
        $data = json_decode($instance['data_json'], true) ?: [];
        $fromYearId = $this->resolveYearIdFromCode($data['from_year'] ?? 0);
        $toYearId = (int) ($data['academic_year_id'] ?? 0);
        if (!$fromYearId || !$toYearId) return formatResponse(false, null, 'Transition years are not prepared');

        $this->db->beginTransaction();
        try {
            $saved = 0;
            foreach ($assignments as $assignment) {
                $studentId = (int) ($assignment['student_id'] ?? 0);
                $targetAycsId = (int) ($assignment['target_stream_id'] ?? $assignment['target_aycs_id'] ?? 0);
                if (!$studentId || !$targetAycsId) throw new Exception('Student and target stream are required');

                $source = $this->db->prepare(
                    "SELECT sae.id, sae.academic_year_class_stream_id, ayc.class_id
                     FROM student_academic_enrollments sae
                     JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                     JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                     WHERE sae.student_id = ? AND sae.academic_year_id = ?
                       AND (sae.enrollment_status IN ('pending','active') OR EXISTS
                            (SELECT 1 FROM student_transitions st
                             WHERE st.from_student_academic_enrollment_id = sae.id
                               AND st.academic_year_id = ?)) LIMIT 1"
                );
                $source->execute([$studentId, $fromYearId, $toYearId]);
                $sourceRow = $source->fetch(PDO::FETCH_ASSOC);
                if (!$sourceRow) throw new Exception('Learner is not an active learner in the source year');

                $target = $this->db->prepare(
                    "SELECT aycs.id, ayc.class_id FROM academic_year_class_streams aycs
                     JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                     WHERE aycs.id = ? AND ayc.academic_year_id = ? LIMIT 1"
                );
                $target->execute([$targetAycsId, $toYearId]);
                $targetRow = $target->fetch(PDO::FETCH_ASSOC);
                if (!$targetRow) throw new Exception('Selected stream does not belong to the target academic year');

                $progression = $this->db->prepare(
                    "SELECT COUNT(*) FROM academic_class_progression
                     WHERE source_class_id = ? AND target_class_id = ? AND active = 1"
                );
                $progression->execute([(int) $sourceRow['class_id'], (int) $targetRow['class_id']]);
                if (!(int) $progression->fetchColumn()) throw new Exception('Selected stream is not a valid next class for this learner');

                $existing = $this->db->prepare(
                    "SELECT id FROM student_academic_enrollments WHERE student_id = ? AND academic_year_id = ? LIMIT 1"
                );
                $existing->execute([$studentId, $toYearId]);
                $targetEnrollmentId = (int) ($existing->fetchColumn() ?: 0);
                if ($targetEnrollmentId) {
                    $this->db->prepare("UPDATE student_academic_enrollments SET academic_year_class_stream_id = ?, enrollment_status = 'active' WHERE id = ?")
                        ->execute([$targetAycsId, $targetEnrollmentId]);
                } else {
                    $this->db->prepare(
                        "INSERT INTO student_academic_enrollments
                            (student_id, academic_year_id, academic_year_class_stream_id, enrolled_on, enrollment_status)
                         VALUES (?, ?, ?, CURDATE(), 'active')"
                    )->execute([$studentId, $toYearId, $targetAycsId]);
                    $targetEnrollmentId = (int) $this->db->lastInsertId();
                }
                $this->db->prepare("UPDATE student_academic_enrollments SET enrollment_status = 'completed' WHERE id = ?")
                    ->execute([(int) $sourceRow['id']]);
                $transition = $this->db->prepare(
                    "SELECT id FROM student_transitions WHERE student_id = ? AND academic_year_id = ?
                     AND from_student_academic_enrollment_id = ? LIMIT 1"
                );
                $transition->execute([$studentId, $toYearId, (int) $sourceRow['id']]);
                if (!$transition->fetchColumn()) {
                    $this->db->prepare(
                        "INSERT INTO student_transitions
                            (student_id, from_student_academic_enrollment_id, to_student_academic_enrollment_id,
                             academic_year_id, transition_type, reason, decided_by, decided_at, executed_at)
                         VALUES (?, ?, ?, ?, 'promotion', 'Administrator stream assignment', ?, NOW(), NOW())"
                    )->execute([$studentId, (int) $sourceRow['id'], $targetEnrollmentId, $toYearId, $this->user_id]);
                }
                $saved++;
            }

            // Learners at the end of the progression ladder are not stream
            // assignments. Record their graduation as part of the same saved
            // promotion board so they do not block completion.
            $graduates = $this->db->prepare(
                "SELECT sae.id, sae.student_id
                 FROM student_academic_enrollments sae
                 JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 JOIN students s ON s.id = sae.student_id AND s.status = 'active'
                 WHERE sae.academic_year_id = ? AND sae.enrollment_status IN ('pending','active')
                   AND NOT EXISTS (SELECT 1 FROM academic_class_progression p
                                   WHERE p.source_class_id = ayc.class_id AND p.active = 1)"
            );
            $graduates->execute([$fromYearId]);
            foreach ($graduates->fetchAll(PDO::FETCH_ASSOC) as $graduate) {
                $this->db->prepare("UPDATE student_academic_enrollments SET enrollment_status = 'graduated' WHERE id = ?")
                    ->execute([(int) $graduate['id']]);
                $exists = $this->db->prepare(
                    "SELECT id FROM student_transitions WHERE student_id = ? AND academic_year_id = ?
                     AND from_student_academic_enrollment_id = ? LIMIT 1"
                );
                $exists->execute([(int) $graduate['student_id'], $toYearId, (int) $graduate['id']]);
                if (!$exists->fetchColumn()) {
                    $this->db->prepare(
                        "INSERT INTO student_transitions
                            (student_id, from_student_academic_enrollment_id, academic_year_id,
                             transition_type, reason, decided_by, decided_at, executed_at)
                         VALUES (?, ?, ?, 'graduation', 'End of CBC progression', ?, NOW(), NOW())"
                    )->execute([(int) $graduate['student_id'], (int) $graduate['id'], $toYearId, $this->user_id]);
                }
            }

            $remaining = $this->db->prepare(
                "SELECT COUNT(*) FROM student_academic_enrollments sae
                 JOIN students s ON s.id = sae.student_id AND s.status = 'active'
                 JOIN academic_year_class_streams srcAycs ON srcAycs.id = sae.academic_year_class_stream_id
                 JOIN academic_year_classes srcAyc ON srcAyc.id = srcAycs.academic_year_class_id
                 WHERE sae.academic_year_id = ?
                   AND EXISTS (SELECT 1 FROM academic_class_progression p
                               WHERE p.source_class_id = srcAyc.class_id AND p.active = 1)
                   AND NOT EXISTS (SELECT 1 FROM student_transitions st
                                   WHERE st.from_student_academic_enrollment_id = sae.id
                                     AND st.academic_year_id = ?)"
            );
            $remaining->execute([$fromYearId, $toYearId]);
            $unassigned = (int) $remaining->fetchColumn();
            $summary = ['saved' => $saved, 'unassigned' => $unassigned, 'complete' => $unassigned === 0];
            $data['promotion_summary'] = array_merge($data['promotion_summary'] ?? [], [
                'assigned' => ($data['promotion_summary']['assigned'] ?? 0) + $saved,
                'unassigned' => $unassigned,
                'assignment_complete' => $unassigned === 0,
            ]);
            $this->db->prepare("UPDATE workflow_instances SET data_json = ? WHERE id = ?")
                ->execute([json_encode($data), $instance_id]);
            if ($unassigned === 0) {
                $currentInstance = $this->getWorkflowInstance($instance_id);
                $currentStage = $currentInstance['current_stage_code'] ?? ($currentInstance['current_stage'] ?? '');
                if ($currentStage === 'review_promotion_candidates') {
                    $this->advanceStage($instance_id, 'assign_promotion_decisions', 'Promotion decisions recorded from the administrator board', $data);
                    $currentStage = 'assign_promotion_decisions';
                }
                if ($currentStage === 'assign_promotion_decisions') {
                    $this->advanceStage($instance_id, 'assign_target_streams', 'Target class and stream assignment started', $data);
                }
                $this->advanceStage($instance_id, 'create_new_year_enrollments', 'Learners assigned and target-year enrollments created', $data);
                $this->advanceStage($instance_id, 'carry_forward_finances', 'Ready for arrears, credits and advance-payment carry-forward', $data);
            }
            $this->db->commit();
            return formatResponse(true, $summary, $unassigned === 0 ? 'All promotion assignments saved' : 'Promotion assignments saved; you may continue later');
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    /**
     * Stage 4: Setup new academic year
     *
     * Two modes:
     *
     *  A) AUTO (default, no class_structures) - clones the source (Term 3)
     *     year's class structure one grade ahead:
     *       * target class resolved through academic_class_progression
     *         (e.g. Grade 5 -> Grade 6, Grade 8 -> Grade 9);
     *       * Grade 4 and above copies the source stream set (5A/B/C -> 6A/B/C);
     *       * ECD / lower primary (Playgroup..Grade 3) always gets 2 streams (A, B);
     *       * Grade 9 graduates (no target created);
     *       * idempotent - existing classes/streams in the target year are reused.
     *
     *  B) EXPLICIT (class_structures provided) - legacy behavior, creates the
     *     listed levels/streams verbatim.
     *
     * @param int $instance_id Workflow instance ID
     * @param array $setup_config {
     *   @type int $from_year Source year (defaults to workflow from_year)
     *   @type array $class_structures Explicit class definitions (mode B)
     *   @type array $stream_overrides { target_class_id: [stream names] }
     *   @type int $default_ecd_streams Streams for ECD/lower (default 2)
     *   @type int $capacity Default stream capacity (default 40)
     * }
     * @return array Response with setup summary
     */
    public function setupNewYear(int $instance_id, array $setup_config): array
    {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];
            $toYear = (int) ($data['to_year'] ?? 0);
            $fromYear = (int) ($setup_config['from_year'] ?? ($data['from_year'] ?? 0));
            $capacity = (int) ($setup_config['capacity'] ?? 40);
            $defaultEcdStreams = max(1, (int) ($setup_config['default_ecd_streams'] ?? 2));
            $streamOverrides = $setup_config['stream_overrides'] ?? [];

            $academicYearId = $this->resolveYearIdFromCode($toYear);
            if (!$academicYearId) {
                throw new Exception("Academic year {$toYear} not found");
            }

            $this->db->beginTransaction();

            // Mode B: explicit class structures (legacy path).
            if (!empty($setup_config['class_structures'])) {
                $newClasses = $this->setupNewYearExplicit(
                    $academicYearId,
                    $setup_config['class_structures'],
                    $capacity
                );

                $contextSummary = $this->copyNewYearContext($fromYear, $academicYearId);

                $data['new_classes'] = array_merge($data['new_classes'] ?? [], $newClasses);
                $data['academic_year_id'] = $academicYearId;
                $data['new_year_context'] = $contextSummary;

                $this->advanceNewYearSetupStages(
                    $instance_id,
                    $data,
                    "Created " . count($newClasses) . " classes for year {$toYear}"
                );

                $this->db->commit();

                return formatResponse(true, [
                    'classes_created' => count($newClasses),
                    'new_classes' => $newClasses,
                    'context' => $contextSummary,
                    'academic_year_id' => $academicYearId,
                    'mode' => 'explicit',
                ], 'New academic year structure created');
            }

            // Mode A: auto-clone from the source year one grade ahead.
            $newClasses = $this->setupNewYearAuto(
                $fromYear,
                $academicYearId,
                $capacity,
                $defaultEcdStreams,
                $streamOverrides
            );

            $contextSummary = $this->copyNewYearContext($fromYear, $academicYearId);

            $data['new_classes'] = array_merge($data['new_classes'] ?? [], $newClasses);
            $data['academic_year_id'] = $academicYearId;
            $data['new_year_context'] = $contextSummary;

            $this->advanceNewYearSetupStages(
                $instance_id,
                $data,
                "Created " . count($newClasses) . " classes for year {$toYear}"
            );

            $this->db->commit();

            return formatResponse(true, [
                'classes_created' => count($newClasses),
                'new_classes' => $newClasses,
                'context' => $contextSummary,
                'academic_year_id' => $academicYearId,
                'mode' => 'auto',
            ], 'New academic year structure created');

        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    /**
     * Mode A: clone the source year's class/stream structure one grade ahead.
     *
     * @return array List of created/ensured class entries
     */
    private function setupNewYearAuto(int $fromYear, int $toYearId, int $capacity, int $defaultEcdStreams, array $streamOverrides): array
    {
        if ($fromYear <= 0) {
            throw new Exception('from_year is required for automatic class setup');
        }

        $fromYearId = $this->resolveYearIdFromCode($fromYear);
        if (!$fromYearId) {
            throw new Exception("Source academic year {$fromYear} not found");
        }

        // Source structure: class => stream names (Term 3 / year-end snapshot).
        $sourceStmt = $this->db->prepare(
            "SELECT c.id AS class_id, c.name AS class_name, c.level_id,
                    ayc.id AS ayc_id
             FROM academic_year_classes ayc
             JOIN classes c ON c.id = ayc.class_id
             WHERE ayc.academic_year_id = ?
             ORDER BY c.id"
        );
        $sourceStmt->execute([$fromYearId]);
        $sourceClasses = $sourceStmt->fetchAll(PDO::FETCH_ASSOC);

        $streamStmt = $this->db->prepare(
            "SELECT aycs.academic_year_class_id, s.name
             FROM academic_year_class_streams aycs
             JOIN streams s ON s.id = aycs.stream_id
             WHERE aycs.academic_year_class_id IN (SELECT id FROM academic_year_classes WHERE academic_year_id = ?)"
        );
        $streamStmt->execute([$fromYearId]);
        $streamsByAycs = [];
        foreach ($streamStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $streamsByAycs[(int) $row['academic_year_class_id']][] = $row['name'];
        }

        // Existing structure in the target year (for idempotency).
        $existingTarget = [];
        $targetStmt = $this->db->prepare(
            "SELECT c.id AS class_id, ayc.id AS ayc_id
             FROM academic_year_classes ayc
             JOIN classes c ON c.id = ayc.class_id
             WHERE ayc.academic_year_id = ?"
        );
        $targetStmt->execute([$toYearId]);
        foreach ($targetStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existingTarget[(int) $row['class_id']] = (int) $row['ayc_id'];
        }

        $created = [];
        $graduated = [];

        foreach ($sourceClasses as $source) {
            $sourceClassId = (int) $source['class_id'];
            $sourceName = (string) $source['class_name'];

            // Resolve the next class through the progression ladder.
            $nextClassId = $this->resolveNextClassId($sourceClassId);
            if ($nextClassId <= 0) {
                $graduated[] = $sourceName; // e.g. Grade 9 - graduates.
                continue;
            }

            // Determine stream names for the target class.
            if (isset($streamOverrides[$nextClassId]) && is_array($streamOverrides[$nextClassId])) {
                $streamNames = array_values(array_map('strval', $streamOverrides[$nextClassId]));
            } elseif ($this->isUpperPrimaryOrAbove($sourceName)) {
                // Grade 4+ keeps the source stream set (A/B/C stays A/B/C).
                $streamNames = $streamsByAycs[(int) $source['ayc_id']] ?? ['A'];
                if (empty($streamNames)) {
                    $streamNames = ['A'];
                }
            } else {
                // ECD / lower primary always gets the default stream set (A, B).
                $streamNames = ['A', 'B'];
                if ($defaultEcdStreams > 2) {
                    for ($i = 3; $i <= $defaultEcdStreams; $i++) {
                        $streamNames[] = chr(64 + $i);
                    }
                }
            }

            $created[] = $this->ensureYearClass(
                $toYearId,
                $nextClassId,
                $streamNames,
                $capacity,
                $existingTarget
            );
        }

        return $created;
    }

    /**
     * Copy year-scoped curriculum, teacher, timetable and fee context after
     * the target class/stream structure has been prepared. Master records are
     * reused; only context rows are created for the new academic year.
     */
    private function copyNewYearContext(int $fromYear, int $toYearId): array
    {
        $fromYearId = $this->resolveYearIdFromCode($fromYear);
        if (!$fromYearId) {
            throw new Exception('Source academic year could not be resolved');
        }

        $classMap = [];
        $sourceClasses = $this->db->prepare(
            "SELECT ayc.id AS source_ayc_id, ayc.class_id AS source_class_id
             FROM academic_year_classes ayc WHERE ayc.academic_year_id = ?"
        );
        $sourceClasses->execute([$fromYearId]);
        foreach ($sourceClasses->fetchAll(PDO::FETCH_ASSOC) as $source) {
            $targetClassId = $this->resolveNextClassId((int) $source['source_class_id']);
            if (!$targetClassId) continue;
            $targetAyc = $this->db->prepare(
                "SELECT id FROM academic_year_classes WHERE academic_year_id = ? AND class_id = ? LIMIT 1"
            );
            $targetAyc->execute([$toYearId, $targetClassId]);
            $targetAycId = (int) ($targetAyc->fetchColumn() ?: 0);
            if ($targetAycId) $classMap[(int) $source['source_ayc_id']] = $targetAycId;
        }

        $targetTerms = [];
        $termStmt = $this->db->prepare(
            "SELECT id, term_id FROM academic_year_terms WHERE academic_year_id = ?"
        );
        $termStmt->execute([$toYearId]);
        foreach ($termStmt->fetchAll(PDO::FETCH_ASSOC) as $term) {
            $targetTerms[(int) $term['term_id']] = (int) $term['id'];
        }

        $learningAreaMap = [];
        $areaStmt = $this->db->prepare(
            "SELECT id, academic_year_class_id, learning_area_id, strand_id,
                    sub_strand_id, planned_weeks, notes
             FROM academic_year_class_learning_areas
             WHERE academic_year_class_id IN (SELECT id FROM academic_year_classes WHERE academic_year_id = ?)"
        );
        $areaStmt->execute([$fromYearId]);
        foreach ($areaStmt->fetchAll(PDO::FETCH_ASSOC) as $area) {
            $targetAycId = $classMap[(int) $area['academic_year_class_id']] ?? 0;
            if (!$targetAycId) continue;
            $existing = $this->db->prepare(
                "SELECT id FROM academic_year_class_learning_areas
                 WHERE academic_year_class_id = ? AND learning_area_id = ?
                   AND strand_id <=> ? AND sub_strand_id <=> ? LIMIT 1"
            );
            $existing->execute([
                $targetAycId, (int) $area['learning_area_id'],
                $area['strand_id'], $area['sub_strand_id']
            ]);
            $targetAreaId = (int) ($existing->fetchColumn() ?: 0);
            if (!$targetAreaId) {
                $this->db->prepare(
                    "INSERT INTO academic_year_class_learning_areas
                        (academic_year_class_id, learning_area_id, strand_id,
                         sub_strand_id, status, planned_weeks, notes)
                     VALUES (?, ?, ?, ?, 'planned', ?, ?)
                     "
                )->execute([
                    $targetAycId, (int) $area['learning_area_id'],
                    $area['strand_id'], $area['sub_strand_id'],
                    $area['planned_weeks'], $area['notes']
                ]);
                $targetAreaId = (int) $this->db->lastInsertId();
            }
            $learningAreaMap[(int) $area['id']] = $targetAreaId;
        }

        // Copy class teachers by stream name, preserving the target-year stream row.
        $teacherCount = 0;
        $streamTeacherStmt = $this->db->prepare(
            "SELECT srcAycs.academic_year_class_id, srcStream.name, srcAycs.class_teacher_id
             FROM academic_year_class_streams srcAycs
             JOIN streams srcStream ON srcStream.id = srcAycs.stream_id
             WHERE srcAycs.academic_year_class_id IN
                (SELECT id FROM academic_year_classes WHERE academic_year_id = ?)
               AND srcAycs.class_teacher_id IS NOT NULL"
        );
        $streamTeacherStmt->execute([$fromYearId]);
        foreach ($streamTeacherStmt->fetchAll(PDO::FETCH_ASSOC) as $teacher) {
            $targetAycId = $classMap[(int) $teacher['academic_year_class_id']] ?? 0;
            if (!$targetAycId) continue;
            $update = $this->db->prepare(
                "UPDATE academic_year_class_streams targetStream
                 JOIN streams targetName ON targetName.id = targetStream.stream_id
                 SET targetStream.class_teacher_id = ?
                 WHERE targetStream.academic_year_class_id = ?
                   AND LOWER(targetName.name) = LOWER(?)"
            );
            $update->execute([(int) $teacher['class_teacher_id'], $targetAycId, $teacher['name']]);
            $teacherCount += $update->rowCount();
        }

        // Map source stream bindings to the target stream with the same name;
        // this is also used for timetable cloning below.
        $streamMap = [];
        $sourceStreamMap = $this->db->prepare(
            "SELECT src.id AS source_stream_id, target.id AS target_stream_id
             FROM academic_year_class_streams src
             JOIN streams srcName ON srcName.id = src.stream_id
             JOIN academic_year_classes srcClass ON srcClass.id = src.academic_year_class_id
             JOIN academic_year_class_streams target ON target.academic_year_class_id =
                 (SELECT id FROM academic_year_classes WHERE academic_year_id = ?
                  AND class_id = srcClass.class_id LIMIT 1)
             JOIN streams targetName ON targetName.id = target.stream_id
             WHERE srcClass.academic_year_id = ?
               AND LOWER(srcName.name) = LOWER(targetName.name)"
        );
        $sourceStreamMap->execute([$toYearId, $fromYearId]);
        foreach ($sourceStreamMap->fetchAll(PDO::FETCH_ASSOC) as $map) {
            $streamMap[(int) $map['source_stream_id']] = (int) $map['target_stream_id'];
        }

        // Copy subject-teacher assignments to matching target learning areas and terms.
        $subjectTeacherCount = 0;
        $teacherStmt = $this->db->prepare(
            "SELECT srcTeacher.academic_year_class_learning_area_id,
                    srcTeacher.academic_year_term_id, srcTeacher.staff_id, srcTeacher.role,
                    srcTerm.term_id
             FROM academic_year_class_learning_area_teachers srcTeacher
             JOIN academic_year_terms srcTerm ON srcTerm.id = srcTeacher.academic_year_term_id
             WHERE srcTeacher.academic_year_class_learning_area_id IN
                (SELECT id FROM academic_year_class_learning_areas
                 WHERE academic_year_class_id IN
                    (SELECT id FROM academic_year_classes WHERE academic_year_id = ?))"
        );
        $teacherStmt->execute([$fromYearId]);
        foreach ($teacherStmt->fetchAll(PDO::FETCH_ASSOC) as $teacher) {
            $targetAreaId = $learningAreaMap[(int) $teacher['academic_year_class_learning_area_id']] ?? 0;
            $targetTermId = $targetTerms[(int) $teacher['term_id']] ?? 0;
            if (!$targetAreaId || !$targetTermId) continue;
            $exists = $this->db->prepare(
                "SELECT id FROM academic_year_class_learning_area_teachers
                 WHERE academic_year_class_learning_area_id = ?
                   AND academic_year_term_id = ? AND staff_id = ? AND role = ? LIMIT 1"
            );
            $exists->execute([$targetAreaId, $targetTermId, (int) $teacher['staff_id'], $teacher['role']]);
            if ($exists->fetchColumn()) continue;
            $this->db->prepare(
                "INSERT INTO academic_year_class_learning_area_teachers
                    (academic_year_class_learning_area_id, academic_year_term_id, staff_id, role)
                 VALUES (?, ?, ?, ?)"
            )->execute([
                $targetAreaId, $targetTermId, (int) $teacher['staff_id'], $teacher['role']
            ]);
            $subjectTeacherCount++;
        }

        $timetableCount = 0;
        $timetableStmt = $this->db->prepare(
            "SELECT academic_year_class_stream_id, academic_year_term_id,
                    day_of_week, time_slot_id, learning_area_id, teacher_id, status
             FROM timetable_entries
             WHERE academic_year_class_stream_id IN
                (SELECT aycs.id FROM academic_year_class_streams aycs
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 WHERE ayc.academic_year_id = ?)"
        );
        $timetableStmt->execute([$fromYearId]);
        foreach ($timetableStmt->fetchAll(PDO::FETCH_ASSOC) as $entry) {
            $targetStreamId = $streamMap[(int) $entry['academic_year_class_stream_id']] ?? 0;
            $sourceTerm = $this->db->prepare("SELECT term_id FROM academic_year_terms WHERE id = ? LIMIT 1");
            $sourceTerm->execute([(int) $entry['academic_year_term_id']]);
            $targetTermId = $targetTerms[(int) $sourceTerm->fetchColumn()] ?? 0;
            if (!$targetStreamId || !$targetTermId) continue;
            $exists = $this->db->prepare(
                "SELECT id FROM timetable_entries
                 WHERE academic_year_class_stream_id = ? AND academic_year_term_id = ?
                   AND day_of_week = ? AND time_slot_id = ? LIMIT 1"
            );
            $exists->execute([$targetStreamId, $targetTermId, $entry['day_of_week'], $entry['time_slot_id']]);
            if ($exists->fetchColumn()) continue;
            $this->db->prepare(
                "INSERT INTO timetable_entries
                    (academic_year_class_stream_id, academic_year_term_id, day_of_week,
                     time_slot_id, learning_area_id, teacher_id, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $targetStreamId, $targetTermId,
                $entry['day_of_week'], $entry['time_slot_id'], $entry['learning_area_id'],
                $entry['teacher_id'], $entry['status']
            ]);
            $timetableCount++;
        }

        // Copy source fees to the promoted target class as draft schedules.
        $feeCount = 0;
        $feeStmt = $this->db->prepare(
            "SELECT src.academic_year_class_id, src.academic_year_term_id,
                    src.student_type_id, src.fee_catalog_id, src.amount
             FROM academic_year_fee_schedules src
             WHERE src.academic_year_id = ? AND src.status = 'active'"
        );
        $feeStmt->execute([$fromYearId]);
        foreach ($feeStmt->fetchAll(PDO::FETCH_ASSOC) as $fee) {
            $targetAycId = $classMap[(int) $fee['academic_year_class_id']] ?? 0;
            $sourceTerm = $this->db->prepare("SELECT term_id FROM academic_year_terms WHERE id = ? LIMIT 1");
            $sourceTerm->execute([(int) $fee['academic_year_term_id']]);
            $targetTermId = $targetTerms[(int) $sourceTerm->fetchColumn()] ?? 0;
            if (!$targetAycId || !$targetTermId) continue;
            $exists = $this->db->prepare(
                "SELECT id FROM academic_year_fee_schedules
                 WHERE academic_year_id = ? AND academic_year_term_id = ?
                   AND academic_year_class_id = ? AND student_type_id = ?
                   AND fee_catalog_id = ? AND status IN ('active','draft') LIMIT 1"
            );
            $exists->execute([$toYearId, $targetTermId, $targetAycId, $fee['student_type_id'], $fee['fee_catalog_id']]);
            if ($exists->fetchColumn()) continue;
            $this->db->prepare(
                "INSERT INTO academic_year_fee_schedules
                    (academic_year_id, academic_year_term_id, academic_year_class_id,
                     student_type_id, fee_catalog_id, amount, due_date, status, created_at, updated_at)
                 SELECT ?, ?, ?, ?, ?, ?, closing_date, 'draft', NOW(), NOW()
                 FROM academic_year_terms WHERE id = ?"
            )->execute([
                $toYearId, $targetTermId,
                $targetAycId, $fee['student_type_id'], $fee['fee_catalog_id'], $fee['amount'], $targetTermId
            ]);
            $feeCount++;
        }

        return [
            'classes_mapped' => count($classMap),
            'learning_areas_copied' => count($learningAreaMap),
            'class_teachers_copied' => $teacherCount,
            'subject_teacher_assignments_copied' => $subjectTeacherCount,
            'timetable_entries_copied' => $timetableCount,
            'fee_schedules_copied_as_draft' => $feeCount,
        ];
    }

    /**
     * Resolve the next class id for a source class via academic_class_progression.
     * Returns 0 when the source class graduates (no next class).
     */
    private function resolveNextClassId(int $sourceClassId): int
    {
        $stmt = $this->db->prepare(
            "SELECT target_class_id FROM academic_class_progression
             WHERE source_class_id = ? AND active = 1 LIMIT 1"
        );
        $stmt->execute([$sourceClassId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Ensure a class + streams exist for a year, creating them when missing.
     */
    private function ensureYearClass(int $toYearId, int $classId, array $streamNames, int $capacity, array &$existingTarget): array
    {
        $aycId = $existingTarget[$classId] ?? 0;
        if ($aycId <= 0) {
            $aycStmt = $this->db->prepare(
                "INSERT INTO academic_year_classes (academic_year_id, class_id, status)
                 VALUES (?, ?, 'active')"
            );
            $aycStmt->execute([$toYearId, $classId]);
            $aycId = (int) $this->db->lastInsertId();
            $existingTarget[$classId] = $aycId;
        }

        $streamIds = [];
        foreach ($streamNames as $streamName) {
            $streamIds[] = $this->createStreamLink($aycId, [
                'name' => (string) $streamName,
                'capacity' => $capacity,
            ]);
        }

        return [
            'class_id' => $classId,
            'academic_year_class_id' => $aycId,
            'streams' => $streamIds,
            'stream_names' => $streamNames,
        ];
    }

    /**
     * Mode B: create explicitly-provided class structures (legacy behavior).
     */
    private function setupNewYearExplicit(int $academicYearId, array $classStructures, int $capacity): array
    {
        $newClasses = [];

        foreach ($classStructures as $structure) {
            $levelId = (int) ($structure['level_id'] ?? 0);
            $streams = $structure['streams'] ?? ['A'];

            // Resolve school level for class naming.
            $levelStmt = $this->db->prepare("SELECT name, code FROM school_levels WHERE id = ? LIMIT 1");
            $levelStmt->execute([$levelId]);
            $level = $levelStmt->fetch(PDO::FETCH_ASSOC);
            if (!$level) {
                throw new Exception("School level {$levelId} not found");
            }
            $levelName = $level['name'];
            $levelCode = strtoupper($level['code']);

            // Reuse the canonical class master when present; only create it when missing.
            $classStmt = $this->db->prepare("SELECT id FROM classes WHERE level_id = ? AND name = ? LIMIT 1");
            $classStmt->execute([$levelId, $levelName]);
            $classId = (int) ($classStmt->fetchColumn() ?: 0);
            if ($classId <= 0) {
                $classInsert = $this->db->prepare(
                    "INSERT INTO classes (code, name, level_id, grade_level)
                     VALUES (:code, :name, :level_id, NULL)"
                );
                $classInsert->execute([
                    'code' => $levelCode,
                    'name' => $levelName,
                    'level_id' => $levelId,
                ]);
                $classId = (int) $this->db->lastInsertId();
            }

            // Link class to academic year idempotently.
            $aycLookup = $this->db->prepare(
                "SELECT id FROM academic_year_classes WHERE academic_year_id = ? AND class_id = ? LIMIT 1"
            );
            $aycLookup->execute([$academicYearId, $classId]);
            $aycId = (int) ($aycLookup->fetchColumn() ?: 0);
            if ($aycId <= 0) {
                $aycStmt = $this->db->prepare(
                    "INSERT INTO academic_year_classes (academic_year_id, class_id, status)
                     VALUES (:year_id, :class_id, 'active')"
                );
                $aycStmt->execute(['year_id' => $academicYearId, 'class_id' => $classId]);
                $aycId = (int) $this->db->lastInsertId();
            }

            // Create/link streams for this class
            $streamIds = [];
            foreach ($streams as $streamName) {
                $streamName = is_array($streamName) ? ($streamName['name'] ?? 'A') : $streamName;
                $streamIds[] = $this->createStreamLink($aycId, [
                    'name' => (string) $streamName,
                    'capacity' => $capacity,
                ]);
            }

            $newClasses[] = [
                'level_id' => $levelId,
                'class_id' => $classId,
                'academic_year_class_id' => $aycId,
                'streams' => $streamIds,
            ];
        }

        return $newClasses;
    }

    /**
     * Upper-primary and above (Grade 4..Grade 9) copy the source stream set;
     * everything lower (Playgroup..Grade 3) always uses the default ECD set.
     */
    private function isUpperPrimaryOrAbove(string $className): bool
    {
        return (bool) preg_match('/^Grade\s+([4-9]|1[0-2])\b/i', trim($className));
    }

    /**
     * Stage 5: Migrate competency baselines
     * 
     * Carries forward competency achievement data for continued tracking.
     * 
     * @param int $instance_id Workflow instance ID
     * @return array Response with migration summary
     */
    public function migrateBaselines(int $instance_id): array
    {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];
            $fromYear = (int) ($data['from_year'] ?? 0);
            $toYear = (int) ($data['to_year'] ?? 0);

            // Get final competency assessments from previous year
            $baselineStmt = $this->db->prepare(
                "SELECT 
                    lc.student_id,
                    lc.competency_id,
                    lc.performance_level_id,
                    MAX(lc.assessed_date) as last_assessed
                FROM learner_competencies lc
                WHERE lc.academic_year = :from_year
                GROUP BY lc.student_id, lc.competency_id"
            );
            $baselineStmt->execute(['from_year' => $fromYear]);
            $baselines = $baselineStmt->fetchAll(PDO::FETCH_ASSOC);

            $migratedBaselines = [
                'total_baselines' => count($baselines),
                'students_tracked' => count(array_unique(array_column($baselines, 'student_id'))),
                'competencies_tracked' => count(array_unique(array_column($baselines, 'competency_id'))),
                'migrated_at' => date('Y-m-d H:i:s'),
            ];

            // Note: Baselines are maintained in learner_competencies table
            // They serve as starting points for new year assessments

            $data['migrated_baselines'] = $migratedBaselines;

            $this->advanceStage(
                $instance_id,
                'migrate_baselines',
                "Migrated {$migratedBaselines['total_baselines']} competency baselines",
                $data
            );

            return formatResponse(true, $migratedBaselines, 'Competency baselines migrated successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Stage 6: Validate readiness
     * 
     * Final validation checks before new academic year goes live.
     * 
     * @param int $instance_id Workflow instance ID
     * @return array Response with validation results
     */
    public function validateReadiness(int $instance_id): array
    {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];
            $toYear = $this->yearStart($data['to_year'] ?? 0);
            $fromYear = $this->yearStart($data['from_year'] ?? 0);

            $validationResults = [];
            $yearId = (int) ($data['academic_year_id'] ?? 0);
            if ($yearId <= 0) {
                $yearId = $this->resolveYearIdFromCode($toYear);
            }

            // Check 1: Target academic year exists.
            $yearStmt = $this->db->prepare(
                "SELECT id, status, is_current FROM academic_years WHERE id = ? LIMIT 1"
            );
            $yearStmt->execute([$yearId]);
            $year = $yearStmt->fetch(PDO::FETCH_ASSOC);
            $validationResults['academic_year_exists'] = [
                'status' => $year ? 'pass' : 'fail',
                'academic_year_id' => $yearId,
                'year_status' => $year['status'] ?? null,
            ];

            // Check 2: Expected year terms created.
            $termsStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM academic_year_terms WHERE academic_year_id = ?"
            );
            $termsStmt->execute([$yearId]);
            $termsCount = (int) $termsStmt->fetchColumn();
            $validationResults['terms_created'] = [
                'status' => $termsCount >= 3 ? 'pass' : 'fail',
                'count' => $termsCount,
                'expected_minimum' => 3,
            ];

            // Check 3: Target classes created.
            $classesStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM academic_year_classes
                 WHERE academic_year_id = ? AND status = 'active'"
            );
            $classesStmt->execute([$yearId]);
            $classesCount = (int) $classesStmt->fetchColumn();
            $validationResults['classes_created'] = [
                'status' => $classesCount > 0 ? 'pass' : 'fail',
                'count' => $classesCount,
            ];

            // Check 4: Every active target class has at least one stream.
            $streamGapsStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM academic_year_classes ayc
                 LEFT JOIN academic_year_class_streams aycs
                    ON aycs.academic_year_class_id = ayc.id
                 WHERE ayc.academic_year_id = ? AND ayc.status = 'active'
                   AND aycs.id IS NULL"
            );
            $streamGapsStmt->execute([$yearId]);
            $streamGaps = (int) $streamGapsStmt->fetchColumn();
            $validationResults['class_streams_seeded'] = [
                'status' => $classesCount > 0 && $streamGaps === 0 ? 'pass' : 'fail',
                'classes_without_streams' => $streamGaps,
            ];

            // Check 5: Classes that require CBC curriculum have learning areas.
            $learningAreaGapsStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM academic_year_classes ayc
                 JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN academic_year_class_learning_areas acla
                    ON acla.academic_year_class_id = ayc.id
                 WHERE ayc.academic_year_id = ? AND ayc.status = 'active'
                   AND c.name NOT IN ('Playgroup', 'Grade 0')
                   AND acla.id IS NULL"
            );
            $learningAreaGapsStmt->execute([$yearId]);
            $learningAreaGaps = (int) $learningAreaGapsStmt->fetchColumn();
            $validationResults['learning_areas_seeded'] = [
                'status' => $learningAreaGaps === 0 ? 'pass' : 'fail',
                'classes_without_learning_areas' => $learningAreaGaps,
            ];

            // Check 6: Calendar exists for every target term.
            $calendarGapsStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM academic_year_terms ayt
                 LEFT JOIN academic_year_calendar aycal
                    ON aycal.academic_year_term_id = ayt.id
                 WHERE ayt.academic_year_id = ? AND aycal.id IS NULL"
            );
            $calendarGapsStmt->execute([$yearId]);
            $calendarGaps = (int) $calendarGapsStmt->fetchColumn();
            $validationResults['calendar_generated'] = [
                'status' => $termsCount > 0 && $calendarGaps === 0 ? 'pass' : 'fail',
                'terms_without_calendar' => $calendarGaps,
            ];

            // Check 6b: Calendar week numbers are sequential per term (no gaps/duplicates).
            $calendarStructureStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM (
                     SELECT aycal.academic_year_term_id
                     FROM academic_year_calendar aycal
                     JOIN academic_year_terms ayt ON ayt.id = aycal.academic_year_term_id
                     WHERE ayt.academic_year_id = ?
                     GROUP BY aycal.academic_year_term_id
                     HAVING COUNT(*) <> COUNT(DISTINCT aycal.week_number)
                         OR COUNT(*) <> MAX(aycal.week_number)
                 ) malformed"
            );
            $calendarStructureStmt->execute([$yearId]);
            $malformedTerms = (int) $calendarStructureStmt->fetchColumn();
            $validationResults['calendar_week_structure'] = [
                'status' => $malformedTerms === 0 ? 'pass' : 'fail',
                'malformed_terms' => $malformedTerms,
            ];

            // Check 6c: Source (from) year terms must all be closed before a rollover.
            $sourceYearId = 0;
            if ($fromYear > 0) {
                $sourceYearId = $this->resolveYearIdFromCode($fromYear);
            }
            if ($sourceYearId > 0) {
                $sourceOpenStmt = $this->db->prepare(
                    "SELECT COUNT(*) FROM academic_year_terms
                     WHERE academic_year_id = ? AND status <> 'completed'"
                );
                $sourceOpenStmt->execute([$sourceYearId]);
                $sourceOpenTerms = (int) $sourceOpenStmt->fetchColumn();
                $validationResults['source_year_closed'] = [
                    'status' => $sourceOpenTerms === 0 ? 'pass' : 'fail',
                    'source_year_id' => $sourceYearId,
                    'open_terms' => $sourceOpenTerms,
                ];
            } else {
                $validationResults['source_year_closed'] = [
                    'status' => 'pass',
                    'source_year_id' => null,
                    'open_terms' => null,
                ];
            }

            // Check 7: Promotion stage was run.
            $validationResults['promotions_executed'] = [
                'status' => !empty($data['promotion_summary']) ? 'pass' : 'fail',
                'summary' => $data['promotion_summary'] ?? null,
            ];

            // Check 8: No unresolved pending promotion transitions for the target year.
            $pendingTransitionsStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM student_transitions st
                 WHERE st.academic_year_id = ?
                   AND st.transition_type = 'promotion'
                   AND st.to_student_academic_enrollment_id IS NULL
                   AND st.executed_at IS NULL"
            );
            $pendingTransitionsStmt->execute([$yearId]);
            $pendingTransitions = (int) $pendingTransitionsStmt->fetchColumn();
            $validationResults['promotion_transitions_resolved'] = [
                'status' => $pendingTransitions === 0 ? 'pass' : 'fail',
                'pending_transitions' => $pendingTransitions,
            ];

            // Check 9: Archive stage was run.
            $validationResults['data_archived'] = [
                'status' => !empty($data['archive_summary']) ? 'pass' : 'fail',
                'summary' => $data['archive_summary'] ?? null,
            ];

            // Check 10: approved/active fee schedules exist for every target
            // class, term and configured student type. Draft copies are useful
            // for preparation but must never be used for live billing.
            $feeCountStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM academic_year_fee_schedules
                 WHERE academic_year_id = ? AND status = 'active'"
            );
            $feeCountStmt->execute([$yearId]);
            $activeFees = (int) $feeCountStmt->fetchColumn();
            $feeGapStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM academic_year_classes ayc
                 JOIN academic_year_terms ayt ON ayt.academic_year_id = ayc.academic_year_id
                 JOIN student_types st ON st.status = 'active'
                 LEFT JOIN academic_year_fee_schedules fs
                   ON fs.academic_year_id = ayc.academic_year_id
                  AND fs.academic_year_class_id = ayc.id
                  AND fs.academic_year_term_id = ayt.id
                  AND fs.student_type_id = st.id AND fs.status = 'active'
                 WHERE ayc.academic_year_id = ? AND ayc.status = 'active'
                   AND fs.id IS NULL"
            );
            $feeGapStmt->execute([$yearId]);
            $feeGaps = (int) $feeGapStmt->fetchColumn();
            $validationResults['fee_structures_ready'] = [
                'status' => $activeFees > 0 && $feeGaps === 0 ? 'pass' : 'fail',
                'active_schedules' => $activeFees,
                'missing_class_term_student_type_combinations' => $feeGaps,
            ];

            // If fee review has happened since promotion, seed any target
            // enrollments that were created while schedules were still draft.
            // This is idempotent and keeps the stored procedure as the single
            // billing authority.
            $unseededStmt = $this->db->prepare(
                "SELECT sae.id FROM student_academic_enrollments sae
                 LEFT JOIN student_fee_obligations fo ON fo.student_academic_enrollment_id = sae.id
                 WHERE sae.academic_year_id = ? AND sae.enrollment_status = 'active'
                   AND fo.id IS NULL"
            );
            $unseededStmt->execute([$yearId]);
            $seededAtValidation = 0;
            foreach ($unseededStmt->fetchAll(PDO::FETCH_COLUMN) as $enrollmentId) {
                $call = $this->db->prepare("CALL sp_onboard_student_enrollment(?, ?, @validation_obligations)");
                $call->execute([(int) $enrollmentId, $this->user_id]);
                while ($call->nextRowset()) {}
                $this->db->query("SELECT @validation_obligations");
                $seededAtValidation++;
            }

            // Check 11: every target-year continuing enrollment has at least
            // one obligation unless it is a graduating/no-fee exception.
            $enrollmentCount = 0;
            $enrollmentGapStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM student_academic_enrollments sae
                 LEFT JOIN student_fee_obligations fo ON fo.student_academic_enrollment_id = sae.id
                 WHERE sae.academic_year_id = ? AND sae.enrollment_status = 'active'
                   AND fo.id IS NULL"
            );
            $enrollmentGapStmt->execute([$yearId]);
            $enrollmentGaps = (int) $enrollmentGapStmt->fetchColumn();
            $validationResults['target_billing_seeded'] = [
                'status' => $enrollmentGaps === 0 ? 'pass' : 'fail',
                'active_enrollments_without_obligations' => $enrollmentGaps,
                'onboarding_attempts' => $seededAtValidation,
            ];

            // Overall readiness
            $allPassed = true;
            foreach ($validationResults as $check) {
                if (($check['status'] ?? 'fail') === 'fail') {
                    $allPassed = false;
                    break;
                }
            }

            $data['validation_results'] = $validationResults;
            $data['ready_for_new_year'] = $allPassed;

            if ($allPassed) {
                // Final cutover is atomic at the end of the workflow. Historical
                // rows remain in their original year; only the context flags
                // change here.
                if ($sourceYearId > 0) {
                    $this->db->prepare(
                        "UPDATE academic_years SET is_current = 0, status = 'archived' WHERE id = ?"
                    )->execute([$sourceYearId]);
                    $archive = $this->db->prepare(
                        "INSERT INTO academic_year_archives
                            (academic_year, status, closure_initiated_by, closure_date, archived_at)
                         VALUES (?, 'archived', ?, NOW(), NOW())
                         ON DUPLICATE KEY UPDATE status = 'archived', archived_at = NOW()"
                    );
                    $archive->execute([$fromYear, $this->user_id]);
                }
                $this->db->prepare(
                    "UPDATE academic_years SET is_current = 0 WHERE id <> ?"
                )->execute([$yearId]);
                $this->db->prepare(
                    "UPDATE academic_years SET is_current = 1, status = 'active' WHERE id = ?"
                )->execute([$yearId]);

                // Mark only the first target term current after all gates pass.
                $this->db->prepare(
                    "UPDATE academic_year_terms ayt
                     SET ayt.status = CASE WHEN ayt.id = (
                         SELECT min_id FROM (SELECT MIN(id) AS min_id FROM academic_year_terms WHERE academic_year_id = ?) min_term
                     ) THEN 'current' ELSE 'upcoming' END
                     WHERE ayt.academic_year_id = ?"
                )->execute([$yearId, $yearId]);
            }

            // Complete workflow only when all required checks pass; otherwise persist
            // the failure details without activating the year.
            if ($allPassed) {
                $this->completeWorkflow(
                    $instance_id,
                    json_encode($data),
                    'Year transition completed successfully'
                );
            }

            return formatResponse($allPassed, [
                'ready_for_new_year' => $allPassed,
                'validation_results' => $validationResults,
            ], $allPassed ? 'System ready for new academic year' : 'New academic year is not ready');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get transition status and summary
     * 
     * @param int $instance_id Workflow instance ID
     * @return array Response with transition details
     */
    public function getTransitionStatus(int $instance_id): array
    {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];

            return formatResponse(true, [
                'from_year' => $data['from_year'] ?? null,
                'to_year' => $data['to_year'] ?? null,
                'current_stage' => $instance['current_stage_code'] ?? 'pending',
                'workflow_status' => $instance['status'] ?? 'pending',
                'archive_summary' => $data['archive_summary'] ?? null,
                'promotion_summary' => $data['promotion_summary'] ?? null,
                'classes_created' => count($data['new_classes'] ?? []),
                'ready_for_new_year' => $data['ready_for_new_year'] ?? false,
            ], 'Transition status retrieved');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
