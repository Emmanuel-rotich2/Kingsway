# Kingsway School Management System

Kingsway is a PHP school-management application for Kingsway Preparatory School. It combines a public school website, an authenticated management shell, and a convention-routed JSON API for academics, admissions, students, staff, finance, inventory, transport, communications, reports, and system administration.

## Current Architecture

- **Public website**: top-level PHP pages such as `index.php`, `about.php`, `admissions.php`, `events.php`, and `news.php` use shared public layout/data helpers under `public/layout/`.
- **Authenticated app shell**: `home.php` loads `layouts/app_layout.php`, shared styles, `js/api.js`, core browser services, reusable UI components, and page modules from `js/pages/`.
- **API entry point**: `api/index.php` initializes `App\Config\Config`, sets JSON error handling, runs the router, and normalizes responses through `App\API\Includes\ApiResponse`.
- **Middleware pipeline**: `api/router/Router.php` applies CORS, IP access control, rate limiting, JWT auth, RBAC, route authorization, and device logging before dispatching to controllers.
- **Controller routing**: `api/router/ControllerRouter.php` maps `/api/<controller>/<resource>/<id>` to controller methods such as `getStudents`, `postPayments`, or bare `get`/`index`.
- **Business modules**: `api/modules/` contains domain managers, workflows, and API facades; `api/services/` contains shared services used across controllers and pages.

For a deeper map, see [docs/CODEBASE_OVERVIEW.md](docs/CODEBASE_OVERVIEW.md).

## Tech Stack

- PHP `>=7.4` per `composer.json`
- Composer PSR-4 autoloading for `App\`, `App\API\`, `App\Config\`, and `App\Database\`
- MySQL/MariaDB through PDO
- Vanilla JavaScript with Bootstrap, Chart.js, browser storage/sync helpers, and Puppeteer smoke tests
- PHPUnit 10 for unit tests

## Project Layout

```text
api/
  controllers/      HTTP-facing controllers
  middleware/       CORS, auth, RBAC, rate limit, device and IP checks
  modules/          Domain APIs, managers, and workflows
  router/           Router and controller dispatch
  services/         Shared application services
  includes/         Response, validation, workflow, export, and helper utilities
components/         Reusable PHP UI fragments
config/             Environment config, permissions, sidebars, upload paths
database/           PDO wrapper, schema dumps, migrations
docs/               Architecture notes, audits, migration guides
js/
  core/             Browser bootstrap, session, service worker, storage services
  pages/            Page-specific controllers
  components/       Reusable JS UI helpers
layouts/            Authenticated app layout shell
pages/              Authenticated PHP page views
public/             Public-site CSS, JS, layouts, verification helpers
scripts/            Migration, verification, and smoke-test scripts
tests/Unit/         PHPUnit tests
uploads/            Runtime upload storage, ignored/sensitive in normal operation
```

## Setup

1. Install PHP dependencies:

```bash
composer install
```

2. Install JavaScript tooling for smoke tests:

```bash
npm install
```

3. Create environment config:

```bash
cp config/.env.example config/.env
```

Then update `config/.env` with local database credentials, `APP_ENV`, `BASE_URL`, and `JWT_SECRET`. `config/Config.php` loads `config/.env`, detects the environment, and then loads `config/config_development.php` or `config/config_production.php`.

4. Provision a database from the available schema dump and migrations:

```bash
mysql -u <user> -p -e "CREATE DATABASE KingsWayAcademy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u <user> -p KingsWayAcademy < database/localhost.sql
scripts/run_kingsway_migrations.sh
```

Review migration scripts before running them against shared or production databases.

5. Serve locally from the repository root:

```bash
php -S localhost:8000
```

If using Apache/XAMPP with a `/Kingsway` base path, set `BASE_URL=http://localhost/Kingsway`. If using PHP's built-in server at the repository root, set the base URL to match how you open the app.

## Common URLs

- Public website: `/index.php`
- Authenticated shell: `/home.php`
- Route-loaded app page: `/home.php?route=<route_key>`
- API base: `/api`
- Login endpoint: `POST /api/auth/login`

Most API calls require `Authorization: Bearer <token>` after login. The browser client stores auth state in localStorage and routes all calls through `js/api.js`.

## Development Commands

- `composer test` or `vendor/bin/phpunit`: run PHPUnit unit tests from `tests/Unit`.
- `npm run test:ui`: run the Puppeteer UI smoke test in `scripts/ui-test.js`.
- `BASE_URL=http://localhost:8000 npm run test:ui`: run UI smoke tests against a non-default local URL.
- `php -S localhost:8000`: quick local server from the project root.

## API Response Contract

API responses are normalized to this general shape:

```json
{
  "success": true,
  "status": "success",
  "data": {},
  "message": "OK",
  "errors": [],
  "code": 200
}
```

Controllers may return arrays, scalar values, or JSON strings; `api/index.php` and `ApiResponse` normalize the final payload. Unknown named resources should return 404 instead of falling back to a controller list method.

## Testing Guidance

Unit tests are configured by `phpunit.xml` and cover `api/includes`, `api/middleware`, `api/router`, and `api/services`. Keep tests deterministic: PHPUnit is configured to fail on warnings, risky tests, and accidental output.

Run the UI smoke test when changing the app shell, dashboard loading, public entry pages, or browser routing.

## Security Notes

- Do not commit secrets from `config/.env`, production config files, `logs/`, `uploads/`, or database dumps.
- Keep `DEBUG=false` in production.
- Use a strong production `JWT_SECRET`.
- Treat `database/` files as potentially sensitive operational data.
- Review middleware behavior before adding public API routes; the default API path is protected by auth, RBAC, route authorization, IP controls, rate limiting, and device checks.

## Additional Documentation

Important supporting docs include:

- [config/README.md](config/README.md) for environment configuration.
- [docs/RBAC_SYNC.md](docs/RBAC_SYNC.md) for role and permission synchronization.
- [docs/SERVICE_WORKER_GUIDE.md](docs/SERVICE_WORKER_GUIDE.md) for offline/service-worker behavior.
- [docs/PRINTING_SYSTEM_GUIDE.md](docs/PRINTING_SYSTEM_GUIDE.md) for print and template behavior.
- [docs/file_lifecycle/FINAL_ARCHITECTURE.md](docs/file_lifecycle/FINAL_ARCHITECTURE.md) for upload and file lifecycle architecture.
