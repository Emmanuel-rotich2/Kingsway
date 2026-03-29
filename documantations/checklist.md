=========================================================
## CAN BE MODIFIED AND NEW TASKS THAT ARE MISSING ADDED
=========================================================

You must run this checklist AFTER every implementation cycle.

Do NOT skip any section.
Do NOT assume something is complete without verifying it.
Do NOT claim something is done unless all checks pass.

If something is partially done, explicitly state it as PARTIAL.

================================================================================
SECTION 1 — FILE & LOGIC COMPLETION CHECK
================================================================================

[ ] All targeted files were actually modified
[ ] No placeholder code remains (e.g. TODO, mock data, dummy arrays)
[ ] No unused or duplicate logic introduced
[ ] No conflicting old vs new logic remains
[ ] Naming is consistent with database and existing repo
[ ] No broken references (undefined variables, wrong IDs, wrong fields)

IF ANY FAIL:
→ Mark module as INCOMPLETE

================================================================================
SECTION 2 — DATABASE ALIGNMENT CHECK
================================================================================

[ ] All fields used in frontend match actual SQL columns
[ ] All relationships (FKs) are respected
[ ] All required fields are handled (not ignored)
[ ] Status fields match actual DB values (not guessed)
[ ] Any workflow states come from DB, not hardcoded
[ ] Any procedures/views/functions used correctly if applicable

IF ANY FAIL:
→ Mark as DATA MISMATCH BUG

================================================================================
SECTION 3 — API INTEGRATION CHECK
================================================================================

[ ] Frontend calls correct API endpoints
[ ] API payload structure matches backend expectations
[ ] API responses are correctly parsed (no wrong field names)
[ ] Error handling is implemented (try/catch or equivalent)
[ ] Success responses trigger UI updates
[ ] No old API patterns remain (unless intentionally preserved)

IF ANY FAIL:
→ Mark as API INTEGRATION ISSUE

================================================================================
SECTION 4 — RBAC ENFORCEMENT CHECK (CRITICAL)
================================================================================

[ ] Roles used are from SQL, not assumed
[ ] Permissions are checked (not bypassed)
[ ] Unauthorized users cannot access restricted actions
[ ] Buttons/actions hidden where permission is missing
[ ] API blocks unauthorized access (not just UI hiding)
[ ] Data is filtered by user scope (NOT global access)
[ ] Shared pages render differently based on role/permission

IF ANY FAIL:
→ Mark as SECURITY/RBAC FAILURE ❌

================================================================================
SECTION 5 — DATA SCOPE VALIDATION (VERY IMPORTANT)
================================================================================

[ ] Users only see data they are allowed to see
[ ] Parent → only their children
[ ] Teacher → only assigned classes/subjects
[ ] Staff → only their department/module scope
[ ] Admin roles → broader access only if permitted
[ ] No data leakage across roles

IF ANY FAIL:
→ Mark as DATA LEAK BUG 🚨

================================================================================
SECTION 6 — WORKFLOW COMPLETENESS CHECK
================================================================================

For workflow-based modules (admissions, results, etc):

[ ] All workflow stages implemented (not partially)
[ ] Transitions between stages work correctly
[ ] Status updates persist in DB
[ ] Correct roles can trigger transitions
[ ] Incorrect roles cannot trigger transitions
[ ] UI reflects current workflow state
[ ] No broken transitions or skipped steps

IF ANY FAIL:
→ Mark as WORKFLOW INCOMPLETE

================================================================================
SECTION 7 — UI/UX & RESPONSIVENESS CHECK
================================================================================

[ ] Page renders correctly on desktop
[ ] Page adapts correctly on tablet
[ ] Page adapts correctly on mobile
[ ] Tables scroll properly on small screens
[ ] Forms are usable and readable
[ ] Buttons are accessible and visible
[ ] Layout spacing is consistent
[ ] No UI overlap or broken layout

IF ANY FAIL:
→ Mark as UI ISSUE

================================================================================
SECTION 8 — USER INTERACTION CHECK
================================================================================

[ ] Forms validate before submission
[ ] Invalid inputs are blocked
[ ] Errors are shown clearly to user
[ ] Success messages are shown
[ ] Loading states are visible
[ ] Empty states handled (no data case)
[ ] No silent failures

IF ANY FAIL:
→ Mark as UX/INTERACTION BUG

================================================================================
SECTION 9 — STATE MANAGEMENT CHECK
================================================================================

[ ] UI updates correctly after API actions
[ ] No stale data remains after update
[ ] Tables refresh correctly
[ ] Stats/cards update correctly
[ ] No duplicate records shown
[ ] No missing updates after actions

IF ANY FAIL:
→ Mark as STATE BUG

================================================================================
SECTION 10 — EDGE CASE CHECK
================================================================================

[ ] Handles empty datasets
[ ] Handles large datasets
[ ] Handles missing optional fields
[ ] Handles invalid API responses
[ ] Handles partial data gracefully
[ ] Handles network/API failure

IF ANY FAIL:
→ Mark as EDGE CASE FAILURE

================================================================================
SECTION 11 — CODE QUALITY CHECK
================================================================================

[ ] No console errors (JS)
[ ] No syntax errors (PHP/JS)
[ ] No redundant loops or inefficient logic
[ ] No repeated code that should be reused
[ ] Functions are clear and maintainable

IF ANY FAIL:
→ Mark as CODE QUALITY ISSUE

================================================================================
SECTION 12 — FALSE COMPLETION DETECTION (CRITICAL)
================================================================================

Check if anything was CLAIMED DONE but actually NOT done:

[ ] Any feature marked "complete" but still uses mock data
[ ] Any feature marked "complete" but not connected to API
[ ] Any feature marked "complete" but missing validation
[ ] Any feature marked "complete" but missing RBAC
[ ] Any feature marked "complete" but broken on mobile
[ ] Any feature marked "complete" but fails edge cases
[ ] Any workflow marked "complete" but missing transitions

IF ANY TRUE:
→ Mark as FALSE COMPLETION ❌

================================================================================
FINAL STATUS REPORT (MANDATORY)
================================================================================

After running all checks, output:

1. COMPLETION STATUS

- COMPLETE ✅
- PARTIAL ⚠️
- INCOMPLETE ❌

1. PASSED SECTIONS
(list all sections that passed)

2. FAILED SECTIONS
(list all sections that failed + reason)

3. CRITICAL ISSUES
(list blocking issues)

4. SAFE TO PROCEED?

- YES / NO

1. REQUIRED FIXES BEFORE NEXT MODULE
(list exact fixes needed)

================================================================================
CRITICAL RULE
================================================================================

You are NOT allowed to say a module is complete unless:
ALL sections pass.

If even ONE critical section fails (RBAC, API, DB, workflow):
→ The module is NOT complete.
