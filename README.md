# Kingsway School Management System

Kingsway is a full-featured, API-first school management platform built with PHP and modern JavaScript. It delivers a stateless, JWT-secured REST API, dynamic dashboards, and role-aware navigation so schools can operate academics, finance, people, transport, and communications from one cohesive system.

## Highlights

- **API-first architecture**: All UI screens consume the REST API under `/api/*`, orchestrated by a centralized `js/api.js` client with unified notifications and caching.
- **Stateless auth**: JWT-based authentication with role-based dashboards and permission-aware menus; safe for load balancers and horizontal scaling.
- **Operational breadth**: Academics, students, staff, finance, inventory, transport, activities, communications, and workflows.
- **Realtime-ready UX**: Auto-refreshing data, unified notification modal, and graceful dummy-data fallbacks to keep the UI usable even when services are slow.
- **Secure by design**: RBAC, permission checks on every call, rate limiting, prepared statements, and input validation throughout the stack.

## Core Modules

- **User & Access Control**: Roles, permissions, JWT auth, login/logout, password reset.
- **Academics & Attendance**: Subjects, classes, assessments, results, attendance capture, and reports.
- **Students & Staff**: Enrollment, profiles, ID cards, payroll, HR workflows.
- **Finance**: Fees, payments, invoices, and financial reporting.
- **Inventory & Transport**: Stock tracking, routes, vehicles, drivers, and maintenance.
- **Communications & Activities**: Announcements, notifications, events, and extracurriculars.

## Tech Stack

- **Backend**: PHP 8+, Composer, REST controllers under `api/`, stateless JWT auth.
- **Frontend**: Vanilla JS/Bootstrap powered by `js/api.js`, dynamic dashboards, and sidebar.
- **Database**: MySQL/MariaDB with migrations under `database/migrations/`.
- **Tooling**: Bash scripts in `scripts/` for migrations and setup; tests under `tests/`.

## System Requirements

- PHP 8.0+ (CLI + web SAPI)
- MySQL 5.7+ or MariaDB 10.5+
- Apache/Nginx with `mod_rewrite` (or equivalent) enabled
- Composer for dependency management
- Bash + Git for scripts and workflow

## Quick Start

1. **Clone and install dependencies**

```bash
git clone https://github.com/Emmanuel-rotich2/Kingsway.git 
cd kingsway
composer install
```

2. **Configure environment**

- Copy `.env.example` to `.env` and set DB credentials, JWT secret, and base URLs.
- Ensure `config/config.php` aligns with your environment paths and database settings.

3. **Provision the database**

```bash
mysql -u <user> -p -e "CREATE DATABASE kingsway CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u <user> -p kingsway < database/KingsWayAcademy.sql
# or run the migration helper
scripts/run_migration.sh
```

4. **Serve the app**

- Point your virtual host/root to the project (e.g., `/Kingsway` locally).
- API entry: `/Kingsway/api/index.php`
- App entry: `/Kingsway/index.php` (login) and `/Kingsway/home.php` (post-login dashboard)

5. **Smoke-test authentication**

Use the bundled test credentials (if seeded) or create a user, then:

```bash
curl -X POST http://localhost/Kingsway/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"<user>","password":"<pass>"}'
```
You should receive a JWT plus `user`, `sidebar_items`, and `dashboard` payload.


## API Overview

- Base URL: `/Kingsway/api`
- Auth: `POST /auth/login` (returns JWT + user + sidebar + dashboard)
- All endpoints enforce permission checks; see `ENDPOINT_PERMISSIONS` map in `js/api.js` for frontend guards.
- Middlewares: CORS, rate limiting, JWT auth, RBAC, device logging.
- Dashboards and sidebar items are role-aware and permission-filtered (see `api/includes/dashboards.php`).

For full endpoint specifics, refer to `docs/api_guide.md` (or regenerate docs from controllers if updated).

## Frontend Architecture

- **Single API client**: `js/api.js` centralizes all calls, permissions, and notifications.
- **Dashboard + Sidebar**: Dynamically built from API responses; sidebar always includes a Dashboard link as the first item.
- **Live UX**: Auto-refresh hooks for data-heavy pages; unified Bootstrap notification modal for user feedback.
- **Stateless session**: JWT stored in localStorage; works behind load balancers.

## Authentication

- Login: `POST /api/auth/login` with `username` and `password`.
- Authorization: `Authorization: Bearer <token>` header on all subsequent calls.
- Logout: `POST /api/auth/logout` clears client context (stateless on server side).
- Roles/permissions: Embedded in the JWT and re-hydrated on login; also used to build sidebar and dashboard selection.

## Directory Structure

```plaintext
Kingsway/
├── api/                 # REST controllers, middleware, modules
│   ├── modules/         # Core business logic
│   ├── includes/        # Shared components (DashboardManager, etc.)
│   ├── middleware/      # CORS, auth, RBAC, rate limiting
│   └── index.php        # API entry point
├── config/              # Config files
├── database/            # Migrations and seed SQL
├── docs/                # Documentation
├── js/                  # Frontend API client and UI logic
├── layouts/             # Shared layouts
├── pages/               # UI pages consuming the API
├── scripts/             # Helper scripts (migrations, setup)
├── tests/               # Shell/PHP API tests
└── vendor/              # Composer dependencies
```

## Security Features

- JWT authentication and stateless sessions
- Role-based access control with granular permissions
- Input validation/sanitization and prepared statements
- CORS protection and rate limiting middleware
- Request validation and device logging

## Development & Testing

- **Install deps**: `composer install`
- **Migrations**: `scripts/run_migration.sh`
- **API smoke tests**: see `tests/` (e.g., `tests/test_auth_login_endpoint.php`, `tests/verify_api_endpoints.sh`).
- **Auth flow**: Login returns `token`, `user`, `sidebar_items`, `dashboard`; sidebar always includes a dashboard link first.

## License

This project is licensed under the MIT License. See `LICENSE` for details.

## Support

For support or questions, open an issue or contact the development team.
