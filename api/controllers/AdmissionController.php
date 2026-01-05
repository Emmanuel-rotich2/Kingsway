<?php
namespace App\API\Controllers;

use App\API\Modules\admission\StudentAdmissionWorkflow;
use Exception;

class AdmissionController extends BaseController
{
    private StudentAdmissionWorkflow $api;

    public function __construct() {
        parent::__construct();
        $this->api = new StudentAdmissionWorkflow();
    }

    public function index()
    {
        return $this->success(['message' => 'Admission API is running']);
    }

    /**
     * GET /api/admissions/pending - Get pending admissions for dashboard
     */
    public function getPending($id = null, $data = [], $segments = [])
    {
        try {
            $db = $this->db;

            // Get pending admissions count
            $countQuery = "
                SELECT COUNT(*) as total_pending
                FROM admission_applications
                WHERE status = 'pending'
            ";
            $countResult = $db->query($countQuery);
            $countRow = $countResult->fetch();
            $totalPending = (int) ($countRow['total_pending'] ?? 0);

            // Get recent pending admissions (last 8)
            $listQuery = "
                SELECT 
                    aa.id,
                    CONCAT(s.first_name, ' ', s.last_name) as student_name,
                    aa.application_date,
                    aa.status,
                    aa.created_at as admission_date
                FROM admission_applications aa
                LEFT JOIN students s ON aa.student_id = s.id
                WHERE aa.status = 'pending'
                ORDER BY aa.created_at DESC
                LIMIT 8
            ";

            $listResult = $db->query($listQuery);
            $recentAdmissions = [];
            while ($row = $listResult->fetch()) {
                $recentAdmissions[] = [
                    'id' => $row['id'],
                    'student_name' => $row['student_name'] ?? 'Unknown',
                    'admission_date' => $row['admission_date'],
                    'status' => $row['status'] ?? 'pending'
                ];
            }

            return $this->success([
                'total_pending' => $totalPending,
                'recent' => $recentAdmissions,
                'timestamp' => date('Y-m-d H:i:s')
            ], 'Pending admissions retrieved');

        } catch (\Exception $e) {
            return $this->error('Failed to fetch pending admissions: ' . $e->getMessage());
        }
    }

    // Explicit REST endpoints for all StudentAdmissionWorkflow public methods

    // 1. Application Submission
    public function postSubmitApplication($id = null, $data = [], $segments = [])
    {
        $result = $this->api->submitApplication($data);
        return $this->handleResponse($result);
    }

    // 2. Document Upload
    public function postUploadDocument($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $document_type = $data['document_type'] ?? null;
        $file = $data['file'] ?? null;
        $result = $this->api->uploadDocument($application_id, $document_type, $file);
        return $this->handleResponse($result);
    }

    // 3. Document Verification
    public function postVerifyDocument($id = null, $data = [], $segments = [])
    {
        $document_id = $data['document_id'] ?? $id;
        $status = $data['status'] ?? null;
        $notes = $data['notes'] ?? '';
        $result = $this->api->verifyDocument($document_id, $status, $notes);
        return $this->handleResponse($result);
    }

    // 4. Interview Scheduling
    public function postScheduleInterview($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $interview_date = $data['interview_date'] ?? null;
        $interview_time = $data['interview_time'] ?? null;
        $venue = $data['venue'] ?? 'Main Office';
        $result = $this->api->scheduleInterview($application_id, $interview_date, $interview_time, $venue);
        return $this->handleResponse($result);
    }

    // 5. Interview Assessment
    public function postRecordInterviewResults($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $assessment_data = $data['assessment_data'] ?? $data;
        $result = $this->api->recordInterviewResults($application_id, $assessment_data);
        return $this->handleResponse($result);
    }

    // 6. Placement Offer
    public function postGeneratePlacementOffer($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $assigned_class_id = $data['assigned_class_id'] ?? null;
        $result = $this->api->generatePlacementOffer($application_id, $assigned_class_id);
        return $this->handleResponse($result);
    }

    // 7. Fee Payment
    public function postRecordFeePayment($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $payment_data = $data['payment_data'] ?? $data;
        $result = $this->api->recordFeePayment($application_id, $payment_data);
        return $this->handleResponse($result);
    }

    // 8. Enrollment
    public function postCompleteEnrollment($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $result = $this->api->completeEnrollment($application_id);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/admission/queues - Get workflow queues by stage for role-based views
     * Returns counts and lists of applications at each stage
     */
    public function getQueues($id = null, $data = [], $segments = [])
    {
        try {
            $db = $this->db;

            // Get applications at each workflow stage
            $queues = [
                'documents_pending' => [],
                'interview_pending' => [],
                'placement_pending' => [],
                'payment_pending' => [],
                'enrollment_pending' => []
            ];

            // Documents Pending (status = submitted or documents_pending)
            $sql = "SELECT aa.id, aa.application_no, aa.applicant_name, aa.grade_applying_for, 
                           aa.status, aa.created_at,
                           p.first_name as parent_first_name, p.last_name as parent_last_name, p.phone_1,
                           (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id) as doc_count,
                           (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id AND verification_status = 'verified') as verified_count
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    WHERE aa.status IN ('submitted', 'documents_pending')
                    ORDER BY aa.created_at DESC";
            $stmt = $db->query($sql);
            $queues['documents_pending'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Interview Pending (status = documents_verified)
            $sql = "SELECT aa.id, aa.application_no, aa.applicant_name, aa.grade_applying_for, 
                           aa.status, aa.created_at,
                           p.first_name as parent_first_name, p.last_name as parent_last_name, p.phone_1,
                           wi.data_json
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE aa.status = 'documents_verified'
                    ORDER BY aa.created_at DESC";
            $stmt = $db->query($sql);
            $queues['interview_pending'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Placement Pending (status = auto_qualified or interview passed but no placement yet)
            $sql = "SELECT aa.id, aa.application_no, aa.applicant_name, aa.grade_applying_for, 
                           aa.status, aa.created_at,
                           p.first_name as parent_first_name, p.last_name as parent_last_name, p.phone_1,
                           wi.current_stage, wi.data_json
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE aa.status = 'auto_qualified' 
                       OR (wi.current_stage = 'placement_offer' AND aa.status NOT IN ('placement_offered', 'fees_pending', 'enrolled', 'cancelled'))
                    ORDER BY aa.created_at DESC";
            $stmt = $db->query($sql);
            $queues['placement_pending'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Payment Pending (status = placement_offered or fees_pending)
            $sql = "SELECT aa.id, aa.application_no, aa.applicant_name, aa.grade_applying_for, 
                           aa.status, aa.created_at,
                           p.first_name as parent_first_name, p.last_name as parent_last_name, p.phone_1,
                           wi.data_json,
                           JSON_UNQUOTE(JSON_EXTRACT(wi.data_json, '$.total_fees')) as total_fees,
                           JSON_UNQUOTE(JSON_EXTRACT(wi.data_json, '$.assigned_class_id')) as assigned_class_id
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE aa.status IN ('placement_offered', 'fees_pending')
                    ORDER BY aa.created_at DESC";
            $stmt = $db->query($sql);
            $queues['payment_pending'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Enrollment Pending (payment recorded, ready for final enrollment)
            $sql = "SELECT aa.id, aa.application_no, aa.applicant_name, aa.grade_applying_for, 
                           aa.status, aa.created_at,
                           p.first_name as parent_first_name, p.last_name as parent_last_name, p.phone_1,
                           wi.current_stage, wi.data_json
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE wi.current_stage = 'enrollment' AND aa.status != 'enrolled'
                    ORDER BY aa.created_at DESC";
            $stmt = $db->query($sql);
            $queues['enrollment_pending'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Get summary counts
            $summary = [
                'documents_pending' => count($queues['documents_pending']),
                'interview_pending' => count($queues['interview_pending']),
                'placement_pending' => count($queues['placement_pending']),
                'payment_pending' => count($queues['payment_pending']),
                'enrollment_pending' => count($queues['enrollment_pending']),
                'total_pending' => count($queues['documents_pending']) + count($queues['interview_pending'])
                    + count($queues['placement_pending']) + count($queues['payment_pending'])
                    + count($queues['enrollment_pending'])
            ];

            return $this->success([
                'queues' => $queues,
                'summary' => $summary,
                'timestamp' => date('Y-m-d H:i:s')
            ], 'Workflow queues retrieved');

        } catch (\Exception $e) {
            return $this->error('Failed to fetch workflow queues: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/admission/application/{id} - Get single application details with full workflow status
     */
    public function getApplication($id = null, $data = [], $segments = [])
    {
        try {
            if (!$id) {
                return $this->badRequest('Application ID is required');
            }

            $db = $this->db;

            // Get application details
            $sql = "SELECT aa.*, 
                           p.first_name as parent_first_name, p.last_name as parent_last_name, 
                           p.phone_1, p.phone_2, p.email as parent_email,
                           wi.id as workflow_instance_id, wi.current_stage, wi.status as workflow_status, wi.data_json
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE aa.id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$id]);
            $application = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$application) {
                return $this->notFound('Application not found');
            }

            // Get documents
            $sql = "SELECT * FROM admission_documents WHERE application_id = ? ORDER BY is_mandatory DESC, document_type";
            $stmt = $db->prepare($sql);
            $stmt->execute([$id]);
            $documents = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Parse workflow data
            $workflowData = json_decode($application['data_json'] ?? '{}', true) ?: [];

            // Determine what actions are available based on current stage
            $availableActions = $this->getAvailableActions($application['current_stage'], $application['status']);

            return $this->success([
                'application' => $application,
                'documents' => $documents,
                'workflow_data' => $workflowData,
                'available_actions' => $availableActions
            ], 'Application details retrieved');

        } catch (\Exception $e) {
            return $this->error('Failed to fetch application: ' . $e->getMessage());
        }
    }

    /**
     * Get available actions based on workflow stage
     */
    private function getAvailableActions($currentStage, $status)
    {
        $actions = [];

        switch ($currentStage) {
            case 'application':
            case 'application_submission':
                $actions = ['upload_documents'];
                break;
            case 'document_verification':
                $actions = ['verify_documents', 'upload_documents'];
                break;
            case 'interview_scheduling':
                $actions = ['schedule_interview'];
                break;
            case 'interview_assessment':
                $actions = ['record_interview_results'];
                break;
            case 'placement_offer':
                $actions = ['generate_placement_offer'];
                break;
            case 'fee_payment':
                $actions = ['record_payment'];
                break;
            case 'enrollment':
                $actions = ['complete_enrollment'];
                break;
            default:
                $actions = [];
        }

        // Add status-based overrides
        if ($status === 'auto_qualified') {
            $actions = ['generate_placement_offer'];
        }
        if ($status === 'enrolled') {
            $actions = ['view_student'];
        }
        if ($status === 'cancelled') {
            $actions = [];
        }

        return $actions;
    }

    /**
     * GET /api/admission/stats - Get admission statistics for dashboards
     */
    public function getStats($id = null, $data = [], $segments = [])
    {
        try {
            $db = $this->db;

            $stats = [];

            // Total applications this year
            $sql = "SELECT COUNT(*) as total FROM admission_applications WHERE academic_year = YEAR(CURDATE())";
            $stmt = $db->query($sql);
            $stats['total_applications'] = (int) $stmt->fetchColumn();

            // By status
            $sql = "SELECT status, COUNT(*) as count 
                    FROM admission_applications 
                    WHERE academic_year = YEAR(CURDATE())
                    GROUP BY status";
            $stmt = $db->query($sql);
            $stats['by_status'] = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            // By grade
            $sql = "SELECT grade_applying_for, COUNT(*) as count 
                    FROM admission_applications 
                    WHERE academic_year = YEAR(CURDATE())
                    GROUP BY grade_applying_for";
            $stmt = $db->query($sql);
            $stats['by_grade'] = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            // This week
            $sql = "SELECT COUNT(*) FROM admission_applications 
                    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            $stats['this_week'] = (int) $db->query($sql)->fetchColumn();

            // Enrolled (completed)
            $stats['enrolled'] = (int) ($stats['by_status']['enrolled'] ?? 0);

            // Pending (not enrolled or cancelled)
            $stats['pending'] = $stats['total_applications'] - $stats['enrolled'] - (int) ($stats['by_status']['cancelled'] ?? 0);

            return $this->success($stats, 'Admission statistics retrieved');

        } catch (\Exception $e) {
            return $this->error('Failed to fetch admission stats: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/admission/notifications - Get role-specific admission notifications for dashboards
     * Returns pending work items based on the user's role
     */
    public function getNotifications($id = null, $data = [], $segments = [])
    {
        try {
            $db = $this->db;

            // Get the user's role from auth context
            $userRole = $_SESSION['role'] ?? $data['role'] ?? 'guest';

            $notifications = [
                'pending_tasks' => [],
                'total_count' => 0,
                'role' => $userRole
            ];

            // Define which roles see which notifications
            $roleNotifications = [
                // Document verification - registrar, deputy, admin
                'registrar' => ['documents_pending'],
                'deputy_headteacher' => ['documents_pending', 'interview_pending'],
                'school_admin' => ['documents_pending', 'interview_pending', 'enrollment_pending'],

                // Interview - headteacher
                'headteacher' => ['interview_pending', 'placement_pending'],

                // Payment - accountant, bursar, finance
                'accountant' => ['payment_pending'],
                'bursar' => ['payment_pending'],
                'finance_officer' => ['payment_pending'],

                // Director sees everything
                'director' => ['documents_pending', 'interview_pending', 'placement_pending', 'payment_pending', 'enrollment_pending'],

                // Admin sees everything
                'admin' => ['documents_pending', 'interview_pending', 'placement_pending', 'payment_pending', 'enrollment_pending'],
                'system_administrator' => ['documents_pending', 'interview_pending', 'placement_pending', 'payment_pending', 'enrollment_pending']
            ];

            $relevantQueues = $roleNotifications[$userRole] ?? [];

            if (empty($relevantQueues)) {
                return $this->success($notifications, 'No admission notifications for this role');
            }

            // Documents Pending
            if (in_array('documents_pending', $relevantQueues)) {
                $sql = "SELECT COUNT(*) FROM admission_applications WHERE status IN ('submitted', 'documents_pending')";
                $count = (int) $db->query($sql)->fetchColumn();
                if ($count > 0) {
                    $notifications['pending_tasks'][] = [
                        'type' => 'documents_pending',
                        'label' => 'Documents to Verify',
                        'count' => $count,
                        'icon' => 'bi-file-earmark-text',
                        'color' => 'warning',
                        'link' => '/pages/manage_admissions.php?tab=documents_pending'
                    ];
                    $notifications['total_count'] += $count;
                }
            }

            // Interview Pending
            if (in_array('interview_pending', $relevantQueues)) {
                $sql = "SELECT COUNT(*) FROM admission_applications WHERE status = 'documents_verified'";
                $count = (int) $db->query($sql)->fetchColumn();
                if ($count > 0) {
                    $notifications['pending_tasks'][] = [
                        'type' => 'interview_pending',
                        'label' => 'Interviews Pending',
                        'count' => $count,
                        'icon' => 'bi-calendar-event',
                        'color' => 'info',
                        'link' => '/pages/manage_admissions.php?tab=interview_pending'
                    ];
                    $notifications['total_count'] += $count;
                }
            }

            // Placement Pending
            if (in_array('placement_pending', $relevantQueues)) {
                $sql = "SELECT COUNT(*) FROM admission_applications WHERE status IN ('auto_qualified', 'interview_passed')";
                $count = (int) $db->query($sql)->fetchColumn();
                if ($count > 0) {
                    $notifications['pending_tasks'][] = [
                        'type' => 'placement_pending',
                        'label' => 'Placements to Generate',
                        'count' => $count,
                        'icon' => 'bi-check-circle',
                        'color' => 'primary',
                        'link' => '/pages/manage_admissions.php?tab=placement_pending'
                    ];
                    $notifications['total_count'] += $count;
                }
            }

            // Payment Pending
            if (in_array('payment_pending', $relevantQueues)) {
                $sql = "SELECT COUNT(*) FROM admission_applications WHERE status IN ('placement_offered', 'fees_pending')";
                $count = (int) $db->query($sql)->fetchColumn();
                if ($count > 0) {
                    $notifications['pending_tasks'][] = [
                        'type' => 'payment_pending',
                        'label' => 'Payments to Record',
                        'count' => $count,
                        'icon' => 'bi-cash-stack',
                        'color' => 'success',
                        'link' => '/pages/manage_admissions.php?tab=payment_pending'
                    ];
                    $notifications['total_count'] += $count;
                }
            }

            // Enrollment Pending
            if (in_array('enrollment_pending', $relevantQueues)) {
                $sql = "SELECT COUNT(*) FROM admission_applications aa
                        JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                        WHERE wi.current_stage = 'enrollment' AND aa.status != 'enrolled'";
                $count = (int) $db->query($sql)->fetchColumn();
                if ($count > 0) {
                    $notifications['pending_tasks'][] = [
                        'type' => 'enrollment_pending',
                        'label' => 'Enrollments to Complete',
                        'count' => $count,
                        'icon' => 'bi-person-check',
                        'color' => 'dark',
                        'link' => '/pages/manage_admissions.php?tab=enrollment_pending'
                    ];
                    $notifications['total_count'] += $count;
                }
            }

            return $this->success($notifications, 'Notifications retrieved');

        } catch (\Exception $e) {
            return $this->error('Failed to fetch notifications: ' . $e->getMessage());
        }
    }

    // Helper for consistent API response
    private function handleResponse($result)
    {
        $response = null;
        if (is_array($result)) {
            if (isset($result['success'])) {
                if ($result['success']) {
                    $response = $this->success($result['data'] ?? null, $result['message'] ?? 'Success');
                } else {
                    $response = $this->badRequest($result['error'] ?? $result['message'] ?? 'Operation failed');
                }
            } else {
                $response = $this->success($result);
            }
        } else {
            $response = $this->success($result);
        }
        return $response;
    }
}
