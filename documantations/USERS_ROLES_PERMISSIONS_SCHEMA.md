# Users, Roles, and Permissions Schema Documentation

## Entity Relationship Diagram

```
┌─────────────┐
│   USERS     │
├─────────────┤
│ id (PK)     │
│ username    │
│ email       │
│ first_name  │
│ last_name   │
│ password    │
│ role_id (FK)├──────────┐
│ status      │          │
└─────────────┘          │
                         │
                    ┌────▼────────────┐
                    │  ROLES          │
                    ├─────────────────┤
                    │ id (PK)         │
                    │ name            │
                    │ description     │
                    │ permissions     │
                    │ (JSON)          │
                    └─────────────────┘
                             │
     ┌───────────────────────┼───────────────────────┐
     │                       │                       │
┌────▼──────────────┐   ┌────▼──────────────────┐   ┌────▼──────────────────┐
│  USER_ROLES       │   │  ROLE_FORM_           │   │  FORM_PERMISSIONS     │
│  (Many-to-Many)   │   │  PERMISSIONS          │   │  (Permission Types)   │
├───────────────────┤   ├───────────────────────┤   ├───────────────────────┤
│ id (PK)           │   │ id (PK)               │   │ id (PK)               │
│ user_id (FK)  ────┼───┤ role_id (FK)      ────┼───┤ form_code             │
│ role_id (FK)      │   │ form_permission_id ────┼───┤ form_name             │
│ created_at        │   │ allowed_actions(JSON) │   │ description           │
└───────────────────┘   └───────────────────────┘   └───────────────────────┘
     │
     └─────────┬─────────┬─────────┐
               │         │         │
        ┌──────▼──┐ ┌────▼────┐ ┌─▼─────────────┐
        │ USER_   │ │RECORD_  │ │PERMISSION_    │
        │PERMS    │ │PERMS    │ │DELEGATIONS    │
        └─────────┘ └─────────┘ └───────────────┘

## Key Tables and Fields

### 1. USERS
- **Primary Key**: id (unsigned int)
- **Unique**: username, email
- **Foreign Key**: role_id → roles.id
- **Status**: enum('active', 'inactive', 'suspended', 'pending')
- **Note**: Every user must have a primary role_id

### 2. ROLES
- **Primary Key**: id (unsigned int)
- **Unique**: name
- **Description**: text (nullable)
- **Permissions**: longtext (JSON format, stores permission list)

### 3. USER_ROLES (Many-to-Many)
- **Purpose**: Assign additional roles to users (beyond the primary role_id in users table)
- **Composite**: (user_id, role_id) with duplicate prevention
- **Usage**: Users can have multiple secondary roles

### 4. FORM_PERMISSIONS
- **Purpose**: Define all available permissions/forms in the system
- **Fields**: form_code, form_name, description
- **Usage**: Reference for what permissions can be assigned

### 5. ROLE_FORM_PERMISSIONS
- **Purpose**: Link roles to specific form permissions with allowed actions
- **Foreign Keys**: role_id → roles.id, form_permission_id → form_permissions.id
- **Allowed Actions**: JSON array (e.g., ["grant", "view", "edit", "delete"])

### 6. USER_PERMISSIONS
- **Design**: String-based (permission_name varchar(100))
- **Purpose**: Direct user permissions (bypassing role permissions)
- **Fields**: user_id, permission_name

### 7. RECORD_PERMISSIONS
- **Purpose**: Fine-grained permissions at record/table level
- **Fields**: user_id, table_name, record_id, permission_type (grant/deny)
- **Use Case**: Control access to specific records or tables

## Three-Layer Permission Model

### Layer 1: Primary Role (Direct on users table)
```
Users.role_id → Roles.id
Every user MUST have a primary role assigned
```

### Layer 2: Secondary Roles (Many-to-Many)
```
Users → user_roles → roles
Users can have additional roles beyond primary role
```

### Layer 3: Direct Permissions
```
Users → user_permissions (direct permission_name)
Users → record_permissions (fine-grained access control)
```

## API Classes

### UsersAPI (UsersAPI.php)
Main entry point for all user operations
- User CRUD: create(), get(), list(), update(), delete()
- Authentication: login(), changePassword(), resetPassword()
- Profile: getProfile()
- Roles management: delegated to RoleManager & UserRoleManager
- Permissions: delegated to PermissionManager & UserPermissionManager

### RoleManager (RoleManager.php)
Manages roles as standalone entities
- CRUD: createRole(), getRole(), getAllRoles(), updateRole(), deleteRole()
- Permissions: assignPermission(), revokePermission()
- Bulk: bulkCreateRoles(), bulkUpdateRoles(), bulkDeleteRoles()

### UserRoleManager (UserRoleManager.php)
Manages user-role relationships (many-to-many)
- Assign: assignRole(), bulkAssignRoles()
- Revoke: revokeRole(), bulkRevokeRoles()
- Query: getUserRoles()
- Bulk User Assignment: bulkAssignUsersToRole(), bulkRevokeUsersFromRole()

### PermissionManager (PermissionManager.php)
Manages permissions at form/record level
- Query: getAllPermissions(), getPermissionsByUser(), getPermissionsByRole()
- Assign: assignPermissionToUser(), assignPermissionToRole()
- Revoke: revokePermissionFromUser(), revokePermissionFromRole()
- Bulk: bulkAssignPermissionsToUser(), bulkAssignPermissionsToRole(), etc.

### UserPermissionManager (UserPermissionManager.php)
Manages direct user permissions (currently uses user_permissions table)
- Assign: assignPermission(), bulkAssignPermissions()
- Revoke: revokePermission(), bulkRevokePermissions()
- Query: getUserPermissions()
- Bulk User Assignment: bulkAssignUsersToPermission(), bulkRevokeUsersFromPermission()

## Data Flow for Authorization

1. **User logs in** → UsersAPI.login()
   - Fetches user from users table
   - Gets user's primary role via users.role_id
   - Gets secondary roles via user_roles
   - Gets direct permissions via user_permissions

2. **Check permission** (in middleware/controller)
   - Collect all roles: [primary_role] + secondary_roles
   - For each role, get permissions from role_form_permissions
   - Add direct user permissions from user_permissions
   - Merge permission sets

3. **Enforce permission** (in business logic)
   - Check if user has required permission
   - Optionally check record-level permissions in record_permissions

## API Endpoint Mapping

### Users Endpoints
- `GET /api/users` → list all users
- `POST /api/users/user` → create user (requires role_id)
- `GET /api/users/user/{id}` → get single user
- `PUT /api/users/user/{id}` → update user
- `DELETE /api/users/user/{id}` → delete user
- `GET /api/users/profile-get` → get current user profile with roles/perms

### Roles Endpoints
- `GET /api/users/roles-get` → get all roles
- `GET /api/users/roles-get?role_id=X` → get specific role
- `POST /api/users/roles-bulk-create` → bulk create roles
- `PUT /api/users/roles-bulk-update` → bulk update roles
- `DELETE /api/users/roles-bulk-delete` → bulk delete roles

### User-Role Assignment Endpoints
- `POST /api/users/{id}/role-assign` → assign role to user
- `DELETE /api/users/{id}/role-revoke-from-user` → revoke role from user
- `POST /api/users/{id}/roles-bulk-assign-to-user` → bulk assign roles
- `DELETE /api/users/{id}/roles-bulk-revoke-from-user` → bulk revoke roles
- `POST /api/users/users-bulk-assign-to-role` → assign multiple users to role
- `DELETE /api/users/users-bulk-revoke-from-role` → revoke multiple users from role

### Permission Endpoints
- `GET /api/users/permissions-get` → get all permissions
- `POST /api/users/permissions-bulk-assign-to-role` → assign permissions to role
- `DELETE /api/users/permissions-bulk-revoke-from-role` → revoke permissions from role
- `POST /api/users/{id}/permissions-bulk-assign-to-user` → assign direct permissions to user
- `DELETE /api/users/{id}/permissions-bulk-revoke-from-user` → revoke direct permissions

## Implementation Notes

1. **Primary Role vs Secondary Roles**:
   - Primary role is required at user creation (role_id in users table)
   - Secondary roles are optional (added via user_roles table)

2. **Permission Assignment**:
   - Permissions can be assigned at role level (affects all users with that role)
   - Permissions can be assigned at user level (direct override)
   - Record-level permissions for fine-grained access

3. **Best Practices**:
   - Always validate role_id exists when creating users
   - Use bulk operations for performance with many records
   - Cache role and permission data for frequent access
   - Implement middleware check for permission before business logic
