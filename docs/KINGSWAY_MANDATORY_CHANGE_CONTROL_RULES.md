# Kingsway School Management — Mandatory Change Control Rules

These rules apply to every future analysis, fix, update, refactor, feature, migration, UI change, backend change, database change, and code-generation task in the Kingsway School Management project.

## 1. Full-Codebase Context Is Mandatory

Before changing anything, thoroughly inspect all relevant existing project files and previously produced updates.

The inspection must include, where applicable:

- PHP pages and shared PHP components
- JavaScript page helpers and shared JavaScript modules
- CSS files and shared styles
- `api.js` namespaces, helpers, endpoint wrappers, and request conventions
- Backend routes and endpoint registration
- Controllers
- Services
- Repositories, models, modules, helpers, middleware, and utilities
- Authentication, authorization, RBAC, and permission checks
- Sidebar definitions and route mappings
- Database tables
- Columns and data types
- Primary keys and foreign keys
- Unique constraints and indexes
- Enum values
- Views
- Stored procedures
- Functions
- Triggers
- Scheduled events
- Existing migrations
- Previously generated or updated Kingsway files available in the project library

No implementation may begin from assumptions, isolated snippets, generic templates, or newly invented parallel architecture.

## 2. Database-First Verification

Before writing database-dependent code:

1. Verify the actual database schema.
2. Confirm every referenced table.
3. Confirm every referenced column.
4. Confirm exact data types and signed/unsigned properties.
5. Confirm nullability and defaults.
6. Confirm enum values.
7. Confirm relationships and foreign keys.
8. Confirm indexes and unique constraints.
9. Confirm triggers, procedures, functions, events, and views.
10. Confirm whether the requested capability already exists.

Never create a competing table when an existing canonical table can support the requirement.

Never write queries against assumed column names.

## 2A. Uploads Are Runtime-Owned

The `uploads/` directory is environment-owned runtime storage and must not be tracked in Git.

Do not commit files from `uploads/`, including generated print files, user documents, imported files, profile pictures, logos, certificates, ID-card output, or `.gitkeep` placeholders.

When a change to an upload-backed template or asset is intentionally needed for production, copy that file into `uploads_backup/` and document the manual promotion path. Production deployment must then copy the reviewed file from `uploads_backup/` into the production `uploads/` tree manually.

Never use `uploads/` as the canonical source for deployable templates. Use `uploads_backup/` for repository-managed backup copies only.

## 3. API Audit

Before adding or changing API calls:

1. Inventory all relevant existing namespaces and methods in `js/api.js`.
2. Identify existing endpoint helpers that already cover the workflow.
3. Confirm the project's `apiCall` signature and request conventions.
4. Confirm authentication, permission checks, cache invalidation, file handling, and query parameter conventions.
5. Extend the existing canonical namespace where appropriate.
6. Do not create a new namespace merely for convenience when an existing namespace owns the domain.

No duplicate endpoint wrapper may be introduced.

## 4. Backend Audit

Before creating a controller, service, repository, helper, or module:

1. Search for existing controllers handling the same domain.
2. Search for existing service methods implementing similar logic.
3. Search for reusable base classes and shared services.
4. Check middleware and permission enforcement.
5. Check audit logging and error handling conventions.
6. Check upload, download, print, and file lifecycle services.
7. Check transaction patterns.
8. Extend canonical files whenever reasonable.

Create a new backend file only when there is no appropriate canonical location.

### Controller Boundary Rule

Controllers must remain thin REST endpoint adapters.

A controller's responsibility is to:

- Expose RESTful endpoints.
- Validate and normalize request input at the boundary.
- Enforce authentication, authorization, and route-level guards.
- Delegate business operations to the canonical API file, service, module manager, workflow, repository, or microservice layer.
- Normalize and return responses using existing project conventions.

Controllers must not contain core business logic, workflow orchestration, complex calculations, database-heavy operations, reporting logic, import/export processing, lifecycle rules, payment rules, academic rules, finance rules, or staff/student domain rules.

When a controller starts accumulating business behavior, move that behavior into the appropriate existing domain layer, preferring this order:

1. Existing `api/modules/<domain>/*API.php`
2. Existing `api/modules/<domain>/*Manager.php` or workflow class
3. Existing `api/services/*Service.php`
4. A new narrowly scoped service or module manager only when no canonical location exists
5. An external microservice only when the project already uses or explicitly requires that boundary

The controller may coordinate the call, but the domain decision must live outside the controller.

## 5. Route and UI Audit

For every affected page or route, map the complete chain:

```text
role_sidebar.php
→ route registration
→ PHP page
→ page JavaScript
→ shared JavaScript modules
→ api.js endpoint helper
→ backend endpoint
→ controller
→ service
→ database
→ permissions/RBAC
→ audit logging
```

Confirm that all parts exist, use matching names, and remain synchronized.

A route is not considered complete merely because the PHP page loads.

## 5A. Staff Teaching Role Model

Every teaching-domain staff member must be treated as a teacher first.

The baseline teaching role is `Subject Teacher`. Additional roles such as `Headteacher`, `Deputy Head - Academic`, `Deputy Head - Discipline`, `Class Teacher`, `Intern/Student Teacher`, games/co-curricular duty, boarding duty, or similar responsibilities are layered duties, not replacements for `Subject Teacher`.

When creating, importing, onboarding, assigning, reporting on, or building dashboards for teachers:

- Ensure teaching-domain users have the `Subject Teacher` school role in `user_roles`.
- Do not model `Class Teacher`, `Headteacher`, or deputy-head roles as mutually exclusive with subject teaching.
- Use `staff_class_assignments` plus `learning_areas`, `classes`, `class_streams`, `school_levels`, and `academic_years` for teaching responsibilities.
- For lower primary and pre-primary, class teachers may cover all learning areas in their class curriculum.
- For upper primary and junior secondary, class teachers are subject teachers with additional class oversight responsibilities.

## 6. Gap Analysis Before Implementation

Before writing code, explicitly determine:

- What already exists
- What is working
- What is incomplete
- What is broken
- What is duplicated
- What should be reused
- What should be extended
- What should be removed
- What genuinely needs to be created
- Which files will be affected
- Which database objects will be affected
- Which permissions and roles will be affected
- Which routes and sidebar items will be affected
- Which tests or verification steps are required

Implementation must address only verified gaps.

## 7. Impact Analysis for Every Change

Even for a small change, such as changing a button, first identify:

- The page containing the button
- The JavaScript event handler
- The API method it calls
- The backend endpoint
- The controller and service method
- The database operation
- Permission requirements
- Loading, success, error, empty, and forbidden states
- Shared components or styles affected
- Other roles or pages using the same component
- Whether the change is safe
- Whether it introduces inconsistency
- Whether it is beneficial or harmful
- Whether a better existing pattern should be reused

The impact analysis must be shown before implementation.

## 8. Plan Before Code

No code should be produced before presenting a concise implementation plan containing:

1. Verified current architecture
2. Affected files
3. Affected database objects
4. Existing logic to reuse
5. Missing logic to add
6. Logic to remove or consolidate
7. Risks and compatibility concerns
8. Verification steps

After the plan, implement the code without unnecessary delays or repeated confirmation requests unless a destructive or ambiguous decision genuinely requires clarification.

## 9. Canonical Architecture Only

Preserve this architecture:

```text
role_sidebar.php
→ route
→ PHP page
→ page JS
→ api.js
→ backend endpoint
→ controller
→ service
→ database
```

Also preserve:

- Server-side permission enforcement
- Frontend permission-based visibility
- Real database data
- Canonical shared services
- Existing authentication and RBAC
- Existing layouts and routers
- Existing error-handling conventions
- Existing audit logging where available

Do not bypass layers.

Do not place database logic directly in page JavaScript or PHP view files.

## 10. No Parallel or Duplicate Logic

Before creating anything new, search for equivalent functionality.

Do not introduce:

- Duplicate API namespaces
- Duplicate endpoint wrappers
- Duplicate controllers
- Duplicate services
- Duplicate tables
- Duplicate settings stores
- Duplicate route registries
- Duplicate navigation systems
- Duplicate permission systems
- Duplicate page workflows
- Generic abstractions that replace working domain-specific logic
- Competing sources of truth

When duplication already exists, consolidate toward the canonical implementation.

## 11. Role-Specific Workflows

Do not force unrelated roles into one generic page or workflow when their responsibilities differ.

For each role:

- Verify its permissions
- Verify its sidebar
- Verify its route access
- Verify its data scope
- Verify its allowed actions
- Verify server-side enforcement
- Verify frontend visibility

Shared components may be reused, but business workflows must remain role-appropriate.

## 12. Real Data Only

Do not use:

- Mock data
- Placeholder metrics
- Dummy records
- Fabricated fallbacks
- Hardcoded production values
- Silent empty-array fallbacks that hide backend failures

Every page must have proper:

- Loading state
- Empty state
- Error state
- Forbidden state
- Success state

## 13. Verification Is Mandatory

After implementation, verify:

- PHP syntax
- JavaScript syntax
- SQL syntax
- Route registration
- Endpoint availability
- API helper availability
- Controller dispatch
- Service method compatibility
- Database column compatibility
- Foreign-key compatibility
- Permission assignment
- Server-side authorization
- Sidebar visibility
- Page loading
- Real-data retrieval
- CRUD actions where applicable
- Audit logging where applicable
- No duplicate logic was introduced
- No existing workflow was broken

Do not claim a feature is complete unless it has been verified.

If runtime verification is not possible, state exactly what was statically verified and what still requires execution in the user's environment.

## 14. Previously Generated Work Must Also Be Audited

Do not assume earlier AI-generated Kingsway files are correct.

Before reusing earlier updates:

- Compare them with the current codebase
- Compare them with the current database
- Check whether later changes made them stale
- Check whether they introduced duplicate logic
- Correct or remove invalid earlier work

The latest verified canonical implementation takes precedence over all earlier generated files.

## 15. Required Response Format for Future Changes

For each requested update, respond in this order:

### A. Verified Existing Context
State what currently exists and where.

### B. Impact Analysis
Explain every affected layer and whether the change is beneficial, risky, or incompatible.

### C. Implementation Plan
List the exact files and database objects to be changed.

### D. Code
Provide only the affected/new files, not the entire project.

### E. Verification
State what was checked, what passed, and what still requires runtime testing.

## 16. Non-Negotiable Principle

The goal is to clean, consolidate, and complete the Kingsway codebase.

Every change must reduce confusion, preserve one source of truth, and improve architectural consistency.

No assumption-driven implementation is acceptable.
