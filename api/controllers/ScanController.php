<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Modules\transport\StudentTransportEntitlementManager;
use App\Database\Database;
use PDO;

/**
 * Authenticated staff scanning endpoint for school-issued learner cards.
 *
 * QR contents are treated as an opaque credential. The endpoint deliberately
 * does not trust names, balances, or portal URLs encoded in a QR image.
 */
class ScanController extends BaseController {

    private PDO $pdo;
    private StudentTransportEntitlementManager $transportEntitlements;

    private \App\API\Services\GateScanService $scanService;

    public function __construct() {
        parent::__construct();
        $this->pdo = $this->db;
        $this->transportEntitlements = $this->contract('App\API\Modules\transport\StudentTransportEntitlementManager', Database::getInstance()->getConnection());
        $this->scanService = new \App\API\Services\GateScanService($this->pdo, $this->transportEntitlements);
    }

    /* =========================================================
       POST /api/scan/verify
       Body: { qr_data, context, operator_id, session_id? }
       ========================================================= */

    public function postVerify(int $id = null, array $data = [], array $segments = []): array {
        $qrData     = trim((string)($data['qr_data'] ?? ''));
        $context    = strtolower(trim((string)($data['context'] ?? 'gate')));
        $operatorId = (int)($this->user['user_id'] ?? $this->user['id'] ?? 0);
        $sessionId  = (int)($data['session_id'] ?? 0);
        $action     = strtolower(trim((string)($data['action'] ?? '')));
        $tripSession = strtolower(trim((string)($data['trip_session'] ?? 'morning_pickup')));
        $clientReference = trim((string)($data['client_reference'] ?? ''));

        if (!$this->user || $operatorId <= 0) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['driver', 'transport_officer', 'admin', 'school_administrator', 'director', 'headteacher', 'deputy_headteacher', 'teacher'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to scan learner cards');
        }

        if ($qrData === '') {
            return ['success' => false, 'message' => 'qr_data is required', 'status' => 400];
        }

        $validContexts = ['transport', 'exam', 'gate', 'attendance'];
        if (!in_array($context, $validContexts, true)) {
            return ['success' => false, 'message' => "Invalid context. Must be: " . implode(', ', $validContexts), 'status' => 400];
        }

        $student = $this->scanService->resolveStudent($qrData);
        if (!$student) {
            $this->scanService->recordScanEvent(null, $operatorId, $context, $action, 'rejected', null, $clientReference, 'Invalid or inactive learner credential');
            return ['success' => false, 'message' => 'Student not found', 'status' => 404];
        }

        $result = match($context) {
            'transport'  => $this->scanService->processTransport($student, $operatorId, $action, $tripSession),
            'exam'       => $this->scanService->verifyExam($student),
            'gate'       => $this->scanService->verifyGate($student),
            'attendance' => $this->scanService->recordAttendance($student, $operatorId, $sessionId),
            default      => ['eligible' => false, 'message' => 'Unknown context'],
        };

        $output = array_merge([
            'success' => true,
            'context' => $context,
            'student' => [
                'id'           => $student['student_id'],
                'admission_no' => $student['admission_no'],
                'first_name'   => $student['first_name'],
                'last_name'    => $student['last_name'],
                'full_name'    => trim($student['first_name'] . ' ' . $student['last_name']),
                'class_name'   => $student['class_name'] ?? '',
                'stream_name'  => $student['stream_name'] ?? '',
                'student_type' => $student['student_type'] ?? '',
            ],
            'scanned_at' => date('Y-m-d H:i:s'),
        ], $result);

        $this->scanService->recordScanEvent(
            (int)$student['student_id'],
            $operatorId,
            $context,
            $action,
            !empty($output['eligible']) ? 'accepted' : 'rejected',
            $output['record']['id'] ?? $output['attendance_id'] ?? null,
            $clientReference,
            $output['message'] ?? null
        );

        return $output;
    }

    /* =========================================================
       Resolve student — SINGLE combined query.
       Handles: JSON payload, card number, numeric ID, qr_token.
       ========================================================= */



    /* =========================================================
       TRANSPORT — single combined query (assignment + route + bills)
       ========================================================= */



    /** Record a transport boarding/drop-off scan against the normalized attendance table. */




    /* =========================================================
       EXAM — single combined query (fee clearance + exam count)
       ========================================================= */



    /* =========================================================
       GATE — two queries (attendance + parents), transport is cached
       ========================================================= */



    /* =========================================================
       ATTENDANCE — INSERT IGNORE (single query, no race condition)
       ========================================================= */


}
