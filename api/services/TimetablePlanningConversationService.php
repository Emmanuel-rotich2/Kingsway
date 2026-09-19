<?php
namespace App\API\Services;

use PDO;
use RuntimeException;

/** Owner-scoped conversation state; timetable rows remain outside this service. */
final class TimetablePlanningConversationService
{
    public function __construct(private PDO $db) {}

    public function create(int $ownerId, array $data): int
    {
        if ($ownerId < 1) throw new RuntimeException('Authenticated user is required', 401);
        $scope = (string)($data['scope'] ?? 'upper_primary');
        if (!in_array($scope, ['lower_primary','upper_primary','whole_school'], true)) throw new RuntimeException('Invalid timetable planning scope', 422);
        $stmt=$this->db->prepare('INSERT INTO ai_timetable_planning_sessions (owner_user_id,academic_year_id,academic_year_term_id,scope) VALUES (?,?,?,?)');
        $stmt->execute([$ownerId, !empty($data['academic_year_id']) ? (int)$data['academic_year_id'] : null, !empty($data['academic_year_term_id']) ? (int)$data['academic_year_term_id'] : null, $scope]);
        return (int)$this->db->lastInsertId();
    }

    public function appendUserMessage(int $sessionId, int $ownerId, string $message, array $constraints=[]): void
    {
        $session=$this->ownedSession($sessionId,$ownerId);
        $message=trim($message);
        if ($message === '' || mb_strlen($message)>2000) throw new RuntimeException('A planning message between 1 and 2,000 characters is required',422);
        $startedTransaction = !$this->db->inTransaction();
        if ($startedTransaction) $this->db->beginTransaction();
        try {
            $this->db->prepare("INSERT INTO ai_timetable_planning_messages (session_id,actor_type,actor_user_id,message_text) VALUES (?, 'user', ?, ?)")->execute([$sessionId,$ownerId,$message]);
            $allowed=['class_band','class_count','learning_areas','candidate_counts','assignment_candidates','period_count','availability_constraints','workload_constraints','room_constraints','double_periods','user_constraints'];
            foreach($constraints as $key=>$value){
                if(!in_array($key,$allowed,true)) continue;
                $encoded=is_array($value)?json_encode(array_slice($value,0,$key === 'assignment_candidates' ? 60 : 20),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):substr(trim((string)$value),0,2000);
                if($encoded==='') continue;
                $exists=$this->db->prepare('SELECT 1 FROM ai_timetable_planning_constraints WHERE session_id=? AND constraint_key=? LIMIT 1');
                $exists->execute([$sessionId,$key]);
                if($exists->fetchColumn()) {
                    $this->db->prepare("UPDATE ai_timetable_planning_constraints SET constraint_value=?,source='user' WHERE session_id=? AND constraint_key=?")->execute([$encoded,$sessionId,$key]);
                } else {
                    $this->db->prepare("INSERT INTO ai_timetable_planning_constraints (session_id,constraint_key,constraint_value,source) VALUES (?,?,?,'user')")->execute([$sessionId,$key,$encoded]);
                }
            }
            if ($startedTransaction) $this->db->commit();
        } catch(\Throwable $e){if ($startedTransaction && $this->db->inTransaction()) $this->db->rollBack();throw $e;}
    }

    public function input(int $sessionId,int $ownerId,string $message): array
    {
        $session=$this->ownedSession($sessionId,$ownerId);
        $rows=$this->db->prepare('SELECT constraint_key,constraint_value FROM ai_timetable_planning_constraints WHERE session_id=? ORDER BY constraint_key');$rows->execute([$sessionId]);
        $input=['class_band'=>(string)$session['scope'],'class_count'=>'0','learning_areas'=>[],'period_count'=>'0','user_constraints'=>[$message]];
        foreach($rows->fetchAll(PDO::FETCH_ASSOC) as $row){
            $v=json_decode($row['constraint_value'],true);
            $v=json_last_error()===JSON_ERROR_NONE?$v:$row['constraint_value'];
            if ($row['constraint_key'] === 'user_constraints') {
                $input['user_constraints'] = array_values(array_merge((array)$v, [$message]));
            } else {
                $input[$row['constraint_key']] = $v;
            }
        }
        return [$session,$input];
    }

    public function recordQueuedJob(int $sessionId,int $ownerId,int $jobId): void
    {
        $this->ownedSession($sessionId,$ownerId);
        $this->db->prepare("UPDATE ai_timetable_planning_messages SET queue_job_id=? WHERE id=(SELECT id FROM (SELECT id FROM ai_timetable_planning_messages WHERE session_id=? AND actor_type='user' ORDER BY id DESC LIMIT 1) x)")->execute([$jobId,$sessionId]);
    }

    public function get(int $sessionId,int $ownerId): array
    {
        $session = $this->ownedSession($sessionId, $ownerId);
        $stmt = $this->db->prepare('SELECT id,actor_type,actor_user_id,message_text,ai_draft_id,queue_job_id,created_at FROM ai_timetable_planning_messages WHERE session_id=? ORDER BY id');
        $stmt->execute([$sessionId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $drafts = $this->db->prepare('SELECT id,status,draft_json FROM ai_workflow_drafts WHERE id=? AND workflow_id=? LIMIT 1');
        foreach ($messages as &$message) {
            $draftId = (int) ($message['ai_draft_id'] ?? 0);
            if ($draftId < 1) continue;
            $drafts->execute([$draftId, 'academics.timetable_planning']);
            $draft = $drafts->fetch(PDO::FETCH_ASSOC);
            if (!$draft) continue;
            $decoded = json_decode((string) ($draft['draft_json'] ?? ''), true);
            $message['ai_draft'] = [
                'id' => $draftId,
                'status' => (string) ($draft['status'] ?? 'queued'),
                'draft' => is_array($decoded) ? $decoded : [],
            ];
        }
        unset($message);
        $session['messages'] = $messages;
        return $session;
    }

    private function ownedSession(int $id,int $ownerId): array
    {
        $stmt=$this->db->prepare('SELECT * FROM ai_timetable_planning_sessions WHERE id=? AND owner_user_id=? LIMIT 1');$stmt->execute([$id,$ownerId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException('Timetable planning session not found',404);if($row['status']!=='active')throw new RuntimeException('Timetable planning session is closed',409);return $row;
    }
}
