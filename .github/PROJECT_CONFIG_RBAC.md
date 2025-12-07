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

**Last Updated**: 6 December 2025  
**RBAC Status**: ✅ Fully Normalized  
**Total Permissions**: 4,456  
**Active Roles**: 29 (+ 1 legacy Admin)  
**Role-Permission Mappings**: 16,213  
**Maintained By**: Development Team
