/**
 * Student Rewards & Recognition Controller
 * Awards, merit points, certificates, achievements.
 * API: /api/activities/achievements  (fallback: /api/students/discipline-get?type=reward)
 */

const studentRewardsController = {

  _rewards: [],
  _students: [],
  _activeFilter: 'all',

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    // Pre-fill today's date
    const dateEl = document.getElementById('srDate');
    if (dateEl) dateEl.value = new Date().toISOString().split('T')[0];

    // Pre-fill awarded-by from current user
    const user = AuthContext.getCurrentUser();
    const awardedByEl = document.getElementById('srAwardedBy');
    if (awardedByEl && user) {
      awardedByEl.value = (user.first_name || '') + ' ' + (user.last_name || '');
    }

    await this._loadStudentDropdown();
    await this._loadRewards();
    this._loadStats();
  },

  // ── DATA LOADING ──────────────────────────────────────────────────

  _loadRewards: async function () {
    this._renderTableSpinner();
    try {
      let r;
      try {
        r = await callAPI('/activities/achievements', 'GET');
      } catch (e) {
        r = await callAPI('/students/discipline-get?type=reward', 'GET');
      }
      this._rewards = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._renderTable(this._rewards);
      this._loadStats();
    } catch (e) {
      document.getElementById('srTableBody').innerHTML =
        `<tr><td colspan="8" class="text-center text-danger py-3">Failed to load awards: ${this._esc(e.message)}</td></tr>`;
    }
  },

  _loadStats: function () {
    const data = this._rewards;
    const total    = data.length;
    const points   = data.filter(r => r.award_type === 'Merit Point' || r.type === 'Merit Point')
                         .reduce((s, r) => s + (parseInt(r.points) || 1), 0);
    const certs    = data.filter(r => (r.award_type || r.type || '').toLowerCase().includes('certificate')).length;
    const students = new Set(data.map(r => r.student_id || r.id)).size;

    this._set('srStatTotal',    total);
    this._set('srStatPoints',   points);
    this._set('srStatCerts',    certs);
    this._set('srStatStudents', students);
  },

  // ── TABLE RENDERING ───────────────────────────────────────────────

  _renderTableSpinner: function () {
    document.getElementById('srTableBody').innerHTML =
      `<tr><td colspan="8" class="text-center py-4">
        <div class="spinner-border spinner-border-sm text-primary"></div>
        <span class="ms-2 text-muted">Loading awards…</span>
      </td></tr>`;
  },

  _renderTable: function (data) {
    const tbody = document.getElementById('srTableBody');
    if (!data.length) {
      tbody.innerHTML =
        '<tr><td colspan="8" class="text-center text-muted py-4">No awards found.</td></tr>';
      return;
    }
    tbody.innerHTML = data.map(r => {
      const type    = this._esc(r.award_type || r.type || '—');
      const typeBadge = this._typeBadge(r.award_type || r.type);
      const pts     = (r.award_type === 'Merit Point' || r.type === 'Merit Point')
                      ? `<span class="badge bg-success">${r.points || 1}</span>`
                      : '—';
      return `<tr>
        <td class="fw-semibold">${this._esc((r.first_name || r.student_name || '') + ' ' + (r.last_name || ''))}</td>
        <td>${this._esc(r.class_name || '—')}</td>
        <td>${typeBadge}</td>
        <td class="text-wrap" style="max-width:220px">${this._esc(r.description || r.details || '—')}</td>
        <td>${this._esc(r.awarded_by || r.issued_by || '—')}</td>
        <td>${this._esc(r.award_date || r.date || r.created_at || '—')}</td>
        <td class="text-center">${pts}</td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-primary me-1" onclick="studentRewardsController.editReward(${r.id})" title="Edit">
            <i class="bi bi-pencil"></i>
          </button>
          <button class="btn btn-sm btn-outline-danger" onclick="studentRewardsController.revokeReward(${r.id})" title="Revoke">
            <i class="bi bi-x-circle"></i>
          </button>
        </td>
      </tr>`;
    }).join('');
  },

  _typeBadge: function (type) {
    const t = (type || '').toLowerCase();
    let cls = 'bg-secondary';
    if (t.includes('merit'))       cls = 'bg-success';
    else if (t.includes('certif')) cls = 'bg-info text-dark';
    else if (t.includes('trophy') || t.includes('cup')) cls = 'bg-warning text-dark';
    else if (t.includes('special')) cls = 'bg-primary';
    else if (t.includes('academic')) cls = 'bg-info';
    else if (t.includes('behav'))  cls = 'bg-secondary';
    return `<span class="badge ${cls}">${this._esc(type || '—')}</span>`;
  },

  // ── FILTERING ─────────────────────────────────────────────────────

  filterByType: function (filter, btn) {
    this._activeFilter = filter;
    document.querySelectorAll('#srTabs .nav-link').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');

    const filtered = filter === 'all'
      ? this._rewards
      : this._rewards.filter(r => (r.award_type || r.type || '') === filter);
    this._renderTable(filtered);
  },

  // ── MODAL ─────────────────────────────────────────────────────────

  showAwardModal: function () {
    document.getElementById('srEditId').value  = '';
    document.getElementById('srStudentId').value = '';
    document.getElementById('srType').value    = '';
    document.getElementById('srDesc').value    = '';
    document.getElementById('srPoints').value  = '1';
    document.getElementById('srAwardedBy').value = '';
    document.getElementById('srDate').value    = new Date().toISOString().split('T')[0];
    document.getElementById('srError').classList.add('d-none');
    document.getElementById('srModalTitle').textContent = 'Add Award';

    const user = AuthContext.getCurrentUser();
    if (user) {
      document.getElementById('srAwardedBy').value =
        ((user.first_name || '') + ' ' + (user.last_name || '')).trim();
    }
    this._togglePointsField();
    new bootstrap.Modal(document.getElementById('srModal')).show();
  },

  editReward: function (id) {
    const r = this._rewards.find(x => x.id == id);
    if (!r) { showNotification('Award not found', 'warning'); return; }
    document.getElementById('srEditId').value     = r.id;
    document.getElementById('srStudentId').value  = r.student_id || '';
    document.getElementById('srType').value       = r.award_type || r.type || '';
    document.getElementById('srDesc').value       = r.description || r.details || '';
    document.getElementById('srPoints').value     = r.points || '1';
    document.getElementById('srAwardedBy').value  = r.awarded_by || r.issued_by || '';
    document.getElementById('srDate').value       = (r.award_date || r.date || '').split(' ')[0];
    document.getElementById('srError').classList.add('d-none');
    document.getElementById('srModalTitle').textContent = 'Edit Award';
    this._togglePointsField();
    new bootstrap.Modal(document.getElementById('srModal')).show();
  },

  onTypeChange: function () {
    this._togglePointsField();
  },

  _togglePointsField: function () {
    const type = document.getElementById('srType')?.value || '';
    const group = document.getElementById('srPointsGroup');
    if (group) {
      group.style.display = type === 'Merit Point' ? '' : 'none';
    }
  },

  // ── SAVE ──────────────────────────────────────────────────────────

  saveReward: async function () {
    const errEl = document.getElementById('srError');
    errEl.classList.add('d-none');

    const studentId = document.getElementById('srStudentId').value;
    const type      = document.getElementById('srType').value;
    const desc      = document.getElementById('srDesc').value.trim();
    const date      = document.getElementById('srDate').value;

    if (!studentId || !type || !desc || !date) {
      errEl.textContent = 'Please fill in all required fields (student, type, description, date)';
      errEl.classList.remove('d-none');
      return;
    }

    const editId  = document.getElementById('srEditId').value;
    const payload = {
      student_id:  parseInt(studentId),
      award_type:  type,
      type:        type,
      description: desc,
      details:     desc,
      points:      parseInt(document.getElementById('srPoints').value) || 1,
      award_date:  date,
      awarded_by:  document.getElementById('srAwardedBy').value.trim(),
    };

    try {
      if (editId) {
        await callAPI('/activities/achievements/' + editId, 'PUT', payload);
      } else {
        await callAPI('/activities/achievements', 'POST', payload);
      }
      bootstrap.Modal.getInstance(document.getElementById('srModal')).hide();
      showNotification(editId ? 'Award updated' : 'Award recorded', 'success');
      await this._loadRewards();
    } catch (e) {
      errEl.textContent = e.message || 'Save failed';
      errEl.classList.remove('d-none');
    }
  },

  revokeReward: async function (id) {
    if (!confirm('Revoke this award? This action cannot be undone.')) return;
    try {
      await callAPI('/activities/achievements/' + id, 'DELETE');
      showNotification('Award revoked', 'success');
      await this._loadRewards();
    } catch (e) {
      showNotification('Failed to revoke: ' + e.message, 'danger');
    }
  },

  // ── EXPORT ────────────────────────────────────────────────────────

  exportCSV: function () {
    const filtered = this._activeFilter === 'all'
      ? this._rewards
      : this._rewards.filter(r => (r.award_type || r.type || '') === this._activeFilter);

    if (!filtered.length) { showNotification('No data to export', 'warning'); return; }

    const headers = ['Student', 'Class', 'Award Type', 'Description', 'Awarded By', 'Date', 'Points'];
    const rows    = filtered.map(r => [
      `"${(r.first_name || r.student_name || '') + ' ' + (r.last_name || '')}"`,
      `"${r.class_name || ''}"`,
      `"${r.award_type || r.type || ''}"`,
      `"${(r.description || r.details || '').replace(/"/g, '""')}"`,
      `"${r.awarded_by || r.issued_by || ''}"`,
      `"${r.award_date || r.date || ''}"`,
      r.points || '',
    ]);

    const csv  = [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = 'student_rewards_' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
    URL.revokeObjectURL(url);
  },

  // ── STUDENT DROPDOWN ──────────────────────────────────────────────

  _loadStudentDropdown: async function () {
    try {
      const r = await callAPI('/students/list', 'GET').catch(() => callAPI('/students', 'GET'));
      this._students = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const sel = document.getElementById('srStudentId');
      if (!sel) return;
      sel.innerHTML = '<option value="">— Select student —</option>';
      this._students.forEach(s => {
        const o = document.createElement('option');
        o.value = s.id;
        o.textContent = `${s.first_name} ${s.last_name} (${s.admission_no || s.id})`;
        sel.appendChild(o);
      });
    } catch (e) { console.warn('Could not load students:', e); }
  },

  // ── UTILS ─────────────────────────────────────────────────────────

  _set: function (id, val) { const el = document.getElementById(id); if (el) el.textContent = val; },
  _esc: function (s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  },
};
