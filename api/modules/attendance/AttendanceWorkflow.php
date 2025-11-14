<?php
namespace App\API\Modules\Attendance;

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../includes/WorkflowHandler.php';

use App\API\Includes\WorkflowHandler;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Attendance Management Workflow Handler
 * 
 * 5-STAGE WORKFLOW:
 * 1. Attendance Collection → 2. Attendance Verification → 3. Attendance Recording
 * → 4. Notification Dispatch → 5. Report Generation
 * 
 * Database Objects Used:
 * - Tables: attendance, students, classes
 * - Procedures: sp_bulk_mark_student_attendance, sp_generate_attendance_report
 * - Functions: calculate_attendance_percentage
 */
class AttendanceWorkflow extends WorkflowHandler {
    
    public function __construct() {
        parent::__construct('attendance_management');
    }

    /**
     * =======================================================================
     * STAGE 1: ATTENDANCE COLLECTION
     * =======================================================================
     * Role: Teacher
     * Collect attendance for a class on a specific date
     */
    public function collectAttendance($class_id, $date, $attendance_data) {
        try {
            $this->db->beginTransaction();

            // Validate required fields
            if (empty($class_id) || empty($date) || empty($attendance_data)) {
                throw new Exception("Missing required fields: class_id, date, or attendance_data");
            }

            // Check if attendance already collected for this class/date
            $sql = "SELECT id FROM workflow_instances 
                    WHERE workflow_id = (SELECT id FROM workflow_definitions WHERE code = 'attendance_management')
                    AND reference_type = 'attendance_session'
                    AND data_json->>'$.class_id' = :class_id
                    AND data_json->>'$.date' = :date
                    AND status = 'in_progress'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['class_id' => $class_id, 'date' => $date]);
            
            if ($stmt->fetch()) {
                throw new Exception("Attendance already being processed for this class on this date");
            }

            // Store attendance data temporarily (not yet verified)
            $workflow_data = [
                'class_id' => $class_id,
                'date' => $date,
                'attendance_records' => $attendance_data, // Array of {student_id, status}
                'collected_by' => $this->user_id,
                'collected_at' => date('Y-m-d H:i:s'),
                'total_students' => count($attendance_data),
                'present_count' => count(array_filter($attendance_data, fn($a) => $a['status'] === 'present')),
                'absent_count' => count(array_filter($attendance_data, fn($a) => $a['status'] === 'absent'))
            ];

            // Start workflow
            $reference_id = time(); // Temporary ID for attendance session
            $instance_id = $this->startWorkflow('attendance_session', $reference_id, $workflow_data);

            $this->db->commit();

            return formatResponse(true, [
                'workflow_instance_id' => $instance_id,
                'class_id' => $class_id,
                'date' => $date,
                'total_students' => $workflow_data['total_students'],
                'present' => $workflow_data['present_count'],
                'absent' => $workflow_data['absent_count'],
                'next_stage' => 'attendance_verification'
            ], 'Attendance collected successfully. Awaiting verification.');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('attendance_collection_failed', $e->getMessage());
            return formatResponse(false, null, 'Attendance collection failed: ' . $e->getMessage());
        }
    }

    /**
     * =======================================================================
     * STAGE 2: ATTENDANCE VERIFICATION
     * =======================================================================
     * Role: Head Teacher / Class Teacher
     * Verify collected attendance data before recording
     */
    public function verifyAttendance($instance_id, $verified = true, $corrections = []) {
        try {
            $this->db->beginTransaction();

            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance || $instance['current_stage'] !== 'attendance_collection') {
                throw new Exception("Invalid workflow state for attendance verification");
            }

            $instance_data = json_decode($instance['data_json'], true);

            if (!$verified) {
                // Reject and request corrections
                $this->advanceStage($instance_id, 'attendance_collection', 'verification_rejected', [
                    'rejection_reason' => $corrections['reason'] ?? 'Corrections needed',
                    'rejected_by' => $this->user_id,
                    'rejected_at' => date('Y-m-d H:i:s')
                ]);

                $this->db->commit();
                return formatResponse(true, null, 'Attendance rejected. Please correct and resubmit.');
            }

            // Apply any corrections
            if (!empty($corrections)) {
                foreach ($corrections as $correction) {
                    $student_id = $correction['student_id'];
                    $new_status = $correction['status'];
                    
                    // Update the attendance record
                    $key = array_search($student_id, array_column($instance_data['attendance_records'], 'student_id'));
                    if ($key !== false) {
                        $instance_data['attendance_records'][$key]['status'] = $new_status;
                    }
                }

                // Recalculate counts
                $instance_data['present_count'] = count(array_filter($instance_data['attendance_records'], fn($a) => $a['status'] === 'present'));
                $instance_data['absent_count'] = count(array_filter($instance_data['attendance_records'], fn($a) => $a['status'] === 'absent'));
            }

            // Mark as verified
            $instance_data['verified_by'] = $this->user_id;
            $instance_data['verified_at'] = date('Y-m-d H:i:s');

            // Advance to recording stage
            $this->advanceStage($instance_id, 'attendance_verification', 'verified', $instance_data);

            $this->db->commit();

            return formatResponse(true, [
                'instance_id' => $instance_id,
                'present' => $instance_data['present_count'],
                'absent' => $instance_data['absent_count']
            ], 'Attendance verified successfully. Ready for recording.');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('attendance_verification_failed', $e->getMessage());
            return formatResponse(false, null, 'Verification failed: ' . $e->getMessage());
        }
    }

    /**
     * =======================================================================
     * STAGE 3: ATTENDANCE RECORDING
     * =======================================================================
     * Role: System
     * Record verified attendance to database using bulk procedure
     */
    public function recordAttendance($instance_id) {
        try {
            $this->db->beginTransaction();

            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance || $instance['current_stage'] !== 'attendance_verification') {
                throw new Exception("Invalid workflow state for attendance recording");
            }

            $instance_data = json_decode($instance['data_json'], true);

            // Use bulk attendance stored procedure
            // sp_bulk_mark_student_attendance(class_id, date, attendance_json, recorded_by)
            $attendance_json = json_encode($instance_data['attendance_records']);
            
            $sql = "CALL sp_bulk_mark_student_attendance(:class_id, :date, :attendance_json, :recorded_by)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'class_id' => $instance_data['class_id'],
                'date' => $instance_data['date'],
                'attendance_json' => $attendance_json,
                'recorded_by' => $this->user_id
            ]);

            // Get absent students for notifications
            $absent_students = array_filter($instance_data['attendance_records'], fn($a) => $a['status'] === 'absent');
            $instance_data['absent_student_ids'] = array_column($absent_students, 'student_id');

            // Advance to notification stage
            $this->advanceStage($instance_id, 'attendance_recording', 'recorded', $instance_data);

            $this->db->commit();

            return formatResponse(true, [
                'recorded_count' => count($instance_data['attendance_records']),
                'absent_count' => count($absent_students)
            ], 'Attendance recorded successfully.');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('attendance_recording_failed', $e->getMessage());
            return formatResponse(false, null, 'Recording failed: ' . $e->getMessage());
        }
    }

    /**
     * =======================================================================
     * STAGE 4: NOTIFICATION DISPATCH
     * =======================================================================
     * Role: System
     * Send SMS notifications to parents of absent students
     */
    public function dispatchNotifications($instance_id) {
        try {
            $this->db->beginTransaction();

            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance || $instance['current_stage'] !== 'attendance_recording') {
                throw new Exception("Invalid workflow state for notification dispatch");
            }

            $instance_data = json_decode($instance['data_json'], true);
            $absent_student_ids = $instance_data['absent_student_ids'] ?? [];

            if (empty($absent_student_ids)) {
                // No absences - skip notifications
                $this->advanceStage($instance_id, 'notification_dispatch', 'no_absences');
                $this->db->commit();
                
                return formatResponse(true, ['sent' => 0], 'No absence notifications needed.');
            }

            // Get parent phone numbers for absent students
            $placeholders = str_repeat('?,', count($absent_student_ids) - 1) . '?';
            $sql = "SELECT s.id, s.first_name, s.last_name, p.phone 
                    FROM students s
                    JOIN parents p ON s.parent_id = p.id
                    WHERE s.id IN ($placeholders)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($absent_student_ids);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $notifications_sent = 0;
            foreach ($students as $student) {
                $message = "Dear Parent, {$student['first_name']} {$student['last_name']} was marked absent on {$instance_data['date']}. - Kingsway Academy";
                
                // Send SMS (integrate with SMS service)
                $this->sendAbsenceSMS($student['phone'], $message);
                $notifications_sent++;
            }

            $instance_data['notifications_sent'] = $notifications_sent;
            $instance_data['notified_at'] = date('Y-m-d H:i:s');

            // Advance to report generation
            $this->advanceStage($instance_id, 'notification_dispatch', 'notifications_sent', $instance_data);

            $this->db->commit();

            return formatResponse(true, [
                'notifications_sent' => $notifications_sent
            ], 'Absence notifications dispatched successfully.');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('notification_dispatch_failed', $e->getMessage());
            return formatResponse(false, null, 'Notification dispatch failed: ' . $e->getMessage());
        }
    }

    /**
     * =======================================================================
     * STAGE 5: REPORT GENERATION
     * =======================================================================
     * Role: System
     * Generate attendance summary report
     */
    public function generateReport($instance_id) {
        try {
            $this->db->beginTransaction();

            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance || $instance['current_stage'] !== 'notification_dispatch') {
                throw new Exception("Invalid workflow state for report generation");
            }

            $instance_data = json_decode($instance['data_json'], true);

            // Generate attendance report using stored procedure
            // sp_generate_attendance_report(class_id, date)
            $sql = "CALL sp_generate_attendance_report(:class_id, :date, @report_id)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'class_id' => $instance_data['class_id'],
                'date' => $instance_data['date']
            ]);

            // Get generated report ID
            $result = $this->db->query("SELECT @report_id as report_id")->fetch(PDO::FETCH_ASSOC);
            $report_id = $result['report_id'];

            // Complete workflow
            $this->completeWorkflow($instance_id, [
                'report_id' => $report_id,
                'completed_at' => date('Y-m-d H:i:s'),
                'summary' => [
                    'class_id' => $instance_data['class_id'],
                    'date' => $instance_data['date'],
                    'total' => $instance_data['total_students'],
                    'present' => $instance_data['present_count'],
                    'absent' => $instance_data['absent_count'],
                    'percentage' => round(($instance_data['present_count'] / $instance_data['total_students']) * 100, 2)
                ]
            ]);

            $this->db->commit();

            return formatResponse(true, [
                'report_id' => $report_id,
                'attendance_percentage' => round(($instance_data['present_count'] / $instance_data['total_students']) * 100, 2)
            ], 'Attendance workflow completed successfully.');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('report_generation_failed', $e->getMessage());
            return formatResponse(false, null, 'Report generation failed: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    private function sendAbsenceSMS($phone, $message) {
        // TODO: Integrate with SMS service
        $this->logAction('sms_sent', 0, "Absence notification sent to $phone: $message");
    }

    /**
     * Get attendance summary for a date range
     */
    public function getAttendanceSummary($class_id, $start_date, $end_date) {
        try {
            $sql = "SELECT 
                        DATE(attendance_date) as date,
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                        ROUND((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as percentage
                    FROM attendance
                    WHERE class_id = :class_id
                    AND attendance_date BETWEEN :start_date AND :end_date
                    GROUP BY DATE(attendance_date)
                    ORDER BY date DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'class_id' => $class_id,
                'start_date' => $start_date,
                'end_date' => $end_date
            ]);

            $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, $summary, 'Attendance summary retrieved successfully');

        } catch (Exception $e) {
            $this->logError('get_attendance_summary_failed', $e->getMessage());
            return formatResponse(false, null, 'Failed to retrieve summary: ' . $e->getMessage());
        }
    }
}

