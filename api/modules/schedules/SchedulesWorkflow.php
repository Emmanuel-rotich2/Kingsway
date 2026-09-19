<?php
namespace App\API\Modules\schedules;

use Exception;

use App\API\Includes\WorkflowHandler;
use App\API\Services\ServiceContractBroker;
use App\API\Services\TeacherSpecializationService;
use function App\API\Includes\dayNameToNumber;

class SchedulesWorkflow extends WorkflowHandler
{
    private TeacherSpecializationService $teacherSpecializations;
    /**
     * Orchestrate Exam Scheduling Workflow
     */
    public function startExamScheduling(array $data)
    {
        // ExamsWorkflow assumed in App\API\Modules\Exams
        $cls = 'App\\API\\Modules\\Exams\\ExamsWorkflow';
        if (!class_exists($cls)) {
            throw new \Exception("Missing workflow class: $cls");
        }
        $examsWorkflow = new $cls();
        return $examsWorkflow->startExamScheduling($data);
    }

    /**
     * Orchestrate School Event Scheduling Workflow
     */
    public function startEventScheduling(array $data)
    {
        // EventsWorkflow assumed in App\API\Modules\Events
        $cls = 'App\\API\\Modules\\Events\\EventsWorkflow';
        if (!class_exists($cls)) {
            throw new \Exception("Missing workflow class: $cls");
        }
        $eventsWorkflow = new $cls();
        return $eventsWorkflow->startEventScheduling($data);
    }

    /**
     * Orchestrate Term & Holiday Scheduling Workflow
     */
    public function startTermHolidayScheduling(array $data)
    {
        // TermHolidayWorkflow assumed in App\API\Modules\TermHoliday
        $cls = 'App\\API\\Modules\\TermHoliday\\TermHolidayWorkflow';
        if (!class_exists($cls)) {
            throw new \Exception("Missing workflow class: $cls");
        }
        $termHolidayWorkflow = new $cls();
        return $termHolidayWorkflow->startTermHolidayScheduling($data);
    }

    /**
     * Orchestrate Room/Resource Booking Workflow
     */
    public function startRoomBooking(array $data)
    {
        // ResourceBookingWorkflow assumed in App\API\Modules\Resources
        $cls = 'App\\API\\Modules\\Resources\\ResourceBookingWorkflow';
        if (!class_exists($cls)) {
            throw new \Exception("Missing workflow class: $cls");
        }
        $resourceBookingWorkflow = new $cls();
        return $resourceBookingWorkflow->startRoomBooking($data);
    }

    /**
     * Orchestrate Transport Scheduling Workflow
     */
    public function startTransportScheduling(array $data)
    {
        // TransportWorkflow assumed in App\API\Modules\Transport
        $cls = 'App\\API\\Modules\\Transport\\TransportWorkflow';
        if (!class_exists($cls)) {
            throw new \Exception("Missing workflow class: $cls");
        }
        $transportWorkflow = new $cls();
        return $transportWorkflow->startTransportScheduling($data);
    }
    public function __construct()
    {
        parent::__construct('class_timetabling');
        $this->teacherSpecializations = ServiceContractBroker::contract(
            TeacherSpecializationService::class,
            [],
            $this->db
        );
    }

    /**
     * Stage 1: Plan Timetable
     * @param array $plan { class_id, term_id, timetable_entries }
     * @return array
     */
    public function planTimetable(array $plan): array
    {
        try {
            $required = ['class_id', 'term_id', 'timetable_entries'];
            foreach ($required as $field) {
                if (empty($plan[$field])) {
                    throw new \Exception("Missing required field: $field");
                }
            }
            $classStreamId = $this->resolveClassStreamId((int) $plan['class_id']);
            if ($classStreamId <= 0) {
                throw new \Exception('No active class-stream found for the given class');
            }
            $termId = $this->resolveAcademicYearTermId((int) $plan['term_id']);
            if ($termId <= 0) {
                throw new \Exception('No active term found for the given term');
            }
            $startedTransaction = !$this->db->inTransaction();
            if ($startedTransaction) $this->db->beginTransaction();
            // Insert timetable entries into timetable_entries
            foreach ($plan['timetable_entries'] as $entry) {
                $dayNum = dayNameToNumber($entry['day_of_week']);
                if ($dayNum === null) {
                    throw new \Exception('Invalid day_of_week in timetable entry');
                }
                $learningAreaId = $this->resolveLearningAreaId((int) ($entry['subject_id'] ?? 0));
                if ($learningAreaId <= 0) {
                    throw new \Exception('Invalid subject_id in timetable entry');
                }
                $streamLearningAreaId = (int)$this->db->query(
                    "SELECT sla.id
                     FROM academic_year_class_stream_learning_areas sla
                     JOIN academic_year_class_learning_areas cla ON cla.id = sla.academic_year_class_learning_area_id
                     WHERE sla.academic_year_class_stream_id = ? AND cla.learning_area_id = ? LIMIT 1",
                    [$classStreamId, $learningAreaId]
                )->fetchColumn();
                if ($streamLearningAreaId <= 0) {
                    throw new \Exception('Learning area is not configured for the selected class stream');
                }
                $teacherId = (int) ($entry['teacher_id'] ?? 0);
                if ($teacherId <= 0) throw new \Exception('A valid teacher is required for every timetable entry');
                $this->teacherSpecializations->assertEligible($teacherId, $learningAreaId, $classStreamId);
                $timeSlotId = $this->resolveTimeSlotId($entry['time_slot_id'] ?? null, $entry['start_time'] ?? null, $entry['end_time'] ?? null);
                if ($timeSlotId === null) {
                    throw new \Exception('No time slot matches the entry start/end time');
                }
                $stmt = $this->db->prepare("INSERT INTO timetable_entries (academic_year_class_stream_id, academic_year_class_stream_learning_area_id, academic_year_term_id, day_of_week, time_slot_id, learning_area_id, teacher_id, status) VALUES (:aycs, :stream_la, :term, :day, :ts, :la, :teacher, 'scheduled')");
                $stmt->execute([
                    'aycs' => $classStreamId,
                    'stream_la' => $streamLearningAreaId,
                    'term' => $termId,
                    'day' => $dayNum,
                    'ts' => $timeSlotId,
                    'la' => $learningAreaId,
                    'teacher' => $teacherId
                ]);
            }
            // Start workflow instance
            $instance_id = parent::startWorkflow('class', $plan['class_id'], $plan);
            if ($startedTransaction) $this->db->commit();
            return ['success' => true, 'instance_id' => $instance_id, 'next_stage' => 'timetable_review'];
        } catch (\Exception $e) {
            if (($startedTransaction ?? false) && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[SchedulesWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return ['success' => false, 'error' => 'An internal error occurred.'];
        }
    }

    /**
     * Stage 2: Review Timetable
     * @param int $instance_id
     * @return array
     */
    public function reviewTimetable($instance_id): array
    {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                throw new \Exception('Workflow instance not found');
            }
            $data = json_decode($instance['data_json'], true);
            // Example: call conflict detection procedure
            $conflicts = [];
            $proc = "SHOW PROCEDURE STATUS WHERE Db = DATABASE() AND Name = 'sp_detect_schedule_conflicts'";
            if ($this->db->query($proc)->fetchColumn()) {
                $stmt = $this->db->prepare('CALL sp_detect_schedule_conflicts(:class_id, :term_id)');
                $stmt->execute([
                    'class_id' => $data['class_id'],
                    'term_id' => $data['term_id']
                ]);
                $conflicts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } else {
                // Fall back to view-based overlap detection
                $classStreamId = $this->resolveClassStreamId((int) $data['class_id']);
                $termId = $this->resolveAcademicYearTermId((int) $data['term_id']);
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM vw_timetable_entries a JOIN vw_timetable_entries b ON a.academic_year_class_stream_id = b.academic_year_class_stream_id AND a.day_of_week = b.day_of_week AND a.id < b.id AND a.start_time < b.end_time AND a.end_time > b.start_time WHERE a.academic_year_class_stream_id = ? AND a.academic_year_term_id = ? AND a.status = 'scheduled'");
                $stmt->execute([$classStreamId, $termId]);
                $overlapCount = (int) $stmt->fetchColumn();
                if ($overlapCount > 0) {
                    $conflicts = [['conflict_type' => 'class_overlap', 'count' => $overlapCount]];
                }
            }
            // Advance workflow to next stage if no conflicts
            if (empty($conflicts)) {
                $this->advanceStage($instance_id, 'timetable_approval', 'review_passed');
            }
            return ['success' => true, 'conflicts' => $conflicts, 'next_stage' => empty($conflicts) ? 'timetable_approval' : 'timetable_planning'];
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[SchedulesWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return ['success' => false, 'error' => 'An internal error occurred.'];
        }
    }

    /**
     * Stage 3: Approve Timetable
     * @param int $instance_id
     * @return array
     */
    public function approveTimetable($instance_id): array
    {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                throw new \Exception('Workflow instance not found');
            }
            // Mark all timetable entries as approved for this class/term
            $data = json_decode($instance['data_json'], true);
            $classStreamId = $this->resolveClassStreamId((int) $data['class_id']);
            $termId = $this->resolveAcademicYearTermId((int) $data['term_id']);
            $stmt = $this->db->prepare('UPDATE timetable_entries SET status = "scheduled" WHERE academic_year_class_stream_id = :class_stream_id AND academic_year_term_id = :term_id');
            $stmt->execute([
                'class_stream_id' => $classStreamId,
                'term_id' => $termId
            ]);
            $this->advanceStage($instance_id, 'timetable_publication', 'approved');
            return ['success' => true, 'next_stage' => 'timetable_publication'];
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[SchedulesWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return ['success' => false, 'error' => 'An internal error occurred.'];
        }
    }

    /**
     * Stage 4: Publish Timetable
     * @param int $instance_id
     * @return array
     */
    public function publishTimetable($instance_id): array
    {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                throw new \Exception('Workflow instance not found');
            }
            // Mark all timetable entries as published for this class/term
            $data = json_decode($instance['data_json'], true);
            $classStreamId = $this->resolveClassStreamId((int) $data['class_id']);
            $termId = $this->resolveAcademicYearTermId((int) $data['term_id']);
            $stmt = $this->db->prepare('UPDATE timetable_entries SET status = "scheduled" WHERE academic_year_class_stream_id = :class_stream_id AND academic_year_term_id = :term_id');
            $stmt->execute([
                'class_stream_id' => $classStreamId,
                'term_id' => $termId
            ]);
            $this->advanceStage($instance_id, 'completed', 'published');
            return ['success' => true, 'next_stage' => 'completed'];
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[SchedulesWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return ['success' => false, 'error' => 'An internal error occurred.'];
        }
    }

    /**
     * Resolve the current academic year id.
     */
    private function getCurrentAcademicYearId(): int
    {
        $yearId = (int) $this->db->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetchColumn();
        if ($yearId <= 0) {
            $yearId = (int) $this->db->query("SELECT id FROM academic_years WHERE status = 'active' ORDER BY id DESC LIMIT 1")->fetchColumn();
        }
        return $yearId;
    }

    /**
     * Resolve a legacy `class_id` to academic_year_class_streams.id.
     */
    private function resolveClassStreamId(int $classId): int
    {
        if ($classId <= 0) {
            return 0;
        }
        $academicYearId = $this->getCurrentAcademicYearId();
        if ($academicYearId <= 0) {
            return 0;
        }
        $stmt = $this->db->prepare(
            "SELECT aycs.id
             FROM academic_year_class_streams aycs
             JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
             WHERE ayc.academic_year_id = ? AND ayc.class_id = ?
             ORDER BY aycs.id LIMIT 1"
        );
        $stmt->execute([$academicYearId, $classId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Resolve a legacy `term_id` to academic_year_terms.id.
     */
    private function resolveAcademicYearTermId(int $termId): int
    {
        $academicYearId = $this->getCurrentAcademicYearId();
        if ($academicYearId <= 0) {
            return 0;
        }
        if ($termId > 0) {
            $stmt = $this->db->prepare("SELECT id FROM academic_year_terms WHERE id = ? AND academic_year_id = ? LIMIT 1");
            $stmt->execute([$termId, $academicYearId]);
            $found = (int) $stmt->fetchColumn();
            if ($found > 0) {
                return $found;
            }
            $stmt = $this->db->prepare("SELECT ayt.id FROM academic_year_terms ayt WHERE ayt.academic_year_id = ? AND ayt.term_id = ? LIMIT 1");
            $stmt->execute([$academicYearId, $termId]);
            $found = (int) $stmt->fetchColumn();
            if ($found > 0) {
                return $found;
            }
        }
        $stmt = $this->db->prepare("SELECT id FROM academic_year_terms WHERE academic_year_id = ? AND status = 'current' LIMIT 1");
        $stmt->execute([$academicYearId]);
        $found = (int) $stmt->fetchColumn();
        if ($found > 0) {
            return $found;
        }
        $stmt = $this->db->prepare("SELECT id FROM academic_year_terms WHERE academic_year_id = ? AND status = 'upcoming' ORDER BY opening_date LIMIT 1");
        $stmt->execute([$academicYearId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Resolve a legacy `subject_id` to learning_areas.id.
     */
    private function resolveLearningAreaId(int $subjectId): int
    {
        if ($subjectId <= 0) {
            return 0;
        }
        $stmt = $this->db->prepare("SELECT learning_area_id FROM strands WHERE id = ? LIMIT 1");
        $stmt->execute([$subjectId]);
        $learningAreaId = (int) $stmt->fetchColumn();
        if ($learningAreaId > 0) {
            return $learningAreaId;
        }
        $stmt = $this->db->prepare("SELECT id FROM learning_areas WHERE id = ? LIMIT 1");
        $stmt->execute([$subjectId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Resolve a time_slot by explicit id or start/end time.
     */
    private function resolveTimeSlotId($timeSlotId, $startTime = null, $endTime = null): ?int
    {
        if (!empty($timeSlotId) && is_numeric($timeSlotId)) {
            $stmt = $this->db->prepare("SELECT id FROM time_slots WHERE id = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([(int) $timeSlotId]);
            if ($stmt->fetchColumn()) {
                return (int) $timeSlotId;
            }
        }
        if ($startTime !== null && $startTime !== '') {
            $sql = "SELECT id FROM time_slots WHERE is_active = 1 AND start_time = ?";
            $params = [$startTime];
            if ($endTime !== null && $endTime !== '') {
                $sql .= " AND end_time = ?";
                $params[] = $endTime;
            }
            $sql .= " ORDER BY period_number LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $id = (int) $stmt->fetchColumn();
            if ($id > 0) {
                return $id;
            }
        }
        return null;
    }

    // Optionally, keep the generic workflow instance/status/list methods for API use
    public function getWorkflowStatus($instanceId)
    {
        return $this->getWorkflowInstance($instanceId);
    }

    public function listWorkflows($filters = [])
    {
        $sql = "SELECT * FROM workflow_instances WHERE workflow_id = :workflow_id";
        $params = ['workflow_id' => $this->workflow_id];
        if (!empty($filters['reference_type'])) {
            $sql .= " AND reference_type = :reference_type";
            $params['reference_type'] = $filters['reference_type'];
        }
        if (!empty($filters['reference_id'])) {
            $sql .= " AND reference_id = :reference_id";
            $params['reference_id'] = $filters['reference_id'];
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
