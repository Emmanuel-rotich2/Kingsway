# Updated Users API Endpoints Documentation

## Overview

The Users API has been updated to support the normalized RBAC schema with comprehensive permission management, querying, and role management capabilities. All endpoints now leverage the 10 new database procedures and 1 function created for efficient permission handling.

---

## Table of Contents

1. [User CRUD Operations](#user-crud-operations)
2. [Authentication Endpoints](#authentication-endpoints)
3. [Permission Management](#permission-management)
4. [Role Management](#role-management)
5. [Permission Querying](#permission-querying)
6. [Query Helpers](#query-helpers)
7. [Bulk Operations](#bulk-operations)

---

## User CRUD Operations

### List All Users
```
GET /api/users
Response:
{
  "success": true,
  "data": [
    { "id": 1, "username": "admin", "email": "admin@example.com", ... },
    ...
  ]
}
```

### Get Single User
```
GET /api/users/{id}
Response:
{
  "success": true,
  "data": { "id": 1, "username": "admin", "email": "admin@example.com", ... }
}
```

### Create New User
```
POST /api/users
Body:
{
  "username": "newuser",
  "email": "newuser@example.com",
  "password": "secure_password",
  "first_name": "John",
  "last_name": "Doe",
  "status": "active"
}
Response:
{
  "success": true,
  "data": { "id": 2, "username": "newuser", ... }
}
```

### Update User
```
PUT /api/users/{id}
Body:
{
  "email": "newemail@example.com",
  "first_name": "Jane",
  "status": "inactive"
}
Response:
{
  "success": true,
  "data": { "id": 1, "username": "admin", "email": "newemail@example.com", ... }
}
```

### Delete User
```
DELETE /api/users/{id}
Response:
{
  "success": true,
  "data": { "id": 1, "deleted": true }
}
```

---

## Authentication Endpoints

### User Login
```
POST /api/users/login
Body:
{
  "username": "admin",
  "password": "admin_password"
}
Response:
{
  "success": true,
  "data": {
    "id": 1,
    "username": "admin",
    "email": "admin@example.com",
    "first_name": "Admin",
    "last_name": "User",
    "roles": [ { "id": 1, "name": "Administrator", ... } ],
    "permissions": [ "users.view", "users.create", ... ],
    "status": "active"
  }
}
```

### Get User Profile
```
GET /api/users/profile/get
Response:
{
  "success": true,
  "data": {
    "id": 1,
    "profile": { ... },
    "roles": [ ... ],
    "permissions": [ ... ]
  }
}
```

### Change Password
```
PUT /api/users/password/change
Body:
{
  "old_password": "current_password",
  "new_password": "new_password"
}
Response:
{
  "success": true,
  "data": { "id": 1, "changed": true }
}
```

### Reset Password
```
POST /api/users/password/reset
Body:
{
  "token": "reset_token_from_email",
  "new_password": "new_password"
}
Response:
{
  "success": true,
  "data": { "reset": true }
}
```

---

## Permission Management

### Assign Permission to User (Direct)
```
POST /api/users/{id}/permissions/assign
Body:
{
  "permission_code": "users.create",
  "permission_type": "grant",  // grant | deny | override
  "expires_at": "2024-12-31 23:59:59",  // optional
  "reason": "Temporary access for training",  // optional
  "granted_by": "admin@example.com"  // optional
}
Response:
{
  "success": true,
  "data": {
    "user_id": 1,
    "permission_code": "users.create",
    "permission_type": "grant",
    "expires_at": "2024-12-31 23:59:59",
    "assigned": true
  }
}
```

### Revoke Permission from User
```
DELETE /api/users/{id}/permissions/{permission_id}
Response:
{
  "success": true,
  "data": { "user_id": 1, "permission_id": 15, "revoked": true }
}
```

### Assign Role to User
```
POST /api/users/{id}/role/assign
Body:
{
  "role_id": 2
}
Response:
{
  "success": true,
  "data": { "id": 1, "role_assigned": true }
}
```

### Revoke Role from User
```
DELETE /api/users/{id}/role/{role_id}
Response:
{
  "success": true,
  "data": { "user_id": 1, "role_id": 2, "revoked": true }
}
```

---

## Role Management

### Get All Available Roles
```
GET /api/users/roles/get
Response:
{
  "success": true,
  "data": [ { "id": 1, "name": "Administrator", ... }, ... ]
}
```

### Get User's Roles (Basic)
```
GET /api/users/{id}/role/main
Response:
{
  "success": true,
  "data": { "id": 1, "main_role": { "id": 1, "name": "Administrator", ... } }
}
```

### Get User's Roles (Detailed with Permission Counts)
```
GET /api/users/{id}/roles/detailed
Response:
{
  "success": true,
  "data": [
    {
      "role_id": 1,
      "role_name": "Administrator",
      "permission_count": 156,
      "description": "Full system access"
    },
    ...
  ],
  "count": 2
}
```

### Get Extra Roles
```
GET /api/users/{id}/role/extra
Response:
{
  "success": true,
  "data": { "id": 1, "extra_roles": [ { "id": 2, "name": "Moderator", ... } ] }
}
```

---

## Permission Querying

### Get All Available Permissions
```
GET /api/users/permissions/get
Response:
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "users.create",
      "entity": "users",
      "action": "create",
      "description": "Create new users"
    },
    ...
  ]
}
```

### Get User's Effective Permissions (Role + Direct)
**Most commonly used endpoint - includes role-based AND direct permissions**

```
GET /api/users/{id}/permissions/effective
Response:
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "users.create",
      "entity": "users",
      "action": "create",
      "description": "Create new users",
      "source": "role",
      "permission_type": "grant"
    },
    {
      "id": 15,
      "code": "reports.export",
      "entity": "reports",
      "action": "export",
      "description": "Export reports",
      "source": "direct",
      "permission_type": "grant",
      "expires_at": "2024-12-31 23:59:59"
    }
  ],
  "count": 157
}
```

### Get User's Direct Permissions Only
```
GET /api/users/{id}/permissions/direct
Response:
{
  "success": true,
  "data": [
    {
      "id": 15,
      "code": "reports.export",
      "permission_type": "grant",
      "expires_at": "2024-12-31 23:59:59",
      "reason": "Temporary export access",
      "granted_by": "admin@example.com"
    }
  ],
  "count": 1
}
```

### Get User's Denied Permissions
```
GET /api/users/{id}/permissions/denied
Response:
{
  "success": true,
  "data": [
    {
      "id": 25,
      "code": "users.delete",
      "reason": "User cannot delete accounts",
      "granted_by": "admin@example.com"
    }
  ],
  "count": 1
}
```

### Get Permissions Organized by Entity
```
GET /api/users/{id}/permissions/by-entity
Response:
{
  "success": true,
  "data": [
    {
      "entity": "users",
      "entity_description": "User Management",
      "permissions": [
        { "id": 1, "code": "users.create", "action": "create" },
        { "id": 2, "code": "users.view", "action": "view" },
        { "id": 3, "code": "users.update", "action": "update" },
        { "id": 4, "code": "users.delete", "action": "delete" }
      ]
    },
    {
      "entity": "roles",
      "entity_description": "Role Management",
      "permissions": [ ... ]
    }
  ]
}
```

### Get Permission Summary Statistics
```
GET /api/users/{id}/permissions/summary
Response:
{
  "success": true,
  "data": [
    {
      "total_permissions": 157,
      "role_based_permissions": 150,
      "direct_permissions": 10,
      "denied_permissions": 1,
      "overridden_permissions": 0,
      "temporary_permissions": 2
    }
  ]
}
```

### Check Single Permission
```
POST /api/users/{id}/permissions/check
Body:
{
  "permission_code": "users.create"
}
Response:
{
  "success": true,
  "data": {
    "has_permission": true,
    "permission_code": "users.create"
  }
}
```

### Check Multiple Permissions
```
POST /api/users/{id}/permissions/check-multiple
Body:
{
  "permission_codes": ["users.create", "users.delete", "reports.view"]
}
Response:
{
  "success": true,
  "data": {
    "users.create": true,
    "users.delete": false,
    "reports.view": true
  }
}
```

---

## Query Helpers

### Get All Users with Specific Permission
```
GET /api/users/with-permission/{permission_code}

Example:
GET /api/users/with-permission/reports.export

Response:
{
  "success": true,
  "data": [
    {
      "user_id": 1,
      "username": "admin",
      "email": "admin@example.com",
      "first_name": "Admin",
      "last_name": "User"
    },
    {
      "user_id": 5,
      "username": "reporter",
      "email": "reporter@example.com",
      "first_name": "Jane",
      "last_name": "Reporter"
    }
  ],
  "count": 2
}
```

### Get All Users with Specific Role
```
GET /api/users/with-role/{role_name}

Example:
GET /api/users/with-role/Administrator

Response:
{
  "success": true,
  "data": [
    {
      "user_id": 1,
      "username": "admin",
      "email": "admin@example.com",
      "first_name": "Admin",
      "last_name": "User"
    }
  ],
  "count": 1,
  "role": "Administrator"
}
```

### Get Users with Multiple Roles
```
GET /api/users/with-multiple-roles
Response:
{
  "success": true,
  "data": [
    {
      "user_id": 2,
      "username": "poweruser",
      "email": "poweruser@example.com",
      "role_count": 3,
      "roles": ["Administrator", "Moderator", "Editor"]
    }
  ],
  "count": 1
}
```

### Get Users with Temporary Permissions Expiring Soon
```
GET /api/users/with-temporary-permissions
Response:
{
  "success": true,
  "data": [
    {
      "user_id": 3,
      "username": "temp_user",
      "email": "temp@example.com",
      "permission_code": "reports.export",
      "expires_at": "2024-01-15 23:59:59",
      "days_until_expiry": 5,
      "reason": "Temporary export access"
    },
    {
      "user_id": 4,
      "username": "intern",
      "email": "intern@example.com",
      "permission_code": "documents.view",
      "expires_at": "2024-01-20 23:59:59",
      "days_until_expiry": 10
    }
  ],
  "count": 2
}
```

---

## Bulk Operations

### Bulk Assign Permissions to User
```
POST /api/users/{id}/permissions/bulk-assign
Body:
{
  "permissions": [
    {
      "permission_code": "users.create",
      "permission_type": "grant"
    },
    {
      "permission_code": "reports.export",
      "permission_type": "grant",
      "expires_at": "2024-12-31"
    },
    {
      "permission_code": "users.delete",
      "permission_type": "deny",
      "reason": "Security restriction"
    }
  ]
}
Response:
{
  "success": true,
  "data": {
    "user_id": 1,
    "assigned_count": 3,
    "total_attempted": 3
  }
}
```

### Bulk Revoke Permissions from User
```
POST /api/users/{id}/permissions/bulk-revoke
Body:
{
  "permission_ids": [1, 2, 3]
}
Response:
{
  "success": true,
  "data": {
    "user_id": 1,
    "revoked_count": 3,
    "total_attempted": 3
  }
}
```

### Bulk Assign Roles to User
```
POST /api/users/{id}/roles/bulk-assign
Body:
{
  "role_ids": [1, 2, 3]
}
Response:
{
  "success": true,
  "data": {
    "user_id": 1,
    "assigned_roles": 3,
    "total_attempted": 3
  }
}
```

### Bulk Revoke Roles from User
```
POST /api/users/{id}/roles/bulk-revoke
Body:
{
  "role_ids": [2, 3]
}
Response:
{
  "success": true,
  "data": {
    "user_id": 1,
    "revoked_roles": 2,
    "total_attempted": 2
  }
}
```

### Bulk Assign Users to Role
```
POST /api/users/bulk-assign-to-role
Body:
{
  "role_id": 2,
  "user_ids": [3, 4, 5]
}
Response:
{
  "success": true,
  "data": {
    "role_id": 2,
    "assigned_users": 3,
    "total_attempted": 3
  }
}
```

### Bulk Revoke Users from Role
```
POST /api/users/bulk-revoke-from-role
Body:
{
  "role_id": 2,
  "user_ids": [3, 4]
}
Response:
{
  "success": true,
  "data": {
    "role_id": 2,
    "revoked_users": 2,
    "total_attempted": 2
  }
}
```

### Bulk Assign Users to Permission
```
POST /api/users/bulk-assign-permission
Body:
{
  "permission_id": 15,
  "user_ids": [3, 4, 5],
  "permission_type": "grant"
}
Response:
{
  "success": true,
  "data": {
    "permission_id": 15,
    "assigned_users": 3,
    "total_attempted": 3
  }
}
```

### Bulk Revoke Users from Permission
```
POST /api/users/bulk-revoke-permission
Body:
{
  "permission_id": 15,
  "user_ids": [3, 4]
}
Response:
{
  "success": true,
  "data": {
    "permission_id": 15,
    "revoked_users": 2,
    "total_attempted": 2
  }
}
```

---

## Permission Precedence

The system implements the following permission precedence (highest to lowest):

1. **DENY** - Explicitly blocks a permission (overrides everything)
2. **OVERRIDE** - Specific override setting for a permission
3. **GRANT** - Direct permission grant
4. **ROLE-BASED** - Permissions from assigned roles

### Example Scenario:
- User has role "Editor" (which includes "documents.delete" permission)
- User is explicitly DENIED "documents.delete" permission
- **Result**: User cannot delete documents (DENY takes precedence)

---

## Error Responses

All endpoints return standard error responses:

```json
{
  "success": false,
  "error": "User ID is required"
}
```

### Common Status Codes:
- **200**: Successful operation
- **400**: Bad request (missing parameters, invalid data)
- **401**: Unauthorized
- **403**: Forbidden (insufficient permissions)
- **404**: Resource not found
- **500**: Server error

---

## Implementation Details

### Database Procedures Used
- `sp_user_get_effective_permissions()` - For effective permissions queries
- `sp_user_get_denied_permissions()` - For denied permissions queries
- `sp_user_get_permission_summary()` - For statistics
- `sp_user_get_roles_detailed()` - For detailed role queries
- `sp_users_with_permission()` - For finding users with permission
- `sp_users_with_role()` - For finding users with role
- `sp_users_with_multiple_roles()` - For finding power users
- `sp_users_with_temporary_permissions()` - For expiring permissions
- `sp_user_get_permissions_by_entity()` - For entity-based queries
- `fn_user_has_permission()` - For boolean permission checks

### Manager Classes
- `UserPermissionManager` - Handles all permission operations
- `UserRoleManager` - Handles all role operations
- `UsersAPI` - Facade that delegates to managers
- `UsersController` - REST endpoints routing

---

## Example Usage Scenarios

### Scenario 1: Granting Temporary Export Permission
```bash
POST /api/users/5/permissions/assign
{
  "permission_code": "reports.export",
  "permission_type": "grant",
  "expires_at": "2024-06-30 23:59:59",
  "reason": "Quarterly report generation",
  "granted_by": "admin@example.com"
}
```

### Scenario 2: Revoking Access Due to Security Concern
```bash
POST /api/users/3/permissions/assign
{
  "permission_code": "users.delete",
  "permission_type": "deny",
  "reason": "Account flagged for unusual activity"
}
```

### Scenario 3: Auditing User Access
```bash
GET /api/users/2/permissions/summary
GET /api/users/2/permissions/by-entity
GET /api/users/2/roles/detailed
```

### Scenario 4: Finding Users for Bulk Operations
```bash
GET /api/users/with-permission/reports.export
GET /api/users/with-role/Editor
GET /api/users/with-temporary-permissions
```

---

## Notes

- All timestamp fields use format: `YYYY-MM-DD HH:MM:SS`
- Permission codes follow pattern: `entity.action` (e.g., `users.create`)
- The system automatically handles permission inheritance from roles
- Temporary permissions are automatically managed by the system
- All operations are logged for audit purposes

