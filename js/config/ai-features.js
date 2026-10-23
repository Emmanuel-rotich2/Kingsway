/**
 * AI Feature Flags Configuration
 * 
 * Per-page toggles for safe rollout of Node gateway migration.
 * Set to true to enable Node gateway + AgentTaskPanel for each page.
 * Set to false to use legacy PHP queue endpoints.
 * 
 * Usage:
 *   if (window.AI_FEATURES.useAgentTaskPanel['ai_academic_reviews']) {
 *     // Use new AgentTaskPanel
 *   } else {
 *     // Use legacy polling
 *   }
 */

window.AI_FEATURES = {
  // Global kill switch for all Node gateway AI features
  useNodeGateway: true,
  
  // Per-page AgentTaskPanel enablement
  useAgentTaskPanel: {
    // Domain workflows must keep using their PHP-owned adapters until the
    // Node task contract can preserve their exact input, draft persistence,
    // reviewer policy and approval lifecycle. Generic assistant tasks are
    // not substitutes for these governed workflows.
    'ai_lesson_plan_draft': false,
    'ai_scheme_draft': false,
    'ai_assessment_draft': false,
    
    // Review/Summary pages (being migrated)
    'ai_academic_reviews': false,
    'ai_activities_review': false,
    'ai_attendance_summary': false,
    'ai_boarding_summary': false,  // Pre-generated, no queue
    'ai_catering_review': false,
    'ai_counseling_review': false,
    'ai_facilities_review': false,
    'ai_health_welfare': false,
    'ai_inventory_review': false,
    'ai_kpi_brief': false,
    'ai_staff_hr': false,
    'ai_transport_summary': false, // Pre-generated, no queue
    'payment_reconciliation_ai': false,
    
    // Timetable/Scheduling pages
    'manage_timetable': false,
    'exam_periods': false,
    
    // Admissions
    'admissions_workspace': false,
  },
  
  // Public Assistant (www subdomain)
  publicAssistant: {
    enabled: true,
    handoffEnabled: true,
    rateLimitPerMinute: 20,
    burstLimit: 50,
  },
  
  // Security Monitoring
  securityMonitoring: {
    enabled: true,
    alertEmail: true,
    bruteForceThreshold: 5,
    credentialStuffingThreshold: 10,
    suspiciousQueryThreshold: 3,
  },
  
  // Development/debug
  debug: {
    logSSEEvents: false,
    logThreatDetection: false,
    mockAIResponses: false,
  },
};

// Helper to check if a page should use AgentTaskPanel
window.AI_FEATURES.shouldUseAgentTaskPanel = function(pageKey) {
  if (!this.useNodeGateway) return false;
  return this.useAgentTaskPanel[pageKey] === true;
};

// Helper to get public assistant config
window.AI_FEATURES.getPublicAssistantConfig = function() {
  return this.publicAssistant;
};

// Helper to get security monitoring config
window.AI_FEATURES.getSecurityMonitoringConfig = function() {
  return this.securityMonitoring;
};
