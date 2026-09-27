<?php

namespace App\API\Modules\attendance;

use App\API\Includes\WorkflowHandler;

class AttendanceWorkflow extends WorkflowHandler
{
    public function __construct()
    {
        parent::__construct('attendance_management');
    }

    // Stage 1: Collect attendance (start workflow instance)
    public function collectAttendance($classId, $date, $records)
    {
        $workflowData = [
            'class_id' => $classId,
            'date' => $date,
            'attendance_records' => $records,
            'collected_by' => $this->user_id,
            'collected_at' => date('Y-m-d H:i:s'),
        ];
        $referenceId = $classId . '_' . $date;
        return $this->startWorkflow('attendance_session', $referenceId, $workflowData);
    }

    // Stage 2: Verify attendance (advance workflow)
    public function verifyAttendance($instanceId, $verified = true, $corrections = [])
    {
        $instance = $this->getWorkflowInstance($instanceId);
        if (!$instance) return false;
        $data = $instance['data'];
        if (!$verified) {
            $this->advanceStage($instanceId, 'attendance_collection', 'verification_rejected', [
                'rejection_reason' => $corrections['reason'] ?? 'Corrections needed',
                'rejected_by' => $this->user_id,
                'rejected_at' => date('Y-m-d H:i:s')
            ]);
            return false;
        }
        // Apply corrections if any
        if (!empty($corrections)) {
            foreach ($corrections as $correction) {
                foreach ($data['attendance_records'] as &$rec) {
                    if ($rec['student_id'] == $correction['student_id']) {
                        $rec['status'] = $correction['status'];
                    }
                }
            }
        }
        $data['verified_by'] = $this->user_id;
        $data['verified_at'] = date('Y-m-d H:i:s');
        $this->advanceStage($instanceId, 'attendance_verification', 'verified', $data);
        return true;
    }

    // Stage 3: Record attendance (direct INSERT — sp_bulk_mark_student_attendance is deprecated)
    public function recordAttendance($instanceId)
    {
        $instance = $this->getWorkflowInstance($instanceId);
        if (!$instance) return false;
        $data = $instance['data'];
        foreach ($data['attendance_records'] as $record) {
            $sql = "INSERT INTO student_attendance (student_academic_enrollment_id, date, status, marked_by, created_at)
                    SELECT sae.id, ?, ?, ?, NOW()
                    FROM student_academic_enrollments sae
                    JOIN students s ON s.id=sae.student_id
                    LEFT JOIN admission_applications aa ON aa.id=s.application_id
                    WHERE sae.student_id = ? AND sae.enrollment_status = 'active'
                      AND COALESCE(CASE WHEN s.entry_source = 'admission' THEN aa.enrolled_at END, CASE WHEN s.entry_source IS NULL OR s.entry_source <> 'admission' THEN sae.enrolled_on END) <= ?
                    LIMIT 1";
                $this->db->prepare($sql)->execute([
                    $data['date'],
                    $record['status'],
                    $this->user_id,
                    $record['student_id'],
                    $data['date']
                ]);
        }
        $this->advanceStage($instanceId, 'attendance_recording', 'recorded', $data);
        return true;
    }

    // Stage 4: Generate report (call procedure or view)
    public function generateReport($classId, $termId = null)
    {
        // Use procedure or view for reporting
        $params = [$classId];
        if ($termId !== null) $params[] = $termId;
        return $this->callProcedure('sp_generate_student_report', $params);
    }
}
