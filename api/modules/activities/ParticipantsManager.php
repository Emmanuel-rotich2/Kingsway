<?php
namespace App\API\Modules\activities;


use App\API\Includes\BaseAPI;
use PDO;
use Exception;

/**
 * ParticipantsManager - Manages student enrollment and participation in activities
 * Handles registration, withdrawal, and tracking of student participation
 */
class ParticipantsManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('activity_participants');
    }

    /**
     * List participants with filtering
     * 
     * @param array $params Filter parameters
     * @return array List of participants
     */
    public function listParticipants($params = [])
    {
        try {
            $page = isset($params['page']) ? (int) $params['page'] : 1;
            $limit = isset($params['limit']) ? (int) $params['limit'] : 50;
            $offset = ($page - 1) * $limit;

            $where = ['1=1'];
            $bindings = [];

            // Filter by activity
            if (!empty($params['activity_id'])) {
                $where[] = 'ap.activity_id = ?';
                $bindings[] = $params['activity_id'];
            }

            // Filter by student
            if (!empty($params['student_id'])) {
                $where[] = 'ap.student_id = ?';
                $bindings[] = $params['student_id'];
            }

            // Filter by status
            if (!empty($params['status'])) {
                $where[] = 'ap.status = ?';
                $bindings[] = $params['status'];
            }

            // Filter by role
            if (!empty($params['role'])) {
                $where[] = 'ap.role = ?';
                $bindings[] = $params['role'];
            }

            // Filter by class
            if (!empty($params['class_id'])) {
                $where[] = 'cs.class_id = ?';
                $bindings[] = $params['class_id'];
            }

            $whereClause = implode(' AND ', $where);

            // Get total count
            $sql = "
                SELECT COUNT(DISTINCT ap.id)
                FROM activity_participants ap
                JOIN students s ON ap.student_id = s.id
                JOIN class_streams cs ON s.stream_id = cs.id
                WHERE $whereClause
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            // Get paginated results
            $sql = "
                SELECT 
                    ap.*,
                    s.admission_no,
                    s.first_name,
                    s.last_name,
                    c.name as class_name,
                    cs.stream_name,
                    a.title as activity_title,
                    ac.name as category_name
                FROM activity_participants ap
                JOIN students s ON ap.student_id = s.id
                JOIN class_streams cs ON s.stream_id = cs.id
                JOIN classes c ON cs.class_id = c.id
                JOIN activities a ON ap.activity_id = a.id
                LEFT JOIN activity_categories ac ON a.category_id = ac.id
                WHERE $whereClause
                ORDER BY ap.joined_at DESC
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $participants,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ];

        } catch (Exception $e) {
            $this->logError($e, 'Failed to list participants');
            throw $e;
        }
    }
    /**
     * Get participant details
     * 
     * @param int $id Participant ID
     * @return array Participant details
     */
    public function getParticipant($id)
    {
        try {
            $sql = "
                SELECT 
                    ap.*,
                    s.admission_no,
                    s.first_name,
                    s.last_name,
                    c.name as class_name,
                    cs.stream_name,
                    a.title as activity_title,
                    a.description as activity_description,
                    a.start_date,
                    a.end_date,
                    ac.name as category_name
                FROM activity_participants ap
                JOIN students s ON ap.student_id = s.id
                JOIN class_streams cs ON s.stream_id = cs.id
                JOIN classes c ON cs.class_id = c.id
                JOIN activities a ON ap.activity_id = a.id
                LEFT JOIN activity_categories ac ON a.category_id = ac.id
                WHERE ap.id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $participant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$participant) {
                return [
                    'success' => false,
                    'code' => 404,
                    'message' => 'Participant record not found'
                ];
            }

            return [
                'success' => true,
                'data' => $participant
            ];

        } catch (Exception $e) {
            $this->logError($e, "Failed to get participant $id");
            throw $e;
        }
    }

    /**
     * Register a student for an activity
     * 
     * @param array $data Participant data
     * @param int $userId User performing the registration
     * @return array Registration result
     */
    public function registerParticipant($data, $userId)
    {
        $transactionStarted = false;
        try {
            // Validate required fields
            $required = ['activity_id', 'student_id'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Field '$field' is required");
                }
            }

            // Check if activity exists and is open for registration
            $stmt = $this->db->prepare("
                SELECT 
                    id, 
                    title, 
                    status, 
                    max_participants,
                    start_date
                FROM activities 
                WHERE id = ?
            ");
            $stmt->execute([$data['activity_id']]);
            $activity = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$activity) {
                throw new Exception('Activity not found');
            }

            // ... (other validation logic for status, dates, student, etc. can go here) ...

            // Check student exists
            $stmt = $this->db->prepare("SELECT id, first_name, last_name FROM students WHERE id = ?");
            $stmt->execute([$data['student_id']]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$student) {
                throw new Exception('Student not found');
            }

            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $transactionStarted = true;
            }

            $sql = "
                INSERT INTO activity_participants (
                    activity_id,
                    student_id,
                    status,
                    joined_at
                ) VALUES (?, ?, ?, NOW())
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['activity_id'],
                $data['student_id'],
                $data['status'] ?? 'active'
            ]);

            $participantId = $this->db->lastInsertId();

            if ($transactionStarted) {
                $this->db->commit();
            }

            $this->logAction(
                'create',
                $participantId,
                "Registered {$student['first_name']} {$student['last_name']} for activity: {$activity['title']}"
            );

            return [
                'success' => true,
                'data' => ['id' => $participantId],
                'message' => 'Participant registered successfully'
            ];
        } catch (Exception $e) {
            if ($transactionStarted && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError($e, 'Failed to register participant');
            throw $e;
        }
    }

    /**
     * Update participant status
     * 
     * @param int $id Participant ID
     * @param array $data Updated data
     * @param int $userId User making the update
     * @return array Update result
     */
    public function updateParticipantStatus($id, $data, $userId)
    {
        try {
            // Get current participant
            $stmt = $this->db->prepare("
                SELECT 
                    ap.*,
                    s.first_name,
                    s.last_name,
                    a.title as activity_title
                FROM activity_participants ap
                JOIN students s ON ap.student_id = s.id
                JOIN activities a ON ap.activity_id = a.id
                WHERE ap.id = ?
            ");
            $stmt->execute([$id]);
            $participant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$participant) {
                throw new Exception('Participant record not found');
            }

            $transactionStarted = false;
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $transactionStarted = true;
            }

            try {
                $updates = [];
                $params = [];
                $allowedFields = ['status', 'role', 'notes'];

                foreach ($allowedFields as $field) {
                    if (array_key_exists($field, $data)) {
                        $updates[] = "$field = ?";
                        $params[] = $data[$field];
                    }
                }

                if (!empty($updates)) {
                    $params[] = $id;
                    $sql = "UPDATE activity_participants SET " . implode(', ', $updates) . " WHERE id = ?";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute($params);
                }

                if ($transactionStarted) {
                    $this->db->commit();
                }

                $this->logAction(
                    'update',
                    $id,
                    "Updated participant status for {$participant['first_name']} {$participant['last_name']} in {$participant['activity_title']}"
                );

                return [
                    'success' => true,
                    'message' => 'Participant status updated successfully'
                ];

            } catch (Exception $e) {
                if ($transactionStarted && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                $this->logError($e, "Failed to update participant $id");
                throw $e;
            }

        } catch (Exception $e) {
            $this->logError($e, "Failed to update participant $id");
            throw $e;
        }
    }
    /**
     * Withdraw a participant from an activity
     * 
     * @param int $id Participant ID
     * @param string $reason Withdrawal reason
     * @param int $userId User performing the withdrawal
     * @return array Withdrawal result
     */
    public function withdrawParticipant($id, $reason, $userId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    ap.*,
                    s.first_name,
                    s.last_name,
                    a.title as activity_title
                FROM activity_participants ap
                JOIN students s ON ap.student_id = s.id
                JOIN activities a ON ap.activity_id = a.id
                WHERE ap.id = ?
            ");
            $stmt->execute([$id]);
            $participant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$participant) {
                throw new Exception('Participant record not found');
            }

            if ($participant['status'] === 'withdrawn') {
                throw new Exception('Participant is already withdrawn');
            }

            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $transactionStarted = true;
            } else {
                $transactionStarted = false;
            }

            $stmt = $this->db->prepare("
                UPDATE activity_participants 
                SET status = 'withdrawn', notes = CONCAT(COALESCE(notes, ''), ' | Withdrawn: ', ?)
                WHERE id = ?
            ");
            $stmt->execute([$reason, $id]);

            if ($transactionStarted) {
                $this->db->commit();
            }

            $this->logAction(
                'update',
                $id,
                "Withdrew {$participant['first_name']} {$participant['last_name']} from {$participant['activity_title']}: $reason"
            );

            return [
                'success' => true,
                'message' => 'Participant withdrawn successfully'
            ];

        } catch (Exception $e) {
            if (isset($transactionStarted) && $transactionStarted && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError($e, "Failed to withdraw participant $id");
            throw $e;
        }
    }

    /**
     * Get student's activity history
     * 
     * @param int $studentId Student ID
     * @return array List of activities the student participated in
     */
    public function getStudentActivityHistory($studentId)
    {
        try {
            $sql = "
                SELECT 
                    ap.*,
                    a.title as activity_title,
                    a.description,
                    a.start_date,
                    a.end_date,
                    a.status as activity_status,
                    ac.name as category_name
                FROM activity_participants ap
                JOIN activities a ON ap.activity_id = a.id
                LEFT JOIN activity_categories ac ON a.category_id = ac.id
                WHERE ap.student_id = ?
                ORDER BY a.start_date DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$studentId]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $history
            ];

        } catch (Exception $e) {
            $this->logError($e, "Failed to get activity history for student $studentId");
            throw $e;
        }
    }

    /**
     * Get participation statistics for an activity
     * 
     * @param int $activityId Activity ID
     * @return array Participation statistics
     */
    public function getActivityParticipationStats($activityId)
    {
        try {
            $sql = "
                SELECT 
                    COUNT(*) as total_participants,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'withdrawn' THEN 1 ELSE 0 END) as withdrawn,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN role = 'leader' THEN 1 ELSE 0 END) as leaders,
                    SUM(CASE WHEN role = 'participant' THEN 1 ELSE 0 END) as regular_participants
                FROM activity_participants
                WHERE activity_id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$activityId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get class distribution
            $sql = "
                SELECT 
                    c.name as class_name,
                    COUNT(*) as student_count
                FROM activity_participants ap
                JOIN students s ON ap.student_id = s.id
                JOIN class_streams cs ON s.stream_id = cs.id
                JOIN classes c ON cs.class_id = c.id
                WHERE ap.activity_id = ? AND ap.status = 'active'
                GROUP BY c.id
                ORDER BY c.name
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$activityId]);
            $classDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => [
                    'overall' => $stats,
                    'class_distribution' => $classDistribution
                ]
            ];

        } catch (Exception $e) {
            $this->logError($e, "Failed to get participation stats for activity $activityId");
            throw $e;
        }
    }

    /**
     * Bulk register students for an activity
     * 
     * @param int $activityId Activity ID
     * @param array $studentIds Array of student IDs
     * @param int $userId User performing the registration
     * @return array Bulk registration result
     */
    public function bulkRegisterParticipants($activityId, $studentIds, $userId)
    {
        try {
            $successful = [];
            $failed = [];

            $this->db->beginTransaction();

            foreach ($studentIds as $studentId) {
                try {
                    $result = $this->registerParticipant([
                        'activity_id' => $activityId,
                        'student_id' => $studentId
                    ], $userId);

                    $successful[] = $studentId;
                } catch (Exception $e) {
                    $failed[] = [
                        'student_id' => $studentId,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $this->db->commit();

            return [
                'success' => true,
                'data' => [
                    'successful' => count($successful),
                    'failed' => count($failed),
                    'successful_ids' => $successful,
                    'failed_details' => $failed
                ],
                'message' => sprintf(
                    'Registered %d students successfully, %d failed',
                    count($successful),
                    count($failed)
                )
            ];

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError($e, 'Failed to bulk register participants');
            throw $e;
        }
    }
}
