/**
 * System Admin Console Controller
 * Generic CRUD controller for all 27 system administrator pages.
 * Reads data-resource / data-mode / data-title from the page container
 * and delegates to the matching window.API.system.* methods.
 */
(function () {
  "use strict";

  /* ================================================================
   * HELPERS
   * ================================================================ */

  function esc(s) {
    return String(s || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function notify(message, type) {
    if (typeof window.showNotification === "function") {
      window.showNotification(message, type);
    } else if (window.API && typeof window.API.showNotification === "function") {
      window.API.showNotification(message, type);
    }
  }

  function qs(root, sel) {
    return root ? root.querySelector(sel) : document.querySelector(sel);
  }

  function qsa(root, sel) {
    return root ? root.querySelectorAll(sel) : document.querySelectorAll(sel);
  }

  /* ================================================================
   * RESOURCE REGISTRY
   * Maps each data-resource value to its API accessor, columns, and
   * form-field definitions so one controller handles every page.
   * ================================================================ */

  var RESOURCES = {
    /* ---------- full CRUD resources ---------- */

    settings: {
      title: "System Settings",
      list: function (p) { return API.system.getSchoolConfig(p); },
      save: function (id, d) { return API.system.updateSchoolConfig(d); },
      del: null,
      columns: [
        { key: "key", label: "Key" },
        { key: "value", label: "Value" },
        { key: "description", label: "Description" },
        { key: "updated_at", label: "Updated" }
      ],
      fields: [
        { name: "key", label: "Key", type: "text", required: true },
        { name: "value", label: "Value", type: "textarea", required: true },
        { name: "description", label: "Description", type: "text" }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    },

    backups: {
      title: "Backups",
      list: function (p) { return API.system.getBackups(p); },
      save: function (id, d) { return API.system.createBackup(d); },
      del: function (id) { return API.system.deleteBackup(id); },
      columns: [
        { key: "id", label: "#" },
        { key: "filename", label: "Filename" },
        { key: "size", label: "Size", format: function (v) { return v ? (v / 1024 / 1024).toFixed(1) + " MB" : "—"; } },
        { key: "status", label: "Status", badge: true },
        { key: "created_at", label: "Created" }
      ],
      fields: [
        { name: "label", label: "Label", type: "text", placeholder: "Optional backup label" }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    },

    "feature-flags": {
      title: "Feature Flags",
      list: function (p) { return API.system.getFeatureFlags(p); },
      save: function (id, d) { return API.system.updateFeatureFlags(d); },
      del: null,
      columns: [
        { key: "key", label: "Flag" },
        { key: "name", label: "Name" },
        { key: "enabled", label: "Enabled", badge: true, badgeMap: { true: "success", false: "secondary" } },
        { key: "rollout_percentage", label: "Rollout %" },
        { key: "description", label: "Description" }
      ],
      fields: [
        { name: "key", label: "Flag Key", type: "text", required: true },
        { name: "name", label: "Name", type: "text", required: true },
        { name: "enabled", label: "Enabled", type: "select", options: [{ v: "1", t: "Yes" }, { v: "0", t: "No" }] },
        { name: "rollout_percentage", label: "Rollout %", type: "number", min: 0, max: 100 },
        { name: "description", label: "Description", type: "textarea" }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    },

    modules: {
      title: "Module Enablement",
      list: function (p) { return API.system.getModuleEnablement(p); },
      save: function (id, d) { return API.system.updateModuleEnablement(d); },
      del: null,
      columns: [
        { key: "key", label: "Module" },
        { key: "name", label: "Name" },
        { key: "enabled", label: "Enabled", badge: true, badgeMap: { true: "success", false: "secondary" } },
        { key: "description", label: "Description" }
      ],
      fields: [
        { name: "key", label: "Module Key", type: "text", required: true },
        { name: "name", label: "Name", type: "text", required: true },
        { name: "enabled", label: "Enabled", type: "select", options: [{ v: "1", t: "Yes" }, { v: "0", t: "No" }] },
        { name: "description", label: "Description", type: "textarea" }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    },

    retention: {
      title: "Data Retention",
      list: function (p) { return API.system.getDataRetention(p); },
      save: function (id, d) { return API.system.updateDataRetention(d); },
      del: null,
      columns: [
        { key: "key", label: "Setting" },
        { key: "value", label: "Value" },
        { key: "description", label: "Description" },
        { key: "updated_at", label: "Updated" }
      ],
      fields: [
        { name: "key", label: "Setting Key", type: "text", required: true },
        { name: "value", label: "Value (days)", type: "number", required: true },
        { name: "description", label: "Description", type: "text" }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    },

    "domain-isolation": {
      title: "Domain Isolation Rules",
      list: function (p) { return API.system.getDomainIsolation(p); },
      save: function (id, d) { return API.system.updateDomainIsolation(d); },
      del: null,
      columns: [
        { key: "domain", label: "Domain" },
        { key: "allowed", label: "Allowed", badge: true, badgeMap: { true: "success", false: "danger" } },
        { key: "description", label: "Description" },
        { key: "updated_at", label: "Updated" }
      ],
      fields: [
        { name: "domain", label: "Domain", type: "text", required: true },
        { name: "allowed", label: "Allowed", type: "select", options: [{ v: "1", t: "Yes" }, { v: "0", t: "No" }] },
        { name: "description", label: "Description", type: "text" }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    },

    "time-bound-access": {
      title: "Time-Bound Access",
      list: function (p) { return API.system.getTimeBoundAccess(p); },
      save: function (id, d) { return API.system.updateTimeBoundAccess(d); },
      del: null,
      columns: [
        { key: "id", label: "#" },
        { key: "role_name", label: "Role" },
        { key: "route", label: "Route" },
        { key: "start_time", label: "Start" },
        { key: "end_time", label: "End" },
        { key: "status", label: "Status", badge: true }
      ],
      fields: [
        { name: "role_name", label: "Role Name", type: "text", required: true },
        { name: "route", label: "Route", type: "text", required: true },
        { name: "start_time", label: "Start Time", type: "datetime-local" },
        { name: "end_time", label: "End Time", type: "datetime-local" },
        { name: "status", label: "Status", type: "select", options: [{ v: "active", t: "Active" }, { v: "inactive", t: "Inactive" }] }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    },

    maintenance: {
      title: "Maintenance Mode",
      list: function (p) { return API.system.getSchoolConfig(p); },
      save: function (id, d) { return API.system.updateSchoolConfig(d); },
      del: null,
      columns: [
        { key: "key", label: "Key" },
        { key: "value", label: "Value" },
        { key: "description", label: "Description" },
        { key: "updated_at", label: "Updated" }
      ],
      fields: [
        { name: "key", label: "Key", type: "text", required: true },
        { name: "value", label: "Value", type: "textarea", required: true },
        { name: "description", label: "Description", type: "text" }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    },

    routes: {
      title: "Route Registry",
      list: function (p) { return API.system.getRoutes(p); },
      save: function (id, d) {
        return id ? API.system.updateRoute(id, d) : API.system.createRoute(d);
      },
      del: function (id) { return API.system.deleteRoute(id); },
      columns: [
        { key: "id", label: "#" },
        { key: "method", label: "Method", badge: true, badgeMap: { GET: "info", POST: "success", PUT: "warning", DELETE: "danger" } },
        { key: "path", label: "Path" },
        { key: "controller", label: "Controller" },
        { key: "is_active", label: "Active", badge: true, badgeMap: { true: "success", false: "secondary", 1: "success", 0: "secondary" } },
        { key: "description", label: "Description" }
      ],
      fields: [
        { name: "method", label: "HTTP Method", type: "select", required: true, options: [{ v: "GET", t: "GET" }, { v: "POST", t: "POST" }, { v: "PUT", t: "PUT" }, { v: "DELETE", t: "DELETE" }] },
        { name: "path", label: "Path", type: "text", required: true, placeholder: "/api/example" },
        { name: "controller", label: "Controller", type: "text", placeholder: "Controller@method" },
        { name: "middleware", label: "Middleware", type: "text", placeholder: "auth,throttle" },
        { name: "is_active", label: "Active", type: "select", options: [{ v: "1", t: "Yes" }, { v: "0", t: "No" }] },
        { name: "description", label: "Description", type: "text" }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    },

    "route-rules": {
      title: "Route Access Rules",
      list: function (p) { return API.system.getRouteAccessRules(p); },
      save: function (id, d) {
        return id ? API.system.updateRouteAccessRule(id, d) : API.system.createRouteAccessRule(d);
      },
      del: function (id) { return API.system.deleteRouteAccessRule(id); },
      columns: [
        { key: "id", label: "#" },
        { key: "route", label: "Route" },
        { key: "role_name", label: "Role" },
        { key: "policy", label: "Policy" },
        { key: "is_active", label: "Active", badge: true, badgeMap: { true: "success", false: "secondary", 1: "success", 0: "secondary" } }
      ],
      fields: [
        { name: "route", label: "Route", type: "text", required: true, placeholder: "/api/example" },
        { name: "role_name", label: "Role Name", type: "text", required: true },
        { name: "policy", label: "Policy", type: "select", options: [{ v: "allow", t: "Allow" }, { v: "deny", t: "Deny" }, { v: "role_only", t: "Role Only" }] },
        { name: "is_active", label: "Active", type: "select", options: [{ v: "1", t: "Yes" }, { v: "0", t: "No" }] }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    },

    "role-navigation": {
      title: "Role Navigation",
      list: function (p) { return API.system.getRoleNavigation(p); },
      save: null,
      del: null,
      readonly: true,
      columns: [
        { key: "role_name", label: "Role" },
        { key: "section", label: "Section" },
        { key: "menu_label", label: "Menu Item" },
        { key: "route", label: "Route" },
        { key: "status", label: "Status", badge: true }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    },

    policies: {
      title: "Permission Policies",
      list: function (p) { return API.system.getPermissionPolicies(p); },
      save: function (id, d) {
        return id ? API.system.updatePermissionPolicy(id, d) : API.system.createPermissionPolicy(d);
      },
      del: function (id) { return API.system.deletePermissionPolicy(id); },
      columns: [
        { key: "id", label: "#" },
        { key: "name", label: "Name" },
        { key: "description", label: "Description" },
        { key: "rules_count", label: "Rules" },
        { key: "status", label: "Status", badge: true }
      ],
      fields: [
        { name: "name", label: "Name", type: "text", required: true },
        { name: "description", label: "Description", type: "textarea" },
        { name: "rules", label: "Rules (JSON)", type: "textarea", placeholder: '{"permission":"view","resource":"*"}' },
        { name: "status", label: "Status", type: "select", options: [{ v: "active", t: "Active" }, { v: "inactive", t: "Inactive" }] }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    },

    "sidebar-menus": {
      title: "Role Navigation",
      list: function (p) { return API.system.getSidebarMenus(p); },
      save: null,
      del: null,
      readonly: true,
      columns: [
        { key: "role_name", label: "Role" },
        { key: "section", label: "Section" },
        { key: "menu_label", label: "Menu Item" },
        { key: "route", label: "Route" },
        { key: "status", label: "Status", badge: true }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    },

    dashboards: {
      title: "Dashboard Registry",
      list: function (p) { return API.system.getDashboards(p); },
      save: function (id, d) {
        return id ? API.system.updateDashboard(id, d) : API.system.createDashboard(d);
      },
      del: function (id) { return API.system.deleteDashboard(id); },
      columns: [
        { key: "id", label: "#" },
        { key: "key", label: "Key" },
        { key: "name", label: "Name" },
        { key: "domain", label: "Domain", badge: true },
        { key: "component", label: "Component" },
        { key: "role_name", label: "Roles" },
        { key: "status", label: "Status", badge: true }
      ],
      fields: [
        { name: "key", label: "Dashboard Key", type: "text", required: true, placeholder: "e.g. director_dashboard" },
        { name: "name", label: "Display Name", type: "text", required: true },
        { name: "description", label: "Description", type: "textarea" },
        { name: "domain", label: "Domain", type: "select", options: [{ v: "SCHOOL", t: "SCHOOL" }, { v: "SYSTEM", t: "SYSTEM" }] },
        { name: "status", label: "Status", type: "select", options: [{ v: "active", t: "Active" }, { v: "inactive", t: "Inactive" }] }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    },

    widgets: {
      title: "Widget Registry",
      list: function (p) { return API.system.getWidgets(p); },
      save: function (id, d) {
        return id ? API.system.updateWidget(id, d) : API.system.createWidget(d);
      },
      del: function (id) { return API.system.deleteWidget(id); },
      columns: [
        { key: "id", label: "#" },
        { key: "key", label: "Key" },
        { key: "name", label: "Name" },
        { key: "type", label: "Type", badge: true },
        { key: "permission", label: "Permission" },
        { key: "description", label: "Description" },
        { key: "status", label: "Status", badge: true }
      ],
      fields: [
        { name: "key", label: "Widget Key", type: "text", required: true, placeholder: "e.g. student_count" },
        { name: "name", label: "Widget Name", type: "text", required: true },
        { name: "type", label: "Type", type: "select", options: [
          { v: "chart", t: "Chart" }, { v: "stat", t: "Stat" },
          { v: "table", t: "Table" }, { v: "list", t: "List" }, { v: "custom", t: "Custom" }
        ] },
        { name: "permission", label: "Required Permission", type: "text", placeholder: "e.g. dashboard.view" },
        { name: "description", label: "Description", type: "textarea" },
        { name: "status", label: "Status", type: "select", options: [{ v: "active", t: "Active" }, { v: "inactive", t: "Inactive" }] }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    },

    webhooks: {
      title: "Webhook Registry",
      list: function (p) { return API.system.getWebhookRegistry(p); },
      save: function (id, d) {
        return id ? API.system.updateWebhook(id, d) : API.system.createWebhook(d);
      },
      del: function (id) { return API.system.deleteWebhook(id); },
      columns: [
        { key: "id", label: "#" },
        { key: "name", label: "Name" },
        { key: "url", label: "URL" },
        { key: "events", label: "Events" },
        { key: "is_active", label: "Active", badge: true, badgeMap: { true: "success", false: "secondary", 1: "success", 0: "secondary" } },
        { key: "last_triggered", label: "Last Triggered" }
      ],
      fields: [
        { name: "name", label: "Name", type: "text", required: true },
        { name: "url", label: "URL", type: "url", required: true, placeholder: "https://..." },
        { name: "secret", label: "Secret", type: "text" },
        { name: "events", label: "Events", type: "text", placeholder: "comma-separated event names" },
        { name: "is_active", label: "Active", type: "select", options: [{ v: "1", t: "Yes" }, { v: "0", t: "No" }] }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    },

    incidents: {
      title: "Security Incidents",
      list: function (p) { return API.system.getSecurityIncidents(p); },
      save: null,
      del: null,
      readonly: true,
      columns: [
        { key: "id", label: "#" },
        { key: "action", label: "Event", badge: true, badgeMap: { security_incident: "danger", unauthorized_access: "danger", permission_denied: "warning", failed_login: "warning", login_failed: "warning" } },
        { key: "username", label: "User" },
        { key: "entity", label: "Entity" },
        { key: "entity_id", label: "Entity ID" },
        { key: "details", label: "Details", format: function (v) { return (v !== null && typeof v === "object") ? JSON.stringify(v) : v; } },
        { key: "status", label: "Status", badge: true, badgeMap: { success: "success", failure: "danger" } },
        { key: "created_at", label: "Reported" }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    },

    violations: {
      title: "Policy Violations",
      list: function (p) { return API.system.getPolicyViolations(p); },
      save: null,
      del: null,
      readonly: true,
      columns: [
        { key: "id", label: "#" },
        { key: "action", label: "Event", badge: true, badgeMap: { policy_violation: "danger", permission_denied: "warning", rbac_denied: "warning", access_denied: "warning" } },
        { key: "username", label: "User" },
        { key: "entity", label: "Entity" },
        { key: "entity_id", label: "Entity ID" },
        { key: "details", label: "Details", format: function (v) { return (v !== null && typeof v === "object") ? JSON.stringify(v) : v; } },
        { key: "status", label: "Status", badge: true, badgeMap: { success: "success", failure: "danger" } },
        { key: "created_at", label: "Date" }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    },

    migrations: {
      title: "Migrations",
      list: function (p) { return API.system.getMigrations(p); },
      save: function (id, d) { return API.system.runMigration(d); },
      del: null,
      readonly: false,
      columns: [
        { key: "id", label: "#" },
        { key: "name", label: "Migration" },
        { key: "batch", label: "Batch" },
        { key: "status", label: "Status", badge: true, badgeMap: { pending: "warning", migrated: "success", failed: "danger" } },
        { key: "ran_at", label: "Ran At" }
      ],
      fields: [
        { name: "migration", label: "Migration Name", type: "text", required: true, placeholder: "e.g. 2024_01_01_000000_add_users_table" }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    },

    /* ---------- readonly resources ---------- */

    health: {
      title: "System Health",
      list: function (p) { return API.system.getHealth(p); },
      save: null,
      del: null,
      readonly: true,
      columns: [
        { key: "component", label: "Component" },
        { key: "status", label: "Status", badge: true, badgeMap: { healthy: "success", degraded: "warning", down: "danger" } },
        { key: "latency_ms", label: "Latency (ms)" },
        { key: "message", label: "Message" },
        { key: "checked_at", label: "Checked" }
      ],
      extract: function (r) {
        if (r && typeof r === "object" && !Array.isArray(r)) {
          if (r.data && typeof r.data === "object" && !Array.isArray(r.data)) {
            return Object.keys(r.data).map(function (k) {
              var v = r.data[k];
              return { component: k, status: v.status || v.state || "unknown", latency_ms: v.latency_ms || v.latency || "—", message: v.message || v.error || "—" };
            });
          }
          return Object.keys(r).map(function (k) {
            var v = r[k];
            if (typeof v === "object" && v !== null) {
              return { component: k, status: v.status || v.state || "unknown", latency_ms: v.latency_ms || v.latency || "—", message: v.message || v.error || "—" };
            }
            return null;
          }).filter(Boolean);
        }
        return Array.isArray(r) ? r : [];
      }
    },

    diagnostics: {
      title: "System Diagnostics",
      list: function (p) { return API.system.getDiagnostics(p); },
      save: null,
      del: null,
      readonly: true,
      columns: [
        { key: "key", label: "Check" },
        { key: "value", label: "Value" },
        { key: "status", label: "Status", badge: true, badgeMap: { ok: "success", warning: "warning", error: "danger", pass: "success", fail: "danger" } }
      ],
      extract: function (r) {
        if (r && typeof r === "object" && !Array.isArray(r)) {
          if (r.data && typeof r.data === "object" && !Array.isArray(r.data)) {
            return Object.keys(r.data).map(function (k) {
              var v = r.data[k];
              return { key: k, value: typeof v === "object" ? JSON.stringify(v) : String(v), status: "ok" };
            });
          }
          return Object.keys(r).map(function (k) {
            var v = r[k];
            return { key: k, value: typeof v === "object" ? JSON.stringify(v) : String(v), status: "ok" };
          });
        }
        return Array.isArray(r) ? r : [];
      }
    },

    "audit-logs": {
      title: "Activity Audit Logs",
      list: function (p) { return API.system.getActivityAuditLogs(p); },
      save: null,
      del: null,
      readonly: true,
      columns: [
        { key: "id", label: "#" },
        { key: "user_name", label: "User" },
        { key: "action", label: "Action" },
        { key: "resource_type", label: "Resource" },
        { key: "resource_id", label: "Resource ID" },
        { key: "ip_address", label: "IP" },
        { key: "created_at", label: "Time" }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    },

    "error-logs": {
      title: "Error Logs",
      list: function (p) { return API.system.getErrorLogs(p); },
      save: null,
      del: null,
      readonly: true,
      columns: [
        { key: "id", label: "#" },
        { key: "level", label: "Level", badge: true, badgeMap: { error: "danger", warning: "warning", info: "info", critical: "danger" } },
        { key: "message", label: "Message" },
        { key: "file", label: "File" },
        { key: "created_at", label: "Time" }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    },

    "api-metrics": {
      title: "API Metrics",
      list: function (p) { return API.system.getApiMetrics(p); },
      save: null,
      del: null,
      readonly: true,
      columns: [
        { key: "endpoint", label: "Endpoint" },
        { key: "method", label: "Method" },
        { key: "count", label: "Requests" },
        { key: "avg_ms", label: "Avg (ms)" },
        { key: "p95_ms", label: "P95 (ms)" },
        { key: "error_rate", label: "Error Rate" }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    },

    "rate-limits": {
      title: "Rate Limiting",
      list: function (p) { return API.system.getRateLimiting(p); },
      save: null,
      del: null,
      readonly: true,
      columns: [
        { key: "status", label: "Status", badge: true, badgeMap: { active: "success", throttled: "warning" } },
        { key: "window", label: "Window (sec)" },
        { key: "max_requests", label: "Max Requests" },
        { key: "uptime", label: "Uptime" },
        { key: "source", label: "Source" },
        { key: "timestamp", label: "Timestamp" }
      ],
      extract: function (r) { var d = r && r.data ? r.data : r; return d ? [d] : []; }
    },

    "permission-changes": {
      title: "Permission Changes",
      list: function (p) { return API.system.getPermissionChanges(p); },
      save: null,
      del: null,
      readonly: true,
      columns: [
        { key: "id", label: "#" },
        { key: "action", label: "Action", badge: true, badgeMap: { permission_create: "info", permission_update: "warning", permission_delete: "danger", permission_definition_create: "info", permission_definition_update: "warning", permission_definition_delete: "danger", role_permission_assign: "success", role_permission_remove: "danger", role_create: "info", role_update: "warning", role_delete: "danger", role_assigned: "success", role_revoked: "danger", permission_assign: "success", permission_revoke: "danger" } },
        { key: "entity", label: "Entity" },
        { key: "entity_id", label: "Entity ID" },
        { key: "username", label: "User" },
        { key: "status", label: "Status", badge: true, badgeMap: { success: "success", failure: "danger" } },
        { key: "created_at", label: "Date" }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    },

    jobs: {
      title: "Background Jobs",
      list: function (p) { return API.system.getBackgroundJobs(p); },
      save: null,
      del: null,
      readonly: true,
      columns: [
        { key: "id", label: "#" },
        { key: "queue", label: "Queue" },
        { key: "payload_type", label: "Type" },
        { key: "status", label: "Status", badge: true, badgeMap: { pending: "secondary", processing: "info", done: "success", completed: "success", failed: "danger", dead_letter: "dark", cancelled: "warning", queued: "secondary", retrying: "info" } },
        { key: "attempts", label: "Attempts" },
        { key: "max_attempts", label: "Max" },
        { key: "backoff_seconds", label: "Backoff (s)" },
        { key: "last_error", label: "Last Error" },
        { key: "dead_letter_reason", label: "Dead Letter" },
        { key: "created_at", label: "Created" },
        { key: "completed_at", label: "Completed" }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    },

    "job-inspector": {
      title: "Job Inspector",
      list: function (p) { return API.system.getJobInspector(p); },
      save: null,
      del: null,
      readonly: true,
      columns: [
        { key: "id", label: "#" },
        { key: "queue", label: "Queue" },
        { key: "payload_type", label: "Type" },
        { key: "status", label: "Status", badge: true, badgeMap: { pending: "secondary", processing: "info", done: "success", completed: "success", failed: "danger", dead_letter: "dark", cancelled: "warning", queued: "secondary", retrying: "info" } },
        { key: "attempts", label: "Attempts" },
        { key: "max_attempts", label: "Max" },
        { key: "last_error", label: "Exception" },
        { key: "dead_letter_reason", label: "Dead Letter" },
        { key: "available_at", label: "Available" },
        { key: "created_at", label: "Created" }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    },

    "api-explorer": {
      title: "API Explorer",
      list: function (p) { return API.system.getRoutes(p); },
      save: null,
      del: null,
      readonly: true,
      columns: [
        { key: "method", label: "Method", badge: true, badgeMap: { GET: "info", POST: "success", PUT: "warning", DELETE: "danger" } },
        { key: "path", label: "Path" },
        { key: "controller", label: "Controller" },
        { key: "middleware", label: "Middleware" },
        { key: "is_active", label: "Active", badge: true, badgeMap: { true: "success", false: "secondary", 1: "success", 0: "secondary" } }
      ],
      extract: function (r) { return Array.isArray(r) ? r : r && r.data ? r.data : []; }
    }
  };

  /* ================================================================
   * CONTROLLER
   * ================================================================ */

  var ctrl = {
    initialized: false,
    container: null,
    resource: "",
    mode: "",
    title: "",
    config: null,
    records: [],
    filtered: [],
    search: "",
    currentPage: 1,
    pageSize: 25,
    editingId: null,
    modal: null,
    eventsBound: false,
    liveEnabled: false,
    liveTimer: null,
    loadingData: false,

    /* ---------- bootstrap ---------- */

    init: function () {
      this.container = qs(document, "[data-system-admin-page]");
      if (!this.container) return;

      this.resource = this.container.getAttribute("data-resource") || "";
      this.mode = this.container.getAttribute("data-mode") || "crud";
      this.title = this.container.getAttribute("data-title") || "System Admin";
      this.config = RESOURCES[this.resource] || null;

      if (!this.config) {
        this.setState("error", "Unknown resource: " + this.resource);
        return;
      }

      if (this.config.readonly || this.mode === "readonly") {
        this.mode = "readonly";
      }

      this.bindPageEvents();
      this.loadData();
    },

    /* ---------- event binding ---------- */

    bindPageEvents: function () {
      if (this.eventsBound) return;
      this.eventsBound = true;

      var self = this;

      /* create button */
      var createBtn = qs(this.container, "[data-system-create]");
      if (createBtn) {
        if (this.mode === "readonly") {
          createBtn.style.display = "none";
        } else {
          createBtn.addEventListener("click", function () { self.showForm(); });
        }
      }

      /* refresh button */
      var refreshBtn = qs(this.container, "[data-system-refresh]");
      if (refreshBtn) {
        refreshBtn.addEventListener("click", function () { self.loadData(); });
      }

      /* live toggle button */
      var liveBtn = qs(this.container, "[data-system-live]");
      if (liveBtn) {
        liveBtn.addEventListener("click", function () { self.toggleLive(); });
      }
      document.addEventListener("visibilitychange", function () {
        if (document.hidden && self.liveEnabled) {
          self.stopLive();
        } else if (!document.hidden && self.liveEnabled) {
          self.startLive();
          self.loadData(true);
        }
      });
      window.addEventListener("pagehide", function () { self.stopLive(); });

      /* search input */
      var searchInput = qs(this.container, "[data-system-search]");
      if (searchInput) {
        searchInput.addEventListener("input", function () {
          self.search = searchInput.value;
          self.currentPage = 1;
          self.applyFilter();
          self.render();
        });
      }

      /* modal form submit */
      var form = qs(document, "[data-system-form]");
      if (form) {
        form.addEventListener("submit", function (e) {
          e.preventDefault();
          self.saveRecord();
        });
      }

      /* auto-init on every page load */
      if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function () { self.init(); });
      }
    },

    /* ---------- data loading ---------- */

    loadData: async function (quiet) {
      if (quiet && this.loadingData) return;
      this.loadingData = true;
      if (!quiet) this.setState("loading");
      try {
        var params = { page: this.currentPage, limit: this.pageSize };
        if (this.search) params.search = this.search;

        var response = null;
        if (this.config.list) {
          response = await this.config.list(params);
        }

        var raw = this.config.extract ? this.config.extract(response) : (Array.isArray(response) ? response : (response && response.data ? response.data : []));
        this.records = Array.isArray(raw) ? raw : [];
        this.applyFilter();
        this.renderSummary();
        this.render();
      } catch (err) {
        console.error("[SystemAdminConsole] Load failed:", err);
        if (!quiet) {
          this.setState("error", err.message || "Failed to load data");
          notify(err.message || "Failed to load data", "error");
        }
      } finally {
        this.loadingData = false;
      }
    },

    toggleLive: function () {
      this.liveEnabled = !this.liveEnabled;
      var liveBtn = qs(this.container, "[data-system-live]");
      if (liveBtn) {
        if (this.liveEnabled) {
          liveBtn.classList.add("btn-success");
          liveBtn.classList.remove("btn-outline-secondary");
          liveBtn.innerHTML =
            '<span class="spinner-grow spinner-grow-sm me-1" aria-hidden="true"></span>Live';
        } else {
          liveBtn.classList.remove("btn-success");
          liveBtn.classList.add("btn-outline-secondary");
          liveBtn.innerHTML =
            '<i class="bi bi-broadcast me-1"></i> Live';
        }
      }
      if (this.liveEnabled) {
        this.startLive();
        this.loadData(true);
      } else {
        this.stopLive();
      }
    },

    startLive: function () {
      var self = this;
      this.stopLive();
      this.liveTimer = window.setInterval(function () {
        if (document.hidden) return;
        self.loadData(true);
      }, 5000);
    },

    stopLive: function () {
      if (this.liveTimer) {
        window.clearInterval(this.liveTimer);
        this.liveTimer = null;
      }
    },

    applyFilter: function () {
      var term = (this.search || "").toLowerCase();
      if (!term) {
        this.filtered = this.records.slice();
        return;
      }
      this.filtered = this.records.filter(function (rec) {
        return Object.keys(rec).some(function (k) {
          var v = rec[k];
          return v !== null && v !== undefined && String(v).toLowerCase().indexOf(term) !== -1;
        });
      });
    },

    /* ---------- rendering ---------- */

    renderSummary: function () {
      var el = qs(this.container, "[data-system-summary]");
      if (!el) return;

      var total = this.records.length;
      el.innerHTML =
        '<div class="col-md-3">' +
          '<div class="card border-0 shadow-sm">' +
            '<div class="card-body text-center">' +
              '<div class="text-muted small">Total Records</div>' +
              '<div class="fs-4 fw-bold">' + total + '</div>' +
            '</div>' +
          '</div>' +
        '</div>';
    },

    render: function () {
      var head = qs(this.container, "[data-system-head]");
      var body = qs(this.container, "[data-system-body]");
      var count = qs(this.container, "[data-system-count]");
      var stateEl = qs(this.container, "[data-system-state]");
      if (stateEl) stateEl.style.display = "none";

      var columns = this.config ? this.config.columns : [];
      var isReadonly = this.mode === "readonly";

      /* header */
      if (head) {
        var headerHtml = "<tr><th scope='col'>#</th>";
        columns.forEach(function (col) {
          headerHtml += '<th scope="col">' + esc(col.label) + '</th>';
        });
        if (!isReadonly && this.config && this.config.del) {
          headerHtml += '<th scope="col">Actions</th>';
        }
        headerHtml += "</tr>";
        head.innerHTML = headerHtml;
      }

      /* body */
      if (body) {
        var data = this.filtered || [];
        var start = (this.currentPage - 1) * this.pageSize;
        var page = data.slice(start, start + this.pageSize);

        if (!page.length) {
          var colSpan = columns.length + 1;
          if (!isReadonly) colSpan += 1;
          body.innerHTML = '<tr><td colspan="' + colSpan + '" class="text-center py-4 text-muted">No records found.</td></tr>';
        } else {
          var self = this;
          body.innerHTML = page.map(function (rec, idx) {
            var html = "<tr><td>" + (start + idx + 1) + "</td>";
            columns.forEach(function (col) {
              var val = rec[col.key] !== undefined ? rec[col.key] : rec[col.key.replace(/_/g, "")] || "—";
              if (col.format) {
                val = col.format(val, rec);
              } else if (col.badge) {
                var badgeVal = String(val).toLowerCase();
                var badgeClass = "secondary";
                if (col.badgeMap && col.badgeMap[badgeVal] !== undefined) {
                  badgeClass = col.badgeMap[badgeVal];
                } else if (badgeVal === "active" || badgeVal === "true" || badgeVal === "1" || badgeVal === "success" || badgeVal === "healthy" || badgeVal === "ok" || badgeVal === "pass" || badgeVal === "migrated" || badgeVal === "granted" || badgeVal === "completed") {
                  badgeClass = "success";
                } else if (badgeVal === "inactive" || badgeVal === "false" || badgeVal === "0" || badgeVal === "secondary" || badgeVal === "pending") {
                  badgeClass = "secondary";
                } else if (badgeVal === "error" || badgeVal === "danger" || badgeVal === "down" || badgeVal === "fail" || badgeVal === "failed" || badgeVal === "critical" || badgeVal === "revoked") {
                  badgeClass = "danger";
                } else if (badgeVal === "warning" || badgeVal === "degraded" || badgeVal === "throttled" || badgeVal === "high" || badgeVal === "investigating" || badgeVal === "deprecated") {
                  badgeClass = "warning";
                } else if (badgeVal === "info" || badgeVal === "medium" || badgeVal === "processing") {
                  badgeClass = "info";
                }
                val = '<span class="badge bg-' + badgeClass + '">' + esc(val || "—") + "</span>";
              } else {
                val = esc(String(val));
              }
              html += "<td>" + val + "</td>";
            });

            if (!isReadonly && self.config.del) {
              html += '<td><div class="btn-group btn-group-sm">';
              if (self.config.fields && self.config.fields.length) {
                html += '<button type="button" class="btn btn-outline-info" title="Edit" onclick="SystemAdminConsole.editRecord(\'' + rec.id + "')\">" +
                  '<i class="bi bi-pencil"></i></button>';
              }
              html += '<button type="button" class="btn btn-outline-danger" title="Delete" onclick="SystemAdminConsole.deleteRecord(\'' + rec.id + "')\">" +
                '<i class="bi bi-trash"></i></button>';
              html += "</div></td>";
            }

            html += "</tr>";
            return html;
          }).join("");
        }
      }

      /* count footer */
      if (count) {
        var showing = Math.min(start + this.pageSize, this.filtered.length);
        count.textContent = "Showing " + (this.filtered.length ? start + 1 : 0) + "–" + showing + " of " + this.filtered.length + " records" +
          (this.search ? ' (filtered from ' + this.records.length + ")" : "");
      }
    },

    setState: function (state, message) {
      var el = qs(this.container, "[data-system-state]");
      if (!el) return;

      if (state === "loading") {
        el.className = "alert alert-info";
        el.textContent = "Loading " + this.title + "...";
        el.style.display = "";
      } else if (state === "error") {
        el.className = "alert alert-danger";
        el.textContent = message || "An error occurred";
        el.style.display = "";
      } else {
        el.style.display = "none";
      }
    },

    /* ---------- modal / form ---------- */

    showForm: function (recordId) {
      if (this.mode === "readonly" || !this.config || !this.config.fields) return;

      this.editingId = recordId || null;

      var modalEl = document.getElementById("systemAdminRecordModal");
      if (!modalEl) return;

      /* set title */
      var titleEl = qs(modalEl, "[data-system-modal-title]");
      if (titleEl) {
        titleEl.textContent = (this.editingId ? "Edit " : "Add ") + this.title;
      }

      /* render fields */
      var fieldsEl = qs(modalEl, "[data-system-form-fields]");
      if (fieldsEl) {
        var self = this;
        var record = null;
        if (this.editingId) {
          record = this.records.find(function (r) { return String(r.id) === String(self.editingId); }) || {};
        }
        fieldsEl.innerHTML = this.config.fields.map(function (f) {
          var val = record ? (record[f.name] !== undefined ? record[f.name] : "") : (f.default !== undefined ? f.default : "");
          var html = '<div class="mb-3">';
          html += '<label class="form-label">' + esc(f.label) + (f.required ? ' <span class="text-danger">*</span>' : "") + "</label>";

          if (f.type === "select") {
            html += '<select class="form-select" name="' + esc(f.name) + '"' + (f.required ? " required" : "") + ">";
            (f.options || []).forEach(function (opt) {
              var sel = String(val) === String(opt.v) ? " selected" : "";
              html += '<option value="' + esc(opt.v) + '"' + sel + ">" + esc(opt.t) + "</option>";
            });
            html += "</select>";
          } else if (f.type === "textarea") {
            html += '<textarea class="form-control" name="' + esc(f.name) + '"' +
              (f.required ? " required" : "") +
              (f.placeholder ? ' placeholder="' + esc(f.placeholder) + '"' : "") +
              ' rows="3">' + esc(val) + "</textarea>";
          } else {
            var inputType = f.type || "text";
            html += '<input type="' + esc(inputType) + '" class="form-control" name="' + esc(f.name) + '" value="' + esc(val) + '"' +
              (f.required ? " required" : "") +
              (f.placeholder ? ' placeholder="' + esc(f.placeholder) + '"' : "") +
              (f.min !== undefined ? ' min="' + f.min + '"' : "") +
              (f.max !== undefined ? ' max="' + f.max + '"' : "") +
              (f.step ? ' step="' + f.step + '"' : "") + ">";
          }

          html += "</div>";
          return html;
        }).join("");
      }

      /* show modal */
      if (!this.modal) {
        this.modal = new bootstrap.Modal(modalEl);
      }
      this.modal.show();
    },

    saveRecord: async function () {
      if (!this.config || !this.config.save) return;

      var modalEl = document.getElementById("systemAdminRecordModal");
      var formEl = qs(modalEl, "[data-system-form]");
      if (!formEl || !formEl.checkValidity()) {
        if (formEl) formEl.reportValidity();
        return;
      }

      var data = {};
      this.config.fields.forEach(function (f) {
        var el = formEl.querySelector('[name="' + f.name + '"]');
        if (el) {
          var v = el.value;
          if (f.type === "number") v = v === "" ? null : Number(v);
          data[f.name] = v;
        }
      });

      try {
        await this.config.save(this.editingId, data);
        notify(this.editingId ? "Record updated" : "Record created", "success");
        this.modal.hide();
        this.editingId = null;
        this.loadData();
      } catch (err) {
        console.error("[SystemAdminConsole] Save failed:", err);
        notify(err.message || "Failed to save record", "error");
      }
    },

    editRecord: function (id) {
      this.showForm(id);
    },

    deleteRecord: async function (id) {
      if (!this.config || !this.config.del) return;
      if (!(await window.confirmAction("Confirm Deletion", "Are you sure you want to delete this record? This action cannot be undone.", { confirmText: "Delete", danger: true }))) return;

      try {
        await this.config.del(id);
        notify("Record deleted", "success");
        this.loadData();
      } catch (err) {
        console.error("[SystemAdminConsole] Delete failed:", err);
        notify(err.message || "Failed to delete record", "error");
      }
    }
  };

  /* expose globally */
  window.SystemAdminConsole = ctrl;

  /* auto-initialize */
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () { ctrl.init(); });
  } else {
    ctrl.init();
  }
})();
