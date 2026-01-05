// Only define API_BASE_URL if not already defined
if (typeof API_BASE_URL === 'undefined') {
    var API_BASE_URL = '/Kingsway/api';
}

// Token refresh tracking to prevent duplicate refresh requests
let isRefreshingToken = false;
let refreshTokenPromise = null;

// Notification types
const NOTIFICATION_TYPES = {
    SUCCESS: 'success',
    ERROR: 'error',
    WARNING: 'warning',
    INFO: 'info'
};

// Icons for different notification types
const NOTIFICATION_ICONS = {
    success: 'bi-check-circle',
    error: 'bi-x-circle',
    warning: 'bi-exclamation-triangle',
    info: 'bi-info-circle'
};

// Show notification using Bootstrap modal
function showNotification(message, type = NOTIFICATION_TYPES.INFO) {
    const modal = document.getElementById('notificationModal');
    const modalContent = modal.querySelector('.modal-content');
    const icon = modal.querySelector('.notification-icon i');
    const messageDiv = modal.querySelector('.notification-message');

    // Remove existing notification classes
    modalContent.classList.remove(
        'notification-success',
        'notification-error',
        'notification-warning',
        'notification-info'
    );

    // Add appropriate notification class
    modalContent.classList.add(`notification-${type}`);

    // Set icon
    icon.className = `bi ${NOTIFICATION_ICONS[type]}`;

    // Set message
    messageDiv.textContent = message;

    // Show modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

// Handle API Response
function handleApiResponse(response, showSuccess = false) {
    if (response.status === 'success') {
        // Disabled automatic success notifications - let components handle their own
        // if (showSuccess && response.message) {
        //     showNotification(response.message, NOTIFICATION_TYPES.SUCCESS);
        // }
        // For sidebar endpoint, return the entire response
        if (response.data?.sidebar !== undefined) {
            return response;
        }
        // For other endpoints, return just the data
        return response.data !== undefined ? response.data : response;
    } else {
        const error = new Error(response.message || 'API call failed');
        error.response = response;
        throw error;
    }
}

// Handle API Error
function handleApiError(error) {
    console.error('API Error:', error);
    // Don't show notification here - let caller decide if they want to notify user
    throw error;
}

// Download file helper
async function downloadFile(blob, filename) {
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);
    document.body.removeChild(a);
}

// ============================================================================
// AUTHENTICATION & AUTHORIZATION SYSTEM
// ============================================================================

/**
 * User context manager - handles authentication state, permissions, and access control
 * Stores user info, token, roles, and permissions in localStorage + memory
 * Provides permission checking before API calls
 */
const AuthContext = (() => {
  let currentUser = null;
  let permissions = new Set();
  let roles = [];

  /**
   * Initialize user context from localStorage (on page load)
   */
  function initialize() {
    const token = localStorage.getItem("token");
    const userData = localStorage.getItem("user_data");
    const permissionsData = localStorage.getItem("user_permissions");

    if (token && userData) {
      try {
        currentUser = JSON.parse(userData);
        if (permissionsData) {
          permissions = new Set(JSON.parse(permissionsData));
          roles = JSON.parse(localStorage.getItem("user_roles") || "[]");
        }
      } catch (e) {
        console.warn("Failed to restore user context from localStorage:", e);
        currentUser = null;
        permissions.clear();
        roles = [];
      }
    }
  }

  /**
   * Store user context after login
   * Deduplicates permissions and extracts unique permission codes
   * Also stores sidebar menu items
   */
  function setUser(userData, fullResponse) {
    currentUser = userData;

    console.log("setUser called with:", { userData, fullResponse });

    // Extract and deduplicate permissions
    // Permissions can be in fullResponse.permissions OR userData.permissions
    const permissionsArray =
      fullResponse?.permissions || userData?.permissions || [];

    console.log("Permissions array:", permissionsArray);

    if (Array.isArray(permissionsArray) && permissionsArray.length > 0) {
      // Create Set of unique permission codes (automatically deduplicates)
      const uniquePermissions = new Set(
        permissionsArray.map((p) => p.permission_code || p)
      );
      permissions = uniquePermissions;

      console.log("Unique permissions extracted:", permissions.size);

      // Store in localStorage
      localStorage.setItem(
        "user_permissions",
        JSON.stringify(Array.from(permissions))
      );
    } else {
      console.warn("No permissions found in response");
    }

    // Extract roles and role IDs
    const rolesArray = fullResponse?.roles || userData?.roles || [];
    if (Array.isArray(rolesArray) && rolesArray.length > 0) {
      roles = rolesArray.map((r) => r.name || r);
      localStorage.setItem("user_roles", JSON.stringify(roles));
      console.log("Roles extracted:", roles);

      // Also extract role IDs for dashboard routing
      const roleIds = [];
      for (const role of rolesArray) {
        if (role && typeof role === "object" && (role.id || role.role_id)) {
          roleIds.push(role.id || role.role_id);
        }
      }

      // Add role_ids to userData for dashboard router
      if (roleIds.length > 0) {
        userData.role_ids = roleIds;
        console.log("Role IDs extracted:", roleIds);
      }
    } else {
      console.warn("No roles found in response");
    }

    // Store sidebar items
    if (
      fullResponse?.sidebar_items &&
      Array.isArray(fullResponse.sidebar_items)
    ) {
      localStorage.setItem(
        "sidebar_items",
        JSON.stringify(fullResponse.sidebar_items)
      );
      console.log("Sidebar items stored:", fullResponse.sidebar_items.length);
      // Trigger sidebar refresh
      if (typeof window.refreshSidebar === "function") {
        window.refreshSidebar(fullResponse.sidebar_items);
      }
    } else {
      console.warn("No sidebar items found in response");
    }

    // Store dashboard info
    if (fullResponse?.dashboard) {
      localStorage.setItem(
        "dashboard_info",
        JSON.stringify(fullResponse.dashboard)
      );
      console.log("Dashboard info stored:", fullResponse.dashboard);
    } else {
      console.warn("No dashboard info found in response");
    }

    // Store user data (now includes role_ids)
    localStorage.setItem("user_data", JSON.stringify(userData));
    console.log("User data stored", userData);
  }

  /**
   * Clear user context on logout
   */
  function clearUser() {
    currentUser = null;
    permissions.clear();
    roles = [];
    localStorage.removeItem("token");
    localStorage.removeItem("user_data");
    localStorage.removeItem("user_permissions");
    localStorage.removeItem("user_roles");
    localStorage.removeItem("sidebar_items");
    localStorage.removeItem("dashboard_info");
  }

  /**
   * Check if user has a specific permission
   * @param {string} permissionCode - e.g., 'students_create', 'users_delete'
   * @returns {boolean}
   */
  function hasPermission(permissionCode) {
    if (!currentUser || !permissionCode) return false;

    // Check if user has all permissions flag (super admin)
    if (currentUser.has_all_permissions === true) {
      return true; // User has all permissions
    }

    return permissions.has(permissionCode);
  }

  /**
   * Check if user has ANY of the given permissions
   * @param {string[]} permissionCodes
   * @returns {boolean}
   */
  function hasAnyPermission(permissionCodes = []) {
    if (!currentUser) return false;

    // Check if user has all permissions flag (super admin)
    if (currentUser.has_all_permissions === true) {
      return true; // User has all permissions
    }

    return permissionCodes.some((code) => permissions.has(code));
  }

  /**
   * Check if user has ALL of the given permissions
   * @param {string[]} permissionCodes
   * @returns {boolean}
   */
  function hasAllPermissions(permissionCodes = []) {
    if (!currentUser) return false;

    // Check if user has all permissions flag (super admin)
    if (currentUser.has_all_permissions === true) {
      return true; // User has all permissions
    }

    return permissionCodes.every((code) => permissions.has(code));
  }

  /**
   * Check if user has a specific role
   * @param {string} roleName
   * @returns {boolean}
   */
  function hasRole(roleName) {
    if (!currentUser) return false;
    return roles.includes(roleName);
  }

  /**
   * Get current user
   */
  function getUser() {
    return currentUser;
  }

  /**
   * Get all permissions for current user
   */
  function getPermissions() {
    return Array.from(permissions);
  }

  /**
   * Get all roles for current user
   */
  function getRoles() {
    return [...roles];
  }

  /**
   * Get sidebar menu items from localStorage
   */
  function getSidebarItems() {
    try {
      const items = localStorage.getItem("sidebar_items");
      return items ? JSON.parse(items) : [];
    } catch (e) {
      console.warn("Failed to parse sidebar items:", e);
      return [];
    }
  }

  /**
   * Get dashboard info from localStorage
   */
  function getDashboardInfo() {
    try {
      const info = localStorage.getItem("dashboard_info");
      return info ? JSON.parse(info) : null;
    } catch (e) {
      console.warn("Failed to parse dashboard info:", e);
      return null;
    }
  }

  /**
   * Get unique permission count
   */
  function getPermissionCount() {
    return permissions.size;
  }

  /**
   * Check if user is authenticated
   */
  function isAuthenticated() {
    return !!currentUser && !!localStorage.getItem("token");
  }

  // Initialize on load
  initialize();

  // Return public API
  return {
    setUser,
    clearUser,
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,
    hasRole,
    getUser,
    getPermissions,
    getRoles,
    getSidebarItems,
    getDashboardInfo,
    getPermissionCount,
    isAuthenticated,
    initialize,
  };
})();

// Lightweight state refresher registry so mutation calls can auto-refresh linked data
const APIState = (() => {
  const refreshers = new Map();

  return {
    register: (key, refresherFn) => {
      if (typeof refresherFn === "function") {
        refreshers.set(key, refresherFn);
      }
    },
    unregister: (key) => refreshers.delete(key),
    invalidate: async (key) => {
      if (refreshers.has(key)) {
        await refreshers.get(key)();
      }
    },
    invalidateMany: async (keys = []) => {
      for (const key of keys) {
        await APIState.invalidate(key);
      }
    },
  };
})();

// Infer primary resource from endpoint for automatic invalidation
function inferResourceKey(endpoint = "") {
  const clean = endpoint.split("?")[0].replace(/^\/+/, "");
  const [resource] = clean.split("/");
  return resource || null;
}

const MUTATION_METHODS = new Set(["POST", "PUT", "PATCH", "DELETE"]);

/**
 * Permission requirement mapping for API endpoints
 * Maps endpoints (or resource+method combinations) to required permissions
 *
 * Examples:
 * '/users/user' (POST) => requires 'users_create'
 * '/students/student' (DELETE) => requires 'students_delete'
 * '/attendance/student' (GET) => requires 'attendance_view'
 */
const ENDPOINT_PERMISSIONS = {
  // Auth endpoints (no permission check for login/logout)
  "/users/login": null,
  "/auth/login": null,
  "/auth/logout": null,
  "/auth/refresh-token": null,

  // Users
  "/users/index": "users_view",
  "/users/user": {
    GET: "users_view",
    POST: "users_create",
    PUT: "users_update",
    DELETE: "users_delete",
  },

  // Students
  "/students/index": "students_view",
  "/students/student": {
    GET: "students_view",
    POST: "students_create",
    PUT: "students_update",
    DELETE: "students_delete",
  },

  // Academic
  "/academic/index": "academic_view",
  "/academic/curriculum": {
    GET: "academic_view",
    POST: "academic_create",
    PUT: "academic_update",
  },

  // Attendance
  "/attendance/index": "attendance_view",
  "/attendance/student": {
    GET: "attendance_view",
    POST: "attendance_create",
    PUT: "attendance_update",
  },

  // Finance
  "/finance/index": "finance_view",
  "/finance/payroll": {
    GET: "finance_view",
    POST: "finance_create",
    PUT: "finance_update",
  },

  // Staff
  "/staff/index": "staff_view",
  "/staff/staff": {
    GET: "staff_view",
    POST: "staff_create",
    PUT: "staff_update",
    DELETE: "staff_delete",
  },

  // Activities
  "/activities/index": "activities_view",
  "/activities/activity": {
    GET: "activities_view",
    POST: "activities_create",
    PUT: "activities_update",
  },

  // Inventory
  "/inventory/index": "inventory_view",
  "/inventory/item": {
    GET: "inventory_view",
    POST: "inventory_create",
    PUT: "inventory_update",
    DELETE: "inventory_delete",
  },

  // Admission
  "/admission/index": "admission_view",
  "/admission/application": {
    GET: "admission_view",
    POST: "admission_create",
    PUT: "admission_update",
  },

  // Communications
  "/communications/index": "communications_view",
  "/communications/sms": {
    GET: "communications_view",
    POST: "communications_create",
  },

  // Transport
  "/transport/index": "transport_view",
  "/transport/route": {
    GET: "transport_view",
    POST: "transport_create",
    PUT: "transport_update",
  },

  // Schedules
  "/schedules/index": "schedules_view",
  "/schedules/timetable": {
    GET: "schedules_view",
    POST: "schedules_create",
    PUT: "schedules_update",
  },

  // Reports
  "/reports/index": "reports_view",
  "/reports/academic": "reports_view",

  // System
  "/system/index": "system_view",
  "/system/logs": { GET: "system_view", DELETE: "system_manage" },

  // School Config
  "/schoolconfig/index": "schoolconfig_view",
  "/schoolconfig/config": {
    GET: "schoolconfig_view",
    PUT: "schoolconfig_update",
  },
};

/**
 * Get required permission for an endpoint
 * Accounts for both simple permissions and method-specific permissions
 * @param {string} endpoint - The API endpoint path
 * @param {string} method - HTTP method (GET, POST, PUT, DELETE, etc.)
 * @returns {string|null} Required permission code or null if no permission needed
 */
function getRequiredPermission(endpoint, method = "GET") {
  // Normalize endpoint (remove leading slash, remove query strings)
  const normalizedEndpoint = "/" + endpoint.replace(/^\/+/, "").split("?")[0];

  // Check direct endpoint match
  const requirement = ENDPOINT_PERMISSIONS[normalizedEndpoint];

  if (!requirement) {
    // No specific permission defined for this endpoint
    // Could log a warning in development
    return null;
  }

  if (typeof requirement === "string") {
    // Simple string permission requirement (same for all methods)
    return requirement;
  }

  if (typeof requirement === "object" && requirement !== null) {
    // Method-specific permission requirements
    return requirement[method.toUpperCase()] || requirement["GET"] || null;
  }

  return null;
}

/**
 * Validate user has required permission before making API call
 * Throws error if user lacks permission
 * @param {string} endpoint
 * @param {string} method
 * @throws {Error} If user is not authenticated or lacks permission
 */
function validatePermission(endpoint, method) {
  // Skip permission check if user is not authenticated (will fail at backend)
  if (!AuthContext.isAuthenticated()) {
    console.warn("API call attempted without authentication");
    return;
  }

  const requiredPermission = getRequiredPermission(endpoint, method);

  // No permission requirement for this endpoint
  if (!requiredPermission) {
    return;
  }

  // Check if user has the required permission
  if (!AuthContext.hasPermission(requiredPermission)) {
    const error = new Error(
      `Access Denied: You do not have permission "${requiredPermission}" to ${method} ${endpoint}`
    );
    error.code = "PERMISSION_DENIED";
    error.permission = requiredPermission;
    throw error;
  }
}

/**
 * Refresh access token using stored refresh token
 * Implements token rotation strategy with automatic retry
 * @returns {Promise<boolean>} True if token was refreshed successfully
 */
async function refreshAccessToken() {
  // Prevent simultaneous refresh requests
  if (isRefreshingToken) {
    return refreshTokenPromise;
  }

  isRefreshingToken = true;
  refreshTokenPromise = (async () => {
    try {
      const refreshToken = localStorage.getItem("refresh_token");
      if (!refreshToken) {
        console.warn("No refresh token available, redirecting to login");
        AuthContext.clearUser();
        window.location.href = "/Kingsway/index.php";
        return false;
      }

      console.log("Attempting to refresh access token...");

      // Call refresh endpoint without checking permissions (to avoid recursion)
      const url = new URL(
        API_BASE_URL + "/auth/refresh-token",
        window.location.origin
      );
      const response = await fetch(url, {
        method: "POST",
        credentials: "omit",
        headers: {
          "Content-Type": "application/json",
          Authorization: "Bearer " + localStorage.getItem("token"),
        },
        body: JSON.stringify({ refresh_token: refreshToken }),
      });

      if (!response.ok) {
        console.error("Token refresh failed:", response.status);
        AuthContext.clearUser();
        window.location.href = "/Kingsway/index.php";
        return false;
      }

      const result = await response.json();

      if (result.status === "success" && result.data.token) {
        // Store new tokens
        localStorage.setItem("token", result.data.token);
        if (result.data.refresh_token) {
          localStorage.setItem("refresh_token", result.data.refresh_token);
        }
        console.log("Token refreshed successfully");
        return true;
      } else {
        console.error("Token refresh returned error:", result.message);
        AuthContext.clearUser();
        window.location.href = "/Kingsway/index.php";
        return false;
      }
    } catch (error) {
      console.error("Error refreshing token:", error);
      AuthContext.clearUser();
      window.location.href = "/Kingsway/index.php";
      return false;
    } finally {
      isRefreshingToken = false;
    }
  })();

  return refreshTokenPromise;
}

/**
 * Check if JWT token is expired based on 'exp' claim
 * Returns true if token is about to expire (within 60 seconds)
 */
function isTokenExpired() {
  const token = localStorage.getItem("token");
  if (!token) return true;

  try {
    // Decode JWT (without verification, just get payload)
    const parts = token.split(".");
    if (parts.length !== 3) return true;

    const payload = JSON.parse(atob(parts[1]));
    const now = Math.floor(Date.now() / 1000);
    const expiresIn = payload.exp - now;

    // Return true if expired or about to expire (within 60 seconds)
    return expiresIn < 60;
  } catch (error) {
    console.error("Error checking token expiry:", error);
    return true;
  }
}

// Generic API call function using fetch
async function apiCall(
  endpoint,
  method = "GET",
  data = null,
  params = {},
  options = {}
) {
  try {
    // Check if token is about to expire and refresh if needed
    if (AuthContext.isAuthenticated() && isTokenExpired()) {
      console.log("Token expiring soon, refreshing...");
      const refreshed = await refreshAccessToken();
      if (!refreshed) {
        throw new Error("Token refresh failed, please log in again");
      }
    }

    // Validate permission BEFORE making the request
    // If user lacks permission, this will throw an error
    if (options.checkPermission !== false) {
      validatePermission(endpoint, method);
    }

    // Construct URL with query parameters
    const url = new URL(API_BASE_URL + endpoint, window.location.origin);
    Object.keys(params).forEach((key) =>
      url.searchParams.append(key, params[key])
    );

    console.log("API Call:", method, url.toString());

    // Check if token exists for debugging
    const token = localStorage.getItem("token");
    if (!token) {
      console.warn(
        "⚠️ No JWT token found in localStorage - API call will fail with 401"
      );
      console.warn(
        "Please log in through /Kingsway/index.php to obtain a JWT token"
      );
    } else {
      console.log("✓ Token found, length:", token.length);
    }

    // Request options
    const fetchOptions = {
      method: method,
      // Include credentials to ensure proper session handling
      credentials: options.credentials || "include",
      headers: {
        ...(options.isFile ? {} : { "Content-Type": "application/json" }),
        // Add Authorization header if token exists
        ...(token && {
          Authorization: "Bearer " + token,
        }),
        ...options.headers,
      },
    };

    // Add body for POST/PUT requests
    if (data) {
      if (options.isFile) {
        fetchOptions.body = data;
      } else if (["POST", "PUT", "PATCH"].includes(method)) {
        fetchOptions.body = JSON.stringify(data);
      }
    }

    console.log("Fetch options:", fetchOptions);
    console.log(
      "Request headers:",
      JSON.stringify(fetchOptions.headers, null, 2)
    );

    let response = await fetch(url, fetchOptions);

    // Handle 401 Unauthorized - token may have expired, try to refresh
    if (response.status === 401 && !options.isRefreshAttempt) {
      console.log("Received 401 Unauthorized, attempting token refresh...");
      const refreshed = await refreshAccessToken();

      if (refreshed) {
        // Retry the original request with new token
        console.log("Retrying original request with refreshed token...");
        fetchOptions.headers.Authorization =
          "Bearer " + localStorage.getItem("token");
        response = await fetch(url, fetchOptions);
      } else {
        // Refresh failed, user is logged out and redirected
        throw new Error("Authentication failed, please log in again");
      }
    }

    // If not JSON, throw a clear error
    const contentType = response.headers.get("content-type") || "";
    if (!contentType.includes("application/json")) {
      const text = await response.text();
      throw new Error(
        "API did not return JSON. Response: " + text.substring(0, 200)
      );
    }

    // Handle file downloads
    if (options.isDownload) {
      if (!response.ok) {
        throw new Error("File download failed");
      }
      const blob = await response.blob();
      const filename =
        options.filename ||
        response.headers.get("content-disposition")?.split("filename=")[1] ||
        "download";
      await downloadFile(blob, filename);
      return { status: "success", message: "File downloaded successfully" };
    }

    // Handle regular JSON responses
    const result = await response.json();
    const handled = handleApiResponse(result, options.showSuccess !== false);

    // Auto-invalidate cached data on mutations
    if (MUTATION_METHODS.has(String(method).toUpperCase())) {
      const targets = options.invalidate || [inferResourceKey(endpoint)];
      APIState.invalidateMany(targets.filter(Boolean)).catch((err) => {
        console.warn("Auto-refresh failed for targets:", targets, err);
      });
    }

    return handled;
  } catch (error) {
    // For permission denied errors, log to console instead of showing popup
    if (error.code === "PERMISSION_DENIED") {
      console.warn("Permission Denied:", error.message);
    }
    return handleApiError(error);
  }
}

// File upload helper
function createFormData(data, files = {}) {
  const formData = new FormData();
  Object.keys(data || {}).forEach((key) => formData.append(key, data[key]));
  Object.keys(files).forEach((key) => {
    if (Array.isArray(files[key])) {
      files[key].forEach((file) => formData.append(key + "[]", file));
    } else {
      formData.append(key, files[key]);
    }
  });
  return formData;
}

//attach API to window for global access
window.API = {
  apiCall,
  showNotification,
  state: APIState,

  // Auth endpoints
  auth: {
    index: async () => apiCall("/auth/index", "GET"),
    login: async (username, password) => {
      const response = await apiCall("/auth/login", "POST", {
        username,
        password,
      });

      console.log("Full login response:", response);

      if (response && response.token) {
        // Store both access and refresh tokens
        localStorage.setItem("token", response.token);
        if (response.refresh_token) {
          localStorage.setItem("refresh_token", response.refresh_token);
        }

        // Store user context with permissions
        // The backend returns the user object in response.user
        const userData = response.user || {};

        console.log("User data:", userData);
        console.log("Sidebar items:", response.sidebar_items);
        console.log("Dashboard info:", response.dashboard);

        AuthContext.setUser(userData, response);

        console.log("After setUser - AuthContext state:");
        console.log("- User:", AuthContext.getUser());
        console.log("- Permissions:", AuthContext.getPermissionCount());
        console.log("- Roles:", AuthContext.getRoles());
        console.log("- Sidebar items:", AuthContext.getSidebarItems());
        console.log("- Dashboard info:", AuthContext.getDashboardInfo());

        // Hide login modal
        const modal = document.getElementById("loginModal");
        if (modal) {
          const bsModal =
            bootstrap.Modal.getInstance(modal) || new bootstrap.Modal(modal);
          bsModal.hide();
        }

        // Log permission count for debugging
        console.log(
          `User logged in: ${
            userData.username
          } with ${AuthContext.getPermissionCount()} permissions`,
          `Roles: ${AuthContext.getRoles().join(", ")}`
        );

        // Navigate to user's default dashboard
        const dashboardInfo = AuthContext.getDashboardInfo();
        console.log("Dashboard info for navigation:", dashboardInfo);

        let redirectUrl;
        if (dashboardInfo && dashboardInfo.url) {
          // Dashboard URL uses underscore format: manage_students
          redirectUrl = "/Kingsway/home.php?route=" + dashboardInfo.url;
        } else {
          // Fallback to home page which will redirect to appropriate dashboard
          redirectUrl = "/Kingsway/home.php";
        }

        console.log("Redirecting to:", redirectUrl);
        window.location.href = redirectUrl;
      }
      return response;
    },
    logout: async () => {
      try {
        // Revoke the refresh token on the server
        const refreshToken = localStorage.getItem("refresh_token");
        if (refreshToken) {
          await apiCall(
            "/auth/logout",
            "POST",
            { refresh_token: refreshToken },
            {},
            {
              isRefreshAttempt: true, // Skip token refresh on logout
            }
          );
        }
      } catch (error) {
        console.warn("Error revoking refresh token on server:", error);
        // Continue with logout even if revoke fails
      } finally {
        // Clear local storage
        AuthContext.clearUser();
        // Redirect to login
        window.location.href = "/Kingsway/index.php";
      }
    },
    forgotPassword: async (email) =>
      apiCall("/auth/forgot-password", "POST", { email }),
    resetPassword: async (token, password) =>
      apiCall("/auth/reset-password", "POST", { token, password }),
    refreshToken: async () => {
      const refreshToken = localStorage.getItem("refresh_token");
      if (!refreshToken) {
        throw new Error("No refresh token available");
      }
      const response = await apiCall(
        "/auth/refresh-token",
        "POST",
        { refresh_token: refreshToken },
        {},
        {
          isRefreshAttempt: true, // Skip token refresh check to avoid recursion
        }
      );
      if (response && response.token) {
        localStorage.setItem("token", response.token);
        if (response.refresh_token) {
          localStorage.setItem("refresh_token", response.refresh_token);
        }
      }
      return response;
    },
  },

  // Users endpoints
  users: {
    index: async () => apiCall("/users/index", "GET"),
    get: async (id = null) =>
      id ? apiCall(`/users/user/${id}`, "GET") : apiCall("/users/user", "GET"),
    create: async (data) => apiCall("/users/user", "POST", data),
    update: async (id, data) => apiCall(`/users/user/${id}`, "PUT", data),
    delete: async (id) => apiCall(`/users/user/${id}`, "DELETE"),
    bulkCreate: async (users) =>
      apiCall("/users/bulk-create", "POST", { users }),

    // Profile
    getProfile: async (id = null) =>
      id
        ? apiCall(`/users/profile-get/${id}`, "GET")
        : apiCall("/users/profile-get", "GET"),

    // Password
    changePassword: async (data) =>
      apiCall("/users/password-change", "PUT", data),
    requestPasswordReset: async (email) =>
      apiCall("/users/password-reset", "POST", { email }),

    // Roles
    getRoles: async (id = null) =>
      id
        ? apiCall(`/users/roles-get/${id}`, "GET")
        : apiCall("/users/roles-get", "GET"),
    getRoleMain: async (userId) =>
      apiCall(`/users/role-main?user_id=${userId}`, "GET"),
    getRoleExtra: async (userId) =>
      apiCall(`/users/role-extra?user_id=${userId}`, "GET"),
    assignRole: async (userId, roleData) =>
      apiCall("/users/role-assign", "POST", { user_id: userId, ...roleData }),
    assignRoleToUser: async (userId, roleId) =>
      apiCall("/users/role-assign-to-user", "POST", {
        user_id: userId,
        role_id: roleId,
      }),
    revokeRoleFromUser: async (userId, roleId) =>
      apiCall("/users/role-revoke-from-user", "DELETE", {
        user_id: userId,
        role_id: roleId,
      }),

    // Permissions
    getPermissions: async (id = null) =>
      id
        ? apiCall(`/users/permissions-get/${id}`, "GET")
        : apiCall("/users/permissions-get", "GET"),
    updatePermissions: async (userId, permissions) =>
      apiCall("/users/permissions-update", "PUT", {
        user_id: userId,
        permissions,
      }),
    assignPermission: async (userId, permissionData) =>
      apiCall("/users/permission-assign", "POST", {
        user_id: userId,
        ...permissionData,
      }),
    assignPermissionToUserDirect: async (userId, permissionId) =>
      apiCall("/users/permission-assign-to-user-direct", "POST", {
        user_id: userId,
        permission_id: permissionId,
      }),
    revokePermissionFromUserDirect: async (userId, permissionId) =>
      apiCall("/users/permission-revoke-from-user-direct", "DELETE", {
        user_id: userId,
        permission_id: permissionId,
      }),
    assignPermissionToRole: async (roleId, permissionId) =>
      apiCall("/users/permission-assign-to-role", "POST", {
        role_id: roleId,
        permission_id: permissionId,
      }),
    revokePermissionFromRole: async (roleId, permissionId) =>
      apiCall("/users/permission-revoke-from-role", "DELETE", {
        role_id: roleId,
        permission_id: permissionId,
      }),

    // Bulk operations - Roles
    bulkCreateRoles: async (roles) =>
      apiCall("/users/roles-bulk-create", "POST", { roles }),
    bulkUpdateRoles: async (roles) =>
      apiCall("/users/roles-bulk-update", "PUT", { roles }),
    bulkDeleteRoles: async (roleIds) =>
      apiCall("/users/roles-bulk-delete", "DELETE", { role_ids: roleIds }),
    bulkAssignRolesToUser: async (userId, roleIds) =>
      apiCall("/users/roles-bulk-assign-to-user", "POST", {
        user_id: userId,
        role_ids: roleIds,
      }),
    bulkRevokeRolesFromUser: async (userId, roleIds) =>
      apiCall("/users/roles-bulk-revoke-from-user", "DELETE", {
        user_id: userId,
        role_ids: roleIds,
      }),
    bulkAssignUsersToRole: async (roleId, userIds) =>
      apiCall("/users/users-bulk-assign-to-role", "POST", {
        role_id: roleId,
        user_ids: userIds,
      }),
    bulkRevokeUsersFromRole: async (roleId, userIds) =>
      apiCall("/users/users-bulk-revoke-from-role", "DELETE", {
        role_id: roleId,
        user_ids: userIds,
      }),

    // Bulk operations - Permissions
    bulkAssignPermissionsToRole: async (roleId, permissionIds) =>
      apiCall("/users/permissions-bulk-assign-to-role", "POST", {
        role_id: roleId,
        permission_ids: permissionIds,
      }),
    bulkRevokePermissionsFromRole: async (roleId, permissionIds) =>
      apiCall("/users/permissions-bulk-revoke-from-role", "DELETE", {
        role_id: roleId,
        permission_ids: permissionIds,
      }),
    bulkAssignPermissionsToUser: async (userId, permissionIds) =>
      apiCall("/users/permissions-bulk-assign-to-user", "POST", {
        user_id: userId,
        permission_ids: permissionIds,
      }),
    bulkRevokePermissionsFromUser: async (userId, permissionIds) =>
      apiCall("/users/permissions-bulk-revoke-from-user", "DELETE", {
        user_id: userId,
        permission_ids: permissionIds,
      }),
    bulkAssignPermissionsToUserDirect: async (userId, permissionIds) =>
      apiCall("/users/permissions-bulk-assign-to-user-direct", "POST", {
        user_id: userId,
        permission_ids: permissionIds,
      }),
    bulkRevokePermissionsFromUserDirect: async (userId, permissionIds) =>
      apiCall("/users/permissions-bulk-revoke-from-user-direct", "DELETE", {
        user_id: userId,
        permission_ids: permissionIds,
      }),
    bulkAssignUsersToPermission: async (permissionId, userIds) =>
      apiCall("/users/users-bulk-assign-to-permission", "POST", {
        permission_id: permissionId,
        user_ids: userIds,
      }),
    bulkRevokeUsersFromPermission: async (permissionId, userIds) =>
      apiCall("/users/users-bulk-revoke-from-permission", "DELETE", {
        permission_id: permissionId,
        user_ids: userIds,
      }),

    // Sidebar
    getSidebarItems: async (userId) =>
      apiCall(`/users/sidebar-items?user_id=${userId}`, "GET"),
  },

  // Students endpoints
  students: {
    index: async () => apiCall("/students/index", "GET"),

    // CRUD
    get: async (id = null) =>
      id
        ? apiCall(`/students/student/${id}`, "GET")
        : apiCall("/students/student", "GET"),
    create: async (data) => apiCall("/students/student", "POST", data),
    update: async (id, data) => apiCall(`/students/student/${id}`, "PUT", data),
    delete: async (id) => apiCall(`/students/student/${id}`, "DELETE"),

    // Media
    uploadMedia: async (formData) =>
      apiCall("/students/media-upload", "POST", formData, {}, { isFile: true }),
    getMedia: async (id) => apiCall(`/students/media?student_id=${id}`, "GET"),
    deleteMedia: async (mediaId) =>
      apiCall("/students/media-delete", "POST", { media_id: mediaId }),

    // Profile & Info
    getProfile: async (id = null) =>
      id
        ? apiCall(`/students/profile-get/${id}`, "GET")
        : apiCall("/students/profile-get", "GET"),
    getAttendance: async (id = null) =>
      id
        ? apiCall(`/students/attendance-get/${id}`, "GET")
        : apiCall("/students/attendance-get", "GET"),
    getPerformance: async (id = null) =>
      id
        ? apiCall(`/students/performance-get/${id}`, "GET")
        : apiCall("/students/performance-get", "GET"),
    getFees: async (id = null) =>
      id
        ? apiCall(`/students/fees-get/${id}`, "GET")
        : apiCall("/students/fees-get", "GET"),
    getQrInfo: async (id = null) =>
      id
        ? apiCall(`/students/qr-info-get/${id}`, "GET")
        : apiCall("/students/qr-info-get", "GET"),
    getStatistics: async (id = null) =>
      id
        ? apiCall(`/students/statistics-get/${id}`, "GET")
        : apiCall("/students/statistics-get", "GET"),

    // Bulk operations
    bulkCreate: async (students) =>
      apiCall("/students/bulk-create", "POST", { students }),
    bulkUpdate: async (students) =>
      apiCall("/students/bulk-update", "POST", { students }),
    bulkDelete: async (studentIds) =>
      apiCall("/students/bulk-delete", "POST", { student_ids: studentIds }),
    bulkPromote: async (data) =>
      apiCall("/students/bulk-promote", "POST", data),

    // QR & ID Cards
    generateQrCode: async (studentId) =>
      apiCall("/students/qr-code-generate", "POST", { student_id: studentId }),
    generateEnhancedQrCode: async (studentId) =>
      apiCall("/students/qr-code-generate-enhanced", "POST", {
        student_id: studentId,
      }),
    generateIdCard: async (studentId) =>
      apiCall("/students/id-card-generate", "POST", { student_id: studentId }),
    generateClassIdCards: async (classId) =>
      apiCall("/students/id-card-generate-class", "POST", {
        class_id: classId,
      }),

    // Photo
    uploadPhoto: async (formData) =>
      apiCall("/students/photo-upload", "POST", formData, {}, { isFile: true }),

    // Admission workflow
    startAdmissionWorkflow: async (data) =>
      apiCall("/students/admission-start-workflow", "POST", data),
    verifyDocuments: async (studentId, data) =>
      apiCall("/students/admission-verify-documents", "POST", {
        student_id: studentId,
        ...data,
      }),
    conductInterview: async (studentId, data) =>
      apiCall("/students/admission-conduct-interview", "POST", {
        student_id: studentId,
        ...data,
      }),
    approveAdmission: async (studentId, data) =>
      apiCall("/students/admission-approve", "POST", {
        student_id: studentId,
        ...data,
      }),
    completeRegistration: async (studentId, data) =>
      apiCall("/students/admission-complete-registration", "POST", {
        student_id: studentId,
        ...data,
      }),
    getAdmissionWorkflowStatus: async (studentId) =>
      apiCall(
        `/students/admission-workflow-status?student_id=${studentId}`,
        "GET"
      ),

    // Transfer workflow
    startTransferWorkflow: async (data) =>
      apiCall("/students/transfer-start-workflow", "POST", data),
    verifyTransferEligibility: async (transferId, data) =>
      apiCall("/students/transfer-verify-eligibility", "POST", {
        transfer_id: transferId,
        ...data,
      }),
    approveTransfer: async (transferId, data) =>
      apiCall("/students/transfer-approve", "POST", {
        transfer_id: transferId,
        ...data,
      }),
    executeTransfer: async (transferId, data) =>
      apiCall("/students/transfer-execute", "POST", {
        transfer_id: transferId,
        ...data,
      }),
    getTransferWorkflowStatus: async (transferId) =>
      apiCall(
        `/students/transfer-workflow-status?transfer_id=${transferId}`,
        "GET"
      ),
    getTransferHistory: async (studentId) =>
      apiCall(`/students/transfer-history?student_id=${studentId}`, "GET"),

    // Promotions
    promoteSingle: async (data) =>
      apiCall("/students/promotion-single", "POST", data),
    promoteMultiple: async (data) =>
      apiCall("/students/promotion-multiple", "POST", data),
    promoteEntireClass: async (data) =>
      apiCall("/students/promotion-entire-class", "POST", data),
    promoteMultipleClasses: async (data) =>
      apiCall("/students/promotion-multiple-classes", "POST", data),
    graduateGrade9: async (data) =>
      apiCall("/students/promotion-graduate-grade9", "POST", data),
    getPromotionBatches: async (params) =>
      apiCall("/students/promotion-batches", "GET", null, params),
    getPromotionHistory: async (studentId) =>
      apiCall(`/students/promotion-history?student_id=${studentId}`, "GET"),

    // Parents
    getParents: async (id = null) =>
      id
        ? apiCall(`/students/parents-get/${id}`, "GET")
        : apiCall("/students/parents-get", "GET"),
    addParent: async (studentId, data) =>
      apiCall("/students/parents-add", "POST", {
        student_id: studentId,
        ...data,
      }),
    updateParent: async (parentId, data) =>
      apiCall(`/students/parents-update/${parentId}`, "PUT", data),
    removeParent: async (studentId, parentId) =>
      apiCall("/students/parents-remove", "POST", {
        student_id: studentId,
        parent_id: parentId,
      }),

    // Medical
    getMedical: async (id = null) =>
      id
        ? apiCall(`/students/medical-get/${id}`, "GET")
        : apiCall("/students/medical-get", "GET"),
    addMedical: async (studentId, data) =>
      apiCall("/students/medical-add", "POST", {
        student_id: studentId,
        ...data,
      }),
    updateMedical: async (medicalId, data) =>
      apiCall(`/students/medical-update/${medicalId}`, "PUT", data),

    // Discipline
    getDiscipline: async (id = null) =>
      id
        ? apiCall(`/students/discipline-get/${id}`, "GET")
        : apiCall("/students/discipline-get", "GET"),
    recordDiscipline: async (studentId, data) =>
      apiCall("/students/discipline-record", "POST", {
        student_id: studentId,
        ...data,
      }),
    updateDiscipline: async (recordId, data) =>
      apiCall(`/students/discipline-update/${recordId}`, "PUT", data),
    resolveDiscipline: async (recordId, data) =>
      apiCall("/students/discipline-resolve", "POST", {
        record_id: recordId,
        ...data,
      }),

    // Documents
    getDocuments: async (id = null) =>
      id
        ? apiCall(`/students/documents-get/${id}`, "GET")
        : apiCall("/students/documents-get", "GET"),
    uploadDocument: async (formData) =>
      apiCall(
        "/students/documents-upload",
        "POST",
        formData,
        {},
        { isFile: true }
      ),
    deleteDocument: async (documentId) =>
      apiCall(`/students/documents-delete/${documentId}`, "DELETE"),

    // By class/stream
    getByClass: async (id = null) =>
      id
        ? apiCall(`/students/by-class-get/${id}`, "GET")
        : apiCall("/students/by-class-get", "GET"),
    getByStream: async (id = null) =>
      id
        ? apiCall(`/students/by-stream-get/${id}`, "GET")
        : apiCall("/students/by-stream-get", "GET"),
    getRoster: async (id = null) =>
      id
        ? apiCall(`/students/roster-get/${id}`, "GET")
        : apiCall("/students/roster-get", "GET"),

    // Attendance
    markAttendance: async (data) =>
      apiCall("/students/attendance-mark", "POST", data),

    // Import
    importExisting: async (data) =>
      apiCall("/students/import-existing", "POST", data),
    importAddExisting: async (data) =>
      apiCall("/students/import-add-existing", "POST", data),
    importAddMultiple: async (data) =>
      apiCall("/students/import-add-multiple", "POST", data),
    getImportTemplate: async () =>
      apiCall(
        "/students/import-template",
        "GET",
        null,
        {},
        { isDownload: true }
      ),

    // Academic Year
    getCurrentAcademicYear: async () =>
      apiCall("/students/academic-year-current", "GET"),
    getAcademicYear: async (id = null) =>
      id
        ? apiCall(`/students/academic-year-get/${id}`, "GET")
        : apiCall("/students/academic-year-get", "GET"),
    getAllAcademicYears: async () =>
      apiCall("/students/academic-year-all", "GET"),
    createAcademicYear: async (data) =>
      apiCall("/students/academic-year-create", "POST", data),
    createNextAcademicYear: async (data) =>
      apiCall("/students/academic-year-create-next", "POST", data),
    setCurrentAcademicYear: async (yearId) =>
      apiCall("/students/academic-year-set-current", "POST", {
        year_id: yearId,
      }),
    updateAcademicYearStatus: async (yearId, status) =>
      apiCall("/students/academic-year-update-status", "PUT", {
        year_id: yearId,
        status,
      }),
    archiveAcademicYear: async (yearId) =>
      apiCall("/students/academic-year-archive", "POST", { year_id: yearId }),
    getAcademicYearTerms: async (yearId) =>
      apiCall(`/students/academic-year-terms?year_id=${yearId}`, "GET"),
    getCurrentTerm: async () =>
      apiCall("/students/academic-year-current-term", "GET"),

    // Alumni
    getAlumni: async (id = null) =>
      id
        ? apiCall(`/students/alumni-get/${id}`, "GET")
        : apiCall("/students/alumni-get", "GET"),

    // Enrollment
    getCurrentEnrollment: async () =>
      apiCall("/students/enrollment-current", "GET"),

    // Family Groups / Parents Management
    getFamilyGroups: async (filters = {}) =>
      apiCall("/students/family-groups/list", "GET", null, filters),
    searchFamilyGroups: async (searchTerm, limit = 50, offset = 0) =>
      apiCall(
        `/students/family-groups/search?q=${encodeURIComponent(
          searchTerm
        )}&limit=${limit}&offset=${offset}`,
        "GET"
      ),
    getFamilyGroupStats: async () =>
      apiCall("/students/family-groups/stats", "GET"),
    getFamilyGroupsView: async (filters = {}) =>
      apiCall("/students/family-groups/view", "GET", null, filters),
    getParentsList: async (filters = {}) =>
      apiCall("/students/parents/list", "GET", null, filters),
    getParentDetails: async (parentId) =>
      apiCall(`/students/parents/get?parent_id=${parentId}`, "GET"),
    getParentChildren: async (parentId) =>
      apiCall(`/students/parents/children?parent_id=${parentId}`, "GET"),
    createParentRecord: async (data) =>
      apiCall("/students/parents/create", "POST", data),
    updateParentRecord: async (parentId, data) =>
      apiCall("/students/parents/update", "POST", {
        parent_id: parentId,
        ...data,
      }),
    deleteParentRecord: async (parentId) =>
      apiCall("/students/parents/delete", "POST", { parent_id: parentId }),
    linkParentToStudent: async (parentId, studentId, linkData = {}) =>
      apiCall("/students/parents/link-child", "POST", {
        parent_id: parentId,
        student_id: studentId,
        ...linkData,
      }),
    unlinkParentFromStudent: async (parentId, studentId) =>
      apiCall("/students/parents/unlink-child", "POST", {
        parent_id: parentId,
        student_id: studentId,
      }),
    getAvailableStudentsForParent: async (parentId) =>
      apiCall(
        `/students/parents/available-students?parent_id=${parentId}`,
        "GET"
      ),
    getStudentsWithoutParents: async () =>
      apiCall("/students/without-parents", "GET"),
  },

  // Academic endpoints
  academic: {
    index: async () => apiCall("/academic/index", "GET"),
    get: async (id = null) =>
      id ? apiCall(`/academic/${id}`, "GET") : apiCall("/academic", "GET"),
    create: async (data) => apiCall("/academic", "POST", data),
    update: async (id, data) => apiCall(`/academic/${id}`, "PUT", data),
    delete: async (id) => apiCall(`/academic/${id}`, "DELETE"),

    // Exams workflow
    startExamsWorkflow: async (data) =>
      apiCall("/academic/exams-start-workflow", "POST", data),
    createSchedule: async (data) =>
      apiCall("/academic/exams-create-schedule", "POST", data),
    submitQuestions: async (data) =>
      apiCall("/academic/exams-submit-questions", "POST", data),
    prepareLogistics: async (data) =>
      apiCall("/academic/exams-prepare-logistics", "POST", data),
    conductExam: async (data) =>
      apiCall("/academic/exams-conduct", "POST", data),
    assignMarking: async (data) =>
      apiCall("/academic/exams-assign-marking", "POST", data),
    recordMarks: async (data) =>
      apiCall("/academic/exams-record-marks", "POST", data),
    verifyMarks: async (data) =>
      apiCall("/academic/exams-verify-marks", "POST", data),
    moderateMarks: async (data) =>
      apiCall("/academic/exams-moderate-marks", "POST", data),
    compileResults: async (data) =>
      apiCall("/academic/exams-compile-results", "POST", data),
    approveResults: async (data) =>
      apiCall("/academic/exams-approve-results", "POST", data),

    // Promotions workflow
    startPromotionsWorkflow: async (data) =>
      apiCall("/academic/promotions-start-workflow", "POST", data),
    identifyCandidates: async (data) =>
      apiCall("/academic/promotions-identify-candidates", "POST", data),
    validateEligibility: async (data) =>
      apiCall("/academic/promotions-validate-eligibility", "POST", data),
    executePromotions: async (data) =>
      apiCall("/academic/promotions-execute", "POST", data),
    generatePromotionReports: async (data) =>
      apiCall("/academic/promotions-generate-reports", "POST", data),

    // Assessments workflow
    startAssessmentsWorkflow: async (data) =>
      apiCall("/academic/assessments-start-workflow", "POST", data),
    createItems: async (data) =>
      apiCall("/academic/assessments-create-items", "POST", data),
    administer: async (data) =>
      apiCall("/academic/assessments-administer", "POST", data),
    markAndGrade: async (data) =>
      apiCall("/academic/assessments-mark-and-grade", "POST", data),
    analyzeResults: async (data) =>
      apiCall("/academic/assessments-analyze-results", "POST", data),

    // Reports workflow
    startReportsWorkflow: async (data) =>
      apiCall("/academic/reports-start-workflow", "POST", data),
    compileData: async (data) =>
      apiCall("/academic/reports-compile-data", "POST", data),
    generateStudentReports: async (data) =>
      apiCall("/academic/reports-generate-student-reports", "POST", data),
    reviewAndApprove: async (data) =>
      apiCall("/academic/reports-review-and-approve", "POST", data),
    distribute: async (data) =>
      apiCall("/academic/reports-distribute", "POST", data),

    // Library workflow
    startLibraryWorkflow: async (data) =>
      apiCall("/academic/library-start-workflow", "POST", data),
    reviewRequest: async (data) =>
      apiCall("/academic/library-review-request", "POST", data),
    catalogResources: async (data) =>
      apiCall("/academic/library-catalog-resources", "POST", data),
    distributeAndTrack: async (data) =>
      apiCall("/academic/library-distribute-and-track", "POST", data),

    // Curriculum workflow
    startCurriculumWorkflow: async (data) =>
      apiCall("/academic/curriculum-start-workflow", "POST", data),
    mapOutcomes: async (data) =>
      apiCall("/academic/curriculum-map-outcomes", "POST", data),
    createScheme: async (data) =>
      apiCall("/academic/curriculum-create-scheme", "POST", data),
    reviewAndApproveCurriculum: async (data) =>
      apiCall("/academic/curriculum-review-and-approve", "POST", data),

    // Year transition workflow
    startYearTransition: async (data) =>
      apiCall("/academic/year-transition-start-workflow", "POST", data),
    archiveData: async (data) =>
      apiCall("/academic/year-transition-archive-data", "POST", data),
    executeYearPromotions: async (data) =>
      apiCall("/academic/year-transition-execute-promotions", "POST", data),
    setupNewYear: async (data) =>
      apiCall("/academic/year-transition-setup-new-year", "POST", data),
    migrateCompetencyBaselines: async (data) =>
      apiCall(
        "/academic/year-transition-migrate-competency-baselines",
        "POST",
        data
      ),
    validateReadiness: async (data) =>
      apiCall("/academic/year-transition-validate-readiness", "POST", data),

    // Competency
    recordEvidence: async (data) =>
      apiCall("/academic/competency-record-evidence", "POST", data),
    recordCoreValueEvidence: async (data) =>
      apiCall("/academic/competency-record-core-value-evidence", "POST", data),
    getCompetencyDashboard: async (params) =>
      apiCall("/academic/competency-dashboard", "GET", null, params),

    // Academic Years
    listYears: async (params) =>
      apiCall("/academic/years/list", "GET", null, params),
    getYear: async (id) => apiCall(`/academic/years/get/${id}`, "GET"),
    createYear: async (data) => apiCall("/academic/years/create", "POST", data),
    updateYear: async (id, data) =>
      apiCall(`/academic/years/update/${id}`, "PUT", data),
    deleteYear: async (id) => apiCall(`/academic/years/delete/${id}`, "DELETE"),
    setCurrentYear: async (id) =>
      apiCall(`/academic/years/set-current/${id}`, "PUT"),
    getCurrentAcademicYear: async () =>
      apiCall("/academic/years-current", "GET"),
    getAcademicYear: async (id = null) =>
      id
        ? apiCall(`/academic/years-get/${id}`, "GET")
        : apiCall("/academic/years-get", "GET"),
    getAllAcademicYears: async () => apiCall("/academic/years-list", "GET"),
    createAcademicYear: async (data) =>
      apiCall("/academic/years-create", "POST", data),
    setCurrentAcademicYear: async (id) =>
      apiCall("/academic/years-set-current", "POST", { id }),

    // Terms
    createTerm: async (data) => apiCall("/academic/terms/create", "POST", data),
    listTerms: async (params) =>
      apiCall("/academic/terms-list", "GET", null, params),
    getTerm: async (id) => apiCall(`/academic/terms/get/${id}`, "GET"),
    updateTerm: async (id, data) =>
      apiCall(`/academic/terms/update/${id}`, "PUT", data),
    deleteTerm: async (id) => apiCall(`/academic/terms/delete/${id}`, "DELETE"),

    // Classes
    createClass: async (data) =>
      apiCall("/academic/classes/create", "POST", data),
    listClasses: async (params) =>
      apiCall("/academic/classes-list", "GET", null, params),
    getClass: async (id = null) =>
      id
        ? apiCall(`/academic/classes-get/${id}`, "GET")
        : apiCall("/academic/classes-get", "GET"),
    updateClass: async (id, data) =>
      apiCall(`/academic/classes/update/${id}`, "PUT", data),
    deleteClass: async (id) =>
      apiCall(`/academic/classes/delete/${id}`, "DELETE"),
    assignTeacher: async (data) =>
      apiCall("/academic/classes-assign-teacher", "POST", data),
    autoCreateStreams: async (data) =>
      apiCall("/academic/classes-auto-create-streams", "POST", data),

    // Streams
    createStream: async (data) =>
      apiCall("/academic/streams/create", "POST", data),
    listStreams: async (params) =>
      apiCall("/academic/streams-list", "GET", null, params),
    getStream: async (id) => apiCall(`/academic/streams/get/${id}`, "GET"),
    updateStream: async (id, data) =>
      apiCall(`/academic/streams/update/${id}`, "PUT", data),
    deleteStream: async (id) =>
      apiCall(`/academic/streams/delete/${id}`, "DELETE"),

    // Learning Areas (Subjects)
    listLearningAreas: async (params) =>
      apiCall("/academic/learning-areas/list", "GET", null, params),
    getLearningArea: async (id) =>
      apiCall(`/academic/learning-areas/get/${id}`, "GET"),
    createLearningArea: async (data) =>
      apiCall("/academic/learning-areas/create", "POST", data),
    updateLearningArea: async (id, data) =>
      apiCall(`/academic/learning-areas/update/${id}`, "PUT", data),
    deleteLearningArea: async (id) =>
      apiCall(`/academic/learning-areas/delete/${id}`, "DELETE"),

    // Schedules
    createSchedule: async (data) =>
      apiCall("/academic/schedules-create", "POST", data),
    listSchedules: async (params) =>
      apiCall("/academic/schedules-list", "GET", null, params),
    getSchedule: async (id = null) =>
      id
        ? apiCall(`/academic/schedules-get/${id}`, "GET")
        : apiCall("/academic/schedules-get", "GET"),
    updateSchedule: async (id, data) =>
      apiCall("/academic/schedules-update", "PUT", { id, ...data }),
    deleteSchedule: async (id) =>
      apiCall("/academic/schedules-delete", "DELETE", { id }),
    assignRoom: async (data) =>
      apiCall("/academic/schedules-assign-room", "POST", data),

    // Curriculum units
    createCurriculumUnit: async (data) =>
      apiCall("/academic/curriculum-units-create", "POST", data),
    listCurriculumUnits: async (params) =>
      apiCall("/academic/curriculum-units-list", "GET", null, params),
    getCurriculumUnit: async (id = null) =>
      id
        ? apiCall(`/academic/curriculum-units-get/${id}`, "GET")
        : apiCall("/academic/curriculum-units-get", "GET"),
    updateCurriculumUnit: async (id, data) =>
      apiCall("/academic/curriculum-units-update", "PUT", { id, ...data }),
    deleteCurriculumUnit: async (id) =>
      apiCall("/academic/curriculum-units-delete", "DELETE", { id }),

    // Topics
    createTopic: async (data) =>
      apiCall("/academic/topics-create", "POST", data),
    listTopics: async (params) =>
      apiCall("/academic/topics-list", "GET", null, params),
    getTopic: async (id = null) =>
      id
        ? apiCall(`/academic/topics-get/${id}`, "GET")
        : apiCall("/academic/topics-get", "GET"),
    updateTopic: async (id, data) =>
      apiCall("/academic/topics-update", "PUT", { id, ...data }),
    deleteTopic: async (id) =>
      apiCall("/academic/topics-delete", "DELETE", { id }),

    // Lesson plans
    createLessonPlan: async (data) =>
      apiCall("/academic/lesson-plans-create", "POST", data),
    listLessonPlans: async (params) =>
      apiCall("/academic/lesson-plans-list", "GET", null, params),
    getLessonPlan: async (id = null) =>
      id
        ? apiCall(`/academic/lesson-plans-get/${id}`, "GET")
        : apiCall("/academic/lesson-plans-get", "GET"),
    updateLessonPlan: async (id, data) =>
      apiCall("/academic/lesson-plans-update", "PUT", { id, ...data }),
    deleteLessonPlan: async (id) =>
      apiCall("/academic/lesson-plans-delete", "DELETE", { id }),
    approveLessonPlan: async (data) =>
      apiCall("/academic/lesson-plans-approve", "POST", data),

    // Lesson observations
    createLessonObservation: async (data) =>
      apiCall("/academic/lesson-observations-create", "POST", data),
    listLessonObservations: async (params) =>
      apiCall("/academic/lesson-observations-list", "GET", null, params),

    // Scheme of work
    createSchemeOfWork: async (data) =>
      apiCall("/academic/scheme-of-work-create", "POST", data),
    getSchemeOfWork: async (id = null) =>
      id
        ? apiCall(`/academic/scheme-of-work-get/${id}`, "GET")
        : apiCall("/academic/scheme-of-work-get", "GET"),

    // Teachers
    getTeacherClasses: async (teacherId) =>
      apiCall(`/academic/teachers-classes?teacher_id=${teacherId}`, "GET"),
    getTeacherSubjects: async (teacherId) =>
      apiCall(`/academic/teachers-subjects?teacher_id=${teacherId}`, "GET"),
    getTeacherSchedule: async (teacherId) =>
      apiCall(`/academic/teachers-schedule?teacher_id=${teacherId}`, "GET"),

    // Subjects
    getSubjectTeachers: async (subjectId) =>
      apiCall(`/academic/subjects-teachers?subject_id=${subjectId}`, "GET"),

    // Workflow
    getWorkflowStatus: async (workflowId) =>
      apiCall(`/academic/workflow-status?workflow_id=${workflowId}`, "GET"),

    // Custom
    getCustom: async (params) =>
      apiCall("/academic/custom", "GET", null, params),
    postCustom: async (data) => apiCall("/academic/custom", "POST", data),
  },

  // Attendance endpoints
  attendance: {
    index: async () => apiCall("/attendance/index", "GET"),

    // Student attendance
    getStudentHistory: async (studentId, params) =>
      apiCall(
        `/attendance/student-history?student_id=${studentId}`,
        "GET",
        null,
        params
      ),
    getStudentSummary: async (studentId, params) =>
      apiCall(
        `/attendance/student-summary?student_id=${studentId}`,
        "GET",
        null,
        params
      ),
    getClassAttendance: async (classId, params) =>
      apiCall(
        `/attendance/class-attendance?class_id=${classId}`,
        "GET",
        null,
        params
      ),
    getStudentPercentage: async (studentId, params) =>
      apiCall(
        `/attendance/student-percentage?student_id=${studentId}`,
        "GET",
        null,
        params
      ),
    getChronicAbsentees: async (params) =>
      apiCall("/attendance/chronic-student-absentees", "GET", null, params),

    // Staff attendance
    getStaffHistory: async (staffId, params) =>
      apiCall(
        `/attendance/staff-history?staff_id=${staffId}`,
        "GET",
        null,
        params
      ),
    getStaffSummary: async (staffId, params) =>
      apiCall(
        `/attendance/staff-summary?staff_id=${staffId}`,
        "GET",
        null,
        params
      ),
    getDepartmentAttendance: async (departmentId, params) =>
      apiCall(
        `/attendance/department-attendance?department_id=${departmentId}`,
        "GET",
        null,
        params
      ),
    getStaffPercentage: async (staffId, params) =>
      apiCall(
        `/attendance/staff-percentage?staff_id=${staffId}`,
        "GET",
        null,
        params
      ),
    getChronicStaffAbsentees: async (params) =>
      apiCall("/attendance/chronic-staff-absentees", "GET", null, params),

    // CRUD
    get: async (id = null) =>
      id ? apiCall(`/attendance/${id}`, "GET") : apiCall("/attendance", "GET"),
    create: async (data) => apiCall("/attendance", "POST", data),
    update: async (id, data) => apiCall(`/attendance/${id}`, "PUT", data),
    delete: async (id) => apiCall(`/attendance/${id}`, "DELETE"),

    // Legacy support
    markAttendance: async (data) => apiCall("/attendance", "POST", data),
    getStats: async (params = {}) =>
      apiCall("/attendance", "GET", null, params),
  },

  // Admission endpoints
  admission: {
    index: async () => apiCall("/admission/index", "GET"),
    // Get workflow queues by stage (for role-based views)
    getQueues: async () => apiCall("/admission/queues", "GET"),
    // Get single application details with workflow state
    getApplication: async (id) =>
      apiCall(`/admission/application/${id}`, "GET"),
    // Get admission statistics for dashboards
    getStats: async () => apiCall("/admission/stats", "GET"),
    // Get role-based notifications for dashboards
    getNotifications: async () => apiCall("/admission/notifications", "GET"),
    // Workflow stage methods
    submitApplication: async (data) =>
      apiCall("/admission/submit-application", "POST", data),
    uploadDocument: async (formData) =>
      apiCall(
        "/admission/upload-document",
        "POST",
        formData,
        {},
        { isFile: true }
      ),
    verifyDocument: async (data) =>
      apiCall("/admission/verify-document", "POST", data),
    scheduleInterview: async (data) =>
      apiCall("/admission/schedule-interview", "POST", data),
    recordInterviewResults: async (data) =>
      apiCall("/admission/record-interview-results", "POST", data),
    generatePlacementOffer: async (data) =>
      apiCall("/admission/generate-placement-offer", "POST", data),
    recordFeePayment: async (data) =>
      apiCall("/admission/record-fee-payment", "POST", data),
    completeEnrollment: async (data) =>
      apiCall("/admission/complete-enrollment", "POST", data),
  },

  // Communications endpoints
  communications: {
    index: async () => apiCall("/communications/index", "GET"),

    // SMS callbacks
    smsDeliveryReport: async (data) =>
      apiCall("/communications/sms-delivery-report", "POST", data),
    smsOptOutCallback: async (data) =>
      apiCall("/communications/sms-opt-out-callback", "POST", data),
    smsSubscriptionCallback: async (data) =>
      apiCall("/communications/sms-subscription-callback", "POST", data),

    // Contact
    getContact: async (id = null) =>
      id
        ? apiCall(`/communications/contact/${id}`, "GET")
        : apiCall("/communications/contact", "GET"),
    createContact: async (data) =>
      apiCall("/communications/contact", "POST", data),
    updateContact: async (id, data) =>
      apiCall(`/communications/contact/${id}`, "PUT", data),
    deleteContact: async (id) =>
      apiCall(`/communications/contact/${id}`, "DELETE"),

    // Inbound messages
    getInbound: async (id = null) =>
      id
        ? apiCall(`/communications/inbound/${id}`, "GET")
        : apiCall("/communications/inbound", "GET"),
    createInbound: async (data) =>
      apiCall("/communications/inbound", "POST", data),
    updateInbound: async (id, data) =>
      apiCall(`/communications/inbound/${id}`, "PUT", data),
    deleteInbound: async (id) =>
      apiCall(`/communications/inbound/${id}`, "DELETE"),

    // Thread
    getThread: async (id = null) =>
      id
        ? apiCall(`/communications/thread/${id}`, "GET")
        : apiCall("/communications/thread", "GET"),
    createThread: async (data) =>
      apiCall("/communications/thread", "POST", data),
    updateThread: async (id, data) =>
      apiCall(`/communications/thread/${id}`, "PUT", data),
    deleteThread: async (id) =>
      apiCall(`/communications/thread/${id}`, "DELETE"),

    // Announcement
    getAnnouncement: async (id = null) =>
      id
        ? apiCall(`/communications/announcement/${id}`, "GET")
        : apiCall("/communications/announcement", "GET"),
    createAnnouncement: async (data) =>
      apiCall("/communications/announcement", "POST", data),
    updateAnnouncement: async (id, data) =>
      apiCall(`/communications/announcement/${id}`, "PUT", data),
    deleteAnnouncement: async (id) =>
      apiCall(`/communications/announcement/${id}`, "DELETE"),

    // Internal request
    getInternalRequest: async (id = null) =>
      id
        ? apiCall(`/communications/internal-request/${id}`, "GET")
        : apiCall("/communications/internal-request", "GET"),
    createInternalRequest: async (data) =>
      apiCall("/communications/internal-request", "POST", data),
    updateInternalRequest: async (id, data) =>
      apiCall(`/communications/internal-request/${id}`, "PUT", data),
    deleteInternalRequest: async (id) =>
      apiCall(`/communications/internal-request/${id}`, "DELETE"),

    // Parent message
    getParentMessage: async (id = null) =>
      id
        ? apiCall(`/communications/parent-message/${id}`, "GET")
        : apiCall("/communications/parent-message", "GET"),
    createParentMessage: async (data) =>
      apiCall("/communications/parent-message", "POST", data),
    updateParentMessage: async (id, data) =>
      apiCall(`/communications/parent-message/${id}`, "PUT", data),
    deleteParentMessage: async (id) =>
      apiCall(`/communications/parent-message/${id}`, "DELETE"),

    // Staff forum topic
    getStaffForumTopic: async (id = null) =>
      id
        ? apiCall(`/communications/staff-forum-topic/${id}`, "GET")
        : apiCall("/communications/staff-forum-topic", "GET"),
    createStaffForumTopic: async (data) =>
      apiCall("/communications/staff-forum-topic", "POST", data),
    updateStaffForumTopic: async (id, data) =>
      apiCall(`/communications/staff-forum-topic/${id}`, "PUT", data),
    deleteStaffForumTopic: async (id) =>
      apiCall(`/communications/staff-forum-topic/${id}`, "DELETE"),

    // Staff request
    getStaffRequest: async (id = null) =>
      id
        ? apiCall(`/communications/staff-request/${id}`, "GET")
        : apiCall("/communications/staff-request", "GET"),
    createStaffRequest: async (data) =>
      apiCall("/communications/staff-request", "POST", data),
    updateStaffRequest: async (id, data) =>
      apiCall(`/communications/staff-request/${id}`, "PUT", data),
    deleteStaffRequest: async (id) =>
      apiCall(`/communications/staff-request/${id}`, "DELETE"),

    // Communication
    getCommunication: async (id = null) =>
      id
        ? apiCall(`/communications/communication/${id}`, "GET")
        : apiCall("/communications/communication", "GET"),
    createCommunication: async (data) =>
      apiCall("/communications/communication", "POST", data),
    updateCommunication: async (id, data) =>
      apiCall(`/communications/communication/${id}`, "PUT", data),
    deleteCommunication: async (id) =>
      apiCall(`/communications/communication/${id}`, "DELETE"),

    // Attachment
    getAttachment: async (id = null) =>
      id
        ? apiCall(`/communications/attachment/${id}`, "GET")
        : apiCall("/communications/attachment", "GET"),
    createAttachment: async (formData) =>
      apiCall(
        "/communications/attachment",
        "POST",
        formData,
        {},
        { isFile: true }
      ),
    deleteAttachment: async (id) =>
      apiCall(`/communications/attachment/${id}`, "DELETE"),

    // Group
    getGroup: async (id = null) =>
      id
        ? apiCall(`/communications/group/${id}`, "GET")
        : apiCall("/communications/group", "GET"),
    createGroup: async (data) => apiCall("/communications/group", "POST", data),
    updateGroup: async (id, data) =>
      apiCall(`/communications/group/${id}`, "PUT", data),
    deleteGroup: async (id) => apiCall(`/communications/group/${id}`, "DELETE"),

    // Log
    getLog: async (id = null, params) =>
      id
        ? apiCall(`/communications/log/${id}`, "GET")
        : apiCall("/communications/log", "GET", null, params),
    createLog: async (data) => apiCall("/communications/log", "POST", data),

    // Recipient
    getRecipient: async (id = null) =>
      id
        ? apiCall(`/communications/recipient/${id}`, "GET")
        : apiCall("/communications/recipient", "GET"),
    createRecipient: async (data) =>
      apiCall("/communications/recipient", "POST", data),
    deleteRecipient: async (id) =>
      apiCall(`/communications/recipient/${id}`, "DELETE"),

    // Template
    getTemplate: async (id = null) =>
      id
        ? apiCall(`/communications/template/${id}`, "GET")
        : apiCall("/communications/template", "GET"),
    createTemplate: async (data) =>
      apiCall("/communications/template", "POST", data),
    updateTemplate: async (id, data) =>
      apiCall(`/communications/template/${id}`, "PUT", data),
    deleteTemplate: async (id) =>
      apiCall(`/communications/template/${id}`, "DELETE"),

    // Workflow instance
    getWorkflowInstance: async (id = null) =>
      id
        ? apiCall(`/communications/workflow-instance/${id}`, "GET")
        : apiCall("/communications/workflow-instance", "GET"),
    createWorkflowInstance: async (data) =>
      apiCall("/communications/workflow-instance", "POST", data),
    updateWorkflowInstance: async (id, data) =>
      apiCall(`/communications/workflow-instance/${id}`, "PUT", data),

    // Legacy support
    sendMessage: async (data) =>
      apiCall("/communications/communication", "POST", data),
    getMessages: async (params = {}) =>
      apiCall("/communications/communication", "GET", null, params),
    broadcast: async (data) =>
      apiCall("/communications/announcement", "POST", data),
    getNotifications: async (params = {}) =>
      apiCall("/communications/communication", "GET", null, params),
    markAsRead: async (messageId) =>
      apiCall(`/communications/communication/${messageId}`, "PUT", {
        read: true,
      }),
    getUnreadCount: async () =>
      apiCall("/communications/communication?status=unread", "GET"),
    getTemplates: async () => apiCall("/communications/template", "GET"),
    saveTemplate: async (data) =>
      apiCall("/communications/template", "POST", data),
  },

  // Finance endpoints
  finance: {
    index: async () => apiCall("/finance/index", "GET"),
    get: async (id = null) =>
      id ? apiCall(`/finance/${id}`, "GET") : apiCall("/finance", "GET"),
    create: async (data) => apiCall("/finance", "POST", data),
    update: async (id, data) => apiCall(`/finance/${id}`, "PUT", data),
    delete: async (id) => apiCall(`/finance/${id}`, "DELETE"),

    // Department budgets
    proposeBudget: async (data) =>
      apiCall("/finance/department-budgets-propose", "POST", data),
    getBudgetProposals: async (params) =>
      apiCall("/finance/department-budgets-proposals", "GET", null, params),
    approveBudget: async (data) =>
      apiCall("/finance/department-budgets-approve", "POST", data),
    allocateBudget: async (data) =>
      apiCall("/finance/department-budgets-allocate", "POST", data),
    requestFunds: async (data) =>
      apiCall("/finance/department-budgets-request-funds", "POST", data),

    // Payrolls
    listPayrolls: async (params) =>
      apiCall("/finance/payrolls-list", "GET", null, params),
    getPayroll: async (id = null) =>
      id
        ? apiCall(`/finance/payrolls-get/${id}`, "GET")
        : apiCall("/finance/payrolls-get", "GET"),
    getStaffPayments: async (payrollId, params) =>
      apiCall(
        `/finance/payrolls-staff-payments?payroll_id=${payrollId}`,
        "GET",
        null,
        params
      ),
    createDraftPayroll: async (data) =>
      apiCall("/finance/payrolls-create-draft", "POST", data),
    calculatePayroll: async (data) =>
      apiCall("/finance/payrolls-calculate", "POST", data),
    recalculatePayroll: async (data) =>
      apiCall("/finance/payrolls-recalculate", "POST", data),
    verifyPayroll: async (data) =>
      apiCall("/finance/payrolls-verify", "POST", data),
    approvePayroll: async (data) =>
      apiCall("/finance/payrolls-approve", "POST", data),
    rejectPayroll: async (data) =>
      apiCall("/finance/payrolls-reject", "POST", data),
    processPayroll: async (data) =>
      apiCall("/finance/payrolls-process", "POST", data),
    disbursePayroll: async (data) =>
      apiCall("/finance/payrolls-disburse", "POST", data),
    cancelPayroll: async (data) =>
      apiCall("/finance/payrolls-cancel", "POST", data),
    getPayrollStatus: async (payrollId) =>
      apiCall(`/finance/payrolls-status?payroll_id=${payrollId}`, "GET"),
    getStaffPayment: async (id = null) =>
      id
        ? apiCall(`/finance/payrolls-staff-payments-get/${id}`, "GET")
        : apiCall("/finance/payrolls-staff-payments-get", "GET"),
    getPayrollSummary: async (payrollId) =>
      apiCall(`/finance/payrolls-summary?payroll_id=${payrollId}`, "GET"),
    getPayrollHistory: async (params) =>
      apiCall("/finance/payrolls-history", "GET", null, params),

    // Enhanced Payroll with Children Fee Deductions
    getStaffForPayroll: async () =>
      apiCall("/finance/staff-for-payroll", "GET"),
    getStaffPayrollDetails: async (staffId) =>
      apiCall(`/finance/staff-payroll-details?staff_id=${staffId}`, "GET"),
    processPayrollWithDeductions: async (data) =>
      apiCall("/finance/process-payroll-with-deductions", "POST", data),
    getDetailedPayslip: async (payrollId) =>
      apiCall(`/finance/detailed-payslip?payroll_id=${payrollId}`, "GET"),
    getPayrollStats: async (month, year) =>
      apiCall(`/finance/payroll-stats?month=${month}&year=${year}`, "GET"),
    getPayrollList: async (filters) =>
      apiCall("/finance/payroll-list", "GET", null, filters),
    markPayrollPaid: async (payrollId, paymentRef = "") =>
      apiCall("/finance/mark-payroll-paid", "POST", {
        payroll_id: payrollId,
        payment_reference: paymentRef,
      }),

    // Payments
    generateReceipt: async (paymentId) =>
      apiCall("/finance/payments-generate-receipt", "POST", {
        payment_id: paymentId,
      }),
    generatePayslip: async (staffPaymentId) =>
      apiCall("/finance/payments-generate-payslip", "POST", {
        staff_payment_id: staffPaymentId,
      }),
    sendNotification: async (data) =>
      apiCall("/finance/payments-send-notification", "POST", data),

    // Fees
    createAnnualStructure: async (data) =>
      apiCall("/finance/fees-create-annual-structure", "POST", data),
    reviewStructure: async (data) =>
      apiCall("/finance/fees-review-structure", "POST", data),
    approveStructure: async (data) =>
      apiCall("/finance/fees-approve-structure", "POST", data),
    activateStructure: async (data) =>
      apiCall("/finance/fees-activate-structure", "POST", data),
    rolloverStructure: async (data) =>
      apiCall("/finance/fees-rollover-structure", "POST", data),
    getTermBreakdown: async (params) =>
      apiCall("/finance/fees-term-breakdown", "GET", null, params),
    getPendingReviews: async () =>
      apiCall("/finance/fees-pending-reviews", "GET"),
    getAnnualSummary: async (params) =>
      apiCall("/finance/fees-annual-summary", "GET", null, params),

    // Students
    getStudentPaymentHistory: async (studentId, params) =>
      apiCall(
        `/finance/students-payment-history?student_id=${studentId}`,
        "GET",
        null,
        params
      ),

    // Reports
    generatePayrollReport: async (data) =>
      apiCall("/finance/reports-generate-payroll", "POST", data),
    compareYearlyCollections: async (params) =>
      apiCall(
        "/finance/reports-compare-yearly-collections",
        "GET",
        null,
        params
      ),

    // Legacy support
    getFees: async (params = {}) => apiCall("/finance", "GET", null, params),
    recordPayment: async (data) => apiCall("/finance", "POST", data),
    getTransactions: async (params = {}) =>
      apiCall("/finance", "GET", null, params),
    getPayments: async (params = {}) =>
      apiCall("/finance/payrolls-staff-payments", "GET", null, params),
    getStats: async () => apiCall("/finance", "GET"),
    getOutstandingFees: async () =>
      apiCall("/finance/fees-annual-summary", "GET"),
    getPaymentHistory: async (params = {}) =>
      apiCall("/finance/payrolls-history", "GET", null, params),
  },

  // Inventory endpoints
  inventory: {
    index: async () => apiCall("/inventory/index", "GET"),
    get: async (id = null) =>
      id
        ? apiCall(`/inventory/inventory/${id}`, "GET")
        : apiCall("/inventory/inventory", "GET"),
    create: async (data) => apiCall("/inventory/inventory", "POST", data),
    update: async (id, data) =>
      apiCall(`/inventory/inventory/${id}`, "PUT", data),
    delete: async (id) => apiCall(`/inventory/inventory/${id}`, "DELETE"),

    // Items
    listItems: async (params) =>
      apiCall("/inventory/items-list", "GET", null, params),
    getItemsWithStock: async (params) =>
      apiCall("/inventory/items-with-stock", "GET", null, params),
    getLowStockItems: async (params) =>
      apiCall("/inventory/items-low-stock", "GET", null, params),
    getStockValuation: async (params) =>
      apiCall("/inventory/items-stock-valuation", "GET", null, params),
    getItemHistory: async (itemId, params) =>
      apiCall(
        `/inventory/items-history?item_id=${itemId}`,
        "GET",
        null,
        params
      ),

    // Categories
    listCategories: async (params) =>
      apiCall("/inventory/categories-list", "GET", null, params),
    getCategory: async (id = null) =>
      id
        ? apiCall(`/inventory/categories-get/${id}`, "GET")
        : apiCall("/inventory/categories-get", "GET"),
    createCategory: async (data) =>
      apiCall("/inventory/categories-create", "POST", data),
    updateCategory: async (id, data) =>
      apiCall("/inventory/categories-update", "PUT", { id, ...data }),
    deleteCategory: async (id) =>
      apiCall("/inventory/categories-delete", "DELETE", { id }),

    // Locations
    listLocations: async (params) =>
      apiCall("/inventory/locations-list", "GET", null, params),
    getLocation: async (id = null) =>
      id
        ? apiCall(`/inventory/locations-get/${id}`, "GET")
        : apiCall("/inventory/locations-get", "GET"),
    createLocation: async (data) =>
      apiCall("/inventory/locations-create", "POST", data),
    updateLocation: async (id, data) =>
      apiCall("/inventory/locations-update", "PUT", { id, ...data }),
    deleteLocation: async (id) =>
      apiCall("/inventory/locations-delete", "DELETE", { id }),

    // Suppliers
    listSuppliers: async (params) =>
      apiCall("/inventory/suppliers-list", "GET", null, params),
    getSupplier: async (id = null) =>
      id
        ? apiCall(`/inventory/suppliers-get/${id}`, "GET")
        : apiCall("/inventory/suppliers-get", "GET"),
    createSupplier: async (data) =>
      apiCall("/inventory/suppliers-create", "POST", data),
    updateSupplier: async (id, data) =>
      apiCall("/inventory/suppliers-update", "PUT", { id, ...data }),
    deleteSupplier: async (id) =>
      apiCall("/inventory/suppliers-delete", "DELETE", { id }),

    // Purchase orders
    listPurchaseOrders: async (params) =>
      apiCall("/inventory/purchase-orders-list", "GET", null, params),
    getPurchaseOrder: async (id = null) =>
      id
        ? apiCall(`/inventory/purchase-orders-get/${id}`, "GET")
        : apiCall("/inventory/purchase-orders-get", "GET"),
    createPurchaseOrder: async (data) =>
      apiCall("/inventory/purchase-orders-create", "POST", data),
    updatePurchaseOrder: async (id, data) =>
      apiCall("/inventory/purchase-orders-update", "PUT", { id, ...data }),
    receivePurchaseOrder: async (data) =>
      apiCall("/inventory/purchase-orders-receive", "POST", data),

    // Requisitions
    listRequisitions: async (params) =>
      apiCall("/inventory/requisitions-list", "GET", null, params),
    getRequisition: async (id = null) =>
      id
        ? apiCall(`/inventory/requisitions-get/${id}`, "GET")
        : apiCall("/inventory/requisitions-get", "GET"),
    createRequisition: async (data) =>
      apiCall("/inventory/requisitions-create", "POST", data),
    updateRequisitionStatus: async (id, status) =>
      apiCall("/inventory/requisitions-update-status", "PUT", { id, status }),
    deleteRequisition: async (id) =>
      apiCall("/inventory/requisitions-delete", "DELETE", { id }),

    // Movements
    listMovements: async (params) =>
      apiCall("/inventory/movements-list", "GET", null, params),
    getMovementsSummary: async (params) =>
      apiCall("/inventory/movements-summary", "GET", null, params),
    adjustStock: async (data) =>
      apiCall("/inventory/movements-adjust-stock", "POST", data),
    recordMovement: async (data) =>
      apiCall("/inventory/movements-record", "POST", data),

    // Procurement workflow
    initiateProcurement: async (data) =>
      apiCall("/inventory/procurement-initiate", "POST", data),
    verifyBudget: async (data) =>
      apiCall("/inventory/procurement-verify-budget", "POST", data),
    requestQuotations: async (data) =>
      apiCall("/inventory/procurement-request-quotations", "POST", data),
    evaluateQuotations: async (data) =>
      apiCall("/inventory/procurement-evaluate-quotations", "POST", data),
    approveProcurement: async (data) =>
      apiCall("/inventory/procurement-approve", "POST", data),
    createPO: async (data) =>
      apiCall("/inventory/procurement-create-po", "POST", data),

    // Disposal workflow
    initiateDisposal: async (data) =>
      apiCall("/inventory/disposal-initiate", "POST", data),
    assessCondition: async (data) =>
      apiCall("/inventory/disposal-assess-condition", "POST", data),
    performValuation: async (data) =>
      apiCall("/inventory/disposal-perform-valuation", "POST", data),
    selectMethod: async (data) =>
      apiCall("/inventory/disposal-select-method", "POST", data),
    approveDisposal: async (data) =>
      apiCall("/inventory/disposal-approve", "POST", data),
    executeDisposal: async (data) =>
      apiCall("/inventory/disposal-execute", "POST", data),

    // Transfer workflow
    initiateTransfer: async (data) =>
      apiCall("/inventory/transfer-initiate", "POST", data),
    approveTransfer: async (data) =>
      apiCall("/inventory/transfer-approve", "POST", data),
    pickStock: async (data) =>
      apiCall("/inventory/transfer-pick-stock", "POST", data),
    qualityCheck: async (data) =>
      apiCall("/inventory/transfer-quality-check", "POST", data),
    dispatch: async (data) =>
      apiCall("/inventory/transfer-dispatch", "POST", data),
    receiveTransfer: async (data) =>
      apiCall("/inventory/transfer-receive", "POST", data),
    inspect: async (data) =>
      apiCall("/inventory/transfer-inspect", "POST", data),

    // Audit workflow
    initiateAudit: async (data) =>
      apiCall("/inventory/audit-initiate", "POST", data),
    scheduleAudit: async (data) =>
      apiCall("/inventory/audit-schedule", "POST", data),
    prepareCount: async (data) =>
      apiCall("/inventory/audit-prepare-count", "POST", data),
    performCount: async (data) =>
      apiCall("/inventory/audit-perform-count", "POST", data),
    verifyCount: async (data) =>
      apiCall("/inventory/audit-verify-count", "POST", data),
    analyzeVariances: async (data) =>
      apiCall("/inventory/audit-analyze-variances", "POST", data),
    approveAdjustments: async (data) =>
      apiCall("/inventory/audit-approve-adjustments", "POST", data),
    postAdjustments: async (data) =>
      apiCall("/inventory/audit-post-adjustments", "POST", data),

    // Dashboard
    getDashboard: async (params) =>
      apiCall("/inventory/dashboard", "GET", null, params),
    getWorkflow: async (id = null) =>
      id
        ? apiCall(`/inventory/workflow-get/${id}`, "GET")
        : apiCall("/inventory/workflow-get", "GET"),

    // Legacy support
    list: async (params = {}) =>
      apiCall("/inventory/items-list", "GET", null, params),
    addItem: async (data) => apiCall("/inventory/inventory", "POST", data),
    updateItem: async (id, data) =>
      apiCall(`/inventory/inventory/${id}`, "PUT", data),
    deleteItem: async (id) => apiCall(`/inventory/inventory/${id}`, "DELETE"),
    getStock: async (params = {}) =>
      apiCall("/inventory/items-with-stock", "GET", null, params),
    getCategories: async () => apiCall("/inventory/categories-list", "GET"),
    getSuppliers: async () => apiCall("/inventory/suppliers-list", "GET"),
  },

  // Staff endpoints
  staff: {
    index: async () => apiCall("/staff/index", "GET"),
    get: async (id = null) =>
      id ? apiCall(`/staff/${id}`, "GET") : apiCall("/staff", "GET"),
    create: async (data) => apiCall("/staff", "POST", data),
    update: async (id, data) => apiCall(`/staff/${id}`, "PUT", data),
    delete: async (id) => apiCall(`/staff/${id}`, "DELETE"),

    // Profile & Details
    getProfile: async (id = null) =>
      id
        ? apiCall(`/staff/profile-get/${id}`, "GET")
        : apiCall("/staff/profile-get", "GET"),
    getSchedule: async (id = null) =>
      id
        ? apiCall(`/staff/schedule-get/${id}`, "GET")
        : apiCall("/staff/schedule-get", "GET"),
    getDepartments: async (id = null) =>
      id
        ? apiCall(`/staff/departments-get/${id}`, "GET")
        : apiCall("/staff/departments-get", "GET"),

    // Assignments
    assignClass: async (data) => apiCall("/staff/assign-class", "POST", data),
    assignSubject: async (data) =>
      apiCall("/staff/assign-subject", "POST", data),
    getAssignments: async (id = null) =>
      id
        ? apiCall(`/staff/assignments-get/${id}`, "GET")
        : apiCall("/staff/assignments-get", "GET"),
    getCurrentAssignments: async (staffId) =>
      apiCall(`/staff/assignments-current?staff_id=${staffId}`, "GET"),
    getWorkload: async (id = null) =>
      id
        ? apiCall(`/staff/workload-get/${id}`, "GET")
        : apiCall("/staff/workload-get", "GET"),
    initiateAssignment: async (data) =>
      apiCall("/staff/assignment-initiate", "POST", data),

    // Attendance
    getAttendance: async (id = null) =>
      id
        ? apiCall(`/staff/attendance-get/${id}`, "GET")
        : apiCall("/staff/attendance-get", "GET"),
    markAttendance: async (data) =>
      apiCall("/staff/attendance-mark", "POST", data),

    // Leaves
    listLeaves: async (params) =>
      apiCall("/staff/leaves-list", "GET", null, params),
    applyLeave: async (data) => apiCall("/staff/leaves-apply", "POST", data),
    updateLeaveStatus: async (id, status) =>
      apiCall("/staff/leaves-update-status", "PUT", { id, status }),
    initiateLeaveRequest: async (data) =>
      apiCall("/staff/leave-initiate-request", "POST", data),

    // Payroll
    getPayslip: async (staffId, params) =>
      apiCall(
        `/staff/payroll-payslip?staff_id=${staffId}`,
        "GET",
        null,
        params
      ),
    getPayrollHistory: async (staffId, params) =>
      apiCall(
        `/staff/payroll-history?staff_id=${staffId}`,
        "GET",
        null,
        params
      ),
    getAllowances: async (staffId) =>
      apiCall(`/staff/payroll-allowances?staff_id=${staffId}`, "GET"),
    getDeductions: async (staffId) =>
      apiCall(`/staff/payroll-deductions?staff_id=${staffId}`, "GET"),
    getLoanDetails: async (staffId) =>
      apiCall(`/staff/payroll-loan-details?staff_id=${staffId}`, "GET"),
    requestAdvance: async (data) =>
      apiCall("/staff/payroll-request-advance", "POST", data),
    applyLoan: async (data) =>
      apiCall("/staff/payroll-apply-loan", "POST", data),
    downloadP9: async (staffId, year) =>
      apiCall(
        `/staff/payroll-download-p9?staff_id=${staffId}&year=${year}`,
        "GET",
        null,
        {},
        { isDownload: true }
      ),
    downloadPayslip: async (staffId, params) =>
      apiCall(
        `/staff/payroll-download-payslip?staff_id=${staffId}`,
        "GET",
        null,
        params,
        { isDownload: true }
      ),
    exportHistory: async (staffId, params) =>
      apiCall(
        `/staff/payroll-export-history?staff_id=${staffId}`,
        "GET",
        null,
        params,
        { isDownload: true }
      ),

    // Staff Children (for fee deductions)
    getStaffChildren: async (staffId) =>
      apiCall(`/staff/children-list?staff_id=${staffId}`, "GET"),
    addStaffChild: async (data) => apiCall("/staff/children-add", "POST", data),
    updateStaffChild: async (id, data) =>
      apiCall(`/staff/children-update/${id}`, "PUT", data),
    removeStaffChild: async (id) =>
      apiCall(`/staff/children-remove/${id}`, "DELETE"),
    getChildFeeConfig: async () => apiCall("/staff/children-fee-config", "GET"),
    calculateChildFeeDeductions: async (staffId, month, year) =>
      apiCall(
        `/staff/children-calculate-deductions?staff_id=${staffId}&month=${month}&year=${year}`,
        "GET"
      ),

    // Detailed Payslips
    generateDetailedPayslip: async (staffId, month, year) =>
      apiCall(
        `/staff/payroll-detailed-payslip?staff_id=${staffId}&month=${month}&year=${year}`,
        "GET"
      ),
    downloadDetailedPayslip: async (staffId, month, year) =>
      apiCall(
        `/staff/payroll-download-detailed-payslip?staff_id=${staffId}&month=${month}&year=${year}`,
        "GET",
        null,
        {},
        { isDownload: true }
      ),

    // Performance
    getPerformanceReviewHistory: async (staffId, params) =>
      apiCall(
        `/staff/performance-review-history?staff_id=${staffId}`,
        "GET",
        null,
        params
      ),
    generatePerformanceReport: async (staffId, params) =>
      apiCall(
        `/staff/performance-generate-report?staff_id=${staffId}`,
        "GET",
        null,
        params
      ),
    getAcademicKPISummary: async (staffId, params) =>
      apiCall(
        `/staff/performance-academic-kpi-summary?staff_id=${staffId}`,
        "GET",
        null,
        params
      ),

    // Legacy support
    list: async (params = {}) => apiCall("/staff", "GET", null, params),
    assignRole: async (id, roleData) =>
      apiCall("/staff/assign-class", "POST", { staff_id: id, ...roleData }),
    updatePermissions: async (id, permissions) =>
      apiCall(`/staff/${id}`, "PUT", { permissions }),
  },

  // Transport endpoints
  transport: {
    index: async () => apiCall("/transport/index", "GET"),

    // Student verification
    verifyStudent: async (data) =>
      apiCall("/transport/verify-student", "POST", data),

    // Routes
    getRoute: async (id = null) =>
      id
        ? apiCall(`/transport/route/${id}`, "GET")
        : apiCall("/transport/route", "GET"),
    getAllRoutes: async (params) =>
      apiCall("/transport/all-routes", "GET", null, params),
    createRoute: async (data) => apiCall("/transport/route", "POST", data),
    updateRoute: async (id, data) =>
      apiCall(`/transport/route/${id}`, "PUT", data),
    deleteRoute: async (id) => apiCall(`/transport/route/${id}`, "DELETE"),

    // Stops
    getStop: async (id = null) =>
      id
        ? apiCall(`/transport/stop/${id}`, "GET")
        : apiCall("/transport/stop", "GET"),
    getAllStops: async (params) =>
      apiCall("/transport/all-stops", "GET", null, params),
    createStop: async (data) => apiCall("/transport/stop", "POST", data),
    updateStop: async (id, data) =>
      apiCall(`/transport/stop/${id}`, "PUT", data),
    deleteStop: async (id) => apiCall(`/transport/stop/${id}`, "DELETE"),

    // Vehicles
    getVehicle: async (id = null) =>
      id
        ? apiCall(`/transport/vehicle/${id}`, "GET")
        : apiCall("/transport/vehicle", "GET"),

    // Drivers
    getDriver: async (id = null) =>
      id
        ? apiCall(`/transport/driver/${id}`, "GET")
        : apiCall("/transport/driver", "GET"),
    getAllDrivers: async (params) =>
      apiCall("/transport/all-drivers", "GET", null, params),
    createDriver: async (data) => apiCall("/transport/driver", "POST", data),
    updateDriver: async (id, data) =>
      apiCall(`/transport/driver/${id}`, "PUT", data),
    deleteDriver: async (id) => apiCall(`/transport/driver/${id}`, "DELETE"),
    assignDriver: async (data) =>
      apiCall("/transport/driver-assign", "POST", data),

    // Student assignments
    assignStudent: async (data) =>
      apiCall("/transport/assign-student", "POST", data),
    withdrawAssignment: async (data) =>
      apiCall("/transport/withdraw-assignment", "POST", data),
    getAssignments: async (params) =>
      apiCall("/transport/assignments", "GET", null, params),
    getStudentsByRoute: async (routeId) =>
      apiCall(`/transport/students-by-route?route_id=${routeId}`, "GET"),

    // Payments
    recordPayment: async (data) =>
      apiCall("/transport/record-payment", "POST", data),
    updatePaymentStatus: async (id, status) =>
      apiCall("/transport/payment-status", "PUT", { id, status }),
    getPayments: async (params) =>
      apiCall("/transport/payments", "GET", null, params),
    getPaymentSummary: async (studentId) =>
      apiCall(`/transport/payment-summary?student_id=${studentId}`, "GET"),
    getRoutePaymentSummary: async (routeId) =>
      apiCall(`/transport/route-payment-summary?route_id=${routeId}`, "GET"),
    getAllArrearsCredits: async (params) =>
      apiCall("/transport/all-arrears-credits", "GET", null, params),

    // Status
    checkStatus: async (params) =>
      apiCall("/transport/check-status", "GET", null, params),
    getCurrentStatus: async (studentId) =>
      apiCall(`/transport/current-status?student_id=${studentId}`, "GET"),
    getFullStatus: async (studentId) =>
      apiCall(`/transport/full-status?student_id=${studentId}`, "GET"),

    // Reports & Summary
    getRouteManifest: async (routeId) =>
      apiCall(`/transport/route-manifest?route_id=${routeId}`, "GET"),
    getStudentSummary: async (studentId) =>
      apiCall(`/transport/student-summary?student_id=${studentId}`, "GET"),
    getRouteSummary: async (routeId) =>
      apiCall(`/transport/route-summary?route_id=${routeId}`, "GET"),

    // CRUD
    create: async (data) => apiCall("/transport", "POST", data),
    update: async (id, data) => apiCall(`/transport/${id}`, "PUT", data),
    delete: async (id) => apiCall(`/transport/${id}`, "DELETE"),

    // Advanced
    getRoutes: async (id = null) =>
      id
        ? apiCall(`/transport/routes-get/${id}`, "GET")
        : apiCall("/transport/routes-get", "GET"),
    assignRoute: async (data) =>
      apiCall("/transport/routes-assign", "POST", data),
    getVehicles: async (id = null) =>
      id
        ? apiCall(`/transport/vehicles-get/${id}`, "GET")
        : apiCall("/transport/vehicles-get", "GET"),
    assignVehicle: async (data) =>
      apiCall("/transport/vehicles-assign", "POST", data),
    getDrivers: async (id = null) =>
      id
        ? apiCall(`/transport/drivers-get/${id}`, "GET")
        : apiCall("/transport/drivers-get", "GET"),
    assignDriverToRoute: async (data) =>
      apiCall("/transport/drivers-assign", "POST", data),
  },

  // Schedules endpoints
  schedules: {
    index: async () => apiCall("/schedules/index", "GET"),
    get: async (id = null) =>
      id ? apiCall(`/schedules/${id}`, "GET") : apiCall("/schedules", "GET"),
    create: async (data) => apiCall("/schedules", "POST", data),
    update: async (id, data) => apiCall(`/schedules/${id}`, "PUT", data),
    delete: async (id) => apiCall(`/schedules/${id}`, "DELETE"),

    // Timetable
    getTimetable: async (id = null) =>
      id
        ? apiCall(`/schedules/timetable-get/${id}`, "GET")
        : apiCall("/schedules/timetable-get", "GET"),
    createTimetable: async (data) =>
      apiCall("/schedules/timetable-create", "POST", data),

    // Exams
    getExam: async (id = null) =>
      id
        ? apiCall(`/schedules/exam-get/${id}`, "GET")
        : apiCall("/schedules/exam-get", "GET"),
    createExam: async (data) => apiCall("/schedules/exam-create", "POST", data),

    // Events
    getEvents: async (id = null) =>
      id
        ? apiCall(`/schedules/events-get/${id}`, "GET")
        : apiCall("/schedules/events-get", "GET"),
    createEvent: async (data) =>
      apiCall("/schedules/events-create", "POST", data),

    // Activity schedules
    getActivity: async (id = null) =>
      id
        ? apiCall(`/schedules/activity-get/${id}`, "GET")
        : apiCall("/schedules/activity-get", "GET"),
    createActivity: async (data) =>
      apiCall("/schedules/activity-create", "POST", data),

    // Rooms
    getRooms: async (id = null) =>
      id
        ? apiCall(`/schedules/rooms-get/${id}`, "GET")
        : apiCall("/schedules/rooms-get", "GET"),
    createRoom: async (data) =>
      apiCall("/schedules/rooms-create", "POST", data),

    // Reports
    getReports: async (id = null) =>
      id
        ? apiCall(`/schedules/reports-get/${id}`, "GET")
        : apiCall("/schedules/reports-get", "GET"),
    createReport: async (data) =>
      apiCall("/schedules/reports-create", "POST", data),

    // Routes
    getRoute: async (id = null) =>
      id
        ? apiCall(`/schedules/route-get/${id}`, "GET")
        : apiCall("/schedules/route-get", "GET"),
    createRoute: async (data) =>
      apiCall("/schedules/route-create", "POST", data),

    // Specific schedules
    getTeacherSchedule: async (teacherId) =>
      apiCall(`/schedules/teacher-schedule?teacher_id=${teacherId}`, "GET"),
    getSubjectTeachingLoad: async (subjectId) =>
      apiCall(
        `/schedules/subject-teaching-load?subject_id=${subjectId}`,
        "GET"
      ),
    getAllActivitySchedules: async (params) =>
      apiCall("/schedules/all-activity-schedules", "GET", null, params),
    getDriverSchedule: async (driverId) =>
      apiCall(`/schedules/driver-schedule?driver_id=${driverId}`, "GET"),
    getStaffDutySchedule: async (staffId) =>
      apiCall(`/schedules/staff-duty-schedule?staff_id=${staffId}`, "GET"),
    getMasterSchedule: async (params) =>
      apiCall("/schedules/master-schedule", "GET", null, params),
    getAnalytics: async (params) =>
      apiCall("/schedules/analytics", "GET", null, params),
    getStudentSchedules: async (studentId) =>
      apiCall(`/schedules/student-schedules?student_id=${studentId}`, "GET"),
    getStaffSchedules: async (staffId) =>
      apiCall(`/schedules/staff-schedules?staff_id=${staffId}`, "GET"),
    getAdminTermOverview: async (termId) =>
      apiCall(`/schedules/admin-term-overview?term_id=${termId}`, "GET"),

    // Workflow
    defineTermDates: async (data) =>
      apiCall("/schedules/define-term-dates", "POST", data),
    reviewTermDates: async (termId) =>
      apiCall(`/schedules/review-term-dates?term_id=${termId}`, "GET"),
    checkResourceAvailability: async (params) =>
      apiCall("/schedules/check-resource-availability", "GET", null, params),
    findOptimalSchedule: async (params) =>
      apiCall("/schedules/find-optimal-schedule", "GET", null, params),
    detectConflicts: async (data) =>
      apiCall("/schedules/detect-schedule-conflicts", "POST", data),
    generateMasterSchedule: async (params) =>
      apiCall("/schedules/generate-master-schedule", "GET", null, params),
    validateCompliance: async (params) =>
      apiCall("/schedules/validate-schedule-compliance", "GET", null, params),
    startSchedulingWorkflow: async (data) =>
      apiCall("/schedules/start-scheduling-workflow", "POST", data),
    advanceWorkflow: async (data) =>
      apiCall("/schedules/advance-scheduling-workflow", "POST", data),
    getWorkflowStatus: async (workflowId) =>
      apiCall(
        `/schedules/scheduling-workflow-status?workflow_id=${workflowId}`,
        "GET"
      ),
    listWorkflows: async (params) =>
      apiCall("/schedules/list-scheduling-workflows", "GET", null, params),

    // Legacy support
    getClassSchedule: async (classId) =>
      apiCall(`/schedules/timetable-get?class_id=${classId}`, "GET"),
    updateSchedule: async (data) => apiCall("/schedules", "POST", data),
    addEvent: async (data) => apiCall("/schedules/events-create", "POST", data),
    updateEvent: async (id, data) => apiCall(`/schedules/${id}`, "PUT", data),
    deleteEvent: async (id) => apiCall(`/schedules/${id}`, "DELETE"),
    getHolidays: async () =>
      apiCall("/schedules/events-get?type=holiday", "GET"),
    setHoliday: async (data) =>
      apiCall("/schedules/events-create", "POST", { ...data, type: "holiday" }),
  },

  // Reports endpoints
  reports: {
    index: async () => apiCall("/reports/index", "GET"),

    // Admission reports
    getAdmissionStats: async (params) =>
      apiCall("/reports/admission-stats", "GET", null, params),
    getConversionRates: async (params) =>
      apiCall("/reports/conversion-rates", "GET", null, params),
    getAlumniStats: async (params) =>
      apiCall("/reports/alumni-stats", "GET", null, params),

    // Student reports
    getTotalStudents: async (params) =>
      apiCall("/reports/total-students", "GET", null, params),
    getEnrollmentTrends: async (params) =>
      apiCall("/reports/enrollment-trends", "GET", null, params),
    getAttendanceRates: async (params) =>
      apiCall("/reports/attendance-rates", "GET", null, params),
    getPromotionRates: async (params) =>
      apiCall("/reports/promotion-rates", "GET", null, params),
    getDropoutRates: async (params) =>
      apiCall("/reports/dropout-rates", "GET", null, params),
    getScoreDistributions: async (params) =>
      apiCall("/reports/score-distributions", "GET", null, params),
    getStudentProgressionRates: async (params) =>
      apiCall("/reports/student-progression-rates", "GET", null, params),
    getExamReports: async (params) =>
      apiCall("/reports/exam-reports", "GET", null, params),
    getAcademicYearReports: async (params) =>
      apiCall("/reports/academic-year-reports", "GET", null, params),

    // Staff reports
    getTotalStaff: async (params) =>
      apiCall("/reports/total-staff", "GET", null, params),
    getStaffAttendanceRates: async (params) =>
      apiCall("/reports/staff-attendance-rates", "GET", null, params),
    getActiveStaffCount: async (params) =>
      apiCall("/reports/active-staff-count", "GET", null, params),
    getStaffLoanStats: async (params) =>
      apiCall("/reports/staff-loan-stats", "GET", null, params),

    // Finance reports
    getPayrollSummary: async (params) =>
      apiCall("/reports/payroll-summary", "GET", null, params),
    getFeeSummary: async (params) =>
      apiCall("/reports/fee-summary", "GET", null, params),
    getFeePaymentTrends: async (params) =>
      apiCall("/reports/fee-payment-trends", "GET", null, params),
    getDiscountStats: async (params) =>
      apiCall("/reports/discount-stats", "GET", null, params),
    getArrearsStats: async (params) =>
      apiCall("/reports/arrears-stats", "GET", null, params),
    getFinancialTransactionsSummary: async (params) =>
      apiCall("/reports/financial-transactions-summary", "GET", null, params),
    getBankTransactionsSummary: async (params) =>
      apiCall("/reports/bank-transactions-summary", "GET", null, params),
    getFeeStructureChangeLog: async (params) =>
      apiCall("/reports/fee-structure-change-log", "GET", null, params),

    // Transport reports
    getTransportReport: async (params) =>
      apiCall("/reports/transport-report", "GET", null, params),

    // Inventory reports
    getInventoryStockLevels: async (params) =>
      apiCall("/reports/inventory-stock-levels", "GET", null, params),
    getInventoryUsageRates: async (params) =>
      apiCall("/reports/inventory-usage-rates", "GET", null, params),
    getRequisitionsSummary: async (params) =>
      apiCall("/reports/requisitions-summary", "GET", null, params),
    getAssetMaintenanceStats: async (params) =>
      apiCall("/reports/asset-maintenance-stats", "GET", null, params),
    getInventoryAdjustmentLogs: async (params) =>
      apiCall("/reports/inventory-adjustment-logs", "GET", null, params),

    // Meals reports
    getMealAllocations: async (params) =>
      apiCall("/reports/meal-allocations", "GET", null, params),
    getFoodConsumptionTrends: async (params) =>
      apiCall("/reports/food-consumption-trends", "GET", null, params),

    // Logs reports
    getCommunicationLogs: async (params) =>
      apiCall("/reports/communication-logs", "GET", null, params),
    getFeeStructureLogs: async (params) =>
      apiCall("/reports/fee-structure-logs", "GET", null, params),
    getInventoryLogs: async (params) =>
      apiCall("/reports/inventory-logs", "GET", null, params),
    getSystemLogs: async (params) =>
      apiCall("/reports/system-logs", "GET", null, params),
    getLoginActivity: async (params) =>
      apiCall("/reports/login-activity", "GET", null, params),
    getAccountUnlocks: async (params) =>
      apiCall("/reports/account-unlocks", "GET", null, params),
    getAuditTrailSummary: async (params) =>
      apiCall("/reports/audit-trail-summary", "GET", null, params),
    getBlockedDevicesStats: async (params) =>
      apiCall("/reports/blocked-devices-stats", "GET", null, params),

    // Workflow reports
    getWorkflowInstanceStats: async (params) =>
      apiCall("/reports/workflow-instance-stats", "GET", null, params),
    getWorkflowStageTimes: async (params) =>
      apiCall("/reports/workflow-stage-times", "GET", null, params),
    getWorkflowTransitionFrequencies: async (params) =>
      apiCall("/reports/workflow-transition-frequencies", "GET", null, params),

    // Conduct reports
    getConductCasesStats: async (params) =>
      apiCall("/reports/conduct-cases-stats", "GET", null, params),
    getDisciplinaryTrends: async (params) =>
      apiCall("/reports/disciplinary-trends", "GET", null, params),

    // Communications reports
    getCommunicationsStats: async (params) =>
      apiCall("/reports/communications-stats", "GET", null, params),
    getParentPortalStats: async (params) =>
      apiCall("/reports/parent-portal-stats", "GET", null, params),
    getForumActivityStats: async (params) =>
      apiCall("/reports/forum-activity-stats", "GET", null, params),
    getAnnouncementReach: async (params) =>
      apiCall("/reports/announcement-reach", "GET", null, params),

    // Legacy support
    list: async (params = {}) => apiCall("/reports", "GET", null, params),
    get: async (id) => apiCall(`/reports/${id}`, "GET"),
    generate: async (data) => apiCall("/reports", "POST", data),
    getAcademicReport: async (params = {}) =>
      apiCall("/reports/exam-reports", "GET", null, params),
    getSystemReports: async (params = {}) =>
      apiCall("/reports/system-logs", "GET", null, params),
    getAuditReports: async (params = {}) =>
      apiCall("/reports/audit-trail-summary", "GET", null, params),
    getDashboardStats: async (params = {}) => {
      try {
        const res = await apiCall("/reports", "GET", null, params);
        if (!res || !res.data) {
          return {
            students: {
              total: 0,
              growth: 0,
              by_class: [],
              by_gender: { male: 0, female: 0 },
              by_status: { active: 0, inactive: 0, suspended: 0 },
            },
            staff: {
              total: 0,
              teaching: 0,
              non_teaching: 0,
              growth: 0,
              present: 0,
              on_leave: 0,
              by_department: [],
              by_role: { teaching: 0, non_teaching: 0, admin: 0 },
            },
            attendance: {
              today: 0,
              total: 0,
              rate: 0,
              by_class: [],
              trend: [],
              by_status: { present: 0, absent: 0, late: 0 },
            },
            finance: {
              total: 0,
              paid: 0,
              unpaid: 0,
              growth: 0,
              by_type: [],
              by_status: [],
              trend: [],
            },
            activities: { total: 0, upcoming: [] },
            schedules: { total: 0, today: [] },
          };
        }
        return res.data;
      } catch (e) {
        return {
          students: {
            total: 0,
            growth: 0,
            by_class: [],
            by_gender: { male: 0, female: 0 },
            by_status: { active: 0, inactive: 0, suspended: 0 },
          },
          staff: {
            total: 0,
            teaching: 0,
            non_teaching: 0,
            growth: 0,
            present: 0,
            on_leave: 0,
            by_department: [],
            by_role: { teaching: 0, non_teaching: 0, admin: 0 },
          },
          attendance: {
            today: 0,
            total: 0,
            rate: 0,
            by_class: [],
            trend: [],
            by_status: { present: 0, absent: 0, late: 0 },
          },
          finance: {
            total: 0,
            paid: 0,
            unpaid: 0,
            growth: 0,
            by_type: [],
            by_status: [],
            trend: [],
          },
          activities: { total: 0, upcoming: [] },
          schedules: { total: 0, today: [] },
        };
      }
    },
    getCustomReport: async (params = {}) => apiCall("/reports", "POST", params),
  },

  // Payments endpoints
  payments: {
    index: async () => apiCall("/payments/index", "GET"),

    // M-Pesa callbacks
    mpesaB2CCallback: async (data) =>
      apiCall("/payments/mpesa-b2c-callback", "POST", data),
    mpesaB2CTimeout: async (data) =>
      apiCall("/payments/mpesa-b2c-timeout", "POST", data),
    mpesaC2BConfirmation: async (data) =>
      apiCall("/payments/mpesa-c2b-confirmation", "POST", data),

    // KCB callbacks
    kcbValidation: async (data) =>
      apiCall("/payments/kcb-validation", "POST", data),
    kcbTransferCallback: async (data) =>
      apiCall("/payments/kcb-transfer-callback", "POST", data),
    kcbNotification: async (data) =>
      apiCall("/payments/kcb-notification", "POST", data),

    // Bank webhook
    bankWebhook: async (data) =>
      apiCall("/payments/bank-webhook", "POST", data),
  },

  // System endpoints
  system: {
    index: async () => apiCall("/system/index", "GET"),

    // Media
    uploadMedia: async (formData) =>
      apiCall("/system/media-upload", "POST", formData, {}, { isFile: true }),
    createAlbum: async (data) => apiCall("/system/media-album", "POST", data),
    getAlbums: async (params) =>
      apiCall("/system/media-albums", "GET", null, params),
    getMedia: async (id = null) =>
      id
        ? apiCall(`/system/media?id=${id}`, "GET")
        : apiCall("/system/media", "GET"),
    updateMedia: async (data) => apiCall("/system/media-update", "POST", data),
    deleteMedia: async (mediaId) =>
      apiCall("/system/media-delete", "POST", { media_id: mediaId }),
    deleteAlbum: async (albumId) =>
      apiCall("/system/media-album-delete", "POST", { album_id: albumId }),
    getMediaPreview: async (mediaId) =>
      apiCall(`/system/media-preview?media_id=${mediaId}`, "GET"),
    canAccessMedia: async (mediaId) =>
      apiCall(`/system/media-can-access?media_id=${mediaId}`, "GET"),

    // Logs
    getLogs: async (params) => apiCall("/system/logs", "GET", null, params),
    clearLogs: async (data) => apiCall("/system/logs-clear", "POST", data),
    archiveLogs: async (data) => apiCall("/system/logs-archive", "POST", data),

    // School config
    getSchoolConfig: async (params) =>
      apiCall("/system/school-config", "GET", null, params),
    updateSchoolConfig: async (data) =>
      apiCall("/system/school-config", "POST", data),

    // Health
    getHealth: async () => apiCall("/system/health", "GET"),

    // Routes Management (System Admin)
    getRoutes: async (params) => apiCall("/system/routes", "GET", null, params),
    getRoute: async (id) => apiCall(`/system/routes?id=${id}`, "GET"),
    createRoute: async (data) => apiCall("/system/routes", "POST", data),
    updateRoute: async (id, data) =>
      apiCall("/system/routes", "PUT", { id, ...data }),
    deleteRoute: async (id) => apiCall("/system/routes", "DELETE", { id }),
    toggleRouteStatus: async (id, isActive) =>
      apiCall("/system/routes-toggle", "POST", { id, is_active: isActive }),

    // Roles Management (System Admin)
    getRoles: async (params) => apiCall("/system/roles", "GET", null, params),
    getRole: async (id) => apiCall(`/system/roles?id=${id}`, "GET"),
    createRole: async (data) => apiCall("/system/roles", "POST", data),
    updateRole: async (id, data) =>
      apiCall("/system/roles", "PUT", { id, ...data }),
    deleteRole: async (id) => apiCall("/system/roles", "DELETE", { id }),
    toggleRoleStatus: async (id, isActive) =>
      apiCall("/system/roles-toggle", "POST", { id, is_active: isActive }),

    // Sidebar Menu Management (System Admin)
    getSidebarMenus: async (params) =>
      apiCall("/system/sidebar-menus", "GET", null, params),
    createSidebarMenu: async (data) =>
      apiCall("/system/sidebar-menus", "POST", data),
    updateSidebarMenu: async (id, data) =>
      apiCall("/system/sidebar-menus", "PUT", { id, ...data }),
    deleteSidebarMenu: async (id) =>
      apiCall("/system/sidebar-menus", "DELETE", { id }),

    // Role-Sidebar Assignment (System Admin)
    getRoleSidebarAssignments: async (roleId) =>
      apiCall(`/system/role-sidebar-assignments?role_id=${roleId}`, "GET"),
    assignMenuToRole: async (roleId, menuItemId) =>
      apiCall("/system/role-sidebar-assignments", "POST", {
        role_id: roleId,
        menu_item_id: menuItemId,
      }),
    revokeMenuFromRole: async (roleId, menuItemId) =>
      apiCall("/system/role-sidebar-assignments", "DELETE", {
        role_id: roleId,
        menu_item_id: menuItemId,
      }),

    // Permissions Management (System Admin)
    getPermissions: async (params) =>
      apiCall("/system/permissions", "GET", null, params),
    createPermission: async (data) =>
      apiCall("/system/permissions", "POST", data),
    updatePermission: async (id, data) =>
      apiCall("/system/permissions", "PUT", { id, ...data }),
    deletePermission: async (id) =>
      apiCall("/system/permissions", "DELETE", { id }),

    // Role-Permission Assignment (System Admin)
    getRolePermissions: async (roleId) =>
      apiCall(`/system/role-permissions?role_id=${roleId}`, "GET"),
    assignPermissionToRole: async (roleId, permissionId) =>
      apiCall("/system/role-permissions", "POST", {
        role_id: roleId,
        permission_id: permissionId,
      }),
    revokePermissionFromRole: async (roleId, permissionId) =>
      apiCall("/system/role-permissions", "DELETE", {
        role_id: roleId,
        permission_id: permissionId,
      }),

    // Dashboards Management (System Admin)
    getDashboards: async (params) =>
      apiCall("/system/dashboards", "GET", null, params),
    createDashboard: async (data) =>
      apiCall("/system/dashboards", "POST", data),
    updateDashboard: async (id, data) =>
      apiCall("/system/dashboards", "PUT", { id, ...data }),
    deleteDashboard: async (id) =>
      apiCall("/system/dashboards", "DELETE", { id }),

    // Widgets Management (System Admin)
    getWidgets: async (params) =>
      apiCall("/system/widgets", "GET", null, params),
    createWidget: async (data) => apiCall("/system/widgets", "POST", data),
    updateWidget: async (id, data) =>
      apiCall("/system/widgets", "PUT", { id, ...data }),
    deleteWidget: async (id) => apiCall("/system/widgets", "DELETE", { id }),

    // Policies Management (System Admin)
    getPolicies: async (params) =>
      apiCall("/system/policies", "GET", null, params),
    createPolicy: async (data) => apiCall("/system/policies", "POST", data),
    updatePolicy: async (id, data) =>
      apiCall("/system/policies", "PUT", { id, ...data }),
    deletePolicy: async (id) => apiCall("/system/policies", "DELETE", { id }),
  },

  // School Config endpoints (match SchoolConfigController)
  schoolconfig: {
    index: async () => apiCall("/school-config/index", "GET"),

    get: async (id = null) =>
      id
        ? apiCall(`/school-config/${id}`, "GET")
        : apiCall("/school-config", "GET"),
    create: async (data) => apiCall("/school-config", "POST", data),
    update: async (id, data) => apiCall(`/school-config/${id}`, "PUT", data),
    delete: async (id) => apiCall(`/school-config/${id}`, "DELETE"),

    getLogs: async (params) =>
      apiCall("/school-config/logs", "GET", null, params),
    clearLogs: async (data) =>
      apiCall("/school-config/logs-clear", "POST", data),
    archiveLogs: async (data) =>
      apiCall("/school-config/logs-archive", "POST", data),

    getHealth: async () => apiCall("/school-config/health", "GET"),
  },

  // Maintenance endpoints (exactly as implemented in MaintenanceController)
  maintenance: {
    index: async () => apiCall("/maintenance/index", "GET"),

    getLogs: async (params) =>
      apiCall("/maintenance/logs", "GET", null, params),
    clearLogs: async (data) => apiCall("/maintenance/logs-clear", "POST", data),
    archiveLogs: async (data) =>
      apiCall("/maintenance/logs-archive", "POST", data),

    getConfig: async (params) =>
      apiCall("/maintenance/config", "GET", null, params),
    updateConfig: async (data) => apiCall("/maintenance/config", "POST", data),
  },

  // Legacy endpoints (for backward compatibility)
  admissions: {
    list: async (params = {}) =>
      apiCall("/students/admission-workflow-status", "GET", null, params),
    get: async (id) =>
      apiCall(`/students/admission-workflow-status?student_id=${id}`, "GET"),
    create: async (data, files = {}) =>
      apiCall("/students/admission-start-workflow", "POST", data),
    approve: async (id, data) =>
      apiCall("/students/admission-approve", "POST", {
        student_id: id,
        ...data,
      }),
    getStats: async () => apiCall("/reports/admission-stats", "GET"),
  },

  sms: {
    send: async (data) =>
      apiCall("/communications/communication", "POST", data),
    sendBulk: async (data) =>
      apiCall("/communications/announcement", "POST", data),
    getHistory: async (params = {}) =>
      apiCall("/communications/log", "GET", null, params),
    getTemplates: async () => apiCall("/communications/template", "GET"),
    saveTemplate: async (data) =>
      apiCall("/communications/template", "POST", data),
  },

  studentQR: {
    generate: async (studentId) =>
      apiCall("/students/qr-code-generate", "POST", { student_id: studentId }),
    scan: async (qrData) =>
      apiCall("/students/qr-info-get", "GET", null, { qr_data: qrData }),
    verify: async (qrToken) =>
      apiCall("/students/qr-info-get", "GET", null, { token: qrToken }),
  },

  resetPassword: {
    request: async (email) =>
      apiCall("/auth/forgot-password", "POST", { email }),
    verify: async (token) =>
      apiCall("/auth/reset-password", "GET", null, { token }),
    complete: async (token, newPassword) =>
      apiCall("/auth/reset-password", "POST", {
        token,
        new_password: newPassword,
      }),
  },

  // Dashboard Statistics endpoints
  dashboard: {
    // ⚠️ SECURITY: System Admin Dashboard - Infrastructure & Technical Metrics ONLY
    // NO business data (students, finance, operations)

    // System-focused endpoints
    getAuthEvents: async () => {
      return await apiCall("/system/auth-events", "GET");
    },

    getActiveSessions: async () => {
      return await apiCall("/system/active-sessions", "GET");
    },

    getSystemUptime: async () => {
      return await apiCall("/system/uptime", "GET");
    },

    getSystemHealthErrors: async () => {
      return await apiCall("/system/health-errors", "GET");
    },

    getSystemHealthWarnings: async () => {
      return await apiCall("/system/health-warnings", "GET");
    },

    getAPIRequestLoad: async () => {
      return await apiCall("/system/api-load", "GET");
    },

    // Director-level endpoints (business operational data)
    getStudentStats: async () => {
      return await apiCall("/students/stats", "GET");
    },

    getTodayAttendance: async () => {
      return await apiCall("/attendance/today", "GET");
    },

    getTeachingStats: async () => {
      return await apiCall("/staff/stats", "GET");
    },

    getFeesCollected: async () => {
      return await apiCall("/payments/stats", "GET");
    },

    getWeeklyLessons: async () => {
      return await apiCall("/schedules/weekly", "GET");
    },

    // Additional endpoints for other role dashboards
    getCollectionTrends: async () => {
      return await apiCall("/payments/collection-trends", "GET");
    },

    getPendingApprovals: async () => {
      return await apiCall("/system/pending-approvals", "GET");
    },

    getDirectorPayrollSummary: async () => {
      return await apiCall("/dashboard/director/payroll-summary", "GET");
    },

    getDirectorSystemStatus: async () => {
      return await apiCall("/dashboard/director/system-status", "GET");
    },

    getDirectorAnnouncements: async () => {
      return await apiCall("/dashboard/director/announcements", "GET");
    },

    getActivities: async () => {
      return await apiCall("/activities/list", "GET");
    },

    getPendingAdmissions: async () => {
      return await apiCall("/admissions/pending", "GET");
    },

    getMyClassAttendance: async () => {
      return await apiCall("/attendance/my-class", "GET");
    },

    getMyClassAssessments: async () => {
      return await apiCall("/assessments/my-results", "GET");
    },

    getMyLessonPlan: async () => {
      return await apiCall("/schedules/my-lessons", "GET");
    },

    getFeeStatusByStudent: async () => {
      return await apiCall("/payments/fee-status", "GET");
    },

    getMonthlyFinancialReport: async () => {
      return await apiCall("/finance/monthly-report", "GET");
    },

    getPayrollStatus: async () => {
      return await apiCall("/finance/payroll-status", "GET");
    },

    getInventoryStockStatus: async () => {
      return await apiCall("/inventory/stock-status", "GET");
    },

    getLowStockAlerts: async () => {
      return await apiCall("/inventory/low-stock-alerts", "GET");
    },

    getPendingRequisitions: async () => {
      return await apiCall("/inventory/requisitions-pending", "GET");
    },
  },

  // Dashboard endpoints
  dashboard: {
    getDirectorSummary: async () => {
      return await apiCall("/dashboard/director/summary", "GET");
    },
    getPaymentsTrends: async () => {
      return await apiCall("/payments/trends", "GET");
    },
    getPaymentsRevenueSources: async () => {
      return await apiCall("/payments/revenue-sources", "GET");
    },
    getAcademicsKpis: async () => {
      return await apiCall("/academics/kpis", "GET");
    },
    getAcademicsPerformanceMatrix: async () => {
      return await apiCall("/academics/performance-matrix", "GET");
    },
    getAttendanceTrends: async () => {
      return await apiCall("/attendance/trends", "GET");
    },
    getDirectorRisks: async () => {
      return await apiCall("/dashboard/director/risks", "GET");
    },
    getDirectorAnnouncements: async () => {
      return await apiCall("/dashboard/director/announcements", "GET");
    },
    getDirectorPayrollSummary: async () => {
      return await apiCall("/dashboard/director/payroll-summary", "GET");
    },
    getDirectorSystemStatus: async () => {
      return await apiCall("/dashboard/director/system-status", "GET");
    },
  },
};
