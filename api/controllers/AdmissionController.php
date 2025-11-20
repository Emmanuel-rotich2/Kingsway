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

    // Explicit REST endpoints for all StudentAdmissionWorkflow public methods

    // 1. Application Submission
    public function submitApplication($id = null, $data = [], $segments = [])
    {
        $result = $this->api->submitApplication($data);
        return $this->handleResponse($result);
    }

    // 2. Document Upload
    public function uploadDocument($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $document_type = $data['document_type'] ?? null;
        $file = $data['file'] ?? null;
        $result = $this->api->uploadDocument($application_id, $document_type, $file);
        return $this->handleResponse($result);
    }

    // 3. Document Verification
    public function verifyDocument($id = null, $data = [], $segments = [])
    {
        $document_id = $data['document_id'] ?? $id;
        $status = $data['status'] ?? null;
        $notes = $data['notes'] ?? '';
        $result = $this->api->verifyDocument($document_id, $status, $notes);
        return $this->handleResponse($result);
    }

    // 4. Interview Scheduling
    public function scheduleInterview($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $interview_date = $data['interview_date'] ?? null;
        $interview_time = $data['interview_time'] ?? null;
        $venue = $data['venue'] ?? 'Main Office';
        $result = $this->api->scheduleInterview($application_id, $interview_date, $interview_time, $venue);
        return $this->handleResponse($result);
    }

    // 5. Interview Assessment
    public function recordInterviewResults($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $assessment_data = $data['assessment_data'] ?? $data;
        $result = $this->api->recordInterviewResults($application_id, $assessment_data);
        return $this->handleResponse($result);
    }

    // 6. Placement Offer
    public function generatePlacementOffer($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $assigned_class_id = $data['assigned_class_id'] ?? null;
        $result = $this->api->generatePlacementOffer($application_id, $assigned_class_id);
        return $this->handleResponse($result);
    }

    // 7. Fee Payment
    public function recordFeePayment($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $payment_data = $data['payment_data'] ?? $data;
        $result = $this->api->recordFeePayment($application_id, $payment_data);
        return $this->handleResponse($result);
    }

    // 8. Enrollment
    public function completeEnrollment($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $result = $this->api->completeEnrollment($application_id);
        return $this->handleResponse($result);
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
