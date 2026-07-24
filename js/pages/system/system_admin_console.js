/**
 * Shared renderer for System Administrator list/configuration pages.
 * Every resource is routed to its canonical domain API; this file does not own
 * an API namespace or a generic backend resource endpoint.
 */
(() => {
  "use strict";
  const root = document.querySelector("[data-system-admin-page]");
  if (!root) return;

  const resource = root.dataset.resource;
  const mode = root.dataset.mode || "readonly";
  const title = root.dataset.title || "System Administration";
  const state = root.querySelector("[data-system-state]");
  const head = root.querySelector("[data-system-head]");
  const body = root.querySelector("[data-system-body]");
  const count = root.querySelector("[data-system-count]");
  const summary = root.querySelector("[data-system-summary]");
  const search = root.querySelector("[data-system-search]");
  const createButton = root.querySelector("[data-system-create]");
  const refreshButton = root.querySelector("[data-system-refresh]");
  const modalElement = document.getElementById("systemAdminRecordModal");
  const form = modalElement?.querySelector("[data-system-form]");
  const fieldsContainer = modalElement?.querySelector("[data-system-form-fields]");
  const modalTitle = modalElement?.querySelector("[data-system-modal-title]");
  const saveButton = modalElement?.querySelector("[data-system-save]");
  let modal = null;
  let rows = [];
  let schema = [];
  let editingId = null;

  const esc = (v) => String(v ?? "").replace(/[&<>'"]/g, c => ({
    "&":"&amp;","<":"&lt;",">":"&gt;","'":"&#39;",'"':"&quot;"
  })[c]);

  const readers = {
    users: () => API.users.index(),
    accounts: () => API.system.getAccountStatuses(),
    sessions: () => API.system.getActiveSessions(),
    "audit-logs": () => API.system.getActivityAuditLogs(),
    "api-explorer": () => API.system.getRoutes(),
    "api-metrics": () => API.system.getApiMetrics(),
    "authentication-logs": () => API.system.getAuthenticationLogs(),
    jobs: () => API.system.getBackgroundJobs(),
    backups: () => API.system.getBackups(),
    retention: () => API.system.getDataRetention(),
    "domain-isolation": () => API.system.getDomainIsolation(),
    "error-logs": () => API.system.getErrorLogs(),
    "failed-logins": () => API.system.getFailedLogins(),
    "feature-flags": () => API.system.getFeatureFlags(),
    "job-inspector": () => API.system.getJobInspector(),
    maintenance: () => API.maintenance.getConfig(),
    roles: () => API.system.getRoles(),
    migrations: () => API.system.getMigrations(),
    modules: () => API.system.getModuleEnablement(),
    "permission-changes": () => API.system.getPermissionChanges(),
    policies: () => API.system.getPermissionPolicies(),
    violations: () => API.system.getPolicyViolations(),
    "rate-limits": () => API.system.getRateLimiting(),
    permissions: () => API.system.getResourcePermissions(),
    "role-navigation": () => API.system.getRoleNavigation(),
    "role-permission-matrix": async () => ({ rows: await API.system.getRoles() }),
    "route-rules": () => API.system.getRouteAccessRules(),
    routes: () => API.system.getRoutes(),
    incidents: () => API.system.getSecurityIncidents(),
    "sidebar-menus": () => API.system.getSidebarMenus(),
    diagnostics: () => API.system.getDiagnostics(),
    health: () => API.system.getHealth(),
    settings: () => API.system.getSchoolConfig(),
    "time-bound-access": () => API.system.getTimeBoundAccess(),
    webhooks: () => API.system.getWebhookRegistry(),
  };

  const writers = {
    users: (record, id) => id ? API.users.update(id, record) : API.users.create(record),
    accounts: (record, id) => API.system.updateAccountStatus(id || record.user_id, record),
    maintenance: (record) => API.maintenance.updateConfig(record),
    roles: (record, id) => id ? API.system.updateRole(id, record) : API.system.createRole(record),
    retention: (record) => API.system.updateDataRetention(record),
    "domain-isolation": (record) => API.system.updateDomainIsolation(record),
    "feature-flags": (record) => API.system.updateFeatureFlags(record),
    modules: (record) => API.system.updateModuleEnablement(record),
    policies: (record, id) => id ? API.system.updatePermissionPolicy(id, record) : API.system.createPermissionPolicy(record),
    permissions: (record, id) => id ? API.system.updatePermission(id, record) : API.system.createPermission(record),
    "route-rules": (record, id) => id ? API.system.updateRouteAccessRule(id, record) : API.system.createRouteAccessRule(record),
    routes: (record, id) => id ? API.system.updateRoute(id, record) : API.system.createRoute(record),
    "sidebar-menus": (record, id) => id ? API.system.updateSidebarMenu(id, record) : API.system.createSidebarMenu(record),
    settings: (record) => API.system.updateSchoolConfig(record),
    "time-bound-access": (record) => API.system.updateTimeBoundAccess(record),
    webhooks: (record, id) => id ? API.system.updateWebhook(id, record) : API.system.createWebhook(record),
  };

  const deleters = {
    users: (id) => API.users.delete(id),
    backups: (id) => API.system.deleteBackup(id),
    roles: (id) => API.system.deleteRole(id),
    policies: (id) => API.system.deletePermissionPolicy(id),
    permissions: (id) => API.system.deletePermission(id),
    "route-rules": (id) => API.system.deleteRouteAccessRule(id),
    routes: (id) => API.system.deleteRoute(id),
    "sidebar-menus": (id) => API.system.deleteSidebarMenu(id),
    webhooks: (id) => API.system.deleteWebhook(id),
  };
  const defaultSchemas = {
    users: [
      {name:"username",required:true},{name:"email",type:"email",required:true},
      {name:"first_name",required:true},{name:"last_name",required:true},
      {name:"password",type:"password",list:false},{name:"status",type:"select",options:["active","inactive","suspended"]},
    ],
    accounts: [
      {name:"username",editable:false},{name:"email",editable:false},{name:"status",type:"select",options:["active","inactive","suspended"]},
      {name:"failed_login_attempts",type:"number"},{name:"account_locked_until",type:"datetime-local"},{name:"force_password_change",type:"boolean"},
    ],
    roles: [{name:"name",required:true},{name:"description",type:"textarea"},{name:"scope",type:"select",options:["system","school"]},{name:"is_system",type:"boolean"},{name:"is_active",type:"boolean"}],
    permissions: [{name:"code",required:true},{name:"description",type:"textarea"},{name:"entity"},{name:"action"},{name:"module"}],
    routes: [{name:"name",required:true},{name:"url",required:true},{name:"domain",type:"select",options:["SYSTEM","SCHOOL","SHARED"]},{name:"description",type:"textarea"},{name:"controller"},{name:"action"},{name:"is_active",type:"boolean"}],
    "sidebar-menus": [{name:"name",required:true},{name:"label",required:true},{name:"icon"},{name:"url"},{name:"route_id",type:"number"},{name:"parent_id",type:"number"},{name:"menu_type",type:"select",options:["sidebar","topbar","dropdown"]},{name:"display_order",type:"number"},{name:"domain",type:"select",options:["SYSTEM","SCHOOL","SHARED"]},{name:"is_active",type:"boolean"}],
    settings: [{name:"school_name"},{name:"school_code"},{name:"email",type:"email"},{name:"phone"},{name:"address",type:"textarea"},{name:"academic_year"},{name:"currency"}],
    maintenance: [{name:"enabled",type:"boolean"},{name:"message",type:"textarea"},{name:"start_at",type:"datetime-local"},{name:"end_at",type:"datetime-local"}],
    policies: [{name:"name",required:true},{name:"description",type:"textarea"},{name:"effect",type:"select",options:["allow","deny"]},{name:"conditions",type:"json"},{name:"is_active",type:"boolean"}],
    "route-rules": [{name:"route_id",type:"number",required:true},{name:"role_id",type:"number"},{name:"permission_id",type:"number"},{name:"effect",type:"select",options:["allow","deny"]},{name:"is_active",type:"boolean"}],
    webhooks: [{name:"name",required:true},{name:"url",type:"url",required:true},{name:"event"},{name:"secret",type:"password",list:false},{name:"is_active",type:"boolean"}],
  };

  function requireOperation(map, operation) {
    const handler = map[resource];
    if (!handler) throw new Error(`${title} does not support ${operation}.`);
    return handler;
  }

  const notify = (message, type = "info") =>
    window.showNotification?.(message, type) ||
    window.API?.showNotification?.(message, type) ||
    console.log(message);

  function showState(message, type = "info") {
    state.hidden = false;
    state.className = `alert alert-${type}`;
    state.textContent = message;
  }

  function hideState() { state.hidden = true; }

  function display(value) {
    if (value === null || value === undefined || value === "") return "—";
    if (typeof value === "object") return JSON.stringify(value);
    if (String(value) === "1") return "Yes";
    if (String(value) === "0") return "No";
    return String(value);
  }

  function actionButtons(row) {
    const id = Number(row.id || 0);
    if (!id) return "";

    if (mode === "sessions")
      return `<button class="btn btn-sm btn-outline-danger" data-action="revoke-session" data-id="${id}">Revoke</button>`;
    if (mode === "backups")
      return `<button class="btn btn-sm btn-outline-danger" data-action="delete-backup" data-id="${id}">Delete</button>`;
    if (mode === "accounts")
      return `<div class="btn-group btn-group-sm"><button class="btn btn-outline-success" data-action="activate-account" data-id="${id}">Activate</button><button class="btn btn-outline-warning" data-action="lock-account" data-id="${id}">Lock</button><button class="btn btn-outline-secondary" data-action="unlock-account" data-id="${id}">Unlock</button><button class="btn btn-outline-danger" data-action="disable-account" data-id="${id}">Disable</button></div>`;
    if (["crud","migrations"].includes(mode))
      return `<div class="btn-group btn-group-sm"><button class="btn btn-outline-primary" data-edit="${id}">Edit</button><button class="btn btn-outline-danger" data-delete="${id}">Delete</button></div>`;
    return "";
  }

  function render() {
    const term = (search?.value || "").trim().toLowerCase();
    const filtered = !term ? rows : rows.filter(row =>
      Object.values(row).some(value => display(value).toLowerCase().includes(term))
    );

    if (!filtered.length) {
      head.innerHTML = "<tr><th>Records</th></tr>";
      body.innerHTML = '<tr><td class="text-center text-muted py-5">No records found.</td></tr>';
      count.textContent = "0 records";
      return;
    }

    const preferred = schema.filter(f => f.list !== false).map(f => f.name);
    const discovered = Object.keys(filtered[0]);
    const keys = (preferred.length ? preferred : discovered).filter(k => k !== "details").slice(0, 8);

    head.innerHTML = `<tr>${keys.map(k => `<th>${esc(k.replaceAll("_"," "))}</th>`).join("")}<th class="text-end">Actions</th></tr>`;
    body.innerHTML = filtered.map(row => `<tr>
      ${keys.map(k => `<td>${esc(display(row[k]))}</td>`).join("")}
      <td class="text-end">${actionButtons(row)}</td>
    </tr>`).join("");
    count.textContent = `${filtered.length} of ${rows.length} records`;
  }

  function renderSummary(data = {}) {
    const cards = data.summary || {};
    const entries = Object.entries(cards);
    summary.innerHTML = entries.map(([label,value]) => `
      <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100">
        <div class="card-body"><div class="text-muted small text-uppercase">${esc(label.replaceAll("_"," "))}</div>
        <div class="fs-3 fw-bold">${esc(display(value))}</div></div>
      </div>`).join("");
  }

  async function load() {
    showState(`Loading ${title.toLowerCase()}...`);
    try {
      await window.AuthContext?.ready?.();
      if (!window.API?.system || !window.API?.maintenance || !window.API?.users) {
        throw new Error("Canonical API namespaces are unavailable. Ensure js/api.js is loaded first.");
      }
      const response = await requireOperation(readers, "loading")();
      const payload = response?.data ?? response ?? {};
      rows = Array.isArray(payload)
        ? payload
        : (payload.rows || payload.items || payload.sessions || payload.events || payload.users || payload.roles || payload.permissions || payload.routes || payload.data || []);
      if (!Array.isArray(rows) && rows && typeof rows === "object") rows = [rows];
      schema = payload.schema || defaultSchemas[resource] || [];
      renderSummary(payload);
      render();
      hideState();
    } catch (error) {
      console.error(`[SystemAdmin:${resource}]`, error);
      rows = [];
      render();
      showState(error.message || `Unable to load ${title}.`, error.code === "PERMISSION_DENIED" ? "warning" : "danger");
    }
  }

  function inputFor(field, value = "") {
    const name = field.name;
    const label = field.label || name.replaceAll("_"," ");
    const type = field.type || "text";
    if (type === "boolean") return `<div class="form-check form-switch mb-3">
      <input class="form-check-input" type="checkbox" name="${esc(name)}" id="field_${esc(name)}" ${Number(value) === 1 ? "checked" : ""}>
      <label class="form-check-label" for="field_${esc(name)}">${esc(label)}</label></div>`;
    if (type === "textarea" || type === "json") return `<div class="mb-3"><label class="form-label">${esc(label)}</label><textarea class="form-control" rows="4" name="${esc(name)}">${esc(type === "json" && typeof value === "object" ? JSON.stringify(value, null, 2) : value)}</textarea></div>`;
    if (type === "select") return `<div class="mb-3"><label class="form-label">${esc(label)}</label><select class="form-select" name="${esc(name)}">${(field.options || []).map(opt => `<option value="${esc(opt)}" ${String(opt) === String(value) ? "selected" : ""}>${esc(opt)}</option>`).join("")}</select></div>`;
    return `<div class="mb-3"><label class="form-label">${esc(label)}</label><input class="form-control" type="${esc(type)}" name="${esc(name)}" value="${esc(value)}" ${field.required ? "required" : ""}></div>`;
  }

  function openForm(row = null) {
    if (!form || !fieldsContainer) return;
    editingId = row ? Number(row.id || 0) : null;
    modalTitle.textContent = `${editingId ? "Edit" : "Add"} ${title}`;
    const editable = schema.filter(field => field.editable !== false && field.name !== "id");
    fieldsContainer.innerHTML = editable.length
      ? editable.map(field => inputFor(field, row?.[field.name] ?? field.default ?? "")).join("")
      : '<div class="alert alert-warning">This resource does not expose editable fields.</div>';
    modal ||= bootstrap.Modal.getOrCreateInstance(modalElement);
    modal.show();
  }

  async function save(event) {
    event.preventDefault();
    const payload = {};
    for (const field of schema.filter(f => f.editable !== false && f.name !== "id")) {
      const el = form.elements[field.name];
      if (!el) continue;
      let value = field.type === "boolean" ? (el.checked ? 1 : 0) : el.value;
      if (field.type === "json" && value.trim()) {
        try { value = JSON.parse(value); } catch { notify(`${field.label || field.name} must contain valid JSON.`, "warning"); return; }
      }
      payload[field.name] = value;
    }

    try {
      saveButton.disabled = true;
      await requireOperation(writers, "saving")(payload, editingId);
      modal?.hide();
      notify(`${title} saved successfully.`, "success");
      await load();
    } catch (error) {
      notify(error.message || `Unable to save ${title}.`, "error");
    } finally {
      saveButton.disabled = false;
    }
  }

  async function runAction(action, id) {
    const confirmed = !["revoke-session","cancel-job","disable-account","lock-account"].includes(action) ||
      confirm(`Confirm ${action.replaceAll("-"," ")}?`);
    if (!confirmed) return;
    if (action === "revoke-session") await API.system.revokeSession(id);
    else if (action === "create-backup") await API.system.createBackup({});
    else if (action === "delete-backup") await API.system.deleteBackup(id);
    else if (action === "activate-account") await API.system.updateAccountStatus(id, {status:"active", account_locked_until:null, failed_login_attempts:0});
    else if (action === "disable-account") await API.system.updateAccountStatus(id, {status:"inactive"});
    else if (action === "unlock-account") await API.system.updateAccountStatus(id, {account_locked_until:null, failed_login_attempts:0});
    else if (action === "lock-account") await API.system.updateAccountStatus(id, {account_locked_until:new Date(Date.now() + 3600000).toISOString().slice(0,19).replace("T"," ")});
    else throw new Error(`${title} action '${action}' requires its dedicated workflow.`);
    notify("Action completed successfully.", "success");
    await load();
  }

  body.addEventListener("click", async event => {
    const edit = event.target.closest("[data-edit]");
    const del = event.target.closest("[data-delete]");
    const action = event.target.closest("[data-action]");
    try {
      if (edit) openForm(rows.find(row => Number(row.id) === Number(edit.dataset.edit)));
      if (del && confirm("Delete this record?")) {
        await requireOperation(deleters, "deleting")(Number(del.dataset.delete));
        notify("Record deleted.", "success"); await load();
      }
      if (action) {
        await runAction(
          action.dataset.action,
          Number(action.dataset.id || 0),
        );
      }
    } catch (error) { notify(error.message || "Action failed.", "error"); }
  });

  if (!["crud","migrations","backups"].includes(mode)) createButton.hidden = true;
  createButton?.addEventListener("click", async () => {
    if (mode === "backups") {
      try { await API.system.createBackup({}); notify("Backup created.", "success"); await load(); }
      catch (error) { notify(error.message || "Unable to create backup.", "error"); }
      return;
    }
    openForm();
  });
  refreshButton?.addEventListener("click", load);
  search?.addEventListener("input", render);
  form?.addEventListener("submit", save);
  load();
})();
