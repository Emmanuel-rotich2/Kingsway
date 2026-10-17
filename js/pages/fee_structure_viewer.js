console.info('[FeeStructureViewer] controller loaded');

/**
 * Fee Structure Viewer Controller
 * Read-only overview for Headteacher, Deputy, HODs
 *
 * Features:
 * - View fee structures
 * - Export reports
 * - Print summaries
 * - No create/edit/delete capabilities
 */

class FeeStructureViewerController {
  constructor() {
    this.currentPage = 1;
    this.itemsPerPage = 20;
    this.currentFilters = {};
    this.chart = null;
    this.userRole = window.AuthContext?.getRoles?.()?.[0] || "viewer";

    this.academicYears = [];
    this.levels = [];
    this.studentTypes = [];
    this.terms = [];
    this.termNameMap = {};
    this.termsByYear = {};

    this.currentStructures = [];
    this.currentAggregated = [];
    this.state = { referenceCache: null };
  }

  /**
   * Initialize the controller
   */
  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.resolve("finance", "fee-structure-viewer-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Fee structure viewer Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  }

  static async init() {
    const pageBody = document.getElementById('activeFeeMatrix');
    if (pageBody?.dataset.feeStructureViewerInitialized === '1') {
      return window.viewerController || null;
    }
    if (pageBody) pageBody.dataset.feeStructureViewerInitialized = '1';
    if (window.__feeStructureViewerInitialising) return window.__feeStructureViewerInitialising;
    window.__feeStructureViewerInitialising = (async () => {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    const controller = new FeeStructureViewerController();
    window.viewerController = controller;
    controller.setupEventListeners();
    await controller.loadReferencesComposed();
    await controller.loadDropdowns();
    await controller.loadFeeStructures();
    const roleValues = [
      ...(window.AuthContext?.getRoles?.() || []),
      window.AuthContext?.getUser?.()?.role_name,
      window.AuthContext?.getUser?.()?.role,
    ].filter(Boolean);
    const reviewRoles = roleValues.map(r => {
      if (r && typeof r === 'object') return String(r.name || r.role_name || r.label || '').toLowerCase();
      return String(r).toLowerCase();
    });
    if (reviewRoles.some(r => r.includes('headteacher'))) {
      controller.loadPendingReviews();
    } else {
      document.getElementById('headteacherFeeReviewCard')?.remove();
    }
    controller.initializeChart();
    window.APIRealtime?.register?.("fee_structure_viewer", {
      refresh: async () => {
        controller.state.referenceCache = null;
        await controller.loadReferencesComposed();
        await controller.loadDropdowns();
        await controller.loadFeeStructures();
      },
    }, ["academic", "finance"]);
    return controller;
    })();
    try {
      return await window.__feeStructureViewerInitialising;
    } finally {
      window.__feeStructureViewerInitialising = null;
    }
  }

  async loadPendingReviews() {
    const body = document.getElementById('viewerPendingFeeBody');
    const badge = document.getElementById('viewerPendingFeeCount');
    if (!body) return;
    try {
      let resp = await window.API.apiCall('/finance/fees-bundle-list?status=submitted&limit=100', 'GET');
      let bundles = Array.isArray(resp)
        ? resp
        : (resp?.bundles || resp?.data?.bundles || resp?.data || []);
      // Some older router paths do not forward query-string filters. In that
      // case fetch the queue once without the filter and apply the same rule
      // in the browser so submitted structures are still visible.
      if (!bundles.length) {
        resp = await window.API.apiCall('/finance/fees-bundle-list?limit=100', 'GET');
        const allBundles = Array.isArray(resp)
          ? resp
          : (resp?.bundles || resp?.data?.bundles || resp?.data || []);
        bundles = allBundles.filter(b => String(b.status || '').toLowerCase() === 'submitted');
      }
      const grouped = Object.values(bundles.reduce((groups, bundle) => {
        const key = String(bundle.academic_year || bundle.academic_year_id || '');
        if (!groups[key]) groups[key] = { key, academic_year: bundle.academic_year, ids: [], terms: new Set(), types: new Set(), classes: 0 };
        const group = groups[key];
        group.ids.push(Number(bundle.id));
        group.terms.add(bundle.term_name || `Term ${bundle.term_id}`);
        group.types.add(bundle.student_type_name || bundle.student_type_id);
        group.classes = Math.max(group.classes, Number(bundle.class_count || 0));
        return groups;
      }, {})).map(group => ({ ...group, terms: [...group.terms], types: [...group.types] }));
      if (badge) badge.textContent = `${grouped.length} pending`;
      this.pendingReviewBundles = grouped;
      if (!grouped.length) {
        body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No structures awaiting review.</td></tr>';
        return;
      }
      body.innerHTML = grouped.map(group => `<tr>
        <td>${this.escapeHtml(group.academic_year)}</td>
        <td>${this.escapeHtml(group.terms.join(', '))}</td>
        <td>${this.escapeHtml(group.types.join(', '))}</td>
        <td>${group.classes ? `${group.classes} classes` : 'All configured classes'}</td>
        <td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="window.viewerController.reviewFeeBundle('${this.escapeHtml(group.key)}')"><i class="bi bi-chat-square-text"></i> Review</button></td>
      </tr>`).join('');
    } catch (e) {
      body.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-3">${this.escapeHtml(e.message || 'Failed to load review queue')}</td></tr>`;
    }
  }

  async reviewFeeBundle(id) {
    const bundle = this.pendingReviewBundles?.find(b => String(b.key) === String(id));
    const modal = document.getElementById('feeReviewModal');
    const body = document.getElementById('feeReviewModalBody');
    const meta = document.getElementById('feeReviewMeta');
    const notes = document.getElementById('feeReviewNotes');
    if (!modal || !body) return;
    if (notes) notes.value = '';
    body.innerHTML = '<div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm me-2"></div>Loading saved structure…</div>';
    if (meta) meta.textContent = `${bundle?.academic_year || ''} · ${bundle?.terms?.join(', ') || ''} · ${bundle?.types?.join(', ') || ''}`;
    this.reviewingBundleIds = bundle?.ids || [];
    bootstrap.Modal.getOrCreateInstance(modal).show();
    try {
      await this.loadReviewMatrix(bundle?.academic_year || '');
    } catch (e) {
      body.innerHTML = `<div class="alert alert-danger">${this.escapeHtml(e.message || 'Failed to load saved structure')}</div>`;
    }
  }

  async loadReviewMatrix(academicYear) {
    if (window.FeeStructureMatrix) {
      const model = await window.FeeStructureMatrix.load(academicYear);
      this.reviewMatrixModel = model;
      window.FeeStructureMatrix.render(document.getElementById('feeReviewModalBody'), model);
      const submit = document.getElementById('submitFeeReviewBtn');
      if (submit) submit.onclick = () => this.submitReviewFeedback();
      return;
    }
    const [classesResponse, typesResponse] = await Promise.all([
      window.API.academic.listClasses(),
      window.API.finance.listStudentTypes(),
    ]);
    const classes = this.unwrapList(classesResponse).sort((a, b) => Number(a.sort_order || a.id) - Number(b.sort_order || b.id));
    const types = this.unwrapList(typesResponse).filter(t => String(t.code || '').toUpperCase() !== 'WEEKLY');
    if (!classes.length || !types.length) throw new Error('Grades or student types are not configured.');
    const typeIds = types.map(t => Number(t.id));
    const response = await window.API.apiCall(`/finance/fees-bundle-grid?academic_year=${encodeURIComponent(academicYear)}&from_id=${classes[0].id}&to_id=${classes[classes.length - 1].id}&student_type_ids=${typeIds.join(',')}`, 'GET');
    const data = response?.class_grid ? response : (response?.data || response || {});
    this.renderReviewMatrix(classes, types, data.class_grid || {});
  }

  renderReviewMatrix(classes, types, grid) {
    const body = document.getElementById('feeReviewModalBody');
    if (!body) return;
    const terms = [1, 2, 3];
    let html = `<div class="table-responsive"><table class="table table-bordered table-sm align-middle mb-0">
      <thead class="table-success text-center"><tr><th class="text-start">Grade</th><th class="text-start">Student Type</th>${terms.map(n => `<th>Term ${n}</th>`).join('')}<th>Total</th></tr></thead><tbody>`;
    classes.forEach(cls => {
      types.forEach((type, typeIndex) => {
        const values = terms.map(n => Number(grid?.[cls.id]?.TUITION?.[`term${n}`]?.[String(type.id)] || 0));
        const hasValue = values.some(v => v > 0);
        html += `<tr>${typeIndex === 0 ? `<td rowspan="${types.length}" class="fw-semibold align-middle">${this.escapeHtml(cls.name || cls.code || cls.id)}</td>` : ''}
          <td class="small fw-semibold">${this.escapeHtml(type.name || type.code || type.id)}</td>
          ${values.map(v => `<td class="text-end">${hasValue && v ? this.formatCurrency(v).replace('KES ', '') : '–'}</td>`).join('')}
          <td class="text-end fw-semibold">${hasValue ? values.reduce((a, v) => a + v, 0).toLocaleString('en-KE') : '–'}</td></tr>`;
      });
    });
    html += `</tbody><tfoot class="table-light"><tr><td colspan="2" class="fw-bold">Column Total</td>${terms.map(n => {
      let total = 0;
      classes.forEach(cls => types.forEach(type => { total += Number(grid?.[cls.id]?.TUITION?.[`term${n}`]?.[String(type.id)] || 0); }));
      return `<td class="text-end fw-bold">${total ? total.toLocaleString('en-KE') : '–'}</td>`;
    }).join('')}<td class="text-end fw-bold">${terms.reduce((sum, n) => classes.reduce((s, cls) => types.reduce((x, type) => x + Number(grid?.[cls.id]?.TUITION?.[`term${n}`]?.[String(type.id)] || 0), s), sum), 0).toLocaleString('en-KE')}</td></tr></tfoot></table></div>`;
    body.innerHTML = html;
    const submit = document.getElementById('submitFeeReviewBtn');
    if (submit) submit.onclick = () => this.submitReviewFeedback();
  }

  async submitReviewFeedback() {
    const notes = document.getElementById('feeReviewNotes')?.value?.trim();
    if (!notes) return window.infoDialog('Notice', 'Enter review feedback before saving.');
    try {
      for (const id of (this.reviewingBundleIds || [])) {
        await window.API.apiCall(`/finance/fees-bundle-review/${id}`, 'POST', { action: 'approve', notes });
      }
      bootstrap.Modal.getInstance(document.getElementById('feeReviewModal'))?.hide();
      await window.infoDialog('Notice', 'Review feedback recorded. The structure remains pending final approval.');
      this.loadPendingReviews();
    } catch (e) {
      await window.infoDialog('Notice', 'Review failed: ' + (e.message || 'Unknown error'));
    }
  }

  unwrapList(response) {
    if (Array.isArray(response)) return response;
    if (Array.isArray(response?.data)) return response.data;
    if (Array.isArray(response?.items)) return response.items;
    if (Array.isArray(response?.data?.items)) return response.data.items;
    if (Array.isArray(response?.classes)) return response.classes;
    if (Array.isArray(response?.data?.classes)) return response.data.classes;
    return [];
  }

  /**
   * Setup event listeners
   */
  setupEventListeners() {
    document
      .getElementById("academicYearFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document
      .getElementById("termFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document
      .getElementById("levelFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document
      .getElementById("studentTypeFilter")
      ?.addEventListener("change", () => this.applyFilters());
    document.getElementById("searchInput")?.addEventListener(
      "input",
      this.debounce(() => this.applyFilters(), 500),
    );

    window.viewStructure = (id) => this.viewStructure(id);
    window.exportReport = () => this.exportReport();
    window.printSummary = () => this.printSummary();
    window.printStructure = () => this.printStructure();
    window.clearFilters = () => this.clearFilters();
    window.closeModal = (modalId) => this.closeModal(modalId);
  }

  /**
   * Load dropdown options
   */
  async loadDropdowns() {
    try {
      const cache = this.state.referenceCache;
      const [
        yearsResponse,
        levelsResponse,
        studentTypesResponse,
        termsResponse,
      ] = await Promise.all([
        cache?.years !== undefined ? cache.years : API.academic.getAllAcademicYears().catch(() => []),
        cache?.levels !== undefined ? cache.levels : API.academic.listLevels().catch(() => []),
        cache?.studentTypes !== undefined ? cache.studentTypes : API.finance.listStudentTypes().catch(() => []),
        cache?.terms !== undefined ? cache.terms : API.academic.listTerms().catch(() => []),
      ]);

      this.academicYears = Array.isArray(yearsResponse) ? yearsResponse : [];
      this.levels = Array.isArray(levelsResponse) ? levelsResponse : [];
      this.studentTypes = Array.isArray(studentTypesResponse)
        ? studentTypesResponse
        : [];
      this.terms = Array.isArray(termsResponse) ? termsResponse : [];

      this.buildTermMaps();

      this.populateAcademicYearSelect("academicYearFilter", true);
      this.populateLevelFilter();
      this.populateStudentTypeFilter();
      this.populateTermFilter();
    } catch (error) {
      console.error("Failed to load dropdown data:", error);
    }
  }

  buildTermMaps() {
    this.termNameMap = {};
    this.termsByYear = {};

    this.terms.forEach((term) => {
      if (!term || !term.id) return;
      const yearValue = this.parseAcademicYear(term.year || term.year_code);
      if (!this.termsByYear[yearValue]) {
        this.termsByYear[yearValue] = [];
      }
      this.termsByYear[yearValue].push(term);
      this.termNameMap[term.id] =
        term.name || `Term ${term.term_number || term.id}`;
    });
  }

  parseAcademicYear(value) {
    if (!value) return "";
    const match = String(value).match(/\d{4}/);
    return match ? match[0] : String(value);
  }

  getAcademicYearLabel(year) {
    return year.year_name || year.year_code || year.year || "";
  }

  populateAcademicYearSelect(elementId, includeAll = false) {
    const select = document.getElementById(elementId);
    if (!select) return;

    const selected = select.value;
    const allOption = includeAll
      ? select.querySelector('option[value=""]')
      : null;

    select.innerHTML = "";
    if (allOption) select.appendChild(allOption.cloneNode(true));

    this.academicYears.forEach((year) => {
      const value = this.parseAcademicYear(
        year.year_code || year.year || year.id,
      );
      const option = document.createElement("option");
      option.value = value;
      option.textContent = this.getAcademicYearLabel(year) || value;
      select.appendChild(option);
    });

    if (selected) select.value = selected;
  }

  populateLevelFilter() {
    const select = document.getElementById("levelFilter");
    if (!select) return;

    const selected = select.value;
    const allOption = select.querySelector('option[value=""]');
    select.innerHTML = "";
    if (allOption) select.appendChild(allOption.cloneNode(true));

    this.levels
      .slice()
      .sort((a, b) => (a.name || "").localeCompare(b.name || ""))
      .forEach((level) => {
        const option = document.createElement("option");
        option.value = level.id;
        option.textContent = `${level.name} (${level.code})`;
        select.appendChild(option);
      });

    if (selected) select.value = selected;
  }

  populateStudentTypeFilter() {
    const select = document.getElementById("studentTypeFilter");
    if (!select) return;

    const selected = select.value;
    const allOption = select.querySelector('option[value=""]');
    select.innerHTML = "";
    if (allOption) select.appendChild(allOption.cloneNode(true));

    this.studentTypes
      .slice()
      .sort((a, b) => (a.name || "").localeCompare(b.name || ""))
      .forEach((type) => {
        const option = document.createElement("option");
        option.value = type.id;
        option.textContent = `${type.name} (${type.code})`;
        select.appendChild(option);
      });

    if (selected) select.value = selected;
  }

  populateTermFilter() {
    const select = document.getElementById("termFilter");
    if (!select) return;

    const selected = select.value;
    const allOption = select.querySelector('option[value=""]');
    select.innerHTML = "";
    if (allOption) select.appendChild(allOption.cloneNode(true));

    const terms = this.terms.slice().sort((a, b) => {
      const termA = a.term_number || a.id;
      const termB = b.term_number || b.id;
      return termA - termB;
    });

    terms.forEach((term) => {
      const option = document.createElement("option");
      option.value = term.id;
      option.textContent = term.name || `Term ${term.term_number || term.id}`;
      select.appendChild(option);
    });

    if (selected) select.value = selected;
  }

  /**
   * Load fee structures with filters
   */
  async loadFeeStructures(page = 1) {
    this.currentPage = page;

    const filters = {
      page: page,
      limit: this.itemsPerPage,
      academic_year: document.getElementById("academicYearFilter")?.value || "",
      term_id: document.getElementById("termFilter")?.value || "",
      level_id: document.getElementById("levelFilter")?.value || "",
      student_type_id:
        document.getElementById("studentTypeFilter")?.value || "",
      status: "active",
      search: document.getElementById("searchInput")?.value || "",
    };

    Object.keys(filters).forEach((key) => {
      if (filters[key] === "" || filters[key] === null) {
        delete filters[key];
      }
    });

    this.currentFilters = filters;

    try {
      const response = await apiCall(
        "/finance/fee-structures/list",
        "GET",
        null,
        filters,
      );

      const payload = response?.data || response || {};
      const structures = payload?.fee_structures || payload?.structures || [];
      const pagination = payload?.pagination || {};
      this.billingSummary = payload?.billing_summary || { billed_students: 0, billed_amount: 0 };

      this.currentStructures = Array.isArray(structures) ? structures : [];
      const aggregated = this.aggregateFeeStructures(this.currentStructures);
      this.currentAggregated = Object.values(aggregated);

      this.renderFeeStructures(this.currentAggregated);
      this.updateStatistics(this.currentAggregated);
      this.renderPagination(pagination);
      this.updateChart(this.currentAggregated);
      await this.loadCanonicalMatrix();
    } catch (error) {
      console.error("Failed to load fee structures:", error);
      this.renderLoadError(error?.message || "Unable to load fee structures.");
    }
  }

  getGroupKey(structure) {
    return `${structure.academic_year}|${structure.level_id}|${structure.student_type_id}|${structure.term_id}`;
  }

  aggregateFeeStructures(structures) {
    const aggregated = {};

    structures.forEach((structure) => {
      const key = this.getGroupKey(structure);

      if (!aggregated[key]) {
        aggregated[key] = {
          group_key: key,
          first_id: structure.id,
          academic_year: structure.academic_year,
          level_id: structure.level_id,
          level_name: structure.level_name,
          term_id: structure.term_id,
          term_name: structure.term_name,
          student_type_id: structure.student_type_id,
          student_type_name: structure.student_type_name,
          student_count: structure.student_count || 0,
          status: structure.status,
          total_amount: 0,
        };
      }

      const group = aggregated[key];
      const amount = parseFloat(structure.amount) || 0;

      group.total_amount += amount;
      group.student_count = Math.max(
        group.student_count || 0,
        structure.student_count || 0,
      );
      group.status = structure.status || group.status;
    });

    Object.values(aggregated).forEach((group) => {
      group.total_expected_revenue =
        (group.total_amount || 0) * (group.student_count || 0);
    });

    return aggregated;
  }

  /**
   * Render fee structures table
   */
  renderFeeStructures(structures) {
    const tbody = document.getElementById("feeStructuresBody");
    if (!tbody) return;

    if (!structures || structures.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="9" class="text-center text-muted py-4">No fee structures found</td></tr>';
      return;
    }

    tbody.innerHTML = structures
      .map(
        (structure) => `
            <tr>
                <td>${structure.academic_year || "-"}</td>
                <td>${structure.level_name || "-"}</td>
                <td>${structure.student_type_name || "-"}</td>
                <td>${this.getTermName(structure.term_id, structure.term_name)}</td>
                <td class="text-end">${this.formatCurrency(structure.total_amount)}</td>
                <td>${structure.student_count || 0}</td>
                <td class="text-end">${this.formatCurrency(structure.total_expected_revenue)}</td>
                <td>${structure.status || "-"}</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="viewStructure('${structure.group_key}')" title="View Details">
                        <i class="bi bi-eye"></i> View
                    </button>
                </td>
            </tr>
        `,
      )
      .join("");
  }

  renderLoadError(message) {
    const body = document.getElementById("activeFeeMatrix");
    if (body) {
      body.innerHTML = `<div class="text-center text-danger py-4"><i class="bi bi-exclamation-triangle me-1"></i>${this.escapeHtml(message)} <button class="btn btn-sm btn-outline-danger ms-2" onclick="window.viewerController.loadFeeStructures(1)">Retry</button></div>`;
    }
    const info = document.getElementById("paginationInfo");
    if (info) info.textContent = "Unable to load fee structures";
  }

  async loadCanonicalMatrix() {
    const body = document.getElementById('activeFeeMatrix');
    if (!body || !window.FeeStructureMatrix) return;
    const yearSelect = document.getElementById('academicYearFilter');
    const selectedYear = yearSelect?.value || this.parseAcademicYear(this.academicYears.find(y => y.is_current)?.year_code || this.academicYears[0]?.year_code || '');
    const typeId = document.getElementById('studentTypeFilter')?.value || '';
    body.innerHTML = '<div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm me-2"></div>Loading saved fee matrix…</div>';
    try {
      const model = await window.FeeStructureMatrix.load(selectedYear, typeId ? [typeId] : undefined);
      window.FeeStructureMatrix.render(body, model);
      const level = document.getElementById('levelFilter')?.value || '';
      const grade = document.getElementById('searchInput')?.value || '';
      const levelFilter = body.querySelector('[data-matrix-level]');
      const typeFilter = body.querySelector('[data-matrix-type]');
      if (levelFilter && level) { levelFilter.value = level; levelFilter.dispatchEvent(new Event('change')); }
      if (typeFilter && typeId) { typeFilter.value = typeId; typeFilter.dispatchEvent(new Event('change')); }
      if (grade && body.querySelector('[data-matrix-class]')) {
        const option = [...body.querySelector('[data-matrix-class]').options].find(o => o.textContent.toLowerCase().includes(grade.toLowerCase()));
        if (option) { body.querySelector('[data-matrix-class]').value = option.value; body.querySelector('[data-matrix-class]').dispatchEvent(new Event('change')); }
      }
    } catch (e) {
      body.innerHTML = `<div class="alert alert-danger mb-0">${this.escapeHtml(e.message || 'Failed to load saved fee matrix')}</div>`;
    }
  }

  /**
   * Update statistics cards and summary
   */
  updateStatistics(structures) {
    const activeCount = structures.filter((s) => s.status === "active").length;
    const totalExpected = Number(this.billingSummary?.billed_amount || 0);
    const totalStudents = Number(this.billingSummary?.billed_students || 0);

    const activeEl = document.getElementById("activeStructures");
    const expectedEl = document.getElementById("totalExpectedRevenue");
    const studentsEl = document.getElementById("totalStudents");

    if (activeEl) activeEl.textContent = activeCount;
    if (expectedEl) expectedEl.textContent = this.formatCurrency(totalExpected);
    if (studentsEl) studentsEl.textContent = totalStudents;

    const summaryTotal = document.getElementById("summaryTotal");
    const summaryActive = document.getElementById("summaryActive");
    const summaryRevenue = document.getElementById("summaryRevenue");
    const summaryAverage = document.getElementById("summaryAverage");

    if (summaryTotal) summaryTotal.textContent = structures.length;
    if (summaryActive) summaryActive.textContent = activeCount;
    if (summaryRevenue)
      summaryRevenue.textContent = this.formatCurrency(totalExpected);
    if (summaryAverage) {
      const average = totalStudents > 0 ? totalExpected / totalStudents : 0;
      summaryAverage.textContent = this.formatCurrency(average);
    }
  }

  /**
   * Render pagination
   */
  renderPagination(pagination) {
    const container = document.getElementById("paginationControls");
    const info = document.getElementById("paginationInfo");

    if (!container || !info) return;

    const current_page = parseInt(pagination.page) || 1;
    const total_pages = parseInt(pagination.pages) || 1;
    const total_items = parseInt(pagination.total) || 0;
    const page_size = parseInt(pagination.limit) || this.itemsPerPage;

    const start = total_items === 0 ? 0 : (current_page - 1) * page_size + 1;
    const end = Math.min(current_page * page_size, total_items);

    info.textContent = `Showing ${start}-${end} of ${total_items}`;

    if (total_pages <= 1) {
      container.innerHTML = "";
      return;
    }

    let html = "";
    html += `<button class="btn btn-sm btn-outline-primary" ${current_page === 1 ? "disabled" : ""} 
                         onclick="window.viewerController.loadFeeStructures(${current_page - 1})">Previous</button>`;

    const range = 5;
    let start_page = Math.max(1, current_page - Math.floor(range / 2));
    let end_page = Math.min(total_pages, start_page + range - 1);

    if (end_page - start_page < range - 1) {
      start_page = Math.max(1, end_page - range + 1);
    }

    for (let i = start_page; i <= end_page; i++) {
      html += `<button class="btn btn-sm ${i === current_page ? "btn-primary" : "btn-outline-primary"}" 
                             onclick="window.viewerController.loadFeeStructures(${i})">${i}</button>`;
    }

    html += `<button class="btn btn-sm btn-outline-primary" ${current_page === total_pages ? "disabled" : ""} 
                         onclick="window.viewerController.loadFeeStructures(${current_page + 1})">Next</button>`;

    container.innerHTML = html;
  }

  /**
   * Initialize chart
   */
  initializeChart() {
    const ctx = document
      .getElementById("feeDistributionChart")
      ?.getContext("2d");

    if (!ctx) return;

    this.chart = new Chart(ctx, {
      type: "bar",
      data: {
        labels: [],
        datasets: [
          {
            label: "Expected Revenue by Level",
            data: [],
            backgroundColor: "rgba(54, 162, 235, 0.5)",
            borderColor: "rgba(54, 162, 235, 1)",
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function (value) {
                return "KES " + value.toLocaleString();
              },
            },
          },
        },
        plugins: {
          tooltip: {
            callbacks: {
              label: function (context) {
                return "KES " + context.parsed.y.toLocaleString();
              },
            },
          },
        },
      },
    });
  }

  /**
   * Update chart with data
   */
  updateChart(structures) {
    if (!this.chart || !structures) return;

    const levelTotals = {};
    structures.forEach((s) => {
      const label = s.level_name || "Unknown";
      levelTotals[label] =
        (levelTotals[label] || 0) + (s.total_expected_revenue || 0);
    });

    this.chart.data.labels = Object.keys(levelTotals);
    this.chart.data.datasets[0].data = Object.values(levelTotals);
    this.chart.update();
  }

  /**
   * View structure details
   */
  viewStructure(groupKey) {
    const group = this.currentAggregated.find((g) => g.group_key === groupKey);
    if (!group) {
      this.showError("Fee structure not found in current list");
      return;
    }

    const items = this.getFeeItemsForGroup(group);
    const details = {
      ...group,
      fee_items: items,
      total_amount: group.total_amount,
      expected_revenue: group.total_expected_revenue,
    };

    this.displayStructureDetails(details);
  }

  getFeeItemsForGroup(group) {
    return this.currentStructures
      .filter(
        (row) =>
          row.academic_year === group.academic_year &&
          row.level_id === group.level_id &&
          row.student_type_id === group.student_type_id &&
          row.term_id === group.term_id,
      )
      .map((row) => ({
        name: row.fee_name || row.fee_type_name || row.fee_type_code || "Fee",
        description: row.fee_category || "",
        amount: parseFloat(row.amount) || 0,
      }));
  }

  /**
   * Display structure details in modal
   */
  displayStructureDetails(structure) {
    const modal = document.getElementById("viewFeeStructureModal");
    const body = document.getElementById("viewModalBody");

    if (!modal || !body) return;

    body.innerHTML = `
            <div class="structure-details">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Academic Year:</strong> ${structure.academic_year}
                    </div>
                    <div class="col-md-6">
                        <strong>Term:</strong> ${this.getTermName(structure.term_id, structure.term_name)}
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Level:</strong> ${structure.level_name}
                    </div>
                    <div class="col-md-6">
                        <strong>Student Type:</strong> ${structure.student_type_name}
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Total Amount:</strong> ${this.formatCurrency(structure.total_amount)}
                    </div>
                    <div class="col-md-6">
                        <strong>Students:</strong> ${structure.student_count}
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>Expected Revenue:</strong>
                        <div class="text-primary fs-4">${this.formatCurrency(structure.expected_revenue)}</div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <strong>Fee Items:</strong>
                        <table class="table table-sm mt-2">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Description</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${(structure.fee_items || [])
                                  .map(
                                    (item) => `
                                    <tr>
                                        <td>${item.name}</td>
                                        <td>${item.description || "-"}</td>
                                        <td class="text-end">${this.formatCurrency(item.amount)}</td>
                                    </tr>
                                `,
                                  )
                                  .join("")}
                                <tr class="table-primary">
                                    <td colspan="2"><strong>Total</strong></td>
                                    <td class="text-end"><strong>${this.formatCurrency(structure.total_amount)}</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;

    this.showModal(modal.id);
  }

  /**
   * Export report
   */
  exportReport() {
    this.exportCsv(this.currentAggregated, "fee_structures_report.csv");
  }

  /**
   * Print summary
   */
  async printSummary() {
    if (!this.currentAggregated || this.currentAggregated.length === 0) {
      await window.infoDialog('Notice', "No fee structure data to print");
      return;
    }

    const academicYear = document.getElementById("academicYearFilter")?.options[document.getElementById("academicYearFilter").selectedIndex]?.text || 'All';
    const level = document.getElementById("levelFilter")?.options[document.getElementById("levelFilter").selectedIndex]?.text || 'All';
    const studentType = document.getElementById("studentTypeFilter")?.options[document.getElementById("studentTypeFilter").selectedIndex]?.text || 'All';

    const filters = {
      'Academic Year': academicYear,
      'Level': level,
      'Student Type': studentType
    };

    // Remove empty filters
    Object.keys(filters).forEach(key => {
      if (filters[key] === 'All' || !filters[key]) {
        delete filters[key];
      }
    });

    const money = (value) => this.formatCurrency(value);
    const columns = [
      { key: "structure_name", label: "Structure Name", width: "28%", cellClassName: "print-cell-strong" },
      { key: "academic_year", label: "Academic Year", width: "14%" },
      { key: "level_name", label: "Level", width: "14%" },
      { key: "student_type", label: "Student Type", width: "15%" },
      { key: "total_amount", label: "Total Amount", type: "currency", width: "18%", formatter: money },
      { key: "status", label: "Status", width: "11%" },
    ];

    window.PrintManager.printTable({
      title: 'Fee Structure Summary',
      subtitle: 'School Fee Structures Overview',
      columns: columns,
      rows: this.currentAggregated,
      summary: {
        'Total Structures': this.currentAggregated.length,
              },
      filters: filters,
      orientation: 'landscape',
      paperSize: 'A4',
      reportCode: 'FEE-' + new Date().toISOString().slice(0, 10).replace(/-/g, ''),
      signatureSection: [
        { label: 'Accountant', dateLine: true },
        { label: 'Headteacher', dateLine: true }
      ]
    });
  }

  async printStructure() {
    if (!this.selectedStructure) {
      await window.infoDialog('Notice', "No fee structure selected");
      return;
    }

    const structure = this.selectedStructure;
    const feeItems = (structure.fee_items || []).map(item => ({
      name: item.fee_item_name || item.name || '—',
      amount: item.amount || 0,
    }));

    if (window.PrintManager?.printFeeStructure) {
      window.PrintManager.printFeeStructure({
        academicYear: structure.academic_year || '',
        studentType: structure.student_type_name || structure.student_type || 'Day',
        feeItems: feeItems,
        terms: [],
        structureName: structure.structure_name || '',
        level: structure.level_name || '',
        filename: `fee_structure_${structure.id || Date.now()}`,
      });
    } else {
      const sections = [
        {
          title: 'Structure Information',
          fields: [
            { label: 'Structure Name', value: structure.structure_name || '—' },
            { label: 'Academic Year', value: structure.academic_year || '—' },
            { label: 'Level', value: structure.level_name || '—' },
            { label: 'Student Type', value: structure.student_type || '—' },
            { label: 'Total Amount', value: this.formatCurrency(structure.total_amount) },
            { label: 'Status', value: structure.status || '—' }
          ]
        },
        {
          title: 'Fee Items',
          content: feeItems.map(item => `
            <div style="display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #eee;">
              <span>${item.name}</span>
              <span>${this.formatCurrency(item.amount)}</span>
            </div>
          `).join('')
        }
      ];
      window.PrintManager.printRecord({
        title: 'Fee Structure Details',
        subtitle: structure.structure_name || 'Structure',
        sections: sections,
        orientation: 'portrait',
        paperSize: 'A4',
      });
    }
  }

  /**
   * Utility functions
   */
  applyFilters() {
    this.loadFeeStructures(1);
  }

  clearFilters() {
    document.getElementById("academicYearFilter").value = "";
    document.getElementById("termFilter").value = "";
    document.getElementById("levelFilter").value = "";
    document.getElementById("studentTypeFilter").value = "";
    document.getElementById("searchInput").value = "";
    this.loadFeeStructures(1);
  }

  formatCurrency(amount) {
    return (
      "KES " +
      parseFloat(amount || 0).toLocaleString("en-KE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })
    );
  }

  getTermName(termId, termName) {
    if (termName) return termName;
    if (!termId) return "-";
    return this.termNameMap[termId] || `Term ${termId}`;
  }

  showModal(modalId) {
    const modalEl = document.getElementById(modalId);
    if (modalEl) {
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    }
  }

  closeModal(modalId) {
    const modalEl = document.getElementById(modalId);
    if (modalEl) {
      const modal = bootstrap.Modal.getInstance(modalEl);
      if (modal) modal.hide();
    }
  }

  async showError(message) {
    await window.infoDialog('Notice', "Error: " + message);
  }

  escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  exportCsv(rows, filename) {
    if (!Array.isArray(rows) || rows.length === 0) {
      this.showError("No fee structures to export");
      return;
    }

    const headers = [
      "Academic Year",
      "Level",
      "Student Type",
      "Term",
      "Status",
      "Total Amount",
      "Students",
      "Expected Revenue",
    ];

    const csvRows = rows.map((row) => ({
      "Academic Year": row.academic_year ?? "",
      Level: row.level_name || row.level_code || row.level_id || "",
      "Student Type": row.student_type_name || row.student_type_id || "",
      Term: this.getTermName(row.term_id, row.term_name),
      Status: row.status || "",
      "Total Amount": row.total_amount ?? 0,
      Students: row.student_count ?? 0,
      "Expected Revenue": row.total_expected_revenue ?? 0,
    }));

    const escape = (value) => `"${String(value ?? "").replace(/"/g, '""')}"`;
    const csv = [headers.join(",")]
      .concat(
        csvRows.map((row) =>
          headers.map((header) => escape(row[header])).join(","),
        ),
      )
      .join("\n");

    KingswayFileLifecycle.exportText(csv, filename, "text/csv;charset=utf-8;");
    link.remove();
    window.URL.revokeObjectURL(url);
  }

  debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }
}

window.FeeStructureViewerController = FeeStructureViewerController;

const startFeeStructureViewer = () => FeeStructureViewerController.init().catch((error) => {
  console.error("Fee structure viewer failed to initialise:", error);
});
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", startFeeStructureViewer, { once: true });
} else {
  startFeeStructureViewer();
}
