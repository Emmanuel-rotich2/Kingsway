<?php

declare(strict_types=1);

namespace App\API\Services;

use App\Database\Database;
use PDO;

/**
 * StudentCardVerificationService — data owner for the public QR ID-card
 * verification page (student_portal.php). All SQL for that page lives here;
 * the thin page file renders UI only and decides visibility PER SECTION from
 * the caller's already-resolved section allowlist. Row-level scoping stays
 * inside these queries; callers pass section names, never SQL.
 */
final class StudentCardVerificationService
{
    public static function shared(): self
    {
        return new self();
    }

    /** Identity + current class (latest active enrollment chain). */
    public function identity(int $studentId): ?array
    {
        $stmt = $this->pdo()->prepare("
            SELECT
                s.id,
                p.first_name,
                p.last_name,
                p.gender,
                p.photo_url,
                p.dob AS date_of_birth,
                s.admission_no,
                s.status,
                s.admission_date,
                c.name AS class_name,
                c.grade_level,
                st.name AS stream_name,
                YEAR(s.admission_date) AS year_joined,
                (YEAR(s.admission_date) + IFNULL(CAST(REGEXP_REPLACE(c.grade_level, '[^0-9]', '') AS UNSIGNED), 0)) AS expected_graduation_year
            FROM " . ReadReplicaService::qualifiedRef("students") . " s
            JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " p ON p.person_id = s.person_id
            LEFT JOIN (
                SELECT sae.student_id, aycs.academic_year_class_id, aycs.stream_id,
                       ROW_NUMBER() OVER (PARTITION BY sae.student_id ORDER BY ay.start_date DESC) AS rn
                FROM student_academic_enrollments sae
                JOIN " . ReadReplicaService::qualifiedRef("academic_years") . " ay ON ay.id = sae.academic_year_id
                JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                WHERE sae.enrollment_status = 'active'
            ) cur ON cur.student_id = s.id AND cur.rn = 1
            LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc ON ayc.id = cur.academic_year_class_id
            LEFT JOIN " . ReadReplicaService::qualifiedRef("classes") . " c ON c.id = ayc.class_id
            LEFT JOIN " . ReadReplicaService::qualifiedRef("streams") . " st ON st.id = cur.stream_id
            WHERE s.id = ?
        ");
        $stmt->execute([$studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Section payloads. Only the sections in $sections are queried.
     *
     * @param list<string> $sections e.g. ['financial','transportation',...] or ['all']
     */
    public function sections(int $studentId, array $sections): array
    {
        $want = static function (string $section) use ($sections): bool {
            return in_array('all', $sections, true) || in_array($section, $sections, true);
        };

        $data = [];
        if ($want('financial')) $data['fees'] = $this->fees($studentId);
        if ($want('transportation')) $data += $this->transport($studentId);
        if ($want('medical')) $data['medical'] = $this->medical($studentId);
        if ($want('sports')) $data['sports'] = $this->sports($studentId);
        if ($want('library')) $data['library'] = $this->library($studentId);
        if ($want('guidance')) $data['guidance'] = $this->guidance($studentId);
        if ($want('academic')) $data['academic'] = $this->academic($studentId);
        if ($want('authorization')) $data['id_card'] = $this->idCard($studentId);

        return $data;
    }

    private function fees(int $studentId): array
    {
        return $this->rows("
            SELECT term_code AS term, academic_year, amount_due AS amount,
                   payment_status AS status, balance
            FROM vw_student_fee_ledger
            WHERE student_id = ?
            ORDER BY academic_year DESC, term_code DESC
            LIMIT 5
        ", [$studentId]);
    }

    private function transport(int $studentId): array
    {
        $assignment = $this->row("
            SELECT sta.id, sta.month, sta.year, sta.status,
                   sta.pickup_time, sta.dropoff_time,
                   tr.name AS route_name, tr.code AS route_code,
                   tv.registration_number AS vehicle_number, tv.type AS vehicle_type,
                   CONCAT(driver_p.first_name, ' ', driver_p.last_name) AS driver_name,
                   tsp.name AS pickup_point, tsd.name AS dropoff_point
            FROM " . ReadReplicaService::qualifiedRef("student_transport_assignments") . " sta
            JOIN " . ReadReplicaService::qualifiedRef("transport_routes") . " tr ON tr.id = sta.route_id
            LEFT JOIN " . ReadReplicaService::qualifiedRef("transport_vehicle_routes") . " tvr ON tvr.route_id = tr.id AND tvr.status = 'active'
            LEFT JOIN transport_vehicles tv ON tv.id = tvr.vehicle_id
            LEFT JOIN " . ReadReplicaService::qualifiedRef("staff") . " ds ON ds.id = tv.driver_id
            LEFT JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " driver_p ON driver_p.person_id = ds.person_id
            LEFT JOIN transport_stops tsp ON tsp.id = sta.pickup_stop_id
            LEFT JOIN transport_stops tsd ON tsd.id = sta.dropoff_stop_id
            WHERE sta.student_id = ?
              AND sta.status IN ('active', 'suspended')
            ORDER BY sta.year DESC, sta.month DESC, sta.id DESC
            LIMIT 1
        ", [$studentId]);

        $bill = $this->row("
            SELECT billing_month, amount_due, payment_status, due_date
            FROM transport_monthly_bills
            WHERE student_id = ?
            ORDER BY billing_month DESC
            LIMIT 1
        ", [$studentId]);

        $curMonth = (int) date('n');
        $curYear = (int) date('Y');
        $eligibility = [
            'code' => 'not_subscribed',
            'label' => 'Not Subscribed',
            'allowed' => false,
            'reason' => 'This learner has no transport subscription.',
        ];
        if ($assignment) {
            $isCurrent = ((int) $assignment['month'] === $curMonth && (int) $assignment['year'] === $curYear);
            if ($assignment['status'] === 'suspended') {
                $eligibility = [
                    'code' => 'suspended',
                    'label' => 'Subscription Suspended',
                    'allowed' => false,
                    'reason' => 'The transport subscription is currently suspended.',
                ];
            } elseif ($isCurrent) {
                $eligibility = [
                    'code' => 'subscribed',
                    'label' => 'Subscribed - Eligible',
                    'allowed' => true,
                    'reason' => 'Active transport subscription for ' . date('F Y') . '.',
                ];
                if ($bill && $bill['payment_status'] !== 'paid') {
                    $eligibility['label'] = 'Subscribed (Payment ' . ucfirst((string) $bill['payment_status']) . ')';
                    $eligibility['reason'] = 'Subscription is active, but the ' . $bill['billing_month'] . ' bill (KES ' . number_format((float) $bill['amount_due'], 2) . ') is ' . $bill['payment_status'] . '.';
                }
            } else {
                $eligibility = [
                    'code' => 'expired',
                    'label' => 'Subscription Expired',
                    'allowed' => false,
                    'reason' => 'Subscription covered ' . $assignment['month'] . '/' . $assignment['year'] . '; renew for the current month.',
                ];
            }
        }

        // JOINed transport detail is a cross-table aggregate; when the replica
        // projection exists it reads from KingsWayReads (fee ledger stays a view).
        return $assignment ? [
            'transport' => array_merge($assignment, ['eligibility' => $eligibility]),
            'transport_bill' => $bill,
        ] : ['transport' => null, 'transport_bill' => $bill];
    }

    private function medical(int $studentId): array
    {
        return $this->rows("
            SELECT created_at AS date, notes
            FROM student_health_records
            WHERE student_id = ?
            ORDER BY created_at DESC
            LIMIT 5
        ", [$studentId]);
    }

    private function sports(int $studentId): array
    {
        return $this->rows("
            SELECT act.title AS sport_name, ap.role AS position, act.end_date AS date, ap.status
            FROM " . ReadReplicaService::qualifiedRef("activity_participants") . " ap
            JOIN activities act ON act.id = ap.activity_id
            JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae ON sae.id = ap.student_academic_enrollment_id
            WHERE sae.student_id = ?
              AND ap.status = 'active'
            ORDER BY act.end_date DESC
            LIMIT 5
        ", [$studentId]);
    }

    private function library(int $studentId): array
    {
        return $this->rows("
            SELECT lb.title AS book_title, li.due_date, li.status
            FROM " . ReadReplicaService::qualifiedRef("counseling_sessions") . "
            JOIN " . ReadReplicaService::qualifiedRef("library_books") . " lb ON lb.id = li.book_id
            WHERE li.borrower_type = 'student'
              AND li.borrower_id = ?
            ORDER BY li.due_date ASC
            LIMIT 5
        ", [$studentId]);
    }

    private function guidance(int $studentId): array
    {
        return $this->rows("
            SELECT cs.session_date AS date, cs.summary AS notes, cs.session_type
            FROM " . ReadReplicaService::qualifiedRef("counseling_sessions") . "
            JOIN counseling_cases cc ON cc.id = cs.case_id
            WHERE cc.counselee_type = 'student'
              AND cc.student_id = ?
            ORDER BY cs.session_date DESC
            LIMIT 5
        ", [$studentId]);
    }

    private function academic(int $studentId): array
    {
        return $this->rows("
            SELECT year_code AS academic_year, term_name AS term, grade_band AS grade,
                   percentage, marks_obtained
            FROM vw_assessment_results_detail
            WHERE student_id = ?
            ORDER BY year_code DESC, term_number DESC
            LIMIT 5
        ", [$studentId]);
    }

    private function idCard(int $studentId): ?array
    {
        return $this->row("
            SELECT * FROM student_id_cards
            WHERE student_id = ?
              AND status IN ('issued', 'printed')
            ORDER BY issue_date DESC
            LIMIT 1
        ", [$studentId]);
    }

    /**
     * View policy: which sections the current observer may see.
     * Authorization semantics belong here (server side), never in page source.
     *
     * @param array|null $user  Session/JWT user payload (null = anonymous QR scan).
     * @param string     $scope Optional department scanner pin (?scope=transport,…)
     * @return array{sections: list<string>, viewing_as: string}
     */
    public function viewPolicy(?array $user, string $scope = ''): array
    {
        if (!$user) {
            return ['sections' => ['basic'], 'viewing_as' => 'public'];
        }

        $roleIds = array_values(array_map('intval', (array) ($user['role_ids'] ?? [])));
        $roleNames = array_values(array_map('strtolower', (array) ($user['role_names'] ?? [])));

        $sections = ['basic'];
        if ($roleIds !== []) {
            $adminRoleIds = [2, 3, 4, 5, 6, 63]; // System Admin, Director, School Admin, Headteacher, Deputies
            if (array_intersect($roleIds, $adminRoleIds) !== []) {
                $sections = ['all'];
            } else {
                if (in_array(23, $roleIds, true)) { $sections[] = 'transportation'; }             // Driver
                if (in_array(33, $roleIds, true)) { $sections[] = 'authorization'; }              // Security Staff
                if (in_array(7, $roleIds, true) || in_array(8, $roleIds, true)) { $sections[] = 'academic'; }
                if (in_array(10, $roleIds, true)) { $sections[] = 'financial'; $sections[] = 'academic'; } // Accountant
                if (in_array(21, $roleIds, true)) { $sections[] = 'sports'; }                     // Talent Development
            }
        } else {
            // Legacy name-keyed session shapes
            $legacy = (string) ($user['role'] ?? '');
            $legacyMap = [
                'parent' => ['all'], 'admin' => ['all'], 'director' => ['all'],
                'headteacher' => ['all'], 'deputy_headteacher' => ['all'],
                'teacher' => ['academic'], 'security' => ['authorization'],
                'driver' => ['transportation'], 'accountant' => ['financial', 'academic'],
                'finance_manager' => ['financial', 'academic'], 'transport_manager' => ['transportation'],
                'medical_staff' => ['medical'], 'sports_coordinator' => ['sports'],
                'librarian' => ['library'], 'guidance_counselor' => ['guidance'],
            ];
            $sections = array_merge($sections, $legacyMap[$legacy] ?? []);
        }

        // A department-registered scanning device may pin one department via
        // ?scope=; it narrows the view, never widens it.
        $scope = strtolower(trim($scope));
        $scopeSections = [
            'transport' => ['transportation'], 'sports' => ['sports'], 'medical' => ['medical'],
            'security' => ['authorization'], 'authorization' => ['authorization'],
            'academic' => ['academic'], 'financial' => ['financial'], 'finance' => ['financial'],
            'library' => ['library'], 'guidance' => ['guidance'],
        ];
        if ($scope !== '' && isset($scopeSections[$scope])) {
            // Narrowing only: admins pinned to a scope see the scope's sections;
            // a narrower role never gains the scope's extras. No overlap → basic.
            $sections = $sections === ['all']
                ? $scopeSections[$scope]
                : array_values(array_intersect($sections, $scopeSections[$scope]));
            if ($sections === []) { $sections = []; }
        }

        $viewingAs = $roleNames !== []
            ? implode(' / ', array_map('ucwords', $roleNames))
            : (string) ($user['role'] ?? 'staff');

        return [
            'sections' => array_values(array_unique($sections)),
            'viewing_as' => $viewingAs,
        ];
    }

    private function pdo(): PDO
    {
        return Database::getInstance()->getConnection();
    }

    private function rows(string $sql, array $params): array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function row(string $sql, array $params): ?array
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
