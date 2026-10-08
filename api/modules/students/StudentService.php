<?php
declare(strict_types=1);

namespace App\API\Modules\students;
use App\API\Services\ReadReplicaService;

use Exception;
use PDO;

class StudentService
{
    private StudentPermissionService $permissions;
    public StudentScopeService $scopeService;
    private StudentRepository $repository;

    public function __construct(PDO $db)
    {
        $this->permissions = new StudentPermissionService();
        $this->scopeService = new StudentScopeService($db);
        $this->repository = new StudentRepository($db);
    }

    public function resolveContext(array $user, ?string $requestedContext): array
    {
        $context = $this->permissions->normalizeContext($requestedContext)
            ?? $this->permissions->defaultContextForUser($user);

        if (!$context) {
            return [
                'allowed' => false,
                'context' => null,
                'message' => 'No student context is available for this user',
            ];
        }

        if (!$this->permissions->canAccessContext($user, $context)) {
            return [
                'allowed' => false,
                'context' => $context,
                'message' => 'You are not allowed to access this student context',
            ];
        }

        return [
            'allowed' => true,
            'context' => $context,
            'actions' => $this->permissions->actionsForContext($context),
            'fields' => $this->permissions->fieldsForContext($context),
        ];
    }

    public function listForContext(array $user, string $context, array $filters = []): array
    {
        $scope = $this->scopeService->buildScope($context, $user);
        [$conditions, $bindings] = $this->scopeService->whereClause($scope);
        $result = $this->repository->listScoped($conditions, $bindings, $filters);

        $students = array_map(function (array $student) use ($context): array {
            return $this->permissions->filterStudentFields($student, $context);
        }, $result['students']);

        return [
            'students' => $students,
            'context' => $context,
            'actions' => $this->permissions->actionsForContext($context),
            'fields' => $this->permissions->fieldsForContext($context),
            'pagination' => $result['pagination'],
        ];
    }

    public function profileForContext(array $user, string $context, int $studentId): ?array
    {
        $scope = $this->scopeService->buildScope($context, $user);
        [$conditions, $bindings] = $this->scopeService->whereClause($scope);
        $student = $this->repository->findScoped($studentId, $conditions, $bindings);

        if (!$student) {
            return null;
        }

        return [
            'student' => $this->permissions->filterStudentFields($student, $context),
            'tabs' => $this->tabsForContext($context),
            'context' => $context,
            'actions' => $this->permissions->actionsForContext($context),
        ];
    }

    private function tabsForContext(string $context): array
    {
        $tabs = ['summary'];
        switch ($context) {
            case 'full_management':
                return ['summary', 'guardians', 'academic', 'boarding', 'transport', 'discipline', 'finance'];
            case 'oversight':
                return ['summary', 'academic', 'discipline'];
            case 'academic':
            case 'teacher_class':
            case 'subject_teacher':
                return ['summary', 'academic'];
            case 'discipline':
                return ['summary', 'discipline'];
            case 'boarding':
                return ['summary', 'boarding'];
            case 'transport':
                return ['summary', 'transport'];
            case 'catering':
                return ['summary', 'meal_planning'];
            case 'welfare':
                return ['summary', 'welfare'];
            case 'parent_children':
                return ['summary', 'academic'];
            default:
                return $tabs;
        }
    }

    /* =====================================================
     * ID CARD MANAGEMENT METHODS
     * ===================================================== */

    /**
     * Get ID card metadata (academic years, classes, streams, statuses, school settings)
     */
    public function getIdCardMeta(array $user): array
    {
        try {
            $db = $this->repository->getDb();

            // Get academic years
            $stmt = $db->prepare("SELECT id, year_code, year_name, status FROM academic_years ORDER BY year_code DESC");
            $stmt->execute();
            $academicYears = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get classes
            $stmt = $db->prepare("SELECT id, name FROM classes ORDER BY name");
            $stmt->execute();
            $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get streams
            $stmt = $db->prepare("SELECT class_stream_id AS id, stream_name, class_id
                                  FROM " . \App\API\Services\ReadReplicaService::qualifiedRef('academic_calendar') . "
                                  WHERE class_stream_status = 'active'
                                  ORDER BY stream_name");
            $stmt->execute();
            $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get school profile
            $stmt = $db->query("SELECT school_name, address AS school_address, phone AS school_phone, email AS school_email, website AS school_website, motto AS school_motto FROM school_profile LIMIT 1");
            $schoolSettings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            // Get headteacher from staff table
            $hStmt = $db->query("SELECT CONCAT(first_name,' ',last_name) FROM " . \App\API\Services\ReadReplicaService::qualifiedRef('staff_directory') . " WHERE position = 'Headteacher' LIMIT 1");
            $schoolSettings['headteacher_name'] = $hStmt->fetchColumn() ?: '';
            // Add ID-card-specific settings from school_settings
            $stmt2 = $db->prepare("SELECT setting_key, setting_value FROM school_settings WHERE setting_key IN ('authorized_signature', 'card_expiry_years', 'card_prefix')");
            $stmt2->execute();
            foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $schoolSettings[$row['setting_key']] = $row['setting_value'];
            }

            // Card statuses enum
            $cardStatuses = [
                'not_generated' => 'Not Generated',
                'generated' => 'Generated',
                'printed' => 'Printed',
                'issued' => 'Issued',
                'lost' => 'Lost',
                'replaced' => 'Replaced',
                'revoked' => 'Revoked'
            ];

            return [
                'success' => true,
                'data' => [
                    'academic_years' => $academicYears,
                    'classes' => $classes,
                    'streams' => $streams,
                    'card_statuses' => $cardStatuses,
                    'school_profile' => $schoolSettings,
                    'current_year' => date('Y')
                ]
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("StudentService::getIdCardMeta error: " . $e->getMessage());
            return [
                'success' => false,
                'data' => [
                    'academic_years' => [],
                    'classes' => [],
                    'streams' => [],
                    'card_statuses' => [],
                    'school_profile' => [],
                    'current_year' => date('Y')
                ]
            ];
        }
    }

    /**
     * Get students with ID card status with filters
     */
    public function getIdCards(array $user, array $filters = []): array
    {
        try {
            $db = $this->repository->getDb();
            $conditions = [];
            $bindings = [];

            $scope = $this->scopeService->buildScope('full_management', $user);
            [$scopeConditions, $scopeBindings] = $this->scopeService->whereClause($scope);
            if ($scopeConditions) {
                $conditions = array_merge($conditions, $scopeConditions);
            }

            $placementView = ReadReplicaService::qualifiedRef('learner_placement');
            $academicYear = trim((string)($filters['academic_year'] ?? ''));
            $placementYear = $academicYear !== ''
                ? '?'
                : '(SELECT ay.id FROM ' . ReadReplicaService::qualifiedRef('academic_years') . ' ay WHERE ay.is_current = 1 ORDER BY ay.id DESC LIMIT 1)';
            if ($academicYear !== '') {
                $bindings[] = $academicYear;
            }
            $bindings = array_merge($bindings, $scopeBindings);
            $placementJoin = "
                LEFT JOIN (
                    SELECT lp_latest.student_id, MAX(lp_latest.enrollment_id) AS enrollment_id
                    FROM {$placementView} lp_latest
                    WHERE lp_latest.academic_year_id = {$placementYear}
                    GROUP BY lp_latest.student_id
                ) latest_lp ON latest_lp.student_id = s.id
                LEFT JOIN {$placementView} lp ON lp.enrollment_id = latest_lp.enrollment_id";

            foreach ([
                'class_id' => 'lp.class_id',
                'stream_id' => 'lp.stream_id',
                'gender' => 'per.gender',
                'student_status' => 's.status',
                'card_status' => 'sic.status',
                'expiry_year' => 'sic.expiry_year',
            ] as $filter => $column) {
                if (isset($filters[$filter]) && $filters[$filter] !== '') {
                    $conditions[] = "{$column} = ?";
                    $bindings[] = $filters[$filter];
                }
            }

            if (!empty($filters['issue_year'])) {
                $conditions[] = 'YEAR(sic.issue_date) = ?';
                $bindings[] = (int)$filters['issue_year'];
            }
            if (!empty($filters['new_students_only'])) {
                $conditions[] = 'sic.id IS NULL';
            }
            if (array_key_exists('has_photo', $filters) && $filters['has_photo'] !== '') {
                $conditions[] = $filters['has_photo'] === 'true'
                    ? "per.photo_url IS NOT NULL AND per.photo_url <> ''"
                    : "(per.photo_url IS NULL OR per.photo_url = '')";
            }
            if (!empty($filters['search'])) {
                $term = '%' . trim((string)$filters['search']) . '%';
                $conditions[] = '(s.admission_no LIKE ? OR per.first_name LIKE ? OR per.last_name LIKE ?)';
                array_push($bindings, $term, $term, $term);
            }

            $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
            $page = max(1, (int)($filters['page'] ?? 1));
            $limit = min(100, max(1, (int)($filters['limit'] ?? 50)));
            $offset = ($page - 1) * $limit;

            $from = "
                FROM " . ReadReplicaService::qualifiedRef('students') . " s
                JOIN " . ReadReplicaService::qualifiedRef('persons') . " per ON per.id = s.person_id
                {$placementJoin}
                LEFT JOIN student_types st ON st.id = s.student_type_id
                LEFT JOIN (
                    SELECT student_id, MAX(id) AS card_id
                    FROM student_id_cards
                    GROUP BY student_id
                ) latest_card ON latest_card.student_id = s.id
                LEFT JOIN student_id_cards sic ON sic.id = latest_card.card_id";

            $countStmt = $db->prepare("SELECT COUNT(DISTINCT s.id) {$from} {$whereClause}");
            $countStmt->execute($bindings);
            $total = (int)$countStmt->fetchColumn();

            $sql = "SELECT DISTINCT
                        s.id,
                        s.admission_no,
                        per.first_name,
                        per.last_name,
                        per.gender,
                        s.status AS student_status,
                        per.photo_url,
                        per.dob AS date_of_birth,
                        lp.academic_year_id,
                        lp.class_id,
                        lp.class_name,
                        lp.stream_id,
                        lp.stream_name,
                        sic.id AS card_id,
                        sic.card_number,
                        sic.status AS card_status,
                        sic.qr_token,
                        sic.issue_date,
                        sic.expiry_year,
                        sic.generated_at,
                        sic.printed_at,
                        sic.issued_at
                    {$from}
                    {$whereClause}
                    ORDER BY per.last_name, per.first_name
                    LIMIT {$limit} OFFSET {$offset}";
            $stmt = $db->prepare($sql);
            $stmt->execute($bindings);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $students,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / $limit),
                ],
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("StudentService::getIdCards error: " . $e->getMessage());
            return [
                'success' => false,
                'data' => [],
                'pagination' => ['page' => 1, 'limit' => 50, 'total' => 0, 'pages' => 0],
            ];
        }
    }

    /**
     * Get full ID card details for a student
     */
    public function getIdCardDetails(int $studentId): ?array
    {
        try {
            $db = $this->repository->getDb();

            // Use the governed directory projection for learner and placement
            // details, then attach the latest card from the primary database.
            $directory = ReadReplicaService::qualifiedRef('student_directory');
            $sql = "SELECT
                        sd.student_id AS id,
                        sd.admission_no,
                        sd.first_name,
                        sd.middle_name,
                        sd.last_name,
                        sd.gender,
                        sd.dob AS date_of_birth,
                        sd.photo_url,
                        sd.student_status,
                        sic.academic_year_id,
                        sd.class_name,
                        sd.stream_name,
                        sic.id AS card_id,
                        sic.card_number,
                        sic.status AS card_status,
                        sic.qr_token,
                        sic.qr_payload,
                        sic.qr_code_path,
                        sic.issue_date,
                        sic.expiry_year,
                        sic.generated_at,
                        sic.printed_at,
                        sic.issued_at,
                        sic.notes
                    FROM {$directory} sd
                    LEFT JOIN student_id_cards sic ON sic.student_id = sd.student_id
                      AND sic.id = (
                          SELECT MAX(sic_latest.id)
                          FROM student_id_cards sic_latest
                          WHERE sic_latest.student_id = sd.student_id
                      )
                    WHERE sd.student_id = ?
                    LIMIT 1";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$studentId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                return null;
            }

            // Get school profile
            $stmt = $db->query("SELECT school_name, address AS school_address, phone AS school_phone, email AS school_email, website AS school_website, motto AS school_motto FROM school_profile LIMIT 1");
            $schoolSettings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            // Get headteacher from staff table
            $hStmt = $db->query("SELECT CONCAT(first_name,' ',last_name) FROM " . ReadReplicaService::qualifiedRef('staff_directory') . " WHERE position = 'Headteacher' LIMIT 1");
            $schoolSettings['headteacher_name'] = $hStmt->fetchColumn() ?: '';

            // Get card history
            $stmt = $db->prepare("SELECT 
                        h.action,
                        h.from_status,
                        h.to_status,
                        h.remarks,
                        h.performed_at,
                        up.first_name,
                        up.last_name
                    FROM student_id_card_history h
                    LEFT JOIN users u ON h.performed_by = u.id
                    LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " up ON up.id = u.person_id
                    WHERE h.student_id = ?
                    ORDER BY h.performed_at DESC");
            $stmt->execute([$studentId]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'student' => $student,
                'school_settings' => $schoolSettings,
                'card_history' => $history
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("StudentService::getIdCardDetails error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate ID card for a student with unique card number, QR token, and expiry
     */
    public function generateIdCard(int $studentId, ?int $userId = null): array
    {
        $db = $this->repository->getDb();

        try {
            $db->beginTransaction();

            // Check if student exists
            $stmt = $db->prepare("SELECT s.id, s.admission_no, sae.academic_year_id FROM " . ReadReplicaService::qualifiedRef("student_directory") . "  WHERE s.id = ?");
            $stmt->execute([$studentId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                throw new Exception('Student not found');
            }

            // Use academic year from enrollment, or current year if not enrolled
            $academicYearId = $student['academic_year_id'] ?? null;

            // Check if active card already exists
            $stmt = $db->prepare("SELECT id, status FROM student_id_cards WHERE student_id = ? AND status NOT IN ('replaced', 'revoked') ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$studentId]);
            $existingCard = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingCard && $existingCard['status'] !== 'lost') {
                $db->rollBack();
                return ['success' => false, 'message' => 'Student already has an active card'];
            }

            // Get school settings
            $stmt = $db->prepare("SELECT setting_value FROM school_settings WHERE setting_key IN ('card_prefix', 'card_expiry_years')");
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $cardPrefix = $settings['card_prefix'] ?? 'KPA-ID';
            $expiryYears = (int)($settings['card_expiry_years'] ?? 2);
            $currentYear = (int)date('Y');
            $expiryYear = $currentYear + $expiryYears;

            // Generate unique card number: KPA-ID-YYYY-XXXXXX
            $cardNumber = $this->generateUniqueCardNumber($db, $cardPrefix, $currentYear);

            // Generate QR token
            $qrToken = $this->generateQrToken($db);

            // Generate QR payload
            $qrPayload = json_encode([
                'card_number' => $cardNumber,
                'student_id' => $studentId,
                'admission_no' => $student['admission_no'],
                'token' => $qrToken,
                'issued' => date('Y-m-d')
            ]);

            // Create the card record
            $stmt = $db->prepare("INSERT INTO student_id_cards 
                (student_id, card_number, qr_token, qr_payload, academic_year_id, expiry_year, status, generated_by, generated_at, issue_date)
                VALUES (?, ?, ?, ?, ?, ?, 'generated', ?, NOW(), CURDATE())");
            $stmt->execute([
                $studentId,
                $cardNumber,
                $qrToken,
                $qrPayload,
                $academicYearId,
                $expiryYear,
                $userId
            ]);

            $cardId = (int)$db->lastInsertId();

            // Record history
            $this->recordCardHistory($db, $cardId, $studentId, 'generated', null, 'generated', 'Card generated', $userId);

            $db->commit();

            return [
                'success' => true,
                'message' => 'ID card generated successfully',
                'card_id' => $cardId,
                'card_number' => $cardNumber,
                'qr_token' => $qrToken,
                'expiry_year' => $expiryYear
            ];
        } catch (Exception $e) {
            $db->rollBack();
            \App\API\Services\Logger::legacyError("StudentService::generateIdCard error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An internal error occurred.'];
        }
    }

    /**
     * Bulk generate ID cards for multiple students
     */
    public function generateIdCardsBulk(array $studentIds, ?int $userId = null): array
    {
        $results = [
            'success' => true,
            'generated' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($studentIds as $studentId) {
            $result = $this->generateIdCard((int)$studentId, $userId);
            if ($result['success']) {
                $results['generated']++;
            } else {
                $results['failed']++;
                $results['errors'][$studentId] = $result['message'];
            }
        }

        if ($results['failed'] > 0) {
            $results['success'] = false;
            $results['message'] = "Generated {$results['generated']} cards, {$results['failed']} failed";
        } else {
            $results['message'] = "Successfully generated {$results['generated']} ID cards";
        }

        return $results;
    }

    /**
     * Generate QR code for a card
     */
    public function generateCardQrCode(int $cardId, ?int $userId = null): array
    {
        $db = $this->repository->getDb();

        try {
            $db->beginTransaction();

            // Get card details
            $stmt = $db->prepare("SELECT id, card_number, qr_token, student_id, status FROM student_id_cards WHERE id = ?");
            $stmt->execute([$cardId]);
            $card = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$card) {
                throw new Exception('Card not found');
            }

            if ($card['status'] === 'revoked') {
                throw new Exception('Cannot generate QR for revoked card');
            }

            // Generate QR code file path (placeholder - actual QR generation would use a library)
            $qrCodePath = '/images/qr_codes/' . $card['card_number'] . '.png';

            // Update card with QR code path
            $stmt = $db->prepare("UPDATE student_id_cards SET qr_code_path = ? WHERE id = ?");
            $stmt->execute([$qrCodePath, $cardId]);

            // Record history
            $this->recordCardHistory($db, $cardId, $card['student_id'], 'qr_generated', $card['status'], $card['status'], 'QR code generated', $userId);

            $db->commit();

            return [
                'success' => true,
                'message' => 'QR code generated successfully',
                'qr_code_path' => $qrCodePath
            ];
        } catch (Exception $e) {
            $db->rollBack();
            \App\API\Services\Logger::legacyError("StudentService::generateCardQrCode error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An internal error occurred.'];
        }
    }

    /**
     * Mark card as printed
     */
    public function markCardPrinted(int $cardId, ?int $userId = null): array
    {
        $db = $this->repository->getDb();

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT id, student_id, status FROM student_id_cards WHERE id = ?");
            $stmt->execute([$cardId]);
            $card = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$card) {
                throw new Exception('Card not found');
            }

            $stmt = $db->prepare("UPDATE student_id_cards SET status = 'printed', printed_at = NOW() WHERE id = ?");
            $stmt->execute([$cardId]);

            $this->recordCardHistory($db, $cardId, $card['student_id'], 'printed', $card['status'], 'printed', 'Card marked as printed', $userId);

            $db->commit();

            return ['success' => true, 'message' => 'Card marked as printed'];
        } catch (Exception $e) {
            $db->rollBack();
            \App\API\Services\Logger::legacyError("StudentService::markCardPrinted error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An internal error occurred.'];
        }
    }

    /**
     * Mark card as issued
     */
    public function markCardIssued(int $cardId, ?int $userId = null): array
    {
        $db = $this->repository->getDb();

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT id, student_id, status FROM student_id_cards WHERE id = ?");
            $stmt->execute([$cardId]);
            $card = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$card) {
                throw new Exception('Card not found');
            }

            $stmt = $db->prepare("UPDATE student_id_cards SET status = 'issued', issued_at = NOW(), issued_by = ? WHERE id = ?");
            $stmt->execute([$userId, $cardId]);

            $this->recordCardHistory($db, $cardId, $card['student_id'], 'issued', $card['status'], 'issued', 'Card issued to student', $userId);

            $db->commit();

            return ['success' => true, 'message' => 'Card marked as issued'];
        } catch (Exception $e) {
            $db->rollBack();
            \App\API\Services\Logger::legacyError("StudentService::markCardIssued error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An internal error occurred.'];
        }
    }

    /**
     * Mark card as lost
     */
    public function markCardLost(int $cardId, ?int $userId = null): array
    {
        $db = $this->repository->getDb();

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT id, student_id, status FROM student_id_cards WHERE id = ?");
            $stmt->execute([$cardId]);
            $card = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$card) {
                throw new Exception('Card not found');
            }

            $stmt = $db->prepare("UPDATE student_id_cards SET status = 'lost', lost_at = NOW() WHERE id = ?");
            $stmt->execute([$cardId]);

            $this->recordCardHistory($db, $cardId, $card['student_id'], 'marked_lost', $card['status'], 'lost', 'Card reported as lost', $userId);

            $db->commit();

            return ['success' => true, 'message' => 'Card marked as lost'];
        } catch (Exception $e) {
            $db->rollBack();
            \App\API\Services\Logger::legacyError("StudentService::markCardLost error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An internal error occurred.'];
        }
    }

    /**
     * Renew expired card
     */
    public function renewCard(int $cardId, ?int $userId = null): array
    {
        $db = $this->repository->getDb();

        try {
            $db->beginTransaction();

            // Get old card
            $stmt = $db->prepare("SELECT id, student_id, card_number, academic_year_id, status FROM student_id_cards WHERE id = ?");
            $stmt->execute([$cardId]);
            $oldCard = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$oldCard) {
                throw new Exception('Card not found');
            }

            // Get student info
            $stmt = $db->prepare("SELECT admission_no, academic_year_id FROM students WHERE id = ?");
            $stmt->execute([$oldCard['student_id']]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get school settings
            $stmt = $db->prepare("SELECT setting_value FROM school_settings WHERE setting_key IN ('card_prefix', 'card_expiry_years')");
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $cardPrefix = $settings['card_prefix'] ?? 'KPA-ID';
            $expiryYears = (int)($settings['card_expiry_years'] ?? 2);
            $currentYear = (int)date('Y');
            $expiryYear = $currentYear + $expiryYears;

            // Generate new card number
            $newCardNumber = $this->generateUniqueCardNumber($db, $cardPrefix, $currentYear);
            $newQrToken = $this->generateQrToken($db);

            $qrPayload = json_encode([
                'card_number' => $newCardNumber,
                'student_id' => $oldCard['student_id'],
                'admission_no' => $student['admission_no'],
                'token' => $newQrToken,
                'issued' => date('Y-m-d')
            ]);

            // Create new card
            $stmt = $db->prepare("INSERT INTO student_id_cards 
                (student_id, card_number, qr_token, qr_payload, academic_year_id, expiry_year, status, generated_by, generated_at, issue_date, replaced_from_card_id, replacement_reason)
                VALUES (?, ?, ?, ?, ?, ?, 'generated', ?, NOW(), CURDATE(), ?, 'expired')");
            $stmt->execute([
                $oldCard['student_id'],
                $newCardNumber,
                $newQrToken,
                $qrPayload,
                $student['academic_year_id'],
                $expiryYear,
                $userId,
                $cardId
            ]);

            $newCardId = (int)$db->lastInsertId();

            // Mark old card as replaced
            $stmt = $db->prepare("UPDATE student_id_cards SET status = 'replaced', replaced_at = NOW() WHERE id = ?");
            $stmt->execute([$cardId]);

            // Record history for old card
            $this->recordCardHistory($db, $cardId, $oldCard['student_id'], 'renewed', $oldCard['status'], 'replaced', 'Card renewed - replaced by new card', $userId);

            // Record history for new card
            $this->recordCardHistory($db, $newCardId, $oldCard['student_id'], 'generated', null, 'generated', 'Card generated (renewal)', $userId);

            $db->commit();

            return [
                'success' => true,
                'message' => 'Card renewed successfully',
                'new_card_id' => $newCardId,
                'new_card_number' => $newCardNumber
            ];
        } catch (Exception $e) {
            $db->rollBack();
            \App\API\Services\Logger::legacyError("StudentService::renewCard error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An internal error occurred.'];
        }
    }

    /**
     * Replace lost/damaged card
     */
    public function replaceCard(int $cardId, string $reason, ?int $userId = null): array
    {
        $db = $this->repository->getDb();

        try {
            $db->beginTransaction();

            // Get old card
            $stmt = $db->prepare("SELECT id, student_id, card_number, academic_year_id, status FROM student_id_cards WHERE id = ?");
            $stmt->execute([$cardId]);
            $oldCard = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$oldCard) {
                throw new Exception('Card not found');
            }

            // Get student info
            $stmt = $db->prepare("SELECT admission_no, academic_year_id FROM students WHERE id = ?");
            $stmt->execute([$oldCard['student_id']]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get school settings
            $stmt = $db->prepare("SELECT setting_value FROM school_settings WHERE setting_key IN ('card_prefix', 'card_expiry_years')");
            $stmt->execute();
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $cardPrefix = $settings['card_prefix'] ?? 'KPA-ID';
            $expiryYears = (int)($settings['card_expiry_years'] ?? 2);
            $currentYear = (int)date('Y');
            $expiryYear = $currentYear + $expiryYears;

            // Generate new card number
            $newCardNumber = $this->generateUniqueCardNumber($db, $cardPrefix, $currentYear);
            $newQrToken = $this->generateQrToken($db);

            $qrPayload = json_encode([
                'card_number' => $newCardNumber,
                'student_id' => $oldCard['student_id'],
                'admission_no' => $student['admission_no'],
                'token' => $newQrToken,
                'issued' => date('Y-m-d')
            ]);

            // Create new card
            $stmt = $db->prepare("INSERT INTO student_id_cards 
                (student_id, card_number, qr_token, qr_payload, academic_year_id, expiry_year, status, generated_by, generated_at, issue_date, replaced_from_card_id, replacement_reason)
                VALUES (?, ?, ?, ?, ?, ?, 'generated', ?, NOW(), CURDATE(), ?, ?)");
            $stmt->execute([
                $oldCard['student_id'],
                $newCardNumber,
                $newQrToken,
                $qrPayload,
                $student['academic_year_id'],
                $expiryYear,
                $userId,
                $cardId,
                $reason
            ]);

            $newCardId = (int)$db->lastInsertId();

            // Mark old card as replaced
            $stmt = $db->prepare("UPDATE student_id_cards SET status = 'replaced', replaced_at = NOW() WHERE id = ?");
            $stmt->execute([$cardId]);

            // Record history for old card
            $this->recordCardHistory($db, $cardId, $oldCard['student_id'], 'replaced', $oldCard['status'], 'replaced', "Card replaced (reason: {$reason})", $userId);

            // Record history for new card
            $this->recordCardHistory($db, $newCardId, $oldCard['student_id'], 'generated', null, 'generated', 'Card generated (replacement)', $userId);

            $db->commit();

            return [
                'success' => true,
                'message' => 'Card replaced successfully',
                'new_card_id' => $newCardId,
                'new_card_number' => $newCardNumber
            ];
        } catch (Exception $e) {
            $db->rollBack();
            \App\API\Services\Logger::legacyError("StudentService::replaceCard error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An internal error occurred.'];
        }
    }

    /**
     * Revoke a card
     */
    public function revokeCard(int $cardId, ?string $reason, ?int $userId = null): array
    {
        $db = $this->repository->getDb();

        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT id, student_id, status FROM student_id_cards WHERE id = ?");
            $stmt->execute([$cardId]);
            $card = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$card) {
                throw new Exception('Card not found');
            }

            $stmt = $db->prepare("UPDATE student_id_cards SET status = 'revoked', revoked_at = NOW(), revoked_by = ?, revoked_reason = ? WHERE id = ?");
            $stmt->execute([$userId, $reason, $cardId]);

            $this->recordCardHistory($db, $cardId, $card['student_id'], 'revoked', $card['status'], 'revoked', $reason ?? 'Card revoked', $userId);

            $db->commit();

            return ['success' => true, 'message' => 'Card revoked successfully'];
        } catch (Exception $e) {
            $db->rollBack();
            \App\API\Services\Logger::legacyError("StudentService::revokeCard error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An internal error occurred.'];
        }
    }

    /**
     * Get card history for a student
     */
    public function getCardHistory(int $studentId): array
    {
        try {
            $db = $this->repository->getDb();

            $sql = "SELECT 
                        h.id,
                        h.card_id,
                        h.action,
                        h.from_status,
                        h.to_status,
                        h.remarks,
                        h.performed_at,
                        sic.card_number,
                        up.first_name as performed_by_first_name,
                        up.last_name as performed_by_last_name
                    FROM student_id_card_history h
                    LEFT JOIN student_id_cards sic ON h.card_id = sic.id
                    LEFT JOIN users u ON h.performed_by = u.id
                    LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " up ON up.id = u.person_id
                    WHERE h.student_id = ?
                    ORDER BY h.performed_at DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute([$studentId]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ['history' => $history];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("StudentService::getCardHistory error: " . $e->getMessage());
            return ['history' => []];
        }
    }

    /**
     * Verify card by number
     */
    public function verifyCard(string $cardNumber): ?array
    {
        try {
            $db = $this->repository->getDb();

            $sql = "SELECT
                        sic.id,
                        sic.card_number,
                        sic.status,
                        sic.issue_date,
                        sic.expiry_year,
                        s.id as student_id,
                        s.admission_no,
                        per.first_name AS first_name,
                        per.last_name AS last_name,
                        per.gender,
                        per.photo_url,
                        c.name as class_name,
                        st2.name as stream_name,
                        ay.year_code as academic_year
                    FROM student_id_cards sic
                    INNER JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON sic.student_id = s.id
                    JOIN " . ReadReplicaService::qualifiedRef("persons") . " per ON per.id = s.person_id
                    LEFT JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae ON s.id = sae.student_id AND sae.enrollment_status = 'active'
                    LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs ON aycs.id = sae.academic_year_class_stream_id
                    LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc ON ayc.id = aycs.academic_year_class_id
                    LEFT JOIN " . ReadReplicaService::qualifiedRef("classes") . " c ON c.id = ayc.class_id
                    LEFT JOIN " . ReadReplicaService::qualifiedRef("streams") . " st2 ON st2.id = aycs.stream_id
                    LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_years") . " ay ON sae.academic_year_id = ay.id
                    WHERE sic.card_number = ?
                    AND sic.status IN ('generated', 'printed', 'issued')
                    ORDER BY sic.created_at DESC
                    LIMIT 1";

            $stmt = $db->prepare($sql);
            $stmt->execute([$cardNumber]);
            $card = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$card) {
                return null;
            }

            // Check if card is expired
            $currentYear = (int)date('Y');
            $isExpired = $card['expiry_year'] && $currentYear > $card['expiry_year'];

            return [
                'card' => $card,
                'is_valid' => !$isExpired && in_array($card['status'], ['printed', 'issued']),
                'is_expired' => $isExpired
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("StudentService::verifyCard error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate unique card number in format: PREFIX-YYYY-XXXXXX
     */
    private function generateUniqueCardNumber(PDO $db, string $prefix, int $year): string
    {
        $maxAttempts = 10;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            // Get next sequence number for this year
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM student_id_cards WHERE card_number LIKE ?");
            $pattern = "{$prefix}-{$year}-%";
            $stmt->execute([$pattern]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $sequence = ($result['count'] ?? 0) + 1;

            // Format sequence as 6-digit number with leading zeros
            $sequenceStr = str_pad((string)$sequence, 6, '0', STR_PAD_LEFT);
            $cardNumber = "{$prefix}-{$year}-{$sequenceStr}";

            // Verify uniqueness
            $stmt = $db->prepare("SELECT id FROM student_id_cards WHERE card_number = ?");
            $stmt->execute([$cardNumber]);
            if (!$stmt->fetch()) {
                return $cardNumber;
            }

            $attempt++;
        }

        throw new Exception('Failed to generate unique card number after multiple attempts');
    }

    /**
     * Generate unique QR token
     */
    private function generateQrToken(PDO $db): string
    {
        $maxAttempts = 10;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $token = bin2hex(random_bytes(16));

            $stmt = $db->prepare("SELECT id FROM student_id_cards WHERE qr_token = ?");
            $stmt->execute([$token]);
            if (!$stmt->fetch()) {
                return $token;
            }

            $attempt++;
        }

        throw new Exception('Failed to generate unique QR token after multiple attempts');
    }

    /**
     * Record card history
     */
    private function recordCardHistory(PDO $db, int $cardId, int $studentId, string $action, ?string $fromStatus, ?string $toStatus, ?string $remarks, ?int $performedBy): void
    {
        $stmt = $db->prepare("INSERT INTO student_id_card_history 
            (card_id, student_id, action, from_status, to_status, remarks, performed_by, performed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$cardId, $studentId, $action, $fromStatus, $toStatus, $remarks, $performedBy]);
    }
}
