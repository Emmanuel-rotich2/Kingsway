const YearHistoryController = (() => {
    let allData = [];
    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE || '') + '/index.php'; return; }
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
              (await window.API.apiCall("/academic/year-history", "GET").catch(
                () => null,
              )) ||
              (await window.API.academic
                ?.getAllAcademicYears?.()
                .catch(() => null));
            allData = r?.data || r || [];
            renderStats(allData);
            renderTable(Array.isArray(allData) ? allData : []);
        } catch (e) { console.error('Load failed:', e); renderTable([]); }
    }
    function renderStats(data) {
        const items = Array.isArray(data) ? data : [];
        const el = (id, val) => {
          const e = document.getElementById(id);
          if (e) e.textContent = val;
        };
        el("statTotal", items.length);
        const totalStudents = items.reduce(
          (s, d) =>
            s +
            (parseInt(d.total_students || d.student_count || d.enrollment) ||
              0),
          0,
        );
        el("statStudents", totalStudents > 0 ? totalStudents : "--");
        const perfs = items
          .map(
            (d) =>
              parseFloat(d.performance_avg || d.mean_score || d.average) || 0,
          )
          .filter((v) => v > 0);
        el(
          "statPerformance",
          perfs.length > 0
            ? (perfs.reduce((s, v) => s + v, 0) / perfs.length).toFixed(1) + "%"
            : "--",
        );
        el(
          "statGraduation",
          items.filter(
            (d) =>
              (d.status || "").toLowerCase() === "completed" ||
              d.graduation_count,
          ).length,
        );
    }
    function renderTable(items) {
        const tbody = document.querySelector('#dataTable tbody');
        if (!tbody) return;
        if (!items.length) {
          tbody.innerHTML =
            '<tr><td colspan="8" class="text-center text-muted py-4">No academic year history found</td></tr>';
          return;
        }
        tbody.innerHTML = items
          .map((d, i) => {
            const status = d.status || (d.is_current ? "Current" : "Completed");
            const statusColor =
              status.toLowerCase() === "current" ||
              status.toLowerCase() === "active"
                ? "success"
                : "secondary";
            const perf =
              parseFloat(d.performance_avg || d.mean_score || d.average) || 0;
            const perfColor =
              perf >= 70
                ? "success"
                : perf >= 50
                  ? "warning"
                  : perf > 0
                    ? "danger"
                    : "secondary";
            return `<tr>
                <td>${i + 1}</td>
                <td><strong>${escapeHtml(d.name || d.year || d.academic_year || "--")}</strong></td>
                <td>${d.start_date || "--"}</td>
                <td>${d.end_date || "--"}</td>
                <td>${d.terms || d.term_count || "--"}</td>
                <td>${d.total_students || d.student_count || d.enrollment || "--"}</td>
                <td>${perf > 0 ? `<span class="badge bg-${perfColor}">${perf.toFixed(1)}%</span>` : "--"}</td>
                <td><span class="badge bg-${statusColor}">${status}</span></td>
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
          filtered = filtered.filter(
            (item) => (item.status || "").toLowerCase() === f.toLowerCase(),
          );
        renderTable(filtered);
    }
    function exportCSV() {
        if (!allData.length) return;
        const headers = ['#', 'Year', 'Start Date', 'End Date', 'Terms', 'Students', 'Performance Avg', 'Status'];
        const rows = allData.map((d, i) => [
          i + 1,
          d.name || d.year || d.academic_year,
          d.start_date,
          d.end_date,
          d.terms || d.term_count,
          d.total_students || d.student_count,
          (
            parseFloat(d.performance_avg || d.mean_score || d.average) || 0
          ).toFixed(1),
          d.status || "",
        ]);
        let csv =
          headers.join(",") +
          "\n" +
          rows
            .map((r) => r.map((v) => '"' + (v || "") + '"').join(","))
            .join("\n");
        const a = document.createElement("a");
        a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
        a.download = "year_history.csv";
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
document.addEventListener('DOMContentLoaded', () => YearHistoryController.init());
