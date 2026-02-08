/**
 * Results Analysis Page Controller
 * Manages exam results analysis, subject means, and chart rendering
 */
const ResultsAnalysisController = (() => {
  let resultsData = [];
  let pagination = { page: 1, limit: 20, total: 0 };
  let chartInstances = {};

  async function loadData(page = 1) {
    try {
      pagination.page = page;
      const params = new URLSearchParams({ page, limit: pagination.limit });

      const term = document.getElementById("termFilterResults")?.value;
      if (term) params.append("term", term);
      const cls = document.getElementById("classFilterResults")?.value;
      if (cls) params.append("class_id", cls);
      const subject = document.getElementById("subjectFilterResults")?.value;
      if (subject) params.append("subject_id", subject);
      const year = document.getElementById("yearFilterResults")?.value;
      if (year) params.append("year", year);

      const response = await window.API.apiCall(
        `/academic/results-analysis?${params.toString()}`,
        "GET",
      );
      const data = response?.data || response || [];
      resultsData = Array.isArray(data)
        ? data
        : data.results || data.subjects || data.data || [];
      if (data.pagination) pagination = { ...pagination, ...data.pagination };
      pagination.total = data.total || resultsData.length;

      renderStats(resultsData);
      renderTable(resultsData);
      renderPagination();
      renderCharts(resultsData);
    } catch (e) {
      console.error("Load results failed:", e);
      renderTable([]);
    }
  }

  async function loadReferenceData() {
    try {
      const [classResp, subjectResp, yearResp] = await Promise.all([
        window.API.apiCall("/academic/classes", "GET").catch(() => []),
        window.API.apiCall("/academic/subjects", "GET").catch(() => []),
        window.API.apiCall("/academic/years", "GET").catch(() => []),
      ]);

      const classes = Array.isArray(classResp?.data || classResp)
        ? classResp?.data || classResp
        : [];
      const subjects = Array.isArray(subjectResp?.data || subjectResp)
        ? subjectResp?.data || subjectResp
        : [];
      const years = Array.isArray(yearResp?.data || yearResp)
        ? yearResp?.data || yearResp
        : [];

      const classSelect = document.getElementById("classFilterResults");
      if (classSelect) {
        classes.forEach((c) => {
          const opt = document.createElement("option");
          opt.value = c.id;
          opt.textContent = c.name || c.class_name || "";
          classSelect.appendChild(opt);
        });
      }

      const subjectSelect = document.getElementById("subjectFilterResults");
      if (subjectSelect) {
        subjects.forEach((s) => {
          const opt = document.createElement("option");
          opt.value = s.id;
          opt.textContent = s.name || s.subject_name || "";
          subjectSelect.appendChild(opt);
        });
      }

      const yearSelect = document.getElementById("yearFilterResults");
      if (yearSelect) {
        years.forEach((y) => {
          const opt = document.createElement("option");
          opt.value = y.academic_year || y.year || y.id;
          opt.textContent = y.year_code || y.year_name || y.year || "";
          if (y.is_current) opt.selected = true;
          yearSelect.appendChild(opt);
        });
      }
    } catch (e) {
      console.warn("Failed to load reference data:", e);
    }
  }

  function renderStats(data) {
    if (!data.length) {
      document.getElementById("overallMean").textContent = "0%";
      document.getElementById("highestSubject").textContent = "-";
      document.getElementById("highestSubjectScore").textContent = "";
      document.getElementById("lowestSubject").textContent = "-";
      document.getElementById("lowestSubjectScore").textContent = "";
      document.getElementById("studentsAssessed").textContent = "0";
      return;
    }

    const means = data.map((d) => parseFloat(d.mean_score || d.mean || 0));
    const overallMean = means.reduce((a, b) => a + b, 0) / means.length;

    let highest = data[0];
    let lowest = data[0];
    data.forEach((d) => {
      const score = parseFloat(d.mean_score || d.mean || 0);
      if (score > parseFloat(highest.mean_score || highest.mean || 0))
        highest = d;
      if (score < parseFloat(lowest.mean_score || lowest.mean || 0)) lowest = d;
    });

    const totalStudents = data.reduce(
      (sum, d) => sum + (d.students_assessed || d.students_count || 0),
      0,
    );

    document.getElementById("overallMean").textContent =
      overallMean.toFixed(1) + "%";
    document.getElementById("highestSubject").textContent =
      highest.subject_name || highest.subject || "-";
    document.getElementById("highestSubjectScore").textContent =
      parseFloat(highest.mean_score || highest.mean || 0).toFixed(1) + "%";
    document.getElementById("lowestSubject").textContent =
      lowest.subject_name || lowest.subject || "-";
    document.getElementById("lowestSubjectScore").textContent =
      parseFloat(lowest.mean_score || lowest.mean || 0).toFixed(1) + "%";
    document.getElementById("studentsAssessed").textContent = totalStudents;
  }

  function renderTable(items) {
    const tbody = document.getElementById("resultsTableBody");
    if (!tbody) return;

    if (!items.length) {
      tbody.innerHTML =
        '<tr><td colspan="8" class="text-center py-4 text-muted">No results data found</td></tr>';
      return;
    }

    tbody.innerHTML = items
      .map((r, i) => {
        const mean = parseFloat(r.mean_score || r.mean || 0);
        const passRate = parseFloat(r.pass_rate || 0);
        const meanColor =
          mean >= 70 ? "success" : mean >= 50 ? "warning" : "danger";
        const passColor =
          passRate >= 80 ? "success" : passRate >= 50 ? "warning" : "danger";

        const gradeA = r.grade_a || r.grades?.A || 0;
        const gradeB = r.grade_b || r.grades?.B || 0;
        const gradeC = r.grade_c || r.grades?.C || 0;
        const gradeD = r.grade_d || r.grades?.D || 0;
        const gradeE = r.grade_e || r.grades?.E || 0;

        return `
                <tr>
                    <td>${(pagination.page - 1) * pagination.limit + i + 1}</td>
                    <td><strong>${r.subject_name || r.subject || "-"}</strong></td>
                    <td>${r.teacher_name || "-"}</td>
                    <td><span class="badge bg-${meanColor}">${mean.toFixed(1)}%</span></td>
                    <td>${r.highest_score || r.highest || "-"}</td>
                    <td>${r.lowest_score || r.lowest || "-"}</td>
                    <td><span class="text-${passColor} fw-bold">${passRate.toFixed(1)}%</span></td>
                    <td>
                        <small>
                            <span class="badge bg-success">A:${gradeA}</span>
                            <span class="badge bg-primary">B:${gradeB}</span>
                            <span class="badge bg-info">C:${gradeC}</span>
                            <span class="badge bg-warning text-dark">D:${gradeD}</span>
                            <span class="badge bg-danger">E:${gradeE}</span>
                        </small>
                    </td>
                </tr>
            `;
      })
      .join("");
  }

  function renderPagination() {
    const container = document.getElementById("pagination");
    if (!container) return;
    const totalPages = Math.ceil(pagination.total / pagination.limit);

    const fromEl = document.getElementById("showingFrom");
    const toEl = document.getElementById("showingTo");
    const totalEl = document.getElementById("totalRecords");
    if (fromEl)
      fromEl.textContent =
        pagination.total > 0 ? (pagination.page - 1) * pagination.limit + 1 : 0;
    if (toEl)
      toEl.textContent = Math.min(
        pagination.page * pagination.limit,
        pagination.total,
      );
    if (totalEl) totalEl.textContent = pagination.total;

    let html = "";
    for (let i = 1; i <= totalPages; i++) {
      html += `<li class="page-item ${i === pagination.page ? "active" : ""}">
                <a class="page-link" href="#" onclick="ResultsAnalysisController.loadPage(${i}); return false;">${i}</a>
            </li>`;
    }
    container.innerHTML = html;
  }

  function renderCharts(data) {
    if (!window.Chart || !data.length) return;

    // Subject Means Bar Chart
    const barCanvas = document.getElementById("subjectMeansChart");
    if (barCanvas) {
      if (chartInstances.bar) chartInstances.bar.destroy();

      const labels = data.map((d) => d.subject_name || d.subject || "");
      const means = data.map((d) => parseFloat(d.mean_score || d.mean || 0));
      const colors = means.map((m) =>
        m >= 70
          ? "rgba(25,135,84,0.7)"
          : m >= 50
            ? "rgba(255,193,7,0.7)"
            : "rgba(220,53,69,0.7)",
      );

      chartInstances.bar = new Chart(barCanvas, {
        type: "bar",
        data: {
          labels,
          datasets: [
            {
              label: "Mean Score %",
              data: means,
              backgroundColor: colors,
              borderColor: colors.map((c) => c.replace("0.7", "1")),
              borderWidth: 1,
            },
          ],
        },
        options: {
          responsive: true,
          scales: { y: { beginAtZero: true, max: 100 } },
          plugins: { legend: { display: false } },
        },
      });
    }

    // Grade Distribution Pie Chart
    const pieCanvas = document.getElementById("gradeDistributionChart");
    if (pieCanvas) {
      if (chartInstances.pie) chartInstances.pie.destroy();

      let totalA = 0,
        totalB = 0,
        totalC = 0,
        totalD = 0,
        totalE = 0;
      data.forEach((d) => {
        totalA += d.grade_a || d.grades?.A || 0;
        totalB += d.grade_b || d.grades?.B || 0;
        totalC += d.grade_c || d.grades?.C || 0;
        totalD += d.grade_d || d.grades?.D || 0;
        totalE += d.grade_e || d.grades?.E || 0;
      });

      chartInstances.pie = new Chart(pieCanvas, {
        type: "doughnut",
        data: {
          labels: ["A", "B", "C", "D", "E"],
          datasets: [
            {
              data: [totalA, totalB, totalC, totalD, totalE],
              backgroundColor: [
                "#198754",
                "#0d6efd",
                "#0dcaf0",
                "#ffc107",
                "#dc3545",
              ],
            },
          ],
        },
        options: { responsive: true },
      });
    }
  }

  function showNotification(message, type) {
    if (window.API?.showNotification)
      window.API.showNotification(message, type);
    else alert((type === "error" ? "Error: " : "") + message);
  }

  function attachListeners() {
    document
      .getElementById("termFilterResults")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("classFilterResults")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("subjectFilterResults")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("yearFilterResults")
      ?.addEventListener("change", () => loadData(1));
    document
      .getElementById("exportResultsBtn")
      ?.addEventListener("click", () => {
        window.open(
          "/Kingsway/api/?route=academic/results-analysis/export&format=csv",
          "_blank",
        );
      });
    document
      .getElementById("printResultsBtn")
      ?.addEventListener("click", () => window.print());
  }

  async function init() {
    attachListeners();
    await loadReferenceData();
    await loadData();
  }

  return { init, refresh: loadData, loadPage: loadData };
})();

document.addEventListener("DOMContentLoaded", () =>
  ResultsAnalysisController.init(),
);
