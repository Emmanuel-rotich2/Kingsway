const ComparativeReportsController = (() => {
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
            const r =
              (await window.API.apiCall(
                "/academic/comparative-reports",
                "GET",
              ).catch(() => null)) ||
              (await window.API.academic?.compileData?.().catch(() => null));
            allData = r?.data || r || [];
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

        const means = items.map(
          (d) => parseFloat(d.mean || d.annual_mean || d.average) || 0,
        );
        const overallMean =
          means.length > 0
            ? (means.reduce((s, v) => s + v, 0) / means.length).toFixed(1)
            : "0.0";
        const bestClass = items.reduce(
          (best, d) => {
            const m = parseFloat(d.mean || d.annual_mean || d.average) || 0;
            return m > best.score
              ? { name: d.class_name || d.name || "--", score: m }
              : best;
          },
          { name: "--", score: 0 },
        );
        const improved = items.filter((d) => {
          const t1 = parseFloat(d.term1_mean || d.term_1) || 0;
          const t3 = parseFloat(d.term3_mean || d.term_3 || d.mean) || 0;
          return t3 > t1 && t1 > 0;
        }).length;
        const leaders = items.filter(
          (d) => (parseFloat(d.mean || d.annual_mean || d.average) || 0) >= 70,
        ).length;

        el("statBestClass", bestClass.name);
        el("statImproved", improved);
        el("statLeaders", leaders);
        el("statMean", overallMean + "%");
    }
    function renderTable(items) {
        const tbody = document.querySelector('#dataTable tbody');
        if (!tbody) return;
        if (!items.length) {
          tbody.innerHTML =
            '<tr><td colspan="7" class="text-center text-muted py-4">No comparative data found</td></tr>';
          return;
        }
        tbody.innerHTML = items
          .map((d, i) => {
            const t1 = parseFloat(d.term1_mean || d.term_1) || 0;
            const t2 = parseFloat(d.term2_mean || d.term_2) || 0;
            const t3 = parseFloat(d.term3_mean || d.term_3) || 0;
            const annual =
              parseFloat(d.annual_mean || d.mean || d.average) ||
              (
                (t1 + t2 + t3) /
                (t1 > 0 ? 1 : 0 + t2 > 0 ? 1 : 0 + t3 > 0 ? 1 : 0 || 1)
              ).toFixed(1);
            const trendUp = t3 > t1 && t1 > 0;
            const trendDown = t3 < t1 && t1 > 0;
            return `<tr>
                <td>${i + 1}</td>
                <td><strong>${escapeHtml(d.class_name || d.name || "--")}</strong></td>
                <td>${t1 > 0 ? t1.toFixed(1) + "%" : "--"}</td>
                <td>${t2 > 0 ? t2.toFixed(1) + "%" : "--"}</td>
                <td>${t3 > 0 ? t3.toFixed(1) + "%" : "--"}</td>
                <td><span class="badge bg-${parseFloat(annual) >= 70 ? "success" : parseFloat(annual) >= 50 ? "warning" : "danger"}">${parseFloat(annual).toFixed(1)}%</span></td>
                <td>${trendUp ? '<i class="fas fa-arrow-up text-success"></i> Improving' : trendDown ? '<i class="fas fa-arrow-down text-danger"></i> Declining' : '<i class="fas fa-minus text-muted"></i> Stable'}</td>
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
            const t1 = parseFloat(item.term1_mean || item.term_1) || 0;
            const t3 =
              parseFloat(item.term3_mean || item.term_3 || item.mean) || 0;
            if (f === "improving") return t3 > t1 && t1 > 0;
            if (f === "declining") return t3 < t1 && t1 > 0;
            if (f === "stable") return Math.abs(t3 - t1) < 5;
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
              labels: items.map((d) => d.class_name || d.name || ""),
              datasets: [
                {
                  label: "Term 1",
                  data: items.map(
                    (d) => parseFloat(d.term1_mean || d.term_1) || 0,
                  ),
                  borderColor: "#6c757d",
                  tension: 0.3,
                  borderDash: [5, 5],
                },
                {
                  label: "Term 2",
                  data: items.map(
                    (d) => parseFloat(d.term2_mean || d.term_2) || 0,
                  ),
                  borderColor: "#ffc107",
                  tension: 0.3,
                },
                {
                  label: "Term 3",
                  data: items.map(
                    (d) => parseFloat(d.term3_mean || d.term_3) || 0,
                  ),
                  borderColor: "#0d6efd",
                  tension: 0.3,
                },
              ],
            },
            options: {
              responsive: true,
              scales: { y: { beginAtZero: false, min: 0, max: 100 } },
            },
          });
        }

        const ctx2 = document.getElementById('comparisonChart')?.getContext('2d');
        if (ctx2) {
          if (charts.comparison) charts.comparison.destroy();
          charts.comparison = new Chart(ctx2, {
            type: "bar",
            data: {
              labels: items.map((d) => d.class_name || d.name || ""),
              datasets: [
                {
                  label: "Annual Mean",
                  data: items.map(
                    (d) =>
                      parseFloat(d.annual_mean || d.mean || d.average) || 0,
                  ),
                  backgroundColor: items.map((d) => {
                    const m =
                      parseFloat(d.annual_mean || d.mean || d.average) || 0;
                    return m >= 70
                      ? "rgba(25,135,84,0.7)"
                      : m >= 50
                        ? "rgba(255,193,7,0.7)"
                        : "rgba(220,53,69,0.7)";
                  }),
                },
              ],
            },
            options: {
              responsive: true,
              scales: { y: { beginAtZero: true, max: 100 } },
            },
          });
        }
    }
    function exportCSV() {
        if (!allData.length) return;
        const headers = ['#', 'Class', 'Term 1 Mean', 'Term 2 Mean', 'Term 3 Mean', 'Annual Mean', 'Trend'];
        const rows = allData.map((d, i) => {
          const t1 = parseFloat(d.term1_mean || d.term_1) || 0;
          const t3 = parseFloat(d.term3_mean || d.term_3 || d.mean) || 0;
          return [
            i + 1,
            d.class_name || d.name,
            (parseFloat(d.term1_mean || d.term_1) || 0).toFixed(1),
            (parseFloat(d.term2_mean || d.term_2) || 0).toFixed(1),
            t3.toFixed(1),
            (parseFloat(d.annual_mean || d.mean || d.average) || 0).toFixed(1),
            t3 > t1 ? "Improving" : t3 < t1 ? "Declining" : "Stable",
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
        a.download = "comparative_reports.csv";
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
document.addEventListener('DOMContentLoaded', () => ComparativeReportsController.init());
