/**
 * Centralized Session Manager
 * 
 * Single source of truth for authentication state across the application.
 * Replaces fragmented AuthContext token access patterns.
 * 
 * Responsibilities:
 * - Session state management
 * - Token lifecycle (access + refresh)
 * - Cross-tab synchronization
 * - Session event system
 * - Security (CSRF tokens, session rotation)
 * - Migration from legacy token storage
 */

const SessionManager = (function() {
  'use strict';

  // Session state
  let sessionState = {
    authenticated: false,
    user: null,
    roles: [],
    permissions: new Set(),
    sessionId: null,
    csrfToken: null,
    expiresAt: null,
    lastRefreshed: null
  };

  // Event subscribers
  const subscribers = new Map();
  
  // Cross-tab communication
  let broadcastChannel = null;
  
  // Migration tracking
  const MIGRATION_VERSION_KEY = 'kingsway_session_migration_version';
  const CURRENT_MIGRATION_VERSION = 2;
  
  // Configuration
  const config = {
    sessionCheckInterval: 60000, // 1 minute
    tokenRefreshThreshold: 300000, // 5 minutes before expiry
    sessionWarningThreshold: 300000, // 5 minutes before expiry
  };

  // Device fingerprinting
  let deviceFingerprint = null;
  const DEVICE_FINGERPRINT_KEY = 'kingsway_device_fingerprint';
  
  // Token storage mode: 'cookie' (HttpOnly refresh-token cookie) or 'localStorage'
  // (fallback). This label only describes where the *refresh* token lives; the
  // access token is always kept in web-storage (see getToken/setToken above).
  let tokenStorageMode = 'auto'; // auto-detect
  const TOKEN_STORAGE_MODE_KEY = 'kingsway_token_storage_mode';

  /**
   * Build a path rooted at the app base URL. Every API/asset URL must go through
   * this helper so we never hardcode a path that ignores the deployment base
   * (e.g. `/Kingsway`). Without it, calls hit the server root and 404.
   */
  function apiPath(path) {
    const base = (window.APP_BASE || '').replace(/\/+$/, '');
    return base + path;
  }

  /**
   * Initialize Session Manager
   */
  async function initialize() {
    console.log('[SessionManager] Initializing...');
    
    // Detect token storage mode
    tokenStorageMode = detectTokenStorageMode();
    console.log('[SessionManager] Token storage mode:', tokenStorageMode);
    
    // Generate device fingerprint
    deviceFingerprint = generateDeviceFingerprint();
    
    // Initialize cross-tab communication
    initBroadcastChannel();
    
    // Check for migration
    await checkMigration();
    
    // Try to restore session from storage
    await restoreSession();
    
    // Start session monitoring
    startSessionMonitoring();
    
    // Listen for storage events (fallback for cross-tab)
    initStorageEventListener();
    
    console.log('[SessionManager] Initialized', {
      authenticated: sessionState.authenticated,
      user: sessionState.user?.username,
      roles: sessionState.roles.length,
      deviceFingerprint: deviceFingerprint,
      tokenStorageMode: tokenStorageMode
    });
    
    return sessionState;
  }

  /**
   * Detect token storage mode
   * Tries HttpOnly cookies first, falls back to localStorage if cookies are blocked
   */
  function detectTokenStorageMode() {
    // Check if user has manually set preference
    const savedMode = localStorage.getItem(TOKEN_STORAGE_MODE_KEY);
    if (savedMode === 'cookie' || savedMode === 'localStorage') {
      return savedMode;
    }
    
    // Auto-detect: try to set a test cookie
    try {
      document.cookie = 'kingsway_test_cookie=test; SameSite=Strict; path=/';
      const cookiesEnabled = document.cookie.indexOf('kingsway_test_cookie') !== -1;
      
      // Clean up test cookie
      document.cookie = 'kingsway_test_cookie=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/';
      
      if (cookiesEnabled) {
        localStorage.setItem(TOKEN_STORAGE_MODE_KEY, 'cookie');
        return 'cookie';
      }
    } catch (e) {
      console.warn('[SessionManager] Cookie detection failed:', e);
    }
    
    // Fallback to localStorage
    localStorage.setItem(TOKEN_STORAGE_MODE_KEY, 'localStorage');
    return 'localStorage';
  }

  /**
   * Get token from storage
   */
  function getToken() {
    // The access token ALWAYS lives in web-storage (AuthContext), regardless of
    // storage mode. "Cookie mode" only describes where the *refresh* token lives
    // (HttpOnly cookie set by the server). The server authenticates protected
    // routes via the `Authorization: Bearer` header, so the client must always
    // be able to read and attach the access token. Returning null here causes
    // every protected request to 401 ("Missing Authorization header").
    if (typeof AuthContext !== 'undefined') {
      return AuthContext.getToken();
    }
    return localStorage.getItem('auth_token');
  }

  /**
   * Set token in storage
   */
  function setToken(token, refreshToken) {
    // Always persist the access token in web-storage so the client can attach it
    // to the Authorization header. In both modes the refresh token is also kept
    // by the server in an HttpOnly cookie; we additionally store any JS-provided
    // refresh token for resilience (e.g. on login responses that include one).
    if (typeof AuthContext !== 'undefined') {
      AuthContext.setTokens(token, refreshToken);
    } else {
      localStorage.setItem('auth_token', token);
      if (refreshToken) {
        localStorage.setItem('refresh_token', refreshToken);
      }
    }
  }

  /**
   * Clear token from storage
   */
  function clearToken() {
    if (tokenStorageMode === 'cookie') {
      // Token is cleared via HttpOnly cookie by backend
      if (typeof AuthContext !== 'undefined') {
        AuthContext.setRefreshToken(null);
      }
    } else {
      // Fallback to localStorage
      if (typeof AuthContext !== 'undefined') {
        AuthContext.clearUser();
      } else {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('refresh_token');
      }
    }
  }

  /**
   * Generate device fingerprint
   * Creates a stable identifier for the current device/browser
   */
  function generateDeviceFingerprint() {
    // Try to get existing fingerprint from storage
    const existing = localStorage.getItem(DEVICE_FINGERPRINT_KEY);
    if (existing) {
      return existing;
    }

    // Generate new fingerprint based on browser characteristics
    const components = [
      navigator.userAgent,
      navigator.language,
      navigator.platform,
      screen.width + 'x' + screen.height,
      new Date().getTimezoneOffset(),
      // Add more factors for production use if needed
    ];

    const fingerprint = components.join('|');
    
    // Store for future use
    localStorage.setItem(DEVICE_FINGERPRINT_KEY, fingerprint);
    
    return fingerprint;
  }

  /**
   * Get device fingerprint
   */
  function getDeviceFingerprint() {
    return deviceFingerprint || generateDeviceFingerprint();
  }

  /**
   * Initialize BroadcastChannel for cross-tab communication
   */
  function initBroadcastChannel() {
    try {
      if (typeof BroadcastChannel !== 'undefined') {
        broadcastChannel = new BroadcastChannel('kingsway-app');
        broadcastChannel.onmessage = handleBroadcastMessage;
        console.log('[SessionManager] BroadcastChannel initialized');
      }
    } catch (e) {
      console.warn('[SessionManager] BroadcastChannel not available', e);
    }
  }

  /**
   * Handle broadcast messages from other tabs
   */
  function handleBroadcastMessage(event) {
    const { type, data } = event.data;
    console.log('[SessionManager] Received broadcast:', type, data);
    
    switch (type) {
      case 'SESSION_CHANGED':
        handleSessionChange(data);
        break;
      case 'LOGGED_OUT':
        handleRemoteLogout();
        break;
      case 'PERMISSIONS_UPDATED':
        handlePermissionsUpdate(data);
        break;
      case 'CACHE_INVALIDATED':
        handleCacheInvalidation(data);
        break;
      default:
        console.warn('[SessionManager] Unknown broadcast type:', type);
    }
  }

  /**
   * Initialize storage event listener (fallback)
   */
  function initStorageEventListener() {
    window.addEventListener('storage', (event) => {
      if (event.key === 'kingsway_session_event') {
        try {
          const data = JSON.parse(event.newValue);
          handleBroadcastMessage(data);
        } catch (e) {
          console.warn('[SessionManager] Failed to parse storage event', e);
        }
      }
    });
  }

  /**
   * Broadcast event to other tabs
   */
  function broadcast(type, data) {
    const message = { type, data, timestamp: Date.now() };
    
    // Try BroadcastChannel first
    if (broadcastChannel) {
      try {
        broadcastChannel.postMessage(message);
      } catch (e) {
        console.warn('[SessionManager] BroadcastChannel failed', e);
      }
    }
    
    // Fallback to storage event
    try {
      localStorage.setItem('kingsway_session_event', JSON.stringify(message));
      localStorage.removeItem('kingsway_session_event');
    } catch (e) {
      console.warn('[SessionManager] Storage event failed', e);
    }
  }

  /**
   * Check for and perform migration
   */
  async function checkMigration() {
    const currentVersion = parseInt(localStorage.getItem(MIGRATION_VERSION_KEY) || '0');
    
    if (currentVersion < CURRENT_MIGRATION_VERSION) {
      console.log('[SessionManager] Performing migration from version', currentVersion, 'to', CURRENT_MIGRATION_VERSION);
      await performMigration(currentVersion);
      localStorage.setItem(MIGRATION_VERSION_KEY, CURRENT_MIGRATION_VERSION.toString());
    }
  }

  /**
   * Perform migration from legacy token storage
   */
  async function performMigration(fromVersion) {
    console.log('[SessionManager] Migrating from version:', fromVersion);
    
    // Migration from version 0 or 1: consolidate token storage
    if (fromVersion < 2) {
      await migrateLegacyTokens();
    }
    
    console.log('[SessionManager] Migration complete');
  }

  /**
   * Migrate legacy tokens to centralized storage
   */
  async function migrateLegacyTokens() {
    const legacyKeys = ['token', 'auth_token', 'access_token', 'jwt', 'user_token'];
    let foundToken = null;
    let storageSource = null;
    
    // Check localStorage
    for (const key of legacyKeys) {
      const token = localStorage.getItem(key);
      if (token) {
        foundToken = token;
        storageSource = 'localStorage';
        localStorage.removeItem(key);
        break;
      }
    }
    
    // Check sessionStorage if not found
    if (!foundToken) {
      for (const key of legacyKeys) {
        const token = sessionStorage.getItem(key);
        if (token) {
          foundToken = token;
          storageSource = 'sessionStorage';
          sessionStorage.removeItem(key);
          break;
        }
      }
    }
    
    if (foundToken) {
      console.log('[SessionManager] Migrated token from', storageSource);
      // Validate token with backend
      try {
        const response = await fetch(apiPath('/api/session/validate-token'), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${foundToken}`
          },
          credentials: 'include'
        });
        
        if (response.ok) {
          const result = await response.json();
          if (result.success) {
            // Token is valid, store in session state
            sessionState.authenticated = true;
            sessionState.user = result.data.user;
            sessionState.roles = result.data.roles || [];
            sessionState.permissions = new Set(result.data.permissions || []);
            sessionState.csrfToken = result.data.csrf_token;
            sessionState.expiresAt = result.data.expires_at;
            sessionState.lastRefreshed = Date.now();
            
            // Store in active storage (using existing AuthContext pattern for now)
            if (typeof AuthContext !== 'undefined') {
              AuthContext.setTokens(foundToken, result.data.refresh_token);
            }
            
            console.log('[SessionManager] Legacy token validated and migrated');
          }
        }
      } catch (e) {
        console.warn('[SessionManager] Legacy token validation failed', e);
      }
    }
  }

  /**
   * Restore session from storage or backend
   */
  async function restoreSession() {
    // Try to get session from backend first (most authoritative)
    try {
      const response = await fetch(apiPath('/api/session'), {
        method: 'GET',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json'
        }
      });
      
      if (response.ok) {
        const result = await response.json();
        if (result.success && result.data.authenticated) {
          setSessionState(result.data);
          console.log('[SessionManager] Session restored from backend');
          return;
        }
      }
    } catch (e) {
      console.warn('[SessionManager] Backend session check failed', e);
    }
    
    // Fallback to AuthContext (existing implementation)
    if (typeof AuthContext !== 'undefined' && AuthContext.isAuthenticated()) {
      sessionState.authenticated = true;
      sessionState.user = AuthContext.getUser();
      sessionState.roles = AuthContext.getRoles() || [];
      sessionState.permissions = AuthContext.getPermissions ? new Set(AuthContext.getPermissions()) : new Set();
      sessionState.lastRefreshed = Date.now();
      console.log('[SessionManager] Session restored from AuthContext');
    }
  }

  /**
   * Set session state from data
   */
  function setSessionState(data) {
    sessionState.authenticated = data.authenticated;
    sessionState.user = data.user;
    sessionState.roles = data.roles || [];
    sessionState.permissions = new Set(data.permissions || []);
    sessionState.sessionId = data.session_id;
    sessionState.csrfToken = data.csrf_token;
    sessionState.expiresAt = data.expires_at;
    sessionState.lastRefreshed = Date.now();
    
    // Update AuthContext for compatibility
    if (typeof AuthContext !== 'undefined') {
      // Keep AuthContext in sync during transition
    }
  }

  /**
   * Start session monitoring
   */
  function startSessionMonitoring() {
    setInterval(async () => {
      if (sessionState.authenticated) {
        await checkSessionExpiry();
      }
    }, config.sessionCheckInterval);
  }

  /**
   * Check session expiry and refresh if needed
   */
  async function checkSessionExpiry() {
    if (!sessionState.expiresAt) return;
    
    const now = Date.now();
    const timeUntilExpiry = sessionState.expiresAt - now;
    
    // Warn user if session is about to expire
    if (timeUntilExpiry < config.sessionWarningThreshold && timeUntilExpiry > 0) {
      emit('SESSION_EXPIRING', { timeUntilExpiry });
    }
    
    // Refresh if approaching expiry
    if (timeUntilExpiry < config.tokenRefreshThreshold) {
      await refreshSession();
    }
    
    // Logout if expired
    if (timeUntilExpiry <= 0) {
      await logout();
    }
  }

  /**
   * Refresh session
   */
  async function refreshSession() {
    console.log('[SessionManager] Refreshing session...');
    
    try {
      const response = await fetch(apiPath('/api/session/refresh'), {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': sessionState.csrfToken
        }
      });
      
      if (response.ok) {
        const result = await response.json();
        if (result.success) {
          setSessionState(result.data);
          emit('SESSION_REFRESHED', result.data);
          broadcast('SESSION_CHANGED', { authenticated: true });
          console.log('[SessionManager] Session refreshed');
          return true;
        }
      }
      
      console.warn('[SessionManager] Session refresh failed');
      return false;
    } catch (e) {
      console.error('[SessionManager] Session refresh error', e);
      return false;
    }
  }

  /**
   * Public API: Check if authenticated
   */
  function isAuthenticated() {
    return sessionState.authenticated;
  }

  /**
   * Public API: Get session state
   */
  function getSessionState() {
    return { ...sessionState, permissions: Array.from(sessionState.permissions) };
  }

  /**
   * Public API: Get current user
   */
  function getCurrentUser() {
    return sessionState.user;
  }

  /**
   * Public API: Get roles
   */
  function getRoles() {
    return sessionState.roles;
  }

  /**
   * Public API: Get permissions
   */
  function getPermissions() {
    return Array.from(sessionState.permissions);
  }

  /**
   * Public API: Check permission
   */
  function hasPermission(permission) {
    return sessionState.permissions.has(permission);
  }

  /**
   * Public API: Check any permission
   */
  function hasAnyPermission(permissions) {
    return permissions.some(perm => sessionState.permissions.has(perm));
  }

  /**
   * Public API: Check all permissions
   */
  function hasAllPermissions(permissions) {
    return permissions.every(perm => sessionState.permissions.has(perm));
  }

  /**
   * Public API: Get CSRF token
   */
  function getCsrfToken() {
    return sessionState.csrfToken;
  }

  /**
   * Public API: Login (sets session state)
   */
  async function login(credentials) {
    console.log('[SessionManager] Logging in...');
    
    try {
      const response = await fetch('/api/auth/login', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(credentials)
      });
      
      const result = await response.json();
      
      if (result.success) {
        setSessionState({
          authenticated: true,
          user: result.data.user,
          roles: result.data.user?.roles || [],
          permissions: result.data.delegated_permissions || [],
          csrf_token: result.data.csrf_token,
          expires_at: Date.now() + (result.data.token_expires_in * 1000)
        });
        
        // Store tokens using AuthContext for compatibility
        if (typeof AuthContext !== 'undefined') {
          AuthContext.setTokens(result.data.token, result.data.refresh_token);
          AuthContext.setUser(result.data.user, result.data, credentials.remember_me);
        }
        
        emit('SESSION_LOGIN', result.data);
        broadcast('SESSION_CHANGED', { authenticated: true });
        
        console.log('[SessionManager] Login successful');
        return { success: true, data: result.data };
      } else {
        console.error('[SessionManager] Login failed:', result.message);
        return { success: false, message: result.message };
      }
    } catch (e) {
      console.error('[SessionManager] Login error:', e);
      return { success: false, message: 'Login failed: ' + e.message };
    }
  }

  /**
   * Public API: Logout
   */
  async function logout() {
    console.log('[SessionManager] Logging out...');
    
    try {
      // Call backend logout
      await fetch('/api/auth/logout', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': sessionState.csrfToken
        }
      });
    } catch (e) {
      console.warn('[SessionManager] Backend logout failed', e);
    }
    
    // Clear session state
    sessionState = {
      authenticated: false,
      user: null,
      roles: [],
      permissions: new Set(),
      sessionId: null,
      csrfToken: null,
      expiresAt: null,
      lastRefreshed: null
    };
    
    // Clear AuthContext
    if (typeof AuthContext !== 'undefined') {
      AuthContext.clearUser();
    }
    
    // Clear migration flags
    localStorage.removeItem(MIGRATION_VERSION_KEY);
    
    emit('SESSION_LOGOUT');
    broadcast('LOGGED_OUT', {});
    
    console.log('[SessionManager] Logout complete');
  }

  /**
   * Handle remote logout from another tab
   */
  function handleRemoteLogout() {
    console.log('[SessionManager] Remote logout received');
    
    sessionState = {
      authenticated: false,
      user: null,
      roles: [],
      permissions: new Set(),
      sessionId: null,
      csrfToken: null,
      expiresAt: null,
      lastRefreshed: null
    };
    
    if (typeof AuthContext !== 'undefined') {
      AuthContext.clearUser();
    }
    
    emit('SESSION_LOGOUT');
    
    // Redirect to login
    window.location.href = '/index.php';
  }

  /**
   * Handle session change from another tab
   */
  function handleSessionChange(data) {
    console.log('[SessionManager] Session change received', data);
    
    if (data.authenticated && !sessionState.authenticated) {
      // Another tab logged in, refresh our session
      restoreSession();
    } else if (!data.authenticated && sessionState.authenticated) {
      // Another tab logged out, we should too
      handleRemoteLogout();
    }
  }

  /**
   * Handle permissions update from another tab
   */
  function handlePermissionsUpdate(data) {
    console.log('[SessionManager] Permissions update received', data);
    
    if (data.permissions) {
      sessionState.permissions = new Set(data.permissions);
      emit('PERMISSIONS_UPDATED', data.permissions);
    }
  }

  /**
   * Handle cache invalidation from another tab
   */
  function handleCacheInvalidation(data) {
    console.log('[SessionManager] Cache invalidation received', data);
    emit('CACHE_INVALIDATED', data);
  }

  /**
   * Check session validity
   */
  async function checkSession() {
    if (!sessionState.authenticated) {
      return false;
    }

    try {
      const response = await fetch(apiPath('/api/session/validate-token'), {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': sessionState.csrfToken
        }
      });

      if (response.ok) {
        const result = await response.json();
        if (result.success) {
          return true;
        }
      }

      // Session invalid, clear it
      console.warn('[SessionManager] Session invalid, clearing');
      await logout();
      return false;
    } catch (error) {
      console.error('[SessionManager] Session check failed:', error);
      return false;
    }
  }

  /**
   * Subscribe to session events
   */
  function subscribe(event, callback) {
    if (!subscribers.has(event)) {
      subscribers.set(event, new Set());
    }
    subscribers.get(event).add(callback);
    
    // Return unsubscribe function
    return () => {
      const callbacks = subscribers.get(event);
      if (callbacks) {
        callbacks.delete(callback);
      }
    };
  }

  /**
   * Emit event to subscribers
   */
  function emit(event, data) {
    const callbacks = subscribers.get(event);
    if (callbacks) {
      callbacks.forEach(callback => {
        try {
          callback(data);
        } catch (e) {
          console.error('[SessionManager] Event callback error', e);
        }
      });
    }
  }

  /**
   * Get token storage mode
   */
  function getTokenStorageMode() {
    return tokenStorageMode;
  }

  /**
   * Set token storage mode manually
   */
  function setTokenStorageMode(mode) {
    if (mode === 'cookie' || mode === 'localStorage') {
      tokenStorageMode = mode;
      localStorage.setItem(TOKEN_STORAGE_MODE_KEY, mode);
      console.log('[SessionManager] Token storage mode set to:', mode);
    } else {
      console.warn('[SessionManager] Invalid token storage mode:', mode);
    }
  }

  // Public API
  return {
    initialize,
    isAuthenticated,
    getSessionState,
    getCurrentUser,
    getRoles,
    getPermissions,
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,
    getCsrfToken,
    getDeviceFingerprint,
    getTokenStorageMode,
    setTokenStorageMode,
    checkSession,
    login,
    logout,
    refreshSession,
    subscribe,
    broadcast,
    broadcastCacheInvalidation: (keys) => broadcast('CACHE_INVALIDATED', { keys: Array.isArray(keys) ? keys : [keys] })
  };

})();

// Export for use in other modules
if (typeof window !== 'undefined') {
  window.SessionManager = SessionManager;
}
