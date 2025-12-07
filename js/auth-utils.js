/**
 * Frontend Authentication & Authorization Utilities
 * 
 * Provides helper functions for UI components to:
 * - Check user permissions before showing/enabling UI elements
 * - Display user information (name, roles, avatar, etc.)
 * - Handle permission-denied scenarios gracefully
 */

/**
 * Check if user has permission to perform an action
 * Used in UI to show/hide buttons, links, menu items, etc.
 * 
 * @param {string} permissionCode - Permission code to check (e.g., 'students_create')
 * @returns {boolean}
 * 
 * @example
 * if (canUser('students_create')) {
 *   // Show "Create Student" button
 * }
 */
function canUser(permissionCode) {
    return AuthContext.hasPermission(permissionCode);
}

/**
 * Check if user has ANY of the specified permissions
 * Useful for showing an element if user has at least one permission
 * 
 * @param {string[]} permissionCodes - Array of permission codes
 * @returns {boolean}
 * 
 * @example
 * if (canUserAny(['students_create', 'students_update'])) {
 *   // Show "Manage Students" menu
 * }
 */
function canUserAny(permissionCodes = []) {
    return AuthContext.hasAnyPermission(permissionCodes);
}

/**
 * Check if user has ALL of the specified permissions
 * Useful for complex actions requiring multiple permissions
 * 
 * @param {string[]} permissionCodes - Array of permission codes
 * @returns {boolean}
 * 
 * @example
 * if (canUserAll(['finance_view', 'finance_approve'])) {
 *   // Show "Approve Payment" button
 * }
 */
function canUserAll(permissionCodes = []) {
    return AuthContext.hasAllPermissions(permissionCodes);
}

/**
 * Check if user has a specific role
 * 
 * @param {string} roleName - Role name to check (e.g., 'Admin', 'Teacher')
 * @returns {boolean}
 * 
 * @example
 * if (isUserRole('Director/Owner')) {
 *   // Show admin dashboard
 * }
 */
function isUserRole(roleName) {
    return AuthContext.hasRole(roleName);
}

/**
 * Check if user is authenticated
 * 
 * @returns {boolean}
 */
function isUserAuthenticated() {
    return AuthContext.isAuthenticated();
}

/**
 * Get current logged-in user
 * 
 * @returns {object|null} User object or null if not authenticated
 * 
 * @example
 * const user = getCurrentUser();
 * console.log(user.username, user.email);
 */
function getCurrentUser() {
    return AuthContext.getUser();
}

/**
 * Get user's display name
 * 
 * @returns {string} User's name or email or "Guest"
 */
function getUserDisplayName() {
    const user = AuthContext.getUser();
    if (!user) return 'Guest';
    return user.full_name || user.name || user.username || 'User';
}

/**
 * Get user's email
 * 
 * @returns {string|null}
 */
function getUserEmail() {
    const user = AuthContext.getUser();
    return user?.email || null;
}

/**
 * Get user's avatar URL or initials
 * 
 * @returns {string} Avatar URL or initials (e.g., "JD" for John Doe)
 */
function getUserAvatar() {
    const user = AuthContext.getUser();
    if (!user) return '?';
    
    // If user has avatar URL, return it
    if (user.avatar_url) return user.avatar_url;
    
    // Otherwise generate initials
    const name = user.full_name || user.username || 'User';
    const parts = name.split(' ');
    const initials = parts.map(p => p[0]).join('').toUpperCase();
    return initials || '?';
}

/**
 * Get user's roles
 * 
 * @returns {string[]} Array of role names
 */
function getUserRoles() {
    return AuthContext.getRoles();
}

/**
 * Get user's primary role
 * 
 * @returns {string|null} First role name or null
 */
function getUserPrimaryRole() {
    const roles = AuthContext.getRoles();
    return roles.length > 0 ? roles[0] : null;
}

/**
 * Get all user permissions
 * 
 * @returns {string[]} Array of permission codes
 */
function getUserPermissions() {
    return AuthContext.getPermissions();
}

/**
 * Show a permission-denied notification
 * 
 * @param {string} action - Action that was denied (e.g., "Create Student")
 */
function showPermissionDenied(action = 'Access this resource') {
    showNotification(
        `Permission Denied: You are not authorized to ${action}.`,
        NOTIFICATION_TYPES.ERROR
    );
}

/**
 * Check permission and show notification if denied
 * 
 * @param {string} permissionCode - Permission to check
 * @param {string} actionName - Name of action for notification (e.g., "delete this student")
 * @returns {boolean} True if user has permission, false otherwise
 * 
 * @example
 * if (!checkPermissionAndNotify('students_delete', 'delete this student')) {
 *   return; // Exit function, user already notified
 * }
 * // Continue with delete operation
 */
function checkPermissionAndNotify(permissionCode, actionName = 'perform this action') {
    if (AuthContext.hasPermission(permissionCode)) {
        return true;
    }
    
    showPermissionDenied(actionName);
    return false;
}

/**
 * Conditionally show/hide an HTML element based on permission
 * 
 * @param {string} elementId - ID of element to show/hide
 * @param {string} permissionCode - Permission to check
 * 
 * @example
 * toggleElementByPermission('btnCreateStudent', 'students_create');
 */
function toggleElementByPermission(elementId, permissionCode) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const hasPermission = AuthContext.hasPermission(permissionCode);
    element.style.display = hasPermission ? '' : 'none';
    element.disabled = !hasPermission;
}

/**
 * Conditionally show/hide an element based on ANY of multiple permissions
 * 
 * @param {string} elementId - ID of element to show/hide
 * @param {string[]} permissionCodes - Permissions to check
 */
function toggleElementByAnyPermission(elementId, permissionCodes) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const hasPermission = AuthContext.hasAnyPermission(permissionCodes);
    element.style.display = hasPermission ? '' : 'none';
    element.disabled = !hasPermission;
}

/**
 * Conditionally show/hide an element based on ALL permissions
 * 
 * @param {string} elementId - ID of element to show/hide
 * @param {string[]} permissionCodes - Permissions to check
 */
function toggleElementByAllPermissions(elementId, permissionCodes) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const hasPermission = AuthContext.hasAllPermissions(permissionCodes);
    element.style.display = hasPermission ? '' : 'none';
    element.disabled = !hasPermission;
}

/**
 * Add permission check to a button click handler
 * 
 * @param {string} buttonId - ID of button element
 * @param {string} permissionCode - Permission to check
 * @param {Function} callback - Function to call if permission granted
 * @param {string} actionName - Name of action for permission denied message
 * 
 * @example
 * requirePermissionOnClick('btnDelete', 'students_delete', deleteStudent, 'delete this student');
 */
function requirePermissionOnClick(buttonId, permissionCode, callback, actionName) {
    const button = document.getElementById(buttonId);
    if (!button) return;
    
    const originalClick = button.onclick;
    button.addEventListener('click', (e) => {
        if (!checkPermissionAndNotify(permissionCode, actionName)) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
        if (typeof callback === 'function') {
            callback(e);
        }
    });
}

/**
 * Initialize permission-based UI elements on page load
 * Scans for data-permission attributes and toggles visibility
 * 
 * @example
 * <!-- In HTML -->
 * <button id="btnCreate" data-permission="students_create">Create</button>
 * 
 * <!-- In script -->
 * <script>
 *   document.addEventListener('DOMContentLoaded', initializePermissionUI);
 * </script>
 */
function initializePermissionUI() {
    // Find all elements with data-permission attribute
    const elements = document.querySelectorAll('[data-permission]');
    
    elements.forEach(element => {
        const permissions = element.getAttribute('data-permission')
            .split(',')
            .map(p => p.trim());
        
        const requireAll = element.getAttribute('data-permission-all') === 'true';
        
        let hasAccess;
        if (requireAll) {
            hasAccess = AuthContext.hasAllPermissions(permissions);
        } else {
            hasAccess = permissions.length === 1
                ? AuthContext.hasPermission(permissions[0])
                : AuthContext.hasAnyPermission(permissions);
        }
        
        element.style.display = hasAccess ? '' : 'none';
        element.disabled = !hasAccess;
    });
}

/**
 * Log current user's authentication and permission state (for debugging)
 */
function debugAuthState() {
    console.group('🔐 Authentication State');
    console.log('Authenticated:', AuthContext.isAuthenticated());
    console.log('User:', AuthContext.getUser());
    console.log('Roles:', AuthContext.getRoles());
    console.log(`Permissions (${AuthContext.getPermissionCount()} total):`, AuthContext.getPermissions());
    console.groupEnd();
}

// Auto-initialize permission UI when page loads
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePermissionUI);
} else {
    initializePermissionUI();
}
