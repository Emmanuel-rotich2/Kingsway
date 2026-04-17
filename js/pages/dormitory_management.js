/**
 * Dormitory Management Controller
 * Page: dormitory_management.php
 * Manages dormitories, beds, allocation
 */
const DormitoryManagementController = {
  state: {
    dorms: [],
    beds: { total: 0, occupied: 0, available: 0 },
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
    const form = document.getElementById("addDormForm");
    if (form) {
      form.addEventListener("submit", (e) => {
        e.preventDefault();
        this.saveDorm();
      });
    }
  },

  async loadData() {
    try {
      this.showGridLoading();
      // Use a generic approach since boarding API may not exist
      const res =
        (await window.API.academic
          ?.getCustom({ action: "dormitories" })
          .catch(() => null)) ||
        (await fetch((window.APP_BASE || '') + '/api/?route=boarding&action=dormitories')
          .then((r) => r.json())
          .catch(() => null));

      if (res?.success) {
        this.state.dorms = res.data || [];
      }
      this.updateStats();
      this.renderDormsGrid();
    } catch (error) {
      console.error("Error loading dormitories:", error);
      this.renderEmptyGrid();
    }
  },

  updateStats() {
    const dorms = this.state.dorms;
    const totalBeds = dorms.reduce(
      (s, d) => s + parseInt(d.capacity || d.total_beds || 0),
      0,
    );
    const occupied = dorms.reduce(
      (s, d) => s + parseInt(d.occupied || d.occupied_beds || 0),
      0,
    );

    this.setText("#totalDorms", dorms.length);
    this.setText("#totalBeds", totalBeds);
    this.setText("#occupiedBeds", occupied);
    this.setText("#availableBeds", totalBeds - occupied);
  },

  renderDormsGrid() {
    const grid = document.getElementById("dormsGrid");
    if (!grid) return;

    if (this.state.dorms.length === 0) {
      this.renderEmptyGrid();
      return;
    }

    grid.innerHTML = this.state.dorms
      .map((dorm) => {
        const capacity = parseInt(dorm.capacity || dorm.total_beds || 0);
        const occupied = parseInt(dorm.occupied || dorm.occupied_beds || 0);
        const pct = capacity > 0 ? Math.round((occupied / capacity) * 100) : 0;
        const pctClass = pct > 90 ? "danger" : pct > 70 ? "warning" : "success";
        const genderIcon =
          dorm.gender === "female"
            ? "venus"
            : dorm.gender === "male"
              ? "mars"
              : "venus-mars";

        return `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-primary text-white d-flex justify-content-between">
                        <h6 class="mb-0"><i class="fas fa-building me-1"></i>${this.escapeHtml(dorm.name || "")}</h6>
                        <i class="fas fa-${genderIcon}"></i>
                    </div>
                    <div class="card-body">
                        <div class="row text-center mb-3">
                            <div class="col-4"><h5 class="mb-0">${capacity}</h5><small class="text-muted">Beds</small></div>
                            <div class="col-4"><h5 class="mb-0 text-primary">${occupied}</h5><small class="text-muted">Occupied</small></div>
                            <div class="col-4"><h5 class="mb-0 text-success">${capacity - occupied}</h5><small class="text-muted">Available</small></div>
                        </div>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small"><span>Occupancy</span><span>${pct}%</span></div>
                            <div class="progress" style="height:6px;"><div class="progress-bar bg-${pctClass}" style="width:${pct}%"></div></div>
                        </div>
                        ${dorm.warden ? `<div class="small"><i class="fas fa-user-shield me-1"></i>Warden: ${this.escapeHtml(dorm.warden)}</div>` : ""}
                    </div>
                    <div class="card-footer bg-transparent">
                        <div class="btn-group btn-group-sm w-100">
                            <button class="btn btn-outline-primary" onclick="DormitoryManagementController.viewDorm(${dorm.id})"><i class="fas fa-eye me-1"></i>View</button>
                            <button class="btn btn-outline-info" onclick="DormitoryManagementController.viewOccupants(${dorm.id})"><i class="fas fa-users me-1"></i>Occupants</button>
                            <button class="btn btn-outline-warning" onclick="DormitoryManagementController.editDorm(${dorm.id})"><i class="fas fa-edit"></i></button>
                        </div>
                    </div>
                </div>
            </div>`;
      })
      .join("");
  },

  renderEmptyGrid() {
    const grid = document.getElementById("dormsGrid");
    if (grid)
      grid.innerHTML =
        '<div class="col-12 text-center py-5"><i class="fas fa-building fa-3x text-muted mb-3"></i><p class="text-muted">No dormitories found</p></div>';
  },

  showGridLoading() {
    const grid = document.getElementById("dormsGrid");
    if (grid)
      grid.innerHTML =
        '<div class="col-12 text-center py-5"><div class="spinner-border text-primary"></div></div>';
  },

  async viewDorm(id) {
    const dorm = this.state.dorms.find((d) => d.id == id);
    if (dorm) {
      this.showModal(
        "Dormitory Details",
        `
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Name:</strong> ${this.escapeHtml(dorm.name || "")}</p>
                        <p><strong>Gender:</strong> ${this.escapeHtml(dorm.gender || "--")}</p>
                        <p><strong>Location:</strong> ${this.escapeHtml(dorm.location || dorm.block || "--")}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Capacity:</strong> ${dorm.capacity || dorm.total_beds || 0}</p>
                        <p><strong>Occupied:</strong> ${dorm.occupied || dorm.occupied_beds || 0}</p>
                        <p><strong>Warden:</strong> ${this.escapeHtml(dorm.warden || "--")}</p>
                    </div>
                </div>`,
      );
    }
  },

  async viewOccupants(dormId) {
    try {
      const res =
        (await window.API.academic
          ?.getCustom({ action: "dorm-occupants", dorm_id: dormId })
          .catch(() => null)) ||
        (await fetch(
          (window.APP_BASE || "") + `/api/?route=boarding&action=occupants&dorm_id=${dormId}`,
        )
          .then((r) => r.json())
          .catch(() => null));

      const occupants = res?.success ? res.data || [] : [];
      let html =
        occupants.length === 0
          ? '<p class="text-muted">No occupants found</p>'
          : `<table class="table table-sm"><thead><tr><th>#</th><th>Name</th><th>Class</th><th>Bed</th></tr></thead><tbody>
                ${occupants.map((o, i) => `<tr><td>${i + 1}</td><td>${this.escapeHtml(o.student_name || o.name || "")}</td><td>${this.escapeHtml(o.class_name || "")}</td><td>${o.bed_number || "--"}</td></tr>`).join("")}
                </tbody></table>`;
      this.showModal("Dormitory Occupants", html);
    } catch (error) {
      console.error("Error loading occupants:", error);
    }
  },

  async saveDorm() {
    const form = document.getElementById("addDormForm");
    if (!form) return;
    const data = Object.fromEntries(new FormData(form).entries());

    try {
      const res =
        (await window.API.academic
          ?.postCustom({ action: "create-dormitory", ...data })
          .catch(() => null)) ||
        (await fetch((window.APP_BASE || '') + '/api/?route=boarding&action=create-dormitory', {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(data),
        })
          .then((r) => r.json())
          .catch(() => null));

      if (res?.success) {
        this.showNotification("Dormitory saved", "success");
        bootstrap.Modal.getInstance(
          document.getElementById("addDormModal"),
        )?.hide();
        form.reset();
        await this.loadData();
      } else {
        this.showNotification(res?.message || "Failed to save", "error");
      }
    } catch (error) {
      console.error("Error saving dorm:", error);
    }
  },

  editDorm(id) {
    const dorm = this.state.dorms.find((d) => d.id == id);
    if (!dorm) return;
    const form = document.getElementById("addDormForm");
    if (form) {
      form.dataset.editId = id;
      Object.entries(dorm).forEach(([k, v]) => {
        const input = form.querySelector(`[name="${k}"]`);
        if (input && v) input.value = v;
      });
      new bootstrap.Modal(document.getElementById("addDormModal")).show();
    }
  },

  // Utility
  setText(sel, val) {
    const el = document.querySelector(sel);
    if (el) el.textContent = val;
  },
  escapeHtml(str) {
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
  showModal(title, bodyHtml) {
    let modal = document.getElementById("dynamicModal");
    if (!modal) {
      modal = document.createElement("div");
      modal.id = "dynamicModal";
      modal.className = "modal fade";
      modal.innerHTML = `<div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"></div></div></div>`;
      document.body.appendChild(modal);
    }
    modal.querySelector(".modal-title").textContent = title;
    modal.querySelector(".modal-body").innerHTML = bodyHtml;
    new bootstrap.Modal(modal).show();
  },
};

document.addEventListener("DOMContentLoaded", () =>
  DormitoryManagementController.init(),
);
