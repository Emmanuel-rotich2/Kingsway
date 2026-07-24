const StaffAppointmentsPage = {
  internal: [],
  newStaff: [],
  pendingAction: null,

  async init() {
    if (window.StaffAccess) {
      await StaffAccess.init();
      const allowed =
        StaffAccess.can("staff.appointments.view") ||
        StaffAccess.can("staff.appointments.approve") ||
        StaffAccess.can("staff.appointments.onboard");
      if (!allowed) {
        await StaffAccess.require("staff.appointments.view,staff.appointments.approve,staff.appointments.onboard");
        return;
      }
      StaffAccess.apply(document);
    }
    this.ensureModals();
    this.bindEvents();
    this.applyRoleLayout();
    this.applyContext();
    await this.loadAll();
  },

  canApprove() {
    return !window.StaffAccess || StaffAccess.can("staff.appointments.approve");
  },

  canOnboard() {
    return !window.StaffAccess || StaffAccess.can("staff.appointments.onboard");
  },

  getRoleMode() {
    if (this.canApprove()) return "approval";
    if (this.canOnboard()) return "onboarding";
    return "viewer";
  },

  getVisibleCards() {
    const mode = this.getRoleMode();
    if (mode === "approval") return ["internal_pending", "internal_approved", "new_submitted", "new_approved"];
    if (mode === "onboarding") return ["new_approved", "new_submitted"];
    return ["internal_pending", "new_submitted"];
  },

  getInternalColumns() {
    if (this.getRoleMode() === "viewer") return ["staff", "type", "position", "status"];
    return ["staff", "type", "position", "department", "salary", "status", "actions"];
  },

  getNewColumns() {
    const mode = this.getRoleMode();
    if (mode === "approval") return ["candidate", "contact", "position", "department", "start", "status", "actions"];
    if (mode === "onboarding") return ["candidate", "position", "department", "start", "status", "actions"];
    return ["candidate", "position", "department", "status"];
  },

  applyRoleLayout() {
    const mode = this.getRoleMode();
    document.getElementById("staff-appointments-page")?.setAttribute("data-appointment-mode", mode);

    const cards = new Set(this.getVisibleCards());
    document.querySelectorAll("[data-appointment-card]").forEach(card => {
      card.hidden = !cards.has(card.dataset.appointmentCard);
    });

    const internalTab = document.querySelector('[data-appointment-tab="internal"]');
    const internalPane = document.querySelector('[data-appointment-pane="internal"]');
    const newTabButton = document.getElementById("new-tab");

    if (mode === "onboarding") {
      if (internalTab) internalTab.hidden = true;
      if (internalPane) internalPane.hidden = true;
      if (newTabButton && window.bootstrap?.Tab) bootstrap.Tab.getOrCreateInstance(newTabButton).show();
    } else {
      if (internalTab) internalTab.hidden = false;
      if (internalPane) internalPane.hidden = false;
    }

    document.getElementById("openInternalAppointmentForm")?.toggleAttribute("hidden", !this.canApprove());
    this.renderHeaders();
  },

  bindEvents() {
    const refresh = document.getElementById("refreshStaffAppointmentsBtn");
    if (refresh) refresh.addEventListener("click", () => this.loadAll());

    document.getElementById("staffAppointmentReasonConfirm")?.addEventListener("click", () => this.submitReasonAction());
    document.getElementById("staffAppointmentOnboardConfirm")?.addEventListener("click", () => this.submitOnboardAction());
  },

  applyContext() {
    const context = window.STAFF_APPOINTMENTS_CONTEXT || {};
    if (context.activeTab === "new") {
      const tab = document.getElementById("new-tab");
      if (tab && window.bootstrap?.Tab) {
        bootstrap.Tab.getOrCreateInstance(tab).show();
      }
    }
  },

  renderHeaders() {
    const labels = {
      staff: "Staff",
      type: "Type",
      position: "Position Change",
      department: "Department Change",
      salary: "Salary Change",
      status: "Status",
      actions: "Actions",
      candidate: "Candidate",
      contact: "Contact",
      start: "Start Date",
    };
    this.renderHeader("internalAppointmentsHeader", this.getInternalColumns(), labels);
    this.renderHeader("newAppointmentsHeader", this.getNewColumns(), labels);
  },

  renderHeader(id, columns, labels) {
    const header = document.getElementById(id);
    if (!header) return;
    header.innerHTML = columns
      .map(column => `<th class="${column === "actions" ? "text-end" : ""}">${labels[column] || column}</th>`)
      .join("");
  },

  async loadAll() {
    try {
      const [summary, internal, newStaff] = await Promise.all([
        this.request("/staff-appointments"),
        this.request("/staff-appointments/internal"),
        this.request("/staff-appointments/new"),
      ]);
      this.internal = internal.data || [];
      this.newStaff = newStaff.data || [];
      this.applyRoleLayout();
      this.renderSummary(summary.data || {});
      this.renderInternal();
      this.renderNewStaff();
      this.hideAlert();
    } catch (error) {
      this.showAlert(error.message || "Unable to load staff appointments", "danger");
    }
  },

  renderSummary(summary) {
    const internalCounts = this.countByStatus(summary.internal || []);
    const newCounts = this.countByStatus(summary.new_staff || []);
    this.setSummary("internal_pending", internalCounts.pending || 0);
    this.setSummary("internal_approved", (internalCounts.approved || 0) + (internalCounts.effective || 0));
    this.setSummary("new_submitted", newCounts.submitted || 0);
    this.setSummary("new_approved", newCounts.approved || 0);
  },

  renderInternal() {
    const body = document.getElementById("internalAppointmentsBody");
    if (!body) return;
    body.replaceChildren();

    if (!this.internal.length) {
      this.appendEmptyRow(body, this.getInternalColumns().length, "No internal appointments found.");
      return;
    }

    const columns = this.getInternalColumns();
    this.internal.forEach((item) => {
      const row = document.createElement("tr");
      columns.forEach(column => this.appendInternalCell(row, item, column));
      body.appendChild(row);
    });
  },

  renderNewStaff() {
    const body = document.getElementById("newAppointmentsBody");
    if (!body) return;
    body.replaceChildren();

    if (!this.newStaff.length) {
      this.appendEmptyRow(body, this.getNewColumns().length, "No new staff appointments found.");
      return;
    }

    const columns = this.getNewColumns();
    this.newStaff.forEach((item) => {
      const row = document.createElement("tr");
      columns.forEach(column => this.appendNewStaffCell(row, item, column));
      body.appendChild(row);
    });
  },

  appendInternalCell(row, item, column) {
    const cells = {
      staff: () => this.appendStaffCell(row, item.staff_name || "Staff", item.staff_no || ""),
      type: () => this.appendCell(row, [this.makeBadge(item.promotion_type || "internal"), Number(item.is_temporary) === 1 ? this.smallText("Temporary acting") : null]),
      position: () => this.appendTextCell(row, `${item.from_position || "-"} -> ${item.to_position || "-"}`),
      department: () => this.appendTextCell(row, `${item.from_department || "-"} -> ${item.to_department || "-"}`),
      salary: () => this.appendTextCell(row, `${this.money(item.from_salary)} -> ${this.money(item.to_salary)}`),
      status: () => this.appendCell(row, [this.statusBadge(item.status)]),
      actions: () => this.appendCell(row, this.internalActions(item), "text-end"),
    };
    cells[column]?.();
  },

  appendNewStaffCell(row, item, column) {
    const candidateName = `${item.candidate_first_name || ""} ${item.candidate_last_name || ""}`.trim() || "Candidate";
    const cells = {
      candidate: () => this.appendStaffCell(row, candidateName, `ID: ${item.candidate_id_number || "Not provided"}`),
      contact: () => this.appendStaffCell(row, item.candidate_email || "-", item.candidate_phone || ""),
      position: () => this.appendTextCell(row, item.position || "-"),
      department: () => this.appendTextCell(row, item.department_name || "-"),
      start: () => this.appendTextCell(row, item.employment_date || "-"),
      status: () => this.appendCell(row, [this.statusBadge(item.status)]),
      actions: () => this.appendCell(row, this.newStaffActions(item), "text-end"),
    };
    cells[column]?.();
  },

  internalActions(item) {
    const buttons = [];
    if (item.status === "pending" && this.canApprove()) {
      buttons.push(this.actionButton("Approve", "success", () => this.runAction("internal", "approve", item.id)));
      buttons.push(this.actionButton("Reject", "outline-danger", () => this.openReasonModal("internal", "reject", item.id)));
    }
    if ((item.status === "approved" || item.status === "effective") && item.promotion_type === "acting" && Number(item.is_temporary) === 1 && this.canApprove()) {
      buttons.push(this.actionButton("Revert Acting", "outline-warning", () => this.openReasonModal("internal", "revert", item.id)));
    }
    return buttons.length ? buttons : [this.smallText("No actions")];
  },

  newStaffActions(item) {
    const buttons = [];
    if (item.status === "submitted" && this.canApprove()) {
      buttons.push(this.actionButton("Approve", "success", () => this.runAction("new", "approve", item.id)));
      buttons.push(this.actionButton("Reject", "outline-danger", () => this.openReasonModal("new", "reject", item.id)));
    }
    if (item.status === "approved" && this.canOnboard()) {
      buttons.push(this.actionButton("Onboard", "warning", () => this.openOnboardModal(item.id)));
    }
    return buttons.length ? buttons : [this.smallText("No actions")];
  },

  async runAction(queue, action, id, body = {}) {
    if (!this.canApprove() && action !== "onboard") {
      showNotification("You do not have permission to approve staff appointments", NOTIFICATION_TYPES.ERROR);
      return;
    }
    const prefix = queue === "internal" ? "internal" : "new";
    await this.request(`/staff-appointments/${prefix}-${action}/${id}`, { method: "PUT", body });
    showNotification(`${queue === "internal" ? "Internal" : "New staff"} appointment ${action} completed`, NOTIFICATION_TYPES.SUCCESS);
    await this.loadAll();
  },

  openReasonModal(queue, action, id) {
    this.pendingAction = { queue, action, id };
    document.getElementById("staffAppointmentReasonText").value = "";
    document.getElementById("staffAppointmentReasonTitle").textContent = action === "revert" ? "Revert Acting Appointment" : "Reject Appointment";
    bootstrap.Modal.getOrCreateInstance(document.getElementById("staffAppointmentReasonModal")).show();
  },

  async submitReasonAction() {
    if (!this.pendingAction) return;
    const reason = document.getElementById("staffAppointmentReasonText").value.trim();
    bootstrap.Modal.getInstance(document.getElementById("staffAppointmentReasonModal"))?.hide();
    await this.runAction(this.pendingAction.queue, this.pendingAction.action, this.pendingAction.id, { reason, remarks: reason });
    this.pendingAction = null;
  },

  openOnboardModal(id) {
    if (!this.canOnboard()) {
      showNotification("You do not have permission to onboard staff", NOTIFICATION_TYPES.ERROR);
      return;
    }
    this.pendingAction = { queue: "new", action: "onboard", id };
    document.getElementById("staffAppointmentRoleId").value = "";
    bootstrap.Modal.getOrCreateInstance(document.getElementById("staffAppointmentOnboardModal")).show();
  },

  async submitOnboardAction() {
    if (!this.pendingAction) return;
    const roleId = Number(document.getElementById("staffAppointmentRoleId").value);
    if (!roleId) {
      showNotification("Role ID is required to create the staff account", NOTIFICATION_TYPES.ERROR);
      return;
    }
    bootstrap.Modal.getInstance(document.getElementById("staffAppointmentOnboardModal"))?.hide();
    await this.runAction("new", "onboard", this.pendingAction.id, { role_id: roleId });
    this.pendingAction = null;
  },

  async request(path, options = {}) {
    const result = await API.callAPI(path, options.method || "GET", options.body);
    return { data: result };
  },

  appendStaffCell(row, title, subtitle) {
    const cell = document.createElement("td");
    const strong = document.createElement("div");
    strong.className = "fw-semibold";
    strong.textContent = title;
    cell.appendChild(strong);
    if (subtitle) cell.appendChild(this.smallText(subtitle));
    row.appendChild(cell);
  },

  appendTextCell(row, text) {
    const cell = document.createElement("td");
    cell.textContent = text;
    row.appendChild(cell);
  },

  appendCell(row, children, className = "") {
    const cell = document.createElement("td");
    if (className) cell.className = className;
    children.filter(Boolean).forEach((child, index) => {
      if (index > 0) cell.appendChild(document.createTextNode(" "));
      cell.appendChild(child);
    });
    row.appendChild(cell);
  },

  appendEmptyRow(body, columns, message) {
    const row = document.createElement("tr");
    const cell = document.createElement("td");
    cell.colSpan = columns;
    cell.className = "text-center text-muted py-4";
    cell.textContent = message;
    row.appendChild(cell);
    body.appendChild(row);
  },

  actionButton(label, variant, handler) {
    const button = document.createElement("button");
    button.type = "button";
    button.className = `btn btn-sm btn-${variant} me-1`;
    button.textContent = label;
    button.addEventListener("click", handler);
    return button;
  },

  makeBadge(text) {
    const badge = document.createElement("span");
    badge.className = "badge bg-light text-dark border";
    badge.textContent = text;
    return badge;
  },

  statusBadge(status) {
    const palette = { pending: "warning", submitted: "warning", approved: "success", effective: "success", rejected: "danger", onboarded: "primary", cancelled: "secondary" };
    const badge = document.createElement("span");
    badge.className = `badge bg-${palette[status] || "secondary"}`;
    badge.textContent = status || "unknown";
    return badge;
  },

  smallText(text) {
    const el = document.createElement("div");
    el.className = "small text-muted";
    el.textContent = text;
    return el;
  },

  countByStatus(rows) {
    return rows.reduce((counts, row) => {
      counts[row.status] = Number(row.total || 0);
      return counts;
    }, {});
  },

  setSummary(key, value) {
    const element = document.querySelector(`[data-summary="${key}"]`);
    if (element) element.textContent = value;
  },

  showAlert(message, type) {
    const alert = document.getElementById("staffAppointmentsAlert");
    if (!alert) return;
    alert.className = `alert alert-${type}`;
    alert.textContent = message;
  },

  hideAlert() {
    const alert = document.getElementById("staffAppointmentsAlert");
    if (alert) alert.className = "alert d-none";
  },

  money(value) {
    if (value === null || value === undefined || value === "") return "-";
    return `KES ${Number(value).toLocaleString()}`;
  },

  ensureModals() {
    const wrapper = document.createElement("div");
    wrapper.innerHTML = `
      <div class="modal fade" id="staffAppointmentReasonModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="staffAppointmentReasonTitle">Appointment Action</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><label class="form-label" for="staffAppointmentReasonText">Reason / remarks</label><textarea class="form-control" id="staffAppointmentReasonText" rows="4"></textarea></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-success" id="staffAppointmentReasonConfirm">Continue</button></div>
          </div>
        </div>
      </div>
      <div class="modal fade" id="staffAppointmentOnboardModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Onboard New Staff</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><label class="form-label" for="staffAppointmentRoleId">Role ID for created user account</label><input type="number" min="1" class="form-control" id="staffAppointmentRoleId"><div class="form-text">Use the role that matches the approved staff position.</div></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-success" id="staffAppointmentOnboardConfirm">Create Staff Account</button></div>
          </div>
        </div>
      </div>`;
    document.body.appendChild(wrapper);
  },
};

document.addEventListener("DOMContentLoaded", function () {
  StaffAppointmentsPage.init();
});
