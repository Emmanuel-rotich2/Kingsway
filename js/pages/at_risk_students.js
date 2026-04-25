/**
 * At-Risk Students Controller
 * Merges chronic absentees, open discipline cases, and counseling referrals.
 * API: /api/attendance/*, /api/students/discipline-get, /api/counseling/*
 */

const atRiskController = {

  _allRows: [],
  _activeTab: 'all',

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    await this._loadAll();
  },

  // ── STATS + DATA ──────────────────────────────────────────────────

  _loadStats: async function (absentees, cases, counseling) {
    this._set('arStatAbsent',    absentees.length);
    this._set('arStatCases',     cases.length);
    this._set('arStatCounseling', counseling.length);
  },

  _loadAll: async function () {
    const container = document.getElementById('arTableContainer');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>';

    try {
      const [rAbsent, rCases, rCounseling] = await Promise.allSettled([
        callAPI('/attendance/chronic-student-absentees?threshold=80', 'GET'),
        callAPI('/students/discipline-get?status=open', 'GET'),
        callAPI('/counseling/summary', 'GET'),
      ]);

      const absentees  = this._extract(rAbsent);
      const cases      = this._extract(rCases);
      const counseling = this._extract(rCounseling);

      this._loadStats(absentees, cases, counseling);

      const absentRows = absentees.map(s => ({
        id:          s.student_id || s.id,
        name:        this._esc((s.first_name || '') + ' ' + (s.last_name || s.student_name || '')).trim(),
        class_name:  s.class_name || '—',
        category:    'attendance',
        details:     `Attendance: ${s.attendance_percentage ?? s.attendance_rate ?? '?'}%`,
        last_action: s.last_absence_date || s.updated_at || '—',
      }));

      const caseRows = cases.map(s => ({
        id:          s.student_id || s.id,
        name:        this._esc((s.first_name || '') + ' ' + (s.last_name || s.student_name || '')).trim(),
        class_name:  s.class_name || '—',
        category:    'discipline',
        details:     this._esc(s.case_type || s.violation_type || 'Open case'),
        last_action: s.incident_date || s.created_at || '—',
      }));

      const counselingRows = counseling.map(s => ({
        id:          s.student_id || s.id,
        name:        this._esc((s.first_name || '') + ' ' + (s.last_name || s.student_name || '')).trim(),
        class_name:  s.class_name || '—',
        category:    'counseling',
        details:     this._esc(s.reason || s.referral_reason || 'Counseling referral'),
        last_action: s.session_date || s.last_session || s.created_at || '—',
      }));

      this._allRows = [...absentRows, ...caseRows, ...counselingRows];
      this._renderTable(this._allRows);
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed to load at-risk data: ${this._esc(e.message)}</div>`;
    }
  },

  _extract: function (settled) {
    if (settled.status !== 'fulfilled') return [];
    const r = settled.value;
    return Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
  },

  // ── RENDER ────────────────────────────────────────────────────────

  _renderTable: function (rows) {
    const container = document.getElementById('arTableContainer');
    if (!container) return;

    if (!rows.length) {
      container.innerHTML = '<div class="alert alert-info text-center">No at-risk students found for the selected filter.</div>';
      return;
    }

    const categoryBadge = cat => {
      const map = {
        attendance: 'bg-danger',
        discipline: 'bg-warning text-dark',
        counseling: 'bg-info',
      };
      return `<span class="badge ${map[cat] || 'bg-secondary'}">${this._esc(cat)}</span>`;
    };

    const rows_html = rows.map(row => `
      <tr data-category="${this._esc(row.category)}">
        <td class="fw-semibold">${row.name || '—'}</td>
        <td>${this._esc(row.class_name)}</td>
        <td>${categoryBadge(row.category)}</td>
        <td>${this._esc(row.details)}</td>
        <td>${this._esc(row.last_action)}</td>
        <td>
          ${row.id ? `<a href="${window.APP_BASE || ''}/home.php?route=student_profiles&id=${row.id}"
            class="btn btn-sm btn-outline-primary">View Profile</a>` : '—'}
        </td>
      </tr>
    `).join('');

    container.innerHTML = `<div class="table-responsive">
      <table class="table table-hover align-middle" id="arTableBody">
        <thead class="table-light"><tr>
          <th>Student Name</th>
          <th>Class</th>
          <th>Risk Category</th>
          <th>Details</th>
          <th>Last Action</th>
          <th></th>
        </tr></thead>
        <tbody>${rows_html}</tbody>
      </table>
    </div>`;
  },

  // ── FILTER ────────────────────────────────────────────────────────

  filterByTab: function (tab, btn) {
    this._activeTab = tab;

    // Update active tab styles
    document.querySelectorAll('#arFilterTabs .nav-link').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');

    const filtered = tab === 'all'
      ? this._allRows
      : this._allRows.filter(r => r.category === tab);
    this._renderTable(filtered);
  },

  // ── EXPORT ────────────────────────────────────────────────────────

  exportCSV: function () {
    const rows = this._activeTab === 'all'
      ? this._allRows
      : this._allRows.filter(r => r.category === this._activeTab);

    if (!rows.length) {
      showNotification('No data to export', 'warning');
      return;
    }

    const header = ['Student Name', 'Class', 'Risk Category', 'Details', 'Last Action'];
    const csvRows = [
      header.join(','),
      ...rows.map(r => [
        `"${r.name}"`,
        `"${r.class_name}"`,
        `"${r.category}"`,
        `"${r.details}"`,
        `"${r.last_action}"`,
      ].join(',')),
    ];

    const blob = new Blob([csvRows.join('\n')], { type: 'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `at_risk_students_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  },

  // ── UTILS ─────────────────────────────────────────────────────────

  _set: function (id, val) { const el = document.getElementById(id); if (el) el.textContent = val; },
  _esc: function (s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  },
};

document.addEventListener('DOMContentLoaded', () => atRiskController.init());
