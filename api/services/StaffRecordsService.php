<?php

namespace App\API\Services;

use App\Database\Database;
use PDO;
use RuntimeException;

final class StaffRecordsService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?: Database::getInstance();
    }

    /** Position/type snapshot used to gate edits before any write. */
    public function positionSnapshot(int $staffId): ?array
    {
        // Resolve the staff snapshot and primary role from separately published
        // read projections. Keeping these lookups separate avoids a request-path
        // join while preserving NULL when no primary role is assigned.
        $staffRef = ReadReplicaService::qualifiedRef('staff_directory');
        $stmt = $this->db->getConnection()->prepare(
            "SELECT staff_id, position, staff_type_id, staff_category_id, user_id
             FROM {$staffRef} WHERE staff_id = ? LIMIT 1"
        );
        $stmt->execute([$staffId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $roleId = null;
        if (!empty($row['user_id'])) {
            $roleRef = ReadReplicaService::masterRef('user_role_grant');
            $role = $this->db->getConnection()->prepare(
                "SELECT role_id FROM {$roleRef}
                 WHERE user_id = ? AND user_role_is_primary = 1 LIMIT 1"
            );
            $role->execute([(int) $row['user_id']]);
            $roleId = $role->fetchColumn();
        }
        return [
            'position' => $row['position'],
            'staff_type_id' => $row['staff_type_id'],
            'staff_category_id' => $row['staff_category_id'],
            'role_id' => $roleId === false ? null : $roleId,
        ];
    }

    /** Identity row (person fields) of an active staff member. */
    public static function personForStaffId(PDO $pdo, int $staffId): ?array
    {
        $staffRef = ReadReplicaService::qualifiedRef('staff_directory');
        $stmt = $pdo->prepare(
            "SELECT person_id, first_name, middle_name, last_name, phone, email, national_id_no
             FROM {$staffRef}
             WHERE staff_id = ? AND staff_status = 'active' LIMIT 1"
        );
        $stmt->execute([$staffId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Resolve a school staff row from the authenticated account.
     *
     * This is intentionally authoritative: SYSTEM-domain accounts and
     * parent-only accounts are not staff unless the master staff table links
     * the account's person record.
     */
    public static function staffIdForUserId(PDO $pdo, int $userId, bool $requireActive = false): int
    {
        if ($userId <= 0) {
            return 0;
        }
        $sql = "SELECT s.id
                  FROM users u
                  JOIN " . ReadReplicaService::masterRef("staff") . " s ON s.person_id = u.person_id
                 WHERE u.id = ?";
        if ($requireActive) {
            $sql .= " AND s.status = 'active'";
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Classify the school-staff relationship without collapsing onboarding and
     * administrative deactivation into the same state.
     */
    public static function staffContextForUserId(PDO $pdo, int $userId): array
    {
        if ($userId <= 0) {
            return ['state' => 'unlinked', 'staff_id' => null];
        }

        $stmt = $pdo->prepare(
            "SELECT u.id AS user_id, u.status AS user_status,
                    u.profile_completed_at, u.password_changed_at,
                    s.id AS staff_id, s.status AS staff_status,
                    s.position, s.staff_no,
                    inv.status AS invitation_status,
                    inv.accepted_at AS invitation_accepted_at,
                    inv.expires_at AS invitation_expires_at
               FROM users u
               LEFT JOIN " . ReadReplicaService::masterRef("staff") . " s ON s.person_id = u.person_id
               LEFT JOIN user_invitations inv ON inv.id = (
                   SELECT latest.id
                     FROM user_invitations latest
                    WHERE latest.user_id = u.id
                    ORDER BY latest.id DESC
                    LIMIT 1
               )
              WHERE u.id = ?
              LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['staff_id'])) {
            return [
                'state' => 'unlinked',
                'staff_id' => null,
                'user_status' => $row['user_status'] ?? null,
            ];
        }

        $invitationOpen = in_array(
            (string) ($row['invitation_status'] ?? ''),
            ['pending', 'accepted'],
            true
        );
        $profileIncomplete = empty($row['profile_completed_at']);
        if ($profileIncomplete && $invitationOpen) {
            $state = 'invited';
        } elseif (
            (string) $row['staff_status'] === 'inactive'
            && !$profileIncomplete
        ) {
            $state = 'deactivated';
        } elseif ((string) $row['staff_status'] === 'on_leave') {
            $state = 'on_leave';
        } else {
            $state = 'active';
        }

        $row['state'] = $state;
        $row['staff_id'] = (int) $row['staff_id'];
        return $row;
    }

    public function assignRole(int $staffId, int $roleId): array
    {
        $staff = $this->staffUser($staffId);
        $result = (new \App\API\Modules\users\UserRoleManager($this->db->getConnection()))
            ->assignRole((int)$staff['user_id'], $roleId, true);
        if (empty($result['success'])) throw new RuntimeException($result['error'] ?? 'Role assignment failed.');
        return ['staff_id' => $staffId, 'role_id' => $roleId];
    }

    public function revokeRole(int $staffId, int $roleId): void
    {
        $staff = $this->staffUser($staffId);
        $result = (new \App\API\Modules\users\UserRoleManager($this->db->getConnection()))
            ->revokeRole((int)$staff['user_id'], $roleId);
        if (empty($result['success'])) throw new RuntimeException($result['message'] ?? $result['error'] ?? 'Role revocation failed.');
    }

    public function roleAssignments(int $staffId): array
    {
        $this->staffUser($staffId);
        return $this->db->query(
            "SELECT r.id role_id, r.name, r.description, ur.created_at, (ur.is_primary = 1) AS is_primary
             FROM " . ReadReplicaService::qualifiedRef("staff_directory") . " 
             WHERE s.id = ?
             ORDER BY ur.is_primary DESC, ur.id",
            [$staffId]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function availableRoles(): array
    {
        return $this->db->query(
            "SELECT id, name, description, scope, is_system
             FROM roles
             WHERE is_active = 1
               AND scope = 'school'
               AND is_system = 0
               AND LOWER(name) NOT IN ('system administrator', 'parent')
             ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function leaveTypes(): array
    {
        return $this->db->query(
            "SELECT id, code, name, description, days_allowed, requires_approval, is_paid, applicable_to
             FROM leave_types
             WHERE status = 'active'
             ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function idCards(array $filters = []): array
    {
        $where = ["COALESCE(sd.staff_status, 'active') IN ('active', 'on_leave')"];
        $params = [];
        if (!empty($filters['staff_id'])) {
            $where[] = 'sd.staff_id = ?';
            $params[] = (int)$filters['staff_id'];
        }

        if (!empty($filters['staff_ids']) && is_array($filters['staff_ids'])) {
            $staffIds = array_values(
                array_unique(
                    array_filter(
                        array_map('intval', $filters['staff_ids']),
                        static fn (int $staffId): bool => $staffId > 0
                    )
                )
            );

            if ($staffIds === []) {
                return [];
            }

            $where[] = 'sd.staff_id IN ('
                . implode(',', array_fill(0, count($staffIds), '?'))
                . ')';
            array_push($params, ...$staffIds);
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'missing') {
                $where[] = 'c.id IS NULL';
            } else {
                $where[] = 'c.status = ?';
                $params[] = $filters['status'];
            }
        }

        return $this->db->query(
            "SELECT
                    c.id,
                    sd.staff_id,
                    c.card_number,
                    c.generated_by,
                    c.generated_at,
                    c.issued_by,
                    c.issued_at,
                    c.expires_at,
                    c.status,
                    c.metadata,
                    c.created_at,
                    c.updated_at,
                    sd.staff_no,
                    sd.first_name,
                    sd.last_name,
                    sd.position,
                    sd.email,
                    sd.phone,
                    sd.photo_url AS profile_pic_url,
                    sd.department_name
             FROM " . ReadReplicaService::qualifiedRef("staff_directory") . " sd
             LEFT JOIN (
                    SELECT sic.*
                    FROM staff_id_cards sic
                    JOIN (
                        SELECT staff_id, MAX(id) AS latest_id
                        FROM staff_id_cards
                        GROUP BY staff_id
                    ) latest ON latest.latest_id = sic.id
             ) c ON c.staff_id = sd.staff_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY
                CASE WHEN c.id IS NULL THEN 0 ELSE 1 END,
                sd.last_name,
                sd.first_name,
                sd.staff_no",
            $params
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function securityPassNumberForStaff(int $staffId): string
    {
        if ($staffId <= 0) {
            throw new RuntimeException('A valid staff ID is required.');
        }

        return 'KWA-S-' . str_pad(
            (string) $staffId,
            6,
            '0',
            STR_PAD_LEFT
        );
    }

    public function persistGeneratedIdCard(int $staffId, string $cardNumber, ?string $expiresAt, int $actorId): void
    {
        /*
         * $expiresAt is retained for method compatibility only. Staff security
         * passes remain valid while the staff employment relationship is current and
         * are revoked by the canonical lifecycle/offboarding workflows.
         */
        $this->db->query(
            "INSERT INTO staff_id_cards (staff_id, card_number, status, issued_at, expires_at, generated_by, created_at, updated_at)
             VALUES (?, ?, 'generated', NULL, NULL, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                 status = 'generated',
                 issued_by = NULL,
                 issued_at = NULL,
                 generated_at = NOW(),
                 expires_at = NULL,
                 generated_by = VALUES(generated_by),
                 updated_at = NOW()",
            [$staffId, $cardNumber, $actorId]
        );
    }

    public function persistBulkGeneratedIdCards(array $staffIds, ?string $expiresAt, int $actorId): array
    {
        $persisted = [];
        foreach (array_unique(array_map('intval', $staffIds)) as $staffId) {
            if ($staffId <= 0) {
                continue;
            }

            $cardNumber = $this->securityPassNumberForStaff($staffId);
            $this->persistGeneratedIdCard($staffId, $cardNumber, $expiresAt, $actorId);
            $persisted[] = [
                'staff_id' => $staffId,
                'card_number' => $cardNumber,
            ];
        }

        return $persisted;
    }

    public function issueIdCard(int $staffId, int $actorId): void
    {
        if ($staffId <= 0) {
            throw new RuntimeException('A valid staff ID is required.');
        }

        $statement = $this->db->query(
            "UPDATE staff_id_cards
             SET status = 'issued',
                 issued_at = NOW(),
                 issued_by = ?,
                 updated_at = NOW()
             WHERE id = (
                 SELECT latest.id
                 FROM (
                     SELECT id
                     FROM staff_id_cards
                     WHERE staff_id = ?
                     ORDER BY id DESC
                     LIMIT 1
                 ) AS latest
             )",
            [$actorId, $staffId]
        );

        if ($statement->rowCount() !== 1) {
            throw new RuntimeException(
                'No generated staff security pass was found to issue.'
            );
        }
    }

    public function revokeLatestSecurityPass(
        int $staffId,
        int $actorId,
        string $reason
    ): bool {
        if ($staffId <= 0) {
            throw new RuntimeException('A valid staff ID is required.');
        }

        $pass = $this->db->query(
            "SELECT id, metadata
             FROM staff_id_cards
             WHERE staff_id = ?
             ORDER BY id DESC
             LIMIT 1",
            [$staffId]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$pass) {
            return false;
        }

        $metadata = [];
        if (!empty($pass['metadata'])) {
            $decoded = json_decode((string) $pass['metadata'], true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        $metadata['revocation'] = [
            'reason' => trim($reason) !== ''
                ? trim($reason)
                : 'Staff employment is no longer current.',
            'revoked_by' => $actorId,
            'revoked_at' => date('c'),
        ];

        $this->db->query(
            "UPDATE staff_id_cards
             SET status = 'revoked',
                 expires_at = NULL,
                 metadata = ?,
                 updated_at = NOW()
             WHERE id = ?",
            [
                json_encode($metadata, JSON_UNESCAPED_SLASHES),
                (int) $pass['id'],
            ]
        );

        return true;
    }

    public function performanceReviews(array $filters = [], ?int $id = null): array
    {
        $where = ['1=1'];
        $params = [];
        if ($id) {
            $where[] = 'pr.id = ?';
            $params[] = $id;
        }
        if (!empty($filters['staff_id'])) {
            $where[] = 'pr.staff_id = ?';
            $params[] = (int)$filters['staff_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'pr.status = ?';
            $params[] = $filters['status'];
        }

        return $this->db->query(
            "SELECT pr.id AS id,
                    pr.id AS review_id,
                    pr.staff_id,
                    pr.staff_id AS teacher_id,
                    pr.period AS review_period,
                    pr.review_date,
                    pr.rating AS overall_rating,
                    pr.rating AS rating,
                    pr.status,
                    pr.notes AS comments,
                    pr.notes AS remarks,
                    pr.notes AS strengths,
                    pr.notes AS areas_for_improvement,
                    pr.notes AS recommendations,
                    pr.reviewed_by AS reviewer_id,
                    NULL AS subject_id,
                    NULL AS category,
                    NULL AS academic_year,
                    NULL AS review_type,
                    NULL AS term_id,
                    (SELECT COALESCE(ROUND(AVG(prk.score), 1), 0)
                       FROM " . ReadReplicaService::qualifiedRef("performance_review_kpis") . " prk WHERE prk.review_id = pr.id) AS overall_score,
                    CASE
                        WHEN (SELECT COALESCE(AVG(prk.score), 0) FROM " . ReadReplicaService::qualifiedRef("performance_review_kpis") . " prk WHERE prk.review_id = pr.id) >= 80 THEN 'Excellent'
                        WHEN (SELECT COALESCE(AVG(prk.score), 0) FROM " . ReadReplicaService::qualifiedRef("performance_review_kpis") . " prk WHERE prk.review_id = pr.id) >= 70 THEN 'Good'
                        WHEN (SELECT COALESCE(AVG(prk.score), 0) FROM " . ReadReplicaService::qualifiedRef("performance_review_kpis") . " prk WHERE prk.review_id = pr.id) >= 60 THEN 'Satisfactory'
                        WHEN (SELECT COALESCE(AVG(prk.score), 0) FROM " . ReadReplicaService::qualifiedRef("performance_review_kpis") . " prk WHERE prk.review_id = pr.id) >= 50 THEN 'Below Expectation'
                        ELSE 'Needs Improvement'
                    END AS performance_grade,
                    CONCAT(sp.first_name, ' ', sp.last_name) AS staff_name,
                    d.name AS department,
                    CONCAT(rp.first_name, ' ', rp.last_name) AS reviewer_name
             FROM performance_reviews pr
             JOIN " . ReadReplicaService::qualifiedRef("staff") . " s ON s.id = pr.staff_id
             JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " sp ON sp.person_id = s.person_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("staff_department_assignments") . " sda ON sda.staff_id = s.id AND (sda.effective_to IS NULL OR sda.effective_to >= CURDATE())
             LEFT JOIN " . ReadReplicaService::qualifiedRef("departments") . " d ON d.id = sda.department_id
             LEFT JOIN staff r ON r.id = pr.reviewed_by
             LEFT JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " rp ON rp.person_id = r.person_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY pr.review_date DESC, pr.id DESC",
            $params
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createPerformanceReview(array $data): int
    {
        foreach (['staff_id', 'reviewer_id'] as $field) {
            if (empty($data[$field])) {
                throw new RuntimeException("{$field} is required");
            }
        }

        $period = $data['review_period'] ?? null;
        if (!$period && !empty($data['academic_year_id'])) {
            $period = (string)(int)$data['academic_year_id'];
        }
        if (!$period) {
            throw new RuntimeException('review_period is required');
        }

        $status = $data['status'] ?? 'draft';
        $statusMap = ['pending' => 'draft', 'completed' => 'submitted'];
        $status = $statusMap[$status] ?? $status;

        $notes = implode(' ', array_filter([
            $data['comments'] ?? null,
            $data['strengths'] ?? null,
            $data['areas_for_improvement'] ?? null,
            $data['recommendations'] ?? null,
            $data['action_plan'] ?? null,
        ], function ($v) {
            return $v !== null && $v !== '';
        }));

        $this->db->query(
            "INSERT INTO performance_reviews
             (staff_id, period, rating, reviewed_by, review_date, status, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                (int)$data['staff_id'],
                $period,
                $data['overall_rating'] ?? null,
                (int)$data['reviewer_id'],
                $data['review_date'] ?? date('Y-m-d'),
                $status,
                $notes !== '' ? $notes : null,
            ]
        );

        return (int)$this->db->lastInsertId();
    }

    public function updatePerformanceReview(int $id, array $data): array
    {
        $before = $this->performanceReview($id);
        $fieldMap = [
            'review_period' => 'period',
            'review_date' => 'review_date',
            'overall_rating' => 'rating',
            'rating' => 'rating',
            'comments' => 'notes',
            'notes' => 'notes',
            'status' => 'status',
        ];
        $sets = [];
        $params = [];
        foreach ($fieldMap as $inputField => $column) {
            if (array_key_exists($inputField, $data)) {
                $value = $data[$inputField];
                if ($column === 'status') {
                    $statusMap = ['pending' => 'draft', 'completed' => 'submitted'];
                    $value = $statusMap[$value] ?? $value;
                }
                $sets[] = "{$column} = ?";
                $params[] = $value;
            }
        }
        if (!$sets) {
            throw new RuntimeException('No supported fields supplied');
        }
        $params[] = $id;
        $this->db->query(
            'UPDATE performance_reviews SET ' . implode(',', $sets) . ' WHERE id = ?',
            $params
        );
        return $before;
    }

    public function deletePerformanceReview(int $id): array
    {
        $before = $this->performanceReview($id);
        if (($before['status'] ?? '') !== 'draft') {
            throw new RuntimeException('Only draft reviews can be deleted');
        }
        $this->db->query('DELETE FROM performance_reviews WHERE id = ?', [$id]);
        return $before;
    }

    public function appointmentSummary(): array
    {
        return (new StaffAppointmentsService($this->db))->summary();
    }

    public function promotions(array $filters = []): array
    {
        return (new StaffAppointmentsService($this->db))->listInternal($filters);
    }

    public function createPromotion(array $data, int $actorId): int
    {
        return (new StaffAppointmentsService($this->db))->submitInternal($data, $actorId);
    }

    public function decidePromotion(int $promotionId, string $action, int $actorId, ?string $reason = null): void
    {
        (new StaffAppointmentsService($this->db))->reviewInternal($promotionId, $action, $actorId, ['reason' => $reason]);
    }

    public function offboarding(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['staff_id'])) {
            $where[] = 'so.staff_id = ?';
            $params[] = (int)$filters['staff_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'so.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['type'])) {
            $where[] = 'so.offboarding_type = ?';
            $params[] = $filters['type'];
        }

        return $this->db->query(
            "SELECT so.*,
                    CONCAT(sp.first_name, ' ', sp.last_name) AS staff_name,
                    s.staff_no,
                    CONCAT(pp.first_name, ' ', pp.last_name) AS processed_by_name,
                    CONCAT(cp.first_name, ' ', cp.last_name) AS created_by_name
             FROM " . ReadReplicaService::qualifiedRef("staff_offboarding") . "
             JOIN " . ReadReplicaService::qualifiedRef("staff") . " s ON s.id = so.staff_id
             JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " sp ON sp.person_id = s.person_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("staff") . " p ON p.id = so.processed_by
             LEFT JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " pp ON pp.person_id = p.person_id
             LEFT JOIN staff c ON c.id = so.created_by
             LEFT JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " cp ON cp.person_id = c.person_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY so.created_at DESC
             LIMIT 200",
            $params
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createOffboarding(array $data, int $actorId): int
    {
        $staffId = (int)($data['staff_id'] ?? 0);
        if (!$staffId) {
            throw new RuntimeException('staff_id is required');
        }
        if (empty($data['last_working_day'])) {
            throw new RuntimeException('last_working_day is required');
        }
        if (!$this->db->query('SELECT id FROM staff WHERE id = ?', [$staffId])->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('Staff member not found');
        }

        $this->db->query(
            "INSERT INTO staff_offboarding
                (staff_id, offboarding_type, last_working_day,
                 exit_interview_date, exit_interview_notes,
                 asset_return_complete, clearance_form_complete, handover_report_complete,
                 final_pay_calculated, outstanding_leave_days, outstanding_salary,
                 leave_pay_amount, final_settlement_amount,
                 nssf_clearance, paye_clearance, documents_url,
                 notify_hr, notify_finance, notify_it, status, processed_by, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'initiated', ?, ?)",
            [
                $staffId,
                $data['offboarding_type'] ?? 'retirement',
                $data['last_working_day'],
                $data['exit_interview_date'] ?? null,
                $data['exit_interview_notes'] ?? null,
                (int)($data['asset_return_complete'] ?? false),
                (int)($data['clearance_form_complete'] ?? false),
                (int)($data['handover_report_complete'] ?? false),
                (int)($data['final_pay_calculated'] ?? false),
                $data['outstanding_leave_days'] ?? null,
                $data['outstanding_salary'] ?? null,
                $data['leave_pay_amount'] ?? null,
                $data['final_settlement_amount'] ?? null,
                (int)($data['nssf_clearance'] ?? false),
                (int)($data['paye_clearance'] ?? false),
                $data['documents_url'] ?? null,
                (int)($data['notify_hr'] ?? true),
                (int)($data['notify_finance'] ?? true),
                (int)($data['notify_it'] ?? false),
                $actorId,
                $actorId,
            ]
        );

        return (int)$this->db->lastInsertId();
    }

    public function updateOffboarding(int $offboardingId, array $data, int $actorId): void
    {
        $offboarding = $this->db->query('SELECT * FROM staff_offboarding WHERE id = ?', [$offboardingId])->fetch(PDO::FETCH_ASSOC);
        if (!$offboarding) {
            throw new RuntimeException('Offboarding record not found');
        }

        $allowed = [
            'exit_interview_date', 'exit_interview_notes',
            'asset_return_complete', 'clearance_form_complete',
            'handover_report_complete', 'final_pay_calculated',
            'outstanding_leave_days', 'outstanding_salary',
            'leave_pay_amount', 'final_settlement_amount',
            'nssf_clearance', 'paye_clearance',
            'documents_url', 'notify_hr', 'notify_finance', 'notify_it', 'status',
        ];

        $sets = [];
        $params = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        $this->db->beginTransaction();
        try {
            if ($sets) {
                $params[] = $offboardingId;
                $this->db->query(
                    'UPDATE staff_offboarding SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?',
                    $params
                );
            }

            if (($data['status'] ?? '') === 'completed') {
                $staffId = (int) $offboarding['staff_id'];

                $this->db->query(
                    'UPDATE staff SET status = ?, updated_at = NOW() WHERE id = ?',
                    ['inactive', $staffId]
                );

                $this->revokeLatestSecurityPass(
                    $staffId,
                    $actorId,
                    'Staff offboarding completed.'
                );

                $this->db->query(
                    'UPDATE staff_offboarding SET processed_by = ?, processed_at = NOW() WHERE id = ?',
                    [$actorId, $offboardingId]
                );
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function upcomingRetirements(int $months = 12): array
    {
        $months = max(1, $months);
        $cutoff = date('Y-m-d', strtotime("+{$months} months"));

        return $this->db->query(
            "SELECT s.id, s.staff_no, p.first_name, p.last_name,
                    s.position, s.employment_date, p.dob AS date_of_birth,
                    d.name AS department,
                    TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) AS age,
                    DATE_ADD(p.dob, INTERVAL 60 YEAR) AS retirement_date,
                    DATEDIFF(DATE_ADD(p.dob, INTERVAL 60 YEAR), CURDATE()) AS days_remaining,
                    s.status
             FROM " . ReadReplicaService::qualifiedRef("staff_directory") . " 
             WHERE s.status = 'active'
               AND p.dob IS NOT NULL
               AND TIMESTAMPDIFF(YEAR, p.dob, CURDATE()) >= 55
               AND DATE_ADD(p.dob, INTERVAL 60 YEAR) <= ?
             ORDER BY days_remaining ASC",
            [$cutoff]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function scheduleForUser(int $userId): array
    {
        $staffId = $this->staffIdForUser($userId);
        if (!$staffId) {
            return [];
        }

        try {
            return $this->db->query(
                "SELECT 'shift' AS source, day_of_week, shift, start_time, end_time,
                        effective_from, effective_to, notes
                 FROM staff_shift_assignments
                 WHERE staff_id = ?
                   AND status = 'active'
                   AND effective_from <= CURDATE()
                   AND (effective_to IS NULL OR effective_to >= CURDATE())
                 ORDER BY FIELD(day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), start_time",
                [$staffId]
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function staffIdForChild(int $childId): ?int
    {
        $staffId = $this->db->query(
            'SELECT staff_id FROM staff_children WHERE id = ? LIMIT 1',
            [$childId]
        )->fetchColumn();

        return $staffId ? (int)$staffId : null;
    }

    private function performanceReview(int $id): array
    {
        $row = $this->db->query(
            'SELECT * FROM performance_reviews WHERE id = ?',
            [$id]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Performance review not found');
        }
        return $row;
    }

    private function staffUser(int $staffId): array
    {
        if ($staffId <= 0) {
            throw new RuntimeException('staff_id is required');
        }
        // staff.user_id is dropped; the staff↔user link is via the shared person
        // (staff.person_id = users.person_id). Resolve the user id through persons.
        $staffRef = ReadReplicaService::qualifiedRef('staff_directory');
        $staff = $this->db->query(
            "SELECT staff_id AS id, user_id
             FROM {$staffRef}
             WHERE staff_id = ? LIMIT 1",
            [$staffId]
        )->fetch(PDO::FETCH_ASSOC);
        if (!$staff) {
            throw new RuntimeException('Staff member not found');
        }
        if (!(int)$staff['user_id']) {
            throw new RuntimeException('Staff member has no linked user account');
        }
        return $staff;
    }

    private function staffIdForUser(int $userId): ?int
    {
        // Bridge user→staff through the shared person (staff.user_id dropped).
        $staffRef = ReadReplicaService::qualifiedRef('staff_directory');
        $id = $this->db->query(
            "SELECT staff_id
             FROM {$staffRef}
             WHERE user_id = ? LIMIT 1",
            [$userId]
        )->fetchColumn();

        return $id ? (int)$id : null;
    }
    /* -------------------- governed catalog admin (extracted) -------------------- */

    /** Active departments for pickers. */
    public function departments(): array
    {
        return Database::getInstance()->getConnection()
            ->query("SELECT id,name,code,description,status FROM departments WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Full department catalog including inactive. */
    public function departmentCatalog(): array
    {
        return Database::getInstance()->getConnection()
            ->query('SELECT id,name,code,description,status FROM departments ORDER BY name')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createDepartment(string $name, string $code): int
    {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare("INSERT INTO departments(name,code,status) VALUES(?,?,'active')");
        $stmt->execute([$name, $code]);
        return (int) $pdo->lastInsertId();
    }

    public function updateDepartment(int $id, string $name, string $code, string $status): bool
    {
        $stmt = Database::getInstance()->getConnection()
            ->prepare('UPDATE departments SET name=?,code=?,status=? WHERE id=?');
        $stmt->execute([$name, $code, $status, $id]);
        return $stmt->rowCount() > 0;
    }

    /** Active staff types list (catalog picker). */
    public function staffTypes(): array
    {
        return Database::getInstance()->getConnection()
            ->query("SELECT id,name FROM staff_types WHERE is_active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Active staff categories list (catalog picker). */
    public function staffCategories(): array
    {
        return Database::getInstance()->getConnection()
            ->query("SELECT id,staff_type_id,category_name AS name FROM staff_categories WHERE is_active=1 ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }


    /** Whether a staff category belongs to the given staff type (active). */
    public function staffCategoryInType(int $typeId, int $categoryId): bool
    {
        $stmt = Database::getInstance()->getConnection()
            ->prepare('SELECT 1 FROM staff_categories WHERE id=? AND staff_type_id=? AND is_active=1 LIMIT 1');
        $stmt->execute([$categoryId, $typeId]);
        return (bool) $stmt->fetchColumn();
    }

    /** Count of ACTIVE roles matching the given IDs (validation only). */
    public function countActiveRoles(array $roleIds): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $roleIds), static fn(int $v): bool => $v > 0)));
        if (!$ids) return 0;
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::getInstance()->getConnection()
            ->prepare("SELECT COUNT(*) FROM roles WHERE is_active=1 AND id IN ($in)");
        $stmt->execute($ids);
        return (int) $stmt->fetchColumn();
    }

    /** Replace the assigned role set on a staff position (preserves history rule). */
    public function syncPositionRoles(PDO $pdo, int $positionId, array $roleIds): void
    {
        $pdo->prepare('DELETE FROM staff_position_roles WHERE position_id=?')->execute([$positionId]);
        if (!$roleIds) return;
        $stmt = $pdo->prepare('INSERT INTO staff_position_roles(position_id,role_id) VALUES(?,?)');
        foreach ($roleIds as $roleId) $stmt->execute([$positionId, $roleId]);
    }

    /** Replace the DEFAULT-role bindings on a position. */
    public function syncDefaultPositionRoles(PDO $pdo, int $positionId, array $roleIds, int $actorId): void
    {
        $pdo->prepare('DELETE FROM staff_role_default_positions WHERE position_id=?')->execute([$positionId]);
        $stmt = $pdo->prepare('INSERT INTO staff_role_default_positions(role_id,position_id,updated_by) VALUES(?,?,?) ON DUPLICATE KEY UPDATE position_id=VALUES(position_id),updated_by=VALUES(updated_by)');
        foreach ($roleIds as $roleId) $stmt->execute([$roleId, $positionId, $actorId]);
    }

    public function insertPosition(string $name, ?int $staffTypeId, ?int $staffCategoryId, int $actorId): int
    {
        $stmt = Database::getInstance()->getConnection()
            ->prepare('INSERT INTO staff_positions(name,staff_type_id,staff_category_id,created_by) VALUES(?,?,?,?)');
        $stmt->execute([$name, $staffTypeId, $staffCategoryId, $actorId]);
        return (int) Database::getInstance()->getConnection()->lastInsertId();
    }

    public function updatePosition(int $positionId, string $name, ?int $staffTypeId, ?int $staffCategoryId, int $isActive, int $actorId): bool
    {
        $stmt = Database::getInstance()->getConnection()
            ->prepare('UPDATE staff_positions SET name=?,staff_type_id=?,staff_category_id=?,is_active=?,updated_by=? WHERE id=?');
        $stmt->execute([$name, $staffTypeId, $staffCategoryId, $isActive, $actorId, $positionId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Lock + load bulk-selected staff targets inside a transaction, scoping to the
     * caller's data scope. Returns matched rows; throws when the scope narrows.
     */
    public function lockedBulkTargets(PDO $pdo, array $staffIds): array
    {
        $placeholders = implode(',', array_fill(0, count($staffIds), '?'));
        [$scopePredicate, $scopeBindings] = DataScopeService::predicateFor('staff', 's');
        $stmt = $pdo->prepare("SELECT s.id, u.id AS user_id, u.status AS user_status, u.force_password_change
            FROM staff s LEFT JOIN users u ON u.person_id=s.person_id
            WHERE s.id IN ($placeholders) AND $scopePredicate FOR UPDATE");
        $stmt->execute(array_merge($staffIds, $scopeBindings));
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Set account status for bulk-selected staff (guards live in controller+scope). */
    public function bulkSetUserStatus(PDO $pdo, array $userIds, string $status): void
    {
        $stmt = $pdo->prepare('UPDATE users SET status=? WHERE id=?');
        foreach ($userIds as $id) $stmt->execute([$status, (int) $id]);
    }

    /** Active school-scope roles matching the given ids (validation). */
    public function activeSchoolRolesIn(PDO $pdo, array $roleIds): array
    {
        if (!$roleIds) return [];
        $in = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $pdo->prepare("SELECT id FROM roles WHERE id IN ($in) AND is_active=1 AND scope='school'");
        $stmt->execute($roleIds);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /** Account email of a staff member's user record. */
    public function emailForStaff(int $staffId): string
    {
        $staffRef = ReadReplicaService::qualifiedRef('staff_directory');
        $stmt = Database::getInstance()->getConnection()
            ->prepare("SELECT email FROM {$staffRef} WHERE staff_id=? AND user_id IS NOT NULL LIMIT 1");
        $stmt->execute([$staffId]);
        return (string) ($stmt->fetchColumn() ?: '');
    }

}
