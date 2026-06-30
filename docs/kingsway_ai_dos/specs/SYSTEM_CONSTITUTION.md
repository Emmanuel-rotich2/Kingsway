# Kingsway Engineering Constitution

This document is the non-negotiable rulebook for all AI agents working on Kingsway.

## 1. Mission

Kingsway must become a stable MVP school ERP, not a collection of pages.

Every change must improve:
- correctness
- security
- maintainability
- user workflow completion
- role separation
- data integrity
- testability

## 2. Execution mode

AI agents must work in **implementation mode**, not endless audit mode.

Before editing a module, the agent may inspect files, but inspection must be bounded and must produce implementation immediately after a clear dependency map is formed.

## 3. Vertical-slice rule

A module is only complete when the full slice works:

1. Database tables exist and are correct.
2. API endpoints exist and use the shared response contract.
3. Server-side validation exists.
4. Server-side permission checks exist.
5. UI page exists.
6. Matching JS controller exists and is loaded by the page.
7. Frontend uses centralized API client, not random fetch logic.
8. Loading, empty, error, unauthorized, forbidden, and success states exist.
9. Role-specific actions are visible only to permitted users.
10. Sensitive actions write audit logs.
11. Manual tests are documented.
12. No mock, dummy, placeholder, fallback-as-real-data, or "coming soon" remains.

## 4. Do not create architectural debt

Agents must not:
- create duplicate pages when a proper page exists
- create duplicate controllers when a controller exists
- bypass `api/index.php`, router, auth, or RBAC middleware
- hardcode role names inside pages when permission checks exist
- put SQL directly inside UI pages
- introduce mock data as production fallback
- silently catch errors and show fake success
- leave TODOs, placeholders, stubs, or dead code
- modify many unrelated modules in one pass
- redesign UI unless the module cannot be used

## 5. Single source of truth

The source of truth must be:

| Concern | Source of truth |
|---|---|
| Authentication | Auth API + JWT/session contract |
| Permissions | RBAC middleware + permission tables + shared helper |
| Sidebar/navigation | role sidebar config/database-driven menu |
| API response shape | BaseController/helper response contract |
| Frontend API calls | `js/api.js` |
| Dashboard routing | dashboard router + dashboard registry |
| Database schema | migrations + `database/KingsWayAcademy.sql` |
| UI state pattern | shared JS state helpers and page controllers |

## 6. RBAC standard

Every protected page and API action must answer:

- who can view?
- who can create?
- who can edit?
- who can approve?
- who can reject?
- who can delete?
- who can export?
- who can print?

Permission must be enforced on both:
- frontend visibility
- backend authorization

Frontend-only RBAC is not security.

## 7. Shared-page behavior

Where different users access the same page, the page must not duplicate itself for every role unless workflow differences truly require separate pages.

Preferred:
- one canonical page
- one JS controller
- role-aware data scope
- permission-aware action buttons
- server-side enforcement

Example:
- Director can approve fee structure.
- Accountant can draft fee structure.
- Teacher can only view assigned class data.
- Parent can only view own child data.

## 8. State management standard

Every page controller must handle:

- initial loading
- authenticated user loading
- permission loading
- API loading
- empty state
- validation error
- forbidden response
- unauthorized response
- server error
- success notification
- refresh/reload after mutation

No page should rely on unstructured global state.

## 9. API response standard

Use one consistent API response shape:

```json
{
  "success": true,
  "data": {},
  "message": "Action completed",
  "errors": null,
  "meta": {}
}
```

Errors:

```json
{
  "success": false,
  "data": null,
  "message": "Validation failed",
  "errors": {
    "field": ["Message"]
  },
  "code": 422
}
```

## 10. MVP priority

Finish these in order:

1. Auth, RBAC, route/page guards, sidebar
2. Dashboard shell and role dashboards
3. Students and admissions
4. Academics and CBC assessment
5. Attendance
6. Finance, fee structure, payments
7. Staff, HR, payroll
8. Transport
9. Boarding
10. Communications
11. Discipline, counseling, health
12. Inventory, procurement, assets
13. Library
14. Reports and exports
15. Public website/admin content
16. Data import and migration tools
17. QA and release hardening

## 11. Definition of done

A module is MVP-complete only when:
- all critical user journeys work
- all CRUD actions required for MVP work
- all approval flows required for MVP work
- all pages load real API data
- all mutations persist to DB
- all permissions are enforced server-side
- all role-specific UI behavior is correct
- audit logs exist for sensitive actions
- tests or manual verification notes exist
- documentation is updated

