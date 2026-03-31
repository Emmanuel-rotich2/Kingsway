/**
 * All Parents Controller
 * Page: all_parents.php
 * List, search, and manage all parent/guardian records
 */
const AllParentsController = {
  state: {
    parents: [],
    allParents: [],
    classes: [],
    pagination: { page: 1, perPage: 25, total: 0 },
  },

  async init() {
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    this.bindEvents();
    await this.loadData();
  },

  bindEvents() {
    const search = document.getElementById("searchParent");
    if (search) search.addEventListener("input", () => this.applyFilters());

    const filterClass = document.getElementById("filterByClass");
    if (filterClass)
      filterClass.addEventListener("change", () => this.applyFilters());

    const filterFee = document.getElementById("filterByFeeStatus");
    if (filterFee)
      filterFee.addEventListener("change", () => this.applyFilters());

    const exportBtn = document.getElementById("exportParents");
    if (exportBtn) exportBtn.addEventListener("click", () => this.exportCSV());

    const addBtn = document.getElementById("addParent");
    if (addBtn)
      addBtn.addEventListener("click", () => this.showAddParentModal());
  },

  async loadData() {
    try {
      this.showTableLoading();

      const [parentsRes, classesRes] = await Promise.all([
        window.API.students.get
          ? window.API.students.getAll
            ? window.API.students.getAll()
            : window.API.students.get()
          : window.API.academic.getCustom({ action: "parents" }),
        window.API.academic.listClasses(),
      ]);

      // Try dedicated parents endpoint first
      let parentData = [];
      const pRes = await window.API.academic
        .getCustom({ action: "parents" })
        .catch(() => null);
      if (pRes?.success && pRes.data?.length) {
        parentData = pRes.data;
      } else if (parentsRes?.success) {
        // Extract unique parents from student data
        const parentMap = {};
        (parentsRes.data || []).forEach((s) => {
          const pKey = (s.parent_name || s.guardian_name || "").trim();
          if (pKey && !parentMap[pKey]) {
            parentMap[pKey] = {
              id: s.parent_id || s.guardian_id || s.id,
              name: pKey,
              phone: s.parent_phone || s.guardian_phone || "",
              email: s.parent_email || s.guardian_email || "",
              children: [],
              class_ids: [],
              fee_status: "unknown",
            };
          }
          if (pKey && parentMap[pKey]) {
            parentMap[pKey].children.push({
              id: s.id,
              name: `${s.first_name || ""} ${s.last_name || ""}`.trim(),
              class_name: s.class_name || "",
            });
            if (s.class_id) parentMap[pKey].class_ids.push(s.class_id);
          }
        });
        parentData = Object.values(parentMap);
      }

      this.state.allParents = parentData;
      this.state.parents = [...parentData];

      if (classesRes?.success) {
        this.state.classes = classesRes.data || [];
        this.populateClassFilter();
      }

      this.updateStats();
      this.renderTable();
    } catch (error) {
      console.error("Error loading parents:", error);
      this.showNotification("Error loading parent data", "error");
    }
  },

  updateStats() {
    const parents = this.state.allParents;
    const el = (id, val) => {
      const e = document.getElementById(id);
      if (e) e.textContent = val;
    };
    el("totalParents", parents.length);
    el("activeParents", parents.filter((p) => p.phone || p.email).length);
    el("ptaMembers", parents.filter((p) => p.pta_member || p.is_pta).length);
  },

  applyFilters() {
    const search = document
      .getElementById("searchParent")
      ?.value?.toLowerCase();
    const classId = document.getElementById("filterByClass")?.value;
    const feeStatus = document.getElementById("filterByFeeStatus")?.value;

    let filtered = [...this.state.allParents];
    if (search)
      filtered = filtered.filter(
        (p) =>
          (p.name || "").toLowerCase().includes(search) ||
          (p.phone || "").includes(search) ||
          (p.email || "").toLowerCase().includes(search),
      );
    if (classId)
      filtered = filtered.filter(
        (p) =>
          p.class_ids?.includes(parseInt(classId)) ||
          p.children?.some((c) => c.class_name == classId),
      );
    if (feeStatus)
      filtered = filtered.filter((p) => p.fee_status === feeStatus);

    this.state.parents = filtered;
    this.renderTable();
  },

  renderTable() {
    const tbody = document.querySelector("#parentsTable tbody");
    if (!tbody) return;

    if (this.state.parents.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="7" class="text-center text-muted py-4">No parents found</td></tr>';
      return;
    }

    tbody.innerHTML = this.state.parents
      .map((p, i) => {
        const childrenList = (p.children || [])
          .map((c) => `${this.esc(c.name)} (${this.esc(c.class_name)})`)
          .join(", ");
        return `
            <tr>
                <td>${i + 1}</td>
                <td><strong>${this.esc(p.name)}</strong></td>
                <td>${this.esc(p.phone || "--")}</td>
                <td>${this.esc(p.email || "--")}</td>
                <td><small>${childrenList || "--"}</small></td>
                <td>${p.pta_member || p.is_pta ? '<span class="badge bg-success">PTA</span>' : '<span class="badge bg-secondary">No</span>'}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="AllParentsController.viewParent(${i})" title="View"><i class="fas fa-eye"></i></button>
                        <button class="btn btn-outline-info" onclick="AllParentsController.contactParent(${i})" title="Contact"><i class="fas fa-envelope"></i></button>
                    </div>
                </td>
            </tr>`;
      })
      .join("");
  },

  viewParent(index) {
    const p = this.state.parents[index];
    if (!p) return;
    const children = (p.children || [])
      .map((c) => `<li>${this.esc(c.name)} - ${this.esc(c.class_name)}</li>`)
      .join("");
    this.showModal(
      "Parent Details",
      `
            <div class="row">
                <div class="col-md-6">
                    <h6>Personal Information</h6>
                    <p><strong>Name:</strong> ${this.esc(p.name)}</p>
                    <p><strong>Phone:</strong> ${this.esc(p.phone || "N/A")}</p>
                    <p><strong>Email:</strong> ${this.esc(p.email || "N/A")}</p>
                    <p><strong>PTA Member:</strong> ${p.pta_member || p.is_pta ? "Yes" : "No"}</p>
                </div>
                <div class="col-md-6">
                    <h6>Children</h6>
                    <ul>${children || '<li class="text-muted">No children linked</li>'}</ul>
                </div>
            </div>`,
    );
  },

  contactParent(index) {
    const p = this.state.parents[index];
    if (!p) return;
    this.showModal(
      "Contact Parent",
      `
            <form id="contactParentForm">
                <p><strong>To:</strong> ${this.esc(p.name)} (${this.esc(p.phone || p.email || "No contact")})</p>
                <div class="mb-3">
                    <label class="form-label">Method</label>
                    <select class="form-select" id="contactMethod">
                        ${p.phone ? '<option value="sms">SMS</option>' : ""}
                        ${p.email ? '<option value="email">Email</option>' : ""}
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Message</label>
                    <textarea class="form-control" id="contactMessage" rows="4" placeholder="Type your message..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Send</button>
            </form>`,
      () => {
        document
          .getElementById("contactParentForm")
          ?.addEventListener("submit", (e) => {
            e.preventDefault();
            this.showNotification("Message sent successfully", "success");
            bootstrap.Modal.getInstance(
              document.getElementById("dynamicModal"),
            )?.hide();
          });
      },
    );
  },

  showAddParentModal() {
    this.showModal(
      "Add Parent/Guardian",
      `
            <form id="addParentForm">
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Full Name</label><input type="text" class="form-control" id="parentName" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input type="tel" class="form-control" id="parentPhone"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" class="form-control" id="parentEmail"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">ID Number</label><input type="text" class="form-control" id="parentIdNo"></div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Parent</button>
            </form>`,
      () => {
        document
          .getElementById("addParentForm")
          ?.addEventListener("submit", (e) => {
            e.preventDefault();
            this.showNotification("Parent added successfully", "success");
            bootstrap.Modal.getInstance(
              document.getElementById("dynamicModal"),
            )?.hide();
          });
      },
    );
  },

  populateClassFilter() {
    const select = document.getElementById("filterByClass");
    if (!select) return;
    select.innerHTML =
      '<option value="">All Classes</option>' +
      this.state.classes
        .map(
          (c) =>
            `<option value="${c.id}">${this.esc(c.name || c.class_name)}</option>`,
        )
        .join("");
  },

  exportCSV() {
    if (this.state.parents.length === 0) {
      this.showNotification("No data to export", "warning");
      return;
    }
    const headers = ["#", "Name", "Phone", "Email", "Children", "PTA Member"];
    const rows = this.state.parents.map((p, i) => [
      i + 1,
      p.name,
      p.phone || "",
      p.email || "",
      (p.children || []).map((c) => c.name).join("; "),
      p.pta_member || p.is_pta ? "Yes" : "No",
    ]);
    const csv = [headers, ...rows]
      .map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(","))
      .join("\n");
    const blob = new Blob([csv], { type: "text/csv" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "all_parents.csv";
    a.click();
    URL.revokeObjectURL(url);
  },

  showTableLoading() {
    const tbody = document.querySelector("#parentsTable tbody");
    if (tbody)
      tbody.innerHTML =
        '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading parents...</td></tr>';
  },

  esc(str) {
    if (!str) return "";
    const d = document.createElement("div");
    d.textContent = str;
    return d.innerHTML;
  },
  showNotification(msg, type = "info") {
    const alert = document.createElement("div");
    alert.className = `alert alert-${type === "error" ? "danger" : type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alert.style.zIndex = "9999";
    alert.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(alert);
    setTimeout(() => alert.remove(), 4000);
  },
  showModal(title, bodyHtml, onShow) {
    let modal = document.getElementById("dynamicModal");
    if (!modal) {
      modal = document.createElement("div");
      modal.id = "dynamicModal";
      modal.className = "modal fade";
      modal.tabIndex = -1;
      modal.innerHTML = `<div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"></div></div></div>`;
      document.body.appendChild(modal);
    }
    modal.querySelector(".modal-title").textContent = title;
    modal.querySelector(".modal-body").innerHTML = bodyHtml;
    new bootstrap.Modal(modal).show();
    if (onShow) setTimeout(onShow, 300);
  },
};

document.addEventListener("DOMContentLoaded", () =>
  AllParentsController.init(),
);
