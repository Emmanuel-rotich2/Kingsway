# MVP Module Order

Run MVP completion prompts in this order. Do not skip foundation prompts unless the skipped item has already passed the Definition of Done in this repository.

| Order | Module | Priority | MVP outcome |
|---:|---|---|---|
| 0 | AI-DOS Bootstrap and Safety Rails | Critical | Operating rules, templates, and standards installed in `docs/AI_DOS/` |
| 1 | Foundation/Auth/RBAC | Critical | Login/session, route guards, permission resolution, sidebar/page/API enforcement |
| 2 | Dashboard/Layout/Navigation | Critical | Shared shell, dashboard registry, role dashboards, no blank pages |
| 3 | Students | Critical | Student records, guardianship, class assignment, real CRUD/state |
| 4 | Admissions | Critical | Online/physical/nursery intake through approval, class placement, fee handoff |
| 5 | Academics/CBC/Assessments | Critical | Classes, subjects, assessment capture, results workflow |
| 6 | Attendance | High | Student/staff attendance capture, review, reports |
| 7 | Finance/Fees/Payments | Critical | Fee structures, invoices, payments, reconciliation, approvals |
| 8 | Staff/HR/Payroll | High | Staff records, appointments, payroll preparation/approval/payment |
| 9 | Transport | Medium | Routes, vehicles, assignments, transport billing where applicable |
| 10 | Boarding | Medium | Dorms, bed allocation, boarding workflow |
| 11 | Communications | Medium | Notices, messaging, templates, delivery/audit state |
| 12 | Discipline/Counseling/Health | Medium | Incidents, counseling, health records with privacy-aware RBAC |
| 13 | Inventory/Procurement/Assets | Medium | Stock, procurement, assets, issue/return workflows |
| 14 | Library | Low | Books, members, borrowing/return/fines if applicable |
| 15 | Reports/Audit/Exports | High | Role-scoped reports, audit review, safe exports |
| 16 | Public Website/Admin Content | Low | DB-backed public content, admin management, public auth standards |
| 17 | Import/Migration/Data Quality | Medium | Safe imports, validation, duplicate handling, rollback notes |
| 18 | QA/Release Hardening | Critical | Regression checks, smoke tests, deployment checklist |

## Ordering rules

- Complete one vertical slice at a time.
- Commit each completed module separately when commits are requested.
- Do not work on lower-priority modules to avoid unresolved foundation blockers.
- If a later module exposes a foundation bug, repair the foundation bug first and document the dependency.
- Each module must pass `docs/AI_DOS/DEFINITION_OF_DONE.md` before being treated as complete.

## Prompt handoff requirements

Every module prompt must include or derive:

- canonical files;
- database tables;
- permissions;
- API endpoints;
- frontend page and JS controller;
- audit requirements;
- manual tests.
