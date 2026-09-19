<?php
namespace App\API\Modules\schedules;

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
     * Persists the term into academic_year_terms and any holidays into
     * academic_year_calendar_days (backed by auto-created week buckets).
     *
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

            // Resolve the term template (terms holds only id/name/code)
            $termStmt = $this->db->prepare("SELECT id FROM terms WHERE name = :term_name");
            $termStmt->execute(['term_name' => $data['term_name']]);
            $termId = $termStmt->fetchColumn();
            if (!$termId) {
                throw new Exception('Term not found: ' . $data['term_name']);
            }

            $this->db->beginTransaction();

            // Upsert the academic-year term instance
            $existing = $this->db->prepare(
                "SELECT id FROM academic_year_terms WHERE academic_year_id = :year_id AND term_id = :term_id"
            );
            $existing->execute(['year_id' => $data['year_id'], 'term_id' => $termId]);
            $aytId = $existing->fetchColumn();

            if ($aytId) {
                $stmt = $this->db->prepare(
                    "UPDATE academic_year_terms
                     SET opening_date = :start_date, closing_date = :end_date, status = 'upcoming'
                     WHERE id = :id"
                );
                $stmt->execute(['start_date' => $data['start_date'], 'end_date' => $data['end_date'], 'id' => $aytId]);
            } else {
                $stmt = $this->db->prepare(
                    "INSERT INTO academic_year_terms
                        (academic_year_id, term_id, opening_date, closing_date, status)
                     VALUES (:year_id, :term_id, :start_date, :end_date, 'upcoming')"
                );
                $stmt->execute([
                    'year_id' => $data['year_id'],
                    'term_id' => $termId,
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date']
                ]);
                $aytId = (int) $this->db->lastInsertId();
            }

            // Insert holidays into the term calendar
            if (!empty($data['holidays']) && is_array($data['holidays'])) {
                $holidayTypeId = $this->resolveHolidayDayType();
                foreach ($data['holidays'] as $holiday) {
                    $holidayDate = $holiday['date'] ?? null;
                    if (!$holidayDate) {
                        continue;
                    }
                    $calendarId = $this->ensureCalendarForDate((int) $aytId, $holidayDate);

                    $existingDay = $this->db->prepare(
                        "SELECT id FROM academic_year_calendar_days
                         WHERE academic_year_calendar_id = :calendar_id AND date = :date"
                    );
                    $existingDay->execute(['calendar_id' => $calendarId, 'date' => $holidayDate]);
                    $dayId = $existingDay->fetchColumn();

                    if ($dayId) {
                        $stmt = $this->db->prepare(
                            "UPDATE academic_year_calendar_days
                             SET calendar_day_type_id = :day_type_id, title = :title, description = :description
                             WHERE id = :id"
                        );
                        $stmt->execute([
                            'day_type_id' => $holidayTypeId,
                            'title' => $holiday['name'] ?? 'Holiday',
                            'description' => $holiday['description'] ?? '',
                            'id' => $dayId
                        ]);
                    } else {
                        $stmt = $this->db->prepare(
                            "INSERT INTO academic_year_calendar_days
                                (academic_year_calendar_id, date, calendar_day_type_id, title, description)
                             VALUES (:calendar_id, :date, :day_type_id, :title, :description)"
                        );
                        $stmt->execute([
                            'calendar_id' => $calendarId,
                            'date' => $holidayDate,
                            'day_type_id' => $holidayTypeId,
                            'title' => $holiday['name'] ?? 'Holiday',
                            'description' => $holiday['description'] ?? ''
                        ]);
                    }
                }
            }

            // Start workflow instance referencing the academic_year_term
            $instance_id = parent::startWorkflow('term', $aytId, $data);
            $this->db->commit();
            return ['success' => true, 'instance_id' => $instance_id, 'next_stage' => 'review'];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[TermHolidayWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return ['success' => false, 'error' => 'An internal error occurred.'];
        }
    }

    /**
     * Resolve the calendar day type used for holidays, preferring 'school_holiday'.
     *
     * @return int
     */
    private function resolveHolidayDayType()
    {
        foreach (['school_holiday', 'holiday', 'public_holiday'] as $code) {
            $stmt = $this->db->prepare("SELECT id FROM calendar_day_types WHERE code = :code");
            $stmt->execute(['code' => $code]);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int) $id;
            }
        }
        throw new Exception('No calendar_day_types row available for holidays');
    }

    /**
     * Find or create the week bucket in academic_year_calendar covering the given date.
     * The table uses a manually-assigned id and sequentially numbers weeks per term
     * starting from the term's opening date.
     *
     * @param int $aytId
     * @param string $date Y-m-d
     * @return int
     */
    private function ensureCalendarForDate(int $aytId, string $date)
    {
        $openingStmt = $this->db->prepare("SELECT opening_date FROM academic_year_terms WHERE id = :id");
        $openingStmt->execute(['id' => $aytId]);
        $openingDate = $openingStmt->fetchColumn();

        if ($openingDate) {
            $weekNumber = max(1, floor((strtotime($date) - strtotime($openingDate)) / 604800) + 1);
            $weekStart = date('Y-m-d', strtotime($openingDate . ' + ' . ($weekNumber - 1) . ' weeks'));
        } else {
            $weekNumber = (int) date('W', strtotime($date));
            $weekStart = date('Y-m-d', strtotime('monday this week', strtotime($date)));
        }
        $weekEnd = date('Y-m-d', strtotime($weekStart . ' + 6 days'));

        $stmt = $this->db->prepare(
            "SELECT id FROM academic_year_calendar WHERE academic_year_term_id = :ayt_id AND week_number = :week_number"
        );
        $stmt->execute(['ayt_id' => $aytId, 'week_number' => (int) $weekNumber]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO academic_year_calendar (academic_year_term_id, week_number, week_start, week_end)
             VALUES (:ayt_id, :week_number, :week_start, :week_end)"
        );
        $stmt->execute([
            'ayt_id' => $aytId,
            'week_number' => (int) $weekNumber,
            'week_start' => $weekStart,
            'week_end' => $weekEnd
        ]);
        return (int) $this->db->lastInsertId();
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
            \App\API\Services\Logger::legacyError('[TermHolidayWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return ['success' => false, 'error' => 'An internal error occurred.'];
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
            // Mark the academic-year term as current
            $stmt = $this->db->prepare('UPDATE academic_year_terms SET status = "current" WHERE id = :term_id');
            $stmt->execute(['term_id' => $instance['reference_id']]);
            $this->advanceStage($instance_id, 'completed', 'activated');
            return ['success' => true, 'next_stage' => 'completed'];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[TermHolidayWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return ['success' => false, 'error' => 'An internal error occurred.'];
        }
    }
    public function __construct()
    {
        parent::__construct('term_holiday_scheduling');
    }

    /**
     * Start a new term/holiday scheduling workflow
     */
    public function startWorkflow($reference_type, $reference_id, $initial_data = [], $userId = null)
    {
        // Use base handler to start workflow instance
        return parent::startWorkflow($reference_type, $reference_id, $initial_data, $userId);
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
