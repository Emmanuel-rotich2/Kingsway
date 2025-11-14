## Purpose

Short, actionable guidance for AI coding agents working on the Kingsway PHP/JS codebase. Focus on the project's architecture, conventions, common workflows, and exact integration points an agent will need to be productive.

## Quick architecture summary
- Backend: PHP (namespaces under `App\\*`), organized under `api/` for endpoints and `api/includes/` for shared classes (notably `BaseAPI.php`).
- Frontend: JS files in `js/` with a single centralized `js/api.js` that all pages use for API calls.
- Config: `config/config.php` holds constants (DB, JWT, SMTP, CORS, DEBUG) and small helpers like `handleCORS()` and `formatResponse()`.
- Database: singleton `App\\Config\\Database` in `config/db_connection.php`; schema/migrations are in `database/` (import `database/KingsWayAcademy.sql`).

## Key files to consult before editing
- `config/config.php` — environment constants, DEBUG toggle, upload paths, JWT_SECRET, ALLOWED_ORIGINS.
- `config/db_connection.php` — PDO singleton, query/transaction helpers.
- `api/includes/BaseAPI.php` — central API helper: logging, error handling, transactions, file uploads, RBAC helpers.
- `api/includes/auth_middleware.php` — JWT-based authenticate() and authorize() helpers; places user info into `$_REQUEST['user']`.
- `session_handler.php` — how sessions are initialized for the frontend.
- `js/api.js` and `pages/` — examples of frontend usage patterns and centralized API calls.
- `docs/api_guide.md` — detailed API expectations and endpoint list.

## Project-specific conventions and patterns
- All API responses use `formatResponse()` (see `config/config.php`) — follow its shape: {status, message, data} and use proper HTTP codes.
- Use prepared statements via the Database singleton; do not construct raw SQL with interpolated user input.
- Logging: prefer `BaseAPI::logAction()` / `logError()` for structured logs; application also writes to `logs/` files (`system_activity.log`, `errors.log`).
- RBAC & auth: endpoints should call `authenticate()` (from `auth_middleware.php`) and then `authorize([...])` when permission checks are needed. Example header: `Authorization: Bearer <token>`.
- Transactions: prefer `beginTransaction()` / `commit()` / `rollback()` available on Database and via wrappers in `BaseAPI` — ensure rollback on exceptions.
- Uploads: config auto-creates upload directories; use `BaseAPI::uploadFile()` for consistent validation and storage.

## Developer workflows (explicit commands)
- Install dependencies: `composer install` (project uses Composer autoloading — `vendor/autoload.php` is required in API includes).
- Import DB schema: `mysql -u root -p KingsWayAcademy < database/KingsWayAcademy.sql` (see `README.md`).
- Run local server for quick dev: `php -S localhost:8000 -t .` and open `http://localhost:8000/` (or use your Apache/Nginx config). The README recommends XAMPP/LAMPP for a full stack.
- Turn on verbose debugging: set `DEBUG` in `config/config.php` to `true` (be careful not to commit secrets).

## Integration points & external deps
- JWT: uses `firebase/php-jwt` (see `vendor/`). Tokens validated in `api/includes/auth_middleware.php`.
- Email/SMS: SMTP and SMS provider credentials are set in `config/config.php` — these are real keys in the file; treat them as secrets when changing.
- Stored procedures/events: `BaseAPI::emitEvent()` prefers a stored procedure `sp_emit_event` if present — DB-centric event hooks exist in schema.

## Small examples (do this pattern)
- To protect an endpoint:
  - `require_once __DIR__ . '/includes/auth_middleware.php';`
  - call `authenticate();` then `authorize(['manage_students']);`
  - read user via `$_REQUEST['user']` (or session fallback in `BaseAPI::getCurrentUserId`).

- To log and return consistent response from an API class that uses `BaseAPI`:
  - throw Exceptions on error, catch in controller and call `$this->handleException($e)` so logs + formatted response are consistent.

## Notes for code edits
- Respect namespaces (`App\\Config`, `App\\API\\Includes`). Include `vendor/autoload.php` when adding new entry points.
- Avoid changing `JWT_SECRET`, SMTP passwords, or DB credentials in `config/config.php` in a PR—these are environment-level secrets. Use `.env` if introduced; README hints at `.env.example` but config currently uses constants.
- There are no unit tests in the repo; add small integration checks or a manual test plan in PRs (how to reproduce) when changing API behavior.

## Where to look for missing context
- `docs/api_guide.md` — API expectations and sample payloads.
- `pages/` and `js/` — example consumers of the API for payload shape and frontend expectations.
- `database/` SQL files — expected tables, triggers, and stored procedures.

If anything above is unclear or you want the agent to follow a tighter rule-set (for example: always run DB migrations, preferred logging format, or a PR checklist), tell me which area to expand and I will iterate.
