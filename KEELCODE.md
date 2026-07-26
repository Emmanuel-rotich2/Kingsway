# Kingsway Academy — Agent Guide

## Project structure

PHP school-management application with Composer autoloading.

- `api/`: JSON API — `controllers/`, `modules/`, `middleware/`, `router/`, `services/`, `includes/`, and `core/`.
- `api/index.php`: API entrypoint. `index.php` and other top-level PHP pages serve the public site.
- `home.php`: authenticated application shell; views are resolved from `pages/`, `components/`, and `layouts/`.
- `js/core/`: shared bootstrap, session, and runtime code; `js/pages/`: page-specific behavior; `js/components/`: reusable browser behavior. Use `js/api.js` for API calls.
- `css/`, `public/`, `assets/`, `images/`: frontend assets.
- `config/`: environment and application configuration. `database/`: PDO/database code, schema, and migrations.
- `tests/Unit/`: PHPUnit tests. `scripts/`: migrations, verification, and UI smoke-test scripts.

API routes are dispatched by `api/router/ControllerRouter.php` and generally follow `/api/<controller>/<resource>/<id>`. Resource names map to controller methods such as `getStudents`, `postPayments`, and `deleteProfile`. Use the standard `ApiResponse` response shape; unknown named resources should return 404 rather than silently falling back to a list action.

## Install, run, and test

```bash
composer install
npm install
cp config/.env.example config/.env
php -S localhost:8000
```

Set local database credentials, `BASE_URL`, and a development `JWT_SECRET` in `config/.env` before exercising authenticated features. Provision the database from `database/localhost.sql` and review migrations before running `scripts/run_kingsway_migrations.sh`.

```bash
composer test                 # PHPUnit unit suite
vendor/bin/phpunit            # equivalent direct command
BASE_URL=http://localhost:8000 npm run test:ui
```

The UI smoke test requires a running server and Puppeteer. The project has no separate build or lint command; assets are served directly. The Composer manifest declares PHP `>=7.4`, but the locked PHPUnit 10 dependency requires PHP 8.1+ for tests.

## Coding conventions

- Follow the PSR-4 mappings in `composer.json` (`App\\API\\...`, `App\\Config`, `App\\Database`); use PascalCase class filenames and descriptive snake_case procedural page/script filenames.
- Use four-space PHP indentation, PHP 7.4-compatible syntax unless the runtime target changes, and strict return types where established by the surrounding code. Prefer dependency injection and short docblocks for non-obvious behavior.
- Keep API logic in the appropriate controller/module/service and preserve the middleware pipeline (CORS, access control, rate limiting, authentication, RBAC, route authorization, and device checks).
- Put route-specific JavaScript in `js/pages/`, shared session/runtime behavior in `js/core/`, and API access through `js/api.js`. Match existing semicolon, async/await, and module conventions.
- Put tests in `tests/Unit/<Area>/` as `*Test.php`; keep them focused, deterministic, and free of accidental output. PHPUnit is configured to fail on warnings, risky tests, and unexpected output.
- Preserve existing CSS design tokens and use scoped/component class names for page styles.

## Security and repository hygiene

- Never commit `.env`, credentials, API keys, JWT secrets, logs, uploads, or sensitive database dumps. Add new configuration keys to `.env.example` and environment config templates without real values.
- Validate input, use prepared statements, enforce authorization on endpoints, and never expose or log bearer tokens. Keep production `DEBUG=false` and use a strong random production `JWT_SECRET`.
- Use Conventional Commit-style messages (`feat(scope): ...`, `fix(scope): ...`) and report relevant tests, migrations, and UI checks with changes.
