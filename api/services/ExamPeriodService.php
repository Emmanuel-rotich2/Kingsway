<?php
declare(strict_types=1);

namespace App\API\Services;

use App\API\Services\ReadReplicaService;
use DateTimeImmutable;
use PDO;
use RuntimeException;

/** Class-level exam sittings with stream-specific result registers. */
final class ExamPeriodService
{
    private PDO $db;
    private int $userId;
    private ?AssessmentPolicyService $sbaPolicy = null;

    public function __construct(PDO $db, int $userId, ?AssessmentPolicyService $sbaPolicy = null)
    {
        $this->db = $db;
        $this->userId = $userId;
        $this->sbaPolicy = $sbaPolicy;
    }

    /**
     * Assessment operating policy, read from the assessment policy registry
     * (authorities, documents, cohort rules, schedules). Data-driven: a new
     * KNEC circular changes behaviour without a deploy. Injected in tests.
     */
    public function sbaPolicy(): AssessmentPolicyService
    {
        return $this->sbaPolicy ??= new AssessmentPolicyService($this->db);
    }

    public function options(?int $termId = null): array
    {
        $terms = $this->db->query(
            "SELECT academic_year_term_id AS id, academic_year_id, year_name AS academic_year_name, term_id,
                    opening_date, closing_date, term_period_status AS status
             FROM " . ReadReplicaService::qualifiedRef('academic_term') . "
             ORDER BY academic_year_id DESC, term_id"
        )->fetchAll(PDO::FETCH_ASSOC);
        if (!$termId && $terms) {
            foreach ($terms as $candidate) if ($candidate['status'] === 'current') { $termId=(int)$candidate['id']; break; }
            $termId ??= (int)$terms[0]['id'];
        }
        if (!$termId) return ['terms'=>$terms, 'selected_term'=>null, 'classes'=>[]];
        $term = $this->term($termId);
        $stmt = $this->db->prepare(
            "SELECT DISTINCT academic_year_class_id, class_name
             FROM " . ReadReplicaService::qualifiedRef('academic_calendar') . "
             WHERE academic_year_id = ?
               AND class_stream_status = 'active'
             ORDER BY class_name"
        );
        $stmt->execute([(int)$term['academic_year_id']]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $areasStmt = $this->db->prepare(
            "SELECT cla.id AS academic_year_class_learning_area_id, cla.academic_year_class_id,
                    cla.learning_area_id, la.name AS learning_area_name
             FROM " . ReadReplicaService::qualifiedRef('academic_year_class_learning_areas') . " cla
             JOIN " . ReadReplicaService::qualifiedRef('learning_areas') . " la ON la.id=cla.learning_area_id
             WHERE cla.academic_year_class_id IN (
                 SELECT id FROM " . ReadReplicaService::qualifiedRef('academic_year_classes') . " WHERE academic_year_id=?
             ) AND cla.status <> 'skipped' ORDER BY la.name"
        );
        $areasStmt->execute([(int)$term['academic_year_id']]);
        $areasByClass=[];
        foreach ($areasStmt->fetchAll(PDO::FETCH_ASSOC) as $area) {
            $areasByClass[(int)$area['academic_year_class_id']][] = [
                'academic_year_class_learning_area_id'=>(int)$area['academic_year_class_learning_area_id'],
                'learning_area_id'=>(int)$area['learning_area_id'], 'name'=>(string)$area['learning_area_name'],
            ];
        }
        foreach ($classes as &$class) {
            $class['academic_year_class_id']=(int)$class['academic_year_class_id'];
            $class['learning_areas']=$areasByClass[$class['academic_year_class_id']] ?? [];
        }
        unset($class);
        return ['terms'=>$terms, 'selected_term'=>$term, 'classes'=>$classes];
    }

    public function list(bool $includeDeleted = false): array
    {
        $where = $includeDeleted ? '' : "WHERE ep.deleted_at IS NULL AND ep.status <> 'cancelled'";
        $extended=$this->hasColumn('exam_periods','assessment_kind');
        $kindSelect=$extended?'ep.assessment_kind, ep.assessment_authority, ep.national_assessment_code, ep.assessment_type_classification_id,':'\'school_based\' AS assessment_kind, NULL AS assessment_authority, NULL AS national_assessment_code, NULL AS assessment_type_classification_id,';
        $publicationSelect=$this->hasColumn('exam_periods','results_published_at')?'ep.results_published_by, ep.results_published_at, ep.results_release_mode,':'NULL AS results_published_by, NULL AS results_published_at, NULL AS results_release_mode,';
        return $this->db->query(
            "SELECT ep.id, ep.title, ep.academic_year_term_id, aterm.term_id, aterm.year_name AS academic_year_name,
                    ep.starts_on, ep.ends_on, ep.status, ep.kind, ep.entry_mode,
                    {$kindSelect}
                    ep.created_at, ep.published_at, ep.results_opened_at, ep.completed_at,
                    {$publicationSelect}
                    ep.deleted_at, ep.deleted_by,
                    COUNT(DISTINCT epc.id) AS class_count,
                    COUNT(DISTINCT epcla.id) AS learning_area_count,
                    COUNT(DISTINCT CASE WHEN es.status <> 'cancelled' THEN ept.id END) AS scheduled_count,
                    COUNT(DISTINCT CASE WHEN a.status IN ('submitted','pending_approval') THEN esa.assessment_id END) AS submitted_count,
                    COUNT(DISTINCT CASE WHEN a.status='approved' THEN esa.assessment_id END) AS approved_count
             FROM exam_periods ep
             JOIN " . ReadReplicaService::qualifiedRef("academic_term") . " aterm ON aterm.academic_year_term_id=ep.academic_year_term_id
             LEFT JOIN exam_period_classes epc ON epc.exam_period_id=ep.id
             LEFT JOIN exam_period_class_learning_areas epcla ON epcla.exam_period_class_id=epc.id
             LEFT JOIN exam_period_timetable_entries ept ON ept.exam_period_class_learning_area_id=epcla.id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("exam_schedules") . " es ON es.id=ept.exam_schedule_id
             LEFT JOIN exam_schedule_assessments esa ON esa.exam_schedule_id=es.id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("assessments") . " a ON a.id=esa.assessment_id
             {$where}
             GROUP BY ep.id ORDER BY ep.created_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function detail(int $periodId): array
    {
        $period=$this->period($periodId);
        $stmt=$this->db->prepare(
            "SELECT epcla.id AS exam_period_class_learning_area_id, aycd.id AS academic_year_class_id,
                    aycd.class_name AS class_name, la.id AS learning_area_id, la.name AS learning_area_name,
                    es.id AS exam_schedule_id, es.exam_date, es.start_time, es.end_time, es.max_marks,
                    es.venue, es.room_id, es.invigilator_id, es.notes, es.status AS schedule_status,
                    (SELECT COUNT(*) FROM exam_schedule_assessments esa WHERE esa.exam_schedule_id=es.id) AS stream_assessment_count,
                    (SELECT COUNT(*) FROM " . ReadReplicaService::qualifiedRef("assessment_results") . " ar JOIN exam_schedule_assessments esa2 ON esa2.assessment_id=ar.assessment_id
                       WHERE esa2.exam_schedule_id=es.id AND ar.deleted_at IS NULL) AS result_count
             FROM exam_period_classes epc
             JOIN " . ReadReplicaService::qualifiedRef("academic_class_directory") . " aycd ON aycd.id=epc.academic_year_class_id
             JOIN exam_period_class_learning_areas epcla ON epcla.exam_period_class_id=epc.id
             JOIN academic_year_class_learning_areas cla ON cla.id=epcla.academic_year_class_learning_area_id
             JOIN learning_areas la ON la.id=cla.learning_area_id
             LEFT JOIN exam_period_timetable_entries ept ON ept.exam_period_class_learning_area_id=epcla.id
             LEFT JOIN exam_schedules es ON es.id=ept.exam_schedule_id
             WHERE epc.exam_period_id=? ORDER BY aycd.class_name,la.name"
        );
        $stmt->execute([$periodId]);
        $entries=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $classStmt=$this->db->prepare(
            "SELECT aycd.id AS academic_year_class_id, aycd.class_name AS class_name,
                    (SELECT GROUP_CONCAT(cs.stream_name ORDER BY cs.stream_name SEPARATOR ', ') FROM " . ReadReplicaService::qualifiedRef("class_stream_directory") . " cs
                       WHERE cs.academic_year_class_id=aycd.id AND cs.class_stream_status='active') AS stream_names
             FROM exam_period_classes epc
             JOIN " . ReadReplicaService::qualifiedRef("academic_class_directory") . " aycd ON aycd.id=epc.academic_year_class_id
             WHERE epc.exam_period_id=? ORDER BY aycd.class_name"
        );
        $classStmt->execute([$periodId]);
        $classes=$classStmt->fetchAll(PDO::FETCH_ASSOC);
        $period['academic_year_class_ids']=array_map('intval',array_column($classes,'academic_year_class_id'));
        $period['assessment_kind']=$period['assessment_kind']??'school_based';
        $period['national_assessment_code']=$period['national_assessment_code']??null;
        $period['assessment_authority']=$period['assessment_authority']??null;
        $period['entry_mode']=$period['entry_mode']??'timetable';
        return ['period'=>$period,'classes'=>$classes,'class_ids'=>$period['academic_year_class_ids'],'entries'=>$entries];
    }

    /** Build a minimized, pseudonymized context for the governed AI draft workflow. */
    public function aiTimetableInput(int $periodId, array $constraints): array
    {
        $period = $this->period($periodId);
        if ($period['status'] !== 'draft') throw new RuntimeException('AI can draft only an unpublished exam period.', 409);
        $planning = $this->normalizeAiPlanning($constraints);
        $slots = $this->examSlots($period, $planning);
        if (!$slots) throw new RuntimeException('No configured school days and exam sessions fit inside this exam period. Adjust the period or session settings.', 422);

        $areas = $this->periodAreas($periodId);
        if (!$areas) throw new RuntimeException('This exam period has no selected class learning areas.', 409);
        $classes = [];
        foreach ($areas as $area) $classes[(int) $area['academic_year_class_id']] = (string) $area['class_name'];
        asort($classes, SORT_NATURAL | SORT_FLAG_CASE);
        $classKeys = [];
        $nextClassKey = 1;
        foreach (array_keys($classes) as $classId) $classKeys[$classId] = $nextClassKey++;

        $staffByPaper = [];
        $allStaffIds = [];
        $missingTeacherAssignments = [];
        $streamStmt = $this->db->prepare("SELECT class_stream_id AS id, class_teacher_id, COALESCE(stream_name, CONCAT('stream ', class_stream_id)) AS stream_name FROM " . ReadReplicaService::qualifiedRef('academic_calendar') . " WHERE academic_year_class_id=? AND class_stream_status='active' ORDER BY class_stream_id");
        $streamTeachers = $this->db->prepare(
            "SELECT DISTINCT x.staff_id FROM " . ReadReplicaService::qualifiedRef("academic_year_class_stream_learning_area_teachers") . "
             JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_stream_learning_areas") . " sla ON sla.id=x.academic_year_class_stream_learning_area_id
              AND sla.academic_year_class_stream_id=x.academic_year_class_stream_id
              AND sla.academic_year_class_learning_area_id=?
             WHERE x.academic_year_class_stream_id=? AND x.academic_year_term_id=?
               AND x.learning_area_id=? AND x.status='active'"
        );
        $classTeachers = $this->db->prepare(
            'SELECT staff_id FROM academic_year_class_learning_area_teachers WHERE academic_year_class_learning_area_id=? AND academic_year_term_id=?'
        );
        foreach ($areas as $index => $area) {
            $paperId = $index + 1;
            $annualAreaId = (int) $area['academic_year_class_learning_area_id'];
            $streamStmt->execute([(int) $area['academic_year_class_id']]);
            $streams = $streamStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$streams) throw new RuntimeException('Every selected exam class must have active streams.', 409);
            $teachers = [];
            $classTeachers->execute([$annualAreaId, (int) $period['academic_year_term_id']]);
            foreach ($classTeachers->fetchAll(PDO::FETCH_COLUMN) as $teacherId) $teachers[(int) $teacherId] = true;
            foreach ($streams as $stream) {
                $streamTeachers->execute([$annualAreaId, (int) $stream['id'], (int) $period['academic_year_term_id'], (int) $area['learning_area_id']]);
                $assigned = array_map('intval', $streamTeachers->fetchAll(PDO::FETCH_COLUMN));
                if ($assigned) {
                    foreach ($assigned as $teacherId) $teachers[$teacherId] = true;
                } elseif ($teachers) {
                    // A class-level learning-area assignment explicitly applies to every stream.
                } elseif ($area['grade_band'] === 'early' && !empty($stream['class_teacher_id'])) {
                    $teachers[(int) $stream['class_teacher_id']] = true;
                } else {
                    $missingTeacherAssignments[] = $area['class_name'] . ' · ' . $area['learning_area_name'] . ' · ' . $stream['stream_name'];
                }
            }
            $staffByPaper[$paperId] = array_keys($teachers);
            foreach (array_keys($teachers) as $teacherId) $allStaffIds[$teacherId] = true;
        }
        if ($missingTeacherAssignments) {
            $missingTeacherAssignments = array_values(array_unique($missingTeacherAssignments));
            $shown = array_slice($missingTeacherAssignments, 0, 8);
            $more = count($missingTeacherAssignments) - count($shown);
            $message = 'AI cannot avoid teacher clashes until responsible teachers are assigned to these class, learning-area and stream combinations: ' . implode('; ', $shown);
            if ($more > 0) $message .= '; and ' . $more . ' more';
            throw new RuntimeException($message . '. Add subject-teacher assignments for the exam term, then draft the timetable again.', 409);
        }
        $staffIds = array_keys($allStaffIds);
        sort($staffIds, SORT_NUMERIC);
        $staffGroups = [];
        foreach ($staffIds as $index => $staffId) $staffGroups[$staffId] = 'teacher_' . ($index + 1);

        $papers = [];
        $forPaper = [];
        foreach ($areas as $index => $area) {
            $paperId = $index + 1;
            $forPaper[$paperId] = (int) $area['exam_period_class_learning_area_id'];
            $papers[] = [
                'paper_id' => $paperId,
                'class_key' => $classKeys[(int) $area['academic_year_class_id']],
                'class_name' => (string) $area['class_name'],
                'grade_band' => (string) $area['grade_band'],
                'learning_area' => (string) $area['learning_area_name'],
                'teacher_groups' => array_values(array_unique(array_map(static fn(int $staffId): string => $staffGroups[$staffId], $staffByPaper[$paperId]))),
            ];
        }
        $term = $this->db->prepare('SELECT term_id, year_name AS academic_year_name FROM ' . ReadReplicaService::qualifiedRef('academic_term') . ' WHERE academic_year_term_id=?');
        $term->execute([(int) $period['academic_year_term_id']]);
        $term = $term->fetch(PDO::FETCH_ASSOC) ?: [];
        $input = [
            'exam_title' => (string) $period['title'],
            'term_label' => (string) ($term['academic_year_name'] ?? 'Academic year') . ' · Term ' . (int) $term['term_id'],
            'date_window' => (string) $period['starts_on'] . ' to ' . (string) $period['ends_on'],
            'papers' => $papers,
            'allowed_slots' => $slots,
            'planning_rules' => 'One paper per class and learning area; every stream in the same class sits it at the same time. Early band (Playgroup, PP1, PP2, Grades 1–3): no more than two papers per school day, using only the first two configured sittings. Upper band (Grades 4–9): no more than three papers per school day. A pseudonymous teacher group may supervise only one paper in a time slot. Schedule every paper exactly once. Use only supplied school-calendar dates and allowed slots.',
        ];
        return ['input' => $input, 'planning' => $planning, 'scope_hash' => hash('sha256', json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 'paper_map' => $forPaper];
    }

    /** Validate and translate a stored AI draft into editable timetable rows. */
    public function materializeAiTimetableDraft(int $periodId, array $metadata, array $draft): array
    {
        $planning = (array) ($metadata['planning'] ?? []);
        $rebuilt = $this->aiTimetableInput($periodId, $planning);
        if (!hash_equals((string) ($metadata['scope_hash'] ?? ''), $rebuilt['scope_hash'])) {
            throw new RuntimeException('This AI timetable draft is stale because the exam period, curriculum, or teacher assignments changed. Generate a fresh draft.', 409);
        }
        $assignments = (array) ($draft['assignments'] ?? []);
        if (!$assignments) return ['entries' => [], 'unresolved_constraints' => (array) ($draft['unresolved_constraints'] ?? []), 'ready' => false];
        $validated = ExamTimetableDraftValidator::normalize($assignments, $rebuilt['input']);
        $entries = [];
        foreach ($validated as $item) {
            $entries[] = [
                'exam_period_class_learning_area_id' => (int) ($rebuilt['paper_map'][(int) $item['paper_id']] ?? 0),
                'exam_date' => $item['date'], 'start_time' => $item['start_time'], 'end_time' => $item['end_time'],
            ];
        }
        return ['entries' => $entries, 'unresolved_constraints' => [], 'ready' => true];
    }

    public function create(array $data): array
    {
        $termId=(int)($data['academic_year_term_id']??0);
        $title=trim((string)($data['title']??''));
        $starts=(string)($data['starts_on']??''); $ends=(string)($data['ends_on']??'');
        $kind=strtolower(trim((string)($data['kind']??'upcoming')));
        if(!in_array($kind,['upcoming','past'],true))$kind='upcoming';
        $entryMode=strtolower(trim((string)($data['entry_mode']??'timetable')));
        if(!in_array($entryMode,['timetable','results_only'],true))$entryMode='timetable';
        $assessmentKind=strtolower(trim((string)($data['assessment_kind']??'school_based')));
        if(!in_array($assessmentKind,['school_based','national','mock','other'],true))throw new RuntimeException('Choose a valid summative assessment type.',422);
        $authority=trim((string)($data['assessment_authority']??'')) ?: null;
        $nationalCode=strtoupper(trim((string)($data['national_assessment_code']??''))) ?: null;
        // Summative exam TYPE (CA / SBA / SA) — classification dimension, kept
        // separate from the authority dimension (assessment_kind). DB-driven.
        $classificationId=$this->resolveClassificationId($data);
        $classIds=array_values(array_unique(array_filter(array_map('intval',(array)($data['academic_year_class_ids']??$data['class_ids']??[])))));
        if(!$this->hasColumn('exam_periods','assessment_kind') && $assessmentKind!=='school_based')throw new RuntimeException('Apply the summative-assessment migration before creating this assessment type.',409);
        // Per-class entitlements (mixed cohorts in one period). The period-level
        // type stays as the DEFAULT for classes without an explicit override.
        $entitlements=$this->classEntitlements($data,$classIds);
        $this->assertClassTypeCompliance($classIds,$entitlements,$termId,$classificationId);
        if($assessmentKind==='national' && !array_filter($entitlements)){
            // Legacy single-type period: no per-class overrides were sent, so the
            // period-wide national gates apply to every class. (With overrides,
            // assertClassTypeCompliance already validated each class.)
            if(!$this->hasColumn('exam_periods','assessment_kind'))throw new RuntimeException('The summative-assessment migration must be applied before creating national assessments.',409);
            if(!$authority)$authority='KNEC';
            if(!$nationalCode)throw new RuntimeException('Select the national assessment (KPSEA, KJSEA, or other).',422);
            if(!in_array($nationalCode,['KPSEA','KJSEA','OTHER'],true))throw new RuntimeException('Choose KPSEA, KJSEA, or another national assessment.',422);
            if($nationalCode==='KPSEA' && count(array_filter($classIds,fn(int $id):bool=>$this->classMatchesGrade($id,'6')))!==count($classIds))throw new RuntimeException('KPSEA is configured for Grade 6 classes only.',422);
            if($nationalCode==='KJSEA' && count(array_filter($classIds,fn(int $id):bool=>$this->classMatchesGrade($id,'9')))!==count($classIds))throw new RuntimeException('KJSEA is configured for Grade 9 classes only.',422);
        } elseif($assessmentKind!=='national') { $nationalCode=null; }
        if (!$termId || $title==='' || !$starts || !$ends || !$classIds) throw new RuntimeException('Term, exam name, dates, and at least one class are required',422);
        if ($this->date($starts)>$this->date($ends)) throw new RuntimeException('The exam period end date must be on or after its start date',422);
        $term=$this->term($termId);
        // The academic YEAR is the authoritative envelope: special exams may be
        // scheduled after a term closes (and outside term dates generally) as
        // long as the year itself has not ended. Term bounds surface as
        // advisories instead of errors, so staff stay informed while the
        // system stays flexible.
        $yearStmt=$this->db->prepare('SELECT academic_year_start AS start_date, academic_year_end AS end_date, academic_year_status AS status FROM ' . ReadReplicaService::qualifiedRef('academic_term') . ' WHERE academic_year_term_id=?');
        $yearStmt->execute([$termId]);
        $year=$yearStmt->fetch(PDO::FETCH_ASSOC);
        if(!$year) throw new RuntimeException('The academic year for this term was not found',404);
        if(($year['status']??'')==='archived') throw new RuntimeException('This academic year is archived; new exam periods can no longer be added to it',409);
        if(!empty($year['start_date']) && $starts<(string)$year['start_date']) throw new RuntimeException('The exam period starts before the academic year begins',422);
        if(!empty($year['end_date']) && $ends>(string)$year['end_date']) throw new RuntimeException('The exam period ends after the academic year',422);
        $termAdvisories=[];
        if(!empty($term['opening_date']) && $starts<(string)$term['opening_date']) $termAdvisories[]='This period starts before the selected term opens; confirm this is an intended special or early assessment.';
        if(!empty($term['closing_date']) && $ends>(string)$term['closing_date']) $termAdvisories[]='This period runs past the term closing date; special and national exams may proceed while the academic year is open.';
        if ($kind==='past' && $this->date($ends)>new DateTimeImmutable('today')) throw new RuntimeException('A previous exam record must end on or before today',422);
        $valid=$this->classesForTerm($termId,$classIds);
        if (count($valid)!==count($classIds)) throw new RuntimeException('One or more selected classes do not belong to the selected academic year',422);
        // KNEC SBA exemption gate: a school-based period may never score Grade 6
        // (KPSEA) or Grade 9 (KJSEA). Hard stop - an SBA score uploaded against
        // an exempt cohort cannot be withdrawn from the learner's CBC record.
        $this->assertSbaCompliance($classIds, $assessmentKind, $termId, $authority);
        $authority=$this->sbaPolicy()->resolveAuthority($assessmentKind,$authority,(int)($term['term_id']??0));
        // Snapshot the grading system bound to this term so later binding or
        // scale changes never rewrite what this period's grades mean.
        $gradingSystemId=null;
        $gradingResolution=(new GradingScopeService($this->db))->resolve($termId,null,null);
        if($gradingResolution)$gradingSystemId=(int)$gradingResolution['system']['id'];
        $this->db->beginTransaction();
        try {
            if($this->hasColumn('exam_periods','assessment_kind')){
                $insert=$this->db->prepare("INSERT INTO exam_periods (academic_year_term_id,title,starts_on,ends_on,status,kind,entry_mode,assessment_kind,assessment_authority,national_assessment_code,assessment_type_classification_id,grading_system_id,created_by) VALUES (?,?,?,?,'draft',?,?,?,?,?,?,?,?)");
                $insert->execute([$termId,$title,$starts,$ends,$kind,$entryMode,$assessmentKind,$authority,$nationalCode,$classificationId,$gradingSystemId,$this->userId]);
            } else {
                $insert=$this->db->prepare("INSERT INTO exam_periods (academic_year_term_id,title,starts_on,ends_on,status,kind,entry_mode,created_by) VALUES (?,?,?,?,'draft',?,?,?)");
                $insert->execute([$termId,$title,$starts,$ends,$kind,$entryMode,$this->userId]);
            }
            $periodId=(int)$this->db->lastInsertId();
            $addClass=$this->db->prepare('INSERT INTO exam_period_classes (exam_period_id,academic_year_class_id) VALUES (?,?)');
            $addArea=$this->db->prepare('INSERT INTO exam_period_class_learning_areas (exam_period_class_id,academic_year_class_learning_area_id) VALUES (?,?)');
            $lookup=$this->db->prepare("SELECT cla.id FROM academic_year_class_learning_areas cla WHERE cla.academic_year_class_id=? AND cla.status <> 'skipped'");
            $streamCheck=$this->db->prepare("SELECT COUNT(*) FROM academic_year_class_streams WHERE academic_year_class_id=? AND status='active'");
            foreach ($classIds as $classId) {
                $streamCheck->execute([$classId]);
                if (!(int)$streamCheck->fetchColumn()) throw new RuntimeException("The selected class has no active streams in this academic year",409);
                $addClass->execute([$periodId,$classId]); $periodClassId=(int)$this->db->lastInsertId();
                $lookup->execute([$classId]); $areas=array_map('intval',$lookup->fetchAll(PDO::FETCH_COLUMN));
                if (!$areas) throw new RuntimeException('A selected class has no configured learning areas',409);
                foreach ($areas as $areaId) $addArea->execute([$periodClassId,$areaId]);
            }
            $sittings=0;
            if ($entryMode==='results_only') {
                // Record-keeping mode: create one nominal sitting per class learning area
                // and open result entry immediately, so teachers can submit marks without
                // a published timetable.
                $sittings=$this->writeRecordOnlySittings(['id'=>$periodId,'title'=>$title,'academic_year_term_id'=>$termId,'starts_on'=>$starts]);
                $open=$this->db->prepare("UPDATE exam_periods SET status='results_open',results_opened_by=?,results_opened_at=NOW() WHERE id=? AND status='draft'");
                $open->execute([$this->userId,$periodId]);
            }
            $this->db->commit();
            $nationalAdvisories=$assessmentKind==='national'&&$nationalCode&&$nationalCode!=='OTHER'
                ?$this->sbaPolicy()->validateNationalWindow($nationalCode,$starts,$ends):[];
            return ['id'=>$periodId,'status'=>$entryMode==='results_only'?'results_open':'draft','kind'=>$kind,'entry_mode'=>$entryMode,'assessment_kind'=>$assessmentKind,'assessment_authority'=>$authority,'national_assessment_code'=>$nationalCode,'grading_system_id'=>$gradingSystemId,'national_window_advisories'=>$nationalAdvisories,'term_window_advisories'=>$termAdvisories,'classes'=>count($classIds),'sittings'=>$sittings];
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    private function classMatchesGrade(int $academicYearClassId, string $grade): bool
    {
        $stmt=$this->db->prepare('SELECT class_name FROM ' . ReadReplicaService::qualifiedRef('academic_calendar') . ' WHERE academic_year_class_id=?');
        $stmt->execute([$academicYearClassId]);
        return preg_match('/(?:^|\\D)'.preg_quote($grade,'/').'(?:\\D|$)/', (string)$stmt->fetchColumn())===1;
    }

    /**
     * Grade label per selected academic-year class, used to place a proposed
     * period against the KNEC SBA cohorts.
     *
     * @return array<int,string>
     */
    private function classGrades(array $academicYearClassIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $academicYearClassIds)));
        if (!$ids) return [];
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT DISTINCT class_name FROM " . ReadReplicaService::qualifiedRef('academic_calendar') . " WHERE academic_year_class_id IN ({$marks})"
        );
        $stmt->execute($ids);
        $grades = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $name) $grades[] = (string) $name;
        return $grades;
    }

    /**
     * Refuse a school-based SBA period that would score an exempt cohort.
     *
     * The disposition comes from the active registry rows (today Grade 6 is
     * released for KPSEA and Grade 9 for KJSEA). An SBA score recorded against
     * an exempt cohort corrupts the learner's permanent CBC record, so this is
     * a hard stop rather than a warning.
     */
    private function assertSbaCompliance(array $classIds, string $assessmentKind, int $termId, ?string $authority): void
    {
        $term = $this->term($termId);
        $report = $this->sbaPolicy()->validateExamPeriod(
            $this->classGrades($classIds),
            $assessmentKind,
            (int) ($term['term_id'] ?? 0),
            $authority
        );
        if ($report['violations']) {
            throw new RuntimeException((string) $report['violations'][0]['message'], 422);
        }
    }

    private function staffId(): int
    {
        $stmt=$this->db->prepare("SELECT staff_id FROM " . ReadReplicaService::qualifiedRef('staff_directory') . " WHERE user_id=? AND staff_status='active' LIMIT 1");
        $stmt->execute([$this->userId]);
        $staffId=(int)($stmt->fetchColumn()?:0);
        if($staffId<1)throw new RuntimeException('An active staff profile is required to publish results.',403);
        return $staffId;
    }

    private function hasColumn(string $table,string $column): bool
    {
        $allowed=['exam_periods'=>['assessment_kind','results_published_at'],'assessments'=>['class_review_status']];
        if(!in_array($column,$allowed[$table]??[],true))return false;
        $stmt=$this->db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $stmt->execute([$table,$column]);return (int)$stmt->fetchColumn()>0;
    }

    public function update(int $periodId, array $data): array
    {
        $period = $this->period($periodId, true);
        if (!empty($period['deleted_at'])) throw new RuntimeException('Restore this exam period before editing it.', 409);
        $title = trim((string)($data['title'] ?? $period['title']));
        $starts = (string)($data['starts_on'] ?? $period['starts_on']);
        $ends = (string)($data['ends_on'] ?? $period['ends_on']);
        if ($title === '' || $this->date($starts) > $this->date($ends)) throw new RuntimeException('Enter a name and a valid date range.', 422);
        $term=$this->term((int)$period['academic_year_term_id']);
        $updatingKind = strtolower(trim((string)($data['assessment_kind'] ?? $period['assessment_kind'] ?? 'school_based')));
        // Same year-envelope rule as create(): term bounds advise, the year bounds.
        $yearStmt=$this->db->prepare('SELECT academic_year_start AS start_date, academic_year_end AS end_date, academic_year_status AS status FROM ' . ReadReplicaService::qualifiedRef('academic_term') . ' WHERE academic_year_term_id=?');
        $yearStmt->execute([(int)$term['academic_year_term_id']]);
        $year=$yearStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if(($year['status']??'')==='archived') throw new RuntimeException('This academic year is archived; its exam periods can no longer be re-dated.',409);
        if(!empty($year['start_date']) && $starts<(string)$year['start_date']) throw new RuntimeException('Exam dates must fall within the academic year.',422);
        if(!empty($year['end_date']) && $ends>(string)$year['end_date']) throw new RuntimeException('Exam dates must fall within the academic year.',422);
        if (($period['kind']??'upcoming')==='past' && $this->date($ends)>new DateTimeImmutable('today')) throw new RuntimeException('A previous exam record must end on or before today.',422);

        $extended=$this->hasColumn('exam_periods','assessment_kind');
        $assessmentKind=strtolower(trim((string)($data['assessment_kind']??($period['assessment_kind']??'school_based'))));
        $nationalCode=isset($data['national_assessment_code'])?strtoupper(trim((string)$data['national_assessment_code']))?:null:(($period['national_assessment_code']??null)?:null);
        $authority=array_key_exists('assessment_authority',$data)?(trim((string)$data['assessment_authority'])?:null):($period['assessment_authority']??null);
        if($extended){
            if(!in_array($assessmentKind,['school_based','national','mock','other'],true))throw new RuntimeException('Choose a valid summative assessment type.',422);
            if($assessmentKind==='national'){
                if(!$authority)$authority='KNEC';
                if(!$nationalCode)throw new RuntimeException('Select the national assessment (KPSEA, KJSEA, or other).',422);
                if(!in_array($nationalCode,['KPSEA','KJSEA','OTHER'],true))throw new RuntimeException('Choose KPSEA, KJSEA, or another national assessment.',422);
            } else { $nationalCode=null; }
        } else {
            $assessmentKind='school_based';$nationalCode=null;$authority=null;
        }

        $entryMode=strtolower(trim((string)($data['entry_mode']??($period['entry_mode']??'timetable'))));
        if(!in_array($entryMode,['timetable','results_only'],true))throw new RuntimeException('Choose a valid result setup mode.',422);

        $classIds=$this->normalizeClassIds($data,$periodId,$extended?$nationalCode:null);
        // Re-run the KNEC SBA exemption gate so widening or re-typing an existing
        // period cannot bring an exempt cohort into scope after creation.
        if ($extended) {
            $this->assertSbaCompliance($classIds, $assessmentKind, (int) $term['academic_year_term_id'], $authority);
            $authority=$this->sbaPolicy()->resolveAuthority($assessmentKind, $authority, (int) ($term['term_id'] ?? 0));
        }
        $classificationId=array_key_exists('assessment_type_classification_id',$data)||array_key_exists('assessment_type_code',$data)
            ?$this->resolveClassificationId($data)
            :(($period['assessment_type_classification_id']??null)?(int)$period['assessment_type_classification_id']:null);
        $currentIds=$this->periodClassIds($periodId);
        $structuralChange=$entryMode!==(string)($period['entry_mode']??'timetable')||$classIds!==$currentIds;

        $this->db->beginTransaction();
        try {
            if($extended){
                $this->db->prepare('UPDATE exam_periods SET title=?,starts_on=?,ends_on=?,assessment_kind=?,assessment_authority=?,national_assessment_code=?,assessment_type_classification_id=? WHERE id=?')
                    ->execute([$title,$starts,$ends,$assessmentKind,$authority,$nationalCode,$classificationId,$periodId]);
            } else {
                $this->db->prepare('UPDATE exam_periods SET title=?,starts_on=?,ends_on=? WHERE id=?')->execute([$title,$starts,$ends,$periodId]);
            }
            if(array_key_exists('entry_mode',$data)&&$entryMode!==(string)($period['entry_mode']??'timetable')){
                $this->db->prepare('UPDATE exam_periods SET entry_mode=? WHERE id=?')->execute([$entryMode,$periodId]);
            }
            if($structuralChange){
                $this->assertNoRecordedResults($periodId,$classIds,$currentIds);
                $this->syncPeriodClasses($periodId,$classIds);
                if($entryMode==='results_only'&&(string)($period['status']??'')==='draft'){
                    $this->writeRecordOnlySittings(['id'=>$periodId,'title'=>$title,'academic_year_term_id'=>(int)$period['academic_year_term_id'],'starts_on'=>$starts]);
                    $this->db->prepare("UPDATE exam_periods SET status='results_open',results_opened_by=?,results_opened_at=NOW() WHERE id=? AND status='draft'")->execute([$this->userId,$periodId]);
                }
            }
            if($nationalCode&&in_array($nationalCode,['KPSEA','KJSEA'],true)){
                $grade=$nationalCode==='KPSEA'?'6':'9';
                foreach($classIds as $classId){
                    if(!$this->classMatchesGrade($classId,$grade))throw new RuntimeException($nationalCode==='KPSEA'?'KPSEA is configured for Grade 6 classes only.':'KJSEA is configured for Grade 9 classes only.',422);
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) { if($this->db->inTransaction()) $this->db->rollBack(); throw $e; }

        $refreshed=$this->period($periodId);
        return [
            'id'=>$periodId,'title'=>$title,'starts_on'=>$starts,'ends_on'=>$ends,
            'assessment_kind'=>$refreshed['assessment_kind']??'school_based',
            'assessment_authority'=>$refreshed['assessment_authority']??null,
            'national_assessment_code'=>$refreshed['national_assessment_code']??null,
            'entry_mode'=>$refreshed['entry_mode']??'timetable',
            'status'=>$refreshed['status']??null,
            'academic_year_term_id'=>(int)$refreshed['academic_year_term_id'],
            'academic_year_class_ids'=>$classIds,
        ];
    }

    /** Resolve and validate the applicable-class set from an update payload. */
    private function normalizeClassIds(array $data, int $periodId, ?string $nationalCode): array
    {
        if (!array_key_exists('academic_year_class_ids', $data) && !array_key_exists('class_ids', $data)) {
            return $this->periodClassIds($periodId);
        }
        $classIds = array_values(array_unique(array_filter(array_map('intval', (array)($data['academic_year_class_ids'] ?? $data['class_ids'] ?? [])))));
        if (!$classIds) throw new RuntimeException('Select at least one applicable class.', 422);
        $period = $this->period($periodId);
        $valid = $this->classesForTerm((int) $period['academic_year_term_id'], $classIds);
        if (count($valid) !== count($classIds)) throw new RuntimeException('One or more selected classes do not belong to this exam period\'s academic year.', 422);
        foreach ($classIds as $classId) {
            $streams = $this->db->prepare("SELECT COUNT(*) FROM academic_year_class_streams WHERE academic_year_class_id=? AND status='active'");
            $streams->execute([$classId]);
            if (!(int) $streams->fetchColumn()) throw new RuntimeException('A selected class has no active streams in this academic year.', 409);
            $areas = $this->db->prepare("SELECT COUNT(*) FROM academic_year_class_learning_areas WHERE academic_year_class_id=? AND status <> 'skipped'");
            $areas->execute([$classId]);
            if (!(int) $areas->fetchColumn()) throw new RuntimeException('A selected class has no configured learning areas.', 409);
        }
        return $classIds;
    }

    /** @return int[] */
    private function periodClassIds(int $periodId): array
    {
        $stmt = $this->db->prepare('SELECT academic_year_class_id FROM exam_period_classes WHERE exam_period_id=? ORDER BY academic_year_class_id');
        $stmt->execute([$periodId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Block a structural change (classes or result-setup mode) when the removed
     * scope already holds learner results, a submitted register, or published
     * marks. Adding a class that is empty is always safe.
     */
    private function assertNoRecordedResults(int $periodId, array $targetIds, array $currentIds): void
    {
        $removed = array_values(array_diff($currentIds, $targetIds));
        if (!$removed) return;
        $marks = implode(',', array_fill(0, count($removed), '?'));
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM " . ReadReplicaService::qualifiedRef("assessment_results") . " ar
             JOIN " . ReadReplicaService::qualifiedRef("assessments") . " a ON a.id = ar.assessment_id
             JOIN exam_schedule_assessments esa ON esa.assessment_id = a.id
             JOIN exam_period_timetable_entries ept ON ept.exam_schedule_id = esa.exam_schedule_id
             JOIN exam_period_class_learning_areas epcla ON epcla.id = ept.exam_period_class_learning_area_id
             JOIN exam_period_classes epc ON epc.id = epcla.exam_period_class_id
             WHERE epc.exam_period_id=? AND epc.academic_year_class_id IN ({$marks})
               AND (ar.deleted_at IS NULL OR a.status IN ('submitted','pending_approval','approved'))"
        );
        $stmt->execute(array_merge([$periodId], $removed));
        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException('One of the classes you removed already has recorded, submitted, or published results. Reopen those results and resolve them first.', 409);
        }
    }

    /** Add the newly selected classes and drop the removed ones with their sittings. */
    private function syncPeriodClasses(int $periodId, array $targetIds): void
    {
        $current = $this->periodClassIds($periodId);
        $addClass = $this->db->prepare('INSERT INTO exam_period_classes (exam_period_id,academic_year_class_id) VALUES (?,?)');
        $addArea = $this->db->prepare('INSERT INTO exam_period_class_learning_areas (exam_period_class_id,academic_year_class_learning_area_id) VALUES (?,?)');
        $lookup = $this->db->prepare("SELECT id FROM academic_year_class_learning_areas WHERE academic_year_class_id=? AND status <> 'skipped' ORDER BY id");
        foreach (array_diff($targetIds, $current) as $classId) {
            $addClass->execute([$periodId, (int) $classId]);
            $periodClassId = (int) $this->db->lastInsertId();
            $lookup->execute([(int) $classId]);
            foreach (array_map('intval', $lookup->fetchAll(PDO::FETCH_COLUMN)) as $areaId) $addArea->execute([$periodClassId, $areaId]);
        }
        foreach (array_diff($current, $targetIds) as $classId) {
            $this->db->prepare(
                'DELETE esa FROM exam_schedule_assessments esa
                 JOIN exam_period_timetable_entries ept ON ept.exam_schedule_id = esa.exam_schedule_id
                 JOIN exam_period_class_learning_areas epcla ON epcla.id = ept.exam_period_class_learning_area_id
                 JOIN exam_period_classes epc ON epc.id = epcla.exam_period_class_id
                 WHERE epc.exam_period_id=? AND epc.academic_year_class_id=?'
            )->execute([$periodId, (int) $classId]);
            $this->db->prepare(
                'DELETE es FROM exam_schedules es
                 JOIN exam_period_timetable_entries ept ON ept.exam_schedule_id = es.id
                 JOIN exam_period_class_learning_areas epcla ON epcla.id = ept.exam_period_class_learning_area_id
                 JOIN exam_period_classes epc ON epc.id = epcla.exam_period_class_id
                 WHERE epc.exam_period_id=? AND epc.academic_year_class_id=? AND es.status <> \'cancelled\''
            )->execute([$periodId, (int) $classId]);
            $this->db->prepare(
                'DELETE epcla FROM exam_period_class_learning_areas epcla
                 JOIN exam_period_classes epc ON epc.id = epcla.exam_period_class_id
                 WHERE epc.exam_period_id=? AND epc.academic_year_class_id=?'
            )->execute([$periodId, (int) $classId]);
            $this->db->prepare('DELETE FROM exam_period_classes WHERE exam_period_id=? AND academic_year_class_id=?')->execute([$periodId, (int) $classId]);
        }
    }

    public function softDelete(int $periodId): array
    {
        $this->period($periodId, true);
        $this->db->prepare('UPDATE exam_periods SET deleted_at=NOW(),deleted_by=? WHERE id=? AND deleted_at IS NULL')->execute([$this->userId,$periodId]);
        return ['id'=>$periodId,'deleted'=>true];
    }

    public function restore(int $periodId): array
    {
        $this->period($periodId, true);
        $this->db->prepare('UPDATE exam_periods SET deleted_at=NULL,deleted_by=NULL WHERE id=?')->execute([$periodId]);
        return ['id'=>$periodId,'restored'=>true];
    }

    public function reopen(int $periodId, string $target): array
    {
        $period = $this->period($periodId, true);
        $target = $target === 'timetable' ? 'draft' : 'results_open';
        if ($target === 'results_open' && $period['status'] === 'results_open') return ['id'=>$periodId,'status'=>'results_open'];
        $allowed = $target === 'draft' ? ['published','results_open','moderation','completed'] : ['published','moderation','completed'];
        $marks = implode(',', array_fill(0,count($allowed),'?'));
        $stmt=$this->db->prepare("UPDATE exam_periods SET status=?,completed_at=NULL WHERE id=? AND status IN ({$marks})");
        $stmt->execute(array_merge([$target,$periodId],$allowed));
        if (!$stmt->rowCount()) throw new RuntimeException('This exam period cannot be reopened from its current state.',409);
        return ['id'=>$periodId,'status'=>$target];
    }

    /** Build nominal completed sittings + stream assessments for a results-only period. */
    private function writeRecordOnlySittings(array $period): int
    {
        $areas=$this->periodAreas((int)$period['id']);
        if(!$areas) throw new RuntimeException('This exam period has no selected class learning areas.',409);
        $normalized=[];
        foreach($areas as $area){
            $area['exam_period_class_learning_area_id']=(int)$area['exam_period_class_learning_area_id'];
            $normalized[]=[
                'area'=>$area,
                'date'=>(string)$period['starts_on'],
                'start'=>'08:00:00',
                'end'=>'09:00:00',
                'max_marks'=>100.0,
                'venue'=>null,'room_id'=>null,'invigilator_id'=>null,'notes'=>'Record-only entry (no published timetable)',
            ];
        }
        return $this->writeSittings($period,$normalized,true,true);
    }

    /**
     * Insert one exam schedule row plus its per-stream result register for every
     * normalized class learning area. Shared by the manual timetable save and the
     * record-only (results-entry-only) creation path.
     *
     * @param bool $skipClashCheck   record-only sittings share a nominal slot, so
     *                               they must not be run through the clash rules.
     * @param bool $skipCalendarSync record-only sittings are not a real timetable,
     *                               so the academic calendar must stay untouched.
     */
    /**
     * Active class streams that actually have learners to assess.
     *
     * A register is a mark sheet for a roster: creating one for a stream with no
     * enrolled learner produces a register that can never be submitted and would
     * therefore block school publication forever.
     *
     * @return int[]
     */
    private function assessableStreamIds(int $academicYearClassId): array
    {
        $stmt=$this->db->prepare("SELECT aycs.id FROM " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs WHERE aycs.academic_year_class_id=? AND aycs.status='active' AND EXISTS (SELECT 1 FROM " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae WHERE sae.academic_year_class_stream_id=aycs.id AND sae.enrollment_status IN ('active','completed')) ORDER BY aycs.id");
        $stmt->execute([$academicYearClassId]);
        return array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function writeSittings(array $period, array $normalized, bool $skipClashCheck = false, bool $skipCalendarSync = false): int
    {
        if(!$skipClashCheck){
            $this->assertNoClashes($normalized);
            $this->assertNoAssignedTeacherClashes($normalized,(int)$period['academic_year_term_id']);
        }
        $typeId=(int)($this->db->query("SELECT id FROM assessment_type_classifications WHERE is_summative=1 AND status='active' ORDER BY (LOWER(name)='end of term exam') DESC,id LIMIT 1")->fetchColumn()?:0);
        if(!$typeId)throw new RuntimeException('No active summative assessment type is configured',409);
        $insertSchedule=$this->db->prepare("INSERT INTO exam_schedules (academic_year_class_id,academic_year_term_id,learning_area_id,max_marks,exam_name,exam_type,exam_date,start_time,end_time,duration_minutes,room_id,venue,invigilator_id,notes,created_by,status,source) VALUES (?,?,?,? ,?,'summative',?,?,?,?,?,?,?,?,?,'scheduled',?)");
        $linkTimetable=$this->db->prepare('INSERT INTO exam_period_timetable_entries (exam_period_class_learning_area_id,exam_schedule_id) VALUES (?,?)');
        $streamCache=[];
        $insertAssessment=$this->db->prepare("INSERT INTO assessments (academic_year_class_stream_id,academic_year_term_id,learning_area_id,assessment_type_classification_id,title,max_marks,assessment_date,assigned_by,status) VALUES (?,?,?,?,?,?,?,?,'pending_submission')");
        $insertAssessmentLink=$this->db->prepare('INSERT INTO exam_schedule_assessments (exam_schedule_id,academic_year_class_stream_id,assessment_id) VALUES (?,?,?)');
        $saved=0;
        foreach($normalized as $item){
            $area=$item['area'];
            $areaId=(int)$area['exam_period_class_learning_area_id'];
            $label=$period['title'].' - '.$area['class_name'].' - '.$area['learning_area_name'];
            $insertSchedule->execute([(int)$area['academic_year_class_id'],(int)$period['academic_year_term_id'],(int)$area['learning_area_id'],$item['max_marks'],$label,$item['date'],$item['start'],$item['end'],$this->duration($item['start'],$item['end']),$item['room_id'],$item['venue'],$item['invigilator_id'],$item['notes'],$this->userId,'manual']);
            $scheduleId=(int)$this->db->lastInsertId();
            $linkTimetable->execute([$areaId,$scheduleId]);
            $classId=(int)$area['academic_year_class_id'];
            if(!isset($streamCache[$classId]))$streamCache[$classId]=$this->assessableStreamIds($classId);
            foreach($streamCache[$classId] as $streamId){
                $insertAssessment->execute([$streamId,(int)$period['academic_year_term_id'],(int)$area['learning_area_id'],$typeId,$label,$item['max_marks'],$item['date'],$this->userId]);
                $insertAssessmentLink->execute([$scheduleId,$streamId,(int)$this->db->lastInsertId()]);
            }
            $saved++;
        }
        return $saved;
    }

    public function saveTimetable(int $periodId,array $entries):array
    {
        $period=$this->period($periodId,true);
        if (!empty($period['deleted_at'])) throw new RuntimeException('Restore this exam period before editing its timetable.',409);
        if (!in_array($period['status'],['draft','published','results_open','moderation','completed'],true)) throw new RuntimeException('This exam period cannot be scheduled in its current state.',409);
        if (!$entries) throw new RuntimeException('Add timetable entries before saving',422);
        $oldDatesStmt=$this->db->prepare('SELECT DISTINCT es.exam_date FROM exam_period_classes epc JOIN exam_period_class_learning_areas epcla ON epcla.exam_period_class_id=epc.id JOIN exam_period_timetable_entries ept ON ept.exam_period_class_learning_area_id=epcla.id JOIN exam_schedules es ON es.id=ept.exam_schedule_id WHERE epc.exam_period_id=? AND es.status<>\'cancelled\'');
        $oldDatesStmt->execute([$periodId]);$previousDates=array_map('strval',$oldDatesStmt->fetchAll(PDO::FETCH_COLUMN));
        $areas=$this->periodAreas($periodId); $byId=[];
        foreach($areas as $area)$byId[(int)$area['exam_period_class_learning_area_id']]=$area;
        $seen=[];$normalized=[];
        foreach($entries as $entry){
            $areaId=(int)($entry['exam_period_class_learning_area_id']??$entry['exam_period_learning_area_id']??0);
            if(!$areaId||!isset($byId[$areaId]))throw new RuntimeException('A timetable row is outside the selected classes and curriculum',422);
            if(isset($seen[$areaId]))throw new RuntimeException('A class learning area has more than one exam sitting',422);$seen[$areaId]=true;
            $date=(string)($entry['exam_date']??'');$start=(string)($entry['start_time']??'');$end=(string)($entry['end_time']??'');$this->date($date);
            if($date<$period['starts_on']||$date>$period['ends_on'])throw new RuntimeException('Every exam sitting must fall within the exam period dates',422);
            if(!preg_match('/^\d{2}:\d{2}(:\d{2})?$/',$start)||!preg_match('/^\d{2}:\d{2}(:\d{2})?$/',$end)||$start>=$end)throw new RuntimeException('Each sitting needs a valid start time and a later end time',422);
            $area=$byId[$areaId];$area['exam_period_class_learning_area_id']=$areaId;
            $normalized[]=['area'=>$area,'date'=>$date,'start'=>$this->time($start),'end'=>$this->time($end),'max_marks'=>max(1,(float)($entry['max_marks']??100)),'venue'=>trim((string)($entry['venue']??''))?:null,'room_id'=>!empty($entry['room_id'])?(int)$entry['room_id']:null,'invigilator_id'=>!empty($entry['invigilator_id'])?(int)$entry['invigilator_id']:null,'notes'=>trim((string)($entry['notes']??''))?:null];
        }
        $this->assertNoClashes($normalized);$this->assertNoExistingClashes($normalized,$periodId);$this->assertNoAssignedTeacherClashes($normalized,(int)$period['academic_year_term_id']);
        $this->db->beginTransaction();
        try{
            $typeId=(int)($this->db->query("SELECT id FROM assessment_type_classifications WHERE is_summative=1 AND status='active' ORDER BY (LOWER(name)='end of term exam') DESC,id LIMIT 1")->fetchColumn()?:0);
            if(!$typeId)throw new RuntimeException('No active summative assessment type is configured',409);
            $existingLink=$this->db->prepare('SELECT exam_schedule_id FROM exam_period_timetable_entries WHERE exam_period_class_learning_area_id=?');
            $insertSchedule=$this->db->prepare("INSERT INTO exam_schedules (academic_year_class_id,academic_year_term_id,learning_area_id,max_marks,exam_name,exam_type,exam_date,start_time,end_time,duration_minutes,room_id,venue,invigilator_id,notes,created_by,status,source) VALUES (?,?,?,? ,?,'summative',?,?,?,?,?,?,?,?,?,'scheduled','manual')");
            $updateSchedule=$this->db->prepare("UPDATE exam_schedules SET exam_name=?,max_marks=?,exam_date=?,start_time=?,end_time=?,duration_minutes=?,room_id=?,venue=?,invigilator_id=?,notes=? WHERE id=? AND status<>'cancelled'");
            $linkTimetable=$this->db->prepare('INSERT INTO exam_period_timetable_entries (exam_period_class_learning_area_id,exam_schedule_id) VALUES (?,?)');
            $streamCache=[];
            $assessmentByStream=$this->db->prepare("SELECT esa.assessment_id FROM exam_schedule_assessments esa WHERE esa.exam_schedule_id=? AND esa.academic_year_class_stream_id=?");
            $insertAssessment=$this->db->prepare("INSERT INTO assessments (academic_year_class_stream_id,academic_year_term_id,learning_area_id,assessment_type_classification_id,title,max_marks,assessment_date,assigned_by,status) VALUES (?,?,?,?,?,?,?,?,'pending_submission')");
            $insertAssessmentLink=$this->db->prepare('INSERT INTO exam_schedule_assessments (exam_schedule_id,academic_year_class_stream_id,assessment_id) VALUES (?,?,?)');
            $updateAssessment=$this->db->prepare("UPDATE assessments SET title=?,max_marks=?,assessment_date=?,assigned_by=? WHERE id=? AND status='pending_submission'");
            $markCount=$this->db->prepare('SELECT COUNT(*) FROM assessment_results WHERE assessment_id=? AND deleted_at IS NULL');
            $saved=0;
            foreach($normalized as $item){
                $area=$item['area'];$label=$period['title'].' - '.$area['class_name'].' - '.$area['learning_area_name'];
                $existingLink->execute([$area['exam_period_class_learning_area_id']]);$scheduleId=(int)($existingLink->fetchColumn()?:0);
                if($scheduleId){
                    $checkResults=$this->db->prepare('SELECT COUNT(*) FROM ' . ReadReplicaService::masterRef('assessment_results') . ' ar JOIN exam_schedule_assessments esa ON esa.assessment_id=ar.assessment_id WHERE esa.exam_schedule_id=? AND ar.deleted_at IS NULL');$checkResults->execute([$scheduleId]);
                    if((int)$checkResults->fetchColumn()>0){
                        $oldMarks=$this->db->prepare('SELECT max_marks FROM exam_schedules WHERE id=?');$oldMarks->execute([$scheduleId]);
                        if((float)$oldMarks->fetchColumn()!==(float)$item['max_marks']) throw new RuntimeException('Changing maximum marks would affect existing learner results. Reopen results and resolve those records first.',409);
                    }
                    $updateSchedule->execute([$label,$item['max_marks'],$item['date'],$item['start'],$item['end'],$this->duration($item['start'],$item['end']),$item['room_id'],$item['venue'],$item['invigilator_id'],$item['notes'],$scheduleId]);
                }else{
                    $insertSchedule->execute([(int)$area['academic_year_class_id'],(int)$period['academic_year_term_id'],(int)$area['learning_area_id'],$item['max_marks'],$label,$item['date'],$item['start'],$item['end'],$this->duration($item['start'],$item['end']),$item['room_id'],$item['venue'],$item['invigilator_id'],$item['notes'],$this->userId]);
                    $scheduleId=(int)$this->db->lastInsertId();$linkTimetable->execute([$area['exam_period_class_learning_area_id'],$scheduleId]);
                }
                $classId=(int)$area['academic_year_class_id'];
                if(!isset($streamCache[$classId]))$streamCache[$classId]=$this->assessableStreamIds($classId);
                foreach($streamCache[$classId] as $streamId){
                    $assessmentByStream->execute([$scheduleId,$streamId]);$assessmentId=(int)($assessmentByStream->fetchColumn()?:0);
                    if($assessmentId){
                        $markCount->execute([$assessmentId]);if((int)$markCount->fetchColumn()>0)throw new RuntimeException('A stream assessment with saved learner results cannot be changed',409);
                        $updateAssessment->execute([$label,$item['max_marks'],$item['date'],$this->userId,$assessmentId]);
                    }else{
                        $insertAssessment->execute([$streamId,(int)$period['academic_year_term_id'],(int)$area['learning_area_id'],$typeId,$label,$item['max_marks'],$item['date'],$this->userId]);
                        $assessmentId=(int)$this->db->lastInsertId();$insertAssessmentLink->execute([$scheduleId,$streamId,$assessmentId]);
                    }
                }
                $saved++;
            }
            $this->syncExamCalendarDays($periodId,$period,$normalized,$previousDates);
            $this->db->commit();return ['saved'=>$saved,'expected'=>count($areas)];
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    private function syncExamCalendarDays(int $periodId,array $period,array $entries,array $previousDates): void
    {
        $newDates=array_values(array_unique(array_map(static fn(array $entry):string=>(string)$entry['date'],$entries)));
        $examType=(int)$this->db->query("SELECT id FROM calendar_day_types WHERE code='exam_day' LIMIT 1")->fetchColumn();
        $schoolType=(int)$this->db->query("SELECT id FROM calendar_day_types WHERE code='school_day' LIMIT 1")->fetchColumn();
        if(!$examType||!$schoolType)throw new RuntimeException('The academic calendar is missing its exam-day or school-day type.',409);
        $find=$this->db->prepare('SELECT d.calendar_day_id AS id,d.day_type_code AS code FROM ' . ReadReplicaService::qualifiedRef('calendar_day_type') . ' d WHERE d.academic_year_term_id=? AND d.calendar_date=? LIMIT 1');
        $update=$this->db->prepare('UPDATE academic_year_calendar_days SET calendar_day_type_id=?,title=?,is_manual=1 WHERE id=?');
        $sync=new CalendarSyncService($this->db);
        foreach($newDates as $date){
            $find->execute([(int)$period['academic_year_term_id'],$date]);$day=$find->fetch(PDO::FETCH_ASSOC);
            if(!$day)throw new RuntimeException("The exam date {$date} is not present in the generated academic calendar.",422);
            if(!in_array((string)$day['code'],['school_day','exam_day'],true))throw new RuntimeException("The exam date {$date} is not a school or exam day in the academic calendar.",422);
            $update->execute([$examType,'Exam day: '.$period['title'],(int)$day['id']]);
            $sync->syncDay((int)$day['id'],false);
        }
        foreach(array_diff(array_unique($previousDates),$newDates) as $date){
            $find->execute([(int)$period['academic_year_term_id'],(string)$date]);$day=$find->fetch(PDO::FETCH_ASSOC);
            if(!$day||(string)$day['code']!=='exam_day')continue;
            $this->db->prepare('UPDATE academic_year_calendar_days SET calendar_day_type_id=?,title=NULL,is_manual=0 WHERE id=? AND title=?')
                ->execute([$schoolType,(int)$day['id'],'Exam day: '.$period['title']]);
            $sync->syncDay((int)$day['id'],false);
        }
    }

    public function publish(int $periodId):array
    {
        $period=$this->period($periodId,true);if(!empty($period['deleted_at']))throw new RuntimeException('Restore this exam period before publishing it.',409);if($period['status']!=='draft')throw new RuntimeException('Only a draft period can be published',409);
        $areas=$this->periodAreas($periodId);$count=$this->db->prepare("SELECT COUNT(*) FROM exam_period_timetable_entries ept JOIN exam_schedules es ON es.id=ept.exam_schedule_id WHERE ept.exam_period_class_learning_area_id IN (SELECT epcla.id FROM exam_period_class_learning_areas epcla JOIN exam_period_classes epc ON epc.id=epcla.exam_period_class_id WHERE epc.exam_period_id=?) AND es.status<>'cancelled'");$count->execute([$periodId]);
        if((int)$count->fetchColumn()!==count($areas))throw new RuntimeException('Schedule every selected class learning area before publishing the timetable',409);
        $this->db->beginTransaction();try{
            $stmt=$this->db->prepare("UPDATE exam_periods SET status='published',published_by=?,published_at=NOW() WHERE id=? AND status='draft'");$stmt->execute([$this->userId,$periodId]);if($stmt->rowCount()!==1)throw new RuntimeException('Exam period changed while publishing; reload and try again',409);
            $this->db->prepare("UPDATE exam_schedules es JOIN exam_period_timetable_entries ept ON ept.exam_schedule_id=es.id JOIN exam_period_class_learning_areas epcla ON epcla.id=ept.exam_period_class_learning_area_id JOIN exam_period_classes epc ON epc.id=epcla.exam_period_class_id SET es.published_by=?,es.published_at=NOW() WHERE epc.exam_period_id=?")->execute([$this->userId,$periodId]);
            $this->db->commit();return ['id'=>$periodId,'status'=>'published'];
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function openResults(int $periodId):array
    {
        $stmt=$this->db->prepare("UPDATE exam_periods SET status='results_open',results_opened_by=?,results_opened_at=NOW() WHERE id=? AND status='published' AND deleted_at IS NULL");$stmt->execute([$this->userId,$periodId]);if($stmt->rowCount()!==1)throw new RuntimeException('Only a published, active exam period can be opened for result entry',409);return ['id'=>$periodId,'status'=>'results_open'];
    }

    /** School Administrator publication gate for reviewed summative registers. */
    public function publishResults(int $periodId): array
    {
        if(!$this->hasColumn('exam_periods','results_published_at')||!$this->hasColumn('exam_periods','assessment_kind')||!$this->hasColumn('assessments','class_review_status'))throw new RuntimeException('The summative-assessment workflow migration has not been applied.',409);
        $period=$this->period($periodId,true);
        if(!empty($period['deleted_at'])) throw new RuntimeException('Restore this exam period before publishing results.',409);
        if(!empty($period['results_published_at'])) throw new RuntimeException('Results have already been published for this exam period.',409);
        $stmt=$this->db->prepare("SELECT DISTINCT a.id,a.status,a.class_review_status,a.academic_year_class_stream_id,a.academic_year_term_id,a.learning_area_id,csd.class_name AS class_name
            FROM exam_period_classes epc
            JOIN exam_period_class_learning_areas epcla ON epcla.exam_period_class_id=epc.id
            JOIN exam_period_timetable_entries ept ON ept.exam_period_class_learning_area_id=epcla.id
            JOIN exam_schedule_assessments esa ON esa.exam_schedule_id=ept.exam_schedule_id
            JOIN assessments a ON a.id=esa.assessment_id
            JOIN " . ReadReplicaService::qualifiedRef("class_stream_directory") . " csd ON csd.id=a.academic_year_class_stream_id WHERE epc.exam_period_id=?");
        $stmt->execute([$periodId]);$assessments=$stmt->fetchAll(PDO::FETCH_ASSOC);
        if(!$assessments) throw new RuntimeException('This exam period has no result registers to publish.',409);
        // Registers for a stream with no enrolled learner can never be submitted, so
        // they must not hold school publication hostage (pre-existing periods).
        $assessments=array_values(array_filter($assessments,function(array $a):bool{
            $roster=$this->db->prepare("SELECT COUNT(*) FROM student_academic_enrollments sae WHERE sae.academic_year_class_stream_id=? AND sae.enrollment_status IN ('active','completed')");
            $roster->execute([(int)$a['academic_year_class_stream_id']]);
            return (int)$roster->fetchColumn()>0;
        }));
        if(!$assessments) throw new RuntimeException('This exam period has no result registers with learners to publish.',409);
        foreach($assessments as $a){
            if($a['status']!=='submitted') throw new RuntimeException('Every learning-area register must be submitted before school publication.',409);
            if(preg_match('/(?:grade|class)\\s*[4-9]\\b/i',(string)$a['class_name'])===1 && $a['class_review_status']!=='approved') throw new RuntimeException('Every Grade 4–9 register must be approved by its class teacher before school publication.',409);
        }
        $this->db->beginTransaction();
        try{
            $assessmentIds=array_map(static fn(array $a):int=>(int)$a['id'],$assessments);
            $marks=implode(',',array_fill(0,count($assessmentIds),'?'));
            $resultsService=new AssessmentResultsService($this->db,$this->userId);
            foreach($assessmentIds as $assessmentId)$resultsService->publishAssessment($assessmentId);
            $staffId=$this->staffId();
            $this->db->prepare("UPDATE assessments SET status='approved',approved_by=?,moderated_by=?,moderated_at=NOW() WHERE id IN ({$marks}) AND status='submitted'")->execute(array_merge([$staffId,$staffId],$assessmentIds));
            $this->db->prepare('UPDATE exam_periods SET results_published_by=?,results_published_at=NOW() WHERE id=? AND results_published_at IS NULL')->execute([$this->userId,$periodId]);
            $seen=[];
            foreach($assessments as $a){$key=(int)$a['academic_year_class_stream_id'].':'.(int)$a['academic_year_term_id'].':'.(int)$a['learning_area_id'];if(isset($seen[$key]))continue;$seen[$key]=true;(new \App\API\Services\TermResultsService($this->db))->compute((int)$a['academic_year_class_stream_id'],(int)$a['academic_year_term_id'],(int)$a['learning_area_id']);}
            $this->db->commit();
            return ['id'=>$periodId,'status'=>'results_published','published_at'=>date('Y-m-d H:i:s'),'registers'=>count($assessments),'official_parent_view'=>'Results are now part of the official term results; generate/release report cards to deliver a corrected PDF by email and result messages through configured parent channels.'];
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function results(int $periodId, bool $includeDeleted = false):array
    {
        if(!$this->isAcademicLeader())throw new RuntimeException('School-wide exam results require academic leadership access',403);
        return ['period'=>$this->period($periodId),'items'=>$this->resultRows($periodId,null,$includeDeleted)];
    }

    public function resultsForTeacher(int $periodId):array
    {
        $staff=$this->db->prepare("SELECT staff_id FROM " . ReadReplicaService::qualifiedRef('staff_directory') . " WHERE user_id=? AND staff_status='active' LIMIT 1");$staff->execute([$this->userId]);$staffId=(int)($staff->fetchColumn()?:0);
        if(!$staffId)throw new RuntimeException('An active staff profile is required to view teaching results',403);
        return ['period'=>$this->period($periodId),'items'=>$this->resultRows($periodId,$staffId)];
    }

private function resultRows(int $periodId,?int $staffId,bool $includeDeleted=false):array
    {
        $ec = ReadReplicaService::qualifiedRef('exam_context');
        $scope = $staffId === null ? '' : " AND (ec.class_teacher_id = ? OR EXISTS (SELECT 1 FROM " . ReadReplicaService::qualifiedRef("academic_year_class_stream_learning_area_teachers") . " WHERE x.academic_year_class_stream_id = ec.aycs_id AND x.academic_year_term_id = ec.academic_year_term_id AND x.learning_area_id = ec.learning_area_id AND x.staff_id = ?) OR EXISTS (SELECT 1 FROM " . ReadReplicaService::qualifiedRef("academic_year_class_learning_area_teachers") . " JOIN academic_year_class_learning_areas cla ON cla.id = legacy.academic_year_class_learning_area_id WHERE cla.academic_year_class_id = ec.academic_year_class_id AND cla.learning_area_id = ec.learning_area_id AND legacy.academic_year_term_id = ec.academic_year_term_id AND legacy.staff_id = ?))";
        $deletedFilter = $includeDeleted ? '' : ' AND ec.result_deleted_at IS NULL';
        $sql = "SELECT ec.exam_schedule_id, ec.exam_date, ec.start_time, ec.end_time, ec.class_name, ec.stream_name, ec.learning_area_name AS learning_area, ec.assessment_id, ec.assessment_status, ec.assessment_max_marks AS max_marks, ec.admission_no, ec.learner_name, ec.enrollment_id, ec.aycs_id AS academic_year_class_stream_id, ec.result_id, ec.result_deleted_at, ec.marks_obtained, ec.grade, ec.entry_status, ec.remarks, ec.is_submitted, ec.is_approved FROM {$ec} ec WHERE ec.exam_period_id = ?" . $scope . $deletedFilter;
        $bindings = [$periodId];
        if ($staffId !== null) {
            array_push($bindings, $staffId, $staffId, $staffId);
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markSubmitted(int $assessmentId):void
    {
        $this->db->prepare("UPDATE exam_periods ep JOIN exam_period_classes epc ON epc.exam_period_id=ep.id JOIN exam_period_class_learning_areas epcla ON epcla.exam_period_class_id=epc.id JOIN exam_period_timetable_entries ept ON ept.exam_period_class_learning_area_id=epcla.id JOIN exam_schedule_assessments esa ON esa.exam_schedule_id=ept.exam_schedule_id SET ep.status='moderation' WHERE esa.assessment_id=? AND ep.status='results_open'")->execute([$assessmentId]);
    }

    public function refreshCompletion(int $assessmentId):void
    {
        $stmt=$this->db->prepare('SELECT ep.id FROM exam_periods ep JOIN exam_period_classes epc ON epc.exam_period_id=ep.id JOIN exam_period_class_learning_areas epcla ON epcla.exam_period_class_id=epc.id JOIN exam_period_timetable_entries ept ON ept.exam_period_class_learning_area_id=epcla.id JOIN exam_schedule_assessments esa ON esa.exam_schedule_id=ept.exam_schedule_id WHERE esa.assessment_id=? LIMIT 1');$stmt->execute([$assessmentId]);$periodId=(int)($stmt->fetchColumn()?:0);if(!$periodId)return;
        $remaining=$this->db->prepare("SELECT COUNT(*) FROM exam_period_classes epc JOIN exam_period_class_learning_areas epcla ON epcla.exam_period_class_id=epc.id JOIN exam_period_timetable_entries ept ON ept.exam_period_class_learning_area_id=epcla.id JOIN exam_schedule_assessments esa ON esa.exam_schedule_id=ept.exam_schedule_id LEFT JOIN " . ReadReplicaService::qualifiedRef("assessments") . " a ON a.id=esa.assessment_id WHERE epc.exam_period_id=? AND (a.id IS NULL OR a.status<>'approved')");$remaining->execute([$periodId]);if((int)$remaining->fetchColumn()===0)$this->db->prepare("UPDATE exam_periods SET status='completed',completed_at=NOW() WHERE id=? AND status IN ('moderation','results_open')")->execute([$periodId]);
    }

    public function periodAccess(int $assessmentId):?string
    {
        $stmt=$this->db->prepare('SELECT ep.status FROM exam_periods ep JOIN exam_period_classes epc ON epc.exam_period_id=ep.id JOIN exam_period_class_learning_areas epcla ON epcla.exam_period_class_id=epc.id JOIN exam_period_timetable_entries ept ON ept.exam_period_class_learning_area_id=epcla.id JOIN exam_schedule_assessments esa ON esa.exam_schedule_id=ept.exam_schedule_id WHERE esa.assessment_id=? LIMIT 1');$stmt->execute([$assessmentId]);$value=$stmt->fetchColumn();return $value===false?null:(string)$value;
    }

    private function periodAreas(int $periodId):array
    {
        $stmt=$this->db->prepare('SELECT epcla.id AS exam_period_class_learning_area_id,acd.id AS academic_year_class_id,acd.class_name AS class_name,cla.id AS academic_year_class_learning_area_id,cla.learning_area_id,la.name AS learning_area_name FROM exam_period_classes epc JOIN ' . ReadReplicaService::qualifiedRef('academic_class_directory') . ' acd ON acd.id=epc.academic_year_class_id JOIN exam_period_class_learning_areas epcla ON epcla.exam_period_class_id=epc.id JOIN ' . ReadReplicaService::qualifiedRef('academic_year_class_learning_areas') . ' cla ON cla.id=epcla.academic_year_class_learning_area_id JOIN ' . ReadReplicaService::qualifiedRef('learning_areas') . ' la ON la.id=cla.learning_area_id WHERE epc.exam_period_id=? ORDER BY acd.class_name,la.name');$stmt->execute([$periodId]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);foreach($rows as &$row)$row['grade_band']=$this->gradeBand((string)$row['class_name']);unset($row);return $rows;
    }

    private function gradeBand(string $className): string
    {
        $name = mb_strtolower(trim($className));
        if (preg_match('/^(playgroup|pp\s*[12]|grade\s*[1-3])\b/u', $name) === 1) return 'early';
        if (preg_match('/^grade\s*[4-9]\b/u', $name) === 1) return 'upper';
        throw new RuntimeException("Class '{$className}' does not match the school's configured Playgroup, PP1–PP2 or Grade 1–9 bands. Update the class name before using AI timetable drafting.", 422);
    }

    private function normalizeAiPlanning(array $input): array
    {
        $times = [];
        foreach ([
            'session_1_start', 'session_1_end',
            'session_2_start', 'session_2_end',
            'session_3_start', 'session_3_end',
        ] as $key) {
            $value = trim((string) ($input[$key] ?? ''));
            if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) throw new RuntimeException('Set the start and end times for all three exam sittings.', 422);
            $times[$key] = $value;
        }
        for ($index = 1; $index <= 3; $index++) {
            if ($times["session_{$index}_start"] >= $times["session_{$index}_end"]) {
                throw new RuntimeException("Sitting {$index} must end after it starts.", 422);
            }
            if ($index < 3 && $times["session_{$index}_end"] > $times['session_' . ($index + 1) . '_start']) {
                throw new RuntimeException('Exam sittings cannot overlap; leave the tea and lunch breaks between sessions.', 422);
            }
        }
        return $times;
    }

    private function examSlots(array $period, array $planning): array
    {
        $days = $this->db->prepare(
            "SELECT day.calendar_date AS date FROM " . ReadReplicaService::qualifiedRef('calendar_day_type') . " day
             WHERE day.academic_year_term_id=? AND day.calendar_date BETWEEN ? AND ?
               AND day.day_type_code IN ('school_day','half_day','exam_day')
             ORDER BY day.calendar_date"
        );
        $days->execute([(int) $period['academic_year_term_id'], (string) $period['starts_on'], (string) $period['ends_on']]);
        $dates = $days->fetchAll(PDO::FETCH_COLUMN);
        $slots = [];
        $slotId = 1;
        foreach ($dates as $date) {
            for ($index = 1; $index <= 3; $index++) {
                $bands = ['upper'];
                if ($index <= 2) $bands[] = 'early';
                $slots[] = [
                    'slot_id' => $slotId++,
                    'date' => (string) $date,
                    'start_time' => $planning["session_{$index}_start"],
                    'end_time' => $planning["session_{$index}_end"],
                    'grade_bands' => $bands,
                ];
            }
        }
        return $slots;
    }

    private function period(int $periodId,bool $lock=false):array
    {
        $stmt=$this->db->prepare('SELECT * FROM exam_periods WHERE id=?'.($lock?' FOR UPDATE':''));$stmt->execute([$periodId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException('Exam period not found',404);return $row;
    }

    /**
     * Resolve the summative exam TYPE (CA / SBA / SA) from request data.
     * Accepts the classification id directly, or a code (CA/SBA/SA) when the
     * frontend sends the label. Null keeps any existing value on update.
     */
    private function resolveClassificationId(array $data):?int
    {
        $raw=$data['assessment_type_classification_id'] ?? $data['assessment_type_code'] ?? null;
        if($raw===null || $raw==='') return null;
        if(!$this->hasColumn('exam_periods','assessment_type_classification_id')) return null;
        if(is_numeric($raw)){
            $stmt=$this->db->prepare("SELECT id FROM assessment_type_classifications WHERE id=? AND status='active'");
            $stmt->execute([(int)$raw]);
            $row=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$row)throw new RuntimeException('Unknown summative assessment type.',422);
            return (int)$row['id'];
        }
        $stmt=$this->db->prepare("SELECT id FROM assessment_type_classifications WHERE code=? AND status='active'");
        $stmt->execute([strtoupper(trim((string)$raw))]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row)throw new RuntimeException('Unknown summative assessment type.',422);
        return (int)$row['id'];
    }

    /**
     * Normalize per-class assessment entitlements from request data:
     *   class_assessment_types: {<academic_year_class_id>: <classification id|CA|SBA|SA>}
     *   class_national_codes:   {<academic_year_class_id>: KPSEA|KJSEA|OTHER}
     * NULL/absent type = inherit the period default. Returns
     * classId => {classification_id, code, national_code}.
     */
    private function classEntitlements(array $data, array $classIds): array
    {
        if(!$this->hasColumn('exam_period_classes','assessment_type_classification_id')) return [];
        $types=$data['class_assessment_types'] ?? $data['class_types'] ?? [];
        $codes=$data['class_national_codes'] ?? [];
        if(!is_array($types) && !is_array($codes)) return [];
        $lookup=$this->db->prepare("SELECT id, code FROM assessment_type_classifications WHERE status='active'");
        $lookup->execute();
        $byId=[]; $byCode=[];
        foreach($lookup->fetchAll(PDO::FETCH_ASSOC) as $row){
            $byId[(int)$row['id']]=$row;
            $byCode[strtoupper((string)$row['code'])]=$row;
        }
        $entitlements=[];
        foreach($classIds as $classId){
            $raw=$types[(string)$classId] ?? $types[$classId] ?? null;
            $code=strtoupper(trim((string)($codes[(string)$classId] ?? $codes[$classId] ?? ''))) ?: null;
            if($raw===null || $raw==='' ){
                if($code) { $entitlements[(int)$classId]=['classification_id'=>null,'code'=>'SA','national_code'=>$code]; }
                continue;
            }
            $row=is_numeric($raw)?($byId[(int)$raw] ?? null):($byCode[strtoupper(trim((string)$raw))] ?? null);
            if(!$row)throw new RuntimeException('Unknown summative assessment type for one of the selected classes.',422);
            $entitlements[(int)$classId]=['classification_id'=>(int)$row['id'],'code'=>strtoupper((string)$row['code']),'national_code'=>null];
        }
        foreach($entitlements as $classId=>$e){
            if(($e['code']??'')==='SA') $entitlements[$classId]['national_code']=$entitlements[$classId]['national_code'] ?? (strtoupper(trim((string)($codes[(string)$classId] ?? ''))) ?: null);
        }
        return $entitlements;
    }

    /**
     * KNEC gates PER CLASS against its EFFECTIVE type (override -> period default):
     *  - CA: allowed everywhere.
     *  - SBA: never on Grade 6 / Grade 9 (exempt cohorts — KPSEA/KJSEA preparation).
     *  - SA + KPSEA: Grade 6 classes only. SA + KJSEA: Grade 9 classes only.
     *  - SA always requires a national assessment code.
     * Classes absent from $entitlements inherit the period default.
     */
    private function assertClassTypeCompliance(array $classIds, array $entitlements, int $termId, ?int $periodDefaultId): void
    {
        $defaultCode=null;
        if($periodDefaultId){
            $stmt=$this->db->prepare("SELECT code FROM assessment_type_classifications WHERE id=?");
            $stmt->execute([$periodDefaultId]);
            $defaultCode=strtoupper(trim((string)$stmt->fetchColumn()));
        }
        $sbaClassIds=[];
        foreach ($classIds as $classId) {
            $e=$entitlements[(int)$classId] ?? null;
            $code=$e['code'] ?? $defaultCode;
            $nationalCode=$e['national_code'] ?? null;
            if($code==='SA'){
                if(!$nationalCode) throw new RuntimeException("A national-exam class requires its national assessment (KPSEA, KJSEA, or other).",422);
                if(!in_array($nationalCode,['KPSEA','KJSEA','OTHER'],true)) throw new RuntimeException('Choose KPSEA, KJSEA, or another national assessment.',422);
                if($nationalCode==='KPSEA' && !$this->classMatchesGrade((int)$classId,'6')) throw new RuntimeException('KPSEA is configured for Grade 6 classes only.',422);
                if($nationalCode==='KJSEA' && !$this->classMatchesGrade((int)$classId,'9')) throw new RuntimeException('KJSEA is configured for Grade 9 classes only.',422);
            }
            if($code==='SBA') $sbaClassIds[]=(int)$classId;
        }
        if($sbaClassIds){
            $report=$this->sbaPolicy()->validateExamPeriod(
                $this->classGrades($sbaClassIds),
                'school_based',
                (int)($this->term($termId)['term_id'] ?? 0),
                'KNEC'
            );
            if(!empty($report['violations'])) throw new RuntimeException((string)$report['violations'][0]['message'],422);
        }
    }

    /**
     * Effective class types for a period: classId => {classification_id, code, national_code}
     * after override -> period-default resolution.
     */
    private function effectiveClassTypes(int $periodId): array
    {
        $period=$this->period($periodId);
        $defaultId=(int)($period['assessment_type_classification_id'] ?? 0) ?: null;
        $stmt=$this->db->prepare(
            "SELECT epc.academic_year_class_id, epc.assessment_type_classification_id,
                    atc.code AS classification_code, epc.national_assessment_code
             FROM exam_period_classes epc
             LEFT JOIN assessment_type_classifications atc ON atc.id=epc.assessment_type_classification_id
             WHERE epc.exam_period_id=? ORDER BY epc.academic_year_class_id"
        );
        $stmt->execute([$periodId]);
        $out=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
            $classId=(int)$row['academic_year_class_id'];
            $out[$classId]=[
                'classification_id'=>$row['assessment_type_classification_id']!==null?(int)$row['assessment_type_classification_id']:$defaultId,
                'code'=>$row['classification_code']!==null?strtoupper((string)$row['classification_code']):null,
                'national_code'=>$row['national_assessment_code']!==null?strtoupper((string)$row['national_assessment_code']):null,
            ];
        }
        return $out;
    }

    private function term(int $termId):array
    {
        $stmt=$this->db->prepare('SELECT *, id AS academic_year_term_id FROM academic_year_terms WHERE id=?');$stmt->execute([$termId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException('Academic-year term not found',404);return $row;
    }
    private function classesForTerm(int $termId,array $ids):array
    {
        if(!$ids)return [];$term=$this->term($termId);$marks=implode(',',array_fill(0,count($ids),'?'));$stmt=$this->db->prepare("SELECT ayc.id FROM academic_year_classes ayc WHERE ayc.academic_year_id=? AND ayc.id IN ({$marks})");$stmt->execute(array_merge([(int)$term['academic_year_id']],$ids));return array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    }
    private function assertNoClashes(array $items):void
    {
        for($i=0;$i<count($items);$i++)for($j=$i+1;$j<count($items);$j++){$a=$items[$i];$b=$items[$j];if($a['date']!==$b['date']||$a['start']>=$b['end']||$b['start']>=$a['end'])continue;if((int)$a['area']['academic_year_class_id']===(int)$b['area']['academic_year_class_id'])throw new RuntimeException('A class has overlapping exam sittings',422);if($a['room_id']&&$a['room_id']===$b['room_id'])throw new RuntimeException('A room is assigned to overlapping exam sittings',422);if(!$a['room_id']&&!$b['room_id']&&$a['venue']&&$b['venue']&&mb_strtolower($a['venue'])===mb_strtolower($b['venue']))throw new RuntimeException('A venue is assigned to overlapping exam sittings',422);if($a['invigilator_id']&&$a['invigilator_id']===$b['invigilator_id'])throw new RuntimeException('An invigilator is assigned to overlapping exam sittings',422);}
    }
    /** Ensure assigned learning-area teachers are not placed in simultaneous papers. */
    private function assertNoAssignedTeacherClashes(array $items, int $termId): void
    {
        $streamStmt = $this->db->prepare("SELECT class_stream_id AS id,class_teacher_id,COALESCE(stream_name,CONCAT('stream ',class_stream_id)) AS stream_name FROM " . ReadReplicaService::qualifiedRef('academic_calendar') . " WHERE academic_year_class_id=? AND class_stream_status='active'");
        $streamTeachers = $this->db->prepare(
            "SELECT DISTINCT x.staff_id FROM " . ReadReplicaService::qualifiedRef("academic_year_class_stream_learning_area_teachers") . "
             JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_stream_learning_areas") . " sla ON sla.id=x.academic_year_class_stream_learning_area_id
              AND sla.academic_year_class_stream_id=x.academic_year_class_stream_id
              AND sla.academic_year_class_learning_area_id=?
             WHERE x.academic_year_class_stream_id=? AND x.academic_year_term_id=?
               AND x.learning_area_id=? AND x.status='active'"
        );
        $classTeachers = $this->db->prepare(
            'SELECT staff_id FROM academic_year_class_learning_area_teachers WHERE academic_year_class_learning_area_id=? AND academic_year_term_id=?'
        );
        $teachersByItem = [];
        foreach ($items as $index => $item) {
            $area = $item['area'];
            $areaId = (int) $area['academic_year_class_learning_area_id'];
            $teachers = [];
            $classTeachers->execute([$areaId, $termId]);
            foreach ($classTeachers->fetchAll(PDO::FETCH_COLUMN) as $teacherId) $teachers[(int) $teacherId] = true;
            $streamStmt->execute([(int) $area['academic_year_class_id']]);
            $streams = $streamStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$streams) throw new RuntimeException('Every scheduled exam class must have active streams.', 409);
            foreach ($streams as $stream) {
                $streamTeachers->execute([$areaId, (int) $stream['id'], $termId, (int) $area['learning_area_id']]);
                $assigned = array_map('intval', $streamTeachers->fetchAll(PDO::FETCH_COLUMN));
                if ($assigned) {
                    foreach ($assigned as $teacherId) $teachers[$teacherId] = true;
                } elseif ($teachers) {
                    // A class-level assignment is valid for each parallel stream.
                } elseif (($area['grade_band'] ?? $this->gradeBand((string) $area['class_name'])) === 'early' && !empty($stream['class_teacher_id'])) {
                    $teachers[(int) $stream['class_teacher_id']] = true;
                } else {
                    throw new RuntimeException('Assign a responsible teacher to ' . $area['class_name'] . ' · ' . $area['learning_area_name'] . ' · ' . $stream['stream_name'] . ' before saving the timetable.', 409);
                }
            }
            if (!$teachers) throw new RuntimeException('Assign a responsible teacher to every scheduled learning area before saving the timetable.', 409);
            $teachersByItem[$index] = array_keys($teachers);
        }
        for ($i = 0, $count = count($items); $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $items[$i]; $b = $items[$j];
                if ($a['date'] !== $b['date'] || $a['start'] >= $b['end'] || $b['start'] >= $a['end']) continue;
                if (array_intersect($teachersByItem[$i], $teachersByItem[$j])) {
                    throw new RuntimeException('An assigned learning-area teacher cannot supervise overlapping exam sittings.', 422);
                }
            }
        }
    }
    private function assertNoExistingClashes(array $items,int $periodId):void
    {
        $stmt=$this->db->prepare("SELECT es.id,es.academic_year_class_id,es.exam_date,es.start_time,es.end_time,es.room_id,es.venue,es.invigilator_id FROM " . ReadReplicaService::qualifiedRef("exam_schedules") . " es WHERE es.status<>'cancelled' AND NOT EXISTS (SELECT 1 FROM exam_period_timetable_entries ept JOIN exam_period_class_learning_areas epcla ON epcla.id=ept.exam_period_class_learning_area_id JOIN exam_period_classes epc ON epc.id=epcla.exam_period_class_id WHERE ept.exam_schedule_id=es.id AND epc.exam_period_id=?)");$stmt->execute([$periodId]);$existingRows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($items as $item)foreach($existingRows as $existing){if((string)$existing['exam_date']!==$item['date']||(string)$item['start']>=(string)$existing['end_time']||(string)$existing['start_time']>=(string)$item['end'])continue;if((int)$existing['academic_year_class_id']===(int)$item['area']['academic_year_class_id'])throw new RuntimeException('This class already has an overlapping exam sitting',422);if($item['room_id']&&(int)$existing['room_id']===(int)$item['room_id'])throw new RuntimeException('This room is already assigned to an overlapping exam sitting',422);if(!$item['room_id']&&empty($existing['room_id'])&&$item['venue']&&$existing['venue']&&mb_strtolower($item['venue'])===mb_strtolower((string)$existing['venue']))throw new RuntimeException('This venue is already assigned to an overlapping exam sitting',422);if($item['invigilator_id']&&(int)$existing['invigilator_id']===(int)$item['invigilator_id'])throw new RuntimeException('This invigilator already has an overlapping exam sitting',422);}
    }
    private function isAcademicLeader():bool
    {
        $stmt=$this->db->prepare('SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=? AND r.is_active=1');$stmt->execute([$this->userId]);foreach($stmt->fetchAll(PDO::FETCH_COLUMN)as $role)if(preg_match('/system administrator|school administrator|headteacher|deputy head/i',(string)$role))return true;return false;
    }
    private function date(string $value):DateTimeImmutable
    {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);if(!$date||$date->format('Y-m-d')!==$value)throw new RuntimeException('Use a valid calendar date',422);return $date;
    }
    private function time(string $value):string{return strlen($value)===5?$value.':00':$value;}
    private function duration(string $start,string $end):int{return(int)((strtotime('1970-01-01 '.$end)-strtotime('1970-01-01 '.$start))/60);}
}
