<?php

declare(strict_types=1);

namespace App\API\Services;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Data-driven assessment policy service.
 *
 * Reads the ENTIRE external assessment policy (KNEC / KICD / MoE circulars,
 * cohort dispositions, subject inventories, deadlines, national windows,
 * exam-day rules and portfolio guidance) from the assessment policy registry
 * tables — never from PHP constants. When KNEC issues the 2027 circular this
 * stays untouched: a new document row (plus its rules) supersedes the old one.
 *
 * PHP and Python share this data two ways:
 *   1. the same MySQL registry tables, and
 *   2. the JSON buffer at storage/buffers/assessment_policy_buffer.json that
 *      this service writes — the Python platform reads the same buffer file
 *      for grounded context without a second query storm.
 *
 * Deterministic-first: no provider, no DB writes, only governed reads.
 */
final class AssessmentPolicyService
{
    private PDO $db;
    private ?array $dataset;
    private string $bufferPath;
    private int $ttlSeconds;

    public function __construct(PDO $db, ?array $dataset = null, ?string $bufferPath = null, int $ttlSeconds = 300)
    {
        $this->db = $db;
        $this->dataset = $dataset;
        $this->bufferPath = $bufferPath ?? dirname(__DIR__, 2) . '/storage/buffers/assessment_policy_buffer.json';
        $this->ttlSeconds = max(0, $ttlSeconds);
    }

    // ── Dataset loading (DB -> memory, with the shared JSON buffer) ────────

    /**
     * Load the full policy dataset from the registry (or the injected test
     * dataset), through the shared JSON buffer when it is fresh.
     */
    public function dataset(): array
    {
        if ($this->dataset !== null) {
            return $this->dataset;
        }
        $buffered = $this->readBuffer();
        if ($buffered !== null) {
            return $buffered;
        }
        $data = $this->queryDataset();
        $this->writeBuffer($data);
        return $data;
    }

    private function readBuffer(): ?array
    {
        try {
            if (!is_file($this->bufferPath)) {
                return null;
            }
            $raw = @file_get_contents($this->bufferPath);
            if (!is_string($raw) || $raw === '') {
                return null;
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded) || !isset($decoded['fetched_at'], $decoded['ttl'], $decoded['policy'])) {
                return null;
            }
            if ((time() - (int) $decoded['fetched_at']) > (int) $decoded['ttl']) {
                return null;
            }
            return $decoded['policy'];
        } catch (Throwable) {
            return null;
        }
    }

    private function writeBuffer(array $policy): void
    {
        try {
            $dir = dirname($this->bufferPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $payload = json_encode(['fetched_at' => time(), 'ttl' => $this->ttlSeconds, 'policy' => $policy], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (is_string($payload)) {
                @file_put_contents($this->bufferPath, $payload, LOCK_EX);
            }
        } catch (Throwable) {
            // The buffer is an optimisation, never a dependency.
        }
    }

    /** Drop the shared buffer so the next read re-queries the registry. */
    public function invalidateBuffer(): void
    {
        if (is_file($this->bufferPath)) {
            @unlink($this->bufferPath);
        }
    }

    private function rows(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function queryDataset(): array
    {
        $authorities = $this->rows(
            'SELECT id, code, name, description, status FROM assessment_authorities WHERE status=\'active\' ORDER BY id'
        );
        $documents = $this->rows(
            'SELECT d.id, a.code AS authority_code, d.doc_code, d.doc_type, d.title, d.issued_on, d.issued_by,
                    d.portal_url, d.effective_from, d.effective_to, d.academic_year, d.payload_json, d.status
             FROM ' . ReadReplicaService::qualifiedRef('assessment_policy_documents') . ' d JOIN assessment_authorities a ON a.id = d.authority_id
             WHERE d.status = \'active\' ORDER BY d.issued_on'
        );
        foreach ($documents as &$document) {
            $document['payload'] = $this->json($document['payload_json']);
            unset($document['payload_json']);
        }
        unset($document);

        $instruments = $this->rows(
            'SELECT id, code, name, category, grade_scope_json, prepared_by, administered_by, scored_by,
                    scores_destination, weighting_json, banks_forward_json, purpose, placement_effect,
                    evidence_location, pathways_json, structure_note, status
             FROM assessment_policy_instruments WHERE status = \'active\' ORDER BY id'
        );
        foreach ($instruments as &$instrument) {
            $instrument['grade_scope'] = $this->json($instrument['grade_scope_json']);
            $instrument['weighting'] = $this->json($instrument['weighting_json']);
            $instrument['banks_forward'] = $this->json($instrument['banks_forward_json']);
            $instrument['pathways'] = $this->json($instrument['pathways_json']);
            unset($instrument['grade_scope_json'], $instrument['weighting_json'], $instrument['banks_forward_json'], $instrument['pathways_json']);
        }
        unset($instrument);

        $cohorts = $this->rows(
            'SELECT cr.grade_label, cr.disposition, cr.prepared_for, cr.reason, i.code AS instrument_code
             FROM assessment_policy_cohort_rules cr
             JOIN ' . ReadReplicaService::qualifiedRef('assessment_policy_documents') . ' d ON d.id = cr.document_id AND d.status = \'active\'
             LEFT JOIN assessment_policy_instruments i ON i.id = cr.instrument_id
             ORDER BY cr.id'
        );
        $subjects = $this->rows(
            'SELECT i.code AS instrument_code, sr.grade_label, sr.subjects_json
             FROM assessment_policy_subject_rules sr
             JOIN ' . ReadReplicaService::qualifiedRef('assessment_policy_documents') . ' d ON d.id = sr.document_id AND d.status = \'active\'
             JOIN assessment_policy_instruments i ON i.id = sr.instrument_id
             ORDER BY sr.id'
        );
        foreach ($subjects as &$subjectRule) {
            $subjectRule['subjects'] = $this->json($subjectRule['subjects_json']);
            unset($subjectRule['subjects_json']);
        }
        unset($subjectRule);

        $schedules = $this->rows(
            'SELECT d.doc_code, s.rule_key, s.rule_value, s.applies_to_term, s.note
             FROM ' . ReadReplicaService::qualifiedRef('assessment_policy_schedule_rules') . ' s
             JOIN assessment_policy_documents d ON d.id = s.document_id AND d.status = \'active\'
             ORDER BY s.id'
        );
        $rules = $this->rows(
            'SELECT r.rule_key, r.rule_label, r.rule_value_json, r.category, r.owner, i.code AS instrument_code
             FROM assessment_policy_rules r
             LEFT JOIN ' . ReadReplicaService::qualifiedRef('assessment_policy_documents') . ' d ON d.id = r.document_id AND d.status = \'active\'
             LEFT JOIN assessment_policy_instruments i ON i.id = r.instrument_id
             WHERE r.status = \'active\' ORDER BY r.id'
        );
        foreach ($rules as &$rule) {
            $rule['value'] = $this->json($rule['rule_value_json']);
            unset($rule['rule_value_json']);
        }
        unset($rule);

        $windows = $this->rows(
            'SELECT i.code AS instrument_code, w.academic_year, w.reference, w.rehearsal_on,
                    w.papers_start, w.papers_end, w.days, w.variants_json, w.projects_json, w.status
             FROM ' . ReadReplicaService::masterRef('national_assessment_windows') . ' w
             JOIN assessment_policy_instruments i ON i.id = w.instrument_id
             ORDER BY w.academic_year DESC, i.code'
        );
        foreach ($windows as &$window) {
            $window['variants'] = $this->json($window['variants_json']);
            $window['projects'] = $this->json($window['projects_json']);
            unset($window['variants_json'], $window['projects_json']);
        }
        unset($window);

        return [
            'authorities' => $authorities,
            'documents' => $documents,
            'instruments' => $instruments,
            'cohorts' => $cohorts,
            'subject_rules' => $subjects,
            'schedules' => $schedules,
            'rules' => $rules,
            'windows' => $windows,
        ];
    }

    private function json(?string $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    // ── Lookups ────────────────────────────────────────────────────────────

    /** Normalise any stored grade label ("Grade 6", "GRADE6", "6") to "6". */
    public function normaliseGrade(string $grade): string
    {
        $grade = trim($grade);
        if ($grade === '') {
            return '';
        }
        if (preg_match('/(\d+)/', $grade, $matches) === 1) {
            return (string) (int) $matches[1];
        }
        return $grade;
    }

    private function scheduleValue(string $key, ?string $docType = 'circular'): ?string
    {
        foreach ($this->dataset()['schedules'] as $schedule) {
            if ($schedule['rule_key'] === $key && ($docType === null || $this->docType($schedule['doc_code']) === $docType)) {
                return (string) $schedule['rule_value'];
            }
        }
        return null;
    }

    private function docType(string $docCode): ?string
    {
        foreach ($this->dataset()['documents'] as $document) {
            if ($document['doc_code'] === $docCode) {
                return $document['doc_type'];
            }
        }
        return null;
    }

    private function dispositionFor(string $grade): ?array
    {
        $normalised = $this->normaliseGrade($grade);
        foreach ($this->dataset()['cohorts'] as $cohort) {
            if ($this->normaliseGrade((string) $cohort['grade_label']) === $normalised) {
                return $cohort;
            }
        }
        return null;
    }

    private function rulesInCategory(string $category): array
    {
        return array_values(array_filter($this->dataset()['rules'], fn (array $rule): bool => ($rule['category'] ?? '') === $category));
    }

    private function instrumentByCode(string $code): ?array
    {
        foreach ($this->dataset()['instruments'] as $instrument) {
            if ($instrument['code'] === strtoupper(trim($code))) {
                return $instrument;
            }
        }
        return null;
    }

    /** The active SBA circular document (first active KNEC circular). */
    public function circularDocument(): ?array
    {
        foreach ($this->dataset()['documents'] as $document) {
            if (($document['authority_code'] ?? '') === 'KNEC' && ($document['doc_type'] ?? '') === 'circular') {
                return $document;
            }
        }
        return null;
    }

    // ── Public policy reads ───────────────────────────────────────────────

    public function cycle(): array
    {
        $document = $this->circularDocument();
        $cycle = $document['payload']['cycle'] ?? [];
        $shaped = [];
        foreach ($cycle as $phase) {
            $shaped[] = [
                'term' => (int) ($phase['term'] ?? 0),
                'phase' => (string) ($phase['phase'] ?? ''),
                'scores_uploaded_to_knec' => (bool) ($phase['scores_uploaded_to_knec'] ?? false),
                'portfolio_evidence' => (bool) ($phase['portfolio_evidence'] ?? false),
            ];
        }
        return $shaped;
    }

    public function isUploadTerm(int $termNumber): bool
    {
        foreach ($this->cycle() as $phase) {
            if ($phase['term'] === $termNumber) {
                return $phase['scores_uploaded_to_knec'];
            }
        }
        return false;
    }

    public function assessedGrades(): array
    {
        $grades = [];
        foreach ($this->dataset()['cohorts'] as $cohort) {
            if (($cohort['disposition'] ?? '') === 'assessed') {
                $grades[] = $this->normaliseGrade((string) $cohort['grade_label']);
            }
        }
        return array_values(array_unique($grades));
    }

    public function exemptGrades(): array
    {
        $exemptions = [];
        foreach ($this->dataset()['cohorts'] as $cohort) {
            if (($cohort['disposition'] ?? '') === 'exempt') {
                $exemptions[$this->normaliseGrade((string) $cohort['grade_label'])] = $cohort['prepared_for'] ?? null;
            }
        }
        return $exemptions;
    }

    public function deadlineStatus(?DateTimeImmutable $today = null): array
    {
        $today = $today ?? new DateTimeImmutable('today');
        $deadline = $this->scheduleValue('upload_deadline');
        $portalOpen = $this->scheduleValue('portal_open');
        $close = $this->scheduleValue('schools_close');
        if (!$deadline) {
            return ['deadline' => null, 'state' => 'unknown'];
        }
        $deadlineDate = new DateTimeImmutable($deadline);
        $days = (int) $today->diff($deadlineDate)->format('%r%a');
        $state = match (true) {
            $today > $deadlineDate => 'closed',
            $days === 0 => 'due_today',
            $days <= 7 => 'critical',
            $days <= 21 => 'upcoming',
            default => 'ahead',
        };
        return [
            'deadline' => $deadline,
            'portal_opened_on' => $portalOpen,
            'schools_close_on' => $close,
            'as_of' => $today->format('Y-m-d'),
            'days_remaining' => $days,
            'state' => $state,
        ];
    }

    public function nationalWindow(string $code): ?array
    {
        foreach ($this->dataset()['windows'] as $window) {
            if (($window['instrument_code'] ?? '') === strtoupper(trim($code))) {
                return $window;
            }
        }
        return null;
    }

    public function taxonomy(): array
    {
        return $this->dataset()['instruments'];
    }

    public function compositeFor(string $code): ?array
    {
        $instrument = $this->instrumentByCode($code);
        if (!$instrument) {
            return null;
        }
        return [
            'instrument' => $instrument['code'],
            'name' => $instrument['name'],
            'weighting' => $instrument['weighting'],
            'banks_forward' => $instrument['banks_forward'],
            'pathways' => $instrument['pathways'],
        ];
    }

    public function adminRequirements(): array
    {
        return array_map(fn (array $rule): array => [
            'key' => $rule['rule_key'],
            'title' => $rule['rule_label'],
            'detail' => $rule['value']['detail'] ?? '',
            'owner' => $rule['owner'],
        ], $this->rulesInCategory('admin'));
    }

    public function nationalRules(): array
    {
        return array_map(fn (array $rule): array => [
            'key' => $rule['rule_key'],
            'rule' => $rule['value']['detail'] ?? ($rule['value']['value'] ?? ($rule['value']['ratio'] ?? ($rule['value']['detail'] ?? ''))),
            'owner' => $rule['owner'],
        ], $this->rulesInCategory('exam_day') + $this->rulesInCategory('integrity') + $this->rulesInCategory('legal'));
    }

    public function portfolioBlocks(): array
    {
        $rules = $this->rulesInCategory('portfolio');
        $components = [];
        $steps = [];
        $evidenceSources = null;
        foreach ($rules as $rule) {
            $key = (string) $rule['rule_key'];
            if (str_starts_with($key, 'portfolio_component:')) {
                $components[] = ['key' => substr($key, strlen('portfolio_component:')), 'title' => $rule['rule_label'], 'detail' => $rule['value']['detail'] ?? ''];
            } elseif (str_starts_with($key, 'portfolio_step:')) {
                $steps[] = ['step' => (int) substr($key, strlen('portfolio_step:')), 'title' => $rule['rule_label'], 'detail' => $rule['value']['detail'] ?? ''];
            } elseif ($key === 'evidence_sources') {
                $evidenceSources = $rule['value']['sources'] ?? [];
            }
        }
        usort($steps, fn (array $a, array $b): int => $a['step'] <=> $b['step']);
        return ['components' => $components, 'build_steps' => $steps, 'evidence_sources' => $evidenceSources];
    }

    // ── The gate the exams workflow enforces ──────────────────────────────

    /**
     * Validate a proposed school-based SBA period against the ACTIVE registry
     * rows. Violations block; advisories surface. All wording comes from the
     * database, so a superseding circular changes behaviour without a deploy.
     *
     * @param array<int,string> $classGrades
     * @return array{violations:array<int,array>,advisories:array<int,array>,applicable:bool}
     */
    public function validateExamPeriod(array $classGrades, string $assessmentKind, int $termNumber, ?string $authority = null): array
    {
        $violations = [];
        $advisories = [];
        $applicable = strtolower(trim($assessmentKind)) === 'school_based'
            && (trim((string) $authority) === '' || strtoupper(trim((string) $authority)) === 'KNEC');
        if (!$applicable) {
            return ['violations' => [], 'advisories' => [], 'applicable' => false];
        }

        $uploadTerm = $this->isUploadTerm($termNumber);
        $deadline = $this->scheduleValue('upload_deadline');
        $portal = $this->scheduleValue('portal_url');
        $document = $this->circularDocument();

        foreach ($classGrades as $grade) {
            $label = trim($grade) === '' ? 'An unselected class' : trim($grade);
            $cohort = $this->dispositionFor($grade);
            $normalised = $this->normaliseGrade($grade);

            if ($cohort !== null && ($cohort['disposition'] ?? '') === 'exempt') {
                $violations[] = [
                    'code' => 'exempt_cohort',
                    'grade' => $normalised,
                    'class_label' => $label,
                    'message' => sprintf(
                        '%s is exempt from the Term 3 School Based Assessment. KNEC releases this cohort to prepare for %s, so no SBA score may be recorded or uploaded for it.',
                        $label,
                        (string) ($cohort['prepared_for'] ?? 'its national assessment')
                    ),
                    'circular_reference' => $document['doc_code'] ?? null,
                    'prepared_for' => $cohort['prepared_for'] ?? null,
                    'reason' => $cohort['reason'] ?? null,
                ];
                continue;
            }

            if ($cohort === null || ($cohort['disposition'] ?? '') === 'out_of_scope') {
                $advisories[] = [
                    'code' => 'out_of_scope_cohort',
                    'grade' => $normalised,
                    'class_label' => $label,
                    'message' => sprintf('%s is not among the 2026 SBA cohorts (Grades 3, 4, 5, 7 and 8). Confirm this period is school-internal and must not be uploaded to the KNEC portal.', $label),
                    'circular_reference' => $document['doc_code'] ?? null,
                ];
                continue;
            }

            if ($uploadTerm) {
                $advisories[] = [
                    'code' => 'knec_upload_due',
                    'grade' => $normalised,
                    'class_label' => $label,
                    'message' => sprintf('%s sits the 2026 School Based Assessment. Scores must be uploaded to %s by %s.', $label, (string) $portal, (string) $deadline),
                    'circular_reference' => $document['doc_code'] ?? null,
                    'upload_deadline' => $deadline,
                ];
            }
        }

        return ['violations' => $violations, 'advisories' => $advisories, 'applicable' => true];
    }

    /** School-based SBA periods record KNEC as their assessment authority. */
    public function resolveAuthority(string $assessmentKind, ?string $authority, int $termNumber): ?string
    {
        $authority = trim((string) $authority) ?: null;
        if (strtolower(trim($assessmentKind)) !== 'school_based') {
            return $authority;
        }
        return 'KNEC';
    }

    /** National window advisory: does the period cover the official papers? */
    public function validateNationalWindow(string $nationalCode, string $startsOn, string $endsOn): array
    {
        $window = $this->nationalWindow($nationalCode);
        if (!$window) {
            return [];
        }
        $advisories = [];
        if ($startsOn > (string) $window['papers_start']) {
            $advisories[] = ['code' => 'national_window_start', 'message' => sprintf('%s written papers officially start on %s, but this period starts later.', $nationalCode, $window['papers_start'])];
        }
        if ($endsOn < (string) $window['papers_end']) {
            $advisories[] = ['code' => 'national_window_end', 'message' => sprintf('%s written papers run to %s, but this period ends earlier.', $nationalCode, $window['papers_end'])];
        }
        return $advisories;
    }

    /** Full policy payload for the UI, the Python platform and the buffer. */
    public function summary(): array
    {
        $document = $this->circularDocument();
        return [
            'circular' => $document,
            'cycle' => $this->cycle(),
            'assessed_grades' => $this->assessedGrades(),
            'exempt_grades' => $this->exemptGrades(),
            'taxonomy' => $this->taxonomy(),
            'subjects' => $this->dataset()['subject_rules'],
            'deadline' => $this->deadlineStatus(),
            'admin_requirements' => $this->adminRequirements(),
            'national_windows' => $this->dataset()['windows'],
            'national_rules' => $this->nationalRules(),
            'portfolio' => $this->portfolioBlocks(),
        ];
    }
}
