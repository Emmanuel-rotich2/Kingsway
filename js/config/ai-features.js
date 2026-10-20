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
    // Drafting pages (already migrated)
    'ai_lesson_plan_draft': true,
    'ai_scheme_draft': true,
    'ai_assessment_draft': true,
    
    // Review/Summary pages (being migrated)
    'ai_academic_reviews': true,
    'ai_activities_review': true,
    'ai_attendance_summary': true,
    'ai_boarding_summary': false,  // Pre-generated, no queue
    'ai_catering_review': true,
    'ai_counseling_review': true,
    'ai_facilities_review': true,
    'ai_health_welfare': true,
    'ai_inventory_review': true,
    'ai_kpi_brief': true,
    'ai_staff_hr': true,
    'ai_transport_summary': false, // Pre-generated, no queue
    'payment_reconciliation_ai': true,
    
    // Timetable/Scheduling pages
    'manage_timetable': true,
    'exam_periods': true,
    
    // Admissions
    'admissions_workspace': true,
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