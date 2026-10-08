<?php
declare(strict_types=1);

namespace App\API\Modules\students;

use PDO;
use App\API\Services\ReadReplicaService;

/**
 * Normalized student read repository (3NF/4NF).
 *
 * Identity lives in `persons`; `students` is the learner subtype; year-scoped
 * placement lives in `student_academic_enrollments` → `academic_year_class_streams`
 * → `academic_year_classes` → `classes`/`streams`. Parent contact resolves through
 * `student_parents` → `parents` → `persons`. Discipline is read from
 * `discipline_incidents`. Transport from `student_transport_assignments`.
 *
 * This repository owns the canonical student projection consumed by
 * StudentService, StudentsAPI, and the scope service. It never references the
 * retired legacy tables (class_streams, class_enrollments, student_discipline,
 * students.stream_id, academic_terms).
 */
class StudentRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Column list for the learner placement projection (mmv_learner_placement).
     * Mirrors the subset of columns from projectionColumns() that come from the placement chain.
     */
    private function placementColumns(): string
    {
        return "
            lp.student_id AS id,
            lp.enrollment_id,
            lp.academic_year_id,
            lp.class_id,
            lp.class_name,
            lp.stream_id,
            lp.stream_name,
            lp.academic_year_class_stream_id AS aycs_id
        ";
    }

    /**
     * Build the FROM clause using the mmv_learner_placement projection.
     * Replaces the 7-table join chain (students→persons→sae→aycs→ayc→classes→streams).
     * Note: mmv_learner_placement only contains active enrollments. We LEFT JOIN it
     * to students+persons so un-enrolled students still appear (matching old LEFT JOIN behavior).
     */
    private function placementJoins(): string
    {
        return "
            FROM students s
            INNER JOIN persons p ON p.id = s.person_id
            LEFT JOIN student_types st ON st.id = s.student_type_id
            LEFT JOIN " . ReadReplicaService::qualifiedRef('learner_placement') . " lp
                ON lp.student_id = s.id
                AND lp.academic_year_id = (
                    SELECT ay_current.id
                    FROM academic_years ay_current
                    WHERE ay_current.is_current = 1
                    ORDER BY ay_current.id DESC
                    LIMIT 1
                )
            LEFT JOIN academic_years ay ON ay.id = lp.academic_year_id
            LEFT JOIN (
                SELECT
                    sta2.student_id,
                    MIN(sta2.route_id) AS route_id,
                    MIN(tr.name) AS route_name,
                    MIN(sta2.stop_id) AS stop_id,
                    MIN(ts.name) AS stop_name
                FROM student_transport_assignments sta2
                LEFT JOIN transport_routes tr ON tr.id = sta2.route_id
                LEFT JOIN transport_stops ts ON ts.id = sta2.stop_id
                WHERE sta2.status = 'active'
                GROUP BY sta2.student_id
            ) transport_scope ON transport_scope.student_id = s.id
            LEFT JOIN (
                SELECT
                    sp.student_id,
                    SUBSTRING_INDEX(GROUP_CONCAT(CONCAT_WS(' ', pp.first_name, pp.middle_name, pp.last_name) ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC, sp.parent_id ASC SEPARATOR '||'), '||', 1) AS parent_name,
                    SUBSTRING_INDEX(GROUP_CONCAT(pp.phone ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC, sp.parent_id ASC SEPARATOR '||'), '||', 1) AS parent_phone,
                    SUBSTRING_INDEX(GROUP_CONCAT(pp.email ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC, sp.parent_id ASC SEPARATOR '||'), '||', 1) AS parent_email,
                    SUBSTRING_INDEX(GROUP_CONCAT(par.address ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC, sp.parent_id ASC SEPARATOR '||'), '||', 1) AS parent_address
                FROM student_parents sp
                JOIN parents par ON par.id = sp.parent_id
                JOIN persons pp ON pp.id = par.person_id
                GROUP BY sp.student_id
            ) parent_contact ON parent_contact.student_id = s.id
            LEFT JOIN (
                SELECT
                    sae2.student_id,
                    COUNT(di.id) AS discipline_cases_count,
                    SUM(CASE WHEN di.status <> 'resolved' THEN 1 ELSE 0 END) AS open_discipline_cases,
                    MAX(di.severity) AS highest_discipline_severity
                FROM discipline_incidents di
                JOIN student_academic_enrollments sae2 ON sae2.id = di.student_academic_enrollment_id
                GROUP BY sae2.student_id
            ) discipline_summary ON discipline_summary.student_id = s.id
        ";
    }

    public function getDb(): PDO
    {
        return $this->db;
    }

    /**
     * The normalized student projection columns shared by list/detail.
     * Resolves identity (persons), year-scoped placement (enrollments +
     * academic_year_class_streams), student type, primary parent contact,
     * discipline summary, and transport scope.
     */
    private function projectionColumns(): string
    {
        return "
            s.id,
            s.admission_no,
            p.first_name,
            p.middle_name,
            p.last_name,
            CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) AS full_name,
            p.dob AS date_of_birth,
            p.gender,
            p.photo_url,
            p.email AS person_email,
            p.phone AS person_phone,
            s.student_type_id,
            st.name AS student_type_name,
            st.name AS student_type,
            st.code AS student_type_code,
            CASE
                WHEN UPPER(COALESCE(st.code, '')) = 'BOARD' THEN 'boarding'
                ELSE 'day'
            END AS boarding_status,
            s.admission_date,
            s.assessment_number,
            s.assessment_status,
            s.nemis_number,
            s.nemis_status,
            s.status,
            s.blood_group,
            s.application_id,
            s.entry_source,
            s.created_at,
            s.updated_at,
            lp.enrollment_id,
            lp.academic_year_id,
            ay.year_code AS academic_year_code,
            lp.class_id,
            lp.class_name,
            lp.stream_id,
            lp.stream_name,
            lp.aycs_id AS academic_year_class_stream_id,
            parent_contact.parent_name,
            parent_contact.parent_phone,
            parent_contact.parent_email,
            parent_contact.parent_address,
            discipline_summary.discipline_cases_count,
            discipline_summary.open_discipline_cases,
            discipline_summary.highest_discipline_severity,
            transport_scope.route_id,
            transport_scope.route_name,
            transport_scope.stop_id,
            transport_scope.stop_name
";
    }

    public function listScoped(array $conditions, array $bindings, array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(100, max(1, (int) ($filters['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = "(s.admission_no LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) LIKE ?)";
            $term = '%' . $search . '%';
            array_push($bindings, $term, $term, $term, $term);
        }

        if (filter_var($filters['enrolled_only'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $conditions[] = 'lp.enrollment_id IS NOT NULL';
        }

        // Normalized placement filters: class_id and stream_id resolve through
        // the year-scoped enrollment context, never students.stream_id.
        foreach (['class_id' => 'lp.class_id', 'stream_id' => 'lp.stream_id', 'status' => 's.status', 'gender' => 'p.gender', 'student_type_id' => 's.student_type_id', 'academic_year_id' => 'lp.academic_year_id'] as $param => $column) {
            if (!empty($filters[$param])) {
                $conditions[] = "{$column} = ?";
                $bindings[] = $filters[$param];
            }
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $joins = $this->placementJoins();

        $countStmt = $this->db->prepare("SELECT COUNT(DISTINCT s.id) {$joins} {$where}");
        $countStmt->execute($bindings);
        $total = (int) $countStmt->fetchColumn();

        $sql = "
            SELECT DISTINCT
                {$this->projectionColumns()}
            {$joins}
            {$where}
            ORDER BY lp.class_name ASC, lp.stream_name ASC, p.last_name ASC, p.first_name ASC
            LIMIT ? OFFSET ?
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($bindings, [$limit, $offset]));

        return [
            'students' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $limit > 0 ? (int) ceil($total / $limit) : 0,
            ],
        ];
    }

    public function findScoped(int $id, array $conditions, array $bindings): ?array
    {
        $conditions[] = 's.id = ?';
        $bindings[] = $id;
        $where = 'WHERE ' . implode(' AND ', $conditions);

        $stmt = $this->db->prepare("
            SELECT *
            FROM (
                SELECT DISTINCT
                    {$this->projectionColumns()}
                {$this->placementJoins()}
                {$where}
            ) scoped_student
            LIMIT 1
        ");
        $stmt->execute($bindings);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Resolve the active academic-year enrollment id for a student.
     * Returns ['enrollment_id' => int|null, 'academic_year_class_stream_id' => int|null].
     */
    public function currentEnrollmentFor(int $studentId): array
    {
        $stmt = $this->db->prepare("
            SELECT sae.id AS enrollment_id, sae.academic_year_class_stream_id
            FROM " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . "
            JOIN " . ReadReplicaService::qualifiedRef("academic_years") . " ay ON ay.id = sae.academic_year_id
            WHERE sae.student_id = ? AND sae.enrollment_status = 'active'
            ORDER BY ay.is_current DESC, sae.id DESC
            LIMIT 1
        ");
        $stmt->execute([$studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['enrollment_id' => null, 'academic_year_class_stream_id' => null];
        }
        return [
            'enrollment_id' => (int) $row['enrollment_id'],
            'academic_year_class_stream_id' => $row['academic_year_class_stream_id'] ? (int) $row['academic_year_class_stream_id'] : null,
        ];
    }
}
