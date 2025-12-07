# Contributing to Kingsway

Thanks for helping improve the Kingsway School Management System! Please follow these guidelines to keep contributions smooth and reviewable.

## Branches and Workflow
- **main**: Stable, deployable branch. Protected.
- **development**: Active integration branch. Base for feature work.
- **Feature branches**: `feature/<short-description>` from `development`.

## Getting Started
1. Fork/clone the repo and checkout `development`.
2. Install dependencies: `composer install`.
3. Configure your `.env` and database (see `README.md` for setup).
4. Run migrations/seeds as needed: `scripts/run_migration.sh` or import the SQL dump.

## Coding Guidelines
- **PHP**: Follow PSR-12 style where practical; prefer dependency injection over globals; avoid duplicating business logic.
- **JavaScript**: Keep API interactions centralized in `js/api.js`; avoid inline fetch/axios; use the notification modal for user feedback.
- **Security**: Validate and sanitize input, use prepared statements, and respect permission checks on every endpoint.
- **Stateless auth**: Do not introduce server-side session state; rely on JWT for auth.

## Tests and Checks
- Run relevant scripts in `tests/` before opening a PR (e.g., `tests/verify_api_endpoints.sh`, `tests/test_auth_login_endpoint.php`).
- Add/adjust tests when you change API contracts, permissions, or dashboards.

## Pull Requests
- Base all PRs on `development`.
- Keep changes scoped; update docs when behavior or endpoints change.
- Include a short summary, testing notes, and any migration requirements.
- Address review feedback promptly; re-run tests after updates.

## Reporting Issues
- Provide reproduction steps, expected vs actual behavior, logs or responses (omit secrets), and environment details (OS, PHP version, DB).

Thanks for contributing! Your improvements keep Kingsway reliable and secure for every school using it.
