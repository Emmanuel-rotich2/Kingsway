# Senior System Analyst Codebase Brief

# Kingsway AI Development Operating System (AI-DOS)

Generated from the uploaded Kingsway codebase.

## Executive finding

Kingsway is not a small web app anymore. It is an ERP-style school management platform with a PHP REST API, vanilla JavaScript frontend, role dashboards, database-driven navigation, and many partially connected workflows.

The codebase is recoverable, but implementation must be controlled by a strict operating system. Random prompts like "fix finance" or "complete admissions" will keep wasting tokens because the model will jump between pages, invent missing pieces, or patch symptoms instead of finishing full workflows.

## Codebase scale discovered

| Area | Count |
|---|---:|
| PHP files | 643 |
| JavaScript files | 271 |
| PHP UI pages detected | 347 |
| JS page controllers | 227 |
| API controllers | 37 |
| API module/business files | 131 |
| Database tables detected | 358 |
| Pages without matching JS controller | 175 |
| JS controllers without matching page | 55 |
| Unique sidebar URLs | 250 |
| Sidebar URLs without matching page | 22 |

## Main diagnosis

1. The system has too many pages for its current level of integration.
2. UI, JS, API, permissions, and database workflows are not consistently connected.
3. There are many placeholders, fallback flows, mock/dummy references, and incomplete modules.
4. RBAC exists, but it needs to become the single source of truth for pages, API, navigation, and action-level controls.
5. The system needs MVP completion by workflow, not by isolated pages.


## Priority implementation order

1. **Foundation/Auth/RBAC** — Critical
2. **Dashboard/Layout/Navigation** — Critical
3. **Students** — Critical
4. **Admissions** — Critical
5. **Academics/CBC/Assessments** — Critical
6. **Attendance** — High
7. **Finance/Fees/Payments** — Critical
8. **Staff/HR/Payroll** — High
9. **Transport** — Medium
10. **Boarding** — Medium
11. **Communications** — Medium
12. **Discipline/Counseling/Health** — Medium
13. **Inventory/Procurement/Assets** — Medium
14. **Library** — Low
15. **Reports/Audit** — High
16. **Public Website** — Low
17. **Import/Migration** — Medium

## Key evidence files

- `matrices/page_js_api_matrix.csv`
- `matrices/MVP_MODULE_COMPLETION_MATRIX.md`
- `specs/SYSTEM_CONSTITUTION.md`
- `specs/ARCHITECTURE_RECOVERY_PLAN.md`
- `prompts/*.md`
