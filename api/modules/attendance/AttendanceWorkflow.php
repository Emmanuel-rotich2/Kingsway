<?php

namespace App\API\Modules\Attendance;

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

    // Stage 3: Record attendance (call procedure, triggers fire)
    public function recordAttendance($instanceId)
    {
        $instance = $this->getWorkflowInstance($instanceId);
        if (!$instance) return false;
        $data = $instance['data'];
        $attendanceJson = json_encode($data['attendance_records']);
        // Use stored procedure for bulk insert
        $this->callProcedure('sp_bulk_mark_student_attendance', [
            $data['class_id'],
            $data['date'],
            $attendanceJson,
            $this->user_id
        ], false);
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
