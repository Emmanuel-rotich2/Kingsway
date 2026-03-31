/**
 * Chapel Services Controller
 * Page: chapel_services.php
 * Manage chapel/religious services - schedule, attendance, details
 */
const ChapelServicesController = {
  state: {
    services: [],
    allServices: [],
    editId: null,
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
    document
      .getElementById("addServiceBtn")
      ?.addEventListener("click", () => this.openServiceModal());
    document
      .getElementById("saveServiceBtn")
      ?.addEventListener("click", () => this.saveService());
    document
      .getElementById("saveAttendanceBtn")
      ?.addEventListener("click", () => this.saveAttendance());
    document
      .getElementById("editFromViewBtn")
      ?.addEventListener("click", () => {
        bootstrap.Modal.getInstance(
          document.getElementById("viewServiceModal"),
        )?.hide();
        if (this.state.editId) this.openServiceModal(this.state.editId);
      });

    document
      .getElementById("searchBox")
      ?.addEventListener("input", () => this.applyFilters());
    document
      .getElementById("typeFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document
      .getElementById("statusFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document
      .getElementById("monthFilter")
      ?.addEventListener("change", () => this.applyFilters());
  },

  async loadData() {
    try {
      this.showTableLoading();
      const res =
        (await window.API.boarding.getChapelServices().catch(() => null)) ||
        (await window.API.academic
          .getCustom({ action: "chapel-services" })
          .catch(() => null));

      this.state.allServices = res?.success ? res.data || [] : [];
      this.state.services = [...this.state.allServices];
      this.updateStats();
      this.renderTable();
    } catch (error) {
      console.error("Error loading chapel services:", error);
    }
  },

  updateStats() {
    const services = this.state.allServices;
    const now = new Date();
    const thisMonth = now.getMonth();
    const thisYear = now.getFullYear();

    const el = (id, val) => {
      const e = document.getElementById(id);
      if (e) e.textContent = val;
    };
    el(
      "servicesMonth",
      services.filter((s) => {
        const d = new Date(s.date || s.service_date);
        return d.getMonth() === thisMonth && d.getFullYear() === thisYear;
      }).length,
    );
    el(
      "avgAttendance",
      services.length > 0
        ? Math.round(
            services.reduce(
              (sum, s) => sum + (s.attendance || s.total_attendance || 0),
              0,
            ) / services.length,
          )
        : 0,
    );
    el(
      "upcomingServices",
      services.filter(
        (s) =>
          new Date(s.date || s.service_date) > now && s.status !== "cancelled",
      ).length,
    );
    el(
      "activePrograms",
      new Set(services.map((s) => s.type || s.service_type)).size,
    );
  },

  applyFilters() {
    const search = document.getElementById("searchBox")?.value?.toLowerCase();
    const type = document.getElementById("typeFilter")?.value;
    const status = document.getElementById("statusFilter")?.value;
    const month = document.getElementById("monthFilter")?.value;

    let filtered = [...this.state.allServices];
    if (search)
      filtered = filtered.filter((s) =>
        (s.theme || s.topic || s.speaker || "").toLowerCase().includes(search),
      );
    if (type)
      filtered = filtered.filter((s) => (s.type || s.service_type) === type);
    if (status) filtered = filtered.filter((s) => s.status === status);
    if (month)
      filtered = filtered.filter((s) =>
        (s.date || s.service_date || "").startsWith(month),
      );

    this.state.services = filtered;
    this.renderTable();
  },

  renderTable() {
    const tbody = document.querySelector("#servicesTable tbody");
    if (!tbody) return;

    if (this.state.services.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="7" class="text-center text-muted py-4">No chapel services found</td></tr>';
      return;
    }

    const typeLabels = {
      sunday_service: "Sunday Service",
      midweek: "Midweek",
      prayer_meeting: "Prayer Meeting",
      bible_study: "Bible Study",
      youth_service: "Youth Service",
      special: "Special Event",
    };
    const statusColors = {
      scheduled: "primary",
      completed: "success",
      cancelled: "danger",
    };

    tbody.innerHTML = this.state.services
      .map(
        (s) => `
        <tr>
            <td>${this.formatDate(s.date || s.service_date)}<br><small class="text-muted">${s.start_time || ""} - ${s.end_time || ""}</small></td>
            <td>${typeLabels[s.type || s.service_type] || s.type || "--"}</td>
            <td>${this.esc(s.theme || s.topic || "--")}</td>
            <td>${this.esc(s.speaker || "--")}</td>
            <td>${s.attendance || s.total_attendance || "--"}</td>
            <td><span class="badge bg-${statusColors[s.status] || "secondary"}">${s.status || "scheduled"}</span></td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-info" onclick="ChapelServicesController.viewService(${s.id})" title="View"><i class="fas fa-eye"></i></button>
                    <button class="btn btn-outline-primary" onclick="ChapelServicesController.openServiceModal(${s.id})" title="Edit"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-outline-success" onclick="ChapelServicesController.openAttendanceModal(${s.id})" title="Attendance"><i class="fas fa-user-check"></i></button>
                </div>
            </td>
        </tr>`,
      )
      .join("");
  },

  openServiceModal(id = null) {
    const form = document.getElementById("serviceForm");
    const title = document.getElementById("serviceModalTitle");
    if (form) form.reset();
    this.state.editId = id;

    if (id) {
      const s = this.state.allServices.find((x) => x.id == id);
      if (s) {
        title.textContent = "Edit Service";
        document.getElementById("serviceId").value = s.id;
        document.getElementById("serviceType").value =
          s.type || s.service_type || "";
        document.getElementById("serviceDate").value =
          s.date || s.service_date || "";
        document.getElementById("startTime").value = s.start_time || "";
        document.getElementById("endTime").value = s.end_time || "";
        document.getElementById("theme").value = s.theme || s.topic || "";
        document.getElementById("scripture").value = s.scripture || "";
        document.getElementById("speaker").value = s.speaker || "";
        document.getElementById("worshipLeader").value = s.worship_leader || "";
        document.getElementById("location").value = s.location || "main_chapel";
        document.getElementById("expectedAttendance").value =
          s.expected_attendance || "";
        document.getElementById("notes").value = s.notes || "";
        document.getElementById("status").value = s.status || "scheduled";
      }
    } else {
      title.textContent = "Schedule Service";
    }
    new bootstrap.Modal(document.getElementById("serviceModal")).show();
  },

  async saveService() {
    const data = {
      type: document.getElementById("serviceType")?.value,
      date: document.getElementById("serviceDate")?.value,
      start_time: document.getElementById("startTime")?.value,
      end_time: document.getElementById("endTime")?.value,
      theme: document.getElementById("theme")?.value,
      scripture: document.getElementById("scripture")?.value,
      speaker: document.getElementById("speaker")?.value,
      worship_leader: document.getElementById("worshipLeader")?.value,
      location: document.getElementById("location")?.value,
      expected_attendance: document.getElementById("expectedAttendance")?.value,
      notes: document.getElementById("notes")?.value,
      status: document.getElementById("status")?.value,
    };

    if (!data.type || !data.date || !data.theme || !data.speaker) {
      this.showNotification("Please fill all required fields", "warning");
      return;
    }

    try {
      const id = document.getElementById("serviceId")?.value;
      if (id) {
        await window.API.boarding
          .updateChapelService(id, data)
          .catch(() => null);
      } else {
        await window.API.boarding.createChapelService(data).catch(() => null);
      }
      bootstrap.Modal.getInstance(
        document.getElementById("serviceModal"),
      )?.hide();
      this.showNotification("Service saved successfully", "success");
      await this.loadData();
    } catch (error) {
      this.showNotification("Error saving service", "error");
    }
  },

  openAttendanceModal(id) {
    const s = this.state.allServices.find((x) => x.id == id);
    if (!s) return;
    document.getElementById("attendanceServiceId").value = id;
    document.getElementById("attendanceServiceName").textContent =
      s.theme || s.topic || "Service";
    document.getElementById("attendanceDate").textContent = this.formatDate(
      s.date || s.service_date,
    );
    document.getElementById("totalAttendance").value =
      s.attendance || s.total_attendance || "";
    document.getElementById("studentsCount").value = s.students_count || "";
    document.getElementById("staffCount").value = s.staff_count || "";
    document.getElementById("attendanceRemarks").value =
      s.attendance_remarks || "";
    new bootstrap.Modal(document.getElementById("attendanceModal")).show();
  },

  async saveAttendance() {
    const id = document.getElementById("attendanceServiceId")?.value;
    const data = {
      total_attendance: document.getElementById("totalAttendance")?.value,
      students_count: document.getElementById("studentsCount")?.value,
      staff_count: document.getElementById("staffCount")?.value,
      remarks: document.getElementById("attendanceRemarks")?.value,
    };
    bootstrap.Modal.getInstance(
      document.getElementById("attendanceModal"),
    )?.hide();
    this.showNotification("Attendance recorded", "success");
    const s = this.state.allServices.find((x) => x.id == id);
    if (s) {
      s.attendance = parseInt(data.total_attendance) || 0;
      s.status = "completed";
    }
    this.updateStats();
    this.renderTable();
  },

  viewService(id) {
    const s = this.state.allServices.find((x) => x.id == id);
    if (!s) return;
    this.state.editId = id;
    const typeLabels = {
      sunday_service: "Sunday Service",
      midweek: "Midweek",
      prayer_meeting: "Prayer Meeting",
      bible_study: "Bible Study",
      youth_service: "Youth Service",
      special: "Special Event",
    };
    const el = (sel, val) => {
      const e = document.getElementById(sel);
      if (e) e.textContent = val;
    };
    el("viewType", typeLabels[s.type || s.service_type] || s.type || "--");
    el("viewDate", this.formatDate(s.date || s.service_date));
    el("viewTime", `${s.start_time || ""} - ${s.end_time || ""}`);
    el("viewLocation", s.location || "--");
    el("viewStatus", s.status || "scheduled");
    el("viewAttendance", s.attendance || s.total_attendance || "Not recorded");
    el("viewTheme", s.theme || s.topic || "--");
    el("viewScripture", s.scripture || "--");
    el("viewSpeaker", s.speaker || "--");
    el("viewWorship", s.worship_leader || "--");
    el("viewNotes", s.notes || "No notes");
    new bootstrap.Modal(document.getElementById("viewServiceModal")).show();
  },

  formatDate(d) {
    if (!d) return "--";
    try {
      return new Date(d).toLocaleDateString("en-KE", {
        day: "numeric",
        month: "short",
        year: "numeric",
      });
    } catch {
      return d;
    }
  },
  showTableLoading() {
    const t = document.querySelector("#servicesTable tbody");
    if (t)
      t.innerHTML =
        '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading...</td></tr>';
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
};

document.addEventListener('DOMContentLoaded', () => ChapelServicesController.init());
