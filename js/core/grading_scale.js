/**
 * DB-driven grading scale.
 *
 * Grade boundaries live in the database (`grading_systems` +
 * `grading_system_bands`, managed on the Grading Scales admin page) and are
 * served by GET /api/academic/grading-scale. When the current term is known
 * (window.AcademicContext), the endpoint also resolves the term aggregation
 * profile, so the bands follow the grading system the school assigned to that
 * term/exam. Nothing here is hardcoded — edit the database and every page
 * that resolves a CBC grade updates automatically.
 *
 * Usage:
 *   await GradingScale.preload();       // once per page, before first render
 *   GradingScale.grade(score, maxMarks);        // e.g. "EE2"
 *   GradingScale.gradeName(score, maxMarks);    // e.g. "Exceeding Expectation 2"
 *   GradingScale.remarks(score, maxMarks);      // descriptor text
 *   GradingScale.performanceLevel(score, maxMarks); // e.g. "Exceeding Expectation"
 *   GradingScale.gradePoints(score, maxMarks);
 *   GradingScale.band(code);                    // "EE2" -> "EE" (for CSS classes)
 *   GradingScale.rules();                       // all bands for the active scale
 *
 * The helpers are synchronous and read from a module-level cache (memory →
 * localStorage). `preload()` fills the cache; call it alongside page data
 * loading so renders always have the scale ready. `invalidate()` forces a
 * refetch (also triggered by storage events across tabs).
 */
(function () {
  const STORAGE_KEY = "kingsway:grading-scale:v2";
  const TTL = 60 * 60 * 1000;
  let memory = null;

  function toPercent(score, maxMarks) {
    const s = Number(score) || 0;
    const m = Number(maxMarks) || 0;
    return m > 0 ? (s / m) * 100 : s;
  }

  function currentTermId() {
    try {
      const t = window.AcademicContext && window.AcademicContext.state && window.AcademicContext.state.termId;
      return t ? Number(t) : null;
    } catch (e) {
      return null;
    }
  }

  /** Normalize a grading_system_bands row to the legacy `rule` shape. */
  function bandToRule(b) {
    return {
      id: b.id,
      grade_code: b.band_code,
      grade_name: b.band_name,
      min_mark: Number(b.min_percentage),
      max_mark: Number(b.max_percentage),
      grade_points: Number(b.points) || 0,
      achievement_level: b.achievement_level ?? null,
      performance_level: b.performance_level || "",
      description: b.description || "",
      sort_order: Number(b.sort_order) || 0,
    };
  }

  async function fetchFromApi() {
    const params = {};
    const termId = currentTermId();
    if (termId) params.term_id = termId;
    const res = await apiCall("academic/grading-scale", "GET", null, params);
    const data = res && res.data !== undefined && res.systems === undefined ? res.data : res;
    const systems = Array.isArray(data && data.systems) ? data.systems : [];
    const resolved = data && data.resolved_profile ? data.resolved_profile : null;

    let system = null;
    if (resolved && resolved.grading_system_id) {
      system = systems.find(s => Number(s.id) === Number(resolved.grading_system_id)) || null;
    }
    if (!system) {
      // Prefer the 8-level system for exam-style grading, else the first active.
      system = systems.find(s => Number(s.levels_count) === 8) || systems[0] || null;
    }
    const bands = system && Array.isArray(system.bands) ? system.bands : [];
    return {
      scale: system
        ? { id: system.id, code: system.code, name: system.name, levels_count: system.levels_count }
        : null,
      rules: bands.map(bandToRule),
      resolved_scope: resolved ? resolved.resolved_scope || resolved.scope : null,
      formative_weight: resolved ? Number(resolved.formative_weight) : null,
      summative_weight: resolved ? Number(resolved.summative_weight) : null,
    };
  }

  function loadCache() {
    if (memory) return memory;
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      if (!parsed || Date.now() - (parsed.fetchedAt || 0) > TTL) return null;
      memory = parsed;
      return memory;
    } catch (e) {
      return null;
    }
  }

  async function preload(source) {
    if (memory) return memory;
    const cached = loadCache();
    if (cached) return cached;
    try {
      const fresh =
        typeof source === "function" ? await source() : await fetchFromApi();
      memory = Object.assign({}, fresh, { fetchedAt: Date.now() });
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(memory));
      } catch (e) {
        /* storage unavailable (private mode / quota) — memory cache suffices */
      }
      return memory;
    } catch (e) {
      if (cached) {
        memory = cached;
        return memory;
      }
      memory = { scale: null, rules: [], fetchedAt: Date.now() };
      return memory;
    }
  }

  function ruleFor(score, maxMarks) {
    if (!memory || !Array.isArray(memory.rules)) return null;
    const pct = toPercent(score, maxMarks);
    for (let i = 0; i < memory.rules.length; i++) {
      const r = memory.rules[i];
      if (pct >= Number(r.min_mark) && pct <= Number(r.max_mark)) return r;
    }
    return null;
  }

  function grade(score, maxMarks) {
    const r = ruleFor(score, maxMarks);
    return r ? r.grade_code : "";
  }

  function gradeName(score, maxMarks) {
    const r = ruleFor(score, maxMarks);
    return r ? r.grade_name : "";
  }

  function remarks(score, maxMarks) {
    const r = ruleFor(score, maxMarks);
    return r && r.description ? r.description : "";
  }

  function performanceLevel(score, maxMarks) {
    const r = ruleFor(score, maxMarks);
    return r ? r.performance_level : "";
  }

  function gradePoints(score, maxMarks) {
    const r = ruleFor(score, maxMarks);
    return r ? Number(r.grade_points) || 0 : 0;
  }

  function rules() {
    return memory && Array.isArray(memory.rules) ? memory.rules : [];
  }

  function scale() {
    return memory ? memory.scale : null;
  }

  /** Strip numeric suffixes so styling classes (grade-EE etc.) keep working for codes like EE1/EE2. */
  function band(code) {
    return String(code || "").replace(/[0-9]+$/g, "");
  }

  function invalidate() {
    memory = null;
    try {
      localStorage.removeItem(STORAGE_KEY);
    } catch (e) {
      /* ignore */
    }
  }

  window.addEventListener("storage", function (e) {
    if (e.key === STORAGE_KEY) invalidate();
  });

  window.GradingScale = {
    preload: preload,
    ruleFor: ruleFor,
    grade: grade,
    gradeName: gradeName,
    remarks: remarks,
    performanceLevel: performanceLevel,
    gradePoints: gradePoints,
    rules: rules,
    scale: scale,
    band: band,
    invalidate: invalidate,
  };
})();
