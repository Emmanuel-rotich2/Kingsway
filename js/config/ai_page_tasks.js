/**
 * AI_PAGE_TASKS — role-aware suggested tasks for the universal AI panel.
 *
 * Shape: { '<route>': { '<roleKey>': [task, ...], '*': [task, ...] } }
 *   roleKey: role id as string ('2','3','4','5','6','63','7','8', …) or a
 *            lowercase role name; '*' applies to every role on that page.
 *   task:    { label, prompt } — prompt is a string or fn({route}) => string.
 *
 * Role ids mirror config/DashboardRouter.php ROLE_NAME_MAP:
 *   2 system admin · 3 director · 4 school admin · 5 headteacher ·
 *   6 deputy academic · 63 deputy discipline · 7 class teacher ·
 *   8 subject teacher · 9 intern · 10 accountant · 14 inventory ·
 *   16 catering · 18 boarding · 21 talent dev · 23 driver ·
 *   24 counselor/chaplain · 32 kitchen · 33 security · 34 janitor · 64 staff
 *
 * Add a task by appending to the matching route/role list — the panel picks
 * it up automatically on the next page load. Prompts should say what to
 * produce and for whom; the assistant scopes data by the user's permissions.
 */
window.AI_PAGE_TASKS = {

  /* ── Shared by several roles: '*' tasks appear for everyone on the page ── */
  academic_reports: {
    '*': [
      { label: 'Explain this report', prompt: 'Explain what this academic report is showing and the 3 most important things to notice.' },
      { label: 'Draft a summary paragraph', prompt: 'Write a short paragraph summarising this report for a staff meeting.' },
    ],
    '5': [
      { label: 'Headteacher briefing', prompt: 'Write a one-page headteacher briefing from this report: headline, key numbers, concerns, recommended actions.' },
      { label: 'Draft leadership note', prompt: 'Draft a short note to the leadership team highlighting what this report says needs attention.' },
    ],
    '3': [
      { label: 'Director summary', prompt: 'Write a director-level summary of this report: outcomes, risks, and decisions needed.' },
    ],
    '6': [
      { label: 'Academic action list', prompt: 'Turn this report into a prioritised academic action list for the deputy head.' },
    ],
    '7': [
      { label: 'Class talking points', prompt: 'Write 5 talking points from this report I can use with my class or their parents.' },
    ],
    '8': [
      { label: 'Subject takeaway', prompt: 'Summarise what this report means for my subject areas and suggest one improvement.' },
    ],
  },

  class_report_cards: {
    '*': [
      { label: 'Comment-writing help', prompt: 'Help me write clear, encouraging report-card comments. Give me 3 examples I can adapt.' },
    ],
    '7': [
      { label: 'Draft class comments', prompt: 'Draft end-of-term comments for my class. Warm, specific, under 60 words each, no learner names needed in the draft.' },
      { label: 'Parent talking points', prompt: 'Write parent-friendly talking points summarising how the class performed this term.' },
    ],
    '6': [
      { label: 'Review comment quality', prompt: 'Review the style of report-card comments for this class and suggest improvements for consistency.' },
    ],
    '5': [
      { label: 'Whole-school comment audit', prompt: 'What should leadership check before report cards go home? Give me a checklist.' },
    ],
  },

  class_records: {
    '7': [
      { label: 'Draft learner note', prompt: 'Help me write a brief professional note about a learner for their class record.' },
      { label: 'Attendance concern letter', prompt: 'Draft a polite letter to a parent about a learner\'s recent absences.' },
    ],
    '8': [
      { label: 'Subject progress note', prompt: 'Draft a short note on a learner\'s progress in my subject for their record.' },
    ],
    '6': [
      { label: 'Records summary', prompt: 'Summarise what the class records show and flag anything that needs follow-up.' },
    ],
  },

  all_discipline_cases: {
    '63': [
      { label: 'Draft case summary', prompt: 'Draft a clear case summary: facts, dates, actions taken, and recommended next step.' },
      { label: 'Parent warning letter', prompt: 'Write a firm but pastoral warning letter to a parent about a discipline case.' },
      { label: 'Hearing minutes', prompt: 'Draft minutes for a discipline hearing: attendees, statements, decision, follow-up.' },
    ],
    '5': [
      { label: 'Case review note', prompt: 'Summarise this discipline case for headteacher review: severity, pattern, and recommendation.' },
    ],
    '7': [
      { label: 'Classroom follow-up', prompt: 'Suggest classroom-level follow-up steps after this discipline incident.' },
    ],
  },

  behavior_logs: {
    '7': [
      { label: 'Log entry wording', prompt: 'Rewrite this behaviour log entry to be factual, professional and non-judgemental.' },
      { label: 'Parent update draft', prompt: 'Draft a short, calm message to a parent about a behaviour concern.' },
    ],
    '63': [
      { label: 'Pattern analysis', prompt: 'Analyse these behaviour entries for patterns and suggest interventions.' },
    ],
  },

  class_conduct_grades: {
    '7': [
      { label: 'Conduct comment', prompt: 'Write a brief conduct-grade comment for a learner that is encouraging but honest.' },
    ],
    '63': [
      { label: 'Conduct trend note', prompt: 'Summarise conduct trends for this class and recommend actions.' },
    ],
  },

  attendance_reports: {
    '*': [
      { label: 'Explain the numbers', prompt: 'Explain what these attendance figures mean and flag learners or days that need attention.' },
    ],
    '7': [
      { label: 'Parent absence letters', prompt: 'Draft short absence-notification letters to parents for learners with repeated absences.' },
      { label: 'Class attendance note', prompt: 'Write a class attendance summary I can share with my head of department.' },
    ],
    '6': [
      { label: 'Department attendance brief', prompt: 'Write a department-level attendance brief: trends, concerns, recommended actions.' },
    ],
    '5': [
      { label: 'School attendance alert', prompt: 'Summarise school-wide attendance for leadership: who is affected and what to do.' },
    ],
  },

  class_attendance_history: {
    '7': [
      { label: 'Explain a learner\'s pattern', prompt: 'Explain this learner\'s attendance pattern and suggest how to raise it with parents.' },
    ],
  },

  chronic_absenteeism: {
    '6': [
      { label: 'Intervention plan', prompt: 'Draft an intervention plan for learners with chronic absenteeism: steps, owners, timeline.' },
    ],
    '5': [
      { label: 'Leadership briefing', prompt: 'Write a leadership briefing on chronic absenteeism: scale, causes, recommended actions.' },
    ],
    '63': [
      { label: 'Home-visit talking points', prompt: 'Prepare talking points for a home visit about a learner\'s attendance.' },
    ],
  },

  manage_website: {
    '*': [
      { label: 'Draft an announcement', prompt: 'Draft a short school announcement for the website: what happened, when, what parents should do.' },
      { label: 'Write an event blurb', prompt: 'Write a 2-sentence public blurb for a school event.' },
    ],
    '5': [
      { label: 'Headteacher message', prompt: 'Draft a headteacher message for the website or newsletter on this topic.' },
    ],
    '4': [
      { label: 'Parent update', prompt: 'Draft a parent update about school operations in plain, reassuring language.' },
    ],
  },

  communications: {
    '*': [
      { label: 'Draft a message', prompt: 'Draft a clear message to parents/staff about this: what, when, what to do.' },
      { label: 'Shorten it', prompt: 'Rewrite this message shorter and more direct, keeping the key facts.' },
    ],
    '4': [
      { label: 'Staff circular', prompt: 'Draft a staff circular with clear sections: purpose, actions required, deadline.' },
    ],
    '5': [
      { label: 'Official announcement', prompt: 'Draft an official headteacher announcement with appropriate tone and formality.' },
    ],
    '7': [
      { label: 'Class parent note', prompt: 'Draft a friendly note to parents of my class about an upcoming activity or request.' },
    ],
    '8': [
      { label: 'Subject update', prompt: 'Draft a short update to parents about my subject this week.' },
    ],
  },

  all_lesson_plans: {
    '8': [
      { label: 'Lesson outline', prompt: 'Outline a 40-minute CBC lesson on the topic I describe: objectives, activities, assessment.' },
      { label: 'Differentiation ideas', prompt: 'Suggest 3 ways to differentiate this lesson for mixed-ability learners.' },
    ],
    '7': [
      { label: 'Class lesson plan', prompt: 'Draft a simple lesson plan for my class: starter, main activity, plenary, homework.' },
    ],
    '6': [
      { label: 'Scheme coverage check', prompt: 'Review which CBC strands this term\'s plans cover and what is missing.' },
    ],
    '5': [
      { label: 'Coverage report', prompt: 'Summarise lesson-plan coverage across the school and flag gaps.' },
    ],
  },

  assessments_exams: {
    '8': [
      { label: 'Question draft', prompt: 'Draft 5 exam-style questions with mark allocations for the topic I name.' },
      { label: 'Rubric descriptors', prompt: 'Write rubric descriptors for levels: beginning, developing, secure, outstanding.' },
    ],
    '7': [
      { label: 'Class test draft', prompt: 'Draft a short class test: 10 questions covering this topic, with answers.' },
    ],
    '6': [
      { label: 'Assessment blueprint', prompt: 'Propose an assessment blueprint: topics, weightings, question types, duration.' },
    ],
    '5': [
      { label: 'Exam readiness note', prompt: 'Write an exam-readiness note for leadership: coverage, risks, invigilation needs.' },
    ],
  },

  budget_overview: {
    '3': [
      { label: 'Finance briefing', prompt: 'Write a director-level finance briefing: position, variances, decisions needed.' },
    ],
    '4': [
      { label: 'Budget note', prompt: 'Explain this budget position in plain language for a non-finance reader.' },
    ],
    '10': [
      { label: 'Variance explanation', prompt: 'Explain the largest budget variances and suggest causes to verify.' },
    ],
  },

  boarding_roll_call: {
    '18': [
      { label: 'Roll-call note', prompt: 'Help me write a brief note on tonight\'s roll-call issues for the matron\'s log.' },
    ],
    '7': [
      { label: 'Boarder absence note', prompt: 'Draft a note to parents of a boarder who missed roll call.' },
    ],
  },

  /* ── System admin (role 2): infrastructure only ── */
  system_health: {
    '2': [
      { label: 'Explain this alert', prompt: 'Explain this system-health alert in plain language: likely cause and first checks.' },
      { label: 'Draft incident note', prompt: 'Draft a short incident note for the ops log: what happened, impact, actions taken.' },
    ],
  },
  maintenance_mode: {
    '2': [
      { label: 'Draft maintenance notice', prompt: 'Draft a clear maintenance-window notice for staff: what, when, expected downtime, what to do.' },
    ],
  },
  authentication_logs: {
    '2': [
      { label: 'Summarise these events', prompt: 'Summarise these authentication events: anomalies, patterns, recommended actions.' },
    ],
  },
  api_metrics: {
    '2': [
      { label: 'Explain the load', prompt: 'Explain what these API metrics show and whether anything needs attention.' },
    ],
  },

  /* ── System admin deep coverage: monitoring, security, ops ── */
  system_health: {
    '2': [
      { label: 'Health briefing', prompt: 'Write a system-health briefing: overall status, active alerts, and recommended actions.' },
      { label: 'Explain this alert', prompt: 'Explain this system-health alert in plain language: likely cause and first checks.' },
      { label: 'Draft incident note', prompt: 'Draft a short incident note for the ops log: what happened, impact, actions taken.' },
      { label: 'Trend check', prompt: 'What do the health metrics on this page suggest about system stability this week?' },
    ],
  },
  maintenance_mode: {
    '2': [
      { label: 'Draft maintenance notice', prompt: 'Draft a clear maintenance-window notice for staff: what, when, expected downtime, what to do.' },
      { label: 'Pre-flight checklist', prompt: 'Give me a pre-maintenance checklist for taking this system into maintenance mode safely.' },
      { label: 'Post-maintenance report', prompt: 'Draft a post-maintenance report template: window, changes, verification, follow-ups.' },
    ],
  },
  authentication_logs: {
    '2': [
      { label: 'Summarise these events', prompt: 'Summarise these authentication events: anomalies, patterns, recommended actions.' },
      { label: 'Brute-force check', prompt: 'Do these login events look like brute force or credential stuffing? Explain why.' },
      { label: 'Access review', prompt: 'Review these authentication events for suspicious access patterns and flag accounts to check.' },
    ],
  },
  failed_login_attempts: {
    '2': [
      { label: 'Attack analysis', prompt: 'Analyse these failed login attempts: source patterns, targeted accounts, and recommended blocks.' },
      { label: 'Lockout advice', prompt: 'Which accounts look like they are under attack and should be temporarily locked or notified?' },
    ],
  },
  audit_logs: {
    '2': [
      { label: 'Audit digest', prompt: 'Summarise these audit log entries: who did what, anything sensitive or unusual.' },
      { label: 'Privilege check', prompt: 'Flag any audit entries involving permission or role changes that need verification.' },
    ],
  },
  activity_audit_logs: {
    '2': [
      { label: 'Activity digest', prompt: 'Summarise these activity audit entries and flag anything out of the ordinary.' },
    ],
  },
  error_logs: {
    '2': [
      { label: 'Error triage', prompt: 'Triage these errors: most serious first, likely causes, suggested fixes.' },
      { label: 'Recurring issue', prompt: 'Which errors here are recurring and should become tickets? Summarise each.' },
    ],
  },
  log_viewer: {
    '2': [
      { label: 'Explain these logs', prompt: 'Explain what these log entries mean and whether they indicate a problem.' },
      { label: 'Trace request', prompt: 'Help me trace what happened around a specific request or error in these logs.' },
    ],
  },
  active_sessions: {
    '2': [
      { label: 'Session review', prompt: 'Review these active sessions: unusual locations, long-lived sessions, sessions to revoke.' },
      { label: 'Idle cleanup', prompt: 'Which sessions look idle or abandoned and should be ended?' },
    ],
  },
  api_metrics: {
    '2': [
      { label: 'Explain the load', prompt: 'Explain what these API metrics show and whether anything needs attention.' },
      { label: 'Capacity check', prompt: 'Do these metrics suggest we are approaching capacity? What should we scale first?' },
      { label: 'Slow endpoints', prompt: 'Which endpoints look slowest and what are likely causes?' },
    ],
  },
  api_explorer: {
    '2': [
      { label: 'Endpoint review', prompt: 'Review this API endpoint: method, expected payload, and what a correct response looks like.' },
      { label: 'Test suggestion', prompt: 'Suggest test cases for this endpoint including edge cases and auth failures.' },
    ],
  },
  backups: {
    '2': [
      { label: 'Backup status', prompt: 'Assess the backup situation on this page: last successful run, gaps, and risks.' },
      { label: 'Restore plan', prompt: 'Draft a restore plan for the most recent backup, step by step.' },
      { label: 'Retention advice', prompt: 'Review the backup retention settings shown and recommend adjustments.' },
    ],
  },
  manage_users: {
    '2': [
      { label: 'Account review', prompt: 'Review these user accounts: dormant accounts, privilege concerns, recommended actions.' },
      { label: 'Offboarding note', prompt: 'Draft an offboarding checklist for disabling a staff account safely.' },
    ],
    '5': [
      { label: 'Staff account note', prompt: 'Help me write a note about a staff account issue for the records.' },
    ],
  },
  manage_roles: {
    '2': [
      { label: 'Role audit', prompt: 'Audit these role definitions: over-privileged roles, unused permissions, risks.' },
      { label: 'Least privilege', prompt: 'Suggest least-privilege adjustments for the roles shown on this page.' },
    ],
  },
  permission_changes: {
    '2': [
      { label: 'Change digest', prompt: 'Summarise these permission changes: who changed what, anything that needs verification.' },
      { label: 'Escalation check', prompt: 'Flag any permission changes that look like privilege escalation.' },
    ],
  },
  permission_policies: {
    '2': [
      { label: 'Policy review', prompt: 'Review these permission policies for gaps or conflicts and suggest fixes.' },
      { label: 'Explain policy', prompt: 'Explain what this permission policy controls in plain language.' },
    ],
  },
  analytics_catalogue: {
    '2': [
      { label: 'Catalogue overview', prompt: 'Explain what analytics are available here and which matter most for operations.' },
      { label: 'Metric explainer', prompt: 'Explain what the selected metric measures and how it is calculated.' },
    ],
    '3': [
      { label: 'Director metrics', prompt: 'Which metrics here matter most for director-level oversight? Summarise them.' },
    ],
  },
  manage_system_announcements: {
    '2': [
      { label: 'Draft system notice', prompt: 'Draft a clear system notice for staff: what changed, when, what to do.' },
      { label: 'Maintenance wording', prompt: 'Write maintenance-window wording that is clear and reassuring for staff.' },
    ],
  },
  background_jobs: {
    '2': [
      { label: 'Job health', prompt: 'Assess the health of these background jobs: failures, stuck jobs, and recommended actions.' },
      { label: 'Failure triage', prompt: 'Explain why these jobs failed and suggest fixes for each.' },
    ],
  },

  /* ── Fallback for every other page ── */
  '*': {
    '*': [
      { label: 'Explain this page', prompt: 'Explain what this page shows and the most important things to check.' },
      { label: 'Draft a note', prompt: 'Help me draft a short professional note based on what is on this page.' },
      { label: 'Summarise for my role', prompt: 'Summarise what on this page matters most for my role, and what action to take.' },
    ],
  },
};
