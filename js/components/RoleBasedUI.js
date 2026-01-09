/**
 * RoleBasedUI - Comprehensive Role & Permission Based UI Controller
 *
 * Provides declarative and programmatic ways to show/hide UI elements based on:
 * - User permissions (data-permission="permission_code")
 * - User roles (data-role="role1,role2")
 * - Role exclusions (data-role-exclude="role1,role2")
 * - Permission combinations (data-permission-any="perm1,perm2", data-permission-all="perm1,perm2")
 *
 * Also provides utilities for:
 * - Filtering table actions by role
 * - Segmenting dashboard cards
 * - Conditional rendering of forms/buttons
 *
 * Usage:
 * 1. Add data attributes to HTML elements
 * 2. Call RoleBasedUI.apply() on page load
 * 3. For dynamic content, use RoleBasedUI.applyTo(container)
 */

const RoleBasedUI = (() => {
  // Role definitions for quick reference (role name -> role ID)
  const ROLE_IDS = {
    admin: 1,
    system_administrator: 2,
    director: 3,
    principal: 3,
    school_administrator: 4,
    headteacher: 5,
    deputy_head_academic: 6,
    deputy_head_discipline: 7,
    class_teacher: 8,
    subject_teacher: 9,
    intern: 10,
    accountant: 11,
    bursar: 11,
    registrar: 12,
    secretary: 13,
    inventory_manager: 14,
    store_manager: 14,
    hr_manager: 15,
    cateress: 16,
    cook: 16,
    driver: 17,
    boarding_master: 18,
    matron: 18,
    security: 19,
    groundsman: 20,
    librarian: 21,
    lab_technician: 22,
    counselor: 23,
    chaplain: 24,
    nurse: 25,
    parent: 26,
    student: 27,
  };

  // Role categories for grouping
  const ROLE_CATEGORIES = {
    academic_leadership: [
      "headteacher",
      "deputy_head_academic",
      "deputy_head_discipline",
      "director",
      "principal",
    ],
    teachers: ["class_teacher", "subject_teacher", "intern"],
    finance: ["accountant", "bursar", "director"],
    administration: [
      "school_administrator",
      "registrar",
      "secretary",
      "hr_manager",
    ],
    support_staff: [
      "cateress",
      "cook",
      "driver",
      "security",
      "groundsman",
      "librarian",
      "lab_technician",
    ],
    boarding: ["boarding_master", "matron"],
    pastoral: ["counselor", "chaplain", "nurse"],
    inventory: ["inventory_manager", "store_manager"],
    external: ["parent", "student"],
  };

  // Action definitions by module for table segmentation
  const MODULE_ACTIONS = {
    students: {
      view: { permission: "students_view", roles: ["*"] },
      edit: {
        permission: "students_edit",
        roles: [
          "headteacher",
          "deputy_head_academic",
          "registrar",
          "school_administrator",
          "admin",
        ],
      },
      delete: {
        permission: "students_delete",
        roles: ["headteacher", "admin"],
      },
      promote: {
        permission: "students_promote",
        roles: ["headteacher", "deputy_head_academic"],
      },
      enroll: {
        permission: "students_create",
        roles: ["registrar", "headteacher", "admin"],
      },
      transfer: {
        permission: "students_transfer",
        roles: ["headteacher", "admin"],
      },
      print_id: {
        permission: "students_print",
        roles: ["registrar", "secretary", "admin"],
      },
      view_fees: {
        permission: "fees_view",
        roles: ["accountant", "bursar", "director", "admin"],
      },
    },
    finance: {
      view: {
        permission: "finance_view",
        roles: ["accountant", "bursar", "director", "admin"],
      },
      create: { permission: "finance_create", roles: ["accountant", "bursar"] },
      approve: { permission: "finance_approve", roles: ["director", "admin"] },
      edit: { permission: "finance_edit", roles: ["accountant", "admin"] },
      delete: { permission: "finance_delete", roles: ["admin"] },
      export: {
        permission: "finance_export",
        roles: ["accountant", "bursar", "director", "admin"],
      },
    },
    admissions: {
      view: {
        permission: "admissions_view",
        roles: ["registrar", "headteacher", "deputy_head_academic", "admin"],
      },
      create: {
        permission: "admissions_create",
        roles: ["registrar", "secretary"],
      },
      verify_documents: {
        permission: "admissions_verify",
        roles: ["registrar", "deputy_head_academic"],
      },
      schedule_interview: {
        permission: "admissions_schedule",
        roles: ["registrar", "secretary"],
      },
      conduct_interview: {
        permission: "admissions_interview",
        roles: ["headteacher", "deputy_head_academic"],
      },
      approve: {
        permission: "admissions_approve",
        roles: ["headteacher", "director"],
      },
      reject: {
        permission: "admissions_reject",
        roles: ["headteacher", "director"],
      },
      enroll: {
        permission: "students_create",
        roles: ["registrar", "headteacher"],
      },
    },
    attendance: {
      view: { permission: "attendance_view", roles: ["*"] },
      mark: {
        permission: "attendance_mark",
        roles: ["class_teacher", "subject_teacher"],
      },
      edit: {
        permission: "attendance_edit",
        roles: ["class_teacher", "headteacher"],
      },
      report: {
        permission: "attendance_report",
        roles: ["headteacher", "deputy_head_academic", "director"],
      },
    },
    discipline: {
      view: {
        permission: "discipline_view",
        roles: [
          "class_teacher",
          "deputy_head_discipline",
          "headteacher",
          "counselor",
        ],
      },
      create: {
        permission: "discipline_create",
        roles: ["class_teacher", "subject_teacher", "deputy_head_discipline"],
      },
      resolve: {
        permission: "discipline_resolve",
        roles: ["deputy_head_discipline", "headteacher"],
      },
      escalate: {
        permission: "discipline_escalate",
        roles: ["deputy_head_discipline", "headteacher"],
      },
    },
    inventory: {
      view: {
        permission: "inventory_view",
        roles: ["inventory_manager", "store_manager", "director", "admin"],
      },
      create: {
        permission: "inventory_create",
        roles: ["inventory_manager", "store_manager"],
      },
      issue: {
        permission: "inventory_issue",
        roles: ["inventory_manager", "store_manager"],
      },
      approve: {
        permission: "inventory_approve",
        roles: ["director", "admin"],
      },
      restock: {
        permission: "inventory_restock",
        roles: ["inventory_manager", "store_manager"],
      },
    },
    boarding: {
      view: {
        permission: "boarding_view",
        roles: ["boarding_master", "matron", "headteacher", "admin"],
      },
      roll_call: {
        permission: "boarding_rollcall",
        roles: ["boarding_master", "matron"],
      },
      leave_approval: {
        permission: "boarding_leave",
        roles: ["boarding_master", "headteacher"],
      },
      health_log: {
        permission: "boarding_health",
        roles: ["boarding_master", "matron", "nurse"],
      },
    },
    communications: {
      view: { permission: "communications_view", roles: ["*"] },
      create: {
        permission: "communications_create",
        roles: [
          "headteacher",
          "deputy_head_academic",
          "school_administrator",
          "admin",
        ],
      },
      send_sms: {
        permission: "sms_send",
        roles: ["school_administrator", "registrar", "admin"],
      },
      send_email: {
        permission: "email_send",
        roles: ["school_administrator", "registrar", "admin"],
      },
    },
    staff: {
      view: {
        permission: "staff_view",
        roles: ["hr_manager", "director", "admin"],
      },
      create: { permission: "staff_create", roles: ["hr_manager", "admin"] },
      edit: { permission: "staff_edit", roles: ["hr_manager", "admin"] },
      view_payroll: {
        permission: "payroll_view",
        roles: ["accountant", "hr_manager", "director"],
      },
      approve_leave: {
        permission: "leave_approve",
        roles: ["hr_manager", "headteacher", "director"],
      },
    },
    assessments: {
      view: {
        permission: "assessments_view",
        roles: [
          "class_teacher",
          "subject_teacher",
          "headteacher",
          "deputy_head_academic",
        ],
      },
      create: {
        permission: "assessments_create",
        roles: ["class_teacher", "subject_teacher"],
      },
      enter_results: {
        permission: "results_enter",
        roles: ["class_teacher", "subject_teacher"],
      },
      approve: {
        permission: "results_approve",
        roles: ["headteacher", "deputy_head_academic"],
      },
      publish: { permission: "results_publish", roles: ["headteacher"] },
    },
  };

  /**
   * Check if current user has a specific permission
   */
  function hasPermission(permission) {
    return AuthContext.hasPermission(permission);
  }

  /**
   * Check if current user has any of the given permissions
   */
  function hasAnyPermission(permissions) {
    return AuthContext.hasAnyPermission(permissions);
  }

  /**
   * Check if current user has all of the given permissions
   */
  function hasAllPermissions(permissions) {
    return AuthContext.hasAllPermissions(permissions);
  }

  /**
   * Check if current user has a specific role
   */
  function hasRole(roleName) {
    // Normalize role name
    const normalizedRole = roleName.toLowerCase().replace(/[\s-]/g, "_");
    return AuthContext.hasRole(roleName) || AuthContext.hasRole(normalizedRole);
  }

  /**
   * Check if current user has any of the given roles
   */
  function hasAnyRole(roles) {
    return roles.some((role) => hasRole(role));
  }

  /**
   * Check if current user belongs to a role category
   */
  function hasRoleInCategory(category) {
    const categoryRoles = ROLE_CATEGORIES[category] || [];
    return hasAnyRole(categoryRoles);
  }

  /**
   * Get current user's primary role
   */
  function getPrimaryRole() {
    const roles = AuthContext.getRoles();
    return roles.length > 0 ? roles[0] : null;
  }

  /**
   * Get all roles of current user
   */
  function getUserRoles() {
    return AuthContext.getRoles();
  }

  /**
   * Apply role-based visibility to all elements in document or container
   * @param {HTMLElement} container - Optional container element (defaults to document.body)
   */
  function apply(container = document.body) {
    if (!container) return;

    // Process data-permission elements
    container.querySelectorAll("[data-permission]").forEach((el) => {
      const permission = el.dataset.permission;
      if (!hasPermission(permission)) {
        hideElement(el);
      } else {
        showElement(el);
      }
    });

    // Process data-permission-any elements (user needs ANY of the permissions)
    container.querySelectorAll("[data-permission-any]").forEach((el) => {
      const permissions = el.dataset.permissionAny
        .split(",")
        .map((p) => p.trim());
      if (!hasAnyPermission(permissions)) {
        hideElement(el);
      } else {
        showElement(el);
      }
    });

    // Process data-permission-all elements (user needs ALL of the permissions)
    container.querySelectorAll("[data-permission-all]").forEach((el) => {
      const permissions = el.dataset.permissionAll
        .split(",")
        .map((p) => p.trim());
      if (!hasAllPermissions(permissions)) {
        hideElement(el);
      } else {
        showElement(el);
      }
    });

    // Process data-role elements (user needs to have one of these roles)
    container.querySelectorAll("[data-role]").forEach((el) => {
      const roles = el.dataset.role.split(",").map((r) => r.trim());
      // Special case: "*" means all roles
      if (roles.includes("*")) {
        showElement(el);
      } else if (!hasAnyRole(roles)) {
        hideElement(el);
      } else {
        showElement(el);
      }
    });

    // Process data-role-exclude elements (user should NOT have these roles)
    container.querySelectorAll("[data-role-exclude]").forEach((el) => {
      const excludedRoles = el.dataset.roleExclude
        .split(",")
        .map((r) => r.trim());
      if (hasAnyRole(excludedRoles)) {
        hideElement(el);
      } else {
        showElement(el);
      }
    });

    // Process data-role-category elements
    container.querySelectorAll("[data-role-category]").forEach((el) => {
      const category = el.dataset.roleCategory;
      if (!hasRoleInCategory(category)) {
        hideElement(el);
      } else {
        showElement(el);
      }
    });

    // Initialize tooltips for remaining visible elements
    initTooltips(container);
  }

  /**
   * Hide element with proper handling
   */
  function hideElement(el) {
    el.style.display = "none";
    el.classList.add("rbac-hidden");
    el.setAttribute("aria-hidden", "true");
  }

  /**
   * Show element (restore)
   */
  function showElement(el) {
    el.style.display = "";
    el.classList.remove("rbac-hidden");
    el.removeAttribute("aria-hidden");
  }

  /**
   * Initialize Bootstrap tooltips
   */
  function initTooltips(container) {
    const tooltipTriggers = container.querySelectorAll(
      '[data-bs-toggle="tooltip"]:not(.rbac-hidden)'
    );
    tooltipTriggers.forEach((el) => {
      new bootstrap.Tooltip(el);
    });
  }

  /**
   * Get allowed actions for a module based on user's role/permissions
   * @param {string} module - Module name (e.g., 'students', 'finance')
   * @returns {Object} Object with action names as keys and boolean availability as values
   */
  function getAllowedActions(module) {
    const moduleActions = MODULE_ACTIONS[module] || {};
    const allowedActions = {};

    for (const [action, config] of Object.entries(moduleActions)) {
      let allowed = false;

      // Check permission first
      if (config.permission) {
        allowed = hasPermission(config.permission);
      }

      // If permission check passed or no permission required, check role
      if (allowed || !config.permission) {
        if (config.roles.includes("*")) {
          allowed = true;
        } else {
          allowed = allowed && hasAnyRole(config.roles);
        }
      }

      allowedActions[action] = allowed;
    }

    return allowedActions;
  }

  /**
   * Filter an array of action objects based on user permissions/roles
   * @param {string} module - Module name
   * @param {Array} actions - Array of action objects with 'id' property
   * @returns {Array} Filtered actions that user can access
   */
  function filterActions(module, actions) {
    const allowed = getAllowedActions(module);
    return actions.filter((action) => allowed[action.id] !== false);
  }

  /**
   * Generate action buttons HTML for a table row
   * @param {string} module - Module name
   * @param {Object} row - Row data
   * @param {Array} actionDefs - Action definitions with id, icon, label, variant, onClick
   * @returns {string} HTML string
   */
  function renderActionButtons(module, row, actionDefs) {
    const allowed = getAllowedActions(module);
    const rowId = row.id || row.ID || "";

    const buttons = actionDefs
      .filter((action) => allowed[action.id] !== false)
      .filter((action) => !action.visible || action.visible(row))
      .map((action) => {
        const variant = action.variant || "secondary";
        const icon = action.icon || "bi-three-dots";
        const title = action.title || action.label || action.id;
        const onClick = action.onClick
          ? `onclick="${action.onClick.replace("{{id}}", rowId)}"`
          : "";

        return `
                    <button type="button" 
                            class="btn btn-${variant} btn-sm" 
                            data-action="${action.id}" 
                            data-row-id="${rowId}"
                            title="${title}"
                            data-bs-toggle="tooltip"
                            ${onClick}>
                        <i class="bi ${icon}"></i>
                    </button>
                `;
      });

    if (buttons.length === 0) {
      return '<span class="text-muted">-</span>';
    }

    return `<div class="btn-group btn-group-sm">${buttons.join("")}</div>`;
  }

  /**
   * Render dropdown action menu for a table row
   * @param {string} module - Module name
   * @param {Object} row - Row data
   * @param {Array} actionDefs - Action definitions
   * @returns {string} HTML string
   */
  function renderActionDropdown(module, row, actionDefs) {
    const allowed = getAllowedActions(module);
    const rowId = row.id || row.ID || "";

    const items = actionDefs
      .filter((action) => allowed[action.id] !== false)
      .filter((action) => !action.visible || action.visible(row))
      .map((action) => {
        const icon = action.icon || "bi-arrow-right";
        const onClick = action.onClick
          ? action.onClick.replace("{{id}}", rowId)
          : `handleAction('${action.id}', ${rowId})`;

        if (action.divider) {
          return '<li><hr class="dropdown-divider"></li>';
        }

        return `
                    <li>
                        <a class="dropdown-item" href="#" onclick="event.preventDefault(); ${onClick}">
                            <i class="bi ${icon} me-2"></i>${action.label}
                        </a>
                    </li>
                `;
      });

    if (items.length === 0) {
      return '<span class="text-muted">-</span>';
    }

    const dropdownId = `actions-${rowId}-${Math.random()
      .toString(36)
      .substr(2, 6)}`;

    return `
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                        id="${dropdownId}" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-three-dots"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="${dropdownId}">
                    ${items.join("")}
                </ul>
            </div>
        `;
  }

  /**
   * Create a segmented dashboard section that shows different content based on role
   * @param {Object} segments - Object with role names as keys and content/callback as values
   * @returns {string} HTML for the appropriate segment
   */
  function renderRoleSegment(segments) {
    const userRoles = getUserRoles();

    // Try to find a matching segment for user's roles
    for (const role of userRoles) {
      const normalizedRole = role.toLowerCase().replace(/[\s-]/g, "_");
      if (segments[role]) {
        return typeof segments[role] === "function"
          ? segments[role]()
          : segments[role];
      }
      if (segments[normalizedRole]) {
        return typeof segments[normalizedRole] === "function"
          ? segments[normalizedRole]()
          : segments[normalizedRole];
      }
    }

    // Check for category matches
    for (const [category, categoryRoles] of Object.entries(ROLE_CATEGORIES)) {
      if (hasAnyRole(categoryRoles) && segments[category]) {
        return typeof segments[category] === "function"
          ? segments[category]()
          : segments[category];
      }
    }

    // Return default if provided
    if (segments.default) {
      return typeof segments.default === "function"
        ? segments.default()
        : segments.default;
    }

    return "";
  }

  /**
   * Conditionally render content based on permission
   * @param {string} permission - Required permission
   * @param {string|function} content - Content to render if permitted
   * @param {string|function} fallback - Content to render if not permitted (optional)
   * @returns {string}
   */
  function ifPermission(permission, content, fallback = "") {
    if (hasPermission(permission)) {
      return typeof content === "function" ? content() : content;
    }
    return typeof fallback === "function" ? fallback() : fallback;
  }

  /**
   * Conditionally render content based on role
   * @param {string|Array} roles - Required role(s)
   * @param {string|function} content - Content to render if user has role
   * @param {string|function} fallback - Content to render if not (optional)
   * @returns {string}
   */
  function ifRole(roles, content, fallback = "") {
    const roleArray = Array.isArray(roles) ? roles : [roles];
    if (hasAnyRole(roleArray)) {
      return typeof content === "function" ? content() : content;
    }
    return typeof fallback === "function" ? fallback() : fallback;
  }

  /**
   * Get role-specific configuration value
   * @param {Object} config - Object with role keys and configuration values
   * @param {*} defaultValue - Default value if no role matches
   * @returns {*}
   */
  function getRoleConfig(config, defaultValue = null) {
    const userRoles = getUserRoles();

    for (const role of userRoles) {
      if (config[role]) return config[role];
      const normalized = role.toLowerCase().replace(/[\s-]/g, "_");
      if (config[normalized]) return config[normalized];
    }

    return config.default !== undefined ? config.default : defaultValue;
  }

  /**
   * Check if user can perform action on a module
   * @param {string} module - Module name
   * @param {string} action - Action name
   * @returns {boolean}
   */
  function canPerformAction(module, action) {
    const moduleConfig = MODULE_ACTIONS[module];
    if (!moduleConfig || !moduleConfig[action]) {
      return false;
    }

    const actionConfig = moduleConfig[action];

    // Check permission
    if (actionConfig.permission && !hasPermission(actionConfig.permission)) {
      return false;
    }

    // Check role
    if (actionConfig.roles.includes("*")) {
      return true;
    }

    return hasAnyRole(actionConfig.roles);
  }

  // ============== LAYOUT & THEME CONFIGURATION ==============

  /**
   * Role category to layout configuration mapping
   * Defines the visual layout, components, and behaviors per role category
   */
  const LAYOUT_CONFIG = {
    admin: {
      themeClass: "admin-layout",
      sidebarWidth: "280px",
      sidebarStyle: "full", // full, compact, mini, hidden
      gridColumns: 4,
      showCharts: true,
      chartCount: 3,
      tableColumns: "all",
      tableActions: ["view", "edit", "delete", "export", "bulk"],
      headerActions: ["filters", "export", "create"],
      animations: "full",
      cssFile: "/css/roles/admin-theme.css",
    },
    manager: {
      themeClass: "manager-layout",
      sidebarWidth: "80px",
      sidebarStyle: "compact",
      gridColumns: 3,
      showCharts: true,
      chartCount: 2,
      tableColumns: "standard",
      tableActions: ["view", "edit", "export"],
      headerActions: ["export", "create"],
      animations: "moderate",
      cssFile: "/css/roles/manager-theme.css",
    },
    operator: {
      themeClass: "operator-layout",
      sidebarWidth: "60px",
      sidebarStyle: "mini",
      gridColumns: 2,
      showCharts: false,
      chartCount: 0,
      tableColumns: "essential",
      tableActions: ["view"],
      headerActions: [],
      animations: "subtle",
      cssFile: "/css/roles/operator-theme.css",
    },
    viewer: {
      themeClass: "viewer-layout",
      sidebarWidth: "0px",
      sidebarStyle: "hidden",
      gridColumns: 1,
      showCharts: false,
      chartCount: 0,
      tableColumns: "minimal",
      tableActions: [],
      headerActions: [],
      animations: "none",
      cssFile: "/css/roles/viewer-theme.css",
    },
  };

  /**
   * Mapping of roles to layout categories
   */
  const ROLE_TO_LAYOUT = {
    // Admin category
    "System Administrator": "admin",
    Director: "admin",
    "Director/Owner": "admin",
    Headteacher: "admin",
    "School Administrator": "admin",
    "School Administrative Officer": "admin",
    admin: "admin",

    // Manager category
    "Deputy Head - Academic": "manager",
    "Deputy Head - Discipline & Boarding": "manager",
    "HOD - Languages": "manager",
    "HOD - Science": "manager",
    "HOD - Mathematics": "manager",
    "HOD - Humanities": "manager",
    "HOD - Creative Arts": "manager",
    "HOD - Technical Subjects": "manager",
    "HOD - Talent Development": "manager",
    "HOD - Food & Nutrition": "manager",
    "Senior Accountant": "manager",
    "Inventory Manager": "manager",
    "Boarding Master/Matron": "manager",
    Librarian: "manager",

    // Operator category
    "Class Teacher": "operator",
    "Subject Teacher": "operator",
    Accountant: "operator",
    "Secretary/Receptionist": "operator",
    Chaplain: "operator",
    "Games Master/Mistress": "operator",
    Driver: "operator",
    Cateress: "operator",
    "Kitchen Staff": "operator",
    "Security Personnel": "operator",
    "Janitor/Groundsman": "operator",
    Nurse: "operator",
    Staff: "operator",

    // Viewer category
    "Intern/Student Teacher": "viewer",
    Student: "viewer",
    Parent: "viewer",
    Guardian: "viewer",
  };

  /**
   * Get the layout category for current user
   * @returns {string} 'admin', 'manager', 'operator', or 'viewer'
   */
  function getLayoutCategory() {
    const userRoles = getUserRoles();

    // Priority order: admin > manager > operator > viewer
    const categoryPriority = ["admin", "manager", "operator", "viewer"];

    for (const category of categoryPriority) {
      for (const role of userRoles) {
        const roleCategory = ROLE_TO_LAYOUT[role];
        if (roleCategory === category) {
          return category;
        }
      }
    }

    return "viewer"; // Default fallback
  }

  /**
   * Get layout configuration for current user
   * @returns {Object} Layout configuration object
   */
  function getLayoutConfig() {
    const category = getLayoutCategory();
    return LAYOUT_CONFIG[category] || LAYOUT_CONFIG.viewer;
  }

  /**
   * Apply role-based theme and layout
   * Dynamically loads the appropriate CSS and applies layout classes
   */
  function applyLayout() {
    const config = getLayoutConfig();
    const category = getLayoutCategory();

    // Remove any existing layout classes
    document.body.classList.remove(
      "admin-layout",
      "manager-layout",
      "operator-layout",
      "viewer-layout"
    );

    // Add the appropriate layout class
    document.body.classList.add(config.themeClass);

    // Set CSS custom properties
    document.documentElement.style.setProperty(
      "--sidebar-width",
      config.sidebarWidth
    );
    document.documentElement.style.setProperty(
      "--grid-columns",
      config.gridColumns
    );

    // Load role-specific CSS if not already loaded
    loadThemeCSS(config.cssFile);

    console.log(`[RoleBasedUI] Applied ${category} layout`);

    return config;
  }

  /**
   * Load theme CSS file dynamically
   * @param {string} cssFile - Path to CSS file
   */
  function loadThemeCSS(cssFile) {
    const existingLink = document.querySelector(`link[href="${cssFile}"]`);
    if (existingLink) return;

    // First ensure base theme is loaded
    const baseTheme = "/css/school-theme.css";
    if (!document.querySelector(`link[href="${baseTheme}"]`)) {
      const baseLink = document.createElement("link");
      baseLink.rel = "stylesheet";
      baseLink.href = baseTheme;
      document.head.appendChild(baseLink);
    }

    // Then load role-specific theme
    const link = document.createElement("link");
    link.rel = "stylesheet";
    link.href = cssFile;
    link.setAttribute("data-role-theme", "true");
    document.head.appendChild(link);
  }

  /**
   * Render statistics cards based on role layout
   * @param {Array} stats - Array of stat objects {icon, value, label, change}
   * @param {HTMLElement} container - Target container
   */
  function renderStatsGrid(stats, container) {
    const config = getLayoutConfig();
    const category = getLayoutCategory();
    const maxCards = config.gridColumns;
    const displayStats = stats.slice(0, maxCards);

    container.innerHTML = "";
    container.className = `${category}-stats`;

    displayStats.forEach((stat, index) => {
      const card = document.createElement("div");

      if (category === "viewer") {
        card.className = "viewer-summary-card";
        card.innerHTML = `
          <div class="summary-icon">${stat.icon || "📊"}</div>
          <div class="summary-value">${stat.value}</div>
          <div class="summary-label">${stat.label}</div>
        `;
      } else {
        card.className = `${category}-stat-card`;
        card.innerHTML = `
          <div class="stat-icon">${stat.icon || "📈"}</div>
          <div class="stat-content">
            <div class="stat-value">${stat.value}</div>
            <div class="stat-label">${stat.label}</div>
            ${
              config.gridColumns > 2 && stat.change !== undefined
                ? `
              <div class="stat-change ${
                stat.change >= 0 ? "positive" : "negative"
              }">
                ${stat.change >= 0 ? "↑" : "↓"} ${Math.abs(stat.change)}%
              </div>
            `
                : ""
            }
          </div>
        `;
      }

      container.appendChild(card);
    });
  }

  /**
   * Render data table based on role layout
   * @param {Object} options - { title, columns, data, idField }
   * @param {HTMLElement} container - Target container
   */
  function renderRoleTable(options, container) {
    const config = getLayoutConfig();
    const category = getLayoutCategory();
    const {
      title,
      columns,
      data,
      idField = "id",
      module = "general",
    } = options;

    // Determine visible columns based on role
    let visibleColumns = columns;
    if (config.tableColumns === "standard") {
      visibleColumns = columns.slice(0, 7);
    } else if (config.tableColumns === "essential") {
      visibleColumns = columns.slice(0, 4);
    } else if (config.tableColumns === "minimal") {
      visibleColumns = columns.slice(0, 2);
    }

    container.innerHTML = "";
    container.className = `${category}-table-card`;

    let html = `
      <div class="${category}-table-header">
        <span class="table-title">${title}</span>
        ${
          config.tableActions.includes("export")
            ? `<button class="btn btn-outline btn-sm export-btn">Export</button>`
            : ""
        }
      </div>
    `;

    // Filters (for non-viewer roles)
    if (category !== "viewer") {
      html += `
        <div class="${category}-filters">
          <input type="text" class="search-input form-control" 
                 placeholder="Search..." id="tableSearch">
          ${
            config.tableActions.includes("bulk")
              ? `
            <div class="bulk-actions" style="display:none;">
              <span class="selected-count">0 selected</span>
              <button class="btn btn-danger btn-sm bulk-delete-btn">Delete Selected</button>
            </div>
          `
              : ""
          }
        </div>
      `;
    }

    // Table
    html += `<table class="${category}-data-table">`;
    html += "<thead><tr>";

    if (config.tableActions.includes("bulk")) {
      html += '<th><input type="checkbox" class="select-all"></th>';
    }

    visibleColumns.forEach((col) => {
      html += `<th>${col.label}</th>`;
    });

    if (config.tableActions.length > 0) {
      html += "<th>Actions</th>";
    }

    html += "</tr></thead><tbody>";

    data.forEach((row) => {
      const rowId = row[idField] || "";
      html += `<tr data-id="${rowId}">`;

      if (config.tableActions.includes("bulk")) {
        html += `<td><input type="checkbox" class="row-select" data-id="${rowId}"></td>`;
      }

      visibleColumns.forEach((col) => {
        const value = row[col.key] ?? "";
        html += `<td>${
          col.render ? col.render(value, row) : escapeHtml(value)
        }</td>`;
      });

      if (config.tableActions.length > 0) {
        html += `<td class="${category}-row-actions">`;
        config.tableActions.forEach((action) => {
          if (action === "bulk") return; // Skip bulk, handled separately
          html += `<button class="action-btn ${action}-btn" data-action="${action}" data-id="${rowId}" title="${capitalize(
            action
          )}">
            ${getActionIcon(action)}
          </button>`;
        });
        html += "</td>";
      }

      html += "</tr>";
    });

    html += "</tbody></table>";
    container.innerHTML = html;

    // Attach event handlers
    attachTableHandlers(container, options);
  }

  /**
   * Get icon for table action
   */
  function getActionIcon(action) {
    const icons = {
      view: "👁️",
      edit: "✏️",
      delete: "🗑️",
      export: "📤",
    };
    return icons[action] || "•";
  }

  /**
   * Capitalize first letter
   */
  function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  /**
   * Escape HTML to prevent XSS
   */
  function escapeHtml(str) {
    if (typeof str !== "string") return str;
    return str.replace(
      /[&<>"']/g,
      (m) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        }[m])
    );
  }

  /**
   * Attach event handlers to rendered table
   */
  function attachTableHandlers(container, options) {
    // Search
    const searchInput = container.querySelector("#tableSearch");
    if (searchInput) {
      searchInput.addEventListener("input", (e) => {
        const query = e.target.value.toLowerCase();
        container.querySelectorAll("tbody tr").forEach((row) => {
          const text = row.textContent.toLowerCase();
          row.style.display = text.includes(query) ? "" : "none";
        });
      });
    }

    // Select all
    const selectAll = container.querySelector(".select-all");
    if (selectAll) {
      selectAll.addEventListener("change", (e) => {
        container.querySelectorAll(".row-select").forEach((cb) => {
          cb.checked = e.target.checked;
        });
        updateBulkActions(container);
      });
    }

    // Row selection
    container.querySelectorAll(".row-select").forEach((cb) => {
      cb.addEventListener("change", () => updateBulkActions(container));
    });

    // Action buttons
    container.querySelectorAll(".action-btn").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        const action = e.target.closest(".action-btn").dataset.action;
        const id = e.target.closest(".action-btn").dataset.id;

        if (options.onAction) {
          options.onAction(action, id);
        } else {
          console.log(`Action: ${action} on ID: ${id}`);
        }
      });
    });
  }

  /**
   * Update bulk actions visibility and count
   */
  function updateBulkActions(container) {
    const selected = container.querySelectorAll(".row-select:checked").length;
    const bulkActions = container.querySelector(".bulk-actions");
    if (bulkActions) {
      bulkActions.style.display = selected > 0 ? "flex" : "none";
      const count = bulkActions.querySelector(".selected-count");
      if (count) count.textContent = `${selected} selected`;
    }
  }

  /**
   * Render simple list view for viewer role
   * @param {Array} items - Array of { title, subtitle, icon, status }
   * @param {HTMLElement} container - Target container
   */
  function renderSimpleList(items, container) {
    container.className = "viewer-list-card";

    let html = `
      <div class="viewer-list-header">
        <span class="list-title">Items</span>
        <span class="list-count">${items.length}</span>
      </div>
      <ul class="viewer-list">
    `;

    if (items.length === 0) {
      html += `
        <div class="viewer-empty-state">
          <div class="empty-icon">📭</div>
          <div class="empty-text">No items to display</div>
        </div>
      `;
    } else {
      items.forEach((item) => {
        html += `
          <li class="viewer-list-item">
            <div class="item-icon">${item.icon || "📄"}</div>
            <div class="item-content">
              <div class="item-title">${escapeHtml(item.title)}</div>
              ${
                item.subtitle
                  ? `<div class="item-subtitle">${escapeHtml(
                      item.subtitle
                    )}</div>`
                  : ""
              }
            </div>
            ${
              item.status
                ? `<span class="item-status status-${item.status}">${item.status}</span>`
                : ""
            }
          </li>
        `;
      });
    }

    html += "</ul>";
    container.innerHTML = html;
  }

  /**
   * Render sidebar navigation based on role
   * @param {Array} navItems - Array of { icon, label, url, active }
   * @param {HTMLElement} container - Sidebar container
   */
  function renderSidebar(navItems, container) {
    const config = getLayoutConfig();
    const category = getLayoutCategory();

    if (config.sidebarStyle === "hidden") {
      container.style.display = "none";
      return;
    }

    container.className = `${category}-sidebar`;

    const showLabels = config.sidebarStyle === "full";
    const userInitials =
      window.userInitials || AuthContext.getUser()?.name?.charAt(0) || "U";

    let html = `
      <div class="logo-section">
        <img src="/images/logo.png" alt="Kingsway">
        ${showLabels ? '<span class="logo-text">Kingsway</span>' : ""}
      </div>
      <nav class="${category}-nav">
    `;

    navItems.forEach((item) => {
      html += `
        <a href="${item.url}" 
           class="${category}-nav-item ${item.active ? "active" : ""}"
           data-tooltip="${item.label}">
          <span class="nav-icon">${item.icon}</span>
          ${showLabels ? `<span class="nav-label">${item.label}</span>` : ""}
        </a>
      `;
    });

    html += `
      </nav>
      <div class="user-avatar" title="Profile">${userInitials}</div>
    `;

    container.innerHTML = html;
  }

  /**
   * Render page header based on role
   * @param {Object} options - { title, breadcrumb }
   * @param {HTMLElement} container - Header container
   */
  function renderPageHeader(options, container) {
    const config = getLayoutConfig();
    const category = getLayoutCategory();
    const { title, breadcrumb = [] } = options;

    container.className = `${category}-header`;

    let html = "";

    // Breadcrumb (admin only)
    if (category === "admin" && breadcrumb.length > 0) {
      html += '<div class="breadcrumb">';
      breadcrumb.forEach((crumb, i) => {
        html += `<a href="${crumb.url || "#"}">${escapeHtml(crumb.label)}</a>`;
        if (i < breadcrumb.length - 1) html += "<span>/</span>";
      });
      html += "</div>";
    }

    html += `<h1 class="page-title">${escapeHtml(title)}</h1>`;

    // Header actions based on role
    if (config.headerActions.length > 0) {
      html += `<div class="${category}-header-actions">`;

      if (config.headerActions.includes("filters")) {
        html += `<button class="btn btn-outline btn-sm filter-btn">🔍 Filters</button>`;
      }
      if (config.headerActions.includes("export")) {
        html += `<button class="btn btn-outline btn-sm export-btn">📤 Export</button>`;
      }
      if (config.headerActions.includes("create")) {
        html += `<button class="btn btn-primary ${
          category === "admin" ? "" : "btn-sm"
        } create-btn">➕ Create New</button>`;
      }

      html += "</div>";
    }

    container.innerHTML = html;
  }

  // ============== END LAYOUT & THEME CONFIGURATION ==============

  /**
   * Disable elements that user doesn't have permission for (instead of hiding)
   * @param {HTMLElement} container - Container element
   */
  function disableUnauthorized(container = document.body) {
    container.querySelectorAll("[data-permission]").forEach((el) => {
      const permission = el.dataset.permission;
      if (!hasPermission(permission)) {
        el.disabled = true;
        el.classList.add("disabled");
        el.setAttribute(
          "title",
          "You do not have permission to perform this action"
        );
      }
    });
  }

  /**
   * Log RBAC debug info to console
   */
  function debug() {
    console.group("RoleBasedUI Debug Info");
    console.log("User:", AuthContext.getUser());
    console.log("Roles:", AuthContext.getRoles());
    console.log("Permissions count:", AuthContext.getPermissionCount());
    console.log("Permissions:", AuthContext.getPermissions());
    console.groupEnd();
  }

  // Public API
  return {
    // Core functions
    apply,
    applyTo: apply, // alias

    // Permission checks
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,

    // Role checks
    hasRole,
    hasAnyRole,
    hasRoleInCategory,
    getPrimaryRole,
    getUserRoles,

    // Action helpers
    getAllowedActions,
    filterActions,
    canPerformAction,

    // Rendering helpers
    renderActionButtons,
    renderActionDropdown,
    renderRoleSegment,
    ifPermission,
    ifRole,
    getRoleConfig,

    // Element manipulation
    hideElement,
    showElement,
    disableUnauthorized,

    // Debug
    debug,

    // Constants
    ROLE_IDS,
    ROLE_CATEGORIES,
    MODULE_ACTIONS,
  };
})();

// Auto-apply on DOM ready
document.addEventListener("DOMContentLoaded", () => {
  // Small delay to ensure AuthContext is initialized
  setTimeout(() => {
    if (AuthContext.isAuthenticated()) {
      RoleBasedUI.apply();
      console.log("[RoleBasedUI] Applied role-based visibility");
    }
  }, 100);
});

// Make globally available
window.RoleBasedUI = RoleBasedUI;
