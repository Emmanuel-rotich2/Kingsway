<?php
declare(strict_types=1);

namespace App\API\Services;

use App\API\Services\ReadReplicaService;
use PDO;
use RuntimeException;

/**
 * Authoritative summative score-entry and moderation service.
 *
 * It binds every write to the published exam's assessment, exact class-stream
 * enrollment, authenticated teacher scope, active grading scale and lifecycle
 * state. Browser-supplied grades, enrollment ids and marker ids are ignored.
 */
final class AssessmentResultsService
{
    private PDO $db;
    private int $userId;
    private ?int $staffId = null;
    private ?array $roleNames = null;
    private ?bool $classReviewSchema = null;
    private CbcGradingService $grading;

    public function __construct(PDO $db, int $userId)
    {
        $this->db = $db;
        $this->userId = $userId;
        $this->grading = new CbcGradingService($db);
    }

    public function examEntryContext(int $examScheduleId): array
    {
        $exam = $this->exam($examScheduleId);
        $this->assertCanAccessAssessment($exam);
        $this->assertPeriodAllowsEntry((int) $exam['assessment_id']);

        $roster = $this->roster((int) $exam['academic_year_class_stream_id'], (int) $exam['assessment_id']);
        return [
            'exam' => $exam,
            'students' => $roster,
            'editable' => $exam['assessment_status'] === 'pending_submission',
            'can_submit' => $exam['assessment_status'] === 'pending_submission' && count($roster) > 0,
        ];
    }

    public function save(int $assessmentId, array $rows, bool $submit, string $reason = ''): array
    {
        if (!$rows) {
            throw new RuntimeException('At least one learner result is required', 422);
        }

        $this->db->beginTransaction();
        try {
            $assessment = $this->assessment($assessmentId, true);
            $this->assertCanAccessAssessment($assessment);
            $this->assertPeriodAllowsEntry($assessmentId);
            if ($assessment['assessment_status'] !== 'pending_submission') {
                throw new RuntimeException('Submitted or approved results are locked; moderation must reopen them', 409);
            }

            $roster = $this->rosterMap((int) $assessment['academic_year_class_stream_id']);
            if (!$roster) {
                throw new RuntimeException('No active learner enrollments exist for this class stream', 409);
            }

            $normalized = [];
            foreach ($rows as $row) {
                $studentId = (int) ($row['student_id'] ?? 0);
                if (!$studentId || !isset($roster[$studentId])) {
                    throw new RuntimeException('A supplied learner is not enrolled in this exam class stream', 422);
                }
                if (isset($normalized[$studentId])) {
                    throw new RuntimeException('A learner appears more than once in the submitted marks', 422);
                }

                $entryStatus = strtolower(trim((string) ($row['entry_status'] ?? 'present')));
                if (!in_array($entryStatus, ['present', 'absent', 'exempted'], true)) {
                    throw new RuntimeException('Invalid learner examination status', 422);
                }

                $score = null;
                $grade = null;
                if ($entryStatus === 'present') {
                    $raw = $row['marks_obtained'] ?? $row['score_obtained'] ?? $row['score'] ?? null;
                    if ($raw === null || $raw === '' || !is_numeric($raw)) {
                        throw new RuntimeException('Every present learner requires numeric marks', 422);
                    }
                    $score = (float) $raw;
                    $grade = $this->grading->gradeForSystem($score, (float) $assessment['max_marks'], $this->gradingSystemFor((int) $assessment['id']));
                }

                $normalized[$studentId] = [
                    'student_id' => $studentId,
                    'enrollment_id' => $roster[$studentId],
                    'entry_status' => $entryStatus,
                    'score' => $score,
                    'grade' => $grade,
                    'remarks' => trim((string) ($row['remarks'] ?? '')),
                ];
            }

            if ($submit) {
                $missing = array_diff(array_keys($roster), array_keys($normalized));
                if ($missing) {
                    throw new RuntimeException(count($missing) . ' learner(s) still require marks, absent, or exempted status', 422);
                }
            }

            $upsert = $this->db->prepare(
                "INSERT INTO assessment_results
                    (assessment_id, student_academic_enrollment_id, marks_obtained, entry_status,
                     grade, points, remarks, moderation_note, submitted_at, is_submitted,
                     is_approved, responder_type, responder_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, 0, 'teacher', ?)
                 ON DUPLICATE KEY UPDATE
                    marks_obtained = VALUES(marks_obtained),
                    entry_status = VALUES(entry_status),
                    grade = VALUES(grade),
                    points = VALUES(points),
                    remarks = VALUES(remarks),
                    moderation_note = NULL,
                    submitted_at = VALUES(submitted_at),
                    is_submitted = VALUES(is_submitted),
                    is_approved = 0,
                    responder_type = 'teacher',
                    responder_id = VALUES(responder_id),
                    deleted_at = NULL,
                    deleted_by = NULL"
            );
            $existingStmt = $this->db->prepare(
                'SELECT * FROM assessment_results WHERE assessment_id = ? AND student_academic_enrollment_id = ? AND deleted_at IS NULL LIMIT 1'
            );

            $saved = 0;
            foreach ($normalized as $item) {
                $existingStmt->execute([$assessmentId, $item['enrollment_id']]);
                $old = $existingStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($old && (int) $old['is_approved'] === 1) {
                    throw new RuntimeException('An approved learner result cannot be overwritten', 409);
                }

                $grade = $item['grade'];
                $submittedAt = $submit ? date('Y-m-d H:i:s') : null;
                $upsert->execute([
                    $assessmentId,
                    $item['enrollment_id'],
                    $item['score'],
                    $item['entry_status'],
                    $grade['grade_code'] ?? null,
                    $grade['points'] ?? null,
                    $item['remarks'],
                    $submittedAt,
                    $submit ? 1 : 0,
                    $this->staffId(),
                ]);

                $resultId = $old ? (int) $old['id'] : (int) $this->db->lastInsertId();
                $newValues = [
                    'marks_obtained' => $item['score'],
                    'entry_status' => $item['entry_status'],
                    'grade' => $grade['grade_code'] ?? null,
                    'points' => $grade['points'] ?? null,
                    'remarks' => $item['remarks'],
                    'is_submitted' => $submit,
                ];
                $this->recordEvent(
                    $assessmentId,
                    $resultId,
                    $item['enrollment_id'],
                    $submit ? 'submitted' : ($old ? 'updated' : 'created'),
                    $old,
                    $newValues,
                    $reason
                );
                $saved++;
            }

            $status = $submit ? 'submitted' : 'pending_submission';
            $reviewRequired = $this->classTeacherReviewRequired((int)$assessment['academic_year_class_stream_id']);
            if($submit && $reviewRequired && !$this->hasClassReviewSchema())throw new RuntimeException('Class-teacher review needs the summative-assessment migration before Grade 4–9 results can be submitted.',409);
            if($this->hasClassReviewSchema()){
                $stmt = $this->db->prepare('UPDATE assessments SET status = ?, submitted_by = ?, submitted_at = ?, class_review_status = ?, class_reviewed_by = NULL, class_reviewed_at = NULL, class_review_note = NULL WHERE id = ?');
                $stmt->execute([$status,$submit ? $this->staffId() : null,$submit ? date('Y-m-d H:i:s') : null,$submit && $reviewRequired ? 'pending' : 'not_required',$assessmentId]);
            }else{
                $stmt=$this->db->prepare('UPDATE assessments SET status=?,submitted_by=?,submitted_at=? WHERE id=?');
                $stmt->execute([$status,$submit ? $this->staffId() : null,$submit ? date('Y-m-d H:i:s') : null,$assessmentId]);
            }
            if ($submit) (new ExamPeriodService($this->db, $this->userId))->markSubmitted($assessmentId);

            $this->db->commit();
            return [
                'assessment_id' => $assessmentId,
                'saved_count' => $saved,
                'roster_count' => count($roster),
                'status' => $status,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /** Convert an explicit staff-mapped document preview into one validated score register. */
    public function saveImported(int $assessmentId, array $documentRows, array $columnMap, bool $submit): array
    {
        $assessment=$this->assessment($assessmentId,false);
        $this->assertCanAccessAssessment($assessment);
        $this->assertPeriodAllowsEntry($assessmentId);
        $required=['admission_no','marks'];
        foreach($required as $key){if(!isset($columnMap[$key])||!is_numeric($columnMap[$key])||(int)$columnMap[$key]<0)throw new RuntimeException('Map an admission-number column and a marks column before importing.',422);}
        $indexes=[];
        foreach(['admission_no','marks','entry_status','remarks'] as $key){
            if(isset($columnMap[$key])){
                if(!is_numeric($columnMap[$key])||(int)$columnMap[$key]<0)throw new RuntimeException('A mapped document column is invalid.',422);
                $indexes[$key]=(int)$columnMap[$key];
            }
        }
        $roster=$this->roster((int)$assessment['academic_year_class_stream_id'],$assessmentId);
        $byAdmission=[];
        foreach($roster as $learner){$key=strtoupper(trim((string)$learner['admission_no']));if($key!=='')$byAdmission[$key]=(int)$learner['student_id'];}
        $rows=[];
        foreach(array_slice($documentRows,0,5000) as $documentRow){
            if(!is_array($documentRow))continue;
            $admission=strtoupper(trim((string)($documentRow[$indexes['admission_no']]??'')));
            if($admission==='')continue;
            if(!isset($byAdmission[$admission]))throw new RuntimeException('The file contains an admission number that is not enrolled in this exam register: '.$admission,422);
            $rawMarks=trim((string)($documentRow[$indexes['marks']]??''));
            $status=isset($indexes['entry_status'])?strtolower(trim((string)($documentRow[$indexes['entry_status']]??''))):'present';
            $statusMap=['present'=>'present','absent'=>'absent','a'=>'absent','exempted'=>'exempted','exempt'=>'exempted'];
            if($status===''&&$rawMarks!=='')$status='present';
            if(!isset($statusMap[$status]))throw new RuntimeException('The file contains an unsupported learner status: '.$status,422);
            if($statusMap[$status]==='present'&&$rawMarks==='' )continue;
            $rows[]=[
                'student_id'=>$byAdmission[$admission],
                'entry_status'=>$statusMap[$status],
                'marks_obtained'=>$rawMarks,
                'remarks'=>isset($indexes['remarks'])?trim((string)($documentRow[$indexes['remarks']]??'')): '',
            ];
        }
        if(!$rows)throw new RuntimeException('No learner marks matched the selected result register.',422);
        return $this->save($assessmentId,$rows,$submit,'Imported from a staff-reviewed document preview.');
    }

    public function adminUpdateResult(int $resultId, array $data): array
    {
        if (!$this->isAcademicLeader()) throw new RuntimeException('Academic leadership access is required to edit a result.', 403);
        $stmt=$this->db->prepare('SELECT *, max_marks, academic_year_class_stream_id, academic_year_term_id, learning_area_id FROM ' . ReadReplicaService::qualifiedRef("assessment_results_detailed") . ' WHERE id=? AND deleted_at IS NULL');
        $stmt->execute([$resultId]); $old=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$old) throw new RuntimeException('Result record not found.',404);
        $status=strtolower(trim((string)($data['entry_status']??$old['entry_status']??'present')));
        if(!in_array($status,['present','absent','exempted'],true)) throw new RuntimeException('Invalid result status.',422);
        $score=null;$grade=null;$points=null;
        if($status==='present'){
            $raw=$data['marks_obtained']??$old['marks_obtained'];
            if(!is_numeric($raw)||(float)$raw<0||(float)$raw>(float)$old['max_marks']) throw new RuntimeException('Marks must be between zero and the assessment maximum.',422);
            $graded=$this->grading->gradeForSystem((float)$raw,(float)$old['max_marks'],$this->gradingSystemFor((int)$old['assessment_id']));$score=(float)$raw;$grade=$graded['grade_code']??null;$points=$graded['points']??null;
        }
        $remarks=trim((string)($data['remarks']??$old['remarks']??''));
        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE assessment_results SET marks_obtained=?,entry_status=?,grade=?,points=?,remarks=? WHERE id=?')->execute([$score,$status,$grade,$points,$remarks,$resultId]);
            $this->recordEvent((int)$old['assessment_id'],$resultId,(int)$old['student_academic_enrollment_id'],'admin_updated',$old,['marks_obtained'=>$score,'entry_status'=>$status,'grade'=>$grade,'remarks'=>$remarks],(string)($data['reason']??'Administrative correction'));
            (new TermResultsService($this->db))->compute((int)$old['academic_year_class_stream_id'],(int)$old['academic_year_term_id'],(int)$old['learning_area_id']);
            $this->db->commit(); return ['id'=>$resultId,'marks_obtained'=>$score,'entry_status'=>$status,'grade'=>$grade];
        } catch(\Throwable $e) { if($this->db->inTransaction())$this->db->rollBack(); throw $e; }
    }

    /**
     * Record (or re-record over) one summative result as the academic leader.
     * Binds the write to the assessment's exact class-stream enrollment, keeps
     * browser-supplied grades out, and journals the transition.
     */
    public function adminCreateResult(array $data): array
    {
        if (!$this->isAcademicLeader()) throw new RuntimeException('Academic leadership access is required to record a result.', 403);
        $assessmentId=(int)($data['assessment_id']??0);
        $enrollmentId=(int)($data['student_academic_enrollment_id']??0);
        if(!$assessmentId||!$enrollmentId) throw new RuntimeException('Assessment and learner enrollment are required.',422);
        $status=strtolower(trim((string)($data['entry_status']??'present')));
        if(!in_array($status,['present','absent','exempted'],true)) throw new RuntimeException('Invalid result status.',422);

        // Recomputing term results inside every per-learner write means concurrent
        // mark entry contends on the same aggregate rows. InnoDB resolves that as a
        // deadlock, which it expects the application to retry, so retry the whole
        // (idempotent, ON DUPLICATE KEY UPDATE) transaction once before surfacing it.
        for ($attempt = 0; ; $attempt++) {
            try {
                return $this->recordResultRow($data, $assessmentId, $enrollmentId, $status);
            } catch (\PDOException $e) {
                if ($attempt >= 2 || !$this->isRetryableLockFailure($e)) throw $e;
                usleep(120000 * ($attempt + 1));
            }
        }
    }

    /** True for a deadlock or lock-wait timeout that a retry can resolve. */
    private function isRetryableLockFailure(\PDOException $e): bool
    {
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        return $driverCode === 1213 || $driverCode === 1205 || $sqlState === '40001';
    }

    /**
     * Batched single-learner save for the pivoted results workspace: accepts
     * ONLY the changed subject cells for one learner so one call covers the
     * whole row. Validation runs for every cell up-front (no partial apply on
     * a bad payload), updates carry an optimistic updated_at concurrency
     * guard checked for the whole batch before any write, and each returned
     * item carries the authoritative post-save values plus the new
     * updated_at token so the client can reconcile without waiting for the
     * read projection to refresh.
     */
    public function adminSaveResultBatch(array $data): array
    {
        if (!$this->isAcademicLeader()) throw new RuntimeException('Academic leadership access is required to record exam results.', 403);
        $items = array_values((array) ($data['results'] ?? []));
        $reason = trim((string) ($data['reason'] ?? 'Results workspace row save'));

        $batch = SummativeBatchValidator::validateBatch($items);
        if (!$batch['ok']) {
            throw new RuntimeException('Some subject cells failed validation: ' . implode(' ', array_map('strval', array_unique($batch['errors']))), 422);
        }

        $prepared = [];
        foreach ($items as $index => $raw) {
            $item = (array) $raw;
            $entry = [
                'index' => $index,
                'assessment_id' => (int) ($item['assessment_id'] ?? 0),
                'enrollment_id' => (int) ($item['student_academic_enrollment_id'] ?? $item['enrollment_id'] ?? 0),
                'result_id' => (int) ($item['result_id'] ?? 0),
                'marks_obtained' => $item['marks_obtained'] ?? null,
                'entry_status' => strtolower(trim((string) ($item['entry_status'] ?? 'present'))),
                'remarks' => trim((string) ($item['remarks'] ?? '')),
                'expected_updated_at' => trim((string) ($item['expected_updated_at'] ?? '')) ?: null,
            ];
            if (!$entry['assessment_id'] || !$entry['enrollment_id']) {
                throw new RuntimeException('Each changed subject cell needs its assessment and learner enrollment.', 422);
            }
            $prepared[] = $entry;
        }

        // Phase 1 - read-only conflict resolution for the whole batch. No
        // writes happen until every optimistic updated_at expectation holds,
        // so a concurrent editor can never lose marks to a partial batch.
        foreach ($prepared as $entry) {
            if (!$entry['result_id']) continue;
            $stmt = $this->db->prepare('SELECT id, updated_at FROM assessment_results WHERE id=?');
            $stmt->execute([$entry['result_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException('Result record not found.', 404);
            if (!SummativeBatchValidator::updateMatches($entry['expected_updated_at'], (string) $row['updated_at'])) {
                throw new RuntimeException('Another teacher updated one of these subject cells while you were editing. Reload the row and re-enter your change.', 409);
            }
        }

        // Phase 2 - apply each changed cell through the same reviewed paths
        // the single-result modal uses (grading, journaling, term recompute).
        $outcomes = [];
        foreach ($prepared as $entry) {
            if ($entry['result_id']) {
                $payload = ['marks_obtained' => $entry['entry_status'] === 'present' ? $entry['marks_obtained'] : null, 'entry_status' => $entry['entry_status'], 'reason' => $reason];
                if ($entry['remarks'] !== '') $payload['remarks'] = $entry['remarks'];
                $saved = $this->adminUpdateResult($entry['result_id'], $payload);
                $resultId = $entry['result_id'];
            } else {
                $saved = $this->adminCreateResult([
                    'assessment_id' => $entry['assessment_id'],
                    'student_academic_enrollment_id' => $entry['enrollment_id'],
                    'marks_obtained' => $entry['marks_obtained'],
                    'entry_status' => $entry['entry_status'],
                    'remarks' => $entry['remarks'],
                    'reason' => $reason,
                ]);
                $resultId = (int) ($saved['id'] ?? 0);
            }
            $stamp = $this->db->prepare('SELECT updated_at FROM assessment_results WHERE id=?');
            $stamp->execute([$resultId]);
            $outcomes[] = [
                'index' => $entry['index'],
                'assessment_id' => $entry['assessment_id'],
                'result_id' => $resultId,
                'marks_obtained' => $saved['marks_obtained'] ?? null,
                'entry_status' => $saved['entry_status'] ?? null,
                'grade' => $saved['grade'] ?? null,
                'updated_at' => (string) ($stamp->fetchColumn() ?: ''),
                'ok' => true,
            ];
        }
        return ['saved' => count($outcomes), 'results' => $outcomes];
    }

    // ==================== PAPER-BASED SUMMATIVE GRID ====================

    /**
     * Grid payload for one exam register: papers, learner roster (from the
     * exam_context read model), existing per-paper results, and the parent
     * total rows so the UI can show calculated state without typing it.
     */
    public function adminListPaperGrid(int $assessmentId): array
    {
        if (!$this->isAcademicLeader()) throw new RuntimeException('Academic leadership access is required to view this paper grid.', 403);
        $stmt = $this->db->prepare('SELECT id, title, max_marks, status, learning_area_id, academic_year_class_stream_id, academic_year_term_id FROM assessments WHERE id=?');
        $stmt->execute([$assessmentId]);
        $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$assessment) throw new RuntimeException('Assessment register not found.', 404);

        $papers = $this->db->prepare('SELECT id, paper_number, title, max_marks, is_active FROM assessment_papers WHERE assessment_id=? ORDER BY paper_number');
        $papers->execute([$assessmentId]);
        $paperRows = $papers->fetchAll(PDO::FETCH_ASSOC);

        $roster = $this->db->prepare(
            'SELECT DISTINCT ec.enrollment_id, ec.learner_name, ec.admission_no, ec.class_name, ec.stream_name
             FROM ' . ReadReplicaService::qualifiedRef('exam_context') . ' ec WHERE ec.assessment_id=?'
        );
        $roster->execute([$assessmentId]);
        $learners = $roster->fetchAll(PDO::FETCH_ASSOC);

        $results = $this->db->prepare(
            'SELECT r.id, r.assessment_paper_id, r.student_academic_enrollment_id, r.marks_obtained,
                    r.entry_status, r.remarks, r.updated_at
             FROM assessment_paper_results r
             JOIN assessment_papers p ON p.id = r.assessment_paper_id
             WHERE p.assessment_id=?'
        );
        $results->execute([$assessmentId]);
        $paperResults = $results->fetchAll(PDO::FETCH_ASSOC);

        $parents = $this->db->prepare(
            'SELECT id, student_academic_enrollment_id, marks_obtained, entry_status, grade, updated_at
             FROM assessment_results WHERE assessment_id=? AND deleted_at IS NULL'
        );
        $parents->execute([$assessmentId]);

        return [
            'assessment' => $assessment,
            'papers' => $paperRows,
            'learners' => $learners,
            'paper_results' => $paperResults,
            'parent_results' => $parents->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    /**
     * Replace a register's paper set. Papers are per-register so each class
     * and learning area decides its own structure (1 paper, 2 papers, ...).
     * Integrity: a paper carrying recorded results cannot be deleted or have
     * its maximum changed; the register total resyncs to the paper sum.
     */
    public function adminSavePapers(int $assessmentId, array $papers): array
    {
        if (!$this->isAcademicLeader()) throw new RuntimeException('Academic leadership access is required to manage papers.', 403);
        $check = $this->db->prepare('SELECT id FROM assessments WHERE id=?');
        $check->execute([$assessmentId]);
        if (!$check->fetchColumn()) throw new RuntimeException('Assessment register not found.', 404);

        $current = $this->db->prepare('SELECT id, paper_number, max_marks FROM assessment_papers WHERE assessment_id=?');
        $current->execute([$assessmentId]);
        $currentRows = [];
        foreach ($current->fetchAll(PDO::FETCH_ASSOC) as $row) $currentRows[(int) $row['id']] = $row;

        $kept = [];
        foreach (array_values($papers) as $paper) {
            $paper = (array) $paper;
            $paperId = (int) ($paper['id'] ?? $paper['paper_id'] ?? 0);
            $maxMarks = (float) ($paper['max_marks'] ?? 0);
            if ($maxMarks <= 0) throw new RuntimeException('Every paper needs a positive maximum mark.', 422);
            $number = (int) ($paper['paper_number'] ?? 0);
            if ($number <= 0) throw new RuntimeException('Every paper needs a paper number.', 422);
            if ($paperId > 0 && isset($currentRows[$paperId])) {
                $count = $this->db->prepare('SELECT COUNT(*) FROM assessment_paper_results WHERE assessment_paper_id=?');
                $count->execute([$paperId]);
                if ((int) $count->fetchColumn() > 0 && (float) $currentRows[$paperId]['max_marks'] !== $maxMarks) {
                    throw new RuntimeException(sprintf('Paper %d already has recorded marks; its maximum cannot change.', $number), 422);
                }
            }
            $kept[$paperId] = ['number' => $number, 'title' => trim((string) ($paper['title'] ?? '')), 'max' => $maxMarks];
        }
        // Deleting a paper that carries results would silently erase marks.
        foreach (array_keys(array_diff_key($currentRows, $kept)) as $deadId) {
            $count = $this->db->prepare('SELECT COUNT(*) FROM assessment_paper_results WHERE assessment_paper_id=?');
            $count->execute([$deadId]);
            if ((int) $count->fetchColumn() > 0) {
                throw new RuntimeException('A paper with recorded marks cannot be deleted; remove its marks first.', 422);
            }
        }

        $this->db->beginTransaction();
        try {
            if ($kept !== []) {
                $marks = implode(',', array_fill(0, count($kept), '?'));
                $this->db->prepare("DELETE FROM assessment_papers WHERE assessment_id=? AND id NOT IN ($marks)")
                    ->execute(array_merge([$assessmentId], array_keys($kept)));
            } else {
                $this->db->prepare('DELETE FROM assessment_papers WHERE assessment_id=?')->execute([$assessmentId]);
            }
            $maxSum = 0.0;
            foreach ($kept as $paperId => $spec) {
                $this->db->prepare(
                    'INSERT INTO assessment_papers (id, assessment_id, paper_number, title, max_marks)
                     VALUES (?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE paper_number=VALUES(paper_number), title=VALUES(title), max_marks=VALUES(max_marks)'
                )->execute([$paperId ?: null, $assessmentId, $spec['number'], $spec['title'], $spec['max']]);
                $maxSum += $spec['max'];
            }
            // The register's out-of stays the paper sum so grading, registers,
            // and report cards see one consistent maximum.
            if ($kept !== []) {
                $this->db->prepare('UPDATE assessments SET max_marks=? WHERE id=?')->execute([$maxSum, $assessmentId]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        return $this->adminListPaperGrid($assessmentId);
    }

    /**
     * Batched per-learner paper-marks save: accepts only the changed paper
     * cells for one learner, validates each against its own paper maximum,
     * checks optimistic updated_at expectations for the whole batch before
     * writing, and re-materializes the register total once every active
     * paper is settled (absent anywhere withholds the total).
     */
    public function adminSavePaperResultBatch(array $data): array
    {
        if (!$this->isAcademicLeader()) throw new RuntimeException('Academic leadership access is required to record paper marks.', 403);
        $items = array_values((array) ($data['results'] ?? []));
        $reason = trim((string) ($data['reason'] ?? 'Paper marks entry'));

        $papersById = [];
        $assessmentId = 0;
        foreach ($items as $raw) {
            $item = (array) $raw;
            $paperId = (int) ($item['paper_id'] ?? 0);
            if (!$paperId) throw new RuntimeException('Each changed paper cell needs its paper id.', 422);
            if (!$papersById) {
                $find = $this->db->prepare('SELECT p.id, p.assessment_id, p.max_marks, p.paper_number FROM assessment_papers p WHERE p.id=?');
            } else {
                $find = $this->db->prepare('SELECT p.id, p.assessment_id, p.max_marks, p.paper_number FROM assessment_papers p WHERE p.id=? AND p.assessment_id=?');
            }
            $find->execute($papersById ? [$paperId, $assessmentId] : [$paperId]);
            $paper = $find->fetch(PDO::FETCH_ASSOC);
            if (!$paper) throw new RuntimeException('Unknown paper for this register.', 404);
            $assessmentId = (int) $paper['assessment_id'];
            $papersById[$paperId] = $paper;
        }
        if (!$items) throw new RuntimeException('At least one changed paper cell is required.', 422);

        $enrollmentId = (int) (($items[0]['student_academic_enrollment_id'] ?? $items[0]['enrollment_id'] ?? 0));
        foreach ($items as $raw) {
            $item = (array) $raw;
            $enrollment = (int) ($item['student_academic_enrollment_id'] ?? $item['enrollment_id'] ?? 0);
            if ($enrollment !== $enrollmentId) throw new RuntimeException('One save covers the papers of a single learner.', 422);
        }

        // Validation up-front against each paper's own maximum.
        $validated = [];
        foreach (array_values($items) as $index => $raw) {
            $item = (array) $raw;
            $paperId = (int) $item['paper_id'];
            $outcome = SummativeBatchValidator::validateItem([
                'marks_obtained' => $item['marks_obtained'] ?? null,
                'entry_status' => $item['entry_status'] ?? 'present',
                'max_marks' => (float) $papersById[$paperId]['max_marks'],
            ]);
            if (!$outcome['ok']) {
                throw new RuntimeException("Paper {$papersById[$paperId]['paper_number']}: " . implode(' ', $outcome['errors']), 422);
            }
            $validated[] = [
                'paper_id' => $paperId,
                'enrollment_id' => $enrollmentId,
                'marks_obtained' => $outcome['value'],
                'entry_status' => strtolower(trim((string) ($item['entry_status'] ?? 'present'))),
                'remarks' => trim((string) ($item['remarks'] ?? '')),
                'expected_updated_at' => trim((string) ($item['expected_updated_at'] ?? '')) ?: null,
                'index' => $index,
            ];
        }

        // Concurrency pre-check for existing paper rows.
        foreach ($validated as $entry) {
            $stmt = $this->db->prepare('SELECT id, updated_at FROM assessment_paper_results WHERE assessment_paper_id=? AND student_academic_enrollment_id=?');
            $stmt->execute([$entry['paper_id'], $entry['enrollment_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !SummativeBatchValidator::updateMatches($entry['expected_updated_at'], (string) $row['updated_at'])) {
                throw new RuntimeException('Another teacher updated one of these paper marks while you were editing. Reload the row and re-enter your change.', 409);
            }
        }

        $outcomes = [];
        $this->db->beginTransaction();
        try {
            foreach ($validated as $entry) {
                $this->db->prepare(
                    'INSERT INTO assessment_paper_results
                        (assessment_paper_id, student_academic_enrollment_id, marks_obtained, entry_status, remarks, entered_by)
                     VALUES (?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE marks_obtained=VALUES(marks_obtained), entry_status=VALUES(entry_status),
                        remarks=VALUES(remarks), entered_by=VALUES(entered_by), updated_at=NOW()'
                )->execute([$entry['paper_id'], $entry['enrollment_id'], $entry['marks_obtained'], $entry['entry_status'], $entry['remarks'] ?: null, $this->userId ?: null]);
                $stamp = $this->db->prepare('SELECT id, updated_at FROM assessment_paper_results WHERE assessment_paper_id=? AND student_academic_enrollment_id=?');
                $stamp->execute([$entry['paper_id'], $entry['enrollment_id']]);
                $saved = $stamp->fetch(PDO::FETCH_ASSOC);
                $outcomes[] = [
                    'index' => $entry['index'],
                    'paper_id' => $entry['paper_id'],
                    'result_id' => (int) $saved['id'],
                    'marks_obtained' => $entry['marks_obtained'],
                    'entry_status' => $entry['entry_status'],
                    'updated_at' => (string) $saved['updated_at'],
                    'ok' => true,
                ];
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }

        $rollup = $this->materializeParentTotal($assessmentId, $enrollmentId, $reason);
        return ['saved' => count($outcomes), 'results' => $outcomes, 'parent' => $rollup];
    }

    /**
     * Recompute the register total for one learner from their paper rows.
     * The parent assessment_results row is only touched when every active
     * paper is settled, mirroring the classroom rule that a total is
     * calculated, never typed, and withheld when a paper is missed.
     */
    private function materializeParentTotal(int $assessmentId, int $enrollmentId, string $reason): ?array
    {
        $papersStmt = $this->db->prepare('SELECT id, max_marks, is_active, paper_number FROM assessment_papers WHERE assessment_id=?');
        $papersStmt->execute([$assessmentId]);
        $papers = $papersStmt->fetchAll(PDO::FETCH_ASSOC);
        $resultsStmt = $this->db->prepare(
            'SELECT assessment_paper_id, marks_obtained, entry_status FROM assessment_paper_results
             WHERE student_academic_enrollment_id=? AND assessment_paper_id IN (SELECT id FROM assessment_papers WHERE assessment_id=?)'
        );
        $resultsStmt->execute([$enrollmentId, $assessmentId]);
        $rollup = SummativeBatchValidator::computePaperRollup($papers, $resultsStmt->fetchAll(PDO::FETCH_ASSOC));
        if (!$rollup['complete']) return $rollup;

        $assessment = $this->db->prepare('SELECT id, max_marks, academic_year_class_stream_id, academic_year_term_id, learning_area_id FROM assessments WHERE id=?');
        $assessment->execute([$assessmentId]);
        $meta = $assessment->fetch(PDO::FETCH_ASSOC);
        if (!$meta) throw new RuntimeException('Assessment register not found.', 404);

        $score = $rollup['entry_status'] === 'present' ? (float) $rollup['total'] : null;
        $grade = null;
        $points = null;
        if ($score !== null) {
            $graded = $this->grading->gradeForSystem($score, (float) $meta['max_marks'], $this->gradingSystemFor($assessmentId));
            $grade = $graded['grade_code'] ?? null;
            $points = $graded['points'] ?? null;
        }
        $remarks = $rollup['absent_paper_ids'] !== [] ? 'Absent paper(s): ' . count($rollup['absent_paper_ids']) : '';

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "INSERT INTO assessment_results
                    (assessment_id, student_academic_enrollment_id, marks_obtained, entry_status, grade, points, remarks, submitted_at, is_submitted, is_approved, responder_type, responder_id)
                 VALUES (?,?,?,?,?,?,?,NOW(),1,0,'teacher',?)
                 ON DUPLICATE KEY UPDATE
                    marks_obtained=VALUES(marks_obtained), entry_status=VALUES(entry_status), grade=VALUES(grade),
                    points=VALUES(points), remarks=VALUES(remarks), deleted_at=NULL, deleted_by=NULL"
            )->execute([$assessmentId, $enrollmentId, $score, $rollup['entry_status'], $grade, $points, $remarks, $this->userId]);
            $idStmt = $this->db->prepare('SELECT id, updated_at FROM assessment_results WHERE assessment_id=? AND student_academic_enrollment_id=?');
            $idStmt->execute([$assessmentId, $enrollmentId]);
            $parent = $idStmt->fetch(PDO::FETCH_ASSOC);
            $this->recordEvent($assessmentId, (int) $parent['id'], $enrollmentId, 'paper_rollup', null, ['marks_obtained' => $score, 'entry_status' => $rollup['entry_status'], 'grade' => $grade, 'paper_total' => $rollup['total'], 'paper_max' => $rollup['max_marks']], $reason ?: 'Paper marks rollup');
            (new TermResultsService($this->db))->compute((int) $meta['academic_year_class_stream_id'], (int) $meta['academic_year_term_id'], (int) $meta['learning_area_id']);
            $this->db->commit();
            return $rollup + ['result_id' => (int) $parent['id'], 'updated_at' => (string) $parent['updated_at'], 'grade' => $grade];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    private function recordResultRow(array $data, int $assessmentId, int $enrollmentId, string $status): array
    {
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare('SELECT a.id,a.max_marks,a.academic_year_class_stream_id,a.academic_year_term_id,a.learning_area_id FROM assessments a WHERE a.id=?');
            $stmt->execute([$assessmentId]);$assessment=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$assessment) throw new RuntimeException('Assessment not found.',404);
            $enroll=$this->db->prepare("SELECT id FROM student_academic_enrollments WHERE id=? AND academic_year_class_stream_id=? AND enrollment_status IN ('active','completed')");
            $enroll->execute([$enrollmentId,(int)$assessment['academic_year_class_stream_id']]);
            if(!$enroll->fetchColumn()) throw new RuntimeException('The learner enrollment does not belong to this assessment class stream.',422);
            $score=null;$grade=null;$points=null;
            if($status==='present'){
                $raw=$data['marks_obtained']??null;
                if($raw===null||$raw===''||!is_numeric($raw)) throw new RuntimeException('Numeric marks are required for a present learner.',422);
                if((float)$raw<0||(float)$raw>(float)$assessment['max_marks']) throw new RuntimeException('Marks must be between zero and the assessment maximum.',422);
                $score=(float)$raw;
                $graded=$this->grading->gradeForSystem($score,(float)$assessment['max_marks'],$this->gradingSystemFor($assessmentId));$grade=$graded['grade_code']??null;$points=$graded['points']??null;
            }
            $remarks=trim((string)($data['remarks']??''));
            $this->db->prepare("INSERT INTO assessment_results
                    (assessment_id, student_academic_enrollment_id, marks_obtained, entry_status,
                     grade, points, remarks, submitted_at, is_submitted, is_approved, responder_type, responder_id)
                 VALUES (?,?,?,?,?,?,?,NOW(),1,0,'teacher',?)
                 ON DUPLICATE KEY UPDATE
                    marks_obtained=VALUES(marks_obtained), entry_status=VALUES(entry_status),
                    grade=VALUES(grade), points=VALUES(points), remarks=VALUES(remarks),
                    deleted_at=NULL, deleted_by=NULL")->execute([$assessmentId,$enrollmentId,$score,$status,$grade,$points,$remarks,$this->userId]);
            $idStmt=$this->db->prepare('SELECT id FROM assessment_results WHERE assessment_id=? AND student_academic_enrollment_id=?');
            $idStmt->execute([$assessmentId,$enrollmentId]);
            $resultId=(int)$idStmt->fetchColumn();
            $this->recordEvent($assessmentId,$resultId,$enrollmentId,'created',null,['marks_obtained'=>$score,'entry_status'=>$status,'grade'=>$grade,'remarks'=>$remarks],(string)($data['reason']??'Administrative result recording'));
            (new TermResultsService($this->db))->compute((int)$assessment['academic_year_class_stream_id'],(int)$assessment['academic_year_term_id'],(int)$assessment['learning_area_id']);
            $this->db->commit();
            return ['id'=>$resultId,'marks_obtained'=>$score,'entry_status'=>$status,'grade'=>$grade];
        } catch(\Throwable $e) { if($this->db->inTransaction())$this->db->rollBack(); throw $e; }
    }

    public function softDeleteResult(int $resultId): array
    {
        if (!$this->isAcademicLeader()) throw new RuntimeException('Academic leadership access is required to delete a result.',403);
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare('SELECT *, academic_year_class_stream_id, academic_year_term_id, learning_area_id FROM ' . ReadReplicaService::qualifiedRef("assessment_results_detailed") . ' WHERE id=? AND deleted_at IS NULL FOR UPDATE');
            $stmt->execute([$resultId]);$old=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$old) throw new RuntimeException('Active result record not found.',404);
            $this->db->prepare('UPDATE assessment_results SET deleted_at=NOW(),deleted_by=? WHERE id=?')->execute([$this->userId,$resultId]);
            $this->recordEvent((int)$old['assessment_id'],$resultId,(int)$old['student_academic_enrollment_id'],'deleted',$old,['deleted'=>true],'Administrative result deletion');
            (new TermResultsService($this->db))->compute((int)$old['academic_year_class_stream_id'],(int)$old['academic_year_term_id'],(int)$old['learning_area_id']);
            $this->db->commit();
            return ['id'=>$resultId,'deleted'=>true];
        } catch(\Throwable $e) { if($this->db->inTransaction())$this->db->rollBack(); throw $e; }
    }

    public function restoreResult(int $resultId): array
    {
        if (!$this->isAcademicLeader()) throw new RuntimeException('Academic leadership access is required to restore a result.',403);
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare('SELECT *, academic_year_class_stream_id, academic_year_term_id, learning_area_id FROM ' . ReadReplicaService::qualifiedRef("assessment_results_detailed") . ' WHERE id=? AND deleted_at IS NOT NULL FOR UPDATE');
            $stmt->execute([$resultId]);$old=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$old) throw new RuntimeException('Deleted result record not found.',404);
            $this->db->prepare('UPDATE assessment_results SET deleted_at=NULL,deleted_by=NULL WHERE id=?')->execute([$resultId]);
            $this->recordEvent((int)$old['assessment_id'],$resultId,(int)$old['student_academic_enrollment_id'],'restored',$old,['deleted'=>false],'Administrative result restoration');
            (new TermResultsService($this->db))->compute((int)$old['academic_year_class_stream_id'],(int)$old['academic_year_term_id'],(int)$old['learning_area_id']);
            $this->db->commit();
            return ['id'=>$resultId,'restored'=>true];
        } catch(\Throwable $e) { if($this->db->inTransaction())$this->db->rollBack(); throw $e; }
    }

    /**
     * School-leadership submission of one complete learning-area register.
     *
     * Publication requires every register to be submitted, and the teacher bulk
     * path is scoped to the assigned class stream, so academic leadership needs
     * its own governed submission action for record-only exam periods.
     */
    public function adminSubmitRegister(int $assessmentId, string $reason = ''): array
    {
        if (!$this->isAcademicLeader()) throw new RuntimeException('Academic leadership access is required to submit this register.',403);

        $this->db->beginTransaction();
        try {
            $assessment = $this->assessment($assessmentId, true);
            $this->assertPeriodAllowsEntry($assessmentId);
            if ($assessment['assessment_status'] !== 'pending_submission') {
                throw new RuntimeException('Only a register awaiting submission can be submitted.',409);
            }

            $streamId = (int) $assessment['academic_year_class_stream_id'];
            $roster = $this->rosterMap($streamId);
            if (!$roster) throw new RuntimeException('No active learner enrollments exist for this class stream.',409);

            $stmt = $this->db->prepare('SELECT * FROM assessment_results WHERE assessment_id=? AND deleted_at IS NULL FOR UPDATE');
            $stmt->execute([$assessmentId]);
            $recorded = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $recorded[(int) $row['student_academic_enrollment_id']] = $row;
            }

            $missing = [];
            foreach ($roster as $studentId => $enrollmentId) {
                $row = $recorded[$enrollmentId] ?? null;
                if (!$row || !in_array((string) $row['entry_status'], ['present', 'absent', 'exempted'], true)) {
                    $missing[] = $studentId;
                    continue;
                }
                if ((string) $row['entry_status'] === 'present' && ($row['marks_obtained'] === null || !is_numeric((string) $row['marks_obtained']))) {
                    $missing[] = $studentId;
                }
            }
            if ($missing) {
                throw new RuntimeException(count($missing) . ' learner(s) still require marks, absent, or exempted status before this register can be submitted.',422);
            }

            $submittedAt = date('Y-m-d H:i:s');
            $mark = $this->db->prepare('UPDATE assessment_results SET is_submitted=1,is_approved=0,submitted_at=? WHERE id=?');
            foreach ($recorded as $enrollmentId => $row) {
                $mark->execute([$submittedAt, (int) $row['id']]);
                $this->recordEvent($assessmentId, (int) $row['id'], (int) $enrollmentId, 'submitted', $row, ['is_submitted' => true, 'is_approved' => false], $reason !== '' ? trim($reason) : 'Administrative register submission.');
            }

            $reviewRequired = $this->classTeacherReviewRequired($streamId);
            if ($reviewRequired && !$this->hasClassReviewSchema()) {
                throw new RuntimeException('Class-teacher review needs the summative-assessment migration before Grade 4–9 results can be submitted.',409);
            }
            $reviewStatus = $reviewRequired ? 'pending' : 'not_required';
            $this->db->prepare(
                'UPDATE assessments SET status=?,submitted_by=?,submitted_at=?,class_review_status=?,class_reviewed_by=NULL,class_reviewed_at=NULL,class_review_note=NULL WHERE id=?'
            )->execute(['submitted', $this->staffId(), $submittedAt, $reviewStatus, $assessmentId]);

            (new ExamPeriodService($this->db, $this->userId))->markSubmitted($assessmentId);
            (new TermResultsService($this->db))->compute($streamId, (int) $assessment['academic_year_term_id'], (int) $assessment['learning_area_id']);

            $this->db->commit();
            return [
                'assessment_id' => $assessmentId,
                'status' => 'submitted',
                'class_review_status' => $reviewStatus,
                'result_count' => count($recorded),
                'submitted_at' => $submittedAt,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /** Mark a validated register official and journal every result transition. */
    public function publishAssessment(int $assessmentId): int
    {
        $stmt=$this->db->prepare('SELECT * FROM assessment_results WHERE assessment_id=? AND deleted_at IS NULL FOR UPDATE');
        $stmt->execute([$assessmentId]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        if(!$rows) throw new RuntimeException('A submitted register has no active learner results.',409);
        $update=$this->db->prepare('UPDATE assessment_results SET is_approved=1,is_submitted=1 WHERE id=?');
        foreach($rows as $row){
            $update->execute([(int)$row['id']]);
            $this->recordEvent($assessmentId,(int)$row['id'],(int)$row['student_academic_enrollment_id'],'published',$row,['is_approved'=>true,'is_submitted'=>true],'School Administrator published official summative results.');
        }
        return count($rows);
    }

    public function moderate(int $assessmentId, bool $approve, ?int $studentId, string $reason): array
    {
        $isLeader=$this->isAcademicLeader();
        if (!$isLeader && !$this->isClassTeacher()) throw new RuntimeException('Class-teacher review or academic leadership access is required.',403);
        if (!$approve && trim($reason) === '') {
            throw new RuntimeException('A rejection reason is required', 422);
        }

        $this->db->beginTransaction();
        try {
            $assessment = $this->assessment($assessmentId, true);
            if (!in_array($assessment['assessment_status'], ['submitted', 'pending_approval'], true)) {
                throw new RuntimeException('Only submitted results can be moderated', 409);
            }
            if (!$isLeader) {
                if(!$this->hasClassReviewSchema())throw new RuntimeException('Class-teacher review is unavailable until the summative-assessment migration is applied.',409);
                if ($assessment['class_review_status'] !== 'pending' || !$this->classTeacherReviewRequired((int)$assessment['academic_year_class_stream_id'])) throw new RuntimeException('This register is not waiting for class-teacher review.',409);
                $this->assertClassTeacherScope((int)$assessment['academic_year_class_stream_id'],(int)$assessment['academic_year_term_id']);
                if (!empty($assessment['submitted_by']) && (int)$assessment['submitted_by'] === (int)$this->staffId()) throw new RuntimeException('The person who submitted these marks cannot review the same register.',403);
                if ($studentId !== null) throw new RuntimeException('Class teachers review a complete learning-area register at once.',422);
                $nextReview=$approve?'approved':'returned';
                $this->db->prepare('UPDATE assessments SET class_review_status=?,class_reviewed_by=?,class_reviewed_at=NOW(),class_review_note=?,status=? WHERE id=?')
                    ->execute([$nextReview,$this->staffId(),$approve?null:trim($reason),$approve?'submitted':'pending_submission',$assessmentId]);
                if (!$approve) $this->db->prepare('UPDATE assessment_results SET is_submitted=0,is_approved=0,moderation_note=? WHERE assessment_id=? AND deleted_at IS NULL')->execute([trim($reason),$assessmentId]);
                $this->db->commit();
                return ['assessment_id'=>$assessmentId,'status'=>$approve?'awaiting_school_publication':'returned','class_review_status'=>$nextReview];
            }
            if ($approve && (int)($assessment['exam_period_id'] ?? 0) > 0) {
                throw new RuntimeException('Summative exam results must be published through the School Administrator’s exam-period publication action.',409);
            }
            if ($assessment['class_review_status'] === 'pending') throw new RuntimeException('The class teacher must review this register before academic moderation.',409);

            $params = [$assessmentId];
            $studentSql = '';
            if ($studentId !== null) {
                $studentSql = ' AND sae.student_id = ?';
                $params[] = $studentId;
            }
            $stmt = $this->db->prepare(
                "SELECT ar.* FROM " . ReadReplicaService::qualifiedRef("assessment_results") . "
                 JOIN student_academic_enrollments sae ON sae.id = ar.student_academic_enrollment_id
                 WHERE ar.assessment_id = ? AND ar.is_submitted = 1 AND ar.deleted_at IS NULL{$studentSql}
                 FOR UPDATE"
            );
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$results) {
                throw new RuntimeException('No submitted learner results matched this moderation request', 404);
            }

            $update = $this->db->prepare(
                'UPDATE assessment_results SET is_approved = ?, is_submitted = ?, moderation_note = ? WHERE id = ?'
            );
            foreach ($results as $result) {
                $update->execute([
                    $approve ? 1 : 0,
                    $approve ? 1 : 0,
                    $approve ? null : trim($reason),
                    (int) $result['id'],
                ]);
                $this->recordEvent(
                    $assessmentId,
                    (int) $result['id'],
                    (int) $result['student_academic_enrollment_id'],
                    $approve ? 'approved' : 'rejected',
                    $result,
                    ['is_approved' => $approve, 'is_submitted' => $approve, 'moderation_note' => $approve ? null : trim($reason)],
                    $reason
                );
            }

            // A rejected row reopens the assessment as one controlled batch.
            // This prevents a teacher from having to overwrite already-approved
            // rows while resubmitting a complete class register.
            if (!$approve) {
                $reopen = $this->db->prepare(
                    'UPDATE assessment_results
                     SET is_submitted = 0, is_approved = 0
                     WHERE assessment_id = ?'
                );
                $reopen->execute([$assessmentId]);
            }

            $nextStatus = 'submitted';
            if (!$approve) {
                $nextStatus = 'pending_submission';
            } else {
                $remaining = $this->db->prepare(
                    'SELECT COUNT(*) FROM assessment_results WHERE assessment_id = ? AND deleted_at IS NULL AND (is_submitted = 0 OR is_approved = 0)'
                );
                $remaining->execute([$assessmentId]);
                if ((int) $remaining->fetchColumn() === 0) {
                    $expected = count($this->rosterMap((int) $assessment['academic_year_class_stream_id']));
                    $actual = $this->db->prepare('SELECT COUNT(*) FROM assessment_results WHERE assessment_id = ? AND deleted_at IS NULL AND is_approved = 1');
                    $actual->execute([$assessmentId]);
                    if ((int) $actual->fetchColumn() === $expected && $expected > 0) {
                        $nextStatus = 'approved';
                    }
                }
            }

            $assessmentUpdate = $this->db->prepare(
                "UPDATE assessments
                 SET status = ?, approved_by = ?, moderated_by = ?, moderated_at = NOW(),
                     reopened_by = ?, reopened_at = ?, reopen_reason = ?
                 WHERE id = ?"
            );
            $assessmentUpdate->execute([
                $nextStatus,
                $nextStatus === 'approved' ? $this->staffId() : null,
                $this->staffId(),
                $approve ? null : $this->staffId(),
                $approve ? null : date('Y-m-d H:i:s'),
                $approve ? null : trim($reason),
                $assessmentId,
            ]);

            $periodService = new ExamPeriodService($this->db, $this->userId);
            if ($approve) $periodService->refreshCompletion($assessmentId);
            else $this->db->prepare("UPDATE exam_periods ep JOIN exam_period_classes epc ON epc.exam_period_id=ep.id JOIN exam_period_class_learning_areas epcla ON epcla.exam_period_class_id=epc.id JOIN exam_period_timetable_entries ept ON ept.exam_period_class_learning_area_id=epcla.id JOIN exam_schedule_assessments esa ON esa.exam_schedule_id=ept.exam_schedule_id SET ep.status='results_open' WHERE esa.assessment_id=? AND ep.status='moderation'")->execute([$assessmentId]);

            if ($nextStatus === 'approved') {
                (new TermResultsService($this->db))->compute(
                    (int) $assessment['academic_year_class_stream_id'],
                    (int) $assessment['academic_year_term_id'],
                    (int) $assessment['learning_area_id']
                );
            }

            $this->db->commit();
            return [
                'assessment_id' => $assessmentId,
                'moderated_count' => count($results),
                'status' => $nextStatus,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function exam(int $assessmentId): array
    {
        $ec = ReadReplicaService::qualifiedRef('exam_context');
        $stmt = $this->db->prepare(
            "SELECT ec.exam_schedule_id, ec.assessment_id, ec.exam_name, ec.exam_type,
                    ec.exam_date, ec.start_time, ec.end_time, ec.venue, ec.exam_status AS schedule_status,
                    ec.aycs_id AS academic_year_class_stream_id, ec.academic_year_class_id, ec.academic_year_term_id, ec.learning_area_id,
                    ec.exam_period_id, ec.period_status AS exam_period_status,
                    ec.assessment_title, ec.assessment_max_marks AS max_marks, ec.assessment_type_id,
                    ec.assigned_by, ec.assessment_status,
                    ec.learning_area_name, ec.class_name, ec.stream_name,
                    ec.assessment_type_name
             FROM {$ec} ec
             WHERE ec.assessment_id = ? AND ec.exam_status <> 'cancelled' LIMIT 1"
        );
        $stmt->execute([$assessmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Published exam assessment was not found', 404);
        }
        return $row;
    }

    private function assessment(int $assessmentId, bool $lock): array
    {
        $reviewStatus=$this->hasClassReviewSchema()?'a.class_review_status':'\'not_required\' AS class_review_status';
        $sql = "SELECT a.id AS assessment_id, a.academic_year_class_stream_id,
                       a.academic_year_term_id, a.learning_area_id, a.max_marks,
                       a.assigned_by, a.status AS assessment_status,
                       {$reviewStatus}, a.submitted_by, c.name AS class_name,
                       (SELECT epc.exam_period_id
                        FROM exam_schedule_assessments esa
                        JOIN exam_period_timetable_entries ept ON ept.exam_schedule_id=esa.exam_schedule_id
                        JOIN exam_period_class_learning_areas epcla ON epcla.id=ept.exam_period_class_learning_area_id
                        JOIN exam_period_classes epc ON epc.id=epcla.exam_period_class_id
                        WHERE esa.assessment_id=a.id LIMIT 1) AS exam_period_id
                FROM assessments a
                JOIN academic_year_class_streams aycs ON aycs.id=a.academic_year_class_stream_id
                JOIN academic_year_classes ayc ON ayc.id=aycs.academic_year_class_id
                JOIN classes c ON c.id=ayc.class_id
                WHERE a.id = ?" . ($lock ? ' FOR UPDATE' : '');
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$assessmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Assessment not found', 404);
        }
        return $row;
    }

    private function roster(int $classStreamId, int $assessmentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT sae.id AS enrollment_id, s.id AS student_id, s.admission_no,
                    p.first_name, p.middle_name, p.last_name,
                    ar.marks_obtained, COALESCE(ar.entry_status, 'present') AS entry_status,
                    ar.grade, ar.points, ar.remarks, ar.moderation_note,
                    ar.is_submitted, ar.is_approved, ar.updated_at
             FROM " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae
             JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON s.id = sae.student_id AND s.status = 'active'
             JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id = s.person_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("assessment_results") . " ar
               ON ar.student_academic_enrollment_id = sae.id AND ar.assessment_id = ? AND ar.deleted_at IS NULL
             WHERE sae.academic_year_class_stream_id = ?
               AND sae.enrollment_status IN ('pending','active')
             ORDER BY p.first_name, p.middle_name, p.last_name, s.admission_no"
        );
        $stmt->execute([$assessmentId, $classStreamId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * The versioned grading system in force for this assessment: its exam
     * period snapshot first (historical immutability), then the term/class
     * binding, then the global legacy scale. Null = legacy behaviour.
     */
    private function gradingSystemFor(int $assessmentId): ?int
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT ep.grading_system_id
                 FROM ' . ReadReplicaService::qualifiedRef('assessments') . ' a
                 JOIN exam_schedule_assessments esa ON esa.assessment_id = a.id
                 JOIN exam_period_timetable_entries epte ON epte.exam_schedule_id = esa.exam_schedule_id
                 JOIN exam_period_class_learning_areas epcla ON epcla.id = epte.exam_period_class_learning_area_id
                 JOIN exam_period_classes epc ON epc.id = epcla.exam_period_class_id
                 JOIN exam_periods ep ON ep.id = epc.exam_period_id
                 WHERE a.id = ? LIMIT 1'
            );
            $stmt->execute([$assessmentId]);
            $snapshotted = $stmt->fetchColumn();
            if ($snapshotted !== false && $snapshotted !== null && (int) $snapshotted > 0) {
                return (int) $snapshotted;
            }
        } catch (\Throwable) {
            // results_only periods have no timetable link; fall through to the binding.
        }
        try {
            $resolved = (new GradingScopeService($this->db))->resolveForAssessment($assessmentId);
            return $resolved !== null ? (int) $resolved['system']['id'] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function rosterMap(int $classStreamId): array
    {
        $stmt = $this->db->prepare(
            "SELECT student_id, id FROM student_academic_enrollments
             WHERE academic_year_class_stream_id = ? AND enrollment_status IN ('pending','active')"
        );
        $stmt->execute([$classStreamId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['student_id']] = (int) $row['id'];
        }
        return $map;
    }

    private function assertCanAccessAssessment(array $assessment): void
    {
        if ($this->isAcademicLeader()) {
            return;
        }
        $staffId = $this->staffId();
        if ($staffId && (int) $assessment['assigned_by'] === $staffId) {
            return;
        }

        $scope = (new TeacherScopeService($this->db))->forUser(
            ['user_id' => $this->userId, 'staff_id' => $staffId],
            null,
            (int) $assessment['academic_year_term_id']
        );
        foreach ($scope['subject_assignments'] ?? [] as $assignment) {
            if ((int) $assignment['stream_id'] === (int) $assessment['academic_year_class_stream_id']
                && (int) $assignment['learning_area_id'] === (int) $assessment['learning_area_id']) {
                return;
            }
        }
        if (in_array((int) $assessment['academic_year_class_stream_id'], $scope['class_teacher_stream_ids'] ?? [], true)) {
            return;
        }
        throw new RuntimeException('This exam is outside your assigned teaching scope', 403);
    }

    private function assertPeriodAllowsEntry(int $assessmentId): void
    {
        $status = (new ExamPeriodService($this->db, $this->userId))->periodAccess($assessmentId);
        if ($status !== null && !in_array($status, ['results_open', 'moderation'], true)) {
            throw new RuntimeException('The Headteacher or School Administrator has not opened result entry for this exam period', 409);
        }
    }

    private function staffId(): ?int
    {
        if ($this->staffId !== null) {
            return $this->staffId ?: null;
        }
        $stmt = $this->db->prepare(
            "SELECT s.staff_id FROM " . ReadReplicaService::qualifiedRef("staff_directory") . " s
             WHERE s.user_id = ? AND s.staff_status = 'active' LIMIT 1"
        );
        $stmt->execute([$this->userId]);
        $this->staffId = (int) ($stmt->fetchColumn() ?: 0);
        return $this->staffId ?: null;
    }

    private function isAcademicLeader(): bool
    {
        foreach ($this->roleNames() as $role) {
            if (preg_match('/system administrator|school admin|headteacher|deputy head/i', $role)) {
                return true;
            }
        }
        return false;
    }

    private function isClassTeacher(): bool
    {
        foreach ($this->roleNames() as $role) if (preg_match('/class teacher/i',$role)) return true;
        return false;
    }

    private function hasClassReviewSchema(): bool
    {
        if($this->classReviewSchema!==null)return $this->classReviewSchema;
        $stmt=$this->db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='assessments' AND COLUMN_NAME='class_review_status'");
        $this->classReviewSchema=(int)$stmt->fetchColumn()>0;
        return $this->classReviewSchema;
    }

    private function classTeacherReviewRequired(int $streamId): bool
    {
        $stmt=$this->db->prepare('SELECT aycs.class_name AS name FROM ' . ReadReplicaService::qualifiedRef('academic_calendar') . ' aycs WHERE aycs.class_stream_id=?');
        $stmt->execute([$streamId]);
        $name=(string)$stmt->fetchColumn();
        return preg_match('/(?:grade|class)\\s*[4-9]\\b/i',$name)===1;
    }

    private function assertClassTeacherScope(int $streamId,int $termId): void
    {
        $scope=(new TeacherScopeService($this->db))->forUser(['user_id'=>$this->userId,'staff_id'=>$this->staffId()],null,$termId);
        if (!in_array($streamId,$scope['class_teacher_stream_ids']??[],true)) throw new RuntimeException('Only the assigned class teacher may review this register.',403);
    }

    private function roleNames(): array
    {
        if ($this->roleNames !== null) {
            return $this->roleNames;
        }
        $stmt = $this->db->prepare(
            'SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ? AND r.is_active = 1'
        );
        $stmt->execute([$this->userId]);
        $this->roleNames = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        return $this->roleNames;
    }

    private function recordEvent(
        int $assessmentId,
        int $resultId,
        int $enrollmentId,
        string $eventType,
        ?array $oldValues,
        array $newValues,
        string $reason
    ): void {
        $stmt = $this->db->prepare(
            "INSERT INTO assessment_result_events
                (assessment_id, assessment_result_id, student_academic_enrollment_id,
                 event_type, old_values_json, new_values_json, reason, actor_user_id, actor_staff_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $assessmentId,
            $resultId,
            $enrollmentId,
            $eventType,
            $oldValues === null ? null : json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            trim($reason) !== '' ? trim($reason) : null,
            $this->userId ?: null,
            $this->staffId(),
        ]);
    }
}
