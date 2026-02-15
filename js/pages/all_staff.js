/**
 * All Staff Page Controller
 * Shared controller loaded by admin_staff.php, manager_staff.php, operator_staff.php, viewer_staff.php
 * Adapts features based on the view mode passed via StaffController.init({ view: 'admin' | 'manager' | ... })
 */

const StaffController = (() => {
  let allStaff = [];
  let filteredStaff = [];
  let departments = [];
  let view = "admin"; // admin | manager | operator | viewer
  let currentPage = 1;
  const pageSize = 25;
  let charts = {};

  // ── Helpers ──────────────────────────────────────────
  function esc(str) {
    if (!str) return "";
    return String(str).replace(
      /[&<>"']/g,
      (m) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[m],
    );
  }

  function extractList(response) {
    if (!response) return [];
    if (Array.isArray(response)) return response;
    if (Array.isArray(response.staff)) return response.staff;
    if (Array.isArray(response.data?.staff)) return response.data.staff;
    if (Array.isArray(response.data)) return response.data;
    return [];
  }

  function notify(msg, type = "info") {
    if (window.API?.showNotification) {
      window.API.showNotification(msg, type);
    }
  }

  // ── Init ─────────────────────────────────────────────
  async function init(options = {}) {
    view = options.view || "admin";
    console.log(`StaffController initialized – view: ${view}`);
    bindEvents();
    await loadData();
  }

  function bindEvents() {
    const search = document.getElementById("staffSearch");
    if (search) {
      search.addEventListener("input", () => {
        currentPage = 1;
        applyFilters();
      });
    }

    ["departmentFilter", "roleTypeFilter", "statusFilter"].forEach((id) => {
      const el = document.getElementById(id);
      if (el) {
        el.addEventListener("change", () => {
          currentPage = 1;
          applyFilters();
        });
      }
    });
  }

  // ── Data Loading ─────────────────────────────────────
  async function loadData() {
    try {
      const [staffResp, deptResp, statsResp] = await Promise.allSettled([
        window.API.staff.index(),
        window.API.staff.getDepartments(),
        window.API.apiCall("/staff/stats", "GET"),
      ]);

      allStaff = extractList(
        staffResp.status === "fulfilled" ? staffResp.value : [],
      );
      departments =
        deptResp.status === "fulfilled"
          ? deptResp.value?.data || deptResp.value || []
          : [];

      populateDepartmentFilter();
      applyFilters();

      if (statsResp.status === "fulfilled") {
        renderStats(statsResp.value?.data || statsResp.value || {});
      } else {
        renderStatsFromList();
      }

      renderCharts();
    } catch (error) {
      console.error("StaffController.loadData error:", error);
    }
  }

  function populateDepartmentFilter() {
    const el = document.getElementById("departmentFilter");
    if (!el) return;
    el.innerHTML = '<option value="">All Departments</option>';
    (Array.isArray(departments) ? departments : []).forEach((d) => {
      const name = d.name || d.department_name || d;
      el.innerHTML += `<option value="${esc(name)}">${esc(name)}</option>`;
    });
  }

  // ── Filtering ────────────────────────────────────────
  function applyFilters() {
    const search = (
      document.getElementById("staffSearch")?.value || ""
    ).toLowerCase();
    const dept = document.getElementById("departmentFilter")?.value || "";
    const roleType = document.getElementById("roleTypeFilter")?.value || "";
    const status = document.getElementById("statusFilter")?.value || "";

    filteredStaff = allStaff.filter((s) => {
      const name =
        `${s.first_name || ""} ${s.last_name || ""} ${s.name || ""} ${s.staff_no || ""}`.toLowerCase();
      if (search && !name.includes(search)) return false;
      if (
        dept &&
        (s.department_name || s.department || "").toLowerCase() !==
          dept.toLowerCase()
      )
        return false;
      if (roleType) {
        const sType = (s.staff_type || "").toLowerCase();
        if (roleType === "teaching" && sType !== "teaching") return false;
        if (
          roleType === "non-teaching" &&
          sType !== "non-teaching" &&
          sType !== "non_teaching"
        )
          return false;
      }
      if (status && (s.status || "").toLowerCase() !== status.toLowerCase())
        return false;
      return true;
    });

    renderTable();
    renderPagination();
  }

  // ── Stats ────────────────────────────────────────────
  function renderStats(data) {
    const set = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.textContent = val;
    };
    set("totalStaff", data.total_staff || allStaff.length || 0);
    set(
      "teachingStaff",
      data.teacher_count ||
        allStaff.filter(
          (s) => (s.staff_type || "").toLowerCase() === "teaching",
        ).length,
    );
    set(
      "nonTeachingStaff",
      allStaff.filter(
        (s) =>
          (s.staff_type || "").toLowerCase() === "non-teaching" ||
          (s.staff_type || "").toLowerCase() === "non_teaching",
      ).length,
    );
    set(
      "activeStaff",
      allStaff.filter((s) => (s.status || "").toLowerCase() === "active")
        .length,
    );
    set(
      "onLeave",
      allStaff.filter((s) => (s.status || "").toLowerCase() === "on_leave")
        .length,
    );
  }

  function renderStatsFromList() {
    renderStats({
      total_staff: allStaff.length,
      teacher_count: allStaff.filter(
        (s) => (s.staff_type || "").toLowerCase() === "teaching",
      ).length,
    });
  }

  // ── Table Rendering ──────────────────────────────────
  function renderTable() {
    const tbody = document.getElementById("staffTableBody");
    if (!tbody) return;

    const start = (currentPage - 1) * pageSize;
    const page = filteredStaff.slice(start, start + pageSize);

    if (page.length === 0) {
      const cols = view === "admin" ? 10 : view === "manager" ? 7 : 10;
      tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center text-muted py-4">No staff found matching filters</td></tr>`;
      return;
    }

    tbody.innerHTML = page
      .map((s, i) => {
        const name =
          s.name || `${s.first_name || ""} ${s.last_name || ""}`.trim();
        const photo = s.photo || "/images/default-avatar.png";
        const statusBadge =
          (s.status || "").toLowerCase() === "active"
            ? '<span class="badge bg-success">Active</span>'
            : (s.status || "").toLowerCase() === "on_leave"
              ? '<span class="badge bg-warning">On Leave</span>'
              : '<span class="badge bg-secondary">' +
                esc(s.status || "N/A") +
                "</span>";

        if (view === "admin") {
          return `<tr>
            <td><input type="checkbox" class="row-checkbox" value="${s.id}" onchange="updateBulkActions()"></td>
            <td><img src="${esc(photo)}" alt="${esc(name)}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;"></td>
            <td>${esc(s.staff_no || "—")}</td>
            <td>${esc(name)}</td>
            <td>${esc(s.position || s.role || "—")}</td>
            <td>${esc(s.department_name || s.department || "—")}</td>
            <td>${esc(s.phone || "—")}</td>
            <td>${esc(s.email || "—")}</td>
            <td>${statusBadge}</td>
            <td>
              <button class="btn btn-sm btn-info" onclick="StaffController.viewDetail(${s.id})" title="View"><i class="fas fa-eye"></i></button>
              <button class="btn btn-sm btn-warning" onclick="StaffController.editStaff(${s.id})" title="Edit"><i class="fas fa-edit"></i></button>
              <button class="btn btn-sm btn-danger" onclick="StaffController.removeStaff(${s.id})" title="Delete"><i class="fas fa-trash"></i></button>
            </td>
          </tr>`;
        }

        // Manager view (no checkbox, no delete)
        if (view === "manager") {
          return `<tr>
            <td><img src="${esc(photo)}" alt="${esc(name)}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;"></td>
            <td>${esc(s.staff_no || "—")}</td>
            <td>${esc(name)}</td>
            <td>${esc(s.position || s.role || "—")}</td>
            <td>${esc(s.phone || "—")}</td>
            <td>${statusBadge}</td>
            <td>
              <button class="btn btn-sm btn-info" onclick="StaffController.viewDetail(${s.id})" title="View"><i class="fas fa-eye"></i></button>
            </td>
          </tr>`;
        }

        // Default row
        return `<tr>
          <td>${start + i + 1}</td>
          <td>${esc(name)}</td>
          <td>${esc(s.position || s.role || "—")}</td>
          <td>${esc(s.department_name || s.department || "—")}</td>
          <td>${statusBadge}</td>
        </tr>`;
      })
      .join("");

    // Update pagination info
    const info = document.getElementById("paginationInfo");
    if (info) {
      const end = Math.min(start + pageSize, filteredStaff.length);
      info.textContent = `Showing ${start + 1}–${end} of ${filteredStaff.length}`;
    }
  }

  // ── Pagination ───────────────────────────────────────
  function renderPagination() {
    const container = document.getElementById("paginationControls");
    if (!container) return;

    const totalPages = Math.ceil(filteredStaff.length / pageSize);
    if (totalPages <= 1) {
      container.innerHTML = "";
      return;
    }

    let html = "";
    for (let i = 1; i <= totalPages; i++) {
      html += `<button class="btn btn-sm ${i === currentPage ? "btn-primary" : "btn-outline-primary"} me-1"
                 onclick="StaffController.loadPage(${i})">${i}</button>`;
    }
    container.innerHTML = html;
  }

  function loadPage(page) {
    currentPage = page;
    renderTable();
    renderPagination();
  }

  // ── Charts ───────────────────────────────────────────
  function renderCharts() {
    if (typeof Chart === "undefined") return;

    // Department Distribution Chart
    const deptCanvas = document.getElementById("departmentChart");
    if (deptCanvas) {
      const deptCounts = {};
      allStaff.forEach((s) => {
        const dept = s.department_name || s.department || "Unassigned";
        deptCounts[dept] = (deptCounts[dept] || 0) + 1;
      });
      const labels = Object.keys(deptCounts);
      const data = Object.values(deptCounts);
      const colors = [
        "#4e73df",
        "#1cc88a",
        "#36b9cc",
        "#f6c23e",
        "#e74a3b",
        "#858796",
        "#5a5c69",
        "#6610f2",
        "#fd7e14",
        "#20c9a6",
      ];

      if (charts.dept) charts.dept.destroy();
      charts.dept = new Chart(deptCanvas, {
        type: "bar",
        data: {
          labels,
          datasets: [
            {
              label: "Staff Count",
              data,
              backgroundColor: colors.slice(0, labels.length),
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
        },
      });
    }

    // Role Breakdown Chart
    const roleCanvas = document.getElementById("roleChart");
    if (roleCanvas) {
      const typeCounts = { Teaching: 0, "Non-Teaching": 0, Other: 0 };
      allStaff.forEach((s) => {
        const t = (s.staff_type || "").toLowerCase();
        if (t === "teaching") typeCounts["Teaching"]++;
        else if (t === "non-teaching" || t === "non_teaching")
          typeCounts["Non-Teaching"]++;
        else typeCounts["Other"]++;
      });
      const labels = Object.keys(typeCounts);
      const data = Object.values(typeCounts);

      if (charts.role) charts.role.destroy();
      charts.role = new Chart(roleCanvas, {
        type: "doughnut",
        data: {
          labels,
          datasets: [
            {
              data,
              backgroundColor: ["#4e73df", "#1cc88a", "#858796"],
            },
          ],
        },
        options: { responsive: true, maintainAspectRatio: false },
      });
    }
  }

  // ── CRUD Actions ─────────────────────────────────────
  async function viewDetail(id) {
    try {
      const response = await window.API.staff.get(id);
      const s = response?.data || response;
      if (!s) return;

      const name =
        s.name || `${s.first_name || ""} ${s.last_name || ""}`.trim();
      const modalBody = document.getElementById("staffModalBody");
      if (modalBody) {
        modalBody.innerHTML = `
          <div class="row">
            <div class="col-md-4 text-center mb-3">
              <img src="${esc(s.photo || "/images/default-avatar.png")}" class="rounded-circle" style="width:120px;height:120px;object-fit:cover;">
              <h5 class="mt-2">${esc(name)}</h5>
              <span class="badge ${(s.status || "").toLowerCase() === "active" ? "bg-success" : "bg-secondary"}">${esc(s.status || "N/A")}</span>
            </div>
            <div class="col-md-8">
              <table class="table table-sm">
                <tr><th>Staff No</th><td>${esc(s.staff_no || "—")}</td></tr>
                <tr><th>Position</th><td>${esc(s.position || s.role || "—")}</td></tr>
                <tr><th>Department</th><td>${esc(s.department_name || s.department || "—")}</td></tr>
                <tr><th>Email</th><td>${esc(s.email || "—")}</td></tr>
                <tr><th>Phone</th><td>${esc(s.phone || "—")}</td></tr>
                <tr><th>Type</th><td>${esc(s.staff_type || "—")}</td></tr>
                <tr><th>Joined</th><td>${esc(s.date_joined || s.created_at || "—")}</td></tr>
              </table>
            </div>
          </div>`;
      }

      const title = document.getElementById("modalTitle");
      if (title) title.textContent = `Staff: ${name}`;

      const modal = document.getElementById("staffModal");
      if (modal) {
        modal.classList.add("show");
        modal.style.display = "block";
      }
    } catch (error) {
      console.error("viewDetail error:", error);
      notify("Failed to load staff details", "danger");
    }
  }

  async function editStaff(id) {
    // Redirect to manage_staff with edit context
    window.location.href = `/Kingsway/home.php?route=manage_staff&edit=${id}`;
  }

  async function removeStaff(id) {
    if (!confirm("Are you sure you want to delete this staff member?")) return;
    try {
      await window.API.staff.delete(id);
      notify("Staff deleted successfully", "success");
      await loadData();
    } catch (error) {
      console.error("removeStaff error:", error);
      notify("Failed to delete staff", "danger");
    }
  }

  function exportReport() {
    const data = filteredStaff.length > 0 ? filteredStaff : allStaff;
    if (data.length === 0) {
      notify("No data to export", "warning");
      return;
    }

    const headers = [
      "Staff No",
      "Name",
      "Position",
      "Department",
      "Email",
      "Phone",
      "Type",
      "Status",
    ];
    const rows = data.map((s) => [
      s.staff_no || "",
      s.name || `${s.first_name || ""} ${s.last_name || ""}`.trim(),
      s.position || s.role || "",
      s.department_name || s.department || "",
      s.email || "",
      s.phone || "",
      s.staff_type || "",
      s.status || "",
    ]);

    const csv = [
      headers.join(","),
      ...rows.map((r) => r.map((c) => `"${c}"`).join(",")),
    ].join("\n");
    const a = document.createElement("a");
    a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
    a.download = "staff_report.csv";
    a.click();
    notify("Export started", "success");
  }

  // ── Public API ───────────────────────────────────────
  return {
    init,
    loadData,
    loadPage,
    viewDetail,
    editStaff,
    removeStaff,
    exportReport,
  };
})();
