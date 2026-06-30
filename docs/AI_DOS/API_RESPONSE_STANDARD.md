# API Response Standard

All Kingsway APIs must return a predictable JSON contract so frontend controllers can handle success, empty, validation, unauthorized, forbidden, and server errors consistently.

## Canonical response shape

Successful response:

```json
{
  "success": true,
  "data": {},
  "message": "Action completed",
  "errors": null,
  "meta": {}
}
```

Error response:

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

The repository normalizes API output at the entrypoint/helper layer. New and repaired endpoints must cooperate with that shared response contract instead of inventing endpoint-specific shapes.

## HTTP status expectations

| Situation | HTTP status | Response requirement |
|---|---:|---|
| Success | 200/201 | `success: true`, real `data` or explicit empty collection |
| Validation failure | 422 | `success: false`, field or request errors |
| Unauthorized | 401 | `success: false`, login/session message |
| Forbidden | 403 | `success: false`, permission message |
| Not found | 404 | `success: false`, missing resource message |
| Conflict/state guard | 409 | `success: false`, workflow/state message |
| Server error | 500 | `success: false`, generic user-safe message |

## Data rules

- Lists return arrays, not objects keyed by arbitrary IDs unless documented.
- Empty lists return `data: []` or a documented list envelope, not fake rows.
- Mutations return the saved record, identifier, or workflow state needed for refresh.
- Totals/pagination/filter state belong in `meta` when used.
- Sensitive fields must be omitted unless the user has permission and business need.

## Controller rules

API controllers must:

- be reached through `api/index.php` and the existing router;
- validate request input at the boundary;
- delegate business rules to modules/services where those exist;
- enforce permissions server-side before sensitive reads/mutations;
- use prepared queries or existing DB abstractions;
- return normalized success/error structures;
- avoid echoing raw PHP warnings, stack traces, SQL errors, or secrets to users;
- audit sensitive mutations where infrastructure exists.

## Frontend handling rules

JS controllers must:

- use `callAPI` or existing helpers in `js/api.js`;
- treat `401` as unauthorized/session state;
- treat `403` as forbidden, not empty data;
- render validation messages from `errors` where available;
- show empty state only when a successful response returns no records;
- avoid assuming legacy endpoint-specific response shapes unless the endpoint is intentionally documented.

## Direct endpoint abuse check

For every protected create/edit/approve/delete/export endpoint, manual testing must include a direct API call without the required permission. It must return unauthorized or forbidden and must not mutate data.
