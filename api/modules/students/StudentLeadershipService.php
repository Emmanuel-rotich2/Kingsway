<?php
declare(strict_types=1);

namespace App\API\Modules\students;

use PDO;

/**
 * StudentLeadershipService
 *
 * Per-term student leadership, service/office, house membership, and awards —
 * the governed single source of truth that feeds the student portfolio,
 * certificates, and the longitudinal "everything the student participates in"
 * view (AGENTS.md: Student Leadership & Participation subsystem).
 *
 * Leadership/office records live in the normalized `school_leadership`
 * hierarchy (extended per-term in migration 232), houses and house captains
 * in `houses` + `school_leadership`, and awards/certificates in
 * `student_awards`.
 *
 * Learner records are children (under 15) — all learner-scoped output is
 * confidential/restricted. Server-side scope is enforced (data_scope='live'),
 * and client-supplied student_id is never trusted without intersection with
 * the live data scope.
 */
class StudentLeadershipService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /* =====================================================================
     * HELPERS
     * =================================================================== */

    private function ok($data, string $message = 'OK'): array
    {
        return ['status' => 'success', 'code' => 200, 'message' => $message, 'data' => $data];
    }

    private function created($data, string $message = 'Created'): array
    {
        return ['status' => 'success', 'code' => 201, 'message' => $message, 'data' => $data];
    }

    private function fail(int $code, string $message): array
    {
        return ['status' => 'error', 'code' => $code, 'message' => $message, 'data' => null];
    }

    private function studentExists(int $id): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM students st JOIN persons p ON p.id = st.person_id
             WHERE st.id = ? AND p.data_scope = 'live'"
        );
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }

    private function positionExists(int $id, bool $studentLevel = true): bool
    {
        $sql = "SELECT 1 FROM leadership_positions WHERE id = ?";
        $params = [$id];
        if ($studentLevel) {
            $sql .= " AND level_id = 5";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    private function termExists(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM academic_year_terms WHERE id = ?");
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }

    private function homeNotChild(int $studentId): bool
    {
        // Reject assigning a student leader to their own house patronage misuse;
        // kept simple: no-op guard retained for clarity of intent.
        return true;
    }

    /* =====================================================================
     * LEADERSHIP (school_leadership)
     * =================================================================== */

    /**
     * List leadership records with optional filters.
     */
    public function list(array $filters = []): array
    {
        $where = ["p.data_scope = 'live'"];
        $params = [];

        if (!empty($filters['academic_year_id'])) {
            $where[] = 'l.academic_year_id = ?';
            $params[] = (int) $filters['academic_year_id'];
        }
        if (!empty($filters['academic_year_term_id'])) {
            $where[] = 'l.academic_year_term_id = ?';
            $params[] = (int) $filters['academic_year_term_id'];
        }
        if (!empty($filters['position_id'])) {
            $where[] = 'l.position_id = ?';
            $params[] = (int) $filters['position_id'];
        }
        if (!empty($filters['position_category'])) {
            $where[] = 'l.position_category = ?';
            $params[] = $filters['position_category'];
        }
        if (!empty($filters['house_id'])) {
            $where[] = 'l.house_id = ?';
            $params[] = (int) $filters['house_id'];
        }
        if (!empty($filters['class_stream_id'])) {
            $where[] = 'aycs.id = ?';
            $params[] = (int) $filters['class_stream_id'];
        }
        if (!empty($filters['student_id'])) {
            $where[] = 'l.student_id = ?';
            $params[] = (int) $filters['student_id'];
        }

        // Only leadership positions that are student-level (level 5).
        $where[] = 'lp.level_id = 5';

        $sql = "
            SELECT l.id, l.academic_year_id, l.academic_year_term_id, l.house_id,
                   l.position_id, l.position_category, l.public_bio, l.display_order,
                   l.is_active, l.start_date, l.end_date,
                   lp.name AS position_name, lp.display_order AS position_display_order,
                   CONCAT_WS(' ', p.first_name, p.last_name) AS student_name, st.admission_no,
                   p.photo_url AS photo_url, l.public_photo_url,
                   h.name AS house_name, h.code AS house_code, h.color AS house_color,
                   ay.year_name AS academic_year_name,
                   ayt.term_id AS term_number,
                   TRIM(CONCAT(COALESCE(cls.name, ''), ' ', COALESCE(strm.name, ''))) AS class_stream
            FROM school_leadership l
            JOIN leadership_positions lp ON lp.id = l.position_id
            JOIN students st ON st.id = l.student_id
            JOIN persons p ON p.id = st.person_id
            LEFT JOIN houses h ON h.id = l.house_id
            LEFT JOIN academic_years ay ON ay.id = l.academic_year_id
            LEFT JOIN academic_year_terms ayt ON ayt.id = l.academic_year_term_id
            LEFT JOIN student_academic_enrollments sae ON sae.student_id = st.id
                AND sae.academic_year_id = l.academic_year_id
            LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
            LEFT JOIN streams strm ON strm.id = aycs.stream_id
            LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
            LEFT JOIN classes cls ON cls.id = ayc.class_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY lp.display_order ASC, p.first_name, p.last_name
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $this->ok($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Longitudinal record for one student across all terms/years.
     */
    public function history(int $studentId): array
    {
        if (!$this->studentExists($studentId)) {
            return $this->fail(422, 'Unknown or inactive student');
        }

        $stmt = $this->db->prepare("
            SELECT l.id, l.academic_year_id, l.academic_year_term_id, l.house_id,
                   l.position_id, l.position_category, l.public_bio, l.display_order,
                   l.is_active, l.start_date, l.end_date,
                   lp.name AS position_name,
                   h.name AS house_name, h.code AS house_code, h.color AS house_color,
                   ay.year_code AS academic_year, ay.year_name,
                   ayt.term_id AS term_number,
                   strm.name AS class_stream
            FROM school_leadership l
            JOIN leadership_positions lp ON lp.id = l.position_id
            LEFT JOIN houses h ON h.id = l.house_id
            LEFT JOIN academic_years ay ON ay.id = l.academic_year_id
            LEFT JOIN academic_year_terms ayt ON ayt.id = l.academic_year_term_id
            LEFT JOIN student_academic_enrollments sae ON sae.student_id = l.student_id
                AND sae.academic_year_id = l.academic_year_id
            LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
            LEFT JOIN streams strm ON strm.id = aycs.stream_id
            WHERE l.student_id = ?
            ORDER BY l.academic_year_id DESC, l.academic_year_term_id DESC, lp.display_order ASC
        ");
        $stmt->execute([$studentId]);

        $leadership = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Awards history for the same learner.
        $stmt = $this->db->prepare("
            SELECT a.id, a.award_type, a.title, a.award_description, a.issued_by,
                   a.certificate_no, a.issue_date, a.status, a.notes,
                   ay.year_code AS academic_year, ay.year_name,
                   ayt.term_id AS term_number
            FROM student_awards a
            LEFT JOIN academic_years ay ON ay.id = a.academic_year_id
            LEFT JOIN academic_year_terms ayt ON ayt.id = a.academic_year_term_id
            WHERE a.student_id = ?
            ORDER BY COALESCE(a.issue_date, a.created_at) DESC
        ");
        $stmt->execute([$studentId]);
        $awards = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->ok([
            'student_id' => $studentId,
            'leadership' => $leadership,
            'awards' => $awards,
        ]);
    }

    /**
     * Assign a student to a leadership position for a term/year.
     */
    public function create(array $data): array
    {
        $studentId  = (int) ($data['student_id'] ?? 0);
        $positionId = (int) ($data['position_id'] ?? 0);
        $cat        = $this->normalizeCategory($data['position_category'] ?? null);
        $yearId     = (int) ($data['academic_year_id'] ?? 0);
        $termId     = ($data['academic_year_term_id'] ?? null) !== '' && ($data['academic_year_term_id'] ?? null) !== null
                        ? (int) $data['academic_year_term_id'] : null;
        $houseId    = ($data['house_id'] ?? null) !== '' && ($data['house_id'] ?? null) !== null
                        ? (int) $data['house_id'] : null;
        $startDate  = $data['start_date'] ?? date('Y-m-d');
        $endDate    = $data['end_date'] ?? null;
        $bio        = $data['public_bio'] ?? null;

        if (!$studentId || !$positionId) {
            return $this->fail(422, 'student_id and position_id are required');
        }
        if (!$this->studentExists($studentId)) {
            return $this->fail(422, 'Unknown or inactive student');
        }
        if (!$this->positionExists($positionId, true)) {
            return $this->fail(422, 'Unknown student-leadership position');
        }
        if ($termId && !$this->termExists($termId)) {
            return $this->fail(422, 'Unknown academic year term');
        }
        if (!$yearId && $termId) {
            $stmt = $this->db->prepare("SELECT academic_year_id FROM academic_year_terms WHERE id = ?");
            $stmt->execute([$termId]);
            $yearId = (int) $stmt->fetchColumn();
        }
        if (!$yearId && $startDate) {
            // Resolve the governed academic year that contains the start date,
            // so the Chaplain never has to hardcode a year when assigning a
            // student ministry/leadership role.
            $stmt = $this->db->prepare(
                "SELECT id FROM academic_years
                 WHERE status != 'archived' AND start_date <= ? AND ? <= end_date
                 ORDER BY start_date DESC LIMIT 1"
            );
            $stmt->execute([$startDate, $startDate]);
            $yearId = (int) $stmt->fetchColumn();
        }
        if ($houseId) {
            $stmt = $this->db->prepare("SELECT 1 FROM houses WHERE id = ?");
            $stmt->execute([$houseId]);
            if (!$stmt->fetchColumn()) {
                return $this->fail(422, 'Unknown house');
            }
        }

        // Avoid duplicate active leadership for the same student+position+term.
        $dup = $this->db->prepare("
            SELECT 1 FROM school_leadership
            WHERE student_id = ? AND position_id = ?
              AND (? IS NULL AND academic_year_term_id IS NULL OR academic_year_term_id = ?)
              AND is_active = 1
        ");
        $dup->execute([$studentId, $positionId, $termId, $termId]);
        if ($dup->fetchColumn()) {
            return $this->fail(409, 'This student already holds this position for the given term/year');
        }

        $ins = $this->db->prepare("
            INSERT INTO school_leadership
                (academic_year_id, academic_year_term_id, position_id, position_category,
                 student_id, house_id, public_bio, display_order, is_active, start_date, end_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1, ?, ?)
        ");
        $ins->execute([$yearId, $termId, $positionId, $cat, $studentId, $houseId, $bio, $startDate, $endDate]);

        return $this->created(['id' => (int) $this->db->lastInsertId()], 'Leadership position assigned');
    }

    public function update(int $id, array $data): array
    {
        $record = $this->fetchLeaderById($id);
        if (!$record) {
            return $this->fail(404, 'Leadership record not found');
        }

        $fields = [];
        $params = [];
        $map = [
            'position_id' => 'position_id',
            'position_category' => 'position_category',
            'academic_year_id' => 'academic_year_id',
            'academic_year_term_id' => 'academic_year_term_id',
            'house_id' => 'house_id',
            'public_bio' => 'public_bio',
            'display_order' => 'display_order',
            'start_date' => 'start_date',
            'end_date' => 'end_date',
            'is_active' => 'is_active',
        ];
        foreach ($map as $in => $col) {
            if (array_key_exists($in, $data)) {
                if ($in === 'position_category') {
                    $fields[] = "$col = ?";
                    $params[] = $this->normalizeCategory($data[$in]);
                } elseif (in_array($in, ['academic_year_term_id', 'house_id'], true) && ($data[$in] === '' || $data[$in] === null)) {
                    $fields[] = "$col = NULL";
                } else {
                    $fields[] = "$col = ?";
                    $params[] = $data[$in];
                }
            }
        }
        if (!$fields) {
            return $this->ok([], 'Nothing to update');
        }
        $params[] = $id;
        $this->db->prepare("UPDATE school_leadership SET " . implode(', ', $fields) . " WHERE id = ?")
            ->execute($params);

        return $this->ok(['id' => $id], 'Leadership record updated');
    }

    public function delete(int $id): array
    {
        $record = $this->fetchLeaderById($id);
        if (!$record) {
            return $this->fail(404, 'Leadership record not found');
        }
        $this->db->prepare("DELETE FROM school_leadership WHERE id = ?")->execute([$id]);
        return $this->ok(['id' => $id, 'deleted' => true], 'Leadership record removed');
    }

    private function fetchLeaderById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM school_leadership WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function normalizeCategory(?string $cat): string
    {
        $allowed = ['school', 'class', 'house', 'club', 'spiritual', 'sports', 'welfare', 'library', 'patrol', 'academic'];
        $cat = strtolower(trim((string) $cat));
        return in_array($cat, $allowed, true) ? $cat : 'school';
    }

    /* =====================================================================
     * POSITIONS & LEVELS (lookup)
     * =================================================================== */

    public function positions(): array
    {
        $stmt = $this->db->query("
            SELECT lp.id, lp.name, lp.display_order, lp.is_active, ll.id AS level_id, ll.name AS level_name
            FROM leadership_positions lp
            JOIN leadership_levels ll ON ll.id = lp.level_id
            WHERE lp.is_active = 1
            ORDER BY ll.display_order, lp.display_order
        ");
        return $this->ok($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /* =====================================================================
     * HOUSES
     * =================================================================== */

    public function listHouses(array $filters = []): array
    {
        $where = [];
        $params = [];
        if (!empty($filters['is_active'])) {
            $where[] = 'h.is_active = 1';
        }
        $sql = "
            SELECT h.id, h.name, h.code, h.motto, h.color, h.mascot, h.display_order,
                   h.is_active,
                   CONCAT_WS(' ', sp.first_name, sp.last_name) AS patron_name,
                   (SELECT COUNT(*) FROM students st JOIN persons p ON p.id=st.person_id
                      JOIN school_leadership sl ON sl.student_id = st.id AND sl.house_id = h.id AND sl.is_active=1
                     WHERE p.data_scope='live') AS active_members
            FROM houses h
            LEFT JOIN staff hs ON hs.id = h.patron_staff_id
            LEFT JOIN persons sp ON sp.id = hs.person_id
            " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
            ORDER BY h.display_order, h.name
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $this->ok($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function createHouse(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return $this->fail(422, 'House name is required');
        }
        $stmt = $this->db->prepare("SELECT 1 FROM houses WHERE name = ? OR code = ?");
        $stmt->execute([$name, $data['code'] ?? null]);
        if ($stmt->fetchColumn()) {
            return $this->fail(409, 'A house with this name or code already exists');
        }
        $ins = $this->db->prepare("
            INSERT INTO houses (name, code, patron_staff_id, motto, color, mascot, display_order, is_active, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)
        ");
        $ins->execute([
            $name,
            $data['code'] ?? strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 8)),
            $data['patron_staff_id'] ?? null,
            $data['motto'] ?? null,
            $data['color'] ?? null,
            $data['mascot'] ?? null,
            (int) ($data['display_order'] ?? 0),
            $data['created_by'] ?? null,
        ]);
        return $this->created(['id' => (int) $this->db->lastInsertId()], 'House created');
    }

    public function updateHouse(int $id, array $data): array
    {
        $stmt = $this->db->prepare("SELECT 1 FROM houses WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) {
            return $this->fail(404, 'House not found');
        }
        $fields = [];
        $params = [];
        foreach (['name', 'code', 'patron_staff_id', 'motto', 'color', 'mascot', 'display_order', 'is_active'] as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "$f = ?";
                $params[] = $data[$f];
            }
        }
        if (!$fields) {
            return $this->ok([], 'Nothing to update');
        }
        $params[] = $id;
        $this->db->prepare("UPDATE houses SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
        return $this->ok(['id' => $id], 'House updated');
    }

    /* =====================================================================
     * AWARD TAXONOMY (student_award_categories / student_award_types)
     * =================================================================== */

    /**
     * Award catalogue: categories, each with its active types (for cascading
     * selects in the Issue-Award modal).
     */
    public function getAwardCatalog(): array
    {
        $stmt = $this->db->prepare("
            SELECT c.id, c.code, c.name, c.description, c.sort_order
            FROM student_award_categories c
            WHERE c.is_active = 1
            ORDER BY c.sort_order, c.name
        ");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("
            SELECT t.id, t.category_id, t.department_id, t.template_key, t.code, t.name,
                   t.description, t.number_prefix, t.signatory_label,
                   t.secondary_signatory_label, t.requires_certificate,
                   d.name AS department_name
            FROM student_award_types t
            LEFT JOIN departments d ON d.id = t.department_id
            WHERE t.is_active = 1
            ORDER BY t.sort_order, t.name
        ");
        $stmt->execute();
        $types = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($categories as &$category) {
            $category['types'] = array_values(array_filter($types, function ($type) use ($category) {
                return (int) $type['category_id'] === (int) $category['id'];
            }));
        }
        unset($category);

        return $this->ok(['categories' => $categories]);
    }

    public function listAwardCategories(): array
    {
        $stmt = $this->db->prepare("
            SELECT c.id, c.code, c.name, c.description, c.sort_order, c.is_active,
                   COUNT(t.id) AS type_count
            FROM student_award_categories c
            LEFT JOIN student_award_types t ON t.category_id = c.id
            GROUP BY c.id
            ORDER BY c.sort_order, c.name
        ");
        $stmt->execute();
        return $this->ok($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function listAwardTypes(array $filters = []): array
    {
        $where = [];
        $params = [];
        if (!empty($filters['category_id'])) {
            $where[] = 't.category_id = ?';
            $params[] = (int) $filters['category_id'];
        }
        if (!empty($filters['department_id'])) {
            $where[] = 't.department_id = ?';
            $params[] = (int) $filters['department_id'];
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $where[] = 't.is_active = ?';
            $params[] = (int) $filters['is_active'];
        }
        $sql = "
            SELECT t.id, t.category_id, t.department_id, t.template_key, t.code, t.name,
                   t.description, t.number_prefix, t.signatory_label,
                   t.secondary_signatory_label, t.requires_certificate, t.is_active,
                   t.sort_order, c.name AS category_name, c.code AS category_code,
                   d.name AS department_name
            FROM student_award_types t
            LEFT JOIN student_award_categories c ON c.id = t.category_id
            LEFT JOIN departments d ON d.id = t.department_id
        ";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY t.sort_order, t.name';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $this->ok($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function awardTypeExists(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM student_award_types WHERE id = ?");
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }

    private function validateAwardTypeData(array $data): ?array
    {
        $categoryId = (int) ($data['category_id'] ?? 0);
        $code = trim((string) ($data['code'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        if (!$categoryId || $code === '' || $name === '') {
            return ['category_id, code and name are required'];
        }
        $stmt = $this->db->prepare("SELECT 1 FROM student_award_categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        if (!$stmt->fetchColumn()) {
            return ['Unknown award category'];
        }
        $templateKey = $data['template_key'] ?? null;
        if ($templateKey !== null && $templateKey !== '') {
            if (!preg_match('/^[a-z0-9_]{1,80}$/i', (string) $templateKey)) {
                return ['Invalid template key'];
            }
        }
        return null;
    }

    public function createAwardType(array $data): array
    {
        $errors = $this->validateAwardTypeData($data);
        if ($errors) {
            return $this->fail(422, implode(' ', $errors));
        }
        $stmt = $this->db->prepare("SELECT 1 FROM student_award_types WHERE code = ?");
        $stmt->execute([trim((string) $data['code'])]);
        if ($stmt->fetchColumn()) {
            return $this->fail(409, 'An award type with this code already exists');
        }
        $ins = $this->db->prepare("
            INSERT INTO student_award_types
                (category_id, department_id, template_key, code, name, description,
                 number_prefix, signatory_label, secondary_signatory_label,
                 requires_certificate, is_active, sort_order, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([
            (int) $data['category_id'],
            $data['department_id'] !== '' && $data['department_id'] !== null ? (int) $data['department_id'] : null,
            $data['template_key'] !== '' ? $data['template_key'] : null,
            trim((string) $data['code']),
            trim((string) $data['name']),
            $data['description'] !== '' ? $data['description'] : null,
            $data['number_prefix'] !== '' ? strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $data['number_prefix']), 0, 12)) : null,
            $data['signatory_label'] !== '' ? $data['signatory_label'] : null,
            $data['secondary_signatory_label'] !== '' ? $data['secondary_signatory_label'] : null,
            (int) ($data['requires_certificate'] ?? 1),
            (int) ($data['is_active'] ?? 1),
            (int) ($data['sort_order'] ?? 0),
            $data['created_by'] ?? null,
        ]);
        return $this->created(['id' => (int) $this->db->lastInsertId()], 'Award type created');
    }

    public function updateAwardType(int $id, array $data): array
    {
        if (!$this->awardTypeExists($id)) {
            return $this->fail(404, 'Award type not found');
        }
        if (isset($data['category_id']) || isset($data['code']) || isset($data['name'])) {
            $errors = $this->validateAwardTypeData($data);
            if ($errors) {
                return $this->fail(422, implode(' ', $errors));
            }
        }
        if (!empty($data['code'])) {
            $stmt = $this->db->prepare("SELECT 1 FROM student_award_types WHERE code = ? AND id <> ?");
            $stmt->execute([trim((string) $data['code']), $id]);
            if ($stmt->fetchColumn()) {
                return $this->fail(409, 'An award type with this code already exists');
            }
        }
        $fields = [];
        $params = [];
        foreach (['category_id', 'department_id', 'template_key', 'code', 'name', 'description',
                  'signatory_label', 'secondary_signatory_label', 'requires_certificate',
                  'is_active', 'sort_order'] as $f) {
            if (array_key_exists($f, $data)) {
                $value = $data[$f];
                if ($f === 'department_id' || $f === 'description' || $f === 'template_key') {
                    $value = ($value === '' || $value === null) ? null : $value;
                }
                if ($f === 'number_prefix' && $value !== null && $value !== '') {
                    $value = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $value), 0, 12));
                }
                $fields[] = "$f = ?";
                $params[] = $value;
            }
        }
        if (!$fields) {
            return $this->ok([], 'Nothing to update');
        }
        $params[] = $id;
        $this->db->prepare("UPDATE student_award_types SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
        return $this->ok(['id' => $id], 'Award type updated');
    }

    public function deleteAwardType(int $id): array
    {
        if (!$this->awardTypeExists($id)) {
            return $this->fail(404, 'Award type not found');
        }
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM student_awards WHERE award_type_id = ?");
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            return $this->fail(409, 'Award type is in use and cannot be deleted');
        }
        $this->db->prepare("DELETE FROM student_award_types WHERE id = ?")->execute([$id]);
        return $this->ok(['id' => $id, 'deleted' => true], 'Award type removed');
    }

    /* =====================================================================
     * AWARDS & CERTIFICATES (student_awards)
     * =================================================================== */

    public function listAwards(array $filters = []): array
    {
        $where = ['p.data_scope = \'live\''];
        $params = [];
        if (!empty($filters['student_id'])) {
            $where[] = 'a.student_id = ?';
            $params[] = (int) $filters['student_id'];
        }
        if (!empty($filters['award_type_id'])) {
            $where[] = 'a.award_type_id = ?';
            $params[] = (int) $filters['award_type_id'];
        }
        if (!empty($filters['award_category_id'])) {
            $where[] = 'c.id = ?';
            $params[] = (int) $filters['award_category_id'];
        }
        if (!empty($filters['academic_year_id'])) {
            $where[] = 'a.academic_year_id = ?';
            $params[] = (int) $filters['academic_year_id'];
        }
        if (!empty($filters['academic_year_term_id'])) {
            $where[] = 'a.academic_year_term_id = ?';
            $params[] = (int) $filters['academic_year_term_id'];
        }
        $sql = "
            SELECT a.id, a.student_id, a.award_type_id, a.title, a.award_description,
                   a.academic_year_id, a.academic_year_term_id, a.issued_by,
                   a.certificate_no, a.issue_date, a.status, a.notes,
                   st.admission_no,
                   CONCAT_WS(' ', p.first_name, p.last_name) AS student_name,
                   ay.year_name AS academic_year_name,
                   ayt.term_id AS term_number,
                   t.code AS award_type_code, t.name AS award_type_name,
                   t.template_key, t.number_prefix, t.signatory_label,
                   t.secondary_signatory_label,
                   c.id AS award_category_id, c.code AS award_category_code,
                   c.name AS award_category_name,
                   d.name AS department_name,
                   ac.certificate_number AS generated_certificate_number,
                   ac.pdf_path AS certificate_pdf_path
            FROM student_awards a
            JOIN students st ON st.id = a.student_id
            JOIN persons p ON p.id = st.person_id
            LEFT JOIN student_award_types t ON t.id = a.award_type_id
            LEFT JOIN student_award_categories c ON c.id = t.category_id
            LEFT JOIN departments d ON d.id = t.department_id
            LEFT JOIN student_award_certificates ac ON ac.award_id = a.id
            LEFT JOIN academic_years ay ON ay.id = a.academic_year_id
            LEFT JOIN academic_year_terms ayt ON ayt.id = a.academic_year_term_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY COALESCE(a.issue_date, a.created_at) DESC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $this->ok($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function normalizeAwardTypeId(array $data): ?int
    {
        if (!array_key_exists('award_type_id', $data) || $data['award_type_id'] === '' || $data['award_type_id'] === null) {
            return null;
        }
        $id = (int) $data['award_type_id'];
        return $this->awardTypeExists($id) ? $id : null;
    }

    public function createAward(array $data): array
    {
        $studentId = (int) ($data['student_id'] ?? 0);
        $title     = trim((string) ($data['title'] ?? ''));
        if (!$studentId || $title === '') {
            return $this->fail(422, 'student_id and title are required');
        }
        if (!$this->studentExists($studentId)) {
            return $this->fail(422, 'Unknown or inactive student');
        }
        $awardTypeId = $this->normalizeAwardTypeId($data);
        $termId = ($data['academic_year_term_id'] ?? null) !== '' && ($data['academic_year_term_id'] ?? null) !== null
            ? (int) $data['academic_year_term_id'] : null;
        $yearId = (int) ($data['academic_year_id'] ?? 0);
        if ($termId && $this->termExists($termId)) {
            $stmt = $this->db->prepare("SELECT academic_year_id FROM academic_year_terms WHERE id = ?");
            $stmt->execute([$termId]);
            $yearId = (int) $stmt->fetchColumn();
        }

        $ins = $this->db->prepare("
            INSERT INTO student_awards
                (student_id, award_type_id, title, award_description, academic_year_id,
                 academic_year_term_id, issued_by, certificate_no, issue_date, status, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([
            $studentId,
            $awardTypeId,
            $title,
            $data['award_description'] ?? null,
            $yearId,
            $termId,
            $data['issued_by'] ?? null,
            $data['certificate_no'] ?? null,
            $data['issue_date'] ?? null,
            $data['status'] ?? 'awarded',
            $data['notes'] ?? null,
            $data['created_by'] ?? null,
        ]);
        return $this->created(['id' => (int) $this->db->lastInsertId()], 'Award issued');
    }

    public function updateAward(int $id, array $data): array
    {
        $stmt = $this->db->prepare("SELECT 1 FROM student_awards WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) {
            return $this->fail(404, 'Award not found');
        }
        $fields = [];
        $params = [];
        if (array_key_exists('award_type_id', $data)) {
            $fields[] = 'award_type_id = ?';
            $params[] = $this->normalizeAwardTypeId($data);
        }
        foreach (['title', 'award_description', 'academic_year_id', 'issued_by', 'certificate_no', 'issue_date', 'status', 'notes'] as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "$f = ?";
                $params[] = $data[$f];
            }
        }
        if (array_key_exists('academic_year_term_id', $data)) {
            if ($data['academic_year_term_id'] === '' || $data['academic_year_term_id'] === null) {
                $fields[] = 'academic_year_term_id = NULL';
            } else {
                $fields[] = 'academic_year_term_id = ?';
                $params[] = $data['academic_year_term_id'];
            }
        }
        if (!$fields) {
            return $this->ok([], 'Nothing to update');
        }
        $params[] = $id;
        $this->db->prepare("UPDATE student_awards SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
        return $this->ok(['id' => $id], 'Award updated');
    }

    public function deleteAward(int $id): array
    {
        $stmt = $this->db->prepare("SELECT 1 FROM student_awards WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetchColumn()) {
            return $this->fail(404, 'Award not found');
        }
        $this->db->prepare("DELETE FROM student_awards WHERE id = ?")->execute([$id]);
        return $this->ok(['id' => $id, 'deleted' => true], 'Award removed');
    }
}
