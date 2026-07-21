const StaffAppointmentsPage = {
  internal: [],
  newStaff: [],
  pendingAction: null,

  init() {
    this.ensureModals();
    this.bindEvents();
    this.loadAll();
  },

  bindEvents() {
    const refresh = document.getElementById("refreshStaffAppointmentsBtn");
    if (refresh) refresh.addEventListener("click", () => this.loadAll());

    document.getElementById("staffAppointmentReasonConfirm")?.addEventListener("click", () => this.submitReasonAction());
    document.getElementById("staffAppointmentOnboardConfirm")?.addEventListener("click", () => this.submitOnboardAction());
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
      this.appendEmptyRow(body, 7, "No internal appointments found.");
      return;
    }

    this.internal.forEach((item) => {
      const row = document.createElement("tr");
      this.appendStaffCell(row, item.staff_name || "Staff", item.staff_no || "");
      this.appendCell(row, [this.makeBadge(item.promotion_type || "internal"), Number(item.is_temporary) === 1 ? this.smallText("Temporary acting") : null]);
      this.appendTextCell(row, `${item.from_position || "-"} → ${item.to_position || "-"}`);
      this.appendTextCell(row, `${item.from_department || "-"} → ${item.to_department || "-"}`);
      this.appendTextCell(row, `${this.money(item.from_salary)} → ${this.money(item.to_salary)}`);
      this.appendCell(row, [this.statusBadge(item.status)]);
      this.appendCell(row, this.internalActions(item), "text-end");
      body.appendChild(row);
    });
  },

  renderNewStaff() {
    const body = document.getElementById("newAppointmentsBody");
    if (!body) return;
    body.replaceChildren();

    if (!this.newStaff.length) {
      this.appendEmptyRow(body, 7, "No new staff appointments found.");
      return;
    }

    this.newStaff.forEach((item) => {
      const row = document.createElement("tr");
      this.appendStaffCell(row, `${item.candidate_first_name || ""} ${item.candidate_last_name || ""}`.trim(), `ID: ${item.candidate_id_number || "Not provided"}`);
      this.appendStaffCell(row, item.candidate_email || "-", item.candidate_phone || "");
      this.appendTextCell(row, item.position || "-");
      this.appendTextCell(row, item.department_name || "-");
      this.appendTextCell(row, item.employment_date || "-");
      this.appendCell(row, [this.statusBadge(item.status)]);
      this.appendCell(row, this.newStaffActions(item), "text-end");
      body.appendChild(row);
    });
  },

  internalActions(item) {
    const buttons = [];
    if (item.status === "pending") {
      buttons.push(this.actionButton("Approve", "success", () => this.runAction("internal", "approve", item.id)));
      buttons.push(this.actionButton("Reject", "outline-danger", () => this.openReasonModal("internal", "reject", item.id)));
    }
    if ((item.status === "approved" || item.status === "effective") && item.promotion_type === "acting" && Number(item.is_temporary) === 1) {
      buttons.push(this.actionButton("Revert Acting", "outline-warning", () => this.openReasonModal("internal", "revert", item.id)));
    }
    return buttons.length ? buttons : [this.smallText("No actions")];
  },

  newStaffActions(item) {
    const buttons = [];
    if (item.status === "submitted") {
      buttons.push(this.actionButton("Approve", "success", () => this.runAction("new", "approve", item.id)));
      buttons.push(this.actionButton("Reject", "outline-danger", () => this.openReasonModal("new", "reject", item.id)));
    }
    if (item.status === "approved") {
      buttons.push(this.actionButton("Onboard", "warning", () => this.openOnboardModal(item.id)));
    }
    return buttons.length ? buttons : [this.smallText("No actions")];
  },

  async runAction(queue, action, id, body = {}) {
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
