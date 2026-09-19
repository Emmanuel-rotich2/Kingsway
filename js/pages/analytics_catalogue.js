/**
 * Governed enterprise analytics catalogue and reusable report viewer.
 */
const analyticsCatalogueController = {
  initialized: false,
  state: {
    catalogue: [],
    currentDefinition: null,
    currentResult: null,
    classes: [],
    streams: [],
    terms: [],
    years: [],
    teacherScope: null,
  },

  async init() {
    if (this.initialized) return;
    this.initialized = true;
    await window.AuthContext?.ready?.();
    if (window.AuthContext?.isAuthenticated && !window.AuthContext.isAuthenticated()) {
      window.location.href = `${window.APP_BASE || ""}/index.php`;
      return;
    }
    this.setupEventListeners();
    const dedicatedPage = document.getElementById("governedReportPage");
    if (dedicatedPage?.dataset.reportCode) {
      await this.loadReferenceData();
      await this.openReport(dedicatedPage.dataset.reportCode, true);
      return;
    }
    await Promise.all([this.loadReferenceData(), this.loadCatalogue()]);
  },

  setupEventListeners() {
    document.getElementById("analyticsRefresh")?.addEventListener("click", () => this.loadCatalogue());
    document.getElementById("analyticsDomain")?.addEventListener("change", () => this.renderCatalogue());
    document.getElementById("analyticsSearch")?.addEventListener("input", () => this.renderCatalogue());
    document.getElementById("analyticsFilterForm")?.addEventListener("submit", (event) => this.runReport(event));
    let filterTimer;
    document.getElementById("analyticsFilterForm")?.addEventListener("change", () => {
      window.clearTimeout(filterTimer);
      filterTimer = window.setTimeout(() => this.runReport(), 250);
    });
    document.getElementById("analyticsCatalogueGrid")?.addEventListener("click", (event) => {
      const button = event.target.closest("[data-report-code]");
      if (button) this.openReport(button.dataset.reportCode);
    });
    document.getElementById("analyticsExportButtons")?.addEventListener("click", (event) => {
      const button = event.target.closest("[data-export-format]");
      if (button) this.exportReport(button.dataset.exportFormat);
    });
  },

  async loadReferenceData() {
    const settle = async (call) => {
      try {
        const result = await call();
        return result?.data ?? result ?? [];
      } catch {
        return [];
      }
    };
    const [classes, streams, terms, years, teacherScope] = await Promise.all([
      settle(() => window.API.academic.listClasses()),
      settle(() => window.API.academic.listStreams({ status: "active" })),
      settle(() => window.API.academic.listTerms()),
      settle(() => window.API.academic.listYears()),
      settle(() => window.AuthContext?.getTeacherScope?.()),
    ]);
    this.state.classes = Array.isArray(classes) ? classes : [];
    this.state.streams = Array.isArray(streams) ? streams : [];
    this.state.terms = Array.isArray(terms) ? terms : [];
    this.state.years = Array.isArray(years) ? years : [];
    this.state.teacherScope = teacherScope && !Array.isArray(teacherScope) ? teacherScope : null;
  },

  async loadCatalogue() {
    this.setStatus("Loading governed reports…");
    const grid = document.getElementById("analyticsCatalogueGrid");
    if (grid) grid.replaceChildren(this.loadingElement());
    try {
      const response = await window.API.reports.getCatalogue();
      const rows = response?.data ?? response ?? [];
      this.state.catalogue = Array.isArray(rows) ? rows : [];
      this.populateDomains();
      this.renderCatalogue();
      this.setStatus(`${this.state.catalogue.length} governed reports loaded.`);
    } catch (error) {
      this.state.catalogue = [];
      this.renderError(error?.message || "Unable to load the report catalogue.");
      this.notify("error", error?.message || "Unable to load the report catalogue.");
    }
  },

  populateDomains() {
    const select = document.getElementById("analyticsDomain");
    if (!select) return;
    const selected = select.value;
    const domains = [...new Set(this.state.catalogue.map((report) => report.domain).filter(Boolean))].sort();
    select.replaceChildren(new Option("All available domains", ""));
    domains.forEach((domain) => select.add(new Option(this.label(domain), domain)));
    if (domains.includes(selected)) select.value = selected;
  },

  renderCatalogue() {
    const grid = document.getElementById("analyticsCatalogueGrid");
    if (!grid) return;
    const domain = document.getElementById("analyticsDomain")?.value || "";
    const search = (document.getElementById("analyticsSearch")?.value || "").trim().toLowerCase();
    const reports = this.state.catalogue.filter((report) => {
      if (domain && report.domain !== domain) return false;
      if (!search) return true;
      return [report.title, report.description, report.decision_purpose, report.code, report.category]
        .filter(Boolean)
        .some((value) => String(value).toLowerCase().includes(search));
    });
    document.getElementById("analyticsReportCount").textContent = String(reports.length);
    grid.replaceChildren();
    if (!reports.length) {
      const empty = document.createElement("div");
      empty.className = "col-12";
      const panel = document.createElement("div");
      panel.className = "analytics-empty";
      panel.textContent = "No governed reports match the selected filters or your authorized role scope.";
      empty.appendChild(panel);
      grid.appendChild(empty);
      return;
    }
    reports.forEach((report) => grid.appendChild(this.reportCard(report)));
  },

  reportCard(report) {
    const column = document.createElement("div");
    column.className = "col-md-6 col-xl-4";
    const card = document.createElement("article");
    card.className = "analytics-card p-3 d-flex flex-column";

    const domain = document.createElement("div");
    domain.className = "analytics-domain-label";
    domain.textContent = `${this.label(report.domain)} · ${report.category || "Report"}`;
    const title = document.createElement("h2");
    title.className = "h6 mt-2 mb-2";
    title.textContent = report.title;
    const purpose = document.createElement("p");
    purpose.className = "analytics-purpose mb-2";
    purpose.textContent = report.decision_purpose;
    const meta = document.createElement("div");
    meta.className = "analytics-meta mb-3";
    meta.textContent = `${report.code} v${report.version} · ${report.grain} · ${report.freshness_minutes} min freshness`;
    const actions = document.createElement("div");
    actions.className = "mt-auto d-flex justify-content-between align-items-center";
    const sensitivity = document.createElement("span");
    sensitivity.className = `analytics-meta analytics-sensitivity-${report.sensitivity}`;
    sensitivity.textContent = this.label(report.sensitivity);
    const button = document.createElement("button");
    button.type = "button";
    button.className = "btn btn-sm btn-success";
    button.dataset.reportCode = report.code;
    button.textContent = "Open report";
    actions.append(sensitivity, button);
    card.append(domain, title, purpose, meta, actions);
    column.appendChild(card);
    return column;
  },

  async openReport(code, inline = false) {
    this.setStatus(`Loading ${code}…`);
    try {
      const response = await window.API.reports.getDefinition(code);
      this.state.currentDefinition = response?.data ?? response;
      this.state.currentResult = null;
      this.renderDefinition();
      await this.runReport();
      if (!inline) {
        bootstrap.Modal.getOrCreateInstance(document.getElementById("analyticsReportModal")).show();
      }
    } catch (error) {
      this.notify("error", error?.message || "Unable to open this report.");
    }
  },

  renderDefinition() {
    const definition = this.state.currentDefinition;
    if (!definition) return;
    document.getElementById("analyticsReportDomain").textContent = `${this.label(definition.domain)} · ${definition.category}`;
    document.getElementById("analyticsReportModalTitle").textContent = definition.title;
    document.getElementById("analyticsDecisionPurpose").textContent = definition.decision_purpose;
    document.getElementById("analyticsDefinitionMeta").textContent =
      `${definition.code} v${definition.version} · ${definition.grain} · Source: ${definition.source_name} · Freshness: ${definition.freshness_minutes} minutes · ${this.label(definition.sensitivity)}`;
    this.renderMetrics(definition.metrics || []);
    this.renderFilters(definition.allowed_filters || []);
    document.getElementById("analyticsResultRegion").hidden = true;
  },

  renderMetrics(metrics) {
    const panel = document.getElementById("analyticsMetricPanel");
    panel.replaceChildren();
    metrics.forEach((metric) => {
      const col = document.createElement("div");
      col.className = "col-lg-6";
      const card = document.createElement("div");
      card.className = "analytics-metric h-100";
      const name = document.createElement("div");
      name.className = "fw-semibold";
      name.textContent = `${metric.name} · v${metric.version}`;
      const formula = document.createElement("div");
      formula.className = "small text-muted mt-1";
      formula.textContent = metric.formula_text;
      card.append(name, formula);
      col.appendChild(card);
      panel.appendChild(col);
    });
  },

  renderFilters(filters) {
    const target = document.getElementById("analyticsFilterFields");
    target.replaceChildren();
    if (!filters.length) {
      const message = document.createElement("div");
      message.className = "col-12 analytics-meta";
      message.textContent = "This report uses its governed default reporting period and your authorized scope.";
      target.appendChild(message);
      return;
    }
    filters.forEach((filter) => target.appendChild(this.filterField(filter)));
    this.applyTeacherFilterDefaults(filters);
  },

  applyTeacherFilterDefaults(filters) {
    const scope = this.state.teacherScope;
    if (!scope?.is_teacher || !filters.includes("class_id")) return;
    const preferred = Array.isArray(scope.class_teacher_pairs) && scope.class_teacher_pairs.length
      ? scope.class_teacher_pairs : (scope.class_stream_pairs || []);
    if (!preferred.length) return;
    const classSelect = document.getElementById("analyticsFilter_class_id");
    const streamSelect = document.getElementById("analyticsFilter_stream_id");
    if (!classSelect) return;
    const assignedClassIds = [...new Set(preferred.map(pair => Number(pair.class_id)).filter(Boolean))];
    Array.from(classSelect.options).forEach(option => {
      if (option.value && !assignedClassIds.includes(Number(option.value))) option.remove();
    });
    if (!classSelect.value && assignedClassIds.length) classSelect.value = String(assignedClassIds[0]);

    const syncStream = () => {
      if (!streamSelect) return;
      const classId = Number(classSelect.value);
      const allowed = [...new Set(preferred.filter(pair => Number(pair.class_id) === classId).map(pair => Number(pair.stream_id)).filter(Boolean))];
      Array.from(streamSelect.options).forEach(option => {
        if (!option.value) return;
        option.hidden = !allowed.includes(Number(option.value));
        option.disabled = !allowed.includes(Number(option.value));
      });
      if (!allowed.includes(Number(streamSelect.value))) streamSelect.value = allowed.length ? String(allowed[0]) : "";
    };
    syncStream();
    classSelect.addEventListener("change", syncStream);
  },

  filterField(filter) {
    const wrapper = document.createElement("div");
    wrapper.className = "col-md-6 col-lg-4";
    const label = document.createElement("label");
    label.className = "form-label small fw-semibold";
    label.htmlFor = `analyticsFilter_${filter}`;
    label.textContent = this.label(filter);
    let input;
    if (["class_id", "stream_id", "term_id", "academic_term_id", "year", "academic_year", "academic_year_id"].includes(filter)) {
      input = document.createElement("select");
      input.className = "form-select form-select-sm";
      input.add(new Option(`All authorized ${this.label(filter).toLowerCase()}`, ""));
      const rows = filter === "class_id"
        ? this.state.classes
        : filter === "stream_id"
          ? this.state.streams
          : ["year", "academic_year", "academic_year_id"].includes(filter)
            ? this.state.years
            : this.state.terms;
      const seenValues = new Set();
      rows.forEach((row) => {
        const yearValue = String(row.start_date || row.year_code || row.year_name || row.year || "").match(/\d{4}/)?.[0] || "";
        const value = filter === "stream_id"
          ? (row.stream_id ?? row.id)
          : filter === "class_id"
            ? (row.class_id ?? row.id)
            : filter === "academic_term_id"
              ? (row.academic_year_term_id ?? row.id ?? row.term_id)
              : filter === "academic_year_id"
                ? row.id
                : ["year", "academic_year"].includes(filter)
                  ? yearValue
                  : (row.term_id ?? row.id);
        const text = filter === "stream_id"
          ? (row.stream_name ?? row.name)
          : filter === "class_id"
            ? (row.class_name ?? row.name)
            : ["year", "academic_year", "academic_year_id"].includes(filter)
              ? (row.year_name ?? row.year_code ?? row.name)
              : (row.term_name ?? row.name ?? row.year_name);
        const normalizedValue = value == null ? "" : String(value);
        if (!normalizedValue || !String(text || "").trim() || seenValues.has(normalizedValue)) return;
        seenValues.add(normalizedValue);
        const option = new Option(String(text), normalizedValue);
        option.selected = row.is_current === 1 || row.is_current === true || row.status === "current";
        input.add(option);
      });
    } else {
      input = document.createElement("input");
      input.className = "form-control form-control-sm";
      input.type = ["date", "date_from", "date_to"].includes(filter) ? "date" : (filter.endsWith("_id") || filter === "year" ? "number" : "text");
      if (input.type === "number") input.min = "1";
    }
    input.id = `analyticsFilter_${filter}`;
    input.name = filter;
    wrapper.append(label, input);
    return wrapper;
  },

  async runReport(event = null) {
    event?.preventDefault?.();
    const definition = this.state.currentDefinition;
    if (!definition) return;
    this.setStatus(`Refreshing ${definition.title}…`);
    try {
      const filterForm = document.getElementById("analyticsFilterForm");
      const form = new FormData(filterForm);
      const filters = {};
      form.forEach((value, key) => {
        if (String(value).trim() !== "") filters[key] = value;
      });
      const response = await window.API.reports.execute(definition.code, filters);
      this.state.currentResult = response?.data ?? response;
      this.renderResult();
      this.setStatus(`${definition.title} refreshed automatically.`);
    } catch (error) {
      this.notify("error", error?.message || "The report could not be generated.");
    } finally {}
  },

  renderResult() {
    const result = this.state.currentResult;
    if (!result) return;
    const region = document.getElementById("analyticsResultRegion");
    region.hidden = false;
    const run = result.run || {};
    document.getElementById("analyticsRunMeta").textContent =
      `Run #${run.id || "—"} · ${result.row_count || 0} rows · ${run.duration_ms || 0} ms · As of ${this.formatDate(result.as_of)}`;
    this.renderSummary(result.summary || {});
    this.renderWarnings(result.warnings || []);
    this.renderVisuals(result.rows || [], result.columns || [], result.visualizations || []);
    this.renderTable(result.rows || [], result.columns || []);
    this.renderExportButtons(result.permitted_exports || []);
    region.scrollIntoView({ behavior: "smooth", block: "start" });
  },

  renderSummary(summary) {
    const target = document.getElementById("analyticsSummary");
    target.replaceChildren();
    Object.entries(summary).forEach(([key, value]) => {
      if (value !== null && typeof value === "object") return;
      const col = document.createElement("div");
      col.className = "col-6 col-lg-3";
      const card = document.createElement("div");
      card.className = "analytics-summary-card";
      const label = document.createElement("div");
      label.className = "analytics-meta";
      label.textContent = this.label(key);
      const content = document.createElement("div");
      content.className = "fw-bold";
      content.textContent = this.displayValue(value);
      card.append(label, content);
      col.appendChild(card);
      target.appendChild(col);
    });
  },

  renderWarnings(warnings) {
    const target = document.getElementById("analyticsWarnings");
    target.hidden = warnings.length === 0;
    target.textContent = warnings.map((warning) => warning.message || String(warning)).join(" · ");
  },

  renderVisuals(rows, configuredColumns = [], visualizations = []) {
    const page = document.getElementById("governedReportPage");
    const region = document.getElementById("analyticsVisualRegion");
    if (!page || !region || !window.ReportComponents || !rows.length) {
      if (region) region.hidden = true;
      return;
    }
    region.hidden = false;
    const keys = Object.keys(rows[0] || {});
    const configured = configuredColumns.map((column) => typeof column === "string" ? { key: column } : column).filter((column) => keys.includes(column.key));
    const numeric = configured.filter((column) => ["number", "integer", "decimal", "percent", "currency"].includes(String(column.type || "").toLowerCase())).map((column) => column.key);
    const inferredNumeric = keys.filter((key) => rows.some((row) => row[key] !== null && row[key] !== "" && Number.isFinite(Number(row[key]))));
    const valueCandidates = numeric.length ? numeric : inferredNumeric;
    const dimensions = keys.filter((key) => !valueCandidates.includes(key));
    if (!valueCandidates.length) { region.hidden = true; return; }
    const labelKey = dimensions[0] || keys[0];
    const valueKeys = valueCandidates.slice(0, 3);
    const declaredVisual = (Array.isArray(visualizations) ? visualizations : []).find((visual) => !["table", "kpi", "funnel"].includes(String(typeof visual === "string" ? visual : visual?.type).toLowerCase()));
    const type = String(typeof declaredVisual === "string" ? declaredVisual : declaredVisual?.type || page.dataset.primaryVisual || "bar").toLowerCase();
    const title = document.getElementById("analyticsPrimaryVisualTitle");
    if (title) title.textContent = `${this.label(valueKeys[0])} by ${this.label(labelKey)}`;
    const spec = { labels: rows.slice(0, 20).map((row) => row[labelKey]), datasets: valueKeys.map((key) => ({ label: this.label(key), data: rows.slice(0, 20).map((row) => Number(row[key]) || 0) })) };
    const visual = document.getElementById("analyticsPrimaryVisual");
    visual.innerHTML = '<canvas id="analyticsPrimaryChart"></canvas>';
    if (type === "treemap") {
      window.ReportComponents.treemap(visual, rows.slice(0, 16).map((row) => ({ label: row[labelKey], value: row[valueKeys[0]] })));
    } else if (type === "box" || type === "violin") {
      const groups = rows.slice(0, 8).map((row) => ({ label: row[labelKey], values: valueKeys.map((key) => Number(row[key]) || 0) }));
      (type === "box" ? window.ReportComponents.boxPlot : window.ReportComponents.violinPlot)(visual, groups);
    } else if (type === "histogram") {
      window.ReportComponents.chart("#analyticsPrimaryChart", "histogram", { values: rows.map((row) => row[valueKeys[0]]), label: this.label(valueKeys[0]) });
    } else if (type === "waterfall") {
      window.ReportComponents.chart("#analyticsPrimaryChart", "waterfall", { labels: spec.labels, changes: spec.datasets[0].data, label: spec.datasets[0].label });
    } else if (type === "bullet") {
      window.ReportComponents.chart("#analyticsPrimaryChart", "bullet", { labels: spec.labels, current: spec.datasets[0].data, target: spec.datasets[1]?.data || spec.datasets[0].data.map((v) => v * 1.1) });
    } else {
      window.ReportComponents.chart("#analyticsPrimaryChart", type, spec, type === "radar" ? { scales: { r: { beginAtZero: true } } } : {});
    }
    const pivot = document.getElementById("analyticsPivot");
    if (dimensions.length > 1) {
      window.ReportComponents.pivot(pivot, rows, { row: dimensions[0], column: dimensions[1], value: valueKeys[0], aggregate: "sum" });
    } else {
      window.ReportComponents.heatmapTable(pivot, [{key:labelKey,label:this.label(labelKey)},{key:valueKeys[0],label:this.label(valueKeys[0]),format:'number'}], rows.slice(0,12), [valueKeys[0]]);
    }
  },

  renderTable(rows, configuredColumns) {
    const table = document.getElementById("analyticsResultTable");
    const noRows = document.getElementById("analyticsNoRows");
    table.hidden = rows.length === 0;
    noRows.hidden = rows.length !== 0;
    const head = table.querySelector("thead");
    const body = table.querySelector("tbody");
    head.replaceChildren();
    body.replaceChildren();
    if (!rows.length) return;
    const available = new Set(Object.keys(rows[0] || {}));
    const selected = configuredColumns.filter((column) => available.has(typeof column === "string" ? column : column.key));
    const columns = selected.length ? selected : Object.keys(rows[0]);
    const headerRow = document.createElement("tr");
    columns.forEach((column) => {
      const key = typeof column === "string" ? column : column.key;
      const th = document.createElement("th");
      th.scope = "col";
      th.textContent = typeof column === "string" ? this.label(key) : (column.label || this.label(key));
      headerRow.appendChild(th);
    });
    head.appendChild(headerRow);
    rows.forEach((row) => {
      const tr = document.createElement("tr");
      columns.forEach((column) => {
        const key = typeof column === "string" ? column : column.key;
        const td = document.createElement("td");
        td.textContent = this.displayValue(row[key]);
        tr.appendChild(td);
      });
      body.appendChild(tr);
    });
    this.state.currentResult.renderedColumns = columns;
  },

  renderExportButtons(formats) {
    const target = document.getElementById("analyticsExportButtons");
    target.replaceChildren();
    formats.filter((format) => ["pdf", "csv"].includes(format)).forEach((format) => {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "btn btn-outline-secondary";
      button.dataset.exportFormat = format;
      button.textContent = format.toUpperCase();
      target.appendChild(button);
    });
  },

  async exportReport(format) {
    const result = this.state.currentResult;
    if (!result?.rows?.length) return;
    const columns = (result.renderedColumns || Object.keys(result.rows[0])).map((column) => {
      const key = typeof column === "string" ? column : column.key;
      return { key, label: typeof column === "string" ? this.label(key) : (column.label || this.label(key)) };
    });
    const options = {
      title: result.report.title,
      subtitle: `${result.report.code} v${result.report.version} · As of ${this.formatDate(result.as_of)}`,
      description: result.report.decision_purpose,
      rows: result.rows,
      columns,
      summary: result.summary || {},
      filters: result.filters || {},
      filename: result.report.code.toLowerCase(),
      orientation: columns.length > 6 ? "landscape" : "portrait",
      analyticsRunId: result.run?.id,
      analyticsReportCode: result.report.code,
      analyticsReportVersion: result.report.version,
    };
    if (format === "pdf") {
      await window.PrintManager.printTable(options);
    } else if (format === "csv") {
      await window.PrintManager.exportToServerCSV(options);
    }
  },

  loadingElement() {
    const col = document.createElement("div");
    col.className = "col-12 text-center py-5";
    col.innerHTML = '<span class="spinner-border text-success" role="status"><span class="visually-hidden">Loading reports</span></span>';
    return col;
  },

  renderError(message) {
    const grid = document.getElementById("analyticsCatalogueGrid");
    grid.replaceChildren();
    const col = document.createElement("div");
    col.className = "col-12";
    const alert = document.createElement("div");
    alert.className = "alert alert-danger";
    alert.textContent = message;
    col.appendChild(alert);
    grid.appendChild(col);
    document.getElementById("analyticsReportCount").textContent = "0";
  },

  label(value) {
    const humanLabels = {
      class_id: "Class",
      stream_id: "Stream",
      term_id: "Term",
      academic_term_id: "Academic Term",
      academic_year_id: "Academic Year",
      academic_year: "Academic Year",
      year: "Academic Year",
      learning_area_id: "Learning Area",
      date_from: "From Date",
      date_to: "To Date",
    };
    if (humanLabels[value]) return humanLabels[value];
    return String(value || "")
      .replace(/_/g, " ")
      .replace(/\b\w/g, (letter) => letter.toUpperCase());
  },

  displayValue(value) {
    if (value === null || value === undefined || value === "") return "—";
    if (typeof value === "object") return JSON.stringify(value);
    return String(value);
  },

  formatDate(value) {
    if (!value) return "—";
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString("en-KE");
  },

  setStatus(message) {
    const status = document.getElementById("analyticsCatalogueStatus");
    if (status) status.textContent = message;
  },

  notify(type, message) {
    if (typeof window.showNotification === "function") {
      window.showNotification(message, type);
    } else {
      console[type === "error" ? "error" : "log"](message);
    }
  },
};

window.analyticsCatalogueController = analyticsCatalogueController;
document.addEventListener("DOMContentLoaded", () => analyticsCatalogueController.init());
