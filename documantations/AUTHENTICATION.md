# Frontend Authentication & Authorization System

## Overview

This document explains the complete frontend authentication and authorization system implemented in the Kingsway application.

## Architecture

### Components

1. **AuthContext** (`js/api.js`)
   - Manages user state, roles, and permissions
   - Persists authentication data to localStorage
   - Provides permission checking functions
   - Auto-initializes on page load

2. **Permission Validation** (`js/api.js`)
   - `validatePermission()`: Checks permission before API calls
   - `getRequiredPermission()`: Maps endpoints to permission requirements
   - `ENDPOINT_PERMISSIONS`: Configuration of permission rules
   - Integrated into `apiCall()` function

3. **Helper Functions** (`js/auth-utils.js`)
   - UI permission checks: `canUser()`, `canUserAny()`, `canUserAll()`
   - User information: `getCurrentUser()`, `getUserDisplayName()`, `getUserRoles()`
   - UI utilities: `toggleElementByPermission()`, `requirePermissionOnClick()`
   - Debugging: `debugAuthState()`

## Authentication Flow

### Login

```
User enters credentials
    ↓
POST /api/users/login
    ↓
Backend validates & returns JWT token + user data with permissions array
    ↓
Frontend stores:
  - token → localStorage['token']
  - user_data → localStorage['user_data']
  - permissions (deduplicated) → localStorage['user_permissions']
  - roles → localStorage['user_roles']
    ↓
AuthContext initialized with user context
    ↓
Redirect to home.php
```

### Data Structure

**Login Response:**
```json
{
  "status": "success",
  "message": "Login successful",
  "data": {
    "token": "eyJ0eXAiOiJKV1Q...",
    "user": {
      "id": 3,
      "username": "test_director_owner",
      "email": "director@school.ac.ke",
      "full_name": "Director Owner",
      "roles": [
        {
          "id": 3,
          "name": "Director/Owner",
          "description": "School director/owner - reports, approvals, payroll"
        }
      ],
      "permissions": [
        {
          "permission_id": 859,
          "permission_code": "attendance_staff_annotate",
          "entity": "attendance_staff",
          "action": "annotate",
          "description": "Attendance staff annotate",
          "source": "role",
          "role_name": "Director/Owner",
          "permission_type": null,
          "expires_at": null
        },
        // ... 2500+ more permissions
      ]
    }
  }
}
```

**LocalStorage Structure:**
```javascript
localStorage['token']              // JWT token for Authorization header
localStorage['user_data']          // User object JSON
localStorage['user_permissions']   // Array of unique permission codes
localStorage['user_roles']         // Array of role names
```

## Permission Model

### Permission Codes

Permissions follow an `ENTITY_ACTION` naming pattern:

- **Example codes:**
  - `students_view` - View students
  - `students_create` - Create new student
  - `students_update` - Edit student info
  - `students_delete` - Delete student
  - `finance_approve` - Approve financial transactions
  - `attendance_annotate` - Add notes to attendance

### Deduplication

The login response contains **2,586 permission objects** but only **~1,300 unique permission codes** because:
- Each permission appears twice: once from role assignment, once from grant
- Frontend deduplicates using a Set of permission codes
- Reduces memory usage and improves lookup performance

## API Permission Checking

### Before Request

Every API call is validated BEFORE sending:

```javascript
// Example: POST /api/students/student
apiCall('/students/student', 'POST', studentData)
  ↓
validatePermission('/students/student', 'POST')
  ↓
getRequiredPermission('/students/student', 'POST')
  ↓
Check ENDPOINT_PERMISSIONS['students/student'] = { GET: 'students_view', POST: 'students_create', ... }
  ↓
AuthContext.hasPermission('students_create')
  ↓
true/false
  ↓
If false: Error thrown, request blocked, user notified
If true: Request proceeds normally
```

### Endpoint Permission Configuration

**File:** `js/api.js` → `ENDPOINT_PERMISSIONS` object

```javascript
ENDPOINT_PERMISSIONS = {
  '/users/login': null,                    // No permission required
  '/users/user': {
    'GET': 'users_view',
    'POST': 'users_create',
    'PUT': 'users_update',
    'DELETE': 'users_delete'
  },
  '/students/student': {
    'GET': 'students_view',
    'POST': 'students_create',
    'PUT': 'students_update',
    'DELETE': 'students_delete'
  },
  // ... etc
}
```

### Adding New Permission Rules

To protect a new endpoint, add entry to `ENDPOINT_PERMISSIONS`:

```javascript
'/newmodule/action': {
  'GET': 'newmodule_view',
  'POST': 'newmodule_create',
  'PUT': 'newmodule_update',
  'DELETE': 'newmodule_delete'
}
```

Or for simple permissions (same for all methods):

```javascript
'/maintenance/logs': 'maintenance_view'
```

## Frontend Permission Checks

### In JavaScript

```javascript
// Check single permission
if (AuthContext.hasPermission('students_create')) {
  // Show create button
}

// Check multiple (OR logic)
if (AuthContext.hasAnyPermission(['students_create', 'students_import'])) {
  // Show bulk operation buttons
}

// Check multiple (AND logic)
if (AuthContext.hasAllPermissions(['finance_view', 'finance_approve'])) {
  // Show approval panel
}

// Get current user info
const user = AuthContext.getUser();
console.log(user.username, user.email);

// Get roles
const roles = AuthContext.getRoles();
if (roles.includes('Director/Owner')) {
  // Show admin features
}
```

### Using Helper Functions

`auth-utils.js` provides convenient shorthand:

```javascript
// Much shorter!
if (canUser('students_create')) { }
if (canUserAny(['students_create', 'students_import'])) { }
if (canUserAll(['finance_view', 'finance_approve'])) { }
if (isUserRole('Director/Owner')) { }

// User info
console.log(getUserDisplayName());  // "John Director"
console.log(getUserEmail());         // "john@school.ac.ke"
console.log(getUserRoles());         // ["Director/Owner"]
```

### In HTML with data-permission

HTML elements can automatically hide/disable based on permissions:

```html
<!-- Show only if user can create students -->
<button id="btnCreate" data-permission="students_create">
  Create Student
</button>

<!-- Show only if user has ANY of these permissions -->
<div data-permission="finance_create, finance_import, finance_export">
  Finance Operations
</div>

<!-- Show only if user has ALL of these permissions -->
<div data-permission="finance_view, finance_approve" data-permission-all="true">
  Finance Approval Panel
</div>
```

**Note:** Requires including `auth-utils.js` which auto-initializes on page load.

### Dynamic UI Updates

```javascript
// Show/hide element based on permission
toggleElementByPermission('btnDelete', 'students_delete');

// Add permission check to button click
requirePermissionOnClick(
  'btnApprove',
  'finance_approve',
  approveTransaction,
  'approve this transaction'
);

// Show permission denied notification
showPermissionDenied('delete this record');
```

## Authentication Flow in Pages

### 1. Login Page (`index.php`)

```html
<script src="js/api.js"></script>

<form id="loginForm">
  <input type="text" id="username" />
  <input type="password" id="password" />
  <button onclick="API.auth.login(
    document.getElementById('username').value,
    document.getElementById('password').value
  )">Login</button>
</form>
```

### 2. Protected Pages (`home.php`, etc.)

```html
<script src="js/api.js"></script>
<script src="js/auth-utils.js"></script>

<!-- Check authentication on page load -->
<script>
  if (!AuthContext.isAuthenticated()) {
    window.location.href = '/Kingsway/index.php';
  }
</script>

<!-- Show user info -->
<div>
  Welcome, <span id="userName"></span>!
  <span id="userRole"></span>
</div>

<script>
  document.getElementById('userName').textContent = getUserDisplayName();
  document.getElementById('userRole').textContent = '(' + getUserPrimaryRole() + ')';
</script>

<!-- Show buttons conditionally -->
<button id="btnCreate" data-permission="students_create">Create</button>
<button id="btnEdit" data-permission="students_update">Edit</button>
<button id="btnDelete" data-permission="students_delete">Delete</button>
```

### 3. API Calls with Auto Permission Check

```javascript
// Just call API normally - permission check happens automatically
async function createStudent(data) {
  try {
    const result = await API.students.create(data);
    console.log('Student created:', result);
  } catch (error) {
    // If permission denied, error.code === 'PERMISSION_DENIED'
    // User already shown notification
  }
}
```

## Security Notes

### Frontend Validation ✅
- Checks permissions BEFORE sending requests
- Hides/disables UI elements without permission
- Provides better user experience (no wasted network calls)
- Prevents accidental unauthorized actions

### Backend Validation ⚠️
**CRITICAL:** Frontend checks are NOT security!
- Backend MUST validate permissions on every request
- Frontend checks are convenience only
- Never trust frontend validation for security
- Backend should refuse unauthorized requests with 403 Forbidden

### localStorage Security
- JWT token stored in localStorage (not httpOnly for frontend access)
- For production: Consider using httpOnly cookies + CSRF protection
- Permissions array stored in localStorage for quick UI checks
- User is responsible for logging out (clears localStorage)

## Debugging

### View Authentication State

```javascript
// Log everything
debugAuthState();

// Or access directly
console.log(AuthContext.getUser());
console.log(AuthContext.getPermissions());
console.log(AuthContext.getRoles());
```

### Check Permission Before Action

```javascript
const hasPermission = canUser('students_delete');
console.log('Can delete student?', hasPermission);
```

### Test Permission Check

```javascript
// This will show error notification and block request
await API.students.delete(123);  // If user lacks 'students_delete'
```

## Logout

```javascript
// Automatically clears all auth data
await API.auth.logout();
// Redirects to index.php
```

## Complete Permission Code Reference

### Students
- `students_view`
- `students_create`
- `students_update`
- `students_delete`
- `students_import`
- `students_export`
- `students_medical_*`
- `students_discipline_*`

### Finance
- `finance_view`
- `finance_create`
- `finance_update`
- `finance_approve`
- `finance_payroll_*`
- `finance_budget_*`
- `finance_fees_*`

### Staff
- `staff_view`
- `staff_create`
- `staff_update`
- `staff_delete`
- `staff_leave_*`
- `staff_performance_*`

### Attendance
- `attendance_view`
- `attendance_create`
- `attendance_update`
- `attendance_approve`
- `attendance_student_*`
- `attendance_staff_*`

### Academic
- `academic_view`
- `academic_create`
- `academic_update`
- `academic_curriculum_*`
- `academic_promotion_*`
- `academic_results_*`

### And more for: Users, Activities, Admission, Communications, Inventory, Transport, Schedules, Reports, System, School Config, Maintenance

## Files Involved

| File | Purpose |
|------|---------|
| `js/api.js` | Core auth context, permission validation, API calls |
| `js/auth-utils.js` | Helper functions for UI permission checks |
| `js/main.js` | Page-specific initialization |
| `layouts/app_layout.php` | Should include auth scripts |
| `home.php` | Dashboard with permission-based UI |
| All protected pages | Use `canUser()` to show/hide features |

## Troubleshooting

### "Access Denied" notification on button click
- User lacks required permission
- Check `ENDPOINT_PERMISSIONS` in `api.js` for endpoint
- Verify permission code matches backend roles/permissions
- Run `debugAuthState()` to see actual permissions

### Permissions not loading after login
- Check browser console for errors during login
- Verify login response includes permissions array
- Check localStorage for `user_permissions` key
- Refresh page to reinitialize AuthContext

### Permission check always returns false
- Verify permission code is correct (e.g., `students_create` not `student_create`)
- Check if user's role includes that permission
- Run `canUser('students_view')` to verify system works
- Check backend returns permissions in login response

### Buttons visible for users who shouldn't see them
- Ensure `auth-utils.js` is loaded
- Check `data-permission` attribute spelling
- Verify permission code is correct
- Run `initializePermissionUI()` if elements added dynamically
