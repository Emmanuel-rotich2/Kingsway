/**
 * Dashboard Router - Permission-Aware Dashboard Routing
 *
 * Purpose: Detect user role and route to appropriate dashboard
 * Principle: Each role sees ONLY its role-specific dashboard
 *
 * Architecture:
 * 1. Get current user role(s) from session/auth context
 * 2. Fetch role-to-dashboard mapping from PHP DashboardAPI (canonical source)
 * 3. Load appropriate dashboard controller
 * 4. Handle multiple roles (show primary, offer switcher)
 * 5. Graceful fallback for unrecognized roles
 */

const DashboardRouter = {
  /**
   * Cached role-to-dashboard config from PHP DashboardAPI
   * Fetched on init and cached in sessionStorage with version check
   */
  _configCache: null,
  _configVersion: null,
  _initPromise: null,

  /**
   * Role hierarchy for multi-role users (higher = more privileges)
   * Used to determine "primary" role when user has multiple roles
   */
  ROLE_HIERARCHY: {
    2: 100, // System Administrator
    3: 90,  // Director/Owner
    4: 80,  // School Administrator
    5: 70,  // Headteacher
    6: 60,  // Deputy Head Academic
    63: 55, // Deputy Head Discipline
    7: 50,  // Class Teacher
    8: 45,  // Subject Teacher
    9: 40,  // Intern/Student Teacher
    10: 35, // School Accountant
    11: 34, // Accountant (M-Pesa)
    12: 33, // Accountant (Assets)
    13: 32, // Accountant (Vendors)
    14: 30, // Store Manager / Accountant (Controls)
    16: 25, // Catering Manager/Cook Lead
    18: 20, // Matron/Housemother
    21: 18, // HOD Talent Development
    23: 15, // Driver
    24: 12, // School Counselor/Chaplain
    32: 10, // Support Staff
    33: 10,
    34: 10,
    64: 10,
  },

  /**
   * Initialize dashboard router - fetch config from PHP API
   * Uses sessionStorage cache with version check for performance
   */
  async init() {
    if (this._initPromise) {
      return this._initPromise;
    }

    this._initPromise = (async () => {
      try {
        const config = await this._fetchConfig();
        this._configCache = config;
        this._configVersion = config.version || Date.now();
        console.log('[DashboardRouter] Config loaded from PHP API', config);
      } catch (error) {
        console.warn('[DashboardRouter] Failed to fetch config from API, using fallback', error);
        this._configCache = this._getFallbackConfig();
        this._configVersion = 'fallback';
      }
    })();

    return this._initPromise;
  },

  /**
   * Fetch dashboard config from PHP DashboardAPI
   * Uses sessionStorage cache with version check
   */
  async _fetchConfig() {
    const cacheKey = 'dashboard_router_config';
    const versionKey = 'dashboard_router_config_version';

    // Check sessionStorage cache first
    const cachedConfig = sessionStorage.getItem(cacheKey);
    const cachedVersion = sessionStorage.getItem(versionKey);

    if (cachedConfig && cachedVersion) {
      try {
        // Verify version with server (lightweight check)
        const versionResponse = await fetch('/api/dashboard?action=config', {
          method: 'HEAD',
          headers: {
            'Accept': 'application/json',
          },
          credentials: 'include',
        });

        // If server version matches cache, use cached config
        const serverVersion = versionResponse.headers.get('X-Config-Version');
        if (serverVersion && serverVersion === cachedVersion) {
          console.log('[DashboardRouter] Using cached config');
          return JSON.parse(cachedConfig);
        }
      } catch (e) {
        // Cache validation failed, fetch fresh
        console.warn('[DashboardRouter] Cache validation failed, fetching fresh config');
      }
    }

    // Fetch fresh config from API
    const response = await fetch('/api/dashboard?action=config', {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'include',
    });

    if (!response.ok) {
      throw new Error(`Dashboard config fetch failed: ${response.status}`);
    }

    const result = await response.json();

    if (!result.success) {
      throw new Error(result.message || 'Failed to fetch dashboard config');
    }

    const config = result.data;
    config.version = result.version || Date.now();

    // Cache in sessionStorage
    sessionStorage.setItem(cacheKey, JSON.stringify(config));
    sessionStorage.setItem(versionKey, config.version);

    return config;
  },

  /**
   * Fallback hardcoded config if API fails
   * Mirrors PHP DashboardRouter::ROLE_DASHBOARDS as fallback
   */
  _getFallbackConfig() {
    return {
      role_dashboards: {
        2: "system_administrator_dashboard",
        3: "director_owner_dashboard",
        4: "school_administrative_officer_dashboard",
        5: "headteacher_dashboard",
        6: "deputy_head_academic_dashboard",
        7: "class_teacher_dashboard",
        8: "subject_teacher_dashboard",
        9: "intern_student_teacher_dashboard",
        10: "school_accountant_dashboard",
        14: "store_manager_dashboard",
        16: "catering_manager_cook_lead_dashboard",
        18: "matron_housemother_dashboard",
        21: "hod_talent_development_dashboard",
        23: "driver_dashboard",
        24: "school_counselor_chaplain_dashboard",
        32: "support_staff_dashboard",
        33: "support_staff_dashboard",
        34: "support_staff_dashboard",
        63: "deputy_head_discipline_dashboard",
        64: "support_staff_dashboard",
      },
      role_name_map: {
        2: "System Administrator",
        3: "Director/Owner",
        4: "School Administrator",
        5: "Headteacher",
        6: "Deputy Head Academic",
        7: "Class Teacher",
        8: "Subject Teacher",
        9: "Intern/Student Teacher",
        10: "School Accountant",
        14: "Store Manager",
        16: "Catering Manager/Cook Lead",
        18: "Matron/Housemother",
        21: "HOD Talent Development",
        23: "Driver",
        24: "School Counselor/Chaplain",
        32: "Support Staff",
        33: "Support Staff",
        34: "Support Staff",
        63: "Deputy Head Discipline",
        64: "Support Staff",
      },
      default_dashboard: "headteacher_dashboard",
      version: 'fallback-' + Date.now(),
    };
  },

  /**
   * Get dashboard key for a role ID
   * Uses PHP config as canonical source
   */
  getDashboardForRole(roleId) {
    if (!this._configCache) {
      console.warn('[DashboardRouter] Config not loaded, using fallback');
      this._configCache = this._getFallbackConfig();
    }

    const dashboards = this._configCache.role_dashboards || {};
    return dashboards[roleId] || this._configCache.default_dashboard || 'headteacher_dashboard';
  },

  /**
   * Get role name for a role ID
   */
  getRoleName(roleId) {
    if (!this._configCache) {
      return 'Unknown Role';
    }
    return this._configCache.role_name_map?.[roleId] || `Role ${roleId}`;
  },

  /**
   * Get current user's role IDs from AuthContext
   * Returns array of role IDs (multi-role support)
   */
  getCurrentUserRoles() {
    if (window.AuthContext && typeof window.AuthContext.getRoles === 'function') {
      const roles = window.AuthContext.getRoles();
      if (Array.isArray(roles)) {
        return roles.map(r => (typeof r === 'object' ? r.id : parseInt(r, 10))).filter(id => !isNaN(id));
      }
    }

    // Fallback: parse from localStorage user_data
    try {
      const userData = JSON.parse(localStorage.getItem('user_data') || sessionStorage.getItem('user_data') || '{}');
      if (userData.role_ids && Array.isArray(userData.role_ids)) {
        return userData.role_ids.map(id => parseInt(id, 10)).filter(id => !isNaN(id));
      }
      if (userData.roles && Array.isArray(userData.roles)) {
        return userData.roles.map(r => (typeof r === 'object' ? r.id : parseInt(r, 10))).filter(id => !isNaN(id));
      }
      if (userData.role_id) {
        return [parseInt(userData.role_id, 10)];
      }
      if (userData.role) {
        return [parseInt(userData.role, 10)];
      }
    } catch (e) {
      console.warn('[DashboardRouter] Failed to parse user roles from storage', e);
    }

    return [];
  },

  /**
   * Get primary role for multi-role user
   * Uses ROLE_HIERARCHY to pick highest priority role
   */
  getPrimaryRole() {
    const roles = this.getCurrentUserRoles();
    if (roles.length === 0) return null;
    if (roles.length === 1) return roles[0];

    // Sort by hierarchy (higher = more privileged)
    return roles.sort((a, b) => (this.ROLE_HIERARCHY[b] || 0) - (this.ROLE_HIERARCHY[a] || 0))[0];
  },

  /**
   * Load dashboard script dynamically
   * Returns promise that resolves when controller is available
   */
  loadDashboardScript(dashboardKey) {
    // Convert dashboard key to JS file name
    // e.g., "class_teacher_dashboard" -> "class_teacher_dashboard.js"
    const fileName = `${dashboardKey}.js`;
    const scriptUrl = `js/dashboards/${fileName}`;

    // Check if already loaded
    if (document.querySelector(`script[src*="${fileName}"]`)) {
      return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = scriptUrl;
      script.type = 'module';
      script.onload = () => resolve();
      script.onerror = () => reject(new Error(`Failed to load dashboard script: ${fileName}`));
      document.head.appendChild(script);
    });
  },

  /**
   * Route to appropriate dashboard for current user
   * Called from js/index.js after auth initialization
   */
  async routeToDashboard() {
    await this.init();

    const primaryRole = this.getPrimaryRole();

    if (!primaryRole) {
      console.warn('[DashboardRouter] No role found for user, using default dashboard');
      const defaultDashboard = this._configCache?.default_dashboard || 'headteacher_dashboard';
      await this.loadDashboardScript(defaultDashboard);
      return { dashboardKey: defaultDashboard, roleId: null };
    }

    const dashboardKey = this.getDashboardForRole(primaryRole);

    console.log(`[DashboardRouter] Routing role ${primaryRole} (${this.getRoleName(primaryRole)}) -> ${dashboardKey}`);

    try {
      await this.loadDashboardScript(dashboardKey);
      return { dashboardKey, roleId: primaryRole };
    } catch (error) {
      console.error('[DashboardRouter] Failed to load dashboard:', error);
      // Fallback to default
      const defaultDashboard = this._configCache?.default_dashboard || 'headteacher_dashboard';
      await this.loadDashboardScript(defaultDashboard);
      return { dashboardKey: defaultDashboard, roleId: primaryRole };
    }
  },

  /**
   * Add role switcher UI for multi-role users
   * Creates dropdown in navbar to switch between role dashboards
   */
  addRoleSwitcher() {
    const roles = this.getCurrentUserRoles();
    if (roles.length <= 1) return; // No switcher needed for single role

    const navbar = document.querySelector('.navbar-nav.ms-auto') || document.querySelector('.navbar .ms-auto');
    if (!navbar) return;

    const switcherHtml = `
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="roleSwitcher" role="button" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fas fa-user-tag me-1"></i> ${this.getRoleName(roles[0])}
        </a>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="roleSwitcher">
          ${roles.map(roleId => `
            <li>
              <a class="dropdown-item role-switch-item" href="#" data-role-id="${roleId}" data-dashboard="${this.getDashboardForRole(roleId)}">
                ${this.getRoleName(roleId)} <span class="badge bg-secondary ms-1">${this.getDashboardForRole(roleId)}</span>
              </a>
            </li>
          `).join('')}
        </ul>
      </li>
    `;

    navbar.insertAdjacentHTML('beforeend', switcherHtml);

    // Handle role switch clicks
    document.querySelectorAll('.role-switch-item').forEach(item => {
      item.addEventListener('click', async (e) => {
        e.preventDefault();
        const roleId = parseInt(e.currentTarget.dataset.roleId, 10);
        const dashboardKey = e.currentTarget.dataset.dashboard;

        // Store selected role in sessionStorage for session
        sessionStorage.setItem('selected_role_id', roleId.toString());

        // Reload page to trigger new dashboard load
        window.location.reload();
      });
    });
  },

  /**
   * Get selected role override from sessionStorage
   * Used by role switcher
   */
  getSelectedRoleOverride() {
    const override = sessionStorage.getItem('selected_role_id');
    return override ? parseInt(override, 10) : null;
  },

  /**
   * Clear role override (on logout)
   */
  clearRoleOverride() {
    sessionStorage.removeItem('selected_role_id');
  },

  /**
   * Force refresh config from server (e.g., after role assignment changes)
   */
  async refreshConfig() {
    this._configCache = null;
    this._configVersion = null;
    this._initPromise = null;
    await this.init();
  },
};

// Export for module usage
export default DashboardRouter;

// Also attach to window for backward compatibility with inline scripts
window.DashboardRouter = DashboardRouter;