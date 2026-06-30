# Definition of Done

A Kingsway module is not complete because a page exists or an endpoint returns something. It is complete only when the full real-data workflow is usable and secured.

## MVP completion gate

A module is MVP-complete only when all applicable items are true:

- canonical page(s) load through the shared layout without blank screens;
- page JS controller is actually loaded by the page;
- data loads from real database-backed APIs;
- no MVP path depends on mock, dummy, placeholder, fake, or fallback-as-real data;
- create/edit/approve/reject/delete/export actions persist real database changes;
- unauthorized users are blocked;
- authenticated users without permission receive a forbidden state;
- authorized users see only the actions their permissions allow;
- backend endpoints enforce the same permissions as the UI;
- direct API calls cannot bypass page/sidebar hiding;
- sensitive mutations write audit logs where audit infrastructure exists;
- loading, empty, error, unauthorized, forbidden, and success states are visible;
- syntax checks pass for changed PHP/JS where tooling exists;
- regression searches for references/placeholders are complete;
- manual test checklist is written.

## Not done

The module is not done if any of these are true:

- the page only renders static cards without real API data;
- the JS file exists but is not loaded;
- a sidebar item is hidden but direct URL/API access still works;
- errors are swallowed and shown as success;
- a placeholder endpoint returns success without persistence;
- fake rows are displayed when the database is empty or broken;
- permission checks exist only in JavaScript;
- a mutation works but no audit path exists for sensitive actions;
- the implementation changes unrelated modules to make tests pass.

## Required manual test checklist

Each completed module must document exact manual tests for:

1. Login/session expired behavior.
2. User without view permission.
3. User with view-only permission.
4. User with create/edit permission.
5. User with approve/reject permission when applicable.
6. Empty database result.
7. API/server error result.
8. Successful mutation and visible refresh.
9. Direct API abuse attempt without required permission.
10. Sidebar/page/action visibility for each relevant role.

## Completion report

Every completion response must include:

- files changed;
- workflows completed;
- permissions enforced;
- APIs completed;
- remaining risks;
- exact manual tests to run.
