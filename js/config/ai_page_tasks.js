/**
 * AI_PAGE_TASKS — role-aware suggested tasks for the universal AI panel.
 *
 * Generated base covers every known page route; hand-tuned entries override/extend.
 * Shape: { '<route>': { '<roleKey>': [task,...], '*': [...] } }
 * task: { label, prompt } where prompt is string or fn({route}) => string.
 */
window.AI_PAGE_TASKS = {
  "_shell_bootstrap": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this shell bootstrap page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this shell bootstrap page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this shell bootstrap page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this shell bootstrap page."
      }
    ]
  },
  "academic_calendar": {
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic calendar page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this academic calendar page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic calendar page for a Headteacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic calendar page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic calendar page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this academic calendar page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic calendar page for a Deputy Head - Academic."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic calendar page."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic calendar page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this academic calendar page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic calendar page for a Class Teacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic calendar page."
      }
    ],
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic calendar page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this academic calendar page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic calendar page for a Subject Teacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic calendar page."
      }
    ],
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic calendar page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this academic calendar page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic calendar page for a Intern/Student Teacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic calendar page."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic calendar page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this academic calendar page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic calendar page for a Accountant."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic calendar page."
      }
    ],
    "14": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic calendar page for a Inventory Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Inventory Manager based on this academic calendar page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic calendar page for a Inventory Manager."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic calendar page."
      }
    ],
    "16": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic calendar page for a Catering Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Catering Manager based on this academic calendar page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic calendar page for a Catering Manager."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic calendar page."
      }
    ],
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic calendar page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this academic calendar page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic calendar page for a Boarding Master/Matron."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic calendar page."
      }
    ],
    "21": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic calendar page for a Talent Development: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Talent Development based on this academic calendar page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic calendar page for a Talent Development."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic calendar page."
      }
    ],
    "23": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic calendar page for a Driver: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Driver based on this academic calendar page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic calendar page for a Driver."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic calendar page."
      }
    ],
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic calendar page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this academic calendar page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic calendar page for a Counselor/Chaplain."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic calendar page."
      }
    ],
    "32": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic calendar page for a Kitchen Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Kitchen Staff based on this academic calendar page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic calendar page for a Kitchen Staff."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic calendar page."
      }
    ],
    "33": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic calendar page for a Security Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Security Staff based on this academic calendar page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic calendar page for a Security Staff."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic calendar page."
      }
    ],
    "34": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic calendar page for a Janitor: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Janitor based on this academic calendar page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic calendar page for a Janitor."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic calendar page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic calendar page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this academic calendar page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic calendar page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic calendar page."
      }
    ],
    "64": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic calendar page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this academic calendar page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic calendar page for a Staff."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic calendar page."
      }
    ],
    "71": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic calendar page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this academic calendar page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic calendar page for a Staff."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic calendar page."
      }
    ],
    "72": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic calendar page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this academic calendar page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic calendar page for a Staff."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic calendar page."
      }
    ]
  },
  "academic_content_reconciliation": {
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic content reconciliation page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this academic content reconciliation page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this academic content reconciliation page for a Headteacher."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this academic content reconciliation page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic content reconciliation page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this academic content reconciliation page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this academic content reconciliation page for a Deputy Head - Academic."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this academic content reconciliation page."
      }
    ]
  },
  "academic_planning_oversight": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic planning oversight page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this academic planning oversight page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic planning oversight page for a Director/Owner."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic planning oversight page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic planning oversight page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this academic planning oversight page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic planning oversight page for a School Administrator."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic planning oversight page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic planning oversight page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this academic planning oversight page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic planning oversight page for a Headteacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic planning oversight page."
      }
    ]
  },
  "academic_reports": {
    "3": [
      {
        "label": "Director summary",
        "prompt": "Write a director-level summary of this report: outcomes, risks, and decisions needed."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic reports page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this academic reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this academic reports page for a School Administrator."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this academic reports page for stakeholders, tailored to a School Administrator."
      }
    ],
    "5": [
      {
        "label": "Headteacher briefing",
        "prompt": "Write a one-page headteacher briefing from this report: headline, key numbers, concerns, recommended actions."
      },
      {
        "label": "Draft leadership note",
        "prompt": "Draft a short note to the leadership team highlighting what this report says needs attention."
      }
    ],
    "6": [
      {
        "label": "Academic action list",
        "prompt": "Turn this report into a prioritised academic action list for the deputy head."
      }
    ],
    "7": [
      {
        "label": "Class talking points",
        "prompt": "Write 5 talking points from this report I can use with my class or their parents."
      }
    ],
    "8": [
      {
        "label": "Subject takeaway",
        "prompt": "Summarise what this report means for my subject areas and suggest one improvement."
      }
    ],
    "*": [
      {
        "label": "Explain this report",
        "prompt": "Explain what this academic report is showing and the 3 most important things to notice."
      },
      {
        "label": "Draft a summary paragraph",
        "prompt": "Write a short paragraph summarising this report for a staff meeting."
      }
    ]
  },
  "academic_students": {
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic students page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this academic students page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic students page for a Deputy Head - Academic."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic students page."
      }
    ]
  },
  "academic_years": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic years page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this academic years page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic years page for a Director/Owner."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic years page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic years page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this academic years page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic years page for a School Administrator."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic years page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic years page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this academic years page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic years page for a Headteacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic years page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this academic years page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this academic years page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this academic years page for a Deputy Head - Academic."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this academic years page."
      }
    ]
  },
  "account_settings": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this account settings page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this account settings page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this account settings page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this account settings page."
      }
    ]
  },
  "account_status": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this account status page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this account status page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this account status page for a System Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this account status page."
      }
    ]
  },
  "active_sessions": {
    "2": [
      {
        "label": "Session review",
        "prompt": "Review these active sessions: unusual locations, long-lived sessions, sessions to revoke."
      },
      {
        "label": "Idle cleanup",
        "prompt": "Which sessions look idle or abandoned and should be ended?"
      }
    ]
  },
  "activity_achievements": {
    "21": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this activity achievements page for a Talent Development: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Talent Development based on this activity achievements page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this activity achievements page for a Talent Development? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this activity achievements page."
      }
    ]
  },
  "activity_audit_logs": {
    "2": [
      {
        "label": "Activity digest",
        "prompt": "Summarise these activity audit entries and flag anything out of the ordinary."
      }
    ]
  },
  "activity_participants": {
    "21": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this activity participants page for a Talent Development: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Talent Development based on this activity participants page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this activity participants page for a Talent Development? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this activity participants page."
      }
    ]
  },
  "activity_reports": {
    "21": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this activity reports page for a Talent Development: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Talent Development based on this activity reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this activity reports page for a Talent Development."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this activity reports page for stakeholders, tailored to a Talent Development."
      }
    ]
  },
  "add_results": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this add results page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this add results page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this add results page for a Staff."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this add results page."
      }
    ]
  },
  "adjustments": {
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this adjustments page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this adjustments page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this adjustments page for a Accountant? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this adjustments page."
      }
    ]
  },
  "admission_interviews": {
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this admission interviews page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this admission interviews page."
      },
      {
        "label": "Summarize pipeline",
        "prompt": "Summarize the admissions pipeline/status on this admission interviews page for a Headteacher."
      },
      {
        "label": "Draft applicant communication",
        "prompt": "Draft an applicant/parent communication based on this admission interviews page."
      }
    ]
  },
  "admission_status": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this admission status page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this admission status page."
      },
      {
        "label": "Summarize pipeline",
        "prompt": "Summarize the admissions pipeline/status on this admission status page for a Staff."
      },
      {
        "label": "Draft applicant communication",
        "prompt": "Draft an applicant/parent communication based on this admission status page."
      }
    ]
  },
  "admissions_academic_applications": {
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this admissions academic applications page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this admissions academic applications page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this admissions academic applications page for a Headteacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this admissions academic applications page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this admissions academic applications page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this admissions academic applications page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this admissions academic applications page for a Deputy Head - Academic."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this admissions academic applications page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this admissions academic applications page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this admissions academic applications page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this admissions academic applications page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this admissions academic applications page."
      }
    ]
  },
  "admissions_admission_decisions": {
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this admissions admission decisions page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this admissions admission decisions page."
      },
      {
        "label": "Summarize pipeline",
        "prompt": "Summarize the admissions pipeline/status on this admissions admission decisions page for a Headteacher."
      },
      {
        "label": "Draft applicant communication",
        "prompt": "Draft an applicant/parent communication based on this admissions admission decisions page."
      }
    ]
  },
  "admissions_class_placement": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this admissions class placement page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this admissions class placement page."
      },
      {
        "label": "Summarize pipeline",
        "prompt": "Summarize the admissions pipeline/status on this admissions class placement page for a School Administrator."
      },
      {
        "label": "Draft applicant communication",
        "prompt": "Draft an applicant/parent communication based on this admissions class placement page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this admissions class placement page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this admissions class placement page."
      },
      {
        "label": "Summarize pipeline",
        "prompt": "Summarize the admissions pipeline/status on this admissions class placement page for a Deputy Head - Academic."
      },
      {
        "label": "Draft applicant communication",
        "prompt": "Draft an applicant/parent communication based on this admissions class placement page."
      }
    ]
  },
  "admissions_headteacher_applications": {
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this admissions headteacher applications page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this admissions headteacher applications page."
      },
      {
        "label": "Summarize pipeline",
        "prompt": "Summarize the admissions pipeline/status on this admissions headteacher applications page for a Headteacher."
      },
      {
        "label": "Draft applicant communication",
        "prompt": "Draft an applicant/parent communication based on this admissions headteacher applications page."
      }
    ]
  },
  "admissions_pending_admission_approvals": {
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this admissions pending admission approvals page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this admissions pending admission approvals page."
      },
      {
        "label": "Summarize pipeline",
        "prompt": "Summarize the admissions pipeline/status on this admissions pending admission approvals page for a Headteacher."
      },
      {
        "label": "Draft applicant communication",
        "prompt": "Draft an applicant/parent communication based on this admissions pending admission approvals page."
      }
    ]
  },
  "all_discipline_cases": {
    "5": [
      {
        "label": "Case review note",
        "prompt": "Summarise this discipline case for headteacher review: severity, pattern, and recommendation."
      }
    ],
    "7": [
      {
        "label": "Classroom follow-up",
        "prompt": "Suggest classroom-level follow-up steps after this discipline incident."
      }
    ],
    "63": [
      {
        "label": "Draft case summary",
        "prompt": "Draft a clear case summary: facts, dates, actions taken, and recommended next step."
      },
      {
        "label": "Parent warning letter",
        "prompt": "Write a firm but pastoral warning letter to a parent about a discipline case."
      },
      {
        "label": "Hearing minutes",
        "prompt": "Draft minutes for a discipline hearing: attendees, statements, decision, follow-up."
      }
    ],
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this all discipline cases page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this all discipline cases page."
      },
      {
        "label": "Summarize cases",
        "prompt": "Summarize discipline/conduct issues on this all discipline cases page and recommend next steps for a Staff."
      },
      {
        "label": "Draft case note",
        "prompt": "Draft a factual case note or parent communication based on this all discipline cases page."
      }
    ]
  },
  "all_lesson_plans": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this all lesson plans page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this all lesson plans page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this all lesson plans page for a School Administrator."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this all lesson plans page."
      }
    ],
    "5": [
      {
        "label": "Coverage report",
        "prompt": "Summarise lesson-plan coverage across the school and flag gaps."
      }
    ],
    "6": [
      {
        "label": "Scheme coverage check",
        "prompt": "Review which CBC strands this term's plans cover and what is missing."
      }
    ],
    "7": [
      {
        "label": "Class lesson plan",
        "prompt": "Draft a simple lesson plan for my class: starter, main activity, plenary, homework."
      }
    ],
    "8": [
      {
        "label": "Lesson outline",
        "prompt": "Outline a 40-minute CBC lesson on the topic I describe: objectives, activities, assessment."
      },
      {
        "label": "Differentiation ideas",
        "prompt": "Suggest 3 ways to differentiate this lesson for mixed-ability learners."
      }
    ]
  },
  "all_parents": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this all parents page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this all parents page."
      },
      {
        "label": "Parent summary",
        "prompt": "Summarize parent/guardian matters on this all parents page for a Director/Owner."
      },
      {
        "label": "Draft parent message",
        "prompt": "Draft a clear parent/guardian message based on this all parents page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this all parents page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this all parents page."
      },
      {
        "label": "Parent summary",
        "prompt": "Summarize parent/guardian matters on this all parents page for a School Administrator."
      },
      {
        "label": "Draft parent message",
        "prompt": "Draft a clear parent/guardian message based on this all parents page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this all parents page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this all parents page."
      },
      {
        "label": "Parent summary",
        "prompt": "Summarize parent/guardian matters on this all parents page for a Headteacher."
      },
      {
        "label": "Draft parent message",
        "prompt": "Draft a clear parent/guardian message based on this all parents page."
      }
    ]
  },
  "all_staff": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this all staff page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this all staff page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this all staff page for a Staff."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this all staff page."
      }
    ]
  },
  "all_students": {
    "21": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this all students page for a Talent Development: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Talent Development based on this all students page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this all students page for a Talent Development."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this all students page."
      }
    ]
  },
  "all_teachers": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this all teachers page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this all teachers page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this all teachers page for a Director/Owner."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this all teachers page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this all teachers page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this all teachers page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this all teachers page for a School Administrator."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this all teachers page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this all teachers page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this all teachers page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this all teachers page for a Headteacher."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this all teachers page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this all teachers page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this all teachers page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this all teachers page for a Deputy Head - Academic."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this all teachers page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this all teachers page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this all teachers page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this all teachers page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this all teachers page."
      }
    ]
  },
  "alumni_management": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this alumni management page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this alumni management page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this alumni management page for a Director/Owner? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this alumni management page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this alumni management page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this alumni management page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this alumni management page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this alumni management page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this alumni management page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this alumni management page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this alumni management page for a Headteacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this alumni management page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this alumni management page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this alumni management page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this alumni management page for a Deputy Head - Academic? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this alumni management page."
      }
    ]
  },
  "analytics_catalogue": {
    "2": [
      {
        "label": "Catalogue overview",
        "prompt": "Explain what analytics are available here and which matter most for operations."
      },
      {
        "label": "Metric explainer",
        "prompt": "Explain what the selected metric measures and how it is calculated."
      }
    ],
    "3": [
      {
        "label": "Director metrics",
        "prompt": "Which metrics here matter most for director-level oversight? Summarise them."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this analytics catalogue page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this analytics catalogue page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this analytics catalogue page for a School Administrator."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this analytics catalogue page for stakeholders, tailored to a School Administrator."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this analytics catalogue page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this analytics catalogue page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this analytics catalogue page for a Headteacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this analytics catalogue page for stakeholders, tailored to a Headteacher."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this analytics catalogue page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this analytics catalogue page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this analytics catalogue page for a Deputy Head - Academic."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this analytics catalogue page for stakeholders, tailored to a Deputy Head - Academic."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this analytics catalogue page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this analytics catalogue page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this analytics catalogue page for a Class Teacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this analytics catalogue page for stakeholders, tailored to a Class Teacher."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this analytics catalogue page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this analytics catalogue page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this analytics catalogue page for a Accountant."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this analytics catalogue page for stakeholders, tailored to a Accountant."
      }
    ],
    "14": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this analytics catalogue page for a Inventory Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Inventory Manager based on this analytics catalogue page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this analytics catalogue page for a Inventory Manager."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this analytics catalogue page for stakeholders, tailored to a Inventory Manager."
      }
    ],
    "16": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this analytics catalogue page for a Catering Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Catering Manager based on this analytics catalogue page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this analytics catalogue page for a Catering Manager."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this analytics catalogue page for stakeholders, tailored to a Catering Manager."
      }
    ],
    "32": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this analytics catalogue page for a Kitchen Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Kitchen Staff based on this analytics catalogue page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this analytics catalogue page for a Kitchen Staff."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this analytics catalogue page for stakeholders, tailored to a Kitchen Staff."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this analytics catalogue page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this analytics catalogue page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this analytics catalogue page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this analytics catalogue page for stakeholders, tailored to a Deputy Head - Discipline."
      }
    ]
  },
  "api_explorer": {
    "2": [
      {
        "label": "Endpoint review",
        "prompt": "Review this API endpoint: method, expected payload, and what a correct response looks like."
      },
      {
        "label": "Test suggestion",
        "prompt": "Suggest test cases for this endpoint including edge cases and auth failures."
      }
    ]
  },
  "api_metrics": {
    "2": [
      {
        "label": "Explain the load",
        "prompt": "Explain what these API metrics show and whether anything needs attention."
      },
      {
        "label": "Capacity check",
        "prompt": "Do these metrics suggest we are approaching capacity? What should we scale first?"
      },
      {
        "label": "Slow endpoints",
        "prompt": "Which endpoints look slowest and what are likely causes?"
      }
    ]
  },
  "approved_lesson_plans": {
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this approved lesson plans page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this approved lesson plans page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this approved lesson plans page for a Deputy Head - Academic."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this approved lesson plans page."
      }
    ]
  },
  "assemblies": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this assemblies page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this assemblies page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this assemblies page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this assemblies page."
      }
    ],
    "21": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this assemblies page for a Talent Development: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Talent Development based on this assemblies page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this assemblies page for a Talent Development? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this assemblies page."
      }
    ]
  },
  "assessment_overview": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this assessment overview page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this assessment overview page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this assessment overview page for a Staff."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this assessment overview page."
      }
    ]
  },
  "assessment_rubrics": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this assessment rubrics page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this assessment rubrics page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this assessment rubrics page for a School Administrator."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this assessment rubrics page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this assessment rubrics page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this assessment rubrics page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this assessment rubrics page for a Headteacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this assessment rubrics page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this assessment rubrics page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this assessment rubrics page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this assessment rubrics page for a Deputy Head - Academic."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this assessment rubrics page."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this assessment rubrics page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this assessment rubrics page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this assessment rubrics page for a Class Teacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this assessment rubrics page."
      }
    ],
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this assessment rubrics page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this assessment rubrics page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this assessment rubrics page for a Subject Teacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this assessment rubrics page."
      }
    ],
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this assessment rubrics page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this assessment rubrics page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this assessment rubrics page for a Intern/Student Teacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this assessment rubrics page."
      }
    ]
  },
  "assessments_exams": {
    "5": [
      {
        "label": "Exam readiness note",
        "prompt": "Write an exam-readiness note for leadership: coverage, risks, invigilation needs."
      }
    ],
    "6": [
      {
        "label": "Assessment blueprint",
        "prompt": "Propose an assessment blueprint: topics, weightings, question types, duration."
      }
    ],
    "7": [
      {
        "label": "Class test draft",
        "prompt": "Draft a short class test: 10 questions covering this topic, with answers."
      }
    ],
    "8": [
      {
        "label": "Question draft",
        "prompt": "Draft 5 exam-style questions with mark allocations for the topic I name."
      },
      {
        "label": "Rubric descriptors",
        "prompt": "Write rubric descriptors for levels: beginning, developing, secure, outstanding."
      }
    ],
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this assessments exams page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this assessments exams page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this assessments exams page for a Staff."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this assessments exams page."
      }
    ]
  },
  "asset_purchases": {
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this asset purchases page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this asset purchases page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this asset purchases page for a Accountant."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this asset purchases page."
      }
    ]
  },
  "assign_class_teachers": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this assign class teachers page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this assign class teachers page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this assign class teachers page for a School Administrator."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this assign class teachers page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this assign class teachers page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this assign class teachers page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this assign class teachers page for a Deputy Head - Academic."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this assign class teachers page."
      }
    ]
  },
  "assign_subjects_to_teachers": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this assign subjects to teachers page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this assign subjects to teachers page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this assign subjects to teachers page for a School Administrator."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this assign subjects to teachers page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this assign subjects to teachers page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this assign subjects to teachers page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this assign subjects to teachers page for a Deputy Head - Academic."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this assign subjects to teachers page."
      }
    ]
  },
  "at_risk_students": {
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this at risk students page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this at risk students page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this at risk students page for a Counselor/Chaplain."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this at risk students page."
      }
    ]
  },
  "attendance_reports": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this attendance reports page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this attendance reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this attendance reports page for a Director/Owner."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this attendance reports page for stakeholders, tailored to a Director/Owner."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this attendance reports page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this attendance reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this attendance reports page for a School Administrator."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this attendance reports page for stakeholders, tailored to a School Administrator."
      }
    ],
    "5": [
      {
        "label": "School attendance alert",
        "prompt": "Summarise school-wide attendance for leadership: who is affected and what to do."
      }
    ],
    "6": [
      {
        "label": "Department attendance brief",
        "prompt": "Write a department-level attendance brief: trends, concerns, recommended actions."
      }
    ],
    "7": [
      {
        "label": "Parent absence letters",
        "prompt": "Draft short absence-notification letters to parents for learners with repeated absences."
      },
      {
        "label": "Class attendance note",
        "prompt": "Write a class attendance summary I can share with my head of department."
      }
    ],
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this attendance reports page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this attendance reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this attendance reports page for a Boarding Master/Matron."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this attendance reports page for stakeholders, tailored to a Boarding Master/Matron."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this attendance reports page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this attendance reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this attendance reports page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this attendance reports page for stakeholders, tailored to a Deputy Head - Discipline."
      }
    ],
    "*": [
      {
        "label": "Explain the numbers",
        "prompt": "Explain what these attendance figures mean and flag learners or days that need attention."
      }
    ]
  },
  "attendance_trends": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this attendance trends page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this attendance trends page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this attendance trends page for a Director/Owner."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this attendance trends page for stakeholders, tailored to a Director/Owner."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this attendance trends page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this attendance trends page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this attendance trends page for a Deputy Head - Academic."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this attendance trends page for stakeholders, tailored to a Deputy Head - Academic."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this attendance trends page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this attendance trends page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this attendance trends page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this attendance trends page for stakeholders, tailored to a Deputy Head - Discipline."
      }
    ]
  },
  "audit_logs": {
    "2": [
      {
        "label": "Audit digest",
        "prompt": "Summarise these audit log entries: who did what, anything sensitive or unusual."
      },
      {
        "label": "Privilege check",
        "prompt": "Flag any audit entries involving permission or role changes that need verification."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this audit logs page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this audit logs page."
      },
      {
        "label": "Security/ops check",
        "prompt": "Identify security, operational, or configuration risks on this audit logs page for a Accountant."
      },
      {
        "label": "Draft ops note",
        "prompt": "Draft an operations/security note based on this audit logs page."
      }
    ]
  },
  "authentication_logs": {
    "2": [
      {
        "label": "Summarise these events",
        "prompt": "Summarise these authentication events: anomalies, patterns, recommended actions."
      },
      {
        "label": "Brute-force check",
        "prompt": "Do these login events look like brute force or credential stuffing? Explain why."
      },
      {
        "label": "Access review",
        "prompt": "Review these authentication events for suspicious access patterns and flag accounts to check."
      }
    ]
  },
  "background_jobs": {
    "2": [
      {
        "label": "Job health",
        "prompt": "Assess the health of these background jobs: failures, stuck jobs, and recommended actions."
      },
      {
        "label": "Failure triage",
        "prompt": "Explain why these jobs failed and suggest fixes for each."
      }
    ]
  },
  "backups": {
    "2": [
      {
        "label": "Backup status",
        "prompt": "Assess the backup situation on this page: last successful run, gaps, and risks."
      },
      {
        "label": "Restore plan",
        "prompt": "Draft a restore plan for the most recent backup, step by step."
      },
      {
        "label": "Retention advice",
        "prompt": "Review the backup retention settings shown and recommend adjustments."
      }
    ]
  },
  "balances_by_class": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this balances by class page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this balances by class page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this balances by class page for a Staff."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this balances by class page."
      }
    ]
  },
  "bank_accounts": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this bank accounts page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this bank accounts page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this bank accounts page for a Director/Owner."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this bank accounts page."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this bank accounts page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this bank accounts page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this bank accounts page for a Accountant."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this bank accounts page."
      }
    ]
  },
  "bank_transactions": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this bank transactions page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this bank transactions page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this bank transactions page for a Director/Owner."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this bank transactions page."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this bank transactions page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this bank transactions page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this bank transactions page for a Accountant."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this bank transactions page."
      }
    ]
  },
  "behavior_logs": {
    "7": [
      {
        "label": "Log entry wording",
        "prompt": "Rewrite this behaviour log entry to be factual, professional and non-judgemental."
      },
      {
        "label": "Parent update draft",
        "prompt": "Draft a short, calm message to a parent about a behaviour concern."
      }
    ],
    "63": [
      {
        "label": "Pattern analysis",
        "prompt": "Analyse these behaviour entries for patterns and suggest interventions."
      }
    ]
  },
  "boarding_reports": {
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this boarding reports page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this boarding reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this boarding reports page for a Boarding Master/Matron."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this boarding reports page for stakeholders, tailored to a Boarding Master/Matron."
      }
    ]
  },
  "boarding_roll_call": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this boarding roll call page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this boarding roll call page."
      },
      {
        "label": "Boarding summary",
        "prompt": "Summarize boarding/welfare issues on this boarding roll call page for a School Administrator."
      },
      {
        "label": "Draft boarding log",
        "prompt": "Draft a boarding log or parent/staff note based on this boarding roll call page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this boarding roll call page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this boarding roll call page."
      },
      {
        "label": "Boarding summary",
        "prompt": "Summarize boarding/welfare issues on this boarding roll call page for a Headteacher."
      },
      {
        "label": "Draft boarding log",
        "prompt": "Draft a boarding log or parent/staff note based on this boarding roll call page."
      }
    ],
    "7": [
      {
        "label": "Boarder absence note",
        "prompt": "Draft a note to parents of a boarder who missed roll call."
      }
    ],
    "18": [
      {
        "label": "Roll-call note",
        "prompt": "Help me write a brief note on tonight's roll-call issues for the matron's log."
      }
    ]
  },
  "boarding_students": {
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this boarding students page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this boarding students page."
      },
      {
        "label": "Boarding summary",
        "prompt": "Summarize boarding/welfare issues on this boarding students page for a Boarding Master/Matron."
      },
      {
        "label": "Draft boarding log",
        "prompt": "Draft a boarding log or parent/staff note based on this boarding students page."
      }
    ]
  },
  "bootstrap_school_administrator": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this bootstrap school administrator page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this bootstrap school administrator page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this bootstrap school administrator page for a System Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this bootstrap school administrator page."
      }
    ]
  },
  "budget_actuals": {
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this budget actuals page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this budget actuals page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this budget actuals page for a Accountant."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this budget actuals page."
      }
    ]
  },
  "budget_overview": {
    "3": [
      {
        "label": "Finance briefing",
        "prompt": "Write a director-level finance briefing: position, variances, decisions needed."
      }
    ],
    "4": [
      {
        "label": "Budget note",
        "prompt": "Explain this budget position in plain language for a non-finance reader."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this budget overview page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this budget overview page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this budget overview page for a Headteacher."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this budget overview page."
      }
    ],
    "10": [
      {
        "label": "Variance explanation",
        "prompt": "Explain the largest budget variances and suggest causes to verify."
      }
    ]
  },
  "catalog_orders_management": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this catalog orders management page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this catalog orders management page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this catalog orders management page for a Director/Owner."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this catalog orders management page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this catalog orders management page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this catalog orders management page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this catalog orders management page for a School Administrator."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this catalog orders management page."
      }
    ],
    "14": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this catalog orders management page for a Inventory Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Inventory Manager based on this catalog orders management page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this catalog orders management page for a Inventory Manager."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this catalog orders management page."
      }
    ]
  },
  "catering_boarding_students": {
    "16": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this catering boarding students page for a Catering Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Catering Manager based on this catering boarding students page."
      },
      {
        "label": "Boarding summary",
        "prompt": "Summarize boarding/welfare issues on this catering boarding students page for a Catering Manager."
      },
      {
        "label": "Draft boarding log",
        "prompt": "Draft a boarding log or parent/staff note based on this catering boarding students page."
      }
    ],
    "32": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this catering boarding students page for a Kitchen Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Kitchen Staff based on this catering boarding students page."
      },
      {
        "label": "Boarding summary",
        "prompt": "Summarize boarding/welfare issues on this catering boarding students page for a Kitchen Staff."
      },
      {
        "label": "Draft boarding log",
        "prompt": "Draft a boarding log or parent/staff note based on this catering boarding students page."
      }
    ]
  },
  "cbc_curriculum": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this cbc curriculum page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this cbc curriculum page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this cbc curriculum page for a Staff."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this cbc curriculum page."
      }
    ]
  },
  "chaplaincy_department": {
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this chaplaincy department page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this chaplaincy department page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this chaplaincy department page for a Counselor/Chaplain? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this chaplaincy department page."
      }
    ]
  },
  "chaplaincy_pastoral": {
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this chaplaincy pastoral page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this chaplaincy pastoral page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this chaplaincy pastoral page for a Counselor/Chaplain? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this chaplaincy pastoral page."
      }
    ]
  },
  "chaplaincy_programs": {
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this chaplaincy programs page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this chaplaincy programs page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this chaplaincy programs page for a Counselor/Chaplain? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this chaplaincy programs page."
      }
    ]
  },
  "chronic_absenteeism": {
    "5": [
      {
        "label": "Leadership briefing",
        "prompt": "Write a leadership briefing on chronic absenteeism: scale, causes, recommended actions."
      }
    ],
    "6": [
      {
        "label": "Intervention plan",
        "prompt": "Draft an intervention plan for learners with chronic absenteeism: steps, owners, timeline."
      }
    ],
    "63": [
      {
        "label": "Home-visit talking points",
        "prompt": "Prepare talking points for a home visit about a learner's attendance."
      }
    ],
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this chronic absenteeism page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this chronic absenteeism page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this chronic absenteeism page for a Staff."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this chronic absenteeism page."
      }
    ]
  },
  "class_attendance_history": {
    "7": [
      {
        "label": "Explain a learner's pattern",
        "prompt": "Explain this learner's attendance pattern and suggest how to raise it with parents."
      }
    ]
  },
  "class_capacity": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this class capacity page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this class capacity page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this class capacity page for a School Administrator."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this class capacity page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this class capacity page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this class capacity page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this class capacity page for a Headteacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this class capacity page."
      }
    ]
  },
  "class_conduct_grades": {
    "7": [
      {
        "label": "Conduct comment",
        "prompt": "Write a brief conduct-grade comment for a learner that is encouraging but honest."
      }
    ],
    "63": [
      {
        "label": "Conduct trend note",
        "prompt": "Summarise conduct trends for this class and recommend actions."
      }
    ]
  },
  "class_mark_attendance": {
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this class mark attendance page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this class mark attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this class mark attendance page for a Deputy Head - Academic."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this class mark attendance page."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this class mark attendance page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this class mark attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this class mark attendance page for a Class Teacher."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this class mark attendance page."
      }
    ],
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this class mark attendance page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this class mark attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this class mark attendance page for a Subject Teacher."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this class mark attendance page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this class mark attendance page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this class mark attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this class mark attendance page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this class mark attendance page."
      }
    ]
  },
  "class_parent_contacts": {
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this class parent contacts page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this class parent contacts page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this class parent contacts page for a Class Teacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this class parent contacts page."
      }
    ]
  },
  "class_records": {
    "6": [
      {
        "label": "Records summary",
        "prompt": "Summarise what the class records show and flag anything that needs follow-up."
      }
    ],
    "7": [
      {
        "label": "Draft learner note",
        "prompt": "Help me write a brief professional note about a learner for their class record."
      },
      {
        "label": "Attendance concern letter",
        "prompt": "Draft a polite letter to a parent about a learner's recent absences."
      }
    ],
    "8": [
      {
        "label": "Subject progress note",
        "prompt": "Draft a short note on a learner's progress in my subject for their record."
      }
    ],
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this class records page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this class records page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this class records page for a Staff."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this class records page."
      }
    ]
  },
  "class_report_cards": {
    "5": [
      {
        "label": "Whole-school comment audit",
        "prompt": "What should leadership check before report cards go home? Give me a checklist."
      }
    ],
    "6": [
      {
        "label": "Review comment quality",
        "prompt": "Review the style of report-card comments for this class and suggest improvements for consistency."
      }
    ],
    "7": [
      {
        "label": "Draft class comments",
        "prompt": "Draft end-of-term comments for my class. Warm, specific, under 60 words each, no learner names needed in the draft."
      },
      {
        "label": "Parent talking points",
        "prompt": "Write parent-friendly talking points summarising how the class performed this term."
      }
    ],
    "*": [
      {
        "label": "Comment-writing help",
        "prompt": "Help me write clear, encouraging report-card comments. Give me 3 examples I can adapt."
      }
    ]
  },
  "class_results": {
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this class results page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this class results page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this class results page for a Class Teacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this class results page."
      }
    ]
  },
  "class_streams": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this class streams page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this class streams page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this class streams page for a School Administrator."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this class streams page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this class streams page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this class streams page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this class streams page for a Headteacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this class streams page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this class streams page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this class streams page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this class streams page for a Deputy Head - Academic."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this class streams page."
      }
    ]
  },
  "clubs_societies": {
    "21": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this clubs societies page for a Talent Development: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Talent Development based on this clubs societies page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this clubs societies page for a Talent Development? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this clubs societies page."
      }
    ]
  },
  "comparative_reports": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this comparative reports page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this comparative reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this comparative reports page for a Director/Owner."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this comparative reports page for stakeholders, tailored to a Director/Owner."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this comparative reports page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this comparative reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this comparative reports page for a Headteacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this comparative reports page for stakeholders, tailored to a Headteacher."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this comparative reports page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this comparative reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this comparative reports page for a Deputy Head - Academic."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this comparative reports page for stakeholders, tailored to a Deputy Head - Academic."
      }
    ]
  },
  "competencies_sheet": {
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this competencies sheet page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this competencies sheet page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this competencies sheet page for a Deputy Head - Academic? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this competencies sheet page."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this competencies sheet page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this competencies sheet page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this competencies sheet page for a Class Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this competencies sheet page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this competencies sheet page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this competencies sheet page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this competencies sheet page for a Deputy Head - Discipline? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this competencies sheet page."
      }
    ]
  },
  "competency_checklist": {
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this competency checklist page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this competency checklist page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this competency checklist page for a Intern/Student Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this competency checklist page."
      }
    ]
  },
  "competitions": {
    "21": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this competitions page for a Talent Development: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Talent Development based on this competitions page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this competitions page for a Talent Development? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this competitions page."
      }
    ]
  },
  "complete_staff_profile": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this complete staff profile page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this complete staff profile page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this complete staff profile page for a Staff."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this complete staff profile page."
      }
    ]
  },
  "conduct_grades": {
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this conduct grades page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this conduct grades page."
      },
      {
        "label": "Summarize cases",
        "prompt": "Summarize discipline/conduct issues on this conduct grades page and recommend next steps for a Deputy Head - Discipline."
      },
      {
        "label": "Draft case note",
        "prompt": "Draft a factual case note or parent communication based on this conduct grades page."
      }
    ]
  },
  "conduct_reports": {
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this conduct reports page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this conduct reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this conduct reports page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this conduct reports page for stakeholders, tailored to a Deputy Head - Discipline."
      }
    ]
  },
  "counseling_cases": {
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this counseling cases page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this counseling cases page."
      },
      {
        "label": "Summarize cases",
        "prompt": "Summarize discipline/conduct issues on this counseling cases page and recommend next steps for a Counselor/Chaplain."
      },
      {
        "label": "Draft case note",
        "prompt": "Draft a factual case note or parent communication based on this counseling cases page."
      }
    ]
  },
  "counseling_records": {
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this counseling records page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this counseling records page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this counseling records page for a Counselor/Chaplain? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this counseling records page."
      }
    ]
  },
  "counseling_referrals": {
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this counseling referrals page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this counseling referrals page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this counseling referrals page for a Counselor/Chaplain? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this counseling referrals page."
      }
    ]
  },
  "counseling_reports": {
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this counseling reports page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this counseling reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this counseling reports page for a Counselor/Chaplain."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this counseling reports page for stakeholders, tailored to a Counselor/Chaplain."
      }
    ]
  },
  "create_activity": {
    "21": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this create activity page for a Talent Development: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Talent Development based on this create activity page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this create activity page for a Talent Development? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this create activity page."
      }
    ]
  },
  "create_assessment": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this create assessment page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this create assessment page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this create assessment page for a Staff."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this create assessment page."
      }
    ]
  },
  "create_lesson_plan": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this create lesson plan page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this create lesson plan page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this create lesson plan page for a Staff."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this create lesson plan page."
      }
    ]
  },
  "create_subject_cat": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this create subject cat page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this create subject cat page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this create subject cat page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this create subject cat page."
      }
    ]
  },
  "curriculum_cbc": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this curriculum cbc page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this curriculum cbc page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this curriculum cbc page for a School Administrator."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this curriculum cbc page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this curriculum cbc page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this curriculum cbc page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this curriculum cbc page for a Headteacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this curriculum cbc page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this curriculum cbc page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this curriculum cbc page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this curriculum cbc page for a Deputy Head - Academic."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this curriculum cbc page."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this curriculum cbc page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this curriculum cbc page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this curriculum cbc page for a Class Teacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this curriculum cbc page."
      }
    ],
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this curriculum cbc page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this curriculum cbc page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this curriculum cbc page for a Subject Teacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this curriculum cbc page."
      }
    ],
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this curriculum cbc page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this curriculum cbc page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this curriculum cbc page for a Intern/Student Teacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this curriculum cbc page."
      }
    ]
  },
  "curriculum_proposals": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this curriculum proposals page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this curriculum proposals page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this curriculum proposals page for a School Administrator."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this curriculum proposals page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this curriculum proposals page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this curriculum proposals page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this curriculum proposals page for a Headteacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this curriculum proposals page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this curriculum proposals page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this curriculum proposals page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this curriculum proposals page for a Deputy Head - Academic."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this curriculum proposals page."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this curriculum proposals page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this curriculum proposals page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this curriculum proposals page for a Class Teacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this curriculum proposals page."
      }
    ],
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this curriculum proposals page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this curriculum proposals page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this curriculum proposals page for a Subject Teacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this curriculum proposals page."
      }
    ],
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this curriculum proposals page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this curriculum proposals page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this curriculum proposals page for a Intern/Student Teacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this curriculum proposals page."
      }
    ]
  },
  "daily_attendance": {
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this daily attendance page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this daily attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this daily attendance page for a Deputy Head - Academic."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this daily attendance page."
      }
    ]
  },
  "dashboard": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this dashboard page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this dashboard page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this dashboard page for a Staff."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this dashboard page for stakeholders, tailored to a Staff."
      }
    ]
  },
  "dashboard_registry": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this dashboard registry page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this dashboard registry page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this dashboard registry page for a Staff."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this dashboard registry page for stakeholders, tailored to a Staff."
      }
    ]
  },
  "data_import": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this data import page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this data import page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this data import page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this data import page."
      }
    ]
  },
  "data_retention": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this data retention page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this data retention page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this data retention page for a System Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this data retention page."
      }
    ]
  },
  "depreciation": {
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this depreciation page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this depreciation page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this depreciation page for a Accountant? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this depreciation page."
      }
    ]
  },
  "detailed_payslip": {
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this detailed payslip page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this detailed payslip page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this detailed payslip page for a Deputy Head - Academic? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this detailed payslip page."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this detailed payslip page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this detailed payslip page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this detailed payslip page for a Class Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this detailed payslip page."
      }
    ],
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this detailed payslip page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this detailed payslip page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this detailed payslip page for a Subject Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this detailed payslip page."
      }
    ],
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this detailed payslip page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this detailed payslip page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this detailed payslip page for a Boarding Master/Matron? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this detailed payslip page."
      }
    ],
    "23": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this detailed payslip page for a Driver: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Driver based on this detailed payslip page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this detailed payslip page for a Driver? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this detailed payslip page."
      }
    ],
    "32": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this detailed payslip page for a Kitchen Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Kitchen Staff based on this detailed payslip page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this detailed payslip page for a Kitchen Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this detailed payslip page."
      }
    ],
    "33": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this detailed payslip page for a Security Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Security Staff based on this detailed payslip page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this detailed payslip page for a Security Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this detailed payslip page."
      }
    ],
    "34": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this detailed payslip page for a Janitor: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Janitor based on this detailed payslip page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this detailed payslip page for a Janitor? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this detailed payslip page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this detailed payslip page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this detailed payslip page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this detailed payslip page for a Deputy Head - Discipline? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this detailed payslip page."
      }
    ],
    "64": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this detailed payslip page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this detailed payslip page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this detailed payslip page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this detailed payslip page."
      }
    ]
  },
  "development_progress": {
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this development progress page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this development progress page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this development progress page for a Intern/Student Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this development progress page."
      }
    ]
  },
  "discipline_cases": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this discipline cases page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this discipline cases page."
      },
      {
        "label": "Summarize cases",
        "prompt": "Summarize discipline/conduct issues on this discipline cases page and recommend next steps for a Director/Owner."
      },
      {
        "label": "Draft case note",
        "prompt": "Draft a factual case note or parent communication based on this discipline cases page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this discipline cases page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this discipline cases page."
      },
      {
        "label": "Summarize cases",
        "prompt": "Summarize discipline/conduct issues on this discipline cases page and recommend next steps for a School Administrator."
      },
      {
        "label": "Draft case note",
        "prompt": "Draft a factual case note or parent communication based on this discipline cases page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this discipline cases page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this discipline cases page."
      },
      {
        "label": "Summarize cases",
        "prompt": "Summarize discipline/conduct issues on this discipline cases page and recommend next steps for a Headteacher."
      },
      {
        "label": "Draft case note",
        "prompt": "Draft a factual case note or parent communication based on this discipline cases page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this discipline cases page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this discipline cases page."
      },
      {
        "label": "Summarize cases",
        "prompt": "Summarize discipline/conduct issues on this discipline cases page and recommend next steps for a Deputy Head - Discipline."
      },
      {
        "label": "Draft case note",
        "prompt": "Draft a factual case note or parent communication based on this discipline cases page."
      }
    ]
  },
  "discipline_reports": {
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this discipline reports page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this discipline reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this discipline reports page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this discipline reports page for stakeholders, tailored to a Deputy Head - Discipline."
      }
    ]
  },
  "discipline_students": {
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this discipline students page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this discipline students page."
      },
      {
        "label": "Summarize cases",
        "prompt": "Summarize discipline/conduct issues on this discipline students page and recommend next steps for a Deputy Head - Discipline."
      },
      {
        "label": "Draft case note",
        "prompt": "Draft a factual case note or parent communication based on this discipline students page."
      }
    ]
  },
  "domain_isolation_rules": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this domain isolation rules page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this domain isolation rules page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this domain isolation rules page for a System Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this domain isolation rules page."
      }
    ]
  },
  "dormitory_management": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this dormitory management page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this dormitory management page."
      },
      {
        "label": "Boarding summary",
        "prompt": "Summarize boarding/welfare issues on this dormitory management page for a Director/Owner."
      },
      {
        "label": "Draft boarding log",
        "prompt": "Draft a boarding log or parent/staff note based on this dormitory management page."
      }
    ],
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this dormitory management page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this dormitory management page."
      },
      {
        "label": "Boarding summary",
        "prompt": "Summarize boarding/welfare issues on this dormitory management page for a Boarding Master/Matron."
      },
      {
        "label": "Draft boarding log",
        "prompt": "Draft a boarding log or parent/staff note based on this dormitory management page."
      }
    ]
  },
  "draft_fee_structure": {
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this draft fee structure page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this draft fee structure page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this draft fee structure page for a Accountant."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this draft fee structure page."
      }
    ]
  },
  "duty_roster": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this duty roster page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this duty roster page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this duty roster page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this duty roster page."
      }
    ]
  },
  "end_of_term_travel": {
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this end of term travel page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this end of term travel page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this end of term travel page for a Boarding Master/Matron? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this end of term travel page."
      }
    ]
  },
  "enrollment_reports": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this enrollment reports page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this enrollment reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this enrollment reports page for a Director/Owner."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this enrollment reports page for stakeholders, tailored to a Director/Owner."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this enrollment reports page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this enrollment reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this enrollment reports page for a School Administrator."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this enrollment reports page for stakeholders, tailored to a School Administrator."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this enrollment reports page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this enrollment reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this enrollment reports page for a Headteacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this enrollment reports page for stakeholders, tailored to a Headteacher."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this enrollment reports page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this enrollment reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this enrollment reports page for a Accountant."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this enrollment reports page for stakeholders, tailored to a Accountant."
      }
    ]
  },
  "enrollment_trends": {
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this enrollment trends page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this enrollment trends page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this enrollment trends page for a Deputy Head - Academic."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this enrollment trends page for stakeholders, tailored to a Deputy Head - Academic."
      }
    ]
  },
  "enter_exam_results": {
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this enter exam results page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this enter exam results page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this enter exam results page for a Class Teacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this enter exam results page."
      }
    ],
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this enter exam results page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this enter exam results page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this enter exam results page for a Subject Teacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this enter exam results page."
      }
    ]
  },
  "enter_marks": {
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this enter marks page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this enter marks page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this enter marks page for a Class Teacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this enter marks page."
      }
    ]
  },
  "enter_results": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this enter results page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this enter results page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this enter results page for a Staff."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this enter results page."
      }
    ]
  },
  "error_logs": {
    "2": [
      {
        "label": "Error triage",
        "prompt": "Triage these errors: most serious first, likely causes, suggested fixes."
      },
      {
        "label": "Recurring issue",
        "prompt": "Which errors here are recurring and should become tickets? Summarise each."
      }
    ]
  },
  "evening_roll_call": {
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this evening roll call page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this evening roll call page."
      },
      {
        "label": "Boarding summary",
        "prompt": "Summarize boarding/welfare issues on this evening roll call page for a Boarding Master/Matron."
      },
      {
        "label": "Draft boarding log",
        "prompt": "Draft a boarding log or parent/staff note based on this evening roll call page."
      }
    ]
  },
  "exam_moderation": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this exam moderation page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this exam moderation page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this exam moderation page for a School Administrator."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this exam moderation page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this exam moderation page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this exam moderation page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this exam moderation page for a Headteacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this exam moderation page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this exam moderation page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this exam moderation page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this exam moderation page for a Deputy Head - Academic."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this exam moderation page."
      }
    ]
  },
  "exam_schedule": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this exam schedule page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this exam schedule page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this exam schedule page for a Director/Owner."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this exam schedule page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this exam schedule page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this exam schedule page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this exam schedule page for a School Administrator."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this exam schedule page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this exam schedule page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this exam schedule page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this exam schedule page for a Headteacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this exam schedule page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this exam schedule page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this exam schedule page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this exam schedule page for a Deputy Head - Academic."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this exam schedule page."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this exam schedule page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this exam schedule page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this exam schedule page for a Class Teacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this exam schedule page."
      }
    ]
  },
  "exam_setup": {
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this exam setup page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this exam setup page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this exam setup page for a Deputy Head - Academic."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this exam setup page."
      }
    ]
  },
  "exam_timetable_drafts": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this exam timetable drafts page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this exam timetable drafts page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this exam timetable drafts page for a School Administrator."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this exam timetable drafts page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this exam timetable drafts page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this exam timetable drafts page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this exam timetable drafts page for a Headteacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this exam timetable drafts page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this exam timetable drafts page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this exam timetable drafts page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this exam timetable drafts page for a Deputy Head - Academic."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this exam timetable drafts page."
      }
    ]
  },
  "exception_reports": {
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this exception reports page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this exception reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this exception reports page for a Accountant."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this exception reports page for stakeholders, tailored to a Accountant."
      }
    ]
  },
  "exeat_history": {
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this exeat history page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this exeat history page."
      },
      {
        "label": "Boarding summary",
        "prompt": "Summarize boarding/welfare issues on this exeat history page for a Boarding Master/Matron."
      },
      {
        "label": "Draft boarding log",
        "prompt": "Draft a boarding log or parent/staff note based on this exeat history page."
      }
    ]
  },
  "exeat_reports": {
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this exeat reports page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this exeat reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this exeat reports page for a Boarding Master/Matron."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this exeat reports page for stakeholders, tailored to a Boarding Master/Matron."
      }
    ]
  },
  "failed_login_attempts": {
    "2": [
      {
        "label": "Attack analysis",
        "prompt": "Analyse these failed login attempts: source patterns, targeted accounts, and recommended blocks."
      },
      {
        "label": "Lockout advice",
        "prompt": "Which accounts look like they are under attack and should be temporarily locked or notified?"
      }
    ]
  },
  "feature_flags": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this feature flags page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this feature flags page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this feature flags page for a System Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this feature flags page."
      }
    ]
  },
  "fee_structure_approvals": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this fee structure approvals page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this fee structure approvals page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this fee structure approvals page for a Director/Owner."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this fee structure approvals page."
      }
    ]
  },
  "finance_approvals": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this finance approvals page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this finance approvals page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this finance approvals page for a Director/Owner."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this finance approvals page."
      }
    ]
  },
  "finance_reports": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this finance reports page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this finance reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this finance reports page for a Director/Owner."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this finance reports page for stakeholders, tailored to a Director/Owner."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this finance reports page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this finance reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this finance reports page for a School Administrator."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this finance reports page for stakeholders, tailored to a School Administrator."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this finance reports page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this finance reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this finance reports page for a Headteacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this finance reports page for stakeholders, tailored to a Headteacher."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this finance reports page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this finance reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this finance reports page for a Accountant."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this finance reports page for stakeholders, tailored to a Accountant."
      }
    ]
  },
  "food_consumption": {
    "16": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this food consumption page for a Catering Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Catering Manager based on this food consumption page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this food consumption page for a Catering Manager? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this food consumption page."
      }
    ],
    "71": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this food consumption page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this food consumption page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this food consumption page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this food consumption page."
      }
    ]
  },
  "food_low_stock": {
    "16": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this food low stock page for a Catering Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Catering Manager based on this food low stock page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this food low stock page for a Catering Manager."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this food low stock page."
      }
    ],
    "71": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this food low stock page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this food low stock page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this food low stock page for a Staff."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this food low stock page."
      }
    ]
  },
  "food_orders": {
    "16": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this food orders page for a Catering Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Catering Manager based on this food orders page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this food orders page for a Catering Manager? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this food orders page."
      }
    ],
    "71": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this food orders page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this food orders page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this food orders page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this food orders page."
      }
    ]
  },
  "food_stock_levels": {
    "16": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this food stock levels page for a Catering Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Catering Manager based on this food stock levels page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this food stock levels page for a Catering Manager."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this food stock levels page."
      }
    ],
    "71": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this food stock levels page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this food stock levels page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this food stock levels page for a Staff."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this food stock levels page."
      }
    ]
  },
  "food_store": {
    "16": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this food store page for a Catering Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Catering Manager based on this food store page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this food store page for a Catering Manager."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this food store page."
      }
    ],
    "32": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this food store page for a Kitchen Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Kitchen Staff based on this food store page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this food store page for a Kitchen Staff."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this food store page."
      }
    ],
    "71": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this food store page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this food store page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this food store page for a Staff."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this food store page."
      }
    ]
  },
  "formative_assessments": {
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this formative assessments page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this formative assessments page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this formative assessments page for a Deputy Head - Academic."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this formative assessments page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this formative assessments page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this formative assessments page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this formative assessments page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this formative assessments page."
      }
    ]
  },
  "generate_class_report": {
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this generate class report page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this generate class report page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this generate class report page for a Class Teacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this generate class report page for stakeholders, tailored to a Class Teacher."
      }
    ]
  },
  "generate_subject_report": {
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this generate subject report page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this generate subject report page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this generate subject report page for a Subject Teacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this generate subject report page for stakeholders, tailored to a Subject Teacher."
      }
    ]
  },
  "goods_received": {
    "14": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this goods received page for a Inventory Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Inventory Manager based on this goods received page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this goods received page for a Inventory Manager? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this goods received page."
      }
    ],
    "71": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this goods received page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this goods received page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this goods received page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this goods received page."
      }
    ]
  },
  "grade_entry": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this grade entry page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this grade entry page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this grade entry page for a Staff."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this grade entry page."
      }
    ]
  },
  "grading_scales": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this grading scales page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this grading scales page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this grading scales page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this grading scales page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this grading scales page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this grading scales page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this grading scales page for a Headteacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this grading scales page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this grading scales page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this grading scales page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this grading scales page for a Deputy Head - Academic? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this grading scales page."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this grading scales page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this grading scales page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this grading scales page for a Class Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this grading scales page."
      }
    ],
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this grading scales page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this grading scales page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this grading scales page for a Subject Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this grading scales page."
      }
    ],
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this grading scales page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this grading scales page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this grading scales page for a Intern/Student Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this grading scales page."
      }
    ]
  },
  "grading_status": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this grading status page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this grading status page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this grading status page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this grading status page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this grading status page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this grading status page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this grading status page for a Headteacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this grading status page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this grading status page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this grading status page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this grading status page for a Deputy Head - Academic? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this grading status page."
      }
    ]
  },
  "health_reports": {
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this health reports page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this health reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this health reports page for a Boarding Master/Matron."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this health reports page for stakeholders, tailored to a Boarding Master/Matron."
      }
    ]
  },
  "import_existing_staff": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this import existing staff page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this import existing staff page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this import existing staff page for a Director/Owner."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this import existing staff page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this import existing staff page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this import existing staff page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this import existing staff page for a School Administrator."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this import existing staff page."
      }
    ]
  },
  "import_existing_students": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this import existing students page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this import existing students page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this import existing students page for a Staff."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this import existing students page."
      }
    ]
  },
  "improvement_areas": {
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this improvement areas page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this improvement areas page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this improvement areas page for a Intern/Student Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this improvement areas page."
      }
    ]
  },
  "intern_assigned_classes": {
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this intern assigned classes page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this intern assigned classes page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this intern assigned classes page for a Intern/Student Teacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this intern assigned classes page."
      }
    ]
  },
  "intern_assigned_subjects": {
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this intern assigned subjects page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this intern assigned subjects page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this intern assigned subjects page for a Intern/Student Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this intern assigned subjects page."
      }
    ]
  },
  "intern_schedule": {
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this intern schedule page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this intern schedule page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this intern schedule page for a Intern/Student Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this intern schedule page."
      }
    ]
  },
  "internal_catalog_account": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal catalog account page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this internal catalog account page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal catalog account page for a System Administrator."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal catalog account page."
      }
    ],
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal catalog account page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this internal catalog account page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal catalog account page for a Director/Owner."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal catalog account page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal catalog account page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this internal catalog account page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal catalog account page for a School Administrator."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal catalog account page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal catalog account page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this internal catalog account page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal catalog account page for a Headteacher."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal catalog account page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal catalog account page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this internal catalog account page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal catalog account page for a Deputy Head - Academic."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal catalog account page."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal catalog account page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this internal catalog account page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal catalog account page for a Class Teacher."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal catalog account page."
      }
    ],
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal catalog account page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this internal catalog account page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal catalog account page for a Subject Teacher."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal catalog account page."
      }
    ],
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal catalog account page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this internal catalog account page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal catalog account page for a Intern/Student Teacher."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal catalog account page."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal catalog account page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this internal catalog account page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal catalog account page for a Accountant."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal catalog account page."
      }
    ],
    "14": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal catalog account page for a Inventory Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Inventory Manager based on this internal catalog account page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal catalog account page for a Inventory Manager."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal catalog account page."
      }
    ],
    "16": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal catalog account page for a Catering Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Catering Manager based on this internal catalog account page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal catalog account page for a Catering Manager."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal catalog account page."
      }
    ],
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal catalog account page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this internal catalog account page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal catalog account page for a Boarding Master/Matron."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal catalog account page."
      }
    ],
    "21": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal catalog account page for a Talent Development: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Talent Development based on this internal catalog account page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal catalog account page for a Talent Development."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal catalog account page."
      }
    ],
    "23": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal catalog account page for a Driver: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Driver based on this internal catalog account page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal catalog account page for a Driver."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal catalog account page."
      }
    ],
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal catalog account page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this internal catalog account page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal catalog account page for a Counselor/Chaplain."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal catalog account page."
      }
    ],
    "32": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal catalog account page for a Kitchen Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Kitchen Staff based on this internal catalog account page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal catalog account page for a Kitchen Staff."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal catalog account page."
      }
    ],
    "33": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal catalog account page for a Security Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Security Staff based on this internal catalog account page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal catalog account page for a Security Staff."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal catalog account page."
      }
    ],
    "34": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal catalog account page for a Janitor: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Janitor based on this internal catalog account page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal catalog account page for a Janitor."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal catalog account page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal catalog account page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this internal catalog account page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal catalog account page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal catalog account page."
      }
    ],
    "64": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal catalog account page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this internal catalog account page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal catalog account page for a Staff."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal catalog account page."
      }
    ],
    "71": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal catalog account page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this internal catalog account page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal catalog account page for a Staff."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal catalog account page."
      }
    ],
    "72": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal catalog account page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this internal catalog account page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal catalog account page for a Staff."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal catalog account page."
      }
    ]
  },
  "internal_product_details": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal product details page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this internal product details page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this internal product details page for a System Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this internal product details page."
      }
    ],
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal product details page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this internal product details page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this internal product details page for a Director/Owner? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this internal product details page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal product details page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this internal product details page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this internal product details page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this internal product details page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal product details page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this internal product details page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this internal product details page for a Headteacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this internal product details page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal product details page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this internal product details page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this internal product details page for a Deputy Head - Academic? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this internal product details page."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal product details page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this internal product details page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this internal product details page for a Class Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this internal product details page."
      }
    ],
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal product details page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this internal product details page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this internal product details page for a Subject Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this internal product details page."
      }
    ],
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal product details page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this internal product details page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this internal product details page for a Intern/Student Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this internal product details page."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal product details page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this internal product details page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this internal product details page for a Accountant? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this internal product details page."
      }
    ],
    "14": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal product details page for a Inventory Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Inventory Manager based on this internal product details page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this internal product details page for a Inventory Manager? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this internal product details page."
      }
    ],
    "16": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal product details page for a Catering Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Catering Manager based on this internal product details page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this internal product details page for a Catering Manager? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this internal product details page."
      }
    ],
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal product details page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this internal product details page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this internal product details page for a Boarding Master/Matron? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this internal product details page."
      }
    ],
    "21": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal product details page for a Talent Development: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Talent Development based on this internal product details page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this internal product details page for a Talent Development? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this internal product details page."
      }
    ],
    "23": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal product details page for a Driver: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Driver based on this internal product details page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this internal product details page for a Driver? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this internal product details page."
      }
    ],
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal product details page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this internal product details page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this internal product details page for a Counselor/Chaplain? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this internal product details page."
      }
    ],
    "32": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal product details page for a Kitchen Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Kitchen Staff based on this internal product details page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this internal product details page for a Kitchen Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this internal product details page."
      }
    ],
    "33": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal product details page for a Security Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Security Staff based on this internal product details page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this internal product details page for a Security Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this internal product details page."
      }
    ],
    "34": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal product details page for a Janitor: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Janitor based on this internal product details page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this internal product details page for a Janitor? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this internal product details page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal product details page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this internal product details page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this internal product details page for a Deputy Head - Discipline? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this internal product details page."
      }
    ],
    "64": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal product details page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this internal product details page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this internal product details page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this internal product details page."
      }
    ],
    "71": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal product details page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this internal product details page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this internal product details page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this internal product details page."
      }
    ],
    "72": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal product details page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this internal product details page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this internal product details page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this internal product details page."
      }
    ]
  },
  "internal_products_catalog": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal products catalog page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this internal products catalog page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal products catalog page for a System Administrator."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal products catalog page."
      }
    ],
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal products catalog page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this internal products catalog page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal products catalog page for a Director/Owner."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal products catalog page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal products catalog page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this internal products catalog page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal products catalog page for a School Administrator."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal products catalog page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal products catalog page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this internal products catalog page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal products catalog page for a Headteacher."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal products catalog page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal products catalog page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this internal products catalog page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal products catalog page for a Deputy Head - Academic."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal products catalog page."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal products catalog page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this internal products catalog page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal products catalog page for a Class Teacher."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal products catalog page."
      }
    ],
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal products catalog page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this internal products catalog page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal products catalog page for a Subject Teacher."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal products catalog page."
      }
    ],
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal products catalog page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this internal products catalog page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal products catalog page for a Intern/Student Teacher."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal products catalog page."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal products catalog page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this internal products catalog page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal products catalog page for a Accountant."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal products catalog page."
      }
    ],
    "14": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal products catalog page for a Inventory Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Inventory Manager based on this internal products catalog page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal products catalog page for a Inventory Manager."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal products catalog page."
      }
    ],
    "16": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal products catalog page for a Catering Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Catering Manager based on this internal products catalog page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal products catalog page for a Catering Manager."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal products catalog page."
      }
    ],
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal products catalog page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this internal products catalog page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal products catalog page for a Boarding Master/Matron."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal products catalog page."
      }
    ],
    "21": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal products catalog page for a Talent Development: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Talent Development based on this internal products catalog page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal products catalog page for a Talent Development."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal products catalog page."
      }
    ],
    "23": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal products catalog page for a Driver: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Driver based on this internal products catalog page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal products catalog page for a Driver."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal products catalog page."
      }
    ],
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal products catalog page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this internal products catalog page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal products catalog page for a Counselor/Chaplain."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal products catalog page."
      }
    ],
    "32": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal products catalog page for a Kitchen Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Kitchen Staff based on this internal products catalog page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal products catalog page for a Kitchen Staff."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal products catalog page."
      }
    ],
    "33": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal products catalog page for a Security Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Security Staff based on this internal products catalog page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal products catalog page for a Security Staff."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal products catalog page."
      }
    ],
    "34": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal products catalog page for a Janitor: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Janitor based on this internal products catalog page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal products catalog page for a Janitor."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal products catalog page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal products catalog page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this internal products catalog page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal products catalog page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal products catalog page."
      }
    ],
    "64": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal products catalog page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this internal products catalog page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal products catalog page for a Staff."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal products catalog page."
      }
    ],
    "71": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal products catalog page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this internal products catalog page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal products catalog page for a Staff."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal products catalog page."
      }
    ],
    "72": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this internal products catalog page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this internal products catalog page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this internal products catalog page for a Staff."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this internal products catalog page."
      }
    ]
  },
  "intervention_plans": {
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this intervention plans page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this intervention plans page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this intervention plans page for a Counselor/Chaplain? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this intervention plans page."
      }
    ]
  },
  "inventory_expenses": {
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this inventory expenses page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this inventory expenses page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this inventory expenses page for a Accountant."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this inventory expenses page."
      }
    ]
  },
  "inventory_reports": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this inventory reports page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this inventory reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this inventory reports page for a Staff."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this inventory reports page for stakeholders, tailored to a Staff."
      }
    ]
  },
  "ip_whitelist_blacklist": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this ip whitelist blacklist page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this ip whitelist blacklist page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this ip whitelist blacklist page for a System Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this ip whitelist blacklist page."
      }
    ]
  },
  "job_inspector": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this job inspector page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this job inspector page."
      },
      {
        "label": "Draft website content",
        "prompt": "Draft website content/copy appropriate for this job inspector page as a System Administrator."
      },
      {
        "label": "Improve clarity",
        "prompt": "Improve clarity and tone of content on this job inspector page for families/community."
      }
    ]
  },
  "kcb_disbursement_reconciliation": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this kcb disbursement reconciliation page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this kcb disbursement reconciliation page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this kcb disbursement reconciliation page for a Director/Owner."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this kcb disbursement reconciliation page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this kcb disbursement reconciliation page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this kcb disbursement reconciliation page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this kcb disbursement reconciliation page for a School Administrator."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this kcb disbursement reconciliation page."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this kcb disbursement reconciliation page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this kcb disbursement reconciliation page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this kcb disbursement reconciliation page for a Accountant."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this kcb disbursement reconciliation page."
      }
    ]
  },
  "late_arrivals": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this late arrivals page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this late arrivals page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this late arrivals page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this late arrivals page."
      }
    ]
  },
  "learning_areas": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this learning areas page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this learning areas page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this learning areas page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this learning areas page."
      }
    ]
  },
  "learning_goals": {
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this learning goals page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this learning goals page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this learning goals page for a Intern/Student Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this learning goals page."
      }
    ]
  },
  "lesson_periods": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this lesson periods page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this lesson periods page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this lesson periods page for a School Administrator."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this lesson periods page."
      }
    ]
  },
  "lesson_plan_approval": {
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this lesson plan approval page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this lesson plan approval page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this lesson plan approval page for a Deputy Head - Academic."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this lesson plan approval page."
      }
    ]
  },
  "lesson_plans_by_class": {
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this lesson plans by class page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this lesson plans by class page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this lesson plans by class page for a Deputy Head - Academic."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this lesson plans by class page."
      }
    ]
  },
  "lesson_plans_by_teacher": {
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this lesson plans by teacher page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this lesson plans by teacher page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this lesson plans by teacher page for a Deputy Head - Academic."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this lesson plans by teacher page."
      }
    ]
  },
  "library_issue_return": {
    "72": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this library issue return page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this library issue return page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this library issue return page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this library issue return page."
      }
    ]
  },
  "library_overdue": {
    "72": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this library overdue page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this library overdue page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this library overdue page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this library overdue page."
      }
    ]
  },
  "log_discipline_case": {
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this log discipline case page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this log discipline case page."
      },
      {
        "label": "Summarize cases",
        "prompt": "Summarize discipline/conduct issues on this log discipline case page and recommend next steps for a Deputy Head - Discipline."
      },
      {
        "label": "Draft case note",
        "prompt": "Draft a factual case note or parent communication based on this log discipline case page."
      }
    ]
  },
  "log_discipline_incident": {
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this log discipline incident page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this log discipline incident page."
      },
      {
        "label": "Summarize cases",
        "prompt": "Summarize discipline/conduct issues on this log discipline incident page and recommend next steps for a Class Teacher."
      },
      {
        "label": "Draft case note",
        "prompt": "Draft a factual case note or parent communication based on this log discipline incident page."
      }
    ]
  },
  "log_viewer": {
    "2": [
      {
        "label": "Explain these logs",
        "prompt": "Explain what these log entries mean and whether they indicate a problem."
      },
      {
        "label": "Trace request",
        "prompt": "Help me trace what happened around a specific request or error in these logs."
      }
    ]
  },
  "low_stock_alerts": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this low stock alerts page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this low stock alerts page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this low stock alerts page for a Staff."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this low stock alerts page."
      }
    ]
  },
  "maintenance_mode": {
    "2": [
      {
        "label": "Draft maintenance notice",
        "prompt": "Draft a clear maintenance-window notice for staff: what, when, expected downtime, what to do."
      },
      {
        "label": "Pre-flight checklist",
        "prompt": "Give me a pre-maintenance checklist for taking this system into maintenance mode safely."
      },
      {
        "label": "Post-maintenance report",
        "prompt": "Draft a post-maintenance report template: window, changes, verification, follow-ups."
      }
    ]
  },
  "manage_academics": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage academics page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this manage academics page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this manage academics page for a Staff."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this manage academics page."
      }
    ]
  },
  "manage_activities": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage activities page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage activities page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage activities page for a Director/Owner? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage activities page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage activities page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this manage activities page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage activities page for a Headteacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage activities page."
      }
    ],
    "21": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage activities page for a Talent Development: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Talent Development based on this manage activities page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage activities page for a Talent Development? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage activities page."
      }
    ]
  },
  "manage_admissions_payments": {
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage admissions payments page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this manage admissions payments page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this manage admissions payments page for a Accountant."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this manage admissions payments page."
      }
    ]
  },
  "manage_announcements": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage announcements page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage announcements page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage announcements page and Director/Owner role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage announcements page for clarity and professionalism."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage announcements page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this manage announcements page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage announcements page and School Administrator role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage announcements page for clarity and professionalism."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage announcements page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this manage announcements page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage announcements page and Headteacher role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage announcements page for clarity and professionalism."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage announcements page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this manage announcements page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage announcements page and Deputy Head - Academic role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage announcements page for clarity and professionalism."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage announcements page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this manage announcements page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage announcements page and Class Teacher role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage announcements page for clarity and professionalism."
      }
    ],
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage announcements page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this manage announcements page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage announcements page and Subject Teacher role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage announcements page for clarity and professionalism."
      }
    ],
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage announcements page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this manage announcements page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage announcements page and Intern/Student Teacher role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage announcements page for clarity and professionalism."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage announcements page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this manage announcements page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage announcements page and Accountant role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage announcements page for clarity and professionalism."
      }
    ],
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage announcements page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this manage announcements page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage announcements page and Boarding Master/Matron role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage announcements page for clarity and professionalism."
      }
    ],
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage announcements page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this manage announcements page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage announcements page and Counselor/Chaplain role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage announcements page for clarity and professionalism."
      }
    ],
    "32": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage announcements page for a Kitchen Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Kitchen Staff based on this manage announcements page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage announcements page and Kitchen Staff role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage announcements page for clarity and professionalism."
      }
    ],
    "33": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage announcements page for a Security Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Security Staff based on this manage announcements page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage announcements page and Security Staff role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage announcements page for clarity and professionalism."
      }
    ],
    "34": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage announcements page for a Janitor: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Janitor based on this manage announcements page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage announcements page and Janitor role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage announcements page for clarity and professionalism."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage announcements page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this manage announcements page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage announcements page and Deputy Head - Discipline role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage announcements page for clarity and professionalism."
      }
    ],
    "64": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage announcements page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this manage announcements page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage announcements page and Staff role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage announcements page for clarity and professionalism."
      }
    ]
  },
  "manage_articles": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage articles page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage articles page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage articles page for a Director/Owner? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage articles page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage articles page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this manage articles page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage articles page for a Deputy Head - Academic? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage articles page."
      }
    ],
    "21": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage articles page for a Talent Development: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Talent Development based on this manage articles page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage articles page for a Talent Development? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage articles page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage articles page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this manage articles page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage articles page for a Deputy Head - Discipline? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage articles page."
      }
    ]
  },
  "manage_boarding": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage boarding page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage boarding page."
      },
      {
        "label": "Boarding summary",
        "prompt": "Summarize boarding/welfare issues on this manage boarding page for a Director/Owner."
      },
      {
        "label": "Draft boarding log",
        "prompt": "Draft a boarding log or parent/staff note based on this manage boarding page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage boarding page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this manage boarding page."
      },
      {
        "label": "Boarding summary",
        "prompt": "Summarize boarding/welfare issues on this manage boarding page for a School Administrator."
      },
      {
        "label": "Draft boarding log",
        "prompt": "Draft a boarding log or parent/staff note based on this manage boarding page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage boarding page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this manage boarding page."
      },
      {
        "label": "Boarding summary",
        "prompt": "Summarize boarding/welfare issues on this manage boarding page for a Headteacher."
      },
      {
        "label": "Draft boarding log",
        "prompt": "Draft a boarding log or parent/staff note based on this manage boarding page."
      }
    ],
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage boarding page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this manage boarding page."
      },
      {
        "label": "Boarding summary",
        "prompt": "Summarize boarding/welfare issues on this manage boarding page for a Boarding Master/Matron."
      },
      {
        "label": "Draft boarding log",
        "prompt": "Draft a boarding log or parent/staff note based on this manage boarding page."
      }
    ]
  },
  "manage_boarding_fee_structure": {
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage boarding fee structure page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this manage boarding fee structure page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this manage boarding fee structure page for a Accountant."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this manage boarding fee structure page."
      }
    ]
  },
  "manage_classes": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage classes page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage classes page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this manage classes page for a Director/Owner."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this manage classes page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage classes page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this manage classes page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this manage classes page for a School Administrator."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this manage classes page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage classes page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this manage classes page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this manage classes page for a Headteacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this manage classes page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage classes page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this manage classes page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this manage classes page for a Deputy Head - Academic."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this manage classes page."
      }
    ]
  },
  "manage_communications": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage communications page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this manage communications page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage communications page and School Administrator role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage communications page for clarity and professionalism."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage communications page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this manage communications page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage communications page and Deputy Head - Discipline role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage communications page for clarity and professionalism."
      }
    ]
  },
  "manage_email": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage email page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage email page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage email page and Director/Owner role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage email page for clarity and professionalism."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage email page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this manage email page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage email page and School Administrator role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage email page for clarity and professionalism."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage email page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this manage email page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage email page and Headteacher role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage email page for clarity and professionalism."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage email page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this manage email page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage email page and Deputy Head - Academic role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage email page for clarity and professionalism."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage email page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this manage email page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage email page and Class Teacher role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage email page for clarity and professionalism."
      }
    ],
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage email page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this manage email page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage email page and Subject Teacher role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage email page for clarity and professionalism."
      }
    ],
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage email page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this manage email page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage email page and Intern/Student Teacher role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage email page for clarity and professionalism."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage email page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this manage email page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage email page and Accountant role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage email page for clarity and professionalism."
      }
    ],
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage email page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this manage email page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage email page and Boarding Master/Matron role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage email page for clarity and professionalism."
      }
    ],
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage email page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this manage email page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage email page and Counselor/Chaplain role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage email page for clarity and professionalism."
      }
    ],
    "32": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage email page for a Kitchen Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Kitchen Staff based on this manage email page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage email page and Kitchen Staff role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage email page for clarity and professionalism."
      }
    ],
    "33": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage email page for a Security Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Security Staff based on this manage email page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage email page and Security Staff role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage email page for clarity and professionalism."
      }
    ],
    "34": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage email page for a Janitor: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Janitor based on this manage email page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage email page and Janitor role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage email page for clarity and professionalism."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage email page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this manage email page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage email page and Deputy Head - Discipline role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage email page for clarity and professionalism."
      }
    ],
    "64": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage email page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this manage email page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage email page and Staff role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage email page for clarity and professionalism."
      }
    ],
    "71": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage email page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this manage email page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage email page and Staff role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage email page for clarity and professionalism."
      }
    ],
    "72": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage email page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this manage email page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage email page and Staff role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage email page for clarity and professionalism."
      }
    ]
  },
  "manage_email_configurations": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage email configurations page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this manage email configurations page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage email configurations page and System Administrator role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage email configurations page for clarity and professionalism."
      }
    ]
  },
  "manage_expenses": {
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage expenses page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this manage expenses page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this manage expenses page for a Accountant."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this manage expenses page."
      }
    ]
  },
  "manage_extra_charges": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage extra charges page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage extra charges page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage extra charges page for a Director/Owner? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage extra charges page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage extra charges page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this manage extra charges page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage extra charges page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage extra charges page."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage extra charges page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this manage extra charges page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage extra charges page for a Accountant? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage extra charges page."
      }
    ]
  },
  "manage_family_groups": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage family groups page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this manage family groups page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage family groups page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage family groups page."
      }
    ]
  },
  "manage_fee_structure": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage fee structure page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage fee structure page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this manage fee structure page for a Director/Owner."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this manage fee structure page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage fee structure page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this manage fee structure page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this manage fee structure page for a School Administrator."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this manage fee structure page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage fee structure page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this manage fee structure page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this manage fee structure page for a Headteacher."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this manage fee structure page."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage fee structure page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this manage fee structure page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this manage fee structure page for a Accountant."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this manage fee structure page."
      }
    ]
  },
  "manage_finance": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage finance page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this manage finance page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this manage finance page for a Staff."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this manage finance page."
      }
    ]
  },
  "manage_holidays": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage holidays page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this manage holidays page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage holidays page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage holidays page."
      }
    ]
  },
  "manage_inquiries": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage inquiries page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage inquiries page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage inquiries page and Director/Owner role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage inquiries page for clarity and professionalism."
      }
    ]
  },
  "manage_inventory": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage inventory page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage inventory page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this manage inventory page for a Director/Owner."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this manage inventory page."
      }
    ]
  },
  "manage_job_applications": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage job applications page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage job applications page."
      },
      {
        "label": "Draft website content",
        "prompt": "Draft website content/copy appropriate for this manage job applications page as a Director/Owner."
      },
      {
        "label": "Improve clarity",
        "prompt": "Improve clarity and tone of content on this manage job applications page for families/community."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage job applications page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this manage job applications page."
      },
      {
        "label": "Draft website content",
        "prompt": "Draft website content/copy appropriate for this manage job applications page as a School Administrator."
      },
      {
        "label": "Improve clarity",
        "prompt": "Improve clarity and tone of content on this manage job applications page for families/community."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage job applications page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this manage job applications page."
      },
      {
        "label": "Draft website content",
        "prompt": "Draft website content/copy appropriate for this manage job applications page as a Headteacher."
      },
      {
        "label": "Improve clarity",
        "prompt": "Improve clarity and tone of content on this manage job applications page for families/community."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage job applications page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this manage job applications page."
      },
      {
        "label": "Draft website content",
        "prompt": "Draft website content/copy appropriate for this manage job applications page as a Deputy Head - Academic."
      },
      {
        "label": "Improve clarity",
        "prompt": "Improve clarity and tone of content on this manage job applications page for families/community."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage job applications page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this manage job applications page."
      },
      {
        "label": "Draft website content",
        "prompt": "Draft website content/copy appropriate for this manage job applications page as a Deputy Head - Discipline."
      },
      {
        "label": "Improve clarity",
        "prompt": "Improve clarity and tone of content on this manage job applications page for families/community."
      }
    ]
  },
  "manage_job_vacancies": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage job vacancies page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage job vacancies page."
      },
      {
        "label": "Draft website content",
        "prompt": "Draft website content/copy appropriate for this manage job vacancies page as a Director/Owner."
      },
      {
        "label": "Improve clarity",
        "prompt": "Improve clarity and tone of content on this manage job vacancies page for families/community."
      }
    ]
  },
  "manage_lesson_plans": {
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage lesson plans page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this manage lesson plans page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this manage lesson plans page for a Headteacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this manage lesson plans page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage lesson plans page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this manage lesson plans page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this manage lesson plans page for a Deputy Head - Academic."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this manage lesson plans page."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage lesson plans page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this manage lesson plans page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this manage lesson plans page for a Class Teacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this manage lesson plans page."
      }
    ],
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage lesson plans page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this manage lesson plans page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this manage lesson plans page for a Subject Teacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this manage lesson plans page."
      }
    ],
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage lesson plans page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this manage lesson plans page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this manage lesson plans page for a Intern/Student Teacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this manage lesson plans page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage lesson plans page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this manage lesson plans page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this manage lesson plans page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this manage lesson plans page."
      }
    ]
  },
  "manage_library": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage library page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage library page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage library page for a Director/Owner? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage library page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage library page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this manage library page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage library page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage library page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage library page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this manage library page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage library page for a Headteacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage library page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage library page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this manage library page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage library page for a Deputy Head - Academic? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage library page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage library page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this manage library page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage library page for a Deputy Head - Discipline? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage library page."
      }
    ],
    "72": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage library page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this manage library page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage library page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage library page."
      }
    ]
  },
  "manage_menus": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage menus page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this manage menus page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage menus page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage menus page."
      }
    ]
  },
  "manage_page_content": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage page content page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage page content page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage page content page for a Director/Owner? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage page content page."
      }
    ]
  },
  "manage_payments": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage payments page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage payments page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this manage payments page for a Director/Owner."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this manage payments page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage payments page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this manage payments page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this manage payments page for a School Administrator."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this manage payments page."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage payments page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this manage payments page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this manage payments page for a Accountant."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this manage payments page."
      }
    ]
  },
  "manage_payrolls": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage payrolls page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage payrolls page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this manage payrolls page for a Director/Owner."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this manage payrolls page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage payrolls page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this manage payrolls page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this manage payrolls page for a School Administrator."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this manage payrolls page."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage payrolls page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this manage payrolls page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this manage payrolls page for a Accountant."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this manage payrolls page."
      }
    ]
  },
  "manage_public_downloads": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage public downloads page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage public downloads page."
      },
      {
        "label": "Draft website content",
        "prompt": "Draft website content/copy appropriate for this manage public downloads page as a Director/Owner."
      },
      {
        "label": "Improve clarity",
        "prompt": "Improve clarity and tone of content on this manage public downloads page for families/community."
      }
    ]
  },
  "manage_public_events": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage public events page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage public events page."
      },
      {
        "label": "Draft website content",
        "prompt": "Draft website content/copy appropriate for this manage public events page as a Director/Owner."
      },
      {
        "label": "Improve clarity",
        "prompt": "Improve clarity and tone of content on this manage public events page for families/community."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage public events page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this manage public events page."
      },
      {
        "label": "Draft website content",
        "prompt": "Draft website content/copy appropriate for this manage public events page as a Deputy Head - Academic."
      },
      {
        "label": "Improve clarity",
        "prompt": "Improve clarity and tone of content on this manage public events page for families/community."
      }
    ],
    "21": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage public events page for a Talent Development: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Talent Development based on this manage public events page."
      },
      {
        "label": "Draft website content",
        "prompt": "Draft website content/copy appropriate for this manage public events page as a Talent Development."
      },
      {
        "label": "Improve clarity",
        "prompt": "Improve clarity and tone of content on this manage public events page for families/community."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage public events page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this manage public events page."
      },
      {
        "label": "Draft website content",
        "prompt": "Draft website content/copy appropriate for this manage public events page as a Deputy Head - Discipline."
      },
      {
        "label": "Improve clarity",
        "prompt": "Improve clarity and tone of content on this manage public events page for families/community."
      }
    ]
  },
  "manage_requisitions": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage requisitions page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this manage requisitions page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage requisitions page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage requisitions page."
      }
    ]
  },
  "manage_roles": {
    "2": [
      {
        "label": "Role audit",
        "prompt": "Audit these role definitions: over-privileged roles, unused permissions, risks."
      },
      {
        "label": "Least privilege",
        "prompt": "Suggest least-privilege adjustments for the roles shown on this page."
      }
    ]
  },
  "manage_school_gallery": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage school gallery page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage school gallery page."
      },
      {
        "label": "Draft website content",
        "prompt": "Draft website content/copy appropriate for this manage school gallery page as a Director/Owner."
      },
      {
        "label": "Improve clarity",
        "prompt": "Improve clarity and tone of content on this manage school gallery page for families/community."
      }
    ],
    "21": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage school gallery page for a Talent Development: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Talent Development based on this manage school gallery page."
      },
      {
        "label": "Draft website content",
        "prompt": "Draft website content/copy appropriate for this manage school gallery page as a Talent Development."
      },
      {
        "label": "Improve clarity",
        "prompt": "Improve clarity and tone of content on this manage school gallery page for families/community."
      }
    ]
  },
  "manage_sms": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage sms page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage sms page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage sms page and Director/Owner role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage sms page for clarity and professionalism."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage sms page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this manage sms page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage sms page and School Administrator role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage sms page for clarity and professionalism."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage sms page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this manage sms page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage sms page and Headteacher role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage sms page for clarity and professionalism."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage sms page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this manage sms page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage sms page and Accountant role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage sms page for clarity and professionalism."
      }
    ]
  },
  "manage_sms_configurations": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage sms configurations page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this manage sms configurations page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this manage sms configurations page and System Administrator role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this manage sms configurations page for clarity and professionalism."
      }
    ]
  },
  "manage_staff": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage staff page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage staff page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this manage staff page for a Director/Owner."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this manage staff page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage staff page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this manage staff page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this manage staff page for a School Administrator."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this manage staff page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage staff page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this manage staff page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this manage staff page for a Headteacher."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this manage staff page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage staff page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this manage staff page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this manage staff page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this manage staff page."
      }
    ]
  },
  "manage_staff_children": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage staff children page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this manage staff children page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this manage staff children page for a Staff."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this manage staff children page."
      }
    ]
  },
  "manage_staff_meetings": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage staff meetings page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this manage staff meetings page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this manage staff meetings page for a School Administrator."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this manage staff meetings page."
      }
    ]
  },
  "manage_stock": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage stock page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this manage stock page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this manage stock page for a Staff."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this manage stock page."
      }
    ]
  },
  "manage_students": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage students page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this manage students page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this manage students page for a School Administrator."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this manage students page."
      }
    ]
  },
  "manage_students_admissions": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage students admissions page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this manage students admissions page."
      },
      {
        "label": "Summarize pipeline",
        "prompt": "Summarize the admissions pipeline/status on this manage students admissions page for a School Administrator."
      },
      {
        "label": "Draft applicant communication",
        "prompt": "Draft an applicant/parent communication based on this manage students admissions page."
      }
    ]
  },
  "manage_subjects": {
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage subjects page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this manage subjects page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage subjects page for a Headteacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage subjects page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage subjects page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this manage subjects page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage subjects page for a Deputy Head - Academic? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage subjects page."
      }
    ]
  },
  "manage_system_announcements": {
    "2": [
      {
        "label": "Draft system notice",
        "prompt": "Draft a clear system notice for staff: what changed, when, what to do."
      },
      {
        "label": "Maintenance wording",
        "prompt": "Write maintenance-window wording that is clear and reassuring for staff."
      }
    ]
  },
  "manage_teachers": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage teachers page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this manage teachers page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this manage teachers page for a Staff."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this manage teachers page."
      }
    ]
  },
  "manage_terms": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage terms page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage terms page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage terms page for a Director/Owner? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage terms page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage terms page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this manage terms page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage terms page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage terms page."
      }
    ]
  },
  "manage_timetable": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage timetable page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage timetable page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage timetable page for a Director/Owner? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage timetable page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage timetable page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this manage timetable page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage timetable page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage timetable page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage timetable page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this manage timetable page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage timetable page for a Headteacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage timetable page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage timetable page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this manage timetable page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage timetable page for a Deputy Head - Academic? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage timetable page."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage timetable page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this manage timetable page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage timetable page for a Class Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage timetable page."
      }
    ]
  },
  "manage_transport": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage transport page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage transport page."
      },
      {
        "label": "Transport summary",
        "prompt": "Summarize transport operations/issues on this manage transport page for a Director/Owner."
      },
      {
        "label": "Draft transport notice",
        "prompt": "Draft a transport notice or incident note based on this manage transport page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage transport page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this manage transport page."
      },
      {
        "label": "Transport summary",
        "prompt": "Summarize transport operations/issues on this manage transport page for a School Administrator."
      },
      {
        "label": "Draft transport notice",
        "prompt": "Draft a transport notice or incident note based on this manage transport page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage transport page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this manage transport page."
      },
      {
        "label": "Transport summary",
        "prompt": "Summarize transport operations/issues on this manage transport page for a Headteacher."
      },
      {
        "label": "Draft transport notice",
        "prompt": "Draft a transport notice or incident note based on this manage transport page."
      }
    ]
  },
  "manage_transport_fee_structure": {
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage transport fee structure page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this manage transport fee structure page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this manage transport fee structure page for a Accountant."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this manage transport fee structure page."
      }
    ]
  },
  "manage_uniform_sales": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage uniform sales page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage uniform sales page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage uniform sales page for a Director/Owner? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage uniform sales page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage uniform sales page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this manage uniform sales page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage uniform sales page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage uniform sales page."
      }
    ],
    "14": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage uniform sales page for a Inventory Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Inventory Manager based on this manage uniform sales page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage uniform sales page for a Inventory Manager? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage uniform sales page."
      }
    ]
  },
  "manage_users": {
    "2": [
      {
        "label": "Account review",
        "prompt": "Review these user accounts: dormant accounts, privilege concerns, recommended actions."
      },
      {
        "label": "Offboarding note",
        "prompt": "Draft an offboarding checklist for disabling a staff account safely."
      }
    ],
    "5": [
      {
        "label": "Staff account note",
        "prompt": "Help me write a note about a staff account issue for the records."
      }
    ]
  },
  "manage_website": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage website page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this manage website page."
      },
      {
        "label": "Draft website content",
        "prompt": "Draft website content/copy appropriate for this manage website page as a Director/Owner."
      },
      {
        "label": "Improve clarity",
        "prompt": "Improve clarity and tone of content on this manage website page for families/community."
      }
    ],
    "4": [
      {
        "label": "Parent update",
        "prompt": "Draft a parent update about school operations in plain, reassuring language."
      }
    ],
    "5": [
      {
        "label": "Headteacher message",
        "prompt": "Draft a headteacher message for the website or newsletter on this topic."
      }
    ],
    "*": [
      {
        "label": "Draft an announcement",
        "prompt": "Draft a short school announcement for the website: what happened, when, what parents should do."
      },
      {
        "label": "Write an event blurb",
        "prompt": "Write a 2-sentence public blurb for a school event."
      }
    ]
  },
  "manage_whatsapp": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage whatsapp page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this manage whatsapp page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage whatsapp page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage whatsapp page."
      }
    ]
  },
  "manage_whatsapp_configurations": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this manage whatsapp configurations page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this manage whatsapp configurations page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this manage whatsapp configurations page for a System Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this manage whatsapp configurations page."
      }
    ]
  },
  "mark_attendance": {
    "23": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this mark attendance page for a Driver: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Driver based on this mark attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this mark attendance page for a Driver."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this mark attendance page."
      }
    ]
  },
  "meal_statistics": {
    "16": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this meal statistics page for a Catering Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Catering Manager based on this meal statistics page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this meal statistics page for a Catering Manager? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this meal statistics page."
      }
    ]
  },
  "medical_alerts": {
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this medical alerts page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this medical alerts page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this medical alerts page for a Boarding Master/Matron? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this medical alerts page."
      }
    ]
  },
  "mentor_feedback": {
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this mentor feedback page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this mentor feedback page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this mentor feedback page for a Intern/Student Teacher."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this mentor feedback page."
      }
    ]
  },
  "mentor_meetings": {
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this mentor meetings page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this mentor meetings page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this mentor meetings page for a Intern/Student Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this mentor meetings page."
      }
    ]
  },
  "mentor_notes": {
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this mentor notes page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this mentor notes page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this mentor notes page for a Intern/Student Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this mentor notes page."
      }
    ]
  },
  "menu_planning": {
    "16": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this menu planning page for a Catering Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Catering Manager based on this menu planning page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this menu planning page for a Catering Manager? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this menu planning page."
      }
    ]
  },
  "migrations": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this migrations page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this migrations page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this migrations page for a System Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this migrations page."
      }
    ]
  },
  "module_enablement": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this module enablement page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this module enablement page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this module enablement page for a System Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this module enablement page."
      }
    ]
  },
  "monthly_discipline_report": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this monthly discipline report page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this monthly discipline report page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this monthly discipline report page for a Staff."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this monthly discipline report page for stakeholders, tailored to a Staff."
      }
    ]
  },
  "morning_roll_call": {
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this morning roll call page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this morning roll call page."
      },
      {
        "label": "Boarding summary",
        "prompt": "Summarize boarding/welfare issues on this morning roll call page for a Boarding Master/Matron."
      },
      {
        "label": "Draft boarding log",
        "prompt": "Draft a boarding log or parent/staff note based on this morning roll call page."
      }
    ]
  },
  "mpesa_reconciliation": {
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this mpesa reconciliation page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this mpesa reconciliation page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this mpesa reconciliation page for a Accountant."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this mpesa reconciliation page."
      }
    ]
  },
  "mpesa_settlements": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this mpesa settlements page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this mpesa settlements page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this mpesa settlements page for a Director/Owner? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this mpesa settlements page."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this mpesa settlements page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this mpesa settlements page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this mpesa settlements page for a Accountant? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this mpesa settlements page."
      }
    ]
  },
  "my_attendance": {
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my attendance page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this my attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this my attendance page for a Deputy Head - Academic."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this my attendance page."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my attendance page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this my attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this my attendance page for a Class Teacher."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this my attendance page."
      }
    ],
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my attendance page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this my attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this my attendance page for a Subject Teacher."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this my attendance page."
      }
    ],
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my attendance page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this my attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this my attendance page for a Boarding Master/Matron."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this my attendance page."
      }
    ],
    "23": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my attendance page for a Driver: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Driver based on this my attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this my attendance page for a Driver."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this my attendance page."
      }
    ],
    "32": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my attendance page for a Kitchen Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Kitchen Staff based on this my attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this my attendance page for a Kitchen Staff."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this my attendance page."
      }
    ],
    "33": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my attendance page for a Security Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Security Staff based on this my attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this my attendance page for a Security Staff."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this my attendance page."
      }
    ],
    "34": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my attendance page for a Janitor: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Janitor based on this my attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this my attendance page for a Janitor."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this my attendance page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my attendance page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this my attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this my attendance page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this my attendance page."
      }
    ],
    "64": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my attendance page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this my attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this my attendance page for a Staff."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this my attendance page."
      }
    ]
  },
  "my_cats": {
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my cats page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this my cats page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my cats page for a Class Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my cats page."
      }
    ],
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my cats page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this my cats page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my cats page for a Subject Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my cats page."
      }
    ]
  },
  "my_classes_taught": {
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my classes taught page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this my classes taught page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this my classes taught page for a Subject Teacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this my classes taught page."
      }
    ]
  },
  "my_family": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my family page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this my family page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my family page for a System Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my family page."
      }
    ],
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my family page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this my family page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my family page for a Director/Owner? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my family page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my family page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this my family page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my family page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my family page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my family page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this my family page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my family page for a Headteacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my family page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my family page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this my family page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my family page for a Deputy Head - Academic? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my family page."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my family page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this my family page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my family page for a Class Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my family page."
      }
    ],
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my family page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this my family page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my family page for a Subject Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my family page."
      }
    ],
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my family page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this my family page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my family page for a Intern/Student Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my family page."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my family page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this my family page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my family page for a Accountant? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my family page."
      }
    ],
    "14": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my family page for a Inventory Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Inventory Manager based on this my family page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my family page for a Inventory Manager? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my family page."
      }
    ],
    "16": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my family page for a Catering Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Catering Manager based on this my family page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my family page for a Catering Manager? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my family page."
      }
    ],
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my family page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this my family page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my family page for a Boarding Master/Matron? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my family page."
      }
    ],
    "21": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my family page for a Talent Development: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Talent Development based on this my family page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my family page for a Talent Development? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my family page."
      }
    ],
    "23": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my family page for a Driver: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Driver based on this my family page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my family page for a Driver? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my family page."
      }
    ],
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my family page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this my family page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my family page for a Counselor/Chaplain? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my family page."
      }
    ],
    "32": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my family page for a Kitchen Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Kitchen Staff based on this my family page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my family page for a Kitchen Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my family page."
      }
    ],
    "33": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my family page for a Security Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Security Staff based on this my family page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my family page for a Security Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my family page."
      }
    ],
    "34": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my family page for a Janitor: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Janitor based on this my family page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my family page for a Janitor? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my family page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my family page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this my family page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my family page for a Deputy Head - Discipline? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my family page."
      }
    ],
    "64": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my family page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this my family page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my family page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my family page."
      }
    ],
    "71": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my family page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this my family page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my family page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my family page."
      }
    ],
    "72": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my family page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this my family page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my family page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my family page."
      }
    ]
  },
  "my_mentor": {
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my mentor page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this my mentor page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my mentor page for a Intern/Student Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my mentor page."
      }
    ]
  },
  "my_routes": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my routes page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this my routes page."
      },
      {
        "label": "Transport summary",
        "prompt": "Summarize transport operations/issues on this my routes page for a Director/Owner."
      },
      {
        "label": "Draft transport notice",
        "prompt": "Draft a transport notice or incident note based on this my routes page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my routes page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this my routes page."
      },
      {
        "label": "Transport summary",
        "prompt": "Summarize transport operations/issues on this my routes page for a School Administrator."
      },
      {
        "label": "Draft transport notice",
        "prompt": "Draft a transport notice or incident note based on this my routes page."
      }
    ],
    "23": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my routes page for a Driver: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Driver based on this my routes page."
      },
      {
        "label": "Transport summary",
        "prompt": "Summarize transport operations/issues on this my routes page for a Driver."
      },
      {
        "label": "Draft transport notice",
        "prompt": "Draft a transport notice or incident note based on this my routes page."
      }
    ]
  },
  "my_schemes_of_work": {
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my schemes of work page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this my schemes of work page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this my schemes of work page for a Headteacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this my schemes of work page."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my schemes of work page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this my schemes of work page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this my schemes of work page for a Class Teacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this my schemes of work page."
      }
    ],
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my schemes of work page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this my schemes of work page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this my schemes of work page for a Subject Teacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this my schemes of work page."
      }
    ]
  },
  "my_students_list": {
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my students list page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this my students list page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this my students list page for a Deputy Head - Academic."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this my students list page."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my students list page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this my students list page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this my students list page for a Class Teacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this my students list page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my students list page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this my students list page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this my students list page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this my students list page."
      }
    ]
  },
  "my_students_performance": {
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my students performance page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this my students performance page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this my students performance page for a Class Teacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this my students performance page."
      }
    ]
  },
  "my_subject_cats": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my subject cats page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this my subject cats page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my subject cats page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my subject cats page."
      }
    ]
  },
  "my_subject_syllabus": {
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my subject syllabus page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this my subject syllabus page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my subject syllabus page for a Subject Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my subject syllabus page."
      }
    ]
  },
  "my_subjects_overview": {
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my subjects overview page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this my subjects overview page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this my subjects overview page for a Subject Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this my subjects overview page."
      }
    ]
  },
  "my_vehicle": {
    "23": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this my vehicle page for a Driver: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Driver based on this my vehicle page."
      },
      {
        "label": "Transport summary",
        "prompt": "Summarize transport operations/issues on this my vehicle page for a Driver."
      },
      {
        "label": "Draft transport notice",
        "prompt": "Draft a transport notice or incident note based on this my vehicle page."
      }
    ]
  },
  "national_exams": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this national exams page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this national exams page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this national exams page for a Director/Owner."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this national exams page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this national exams page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this national exams page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this national exams page for a School Administrator."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this national exams page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this national exams page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this national exams page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this national exams page for a Headteacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this national exams page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this national exams page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this national exams page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this national exams page for a Deputy Head - Academic."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this national exams page."
      }
    ]
  },
  "observation_feedback": {
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this observation feedback page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this observation feedback page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this observation feedback page for a Intern/Student Teacher."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this observation feedback page."
      }
    ]
  },
  "observation_schedule": {
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this observation schedule page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this observation schedule page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this observation schedule page for a Intern/Student Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this observation schedule page."
      }
    ]
  },
  "parent_meeting_records": {
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this parent meeting records page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this parent meeting records page."
      },
      {
        "label": "Parent summary",
        "prompt": "Summarize parent/guardian matters on this parent meeting records page for a Class Teacher."
      },
      {
        "label": "Draft parent message",
        "prompt": "Draft a clear parent/guardian message based on this parent meeting records page."
      }
    ],
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this parent meeting records page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this parent meeting records page."
      },
      {
        "label": "Parent summary",
        "prompt": "Summarize parent/guardian matters on this parent meeting records page for a Counselor/Chaplain."
      },
      {
        "label": "Draft parent message",
        "prompt": "Draft a clear parent/guardian message based on this parent meeting records page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this parent meeting records page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this parent meeting records page."
      },
      {
        "label": "Parent summary",
        "prompt": "Summarize parent/guardian matters on this parent meeting records page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft parent message",
        "prompt": "Draft a clear parent/guardian message based on this parent meeting records page."
      }
    ]
  },
  "parent_meetings": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this parent meetings page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this parent meetings page."
      },
      {
        "label": "Parent summary",
        "prompt": "Summarize parent/guardian matters on this parent meetings page for a School Administrator."
      },
      {
        "label": "Draft parent message",
        "prompt": "Draft a clear parent/guardian message based on this parent meetings page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this parent meetings page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this parent meetings page."
      },
      {
        "label": "Parent summary",
        "prompt": "Summarize parent/guardian matters on this parent meetings page for a Headteacher."
      },
      {
        "label": "Draft parent message",
        "prompt": "Draft a clear parent/guardian message based on this parent meetings page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this parent meetings page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this parent meetings page."
      },
      {
        "label": "Parent summary",
        "prompt": "Summarize parent/guardian matters on this parent meetings page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft parent message",
        "prompt": "Draft a clear parent/guardian message based on this parent meetings page."
      }
    ]
  },
  "parent_refunds": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this parent refunds page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this parent refunds page."
      },
      {
        "label": "Parent summary",
        "prompt": "Summarize parent/guardian matters on this parent refunds page for a Director/Owner."
      },
      {
        "label": "Draft parent message",
        "prompt": "Draft a clear parent/guardian message based on this parent refunds page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this parent refunds page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this parent refunds page."
      },
      {
        "label": "Parent summary",
        "prompt": "Summarize parent/guardian matters on this parent refunds page for a School Administrator."
      },
      {
        "label": "Draft parent message",
        "prompt": "Draft a clear parent/guardian message based on this parent refunds page."
      }
    ]
  },
  "participation_reports": {
    "21": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this participation reports page for a Talent Development: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Talent Development based on this participation reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this participation reports page for a Talent Development."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this participation reports page for stakeholders, tailored to a Talent Development."
      }
    ]
  },
  "past_papers": {
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this past papers page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this past papers page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this past papers page for a Subject Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this past papers page."
      }
    ]
  },
  "payment_integration_settings": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this payment integration settings page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this payment integration settings page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this payment integration settings page for a System Administrator."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this payment integration settings page."
      }
    ]
  },
  "payment_reconciliation": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this payment reconciliation page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this payment reconciliation page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this payment reconciliation page for a Director/Owner."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this payment reconciliation page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this payment reconciliation page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this payment reconciliation page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this payment reconciliation page for a School Administrator."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this payment reconciliation page."
      }
    ]
  },
  "payment_records": {
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this payment records page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this payment records page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this payment records page for a Accountant."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this payment records page."
      }
    ]
  },
  "payment_reports": {
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this payment reports page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this payment reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this payment reports page for a Accountant."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this payment reports page for stakeholders, tailored to a Accountant."
      }
    ]
  },
  "payroll": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this payroll page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this payroll page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this payroll page for a Director/Owner."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this payroll page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this payroll page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this payroll page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this payroll page for a School Administrator."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this payroll page."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this payroll page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this payroll page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this payroll page for a Accountant."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this payroll page."
      }
    ]
  },
  "payslips": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this payslips page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this payslips page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this payslips page for a Director/Owner? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this payslips page."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this payslips page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this payslips page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this payslips page for a Accountant? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this payslips page."
      }
    ]
  },
  "pending_discipline_cases": {
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this pending discipline cases page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this pending discipline cases page."
      },
      {
        "label": "Summarize cases",
        "prompt": "Summarize discipline/conduct issues on this pending discipline cases page and recommend next steps for a Deputy Head - Discipline."
      },
      {
        "label": "Draft case note",
        "prompt": "Draft a factual case note or parent communication based on this pending discipline cases page."
      }
    ]
  },
  "pending_exeat_requests": {
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this pending exeat requests page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this pending exeat requests page."
      },
      {
        "label": "Boarding summary",
        "prompt": "Summarize boarding/welfare issues on this pending exeat requests page for a Boarding Master/Matron."
      },
      {
        "label": "Draft boarding log",
        "prompt": "Draft a boarding log or parent/staff note based on this pending exeat requests page."
      }
    ]
  },
  "performance_analysis": {
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this performance analysis page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this performance analysis page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this performance analysis page for a Headteacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this performance analysis page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this performance analysis page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this performance analysis page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this performance analysis page for a Deputy Head - Academic? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this performance analysis page."
      }
    ]
  },
  "performance_reports": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this performance reports page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this performance reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this performance reports page for a Director/Owner."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this performance reports page for stakeholders, tailored to a Director/Owner."
      }
    ]
  },
  "performance_trends": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this performance trends page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this performance trends page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this performance trends page for a Staff."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this performance trends page for stakeholders, tailored to a Staff."
      }
    ]
  },
  "permission_changes": {
    "2": [
      {
        "label": "Change digest",
        "prompt": "Summarise these permission changes: who changed what, anything that needs verification."
      },
      {
        "label": "Escalation check",
        "prompt": "Flag any permission changes that look like privilege escalation."
      }
    ]
  },
  "permission_policies": {
    "2": [
      {
        "label": "Policy review",
        "prompt": "Review these permission policies for gaps or conflicts and suggest fixes."
      },
      {
        "label": "Explain policy",
        "prompt": "Explain what this permission policy controls in plain language."
      }
    ]
  },
  "permissions_exeats": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this permissions exeats page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this permissions exeats page."
      },
      {
        "label": "Boarding summary",
        "prompt": "Summarize boarding/welfare issues on this permissions exeats page for a Director/Owner."
      },
      {
        "label": "Draft boarding log",
        "prompt": "Draft a boarding log or parent/staff note based on this permissions exeats page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this permissions exeats page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this permissions exeats page."
      },
      {
        "label": "Boarding summary",
        "prompt": "Summarize boarding/welfare issues on this permissions exeats page for a School Administrator."
      },
      {
        "label": "Draft boarding log",
        "prompt": "Draft a boarding log or parent/staff note based on this permissions exeats page."
      }
    ],
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this permissions exeats page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this permissions exeats page."
      },
      {
        "label": "Boarding summary",
        "prompt": "Summarize boarding/welfare issues on this permissions exeats page for a Boarding Master/Matron."
      },
      {
        "label": "Draft boarding log",
        "prompt": "Draft a boarding log or parent/staff note based on this permissions exeats page."
      }
    ],
    "33": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this permissions exeats page for a Security Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Security Staff based on this permissions exeats page."
      },
      {
        "label": "Boarding summary",
        "prompt": "Summarize boarding/welfare issues on this permissions exeats page for a Security Staff."
      },
      {
        "label": "Draft boarding log",
        "prompt": "Draft a boarding log or parent/staff note based on this permissions exeats page."
      }
    ]
  },
  "petty_cash": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this petty cash page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this petty cash page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this petty cash page for a Director/Owner? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this petty cash page."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this petty cash page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this petty cash page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this petty cash page for a Accountant? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this petty cash page."
      }
    ]
  },
  "photo_capture": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this photo capture page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this photo capture page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this photo capture page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this photo capture page."
      }
    ]
  },
  "placement_tests": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this placement tests page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this placement tests page."
      },
      {
        "label": "Summarize pipeline",
        "prompt": "Summarize the admissions pipeline/status on this placement tests page for a School Administrator."
      },
      {
        "label": "Draft applicant communication",
        "prompt": "Draft an applicant/parent communication based on this placement tests page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this placement tests page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this placement tests page."
      },
      {
        "label": "Summarize pipeline",
        "prompt": "Summarize the admissions pipeline/status on this placement tests page for a Deputy Head - Academic."
      },
      {
        "label": "Draft applicant communication",
        "prompt": "Draft an applicant/parent communication based on this placement tests page."
      }
    ]
  },
  "policy_violations": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this policy violations page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this policy violations page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this policy violations page for a System Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this policy violations page."
      }
    ]
  },
  "products_catalog_management": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this products catalog management page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this products catalog management page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this products catalog management page for a School Administrator."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this products catalog management page."
      }
    ],
    "14": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this products catalog management page for a Inventory Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Inventory Manager based on this products catalog management page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this products catalog management page for a Inventory Manager."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this products catalog management page."
      }
    ]
  },
  "profile": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this profile page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this profile page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this profile page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this profile page."
      }
    ]
  },
  "pta_management": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this pta management page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this pta management page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this pta management page for a Director/Owner? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this pta management page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this pta management page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this pta management page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this pta management page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this pta management page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this pta management page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this pta management page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this pta management page for a Headteacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this pta management page."
      }
    ]
  },
  "purchase_orders": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this purchase orders page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this purchase orders page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this purchase orders page for a Director/Owner."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this purchase orders page."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this purchase orders page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this purchase orders page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this purchase orders page for a Accountant."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this purchase orders page."
      }
    ],
    "14": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this purchase orders page for a Inventory Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Inventory Manager based on this purchase orders page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this purchase orders page for a Inventory Manager."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this purchase orders page."
      }
    ],
    "16": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this purchase orders page for a Catering Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Catering Manager based on this purchase orders page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this purchase orders page for a Catering Manager."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this purchase orders page."
      }
    ],
    "71": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this purchase orders page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this purchase orders page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this purchase orders page for a Staff."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this purchase orders page."
      }
    ]
  },
  "purchase_reports": {
    "14": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this purchase reports page for a Inventory Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Inventory Manager based on this purchase reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this purchase reports page for a Inventory Manager."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this purchase reports page for stakeholders, tailored to a Inventory Manager."
      }
    ],
    "71": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this purchase reports page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this purchase reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this purchase reports page for a Staff."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this purchase reports page for stakeholders, tailored to a Staff."
      }
    ]
  },
  "qr-scanner": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this qr-scanner page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this qr-scanner page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this qr-scanner page for a Director/Owner? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this qr-scanner page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this qr-scanner page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this qr-scanner page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this qr-scanner page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this qr-scanner page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this qr-scanner page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this qr-scanner page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this qr-scanner page for a Headteacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this qr-scanner page."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this qr-scanner page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this qr-scanner page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this qr-scanner page for a Class Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this qr-scanner page."
      }
    ],
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this qr-scanner page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this qr-scanner page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this qr-scanner page for a Subject Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this qr-scanner page."
      }
    ],
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this qr-scanner page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this qr-scanner page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this qr-scanner page for a Boarding Master/Matron? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this qr-scanner page."
      }
    ],
    "23": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this qr-scanner page for a Driver: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Driver based on this qr-scanner page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this qr-scanner page for a Driver? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this qr-scanner page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this qr-scanner page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this qr-scanner page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this qr-scanner page for a Deputy Head - Discipline? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this qr-scanner page."
      }
    ]
  },
  "rate_limiting_status": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this rate limiting status page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this rate limiting status page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this rate limiting status page for a System Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this rate limiting status page."
      }
    ]
  },
  "reflection_journal": {
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this reflection journal page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this reflection journal page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this reflection journal page for a Intern/Student Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this reflection journal page."
      }
    ]
  },
  "report_admission_conversion": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report admission conversion page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this report admission conversion page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report admission conversion page for a Director/Owner."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report admission conversion page for stakeholders, tailored to a Director/Owner."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report admission conversion page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this report admission conversion page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report admission conversion page for a School Administrator."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report admission conversion page for stakeholders, tailored to a School Administrator."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report admission conversion page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this report admission conversion page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report admission conversion page for a Headteacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report admission conversion page for stakeholders, tailored to a Headteacher."
      }
    ]
  },
  "report_cards": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report cards page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this report cards page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report cards page for a Director/Owner."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report cards page for stakeholders, tailored to a Director/Owner."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report cards page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this report cards page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report cards page for a School Administrator."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report cards page for stakeholders, tailored to a School Administrator."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report cards page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this report cards page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report cards page for a Headteacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report cards page for stakeholders, tailored to a Headteacher."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report cards page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this report cards page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report cards page for a Deputy Head - Academic."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report cards page for stakeholders, tailored to a Deputy Head - Academic."
      }
    ]
  },
  "report_cbc_class_assessment": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report cbc class assessment page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this report cbc class assessment page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report cbc class assessment page for a Director/Owner."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report cbc class assessment page for stakeholders, tailored to a Director/Owner."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report cbc class assessment page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this report cbc class assessment page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report cbc class assessment page for a School Administrator."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report cbc class assessment page for stakeholders, tailored to a School Administrator."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report cbc class assessment page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this report cbc class assessment page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report cbc class assessment page for a Headteacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report cbc class assessment page for stakeholders, tailored to a Headteacher."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report cbc class assessment page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this report cbc class assessment page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report cbc class assessment page for a Deputy Head - Academic."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report cbc class assessment page for stakeholders, tailored to a Deputy Head - Academic."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report cbc class assessment page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this report cbc class assessment page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report cbc class assessment page for a Class Teacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report cbc class assessment page for stakeholders, tailored to a Class Teacher."
      }
    ]
  },
  "report_cbc_rubric_distribution": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report cbc rubric distribution page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this report cbc rubric distribution page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report cbc rubric distribution page for a Director/Owner."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report cbc rubric distribution page for stakeholders, tailored to a Director/Owner."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report cbc rubric distribution page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this report cbc rubric distribution page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report cbc rubric distribution page for a School Administrator."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report cbc rubric distribution page for stakeholders, tailored to a School Administrator."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report cbc rubric distribution page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this report cbc rubric distribution page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report cbc rubric distribution page for a Headteacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report cbc rubric distribution page for stakeholders, tailored to a Headteacher."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report cbc rubric distribution page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this report cbc rubric distribution page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report cbc rubric distribution page for a Deputy Head - Academic."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report cbc rubric distribution page for stakeholders, tailored to a Deputy Head - Academic."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report cbc rubric distribution page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this report cbc rubric distribution page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report cbc rubric distribution page for a Class Teacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report cbc rubric distribution page for stakeholders, tailored to a Class Teacher."
      }
    ]
  },
  "report_class_attendance_by_term": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report class attendance by term page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this report class attendance by term page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report class attendance by term page for a Director/Owner."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report class attendance by term page for stakeholders, tailored to a Director/Owner."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report class attendance by term page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this report class attendance by term page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report class attendance by term page for a School Administrator."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report class attendance by term page for stakeholders, tailored to a School Administrator."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report class attendance by term page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this report class attendance by term page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report class attendance by term page for a Headteacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report class attendance by term page for stakeholders, tailored to a Headteacher."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report class attendance by term page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this report class attendance by term page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report class attendance by term page for a Deputy Head - Academic."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report class attendance by term page for stakeholders, tailored to a Deputy Head - Academic."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report class attendance by term page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this report class attendance by term page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report class attendance by term page for a Class Teacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report class attendance by term page for stakeholders, tailored to a Class Teacher."
      }
    ]
  },
  "report_communication_delivery": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report communication delivery page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this report communication delivery page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report communication delivery page for a Director/Owner."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report communication delivery page for stakeholders, tailored to a Director/Owner."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report communication delivery page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this report communication delivery page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report communication delivery page for a School Administrator."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report communication delivery page for stakeholders, tailored to a School Administrator."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report communication delivery page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this report communication delivery page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report communication delivery page for a Headteacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report communication delivery page for stakeholders, tailored to a Headteacher."
      }
    ]
  },
  "report_current_enrollment_by_class": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report current enrollment by class page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this report current enrollment by class page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report current enrollment by class page for a Director/Owner."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report current enrollment by class page for stakeholders, tailored to a Director/Owner."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report current enrollment by class page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this report current enrollment by class page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report current enrollment by class page for a School Administrator."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report current enrollment by class page for stakeholders, tailored to a School Administrator."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report current enrollment by class page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this report current enrollment by class page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report current enrollment by class page for a Headteacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report current enrollment by class page for stakeholders, tailored to a Headteacher."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report current enrollment by class page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this report current enrollment by class page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report current enrollment by class page for a Deputy Head - Academic."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report current enrollment by class page for stakeholders, tailored to a Deputy Head - Academic."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report current enrollment by class page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this report current enrollment by class page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report current enrollment by class page for a Class Teacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report current enrollment by class page for stakeholders, tailored to a Class Teacher."
      }
    ]
  },
  "report_discipline_incident_trend": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report discipline incident trend page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this report discipline incident trend page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report discipline incident trend page for a Director/Owner."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report discipline incident trend page for stakeholders, tailored to a Director/Owner."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report discipline incident trend page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this report discipline incident trend page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report discipline incident trend page for a School Administrator."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report discipline incident trend page for stakeholders, tailored to a School Administrator."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report discipline incident trend page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this report discipline incident trend page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report discipline incident trend page for a Headteacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report discipline incident trend page for stakeholders, tailored to a Headteacher."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report discipline incident trend page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this report discipline incident trend page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report discipline incident trend page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report discipline incident trend page for stakeholders, tailored to a Deputy Head - Discipline."
      }
    ]
  },
  "report_enrolled_admissions_cohort": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report enrolled admissions cohort page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this report enrolled admissions cohort page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report enrolled admissions cohort page for a Director/Owner."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report enrolled admissions cohort page for stakeholders, tailored to a Director/Owner."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report enrolled admissions cohort page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this report enrolled admissions cohort page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report enrolled admissions cohort page for a School Administrator."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report enrolled admissions cohort page for stakeholders, tailored to a School Administrator."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report enrolled admissions cohort page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this report enrolled admissions cohort page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report enrolled admissions cohort page for a Headteacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report enrolled admissions cohort page for stakeholders, tailored to a Headteacher."
      }
    ]
  },
  "report_enrollment_trend": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report enrollment trend page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this report enrollment trend page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report enrollment trend page for a Director/Owner."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report enrollment trend page for stakeholders, tailored to a Director/Owner."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report enrollment trend page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this report enrollment trend page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report enrollment trend page for a School Administrator."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report enrollment trend page for stakeholders, tailored to a School Administrator."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report enrollment trend page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this report enrollment trend page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report enrollment trend page for a Headteacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report enrollment trend page for stakeholders, tailored to a Headteacher."
      }
    ]
  },
  "report_fee_arrears_by_class": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report fee arrears by class page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this report fee arrears by class page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report fee arrears by class page for a Director/Owner."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report fee arrears by class page for stakeholders, tailored to a Director/Owner."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report fee arrears by class page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this report fee arrears by class page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report fee arrears by class page for a School Administrator."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report fee arrears by class page for stakeholders, tailored to a School Administrator."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report fee arrears by class page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this report fee arrears by class page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report fee arrears by class page for a Headteacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report fee arrears by class page for stakeholders, tailored to a Headteacher."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report fee arrears by class page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this report fee arrears by class page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report fee arrears by class page for a Accountant."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report fee arrears by class page for stakeholders, tailored to a Accountant."
      }
    ]
  },
  "report_fee_summary_by_class": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report fee summary by class page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this report fee summary by class page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report fee summary by class page for a Director/Owner."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report fee summary by class page for stakeholders, tailored to a Director/Owner."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report fee summary by class page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this report fee summary by class page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report fee summary by class page for a School Administrator."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report fee summary by class page for stakeholders, tailored to a School Administrator."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report fee summary by class page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this report fee summary by class page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report fee summary by class page for a Headteacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report fee summary by class page for stakeholders, tailored to a Headteacher."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report fee summary by class page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this report fee summary by class page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report fee summary by class page for a Accountant."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report fee summary by class page for stakeholders, tailored to a Accountant."
      }
    ]
  },
  "report_food_consumption_trend": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report food consumption trend page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this report food consumption trend page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report food consumption trend page for a Director/Owner."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report food consumption trend page for stakeholders, tailored to a Director/Owner."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report food consumption trend page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this report food consumption trend page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report food consumption trend page for a School Administrator."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report food consumption trend page for stakeholders, tailored to a School Administrator."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report food consumption trend page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this report food consumption trend page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report food consumption trend page for a Headteacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report food consumption trend page for stakeholders, tailored to a Headteacher."
      }
    ],
    "16": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report food consumption trend page for a Catering Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Catering Manager based on this report food consumption trend page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report food consumption trend page for a Catering Manager."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report food consumption trend page for stakeholders, tailored to a Catering Manager."
      }
    ],
    "32": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report food consumption trend page for a Kitchen Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Kitchen Staff based on this report food consumption trend page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report food consumption trend page for a Kitchen Staff."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report food consumption trend page for stakeholders, tailored to a Kitchen Staff."
      }
    ],
    "71": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report food consumption trend page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this report food consumption trend page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report food consumption trend page for a Staff."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report food consumption trend page for stakeholders, tailored to a Staff."
      }
    ]
  },
  "report_inventory_stock_levels": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report inventory stock levels page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this report inventory stock levels page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report inventory stock levels page for a Director/Owner."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report inventory stock levels page for stakeholders, tailored to a Director/Owner."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report inventory stock levels page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this report inventory stock levels page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report inventory stock levels page for a School Administrator."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report inventory stock levels page for stakeholders, tailored to a School Administrator."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report inventory stock levels page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this report inventory stock levels page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report inventory stock levels page for a Headteacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report inventory stock levels page for stakeholders, tailored to a Headteacher."
      }
    ],
    "14": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report inventory stock levels page for a Inventory Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Inventory Manager based on this report inventory stock levels page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report inventory stock levels page for a Inventory Manager."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report inventory stock levels page for stakeholders, tailored to a Inventory Manager."
      }
    ]
  },
  "report_staff_attendance_rate": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report staff attendance rate page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this report staff attendance rate page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report staff attendance rate page for a Director/Owner."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report staff attendance rate page for stakeholders, tailored to a Director/Owner."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report staff attendance rate page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this report staff attendance rate page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report staff attendance rate page for a School Administrator."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report staff attendance rate page for stakeholders, tailored to a School Administrator."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report staff attendance rate page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this report staff attendance rate page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report staff attendance rate page for a Headteacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report staff attendance rate page for stakeholders, tailored to a Headteacher."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report staff attendance rate page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this report staff attendance rate page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report staff attendance rate page for a Deputy Head - Academic."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report staff attendance rate page for stakeholders, tailored to a Deputy Head - Academic."
      }
    ]
  },
  "report_system_audit_summary": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report system audit summary page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this report system audit summary page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report system audit summary page for a System Administrator."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report system audit summary page for stakeholders, tailored to a System Administrator."
      }
    ],
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this report system audit summary page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this report system audit summary page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this report system audit summary page for a Director/Owner."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this report system audit summary page for stakeholders, tailored to a Director/Owner."
      }
    ]
  },
  "reset_default_password": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this reset default password page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this reset default password page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this reset default password page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this reset default password page."
      }
    ]
  },
  "resolved_discipline_cases": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this resolved discipline cases page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this resolved discipline cases page."
      },
      {
        "label": "Summarize cases",
        "prompt": "Summarize discipline/conduct issues on this resolved discipline cases page and recommend next steps for a Staff."
      },
      {
        "label": "Draft case note",
        "prompt": "Draft a factual case note or parent communication based on this resolved discipline cases page."
      }
    ]
  },
  "resource_based_permissions": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this resource based permissions page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this resource based permissions page."
      },
      {
        "label": "Security/ops check",
        "prompt": "Identify security, operational, or configuration risks on this resource based permissions page for a System Administrator."
      },
      {
        "label": "Draft ops note",
        "prompt": "Draft an operations/security note based on this resource based permissions page."
      }
    ]
  },
  "results_analysis": {
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this results analysis page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this results analysis page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this results analysis page for a Headteacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this results analysis page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this results analysis page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this results analysis page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this results analysis page for a Deputy Head - Academic."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this results analysis page."
      }
    ]
  },
  "rewards_recognition": {
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this rewards recognition page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this rewards recognition page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this rewards recognition page for a Deputy Head - Discipline? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this rewards recognition page."
      }
    ]
  },
  "role_navigation_config": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this role navigation config page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this role navigation config page."
      },
      {
        "label": "Security/ops check",
        "prompt": "Identify security, operational, or configuration risks on this role navigation config page for a Staff."
      },
      {
        "label": "Draft ops note",
        "prompt": "Draft an operations/security note based on this role navigation config page."
      }
    ]
  },
  "role_permission_matrix": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this role permission matrix page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this role permission matrix page."
      },
      {
        "label": "Security/ops check",
        "prompt": "Identify security, operational, or configuration risks on this role permission matrix page for a System Administrator."
      },
      {
        "label": "Draft ops note",
        "prompt": "Draft an operations/security note based on this role permission matrix page."
      }
    ]
  },
  "room_assignments": {
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this room assignments page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this room assignments page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this room assignments page for a Boarding Master/Matron? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this room assignments page."
      }
    ]
  },
  "route_access_rules": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this route access rules page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this route access rules page."
      },
      {
        "label": "Transport summary",
        "prompt": "Summarize transport operations/issues on this route access rules page for a System Administrator."
      },
      {
        "label": "Draft transport notice",
        "prompt": "Draft a transport notice or incident note based on this route access rules page."
      }
    ]
  },
  "route_registry": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this route registry page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this route registry page."
      },
      {
        "label": "Transport summary",
        "prompt": "Summarize transport operations/issues on this route registry page for a System Administrator."
      },
      {
        "label": "Draft transport notice",
        "prompt": "Draft a transport notice or incident note based on this route registry page."
      }
    ]
  },
  "salary_advances": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this salary advances page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this salary advances page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this salary advances page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this salary advances page."
      }
    ]
  },
  "sanctions": {
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this sanctions page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this sanctions page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this sanctions page for a Deputy Head - Discipline? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this sanctions page."
      }
    ]
  },
  "schedule_parent_meetings": {
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this schedule parent meetings page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this schedule parent meetings page."
      },
      {
        "label": "Parent summary",
        "prompt": "Summarize parent/guardian matters on this schedule parent meetings page for a Counselor/Chaplain."
      },
      {
        "label": "Draft parent message",
        "prompt": "Draft a clear parent/guardian message based on this schedule parent meetings page."
      }
    ]
  },
  "schemes_of_work": {
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this schemes of work page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this schemes of work page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this schemes of work page for a Deputy Head - Academic."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this schemes of work page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this schemes of work page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this schemes of work page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this schemes of work page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this schemes of work page."
      }
    ]
  },
  "school_events": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this school events page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this school events page."
      },
      {
        "label": "Draft website content",
        "prompt": "Draft website content/copy appropriate for this school events page as a Director/Owner."
      },
      {
        "label": "Improve clarity",
        "prompt": "Improve clarity and tone of content on this school events page for families/community."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this school events page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this school events page."
      },
      {
        "label": "Draft website content",
        "prompt": "Draft website content/copy appropriate for this school events page as a School Administrator."
      },
      {
        "label": "Improve clarity",
        "prompt": "Improve clarity and tone of content on this school events page for families/community."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this school events page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this school events page."
      },
      {
        "label": "Draft website content",
        "prompt": "Draft website content/copy appropriate for this school events page as a Headteacher."
      },
      {
        "label": "Improve clarity",
        "prompt": "Improve clarity and tone of content on this school events page for families/community."
      }
    ],
    "21": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this school events page for a Talent Development: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Talent Development based on this school events page."
      },
      {
        "label": "Draft website content",
        "prompt": "Draft website content/copy appropriate for this school events page as a Talent Development."
      },
      {
        "label": "Improve clarity",
        "prompt": "Improve clarity and tone of content on this school events page for families/community."
      }
    ]
  },
  "school_initialization": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this school initialization page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this school initialization page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this school initialization page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this school initialization page."
      }
    ]
  },
  "school_settings": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this school settings page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this school settings page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this school settings page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this school settings page."
      }
    ]
  },
  "security_incidents": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this security incidents page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this security incidents page."
      },
      {
        "label": "Summarize cases",
        "prompt": "Summarize discipline/conduct issues on this security incidents page and recommend next steps for a System Administrator."
      },
      {
        "label": "Draft case note",
        "prompt": "Draft a factual case note or parent communication based on this security incidents page."
      }
    ]
  },
  "send_class_message": {
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this send class message page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this send class message page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this send class message page and Class Teacher role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this send class message page for clarity and professionalism."
      }
    ]
  },
  "send_parent_notifications": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this send parent notifications page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this send parent notifications page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this send parent notifications page and School Administrator role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this send parent notifications page for clarity and professionalism."
      }
    ],
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this send parent notifications page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this send parent notifications page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this send parent notifications page and Boarding Master/Matron role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this send parent notifications page for clarity and professionalism."
      }
    ],
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this send parent notifications page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this send parent notifications page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this send parent notifications page and Counselor/Chaplain role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this send parent notifications page for clarity and professionalism."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this send parent notifications page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this send parent notifications page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this send parent notifications page and Deputy Head - Discipline role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this send parent notifications page for clarity and professionalism."
      }
    ]
  },
  "sick_bay": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this sick bay page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this sick bay page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this sick bay page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this sick bay page."
      }
    ],
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this sick bay page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this sick bay page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this sick bay page for a Boarding Master/Matron? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this sick bay page."
      }
    ]
  },
  "sidebar_menus": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this sidebar menus page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this sidebar menus page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this sidebar menus page for a System Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this sidebar menus page."
      }
    ]
  },
  "special_needs": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this special needs page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this special needs page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this special needs page for a Director/Owner? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this special needs page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this special needs page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this special needs page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this special needs page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this special needs page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this special needs page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this special needs page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this special needs page for a Headteacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this special needs page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this special needs page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this special needs page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this special needs page for a Deputy Head - Academic? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this special needs page."
      }
    ],
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this special needs page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this special needs page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this special needs page for a Boarding Master/Matron? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this special needs page."
      }
    ],
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this special needs page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this special needs page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this special needs page for a Counselor/Chaplain? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this special needs page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this special needs page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this special needs page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this special needs page for a Deputy Head - Discipline? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this special needs page."
      }
    ]
  },
  "special_needs_students": {
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this special needs students page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this special needs students page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this special needs students page for a Class Teacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this special needs students page."
      }
    ]
  },
  "sports": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this sports page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this sports page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this sports page for a Director/Owner? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this sports page."
      }
    ],
    "21": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this sports page for a Talent Development: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Talent Development based on this sports page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this sports page for a Talent Development? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this sports page."
      }
    ]
  },
  "staff_appointments": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff appointments page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this staff appointments page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff appointments page for a Director/Owner."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff appointments page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff appointments page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this staff appointments page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff appointments page for a School Administrator."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff appointments page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff appointments page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this staff appointments page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff appointments page for a Headteacher."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff appointments page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff appointments page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this staff appointments page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff appointments page for a Deputy Head - Academic."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff appointments page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff appointments page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this staff appointments page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff appointments page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff appointments page."
      }
    ]
  },
  "staff_attendance": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff attendance page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this staff attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this staff attendance page for a Director/Owner."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this staff attendance page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff attendance page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this staff attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this staff attendance page for a School Administrator."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this staff attendance page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff attendance page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this staff attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this staff attendance page for a Headteacher."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this staff attendance page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff attendance page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this staff attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this staff attendance page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this staff attendance page."
      }
    ]
  },
  "staff_discipline": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff discipline page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this staff discipline page."
      },
      {
        "label": "Summarize cases",
        "prompt": "Summarize discipline/conduct issues on this staff discipline page and recommend next steps for a Staff."
      },
      {
        "label": "Draft case note",
        "prompt": "Draft a factual case note or parent communication based on this staff discipline page."
      }
    ]
  },
  "staff_id_cards": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff id cards page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this staff id cards page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff id cards page for a School Administrator."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff id cards page."
      }
    ]
  },
  "staff_leave": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff leave page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this staff leave page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff leave page for a Director/Owner."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff leave page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff leave page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this staff leave page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff leave page for a School Administrator."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff leave page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff leave page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this staff leave page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff leave page for a Headteacher."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff leave page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff leave page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this staff leave page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff leave page for a Deputy Head - Academic."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff leave page."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff leave page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this staff leave page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff leave page for a Class Teacher."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff leave page."
      }
    ],
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff leave page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this staff leave page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff leave page for a Subject Teacher."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff leave page."
      }
    ],
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff leave page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this staff leave page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff leave page for a Boarding Master/Matron."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff leave page."
      }
    ],
    "23": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff leave page for a Driver: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Driver based on this staff leave page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff leave page for a Driver."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff leave page."
      }
    ],
    "32": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff leave page for a Kitchen Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Kitchen Staff based on this staff leave page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff leave page for a Kitchen Staff."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff leave page."
      }
    ],
    "33": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff leave page for a Security Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Security Staff based on this staff leave page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff leave page for a Security Staff."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff leave page."
      }
    ],
    "34": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff leave page for a Janitor: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Janitor based on this staff leave page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff leave page for a Janitor."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff leave page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff leave page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this staff leave page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff leave page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff leave page."
      }
    ],
    "64": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff leave page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this staff leave page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff leave page for a Staff."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff leave page."
      }
    ]
  },
  "staff_lifecycle": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff lifecycle page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this staff lifecycle page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff lifecycle page for a Director/Owner."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff lifecycle page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff lifecycle page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this staff lifecycle page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff lifecycle page for a School Administrator."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff lifecycle page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff lifecycle page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this staff lifecycle page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff lifecycle page for a Headteacher."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff lifecycle page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff lifecycle page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this staff lifecycle page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff lifecycle page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff lifecycle page."
      }
    ]
  },
  "staff_my_admission": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff my admission page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this staff my admission page."
      },
      {
        "label": "Summarize pipeline",
        "prompt": "Summarize the admissions pipeline/status on this staff my admission page for a Staff."
      },
      {
        "label": "Draft applicant communication",
        "prompt": "Draft an applicant/parent communication based on this staff my admission page."
      }
    ]
  },
  "staff_onboarding": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff onboarding page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this staff onboarding page."
      },
      {
        "label": "Boarding summary",
        "prompt": "Summarize boarding/welfare issues on this staff onboarding page for a Director/Owner."
      },
      {
        "label": "Draft boarding log",
        "prompt": "Draft a boarding log or parent/staff note based on this staff onboarding page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff onboarding page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this staff onboarding page."
      },
      {
        "label": "Boarding summary",
        "prompt": "Summarize boarding/welfare issues on this staff onboarding page for a School Administrator."
      },
      {
        "label": "Draft boarding log",
        "prompt": "Draft a boarding log or parent/staff note based on this staff onboarding page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff onboarding page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this staff onboarding page."
      },
      {
        "label": "Boarding summary",
        "prompt": "Summarize boarding/welfare issues on this staff onboarding page for a Headteacher."
      },
      {
        "label": "Draft boarding log",
        "prompt": "Draft a boarding log or parent/staff note based on this staff onboarding page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff onboarding page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this staff onboarding page."
      },
      {
        "label": "Boarding summary",
        "prompt": "Summarize boarding/welfare issues on this staff onboarding page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft boarding log",
        "prompt": "Draft a boarding log or parent/staff note based on this staff onboarding page."
      }
    ]
  },
  "staff_performance": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff performance page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this staff performance page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff performance page for a Director/Owner."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff performance page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff performance page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this staff performance page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff performance page for a School Administrator."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff performance page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff performance page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this staff performance page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff performance page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff performance page."
      }
    ]
  },
  "staff_role_assignments": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff role assignments page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this staff role assignments page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff role assignments page for a School Administrator."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff role assignments page."
      }
    ]
  },
  "staff_schedule": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this staff schedule page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this staff schedule page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this staff schedule page for a Staff."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this staff schedule page."
      }
    ]
  },
  "statutory_remittances": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this statutory remittances page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this statutory remittances page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this statutory remittances page for a Director/Owner? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this statutory remittances page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this statutory remittances page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this statutory remittances page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this statutory remittances page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this statutory remittances page."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this statutory remittances page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this statutory remittances page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this statutory remittances page for a Accountant? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this statutory remittances page."
      }
    ]
  },
  "stock_reports": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this stock reports page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this stock reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this stock reports page for a Staff."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this stock reports page for stakeholders, tailored to a Staff."
      }
    ]
  },
  "student_behavior_notes": {
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student behavior notes page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this student behavior notes page."
      },
      {
        "label": "Summarize cases",
        "prompt": "Summarize discipline/conduct issues on this student behavior notes page and recommend next steps for a Class Teacher."
      },
      {
        "label": "Draft case note",
        "prompt": "Draft a factual case note or parent communication based on this student behavior notes page."
      }
    ]
  },
  "student_clearance": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student clearance page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this student clearance page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student clearance page for a Staff."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student clearance page."
      }
    ]
  },
  "student_counseling": {
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student counseling page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this student counseling page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student counseling page for a Headteacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student counseling page."
      }
    ],
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student counseling page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this student counseling page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student counseling page for a Counselor/Chaplain."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student counseling page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student counseling page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this student counseling page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student counseling page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student counseling page."
      }
    ]
  },
  "student_fees": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student fees page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this student fees page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this student fees page for a Director/Owner."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this student fees page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student fees page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this student fees page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this student fees page for a School Administrator."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this student fees page."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student fees page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this student fees page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this student fees page for a Accountant."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this student fees page."
      }
    ]
  },
  "student_fund_transfers": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student fund transfers page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this student fund transfers page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student fund transfers page for a Director/Owner."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student fund transfers page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student fund transfers page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this student fund transfers page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student fund transfers page for a School Administrator."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student fund transfers page."
      }
    ]
  },
  "student_growth": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student growth page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this student growth page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student growth page for a Staff."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student growth page."
      }
    ]
  },
  "student_health": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student health page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this student health page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student health page for a School Administrator."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student health page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student health page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this student health page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student health page for a Headteacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student health page."
      }
    ],
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student health page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this student health page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student health page for a Boarding Master/Matron."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student health page."
      }
    ]
  },
  "student_id_cards": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student id cards page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this student id cards page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student id cards page for a School Administrator."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student id cards page."
      }
    ]
  },
  "student_leadership": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student leadership page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this student leadership page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student leadership page for a Director/Owner."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student leadership page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student leadership page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this student leadership page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student leadership page for a School Administrator."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student leadership page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student leadership page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this student leadership page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student leadership page for a Headteacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student leadership page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student leadership page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this student leadership page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student leadership page for a Deputy Head - Academic."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student leadership page."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student leadership page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this student leadership page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student leadership page for a Class Teacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student leadership page."
      }
    ],
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student leadership page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this student leadership page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student leadership page for a Subject Teacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student leadership page."
      }
    ],
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student leadership page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this student leadership page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student leadership page for a Intern/Student Teacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student leadership page."
      }
    ],
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student leadership page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this student leadership page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student leadership page for a Counselor/Chaplain."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student leadership page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student leadership page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this student leadership page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student leadership page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student leadership page."
      }
    ]
  },
  "student_performance": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student performance page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this student performance page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student performance page for a Director/Owner."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student performance page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student performance page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this student performance page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student performance page for a Headteacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student performance page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student performance page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this student performance page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student performance page for a Deputy Head - Academic."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student performance page."
      }
    ]
  },
  "student_portfolio": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student portfolio page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this student portfolio page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student portfolio page for a School Administrator."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student portfolio page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student portfolio page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this student portfolio page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student portfolio page for a Headteacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student portfolio page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student portfolio page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this student portfolio page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student portfolio page for a Deputy Head - Academic."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student portfolio page."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student portfolio page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this student portfolio page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student portfolio page for a Class Teacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student portfolio page."
      }
    ],
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student portfolio page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this student portfolio page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student portfolio page for a Subject Teacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student portfolio page."
      }
    ]
  },
  "student_profiles": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student profiles page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this student profiles page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student profiles page for a School Administrator."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student profiles page."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student profiles page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this student profiles page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student profiles page for a Class Teacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student profiles page."
      }
    ],
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student profiles page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this student profiles page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student profiles page for a Boarding Master/Matron."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student profiles page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student profiles page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this student profiles page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student profiles page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student profiles page."
      }
    ]
  },
  "student_progress_reports": {
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student progress reports page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this student progress reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this student progress reports page for a Class Teacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this student progress reports page for stakeholders, tailored to a Class Teacher."
      }
    ]
  },
  "student_promotion": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student promotion page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this student promotion page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student promotion page for a School Administrator."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student promotion page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student promotion page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this student promotion page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student promotion page for a Deputy Head - Academic."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student promotion page."
      }
    ]
  },
  "student_route_assignment": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student route assignment page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this student route assignment page."
      },
      {
        "label": "Transport summary",
        "prompt": "Summarize transport operations/issues on this student route assignment page for a School Administrator."
      },
      {
        "label": "Draft transport notice",
        "prompt": "Draft a transport notice or incident note based on this student route assignment page."
      }
    ]
  },
  "student_subject_performance": {
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student subject performance page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this student subject performance page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student subject performance page for a Subject Teacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student subject performance page."
      }
    ]
  },
  "student_timeline": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student timeline page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this student timeline page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student timeline page for a Staff."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student timeline page."
      }
    ]
  },
  "student_welfare": {
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student welfare page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this student welfare page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student welfare page for a Counselor/Chaplain."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student welfare page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this student welfare page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this student welfare page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this student welfare page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this student welfare page."
      }
    ]
  },
  "students_by_class": {
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this students by class page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this students by class page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this students by class page for a Subject Teacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this students by class page."
      }
    ]
  },
  "students_oversview": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this students oversview page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this students oversview page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this students oversview page for a Staff."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this students oversview page."
      }
    ]
  },
  "students_overview": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this students overview page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this students overview page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this students overview page for a Director/Owner."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this students overview page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this students overview page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this students overview page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this students overview page for a Headteacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this students overview page."
      }
    ]
  },
  "students_with_balance": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this students with balance page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this students with balance page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this students with balance page for a School Administrator."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this students with balance page."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this students with balance page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this students with balance page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this students with balance page for a Accountant."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this students with balance page."
      }
    ]
  },
  "subject_class_comparison": {
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this subject class comparison page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this subject class comparison page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this subject class comparison page for a Subject Teacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this subject class comparison page."
      }
    ]
  },
  "subject_exam_schedule": {
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this subject exam schedule page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this subject exam schedule page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this subject exam schedule page for a Subject Teacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this subject exam schedule page."
      }
    ]
  },
  "subject_grade_entry": {
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this subject grade entry page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this subject grade entry page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this subject grade entry page for a Subject Teacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this subject grade entry page."
      }
    ]
  },
  "subject_grading_status": {
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this subject grading status page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this subject grading status page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this subject grading status page for a Subject Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this subject grading status page."
      }
    ]
  },
  "subject_results_summary": {
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this subject results summary page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this subject results summary page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this subject results summary page for a Subject Teacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this subject results summary page for stakeholders, tailored to a Subject Teacher."
      }
    ]
  },
  "subject_schemes_of_work": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this subject schemes of work page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this subject schemes of work page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this subject schemes of work page for a Staff."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this subject schemes of work page."
      }
    ]
  },
  "subject_students_list": {
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this subject students list page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this subject students list page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this subject students list page for a Subject Teacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this subject students list page."
      }
    ]
  },
  "supervision_roster": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this supervision roster page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this supervision roster page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this supervision roster page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this supervision roster page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this supervision roster page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this supervision roster page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this supervision roster page for a Headteacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this supervision roster page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this supervision roster page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this supervision roster page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this supervision roster page for a Deputy Head - Academic? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this supervision roster page."
      }
    ]
  },
  "supplier_payments": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this supplier payments page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this supplier payments page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this supplier payments page for a Director/Owner."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this supplier payments page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this supplier payments page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this supplier payments page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this supplier payments page for a School Administrator."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this supplier payments page."
      }
    ]
  },
  "suspensions_expulsions": {
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this suspensions expulsions page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this suspensions expulsions page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this suspensions expulsions page for a Deputy Head - Discipline? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this suspensions expulsions page."
      }
    ]
  },
  "system_diagnostics": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this system diagnostics page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this system diagnostics page."
      },
      {
        "label": "Security/ops check",
        "prompt": "Identify security, operational, or configuration risks on this system diagnostics page for a System Administrator."
      },
      {
        "label": "Draft ops note",
        "prompt": "Draft an operations/security note based on this system diagnostics page."
      }
    ]
  },
  "system_health": {
    "2": [
      {
        "label": "Health briefing",
        "prompt": "Write a system-health briefing: overall status, active alerts, and recommended actions."
      },
      {
        "label": "Explain this alert",
        "prompt": "Explain this system-health alert in plain language: likely cause and first checks."
      },
      {
        "label": "Draft incident note",
        "prompt": "Draft a short incident note for the ops log: what happened, impact, actions taken."
      },
      {
        "label": "Trend check",
        "prompt": "What do the health metrics on this page suggest about system stability this week?"
      }
    ]
  },
  "system_settings": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this system settings page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this system settings page."
      },
      {
        "label": "Security/ops check",
        "prompt": "Identify security, operational, or configuration risks on this system settings page for a System Administrator."
      },
      {
        "label": "Draft ops note",
        "prompt": "Draft an operations/security note based on this system settings page."
      }
    ]
  },
  "teacher_performance_reviews": {
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this teacher performance reviews page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this teacher performance reviews page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this teacher performance reviews page for a Headteacher."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this teacher performance reviews page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this teacher performance reviews page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this teacher performance reviews page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this teacher performance reviews page for a Deputy Head - Academic."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this teacher performance reviews page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this teacher performance reviews page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this teacher performance reviews page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this teacher performance reviews page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this teacher performance reviews page."
      }
    ]
  },
  "teacher_subject_assignment": {
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this teacher subject assignment page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this teacher subject assignment page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this teacher subject assignment page for a Deputy Head - Academic."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this teacher subject assignment page."
      }
    ]
  },
  "teacher_timetables": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this teacher timetables page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this teacher timetables page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this teacher timetables page for a Staff."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this teacher timetables page."
      }
    ]
  },
  "teacher_workload": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this teacher workload page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this teacher workload page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this teacher workload page for a Director/Owner."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this teacher workload page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this teacher workload page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this teacher workload page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this teacher workload page for a School Administrator."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this teacher workload page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this teacher workload page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this teacher workload page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this teacher workload page for a Headteacher."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this teacher workload page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this teacher workload page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this teacher workload page."
      },
      {
        "label": "Staff summary",
        "prompt": "Summarize staff/HR issues on this teacher workload page for a Deputy Head - Academic."
      },
      {
        "label": "Draft staff communication",
        "prompt": "Draft a professional staff/HR note based on this teacher workload page."
      }
    ]
  },
  "teaching_materials": {
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this teaching materials page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this teaching materials page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this teaching materials page for a Subject Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this teaching materials page."
      }
    ]
  },
  "term_dates": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this term dates page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this term dates page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this term dates page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this term dates page."
      }
    ]
  },
  "term_reports": {
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this term reports page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this term reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this term reports page for a Headteacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this term reports page for stakeholders, tailored to a Headteacher."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this term reports page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this term reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this term reports page for a Deputy Head - Academic."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this term reports page for stakeholders, tailored to a Deputy Head - Academic."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this term reports page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this term reports page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this term reports page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this term reports page for stakeholders, tailored to a Deputy Head - Discipline."
      }
    ]
  },
  "term_transition": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this term transition page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this term transition page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this term transition page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this term transition page."
      }
    ]
  },
  "time_bound_access": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this time bound access page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this time bound access page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this time bound access page for a System Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this time bound access page."
      }
    ]
  },
  "timetable": {
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this timetable page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this timetable page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this timetable page for a Deputy Head - Academic? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this timetable page."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this timetable page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this timetable page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this timetable page for a Class Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this timetable page."
      }
    ],
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this timetable page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this timetable page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this timetable page for a Subject Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this timetable page."
      }
    ],
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this timetable page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this timetable page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this timetable page for a Intern/Student Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this timetable page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this timetable page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this timetable page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this timetable page for a Deputy Head - Discipline? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this timetable page."
      }
    ],
    "64": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this timetable page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this timetable page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this timetable page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this timetable page."
      }
    ]
  },
  "today_absentees": {
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this today absentees page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this today absentees page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this today absentees page for a Class Teacher."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this today absentees page."
      }
    ]
  },
  "todays_menu": {
    "16": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this todays menu page for a Catering Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Catering Manager based on this todays menu page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this todays menu page for a Catering Manager? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this todays menu page."
      }
    ]
  },
  "token_management": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this token management page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this token management page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this token management page for a System Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this token management page."
      }
    ]
  },
  "transaction_approvals": {
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this transaction approvals page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this transaction approvals page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this transaction approvals page for a Accountant? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this transaction approvals page."
      }
    ]
  },
  "transport_fees": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this transport fees page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this transport fees page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this transport fees page for a Director/Owner."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this transport fees page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this transport fees page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this transport fees page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this transport fees page for a School Administrator."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this transport fees page."
      }
    ]
  },
  "transport_passengers": {
    "23": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this transport passengers page for a Driver: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Driver based on this transport passengers page."
      },
      {
        "label": "Transport summary",
        "prompt": "Summarize transport operations/issues on this transport passengers page for a Driver."
      },
      {
        "label": "Draft transport notice",
        "prompt": "Draft a transport notice or incident note based on this transport passengers page."
      }
    ]
  },
  "truancy_cases": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this truancy cases page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this truancy cases page."
      },
      {
        "label": "Summarize cases",
        "prompt": "Summarize discipline/conduct issues on this truancy cases page and recommend next steps for a Staff."
      },
      {
        "label": "Draft case note",
        "prompt": "Draft a factual case note or parent communication based on this truancy cases page."
      }
    ]
  },
  "uniform_catalog": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this uniform catalog page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this uniform catalog page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this uniform catalog page for a Staff."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this uniform catalog page."
      }
    ]
  },
  "uniform_discounts": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this uniform discounts page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this uniform discounts page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this uniform discounts page for a Director/Owner? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this uniform discounts page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this uniform discounts page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this uniform discounts page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this uniform discounts page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this uniform discounts page."
      }
    ],
    "14": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this uniform discounts page for a Inventory Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Inventory Manager based on this uniform discounts page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this uniform discounts page for a Inventory Manager? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this uniform discounts page."
      }
    ]
  },
  "uniform_point_of_sale": {
    "14": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this uniform point of sale page for a Inventory Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Inventory Manager based on this uniform point of sale page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this uniform point of sale page for a Inventory Manager? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this uniform point of sale page."
      }
    ]
  },
  "uniform_sales_records": {
    "14": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this uniform sales records page for a Inventory Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Inventory Manager based on this uniform sales records page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this uniform sales records page for a Inventory Manager? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this uniform sales records page."
      }
    ]
  },
  "uniform_stock_intake": {
    "14": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this uniform stock intake page for a Inventory Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Inventory Manager based on this uniform stock intake page."
      },
      {
        "label": "Stock/action summary",
        "prompt": "Summarize stock, asset, or procurement actions needed on this uniform stock intake page for a Inventory Manager."
      },
      {
        "label": "Draft requisition note",
        "prompt": "Draft a requisition/procurement note based on this uniform stock intake page."
      }
    ]
  },
  "uniform_unit_tracking": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this uniform unit tracking page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this uniform unit tracking page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this uniform unit tracking page for a Director/Owner? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this uniform unit tracking page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this uniform unit tracking page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this uniform unit tracking page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this uniform unit tracking page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this uniform unit tracking page."
      }
    ],
    "14": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this uniform unit tracking page for a Inventory Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Inventory Manager based on this uniform unit tracking page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this uniform unit tracking page for a Inventory Manager? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this uniform unit tracking page."
      }
    ]
  },
  "unmatched_payments": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this unmatched payments page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this unmatched payments page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this unmatched payments page for a Director/Owner."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this unmatched payments page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this unmatched payments page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this unmatched payments page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this unmatched payments page for a School Administrator."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this unmatched payments page."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this unmatched payments page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this unmatched payments page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this unmatched payments page for a Accountant."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this unmatched payments page."
      }
    ]
  },
  "upload_teaching_resource": {
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this upload teaching resource page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this upload teaching resource page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this upload teaching resource page for a Subject Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this upload teaching resource page."
      }
    ]
  },
  "vaccination_records": {
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this vaccination records page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this vaccination records page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this vaccination records page for a Boarding Master/Matron? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this vaccination records page."
      }
    ]
  },
  "vendor_invoices": {
    "14": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this vendor invoices page for a Inventory Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Inventory Manager based on this vendor invoices page."
      },
      {
        "label": "Explain financial state",
        "prompt": "Explain the financial state, variances, or risks visible on this vendor invoices page for a Inventory Manager."
      },
      {
        "label": "Draft finance note",
        "prompt": "Draft a finance follow-up/reconciliation note based on this vendor invoices page."
      }
    ]
  },
  "vendors": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this vendors page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this vendors page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this vendors page for a Director/Owner? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this vendors page."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this vendors page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this vendors page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this vendors page for a Accountant? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this vendors page."
      }
    ],
    "14": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this vendors page for a Inventory Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Inventory Manager based on this vendors page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this vendors page for a Inventory Manager? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this vendors page."
      }
    ],
    "16": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this vendors page for a Catering Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Catering Manager based on this vendors page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this vendors page for a Catering Manager? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this vendors page."
      }
    ],
    "71": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this vendors page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this vendors page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this vendors page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this vendors page."
      }
    ]
  },
  "view_attendance": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this view attendance page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this view attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this view attendance page for a Director/Owner."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this view attendance page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this view attendance page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this view attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this view attendance page for a School Administrator."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this view attendance page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this view attendance page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this view attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this view attendance page for a Headteacher."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this view attendance page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this view attendance page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this view attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this view attendance page for a Deputy Head - Academic."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this view attendance page."
      }
    ],
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this view attendance page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this view attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this view attendance page for a Subject Teacher."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this view attendance page."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this view attendance page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this view attendance page."
      },
      {
        "label": "Spot attendance risks",
        "prompt": "Identify attendance risks and learners needing follow-up on this view attendance page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft parent/staff follow-up",
        "prompt": "Draft a follow-up message about attendance concerns from this view attendance page."
      }
    ]
  },
  "view_calendar": {
    "21": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this view calendar page for a Talent Development: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Talent Development based on this view calendar page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this view calendar page for a Talent Development? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this view calendar page."
      }
    ]
  },
  "view_class_lists": {
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this view class lists page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this view class lists page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this view class lists page for a Intern/Student Teacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this view class lists page."
      }
    ]
  },
  "view_past_papers": {
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this view past papers page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this view past papers page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this view past papers page for a Intern/Student Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this view past papers page."
      }
    ]
  },
  "view_results": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this view results page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this view results page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this view results page for a Director/Owner."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this view results page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this view results page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this view results page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this view results page for a School Administrator."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this view results page."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this view results page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this view results page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this view results page for a Headteacher."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this view results page."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this view results page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this view results page."
      },
      {
        "label": "Academic next steps",
        "prompt": "Summarize academic status and next steps on this view results page for a Deputy Head - Academic."
      },
      {
        "label": "Draft academic feedback",
        "prompt": "Draft academic feedback, plan, or comment based on this view results page."
      }
    ]
  },
  "view_student_info": {
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this view student info page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this view student info page."
      },
      {
        "label": "Learner summary",
        "prompt": "Summarize learner/class information on this view student info page for a Intern/Student Teacher."
      },
      {
        "label": "Draft learner follow-up",
        "prompt": "Draft a learner or parent follow-up note based on this view student info page."
      }
    ]
  },
  "view_syllabus": {
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this view syllabus page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this view syllabus page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this view syllabus page for a Intern/Student Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this view syllabus page."
      }
    ]
  },
  "view_teaching_materials": {
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this view teaching materials page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this view teaching materials page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this view teaching materials page for a Intern/Student Teacher? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this view teaching materials page."
      }
    ]
  },
  "webhook_registry": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this webhook registry page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this webhook registry page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this webhook registry page for a System Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this webhook registry page."
      }
    ]
  },
  "weekly_discipline_report": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this weekly discipline report page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this weekly discipline report page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this weekly discipline report page for a Staff."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this weekly discipline report page for stakeholders, tailored to a Staff."
      }
    ]
  },
  "weekly_menu": {
    "16": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this weekly menu page for a Catering Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Catering Manager based on this weekly menu page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this weekly menu page for a Catering Manager? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this weekly menu page."
      }
    ]
  },
  "welfare_follow_ups": {
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this welfare follow ups page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this welfare follow ups page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this welfare follow ups page for a Counselor/Chaplain? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this welfare follow ups page."
      }
    ]
  },
  "welfare_records": {
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this welfare records page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this welfare records page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this welfare records page for a Counselor/Chaplain? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this welfare records page."
      }
    ]
  },
  "welfare_summary": {
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this welfare summary page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this welfare summary page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this welfare summary page for a Counselor/Chaplain."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this welfare summary page for stakeholders, tailored to a Counselor/Chaplain."
      }
    ]
  },
  "widget_registry": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this widget registry page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this widget registry page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this widget registry page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this widget registry page."
      }
    ]
  },
  "year_calendar": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this year calendar page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this year calendar page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this year calendar page for a Director/Owner? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this year calendar page."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this year calendar page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this year calendar page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this year calendar page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this year calendar page."
      }
    ]
  },
  "year_history": {
    "*": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this year history page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this year history page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this year history page for a Staff? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this year history page."
      }
    ]
  },
  "year_rollover": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this year rollover page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this year rollover page."
      },
      {
        "label": "What needs attention",
        "prompt": "What needs attention on this year rollover page for a School Administrator? List top items."
      },
      {
        "label": "Draft update",
        "prompt": "Draft a short update or request based on this year rollover page."
      }
    ]
  },
  "catering_manager_cook_lead_dashboard": {
    "16": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this catering manager cook lead dashboard page for a Catering Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Catering Manager based on this catering manager cook lead dashboard page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this catering manager cook lead dashboard page for a Catering Manager."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this catering manager cook lead dashboard page for stakeholders, tailored to a Catering Manager."
      }
    ]
  },
  "class_teacher_dashboard": {
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this class teacher dashboard page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this class teacher dashboard page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this class teacher dashboard page for a Class Teacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this class teacher dashboard page for stakeholders, tailored to a Class Teacher."
      }
    ]
  },
  "communications/messages_inbox": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this communications/messages inbox page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this communications/messages inbox page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this communications/messages inbox page and System Administrator role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this communications/messages inbox page for clarity and professionalism."
      }
    ],
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this communications/messages inbox page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this communications/messages inbox page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this communications/messages inbox page and Director/Owner role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this communications/messages inbox page for clarity and professionalism."
      }
    ],
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this communications/messages inbox page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this communications/messages inbox page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this communications/messages inbox page and School Administrator role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this communications/messages inbox page for clarity and professionalism."
      }
    ],
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this communications/messages inbox page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this communications/messages inbox page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this communications/messages inbox page and Headteacher role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this communications/messages inbox page for clarity and professionalism."
      }
    ],
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this communications/messages inbox page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this communications/messages inbox page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this communications/messages inbox page and Deputy Head - Academic role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this communications/messages inbox page for clarity and professionalism."
      }
    ],
    "7": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this communications/messages inbox page for a Class Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Class Teacher based on this communications/messages inbox page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this communications/messages inbox page and Class Teacher role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this communications/messages inbox page for clarity and professionalism."
      }
    ],
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this communications/messages inbox page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this communications/messages inbox page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this communications/messages inbox page and Subject Teacher role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this communications/messages inbox page for clarity and professionalism."
      }
    ],
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this communications/messages inbox page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this communications/messages inbox page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this communications/messages inbox page and Intern/Student Teacher role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this communications/messages inbox page for clarity and professionalism."
      }
    ],
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this communications/messages inbox page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this communications/messages inbox page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this communications/messages inbox page and Accountant role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this communications/messages inbox page for clarity and professionalism."
      }
    ],
    "14": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this communications/messages inbox page for a Inventory Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Inventory Manager based on this communications/messages inbox page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this communications/messages inbox page and Inventory Manager role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this communications/messages inbox page for clarity and professionalism."
      }
    ],
    "16": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this communications/messages inbox page for a Catering Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Catering Manager based on this communications/messages inbox page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this communications/messages inbox page and Catering Manager role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this communications/messages inbox page for clarity and professionalism."
      }
    ],
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this communications/messages inbox page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this communications/messages inbox page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this communications/messages inbox page and Boarding Master/Matron role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this communications/messages inbox page for clarity and professionalism."
      }
    ],
    "21": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this communications/messages inbox page for a Talent Development: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Talent Development based on this communications/messages inbox page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this communications/messages inbox page and Talent Development role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this communications/messages inbox page for clarity and professionalism."
      }
    ],
    "23": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this communications/messages inbox page for a Driver: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Driver based on this communications/messages inbox page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this communications/messages inbox page and Driver role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this communications/messages inbox page for clarity and professionalism."
      }
    ],
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this communications/messages inbox page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this communications/messages inbox page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this communications/messages inbox page and Counselor/Chaplain role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this communications/messages inbox page for clarity and professionalism."
      }
    ],
    "32": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this communications/messages inbox page for a Kitchen Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Kitchen Staff based on this communications/messages inbox page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this communications/messages inbox page and Kitchen Staff role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this communications/messages inbox page for clarity and professionalism."
      }
    ],
    "33": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this communications/messages inbox page for a Security Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Security Staff based on this communications/messages inbox page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this communications/messages inbox page and Security Staff role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this communications/messages inbox page for clarity and professionalism."
      }
    ],
    "34": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this communications/messages inbox page for a Janitor: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Janitor based on this communications/messages inbox page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this communications/messages inbox page and Janitor role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this communications/messages inbox page for clarity and professionalism."
      }
    ],
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this communications/messages inbox page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this communications/messages inbox page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this communications/messages inbox page and Deputy Head - Discipline role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this communications/messages inbox page for clarity and professionalism."
      }
    ],
    "64": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this communications/messages inbox page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this communications/messages inbox page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this communications/messages inbox page and Staff role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this communications/messages inbox page for clarity and professionalism."
      }
    ],
    "71": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this communications/messages inbox page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this communications/messages inbox page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this communications/messages inbox page and Staff role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this communications/messages inbox page for clarity and professionalism."
      }
    ],
    "72": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this communications/messages inbox page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this communications/messages inbox page."
      },
      {
        "label": "Draft message",
        "prompt": "Draft a clear message/announcement appropriate for this communications/messages inbox page and Staff role."
      },
      {
        "label": "Improve tone",
        "prompt": "Rewrite or improve the communication draft on this communications/messages inbox page for clarity and professionalism."
      }
    ]
  },
  "deputy_head_academic_dashboard": {
    "6": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this deputy head academic dashboard page for a Deputy Head - Academic: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Academic based on this deputy head academic dashboard page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this deputy head academic dashboard page for a Deputy Head - Academic."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this deputy head academic dashboard page for stakeholders, tailored to a Deputy Head - Academic."
      }
    ]
  },
  "deputy_head_discipline_dashboard": {
    "63": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this deputy head discipline dashboard page for a Deputy Head - Discipline: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Deputy Head - Discipline based on this deputy head discipline dashboard page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this deputy head discipline dashboard page for a Deputy Head - Discipline."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this deputy head discipline dashboard page for stakeholders, tailored to a Deputy Head - Discipline."
      }
    ]
  },
  "director_owner_dashboard": {
    "3": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this director owner dashboard page for a Director/Owner: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Director/Owner based on this director owner dashboard page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this director owner dashboard page for a Director/Owner."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this director owner dashboard page for stakeholders, tailored to a Director/Owner."
      }
    ]
  },
  "driver_dashboard": {
    "23": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this driver dashboard page for a Driver: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Driver based on this driver dashboard page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this driver dashboard page for a Driver."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this driver dashboard page for stakeholders, tailored to a Driver."
      }
    ]
  },
  "food_store_manager_dashboard": {
    "71": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this food store manager dashboard page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this food store manager dashboard page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this food store manager dashboard page for a Staff."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this food store manager dashboard page for stakeholders, tailored to a Staff."
      }
    ]
  },
  "headteacher_dashboard": {
    "5": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this headteacher dashboard page for a Headteacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Headteacher based on this headteacher dashboard page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this headteacher dashboard page for a Headteacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this headteacher dashboard page for stakeholders, tailored to a Headteacher."
      }
    ]
  },
  "hod_talent_development_dashboard": {
    "21": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this hod talent development dashboard page for a Talent Development: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Talent Development based on this hod talent development dashboard page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this hod talent development dashboard page for a Talent Development."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this hod talent development dashboard page for stakeholders, tailored to a Talent Development."
      }
    ]
  },
  "intern_student_teacher_dashboard": {
    "9": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this intern student teacher dashboard page for a Intern/Student Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Intern/Student Teacher based on this intern student teacher dashboard page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this intern student teacher dashboard page for a Intern/Student Teacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this intern student teacher dashboard page for stakeholders, tailored to a Intern/Student Teacher."
      }
    ]
  },
  "librarian_dashboard": {
    "72": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this librarian dashboard page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this librarian dashboard page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this librarian dashboard page for a Staff."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this librarian dashboard page for stakeholders, tailored to a Staff."
      }
    ]
  },
  "matron_housemother_dashboard": {
    "18": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this matron housemother dashboard page for a Boarding Master/Matron: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Boarding Master/Matron based on this matron housemother dashboard page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this matron housemother dashboard page for a Boarding Master/Matron."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this matron housemother dashboard page for stakeholders, tailored to a Boarding Master/Matron."
      }
    ]
  },
  "school_accountant_dashboard": {
    "10": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this school accountant dashboard page for a Accountant: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Accountant based on this school accountant dashboard page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this school accountant dashboard page for a Accountant."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this school accountant dashboard page for stakeholders, tailored to a Accountant."
      }
    ]
  },
  "school_administrative_officer_dashboard": {
    "4": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this school administrative officer dashboard page for a School Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a School Administrator based on this school administrative officer dashboard page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this school administrative officer dashboard page for a School Administrator."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this school administrative officer dashboard page for stakeholders, tailored to a School Administrator."
      }
    ]
  },
  "school_counselor_chaplain_dashboard": {
    "24": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this school counselor chaplain dashboard page for a Counselor/Chaplain: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Counselor/Chaplain based on this school counselor chaplain dashboard page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this school counselor chaplain dashboard page for a Counselor/Chaplain."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this school counselor chaplain dashboard page for stakeholders, tailored to a Counselor/Chaplain."
      }
    ]
  },
  "store_manager_dashboard": {
    "14": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this store manager dashboard page for a Inventory Manager: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Inventory Manager based on this store manager dashboard page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this store manager dashboard page for a Inventory Manager."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this store manager dashboard page for stakeholders, tailored to a Inventory Manager."
      }
    ]
  },
  "subject_teacher_dashboard": {
    "8": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this subject teacher dashboard page for a Subject Teacher: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Subject Teacher based on this subject teacher dashboard page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this subject teacher dashboard page for a Subject Teacher."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this subject teacher dashboard page for stakeholders, tailored to a Subject Teacher."
      }
    ]
  },
  "support_staff_dashboard": {
    "32": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this support staff dashboard page for a Kitchen Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Kitchen Staff based on this support staff dashboard page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this support staff dashboard page for a Kitchen Staff."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this support staff dashboard page for stakeholders, tailored to a Kitchen Staff."
      }
    ],
    "33": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this support staff dashboard page for a Security Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Security Staff based on this support staff dashboard page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this support staff dashboard page for a Security Staff."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this support staff dashboard page for stakeholders, tailored to a Security Staff."
      }
    ],
    "34": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this support staff dashboard page for a Janitor: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Janitor based on this support staff dashboard page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this support staff dashboard page for a Janitor."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this support staff dashboard page for stakeholders, tailored to a Janitor."
      }
    ],
    "64": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this support staff dashboard page for a Staff: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a Staff based on this support staff dashboard page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this support staff dashboard page for a Staff."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this support staff dashboard page for stakeholders, tailored to a Staff."
      }
    ]
  },
  "system_administrator_dashboard": {
    "2": [
      {
        "label": "Summarize this page",
        "prompt": "Summarize this system administrator dashboard page for a System Administrator: what matters now, key risks, and next actions."
      },
      {
        "label": "Draft action note",
        "prompt": "Draft a short professional action note for a System Administrator based on this system administrator dashboard page."
      },
      {
        "label": "Explain key metrics",
        "prompt": "Explain the key metrics and exceptions on this system administrator dashboard page for a System Administrator."
      },
      {
        "label": "Draft briefing",
        "prompt": "Draft a briefing from this system administrator dashboard page for stakeholders, tailored to a System Administrator."
      }
    ]
  },
  "communications": {
    "4": [
      {
        "label": "Staff circular",
        "prompt": "Draft a staff circular with clear sections: purpose, actions required, deadline."
      }
    ],
    "5": [
      {
        "label": "Official announcement",
        "prompt": "Draft an official headteacher announcement with appropriate tone and formality."
      }
    ],
    "7": [
      {
        "label": "Class parent note",
        "prompt": "Draft a friendly note to parents of my class about an upcoming activity or request."
      }
    ],
    "8": [
      {
        "label": "Subject update",
        "prompt": "Draft a short update to parents about my subject this week."
      }
    ],
    "*": [
      {
        "label": "Draft a message",
        "prompt": "Draft a clear message to parents/staff about this: what, when, what to do."
      },
      {
        "label": "Shorten it",
        "prompt": "Rewrite this message shorter and more direct, keeping the key facts."
      }
    ]
  },
  "*": {
    "*": [
      {
        "label": "Explain this page",
        "prompt": "Explain what this page shows and the most important things to check."
      },
      {
        "label": "Draft a note",
        "prompt": "Help me draft a short professional note based on what is on this page."
      },
      {
        "label": "Summarise for my role",
        "prompt": "Summarise what on this page matters most for my role, and what action to take."
      }
    ]
  }
};
