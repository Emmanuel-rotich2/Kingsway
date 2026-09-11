<?php

namespace App\API\Services;

use App\API\Includes\AuditLogger;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class CurriculumProposalService
{
    private const ENTITIES = ['learning_area', 'strand', 'sub_strand'];
    private const ACTIONS = ['create', 'update', 'remove'];

    public function __construct(private PDO $db, private TeacherCurriculumScopeService $scopeService) {}

    public function list(array $filters, int $userId, bool $schoolAdmin): array
    {
        $where = [];
        $params = [];
        if (!$schoolAdmin) {
            $where[] = 'p.proposed_by=:viewer';
            $params[':viewer'] = $userId;
        }
        foreach (['status', 'entity_type', 'change_source'] as $field) {
            if (!empty($filters[$field])) {
                $where[] = "p.$field=:$field";
                $params[":$field"] = $filters[$field];
            }
        }
        foreach (['academic_year_id', 'academic_year_term_id', 'learning_area_id'] as $field) {
            if (!empty($filters[$field])) {
                $where[] = "p.$field=:$field";
                $params[":$field"] = (int) $filters[$field];
            }
        }
        $sql = "SELECT p.*, la.name learning_area_name, ay.year_name academic_year,
                       t.name term_name,
                       CONCAT_WS(' ', pp.first_name, pp.middle_name, pp.last_name) proposer_name,
                       CONCAT_WS(' ', rp.first_name, rp.middle_name, rp.last_name) reviewer_name
                  FROM curriculum_change_proposals p
                  JOIN learning_areas la ON la.id=p.learning_area_id
                  JOIN academic_years ay ON ay.id=p.academic_year_id
             LEFT JOIN academic_year_terms ayt ON ayt.id=p.academic_year_term_id
             LEFT JOIN terms t ON t.id=ayt.term_id
             LEFT JOIN users pu ON pu.id=p.proposed_by LEFT JOIN persons pp ON pp.id=pu.person_id
             LEFT JOIN users ru ON ru.id=p.reviewed_by LEFT JOIN persons rp ON rp.id=ru.person_id"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . " ORDER BY FIELD(p.status,'submitted','draft','rejected','approved','withdrawn'), p.updated_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map([$this, 'decodeProposal'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function saveDraft(array $data, int $userId, array $roleIds, ?int $proposalId = null): array
    {
        $entity = (string) ($data['entity_type'] ?? '');
        $action = (string) ($data['change_action'] ?? '');
        if (!in_array($entity, self::ENTITIES, true) || !in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException('A valid curriculum item and change action are required.');
        }
        $yearId = (int) ($data['academic_year_id'] ?? 0);
        $areaId = (int) ($data['learning_area_id'] ?? 0);
        $grade = trim((string) ($data['grade_level'] ?? ''));
        $targetId = !empty($data['target_entity_id']) ? (int) $data['target_entity_id'] : null;
        $original = null;
        if ($action !== 'create') {
            if (!$targetId) throw new InvalidArgumentException('Select the existing curriculum item to change.');
            $original = $this->snapshot($entity, $targetId);
            if (!$original) throw new RuntimeException('The selected curriculum item was not found.');
            [$areaId, $grade] = $this->identity($entity, $original);
        }
        if (!$yearId || !$areaId || ($entity !== 'learning_area' && $grade === '')) {
            throw new InvalidArgumentException('Academic year, learning area and grade are required.');
        }
        $termId = !empty($data['academic_year_term_id']) ? (int) $data['academic_year_term_id'] : null;
        if ($termId) {
            $term = $this->db->prepare('SELECT COUNT(*) FROM academic_year_terms WHERE id=? AND academic_year_id=?');
            $term->execute([$termId, $yearId]);
            if (!(int) $term->fetchColumn()) throw new InvalidArgumentException('The selected term does not belong to the selected academic year.');
        }
        $schoolAdmin = in_array(4, array_map('intval', $roleIds), true);
        if ($entity === 'learning_area' && $action === 'create') {
            throw new InvalidArgumentException('New learning areas must first be configured by the School Administrator; teachers may propose changes or retirement of assigned areas.');
        }
        $scope = $this->scopeService->resolve($userId, $roleIds, $yearId);
        if (!$schoolAdmin && !$this->withinScope($scope, $areaId, $grade)) {
            throw new RuntimeException('You can only propose changes for learning areas and classes assigned to you in that academic year.');
        }
        if ($proposalId) {
            $owned = $this->db->prepare("SELECT id FROM curriculum_change_proposals WHERE id=? AND proposed_by=? AND status='draft'");
            $owned->execute([$proposalId, $userId]);
            if (!$owned->fetchColumn()) throw new RuntimeException('Only your own draft proposal can be edited.');
        }
        $payload = $this->sanitizePayload($entity, (array) ($data['proposed_data'] ?? []));
        if ($action !== 'remove' && empty($payload['name'])) throw new InvalidArgumentException('The proposed name is required.');
        if ($action !== 'remove' && empty($payload['code'])) throw new InvalidArgumentException('The proposed curriculum code is required.');
        if ($entity === 'sub_strand' && $action === 'create') {
            $parent = $this->db->prepare('SELECT COUNT(*) FROM strands WHERE id=? AND learning_area_id=? AND grade_level=?');
            $parent->execute([(int) ($payload['strand_id'] ?? 0), $areaId, $grade]);
            if (!(int) $parent->fetchColumn()) throw new InvalidArgumentException('The parent strand must belong to the selected learning area and grade.');
        }
        $rationale = trim((string) ($data['rationale'] ?? ''));
        if ($rationale === '') throw new InvalidArgumentException('Explain why this curriculum change is needed.');
        $source = $schoolAdmin ? (string) ($data['change_source'] ?? 'school') : 'teacher';
        if (!in_array($source, ['teacher', 'school', 'ministry', 'import'], true)) $source = $schoolAdmin ? 'school' : 'teacher';
        if ($source === 'ministry' && trim((string) ($data['source_reference'] ?? '')) === '') {
            throw new InvalidArgumentException('A Ministry circular or curriculum document reference is required.');
        }
        $params = [
            ':et' => $entity, ':ca' => $action, ':target' => $targetId, ':area' => $areaId,
            ':grade' => $grade ?: 'All', ':year' => $yearId,
            ':term' => $termId,
            ':proposed' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ':original' => $original ? json_encode($original, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
            ':rationale' => $rationale, ':source' => $source,
            ':reference' => trim((string) ($data['source_reference'] ?? '')) ?: null,
            ':user' => $userId,
        ];
        if ($proposalId) {
            $params[':id'] = $proposalId;
            $sql = 'UPDATE curriculum_change_proposals SET entity_type=:et, change_action=:ca, target_entity_id=:target,
                    learning_area_id=:area, grade_level=:grade, academic_year_id=:year, academic_year_term_id=:term,
                    proposed_data=:proposed, original_snapshot=:original, rationale=:rationale, change_source=:source,
                    source_reference=:reference WHERE id=:id AND proposed_by=:user AND status=\'draft\'';
        } else {
            $sql = 'INSERT INTO curriculum_change_proposals
                    (entity_type,change_action,target_entity_id,learning_area_id,grade_level,academic_year_id,
                     academic_year_term_id,proposed_data,original_snapshot,rationale,change_source,source_reference,proposed_by)
                    VALUES (:et,:ca,:target,:area,:grade,:year,:term,:proposed,:original,:rationale,:source,:reference,:user)';
        }
        $stmt = $this->db->prepare($sql); $stmt->execute($params);
        $id = $proposalId ?: (int) $this->db->lastInsertId();
        (new AuditLogger($this->db))->log($proposalId ? 'update_draft' : 'create_draft', 'curriculum_proposal', $id, $userId,
            ['entity_type' => $entity, 'action' => $action, 'academic_year_id' => $yearId, 'learning_area_id' => $areaId]);
        return $this->find($id);
    }

    public function submit(int $proposalId, int $userId): array
    {
        $stmt = $this->db->prepare("UPDATE curriculum_change_proposals SET status='submitted',submitted_at=NOW() WHERE id=? AND proposed_by=? AND status='draft'");
        $stmt->execute([$proposalId, $userId]);
        if (!$stmt->rowCount()) throw new RuntimeException('Only your own draft proposal can be submitted.');
        (new AuditLogger($this->db))->log('submit', 'curriculum_proposal', $proposalId, $userId);
        return $this->find($proposalId);
    }

    public function review(int $proposalId, string $decision, string $notes, int $reviewerId): array
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) throw new InvalidArgumentException('Decision must be approved or rejected.');
        if ($decision === 'rejected' && trim($notes) === '') throw new InvalidArgumentException('Review notes are required when rejecting a proposal.');
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT * FROM curriculum_change_proposals WHERE id=? AND status='submitted' FOR UPDATE");
            $stmt->execute([$proposalId]); $proposal = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$proposal) throw new RuntimeException('This proposal is no longer awaiting review.');
            $appliedId = null;
            if ($decision === 'approved') $appliedId = $this->applyApproved($proposal, $reviewerId);
            $update = $this->db->prepare('UPDATE curriculum_change_proposals SET status=?, reviewed_by=?, review_notes=?, reviewed_at=NOW(), applied_entity_id=? WHERE id=?');
            $update->execute([$decision, $reviewerId, trim($notes) ?: null, $appliedId, $proposalId]);
            $this->db->commit();
            (new AuditLogger($this->db))->log($decision, 'curriculum_proposal', $proposalId, $reviewerId,
                ['applied_entity_id' => $appliedId, 'notes' => $notes]);
            return $this->find($proposalId);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function history(array $filters, int $userId, array $roleIds): array
    {
        $yearId = !empty($filters['academic_year_id']) ? (int) $filters['academic_year_id'] : null;
        $scope = $this->historyScope($userId, $roleIds, $yearId);
        $where = []; $params = [];
        if (!empty($scope['restricted'])) {
            $contexts=$scope['contexts']??[];if(!$contexts)return [];$clauses=[];
            foreach(array_values($contexts) as $i=>$context){
                $params[":hya$i"]=(int)$context['learning_area_id'];$params[":hyg$i"]=(string)$context['grade_level'];$params[":hyy$i"]=(int)$context['academic_year_id'];
                $clauses[]="(v.learning_area_id=:hya$i AND v.grade_level=:hyg$i AND v.academic_year_id=:hyy$i)";
            }
            $where[]='('.implode(' OR ',$clauses).')';
        }
        foreach (['academic_year_id','academic_year_term_id','learning_area_id'] as $field) {
            if (!empty($filters[$field])) { $where[] = "v.$field=:$field"; $params[":$field"] = (int) $filters[$field]; }
        }
        if (!empty($filters['entity_type'])) { $where[]='v.entity_type=:entity'; $params[':entity']=$filters['entity_type']; }
        $sql = "SELECT v.*, 'curriculum_change' event_type, la.name learning_area_name,ay.year_name academic_year,
                       ay.start_date academic_year_start,t.name term_name,
                       CONCAT_WS(' ',cp.first_name,cp.middle_name,cp.last_name) changed_by_name,
                       CONCAT_WS(' ',ap.first_name,ap.middle_name,ap.last_name) approved_by_name
                  FROM curriculum_entity_versions v JOIN learning_areas la ON la.id=v.learning_area_id
             LEFT JOIN academic_years ay ON ay.id=v.academic_year_id
             LEFT JOIN academic_year_terms ayt ON ayt.id=v.academic_year_term_id LEFT JOIN terms t ON t.id=ayt.term_id
             LEFT JOIN users cu ON cu.id=v.changed_by LEFT JOIN persons cp ON cp.id=cu.person_id
             LEFT JOIN users au ON au.id=v.approved_by LEFT JOIN persons ap ON ap.id=au.person_id"
             . ($where ? ' WHERE '.implode(' AND ',$where) : '')
             . ' ORDER BY COALESCE(ay.start_date,DATE(v.valid_from)) DESC, ayt.id DESC, v.created_at DESC';
        $stmt=$this->db->prepare($sql); $stmt->execute($params);
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) $row['snapshot']=json_decode($row['snapshot'],true) ?: [];
        unset($row);
        $rows = array_merge($rows, $this->teachingHistory($filters, $scope));
        usort($rows, static function (array $a, array $b): int {
            $dateCompare = strcmp((string) ($b['academic_year_start'] ?? ''), (string) ($a['academic_year_start'] ?? ''));
            if ($dateCompare !== 0) return $dateCompare;
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });
        return $rows;
    }

    private function historyScope(int $userId,array $roleIds,?int $yearId): array
    {
        if(array_intersect([3,4,5,6],array_map('intval',$roleIds)))return ['restricted'=>false,'contexts'=>[],'learning_area_ids'=>[]];
        $yearIds=$yearId?[$yearId]:array_map('intval',$this->db->query('SELECT id FROM academic_years ORDER BY start_date')->fetchAll(PDO::FETCH_COLUMN));
        $contexts=[];
        foreach($yearIds as $id){
            $yearScope=$this->scopeService->resolve($userId,$roleIds,$id);
            foreach($yearScope['contexts']??[] as $context){$context['academic_year_id']=$id;$contexts[]=$context;}
        }
        return ['restricted'=>true,'contexts'=>$contexts,'learning_area_ids'=>array_values(array_unique(array_column($contexts,'learning_area_id')))];
    }

    private function teachingHistory(array $filters, array $scope): array
    {
        if (!empty($filters['entity_type']) && $filters['entity_type'] !== 'learning_area') return [];
        $where = ["v.status='active'"];
        $params = [];
        if (!empty($filters['academic_year_id'])) { $where[]='v.academic_year_id=:ty'; $params[':ty']=(int)$filters['academic_year_id']; }
        if (!empty($filters['learning_area_id'])) { $where[]='v.subject_id=:ta'; $params[':ta']=(int)$filters['learning_area_id']; }
        if (!empty($scope['restricted'])) {
            $contexts=$scope['contexts']??[]; if(!$contexts)return [];$clauses=[];
            foreach(array_values($contexts) as $i=>$context){
                $params[":tsa$i"]=(int)$context['learning_area_id'];$params[":tsg$i"]=(string)$context['grade_level'];$params[":tsc$i"]=(int)$context['class_id'];
                $params[":tsy$i"]=(int)($context['academic_year_id']??$filters['academic_year_id']??0);
                $clauses[]="(v.subject_id=:tsa$i AND c.grade_level=:tsg$i AND v.class_id=:tsc$i AND v.academic_year_id=:tsy$i)";
            }
            $where[]='('.implode(' OR ',$clauses).')';
        }
        $sql="SELECT CONCAT('assignment-',v.id) id,'teaching_assignment' event_type,'learning_area' entity_type,
                    v.subject_id entity_id,v.subject_id learning_area_id,c.grade_level,1 version_number,
                    v.academic_year_id,NULL academic_year_term_id,'baseline' change_action,'active' lifecycle_status,
                    v.subject_name learning_area_name,v.academic_year,NULL term_name,ay.start_date academic_year_start,
                    v.staff_name changed_by_name,NULL approved_by_name,'school' change_source,NULL source_reference,
                    CONCAT('Taught by ',v.staff_name,' as ',REPLACE(v.role,'_',' '),' for ',v.class_name) rationale,
                    v.created_at,JSON_OBJECT('name',v.subject_name,'class_name',v.class_name,'teacher_name',v.staff_name,'assignment_role',v.role) snapshot
               FROM vw_staff_assignments_detailed v JOIN academic_years ay ON ay.id=v.academic_year_id JOIN classes c ON c.id=v.class_id
              WHERE ".implode(' AND ',$where);
        $stmt=$this->db->prepare($sql);$stmt->execute($params);$events=$stmt->fetchAll(PDO::FETCH_ASSOC);

        $classWhere = ["aycs.status='active'"];
        $classParams = [];
        if (!empty($filters['academic_year_id'])) { $classWhere[]='ayc.academic_year_id=:cty'; $classParams[':cty']=(int)$filters['academic_year_id']; }
        if (!empty($filters['learning_area_id'])) { $classWhere[]='aycla.learning_area_id=:cta'; $classParams[':cta']=(int)$filters['learning_area_id']; }
        if (!empty($scope['restricted'])) {
            $clauses=[];foreach(array_values($scope['contexts']??[]) as $i=>$context){
                $classParams[":ctsa$i"]=(int)$context['learning_area_id'];$classParams[":ctsg$i"]=(string)$context['grade_level'];$classParams[":ctsc$i"]=(int)$context['class_id'];
                $classParams[":ctsy$i"]=(int)($context['academic_year_id']??$filters['academic_year_id']??0);
                $clauses[]="(aycla.learning_area_id=:ctsa$i AND c.grade_level=:ctsg$i AND c.id=:ctsc$i AND ayc.academic_year_id=:ctsy$i)";
            }
            $classWhere[]='('.implode(' OR ',$clauses).')';
        }
        $classSql="SELECT CONCAT('class-assignment-',aycs.id,'-',aycla.learning_area_id) id,'teaching_assignment' event_type,
                    'learning_area' entity_type,aycla.learning_area_id entity_id,aycla.learning_area_id learning_area_id,
                    c.grade_level,1 version_number,ayc.academic_year_id,NULL academic_year_term_id,'baseline' change_action,
                    'active' lifecycle_status,la.name learning_area_name,ay.year_name academic_year,NULL term_name,
                    ay.start_date academic_year_start,CONCAT_WS(' ',p.first_name,p.middle_name,p.last_name) changed_by_name,
                    NULL approved_by_name,'school' change_source,NULL source_reference,
                    CONCAT('Taught by ',CONCAT_WS(' ',p.first_name,p.middle_name,p.last_name),' as class teacher for ',c.name) rationale,
                    ay.start_date created_at,
                    JSON_OBJECT('name',la.name,'class_name',c.name,'teacher_name',CONCAT_WS(' ',p.first_name,p.middle_name,p.last_name),'assignment_role','class teacher') snapshot
               FROM academic_year_class_streams aycs
               JOIN academic_year_classes ayc ON ayc.id=aycs.academic_year_class_id
               JOIN academic_year_class_learning_areas aycla ON aycla.academic_year_class_id=ayc.id
               JOIN academic_years ay ON ay.id=ayc.academic_year_id JOIN classes c ON c.id=ayc.class_id
               JOIN learning_areas la ON la.id=aycla.learning_area_id JOIN staff s ON s.id=aycs.class_teacher_id
               JOIN persons p ON p.id=s.person_id WHERE ".implode(' AND ',$classWhere);
        $classStmt=$this->db->prepare($classSql);$classStmt->execute($classParams);
        $events=array_merge($events,$classStmt->fetchAll(PDO::FETCH_ASSOC));
        $unique=[];
        foreach($events as $event){
            $event['snapshot']=json_decode($event['snapshot'],true)?:[];
            $key=implode('|',[$event['academic_year_id'],$event['learning_area_id'],$event['changed_by_name'],$event['snapshot']['class_name']??'']);
            if(!isset($unique[$key]))$unique[$key]=$event;
        }
        return array_values($unique);
    }

    private function applyApproved(array $proposal, int $reviewerId): int
    {
        $entity=$proposal['entity_type']; $action=$proposal['change_action'];
        $payload=json_decode($proposal['proposed_data'],true) ?: [];
        $target=(int)($proposal['target_entity_id'] ?: 0);
        if ($action === 'create') $target=$this->insertEntity($entity,$payload,$proposal);
        else {
            $before=$this->snapshot($entity,$target);
            if (!$before) throw new RuntimeException('The curriculum item no longer exists.');
            $this->ensureBaseline($entity,$target,$proposal,$before);
            if ($action === 'remove') {
                $table=$this->table($entity);
                $this->db->prepare("UPDATE `$table` SET status='inactive' WHERE id=?")->execute([$target]);
            } else $this->updateEntity($entity,$target,$payload);
        }
        $snapshot=$this->snapshot($entity,$target);
        $current=$this->db->prepare('UPDATE curriculum_entity_versions SET valid_to=NOW() WHERE entity_type=? AND entity_id=? AND valid_to IS NULL');
        $current->execute([$entity,$target]);
        $num=$this->db->prepare('SELECT COALESCE(MAX(version_number),0)+1 FROM curriculum_entity_versions WHERE entity_type=? AND entity_id=? FOR UPDATE');
        $num->execute([$entity,$target]); $version=(int)$num->fetchColumn();
        $life=$action==='remove'?'removed':(($snapshot['status']??'active')==='active'?'active':'inactive');
        $insert=$this->db->prepare('INSERT INTO curriculum_entity_versions
            (entity_type,entity_id,learning_area_id,grade_level,version_number,academic_year_id,academic_year_term_id,
             change_action,lifecycle_status,snapshot,change_source,source_reference,rationale,proposal_id,changed_by,approved_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $insert->execute([$entity,$target,$proposal['learning_area_id'],$proposal['grade_level'],$version,$proposal['academic_year_id'],
            $proposal['academic_year_term_id'],$action,$life,json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
            $proposal['change_source'],$proposal['source_reference'],$proposal['rationale'],$proposal['id'],$proposal['proposed_by'],$reviewerId]);
        return $target;
    }

    private function ensureBaseline(string $entity,int $id,array $proposal,array $snapshot): void
    {
        $q=$this->db->prepare('SELECT COUNT(*) FROM curriculum_entity_versions WHERE entity_type=? AND entity_id=?');
        $q->execute([$entity,$id]); if ((int)$q->fetchColumn()) return;
        $stmt=$this->db->prepare('INSERT INTO curriculum_entity_versions
            (entity_type,entity_id,learning_area_id,grade_level,version_number,academic_year_id,academic_year_term_id,
             change_action,lifecycle_status,snapshot,change_source,source_reference,rationale,valid_from,valid_to)
             VALUES (?,?,?,?,1,NULL,NULL,\'baseline\',?,?,\'legacy\',NULL,\'State before governed version history\',?,NOW())');
        $stmt->execute([$entity,$id,$proposal['learning_area_id'],$proposal['grade_level'],($snapshot['status']??'active')==='active'?'active':'inactive',
            json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$snapshot['created_at']??date('Y-m-d H:i:s')]);
    }

    private function insertEntity(string $entity,array $p,array $proposal): int
    {
        if ($entity==='learning_area') {
            $stmt=$this->db->prepare("INSERT INTO learning_areas(name,code,level_band,description,status,levels,is_optional) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$p['name'],$p['code'],$p['level_band']??'lower_primary',$p['description']??null,$p['status']??'active',$p['levels']??$proposal['grade_level'],(int)($p['is_optional']??0)]);
        } elseif ($entity==='strand') {
            $stmt=$this->db->prepare('INSERT INTO strands(learning_area_id,grade_level,code,name,variant,description,sort_order,status) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute([$proposal['learning_area_id'],$proposal['grade_level'],$p['code'],$p['name'],$p['variant']??null,$p['description']??null,(int)($p['sort_order']??1),$p['status']??'active']);
        } else {
            if (empty($p['strand_id'])) throw new InvalidArgumentException('A parent strand is required for a new sub-strand.');
            $stmt=$this->db->prepare('INSERT INTO sub_strands(strand_id,grade_level,code,name,variant,description,sort_order,status) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute([(int)$p['strand_id'],$proposal['grade_level'],$p['code'],$p['name'],$p['variant']??null,$p['description']??null,(int)($p['sort_order']??1),$p['status']??'active']);
        }
        return (int)$this->db->lastInsertId();
    }

    private function updateEntity(string $entity,int $id,array $p): void
    {
        $allowed=$entity==='learning_area'?['name','code','level_band','description','status','levels','is_optional']:
            ($entity==='strand'?['code','name','variant','description','sort_order','status']:['strand_id','code','name','variant','description','sort_order','status']);
        $sets=[];$params=[];
        foreach ($allowed as $field) if (array_key_exists($field,$p)) { $sets[]="`$field`=?";$params[]=$p[$field]; }
        if (!$sets) throw new InvalidArgumentException('No proposed fields were supplied.');
        $params[]=$id; $this->db->prepare("UPDATE `{$this->table($entity)}` SET ".implode(',',$sets).' WHERE id=?')->execute($params);
    }

    private function snapshot(string $entity,int $id): ?array
    {
        if ($entity==='sub_strand') $sql='SELECT ss.*,s.learning_area_id FROM sub_strands ss JOIN strands s ON s.id=ss.strand_id WHERE ss.id=?';
        else $sql='SELECT * FROM `'.$this->table($entity).'` WHERE id=?';
        $stmt=$this->db->prepare($sql);$stmt->execute([$id]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function identity(string $entity,array $row): array
    {
        return $entity==='learning_area'?[(int)$row['id'],'All']:[(int)$row['learning_area_id'],(string)$row['grade_level']];
    }

    private function sanitizePayload(string $entity,array $payload): array
    {
        $allowed=$entity==='learning_area'?['name','code','level_band','description','status','levels','is_optional']:
            ($entity==='strand'?['name','code','variant','description','sort_order','status']:['strand_id','name','code','variant','description','sort_order','status']);
        return array_intersect_key($payload,array_flip($allowed));
    }

    private function withinScope(array $scope,int $areaId,string $grade): bool
    {
        if (empty($scope['restricted'])) return true;
        foreach ($scope['contexts']??[] as $context) {
            if ((int)$context['learning_area_id']===$areaId && ($grade==='All'||strcasecmp((string)$context['grade_level'],$grade)===0)) return true;
        }
        return false;
    }

    private function table(string $entity): string { return ['learning_area'=>'learning_areas','strand'=>'strands','sub_strand'=>'sub_strands'][$entity]; }
    private function find(int $id): array
    {
        $stmt=$this->db->prepare('SELECT * FROM curriculum_change_proposals WHERE id=?');$stmt->execute([$id]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC); if (!$row) throw new RuntimeException('Proposal not found.');
        return $this->decodeProposal($row);
    }
    private function decodeProposal(array $row): array
    {
        $row['proposed_data']=json_decode($row['proposed_data']??'{}',true)?:[];
        $row['original_snapshot']=json_decode($row['original_snapshot']??'null',true);
        return $row;
    }
}
