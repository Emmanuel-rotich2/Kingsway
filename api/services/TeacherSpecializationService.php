<?php
namespace App\API\Services;

use PDO;
use RuntimeException;

/** Qualification-backed teaching eligibility. Assignment and timetable facts remain separate. */
final class TeacherSpecializationService
{
    public function __construct(private PDO $db) {}

    private function rows(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function value(string $sql, array $params = []): mixed
    {
        $stmt = $this->db->prepare($sql); $stmt->execute($params); return $stmt->fetchColumn();
    }

    public function list(array $filters = []): array
    {
        $where = ['1=1']; $params = [];
        foreach (['staff_id'=>'s.staff_id','learning_area_id'=>'s.learning_area_id','status'=>'s.status'] as $key => $column) {
            if (isset($filters[$key]) && $filters[$key] !== '') { $where[] = "$column = ?"; $params[] = (int)$filters[$key]; }
        }
        return $this->rows("SELECT s.id, s.staff_id, s.learning_area_id, s.specialization_level,
                    s.is_primary, s.status, s.notes, s.created_by, s.approved_by, s.approved_at,
                    s.effective_from, s.effective_to, la.name learning_area_name, la.code learning_area_code,
                    CONCAT_WS(' ',p.first_name,p.middle_name,p.last_name) teacher_name,
                    GROUP_CONCAT(DISTINCT q.id ORDER BY q.id SEPARATOR ',') qualification_ids,
                    GROUP_CONCAT(DISTINCT q.title ORDER BY q.id SEPARATOR ' | ') qualification_titles
               FROM staff_learning_area_specializations s
               JOIN staff st ON st.id=s.staff_id JOIN persons p ON p.id=st.person_id
               JOIN learning_areas la ON la.id=s.learning_area_id
               LEFT JOIN staff_specialization_qualifications sq ON sq.specialization_id=s.id
               LEFT JOIN staff_qualifications q ON q.id=sq.qualification_id
              WHERE " . implode(' AND ', $where) . " GROUP BY s.id ORDER BY teacher_name,learning_area_name,s.id", $params);
    }

    public function save(array $data, int $actorId): int
    {
        $staffId = (int)($data['staff_id'] ?? 0); $areaId = (int)($data['learning_area_id'] ?? 0);
        if (!$staffId || !$areaId) throw new RuntimeException('staff_id and learning_area_id are required', 422);
        $level = (string)($data['specialization_level'] ?? 'primary');
        if (!in_array($level, ['primary','secondary','advanced'], true)) throw new RuntimeException('Invalid specialization_level', 422);
        $staff = $this->value("SELECT s.id FROM staff s LEFT JOIN staff_types st ON st.id=s.staff_type_id WHERE s.id=? AND s.status='active' AND LOWER(COALESCE(st.name,'')) LIKE '%teach%' LIMIT 1", [$staffId]);
        if (!$staff) throw new RuntimeException('Active teaching staff member not found', 422);
        if (!$this->value("SELECT id FROM learning_areas WHERE id=? AND status='active' LIMIT 1", [$areaId])) throw new RuntimeException('Active learning area not found', 422);
        $qualificationIds = $data['qualification_ids'] ?? [];
        if (!is_array($qualificationIds)) $qualificationIds = [$qualificationIds];
        if (!empty($data['qualification_id'])) $qualificationIds[] = $data['qualification_id'];
        $qualificationIds = array_values(array_unique(array_filter(array_map('intval', $qualificationIds), static fn(int $id): bool => $id > 0)));
        foreach ($qualificationIds as $qualificationId) {
            if (!$this->value('SELECT id FROM staff_qualifications WHERE id=? AND staff_id=? LIMIT 1', [$qualificationId, $staffId])) throw new RuntimeException('Qualification does not belong to this staff member', 422);
        }
        $existing = $this->value('SELECT id FROM staff_learning_area_specializations WHERE staff_id=? AND learning_area_id=? LIMIT 1', [$staffId, $areaId]);
        $this->db->beginTransaction();
        try {
            if ($existing) {
                $id = (int)$existing;
                $this->db->prepare("UPDATE staff_learning_area_specializations SET specialization_level=?, status='pending', is_primary=?, notes=?, created_by=?, approved_by=NULL, approved_at=NULL, effective_from=?, effective_to=? WHERE id=?")
                    ->execute([$level, !empty($data['is_primary']) ? 1 : 0, $data['notes'] ?? null, $actorId, $data['effective_from'] ?? null, $data['effective_to'] ?? null, $id]);
                $this->db->prepare('DELETE FROM staff_specialization_qualifications WHERE specialization_id=?')->execute([$id]);
            } else {
                $this->db->prepare('INSERT INTO staff_learning_area_specializations (staff_id,learning_area_id,specialization_level,is_primary,status,notes,created_by,effective_from,effective_to) VALUES (?,?,?,? ,\'pending\',?,?,?,?)')
                    ->execute([$staffId, $areaId, $level, !empty($data['is_primary']) ? 1 : 0, $data['notes'] ?? null, $actorId, $data['effective_from'] ?? null, $data['effective_to'] ?? null]);
                $id = (int)$this->db->lastInsertId();
            }
            $link = $this->db->prepare('INSERT INTO staff_specialization_qualifications (specialization_id,qualification_id) VALUES (?,?)');
            $mapping = $this->db->prepare('INSERT IGNORE INTO qualification_learning_areas (qualification_id,learning_area_id) VALUES (?,?)');
            foreach ($qualificationIds as $qualificationId) { $link->execute([$id, $qualificationId]); $mapping->execute([$qualificationId, $areaId]); }
            $this->db->commit(); return $id;
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    public function approve(int $id, int $actorId): void
    {
        $row = $this->rows('SELECT id,staff_id,created_by,status FROM staff_learning_area_specializations WHERE id=? LIMIT 1', [$id])[0] ?? null;
        if (!$row) throw new RuntimeException('Specialization record not found', 404);
        if ((int)$row['created_by'] === $actorId) throw new RuntimeException('A specialization must be approved by another authorised user', 403);
        if (!(int)$this->value("SELECT COUNT(*) FROM staff_specialization_qualifications sq JOIN staff_qualifications q ON q.id=sq.qualification_id WHERE sq.specialization_id=? AND q.verification_status='verified'", [$id])) throw new RuntimeException('At least one school-verified qualification must support the specialization', 422);
        $this->db->beginTransaction();
        try {
            $verify = $this->db->prepare("UPDATE staff_specialization_qualifications sq JOIN qualification_learning_areas qla ON qla.qualification_id=sq.qualification_id JOIN staff_learning_area_specializations s ON s.id=sq.specialization_id SET sq.evidence_status='verified',sq.verified_by=?,sq.verified_at=NOW(),qla.mapping_status='verified',qla.verified_by=?,qla.verified_at=NOW() WHERE sq.specialization_id=? AND qla.learning_area_id=s.learning_area_id");
            $verify->execute([$actorId, $actorId, $id]);
            $stmt = $this->db->prepare("UPDATE staff_learning_area_specializations SET status='approved', approved_by=?, approved_at=NOW() WHERE id=? AND status='pending'");
            $stmt->execute([$actorId, $id]);
            if ($stmt->rowCount() < 1 && $row['status'] !== 'approved') throw new RuntimeException('Specialization is not pending approval', 409);
            $this->db->commit();
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    public function verifyQualification(int $qualificationId, int $actorId, string $status = 'verified'): void
    {
        if (!in_array($status, ['verified', 'rejected'], true)) throw new RuntimeException('Invalid qualification verification status', 422);
        $row = $this->rows('SELECT id, submitted_by, verification_status FROM staff_qualifications WHERE id=? LIMIT 1', [$qualificationId])[0] ?? null;
        if (!$row) throw new RuntimeException('Qualification record not found', 404);
        if ((int)($row['submitted_by'] ?? 0) === $actorId) throw new RuntimeException('A qualification must be verified by another authorised user', 403);
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE staff_qualifications SET verification_status=?,verified_by=?,verified_at=NOW() WHERE id=?")->execute([$status, $actorId, $qualificationId]);
            $linkedStatus = $status === 'verified' ? 'pending' : 'rejected';
            $this->db->prepare("UPDATE qualification_learning_areas SET mapping_status=?,verified_by=NULL,verified_at=NULL WHERE qualification_id=?")->execute([$linkedStatus, $qualificationId]);
            $this->db->prepare("UPDATE staff_specialization_qualifications SET evidence_status=?,verified_by=NULL,verified_at=NULL WHERE qualification_id=?")->execute([$linkedStatus === 'pending' ? 'pending' : 'rejected', $qualificationId]);
            $this->db->commit();
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    public function saveLevelAuthorization(array $data, int $actorId): int
    {
        $staffId=(int)($data['staff_id']??0); $band=(string)($data['level_band']??'');
        $bands=['playgroup','pp','lower_primary','upper_primary','junior_secondary'];
        if(!$staffId || !in_array($band,$bands,true)) throw new RuntimeException('staff_id and valid level_band are required',422);
        if(!$this->value("SELECT s.id FROM staff s LEFT JOIN staff_types t ON t.id=s.staff_type_id WHERE s.id=? AND s.status='active' AND LOWER(COALESCE(t.name,'')) LIKE '%teach%' LIMIT 1",[$staffId])) throw new RuntimeException('Active teaching staff member not found',422);
        $stmt=$this->db->prepare("INSERT INTO staff_teaching_level_authorizations (staff_id,created_by,level_band,grade_from,grade_to,status,effective_from,effective_to,notes) VALUES (?,?,?,?,?, 'pending',?,?,?) ON DUPLICATE KEY UPDATE created_by=VALUES(created_by),status='pending',grade_from=VALUES(grade_from),grade_to=VALUES(grade_to),effective_from=VALUES(effective_from),effective_to=VALUES(effective_to),notes=VALUES(notes),approved_by=NULL,approved_at=NULL");
        $stmt->execute([$staffId,$actorId,$band,$data['grade_from']??null,$data['grade_to']??null,$data['effective_from']??null,$data['effective_to']??null,$data['notes']??null]);
        return (int)$this->db->lastInsertId() ?: (int)$this->value('SELECT id FROM staff_teaching_level_authorizations WHERE staff_id=? AND level_band=? AND (grade_from <=> ?) AND (grade_to <=> ?) LIMIT 1',[$staffId,$band,$data['grade_from']??null,$data['grade_to']??null]);
    }

    public function listLevelAuthorizations(array $filters=[]): array
    {
        $where=['1=1'];$params=[]; foreach(['staff_id','status','level_band'] as $key) if(isset($filters[$key])&&$filters[$key]!==''){ $where[]="$key = ?";$params[]=$filters[$key]; }
        return $this->rows('SELECT * FROM staff_teaching_level_authorizations WHERE '.implode(' AND ',$where).' ORDER BY staff_id,level_band,id',$params);
    }

    public function approveLevelAuthorization(int $id,int $actorId): void
    {
        $row=$this->rows("SELECT id,created_by,status FROM staff_teaching_level_authorizations WHERE id=? LIMIT 1",[$id])[0]??null;
        if(!$row) throw new RuntimeException('Teaching level authorization not found',404);
        if((int)($row['created_by']??0)===$actorId) throw new RuntimeException('A teaching level authorization must be approved by another authorised user',403);
        if(($row['status']??'')!=='pending') throw new RuntimeException('Teaching level authorization is not pending approval',409);
        $this->db->prepare("UPDATE staff_teaching_level_authorizations SET status='approved',approved_by=?,approved_at=NOW() WHERE id=? AND status='pending'")->execute([$actorId,$id]);
    }

    public function eligibleTeachers(int $learningAreaId, ?int $academicYearId = null, ?int $classStreamId = null): array
    {
        $className='';$gradeLevel='';$classTeacherId=0;
        if($classStreamId){$row=$this->rows('SELECT c.name class_name,c.grade_level,aycs.class_teacher_id FROM academic_year_class_streams aycs JOIN academic_year_classes ayc ON ayc.id=aycs.academic_year_class_id JOIN classes c ON c.id=ayc.class_id WHERE aycs.id=? LIMIT 1',[$classStreamId])[0]??[];$className=(string)($row['class_name']??'');$gradeLevel=$row['grade_level']??null;$classTeacherId=(int)($row['class_teacher_id']??0);}
        $band=TeacherSpecializationPolicy::requiredLevelBand($className,$gradeLevel);
        $params=[$learningAreaId,$classTeacherId,$band];
        $rows=$this->rows("SELECT DISTINCT s.staff_id teacher_id,CONCAT_WS(' ',p.first_name,p.middle_name,p.last_name) teacher_name,s.specialization_level,s.is_primary,la.name learning_area_name,
                    CASE WHEN s.staff_id=? THEN 'class_teacher' ELSE 'learning_area_specialist' END assignment_role,
                    'approved_specialization_and_level_authorization' eligibility_source
               FROM staff_learning_area_specializations s JOIN staff st ON st.id=s.staff_id JOIN persons p ON p.id=st.person_id JOIN learning_areas la ON la.id=s.learning_area_id
              WHERE s.learning_area_id=? AND s.status='approved' AND EXISTS (SELECT 1 FROM staff_specialization_qualifications sq JOIN qualification_learning_areas qla ON qla.qualification_id=sq.qualification_id WHERE sq.specialization_id=s.id AND sq.evidence_status='verified' AND qla.learning_area_id=s.learning_area_id AND qla.mapping_status='verified')
                AND EXISTS (SELECT 1 FROM staff_teaching_level_authorizations a WHERE a.staff_id=s.staff_id AND a.level_band=? AND a.status='approved' AND (a.effective_from IS NULL OR a.effective_from<=CURDATE()) AND (a.effective_to IS NULL OR a.effective_to>=CURDATE())) AND st.status='active' ORDER BY s.is_primary DESC,teacher_name",[$classTeacherId,$learningAreaId,$band]);
        return TeacherSpecializationPolicy::rankCandidates($rows,$className,$gradeLevel);
    }

    /** Return only currently eligible candidates; without a stream, class/stream scope is unavailable. */
    public function suggestCandidates(int $learningAreaId, string $className = '', ?string $gradeLevel = null, ?int $academicYearId = null, ?int $classStreamId = null): array
    {
        if ($learningAreaId < 1) throw new RuntimeException('learning_area_id is required', 422);
        if ($classStreamId) return $this->eligibleTeachers($learningAreaId, $academicYearId, $classStreamId);
        $band = TeacherSpecializationPolicy::requiredLevelBand($className, $gradeLevel);
        $rows = $this->rows("SELECT DISTINCT s.staff_id teacher_id,
                    CONCAT_WS(' ',p.first_name,p.middle_name,p.last_name) teacher_name,
                    s.specialization_level,s.is_primary,la.name learning_area_name,
                    'learning_area_specialist' assignment_role,
                    'approved_specialization_and_level_authorization' eligibility_source
               FROM staff_learning_area_specializations s
               JOIN staff st ON st.id=s.staff_id JOIN persons p ON p.id=st.person_id
               JOIN learning_areas la ON la.id=s.learning_area_id
              WHERE s.learning_area_id=? AND s.status='approved'
                AND EXISTS (SELECT 1 FROM staff_specialization_qualifications sq
                              JOIN qualification_learning_areas qla ON qla.qualification_id=sq.qualification_id
                             WHERE sq.specialization_id=s.id AND sq.evidence_status='verified'
                               AND qla.learning_area_id=s.learning_area_id AND qla.mapping_status='verified')
                AND EXISTS (SELECT 1 FROM staff_teaching_level_authorizations a
                             WHERE a.staff_id=s.staff_id AND a.level_band=? AND a.status='approved'
                               AND (a.effective_from IS NULL OR a.effective_from<=CURDATE())
                               AND (a.effective_to IS NULL OR a.effective_to>=CURDATE()))
                AND st.status='active'
              ORDER BY s.is_primary DESC,teacher_name", [$learningAreaId, $band]);
        return TeacherSpecializationPolicy::rankCandidates($rows, $className, $gradeLevel);
    }

    public function assertEligible(int $staffId, int $learningAreaId, int $classStreamId): void
    {
        foreach ($this->eligibleTeachers($learningAreaId, null, $classStreamId) as $candidate) {
            if ((int)$candidate['teacher_id'] === $staffId) return;
        }
        throw new RuntimeException('Teacher requires approved learning-area specialization, verified qualification evidence, and approved teaching-level authorization for this stream', 422);
    }
}
