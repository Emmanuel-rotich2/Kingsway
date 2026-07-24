<?php
namespace App\API\Services;

use App\Database\Database;
use PDO;
use RuntimeException;

final class StaffTeachingAssignmentService
{
    private $db;
    public function __construct() { $this->db = Database::getInstance(); }

    private function teacher(int $staffId): array
    {
        $row = $this->db->query("SELECT s.id,s.first_name,s.last_name,s.status,s.tsc_no,st.name staff_type
            FROM staff s LEFT JOIN staff_types st ON st.id=s.staff_type_id WHERE s.id=? LIMIT 1",[$staffId])->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['status'] !== 'active') throw new RuntimeException('Active teacher not found',422);
        if (empty($row['tsc_no']) && stripos((string)$row['staff_type'],'teach') === false) throw new RuntimeException('Selected staff member is not teaching staff',422);
        return $row;
    }

    private function currentYearId(): int
    {
        $id=(int)$this->db->query("SELECT id FROM academic_years WHERE is_current=1 OR status='active' ORDER BY is_current DESC,id DESC LIMIT 1")->fetchColumn();
        if (!$id) throw new RuntimeException('No active academic year configured',422);
        return $id;
    }

    public function listClassTeachers(array $filters=[]): array
    {
        $where=["sca.role='class_teacher'","sca.status='active'"]; $params=[];
        if (!empty($filters['academic_year_id'])) {$where[]='sca.academic_year_id=?';$params[]=(int)$filters['academic_year_id'];}
        if (!empty($filters['teacher_id'])) {$where[]='sca.staff_id=?';$params[]=(int)$filters['teacher_id'];}
        return $this->db->query("SELECT sca.id,sca.staff_id teacher_id,sca.class_id,sca.stream_id,sca.class_stream_id,sca.academic_year_id,
            sca.start_date assigned_date,sca.status,c.name class_name,cs.stream_name,
            CONCAT(s.first_name,' ',s.last_name) teacher_name,u.email teacher_email
            FROM staff_class_assignments sca JOIN staff s ON s.id=sca.staff_id
            JOIN classes c ON c.id=sca.class_id LEFT JOIN class_streams cs ON cs.id=COALESCE(sca.class_stream_id,sca.stream_id)
            LEFT JOIN users u ON u.id=s.user_id WHERE ".implode(' AND ',$where)." ORDER BY c.name,cs.stream_name",$params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getClassTeacher(int $id): ?array
    {
        $rows=$this->listClassTeachers([]);
        foreach($rows as $r) if((int)$r['id']===$id) return $r;
        return null;
    }

    public function saveClassTeacher(array $data, ?int $id=null, int $userId=0): int
    {
        $staffId=(int)($data['teacher_id']??$data['staff_id']??0); $classId=(int)($data['class_id']??0);
        $streamId=(int)($data['stream_id']??$data['class_stream_id']??0); $yearId=(int)($data['academic_year_id']??0) ?: $this->currentYearId();
        if(!$staffId||!$classId) throw new RuntimeException('teacher_id and class_id are required',422);
        $this->teacher($staffId);
        if($streamId){$stream=$this->db->query('SELECT id,class_id FROM class_streams WHERE id=? AND status="active"',[$streamId])->fetch(PDO::FETCH_ASSOC);if(!$stream)throw new RuntimeException('Class stream not found',422);$classId=(int)$stream['class_id'];}
        $conflict=$this->db->query("SELECT id FROM staff_class_assignments WHERE role='class_teacher' AND status='active' AND class_id=? AND COALESCE(class_stream_id,stream_id,0)=? AND academic_year_id=? AND id<>? LIMIT 1",[$classId,$streamId,$yearId,$id??0])->fetchColumn();
        if($conflict) throw new RuntimeException('This class/stream already has an active class teacher',409);
        if($id){$this->db->query("UPDATE staff_class_assignments SET staff_id=?,class_id=?,class_stream_id=?,stream_id=?,academic_year_id=?,start_date=COALESCE(?,start_date),updated_at=NOW() WHERE id=? AND role='class_teacher'",[$staffId,$classId,$streamId?:null,$streamId?:null,$yearId,$data['start_date']??null,$id]);return $id;}
        $this->db->query("INSERT INTO staff_class_assignments(staff_id,class_stream_id,class_id,stream_id,academic_year_id,role,start_date,status,notes,created_by,created_at,updated_at) VALUES(?,?,?,?,?,'class_teacher',?,'active',?,?,NOW(),NOW())",[$staffId,$streamId?:null,$classId,$streamId?:null,$yearId,$data['start_date']??date('Y-m-d'),$data['notes']??null,$userId?:null]);
        return (int)$this->db->lastInsertId();
    }

    public function listSubjectAssignments(array $filters=[]): array
    {
        $where=["sca.role='subject_teacher'"]; $params=[];
        foreach(['teacher_id'=>'staff_id','subject_id'=>'subject_id','class_id'=>'class_id','academic_year_id'=>'academic_year_id','status'=>'status'] as $key=>$col){if(isset($filters[$key])&&$filters[$key]!==''){$where[]="sca.$col=?";$params[]=$filters[$key];}}
        if(!empty($filters['search'])){$where[]="(CONCAT(s.first_name,' ',s.last_name) LIKE ? OR la.name LIKE ? OR c.name LIKE ?)";$q='%'.$filters['search'].'%';array_push($params,$q,$q,$q);}
        $page=max(1,(int)($filters['page']??1));$limit=min(200,max(1,(int)($filters['limit']??50)));$offset=($page-1)*$limit;
        $total=(int)$this->db->query("SELECT COUNT(*) FROM staff_class_assignments sca JOIN staff s ON s.id=sca.staff_id LEFT JOIN learning_areas la ON la.id=sca.subject_id JOIN classes c ON c.id=sca.class_id WHERE ".implode(' AND ',$where),$params)->fetchColumn();
        $rows=$this->db->query("SELECT sca.id,sca.staff_id teacher_id,sca.subject_id,sca.class_id,sca.stream_id,sca.class_stream_id,sca.academic_year_id,sca.periods_per_week,sca.status,
            CONCAT(s.first_name,' ',s.last_name) teacher_name,s.first_name,s.last_name,la.name subject_name,c.name class_name,cs.stream_name
            FROM staff_class_assignments sca JOIN staff s ON s.id=sca.staff_id LEFT JOIN learning_areas la ON la.id=sca.subject_id JOIN classes c ON c.id=sca.class_id
            LEFT JOIN class_streams cs ON cs.id=COALESCE(sca.class_stream_id,sca.stream_id) WHERE ".implode(' AND ',$where)." ORDER BY teacher_name,subject_name LIMIT $limit OFFSET $offset",$params)->fetchAll(PDO::FETCH_ASSOC);
        return ['items'=>$rows,'pagination'=>['page'=>$page,'limit'=>$limit,'total'=>$total]];
    }

    public function getSubjectAssignment(int $id): ?array
    {
        $r=$this->listSubjectAssignments(['limit'=>200]);foreach($r['items'] as $row)if((int)$row['id']===$id)return $row;return null;
    }

    public function saveSubjectAssignment(array $data, ?int $id=null, int $userId=0): int
    {
        $staffId=(int)($data['teacher_id']??$data['staff_id']??0);$subjectId=(int)($data['subject_id']??0);$classId=(int)($data['class_id']??0);$streamId=(int)($data['stream_id']??0);$yearId=(int)($data['academic_year_id']??0)?:$this->currentYearId();
        if(!$staffId||!$subjectId||!$classId)throw new RuntimeException('teacher_id, subject_id and class_id are required',422);$this->teacher($staffId);
        $periods=max(1,min(40,(int)($data['periods_per_week']??5)));$status=in_array(($data['status']??'active'),['active','completed','transferred','terminated'],true)?$data['status']:'active';
        $conflict=$this->db->query("SELECT id FROM staff_class_assignments WHERE role='subject_teacher' AND staff_id=? AND subject_id=? AND class_id=? AND COALESCE(stream_id,0)=? AND academic_year_id=? AND status='active' AND id<>? LIMIT 1",[$staffId,$subjectId,$classId,$streamId,$yearId,$id??0])->fetchColumn();if($conflict)throw new RuntimeException('Duplicate active subject assignment',409);
        if($id){$this->db->query("UPDATE staff_class_assignments SET staff_id=?,subject_id=?,class_id=?,stream_id=?,class_stream_id=?,academic_year_id=?,periods_per_week=?,status=?,updated_at=NOW() WHERE id=? AND role='subject_teacher'",[$staffId,$subjectId,$classId,$streamId?:null,$streamId?:null,$yearId,$periods,$status,$id]);return $id;}
        $this->db->query("INSERT INTO staff_class_assignments(staff_id,class_stream_id,class_id,stream_id,academic_year_id,role,subject_id,periods_per_week,start_date,status,created_by,created_at,updated_at) VALUES(?,?,?,?,?,'subject_teacher',?,?,? ,?,?,NOW(),NOW())",[$staffId,$streamId?:null,$classId,$streamId?:null,$yearId,$subjectId,$periods,$data['start_date']??date('Y-m-d'),$status,$userId?:null]);return (int)$this->db->lastInsertId();
    }

    public function remove(int $id): void { $this->db->query("UPDATE staff_class_assignments SET status='completed',end_date=CURDATE(),updated_at=NOW() WHERE id=?",[$id]); }
}
