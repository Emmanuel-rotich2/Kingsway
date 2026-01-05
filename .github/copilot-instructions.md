# Kingsway Academy Management System - Copilot Instructions

# GitHub Copilot Preferences

## Documentation Policy
- **NO automatic documentation creation**
- **Only create documentation when explicitly requested**
- Examples of explicit requests:
  - "Create a documentation for..."
  - "Document this..."
  - "Write a guide for..."
  - "Generate documentation"

## Development Approach
- Focus on direct code changes and implementations
- Answer questions concisely
- Make code edits efficiently
- Only discuss what is asked
- No auto-generated audit reports, guides, or summaries

## Code Generation
- Implement requested features directly
- No explanatory documents unless asked
- Focus on functionality, not documentation

---

## Development Environment

### XAMPP Configuration
- **Server**: XAMPP (Apache + MySQL + PHP)
- **Database Server**: MySQL/MariaDB
- **Database User**: `root`
- **Database Password**: `admin123`
- **Database Name**: `KingsWayAcademy`
- **Default Port**: `3306`

### Database Connection String
```bash
/opt/lampp/bin/mysql -u root -padmin123 KingsWayAcademy
```

### Running Database Migrations
```bash
# Navigate to project root
cd /home/prof_angera/Projects/php_pages/Kingsway

# Run a migration file
/opt/lampp/bin/mysql -u root -padmin123 KingsWayAcademy < database/migrations/<migration_file>.sql

# Import main schema
/opt/lampp/bin/mysql -u root -padmin123 KingsWayAcademy < database/KingsWayAcademy.sql

# Import workflows
/opt/lampp/bin/mysql -u root -padmin123 KingsWayAcademy < database/workflows_FINAL.sql
```

### PHP Configuration
- **PHP Version**: 7.4+ (recommended 8.0+)
- **Required Extensions**:
  - `pdo_mysql`
  - `mysqli`
  - `json`
  - `mbstring`
  - `openssl`
  - `curl`

### Project Structure
```
/home/prof_angera/Projects/php_pages/Kingsway/
├── api/                    # Backend API endpoints
├── config/                 # Configuration files
├── database/               # SQL schema and migrations
│   ├── migrations/        # Database migration files
│   ├── KingsWayAcademy.sql
│   └── workflows_FINAL.sql
├── js/                     # Frontend JavaScript
├── pages/                  # Frontend pages
├── vendor/                 # Composer dependencies
└── .github/               # GitHub configuration
```

### Environment Variables
Located in: `config/config.php`

Key constants:
- `DB_HOST` - Database host (default: localhost)
- `DB_NAME` - Database name
- `DB_USER` - Database username
- `DB_PASS` - Database password
- `JWT_SECRET` - JWT authentication secret
- `DEBUG` - Debug mode flag

### Quick Start Commands

#### Start XAMPP
```bash
sudo /opt/lampp/lampp start
```

#### Stop XAMPP
```bash
sudo /opt/lampp/lampp stop
```

#### Restart XAMPP
```bash
sudo /opt/lampp/lampp restart
```

#### Access MySQL CLI
```bash
/opt/lampp/bin/mysql -u root -padmin123
```

#### Check Database Status
```bash
/opt/lampp/bin/mysql -u root -padmin123 -e "SHOW DATABASES;"
```

#### Run Specific Migration
```bash
/opt/lampp/bin/mysql -u root -padmin123 KingsWayAcademy < database/migrations/inventory_workflows_expansion.sql
```

### Development Workflow

1. **Before Running Migrations**:
   - Ensure XAMPP is running
   - Verify database exists
   - Backup existing data if needed

2. **After Code Changes**:
   - Test API endpoints
   - Check error logs: `logs/errors.log`
   - Verify system logs: `logs/system_activity.log`

3. **Database Changes**:
   - Create migration file in `database/migrations/`
   - Test migration on development database
   - Document changes in migration file header
   - Update relevant documentation

### Common Issues & Solutions

#### Issue: "Access denied for user 'root'"
```bash
# Solution: Verify password
/opt/lampp/bin/mysql -u root -padmin123

# If still fails, reset MySQL password in XAMPP
sudo /opt/lampp/lampp security
```

#### Issue: "Database does not exist"
```bash
# Solution: Create database
/opt/lampp/bin/mysql -u root -padmin123 -e "CREATE DATABASE IF NOT EXISTS KingsWayAcademy;"
```

#### Issue: "Can't connect to MySQL server"
```bash
# Solution: Ensure XAMPP MySQL is running
sudo /opt/lampp/lampp status
sudo /opt/lampp/lampp start
```

### Security Notes

⚠️ **Important**: These credentials are for **DEVELOPMENT ONLY**

For production deployment:
- Change default MySQL root password
- Create dedicated database user with limited privileges
- Use environment variables for sensitive data
- Enable SSL/TLS for database connections
- Keep `DEBUG` mode disabled

### Testing Database Connection

```php
<?php
// Test database connection
$host = 'localhost';
$dbname = 'KingsWayAcademy';
$user = 'root';
$pass = 'admin123';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    echo "Database connection successful!";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>
```

### Useful Database Queries

```sql
-- Check all tables
USE KingsWayAcademy;
SHOW TABLES;

-- Check workflow definitions
SELECT code, name, category FROM workflow_definitions;

-- Check workflow stages for inventory
SELECT ws.name, ws.sequence, ws.required_role 
FROM workflow_stages ws
JOIN workflow_definitions wd ON ws.workflow_id = wd.id
WHERE wd.code = 'inventory_management'
ORDER BY ws.sequence;

-- Check active workflow instances
SELECT wi.id, wd.name, wi.current_stage, wi.status
FROM workflow_instances wi
JOIN workflow_definitions wd ON wi.workflow_id = wd.id
WHERE wi.status IN ('pending', 'in_progress')
ORDER BY wi.started_at DESC;
```

---

## RBAC System Documentation

The RBAC (Role-Based Access Control) system has been completely normalized. For comprehensive documentation on:

- Normalized database schema
- Available procedures and functions
- PHP usage examples
- RBAC queries
- Migration files

See: **[RBAC System Documentation](./PROJECT_CONFIG_RBAC.md)**

### Quick Status

- ✅ **Status**: Fully Normalized (December 2025)
- **Total Permissions**: 4,456
- **Active Roles**: 29 (+ 1 legacy Admin)
- **Role-Permission Mappings**: 16,213
- **Helper Procedures**: 7 procedures + 1 function
- **Audit Logging**: ✅ Complete

---

**Last Updated**: 6 December 2025  
**Maintained By**: Development Team  
**For Support**: Contact system administrator

## System Architecture Overview

**Kingsway** is a comprehensive **PHP-based school management system** with REST API backend and multi-user dashboard frontend. The system manages academics, finance, boarding, communications, and operations for an academy.

### Core Components

1. **API Layer** (`api/` directory)

   - RESTful endpoints for all functionality
   - Router-based dispatch in `api/router/` using `$_GET['route']` pattern
   - Middleware stack for authentication (`auth.php`) and request validation
   - Service pattern in `api/services/` for business logic (e.g., `StudentService`, `UserService`)
   - Database abstraction via `database/Database.php` singleton

2. **Frontend Pages** (`pages/` directory)

   - Dashboard pages (manage_academics.php, manage_finance.php, manage_boarding.php, etc.)
   - Each page typically includes header/auth checks and calls API endpoints via AJAX
   - Component reuse through `components/` directory (forms, cards, tables, modals)
   - Layout wrapper in `layouts/app_layout.php`

3. **Database Layer** (`database/Database.php`)

   - PDO-based MySQL abstraction
   - Singleton pattern: `Database::getInstance()`
   - Methods: `executeQuery()`, `fetchAll()`, `fetchOne()`, `insert()`, `update()`, `delete()`
   - Returns associative arrays; errors throw exceptions

4. **Configuration** (`config/config.php`)

   - Database credentials, email settings, system configuration
   - NOT committed to version control (add to `.gitignore`)

5. **Authentication System**
   - Session-based with role-based access control (RBAC)
   - Roles: admin, teacher, accountant, student, parent, boarding_officer, etc.
   - Protected pages check: `checkUserRole(['admin', 'teacher'])` in `config/permissions.php`
   - API endpoints validate authorization via `api/middleware/auth.php`

## Critical Developer Workflows

### Database Operations

```php
// Always use prepared statements via Database class
$db = Database::getInstance();

// Insert example (from UserService)
$db->insert('users', [
    'first_name' => $firstName,
    'email' => $email,
    'password' => password_hash($password, PASSWORD_BCRYPT),
    'role' => $role,
    'created_at' => date('Y-m-d H:i:s')
]);

// Query example
$result = $db->executeQuery(
    "SELECT * FROM users WHERE email = ? AND status = ?",
    [$email, 'active']
);
$user = $db->fetchOne($result);
```

### API Response Format

All endpoints return JSON with consistent structure:

```php
// Success response
return json_encode([
    'success' => true,
    'message' => 'Operation completed',
    'data' => $result
]);

// Error response
http_response_code(400);
return json_encode([
    'success' => false,
    'message' => 'Error description'
]);
```

### Adding New Pages/Features

1. Create route handler in `api/router/` (e.g., `manage_communications_router.php`)
2. Implement business logic in `api/services/` (e.g., `CommunicationService`)
3. Create frontend page in `pages/` (e.g., `manage_communications.php`)
4. Use existing components (`components/tables/`, `components/forms/`, `components/modals/`)
5. Wire AJAX calls to API endpoints in `js/pages/` or inline JavaScript

## Project-Specific Conventions

### File Organization

- **Pages requiring authentication**: Check role at top of file

  ```php
  session_start();
  if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teacher'])) {
      header('Location: /index.php');
      exit;
  }
  ```

- **API endpoints**: Named as `manage_X_router.php`, export functions like `handleCreate($data)`, `handleUpdate($id, $data)`, etc.

- **Services**: Encapsulate business logic; named as `XService.php`; methods should handle validation and error cases

- **Components**: Reusable HTML/CSS blocks in `components/` subdirectories; included via `include_once`

### Data Modeling Patterns

- **Users table**: Stores roles, email, password (hashed), status
- **Students/Staff**: Linked to users via foreign key
- **Academic year**: Central concept; most queries filtered by `academic_year_id`
- **Hierarchical data**: Classes → Forms → Students (typical school structure)

### JavaScript Patterns

- **Main entry**: `js/main.js` loads common utilities
- **Page-specific scripts**: `js/pages/` directory (e.g., `communications.js`)
- **API calls**: Centralized in `js/api.js` with `fetch` wrapper
  ```javascript
  // Example API call pattern
  fetch("/api/?route=communications&action=send", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(data),
  })
    .then((response) => response.json())
    .then((result) => {
      if (result.success) {
        // Handle success
      } else {
        alert("Error: " + result.message);
      }
    });
  ```

### Styling

- **Primary stylesheet**: `king.css` (school colors and custom utilities)
- **Bootstrap 5**: Used for responsive layouts (referenced in `layouts/app_layout.php`)
- **No SCSS/build process**: All CSS is vanilla; avoid adding complexity

## Integration Points & External Dependencies

- **Composer packages**: See `composer.json` (installed via `composer install`)
- **WhatsApp notifications**: Templates in `documantations/WHATSAPP_TEMPLATES.md`; integration in communication services
- **Email**: Configured in `config/config.php`; may require SMTP relay setup
- **File uploads**: Handled in `uploads/` directory; validate MIME types and sanitize names
- **QR codes**: Generated in `images/qr_codes/` for student identification

## Testing & Debugging

- **API testing**: Use `pages/api_explorer.php` for manual endpoint testing
- **Database debugging**: Query logs should be in `logs/` directory (if enabled)
- **Session debugging**: Check `$_SESSION` array for user context
- **CORS/Headers**: Note `api/test_headers.php` exists for debugging header issues

## Common Gotchas & Anti-Patterns

- **Don't bypass Database class**: Always use prepared statements to prevent SQL injection
- **Don't hardcode values**: Configuration belongs in `config/config.php`
- **Session state matters**: Always call `session_start()` at top of pages that need user context
- **API response format**: Must be JSON; inconsistent formats break frontend error handling
- **File permissions**: Ensure `logs/`, `uploads/`, and `temp/` directories are writable by web server
- **Database migrations**: Schema changes documented in `database/migrations/`; apply before deployment

## Key Files to Reference

| File                                   | Purpose                           |
| -------------------------------------- | --------------------------------- |
| `database/Database.php`                | All DB operations go through here |
| `api/middleware/auth.php`              | Token validation & role checks    |
| `config/permissions.php`               | RBAC definitions                  |
| `layouts/app_layout.php`               | Page shell with navigation        |
| `js/api.js`                            | Frontend API client               |
| `database/KingsWayAcademyDatabase.sql` | Schema reference                  |

---

# RBAC (Role-Based Access Control) System - Kingsway Academy

## Overview

The RBAC system has been normalized to remove denormalization and provide granular permission management.

**Status**: ✅ Fully Normalized (December 2025)

## Normalized Database Structure

### Core Tables

**1. permissions** (4,456 records)
- All system permissions extracted from JSON into individual records
- Columns: `id`, `code`, `description`, `entity`, `action`
- Indexed for fast permission lookups

**2. roles** (30 records)
- Role definitions with no JSON columns
- Columns: `id`, `name`, `description`, `created_at`, `updated_at`
- Previous denormalization removed (permissions now in `role_permissions`)

**3. role_permissions** (16,213 mappings)
- Junction table for normalized role-permission relationships
- Columns: `id`, `role_id`, `permission_id`, `created_at`
- Foreign keys ensure referential integrity
- UNIQUE constraint: `unique_role_permission (role_id, permission_id)`

**4. user_roles**
- Maps users to roles
- Columns: `id`, `user_id`, `role_id`, `created_at`
- UNIQUE constraint: `unique_user_role (user_id, role_id)`

**5. user_permissions**
- User-level permission overrides and grants
- Columns: `id`, `user_id`, `permission_id`, `permission_type` (ENUM: grant/deny/override), `reason`, `granted_by`, `expires_at`, `created_at`, `updated_at`
- Supports temporary permissions with expiration
- Supports delegation and audit trails

**6. role_form_permissions**
- Form-level access control
- Columns: `id`, `role_id`, `form_permission_id`, `action_type`, `can_delegate`, `created_at`, `updated_at`
- Supports form-level granularity

**7. permission_audit_log**
- Complete audit trail of all permission changes
- Tracks: actions (assign_role, revoke_role, grant_permission, deny_permission)
- Includes: changed_by, reason, changed_at timestamp

### Helper Views

- `v_user_permissions_effective` - Combines role-based + user-specific permissions
- `v_role_permission_summary` - Statistics on permissions by role/entity
- `v_delegatable_form_actions` - Shows which form actions can be delegated

## Permission Distribution

- **School Administrative Officer**: 3,120 permissions
- **Headteacher**: 1,599 permissions
- **Director/Owner**: 1,293 permissions
- **HOD - Talent Development**: 1,210 permissions
- **HOD - Food & Nutrition**: 1,015 permissions
- *(And 24 other roles)*

## Available Procedures & Functions

### Get User Permissions

```sql
CALL sp_get_user_permissions(user_id);
-- Returns: permission_id, permission_code, entity, action, description, source
```

### Check Single Permission (Function)

```sql
SELECT fn_has_permission(user_id, 'permission_code') as has_permission;
-- Returns: 1 (true) or 0 (false)
```

### Manage User Permissions

```sql
-- Grant permission to user
CALL sp_grant_permission(user_id, permission_code, reason, granted_by, expires_at, @success);

-- Deny permission to user
CALL sp_deny_permission(user_id, permission_code, reason, changed_by, @success);

-- Revoke permission from user
CALL sp_revoke_permission(user_id, permission_code, reason, changed_by, @success);
```

### Manage User Roles

```sql
-- Assign role to user
CALL sp_assign_role(user_id, role_name, assigned_by, reason, @success);

-- Revoke role from user
CALL sp_revoke_role(user_id, role_name, reason, changed_by, @success);
```

## PHP Usage Examples

### Get User's Effective Permissions

```php
<?php
// Using PDO
$pdo = new PDO("mysql:host=localhost;dbname=KingsWayAcademy", "root", "admin123");

// Get all permissions for a user
$stmt = $pdo->prepare("CALL sp_get_user_permissions(?)");
$stmt->execute([1]); // user_id = 1

$permissions = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $permissions[$row['permission_code']] = $row;
}
print_r($permissions);
?>
```

### Check if User Has Permission

```php
<?php
$userId = 1;
$permissionCode = 'manage_students_view';

$stmt = $pdo->prepare("SELECT fn_has_permission(?, ?) as has_perm");
$stmt->execute([$userId, $permissionCode]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result['has_perm']) {
    echo "User has permission";
} else {
    echo "User does not have permission";
}
?>
```

### Grant Permission to User

```php
<?php
$userId = 1;
$permissionCode = 'manage_students_edit';
$grantedBy = 2; // Admin user ID
$reason = "Promotion to new role";

$stmt = $pdo->prepare("CALL sp_grant_permission(?, ?, ?, ?, NULL, @success)");
$stmt->execute([$userId, $permissionCode, $reason, $grantedBy]);

// Check success
$success = $pdo->query("SELECT @success as success")->fetch()['success'];
echo $success ? "Permission granted" : "Failed to grant permission";
?>
```

### Assign Role to User

```php
<?php
$userId = 1;
$roleName = 'Headteacher';
$assignedBy = 2;
$reason = "New appointment";

$stmt = $pdo->prepare("CALL sp_assign_role(?, ?, ?, ?, @success)");
$stmt->execute([$userId, $roleName, $assignedBy, $reason]);

$success = $pdo->query("SELECT @success as success")->fetch()['success'];
echo $success ? "Role assigned" : "Failed to assign role";
?>
```

## RBAC Queries

### Get All Permissions for a Role

```sql
SELECT p.code, p.description, p.entity, p.action
FROM permissions p
JOIN role_permissions rp ON p.id = rp.permission_id
WHERE rp.role_id = (SELECT id FROM roles WHERE name = 'Headteacher')
ORDER BY p.entity, p.action;
```

### Find Users with Specific Permission

```sql
SELECT DISTINCT u.id, u.name
FROM users u
JOIN user_roles ur ON u.id = ur.user_id
JOIN role_permissions rp ON ur.role_id = rp.role_id
JOIN permissions p ON rp.permission_id = p.id
WHERE p.code = 'manage_students_view'
UNION
SELECT DISTINCT u.id, u.name
FROM users u
JOIN user_permissions up ON u.id = up.user_id
JOIN permissions p ON up.permission_id = p.id
WHERE p.code = 'manage_students_view'
  AND up.permission_type IN ('grant', 'override')
  AND (up.expires_at IS NULL OR up.expires_at > NOW());
```

### Check Permission Hierarchy by Entity

```sql
SELECT p.entity, COUNT(*) as permission_count
FROM permissions p
GROUP BY p.entity
ORDER BY permission_count DESC;
```

### View User's Permission Audit Trail

```sql
SELECT action, role_id, permission_id, changed_by, reason, changed_at
FROM permission_audit_log
WHERE user_id = 1
ORDER BY changed_at DESC
LIMIT 20;
```

## Migration Files Reference

- `rbac_schema_clean.sql` - Creates normalized tables and views
- `populate_normalized_rbac.php` - Populates permissions and role-permission mappings
- `rbac_procedures_final.sql` - Creates helper procedures and functions

## Running RBAC Migrations

```bash
# 1. Create normalized schema
/opt/lampp/bin/mysql -u root -padmin123 KingsWayAcademy < database/migrations/rbac_schema_clean.sql

# 2. Populate permissions from JSON source
php scripts/populate_normalized_rbac.php

# 3. Create helper procedures
/opt/lampp/bin/mysql -u root -padmin123 KingsWayAcademy < database/migrations/rbac_procedures_final.sql
```

## Key Features

- ✅ Normalized schema - no JSON denormalization
- ✅ Granular permissions - 4,456+ individual permissions
- ✅ Role-based access - 30 predefined roles with 16,213 mappings
- ✅ User overrides - Grant/deny permissions at user level
- ✅ Temporary permissions - Automatic expiration support
- ✅ Audit logging - Complete change tracking
- ✅ Fast lookups - Indexed for performance
- ✅ Delegation support - Can delegate form actions
- ✅ Entity/action hierarchy - Organized permission structure

---


PHP Dashboard (static HTML with element IDs)
    ↓
JS Helper (sysAdminDashboardController.init())
    ↓
api.js (API.dashboard.* methods)
    ↓
Backend Controllers (SystemController.php)
    ↓
Database


**Last Updated**: 26 December 2025 | Created for AI Agent Productivity

**RBAC Status**: ✅ Fully Normalized   
**Maintained By**: Development Team