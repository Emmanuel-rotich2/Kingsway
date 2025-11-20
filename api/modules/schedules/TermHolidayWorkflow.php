<?php
namespace App\API\Modules\Schedules;

use App\API\Includes\WorkflowHandler;
use Exception;

/**
 * Term & Holiday Scheduling Workflow Handler
 * Handles workflow logic for defining, reviewing, and activating term dates and holidays
 */
class TermHolidayWorkflow extends WorkflowHandler
{
    /**
     * Stage 1: Define Term/Holiday Dates
     * @param array $data { year_id, term_name, start_date, end_date, holidays: [{name, date, description}] }
     * @return array
     */
    public function defineTermDates(array $data)
    {
        try {
            $required = ['year_id', 'term_name', 'start_date', 'end_date'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }
            $this->db->beginTransaction();
            // Insert term into terms table
            $stmt = $this->db->prepare("INSERT INTO terms (year_id, name, start_date, end_date, status) VALUES (:year_id, :name, :start_date, :end_date, 'proposed')");
            $stmt->execute([
                'year_id' => $data['year_id'],
                'name' => $data['term_name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date']
            ]);
            $term_id = $this->db->lastInsertId();
            // Insert holidays if provided
            if (!empty($data['holidays']) && is_array($data['holidays'])) {
                foreach ($data['holidays'] as $holiday) {
                    $stmt = $this->db->prepare("INSERT INTO holidays (term_id, name, date, description, status) VALUES (:term_id, :name, :date, :description, 'proposed')");
                    $stmt->execute([
                        'term_id' => $term_id,
                        'name' => $holiday['name'],
                        'date' => $holiday['date'],
                        'description' => $holiday['description'] ?? ''
                    ]);
                }
            }
            // Start workflow instance
            $instance_id = parent::startWorkflow('term', $term_id, $data);
            $this->db->commit();
            return ['success' => true, 'instance_id' => $instance_id, 'next_stage' => 'review'];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Stage 2: Review Term/Holiday Dates
     * @param int $instance_id
     * @return array
     */
    public function reviewTermDates($instance_id)
    {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                throw new Exception('Workflow instance not found');
            }
            $data = json_decode($instance['data_json'], true);
            // Example: check for conflicts (overlapping terms/holidays)
            $stmt = $this->db->prepare('CALL sp_validate_term_holiday_conflicts(:start_date, :end_date)');
            $stmt->execute([
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date']
            ]);
            $conflicts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (empty($conflicts)) {
                $this->advanceStage($instance_id, 'activate', 'review_passed');
            }
            return ['success' => true, 'conflicts' => $conflicts, 'next_stage' => empty($conflicts) ? 'activate' : 'define'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Stage 3: Activate Term/Holiday Dates
     * @param int $instance_id
     * @return array
     */
    public function activateTermDates($instance_id)
    {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                throw new Exception('Workflow instance not found');
            }
            $data = json_decode($instance['data_json'], true);
            // Mark term and holidays as active
            $stmt = $this->db->prepare('UPDATE terms SET status = "active" WHERE id = :term_id');
            $stmt->execute(['term_id' => $instance['reference_id']]);
            $stmt = $this->db->prepare('UPDATE holidays SET status = "active" WHERE term_id = :term_id');
            $stmt->execute(['term_id' => $instance['reference_id']]);
            $this->advanceStage($instance_id, 'completed', 'activated');
            return ['success' => true, 'next_stage' => 'completed'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    public function __construct()
    {
        parent::__construct('term_holiday_scheduling');
    }

    /**
     * Start a new term/holiday scheduling workflow
     */
    public function startWorkflow($referenceType, $referenceId, $initialData = [])
    {
        // Use base handler to start workflow instance
        return $this->startWorkflow($referenceType, $referenceId, $initialData);
    }

    /**
     * Advance workflow to next stage
     * @param int $instanceId
     * @param string $toStage
     * @param string $action
     * @param array $actionData
     */
    public function advanceWorkflow($instanceId, $toStage, $action, $actionData = [])
    {
        // Use base handler to advance workflow
        return $this->advanceStage($instanceId, $toStage, $action, $actionData);
    }

    /**
     * Get workflow status/details
     */
    public function getWorkflowStatus($instanceId)
    {
        return $this->getWorkflowInstance($instanceId);
    }

    /**
     * List all workflow instances (optionally filtered by reference type/id)
     */
    public function listWorkflows($filters = [])
    {
        // Basic implementation: filter by reference_type/reference_id if provided
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
