const AttendanceTrendsController = (() => {
    let allData = [];
    let charts = {};
    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE || '') + '/index.php'; return; }
        await loadData(); setupEventListeners();
    }
    function setupEventListeners() {
        document.getElementById('searchInput')?.addEventListener('input', filterData);
        document.getElementById('filterSelect')?.addEventListener('change', filterData);
        document
          .getElementById("dateFilter")
          ?.addEventListener("change", loadData);
    }
    async function loadData() {
        try {
            const dateFilter = document.getElementById("dateFilter")?.value;
            const r =
              (await window.API.apiCall(
                "/attendance/trends",
                "GET",
                null,
                dateFilter ? { date: dateFilter } : {},
              ).catch(() => null)) ||
              (await window.API.attendance
                ?.getClassAttendance?.()
                .catch(() => null));
            allData = r || [];
            renderStats(allData); renderTable(Array.isArray(allData) ? allData : []);
            renderCharts(allData);
        } catch (e) { console.error('Load failed:', e); renderTable([]); }
    }
    function renderStats(data) {
        const items = Array.isArray(data) ? data : [];
        const el = (id, val) => {
          const e = document.getElementById(id);
          if (e) e.textContent = val;
        };
        const totalPresent = items.reduce(
          (s, d) => s + (parseInt(d.present) || 0),
          0,
        );
        const totalAbsent = items.reduce(
          (s, d) => s + (parseInt(d.absent) || 0),
          0,
        );
        const totalStudents = totalPresent + totalAbsent;
        const todayRate =
          totalStudents > 0
            ? Math.round((totalPresent / totalStudents) * 100)
            : 0;

        el("statToday", todayRate + "%");
        el(
          "statWeekly",
          items.length > 0
            ? Math.round(
                items
                  .slice(-5)
                  .reduce(
                    (s, d) =>
                      s + (parseFloat(d.attendance_rate || d.percentage) || 0),
                    0,
                  ) / Math.min(items.length, 5),
              ) + "%"
            : "0%",
        );
        el(
          "statMonthly",
          items.length > 0
            ? Math.round(
                items.reduce(
                  (s, d) =>
                    s + (parseFloat(d.attendance_rate || d.percentage) || 0),
                  0,
                ) / items.length,
              ) + "%"
            : "0%",
        );
        el(
          "statAbsentees",
          totalAbsent ||
            items.filter(
              (d) => (parseFloat(d.attendance_rate || d.percentage) || 0) < 75,
            ).length,
        );
    }
    function renderTable(items) {
        const tbody = document.querySelector('#dataTable tbody');
        if (!tbody) return;
        if (!items.length) {
          tbody.innerHTML =
            '<tr><td colspan="7" class="text-center text-muted py-4">No attendance trend data found</td></tr>';
          return;
        }
        tbody.innerHTML = items
          .map((d, i) => {
            const present = parseInt(d.present) || 0;
            const absent = parseInt(d.absent) || 0;
            const late = parseInt(d.late) || 0;
            const total = present + absent;
            const rate =
              total > 0
                ? ((present / total) * 100).toFixed(1)
                : d.attendance_rate || d.percentage || 0;
            const rateColor =
              rate >= 90 ? "success" : rate >= 75 ? "warning" : "danger";
            return `<tr>
                <td>${i + 1}</td>
                <td>${escapeHtml(d.date || d.week || d.period || "--")}</td>
                <td>${escapeHtml(d.class_name || d.name || "All")}</td>
                <td class="text-success">${present}</td>
                <td class="text-danger">${absent}</td>
                <td>${late}</td>
                <td><span class="badge bg-${rateColor}">${rate}%</span></td>
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
            const rate =
              parseFloat(item.attendance_rate || item.percentage) || 0;
            if (f === "excellent") return rate >= 90;
            if (f === "good") return rate >= 75 && rate < 90;
            if (f === "poor") return rate < 75;
            return true;
          });
        renderTable(filtered);
    }
    function renderCharts(data) {
        const items = Array.isArray(data) ? data : [];
        if (typeof Chart === "undefined") return;

        const ctx1 = document.getElementById('trendChart')?.getContext('2d');
        if (ctx1) {
          if (charts.trend) charts.trend.destroy();
          charts.trend = new Chart(ctx1, {
            type: "line",
            data: {
              labels: items
                .slice(-15)
                .map((d) => d.date || d.week || d.period || ""),
              datasets: [
                {
                  label: "Attendance Rate (%)",
                  data: items.slice(-15).map((d) => {
                    const p = parseInt(d.present) || 0;
                    const a = parseInt(d.absent) || 0;
                    return p + a > 0
                      ? ((p / (p + a)) * 100).toFixed(1)
                      : d.attendance_rate || d.percentage || 0;
                  }),
                  borderColor: "#0d6efd",
                  tension: 0.3,
                  fill: true,
                  backgroundColor: "rgba(13,110,253,0.1)",
                },
              ],
            },
            options: {
              responsive: true,
              scales: { y: { beginAtZero: false, min: 50, max: 100 } },
              plugins: { legend: { display: true } },
            },
          });
        }

        const ctx2 = document.getElementById('comparisonChart')?.getContext('2d');
        if (ctx2) {
          if (charts.comparison) charts.comparison.destroy();
          charts.comparison = new Chart(ctx2, {
            type: "bar",
            data: {
              labels: items
                .slice(0, 10)
                .map((d) => d.class_name || d.name || d.date || ""),
              datasets: [
                {
                  label: "Present",
                  data: items.slice(0, 10).map((d) => parseInt(d.present) || 0),
                  backgroundColor: "rgba(25,135,84,0.7)",
                },
                {
                  label: "Absent",
                  data: items.slice(0, 10).map((d) => parseInt(d.absent) || 0),
                  backgroundColor: "rgba(220,53,69,0.7)",
                },
              ],
            },
            options: {
              responsive: true,
              scales: {
                x: { stacked: true },
                y: { stacked: true, beginAtZero: true },
              },
            },
          });
        }
    }
    function exportCSV() {
        if (!allData.length) return;
        const headers = ['#', 'Date', 'Class', 'Present', 'Absent', 'Late', 'Attendance %'];
        const rows = allData.map((d, i) => {
          const p = parseInt(d.present) || 0;
          const a = parseInt(d.absent) || 0;
          return [
            i + 1,
            d.date || d.period,
            d.class_name || d.name,
            p,
            a,
            d.late || 0,
            p + a > 0
              ? ((p / (p + a)) * 100).toFixed(1)
              : d.attendance_rate || 0,
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
        a.download = "attendance_trends.csv";
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
document.addEventListener('DOMContentLoaded', () => AttendanceTrendsController.init());
