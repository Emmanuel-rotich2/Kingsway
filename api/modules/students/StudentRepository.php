<?php
declare(strict_types=1);

namespace App\API\Modules\students;

use PDO;

class StudentRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getDb(): PDO
    {
        return $this->db;
    }

    public function listScoped(array $conditions, array $bindings, array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(100, max(1, (int) ($filters['limit'] ?? 25)));
        $offset = ($page - 1) * $limit;

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = "(s.admission_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) LIKE ?)";
            $term = '%' . $search . '%';
            array_push($bindings, $term, $term, $term, $term);
        }

        foreach (['class_id' => 'cs.class_id', 'stream_id' => 's.stream_id', 'status' => 's.status', 'gender' => 's.gender'] as $param => $column) {
            if (!empty($filters[$param])) {
                $conditions[] = "{$column} = ?";
                $bindings[] = $filters[$param];
            }
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $joins = $this->joins();

        $countStmt = $this->db->prepare("SELECT COUNT(DISTINCT s.id) FROM students s {$joins} {$where}");
        $countStmt->execute($bindings);
        $total = (int) $countStmt->fetchColumn();

        $sql = "
            SELECT
                s.id,
                s.admission_no,
                s.first_name,
                s.middle_name,
                s.last_name,
                CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS full_name,
                s.date_of_birth,
                s.gender,
                s.stream_id,
                cs.class_id,
                c.name AS class_name,
                cs.stream_name,
                s.student_type_id,
                st.name AS student_type_name,
                st.name AS student_type,
                st.code AS student_type_code,
                CASE
                    WHEN UPPER(COALESCE(st.code, '')) = 'BOARD' THEN 'boarding'
                    WHEN UPPER(COALESCE(st.code, '')) = 'WEEKLY' THEN 'weekly_boarding'
                    ELSE 'day'
                END AS boarding_status,
                s.admission_date,
                s.assessment_number,
                s.assessment_status,
                s.nemis_number,
                s.nemis_status,
                s.status,
                s.photo_url,
                s.is_sponsored,
                s.sponsor_name,
                s.sponsor_type,
                s.sponsor_waiver_percentage,
                s.blood_group,
                s.created_at,
                s.updated_at,
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
            FROM students s
            {$joins}
            {$where}
            GROUP BY s.id
            ORDER BY c.name ASC, cs.stream_name ASC, s.last_name ASC, s.first_name ASC
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
                SELECT
                    s.id,
                    s.admission_no,
                    s.first_name,
                    s.middle_name,
                    s.last_name,
                    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS full_name,
                    s.date_of_birth,
                    s.gender,
                    s.stream_id,
                    cs.class_id,
                    c.name AS class_name,
                    cs.stream_name,
                    s.student_type_id,
                    st.name AS student_type_name,
                    st.name AS student_type,
                    st.code AS student_type_code,
                    CASE
                        WHEN UPPER(COALESCE(st.code, '')) = 'BOARD' THEN 'boarding'
                        WHEN UPPER(COALESCE(st.code, '')) = 'WEEKLY' THEN 'weekly_boarding'
                        ELSE 'day'
                    END AS boarding_status,
                    s.admission_date,
                    s.assessment_number,
                    s.assessment_status,
                    s.nemis_number,
                    s.nemis_status,
                    s.status,
                    s.photo_url,
                    s.is_sponsored,
                    s.sponsor_name,
                    s.sponsor_type,
                    s.sponsor_waiver_percentage,
                    s.blood_group,
                    s.created_at,
                    s.updated_at,
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
                FROM students s
                {$this->joins()}
                {$where}
                GROUP BY s.id
            ) scoped_student
            LIMIT 1
        ");
        $stmt->execute($bindings);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function joins(): string
    {
        return "
            LEFT JOIN class_streams cs ON cs.id = s.stream_id
            LEFT JOIN classes c ON c.id = cs.class_id
            LEFT JOIN student_types st ON st.id = s.student_type_id
            LEFT JOIN student_transport_assignments sta ON sta.student_id = s.id AND sta.status = 'active'
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
                    SUBSTRING_INDEX(GROUP_CONCAT(CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC, sp.id ASC SEPARATOR '||'), '||', 1) AS parent_name,
                    SUBSTRING_INDEX(GROUP_CONCAT(p.phone_1 ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC, sp.id ASC SEPARATOR '||'), '||', 1) AS parent_phone,
                    SUBSTRING_INDEX(GROUP_CONCAT(p.email ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC, sp.id ASC SEPARATOR '||'), '||', 1) AS parent_email,
                    SUBSTRING_INDEX(GROUP_CONCAT(p.address ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC, sp.id ASC SEPARATOR '||'), '||', 1) AS parent_address
                FROM student_parents sp
                JOIN parents p ON p.id = sp.parent_id
                GROUP BY sp.student_id
            ) parent_contact ON parent_contact.student_id = s.id
            LEFT JOIN (
                SELECT
                    student_id,
                    COUNT(*) AS discipline_cases_count,
                    SUM(CASE WHEN status <> 'resolved' THEN 1 ELSE 0 END) AS open_discipline_cases,
                    MAX(severity) AS highest_discipline_severity
                FROM student_discipline
                GROUP BY student_id
            ) discipline_summary ON discipline_summary.student_id = s.id
        ";
    }
}
