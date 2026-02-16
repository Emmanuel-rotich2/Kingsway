const PerformanceAnalysisController = (() => {
    let allData = [];
    let charts = {};
    async function init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = '/Kingsway/index.php'; return; }
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
                "/academic/performance-analysis",
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

        const means = items
          .map((d) => parseFloat(d.mean || d.mean_score || d.average) || 0)
          .filter((v) => v > 0);
        const overallMean =
          means.length > 0
            ? (means.reduce((s, v) => s + v, 0) / means.length).toFixed(1)
            : "0.0";
        const bestSubject = items.reduce(
          (best, d) => {
            const m = parseFloat(d.mean || d.mean_score || d.average) || 0;
            return m > best.score
              ? {
                  name: d.subject_name || d.name || d.subject || "--",
                  score: m,
                }
              : best;
          },
          { name: "--", score: 0 },
        );
        const aboveAvg = items.filter(
          (d) =>
            (parseFloat(d.mean || d.mean_score || d.average) || 0) >=
            parseFloat(overallMean),
        ).length;
        const belowAvg = items.length - aboveAvg;

        el("statMean", overallMean + "%");
        el("statBestSubject", bestSubject.name);
        el("statAboveAvg", aboveAvg);
        el("statBelowAvg", belowAvg);
    }
    function renderTable(items) {
        const tbody = document.querySelector('#dataTable tbody');
        if (!tbody) return;
        if (!items.length) {
          tbody.innerHTML =
            '<tr><td colspan="7" class="text-center text-muted py-4">No performance data found</td></tr>';
          return;
        }
        tbody.innerHTML = items
          .map((d, i) => {
            const mean = parseFloat(d.mean || d.mean_score || d.average) || 0;
            const highest =
              parseFloat(d.highest || d.max_score || d.top_score) || 0;
            const lowest = parseFloat(d.lowest || d.min_score) || 0;
            const passRate = parseFloat(d.pass_rate || d.percentage_pass) || 0;
            const gradeColor =
              mean >= 70 ? "success" : mean >= 50 ? "warning" : "danger";
            return `<tr>
                <td>${i + 1}</td>
                <td>${escapeHtml(d.subject_name || d.name || d.subject || "--")}</td>
                <td>${d.students || d.student_count || d.total_students || "--"}</td>
                <td><span class="badge bg-${gradeColor}">${mean.toFixed(1)}%</span></td>
                <td>${highest.toFixed(1)}%</td>
                <td>${lowest.toFixed(1)}%</td>
                <td>
                    <div class="progress" style="height:18px;">
                        <div class="progress-bar bg-${gradeColor}" style="width:${passRate}%">${passRate.toFixed(0)}%</div>
                    </div>
                </td>
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
            const mean =
              parseFloat(item.mean || item.mean_score || item.average) || 0;
            if (f === "excellent") return mean >= 70;
            if (f === "average") return mean >= 50 && mean < 70;
            if (f === "below") return mean < 50;
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
              labels: items.map(
                (d) => d.subject_name || d.name || d.subject || "",
              ),
              datasets: [
                {
                  label: "Mean Score (%)",
                  data: items.map(
                    (d) => parseFloat(d.mean || d.mean_score || d.average) || 0,
                  ),
                  borderColor: "#0d6efd",
                  tension: 0.3,
                  fill: true,
                  backgroundColor: "rgba(13,110,253,0.1)",
                  pointBackgroundColor: items.map((d) => {
                    const m =
                      parseFloat(d.mean || d.mean_score || d.average) || 0;
                    return m >= 70
                      ? "#198754"
                      : m >= 50
                        ? "#ffc107"
                        : "#dc3545";
                  }),
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
              labels: items.map(
                (d) => d.subject_name || d.name || d.subject || "",
              ),
              datasets: [
                {
                  label: "Highest",
                  data: items.map(
                    (d) => parseFloat(d.highest || d.max_score) || 0,
                  ),
                  backgroundColor: "rgba(25,135,84,0.7)",
                },
                {
                  label: "Mean",
                  data: items.map(
                    (d) => parseFloat(d.mean || d.mean_score || d.average) || 0,
                  ),
                  backgroundColor: "rgba(13,110,253,0.7)",
                },
                {
                  label: "Lowest",
                  data: items.map(
                    (d) => parseFloat(d.lowest || d.min_score) || 0,
                  ),
                  backgroundColor: "rgba(220,53,69,0.7)",
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
        const headers = ['#', 'Subject', 'Students', 'Mean Score', 'Highest', 'Lowest', 'Pass Rate'];
        const rows = allData.map((d, i) => [
          i + 1,
          d.subject_name || d.name || d.subject,
          d.students || d.student_count,
          (parseFloat(d.mean || d.mean_score || d.average) || 0).toFixed(1),
          (parseFloat(d.highest || d.max_score) || 0).toFixed(1),
          (parseFloat(d.lowest || d.min_score) || 0).toFixed(1),
          (parseFloat(d.pass_rate || d.percentage_pass) || 0).toFixed(1),
        ]);
        let csv =
          headers.join(",") +
          "\n" +
          rows
            .map((r) => r.map((v) => '"' + (v || "") + '"').join(","))
            .join("\n");
        const a = document.createElement("a");
        a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
        a.download = "performance_analysis.csv";
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
document.addEventListener('DOMContentLoaded', () => PerformanceAnalysisController.init());
