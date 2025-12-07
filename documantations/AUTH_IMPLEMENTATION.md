# Frontend Authentication & Authorization Implementation Summary

## What Was Built

A complete **client-side authentication and permission system** for the Kingsway school management application, providing:

1. **User Context Management** - Store and retrieve authenticated user info
2. **Permission Checking** - Validate permissions before API calls
3. **Role-Based Access Control** - Check user roles and permissions
4. **UI Integration** - Show/hide/disable UI elements based on permissions
5. **Developer Utilities** - Helper functions for common auth tasks

## Components Added

### 1. **AuthContext** (in `js/api.js`)

Core authentication state manager:

```javascript
// Authentication methods
AuthContext.setUser(userData, fullResponse)         // Store user after login
AuthContext.clearUser()                              // Clear on logout
AuthContext.isAuthenticated()                        // Check if logged in

// Permission checking
AuthContext.hasPermission('students_create')         // Check single permission
AuthContext.hasAnyPermission(['perm1', 'perm2'])     // OR check
AuthContext.hasAllPermissions(['perm1', 'perm2'])    // AND check

// Role checking
AuthContext.hasRole('Director/Owner')                // Check role
AuthContext.getRoles()                               // Get all roles

// User info
AuthContext.getUser()                                // Get user object
AuthContext.getPermissions()                         // Get permission array
AuthContext.getPermissionCount()                     // Get permission count
```

**Features:**
- Auto-initializes from localStorage on page load
- Deduplicates permissions (2,586 objects → 1,300 unique codes)
- Stores permissions as Set for O(1) lookup performance
- Persists user data, permissions, roles, and token to localStorage

### 2. **Permission Validation** (in `js/api.js`)

Pre-flight permission checking before API calls:

```javascript
// Permission mapping by endpoint
ENDPOINT_PERMISSIONS = {
  '/users/user': { GET: 'users_view', POST: 'users_create', PUT: 'users_update', DELETE: 'users_delete' },
  '/students/student': { GET: 'students_view', POST: 'students_create', ... },
  '/finance/payroll': { GET: 'finance_view', POST: 'finance_create', ... },
  // ... 20+ endpoints configured
}

// Validation happens automatically in apiCall()
validatePermission(endpoint, method)                 // Check before request
getRequiredPermission(endpoint, method)              // Get permission for endpoint
```

**Features:**
- Blocks unauthorized API requests at frontend
- Method-specific permissions (GET vs POST vs DELETE)
- Graceful degradation (endpoints without rules still work)
- User shown error notification if permission denied
- Prevents wasted network calls and backend 403 responses

### 3. **Helper Functions** (`js/auth-utils.js`)

Convenient utilities for UI developers:

```javascript
// Permission checks
canUser('students_create')                           // Simple check
canUserAny(['perm1', 'perm2'])                       // OR check
canUserAll(['perm1', 'perm2'])                       // AND check
isUserRole('Director/Owner')                         // Check role

// User info
getCurrentUser()                                     // Get user object
getUserDisplayName()                                 // "John Director"
getUserEmail()                                       // "john@school.ac.ke"
getUserRoles()                                       // ["Director/Owner"]
getUserPermissions()                                 // [...all permissions...]

// UI utilities
toggleElementByPermission(elementId, permCode)       // Show/hide element
toggleElementByAnyPermission(elementId, perms)       // Show if ANY permission
toggleElementByAllPermissions(elementId, perms)      // Show if ALL permissions
requirePermissionOnClick(btnId, perm, callback)      // Require permission for button

// Notifications
showPermissionDenied(action)                         // Show error notification
checkPermissionAndNotify(perm, action)               // Check + notify

// Debugging
debugAuthState()                                     // Log auth state to console
initializePermissionUI()                             // Initialize data-permission elements
```

### 4. **HTML Integration**

Use `data-permission` attributes in HTML:

```html
<!-- Show if user has permission -->
<button id="btnCreate" data-permission="students_create">Create Student</button>

<!-- Show if user has ANY permission (OR logic) -->
<div data-permission="finance_create, finance_import, finance_export">
  Finance Operations
</div>

<!-- Show if user has ALL permissions (AND logic) -->
<div data-permission="finance_view, finance_approve" data-permission-all="true">
  Approval Panel (requires both permissions)
</div>
```

Auto-initialized by `auth-utils.js` on page load.

## Updated Login Flow

**Before:**
```
Login → Store token + user_data → Redirect home
```

**After:**
```
Login 
  ↓
POST /api/users/login (backend validates, returns JWT + permissions)
  ↓
Frontend stores:
  • token → localStorage['token']
  • user_data → localStorage['user_data']
  • permissions (deduplicated) → localStorage['user_permissions']
  • roles → localStorage['user_roles']
  ↓
AuthContext.setUser() called to initialize memory cache
  ↓
Log permission count to console (e.g., "User logged in with 1300 permissions")
  ↓
Redirect to home.php
```

## Updated Logout Flow

**Before:**
```
Logout → Clear localStorage → Redirect to login
```

**After:**
```
Logout
  ↓
POST /api/auth/logout (backend invalidates token)
  ↓
AuthContext.clearUser() called to:
  • Clear memory cache (user, permissions, roles)
  • Remove all localStorage keys
  ↓
Redirect to login page
```

## Updated API Call Flow

**Before:**
```
apiCall(endpoint, method, data)
  ↓
[No permission check]
  ↓
Make HTTP request
  ↓
Handle response
```

**After:**
```
apiCall(endpoint, method, data)
  ↓
validatePermission(endpoint, method)
  ↓
Check ENDPOINT_PERMISSIONS
  ↓
AuthContext.hasPermission(requiredPermission)
  ↓
If false: Throw error, show notification, return
If true: Continue
  ↓
Make HTTP request (with Bearer token)
  ↓
Handle response
  ↓
On mutation (POST/PUT/PATCH/DELETE): Auto-refresh cached data
```

## Permission Code Mapping

All endpoints in `ENDPOINT_PERMISSIONS` configured with appropriate permission codes:

| Module | Permissions |
|--------|-------------|
| **Users** | users_view, users_create, users_update, users_delete |
| **Students** | students_view, students_create, students_update, students_delete |
| **Academic** | academic_view, academic_create, academic_update |
| **Finance** | finance_view, finance_create, finance_update, finance_approve |
| **Staff** | staff_view, staff_create, staff_update, staff_delete |
| **Attendance** | attendance_view, attendance_create, attendance_update, attendance_approve |
| **Activities** | activities_view, activities_create, activities_update |
| **Inventory** | inventory_view, inventory_create, inventory_update, inventory_delete |
| **Admission** | admission_view, admission_create, admission_update |
| **Communications** | communications_view, communications_create |
| **Transport** | transport_view, transport_create, transport_update |
| **Schedules** | schedules_view, schedules_create, schedules_update |
| **Reports** | reports_view |
| **System** | system_view, system_manage |
| **School Config** | schoolconfig_view, schoolconfig_update |

## Example Usage

### 1. Check Permission in JavaScript

```javascript
// Check before performing action
if (canUser('students_delete')) {
  // Show delete confirmation
  if (confirm('Delete this student?')) {
    await API.students.delete(studentId);
  }
} else {
  showNotification('You do not have permission to delete students', 'error');
}
```

### 2. Show/Hide Button Based on Permission

```javascript
// Option A: HTML data attribute (auto-initialized)
<button id="btnCreate" data-permission="students_create">Create</button>

// Option B: JavaScript
toggleElementByPermission('btnCreate', 'students_create');

// Option C: Helper function with callback
requirePermissionOnClick('btnCreate', 'students_create', createStudent, 'create students');
```

### 3. Get Current User Info

```javascript
const user = getCurrentUser();
console.log(`Logged in as: ${user.full_name} (${user.email})`);
console.log(`Role: ${getUserPrimaryRole()}`);
console.log(`Has ${AuthContext.getPermissionCount()} permissions`);
```

### 4. Check Multiple Permissions

```javascript
// Show finance dashboard if user can view AND approve
if (canUserAll(['finance_view', 'finance_approve'])) {
  displayFinanceApprovalPanel();
}

// Show import/export buttons if user can do any
if (canUserAny(['students_import', 'students_export'])) {
  displayBulkOperations();
}
```

### 5. Debug Current Auth State

```javascript
// In browser console
debugAuthState();

// Output:
// 🔐 Authentication State
// Authenticated: true
// User: {id: 3, username: "test_director_owner", email: "director@school.ac.ke", ...}
// Roles: ["Director/Owner"]
// Permissions (1300 total): ["students_view", "students_create", ...]
```

## Security Considerations

✅ **Frontend Permission Checks Provide:**
- Better UX (no wasted API calls)
- Faster feedback (instant permission denied notification)
- Prevents accidental unauthorized actions
- Reduces server load

⚠️ **Frontend Checks Are NOT Security:**
- User can bypass by modifying localStorage
- User can modify browser DevTools to change permissions
- User can craft API requests directly

**CRITICAL:** Backend MUST validate permissions on EVERY request:
- Verify JWT token is valid
- Check user has required permission
- Return 403 Forbidden if not authorized
- Never trust frontend validation

## Files Modified/Created

| File | Change |
|------|--------|
| `js/api.js` | Added AuthContext, permission validation, updated login/logout |
| `js/auth-utils.js` | NEW - Helper functions for UI developers |
| `documantations/AUTHENTICATION.md` | NEW - Complete authentication guide |

## Testing Checklist

- [ ] Login with test user: `test_director_owner` / `testpass`
- [ ] Check console logs: "User logged in with X permissions"
- [ ] Run `debugAuthState()` in console - see permissions loaded
- [ ] Click button with `data-permission` - should be visible
- [ ] Try to call API with missing permission - should show error
- [ ] Logout - check localStorage cleared
- [ ] Verify JWT token sent in Authorization header on API calls
- [ ] Check backend also validates permissions (not just frontend)

## Integration Steps for Team

### 1. Include Scripts in Pages

```html
<!-- In app_layout.php or header -->
<script src="js/api.js"></script>
<script src="js/auth-utils.js"></script>
```

### 2. Protect Pages (Check Authentication)

```html
<script>
  if (!AuthContext.isAuthenticated()) {
    window.location.href = '/Kingsway/index.php';
  }
</script>
```

### 3. Use Helper Functions in HTML

```html
<!-- Show user welcome message -->
<span>Welcome, <span id="userName"></span>!</span>
<script>
  document.getElementById('userName').textContent = getUserDisplayName();
</script>

<!-- Show conditional buttons -->
<button id="btnCreate" data-permission="students_create">Create</button>
<button id="btnDelete" data-permission="students_delete">Delete</button>
```

### 4. Use Helper Functions in JavaScript

```javascript
// Check permission before action
if (!canUser('students_delete')) {
  showPermissionDenied('delete this student');
  return;
}

// Make API call (permission check happens automatically)
const result = await API.students.delete(studentId);
```

### 5. Add Permission Rules for New Endpoints

When creating new API endpoints, add to `ENDPOINT_PERMISSIONS` in `api.js`:

```javascript
ENDPOINT_PERMISSIONS = {
  // ... existing
  '/newmodule/endpoint': {
    'GET': 'newmodule_view',
    'POST': 'newmodule_create',
    'PUT': 'newmodule_update',
    'DELETE': 'newmodule_delete'
  }
}
```

## Next Steps

1. **Test login flow** - Verify permissions load correctly
2. **Add permission guards to existing pages** - Protect pages with auth check
3. **Add permission checks to buttons** - Use `data-permission` attributes
4. **Verify backend validation** - Ensure backend checks permissions too
5. **Add role-based features** - Use `isUserRole()` to show admin-only features
6. **Test permission denied scenarios** - Try to access with insufficient permissions
7. **Review localStorage data** - Ensure no sensitive data stored unencrypted
8. **Document permission codes** - Create reference for developers

## Debugging Tips

**Check if user is authenticated:**
```javascript
AuthContext.isAuthenticated()  // true/false
```

**View all permissions:**
```javascript
AuthContext.getPermissions()   // Array of permission codes
```

**Check specific permission:**
```javascript
AuthContext.hasPermission('students_create')  // true/false
```

**View user info:**
```javascript
AuthContext.getUser()          // User object
```

**Log everything:**
```javascript
debugAuthState()               // Detailed console output
```

**Test permission check on button:**
```javascript
canUser('students_delete')     // true/false
```

## Documentation

See `documantations/AUTHENTICATION.md` for:
- Complete architecture explanation
- API reference for all functions
- Usage examples
- Troubleshooting guide
- Security notes
