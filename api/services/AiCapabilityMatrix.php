<?php

declare(strict_types=1);

namespace App\API\Services;

/** Full ERP AI product inventory; executable workflows live in the registry. */
final class AiCapabilityMatrix
{
    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        $rows = [
            ['id'=>'academics.cbc_planning','domain'=>'academics','capabilities'=>['scheme drafts','lesson plans','assessment drafts','rubrics','coverage and learning gaps'],'audiences'=>['staff'],'action_level'=>'prepare','permission'=>'academic_view','source_of_truth'=>'governed CBC curriculum and academic records','status'=>'partial'],
            ['id'=>'learners.support_planning','domain'=>'learners','capabilities'=>['learner summaries','missing-data prompts','support-plan drafts','parent-meeting briefs'],'audiences'=>['staff'],'action_level'=>'prepare','permission'=>'student_view','source_of_truth'=>'scoped learner and welfare services','status'=>'partial'],
            ['id'=>'admissions.enrollment_assistance','domain'=>'admissions','capabilities'=>['application completeness','interview drafts','follow-up drafts','bottleneck summaries','placement preparation'],'audiences'=>['staff'],'action_level'=>'prepare','permission'=>'admission_view','source_of_truth'=>'admissions workflow and policy services','status'=>'partial'],
            ['id'=>'attendance.boarding_patterns','domain'=>'attendance','capabilities'=>['absence/late patterns','register exceptions','boarding roll-call summaries','welfare follow-up drafts'],'audiences'=>['staff'],'action_level'=>'recommend','permission'=>'attendance_view','source_of_truth'=>'attendance and boarding services','status'=>'partial'],
            ['id'=>'finance.reconciliation_and_planning','domain'=>'finance','capabilities'=>['reconciliation review','KPI explanations','arrears summaries','reminder drafts','budget narratives'],'audiences'=>['staff'],'action_level'=>'prepare','permission'=>'finance_view','source_of_truth'=>'posted accounting and reconciliation services','status'=>'partial'],
            ['id'=>'communications.audience_messaging','domain'=>'communications','capabilities'=>['message drafts','thread summaries','classification','translation','human escalation'],'audiences'=>['staff','parent','public'],'action_level'=>'prepare','permission'=>'communications_view','source_of_truth'=>'approved facts and outbox workflow','status'=>'partial'],
            ['id'=>'staff.hr_assistance','domain'=>'staff','capabilities'=>['onboarding','workload','leave impact','appraisal preparation','coverage suggestions'],'audiences'=>['staff'],'action_level'=>'recommend','permission'=>'staff_view','source_of_truth'=>'scoped staff and HR services','status'=>'partial'],
            ['id'=>'transport.operations','domain'=>'transport','capabilities'=>['route utilization','vehicle/driver summaries','incident drafts','fuel anomalies'],'audiences'=>['staff'],'action_level'=>'recommend','permission'=>'transport_view','source_of_truth'=>'transport services and validators','status'=>'partial'],
            ['id'=>'inventory.procurement','domain'=>'inventory','capabilities'=>['stock anomalies','consumption prediction','requisition drafts','supplier comparisons','uniform demand'],'audiences'=>['staff'],'action_level'=>'recommend','permission'=>'inventory_view','source_of_truth'=>'inventory and procurement services','status'=>'partial'],
            ['id'=>'catering.food_operations','domain'=>'catering','capabilities'=>['meal demand','waste analysis','menu drafts','food-stock follow-up'],'audiences'=>['staff'],'action_level'=>'recommend','permission'=>'inventory_view','source_of_truth'=>'meal and consumption services','status'=>'partial'],
            ['id'=>'health.welfare_administration','domain'=>'health','capabilities'=>['case completeness','appointment drafts','aggregate welfare trends','referral preparation'],'audiences'=>['staff'],'action_level'=>'assist','permission'=>'health_view','source_of_truth'=>'scoped health/counselling services','status'=>'partial'],
            ['id'=>'activities.library_resources','domain'=>'activities','capabilities'=>['programme drafts','participation summaries','resource recommendations'],'audiences'=>['staff'],'action_level'=>'recommend','permission'=>'activities_view','source_of_truth'=>'activities and library services','status'=>'partial'],
            ['id'=>'maintenance.facilities','domain'=>'maintenance','capabilities'=>['ticket classification','recurring failures','work-order summaries','event/meeting preparation'],'audiences'=>['staff'],'action_level'=>'recommend','permission'=>'maintenance_view','source_of_truth'=>'maintenance and facilities services','status'=>'partial'],
            ['id'=>'reports.analytics_intelligence','domain'=>'reports','capabilities'=>['governed NLQ','KPI explanations','trend narratives','forecasting','executive briefings'],'audiences'=>['staff'],'action_level'=>'assist','permission'=>'analytics_catalogue_view','source_of_truth'=>'governed report catalogue and deterministic executor','status'=>'partial'],
            ['id'=>'system.observability_security','domain'=>'system','capabilities'=>['error/warning analysis','queue health','attack/anomaly signals','permission drift','data quality','update recommendations'],'audiences'=>['staff'],'action_level'=>'recommend','permission'=>'system_view','source_of_truth'=>'file journals and deterministic operational services','status'=>'partial'],
            ['id'=>'public.faq_triage','domain'=>'public','capabilities'=>['public FAQ','admissions enquiries','programme/policy guidance','human routing'],'audiences'=>['public'],'action_level'=>'assist','permission'=>'','source_of_truth'=>'approved published school corpus','status'=>'partial'],
            ['id'=>'parent.linked_child_assistant','domain'=>'parent','capabilities'=>['linked-child summaries','fee/attendance guidance','school FAQs','human escalation'],'audiences'=>['parent'],'action_level'=>'assist','permission'=>'','source_of_truth'=>'guardian-linked portal services and public corpus','status'=>'partial'],
            ['id'=>'research.external_knowledge','domain'=>'research','capabilities'=>['KICD/CBC research','education statistics','curriculum comparison','citations and provenance'],'audiences'=>['staff'],'action_level'=>'assist','permission'=>'ai_research','source_of_truth'=>'approved cached external-source corpus','status'=>'implemented'],
            ['id'=>'agents.mcp_governed_tools','domain'=>'agents','capabilities'=>['read-scoped MCP tools','governed workflow preparation','machine-client audit'],'audiences'=>['staff'],'action_level'=>'prepare','permission'=>'ai.workflow.prepare','source_of_truth'=>'existing API contracts and scoped services','status'=>'partial'],
            ['id'=>'platform.provider_gateway','domain'=>'platform','capabilities'=>['NVIDIA','OpenAI','Moonshot','Anthropic','Google','local models','routing/fallback','health checks'],'audiences'=>['staff','parent','public'],'action_level'=>'assist','permission'=>'system_view','source_of_truth'=>'provider contracts and application response schemas','status'=>'partial'],
        ];
        foreach ($rows as &$row) {
            $row['workflow_ids'] = array_values(array_map(static fn(array $workflow): string => (string)$workflow['id'], array_filter(AiWorkflowRegistry::all(), static fn(array $workflow): bool => (string)($workflow['domain'] ?? '') === $row['domain'])));
            $row = $this->contract($row);
        }
        unset($row);
        return $rows;
    }

    /** Normalize every adapter to the mandatory platform contract. */
    private function contract(array $row): array
    {
        $domain = (string) $row['domain'];
        $row['adapter_contract_version'] = 1;
        $row['supported_roles'] = $row['supported_roles'] ?? ($domain === 'public' ? ['public_visitor'] : ($domain === 'parent' ? ['parent_guardian'] : ['teacher','hod','school_administrator','system_administrator','domain_officer']));
        $row['row_scope'] = $row['row_scope'] ?? ($domain === 'public' ? 'published_public_content_only' : ($domain === 'parent' ? 'guardian_linked_children_only' : 'resolved_by_existing_domain_authorization'));
        $row['allowed_data_fields'] = $row['allowed_data_fields'] ?? array_values(array_map(static fn(string $capability): string => preg_replace('/[^a-z0-9]+/i', '_', strtolower($capability)), $row['capabilities']));
        $row['human_approval_required'] = $row['human_approval_required'] ?? in_array($row['action_level'], ['prepare','recommend','execute'], true);
        $row['provider_requirement'] = $row['provider_requirement'] ?? 'approved_provider_gateway_with_json_contract';
        $row['queue_behavior'] = $row['queue_behavior'] ?? 'bounded_retry_then_reviewable_failure';
        $row['audit_events'] = $row['audit_events'] ?? ['queued','provider_call','completed','failed','approved','denied'];
        $row['ui_entry_point'] = $row['ui_entry_point'] ?? 'contextual_assistant_catalogue_or_domain_workspace';
        $row['positive_tests'] = $row['positive_tests'] ?? ['authorized_context_returns_capability'];
        $row['negative_tests'] = $row['negative_tests'] ?? ['unauthorized_context_is_denied','out_of_scope_data_is_excluded'];
        return $row;
    }

    /** @return array{total:int,implemented:int,partial:int,planned:int} */
    public function summary(): array
    {
        $summary = ['total'=>0,'implemented'=>0,'partial'=>0,'planned'=>0];
        foreach ($this->all() as $row) {
            $summary['total']++;
            $status = (string)($row['status'] ?? 'planned');
            if (isset($summary[$status])) $summary[$status]++;
        }
        return $summary;
    }
}
