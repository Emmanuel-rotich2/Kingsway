<?php

declare(strict_types=1);

namespace App\API\Services;

use PDO;
use RuntimeException;

/**
 * Versioned grading-scope resolution.
 *
 * The school binds a grading system (4-level CBC or 8-level CBE) to an
 * academic-year term, optionally overriding per class or per learning area.
 * Resolution is most-specific-wins: learning-area binding > class binding >
 * term-wide binding. Exam periods snapshot the resolved system at creation, so
 * editing or superseding a system never rewrites the meaning of completed
 * exams. All definitions live in grading_systems / grading_system_bands /
 * academic_year_term_grading_bindings — no hardcoded bands.
 */
final class GradingScopeService
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array{id:int,code:string,name:string,levels_count:int,category:string} */
    public function system(int $systemId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, code, name, levels_count, category, status
             FROM grading_systems WHERE id = ?"
        );
        $stmt->execute([$systemId]);
        $system = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$system) {
            throw new RuntimeException('Grading system not found', 404);
        }
        if (($system['status'] ?? '') !== 'active') {
            throw new RuntimeException('That grading system is not active', 409);
        }
        unset($system['status']);
        return $system;
    }

    /** @return array<int,array{id:int,code:string,name:string,levels_count:int,category:string,bands:array<int,array<string,mixed>>}> */
    public function systemsWithBands(?int $systemId = null): array
    {
        $sql = "SELECT id, code, name, levels_count, category FROM grading_systems WHERE status = 'active'";
        $params = [];
        if ($systemId) {
            $sql .= ' AND id = ?';
            $params[] = $systemId;
        }
        $sql .= ' ORDER BY levels_count, code';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $systems = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$systems) {
            return [];
        }
        $bandStmt = $this->db->prepare(
            'SELECT band_code, group_code, band_name, min_percentage, max_percentage, points, performance_level, description
             FROM grading_system_bands WHERE grading_system_id = ? ORDER BY min_percentage DESC'
        );
        foreach ($systems as &$system) {
            $bandStmt->execute([(int) $system['id']]);
            $system['bands'] = $bandStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        unset($system);
        return $systems;
    }

    /**
     * Most-specific-wins binding resolution.
     *
     * @return array{system:array,banding:string}|null the resolved grading
     *         system and which binding scope supplied it, or null when no
     *         binding exists for the term.
     */
    public function resolve(int $termId, ?int $classId = null, ?int $learningAreaId = null): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT b.grading_system_id,
                    CASE WHEN b.class_id IS NULL THEN 'term' WHEN b.learning_area_id IS NULL THEN 'class' ELSE 'learning_area' END AS binding_scope
             FROM " . ReadReplicaService::masterRef("academic_year_term_grading_bindings") . " b
             JOIN grading_systems gs ON gs.id = b.grading_system_id AND gs.status = 'active'
             WHERE b.academic_year_term_id = ? AND b.status = 'active'
               AND (b.class_id IS NULL OR b.class_id = ?)
               AND (b.learning_area_id IS NULL OR b.learning_area_id = ?)
             ORDER BY (b.learning_area_id IS NOT NULL) DESC, (b.class_id IS NOT NULL) DESC, b.id
             LIMIT 1"
        );
        $stmt->execute([$termId, (int) $classId, (int) $learningAreaId]);
        $binding = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$binding) {
            return null;
        }
        return [
            'system' => $this->system((int) $binding['grading_system_id']),
            'binding' => (string) $binding['binding_scope'],
        ];
    }

    /**
     * Resolve the grading system for an existing exam period (from its
     * snapshot first, so historical periods keep their original system even
     * after bindings change).
     */
    /**
     * Default system for a class when no binding names one: Junior School
     * (Grades 7 to 9) is graded on the active 8-level scale (KJSEA); other
     * classes keep the given fallback.
     */
    /** 4-level groups of the 8-level scale (EE1/EE2 -> EE, ...). */
    public const LEVEL4_NAMES = [
        'EE' => 'Exceeding Expectation',
        'ME' => 'Meeting Expectation',
        'AE' => 'Approaching Expectation',
        'BE' => 'Below Expectation',
    ];

    private static array $systemCache = [];

    /**
     * The system grades are actually computed on. A system declared as the
     * grouped view of another (view_of_system_id; the CBC 4-level is the
     * 8-level grouped) resolves to that system, so both views always agree.
     * Unlinked systems (e.g. a new Ministry scale) grade exactly as defined.
     * With no system, the school default profile's system is used.
     */
    public function canonicalSystemId(?int $systemId): ?int
    {
        if (!$systemId) {
            $systemId = $this->schoolDefaultSystemId();
            if (!$systemId) return null;
        }
        $row = $this->systemRow($systemId);
        if ($row && !empty($row['view_of_system_id'])) {
            return (int) $row['view_of_system_id'];
        }
        return $systemId;
    }

    /**
     * Grouped-view level names for a system: band_code => band_name of the
     * system declared as its view (e.g. EE => Exceeding Expectation).
     * @return array<string,string>
     */
    public function groupNames(int $systemId): array
    {
        $key = spl_object_id($this->db) . ':g' . $systemId;
        if (!array_key_exists($key, self::$systemCache)) {
            $names = [];
            $views = $this->db->prepare('SELECT id FROM grading_systems WHERE view_of_system_id = ?');
            $views->execute([$systemId]);
            $viewIds = array_map('intval', $views->fetchAll(PDO::FETCH_COLUMN));
            if ($viewIds) {
                $stmt = $this->db->prepare('SELECT band_code, band_name FROM grading_system_bands WHERE grading_system_id IN (' . implode(',', array_fill(0, count($viewIds), '?')) . ') ORDER BY sort_order');
                $stmt->execute($viewIds);
                $names = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
            }
            self::$systemCache[$key] = $names;
        }
        return self::$systemCache[$key];
    }

    private function systemRow(int $systemId): ?array
    {
        $key = spl_object_id($this->db) . ':s' . $systemId;
        if (!array_key_exists($key, self::$systemCache)) {
            $stmt = $this->db->prepare('SELECT id, category, status, view_of_system_id FROM grading_systems WHERE id = ? LIMIT 1');
            $stmt->execute([$systemId]);
            self::$systemCache[$key] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        return self::$systemCache[$key];
    }

    private function schoolDefaultSystemId(): ?int
    {
        $key = spl_object_id($this->db) . ':default';
        if (!array_key_exists($key, self::$systemCache)) {
            $id = null;
            $candidates = $this->db->query(
                "SELECT grading_system_id FROM assessment_term_profiles
                 WHERE scope = 'school_default' AND grading_system_id IS NOT NULL ORDER BY id DESC"
            )->fetchAll(PDO::FETCH_COLUMN);
            foreach ($candidates as $candidate) {
                $row = $this->systemRow((int) $candidate);
                if ($row && ($row['status'] ?? '') === 'active') { $id = (int) $candidate; break; }
            }
            if (!$id) {
                $id = $this->db->query("SELECT id FROM grading_systems WHERE status = 'active' AND view_of_system_id IS NULL ORDER BY levels_count DESC, id DESC LIMIT 1")->fetchColumn();
            }
            self::$systemCache[$key] = $id ? (int) $id : null;
        }
        return self::$systemCache[$key];
    }

    /** The system a term grades on (its setting, else the school default), canonical. */
    public function systemForTerm(?int $termId): ?int
    {
        $key = spl_object_id($this->db) . ':t' . (int) $termId;
        if (!array_key_exists($key, self::$systemCache)) {
            $resolved = null;
            if ($termId) {
                try { $b = $this->resolve($termId, null, null); $resolved = isset($b['system']['id']) ? (int) $b['system']['id'] : null; } catch (\Throwable $e) { $resolved = null; }
            }
            self::$systemCache[$key] = $this->canonicalSystemId($resolved);
        }
        return self::$systemCache[$key];
    }

    /**
     * Percentage range of a level on a system: one band (e.g. ME1) or a whole
     * group of bands (e.g. ME = ME1 + ME2). Null when the code is unknown.
     * @return array{code:string,min:float,max:float,midpoint:float}|null
     */
    public function levelRange(int $systemId, string $code): ?array
    {
        $code = strtoupper(trim($code));
        if ($code === '') return null;
        $stmt = $this->db->prepare('SELECT band_code, group_code, min_percentage, max_percentage FROM grading_system_bands WHERE grading_system_id = ?');
        $stmt->execute([$systemId]);
        $min = null; $max = null;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
            $group = $b['group_code'] ?: self::level4((string) $b['band_code']);
            if (strtoupper((string) $b['band_code']) === $code || ($group !== null && strtoupper((string) $group) === $code)) {
                $min = $min === null ? (float) $b['min_percentage'] : min($min, (float) $b['min_percentage']);
                $max = $max === null ? (float) $b['max_percentage'] : max($max, (float) $b['max_percentage']);
            }
        }
        if ($min === null) return null;
        return ['code' => $code, 'min' => $min, 'max' => $max, 'midpoint' => round(($min + $max) / 2, 2)];
    }

    /** Forget cached system rows (after creating or publishing a system). */
    public static function forgetCache(): void
    {
        self::$systemCache = [];
    }

    /** 4-level group of a CBC code: ME1 -> ME. Null when not a CBC code. */
    public static function level4(?string $code): ?string
    {
        $group = strtoupper(substr(trim((string) $code), 0, 2));
        return isset(self::LEVEL4_NAMES[$group]) ? $group : null;
    }

    public function defaultSystemForClass(int $classId, ?int $fallback): ?int
    {
        if ($classId <= 0) return $fallback;
        $stmt = $this->db->prepare('SELECT name FROM classes WHERE id = ? LIMIT 1');
        $stmt->execute([$classId]);
        $name = (string) $stmt->fetchColumn();
        if (preg_match('/grade\s*([789])\b/i', $name) !== 1) return $fallback;
        $eight = $this->db->query(
            "SELECT id FROM grading_systems WHERE category = 'cbc_8level' AND status = 'active' ORDER BY id DESC LIMIT 1"
        )->fetchColumn();
        return $eight ? (int) $eight : $fallback;
    }

    public function resolveForPeriod(int $examPeriodId): ?array
    {
        $stmt = $this->db->prepare('SELECT grading_system_id FROM exam_periods WHERE id = ?');
        $stmt->execute([$examPeriodId]);
        $systemId = $stmt->fetchColumn();
        if ($systemId !== null && $systemId !== false && (int) $systemId > 0) {
            return ['system' => $this->system((int) $systemId), 'binding' => 'period_snapshot'];
        }
        return null;
    }

    /**
     * Resolve the grading system governing an assessment from its own scope
     * (term + class + learning area). Used at mark-entry time so every stored
     * grade is computed against the system that was in force for that class.
     */
    public function resolveForAssessment(int $assessmentId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT a.academic_year_term_id, a.learning_area_id, aycs.academic_year_class_id
             FROM ' . ReadReplicaService::masterRef('assessments') . ' a
             JOIN academic_year_class_streams aycs ON aycs.id = a.academic_year_class_stream_id
             WHERE a.id = ?'
        );
        $stmt->execute([$assessmentId]);
        $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$assessment) {
            throw new RuntimeException('Assessment not found', 404);
        }
        return $this->resolve(
            (int) $assessment['academic_year_term_id'],
            (int) $assessment['academic_year_class_id'],
            (int) $assessment['learning_area_id']
        );
    }

    /**
     * Match a percentage against a system's bands. Deterministic: the stored
     * band rows are the authority; nothing is computed in PHP.
     *
     * @return array{band_code:string,band_name:string,min_percentage:float,max_percentage:float,points:float,performance_level:string,description:string}
     */
    public function bandForPercentage(int $systemId, float $percentage): array
    {
        $stmt = $this->db->prepare(
            'SELECT band_code, group_code, band_name, min_percentage, max_percentage, points, performance_level, description
             FROM grading_system_bands
             WHERE grading_system_id = ?
               AND ? >= min_percentage AND ? <= max_percentage
             ORDER BY min_percentage DESC LIMIT 1'
        );
        $stmt->execute([$systemId, $percentage, $percentage]);
        $band = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$band) {
            throw new RuntimeException('The selected grading system does not cover that score', 409);
        }
        return $band;
    }

    /**
     * Save (upsert) a binding. Passing null class/learning-area binds the
     * whole term; a class id binds the class; both bind one learning area.
     */
    public function bind(int $termId, ?int $classId, ?int $learningAreaId, int $systemId, ?int $userId): array
    {
        // Validate the referenced scope rows exist.
        $this->system($systemId);
        $termStmt = $this->db->prepare('SELECT id FROM academic_year_terms WHERE id = ?');
        $termStmt->execute([$termId]);
        if (!$termStmt->fetchColumn()) {
            throw new RuntimeException('Academic-year term not found', 404);
        }
        if ($classId) {
            $classStmt = $this->db->prepare('SELECT id FROM classes WHERE id = ?');
            $classStmt->execute([$classId]);
            if (!$classStmt->fetchColumn()) {
                throw new RuntimeException('Class not found', 404);
            }
        }
        if ($learningAreaId) {
            $areaStmt = $this->db->prepare('SELECT id FROM learning_areas WHERE id = ?');
            $areaStmt->execute([$learningAreaId]);
            if (!$areaStmt->fetchColumn()) {
                throw new RuntimeException('Learning area not found', 404);
            }
        }

        // NULL class / learning area never collide in the unique key, so a
        // new whole-school setting must retire the previous one explicitly.
        $this->db->prepare(
            "UPDATE academic_year_term_grading_bindings SET status = 'inactive'
             WHERE academic_year_term_id = ? AND class_id <=> ? AND learning_area_id <=> ? AND status = 'active'"
        )->execute([$termId, $classId, $learningAreaId]);
        $upsert = $this->db->prepare(
            'INSERT INTO academic_year_term_grading_bindings
                (academic_year_term_id, class_id, learning_area_id, grading_system_id, status, created_by)
             VALUES (?, ?, ?, ?, \'active\', ?)
             ON DUPLICATE KEY UPDATE grading_system_id = VALUES(grading_system_id), status = \'active\', created_by = VALUES(created_by)'
        );
        $upsert->execute([$termId, $classId, $learningAreaId, $systemId, $userId]);
        return [
            'academic_year_term_id' => $termId,
            'class_id' => $classId,
            'learning_area_id' => $learningAreaId,
            'grading_system_id' => $systemId,
        ];
    }

    /**
     * List bindings for a term with resolved names, for the admin UI.
     */
    public function bindingsForTerm(int $termId): array
    {
        $stmt = $this->db->prepare(
            'SELECT b.id, b.academic_year_term_id, b.class_id, c.name AS class_name,
                    b.learning_area_id, la.name AS learning_area_name,
                    b.grading_system_id, gs.code AS system_code, gs.name AS system_name, gs.levels_count,
                    CASE WHEN b.class_id IS NULL THEN \'term\' WHEN b.learning_area_id IS NULL THEN \'class\' ELSE \'learning_area\' END AS binding_scope,
                    b.status, b.created_at
             FROM academic_year_term_grading_bindings b
             JOIN grading_systems gs ON gs.id = b.grading_system_id
             LEFT JOIN ' . ReadReplicaService::masterRef('classes') . ' c ON c.id = b.class_id
             LEFT JOIN ' . ReadReplicaService::masterRef('learning_areas') . ' la ON la.id = b.learning_area_id
             WHERE b.academic_year_term_id = ?
             ORDER BY b.class_id IS NULL DESC, b.learning_area_id IS NULL DESC, c.name, la.name'
        );
        $stmt->execute([$termId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
