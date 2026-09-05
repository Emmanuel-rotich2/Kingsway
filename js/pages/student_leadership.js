/**
 * Student Leadership & Participation Controller
 * Page: student_leadership.php
 * Manages per-term leadership/offices, houses, and awards/certificates —
 * the single governed source feeding the student portfolio.
 */
const StudentLeadershipController = {
  state: {
    students: [],
    positions: [],
    houses: [],
    years: [],
    terms: [],
    leadership: [],
    awards: [],
    awardCategories: [],
    awardTypes: [],
    departments: [],
    editLeadershipId: null,
    editHouseId: null,
    editAwardId: null,
    editAwardTypeId: null,
    canManage: false,
  },

  CATEGORIES: [
    "school", "class", "house", "club", "spiritual",
    "sports", "welfare", "library", "patrol", "academic",
  ],

  async init() {
    await window.AuthContext?.ready();
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    this.state.canManage = !!(window.AuthContext?.hasPermission &&
      window.AuthContext.hasPermission("student_leadership_manage"));
    this.applyRoleRendering();
    this.bindEvents();
    await this.loadReferences();
  },

  /* Read-only (viewers: teachers, director, chaplain) vs manage (school
     admin, headteacher, deputies) — the page renders different interfaces. */
  applyRoleRendering() {
    const banner = document.getElementById("leadReadonlyBanner");
    if (banner) banner.classList.toggle("d-none", this.state.canManage);
    document.querySelectorAll(".lead-manage-only").forEach((el) => {
      el.classList.toggle("d-none", !this.state.canManage);
    });
    document.getElementById("awardActionsTh")?.classList.toggle("d-none", !this.state.canManage);
  },

  bindEvents() {
    const tabs = document.getElementById("leadTabs");
    if (tabs) {
      tabs.addEventListener("shown.bs.tab", (e) => {
        const id = e.target?.getAttribute("data-bs-target");
        if (id === "#tab-houses") this.loadHouses();
        if (id === "#tab-awards") this.loadAwards();
      });
    }
    const leadCat = document.getElementById("leadCatFilter");
    if (leadCat) leadCat.addEventListener("change", () => this.loadLeadership());
    const leadYear = document.getElementById("leadYearFilter");
    if (leadYear) leadYear.addEventListener("change", () => this.refreshTerms("filter"));
    const modalYear = document.getElementById("leadYear");
    if (modalYear) modalYear.addEventListener("change", () => this.refreshTerms("assign"));
    const awardCat = document.getElementById("awardCatFilter");
    if (awardCat) awardCat.addEventListener("change", () => this.loadAwards());
  },

  escapeHtml(value) {
    return String(value ?? "")
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  },

  showNotification(message, type = "info") {
    if (typeof window.showNotification === "function") {
      window.showNotification(message, type);
    } else if (window.API?.showNotification) {
      try { window.API.showNotification(message, type); } catch (e) {}
    } else {
      alert(message);
    }
  },

  badge(type) {
    return `<span class="text-uppercase badge ${this.badgeClass(type)}">${this.escapeHtml(type)}</span>`;
  },
  badgeClass(type) {
    const map = {
      school: "bg-primary", class: "bg-success", house: "bg-danger",
      club: "bg-warning text-dark", spiritual: "bg-info text-dark",
      sports: "bg-success", welfare: "bg-secondary", library: "bg-primary",
      patrol: "bg-danger", academic: "bg-dark",
    };
    return map[type] || "bg-secondary";
  },

  /* ---------------- REFERENCES ---------------- */
  async loadReferences() {
    try {
      const [studentsRes, positionsRes, housesRes, yearsRes, leadershipRes, awardsRes, catalogRes, departmentsRes] =
        await Promise.all([
          window.API.students.contextList({ context: "teacher_class" }).catch(() => window.API.students.list({ limit: 2000 })),
          window.API.students.leadership.positions(),
          window.API.students.houses.list({ is_active: 1 }),
          Promise.resolve(this.currentYears()),
          window.API.students.leadership.list({}),
          window.API.students.awards.list({}),
          window.API.students.awards.catalogue().catch(() => null),
          window.API.departments.list().catch(() => null),
        ]);

      this.state.students = this.toArray(studentsRes, "students");
      this.state.positions = this.toArray(positionsRes, null).filter((p) => String(p.level_id) === "5" || String(p.level_name).toLowerCase().includes("student"));
      this.state.houses = this.toArray(housesRes, "houses");
      this.state.years = Array.isArray(yearsRes) ? yearsRes : [];
      this.state.leadership = this.toArray(leadershipRes, null);
      this.state.awards = this.toArray(awardsRes, null);
      this.state.departments = this.toArray(departmentsRes, "departments");
      const catalog = catalogRes?.data?.data ?? catalogRes?.data ?? catalogRes ?? {};
      this.state.awardCategories = Array.isArray(catalog.categories) ? catalog.categories : [];
      this.state.awardTypes = Array.isArray(catalog.types) ? catalog.types : [];

      this.populateSelects();
      this.renderKpis();
      this.loadLeadership();
      this.renderStudents();
      this.renderHousesGrid();
      this.renderAwardsTable();
    } catch (err) {
      console.error("Error loading references:", err);
      this.showNotification("Failed to load leadership data", "error");
    }
  },

  currentYears() {
    // Fallback year list if the endpoint isn't available.
    return Promise.resolve([
      { id: "1", year_name: "2026/2027", is_current: 1 },
    ]);
  },

  toArray(payload, key) {
    const d = payload?.data?.data ?? payload?.data ?? payload ?? [];
    const arr = key ? (Array.isArray(d) ? d : d?.[key]) : d;
    return Array.isArray(arr) ? arr : (d && key && Array.isArray(d[key]) ? d[key] : []);
  },

  populateSelects() {
    // Category filter + modal
    const catFilter = document.getElementById("leadCatFilter");
    if (catFilter) {
      catFilter.innerHTML = '<option value="">All categories</option>' +
        this.CATEGORIES.map((c) => `<option value="${c}">${this.cap(c)}</option>`).join("");
    }
    const catModal = document.getElementById("leadCategory");
    if (catModal) {
      catModal.innerHTML = this.CATEGORIES.map((c) => `<option value="${c}">${this.cap(c)}</option>`).join("");
    }

    // Positions (student level only)
    const posSel = document.getElementById("leadPosition");
    if (posSel) {
      posSel.innerHTML = '<option value="">Select position...</option>' +
        this.state.positions.map((p) => `<option value="${this.escapeHtml(p.id)}">${this.escapeHtml(p.name || p.position_name)}</option>`).join("");
      // ensure student-level positions present
      if (this.state.positions.length === 0) {
        posSel.innerHTML = '<option value="">No student positions found</option>';
      }
    }

    // Houses
    const houseSel = document.getElementById("leadHouse");
    if (houseSel) {
      houseSel.innerHTML = '<option value="">— None —</option>' +
        this.state.houses.map((h) => `<option value="${this.escapeHtml(h.id)}">${this.escapeHtml(h.name)}</option>`).join("");
    }

    // Years
    const years = this.state.years.length ? this.state.years : [{ id: "1", year_name: "2026/2027" }];
    for (const id of ["leadYearFilter", "leadYear"]) {
      const sel = document.getElementById(id);
      if (sel) {
        sel.innerHTML = (id === "leadYearFilter" ? '<option value="">All years</option>' : "") +
          years.map((y) => `<option value="${this.escapeHtml(y.id)}">${this.escapeHtml(y.year_name || y.name)}</option>`).join("");
      }
    }

    // Award category filter
    const awardCat = document.getElementById("awardCatFilter");
    if (awardCat) {
      awardCat.innerHTML = '<option value="">All categories</option>' +
        this.state.awardCategories.map((c) => `<option value="${this.escapeHtml(c.id)}">${this.escapeHtml(c.name)}</option>`).join("");
    }
    // Award category select in the issue modal
    const awardCatSel = document.getElementById("awardCategory");
    if (awardCatSel && awardCatSel.options.length <= 1) {
      awardCatSel.innerHTML = '<option value="">Select category...</option>' +
        this.state.awardCategories.map((c) => `<option value="${this.escapeHtml(c.id)}">${this.escapeHtml(c.name)}</option>`).join("");
    }
  },

  renderStudents() {
    const ids = ["leadStudent", "awardStudent", "leadStudentFilter"];
    for (const id of ids) {
      const sel = document.getElementById(id);
      if (!sel) continue;
      const isFilter = id === "leadStudentFilter";
      sel.innerHTML = (isFilter ? '<option value="">All students</option>' : '<option value="">Select student...</option>') +
        this.state.students
          .map((s) => `<option value="${this.escapeHtml(s.id)}">${this.escapeHtml(s.full_name || s.first_name + " " + s.last_name)} (${this.escapeHtml(s.admission_no || s.adm_no || s.student_no || "")})</option>`)
          .join("");
    }
  },

  refreshTerms(mode) {
    const yearSel = document.getElementById(mode === "filter" ? "leadYearFilter" : "leadYear");
    const termSel = document.getElementById(mode === "filter" ? "leadTermFilter" : "leadTerm");
    if (!termSel) return;
    if (mode === "filter") {
      // the filter uses year only; no term filter on leadership list
      return;
    }
    const yearId = yearSel?.value;
    if (!yearId) { termSel.innerHTML = '<option value="">All year</option>'; return; }
    const terms = this.state.terms.filter((t) => String(t.academic_year_id) === String(yearId));
    termSel.innerHTML = '<option value="">All year</option>' +
      terms.map((t) => `<option value="${this.escapeHtml(t.id)}">Term ${this.escapeHtml(t.term_id)}</option>`).join("");
  },

  async loadTerms() {
    if (this.state.terms.length) return;
    try {
      const payload = await window.API.apiCall("/academic/terms-list", "GET");
      const d = payload?.data?.data ?? payload?.data ?? payload ?? [];
      const arr = Array.isArray(d) ? d : d?.terms;
      this.state.terms = Array.isArray(arr) ? arr : [];
    } catch (e) {
      this.state.terms = [];
    }
  },

  cap(s) {
    return String(s || "").replace(/_/g, " ").replace(/\b\w/g, (c) => c.toUpperCase());
  },

  requireManage() {
    if (this.state.canManage) return true;
    this.showNotification("You do not have permission to modify student leadership records", "error");
    return false;
  },

  /* ---------------- LEADERSHIP ---------------- */
  async loadLeadership() {
    const params = {};
    const year = document.getElementById("leadYearFilter")?.value;
    const cat = document.getElementById("leadCatFilter")?.value;
    const stu = document.getElementById("leadStudentFilter")?.value;
    if (year) params.academic_year_id = year;
    if (cat) params.position_category = cat;
    if (stu) params.student_id = stu;
    try {
      const payload = await window.API.students.leadership.list(params);
      this.state.leadership = this.toArray(payload, null);
      this.renderLeadership();
    } catch (err) {
      console.error(err);
      this.showNotification("Failed to load leadership records", "error");
    }
  },

  renderLeadership() {
    if (this.state.canManage) {
      document.getElementById("leadershipDirectory")?.classList.add("d-none");
      this.renderLeadershipTable();
    } else {
      document.getElementById("leadershipTable")?.closest(".table-responsive")?.classList.add("d-none");
      this.renderLeadershipDirectory();
    }
  },

  renderLeadershipDirectory() {
    const grid = document.getElementById("leadershipDirectory");
    if (!grid) return;
    if (!this.state.leadership.length) {
      grid.classList.remove("d-none");
      grid.innerHTML = '<div class="col-12 empty-state py-4">No leadership assignments found.</div>';
      return;
    }
    const cards = this.state.leadership.map((r) => {
      const photo = r.photo_url || r.public_photo_url || "";
      const color = r.house_color || "#1a2980";
      const initials = String(r.student_name || " ").split(/\s+/).filter(Boolean)
        .map((w) => w[0]).join("").slice(0, 2).toUpperCase();
      return `<div class="col-6 col-md-4 col-xl-3">
        <div class="card h-100 shadow-sm border-0 lead-directory-card">
          <div class="card-body text-center p-3">
            ${photo
              ? `<img src="${this.escapeHtml(photo)}" alt="${this.escapeHtml(r.student_name)}" class="rounded-circle mb-2" style="width:72px;height:72px;object-fit:cover;border:3px solid ${this.escapeHtml(color)}">`
              : `<div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white mx-auto mb-2" style="width:72px;height:72px;background:${this.escapeHtml(color)};font-size:1.35rem">${this.escapeHtml(initials)}</div>`}
            <div class="fw-semibold lh-sm">${this.escapeHtml(r.student_name)}</div>
            <div class="text-primary small fw-semibold">${this.escapeHtml(r.position_name)}</div>
            <div class="my-1">${this.badge(r.position_category)}</div>
            ${r.house_name ? `<div class="mt-1">${this.houseChip(r)}</div>` : ""}
            <div class="text-muted small mt-1">${this.escapeHtml(r.class_stream || "")}${r.class_stream ? " · " : ""}${this.escapeHtml(r.academic_year_name || "")}${r.term_number ? " · Term " + this.escapeHtml(r.term_number) : ""}</div>
          </div>
        </div>
      </div>`;
    }).join("");
    grid.classList.remove("d-none");
    grid.innerHTML = cards;
  },

  resetLeadershipFilters() {
    ["leadYearFilter", "leadCatFilter", "leadStudentFilter"].forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.value = "";
    });
    this.loadLeadership();
  },

  renderLeadershipTable() {
    const tbody = document.getElementById("leadershipTable");
    if (!tbody) return;
    const rows = this.state.leadership.map((r) => {
      const status = r.is_active === "1" || r.is_active === 1
        ? '<span class="badge bg-success">Active</span>'
        : '<span class="badge bg-secondary">Inactive</span>';
      return `<tr>
        <td class="fw-semibold">${this.escapeHtml(r.student_name)}</td>
        <td>${this.escapeHtml(r.admission_no || "—")}</td>
        <td>${this.escapeHtml(r.position_name)}</td>
        <td>${this.badge(r.position_category)}</td>
        <td><span class="per-term-badge text-muted">${this.escapeHtml(r.academic_year_name || "")}${r.term_number ? " · Term " + this.escapeHtml(r.term_number) : ""}</span></td>
        <td>${r.house_name ? this.houseChip(r) : '<span class="text-muted">—</span>'}</td>
        <td>${this.escapeHtml(r.class_stream || "—")}</td>
        <td>${status}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary" title="History" onclick="StudentLeadershipController.history(${this.escapeHtml(r.student_id)})"><i class="fas fa-history"></i></button>
          <button class="btn btn-sm btn-outline-primary" title="Edit" onclick="StudentLeadershipController.openAssign(${this.escapeHtml(r.id)})"><i class="fas fa-edit"></i></button>
          <button class="btn btn-sm btn-outline-danger" title="Remove" onclick="StudentLeadershipController.removeLeadership(${this.escapeHtml(r.id)})"><i class="fas fa-trash"></i></button>
        </td>
      </tr>`;
    }).join("");
    tbody.innerHTML = rows || '<tr><td colspan="9" class="empty-state">No leadership assignments found.</td></tr>';
  },

  async openAssign(id = null) {
    if (!this.requireManage()) return;
    this.state.editLeadershipId = id ? parseInt(id, 10) : null;
    await this.loadTerms();
    document.getElementById("assignLeadershipTitle").textContent =
      this.state.editLeadershipId ? "Edit Leadership Assignment" : "Assign Leadership Position";
    const form = {
      leadStudent: "", leadPosition: "", leadCategory: "school", leadHouse: "",
      leadYear: "", leadTerm: "", leadStart: new Date().toISOString().slice(0, 10),
      leadBio: "",
    };
    if (this.state.editLeadershipId) {
      const r = this.state.leadership.find((x) => String(x.id) === String(id));
      if (r) {
        form.leadStudent = r.student_id; form.leadPosition = r.position_id;
        form.leadCategory = r.position_category || "school";
        form.leadHouse = r.house_id || ""; form.leadYear = r.academic_year_id || "";
        form.leadTerm = r.academic_year_term_id || ""; form.leadBio = r.public_bio || "";
        form.leadStart = r.start_date || new Date().toISOString().slice(0, 10);
      }
    }
    this.fillForm(form);
    this.refreshTerms("assign");
    bootstrap.Modal.getOrCreateInstance(document.getElementById("assignLeadershipModal")).show();
  },

  fillForm(values) {
    for (const [id, val] of Object.entries(values)) {
      const el = document.getElementById(id);
      if (el) el.value = val ?? "";
    }
  },

  async saveLeadership() {
    if (!this.requireManage()) return;
    const data = {
      student_id: document.getElementById("leadStudent").value,
      position_id: document.getElementById("leadPosition").value,
      position_category: document.getElementById("leadCategory").value,
      house_id: document.getElementById("leadHouse").value || null,
      academic_year_id: document.getElementById("leadYear").value || 0,
      academic_year_term_id: document.getElementById("leadTerm").value || null,
      start_date: document.getElementById("leadStart").value || null,
      end_date: null,
      public_bio: document.getElementById("leadBio").value || null,
    };
    if (!data.student_id || !data.position_id) {
      this.showNotification("Student and Position are required", "error");
      return;
    }
    try {
      if (this.state.editLeadershipId) {
        await window.API.students.leadership.update(this.state.editLeadershipId, data);
      } else {
        await window.API.students.leadership.create(data);
      }
      this.showNotification(this.state.editLeadershipId ? "Assignment updated" : "Position assigned", "success");
      bootstrap.Modal.getInstance(document.getElementById("assignLeadershipModal"))?.hide();
      this.state.editLeadershipId = null;
      await this.loadLeadership();
    } catch (err) {
      this.showNotification((err?.data?.message) || "Failed to save assignment", "error");
    }
  },

  async removeLeadership(id) {
    if (!this.requireManage()) return;
    if (!confirm("Remove this leadership assignment?")) return;
    try {
      await window.API.students.leadership.delete(id);
      this.showNotification("Assignment removed", "success");
      await this.loadLeadership();
    } catch (err) {
      this.showNotification("Failed to remove assignment", "error");
    }
  },

  async history(studentId) {
    try {
      const payload = await window.API.students.leadership.history(studentId);
      const h = payload?.data?.data ?? payload?.data ?? payload;
      const lead = (h?.leadership || []).map((r) => (
        "<li>" + this.escapeHtml(r.position_name) + " — " + this.escapeHtml(r.academic_year || r.academic_year_name || "") +
        (r.term_number ? " · Term " + this.escapeHtml(r.term_number) : "") +
        (r.house_name ? " · " + this.escapeHtml(r.house_name) : "") + "</li>"
      )).join("");
      const awards = (h?.awards || []).map((a) => (
        "<li>" + this.escapeHtml(a.title) + (a.certificate_no ? " (" + this.escapeHtml(a.certificate_no) + ")" : "") + "</li>"
      )).join("");
      alert(
        "Leadership history:\n" + (lead ? lead.replace(/<[^>]+>/g, " ").replace(/<li>/g, "\n• ") : "None yet")
      );
    } catch (err) {
      this.showNotification("Could not load history", "error");
    }
  },

  /* ---------------- HOUSES ---------------- */
  async loadHouses() {
    try {
      const payload = await window.API.students.houses.list();
      this.state.houses = this.toArray(payload, "houses");
      if (this.state.houses.length === 0) this.state.houses = this.toArray(payload, null);
      this.renderHousesGrid();
      this.populateSelects();
    } catch (err) {
      this.showNotification("Failed to load houses", "error");
    }
  },

  renderHousesGrid() {
    const grid = document.getElementById("housesGrid");
    if (!grid) return;
    const cards = this.state.houses.map((h) => {
      const color = h.color || "#1a2980";
      return `<div class="col-md-4">
        <div class="lead-card h-100">
          <div class="p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="house-chip" style="background:${this.escapeHtml(color)}">${this.escapeHtml(h.name)} (${this.escapeHtml(h.code || "")})</span>
              <span class="badge bg-light text-dark">${Number(h.active_members || 0)} members</span>
            </div>
            <p class="text-muted small mb-2 fst-italic">“${this.escapeHtml(h.motto || "")}”</p>
            <p class="small mb-2">${this.escapeHtml(h.mascot ? "Mascot: " + h.mascot : "")}</p>
            <p class="small mb-2">${h.patron_name ? "Patron: " + this.escapeHtml(h.patron_name) : ""}</p>
            <div class="d-flex gap-2 mt-2">
              ${this.state.canManage
                ? `<button class="btn btn-sm btn-outline-primary" onclick="StudentLeadershipController.openHouse(${this.escapeHtml(h.id)})"><i class="fas fa-edit me-1"></i>Edit</button>`
                : ""}
            </div>
          </div>
        </div>
      </div>`;
    }).join("");
    grid.innerHTML = cards || '<div class="col-12 empty-state">No houses defined.</div>';
  },

  async openHouse(id = null) {
    if (!this.requireManage()) return;
    this.state.editHouseId = id ? parseInt(id, 10) : null;
    document.getElementById("houseModalTitle").textContent = this.state.editHouseId ? "Edit House" : "Add House";
    const values = { houseName: "", houseCode: "", houseColor: "", houseMascot: "", houseMotto: "", houseOrder: "0", houseActive: true };
    if (this.state.editHouseId) {
      const h = this.state.houses.find((x) => String(x.id) === String(id));
      if (h) {
        values.houseName = h.name; values.houseCode = h.code || "";
        values.houseColor = h.color || ""; values.houseMascot = h.mascot || "";
        values.houseMotto = h.motto || ""; values.houseOrder = h.display_order || "0";
        values.houseActive = !(String(h.is_active) === "0");
      }
    }
    this.fillForm(values);
    bootstrap.Modal.getOrCreateInstance(document.getElementById("houseModal")).show();
  },

  async saveHouse() {
    if (!this.requireManage()) return;
    const data = {
      name: document.getElementById("houseName").value,
      code: document.getElementById("houseCode").value || null,
      color: document.getElementById("houseColor").value || null,
      mascot: document.getElementById("houseMascot").value || null,
      motto: document.getElementById("houseMotto").value || null,
      display_order: parseInt(document.getElementById("houseOrder").value, 10) || 0,
      is_active: document.getElementById("houseActive").checked ? 1 : 0,
    };
    if (!data.name) { this.showNotification("House name is required", "error"); return; }
    try {
      if (this.state.editHouseId) {
        await window.API.students.houses.update(this.state.editHouseId, data);
      } else {
        await window.API.students.houses.create(data);
      }
      this.showNotification(this.state.editHouseId ? "House updated" : "House created", "success");
      bootstrap.Modal.getInstance(document.getElementById("houseModal"))?.hide();
      this.state.editHouseId = null;
      await this.loadHouses();
    } catch (err) {
      this.showNotification((err?.data?.message) || "Failed to save house", "error");
    }
  },

  houseChip(r) {
    const color = r.house_color || "#1a2980";
    return `<span class="house-chip" style="background:${this.escapeHtml(color)}">${this.escapeHtml(r.house_name || r.house_code || "")}</span>`;
  },

  /* ---------------- AWARDS ---------------- */
  templates() {
    return [
      { key: "academic_excellence", label: "Academic Excellence", design: "Distinctive navy/gold academic design" },
      { key: "sports_achievement", label: "Sports Achievement", design: "Bold navy/red sports design" },
      { key: "graduation", label: "Graduation", design: "Classic graduation design" },
      { key: "co_curricular", label: "Co-Curricular", design: "Navy/gold medal motif" },
      { key: "leadership_service", label: "Leadership & Service", design: "Deep red/gold star corners" },
      { key: "character_values", label: "Character & Values", design: "Teal/orange heart motif" },
      { key: "attendance_engagement", label: "Attendance & Engagement", design: "Minimalist teal accent" },
      { key: "spiritual_chaplaincy", label: "Spiritual & Chaplaincy", design: "Indigo/gold cross motif" },
      { key: "completion", label: "Completion", design: "Dark green ornate" },
    ];
  },

  catalogueMap() {
    const typesById = {};
    this.state.awardTypes.forEach((t) => { typesById[String(t.id)] = t; });
    return { typesById, categories: this.state.awardCategories };
  },

  async loadAwards() {
    const params = {};
    const cat = document.getElementById("awardCatFilter")?.value;
    if (cat) params.award_category_id = cat;
    try {
      const payload = await window.API.students.awards.list(params);
      this.state.awards = this.toArray(payload, null);
      this.renderAwardsTable();
    } catch (err) {
      this.showNotification("Failed to load awards", "error");
    }
  },

  renderAwardsTable() {
    const tbody = document.getElementById("awardsTable");
    if (!tbody) return;
    const rows = this.state.awards.map((a) => {
      const certNo = a.certificate_no || "—";
      const typeName = a.award_type_name || a.award_type || "";
      const catName = a.award_category_name || "";
      const printBtn = this.state.canManage && String(a.status) !== "revoked"
        ? `<button class="btn btn-sm btn-outline-success me-1" title="Generate PDF certificate"
             onclick="StudentLeadershipController.printAwardCertificate(${this.escapeHtml(a.id)})"><i class="fas fa-print"></i> ${a.certificate_no ? "PDF" : "Cert"}</button>`
        : "";
      return `<tr>
      <td class="fw-semibold">${this.escapeHtml(a.student_name)}</td>
      <td>${this.escapeHtml(a.admission_no || "—")}</td>
      <td>${this.escapeHtml(a.title)}</td>
      <td><span class="badge bg-primary">${this.escapeHtml(typeName || catName || "General")}</span></td>
      <td class="per-term-badge text-muted">${this.escapeHtml(a.academic_year_name || "")}${a.term_number ? " · Term " + this.escapeHtml(a.term_number) : ""}</td>
      <td>${this.escapeHtml(certNo)}</td>
      <td>${this.escapeHtml(a.issue_date || "—")}</td>
      <td>${this.statusBadge(a.status)}</td>
      ${this.state.canManage
        ? `<td class="text-end">
        ${printBtn}
        <button class="btn btn-sm btn-outline-primary" onclick="StudentLeadershipController.openAward(${this.escapeHtml(a.id)})"><i class="fas fa-edit"></i></button>
        <button class="btn btn-sm btn-outline-danger" onclick="StudentLeadershipController.removeAward(${this.escapeHtml(a.id)})"><i class="fas fa-trash"></i></button>
      </td>`
        : ""}
    </tr>`;
    }).join("");
    tbody.innerHTML = rows || `<tr><td colspan="${this.state.canManage ? 9 : 8}" class="empty-state">No awards issued in this category.</td></tr>`;
  },

  statusBadge(status) {
    const map = { awarded: "bg-success", revoked: "bg-danger", expired: "bg-secondary" };
    return `<span class="badge ${map[status] || "bg-secondary"}">${this.escapeHtml(status || "awarded")}</span>`;
  },

  fillAwardTypes() {
    const catSel = document.getElementById("awardCategory");
    const typeSel = document.getElementById("awardType");
    if (!catSel || !typeSel) return;
    const catId = catSel.value;
    const types = catId
      ? this.state.awardTypes.filter((t) => String(t.category_id) === String(catId))
      : this.state.awardTypes;
    typeSel.innerHTML = '<option value="">Select type...</option>' +
      types.map((t) => `<option value="${this.escapeHtml(t.id)}">${this.escapeHtml(t.name)}</option>`).join("");
  },

  async openAward(id = null) {
    if (!this.requireManage()) return;
    this.state.editAwardId = id ? parseInt(id, 10) : null;
    document.getElementById("awardModalTitle").textContent = this.state.editAwardId ? "Edit Award" : "Issue Award";
    this.fillAwardTypes();
    const values = { awardStudent: "", awardCategory: "", awardType: "", awardTitle: "", awardCertNo: "", awardIssueDate: new Date().toISOString().slice(0, 10), awardStatus: "awarded", awardDescription: "" };
    if (this.state.editAwardId) {
      const a = this.state.awards.find((x) => String(x.id) === String(id));
      if (a) {
        const type = a.award_type_id ? this.state.awardTypes.find((t) => String(t.id) === String(a.award_type_id)) : null;
        values.awardStudent = a.student_id;
        values.awardCategory = type ? type.category_id : (a.award_category_id || "");
        values.awardType = a.award_type_id || (type ? type.id : "");
        values.awardTitle = a.title;
        values.awardCertNo = a.certificate_no || "";
        values.awardIssueDate = a.issue_date || new Date().toISOString().slice(0, 10);
        values.awardStatus = a.status || "awarded";
        values.awardDescription = a.award_description || "";
      }
    }
    this.fillForm(values);
    this.fillAwardTypes();
    if (this.state.editAwardId) {
      document.getElementById("awardType").value = String(values.awardType);
    }
    bootstrap.Modal.getOrCreateInstance(document.getElementById("awardModal")).show();
  },

  async saveAward() {
    if (!this.requireManage()) return;
    const titleAuto = document.getElementById("awardTitle").value.trim();
    const typeSel = document.getElementById("awardType");
    const type = this.state.awardTypes.find((t) => String(t.id) === String(typeSel.value));
    const data = {
      student_id: document.getElementById("awardStudent").value,
      award_type_id: typeSel.value || null,
      title: titleAuto || (type?.name || ""),
      issue_date: document.getElementById("awardIssueDate").value || null,
      status: document.getElementById("awardStatus").value,
      award_description: document.getElementById("awardDescription").value || null,
    };
    if (!data.student_id || !data.award_type_id || !data.title) {
      this.showNotification("Student, category/type and title are required", "error");
      return;
    }
    try {
      if (this.state.editAwardId) {
        await window.API.students.awards.update(this.state.editAwardId, data);
      } else {
        await window.API.students.awards.create(data);
      }
      this.showNotification(this.state.editAwardId ? "Award updated" : "Award issued", "success");
      bootstrap.Modal.getInstance(document.getElementById("awardModal"))?.hide();
      this.state.editAwardId = null;
      this.loadAwards();
    } catch (err) {
      this.showNotification((err?.data?.message) || "Failed to save award", "error");
    }
  },

  async removeAward(id) {
    if (!this.requireManage()) return;
    if (!confirm("Remove this award? The certificate ledger entry stays for audit.")) return;
    try {
      await window.API.students.awards.delete(id);
      this.showNotification("Award removed", "success");
      this.loadAwards();
    } catch (err) {
      this.showNotification("Failed to remove award", "error");
    }
  },

  async printAwardCertificate(id) {
    if (!this.requireManage()) return;
    try {
      const payload = await window.API.students.awards.printCertificate(id);
      const results = payload?.data?.data?.results ?? payload?.data?.results ?? [];
      const first = Array.isArray(results) ? results[0] : null;
      if (first?.file?.url) {
        window.open(first.file.url, "_blank");
      } else if (first?.file_url) {
        window.open(first.file_url, "_blank");
      } else {
        this.showNotification(first?.message || "Certificate generated", "success");
      }
      this.loadAwards();
    } catch (err) {
      this.showNotification((err?.data?.message) || "Failed to generate certificate", "error");
    }
  },

  /* ---------------- AWARD TYPES ---------------- */
  async openAwardTypes() {
    if (!this.requireManage()) return;
    const catSel = document.getElementById("atCategory");
    if (catSel && catSel.options.length <= 1) {
      catSel.innerHTML = '<option value="">Select category...</option>' +
        this.state.awardCategories.map((c) => `<option value="${this.escapeHtml(c.id)}">${this.escapeHtml(c.name)}</option>`).join("");
    }
    const deptSel = document.getElementById("atDepartment");
    if (deptSel && deptSel.options.length <= 1) {
      deptSel.innerHTML = '<option value="">School-wide</option>' +
        this.state.departments.map((d) => `<option value="${this.escapeHtml(d.id)}">${this.escapeHtml(d.name || d.department_name)}</option>`).join("");
    }
    this.loadAwardTypesTable();
    bootstrap.Modal.getOrCreateInstance(document.getElementById("awardTypesModal")).show();
  },

  loadAwardTypesTable() {
    const tbody = document.getElementById("awardTypesTable");
    if (!tbody) return;
    const cats = this.state.awardCategories;
    const catById = {};
    cats.forEach((c) => { catById[String(c.id)] = c; });
    const dpById = {};
    this.state.departments.forEach((d) => { dpById[String(d.id)] = d; });
    const rows = this.state.awardTypes.map((t) => `<tr>
      <td class="text-muted small">${this.escapeHtml(t.code || "—")}</td>
      <td class="fw-semibold">${this.escapeHtml(t.name)}</td>
      <td>${this.escapeHtml(catById[String(t.category_id)]?.name || "—")}</td>
      <td>${this.escapeHtml(dpById[String(t.department_id)]?.name || "School-wide")}</td>
      <td class="small">${this.escapeHtml(t.template_key || "—")}</td>
      <td class="small">${this.escapeHtml(t.number_prefix || "—")}</td>
      <td class="small">${this.escapeHtml(t.signatory_label || "")}${t.secondary_signatory_label ? " + " + this.escapeHtml(t.secondary_signatory_label) : ""}</td>
      <td class="text-end">
        <button class="btn btn-sm btn-outline-primary" onclick="StudentLeadershipController.editAwardType(${this.escapeHtml(t.id)})"><i class="fas fa-edit"></i></button>
        <button class="btn btn-sm btn-outline-danger" onclick="StudentLeadershipController.removeAwardType(${this.escapeHtml(t.id)})"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`).join("");
    tbody.innerHTML = rows || '<tr><td colspan="7" class="empty-state">No award types. Create your first type below.</td></tr>';
  },

  resetAwardTypeForm() {
    this.state.editAwardTypeId = null;
    Array.from(document.querySelectorAll("#awardTypesSeedRow input")).forEach((i) => { i.value = ""; });
    document.getElementById("atTemplate") && (document.getElementById("atTemplate").value = "");
    document.getElementById("atCategory") && (document.getElementById("atCategory").value = "");
    document.getElementById("atDepartment") && (document.getElementById("atDepartment").value = "");
    const tpl = document.getElementById("atTemplate");
    if (tpl && tpl.options.length <= 1) {
      tpl.innerHTML = '<option value="">Select template...</option>' +
        this.templates().map((t) => `<option value="${t.key}">${t.label}</option>`).join("");
    }
  },

  editAwardType(id) {
    const t = this.state.awardTypes.find((x) => String(x.id) === String(id));
    if (!t) return;
    this.state.editAwardTypeId = t.id;
    document.getElementById("atCategory").value = t.category_id || "";
    document.getElementById("atDepartment").value = t.department_id || "";
    document.getElementById("atCode").value = t.code || "";
    document.getElementById("atName").value = t.name || "";
    document.getElementById("atDescription").value = t.description || "";
    document.getElementById("atPrefix").value = t.number_prefix || "";
    document.getElementById("atSignatory").value = t.signatory_label || "";
    document.getElementById("atSignatory2").value = t.secondary_signatory_label || "";
    document.getElementById("atTemplate").value = t.template_key || "";
  },

  async saveAwardType() {
    if (!this.requireManage()) return;
    const tpl = document.getElementById("atTemplate");
    const categories = this.state.awardCategories;
    const catOpts = document.getElementById("atCategory");
    if (catOpts && catOpts.options.length <= 1) {
      catOpts.innerHTML = '<option value="">Select category...</option>' +
        categories.map((c) => `<option value="${this.escapeHtml(c.id)}">${this.escapeHtml(c.name)}</option>`).join("");
    }
    const dept = document.getElementById("atDepartment");
    if (dept && dept.options.length <= 1) {
      dept.innerHTML = '<option value="">School-wide</option>' +
        this.state.departments.map((d) => `<option value="${this.escapeHtml(d.id)}">${this.escapeHtml(d.name || d.department_name)}</option>`).join("");
    }
    const data = {
      category_id: document.getElementById("atCategory").value,
      department_id: document.getElementById("atDepartment").value || null,
      name: document.getElementById("atName").value.trim(),
      description: document.getElementById("atDescription").value.trim() || null,
      number_prefix: document.getElementById("atPrefix").value.trim().toUpperCase(),
      template_key: tpl.value,
      signatory_label: document.getElementById("atSignatory").value.trim() || null,
      secondary_signatory_label: document.getElementById("atSignatory2").value.trim() || null,
    };
    data.code = document.getElementById("atCode").value.trim().toUpperCase().replace(/\s+/g, "-");
    if (!data.category_id || !data.code || !data.name || !data.template_key) {
      this.showNotification("Category, code, name and template are required", "error");
      return;
    }
    try {
      if (this.state.editAwardTypeId) {
        await window.API.students.awards.types.update(this.state.editAwardTypeId, data);
      } else {
        await window.API.students.awards.types.create(data);
      }
      this.showNotification(this.state.editAwardTypeId ? "Award type updated" : "Award type created", "success");
      this.resetAwardTypeForm();
      await this.loadAwardTypesTable();
      await this.refreshAwardCatalog();
    } catch (err) {
      this.showNotification((err?.data?.message) || "Failed to save award type", "error");
    }
  },

  async removeAwardType(id) {
    if (!this.requireManage()) return;
    const t = this.state.awardTypes.find((x) => String(x.id) === String(id));
    if (!confirm(`Remove award type "${t ? t.name : ""}"? Types already referenced by awards are locked.`)) return;
    try {
      await window.API.students.awards.types.delete(id);
      this.showNotification("Award type removed", "success");
      await this.loadAwardTypesTable();
      await this.refreshAwardCatalog();
    } catch (err) {
      this.showNotification((err?.data?.message) || "Failed to remove award type", "error");
    }
  },

  async refreshAwardCatalog() {
    try {
      const payload = await window.API.students.awards.catalogue();
      const catalog = payload?.data?.data ?? payload?.data ?? payload ?? {};
      this.state.awardCategories = Array.isArray(catalog.categories) ? catalog.categories : this.state.awardCategories;
      this.state.awardTypes = Array.isArray(catalog.types) ? catalog.types : this.state.awardTypes;
      this.populateSelects();
    } catch (e) {}
  },

  /* ---------------- KPIs ---------------- */
  renderKpis() {
    const host = document.getElementById("leadKpis");
    if (!host) return;
    const total = this.state.leadership.length;
    const active = this.state.leadership.filter((l) => String(l.is_active) !== "0").length;
    const awards = this.state.awards.length;
    const kpis = [
      { v: total, l: "Leadership Records" },
      { v: active, l: "Active Assignments" },
      { v: this.state.houses.length, l: "Houses" },
      { v: awards, l: "Awards Issued" },
    ];
    host.innerHTML = kpis.map((k) => `<div class="col-6 col-md-3"><div class="mini-stat"><div class="v">${k.v}</div><div class="l">${k.l}</div></div></div>`).join("");
  },
};

document.addEventListener("DOMContentLoaded", () => StudentLeadershipController.init());
