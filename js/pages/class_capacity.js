const ClassCapacityController = (() => {
    let allData = [];
    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = '/Kingsway/index.php'; return; }
        await loadData(); setupEventListeners();
    }
    function setupEventListeners() {
        document.getElementById('searchInput')?.addEventListener('input', filterData);
        document
          .getElementById("filterSelect")
          ?.addEventListener("change", filterData);
    }
    async function loadData() {
        try {
            const r =
              (await window.API.apiCall(
                "/academic/class-capacity",
                "GET",
              ).catch(() => null)) ||
              (await window.API.academic.listClasses().catch(() => null));
            allData = r?.data || r || [];
            renderStats(allData);
            renderTable(Array.isArray(allData) ? allData : []);
        } catch (e) { console.error('Load failed:', e); renderTable([]); }
    }
    function renderStats(data) {
        const items = Array.isArray(data) ? data : [];
        const totalCapacity = items.reduce(
          (s, c) => s + (parseInt(c.capacity) || 40),
          0,
        );
        const totalEnrolled = items.reduce(
          (s, c) =>
            s + (parseInt(c.enrolled || c.student_count || c.students) || 0),
          0,
        );
        const available = totalCapacity - totalEnrolled;
        const utilization =
          totalCapacity > 0
            ? Math.round((totalEnrolled / totalCapacity) * 100)
            : 0;
        const el = (id, val) => {
          const e = document.getElementById(id);
          if (e) e.textContent = val;
        };
        el("statCapacity", totalCapacity);
        el("statEnrolled", totalEnrolled);
        el("statAvailable", Math.max(0, available));
        el("statUtil", utilization + "%");
    }
    function renderTable(items) {
        const tbody = document.querySelector('#dataTable tbody');
        if (!tbody) return;
        if (!items.length) {
          tbody.innerHTML =
            '<tr><td colspan="8" class="text-center text-muted py-4">No class data found</td></tr>';
          return;
        }
        tbody.innerHTML = items
          .map((c, i) => {
            const capacity = parseInt(c.capacity) || 40;
            const enrolled =
              parseInt(c.enrolled || c.student_count || c.students) || 0;
            const available = Math.max(0, capacity - enrolled);
            const util =
              capacity > 0 ? Math.round((enrolled / capacity) * 100) : 0;
            const barColor =
              util >= 90 ? "danger" : util >= 70 ? "warning" : "success";
            return `<tr>
                <td>${i + 1}</td>
                <td><strong>${escapeHtml(c.name || c.class_name || "")}</strong></td>
                <td>${escapeHtml(c.stream || c.stream_name || "--")}</td>
                <td>${capacity}</td>
                <td>${enrolled}</td>
                <td>${available}</td>
                <td><div class="d-flex align-items-center"><div class="progress flex-fill me-2" style="height:6px"><div class="progress-bar bg-${barColor}" style="width:${util}%"></div></div><small>${util}%</small></div></td>
                <td><span class="badge bg-${util >= 90 ? "danger" : util >= 70 ? "warning" : "success"}">${util >= 90 ? "Full" : util >= 70 ? "Near Full" : "Available"}</span></td>
            </tr>`;
          })
          .join("");
    }
    function filterData() {
        const s = (document.getElementById('searchInput')?.value || '').toLowerCase();
        const f = document.getElementById("filterSelect")?.value;
        let filtered = allData;
        if (s)
          filtered = filtered.filter((item) =>
            JSON.stringify(item).toLowerCase().includes(s),
          );
        if (f)
          filtered = filtered.filter((item) => {
            const util =
              parseInt(item.capacity) > 0
                ? Math.round(
                    (parseInt(item.enrolled || item.student_count || 0) /
                      parseInt(item.capacity)) *
                      100,
                  )
                : 0;
            if (f === "full") return util >= 90;
            if (f === "near_full") return util >= 70 && util < 90;
            if (f === "available") return util < 70;
            return true;
          });
        renderTable(filtered);
    }
    function exportCSV() {
        if (!allData.length) return;
        const headers = ['#', 'Class', 'Stream', 'Capacity', 'Enrolled', 'Available', 'Utilization', 'Status'];
        const rows = allData.map((c, i) => {
          const cap = parseInt(c.capacity) || 40;
          const enr = parseInt(c.enrolled || c.student_count || 0);
          const util = cap > 0 ? Math.round((enr / cap) * 100) : 0;
          return [
            i + 1,
            c.name || c.class_name,
            c.stream || "",
            cap,
            enr,
            Math.max(0, cap - enr),
            util + "%",
            util >= 90 ? "Full" : "Available",
          ];
        });
        let csv =
          headers.join(",") +
          "\n" +
          rows
            .map((r) => r.map((v) => '"' + (v || "") + '"').join(","))
            .join("\n");
        const a = document.createElement("a");
        a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
        a.download = "class_capacity.csv";
        a.click();
    }
    function escapeHtml(s) {
      return String(s || "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
    }
    function showNotification(msg, type) {
      const modal = document.getElementById("notificationModal");
      if (modal) {
        const m = modal.querySelector(".notification-message"),
          c = modal.querySelector(".modal-content");
        if (m) m.textContent = msg;
        if (c) c.className = "modal-content notification-" + (type || "info");
        const b = bootstrap.Modal.getOrCreateInstance(modal);
        b.show();
        setTimeout(() => b.hide(), 3000);
      }
    }
    return { init, refresh: loadData, exportCSV };
})();
document.addEventListener('DOMContentLoaded', () => ClassCapacityController.init());
