<?php
declare(strict_types=1);

namespace App\API\Services;

use DateTimeImmutable;
use PDO;
use RuntimeException;

/** Class-level exam sittings with stream-specific result registers. */
final class ExamPeriodService
{
    private PDO $db;
    private int $userId;

    public function __construct(PDO $db, int $userId)
    {
        $this->db = $db;
        $this->userId = $userId;
    }

    public function options(?int $termId = null): array
    {
        $terms = $this->db->query(
            "SELECT ayt.id, ayt.academic_year_id, ay.year_name AS academic_year_name, ayt.term_id,
                    ayt.opening_date, ayt.closing_date, ayt.status
             FROM academic_year_terms ayt JOIN academic_years ay ON ay.id=ayt.academic_year_id
             ORDER BY ay.id DESC, ayt.term_id"
        )->fetchAll(PDO::FETCH_ASSOC);
        if (!$termId && $terms) {
            foreach ($terms as $candidate) if ($candidate['status'] === 'current') { $termId=(int)$candidate['id']; break; }
            $termId ??= (int)$terms[0]['id'];
        }
        if (!$termId) return ['terms'=>$terms, 'selected_term'=>null, 'classes'=>[]];
        $term = $this->term($termId);
        $stmt = $this->db->prepare(
            "SELECT ayc.id AS academic_year_class_id, c.name AS class_name
             FROM academic_year_classes ayc JOIN classes c ON c.id=ayc.class_id
             WHERE ayc.academic_year_id=?
               AND EXISTS (SELECT 1 FROM academic_year_class_streams aycs WHERE aycs.academic_year_class_id=ayc.id AND aycs.status='active')
             ORDER BY c.name"
        );
        $stmt->execute([(int)$term['academic_year_id']]);
        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $areasStmt = $this->db->prepare(
            "SELECT cla.id AS academic_year_class_learning_area_id, cla.academic_year_class_id,
                    cla.learning_area_id, la.name AS learning_area_name
             FROM academic_year_class_learning_areas cla JOIN learning_areas la ON la.id=cla.learning_area_id
             WHERE cla.academic_year_class_id IN (
                 SELECT id FROM academic_year_classes WHERE academic_year_id=?
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

    public function list(): array
    {
        return $this->db->query(
            "SELECT ep.id, ep.title, ep.academic_year_term_id, ayt.term_id, ay.year_name AS academic_year_name,
                    ep.starts_on, ep.ends_on, ep.status, ep.created_at, ep.published_at, ep.results_opened_at,
                    COUNT(DISTINCT epc.id) AS class_count,
                    COUNT(DISTINCT epcla.id) AS learning_area_count,
                    COUNT(DISTINCT CASE WHEN es.status <> 'cancelled' THEN ept.id END) AS scheduled_count,
                    COUNT(DISTINCT CASE WHEN a.status IN ('submitted','pending_approval') THEN esa.assessment_id END) AS submitted_count,
                    COUNT(DISTINCT CASE WHEN a.status='approved' THEN esa.assessment_id END) AS approved_count
             FROM exam_periods ep
             JOIN academic_year_terms ayt ON ayt.id=ep.academic_year_term_id
             JOIN academic_years ay ON ay.id=ayt.academic_year_id
             LEFT JOIN exam_period_classes epc ON epc.exam_period_id=ep.id
             LEFT JOIN exam_period_class_learning_areas epcla ON epcla.exam_period_class_id=epc.id
             LEFT JOIN exam_period_timetable_entries ept ON ept.exam_period_class_learning_area_id=epcla.id
             LEFT JOIN exam_schedules es ON es.id=ept.exam_schedule_id
             LEFT JOIN exam_schedule_assessments esa ON esa.exam_schedule_id=es.id
             LEFT JOIN assessments a ON a.id=esa.assessment_id
             GROUP BY ep.id, ayt.term_id, ay.year_name ORDER BY ep.created_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function detail(int $periodId): array
    {
        $period=$this->period($periodId);
        $stmt=$this->db->prepare(
            "SELECT epcla.id AS exam_period_class_learning_area_id, ayc.id AS academic_year_class_id,
                    c.name AS class_name, la.id AS learning_area_id, la.name AS learning_area_name,
                    es.id AS exam_schedule_id, es.exam_date, es.start_time, es.end_time, es.max_marks,
                    es.venue, es.room_id, es.invigilator_id, es.notes,
                    (SELECT COUNT(*) FROM exam_schedule_assessments esa WHERE esa.exam_schedule_id=es.id) AS stream_assessment_count
             FROM exam_period_classes epc
             JOIN academic_year_classes ayc ON ayc.id=epc.academic_year_class_id
             JOIN classes c ON c.id=ayc.class_id
             JOIN exam_period_class_learning_areas epcla ON epcla.exam_period_class_id=epc.id
             JOIN academic_year_class_learning_areas cla ON cla.id=epcla.academic_year_class_learning_area_id
             JOIN learning_areas la ON la.id=cla.learning_area_id
             LEFT JOIN exam_period_timetable_entries ept ON ept.exam_period_class_learning_area_id=epcla.id
             LEFT JOIN exam_schedules es ON es.id=ept.exam_schedule_id
             WHERE epc.exam_period_id=? ORDER BY c.name,la.name"
        );
        $stmt->execute([$periodId]);
        return ['period'=>$period,'entries'=>$stmt->fetchAll(PDO::FETCH_ASSOC)];
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
        $streamStmt = $this->db->prepare("SELECT aycs.id, aycs.class_teacher_id, COALESCE(s.name, CONCAT('stream ', aycs.id)) AS stream_name FROM academic_year_class_streams aycs LEFT JOIN streams s ON s.id=aycs.stream_id WHERE aycs.academic_year_class_id=? AND aycs.status='active' ORDER BY aycs.id");
        $streamTeachers = $this->db->prepare(
            "SELECT DISTINCT x.staff_id FROM academic_year_class_stream_learning_area_teachers x
             JOIN academic_year_class_stream_learning_areas sla ON sla.id=x.academic_year_class_stream_learning_area_id
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
        $term = $this->db->prepare('SELECT ayt.term_id, ay.year_name AS academic_year_name FROM academic_year_terms ayt JOIN academic_years ay ON ay.id=ayt.academic_year_id WHERE ayt.id=?');
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
        $classIds=array_values(array_unique(array_filter(array_map('intval',(array)($data['academic_year_class_ids']??$data['class_ids']??[])))));
        if (!$termId || $title==='' || !$starts || !$ends || !$classIds) throw new RuntimeException('Term, exam name, dates, and at least one class are required',422);
        if ($this->date($starts)>$this->date($ends)) throw new RuntimeException('The exam period end date must be on or after its start date',422);
        $term=$this->term($termId);
        if (!empty($term['opening_date']) && $starts<(string)$term['opening_date']) throw new RuntimeException('The exam period starts before the selected term',422);
        if (!empty($term['closing_date']) && $ends>(string)$term['closing_date']) throw new RuntimeException('The exam period ends after the selected term',422);
        $valid=$this->classesForTerm($termId,$classIds);
        if (count($valid)!==count($classIds)) throw new RuntimeException('One or more selected classes do not belong to the selected academic year',422);
        $this->db->beginTransaction();
        try {
            $insert=$this->db->prepare("INSERT INTO exam_periods (academic_year_term_id,title,starts_on,ends_on,status,created_by) VALUES (?,?,?,?,'draft',?)");
            $insert->execute([$termId,$title,$starts,$ends,$this->userId]); $periodId=(int)$this->db->lastInsertId();
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
            $this->db->commit();
            return ['id'=>$periodId,'status'=>'draft','classes'=>count($classIds)];
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    public function saveTimetable(int $periodId,array $entries):array
    {
        $period=$this->period($periodId,true);
        if ($period['status']!=='draft') throw new RuntimeException('Only a draft exam period can be scheduled',409);
        if (!$entries) throw new RuntimeException('Add timetable entries before saving',422);
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
            $typeId=(int)($this->db->query("SELECT id FROM assessment_types WHERE is_summative=1 AND status='active' ORDER BY (LOWER(name)='end of term exam') DESC,id LIMIT 1")->fetchColumn()?:0);
            if(!$typeId)throw new RuntimeException('No active summative assessment type is configured',409);
            $existingLink=$this->db->prepare('SELECT exam_schedule_id FROM exam_period_timetable_entries WHERE exam_period_class_learning_area_id=?');
            $insertSchedule=$this->db->prepare("INSERT INTO exam_schedules (academic_year_class_id,academic_year_term_id,learning_area_id,max_marks,exam_name,exam_type,exam_date,start_time,end_time,duration_minutes,room_id,venue,invigilator_id,notes,created_by,status,source) VALUES (?,?,?,? ,?,'summative',?,?,?,?,?,?,?,?,?,'scheduled','manual')");
            $updateSchedule=$this->db->prepare("UPDATE exam_schedules SET exam_name=?,max_marks=?,exam_date=?,start_time=?,end_time=?,duration_minutes=?,room_id=?,venue=?,invigilator_id=?,notes=? WHERE id=? AND status<>'cancelled'");
            $linkTimetable=$this->db->prepare('INSERT INTO exam_period_timetable_entries (exam_period_class_learning_area_id,exam_schedule_id) VALUES (?,?)');
            $streamStmt=$this->db->prepare("SELECT id FROM academic_year_class_streams WHERE academic_year_class_id=? AND status='active' ORDER BY id");
            $assessmentByStream=$this->db->prepare("SELECT esa.assessment_id FROM exam_schedule_assessments esa WHERE esa.exam_schedule_id=? AND esa.academic_year_class_stream_id=?");
            $insertAssessment=$this->db->prepare("INSERT INTO assessments (academic_year_class_stream_id,academic_year_term_id,learning_area_id,assessment_type_id,title,max_marks,assessment_date,assigned_by,status) VALUES (?,?,?,?,?,?,?,?,'pending_submission')");
            $insertAssessmentLink=$this->db->prepare('INSERT INTO exam_schedule_assessments (exam_schedule_id,academic_year_class_stream_id,assessment_id) VALUES (?,?,?)');
            $updateAssessment=$this->db->prepare("UPDATE assessments SET title=?,max_marks=?,assessment_date=?,assigned_by=? WHERE id=? AND status='pending_submission'");
            $markCount=$this->db->prepare('SELECT COUNT(*) FROM assessment_results WHERE assessment_id=?');
            $saved=0;
            foreach($normalized as $item){
                $area=$item['area'];$label=$period['title'].' - '.$area['class_name'].' - '.$area['learning_area_name'];
                $existingLink->execute([$area['exam_period_class_learning_area_id']]);$scheduleId=(int)($existingLink->fetchColumn()?:0);
                if($scheduleId){
                    $checkResults=$this->db->prepare('SELECT COUNT(*) FROM assessment_results ar JOIN exam_schedule_assessments esa ON esa.assessment_id=ar.assessment_id WHERE esa.exam_schedule_id=?');$checkResults->execute([$scheduleId]);
                    if((int)$checkResults->fetchColumn()>0)throw new RuntimeException('A timetable entry with saved learner results cannot be replaced',409);
                    $updateSchedule->execute([$label,$item['max_marks'],$item['date'],$item['start'],$item['end'],$this->duration($item['start'],$item['end']),$item['room_id'],$item['venue'],$item['invigilator_id'],$item['notes'],$scheduleId]);
                }else{
                    $insertSchedule->execute([(int)$area['academic_year_class_id'],(int)$period['academic_year_term_id'],(int)$area['learning_area_id'],$item['max_marks'],$label,$item['date'],$item['start'],$item['end'],$this->duration($item['start'],$item['end']),$item['room_id'],$item['venue'],$item['invigilator_id'],$item['notes'],$this->userId]);
                    $scheduleId=(int)$this->db->lastInsertId();$linkTimetable->execute([$area['exam_period_class_learning_area_id'],$scheduleId]);
                }
                $streamStmt->execute([(int)$area['academic_year_class_id']]);
                foreach(array_map('intval',$streamStmt->fetchAll(PDO::FETCH_COLUMN)) as $streamId){
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
            $this->db->commit();return ['saved'=>$saved,'expected'=>count($areas)];
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function publish(int $periodId):array
    {
        $period=$this->period($periodId,true);if($period['status']!=='draft')throw new RuntimeException('Only a draft period can be published',409);
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
        $stmt=$this->db->prepare("UPDATE exam_periods SET status='results_open',results_opened_by=?,results_opened_at=NOW() WHERE id=? AND status='published'");$stmt->execute([$this->userId,$periodId]);if($stmt->rowCount()!==1)throw new RuntimeException('Only a published exam period can be opened for result entry',409);return ['id'=>$periodId,'status'=>'results_open'];
    }

    public function results(int $periodId):array
    {
        if(!$this->isAcademicLeader())throw new RuntimeException('School-wide exam results require academic leadership access',403);
        return ['period'=>$this->period($periodId),'items'=>$this->resultRows($periodId,null)];
    }

    public function resultsForTeacher(int $periodId):array
    {
        $staff=$this->db->prepare("SELECT s.id FROM staff s JOIN users u ON u.person_id=s.person_id WHERE u.id=? AND s.status='active' LIMIT 1");$staff->execute([$this->userId]);$staffId=(int)($staff->fetchColumn()?:0);
        if(!$staffId)throw new RuntimeException('An active staff profile is required to view teaching results',403);
        return ['period'=>$this->period($periodId),'items'=>$this->resultRows($periodId,$staffId)];
    }

    private function resultRows(int $periodId,?int $staffId):array
    {
        $scope=$staffId===null?'':' AND (aycs.class_teacher_id=? OR EXISTS (SELECT 1 FROM academic_year_class_stream_learning_area_teachers x WHERE x.academic_year_class_stream_id=aycs.id AND x.academic_year_term_id=ep.academic_year_term_id AND x.learning_area_id=es.learning_area_id AND x.staff_id=?) OR EXISTS (SELECT 1 FROM academic_year_class_learning_area_teachers legacy JOIN academic_year_class_learning_areas cla ON cla.id=legacy.academic_year_class_learning_area_id WHERE cla.academic_year_class_id=ayc.id AND cla.learning_area_id=es.learning_area_id AND legacy.academic_year_term_id=ep.academic_year_term_id AND legacy.staff_id=?))';
        $stmt=$this->db->prepare("SELECT es.id AS exam_schedule_id,es.exam_date,es.start_time,es.end_time,c.name AS class_name,sn.name AS stream_name,la.name AS learning_area,a.id AS assessment_id,a.status AS assessment_status,a.max_marks,s.admission_no,CONCAT_WS(' ',p.first_name,p.middle_name,p.last_name) AS learner_name,ar.marks_obtained,ar.grade,ar.entry_status,ar.is_submitted,ar.is_approved FROM exam_periods ep JOIN academic_year_terms ayt ON ayt.id=ep.academic_year_term_id JOIN exam_period_classes epc ON epc.exam_period_id=ep.id JOIN academic_year_classes ayc ON ayc.id=epc.academic_year_class_id JOIN classes c ON c.id=ayc.class_id JOIN exam_period_class_learning_areas epcla ON epcla.exam_period_class_id=epc.id JOIN academic_year_class_learning_areas cla ON cla.id=epcla.academic_year_class_learning_area_id JOIN learning_areas la ON la.id=cla.learning_area_id JOIN exam_period_timetable_entries ept ON ept.exam_period_class_learning_area_id=epcla.id JOIN exam_schedules es ON es.id=ept.exam_schedule_id JOIN exam_schedule_assessments esa ON esa.exam_schedule_id=es.id JOIN assessments a ON a.id=esa.assessment_id JOIN academic_year_class_streams aycs ON aycs.id=esa.academic_year_class_stream_id AND aycs.academic_year_class_id=ayc.id LEFT JOIN streams sn ON sn.id=aycs.stream_id JOIN student_academic_enrollments sae ON sae.academic_year_class_stream_id=aycs.id AND sae.academic_year_id=ayt.academic_year_id JOIN students s ON s.id=sae.student_id JOIN persons p ON p.id=s.person_id LEFT JOIN assessment_results ar ON ar.assessment_id=a.id AND ar.student_academic_enrollment_id=sae.id WHERE ep.id=?{$scope} ORDER BY c.name,sn.name,la.name,p.first_name,p.last_name");
        $bindings=[$periodId];if($staffId!==null)array_push($bindings,$staffId,$staffId,$staffId);$stmt->execute($bindings);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markSubmitted(int $assessmentId):void
    {
        $this->db->prepare("UPDATE exam_periods ep JOIN exam_period_classes epc ON epc.exam_period_id=ep.id JOIN exam_period_class_learning_areas epcla ON epcla.exam_period_class_id=epc.id JOIN exam_period_timetable_entries ept ON ept.exam_period_class_learning_area_id=epcla.id JOIN exam_schedule_assessments esa ON esa.exam_schedule_id=ept.exam_schedule_id SET ep.status='moderation' WHERE esa.assessment_id=? AND ep.status='results_open'")->execute([$assessmentId]);
    }

    public function refreshCompletion(int $assessmentId):void
    {
        $stmt=$this->db->prepare('SELECT ep.id FROM exam_periods ep JOIN exam_period_classes epc ON epc.exam_period_id=ep.id JOIN exam_period_class_learning_areas epcla ON epcla.exam_period_class_id=epc.id JOIN exam_period_timetable_entries ept ON ept.exam_period_class_learning_area_id=epcla.id JOIN exam_schedule_assessments esa ON esa.exam_schedule_id=ept.exam_schedule_id WHERE esa.assessment_id=? LIMIT 1');$stmt->execute([$assessmentId]);$periodId=(int)($stmt->fetchColumn()?:0);if(!$periodId)return;
        $remaining=$this->db->prepare("SELECT COUNT(*) FROM exam_period_classes epc JOIN exam_period_class_learning_areas epcla ON epcla.exam_period_class_id=epc.id JOIN exam_period_timetable_entries ept ON ept.exam_period_class_learning_area_id=epcla.id JOIN exam_schedule_assessments esa ON esa.exam_schedule_id=ept.exam_schedule_id LEFT JOIN assessments a ON a.id=esa.assessment_id WHERE epc.exam_period_id=? AND (a.id IS NULL OR a.status<>'approved')");$remaining->execute([$periodId]);if((int)$remaining->fetchColumn()===0)$this->db->prepare("UPDATE exam_periods SET status='completed',completed_at=NOW() WHERE id=? AND status IN ('moderation','results_open')")->execute([$periodId]);
    }

    public function periodAccess(int $assessmentId):?string
    {
        $stmt=$this->db->prepare('SELECT ep.status FROM exam_periods ep JOIN exam_period_classes epc ON epc.exam_period_id=ep.id JOIN exam_period_class_learning_areas epcla ON epcla.exam_period_class_id=epc.id JOIN exam_period_timetable_entries ept ON ept.exam_period_class_learning_area_id=epcla.id JOIN exam_schedule_assessments esa ON esa.exam_schedule_id=ept.exam_schedule_id WHERE esa.assessment_id=? LIMIT 1');$stmt->execute([$assessmentId]);$value=$stmt->fetchColumn();return $value===false?null:(string)$value;
    }

    private function periodAreas(int $periodId):array
    {
        $stmt=$this->db->prepare('SELECT epcla.id AS exam_period_class_learning_area_id,ayc.id AS academic_year_class_id,c.name AS class_name,cla.id AS academic_year_class_learning_area_id,cla.learning_area_id,la.name AS learning_area_name FROM exam_period_classes epc JOIN academic_year_classes ayc ON ayc.id=epc.academic_year_class_id JOIN classes c ON c.id=ayc.class_id JOIN exam_period_class_learning_areas epcla ON epcla.exam_period_class_id=epc.id JOIN academic_year_class_learning_areas cla ON cla.id=epcla.academic_year_class_learning_area_id JOIN learning_areas la ON la.id=cla.learning_area_id WHERE epc.exam_period_id=? ORDER BY c.name,la.name');$stmt->execute([$periodId]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);foreach($rows as &$row)$row['grade_band']=$this->gradeBand((string)$row['class_name']);unset($row);return $rows;
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
            "SELECT day.date FROM academic_year_calendar_days day
             JOIN academic_year_calendar cal ON cal.id=day.academic_year_calendar_id
             LEFT JOIN calendar_day_types type ON type.id=day.calendar_day_type_id
             WHERE cal.academic_year_term_id=? AND day.date BETWEEN ? AND ?
               AND COALESCE(type.code,'school_day') IN ('school_day','half_day','exam_day')
             ORDER BY day.date"
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
    private function term(int $termId):array
    {
        $stmt=$this->db->prepare('SELECT * FROM academic_year_terms WHERE id=?');$stmt->execute([$termId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException('Academic-year term not found',404);return $row;
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
        $streamStmt = $this->db->prepare("SELECT aycs.id,aycs.class_teacher_id,COALESCE(s.name,CONCAT('stream ',aycs.id)) AS stream_name FROM academic_year_class_streams aycs LEFT JOIN streams s ON s.id=aycs.stream_id WHERE aycs.academic_year_class_id=? AND aycs.status='active'");
        $streamTeachers = $this->db->prepare(
            "SELECT DISTINCT x.staff_id FROM academic_year_class_stream_learning_area_teachers x
             JOIN academic_year_class_stream_learning_areas sla ON sla.id=x.academic_year_class_stream_learning_area_id
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
        $stmt=$this->db->prepare("SELECT es.id,es.academic_year_class_id,es.exam_date,es.start_time,es.end_time,es.room_id,es.venue,es.invigilator_id FROM exam_schedules es WHERE es.status<>'cancelled' AND NOT EXISTS (SELECT 1 FROM exam_period_timetable_entries ept JOIN exam_period_class_learning_areas epcla ON epcla.id=ept.exam_period_class_learning_area_id JOIN exam_period_classes epc ON epc.id=epcla.exam_period_class_id WHERE ept.exam_schedule_id=es.id AND epc.exam_period_id=?)");$stmt->execute([$periodId]);$existingRows=$stmt->fetchAll(PDO::FETCH_ASSOC);
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
