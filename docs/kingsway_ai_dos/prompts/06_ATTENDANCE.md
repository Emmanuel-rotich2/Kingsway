# CODEX IMPLEMENTATION PROMPT — Attendance Module

You are a Senior Enterprise Software Engineer and System Rescue Architect working inside the Kingsway School Management System codebase.

You are not here to produce generic advice. You are here to complete production-grade MVP implementation.

Read and obey:
- AI_DOS_README.md
- specs/SYSTEM_CONSTITUTION.md
- specs/ARCHITECTURE_RECOVERY_PLAN.md
- matrices/MVP_MODULE_COMPLETION_MATRIX.md
- AGENTS.md
- CLAUDE.md

Execution rules:
1. Work as an implementer, not an endless auditor.
2. Inspect only the files needed for this module and its dependencies.
3. Do not redesign unrelated UI.
4. Do not create duplicate pages/controllers/modules if existing ones can be completed.
5. Do not use mock, dummy, placeholder, or fallback data as real production data.
6. Do not bypass auth, RBAC, router, API client, or shared layouts.
7. Every change must preserve existing users and workflows unless clearly broken.
8. Server-side permission enforcement is mandatory.
9. Frontend permission hiding is mandatory but not sufficient.
10. Document exactly what changed.

Required working method:
A. Identify canonical files for the module.
B. Identify duplicate/dead/placeholder files.
C. Identify required database tables and existing schema.
D. Identify required permissions.
E. Implement backend API first.
F. Implement/repair frontend page and JS controller.
G. Implement role-aware action visibility.
H. Implement loading/empty/error/forbidden states.
I. Add/repair audit logging for sensitive actions.
J. Add manual test checklist.
K. Run syntax checks where possible.
L. Search for regressions and missing references before finishing.

Response at end must include:
- files changed
- workflows completed
- permissions enforced
- APIs completed
- remaining risks
- exact manual tests to run


## Module objective

Complete student attendance MVP with daily class attendance, history, summaries, late arrivals, and role-scoped visibility.

## Primary files and areas to inspect first

Inspect:
- pages/*attendance*.php
- pages/daily_attendance.php
- pages/class_attendance_history.php
- pages/late_arrivals.php
- js/pages/*attendance*.js
- api/controllers/AttendanceController.php
- api/modules/attendance/
- attendance database tables


## MVP workflows that must work

MVP workflows:
1. Class teacher opens assigned class attendance.
2. Teacher marks present/absent/late.
3. System prevents duplicate attendance for same session/day where required.
4. Admin/DH can view attendance summaries.
5. Parent can view own child attendance.
6. Reports show real data.


## Implementation requirements

1. Complete the full vertical slice for this module.
2. Use existing controllers/modules where present.
3. If route/page/controller naming is inconsistent, normalize through the existing router and document any redirects/aliases.
4. Ensure all API responses use the shared response contract.
5. Ensure all list screens support real data loading, empty state, error state, and permission-aware actions.
6. Ensure all create/edit/approve/delete/export actions are permission checked on frontend and backend.
7. Remove production use of placeholder/mock/dummy/fallback data from this module.
8. Do not delete files blindly. If a file is duplicate, deprecate safely or route it to the canonical implementation.
9. Add audit logs for sensitive create/update/delete/approve/payment actions where audit infrastructure exists.
10. Update documentation.

## Module-specific deliverables

Create/update:
- docs/AI_DOS/ATTENDANCE_IMPLEMENTATION_NOTES.md
- docs/AI_DOS/ATTENDANCE_TESTING_CHECKLIST.md

Implement:
- daily attendance page/controller
- class history page/controller
- attendance API endpoints
- scoped reads by role
- audit logs for attendance changes


## Definition of done

This module is done only when:
- canonical page(s) load without blank screens
- page JS is actually loaded by the page
- all MVP workflows persist real database data
- unauthorized users are blocked
- authorized users see correct role-specific actions
- API endpoints cannot be abused directly
- no MVP path depends on mock, dummy, placeholder, or fake fallback data
- syntax checks pass
- testing checklist is written
