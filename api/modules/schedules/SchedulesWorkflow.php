<?php
namespace App\API\Modules\schedules;

use Exception;

use App\API\Includes\WorkflowHandler;

class SchedulesWorkflow extends WorkflowHandler
{
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
            $this->db->beginTransaction();
            // Insert timetable entries into class_schedules
            foreach ($plan['timetable_entries'] as $entry) {
                $stmt = $this->db->prepare("INSERT INTO class_schedules (class_id, term_id, day_of_week, start_time, end_time, subject_id, teacher_id, room_id, status) VALUES (:class_id, :term_id, :day_of_week, :start_time, :end_time, :subject_id, :teacher_id, :room_id, 'planned')");
                $stmt->execute([
                    'class_id' => $plan['class_id'],
                    'term_id' => $plan['term_id'],
                    'day_of_week' => $entry['day_of_week'],
                    'start_time' => $entry['start_time'],
                    'end_time' => $entry['end_time'],
                    'subject_id' => $entry['subject_id'],
                    'teacher_id' => $entry['teacher_id'],
                    'room_id' => $entry['room_id']
                ]);
            }
            // Start workflow instance
            $instance_id = parent::startWorkflow('class', $plan['class_id'], $plan);
            $this->db->commit();
            return ['success' => true, 'instance_id' => $instance_id, 'next_stage' => 'timetable_review'];
        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
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
            // Example: call conflict detection procedure
            $stmt = $this->db->prepare('CALL sp_detect_schedule_conflicts(:class_id, :term_id)');
            $data = json_decode($instance['data_json'], true);
            $stmt->execute([
                'class_id' => $data['class_id'],
                'term_id' => $data['term_id']
            ]);
            $conflicts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            // Advance workflow to next stage if no conflicts
            if (empty($conflicts)) {
                $this->advanceStage($instance_id, 'timetable_approval', 'review_passed');
            }
            return ['success' => true, 'conflicts' => $conflicts, 'next_stage' => empty($conflicts) ? 'timetable_approval' : 'timetable_planning'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
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
            // Mark all class_schedules as approved for this class/term
            $data = json_decode($instance['data_json'], true);
            $stmt = $this->db->prepare('UPDATE class_schedules SET status = "approved" WHERE class_id = :class_id AND term_id = :term_id');
            $stmt->execute([
                'class_id' => $data['class_id'],
                'term_id' => $data['term_id']
            ]);
            $this->advanceStage($instance_id, 'timetable_publication', 'approved');
            return ['success' => true, 'next_stage' => 'timetable_publication'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
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
            // Mark all class_schedules as published for this class/term
            $data = json_decode($instance['data_json'], true);
            $stmt = $this->db->prepare('UPDATE class_schedules SET status = "published" WHERE class_id = :class_id AND term_id = :term_id');
            $stmt->execute([
                'class_id' => $data['class_id'],
                'term_id' => $data['term_id']
            ]);
            $this->advanceStage($instance_id, 'completed', 'published');
            return ['success' => true, 'next_stage' => 'completed'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
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
