/* Kingsway results pivot utilities — pure, DOM-free, unit-testable.
   One place reshapes the flat per-subject result rows into the student-keyed
   pivot used by the summative results table, and owns the score validation
   + autosave debounce rules shared by the client and (mirrored server-side
   in SummativeBatchValidator) the write path.

   Loaded as a page asset; also require-able under `node --test`. */
(function (global) {
  'use strict';

  const DEFAULT_MAX_MARKS = 100;

  /**
   * Pivot flat result rows (one row per learner x subject x exam period)
   * into { columns, students } where each learner appears exactly once.
   *
   * Column keys are `${examPeriodId}:${learningAreaId}` so a scope holding
   * several exam periods renders one column per period-subject pair; with a
   * single period the label is just the subject name.
   */
  function pivotStudentRows(rows) {
    const safeRows = Array.isArray(rows) ? rows : [];
    const columnMap = new Map();
    const studentMap = new Map();

    safeRows.forEach((row) => {
      const periodId = Number(row.exam_period_id || 0);
      const areaId = Number(row.learning_area_id || 0);
      const colKey = `${periodId}:${areaId}`;
      if (!columnMap.has(colKey)) {
        columnMap.set(colKey, {
          key: colKey,
          exam_period_id: periodId,
          learning_area_id: areaId,
          subject: String(row.learning_area || 'Subject'),
          period_title: String(row.exam_period_title || ''),
          max_marks: Number(row.max_marks || DEFAULT_MAX_MARKS),
        });
      }
      const column = columnMap.get(colKey);
      column.max_marks = Math.max(column.max_marks, Number(row.max_marks || DEFAULT_MAX_MARKS));
      column.save_key = 'assessment_id';

      const studentKey = String(row.enrollment_id || `s${row.student_id || 0}`);
      if (!studentMap.has(studentKey)) {
        studentMap.set(studentKey, {
          key: studentKey,
          enrollment_id: Number(row.enrollment_id || 0),
          learner_name: String(row.learner_name || '—'),
          admission_no: String(row.admission_no || ''),
          class_name: String(row.class_name || ''),
          stream_name: String(row.stream_name || ''),
          cells: {},
        });
      }
      studentMap.get(studentKey).cells[colKey] = {
        result_id: Number(row.result_id || 0),
        marks_obtained: row.marks_obtained === null || row.marks_obtained === undefined ? null : Number(row.marks_obtained),
        max_marks: Number(row.max_marks || DEFAULT_MAX_MARKS),
        entry_status: String(row.entry_status || ''),
        percentage: row.percentage === null || row.percentage === undefined ? null : Number(row.percentage),
        grade: row.grade ? String(row.grade).toUpperCase() : '',
        assessment_id: Number(row.assessment_id || 0),
        assessment_status: String(row.assessment_status || ''),
        period_status: String(row.period_status || row.period_status_entry || ''),
        published: Boolean(row.results_published_at),
        deleted: Boolean(row.result_deleted_at),
        remarks: String(row.remarks || ''),
        updated_at: String(row.result_updated_at || ''),
      };
    });

    const columns = [...columnMap.values()].sort((a, b) =>
      a.exam_period_id - b.exam_period_id || a.subject.localeCompare(b.subject));
    const multiPeriod = new Set(columns.map((c) => c.exam_period_id)).size > 1;
    const students = [...studentMap.values()].sort((a, b) =>
      a.class_name.localeCompare(b.class_name) || a.learner_name.localeCompare(b.learner_name));

    return { columns, students, multiPeriod };
  }

  /**
   * Pivot the paper-grid payload (GET /academic/assessment-papers/{id}) into
   * the same { columns, students, multiPeriod } shape the pivot table renders:
   * papers become the dynamic columns, learners the rows, and each cell is
   * that learner's mark for one paper. Carries the same save keys
   * (save_key = 'paper_id') and concurrency token so the table component
   * needs no special-casing.
   */
  function pivotPaperRows(grid) {
    const data = grid || {};
    const columns = (Array.isArray(data.papers) ? data.papers : []).map((paper) => ({
      key: `p${paper.id}`,
      paper_id: Number(paper.id),
      paper_number: Number(paper.paper_number || 1),
      subject: String(paper.title || `Paper ${paper.paper_number || 1}`),
      period_title: '',
      max_marks: Number(paper.max_marks || DEFAULT_MAX_MARKS),
      is_active: Number(paper.is_active === undefined ? 1 : paper.is_active) === 1,
      save_key: 'paper_id',
    }));
    const students = (Array.isArray(data.learners) ? data.learners : []).map((learner) => ({
      key: String(learner.enrollment_id),
      enrollment_id: Number(learner.enrollment_id || 0),
      learner_name: String(learner.learner_name || '—'),
      admission_no: String(learner.admission_no || ''),
      class_name: String(learner.class_name || ''),
      stream_name: String(learner.stream_name || ''),
      cells: {},
    }));
    (Array.isArray(data.paper_results) ? data.paper_results : []).forEach((result) => {
      const student = students.find((s) => s.key === String(result.student_academic_enrollment_id));
      const column = columns.find((c) => c.paper_id === Number(result.assessment_paper_id));
      if (!student || !column) return;
      student.cells[column.key] = {
        result_id: Number(result.result_id || result.id || 0),
        paper_id: column.paper_id,
        marks_obtained: result.marks_obtained === null || result.marks_obtained === undefined ? null : Number(result.marks_obtained),
        max_marks: column.max_marks,
        entry_status: String(result.entry_status || ''),
        percentage: null,
        grade: '',
        assessment_id: Number((data.assessment || {}).id || 0),
        assessment_status: String((data.assessment || {}).status || ''),
        period_status: '',
        published: false,
        deleted: false,
        remarks: String(result.remarks || ''),
        updated_at: String(result.updated_at || ''),
      };
    });
    return { columns, students, multiPeriod: false };
  }

  /**
   * Validate one score input. Returns { ok, value, errors } — never throws.
   * rules: { min, max, decimals, required }
   */
  function validateScoreInput(raw, rules) {
    const config = Object.assign({ min: 0, max: DEFAULT_MAX_MARKS, decimals: 2, required: false }, rules || {});
    const errors = [];
    const text = raw === null || raw === undefined ? '' : String(raw).trim();

    if (text === '') {
      if (config.required) errors.push('A mark is required.');
      return { ok: errors.length === 0, value: null, errors };
    }
    if (!/^-?\d*(\.\d+)?$/.test(text) || !Number.isFinite(Number(text))) {
      return { ok: false, value: null, errors: ['The mark must be numeric.'] };
    }
    const value = Number(text);
    if (value < config.min || value > config.max) {
      errors.push(`The mark must be between ${config.min} and ${config.max}.`);
    }
    const decimals = (text.split('.')[1] || '').length;
    if (decimals > config.decimals) {
      errors.push(`The mark may not exceed ${config.decimals} decimal place(s).`);
    }
    return { ok: errors.length === 0, value, errors };
  }

  /** Normalize an updated_at token the way the server validator does (seconds precision). */
  function normalizeUpdatedAt(stamp) {
    if (!stamp) return '';
    const match = String(stamp).trim().match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})/);
    return match ? `${match[1]} ${match[2]}` : String(stamp).trim();
  }

  /** Optimistic-concurrency comparison; a missing expectation forces (matches the server rule). */
  function updateMatches(expected, actual) {
    if (!expected) return true;
    if (!actual) return false;
    return normalizeUpdatedAt(expected) === normalizeUpdatedAt(actual);
  }

  /**
   * Per-row debounced autosave: schedule(key) resets that row's timer,
   * cancel(key) drops it, flush(key) fires it immediately. One shared timer
   * map keeps many rows cheap; nothing else is scheduled globally.
   */
  function createDebouncedSave(saveFn, delayMs) {
    const delay = Math.max(0, Number(delayMs === undefined ? 800 : delayMs));
    const timers = new Map();
    const api = {
      schedule(key) {
        api.cancel(key);
        timers.set(key, setTimeout(() => {
          timers.delete(key);
          Promise.resolve(saveFn(key)).catch(() => {});
        }, delay));
      },
      cancel(key) {
        const timer = timers.get(key);
        if (timer) { clearTimeout(timer); timers.delete(key); }
      },
      flush(key) {
        if (!timers.has(key)) return Promise.resolve(false);
        api.cancel(key);
        return Promise.resolve(saveFn(key)).then(() => true).catch(() => true);
      },
      pending(key) { return timers.has(key); },
      cancelAll() { [...timers.keys()].forEach(api.cancel); },
    };
    return api;
  }

  /** Sort students by a student column or a subject column's numeric value. */
  function sortStudents(students, sortKey, sortDir, columns) {
    const column = columns ? columns.find((c) => c.key === sortKey) : null;
    const factor = sortDir === 'desc' ? -1 : 1;
    return [...students].sort((a, b) => {
      if (column) {
        const av = a.cells[sortKey] ? (a.cells[sortKey].marks_obtained === null ? -Infinity : a.cells[sortKey].marks_obtained) : -Infinity;
        const bv = b.cells[sortKey] ? (b.cells[sortKey].marks_obtained === null ? -Infinity : b.cells[sortKey].marks_obtained) : -Infinity;
        return (av - bv) * factor || a.learner_name.localeCompare(b.learner_name);
      }
      const field = sortKey === 'admission_no' ? 'admission_no' : sortKey === 'class' ? 'class_name' : 'learner_name';
      return String(a[field] || '').localeCompare(String(b[field] || '')) * factor;
    });
  }

  const api = { pivotStudentRows, pivotPaperRows, validateScoreInput, updateMatches, normalizeUpdatedAt, createDebouncedSave, sortStudents, DEFAULT_MAX_MARKS };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  global.KingswayResultsPivot = api;
})(typeof window !== 'undefined' ? window : globalThis);
