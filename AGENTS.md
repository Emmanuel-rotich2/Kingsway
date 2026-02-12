# Repository Guidelines

## Project Structure & Module Organization
- `api/` houses REST controllers, middleware, and business modules; entry point is `api/index.php`.
- `config/` contains environment-specific PHP config (see `config/config.template.php`).
- `database/` includes migrations plus the `database/KingsWayAcademy.sql` seed dump.
- Frontend assets live in `js/`, `css/`, `images/`, and shared layouts in `layouts/` with pages in `pages/`.
- Helper scripts and ad-hoc checks are in `scripts/`; PHP/JS tests also appear in `tests/` and root test files.

## Build, Test, and Development Commands
- `composer install` installs PHP dependencies into `vendor/`.
- `npm ci` installs Node dependencies for UI smoke tests.
- `php -S 127.0.0.1:8000 -t .` runs a local PHP server for quick testing.
- `mysql -u <user> -p KingsWayAcademy < database/KingsWayAcademy.sql` imports the seed database.

## Coding Style & Naming Conventions
- PHP follows PSR-12; use 4-space indentation and PSR-4 namespaces under `App\` (see `composer.json`).
- Keep API calls centralized in `js/api.js` and reuse the notification modal patterns.
- Page scripts commonly use snake_case filenames (e.g., `forgot_password.php`); class names are PascalCase.
- Match existing file style for JS (double quotes and `const`/`let` are common).

## Testing Guidelines
- PHP checks live in `tests/` (for example, `tests/validate_user.php`) and `scripts/test_*.php`.
- UI smoke tests are driven by Puppeteer: `npm run test:ui` (calls `scripts/ui-test.js`).
- Name new tests with the `test_*.php` pattern where practical, and keep them runnable via CLI.

## Commit & Pull Request Guidelines
- Commit messages are short, descriptive, and imperative; optional Conventional Commit prefixes appear (e.g., `feat:`, `ci:`, `chore(scope):`).
- Branch from `development` using `feature/<short-description>` as described in `CONTRIBUTING.md`.
- PRs should include a summary, testing notes, and any required migration/config changes; update docs if behavior changes.

## Security & Configuration Tips
- Do not commit secrets; use `config/config.template.php` as a starting point for `config/config.php`.
- Report vulnerabilities privately per `SECURITY.md` (do not open public issues).
