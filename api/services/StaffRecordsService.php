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

    public function assignRole(int $staffId, int $roleId): array
    {
        $staff = $this->staffUser($staffId);
        $exists = $this->db->query(
            'SELECT id FROM user_roles WHERE user_id = ? AND role_id = ? LIMIT 1',
            [(int)$staff['user_id'], $roleId]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$exists) {
            $this->db->query(
                'INSERT INTO user_roles (user_id, role_id, created_at) VALUES (?, ?, NOW())',
                [(int)$staff['user_id'], $roleId]
            );
        }

        return ['staff_id' => $staffId, 'role_id' => $roleId];
    }

    public function revokeRole(int $staffId, int $roleId): void
    {
        $staff = $this->staffUser($staffId);
        $this->db->query(
            'DELETE FROM user_roles WHERE user_id = ? AND role_id = ?',
            [(int)$staff['user_id'], $roleId]
        );
    }

    public function roleAssignments(int $staffId): array
    {
        $this->staffUser($staffId);
        return $this->db->query(
            "SELECT r.id role_id, r.name, r.description, ur.created_at
             FROM staff s
             JOIN user_roles ur ON ur.user_id = s.user_id
             JOIN roles r ON r.id = ur.role_id
             WHERE s.id = ?
             ORDER BY r.name",
            [$staffId]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function availableRoles(): array
    {
        return $this->db->query(
            "SELECT id, name, description, scope, is_system
             FROM roles
             WHERE is_active = 1
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
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['staff_id'])) {
            $where[] = 'c.staff_id = ?';
            $params[] = (int)$filters['staff_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'c.status = ?';
            $params[] = $filters['status'];
        }

        return $this->db->query(
            "SELECT c.*, s.staff_no, s.first_name, s.last_name, s.position, s.profile_pic_url,
                    d.name AS department_name
             FROM staff_id_cards c
             JOIN staff s ON s.id = c.staff_id
             LEFT JOIN departments d ON d.id = s.department_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY c.created_at DESC",
            $params
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function persistGeneratedIdCard(int $staffId, string $cardNumber, ?string $expiresAt, int $actorId): void
    {
        $this->db->query(
            "INSERT INTO staff_id_cards (staff_id, card_number, status, issued_at, expires_at, generated_by, created_at, updated_at)
             VALUES (?, ?, 'generated', NULL, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE status='generated', expires_at=VALUES(expires_at), generated_by=VALUES(generated_by), updated_at=NOW()",
            [$staffId, $cardNumber, $expiresAt, $actorId]
        );
    }

    public function issueIdCard(int $staffId, int $actorId): void
    {
        $this->db->query(
            "UPDATE staff_id_cards
             SET status = 'issued', issued_at = NOW(), issued_by = ?, updated_at = NOW()
             WHERE staff_id = ?",
            [$actorId, $staffId]
        );
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
        if (!empty($filters['academic_year_id'])) {
            $where[] = 'pr.academic_year_id = ?';
            $params[] = (int)$filters['academic_year_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'pr.status = ?';
            $params[] = $filters['status'];
        }

        return $this->db->query(
            "SELECT pr.*, CONCAT(s.first_name, ' ', s.last_name) teacher_name,
                    CONCAT(r.first_name, ' ', r.last_name) reviewer_name, ay.year_name academic_year
             FROM staff_performance_reviews pr
             JOIN staff s ON s.id = pr.staff_id
             LEFT JOIN staff r ON r.id = pr.reviewer_id
             LEFT JOIN academic_years ay ON ay.id = pr.academic_year_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY pr.review_date DESC, pr.id DESC",
            $params
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createPerformanceReview(array $data): int
    {
        foreach (['staff_id', 'academic_year_id', 'reviewer_id'] as $field) {
            if (empty($data[$field])) {
                throw new RuntimeException("{$field} is required");
            }
        }

        $this->db->query(
            "INSERT INTO staff_performance_reviews
             (staff_id, academic_year_id, term_id, review_period, review_type, reviewer_id,
              review_date, overall_score, performance_grade, overall_rating, strengths,
              areas_for_improvement, recommendations, action_plan, follow_up_date, status,
              created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [
                (int)$data['staff_id'],
                (int)$data['academic_year_id'],
                $data['term_id'] ?? null,
                $data['review_period'] ?? null,
                $data['review_type'] ?? 'annual',
                (int)$data['reviewer_id'],
                $data['review_date'] ?? date('Y-m-d'),
                $data['overall_score'] ?? null,
                $data['performance_grade'] ?? null,
                $data['overall_rating'] ?? null,
                $data['strengths'] ?? null,
                $data['areas_for_improvement'] ?? null,
                $data['recommendations'] ?? null,
                $data['action_plan'] ?? null,
                $data['follow_up_date'] ?? null,
                $data['status'] ?? 'draft',
            ]
        );

        return (int)$this->db->lastInsertId();
    }

    public function updatePerformanceReview(int $id, array $data): array
    {
        $before = $this->performanceReview($id);
        $allowed = ['review_period', 'review_type', 'review_date', 'overall_score', 'performance_grade', 'overall_rating', 'strengths', 'areas_for_improvement', 'recommendations', 'action_plan', 'follow_up_date', 'status', 'term_id'];
        $sets = [];
        $params = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        if (!$sets) {
            throw new RuntimeException('No supported fields supplied');
        }
        $params[] = $id;
        $this->db->query(
            'UPDATE staff_performance_reviews SET ' . implode(',', $sets) . ', updated_at = NOW() WHERE id = ?',
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
        $this->db->query('DELETE FROM staff_performance_reviews WHERE id = ?', [$id]);
        return $before;
    }

    public function appointmentSummary(): array
    {
        return (new StaffAppointmentsService($this->db))->summary();
    }

    public function promotions(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['staff_id'])) {
            $where[] = 'sp.staff_id = ?';
            $params[] = (int)$filters['staff_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'sp.status = ?';
            $params[] = $filters['status'];
        }

        return $this->db->query(
            "SELECT sp.*,
                    CONCAT(s.first_name, ' ', s.last_name) AS staff_name,
                    s.staff_no,
                    fd.name AS from_department,
                    td.name AS to_department,
                    CONCAT(a.first_name, ' ', a.last_name) AS approved_by_name,
                    CONCAT(c.first_name, ' ', c.last_name) AS created_by_name
             FROM staff_promotions sp
             JOIN staff s ON s.id = sp.staff_id
             LEFT JOIN departments fd ON fd.id = sp.from_department_id
             LEFT JOIN departments td ON td.id = sp.to_department_id
             LEFT JOIN staff a ON a.id = sp.approved_by
             LEFT JOIN staff c ON c.id = sp.created_by
             WHERE " . implode(' AND ', $where) . "
             ORDER BY sp.created_at DESC
             LIMIT 200",
            $params
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createPromotion(array $data, int $actorId): int
    {
        $staffId = (int)($data['staff_id'] ?? 0);
        if (!$staffId) {
            throw new RuntimeException('staff_id is required');
        }
        if (empty($data['effective_date'])) {
            throw new RuntimeException('effective_date is required');
        }

        $staff = $this->db->query('SELECT * FROM staff WHERE id = ?', [$staffId])->fetch(PDO::FETCH_ASSOC);
        if (!$staff) {
            throw new RuntimeException('Staff member not found');
        }

        $this->db->query(
            "INSERT INTO staff_promotions
                (staff_id, promotion_type, from_position, to_position,
                 from_department_id, to_department_id, from_salary, to_salary,
                 effective_date, status, reason, letter_url, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)",
            [
                $staffId,
                $data['promotion_type'] ?? 'substantive',
                $staff['position'],
                $data['to_position'] ?? $staff['position'],
                $staff['department_id'],
                $data['to_department_id'] ?? $staff['department_id'],
                $staff['salary'],
                isset($data['to_salary']) ? (float)$data['to_salary'] : null,
                $data['effective_date'],
                $data['reason'] ?? null,
                $data['letter_url'] ?? null,
                $actorId,
            ]
        );

        return (int)$this->db->lastInsertId();
    }

    public function decidePromotion(int $promotionId, string $action, int $actorId, ?string $reason = null): void
    {
        if (!in_array($action, ['approve', 'reject'], true)) {
            throw new RuntimeException('action must be approve or reject');
        }

        $promo = $this->db->query('SELECT * FROM staff_promotions WHERE id = ?', [$promotionId])->fetch(PDO::FETCH_ASSOC);
        if (!$promo) {
            throw new RuntimeException('Promotion not found');
        }

        $this->db->beginTransaction();
        try {
            $newStatus = $action === 'approve' ? 'approved' : 'rejected';
            $this->db->query(
                "UPDATE staff_promotions
                 SET status = ?, approved_by = ?, approved_at = NOW(), rejected_reason = ?, updated_at = NOW()
                 WHERE id = ?",
                [$newStatus, $actorId, $action === 'reject' ? $reason : null, $promotionId]
            );

            if ($action === 'approve') {
                $this->db->query(
                    'UPDATE staff SET position = ?, salary = ?, updated_at = NOW() WHERE id = ?',
                    [$promo['to_position'], $promo['to_salary'], $promo['staff_id']]
                );
                if ($promo['effective_date'] <= date('Y-m-d')) {
                    $this->db->query("UPDATE staff_promotions SET status = 'effective' WHERE id = ?", [$promotionId]);
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
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
                    CONCAT(s.first_name, ' ', s.last_name) AS staff_name,
                    s.staff_no,
                    CONCAT(p.first_name, ' ', p.last_name) AS processed_by_name,
                    CONCAT(c.first_name, ' ', c.last_name) AS created_by_name
             FROM staff_offboarding so
             JOIN staff s ON s.id = so.staff_id
             LEFT JOIN staff p ON p.id = so.processed_by
             LEFT JOIN staff c ON c.id = so.created_by
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
                $this->db->query('UPDATE staff SET status = ? , updated_at = NOW() WHERE id = ?', ['inactive', $offboarding['staff_id']]);
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
            "SELECT s.id, s.staff_no, s.first_name, s.last_name,
                    s.position, s.employment_date, s.date_of_birth,
                    d.name AS department,
                    TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) AS age,
                    DATE_ADD(s.date_of_birth, INTERVAL 60 YEAR) AS retirement_date,
                    DATEDIFF(DATE_ADD(s.date_of_birth, INTERVAL 60 YEAR), CURDATE()) AS days_remaining,
                    s.status
             FROM staff s
             LEFT JOIN departments d ON d.id = s.department_id
             WHERE s.status = 'active'
               AND s.date_of_birth IS NOT NULL
               AND TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) >= 55
               AND DATE_ADD(s.date_of_birth, INTERVAL 60 YEAR) <= ?
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
            'SELECT * FROM staff_performance_reviews WHERE id = ?',
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
        $staff = $this->db->query(
            'SELECT id, user_id FROM staff WHERE id = ? LIMIT 1',
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
        $id = $this->db->query(
            'SELECT id FROM staff WHERE user_id = ? LIMIT 1',
            [$userId]
        )->fetchColumn();

        return $id ? (int)$id : null;
    }
}
