# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Kingsway is an API-first school management platform for Kingsway Prep School. It uses a PHP 8+ REST API backend with vanilla JavaScript frontend, JWT-based authentication, and database-driven role-based dashboards.

## Development Setup

```bash
# Install PHP dependencies
composer install

# Install Node.js testing tools (Puppeteer)
npm install

# Run UI smoke tests
npm run test:ui

# Apply database schema/seed data
mysql -u root -p KingsWayAcademy < database/KingsWayAcademy.sql
```

No build step is required — PHP files are served directly. The project is designed to run under Apache/Nginx with the document root at the project root.

## Architecture

### Request Lifecycle

All API requests flow through `api/index.php`:

1. **CORSMiddleware** — validates allowed origins, handles OPTIONS preflight
2. **RateLimitMiddleware** — brute-force protection per IP
3. **AuthMiddleware** — validates JWT Bearer token, attaches decoded user to `$_SERVER['auth_user']`
4. **RBACMiddleware** — resolves effective permissions via stored procedure `sp_user_get_effective_permissions(user_id)`
5. **DeviceMiddleware** — device fingerprinting and blacklist enforcement
6. **ControllerRouter** — maps URI segments to controller methods

### URL → Controller Mapping

`/api/{controller}/{resource}/{id}` maps to `{Controller}Controller::{httpMethod}{Resource}()`.

For example: `GET /api/finance/reports/compare-yearly-collections` → `FinanceController::getReportsCompareYearlyCollections()`

Method resolution tries these in order:
1. `{httpMethod}{Resource}` (e.g., `getReportsCompareYearlyCollections`)
2. `{httpMethod}{Controller}` / `{httpMethod}{Singular}`
3. Fallback: `get`, `post`, `index`

### Dashboard System

Dashboards are **database-driven**: the `role_dashboards` table maps roles to dashboard keys, and `DashboardRouter::getDashboardForRole()` looks this up (with caching). The dashboard key maps to a PHP template in `components/dashboards/` and a JS component in `js/dashboards/`.

The main app shell is `home.php`, which reads the `?route=` query param and renders the appropriate dashboard.

### Frontend Architecture

- `js/api.js` — Central API client (`callAPI` function) and `AuthContext` singleton (stores token/user/permissions in `localStorage`)
- `js/index.js` — Client-side routing, initializes dashboard components based on `?route=`
- `js/sidebar.js` — Dynamic sidebar built from `sidebar_items` in the login response
- `js/pages/` — Page-specific logic (one file per feature page)
- `js/components/` — Reusable UI: `DataTable`, `ActionButtons`, `RoleBasedUI`

Permission checks happen on both server (RBACMiddleware) and client (`AuthContext.hasPermission('finance.view')`).

### Authentication

- JWT tokens (HS256, 1-hour expiry) stored in `localStorage`
- Sent as `Authorization: Bearer <token>` header
- Public endpoints (no JWT required): `auth/login`, `auth/register`, `auth/reset-password`, `payments/*` (webhook callbacks)
- **Development bypass**: `X-Test-Token: devtest` header injects a hardcoded accountant test user

### Key Directories

| Path | Purpose |
|------|---------|
| `api/controllers/` | HTTP handlers — one class per domain (Academic, Finance, Staff, Students, etc.) |
| `api/modules/` | Business logic called by controllers |
| `api/middleware/` | Request pipeline (Auth, RBAC, CORS, RateLimit, Device) |
| `api/services/` | External integrations (M-Pesa, KCB, Africa's Talking SMS, email) |
| `config/config.php` | Database credentials, JWT secret, SMTP, payment gateway keys |
| `database/` | PDO singleton (`Database.php`), schema seed (`KingsWayAcademy.sql`), migrations |
| `components/dashboards/` | Role-specific PHP dashboard templates |
| `pages/` | Feature pages served by the app |
| `layouts/` | Shared HTML shell templates |

### Database

Singleton PDO in `database/Database.php`. Config in `config/config.php` (DB_HOST, DB_USER, DB_PASS, DB_NAME). Always use prepared statements — never string-interpolate user input into queries.

### Payments

M-Pesa (C2B/B2C) and KCB Buni integrations are in `api/services/` and `api/modules/payments/`. Their webhook callback URLs must be publicly reachable and are excluded from JWT auth.

## Important Conventions

- Controllers receive `($id, $data, $segments)` — `$data` is the decoded request body, `$segments` are URI path parts beyond the base resource.
- RBAC permissions use dot notation (`finance.view`) and underscore aliases (`finance_view`) — both are stored.
- Dashboard PHP templates are pure HTML/JS; data is fetched client-side via the API after load.
- The `logs/errors.log` file is tracked in git — do not log secrets to it.
