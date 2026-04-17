/**
 * Core Competencies Sheet Controller
 * CBC: Rate each student on 8 core competencies per term.
 * API: /api/academic/competency-ratings
 */

const compCtrl = {

  _competencies:     [],
  _students:         [],
  _ratings:          {},   // { student_id: { competency_id: { rating, evidence } } }
  _ratingToLevelCode: { consistently: 'EE', sometimes: 'ME', rarely: 'BE' },
  _levelCodeToRating: { EE: 'consistently', ME: 'sometimes', AE: 'sometimes', BE: 'rarely' },

  // ── INIT ──────────────────────────────────────────────────────────────

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    await Promise.all([
      this._loadTerms(),
      this._loadClasses(),
      this._loadCompetencies(),
    ]);
  },

  refresh: function () {
    this.loadSheet();
  },

  // ── DROPDOWN LOADERS ──────────────────────────────────────────────────

  _loadTerms: async function () {
    try {
      const r    = await callAPI('/academic/terms-list', 'GET');
      const list = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const sel  = document.getElementById('compTermSelect');
      if (!sel) return;
      sel.innerHTML = '<option value="">— Select term —</option>';
      list.forEach(t => sel.insertAdjacentHTML('beforeend', `<option value="${t.id}">${this._esc(t.name)}</option>`));
    } catch (e) { console.warn('Terms load failed:', e); }
  },

  _loadClasses: async function () {
    try {
      const r    = await callAPI('/academic/classes-list', 'GET');
      const list = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const sel  = document.getElementById('compClassSelect');
      if (!sel) return;
      sel.innerHTML = '<option value="">— Select class —</option>';
      list.forEach(c => sel.insertAdjacentHTML('beforeend', `<option value="${c.id}">${this._esc(c.name)}</option>`));
    } catch (e) { console.warn('Classes load failed:', e); }
  },

  _loadCompetencies: async function () {
    // CBC Kenya — 8 core competencies (fixed curriculum)
    // Try to load from DB; fall back to canonical list if endpoint unavailable
    try {
      const r    = await callAPI('/academic/core-competencies-list', 'GET');
      const list = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      if (list.length) { this._competencies = list; return; }
    } catch (_) {}
    this._competencies = [
      { id: 1, name: 'Communication & Collaboration' },
      { id: 2, name: 'Critical Thinking & Problem Solving' },
      { id: 3, name: 'Creativity & Imagination' },
      { id: 4, name: 'Citizenship' },
      { id: 5, name: 'Digital Literacy' },
      { id: 6, name: 'Learning to Learn' },
      { id: 7, name: 'Self-Efficacy' },
      { id: 8, name: 'Cultural Identity & Expression' },
    ];
  },

  // ── LOAD SHEET ────────────────────────────────────────────────────────

  loadSheet: async function () {
    const termId  = document.getElementById('compTermSelect')?.value;
    const classId = document.getElementById('compClassSelect')?.value;

    if (!termId || !classId) return;

    const container = document.getElementById('compSheetContainer');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-warning"></div></div>';

    try {
      // Load students for class
      const rStudents = await callAPI('/students/by-class-get/' + classId, 'GET');
      this._students  = Array.isArray(rStudents?.data) ? rStudents.data : (Array.isArray(rStudents) ? rStudents : []);

      // Load existing ratings
      const rRatings  = await callAPI(`/academic/competency-ratings?term_id=${termId}&class_id=${classId}`, 'GET');
      const ratings   = Array.isArray(rRatings?.data) ? rRatings.data : (Array.isArray(rRatings) ? rRatings : []);

      // Index ratings
      this._ratings = {};
      ratings.forEach(r => {
        if (!this._ratings[r.student_id]) this._ratings[r.student_id] = {};
        // Map level_code back to friendly rating label for the dropdown
        const ratingLabel = this._levelCodeToRating[r.level_code] || r.rating || '';
        this._ratings[r.student_id][r.competency_id] = { rating: ratingLabel, evidence: r.evidence };
      });

      if (!this._students.length) {
        container.innerHTML = '<div class="alert alert-info m-3">No students found for this class.</div>';
        return;
      }

      this._renderSheet(container, termId, classId);
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger m-3">Failed to load sheet: ${this._esc(e.message)}</div>`;
    }
  },

  _renderSheet: function (container, termId, classId) {
    const compHeaders = this._competencies.map((c, i) =>
      `<th class="text-center" style="min-width:110px; font-size:.78rem;">
        <span title="${this._esc(c.name)}">${this._esc(this._shortName(c.name))}</span>
      </th>`
    ).join('');

    const rows = this._students.map(s => {
      const cells = this._competencies.map(c => {
        const existing = this._ratings[s.id]?.[c.id];
        const rating   = existing?.rating || '';
        const options  = ['', 'consistently', 'sometimes', 'rarely'].map(v =>
          `<option value="${v}" ${rating === v ? 'selected' : ''}>${v ? v.charAt(0).toUpperCase() + v.slice(1) : '— —'}</option>`
        ).join('');
        return `<td class="text-center p-1">
          <select class="form-select form-select-sm comp-rating-sel"
            data-student="${s.id}" data-comp="${c.id}"
            onchange="compCtrl._updateBadge(this)"
            style="font-size:.78rem;padding:2px 4px;min-width:100px;">
            ${options}
          </select>
        </td>`;
      }).join('');

      return `<tr>
        <td class="fw-semibold">${this._esc(s.first_name + ' ' + s.last_name)}</td>
        <td class="text-muted">${this._esc(s.admission_no || '—')}</td>
        ${cells}
      </tr>`;
    }).join('');

    container.innerHTML = `
      <div class="d-flex justify-content-between align-items-center px-3 pt-3 pb-2 border-bottom">
        <span class="fw-semibold">${this._students.length} students · ${this._competencies.length} competencies</span>
        <button class="btn btn-success btn-sm" onclick="compCtrl.saveAll()">
          <i class="bi bi-floppy me-1"></i> Save All Ratings
        </button>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover comp-table mb-0" id="compTable"
          data-term="${termId}" data-class="${classId}">
          <thead class="table-light">
            <tr>
              <th style="min-width:160px;">Student</th>
              <th style="min-width:80px;">Adm No</th>
              ${compHeaders}
            </tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>
      </div>
      <div class="px-3 py-2 border-top">
        <button class="btn btn-success" onclick="compCtrl.saveAll()">
          <i class="bi bi-floppy me-1"></i> Save All Ratings
        </button>
        <span class="text-muted small ms-3">Changes are saved in bulk when you click Save.</span>
      </div>`;
  },

  _shortName: function (name) {
    // Abbreviate long competency names for table headers
    const map = {
      'Communication & Collaboration':         'Comm. & Collab.',
      'Critical Thinking & Problem Solving':   'Critical Thinking',
      'Creativity & Imagination':              'Creativity',
      'Citizenship':                           'Citizenship',
      'Digital Literacy':                      'Digital Literacy',
      'Learning to Learn':                     'Learning to Learn',
      'Self-Efficacy':                         'Self-Efficacy',
      'Cultural Identity & Expression':        'Cultural Identity',
    };
    return map[name] || name.substring(0, 18);
  },

  _updateBadge: function (select) {
    // Visual feedback — change select color based on rating
    select.className = 'form-select form-select-sm comp-rating-sel';
    const v = select.value;
    if (v === 'consistently') select.style.background = '#e8f5e9';
    else if (v === 'sometimes') select.style.background = '#e3f2fd';
    else if (v === 'rarely')    select.style.background = '#ffebee';
    else select.style.background = '';
  },

  // ── SAVE ALL ──────────────────────────────────────────────────────────

  saveAll: async function () {
    const table  = document.getElementById('compTable');
    if (!table) { showNotification('Please load the sheet first', 'warning'); return; }

    const termId  = parseInt(table.dataset.term);
    const ratings = [];

    table.querySelectorAll('.comp-rating-sel').forEach(sel => {
      if (!sel.value) return;
      ratings.push({
        student_id:    parseInt(sel.dataset.student),
        competency_id: parseInt(sel.dataset.comp),
        level_code:    this._ratingToLevelCode[sel.value] || 'ME',
        evidence:      null,
      });
    });

    if (!ratings.length) {
      showNotification('No ratings to save yet — assign at least one rating', 'info');
      return;
    }

    try {
      await callAPI('/academic/competency-ratings', 'POST', { term_id: termId, ratings });
      showNotification(`Saved ${ratings.length} competency ratings`, 'success');
    } catch (e) {
      showNotification('Save failed: ' + (e.message || 'Unknown error'), 'danger');
    }
  },

  // ── UTILS ──────────────────────────────────────────────────────────────

  _esc: function (s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  },
};
