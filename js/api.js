// Only define API_BASE_URL if not already defined
if (typeof API_BASE_URL === "undefined") {
  var API_BASE_URL = (window.APP_BASE || "") + "/api";
}

// Token refresh tracking to prevent duplicate refresh requests
if (typeof isRefreshingToken === "undefined") {
  var isRefreshingToken = false;
}
if (typeof refreshTokenPromise === "undefined") {
  var refreshTokenPromise = null;
}

const SESSION_ACTIVITY_KEY = "kingsway_last_user_activity_at";
const TOKEN_REFRESH_LOCK_KEY = "kingsway_token_refresh_lock";
const TOKEN_REFRESH_EVENT_KEY = "kingsway_token_refresh_event";
const TOKEN_REFRESH_LOCK_TTL_MS = 20000;
const TOKEN_REFRESH_WAIT_MS = 18000;
const ACTIVE_SESSION_WINDOW_MS = 55 * 60 * 1000;
const TOKEN_REFRESH_SKEW_SECONDS = 5 * 60;
const CURRENT_TAB_ID =
  Date.now().toString(36) + "-" + Math.random().toString(36).slice(2);

// Notification types
const NOTIFICATION_TYPES = {
  SUCCESS: "success",
  ERROR: "error",
  WARNING: "warning",
  INFO: "info",
};

// Icons for different notification types
const NOTIFICATION_ICONS = {
  success: "bi-check-circle",
  error: "bi-x-circle",
  warning: "bi-exclamation-triangle",
  info: "bi-info-circle",
};

// Show notification as a non-blocking toast
function showNotification(message, type = NOTIFICATION_TYPES.INFO) {
  const legacyModal = document.getElementById("notificationModal");
  if (legacyModal && typeof bootstrap !== "undefined") {
    const existingModal = bootstrap.Modal.getInstance(legacyModal);
    if (existingModal) {
      existingModal.hide();
    }
    legacyModal.classList.remove("show");
    legacyModal.style.display = "none";
    document.body.classList.remove("modal-open");
    document.querySelectorAll(".modal-backdrop").forEach(function (backdrop) {
      backdrop.remove();
    });
  }

  let container = document.getElementById("appToastContainer");
  if (!container) {
    container = document.createElement("div");
    container.id = "appToastContainer";
    container.style.position = "fixed";
    container.style.top = "86px";
    container.style.right = "24px";
    container.style.zIndex = "1085";
    container.style.display = "flex";
    container.style.flexDirection = "column";
    container.style.gap = "10px";
    container.style.maxWidth = "420px";
    document.body.appendChild(container);
  }

  const palette = {
    success: { bg: "#ffffff", border: "#22c55e", icon: "#15803d" },
    error: { bg: "#ffffff", border: "#ef4444", icon: "#b91c1c" },
    warning: { bg: "#ffffff", border: "#f59e0b", icon: "#b45309" },
    info: { bg: "#ffffff", border: "#3b82f6", icon: "#1d4ed8" },
  };
  const colors = palette[type] || palette.info;

  const toast = document.createElement("div");
  toast.setAttribute("role", "status");
  toast.style.display = "flex";
  toast.style.alignItems = "center";
  toast.style.gap = "12px";
  toast.style.padding = "14px 18px";
  toast.style.borderRadius = "14px";
  toast.style.background = colors.bg;
  toast.style.borderLeft = "5px solid " + colors.border;
  toast.style.boxShadow = "0 14px 36px rgba(15, 23, 42, 0.18)";
  toast.style.color = "#111827";
  toast.style.fontWeight = "500";
  toast.style.transform = "translateX(20px)";
  toast.style.opacity = "0";
  toast.style.transition = "opacity 180ms ease, transform 180ms ease";

  const icon = document.createElement("i");
  icon.className = `bi ${NOTIFICATION_ICONS[type] || NOTIFICATION_ICONS.info}`;
  icon.style.color = colors.icon;
  icon.style.fontSize = "1.1rem";
  icon.style.flexShrink = "0";

  const text = document.createElement("span");
  text.textContent = message;

  const close = document.createElement("button");
  close.type = "button";
  close.setAttribute("aria-label", "Dismiss notification");
  close.textContent = "×";
  close.style.marginLeft = "auto";
  close.style.border = "0";
  close.style.background = "transparent";
  close.style.fontSize = "1.2rem";
  close.style.lineHeight = "1";
  close.style.color = "#6b7280";
  close.style.cursor = "pointer";

  const removeToast = function () {
    toast.style.opacity = "0";
    toast.style.transform = "translateX(20px)";
    setTimeout(function () {
      if (toast.parentNode) {
        toast.parentNode.removeChild(toast);
      }
    }, 200);
  };

  close.addEventListener("click", removeToast);
  toast.appendChild(icon);
  toast.appendChild(text);
  toast.appendChild(close);
  container.appendChild(toast);

  requestAnimationFrame(function () {
    toast.style.opacity = "1";
    toast.style.transform = "translateX(0)";
  });

  setTimeout(removeToast, type === NOTIFICATION_TYPES.ERROR ? 6000 : 3200);
}

// Handle API Response
function handleApiResponse(response, showSuccess = false) {
  const normalized = AppState.normalizeResponse(response);
  if (normalized.success) {
    // Disabled automatic success notifications - let components handle their own
    // if (showSuccess && response.message) {
    //     showNotification(response.message, NOTIFICATION_TYPES.SUCCESS);
    // }
    // For sidebar endpoint, return the entire response
    if (normalized.data?.sidebar !== undefined) {
      return response;
    }
    // For other endpoints, return just the data
    return response.data !== undefined ? response.data : response;
  } else {
    const error = new Error(normalized.message || "API call failed");
    error.response = response;
    error.code = normalized.code;
    error.errors = normalized.errors;
    error.state = AppState.classify(normalized);
    throw error;
  }
}

// Handle API Error
function handleApiError(error) {
  console.error("API Error:", error);
  // Don't show notification here - let caller decide if they want to notify user
  throw error;
}

// Download file helper
async function downloadFile(blob, filename) {
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  window.URL.revokeObjectURL(url);
  document.body.removeChild(a);
}

// Safely read and parse JSON responses with clearer errors
async function readJsonSafely(response, context = "API") {
  const contentType = response.headers.get("content-type") || "";
  const text = await response.text();

  if (!contentType.includes("application/json")) {
    throw new Error(
      `${context} did not return JSON (${response.status}). Content-Type: ${contentType || "unknown"}. ` +
        `Response: ${text.substring(0, 200)}`,
    );
  }

  try {
    return JSON.parse(text);
  } catch (error) {
    throw new Error(
      `${context} returned invalid JSON (${response.status}). ` +
        `Response: ${text.substring(0, 200)}`,
    );
  }
}

function fetchWithBrowserFallback(requestUrl, fetchOptions) {
  return fetch(requestUrl, fetchOptions).catch((fetchError) => {
    const isNetworkFailure = /Failed to fetch|NetworkError|network/i.test(
      fetchError?.message || "",
    );
    if (!isNetworkFailure || typeof XMLHttpRequest === "undefined") {
      throw fetchError;
    }

    console.warn("[api.js] fetch failed; retrying with XMLHttpRequest", {
      url: requestUrl,
      method: fetchOptions.method || "GET",
      error: fetchError.message,
    });

    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open(fetchOptions.method || "GET", requestUrl, true);
      xhr.withCredentials = fetchOptions.credentials !== "omit";

      Object.entries(fetchOptions.headers || {}).forEach(([key, value]) => {
        if (value !== undefined && value !== null) {
          xhr.setRequestHeader(key, String(value));
        }
      });

      xhr.onload = () => {
        const rawHeaders = xhr.getAllResponseHeaders();
        const headers = {
          get(name) {
            const target = String(name || "").toLowerCase();
            const line = rawHeaders
              .split(/\r?\n/)
              .find((entry) => entry.toLowerCase().startsWith(target + ":"));
            return line ? line.slice(line.indexOf(":") + 1).trim() : null;
          },
        };
        resolve({
          ok: xhr.status >= 200 && xhr.status < 300,
          status: xhr.status,
          headers,
          text: async () => xhr.responseText,
          blob: async () => new Blob([xhr.response], {
            type: headers.get("content-type") || "application/octet-stream",
          }),
        });
      };

      xhr.onerror = () => reject(fetchError);
      xhr.ontimeout = () => reject(fetchError);
      xhr.send(fetchOptions.body || null);
    });
  });
}

// ============================================================================
// AUTHENTICATION & AUTHORIZATION SYSTEM
// ============================================================================

/**
 * User context manager - handles authentication state, permissions, and access control
 * Stores user info, token, roles, and permissions in localStorage (single global store) so every page, tab and window shares the same auth state (username, user_id, role, tokens, permissions, sidebar, dashboard).
 * Provides permission checking before API calls
 */
const AuthContext = (() => {
  const AUTH_KEYS = [
    "token",
    "refresh_token",
    "user_data",
    "user_permissions",
    "user_roles",
    "sidebar_items",
    "dashboard_info",
  ];

  let currentUser = null;
  let permissions = new Set();
  let roles = [];

  // SINGLE SOURCE OF TRUTH for all auth state. Every key in AUTH_KEYS
  // (token, refresh_token, user_data, user_permissions, user_roles,
  // sidebar_items, dashboard_info) is read from and written to ONE store:
  // localStorage. This guarantees that username, user_id, role, tokens and
  // permissions are available GLOBALLY across every page, tab and window of the
  // same browser — any new window opens with the full login response already
  // present, so a user never has to "log in twice". sessionStorage is no longer
  // used as a target; we only clear any stale sessionStorage keys left over from
  // older builds. The HttpOnly refresh_token cookie remains the server-side
  // anchor for device-level session lifetime.
  let activeStorage = localStorage;

  function getStorageName(storage) {
    return storage === localStorage ? "local" : "session";
  }

  function getStorageByName(name) {
    // There is only one canonical store now. Keeping this indirection for any
    // external callers, but it always resolves to localStorage.
    return name === "local" ? localStorage : localStorage;
  }

  // Detects whether an existing session is present. With a single store this is
  // trivial, but it still short-circuits when a token is already in localStorage
  // so callers (initialize) know there is nothing to restore from the cookie.
  function detectAuthStorage() {
    // Single store: nothing to switch. Clear any legacy sessionStorage leftovers
    // so they can't shadow or confuse future logic.
    removeAuthKeys(sessionStorage);
    activeStorage = localStorage;
    return activeStorage;
  }

  function removeAuthKeys(storage) {
    AUTH_KEYS.forEach((key) => storage.removeItem(key));
  }

  function setPersistence(rememberMe = false) {
    // Always persist auth state in localStorage (the single source of truth).
    // 'rememberMe' is recorded for telemetry/UX only; the storage target is
    // fixed so the full login response is always globally available. Session
    // lifetime is governed by the server-side HttpOnly refresh cookie, not by
    // client storage choice.
    activeStorage = localStorage;
    localStorage.setItem("auth_storage_mode", "local");
    removeAuthKeys(sessionStorage);
  }

  function getItem(key) {
    // Use the same activeStorage as setItem - don't re-detect
    return activeStorage.getItem(key);
  }

  function setItem(key, value) {
    activeStorage.setItem(key, value);
  }

  function setTokens(token, refreshToken = null) {
    if (token) {
      setItem("token", token);
    }
    if (refreshToken) {
      setItem("refresh_token", refreshToken);
    }
  }

  function getToken() {
    return getItem("token");
  }

  function getRefreshToken() {
    return getItem("refresh_token");
  }

  function getPersistenceMode() {
    detectAuthStorage();
    return getStorageName(activeStorage);
  }

  /**
   * Initialize user context from configured auth storage (on page load)
   */
  /**
   * Silent boot refresh.
   *
   * Web-storage (where this access token lives) can be empty on a fresh window,
   * incognito tab, after "clear cache", or in a second tab — but the server's
   * HttpOnly `refresh_token` cookie survives all of those. So when web-storage
   * has no token, we attempt one silent refresh via that cookie BEFORE deciding
   * the user is logged out. This is the single point that reconciles the two
   * storage worlds and stops the "logged out on first load in a new window" bug.
   *
   * Returns a Promise<boolean> (resolved, not thrown) so callers can await it.
   */
  // One-shot guard: the boot refresh may be triggered both by the module-load
  // initialize() call and by home.php awaiting initialize() — run it only once.
  let _bootRefreshDone = false;
  let _bootRefreshResult = null;

  async function bootstrapFromRefreshCookie() {
    if (_bootRefreshDone) return _bootRefreshResult;
    _bootRefreshDone = true;
    try {
      const refreshToken = getRefreshToken();
      const url = new URL(
        API_BASE_URL + "/auth/refresh-token",
        window.location.origin,
      );
      const response = await fetch(url, {
        method: "POST",
        credentials: "include", // carries the HttpOnly refresh_token cookie
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(
          refreshToken ? { refresh_token: refreshToken } : {},
        ),
      });
      if (!response.ok) return false;
      const result = await readJsonSafely(response, "Token refresh");
      if (
        result &&
        (result.status === "success" || result.success === true) &&
        result.data &&
        result.data.token
      ) {
        // Rehydrate auth state from the refreshed response. The response has the
        // full user/permissions envelope only on the auth/refresh-token endpoint.
        const full = result.data;
        setTokens(full.token, full.refresh_token || null);
        if (full.user) {
          // Rehydrate into the single auth store (localStorage). The refreshed
          // access token and full user envelope land in the same global store
          // every window reads from.
          setUser(full.user, full, true);
        }
        console.log(
          "[AuthContext] Session restored from refresh cookie on boot",
        );
        _bootRefreshResult = true;
        return true;
      }
    } catch (e) {
      console.warn("[AuthContext] Boot refresh failed:", e);
    }
    _bootRefreshResult = false;
    return false;
  }

  // SINGLETON BOOT PROMISE.
  // The old bug: initialize() was async but callers never awaited it in a way that
  // gated their own logic, so the dashboard's synchronous auth check ran FIRST
  // (saw no token) and bounced the user, while the refresh-cookie restore resolved
  // LAST, seconds too late. Wrapping the boot in one shared promise means every
  // caller (home.php, every dashboard gate, SessionManager) awaits the SAME
  // in-flight resolution — so authentication always completes BEFORE any redirect
  // or data load, regardless of DOMContentLoaded listener registration order.
  let _bootPromise = null;

  function doInitialize() {
    // Set activeStorage once based on what actually has data
    detectAuthStorage();
    let token = activeStorage.getItem("token");
    const userData = activeStorage.getItem("user_data");
    const permissionsData = activeStorage.getItem("user_permissions");

    if (token && userData) {
      try {
        currentUser = JSON.parse(userData);
        if (permissionsData) {
          permissions = new Set(JSON.parse(permissionsData));
          roles = JSON.parse(activeStorage.getItem("user_roles") || "[]");
        }
      } catch (e) {
        console.warn("Failed to restore user context from auth storage:", e);
        currentUser = null;
        permissions.clear();
        roles = [];
        removeAuthKeys(activeStorage);
      }
    }

    // Single source of truth: if web-storage had no token, fall back to the
    // server-side refresh cookie exactly once. Resolve rather than throw so the
    // caller can decide whether to redirect.
    if (!token) {
      return bootstrapFromRefreshCookie().catch((e) => {
        console.warn("AuthContext initialize refresh skipped:", e);
      });
    }
  }

  async function initialize() {
    if (!_bootPromise) {
      _bootPromise = Promise.resolve(doInitialize());
    }
    return _bootPromise;
  }

  // Await-able alias: "finish authentication, THEN let me proceed." This is the
  // primitive every page gate should await so auth is settled before the gate.
  function ready() {
    return initialize();
  }

  /**
   * Store user context after login
   * Deduplicates permissions and extracts unique permission codes
   * Also stores sidebar menu items
   */
  function setUser(userData, fullResponse, rememberMe = false) {
    setPersistence(rememberMe);
    currentUser = userData;

    console.log("setUser called with:", { userData, fullResponse });

    // Extract and deduplicate permissions
    // Permissions can be in fullResponse.permissions OR userData.permissions
    const permissionsArray =
      fullResponse?.permissions || userData?.permissions || [];

    console.log("Permissions array:", permissionsArray);

    // Use StorageManager for user preferences if available
    if (
      typeof StorageManager !== "undefined" &&
      typeof StorageManager.setPreference === "function"
    ) {
      try {
        StorageManager.setPreference("user_theme", userData.theme || "light");
        StorageManager.setPreference("sidebar_collapsed", false);
      } catch (e) {
        console.warn("StorageManager.setPreference failed:", e);
      }
    } else {
      // Fallback to localStorage directly
      try {
        localStorage.setItem("user_theme", userData.theme || "light");
        localStorage.setItem("sidebar_collapsed", JSON.stringify(false));
      } catch (e) {
        console.warn("localStorage fallback failed:", e);
      }
    }

    if (Array.isArray(permissionsArray) && permissionsArray.length > 0) {
      // Create Set of unique permission codes (automatically deduplicates)
      const uniquePermissions = new Set(
        permissionsArray.map((p) => p.permission_code || p),
      );
      permissions = uniquePermissions;

      console.log("Unique permissions extracted:", permissions.size);

      // Store in current auth storage
      setItem("user_permissions", JSON.stringify(Array.from(permissions)));
    } else {
      console.warn("No permissions found in response");
    }

    // Extract roles and role IDs
    const rolesArray = fullResponse?.roles || userData?.roles || [];
    if (Array.isArray(rolesArray) && rolesArray.length > 0) {
      roles = rolesArray.map((r) => r.name || r);
      setItem("user_roles", JSON.stringify(roles));
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
      setItem("sidebar_items", JSON.stringify(fullResponse.sidebar_items));
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
      // Normalize dashboard key to ensure it's a route name
      const dashboardInfo = { ...fullResponse.dashboard };
      if (dashboardInfo.key) {
        // Extract route from URLs like "home.php?route=dashboard" or "?route=dashboard"
        const routeMatch = dashboardInfo.key.match(/[?&]route=([^&]*)/);
        if (routeMatch) {
          dashboardInfo.key = decodeURIComponent(routeMatch[1]);
        }
      }
      setItem("dashboard_info", JSON.stringify(dashboardInfo));
      console.log("Dashboard info stored:", dashboardInfo);
    } else {
      console.warn("No dashboard info found in response");
    }

    // Store user data (now includes role_ids)
    setItem("user_data", JSON.stringify(userData));
    console.log("User data stored", userData);
  }

  /**
   * Clear user context on logout
   */
  function clearUser() {
    currentUser = null;
    permissions.clear();
    roles = [];
    removeAuthKeys(localStorage);
    removeAuthKeys(sessionStorage);
    localStorage.removeItem("auth_storage_mode");
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

    return PermissionContract.expandPermissionAliases(permissionCode).some(
      (alias) => permissions.has(alias),
    );
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

    return permissionCodes.some((code) => hasPermission(code));
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

    return permissionCodes.every((code) => hasPermission(code));
  }

  function normalizeRoleName(roleName) {
    return String(roleName || "")
      .trim()
      .toLowerCase()
      .replace(/[\s-]+/g, "_");
  }

  /**
   * Check if user has a specific role
   * @param {string} roleName
   * @returns {boolean}
   */
  function hasRole(roleName) {
    if (!currentUser) return false;
    const normalizedRoleName = normalizeRoleName(roleName);
    return roles.some(
      (role) =>
        role === roleName || normalizeRoleName(role) === normalizedRoleName,
    );
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
   * Get sidebar menu items from current auth storage
   */
  function getSidebarItems() {
    try {
      const items = getItem("sidebar_items");
      return items ? JSON.parse(items) : [];
    } catch (e) {
      console.warn("Failed to parse sidebar items:", e);
      return [];
    }
  }

  /**
   * Get dashboard info from current auth storage
   */
  function getDashboardInfo() {
    try {
      const info = getItem("dashboard_info");
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
    return !!currentUser && !!getToken();
  }

  /**
   * Alias for getUser() — used by dashboard_router.js
   */
  function getCurrentUser() {
    return currentUser;
  }

  /**
   * Module-scoped permission helpers.
   * Checks both dot notation (module.action) and underscore aliases (module_action).
   * @param {string} module — e.g., 'finance', 'students'
   */
  function canView(module) {
    return hasPermission(`${module}.view`) || hasPermission(`${module}_view`);
  }
  function canCreate(module) {
    return (
      hasPermission(`${module}.create`) || hasPermission(`${module}_create`)
    );
  }
  function canEdit(module) {
    return (
      hasPermission(`${module}.edit`) ||
      hasPermission(`${module}_edit`) ||
      hasPermission(`${module}.update`) ||
      hasPermission(`${module}_update`)
    );
  }
  function canDelete(module) {
    return (
      hasPermission(`${module}.delete`) || hasPermission(`${module}_delete`)
    );
  }
  function canApprove(module) {
    return (
      hasPermission(`${module}.approve`) || hasPermission(`${module}_approve`)
    );
  }
  function canExport(module) {
    return (
      hasPermission(`${module}.export`) || hasPermission(`${module}_export`)
    );
  }
  function canReject(module) {
    return (
      hasPermission(`${module}.reject`) || hasPermission(`${module}_reject`)
    );
  }
  function canPrint(module) {
    return hasPermission(`${module}.print`) || hasPermission(`${module}_print`);
  }
  function canManage(module) {
    return (
      hasPermission(`${module}.manage`) || hasPermission(`${module}_manage`)
    );
  }
  function canAction(module, action) {
    return PermissionContract.aliasesFor(module, action).some((permission) =>
      hasPermission(permission),
    );
  }
  
  // Staff-specific permission helpers
  function canManageStaff() {
    return hasPermission('staff.manage') || hasPermission('staff_manage');
  }
  
  function getAllowedActions(module) {
    return PermissionContract.actions.reduce((allowed, action) => {
      allowed[action] = canAction(module, action);
      return allowed;
    }, {});
  }

  // Initialize on load
  initialize();

  // Return public API
  return {
    setUser,
    setPersistence,
    setTokens,
    getToken,
    getRefreshToken,
    getPersistenceMode,
    clearUser,
    initialize,
    // Await-able "auth settled" primitive — every page gate should await this
    // (directly or via AuthContext.whenReady()) so authentication completes
    // BEFORE the gate decides to redirect or load data.
    ready,
    // Canonical token re-issuer. SessionManager delegates here so the access
    // JWT has a SINGLE owner instead of two systems forking their own refresh
    // flows. Re-issues the access token via /api/auth/refresh-token and writes
    // it into the shared localStorage source.
    refreshToken: refreshAccessToken,
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,
    hasRole,
    getUser,
    getCurrentUser,
    getPermissions,
    getRoles,
    getSidebarItems,
    getDashboardInfo,
    getPermissionCount,
    isAuthenticated,
    initialize,
    canView,
    canCreate,
    canEdit,
    canDelete,
    canApprove,
    canReject,
    canExport,
    canPrint,
    canManage,
    canAction,
    getAllowedActions,
    canManageStaff,
  };
})();

window.AuthContext = AuthContext;
startSessionActivityTracking();

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
  const segments = clean.split("/").filter(Boolean);
  if (segments.length === 0) return null;
  // Preserve the module + sub-resource (e.g. "academic/classes-list", "academic/years",
  // "staff/departments") so distinct routes get distinct cache keys and mutation-time
  // invalidation doesn't collide. /api/academic/classes and /api/academic/classes-list are
  // different resources and must not share a key.
  return segments.slice(0, 2).join("/");
}

const MUTATION_METHODS = new Set(["POST", "PUT", "PATCH", "DELETE"]);
const PERMISSION_ACTIONS = [
  "view",
  "create",
  "edit",
  "approve",
  "reject",
  "delete",
  "export",
  "print",
];

const PermissionContract = (() => {
  function normalizeModule(module) {
    return String(module || "")
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "_")
      .replace(/^_+|_+$/g, "");
  }

  function normalizeAction(action) {
    const normalized = String(action || "")
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "_")
      .replace(/^_+|_+$/g, "");
    return normalized === "update" ? "edit" : normalized;
  }

  function aliasesFor(module, action) {
    const mod = normalizeModule(module);
    const act = normalizeAction(action);
    const aliases = [`${mod}_${act}`, `${mod}.${act}`];

    if (act === "edit") {
      aliases.push(`${mod}_update`, `${mod}.update`);
    } else if (act === "approve") {
      aliases.push(`${mod}_approve_final`, `${mod}.approve_final`);
    } else if (act === "view") {
      aliases.push(
        `${mod}_view_all`,
        `${mod}_view_own`,
        `${mod}.view_all`,
        `${mod}.view_own`,
      );
    }

    return [...new Set(aliases)];
  }

  function expandPermissionAliases(permission) {
    const value = String(permission || "").trim();
    if (!value) return [];

    const aliases = new Set([value]);
    if (value.includes("_")) aliases.add(value.replace(/_/g, "."));
    if (value.includes(".")) aliases.add(value.replace(/\./g, "_"));
    if (value.endsWith("_edit"))
      aliases.add(value.replace(/_edit$/, "_update"));
    if (value.endsWith("_update"))
      aliases.add(value.replace(/_update$/, "_edit"));
    if (value.endsWith(".edit"))
      aliases.add(value.replace(/\.edit$/, ".update"));
    if (value.endsWith(".update"))
      aliases.add(value.replace(/\.update$/, ".edit"));
    return [...aliases];
  }

  return {
    actions: PERMISSION_ACTIONS,
    normalizeModule,
    normalizeAction,
    permissionFor: (module, action) =>
      `${normalizeModule(module)}_${normalizeAction(action)}`,
    aliasesFor,
    expandPermissionAliases,
  };
})();

const AppState = (() => {
  const STATES = {
    LOADING: "loading",
    SUCCESS: "success",
    EMPTY: "empty",
    VALIDATION_ERROR: "validation_error",
    UNAUTHORIZED: "unauthorized",
    FORBIDDEN: "forbidden",
    SERVER_ERROR: "server_error",
  };

  function classify(input) {
    const response = input?.response || input || {};
    const code = Number(
      response.code || response.status_code || input?.status || 0,
    );
    const errors = response.errors || {};

    if (code === 401) return STATES.UNAUTHORIZED;
    if (code === 403 || input?.code === "PERMISSION_DENIED")
      return STATES.FORBIDDEN;
    if (code === 422 || (errors && Object.keys(errors).length > 0))
      return STATES.VALIDATION_ERROR;
    if (code >= 500) return STATES.SERVER_ERROR;

    const data = response.data !== undefined ? response.data : response;
    if (Array.isArray(data) && data.length === 0) return STATES.EMPTY;
    if (data && Array.isArray(data.items) && data.items.length === 0)
      return STATES.EMPTY;
    if (data && Array.isArray(data.data) && data.data.length === 0)
      return STATES.EMPTY;

    return STATES.SUCCESS;
  }

  function normalizeResponse(response) {
    const success =
      response?.success !== undefined
        ? Boolean(response.success)
        : response?.status === "success";
    return {
      success,
      status: response?.status || (success ? "success" : "error"),
      data: response?.data ?? null,
      message: response?.message || (success ? "OK" : "Request failed"),
      errors: response?.errors || {},
      code: response?.code || response?.status_code || (success ? 200 : 400),
      raw: response,
    };
  }

  return { STATES, classify, normalizeResponse };
})();

window.AppState = AppState;
window.PermissionContract = PermissionContract;

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
  "/auth/reset-default-password": null,
  "/auth/reset-password": null,
  "/systemconfig/authorize": null,

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
    PUT: "students_edit",
    DELETE: "students_delete",
  },
  "/students/bulk-create": "students_create",
  "/students/bulk-update": "students_edit",
  "/students/bulk-delete": "students_delete",
  "/students/bulk-promote": "students_edit",
  "/students/photo-upload": "students_edit",
  "/students/profile-get": "students_view",
  "/students/attendance-get": "students_view",
  "/students/performance-get": "students_view",
  "/students/fees-get": "fees_view",
  "/students/qr-info-get": "students_view",
  "/students/statistics-get": "students_view",
  "/students/by-class-get": "students_view",
  "/students/by-stream-get": "students_view",
  "/students/roster-get": "students_view",
  "/students/discipline-get": "students_view",
  "/students/discipline-record": "students_discipline_create",
  "/students/discipline-update": "students_discipline_edit",
  "/students/discipline-resolve": "students_discipline_approve",
  "/students/qr-code-generate": "students_qr_generate",
  "/students/qr-code-generate-enhanced": "students_qr_generate",
  "/students/id-card-generate": "students_qr_generate",
  "/students/id-card-generate-class": "students_qr_generate",
  "/students/id-card-get": "students_view",
  "/students/id-card-statistics-get": "students_view",
  "/students/transfer-verify-eligibility": "students_edit",
  "/students/transfer-approve": "students_edit",
  "/students/transfer-execute": "students_edit",
  "/students/transfer-workflow-status": "students_view",
  "/students/transfer-history": "students_view",
  "/students/transfer-start-workflow": "students_edit",
  "/students/promotion-single": "students_promote",
  "/students/promotion-multiple": "students_promote",
  "/students/promotion-entire-class": "students_promote",
  "/students/promotion-multiple-classes": "students_promote",
  "/students/promotion-graduate-grade9": "students_promote",
  "/students/promotion-batches": "students_view",
  "/students/promotion-history": "students_view",
  "/students/promotion-meta-v2": "students_view",
  "/students/promotion-candidates-v2": "students_view",
  "/students/promotion-execute-v2": "students_promote",
  "/students/enrollment-history": "students_view",
  "/students/alumni-get": "students_view",
  "/students/enrollment-current": "students_view",
  "/students/academic-year-current": "students_view",
  "/students/academic-year-get": "students_view",
  "/students/academic-year-all": "students_view",
  "/students/academic-year-terms": "students_view",
  "/students/academic-year-current-term": "students_view",
  "/students/academic-year-create": "students_promote",
  "/students/academic-year-create-next": "students_promote",
  "/students/academic-year-set-current": "students_promote",
  "/students/academic-year-update-status": "students_promote",
  "/students/academic-year-archive": "students_promote",
  "/students/my-profile": ["students_view_own", "students_view"],
  "/students/my-children": [
    "students_view_own",
    "students_view",
    "students_parents_view",
  ],
  "/students/parents-get": [
    "students_parents_view",
    "students_view",
    "finance_view",
  ],
  "/students/parents-add": "students_edit",
  "/students/parents-update": "students_edit",
  "/students/parents-remove": "students_edit",
  "/students/parents/list": [
    "students_parents_view",
    "students_view",
    "finance_view",
  ],
  "/students/parents/get": [
    "students_parents_view",
    "students_view",
    "finance_view",
  ],
  "/students/parents/children": [
    "students_parents_view",
    "students_view",
    "finance_view",
  ],
  "/students/parents/create": "students_create",
  "/students/parents/delete": "students_edit",
  "/students/parents/link-child": "students_edit",
  "/students/parents/unlink-child": "students_edit",
  "/students/parents/available-students": "students_edit",
  "/students/without-parents": ["students_parents_view", "students_view"],

  // Academic
  "/academic/index": "academic_view",
  "/academic/classes-list": "academic_view",
  "/academic/classes/create": "academic_create",
  "/academic/classes/update": "academic_update",
  "/academic/classes/delete": "academic_delete",
  "/academic/streams-list": "academic_view",
  "/academic/streams/create": "academic_create",
  "/academic/streams/update": "academic_update",
  "/academic/streams/delete": "academic_delete",
  "/academic/class-capacity": "academic_view",
  "/academic/teachers-list": "academic_view",
  "/academic/levels-list": "academic_view",
  "/academic/context": "academic_view",
  "/academic/subjects": "academic_view",
  "/academic/subjects-list": "academic_view",
  "/academic/learning-areas/list": "academic_view",
  "/academic/learning-areas/get": "academic_view",
  "/academic/learning-areas/create": "academic_create",
  "/academic/learning-areas/update": "academic_update",
  "/academic/learning-areas/delete": "academic_delete",
  "/academic/curriculum-units": "academic_view",
  "/academic/curriculum-units-list": "academic_view",
  "/academic/curriculum-units-create": "academic_create",
  "/academic/curriculum-units-get": "academic_view",
  "/academic/curriculum-units-update": "academic_update",
  "/academic/curriculum-units-delete": "academic_delete",
  "/academic/curriculum-units/create": "academic_create",
  "/academic/curriculum-units/update": "academic_update",
  "/academic/curriculum-units/delete": "academic_delete",
  "/academic/exam-schedule": {
    GET: "academic_view",
    POST: "academic_update",
    PUT: "academic_update",
    DELETE: "academic_update",
  },
  "/academic/schemes-of-work": {
    GET: "academic_view",
    POST: "academic_update",
    PUT: "academic_update",
    DELETE: "academic_update",
  },
  "/academic/scheme-of-work-get": "academic_view",
  "/academic/lesson-plans-list": "academic_view",
  "/academic/lesson-plans-approval": "academic_view",
  "/academic/lesson-plans-review": "academic_update",
  "/academic/lesson-plans-bulk-approve": "academic_update",
  "/academic/performance-overview": "academic_view",
  "/academic/student-results": "academic_view",
  "/academic/custom": {
    GET: "academic_view",
    POST: "academic_update",
  },
  "/academic/schedules-list": "academic_view",
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
  "/finance": "finance_view",
  "/finance/students/payment-status": "finance_view",
  "/finance/payroll": {
    GET: "finance_view",
    POST: "finance_create",
    PUT: "finance_update",
  },

  // Staff
  // Staff-domain controllers enforce their canonical StaffAccess permissions
  // and role fallbacks server-side. Keeping legacy-only client checks here
  // blocks valid oversight roles before the request reaches PHP.
  "/staff/index": null,
  "/staff/staff": {
    GET: "staff_view",
    POST: "staff_create",
    PUT: "staff_update",
    DELETE: "staff_delete",
  },
  "/staff/children-list": "staff_view",
  "/staff/children-add": "staff_update",
  "/staff/children-update": "staff_update",
  "/staff/children-remove": "staff_update",
  "/staff/children-fee-config": "staff_view",
  "/staff/children-calculate-deductions": "staff_view",
  
  // New staff endpoints for UI controllers
  "/staff/teachers": null,
  "/staff/non-teaching": null,
  "/staff/performance-review-history": "staff_performance_view",
  "/staff/academic-kpi-summary": "staff_performance_view",
  "/staff/performance-reviews": "staff_performance_view",
  "/staff/available-roles": "staff_roles_manage",
  "/staff/role-assignments": "staff_roles_manage",
  "/staff/assign-role": "staff_roles_manage",
  "/staff/revoke-role": "staff_roles_manage",
  "/staff/onboarding": null,
  "/staff/onboarding-task": null,
  "/staff/onboarding-document": null,
  "/staff/probation-review": null,
  "/staff/onboarding-templates": null,
  "/staff/onboarding-pending": null,
  "/staff/lifecycle": "staff_lifecycle_view",
  "/staff/appointments": "staff_appointments_view",
  "/staff/id-card/generate": null,
  "/staff/id-card/generate-bulk-pdf": null,
  "/staff/id-card/print-single": null,
  "/staff/id-cards": null,
  "/staff/id-cards-generate": null,
  "/staff/id-cards-bulk-generate": null,
  "/staff/id-cards-issue": null,
  "/staff/import-existing": "staff_import_manage",
  "/staff-migration/reference-data": "staff_import",
  "/staff-migration/batches": "staff_import",
  "/staff-migration/batch": "staff_import",
  "/staff-migration/template": "staff_import",
  "/staff-migration/template-xlsx": "staff_import",
  "/staff-migration/stage": "staff_import",
  "/staff-migration/commit": "staff_import",
  "/staff-migration/rollback": "staff_import_rollback",
  "/staff-migration/resend-invitation": "staff_invitation_resend",
  "/staff-migration/onboarding": null,
  "/staff-migration/profile": null,

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
  "/admission/queues": "admission_view",
  "/admission/stats": "admission_view",
  "/admission/notifications": "admission_view",
  "/admission/placement-classes": "admission_view",
  "/admission/submit-application": "admission_applications_create",
  "/admission/upload-document": "admission_documents_upload",
  "/admission/verify-document": "admission_documents_verify",
  "/admission/schedule-interview": "admission_interviews_schedule",
  "/admission/record-interview-results": "admission_interviews_create",
  "/admission/generate-placement-offer": "admission_applications_generate",
  "/admission/record-fee-payment": "admission_applications_edit",
  "/admission/complete-enrollment": "admission_applications_approve_final",
  "/admission/application": {
    GET: "admission_view",
    POST: "admission_applications_create",
    PUT: "admission_applications_edit",
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
  "/schedules/timetable-get": "schedules_view",
  "/schedules/timetable-create": "schedules_create",
  "/schedules/timetable-update": "schedules_update",
  "/schedules/timetable-delete": "schedules_update",
  "/schedules/timetable-check-conflicts": "schedules_view",
  "/schedules/timetable-report-conflict": "schedules_create",
  "/schedules/timetable-time-slots": "schedules_view",
  "/schedules/rooms-get": "schedules_view",

  // Reports
  "/reports/index": "reports_view",
  "/reports/academic": "reports_view",

  // System
  "/system/index": "system_view",
  "/system/logs": { GET: "system_view", DELETE: "system_manage" },
  "/system/roles": {
    GET: ["system.rbac.view", "system.rbac.manage", "system_roles_view"],
    POST: ["system.rbac.manage", "system_roles_create"],
    PUT: ["system.rbac.manage", "system_roles_edit"],
    DELETE: ["system.rbac.manage", "system_roles_delete"],
  },
  "/system/roles-toggle": [
    "system.rbac.manage",
    "system_roles_edit",
  ],
  "/system/permissions": {
    GET: ["system.rbac.view", "system.rbac.manage", "system_roles_view"],
    POST: "system.rbac.manage",
    PUT: "system.rbac.manage",
    DELETE: "system.rbac.manage",
  },
  "/system/resource-permissions": [
    "system.rbac.view",
    "system.rbac.manage",
  ],
  "/system/authentication-logs": "system.security.view",
  "/system/failed-login-attempts": "system.security.view",
  "/system/active-sessions": "system.security.view",
  "/system/active-sessions-revoke": "system.security.manage",
  "/system/tokens": "system.security.view",
  "/system/tokens-revoke": "system.security.manage",
  "/system/ip-lists": {
    GET: "system.security.view",
    POST: "system.security.manage",
    PUT: "system.security.manage",
    DELETE: "system.security.manage",
  },
  "/dashboard/system-admin": "system.dashboard.view",

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

  // Check direct endpoint match first.
  let requirement = ENDPOINT_PERMISSIONS[normalizedEndpoint];

  // Fallback for endpoints with path params, e.g. /students/student/123.
  if (!requirement) {
    const fallbackKey = Object.keys(ENDPOINT_PERMISSIONS)
      .filter(
        (key) =>
          normalizedEndpoint === key ||
          normalizedEndpoint.startsWith(key + "/"),
      )
      .sort((a, b) => b.length - a.length)[0];

    if (fallbackKey) {
      requirement = ENDPOINT_PERMISSIONS[fallbackKey];
    }
  }

  if (!requirement) {
    // No specific permission defined for this endpoint
    // Could log a warning in development
    return null;
  }

  if (typeof requirement === "string" || Array.isArray(requirement)) {
    // Simple string permission requirement (same for all methods)
    return requirement;
  }

  if (typeof requirement === "object" && requirement !== null) {
    // Method-specific permission requirements
    return requirement[method.toUpperCase()] || requirement["GET"] || null;
  }

  return null;
}

function hasAdmissionsRouteAccessFallback() {
  // Admission API fallback should only trust a prior API-backed route check,
  // never local sidebar data.
  return Boolean(window.__admissionsRouteAuthorized);
}

/**
 * Validate user has required permission before making API call
 * Throws error if user lacks permission
 * @param {string} endpoint
 * @param {string} method
 * @throws {Error} If user is not authenticated or lacks permission
 */
function validatePermission(endpoint, method) {
  const normalizedEndpoint =
    "/" +
    String(endpoint || "")
      .replace(/^\/+/, "")
      .split("?")[0];

  // Skip permission check if user is not authenticated (will fail at backend)
  if (!AuthContext.isAuthenticated()) {
    // Only warn for endpoints that actually require auth — public endpoints (login, etc.) have no required permission
    const requiredForWarn = getRequiredPermission(endpoint, method);
    if (requiredForWarn) {
      console.warn("API call attempted without authentication:", endpoint);
    }
    return;
  }

  const requiredPermission = getRequiredPermission(endpoint, method);

  // No permission requirement for this endpoint
  if (!requiredPermission) {
    return;
  }

  // Check exact permission and common edit/update aliases for backward compatibility.
  const requiredPermissions = Array.isArray(requiredPermission)
    ? requiredPermission
    : [requiredPermission];
  const aliases = new Set(requiredPermissions);
  requiredPermissions.forEach((permission) => {
    if (permission.endsWith("_edit")) {
      aliases.add(permission.replace(/_edit$/, "_update"));
    }
    if (permission.endsWith("_update")) {
      aliases.add(permission.replace(/_update$/, "_edit"));
    }
    if (permission.endsWith(".edit")) {
      aliases.add(permission.replace(/\.edit$/, ".update"));
    }
    if (permission.endsWith(".update")) {
      aliases.add(permission.replace(/\.update$/, ".edit"));
    }
  });

  const hasPermission = [...aliases].some((permissionCode) =>
    AuthContext.hasPermission(permissionCode),
  );

  if (!hasPermission) {
    const isAdmissionEndpoint =
      normalizedEndpoint.startsWith("/admission/") ||
      normalizedEndpoint.startsWith("/admissions/");

    // Admission workflows also allow route-based/stage-based access in backend.
    // Avoid false client-side denials and defer fine-grained authorization to API.
    if (isAdmissionEndpoint && hasAdmissionsRouteAccessFallback()) {
      return;
    }
  }

  if (!hasPermission) {
    const error = new Error(
      `Access Denied: You do not have permission "${requiredPermissions.join(" or ")}" to ${method} ${endpoint}`,
    );
    error.code = "PERMISSION_DENIED";
    error.permission = requiredPermission;
    throw error;
  }
}

function applyPermissionContract(container = document) {
  if (!container || !container.querySelectorAll || !window.AuthContext) return;

  container
    .querySelectorAll("[data-permission-module][data-permission-action]")
    .forEach((element) => {
      const module = element.getAttribute("data-permission-module");
      const action = element.getAttribute("data-permission-action");
      const allowed = AuthContext.canAction(module, action);
      element.hidden = !allowed;
      if ("disabled" in element) {
        element.disabled = !allowed;
      }
      element.setAttribute("aria-hidden", allowed ? "false" : "true");
    });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () =>
    applyPermissionContract(document),
  );
} else {
  applyPermissionContract(document);
}

/**
 * A 401 refresh attempt failed. Rather than immediately clearing auth state
 * and hard-redirecting to index.php (which would bounce the whole app before
 * telemetry/data runs on initial load), we emit a SESSION_EXPIRED event and let
 * the app decide. A registered handler can show a login modal; only if nothing
 * handles it do we fall back to a redirect. The user is never silently logged
 * out in the middle of boot for a single transient failure.
 */
let _sessionExpiredEmitted = false;
function handleSessionExpired(reason = "refresh_rejected") {
  // This function is called only after the refresh endpoint has explicitly
  // rejected the session with 401/403. Network failures, timeouts, 429 and 5xx
  // responses must never erase a valid local session.
  console.warn("[API] Session expired — emitting SESSION_EXPIRED", reason);

  if (typeof AuthContext !== "undefined" && AuthContext.clearUser) {
    AuthContext.clearUser();
  }

  // Prefer an event-driven UI (login modal) over a hard redirect.
  if (
    typeof SessionManager !== "undefined" &&
    typeof SessionManager.onSessionExpired === "function"
  ) {
    try {
      SessionManager.onSessionExpired();
    } catch (e) {
      console.error(e);
    }
    return;
  }

  // Fallback: emit a global event other code can hook.
  window.dispatchEvent(new CustomEvent("SESSION_EXPIRED"));

  // Last-resort redirect only if nothing handled it and we're not mid-boot.
  if (!_sessionExpiredEmitted) {
    _sessionExpiredEmitted = true;
    if (window.__APP_BOOTED__) {
      // Avoid double redirects within the same tab.
      if (sessionStorage.getItem("_session_expired_redirect")) return;
      sessionStorage.setItem("_session_expired_redirect", "1");
      setTimeout(function () {
        sessionStorage.removeItem("_session_expired_redirect");
        window.location.href = (window.APP_BASE || "") + "/index.php";
      }, 2000);
    }
  }
}

/**
 * Refresh access token using stored refresh token.
 * Implements a per-tab mutex plus a cross-tab lock so multiple open windows do
 * not stampede the refresh endpoint.
 * @returns {Promise<boolean>} True if token was refreshed successfully
 */
async function refreshAccessToken() {
  if (isRefreshingToken && refreshTokenPromise) {
    return refreshTokenPromise;
  }

  isRefreshingToken = true;

  refreshTokenPromise = (async () => {
    const ownsLock = acquireTokenRefreshLock();
    if (!ownsLock) {
      const remoteRefreshWorked = await waitForRemoteTokenRefresh();
      if (remoteRefreshWorked || !isTokenExpired(TOKEN_REFRESH_SKEW_SECONDS)) {
        return true;
      }
    }

    try {
      return await performRefreshAccessToken();
    } finally {
      if (ownsLock) {
        releaseTokenRefreshLock();
      }
      isRefreshingToken = false;
      refreshTokenPromise = null;
    }
  })();

  return refreshTokenPromise;
}

async function performRefreshAccessToken() {
  try {
    const refreshToken = AuthContext.getRefreshToken();
    const url = new URL(
      API_BASE_URL + "/auth/refresh-token",
      window.location.origin,
    );

    const response = await fetch(url, {
      method: "POST",
      credentials: "include",
      cache: "no-store",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "Cache-Control": "no-store",
      },
      body: JSON.stringify(refreshToken ? { refresh_token: refreshToken } : {}),
    });

    // Only an explicit authentication rejection proves the refresh session
    // is dead. Do not log users out for rate limits, backend errors or an
    // interrupted network connection.
    if (response.status === 401 || response.status === 403) {
      handleSessionExpired("refresh_rejected_" + response.status);
      return false;
    }

    if (!response.ok) {
      console.warn("[API] Temporary token refresh failure:", response.status);
      return false;
    }

    const result = await readJsonSafely(response, "Token refresh");
    const payload = result && result.data ? result.data : result;
    const token = payload && (payload.token || payload.access_token);

    if (!token) {
      console.warn("[API] Refresh response did not contain an access token.");
      return false;
    }

    AuthContext.setTokens(token, payload.refresh_token || null);

    if (payload.user) {
      AuthContext.setUser(payload.user, payload, true);
    }

    window.dispatchEvent(new CustomEvent("AUTH_TOKEN_REFRESHED"));
    announceTokenRefresh();
    return true;
  } catch (error) {
    console.warn("[API] Token refresh temporarily unavailable:", error);
    return false;
  }
}

/**
 * Check if JWT token is expired based on 'exp' claim
 * Returns true if token is about to expire within the supplied buffer.
 */
function isTokenExpired(bufferSeconds = 60) {
  const expiresIn = getTokenSecondsUntilExpiry();
  return expiresIn === null || expiresIn < bufferSeconds;
}

function getTokenSecondsUntilExpiry() {
  const token = AuthContext.getToken();
  if (!token) return null;

  try {
    // Decode JWT (without verification, just get payload)
    const parts = token.split(".");
    if (parts.length !== 3) return null;

    const payload = JSON.parse(base64UrlDecode(parts[1]));
    const now = Math.floor(Date.now() / 1000);
    return Number(payload.exp || 0) - now;
  } catch (error) {
    console.error("Error checking token expiry:", error);
    return null;
  }
}

function base64UrlDecode(value) {
  const padded = String(value || "").padEnd(
    Math.ceil(String(value || "").length / 4) * 4,
    "=",
  );
  return atob(padded.replace(/-/g, "+").replace(/_/g, "/"));
}

function recordSessionActivity(source = "user") {
  try {
    localStorage.setItem(SESSION_ACTIVITY_KEY, String(Date.now()));
  } catch (_) {}
  window.KingswaySessionActivitySource = source;
}

function getLastSessionActivityAt() {
  const raw = localStorage.getItem(SESSION_ACTIVITY_KEY);
  const parsed = Number(raw || 0);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
}

function isSessionActiveForRefresh() {
  const lastActivity = getLastSessionActivityAt();
  if (!lastActivity) return true;
  return Date.now() - lastActivity <= ACTIVE_SESSION_WINDOW_MS;
}

function acquireTokenRefreshLock() {
  const now = Date.now();
  try {
    const existing = JSON.parse(
      localStorage.getItem(TOKEN_REFRESH_LOCK_KEY) || "null",
    );
    if (
      existing &&
      existing.owner &&
      existing.owner !== CURRENT_TAB_ID &&
      now - Number(existing.created_at || 0) < TOKEN_REFRESH_LOCK_TTL_MS
    ) {
      return false;
    }

    const lock = { owner: CURRENT_TAB_ID, created_at: now };
    localStorage.setItem(TOKEN_REFRESH_LOCK_KEY, JSON.stringify(lock));
    const stored = JSON.parse(
      localStorage.getItem(TOKEN_REFRESH_LOCK_KEY) || "null",
    );
    return stored && stored.owner === CURRENT_TAB_ID;
  } catch (_) {
    return true;
  }
}

function releaseTokenRefreshLock() {
  try {
    const lock = JSON.parse(
      localStorage.getItem(TOKEN_REFRESH_LOCK_KEY) || "null",
    );
    if (lock && lock.owner === CURRENT_TAB_ID) {
      localStorage.removeItem(TOKEN_REFRESH_LOCK_KEY);
    }
  } catch (_) {
    localStorage.removeItem(TOKEN_REFRESH_LOCK_KEY);
  }
}

function announceTokenRefresh() {
  try {
    localStorage.setItem(
      TOKEN_REFRESH_EVENT_KEY,
      JSON.stringify({ owner: CURRENT_TAB_ID, refreshed_at: Date.now() }),
    );
    localStorage.removeItem(TOKEN_REFRESH_EVENT_KEY);
  } catch (_) {}
}

function waitForRemoteTokenRefresh() {
  return new Promise((resolve) => {
    let settled = false;
    const done = (ok) => {
      if (settled) return;
      settled = true;
      window.removeEventListener("storage", onStorage);
      window.removeEventListener("AUTH_TOKEN_REFRESHED", onLocalRefresh);
      clearTimeout(timer);
      resolve(Boolean(ok));
    };
    const onStorage = (event) => {
      if (event.key === TOKEN_REFRESH_EVENT_KEY) {
        done(!isTokenExpired(TOKEN_REFRESH_SKEW_SECONDS));
      }
    };
    const onLocalRefresh = () => done(true);
    const timer = setTimeout(() => done(false), TOKEN_REFRESH_WAIT_MS);

    window.addEventListener("storage", onStorage);
    window.addEventListener("AUTH_TOKEN_REFRESHED", onLocalRefresh);

    if (!isTokenExpired(TOKEN_REFRESH_SKEW_SECONDS)) {
      done(true);
    }
  });
}

function startSessionActivityTracking() {
  if (window.__KINGSWAY_ACTIVITY_TRACKING__) return;
  window.__KINGSWAY_ACTIVITY_TRACKING__ = true;
  recordSessionActivity("boot");

  let lastWrite = 0;
  const mark = (source) => {
    const now = Date.now();
    if (now - lastWrite < 15000) return;
    lastWrite = now;
    recordSessionActivity(source);
  };

  ["pointerdown", "keydown", "input", "touchstart", "scroll"].forEach(
    (eventName) => {
      window.addEventListener(eventName, () => mark(eventName), {
        passive: true,
        capture: true,
      });
    },
  );
  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") mark("visible");
  });

  window.KingswaySessionActivity = {
    markActive: recordSessionActivity,
    isActive: isSessionActiveForRefresh,
    shouldRefreshSoon: () =>
      Boolean(AuthContext?.isAuthenticated?.()) &&
      isSessionActiveForRefresh() &&
      isTokenExpired(TOKEN_REFRESH_SKEW_SECONDS),
    secondsUntilExpiry: getTokenSecondsUntilExpiry,
  };
}

// Generic API call function using fetch
async function apiCall(
  endpoint,
  method = "GET",
  data = null,
  params = {},
  options = {},
) {
  try {
    const queryParams =
      params && typeof params === "object" && !Array.isArray(params)
        ? params
        : {};

    const upperMethod = String(method || "GET").toUpperCase();
    if (
      AuthContext.isAuthenticated() &&
      upperMethod !== "GET"
    ) {
      recordSessionActivity("api_write");
    }

    // If the token is nearing expiry and the user/session is active, refresh
    // through the cross-tab coordinator. Idle sessions are allowed to expire
    // cleanly instead of being kept alive by background tabs.
    if (
      AuthContext.isAuthenticated() &&
      isTokenExpired(TOKEN_REFRESH_SKEW_SECONDS)
    ) {
      if (!isSessionActiveForRefresh() && isTokenExpired(0)) {
        handleSessionExpired("idle_token_expired");
        throw new Error("Session expired due to inactivity");
      }

      console.log("Token expiring soon, refreshing...");
      try {
        await refreshAccessToken();
      } catch (refreshError) {
        console.warn(
          "Proactive token refresh failed; continuing with current token:",
          refreshError && refreshError.message,
        );
      }
    }

    // Validate permission BEFORE making the request
    // If user lacks permission, this will throw an error
    if (options.checkPermission !== false) {
      validatePermission(endpoint, method);
    }

    // Normalize endpoint once. Cache keys must never be passed here as paths,
    // and callers that still include /api are tolerated without producing /api/api.
    const normalizedEndpoint = (() => {
      let value = String(endpoint || "").trim();
      if (/^https?:\/\//i.test(value)) return value;
      const basePath = String(API_BASE_URL || "")
        .replace(/^https?:\/\/[^/]+/i, "")
        .replace(/\/+$/, "");
      if (basePath.endsWith("/api") && value.startsWith("/api/"))
        value = value.slice(4);
      if (!value.startsWith("/")) value = "/" + value;
      return value;
    })();
    const url = /^https?:\/\//i.test(normalizedEndpoint)
      ? new URL(normalizedEndpoint)
      : new URL(API_BASE_URL + normalizedEndpoint, window.location.origin);
    Object.keys(queryParams).forEach((key) => {
      const value = queryParams[key];
      if (value !== null && value !== undefined) {
        url.searchParams.append(key, value);
      }
    });
    const requestUrl = url.toString();

    // Check if token exists
    const authFreeEndpoints = new Set([
      "/auth/login",
      "/auth/register",
      "/auth/forgot-password",
      "/auth/reset-password",
      "/auth/complete-reset",
      "/auth/verify-reset-token",
      "/auth/refresh-token",
      "/auth/logout-refresh",
      "/auth/session",
      "/auth/refresh-session",
      "/auth/validate-token",
    ]);
    const token = authFreeEndpoints.has(normalizedEndpoint)
      ? null
      : AuthContext.getToken();
    if (!token && !authFreeEndpoints.has(normalizedEndpoint)) {
      console.warn("⚠️ No JWT token found - API call will fail with 401");
    }

    // Request options
    const fetchOptions = {
      method: method,
      credentials:
        options.credentials ||
        (window.location.hostname === "localhost" ? "same-origin" : "include"),
      headers: {
        ...(options.isFile ? {} : { "Content-Type": "application/json" }),
        Accept: "application/json",
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

    let response = await fetchWithBrowserFallback(requestUrl, fetchOptions);

    // Handle 401 Unauthorized - token may have expired, try to refresh
    if (response.status === 401 && !options.isRefreshAttempt) {
      // [DIAG] capture auth storage state at first 401 of this session
      if (!sessionStorage.getItem("_diag_401_dumped")) {
        sessionStorage.setItem("_diag_401_dumped", "1");
        const _ls = {},
          _ss = {};
        for (let i = 0; i < localStorage.length; i++) {
          const k = localStorage.key(i);
          _ls[k] = localStorage.getItem(k);
        }
        for (let i = 0; i < sessionStorage.length; i++) {
          const k = sessionStorage.key(i);
          _ss[k] = sessionStorage.getItem(k);
        }
        console.warn(
          "[DIAG-401] url=",
          url,
          "| hasToken=",
          !!AuthContext.getToken(),
          "| ls.auth_storage_mode=",
          localStorage.getItem("auth_storage_mode"),
          "| ls.token?=",
          !!localStorage.getItem("token"),
          "| ss.token?=",
          !!sessionStorage.getItem("token"),
          "| localStorage=",
          _ls,
          "| sessionStorage=",
          _ss,
        );
      }
      console.log("Received 401 Unauthorized, attempting token refresh...");
      const refreshed = await refreshAccessToken();

      if (refreshed) {
        // Retry the original request with new token
        const newToken = AuthContext.getToken();
        if (!newToken) {
          // Refresh reported success but no access token is available to the
          // client. Retrying would only 401 again ("Missing Authorization
          // header") silently. Fail loud so the session is treated as invalid.
          console.error(
            "Token refresh succeeded but no access token is available; session is invalid.",
          );
          throw new Error("Authentication failed, please log in again");
        }
        console.log("Retrying original request with refreshed token...");
        fetchOptions.headers.Authorization = "Bearer " + newToken;
        response = await fetchWithBrowserFallback(requestUrl, fetchOptions);
      } else {
        // Refresh failed, user is logged out and redirected
        throw new Error("Authentication failed, please log in again");
      }
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
    const result = await readJsonSafely(response, `API ${method} ${endpoint}`);
    const handled = handleApiResponse(result, options.showSuccess !== false);

    // Auto-invalidate cached data on mutations
    if (MUTATION_METHODS.has(String(method).toUpperCase())) {
      const targets = options.invalidate || [inferResourceKey(endpoint)];

      // Use DataStore for automatic cache invalidation if available
      if (typeof DataStore !== "undefined") {
        DataStore.invalidateMany(targets.filter(Boolean)).catch((err) => {
          console.warn("DataStore invalidation failed:", err);
        });
      }

      // Fallback to APIState
      APIState.invalidateMany(targets.filter(Boolean)).catch((err) => {
        console.warn("Auto-refresh failed for targets:", targets, err);
      });

      // Propagate the invalidation to OTHER tabs so they don't keep stale
      // IndexedDB snapshots. Server stays authoritative; this only drops the
      // local cache in sibling tabs (session_manager handles the broadcast).
      if (
        typeof SessionManager !== "undefined" &&
        SessionManager.broadcastCacheInvalidation
      ) {
        SessionManager.broadcastCacheInvalidation(targets.filter(Boolean));
      }
    }

    return handled;
  } catch (error) {
    error.endpoint = error.endpoint || endpoint;
    error.method = error.method || method;
    if (/Failed to fetch|NetworkError|network/i.test(error.message || "")) {
      console.error("[api.js] Network fetch failed", {
        endpoint,
        method,
        apiBaseUrl: typeof API_BASE_URL !== "undefined" ? API_BASE_URL : null,
        appBase: window.APP_BASE || "",
        origin: window.location.origin,
        href: window.location.href,
        online: navigator.onLine,
        serviceWorkerControlled: Boolean(navigator.serviceWorker?.controller),
        error: error.message,
      });
    }

    // For permission denied errors, log to console instead of showing popup
    if (error.code === "PERMISSION_DENIED") {
      console.warn("Permission Denied:", error.message);
    }

    // Offline write-queue producer: if a mutation fails because the network is
    // unreachable, enqueue it so SyncQueue (and Background Sync) replays it on
    // reconnect. This is what makes the /js/sync/sync_queue.js engine actually
    // get fed instead of sitting dormant. Only mutations are queued — a failed
    // GET is just a cache miss and the SW serves the last good response.
    const isMutation = MUTATION_METHODS.has(String(method).toUpperCase());
    const isOffline =
      navigator.onLine === false ||
      (typeof error === "object" &&
        error !== null &&
        /Failed to fetch|NetworkError|network/i.test(error.message || ""));
    if (isMutation && isOffline && typeof SyncQueue !== "undefined") {
      try {
        const module = (endpoint.split("/").filter(Boolean)[0] || "api")
          .replace(/^api\//, "")
          .split("/")[0];
        await SyncQueue.addOperation({
          module,
          endpoint: (window.APP_BASE || "") + endpoint,
          method: String(method).toUpperCase(),
          payload: data,
          entity_type: module,
        });
        console.warn("[api.js] Offline — mutation queued for sync:", endpoint);
        // Surface a clear, non-error state so the UI knows it is pending.
        return {
          status: "queued",
          message: "Saved offline; will sync when connection returns.",
        };
      } catch (queueError) {
        console.error("[api.js] Failed to queue offline mutation:", queueError);
      }
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
  // Alias so controllers using API.callAPI() instead of API.apiCall() work
  callAPI: apiCall,
  showNotification,
  applyPermissionContract,
  state: APIState,
  appState: AppState,
  permissions: PermissionContract,

  // Auth endpoints
  auth: {
    index: async () => apiCall("/auth/index", "GET"),
    login: async (username, password, rememberMe = false) => {
      AuthContext.setPersistence(rememberMe);
      const response = await apiCall("/auth/login", "POST", {
        username,
        password,
        remember_me: rememberMe,
      });

      console.log("Full login response:", response);

      if (response && response.token) {
        // Store both access and refresh tokens in selected auth storage
        AuthContext.setTokens(response.token, response.refresh_token || null);

        // Store user context with permissions
        // The backend returns the user object in response.user
        const userData = response.user || {};

        console.log("User data:", userData);
        console.log("Sidebar items:", response.sidebar_items);
        console.log("Dashboard info:", response.dashboard);

        AuthContext.setUser(userData, response, rememberMe);

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
          `Roles: ${AuthContext.getRoles().join(", ")}`,
        );

        // Navigate to user's default dashboard
        const dashboardInfo = AuthContext.getDashboardInfo();
        console.log("Dashboard info for navigation:", dashboardInfo);

        let redirectUrl;
        if (response.password_setup_required && response.password_setup_url) {
          redirectUrl = response.password_setup_url;
        } else if (dashboardInfo && dashboardInfo.key) {
          // Use the normalized key (route name)
          redirectUrl =
            (window.APP_BASE || "") + "/home.php?route=" + dashboardInfo.key;
        } else {
          // Fallback to home page which will redirect to appropriate dashboard
          redirectUrl = (window.APP_BASE || "") + "/home.php";
        }

        console.log("Redirecting to:", redirectUrl);
        window.location.href = redirectUrl;
      }
      return response;
    },
    logout: async () => {
      try {
        // Revoke the refresh token on the server. Cookie-backed sessions still
        // work even when nothing was stored in localStorage.
        const refreshToken = AuthContext.getRefreshToken();
        await apiCall(
          "/auth/logout-refresh",
          "POST",
          refreshToken ? { refresh_token: refreshToken } : {},
          {},
          {
            checkPermission: false,
            isRefreshAttempt: true,
            credentials: "include",
          },
        );
      } catch (error) {
        console.warn("Error revoking refresh token on server:", error);
        // Continue with logout even if revoke fails
      } finally {
        // Clear local storage
        AuthContext.clearUser();
        // Redirect to login
        window.location.href = (window.APP_BASE || "") + "/index.php";
      }
    },
    forgotPassword: async (email) =>
      apiCall(
        "/auth/forgot-password",
        "POST",
        { email },
        {},
        { checkPermission: false },
      ),
    resetPassword: async (token, password) =>
      apiCall(
        "/auth/reset-password",
        "POST",
        { token, password },
        {},
        { checkPermission: false },
      ),
    refreshToken: async () => {
      const refreshToken = AuthContext.getRefreshToken();
      const response = await apiCall(
        "/auth/refresh-token",
        "POST",
        refreshToken ? { refresh_token: refreshToken } : {},
        {},
        {
          checkPermission: false,
          credentials: "include",
          isRefreshAttempt: true, // Skip token refresh check to avoid recursion
        },
      );
      if (response && response.token) {
        AuthContext.setTokens(response.token, response.refresh_token || null);
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

    // List helpers
    list: async (params = {}) =>
      apiCall("/students/student", "GET", null, params),
    contextList: async (params = {}) =>
      apiCall("/students/context-list", "GET", null, params),
    contextProfile: async (id, params = {}) =>
      apiCall(`/students/context-profile/${id}`, "GET", null, params),
    contextMeta: async (params = {}) =>
      apiCall("/students/context-meta", "GET", null, params),
    getAll: async (params = {}) => {
      const resp = await apiCall("/students/student", "GET", null, params);
      const payload = resp?.data?.data ?? resp?.data ?? resp;
      const students =
        payload?.students ??
        payload?.data?.students ??
        (Array.isArray(payload) ? payload : []);
      const pagination = payload?.pagination ?? payload?.data?.pagination ?? {};
      const total = pagination?.total ?? students.length;
      const success =
        resp?.status === "success" ||
        payload?.status === "success" ||
        payload?.status_code === 200;
      return { success, data: students, pagination, total, raw: resp };
    },

    // CRUD
    get: async (id = null) =>
      id
        ? apiCall(`/students/student/${id}`, "GET")
        : apiCall("/students/student", "GET"),
    create: async (data) => apiCall("/students/student", "POST", data),
    update: async (id, data) => apiCall(`/students/student/${id}`, "PUT", data),
    delete: async (id) => apiCall(`/students/student/${id}`, "DELETE"),

    // Profile & Info
    getProfile: async (id = null) =>
      id
        ? apiCall(`/students/profile-get/${id}`, "GET")
        : apiCall("/students/profile-get", "GET"),
    getMyProfile: async () => apiCall("/students/my-profile", "GET"),
    getMyChildren: async () => apiCall("/students/my-children", "GET"),
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
    getStats: async (params = {}) => {
      const resp = await apiCall(
        "/students/statistics-get",
        "GET",
        null,
        params,
      );
      const payload = resp?.data?.data ?? resp?.data ?? resp;
      const stats = payload?.data ?? payload;
      const success =
        resp?.status === "success" ||
        payload?.status === "success" ||
        payload?.status_code === 200;
      return { success, data: stats, raw: resp };
    },
    getEnrollmentHistory: async (studentId) =>
      apiCall(`/students/enrollment-history/${studentId}`, "GET"),

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
    getIdCard: async (studentId) =>
      apiCall(`/students/id-card-get/${studentId}`, "GET"),
    getIdCardStatistics: async (params = {}) =>
      apiCall("/students/id-card-statistics-get", "GET", null, params),

    // Photo
    uploadPhoto: async (formData) =>
      apiCall("/students/photo-upload", "POST", formData, {}, { isFile: true }),

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
        "GET",
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
        { isFile: true },
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
        { isDownload: true },
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
          searchTerm,
        )}&limit=${limit}&offset=${offset}`,
        "GET",
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
        "GET",
      ),
    getStudentsWithoutParents: async () =>
      apiCall("/students/without-parents", "GET"),
  },

  // Academic endpoints
  academic: {
    index: async () => apiCall("/academic/index", "GET"),
    getContext: async () => apiCall("/academic/context", "GET"),
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
        data,
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
    getCurrentYear: async () => apiCall("/academic/years/current", "GET"),
    getCurrentAcademicYear: async () =>
      apiCall("/academic/years/current", "GET"),
    getAcademicYear: async (id = null) =>
      id
        ? apiCall(`/academic/years/get/${id}`, "GET")
        : apiCall("/academic/years/list", "GET"),
    getAllAcademicYears: async () => apiCall("/academic/years/list", "GET"),
    createAcademicYear: async (data) =>
      apiCall("/academic/years/create", "POST", data),
    setCurrentAcademicYear: async (id) =>
      apiCall(`/academic/years/set-current/${id}`, "PUT"),

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
    getClassCapacity: async (params) =>
      apiCall("/academic/class-capacity", "GET", null, params),
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
    listLevels: async () => apiCall("/academic/levels-list", "GET"),
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
    listSubjects: async (params) =>
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
    rejectLessonPlan: async (data) =>
      apiCall("/academic/lesson-plans-reject", "POST", data),
    submitLessonPlan: async (data) =>
      apiCall("/academic/lesson-plans-submit", "POST", data),
    // Approval page endpoints
    listLessonPlansApproval: async (params) =>
      apiCall("/academic/lesson-plans-approval", "GET", null, params),
    reviewLessonPlan: async (id, data) =>
      apiCall(`/academic/lesson-plans-review/${id}`, "PUT", data),
    bulkApproveLessonPlans: async (ids) =>
      apiCall("/academic/lesson-plans-bulk-approve", "PUT", { ids }),

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
    listTeachers: async (params = {}) =>
      apiCall("/academic/teachers-list", "GET", null, params),
    getTeachers: async (params = {}) =>
      apiCall("/academic/teachers-list", "GET", null, params),
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

    // ---- Admission Stage 5: period-aware, cohort-aware capacity projection ----
    // Capacity for a target academic year / class (optionally a term & stream).
    // Params: target_academic_year_id (required), target_class_id (required),
    //         target_term_id?, target_stream_id?, applied_academic_year?.
    getCohortCapacity: async (params = {}) =>
      apiCall("/academic/cohort-capacity", "GET", null, params),
    // Capacity for a specific admission application (resolves its target class).
    getCohortProjection: async (applicationId) =>
      apiCall(
        `/academic/cohort-projection?application_id=${applicationId}`,
        "GET",
      ),
  },

  // Attendance endpoints
  attendance: {
    index: async () => apiCall("/attendance/index", "GET"),
    getSessions: async (params = {}) =>
      apiCall("/attendance/sessions", "GET", null, params),
    getAcademicSummary: async (params = {}) =>
      apiCall("/attendance/academic-summary", "GET", null, params),
    getDailyRegister: async (params = {}) =>
      apiCall("/attendance/daily-register", "GET", null, params),
    getBoardingSummary: async (params = {}) =>
      apiCall("/attendance/boarding-summary", "GET", null, params),
    getDormitories: async (params = {}) =>
      apiCall("/attendance/dormitories", "GET", null, params),
    getDormitoryStudents: async (params = {}) =>
      apiCall("/attendance/dormitory-students", "GET", null, params),
    markBoarding: async (data) =>
      apiCall("/attendance/mark-boarding", "POST", data),
    isSchoolDay: async (params = {}) =>
      apiCall("/attendance/is-school-day", "GET", null, params),
    getPermissions: async (params = {}) =>
      apiCall("/attendance/permissions", "GET", null, params),
    getPermissionTypes: async (params = {}) =>
      apiCall("/attendance/permission-types", "GET", null, params),
    createPermission: async (data) =>
      apiCall("/attendance/permissions", "POST", data),
    updatePermission: async (id, data) =>
      apiCall(`/attendance/permissions/${id}`, "PUT", data),
    getStaffToday: async (params = {}) =>
      apiCall("/attendance/staff-today", "GET", null, params),
    markStaff: async (data) => apiCall("/attendance/mark-staff", "POST", data),
    getDutyTypes: async (params = {}) =>
      apiCall("/attendance/duty-types", "GET", null, params),
    getStaffReport: async (params = {}) =>
      apiCall("/attendance/staff-report", "GET", null, params),

    // Student attendance
    getStudentHistory: async (studentId, params) =>
      apiCall(
        `/attendance/student-history?student_id=${studentId}`,
        "GET",
        null,
        params,
      ),
    getStudentSummary: async (studentId, params) =>
      apiCall(
        `/attendance/student-summary?student_id=${studentId}`,
        "GET",
        null,
        params,
      ),
    getClassAttendance: async (classId, params) =>
      apiCall(
        `/attendance/class-attendance?class_id=${classId}`,
        "GET",
        null,
        params,
      ),
    getStudentPercentage: async (studentId, params) =>
      apiCall(
        `/attendance/student-percentage?student_id=${studentId}`,
        "GET",
        null,
        params,
      ),
    getChronicAbsentees: async (params) =>
      apiCall("/attendance/chronic-student-absentees", "GET", null, params),

    // Staff attendance
    getStaffHistory: async (staffId, params) =>
      apiCall(
        `/attendance/staff-history?staff_id=${staffId}`,
        "GET",
        null,
        params,
      ),
    getStaffSummary: async (staffId, params) =>
      apiCall(
        `/attendance/staff-summary?staff_id=${staffId}`,
        "GET",
        null,
        params,
      ),
    getDepartmentAttendance: async (departmentId, params) =>
      apiCall(
        `/attendance/department-attendance?department_id=${departmentId}`,
        "GET",
        null,
        params,
      ),
    getStaffPercentage: async (staffId, params) =>
      apiCall(
        `/attendance/staff-percentage?staff_id=${staffId}`,
        "GET",
        null,
        params,
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

  // Activities endpoints
  activities: {
    index: async () => apiCall("/activities/index", "GET"),

    // CRUD
    list: async (params = {}) => apiCall("/activities", "GET", null, params),
    get: async (id) => apiCall(`/activities/${id}`, "GET"),
    create: async (data) => apiCall("/activities", "POST", data),
    update: async (id, data) => apiCall(`/activities/${id}`, "PUT", data),
    delete: async (id) => apiCall(`/activities/${id}`, "DELETE"),

    // Statistics
    getSummary: async () => apiCall("/activities/statistics/get", "GET"),
    getUpcoming: async (limit = 10) =>
      apiCall("/activities/upcoming/list", "GET", null, { limit }),

    // Categories
    listCategories: async (params = {}) =>
      apiCall("/activities/categories/list", "GET", null, params),
    getCategory: async (id) =>
      apiCall(`/activities/categories/get/${id}`, "GET"),
    createCategory: async (data) =>
      apiCall("/activities/categories/create", "POST", data),
    updateCategory: async (id, data) =>
      apiCall(`/activities/categories/update/${id}`, "PUT", data),
    deleteCategory: async (id) =>
      apiCall(`/activities/categories/delete/${id}`, "DELETE"),

    // Participants
    listParticipants: async (params = {}) =>
      apiCall("/activities/participants/list", "GET", null, params),
    registerParticipant: async (data) =>
      apiCall("/activities/participants/register", "POST", data),
    updateParticipantStatus: async (id, data) =>
      apiCall(`/activities/participants/update-status/${id}`, "PUT", data),
    withdrawParticipant: async (id, reason) =>
      apiCall(`/activities/participants/withdraw/${id}`, "POST", { reason }),
    getStudentActivityHistory: async (studentId) =>
      apiCall(`/activities/participants/student-history/${studentId}`, "GET"),
    bulkRegisterParticipants: async (activityId, studentIds) =>
      apiCall("/activities/participants/bulk-register", "POST", {
        activity_id: activityId,
        student_ids: studentIds,
      }),

    // Resources
    listResources: async (params = {}) =>
      apiCall("/activities/resources/list", "GET", null, params),
    addResource: async (data) =>
      apiCall("/activities/resources/add", "POST", data),
    updateResource: async (id, data) =>
      apiCall(`/activities/resources/update/${id}`, "PUT", data),
    deleteResource: async (id) =>
      apiCall(`/activities/resources/delete/${id}`, "DELETE"),
    getActivityResources: async (activityId) =>
      apiCall(`/activities/resources/activity/${activityId}`, "GET"),

    // Schedules
    listSchedules: async (params = {}) =>
      apiCall("/activities/schedules/list", "GET", null, params),
    createSchedule: async (data) =>
      apiCall("/activities/schedules/create", "POST", data),
    updateSchedule: async (id, data) =>
      apiCall(`/activities/schedules/update/${id}`, "PUT", data),
    deleteSchedule: async (id) =>
      apiCall(`/activities/schedules/delete/${id}`, "DELETE"),

    // Workflows
    startRegistrationWorkflow: async (data) =>
      apiCall("/activities/workflow/registration/start", "POST", data),
    startPlanningWorkflow: async (data) =>
      apiCall("/activities/workflow/planning/start", "POST", data),
    startCompetitionWorkflow: async (data) =>
      apiCall("/activities/workflow/competition/start", "POST", data),
    startEvaluationWorkflow: async (data) =>
      apiCall("/activities/workflow/evaluation/start", "POST", data),
    getWorkflowStatus: async (workflowId) =>
      apiCall(`/activities/workflow/status/${workflowId}`, "GET"),
  },

  // Counseling endpoints
  counseling: {
    index: async () => apiCall("/counseling/index", "GET"),

    // Summary
    getSummary: async () => apiCall("/counseling/summary", "GET"),

    // CRUD
    list: async (params = {}) =>
      apiCall("/counseling/session", "GET", null, params),
    get: async (id) => apiCall(`/counseling/session/${id}`, "GET"),
    create: async (data) => apiCall("/counseling/session", "POST", data),
    update: async (id, data) =>
      apiCall(`/counseling/session/${id}`, "PUT", data),
    delete: async (id) => apiCall(`/counseling/session/${id}`, "DELETE"),

    // Aliases for backward compatibility
    getSessions: async (params = {}) =>
      apiCall("/counseling/session", "GET", null, params),
    saveSession: async (data) => {
      if (data.id || data.sessionId) {
        const id = data.id || data.sessionId;
        return apiCall(`/counseling/session/${id}`, "PUT", data);
      }
      return apiCall("/counseling/session", "POST", data);
    },
  },

  // Admission endpoints
  admission: {
    index: async () => apiCall("/admission/index", "GET"),
    // Get workflow queues by stage (for role-based views)
    getQueues: async () => apiCall("/admission/queues", "GET"),
    getStageMatrix: async () => apiCall("/admission/stage-matrix", "GET"),
    getPolicy: async () => apiCall("/admission/policy", "GET"),
    getPayments: async (applicationId) =>
      apiCall(`/admission/payments/${applicationId}`, "GET"),
    // Get single application details with workflow state
    getApplication: async (id) =>
      apiCall(`/admission/application/${id}`, "GET"),
    // Get admission statistics for dashboards
    getStats: async () => apiCall("/admission/stats", "GET"),
    // Get role-based notifications for dashboards
    getNotifications: async () => apiCall("/admission/notifications", "GET"),
    // Get classes for placement offer assignment
    getPlacementClasses: async () =>
      apiCall("/admission/placement-classes", "GET"),
    // Workflow stage methods
    submitApplication: async (data) =>
      apiCall("/admission/submit-application", "POST", data),
    uploadDocument: async (formData) =>
      apiCall(
        "/admission/upload-document",
        "POST",
        formData,
        {},
        { isFile: true },
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
    confirmEnrollment: async (data) =>
      apiCall("/admission/confirm-enrollment", "POST", data),
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
        { isFile: true },
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

    // Fee reminder SMS/WhatsApp
    sendFeeReminder: async (data) =>
      apiCall("/communications/fee-reminder", "POST", data),
    sendBulkFeeReminders: async (data) =>
      apiCall("/communications/fee-reminder-bulk", "POST", data),
    getTemplates: async () => apiCall("/communications/template", "GET"),
    saveTemplate: async (data) =>
      apiCall("/communications/template", "POST", data),
  },

  // Finance endpoints
  finance: {
    // Default to listing payments for dashboard/list views
    index: async () => apiCall("/finance?type=payments", "GET"),
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
    getBudgetSummary: async (departmentId) =>
      apiCall("/finance/department-budgets-summary", "GET", null, {
        department_id: departmentId,
      }),
    // Aliases used by budget_overview.js
    getDepartmentBudgetsSummary: async (params) =>
      apiCall("/finance/department-budgets-summary", "GET", null, params),
    getDepartmentBudgetsProposals: async (params) =>
      apiCall("/finance/department-budgets-proposals", "GET", null, params),
    proposeDepartmentBudget: async (data) =>
      apiCall("/finance/department-budgets-propose", "POST", data),
    approveDepartmentBudget: async (data) =>
      apiCall("/finance/department-budgets-approve", "POST", data),

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
        params,
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
    getBulkPayrollPreview: async (month, year) =>
      apiCall(
        `/finance/bulk-payroll-preview?month=${month}&year=${year}`,
        "GET",
      ),
    processBulkPayroll: async (data) =>
      apiCall("/finance/process-bulk-payroll", "POST", data),
    processPayrollWithDeductions: async (data) =>
      apiCall("/finance/process-payroll-with-deductions", "POST", data),
    getDetailedPayslip: async (payrollId) =>
      apiCall(`/finance/detailed-payslip?payroll_id=${payrollId}`, "GET"),
    getPayrollStats: async (month, year) =>
      apiCall(`/finance/payroll-stats?month=${month}&year=${year}`, "GET"),
    getPayrollList: async (filters) =>
      apiCall("/finance/payroll-list", "GET", null, filters),
    approvePayroll: async (payrollId, approvedBy = null) =>
      apiCall("/finance/approve-payroll", "POST", {
        payroll_id: payrollId,
        approved_by: approvedBy,
      }),
    markPayrollPaid: async (payrollId, paymentRef = "", paymentMode = "bank") =>
      apiCall("/finance/mark-payroll-paid", "POST", {
        payroll_id: payrollId,
        payment_reference: paymentRef,
        payment_mode: paymentMode,
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

    // Expenses approvals
    approveExpense: async (expenseId, notes = "") =>
      apiCall("/finance/expenses-approve", "POST", {
        expense_id: expenseId,
        notes,
      }),
    rejectExpense: async (expenseId, reason = "") =>
      apiCall("/finance/expenses-reject", "POST", {
        expense_id: expenseId,
        reason,
      }),

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
    updateAnnualStructure: async (data) =>
      apiCall("/finance/fees-update-annual-structure", "POST", data),
    deleteAnnualStructure: async (data) =>
      apiCall("/finance/fees-delete-annual-structure", "POST", data),
    listFeeTypes: async () => apiCall("/finance/fee-types-list", "GET"),
    listStudentTypes: async () => apiCall("/finance/student-types-list", "GET"),
    generateFeeInvoice: async (data) =>
      apiCall("/finance/fee-invoices-generate", "POST", data),
    generateFeeInvoicesBatch: async (data) =>
      apiCall("/finance/fee-invoices-generate-batch", "POST", data),
    getFeeInvoice: async (params) =>
      apiCall("/finance/fee-invoices-get", "GET", null, params),

    // Students
    getStudentPaymentStatusList: async (params = {}) =>
      apiCall("/finance/students/payment-status", "GET", null, params),
    getStudentPaymentHistory: async (studentId, params) =>
      apiCall(
        `/finance/students-payment-history?student_id=${studentId}`,
        "GET",
        null,
        params,
      ),
    getStudentFeeStatement: async (studentId, params = {}) =>
      apiCall(
        `/finance/students/fee-statement/${studentId}`,
        "GET",
        null,
        params,
      ),
    getStudentBalance: async (studentId) =>
      apiCall(`/finance/students/balance/${studentId}`, "GET"),

    // Reports
    generatePayrollReport: async (data) =>
      apiCall("/finance/reports-generate-payroll", "POST", data),
    compareYearlyCollections: async (params) =>
      apiCall(
        "/finance/reports-compare-yearly-collections",
        "GET",
        null,
        params,
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
        params,
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

    // ====================================
    // Uniform Sales Management
    // ====================================

    // Get all uniform items with size availability
    getUniformItems: async (params = {}) =>
      apiCall("/inventory/uniform-items", "GET", null, params),

    // Get size variants for a specific uniform item
    getUniformSizes: async (itemId) =>
      apiCall(`/inventory/uniform-sizes/${itemId}`, "GET"),

    // Register a new uniform sale
    registerUniformSale: async (data) =>
      apiCall("/inventory/uniform-sales", "POST", data),

    // Get uniform sales for a specific student
    getStudentUniformSales: async (studentId) =>
      apiCall(`/inventory/uniform-sales-by-student/${studentId}`, "GET"),

    // Get all uniform sales with filters
    listUniformSales: async (params = {}) =>
      apiCall("/inventory/uniform-sales-list", "GET", null, params),

    // Update uniform sale payment status
    updateUniformPayment: async (saleId, paymentStatus) =>
      apiCall(`/inventory/uniform-sales-payment/${saleId}`, "PUT", {
        payment_status: paymentStatus,
      }),

    // Get uniform sales dashboard metrics
    getUniformDashboard: async () =>
      apiCall("/inventory/uniform-dashboard", "GET"),

    // Get uniform payment summary
    getUniformPaymentSummary: async () =>
      apiCall("/inventory/uniform-payment-summary", "GET"),

    // Get student uniform size profile
    getStudentUniformProfile: async (studentId) =>
      apiCall(`/inventory/uniform-student-profile/${studentId}`, "GET"),

    // Update student uniform size profile
    updateStudentUniformProfile: async (studentId, data) =>
      apiCall(`/inventory/uniform-student-profile/${studentId}`, "PUT", data),

    // Restock uniform size
    restockUniformSize: async (data) =>
      apiCall("/inventory/uniform-restock", "POST", data),

    // Get low stock uniform items
    getLowStockUniforms: async () =>
      apiCall("/inventory/uniform-low-stock", "GET"),

    // Get uniform sales analytics/reports
    getUniformSalesReport: async (params = {}) =>
      apiCall("/inventory/uniform-sales-report", "GET", null, params),

    // Delete uniform sale (restores stock)
    deleteUniformSale: async (saleId) =>
      apiCall(`/inventory/uniform-sales/${saleId}`, "DELETE"),

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
    index: async (params = {}) => {
      const data = await apiCall("/staff/index", "GET", null, params);
      // Directory data: warm the staff IndexedDB cache so reloads render
      // instantly and revalidate in the background (network-first strategy).
      if (typeof DataStore !== "undefined" && data != null) {
        DataStore.set("staff", data, {
          ttl: DataStore.DEFAULT_TTL.REFERENCE,
          storeName: "staff_directory_cache",
        });
      }
      return data;
    },
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
    getAll: async (params = {}) => apiCall("/staff", "GET", null, params),

    // Assignments
    assignClass: async (data) => apiCall("/staff/assign-class", "POST", data),
    assignSubject: async (data) =>
      apiCall("/staff/assign-subject", "POST", data),
    getAssignments: async (idOrParams = null) => {
      if (idOrParams && typeof idOrParams === "object")
        return apiCall("/staff/assignments-get", "GET", idOrParams);
      return idOrParams
        ? apiCall(`/staff/assignments-get/${idOrParams}`, "GET")
        : apiCall("/staff/assignments-get", "GET");
    },
    getCurrentAssignments: async (staffId) =>
      apiCall(`/staff/assignments-current?staff_id=${staffId}`, "GET"),
    getWorkload: async (id = null) =>
      id
        ? apiCall(`/staff/workload-get/${id}`, "GET")
        : apiCall("/staff/workload-get", "GET"),
    initiateAssignment: async (data) =>
      apiCall("/staff/assignment-initiate", "POST", data),

    // Attendance
    getAttendance: async (id = null, params = {}) =>
      id
        ? apiCall(`/staff/attendance-get/${id}`, "GET")
        : apiCall("/staff/attendance-get", "GET", null, params),
    markAttendance: async (data) =>
      apiCall("/staff/attendance-mark", "POST", data),

    // Leaves
    listLeaves: async (params) =>
      apiCall("/staff/leaves-list", "GET", null, params),
    applyLeave: async (data) => apiCall("/staff/leaves-apply", "POST", data),
    updateLeaveStatus: async (leaveId, status) =>
      apiCall("/staff/leaves-update-status", "PUT", {
        leave_id: leaveId,
        status,
      }),
    initiateLeaveRequest: async (data) =>
      apiCall("/staff/leave-initiate-request", "POST", data),

    // Payroll
    getPayslip: async (staffId, params) =>
      apiCall(
        `/staff/payroll-payslip?staff_id=${staffId}`,
        "GET",
        null,
        params,
      ),
    listPayroll: async (params = {}) =>
      apiCall("/staff/payroll-list", "GET", null, params),
    getPayrollSummary: async (params = {}) =>
      apiCall("/staff/payroll-summary", "GET", null, params),
    getPayrollHistory: async (staffId, params) =>
      apiCall(
        `/staff/payroll-history?staff_id=${staffId}`,
        "GET",
        null,
        params,
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
        { isDownload: true },
      ),
    downloadPayslip: async (staffId, params) =>
      apiCall(
        `/staff/payroll-download-payslip?staff_id=${staffId}`,
        "GET",
        null,
        params,
        { isDownload: true },
      ),
    exportHistory: async (staffId, params) =>
      apiCall(
        `/staff/payroll-export-history?staff_id=${staffId}`,
        "GET",
        null,
        params,
        { isDownload: true },
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
        "GET",
      ),

    // Detailed Payslips
    generateDetailedPayslip: async (staffId, month, year) =>
      apiCall(
        `/staff/payroll-detailed-payslip?staff_id=${staffId}&month=${month}&year=${year}`,
        "GET",
      ),
    downloadDetailedPayslip: async (staffId, month, year) =>
      apiCall(
        `/staff/payroll-download-detailed-payslip?staff_id=${staffId}&month=${month}&year=${year}`,
        "GET",
        null,
        {},
        { isDownload: true },
      ),

    // Performance
    getPerformanceReviewHistory: async (staffId, params) =>
      apiCall(
        `/staff/performance-review-history?staff_id=${staffId}`,
        "GET",
        null,
        params,
      ),
    generatePerformanceReport: async (reviewId, params) =>
      apiCall(
        `/staff/performance-generate-report?review_id=${reviewId}`,
        "GET",
        null,
        params,
      ),
    getAcademicKPISummary: async (staffId, params) =>
      apiCall(
        `/staff/performance-academic-kpi-summary?staff_id=${staffId}`,
        "GET",
        null,
        params,
      ),

    // Canonical Staff-domain access and workflows
    getAccessContext: async () => apiCall("/staff/access-context", "GET"),
    getTeachers: async (params = {}) =>
      apiCall("/staff/teachers", "GET", null, params),
    getNonTeaching: async (params = {}) =>
      apiCall("/staff/non-teaching", "GET", null, params),
    getPayrollEligibility: async (staffId) =>
      apiCall(`/staff/payroll-eligibility/${staffId}`, "GET"),
    validatePayrollEligibility: async (payload) =>
      apiCall("/staff/payroll-eligibility-validate", "POST", payload),
    getIdCards: async (params = {}) =>
      apiCall("/staff/id-cards", "GET", null, params),
    generateIdCard: async (payload) =>
      apiCall("/staff/id-cards-generate", "POST", payload),
    generateBulkIdCards: async (payload) =>
      apiCall("/staff/id-cards-bulk-generate", "POST", payload),
    previewBulkIdCards: async (payload) =>
      apiCall("/staff/id-card/generate-bulk-pdf", "POST", payload),
    printSingleIdCard: async (payload) =>
      apiCall("/staff/id-card/print-single", "POST", payload),
    issueIdCard: async (payload) =>
      apiCall("/staff/id-cards-issue", "POST", payload),
    getLeaveTypes: async () => apiCall("/staff/leave-types", "GET"),
    getLeaveRequests: async (params = {}) =>
      apiCall("/staff/leave-requests", "GET", null, params),
    createLeaveRequest: async (payload) =>
      apiCall("/staff/leave-requests", "POST", payload),
    updateLeaveRequestStatus: async (id, payload) =>
      apiCall(`/staff/leave-requests-status/${id}`, "PUT", payload),
    getAvailableRoles: async () => apiCall("/staff/available-roles", "GET"),
    getRoleAssignments: async (staffId) =>
      apiCall("/staff/role-assignments", "GET", null, { staff_id: staffId }),
    assignStaffRole: async (payload) =>
      apiCall("/staff/role-assignments", "POST", payload),
    revokeStaffRole: async (staffId, roleId) =>
      apiCall(`/staff/role-assignments/${roleId}`, "DELETE", {
        staff_id: staffId,
      }),
    
    // Additional staff endpoints for new UI controllers
    getPerformanceReviews: async (params = {}) =>
      apiCall("/staff/performance-reviews", "GET", null, params),
    
    // Staff lifecycle and onboarding endpoints
    getOnboarding: async (params = {}) =>
      apiCall("/staff/onboarding", "GET", null, params),
    createOnboarding: async (data) =>
      apiCall("/staff/onboarding", "POST", data),
    getLifecycle: async (params = {}) =>
      apiCall("/staff/lifecycle", "GET", null, params),
    createLifecycleAction: async (data) =>
      apiCall("/staff/lifecycle", "POST", data),
    getAppointments: async (params = {}) =>
      apiCall("/staff/appointments", "GET", null, params),
    createAppointment: async (data) =>
      apiCall("/staff/appointments", "POST", data),
    importExistingStaff: async (data) =>
      apiCall("/staff/import-existing", "POST", data),

    // Legacy support
    list: async (params = {}) => apiCall("/staff", "GET", null, params),
    assignRole: async (id, roleData) =>
      apiCall("/staff/assign-class", "POST", { staff_id: id, ...roleData }),
    updatePermissions: async (id, permissions) =>
      apiCall(`/staff/${id}`, "PUT", { permissions }),

    // Contracts
    listContracts: async (params = {}) =>
      apiCall("/staff/contracts-list", "GET", null, params),
    getContract: async (id) => apiCall(`/staff/contracts-get/${id}`, "GET"),
    createContract: async (data) =>
      apiCall("/staff/contracts-create", "POST", data),
    updateContract: async (id, data) =>
      apiCall(`/staff/contracts-update/${id}`, "PUT", data),

    // Media (photos & documents) — routes to POST /staff/upload/{id}
    uploadPhoto: async (staffId, file, extra = {}) => {
      const formData = createFormData(
        {
          type: "photo",
          description: extra.description || "",
          tags: extra.tags || "",
        },
        { file },
      );
      return apiCall(
        `/staff/upload-photo/${staffId}`,
        "POST",
        formData,
        {},
        { isFile: true },
      );
    },
    uploadDocument: async (staffId, file, extra = {}) => {
      const formData = createFormData(
        {
          type: "document",
          description: extra.description || "",
          tags: extra.tags || "",
        },
        { file },
      );
      return apiCall(
        `/staff/upload-document/${staffId}`,
        "POST",
        formData,
        {},
        { isFile: true },
      );
    },
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

  // Boarding/Dormitory endpoints
  boarding: {
    // Dormitories
    getDormitories: async () => apiCall("/boarding/dormitories", "GET"),
    getDormitory: async (id) => apiCall(`/boarding/dormitories/${id}`, "GET"),
    createDormitory: async (data) =>
      apiCall("/boarding/dormitories", "POST", data),
    updateDormitory: async (id, data) =>
      apiCall(`/boarding/dormitories/${id}`, "PUT", data),
    deleteDormitory: async (id) =>
      apiCall(`/boarding/dormitories/${id}`, "DELETE"),

    // Beds
    getBeds: async (dormId = null) =>
      dormId
        ? apiCall(`/boarding/beds?dormitory_id=${dormId}`, "GET")
        : apiCall("/boarding/beds", "GET"),
    assignBed: async (data) => apiCall("/boarding/beds/assign", "POST", data),
    unassignBed: async (bedId) =>
      apiCall(`/boarding/beds/unassign/${bedId}`, "PUT"),

    // Roll Call
    getRollCalls: async (params = {}) =>
      apiCall("/boarding/roll-call", "GET", null, params),
    submitRollCall: async (data) =>
      apiCall("/boarding/roll-call", "POST", data),
    getRollCallHistory: async (dormId, params = {}) =>
      apiCall(
        `/boarding/roll-call/history?dormitory_id=${dormId}`,
        "GET",
        null,
        params,
      ),

    // Permissions & Exeats
    getExeats: async (params = {}) =>
      apiCall("/boarding/exeats", "GET", null, params),
    requestExeat: async (data) => apiCall("/boarding/exeats", "POST", data),
    approveExeat: async (id) =>
      apiCall(`/boarding/exeats/approve/${id}`, "PUT"),
    rejectExeat: async (id, reason) =>
      apiCall(`/boarding/exeats/reject/${id}`, "PUT", { reason }),

    // Food Store
    getFoodStore: async () => apiCall("/boarding/food-store", "GET"),
    addFoodItem: async (data) => apiCall("/boarding/food-store", "POST", data),
    updateFoodItem: async (id, data) =>
      apiCall(`/boarding/food-store/${id}`, "PUT", data),
    recordConsumption: async (data) =>
      apiCall("/boarding/food-store/consume", "POST", data),

    // Menu Planning
    getMenus: async (params = {}) =>
      apiCall("/boarding/menus", "GET", null, params),
    createMenu: async (data) => apiCall("/boarding/menus", "POST", data),
    updateMenu: async (id, data) =>
      apiCall(`/boarding/menus/${id}`, "PUT", data),
    deleteMenu: async (id) => apiCall(`/boarding/menus/${id}`, "DELETE"),

    // Chapel Services
    getChapelServices: async (params = {}) =>
      apiCall("/boarding/chapel-services", "GET", null, params),
    createChapelService: async (data) =>
      apiCall("/boarding/chapel-services", "POST", data),
    updateChapelService: async (id, data) =>
      apiCall(`/boarding/chapel-services/${id}`, "PUT", data),

    // Statistics
    getStats: async () => apiCall("/boarding/stats", "GET"),
    getOccupancy: async () => apiCall("/boarding/occupancy", "GET"),
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
    getTimetable: async (params = {}) => {
      const qs = new URLSearchParams(params).toString();
      return apiCall(
        qs ? `/schedules/timetable-get?${qs}` : "/schedules/timetable-get",
        "GET",
      );
    },
    createTimetable: async (data) =>
      apiCall("/schedules/timetable-create", "POST", data),
    updateTimetable: async (id, data) =>
      apiCall(`/schedules/timetable-update/${id}`, "PUT", data),
    deleteTimetable: async (data) =>
      apiCall("/schedules/timetable-delete", "POST", data),
    deleteTimetableById: async (id) =>
      apiCall(`/schedules/timetable-delete/${id}`, "DELETE"),
    checkTimetableConflicts: async () =>
      apiCall("/schedules/timetable-check-conflicts", "GET"),
    reportTimetableConflict: async (data) =>
      apiCall("/schedules/timetable-report-conflict", "POST", data),
    getTimeSlots: async () => apiCall("/schedules/timetable-time-slots", "GET"),

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
        "GET",
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
        "GET",
      ),
    listWorkflows: async (params) =>
      apiCall("/schedules/list-scheduling-workflows", "GET", null, params),

    // Legacy support
    getClassSchedule: async (classId) =>
      apiCall(`/schedules/timetable-get?class_id=${classId}`, "GET"),
    updateSchedule: async (data) =>
      apiCall("/schedules/timetable-create", "POST", data),
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

    // Unmatched M-Pesa payments (for reconciliation)
    getUnmatchedMpesa: async (params = {}) =>
      apiCall("/payments/unmatched-mpesa", "GET", null, params),

    // Reconcile M-Pesa payment (with optional student_id to allocate to fees)
    reconcileMpesa: async (
      mpesaId,
      bankStatementRef = "",
      notes = "",
      studentId = null,
    ) =>
      apiCall("/payments/reconcile-mpesa", "POST", {
        mpesa_id: mpesaId,
        bank_statement_ref: bankStatementRef,
        notes: notes,
        student_id: studentId,
      }),

    // Get M-Pesa reconciliation history
    getMpesaReconcileHistory: async (mpesaId) =>
      apiCall(
        `/payments/mpesa-reconcile-history?mpesa_id=${encodeURIComponent(mpesaId)}`,
        "GET",
      ),
  },

  // Accounts endpoints (bank accounts, transactions)
  accounts: {
    // Get all bank accounts
    getBankAccounts: async (params = {}) =>
      apiCall("/accounts/bank-accounts", "GET", null, params),

    // Get transactions for a specific bank account (or all if no bankId)
    getBankTransactions: async (bankId = null, params = {}) => {
      const url = bankId
        ? `/accounts/bank-transactions?bank_id=${encodeURIComponent(bankId)}`
        : `/accounts/bank-transactions`;
      return apiCall(url, "GET", null, params);
    },
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
    getRole: async (id) =>
      apiCall(`/system/roles/${encodeURIComponent(id)}`, "GET"),
    createRole: async (data) => apiCall("/system/roles", "POST", data),
    updateRole: async (id, data) =>
      apiCall("/system/roles", "PUT", { id, ...data }),
    deleteRole: async (id) =>
      apiCall(`/system/roles/${encodeURIComponent(id)}`, "DELETE"),
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
      apiCall(`/system/permissions/${encodeURIComponent(id)}`, "PUT", data),
    deletePermission: async (id) =>
      apiCall(`/system/permissions/${encodeURIComponent(id)}`, "DELETE"),

    // Role-Permission Assignment (System Admin)
    getRolePermissions: async (roleId) =>
      apiCall(`/system/role-permissions?role_id=${roleId}`, "GET"),
    assignPermissionToRole: async (roleId, permissionId) =>
      apiCall("/system/role-permissions", "POST", {
        role_id: roleId,
        permission_ids: [permissionId],
      }),
    revokePermissionFromRole: async (roleId, permissionId) =>
      apiCall(
        `/system/role-permissions/${encodeURIComponent(permissionId)}`,
        "DELETE",
        null,
        { role_id: roleId },
      ),

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
    getAccountStatuses: async (params = {}) => apiCall("/system/account-status", "GET", null, params),
    updateAccountStatus: async (userId, data) => apiCall("/system/account-status", "PUT", { user_id: userId, ...data }),
    getAuthenticationLogs: async (params = {}) => apiCall("/system/authentication-logs", "GET", null, params),
    getFailedLogins: async (params = {}) => apiCall("/system/failed-login-attempts", "GET", null, params),
    getActiveSessions: async (params = {}) => apiCall("/system/active-sessions", "GET", null, params),
    revokeSession: async (sessionId) => apiCall("/system/active-sessions-revoke", "POST", { session_id: sessionId }),
    getTokens: async (params = {}) => apiCall("/system/tokens", "GET", null, params),
    revokeToken: async (tokenId, tokenType) => apiCall("/system/tokens-revoke", "POST", { token_id: tokenId, token_type: tokenType }),
    getActivityAuditLogs: async (params = {}) => apiCall("/system/activity-audit-logs", "GET", null, params),
    getErrorLogs: async (params = {}) => apiCall("/system/error-logs", "GET", null, params),
    getApiMetrics: async (params = {}) => apiCall("/system/api-metrics", "GET", null, params),
    getDiagnostics: async () => apiCall("/system/diagnostics", "GET"),
    getRateLimiting: async () => apiCall("/system/rate-limiting", "GET"),
    getBackgroundJobs: async (params = {}) => apiCall("/system/background-jobs", "GET", null, params),
    getJobInspector: async (params = {}) => apiCall("/system/job-inspector", "GET", null, params),
    getSecurityIncidents: async (params = {}) => apiCall("/system/security-incidents", "GET", null, params),
    getPermissionChanges: async (params = {}) => apiCall("/system/permission-changes", "GET", null, params),
    getPolicyViolations: async (params = {}) => apiCall("/system/policy-violations", "GET", null, params),
    getBackups: async (params = {}) => apiCall("/system/backups", "GET", null, params),
    createBackup: async (data = {}) => apiCall("/system/backups", "POST", data),
    deleteBackup: async (id) => apiCall("/system/backups", "DELETE", { id }),
    getMigrations: async (params = {}) => apiCall("/system/migrations", "GET", null, params),
    runMigration: async (data) => apiCall("/system/migrations", "POST", data),
    getFeatureFlags: async () => apiCall("/system/feature-flags", "GET"),
    updateFeatureFlags: async (data) => apiCall("/system/feature-flags", "PUT", data),
    getModuleEnablement: async () => apiCall("/system/module-enablement", "GET"),
    updateModuleEnablement: async (data) => apiCall("/system/module-enablement", "PUT", data),
    getDataRetention: async () => apiCall("/system/data-retention", "GET"),
    updateDataRetention: async (data) => apiCall("/system/data-retention", "PUT", data),
    getDomainIsolation: async () => apiCall("/system/domain-isolation", "GET"),
    updateDomainIsolation: async (data) => apiCall("/system/domain-isolation", "PUT", data),
    getTimeBoundAccess: async () => apiCall("/system/time-bound-access", "GET"),
    updateTimeBoundAccess: async (data) => apiCall("/system/time-bound-access", "PUT", data),
    getRouteAccessRules: async () => apiCall("/system/route-access-rules", "GET"),
    createRouteAccessRule: async (data) => apiCall("/system/route-access-rules", "POST", data),
    updateRouteAccessRule: async (id, data) => apiCall("/system/route-access-rules", "PUT", { id, ...data }),
    deleteRouteAccessRule: async (id) => apiCall("/system/route-access-rules", "DELETE", { id }),
    getPermissionPolicies: async () => apiCall("/system/permission-policies", "GET"),
    createPermissionPolicy: async (data) => apiCall("/system/permission-policies", "POST", data),
    updatePermissionPolicy: async (id, data) => apiCall("/system/permission-policies", "PUT", { id, ...data }),
    deletePermissionPolicy: async (id) => apiCall("/system/permission-policies", "DELETE", { id }),
    getWebhookRegistry: async () => apiCall("/system/webhook-registry", "GET"),
    createWebhook: async (data) => apiCall("/system/webhook-registry", "POST", data),
    updateWebhook: async (id, data) => apiCall("/system/webhook-registry", "PUT", { id, ...data }),
    deleteWebhook: async (id) => apiCall("/system/webhook-registry", "DELETE", { id }),
    getRoleNavigation: async () => apiCall("/system/role-navigation", "GET"),
    getResourcePermissions: async (params = {}) =>
      apiCall("/system/resource-permissions", "GET", null, params),
    getIpLists: async (params = {}) =>
      apiCall("/system/ip-lists", "GET", null, params),
    createIpRule: async (data) =>
      apiCall("/system/ip-lists", "POST", data),
    updateIpRule: async (id, data) =>
      apiCall(`/system/ip-lists/${encodeURIComponent(id)}`, "PUT", data),
    deleteIpRule: async (id) =>
      apiCall(`/system/ip-lists/${encodeURIComponent(id)}`, "DELETE"),
  },

  // System Config endpoints (match SystemConfigController)
  systemconfig: {
    authorizeRoute: async (route, roleIds = null) => {
      const payload = { route };
      if (Array.isArray(roleIds) && roleIds.length > 0) {
        payload.role_ids = roleIds;
      }
      return apiCall("/systemconfig/authorize", "POST", payload);
    },
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
      apiCall(
        "/auth/forgot-password",
        "POST",
        { email },
        {},
        { checkPermission: false },
      ),
    verify: async (token) =>
      apiCall(
        "/auth/reset-password",
        "GET",
        null,
        { token },
        { checkPermission: false },
      ),
    complete: async (token, newPassword) =>
      apiCall(
        "/auth/reset-password",
        "POST",
        {
          token,
          new_password: newPassword,
        },
        {},
        { checkPermission: false },
      ),
  },

  // Dashboard endpoints — one canonical namespace for all role dashboards.
  dashboard: {
    // System Administrator (role 2): infrastructure only, no School Domain data.
    getAuthEvents: async () =>
      apiCall("/dashboard/system-admin/auth-events", "GET"),
    getActiveSessions: async () =>
      apiCall("/dashboard/system-admin/active-sessions", "GET"),
    getSystemUptime: async () =>
      apiCall("/dashboard/system-admin/uptime", "GET"),
    getSystemHealthErrors: async () =>
      apiCall("/dashboard/system-admin/health-errors", "GET"),
    getSystemHealthWarnings: async () =>
      apiCall("/dashboard/system-admin/health-warnings", "GET"),
    getAPIRequestLoad: async () =>
      apiCall("/dashboard/system-admin/api-load", "GET"),

    // Shared dashboard statistics retained from the superseded duplicate block.
    getStudentStats: async () => apiCall("/students/stats", "GET"),
    getTodayAttendance: async () => apiCall("/attendance/today", "GET"),
    getTeachingStats: async () => apiCall("/staff/stats", "GET"),
    getFeesCollected: async () => apiCall("/payments/stats", "GET"),
    getWeeklyLessons: async () => apiCall("/schedules/weekly", "GET"),
    getCollectionTrends: async () =>
      apiCall("/payments/collection-trends", "GET"),
    getPendingApprovals: async () =>
      apiCall("/system/pending-approvals", "GET"),
    getActivities: async () => apiCall("/activities/list", "GET"),
    getPendingAdmissions: async () =>
      apiCall("/admissions/pending", "GET"),
    getMyClassAttendance: async () =>
      apiCall("/attendance/my-class", "GET"),
    getMyClassAssessments: async () =>
      apiCall("/assessments/my-results", "GET"),
    getMyLessonPlan: async () =>
      apiCall("/schedules/my-lessons", "GET"),
    getFeeStatusByStudent: async () =>
      apiCall("/payments/fee-status", "GET"),
    getMonthlyFinancialReport: async () =>
      apiCall("/finance/monthly-report", "GET"),
    getPayrollStatus: async () =>
      apiCall("/finance/payroll-status", "GET"),
    getInventoryStockStatus: async () =>
      apiCall("/inventory/stock-status", "GET"),
    getLowStockAlerts: async () =>
      apiCall("/inventory/low-stock-alerts", "GET"),
    getPendingRequisitions: async () =>
      apiCall("/inventory/requisitions-pending", "GET"),

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

    // School Accountant endpoints (role 10)
    getAccountantFinancial: async (params = {}) => {
      return await apiCall(
        "/dashboard/accountant/financial",
        "GET",
        null,
        params,
      );
    },

    getAccountantPayments: async (params = {}) => {
      return await apiCall(
        "/dashboard/accountant/payments",
        "GET",
        null,
        params,
      );
    },

    /**
     * Get accountant alerts (fee defaulters, reconciliation issues, etc.)
     * @param {Object} params - Optional filters (severity, limit, etc.)
     */
    getAccountantAlerts: async (params = {}) => {
      return await apiCall("/alerts", "GET", null, params);
    },

    /**
     * Get unmatched M-Pesa payments for reconciliation
     * @param {Object} params - Optional filters (date_from, date_to, etc.)
     */
    getAccountantUnmatchedPayments: async (params = {}) => {
      return await apiCall("/payments/unmatched-mpesa", "GET", null, params);
    },

    /**
     * Get bank accounts with balances
     * @param {Object} params - Optional filters
     */
    getAccountantBankAccounts: async (params = {}) => {
      return await apiCall("/accounts/bank-accounts", "GET", null, params);
    },

    /**
     * Get full accountant dashboard data in a single API call (optimized)
     * Returns: financial, payments, alerts, bankAccounts, unmatchedPayments
     */
    getAccountantFull: async (params = {}) => {
      return await apiCall("/dashboard/accountant/full", "GET", null, params);
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

    // =========================================================================
    // SCHOOL ADMIN DASHBOARD ENDPOINTS (TIER 3: Operational Management)
    // =========================================================================

    /**
     * Get full dashboard data in a single call (optimized for initial load)
     * Returns: cards, charts, tables, timestamp
     */
    getSchoolAdminFull: async () => {
      // Some deployments do not expose the aggregate endpoint. Once a 404 is
      // confirmed, stop retrying it for this page session and let the dashboard
      // use its individual, real endpoints instead.
      if (
        sessionStorage.getItem("school_admin_full_endpoint_missing") === "1"
      ) {
        const error = new Error(
          "School admin aggregate endpoint is unavailable.",
        );
        error.code = "ENDPOINT_UNAVAILABLE";
        throw error;
      }
      try {
        return await apiCall("/dashboard/school-admin/full", "GET");
      } catch (error) {
        if (
          Number(error?.status || error?.code) === 404 ||
          /404/.test(String(error?.message || ""))
        ) {
          sessionStorage.setItem("school_admin_full_endpoint_missing", "1");
          error.code = "ENDPOINT_UNAVAILABLE";
        }
        throw error;
      }
    },

    /**
     * Get active students statistics
     * Returns: total_students, active_classes, male, female, class_distribution
     */
    getSchoolAdminStudents: async () => {
      return await apiCall("/dashboard/school-admin/students", "GET");
    },

    /**
     * Get staff statistics including activities and leaves
     * Returns: teaching, activities, leaves
     */
    getSchoolAdminStaff: async () => {
      return await apiCall("/dashboard/school-admin/staff", "GET");
    },

    /**
     * Get daily attendance statistics and trend
     * Returns: today (with percentage), trend (weekly data)
     */
    getSchoolAdminAttendance: async () => {
      return await apiCall("/dashboard/school-admin/attendance", "GET");
    },

    /**
     * Get admission pipeline statistics
     * Returns: pending, approved, rejected, total
     */
    getSchoolAdminAdmissions: async () => {
      return await apiCall("/dashboard/school-admin/admissions", "GET");
    },

    /**
     * Get timetables and today's schedule
     * Returns: stats (active_timetables, classes_per_week), today (schedule array)
     */
    getSchoolAdminTimetables: async () => {
      return await apiCall("/dashboard/school-admin/timetables", "GET");
    },

    /**
     * Get announcements statistics
     * Returns: count, active, total_views, recipients
     */
    getSchoolAdminAnnouncements: async () => {
      return await apiCall("/dashboard/school-admin/announcements", "GET");
    },

    /**
     * Get all pending items requiring attention
     * Returns: items array (type, description, count, priority, action)
     */
    getSchoolAdminPendingItems: async () => {
      return await apiCall("/dashboard/school-admin/pending-items", "GET");
    },

    /**
     * Get staff directory with optional search
     * @param {string} search - Optional search term
     */
    getSchoolAdminStaffDirectory: async (search = "") => {
      const params = search ? `?search=${encodeURIComponent(search)}` : "";
      return await apiCall(
        `/dashboard/school-admin/staff-directory${params}`,
        "GET",
      );
    },

    /**
     * Get class distribution chart data
     * @param {string} filter - Filter by form (all, form1, form2, etc.)
     */
    getSchoolAdminClassDistribution: async (filter = "all") => {
      return await apiCall(
        `/dashboard/school-admin/class-distribution?filter=${filter}`,
        "GET",
      );
    },

    /**
     * Get weekly attendance trend chart data
     * @param {number} weeks - Number of weeks (default 4)
     */
    getSchoolAdminAttendanceTrend: async (weeks = 4) => {
      return await apiCall(
        `/dashboard/school-admin/attendance-trend?weeks=${weeks}`,
        "GET",
      );
    },

    /**
     * Get system status (limited view for School Admin)
     * Returns: status, uptime, db_healthy
     */
    getSchoolAdminSystemStatus: async () => {
      return await apiCall("/dashboard/school-admin/system-status", "GET");
    },

    // ============= HEADTEACHER DASHBOARD (ROLE 5) =============

    /**
     * Get full headteacher dashboard data in a single call
     * Returns: cards, charts, tables, timestamp
     */
    getHeadteacherFull: async () => {
      return await apiCall("/dashboard/headteacher/full", "GET");
    },

    /**
     * Get Deputy Academic dashboard data
     */
    getDeputyAcademicFull: async () => {
      return await apiCall("/dashboard/deputy-academic/full", "GET");
    },

    /**
     * Get Deputy Discipline dashboard data
     */
    getDeputyDisciplineFull: async () => {
      return await apiCall("/dashboard/deputy-discipline/full", "GET");
    },

    /**
     * Get headteacher overview statistics
     */
    getHeadteacherOverview: async () => {
      return await apiCall("/dashboard/headteacher/overview", "GET");
    },

    /**
     * Get today's attendance statistics
     */
    getHeadteacherAttendance: async () => {
      return await apiCall("/dashboard/headteacher/attendance-today", "GET");
    },

    /**
     * Get class schedules
     */
    getHeadteacherSchedules: async () => {
      return await apiCall("/dashboard/headteacher/schedules", "GET");
    },

    /**
     * Get admission statistics
     */
    getHeadteacherAdmissions: async () => {
      return await apiCall("/dashboard/headteacher/admissions", "GET");
    },

    /**
     * Get discipline statistics
     */
    getHeadteacherDiscipline: async () => {
      return await apiCall("/dashboard/headteacher/discipline", "GET");
    },

    /**
     * Get communications statistics
     */
    getHeadteacherCommunications: async () => {
      return await apiCall("/dashboard/headteacher/communications", "GET");
    },

    /**
     * Get assessments statistics
     */
    getHeadteacherAssessments: async () => {
      return await apiCall("/dashboard/headteacher/assessments", "GET");
    },

    /**
     * Get performance statistics
     */
    getHeadteacherPerformance: async () => {
      return await apiCall("/dashboard/headteacher/performance", "GET");
    },

    /**
     * Get pending admissions table data
     */
    getHeadteacherPendingAdmissions: async () => {
      return await apiCall("/dashboard/headteacher/pending-admissions", "GET");
    },

    /**
     * Get discipline cases table data
     */
    getHeadteacherDisciplineCases: async () => {
      return await apiCall("/dashboard/headteacher/discipline-cases", "GET");
    },

    // ============= CLASS TEACHER DASHBOARD (ROLE 7) =============

    /**
     * Get full class teacher dashboard data in a single call
     * Returns: cards, charts, tables, timestamp
     */
    getClassTeacherFull: async () => {
      return await apiCall("/dashboard/class-teacher/full", "GET");
    },

    /**
     * Get my class statistics
     */
    getClassTeacherMyClass: async () => {
      return await apiCall("/dashboard/class-teacher/my-class", "GET");
    },

    /**
     * Get my class attendance today
     */
    getClassTeacherAttendance: async () => {
      return await apiCall("/dashboard/class-teacher/attendance", "GET");
    },

    /**
     * Get my class assessments
     */
    getClassTeacherAssessments: async () => {
      return await apiCall("/dashboard/class-teacher/assessments", "GET");
    },

    /**
     * Get my class lesson plans
     */
    getClassTeacherLessonPlans: async () => {
      return await apiCall("/dashboard/class-teacher/lesson-plans", "GET");
    },

    /**
     * Get my class students table
     */
    getClassTeacherStudents: async () => {
      return await apiCall("/dashboard/class-teacher/students", "GET");
    },

    // ============= SUBJECT TEACHER DASHBOARD (ROLE 8) =============

    /**
     * Get full subject teacher dashboard data in a single call
     * Returns: cards, charts, tables, timestamp
     */
    getSubjectTeacherFull: async () => {
      return await apiCall("/dashboard/subject-teacher/full", "GET");
    },

    /**
     * Get subject teacher classes/sections
     */
    getSubjectTeacherClasses: async () => {
      return await apiCall("/dashboard/subject-teacher/classes", "GET");
    },

    /**
     * Get subject teacher assessments
     */
    getSubjectTeacherAssessments: async () => {
      return await apiCall("/dashboard/subject-teacher/assessments", "GET");
    },

    /**
     * Get subject teacher students
     */
    getSubjectTeacherStudents: async () => {
      return await apiCall("/dashboard/subject-teacher/students", "GET");
    },

    // ============= INTERN TEACHER DASHBOARD (ROLE 9) =============

    /**
     * Get full intern teacher dashboard data in a single call
     * Returns: cards, charts, tables, timestamp
     */
    getInternTeacherFull: async () => {
      return await apiCall("/dashboard/intern-teacher/full", "GET");
    },

    /**
     * Get intern teacher assigned classes
     */
    getInternTeacherClasses: async () => {
      return await apiCall("/dashboard/intern-teacher/classes", "GET");
    },

    /**
     * Get intern teacher observations
     */
    getInternTeacherObservations: async () => {
      return await apiCall("/dashboard/intern-teacher/observations", "GET");
    },
  },

  staffLifecycle: {
    list: async (params = {}) =>
      apiCall("/stafflifecycle", "GET", null, params),
    get: async (id) => apiCall(`/stafflifecycle/${id}`, "GET"),
    referenceData: async () => apiCall("/stafflifecycle/reference-data", "GET"),
    createAction: async (data) =>
      apiCall("/stafflifecycle/action", "POST", data),
    approve: async (id, comment = "") =>
      apiCall(`/stafflifecycle/approve/${id}`, "PUT", { comment }),
    reject: async (id, comment = "") =>
      apiCall(`/stafflifecycle/reject/${id}`, "PUT", { comment }),
    cancel: async (id, reason) =>
      apiCall(`/stafflifecycle/cancel/${id}`, "PUT", { reason }),
  },

  staffMigration: {
    referenceData: async () =>
      apiCall("/staff-migration/reference-data", "GET"),
    batches: async (params = {}) =>
      apiCall("/staff-migration/batches", "GET", null, params),
    batch: async (id) => apiCall(`/staff-migration/batch/${id}`, "GET"),
    templateUrl: () => `${API_BASE_URL}/staff-migration/template`,
    templateXlsxUrl: () => `${API_BASE_URL}/staff-migration/template-xlsx`,
    downloadTemplate: async () =>
      apiCall("/staff-migration/template", "GET", null, {}, {
        isDownload: true,
        filename: "existing_staff_migration_template.csv",
      }),
    downloadTemplateXlsx: async () =>
      apiCall("/staff-migration/template-xlsx", "GET", null, {}, {
        isDownload: true,
        filename: "existing_staff_migration_template.xlsx",
      }),
    stage: async (formData) =>
      apiCall("/staff-migration/stage", "POST", formData, {}, { isFile: true }),
    commit: async (batchId) =>
      apiCall("/staff-migration/commit", "POST", { batch_id: batchId }),
    rollback: async (batchId) =>
      apiCall("/staff-migration/rollback", "POST", { batch_id: batchId }),
    resendInvitation: async (userId, baseUrl = window.location.origin) =>
      apiCall("/staff-migration/resend-invitation", "POST", {
        user_id: userId,
        base_url: baseUrl,
      }),
    onboarding: async () => apiCall("/staff-migration/onboarding", "GET"),
    completeProfile: async (payload) =>
      apiCall("/staff-migration/profile", "PUT", payload),
  },
};

// Expose apiCall globally as callAPI — many page controllers use this name
window.callAPI = apiCall;
