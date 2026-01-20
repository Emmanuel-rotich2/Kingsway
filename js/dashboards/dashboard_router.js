/**
 * Dashboard Router - Permission-Aware Dashboard Routing
 * 
 * Purpose: Detect user role and route to appropriate dashboard
 * Principle: Each role sees ONLY its role-specific dashboard
 * 
 * Architecture:
 * 1. Get current user role(s) from session/auth context
 * 2. Map role to dashboard controller
 * 3. Load appropriate dashboard
 * 4. Handle multiple roles (show primary, offer switcher)
 * 5. Graceful fallback for unrecognized roles
 */

const DashboardRouter = {
  /**
   * Role-to-Dashboard Mapping
   * Maps role IDs to their respective dashboard controllers
   */
  ROLE_DASHBOARD_MAP: {
    2: {
      name: "System Administrator",
      controller: "sysAdminDashboardController",
      file: "system_administrator_dashboard.js",
      scope: "technical",
      description: "Infrastructure, System Health, Security Monitoring",
    },
    3: {
      name: "Director",
      controller: "directorDashboardController",
      file: "director_dashboard.js",
      scope: "executive",
      description: "Finance, Staff, Students, Strategic KPIs",
    },
    4: {
      name: "School Administrator",
      controller: "schoolAdminDashboardController",
      file: "school_administrator_dashboard.js",
      scope: "operational",
      description: "Operations, Activities, Communications, Admissions",
    },
    5: {
      name: "Headteacher",
      controller: "headteacherDashboardController",
      file: "headteacher_dashboard.js",
      scope: "academic",
      description: "Academic Oversight, Schedules, Student Management",
    },
    6: {
      name: "Deputy Head - Academic",
      controller: "deputyHeadAcademicDashboardController",
      file: "deputy_head_academic_dashboard.js",
      scope: "academic",
      description: "Academic Support, Admissions, Timetabling",
    },
    // Teachers now share a unified teacher dashboard that decides which view to show
    7: {
      name: "Class Teacher",
      controller: "teacherDashboardController",
      file: "teacher_dashboard.js",
      scope: "teaching",
      description: "Teacher unified dashboard (class/subject/intern)",
    },
    8: {
      name: "Subject Teacher",
      controller: "teacherDashboardController",
      file: "teacher_dashboard.js",
      scope: "teaching",
      description: "Teacher unified dashboard (class/subject/intern)",
    },
    9: {
      name: "Intern/Student Teacher",
      controller: "teacherDashboardController",
      file: "teacher_dashboard.js",
      scope: "teaching",
      description: "Teacher unified dashboard (class/subject/intern)",
    },
    10: {
      name: "Accountant",
      controller: "schoolAccountantDashboardController",
      file: "school_accountant_dashboard.js",
      scope: "finance",
      description: "Financial Management, Fees, Payroll, Budget",
    },
    14: {
      name: "Inventory Manager",
      controller: "inventoryDashboardController",
      file: "inventory_dashboard.js",
      scope: "logistics",
      description: "Inventory, Stock, Requisitions, Orders",
    },
    16: {
      name: "Cateress",
      controller: "cateressDashboardController",
      file: "catering_dashboard.js",
      scope: "catering",
      description: "Kitchen, Menu Planning, Food Inventory",
    },
    18: {
      name: "Boarding Master",
      controller: "boardingMasterDashboardController",
      file: "boarding_master_dashboard.js",
      scope: "boarding",
      description: "Boarding, Student Welfare, Health, Discipline",
    },
    21: {
      name: "Talent Development Manager",
      controller: "talentDevelopmentDashboardController",
      file: "talent_development_dashboard.js",
      scope: "activities",
      description: "Sports, Music, Activities, Talent Development",
    },
    23: {
      name: "Driver",
      controller: "driverDashboardController",
      file: "driver_dashboard.js",
      scope: "transport",
      description: "Routes, Student Transport, Vehicle Maintenance",
    },
    24: {
      name: "Chaplain",
      controller: "chaplainDashboardController",
      file: "chaplain_dashboard.js",
      scope: "support",
      description: "Spiritual Care, Counseling, Pastoral",
    },
    32: {
      name: "Kitchen Staff",
      controller: "readOnlyDashboardController",
      file: "read_only_dashboard.js",
      scope: "readonly",
      description: "Personal Info, Schedule, Contact",
    },
    33: {
      name: "Security Staff",
      controller: "readOnlyDashboardController",
      file: "read_only_dashboard.js",
      scope: "readonly",
      description: "Personal Info, Schedule, Contact",
    },
    34: {
      name: "Janitor",
      controller: "readOnlyDashboardController",
      file: "read_only_dashboard.js",
      scope: "readonly",
      description: "Personal Info, Schedule, Contact",
    },
    63: {
      name: "Deputy Head - Discipline",
      controller: "deputyHeadDisciplineDashboardController",
      file: "deputy_head_discipline_dashboard.js",
      scope: "academic",
      description: "Discipline, Student Management, Communications",
    },
  },

  /**
   * Get current user's roles from auth context
   * Returns array of role IDs [2, 5, 7] or null if not authenticated
   */
  getCurrentUserRoles: function () {
    try {
      // Check if AuthContext exists (from auth-utils.js)
      if (typeof AuthContext !== "undefined" && AuthContext.isAuthenticated()) {
        const user = AuthContext.getCurrentUser();
        if (user) {
          // First check if role_ids is directly available
          if (user.role_ids) {
            const roleIds = Array.isArray(user.role_ids)
              ? user.role_ids
              : [user.role_ids];
            console.log("✓ Got role_ids from user.role_ids:", roleIds);
            return roleIds;
          }

          // If not, try to extract from roles array
          if (user.roles && Array.isArray(user.roles)) {
            const roleIds = user.roles
              .map((r) => r.id || r.role_id || null)
              .filter((id) => id !== null && id !== undefined);
            if (roleIds.length > 0) {
              console.log("✓ Extracted role_ids from user.roles:", roleIds);
              return roleIds;
            }
          }

          // Fallback to single role_id
          if (user.role_id) {
            console.log("✓ Got single role_id:", user.role_id);
            return [user.role_id];
          }
        }
      }

      // Fallback: Check sessionStorage for user data
      const userJson = sessionStorage.getItem("user");
      if (userJson) {
        const user = JSON.parse(userJson);
        if (user.role_ids) {
          const roleIds = Array.isArray(user.role_ids)
            ? user.role_ids
            : [user.role_ids];
          console.log("✓ Got role_ids from sessionStorage:", roleIds);
          return roleIds;
        }

        // Try to extract from roles
        if (user.roles && Array.isArray(user.roles)) {
          const roleIds = user.roles
            .map((r) => r.id || r.role_id || null)
            .filter((id) => id !== null && id !== undefined);
          if (roleIds.length > 0) {
            console.log(
              "✓ Extracted role_ids from sessionStorage roles:",
              roleIds
            );
            return roleIds;
          }
        }

        if (user.role_id) {
          console.log("✓ Got role_id from sessionStorage:", user.role_id);
          return [user.role_id];
        }
      }

      // Not authenticated
      console.warn("⚠️ Could not find role information");
      return null;
    } catch (error) {
      console.error("Error getting current user roles:", error);
      return null;
    }
  },

  /**
   * Determine primary role from array of role IDs
   * Priority: Higher ID (more specific) > Lower ID (more general)
   * Or use a predefined hierarchy
   */
  getPrimaryRole: function (roleIds) {
    if (!Array.isArray(roleIds) || roleIds.length === 0) {
      return null;
    }

    // If only one role, that's the primary
    if (roleIds.length === 1) {
      return roleIds[0];
    }

    // Multi-role: Use hierarchy
    // System Admin > Director > School Admin > Specialists > Teachers > Read-only
    const hierarchy = [
      2, 3, 4, 5, 6, 63, 21, 18, 16, 14, 10, 24, 23, 8, 7, 9, 32, 33, 34,
    ];
    for (let roleId of hierarchy) {
      if (roleIds.includes(roleId)) {
        return roleId;
      }
    }

    // Fallback to first role
    return roleIds[0];
  },

  /**
   * Get dashboard config for a role ID
   */
  getDashboardConfig: function (roleId) {
    return this.ROLE_DASHBOARD_MAP[roleId] || null;
  },

  /**
   * Check if dashboard controller is loaded and callable
   */
  isControllerLoaded: function (controllerName) {
    return (
      typeof window[controllerName] !== "undefined" &&
      window[controllerName] !== null &&
      typeof window[controllerName].init === "function"
    );
  },

  /**
   * Load dashboard script dynamically
   */
  loadDashboardScript: function (scriptPath) {
    return new Promise((resolve, reject) => {
      if (document.querySelector(`script[src="${scriptPath}"]`)) {
        // Already loaded
        resolve();
        return;
      }

      const script = document.createElement("script");
      script.src = scriptPath;
      script.onload = () => resolve();
      script.onerror = () => reject(new Error(`Failed to load ${scriptPath}`));
      document.head.appendChild(script);
    });
  },

  /**
   * Route to appropriate dashboard
   * Main entry point for dashboard routing
   */
  routeToDashboard: async function () {
    console.log("🔀 Dashboard Router: Detecting user role...");

    try {
      // 1. Get user roles
      const userRoles = this.getCurrentUserRoles();
      if (!userRoles) {
        console.warn("❌ User not authenticated");
        window.location.href = "/Kingsway/index.php";
        return;
      }

      console.log(`✓ User roles detected: [${userRoles.join(", ")}]`);

      // 2. Determine primary role
      const primaryRoleId = this.getPrimaryRole(userRoles);
      const dashboardConfig = this.getDashboardConfig(primaryRoleId);

      if (!dashboardConfig) {
        console.error(
          `❌ No dashboard configured for role ID ${primaryRoleId}`
        );
        this.showErrorPage(
          `No dashboard available for role ID ${primaryRoleId}`
        );
        return;
      }

      console.log(`✓ Primary role: ${dashboardConfig.name}`);
      console.log(`📄 Loading dashboard: ${dashboardConfig.file}`);

      // 3. Load dashboard script
      const scriptPath = `/Kingsway/js/dashboards/${dashboardConfig.file}`;
      try {
        await this.loadDashboardScript(scriptPath);
      } catch (error) {
        console.warn(
          `⚠️ Could not load ${dashboardConfig.file}: ${error.message}`
        );
        // Continue anyway - controller might be pre-loaded
      }

      // 4. Check if controller exists
      if (!this.isControllerLoaded(dashboardConfig.controller)) {
        console.warn(`⚠️ Controller ${dashboardConfig.controller} not found`);
        this.showErrorPage(
          `Dashboard controller not loaded: ${dashboardConfig.controller}`
        );
        return;
      }

      // 5. Set role context for dashboard
      window.CURRENT_DASHBOARD_ROLE = primaryRoleId;
      window.CURRENT_DASHBOARD_ROLES = userRoles;
      window.CURRENT_DASHBOARD_CONFIG = dashboardConfig;

      // 6. Initialize dashboard
      console.log(`🚀 Initializing ${dashboardConfig.name} dashboard...`);
      const controller = window[dashboardConfig.controller];
      controller.init();

      // 7. Add role switcher if user has multiple roles
      if (userRoles.length > 1) {
        this.addRoleSwitcher(userRoles, primaryRoleId);
      }

      console.log("✓ Dashboard routing complete");
    } catch (error) {
      console.error("❌ Dashboard routing error:", error);
      this.showErrorPage(`Error routing to dashboard: ${error.message}`);
    }
  },

  /**
   * Add role switcher UI for multi-role users
   */
  addRoleSwitcher: function (roleIds, currentRoleId) {
    try {
      const navbar =
        document.querySelector(".navbar") || document.querySelector("nav");
      if (!navbar) return;

      const switcherHtml = `
                <div class="btn-group ms-auto" role="group">
                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                            id="roleSwitcher" data-bs-toggle="dropdown">
                        <i class="bi bi-shield-check"></i> Switch Role
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" id="roleSwitcherMenu">
                        ${roleIds
                          .map((roleId) => {
                            const config = this.getDashboardConfig(roleId);
                            const isActive =
                              roleId === currentRoleId ? "active" : "";
                            return `
                                <li>
                                    <a class="dropdown-item ${isActive}" href="#" data-role-id="${roleId}">
                                        <i class="bi bi-check-circle${
                                          isActive ? "-fill" : ""
                                        }"></i>
                                        ${config.name}
                                    </a>
                                </li>
                            `;
                          })
                          .join("")}
                    </ul>
                </div>
            `;

      navbar.insertAdjacentHTML("beforeend", switcherHtml);

      // Add event listeners
      document.querySelectorAll("#roleSwitcherMenu a").forEach((link) => {
        link.addEventListener("click", (e) => {
          e.preventDefault();
          const roleId = parseInt(link.dataset.roleId);
          this.switchToDashboard(roleId);
        });
      });

      console.log("✓ Role switcher added for multi-role user");
    } catch (error) {
      console.warn("Could not add role switcher:", error.message);
    }
  },

  /**
   * Switch to different dashboard (for multi-role users)
   */
  switchToDashboard: async function (roleId) {
    console.log(`🔄 Switching to role ID ${roleId}...`);

    const config = this.getDashboardConfig(roleId);
    if (!config) {
      console.error(`No dashboard for role ID ${roleId}`);
      return;
    }

    try {
      // Load script
      await this.loadDashboardScript(`/Kingsway/js/dashboards/${config.file}`);

      // Update global context
      window.CURRENT_DASHBOARD_ROLE = roleId;
      window.CURRENT_DASHBOARD_CONFIG = config;

      // Clear previous dashboard
      const mainContent =
        document.getElementById("mainContent") ||
        document.querySelector("main") ||
        document.querySelector(".container-fluid");
      if (mainContent) {
        mainContent.innerHTML = ""; // Clear old content
      }

      // Initialize new controller
      const controller = window[config.controller];
      if (this.isControllerLoaded(config.controller)) {
        controller.init();
        console.log(`✓ Switched to ${config.name}`);
      }
    } catch (error) {
      console.error("Error switching dashboard:", error);
    }
  },

  /**
   * Show error page
   */
  showErrorPage: function (message) {
    document.body.innerHTML = `
            <div class="container mt-5">
                <div class="alert alert-danger" role="alert">
                    <h4 class="alert-heading">Dashboard Error</h4>
                    <p>${message}</p>
                    <hr>
                    <p class="mb-0">
                        <a href="/Kingsway/home.php" class="btn btn-primary btn-sm">Back to Home</a>
                        <a href="/Kingsway/me.php" class="btn btn-secondary btn-sm">My Profile</a>
                    </p>
                </div>
            </div>
        `;
  },

  /**
   * Get dashboard info for current user
   * Useful for displaying role info on dashboard
   */
  getDashboardInfo: function () {
    return {
      currentRoleId: window.CURRENT_DASHBOARD_ROLE,
      allRoles: window.CURRENT_DASHBOARD_ROLES,
      config: window.CURRENT_DASHBOARD_CONFIG,
      isMultiRole:
        window.CURRENT_DASHBOARD_ROLES &&
        window.CURRENT_DASHBOARD_ROLES.length > 1,
    };
  },
};

// Auto-initialize on document ready
document.addEventListener('DOMContentLoaded', function() {
    // Only route if this is a dashboard page
    if (document.querySelector('[data-dashboard-page]') || 
        window.location.pathname.includes('dashboard')) {
        DashboardRouter.routeToDashboard();
    }
});
