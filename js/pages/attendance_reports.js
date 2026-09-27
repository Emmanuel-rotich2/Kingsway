/**
 * Attendance Reports Controller
 * Provides attendance statistics by class, chronic absentees, and trends.
 * API: /api/attendance/*  and  /api/reports/*
 */

const attendanceReportsController = {

  _classData:   [],
  _chronicData: [],
  _registerGroups: [],
  _registerPage: 1,
  _registerLevelPages: {},
  _trendsChart: null,

  init: async function () {
    await window.AuthContext?.ready();
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    this._bindTabs();
    ['arPeriod', 'arDateFrom', 'arDateTo', 'arClass'].forEach(id => {
      document.getElementById(id)?.addEventListener('change', () => {
        if (id === 'arPeriod') this.onPeriodChange();
        this.load();
      });
    });
    this._populateClassFilter();
    await this.load();
  },

  _bindTabs: function () {
    document.querySelectorAll('#arTabs .nav-link').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('#arTabs .nav-link').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const tab = btn.dataset.tab;
        document.getElementById('arTabClasses').classList.toggle('d-none', tab !== 'classes');
        document.getElementById('arTabChronic').classList.toggle('d-none', tab !== 'chronic');
        document.getElementById('arTabTrends').classList.toggle('d-none',  tab !== 'trends');
        if (tab === 'trends') this._renderTrendsChart();
        if (tab === 'chronic' && !this._chronicData.length) this._loadChronic();
      });
    });
  },

  onPeriodChange: function () {
    const period = document.getElementById('arPeriod').value;
    const show   = period === 'custom';
    document.getElementById('arDateFromWrap').classList.toggle('d-none', !show);
    document.getElementById('arDateToWrap').classList.toggle('d-none',   !show);
  },

  _getDateRange: function () {
    const period = document.getElementById('arPeriod').value;
    const today  = new Date();
    const fmt    = d => d.toISOString().split('T')[0];

    if (period === 'this_week') {
      const mon = new Date(today);
      mon.setDate(today.getDate() - today.getDay() + 1);
      return { date_from: fmt(mon), date_to: fmt(today) };
    }
    if (period === 'this_month') {
      return { date_from: fmt(new Date(today.getFullYear(), today.getMonth(), 1)), date_to: fmt(today) };
    }
    if (period === 'this_term') {
      return { period: 'current_term' };
    }
    // custom
    return {
      date_from: document.getElementById('arDateFrom').value,
      date_to:   document.getElementById('arDateTo').value,
    };
  },

  load: async function () {
    const params  = { ...this._getDateRange() };
    const classId = document.getElementById('arClass')?.value;
    if (classId) params.class_id = classId;

    const summaryPromise = this._fetchAcademicSummary(params);
    await Promise.all([
      this._loadSummary(summaryPromise),
      this._loadClassBreakdown(summaryPromise),
    ]);
  },

  _fetchAcademicSummary: async function (params) {
    const r = await callAPI('/attendance/academic-summary?' + new URLSearchParams(params).toString(), 'GET');
    return r?.data ?? r ?? {};
  },

  _loadSummary: async function (summaryPromise) {
    try {
      const d = await summaryPromise;
      const setEl = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
      const summary = d.summary || {};
      setEl('arStatTotal',   summary.student_count ?? d.students?.length ?? 0);
      setEl('arStatRate',    summary.average_attendance != null ? summary.average_attendance + '%' : '0%');
      setEl('arStatAbsent',  summary.absent ?? 0);
      setEl('arStatChronic', Array.isArray(d.low_attendance) ? d.low_attendance.length : 0);
      this._renderRegisterAudit(d.register_audit);
    } catch (e) { console.warn('Summary load failed:', e); }
  },

  _renderRegisterAudit: function (audit) {
    const el = document.getElementById('arRegisterAlert');
    const detailsCard = document.getElementById('arRegisterDetails');
    const detailsList = document.getElementById('arRegisterDetailsList');
    if (!el) return;
    const counts = audit?.counts || {};
    const exceptions = (counts.missing || 0) + (counts.not_marked || 0) + (counts.overdue || 0) + (counts.open || 0);
    if (!exceptions) {
      el.classList.add('d-none');
      el.textContent = '';
      detailsCard?.classList.add('d-none');
      if (detailsList) detailsList.innerHTML = '';
      return;
    }
    const rows = Array.isArray(audit?.exception_registers) ? audit.exception_registers : [];
    this._sessionCoverage = new Map();
    (Array.isArray(audit?.registers) ? audit.registers : []).forEach(register => {
      const key = `${register.register_date}\u0000${register.stream_name}`;
      if (!this._sessionCoverage.has(key)) this._sessionCoverage.set(key, []);
      this._sessionCoverage.get(key).push({
        session_name: register.session_name || 'Attendance session',
        applies_to: register.applies_to || 'all',
      });
    });
    const groups = new Map();
    rows.forEach((row) => {
      // One group per class. A single configured session is the class's
      // full-day register; multiple sessions stay in the same class group so
      // a learner is never printed once for morning and again for afternoon.
      const key = row.stream_name || 'Unassigned class';
      if (!groups.has(key)) groups.set(key, {
        stream_name: row.stream_name || 'Unassigned class',
        session_names: new Set(), dates: new Map(), learners: new Map(), expected: 0, marked: 0,
      });
      const group = groups.get(key);
      const sessionName = row.session_name || 'Attendance session';
      group.session_names.add(sessionName);
      if (!group.dates.has(row.date)) group.dates.set(row.date, []);
      group.dates.get(row.date).push({ session_name: sessionName, status: row.status });
      group.expected = Math.max(group.expected, Number(row.expected_count || 0));
      group.marked = Math.max(group.marked, Number(row.marked_count || 0));
      (Array.isArray(row.unmarked_learners) ? row.unmarked_learners : []).forEach((learner) => {
        const learnerKey = learner.id || learner.admission_no || learner.learner_name;
        if (!group.learners.has(learnerKey)) group.learners.set(learnerKey, { ...learner, missing: [] });
        group.learners.get(learnerKey).missing.push({
          date: row.date,
          session_name: sessionName,
          applies_to: row.applies_to || 'all',
        });
      });
    });
    this._registerGroups = Array.from(groups.values()).map(group => ({
      ...group,
      session_names: [...group.session_names],
      dates: [...group.dates.entries()].sort((a, b) => a[0].localeCompare(b[0])),
      learners: [...group.learners.values()].sort((a, b) => String(a.learner_name).localeCompare(String(b.learner_name))),
    })).sort((a, b) => a.stream_name.localeCompare(b.stream_name));
    const affectedLearners = new Set();
    this._registerGroups.forEach(group => group.learners.forEach(learner => {
      affectedLearners.add(learner.id || learner.admission_no || learner.learner_name);
    }));
    this._registerPage = 1;
    this._registerLevelPages = {};
    el.classList.remove('d-none');
    el.innerHTML = `<strong><i class="bi bi-exclamation-triangle me-1"></i>Attendance follow-up required.</strong> ` +
      `${affectedLearners.size} distinct learner(s) across ${this._registerGroups.length} class(es) need attendance follow-up. See the dates and sessions below.`;
    if (detailsCard && detailsList && this._registerGroups.length) {
      detailsCard.classList.remove('d-none');
      this._renderRegisterGroups(detailsList);
    }
  },

  _renderRegisterGroups: function (detailsList) {
    const levelOrder = ['ECD', 'Lower Primary', 'Upper Primary', 'Junior Secondary', 'Other'];
    const levels = {};
    const levelFor = (streamName) => {
      const className = String(streamName).split(' - ')[0].trim().toUpperCase();
      if (['PLAYGROUP', 'PP1', 'PP2'].includes(className)) return 'ECD';
      if (/^GRADE [123]$/.test(className)) return 'Lower Primary';
      if (/^GRADE [456]$/.test(className)) return 'Upper Primary';
      if (/^GRADE [789]$/.test(className)) return 'Junior Secondary';
      return 'Other';
    };
    this._registerGroups.forEach(group => {
      const level = levelFor(group.stream_name);
      if (!levels[level]) levels[level] = [];
      group.learners.forEach(learner => learner.missing.forEach(item => {
        levels[level].push({
          date: item.date,
          class_name: group.stream_name,
          learner_id: learner.id,
          learner_name: learner.learner_name,
          admission_no: learner.admission_no,
          student_type: learner.student_type,
          session_name: item.session_name,
        });
      }));
    });
    const pageSize = 25;
    const tables = levelOrder.filter(level => levels[level]?.length).map(level => {
      const rows = levels[level].sort((a, b) => `${a.date}${a.class_name}${a.learner_name}${a.session_name}`.localeCompare(`${b.date}${b.class_name}${b.learner_name}${b.session_name}`));
      const combinedRows = new Map();
      rows.forEach(row => {
        const key = `${row.date}\u0000${row.class_name}\u0000${row.learner_id || row.admission_no || row.learner_name}`;
        if (!combinedRows.has(key)) combinedRows.set(key, { ...row, missing_sessions: new Set() });
        combinedRows.get(key).missing_sessions.add(row.session_name);
      });
      const perDayRows = [...combinedRows.values()].map(row => {
        const coverageKey = `${row.date}\u0000${row.class_name}`;
        const isBoarder = String(row.student_type || '').toUpperCase().includes('BOARD');
        const applicable = (this._sessionCoverage?.get(coverageKey) || []).filter(session => {
          if (session.applies_to === 'boarders_only') return isBoarder;
          if (session.applies_to === 'day_only') return !isBoarder;
          return true;
        });
        const missing = [...row.missing_sessions];
        let sessionLabel;
        if (applicable.length <= 1) sessionLabel = 'Full day';
        else if (missing.length >= applicable.length) sessionLabel = 'Full Day (Both)';
        else sessionLabel = missing.sort().join(', ');
        return { ...row, session_name: sessionLabel };
      }).sort((a, b) => `${a.date}${a.class_name}${a.learner_name}`.localeCompare(`${b.date}${b.class_name}${b.learner_name}`));
      const runs = new Map();
      perDayRows.forEach(row => {
        const key = `${row.class_name}\u0000${row.learner_id || row.admission_no || row.learner_name}\u0000${row.session_name}`;
        if (!runs.has(key)) runs.set(key, []);
        runs.get(key).push(row);
      });
      const displayRows = [];
      runs.forEach(dates => {
        dates.sort((a, b) => a.date.localeCompare(b.date));
        let range = null;
        dates.forEach(row => {
          const previous = range?.end ? new Date(`${range.end}T00:00:00Z`) : null;
          const current = new Date(`${row.date}T00:00:00Z`);
          const isNextCalendarDay = previous && (current - previous) === 86400000;
          if (!range || !isNextCalendarDay) {
            if (range) displayRows.push(range);
            range = { ...row, date_label: row.date, end: row.date };
          } else {
            range.end = row.date;
            range.date_label = `${range.date} – ${row.date}`;
          }
        });
        if (range) displayRows.push(range);
      });
      displayRows.sort((a, b) => `${a.date}${a.class_name}${a.learner_name}`.localeCompare(`${b.date}${b.class_name}${b.learner_name}`));
      const distinctLearners = new Set(displayRows.map(row => row.learner_id || row.admission_no || row.learner_name));
      const page = this._registerLevelPages?.[level] || 1;
      const totalPages = Math.max(1, Math.ceil(displayRows.length / pageSize));
      const currentPage = Math.min(Math.max(1, page), totalPages);
      const start = (currentPage - 1) * pageSize;
      const visibleRows = displayRows.slice(start, start + pageSize);
      const body = visibleRows.map(row => `<tr>
        <td>${this._esc(row.date_label || row.date)}</td>
        <td><strong>${this._esc(row.class_name)}</strong></td>
        <td>${this._esc(row.learner_name)} <span class="text-muted small">(${this._esc(row.admission_no || 'No admission number')})</span></td>
        <td>${this._esc(row.session_name)}</td>
      </tr>`).join('');
      const pager = totalPages > 1 ? `<div class="d-flex justify-content-between align-items-center px-3 py-2 border-top small">
        <span>Showing attendance exceptions ${start + 1}–${Math.min(start + pageSize, displayRows.length)} of ${displayRows.length}</span>
        <span class="btn-group btn-group-sm"><button class="btn btn-outline-secondary" type="button" onclick="attendanceReportsController.registerLevelPage(${JSON.stringify(level)}, ${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>Previous</button><span class="btn btn-outline-secondary disabled">${currentPage}/${totalPages}</span><button class="btn btn-outline-secondary" type="button" onclick="attendanceReportsController.registerLevelPage(${JSON.stringify(level)}, ${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>Next</button></span>
      </div>` : '';
      return `<section class="mb-4"><div class="px-3 py-2 bg-light border-bottom"><strong>${this._esc(level)}</strong><span class="badge text-bg-danger ms-2">${distinctLearners.size} learners need follow-up</span></div>
        <div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Date</th><th>Class</th><th>Learner</th><th>Missing session(s)</th></tr></thead><tbody>${body}</tbody></table></div>${pager}</section>`;
    }).join('');
    detailsList.innerHTML = tables || '<div class="p-3 text-muted">No learner-level exception rows are available.</div>';
  },

  registerPage: function (page) {
    const detailsList = document.getElementById('arRegisterDetailsList');
    if (!detailsList) return;
    this._registerPage = page;
    this._renderRegisterGroups(detailsList);
  },

  registerLevelPage: function (level, page) {
    const detailsList = document.getElementById('arRegisterDetailsList');
    if (!detailsList) return;
    this._registerLevelPages = this._registerLevelPages || {};
    this._registerLevelPages[level] = page;
    this._renderRegisterGroups(detailsList);
  },

  _loadClassBreakdown: async function (summaryPromise) {
    const tbody = document.getElementById('arClassTableBody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';
    try {
      const d = await summaryPromise;
      const byClass = Array.isArray(d?.by_class) ? d.by_class
                    : [];

      if (!byClass.length) {
        // Fallback: try fetching class list and mark individual
        await this._loadClassBreakdownFallback(params, tbody);
        return;
      }

      this._classData = byClass;
      this._renderClassTable(tbody, byClass);
    } catch (e) {
      console.warn('Class breakdown failed:', e);
      await this._loadClassBreakdownFallback({}, tbody);
    }
  },

  _loadClassBreakdownFallback: async function (params, tbody) {
    try {
      const classRes = await callAPI('/academic/classes/list?status=active', 'GET');
      const classes  = Array.isArray(classRes?.data) ? classRes.data : (Array.isArray(classRes) ? classRes : []);
      this._classData = classes.map(c => ({ ...c, class_name: c.name }));
      this._renderClassTable(tbody, this._classData);
    } catch (e) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-danger text-center py-4">Failed to load attendance data.</td></tr>';
    }
  },

  _renderClassTable: function (tbody, data) {
    if (!data.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No data found for the selected period.</td></tr>';
      return;
    }
    tbody.innerHTML = data.map(c => {
      const rate    = c.attendance_rate != null ? parseFloat(c.attendance_rate) : null;
      const rateBadge = rate == null ? '—'
        : `<span class="badge bg-${rate >= 90 ? 'success' : rate >= 75 ? 'warning' : 'danger'}">${rate.toFixed(1)}%</span>`;
      return `<tr>
        <td><strong>${this._esc(c.class_name || c.name)}</strong></td>
        <td class="text-center">${c.total_enrolled ?? c.student_count ?? '—'}</td>
        <td class="text-center text-success fw-semibold">${c.present_today ?? '—'}</td>
        <td class="text-center text-danger">${c.absent_today ?? '—'}</td>
        <td class="text-center">${rateBadge}</td>
        <td>
          <a href="${window.APP_BASE || ''}/home.php?route=view_attendance&class_id=${c.id}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-eye"></i> View
          </a>
        </td>
      </tr>`;
    }).join('');
  },

  _loadChronic: async function () {
    const tbody = document.getElementById('arChronicTableBody');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';
    try {
      const params = { ...this._getDateRange(), threshold: 80 };
      const classId = document.getElementById('arClass')?.value;
      if (classId) params.class_id = classId;

      const r = await callAPI('/attendance/chronic-student-absentees?' + new URLSearchParams(params).toString(), 'GET');
      this._chronicData = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);

      const stat = document.getElementById('arStatChronic');
      if (stat) stat.textContent = this._chronicData.length;

      tbody.innerHTML = this._chronicData.length
        ? this._chronicData.map(s => {
            const rate = parseFloat(s.attendance_rate ?? s.percentage ?? 0);
            return `<tr>
              <td><strong>${this._esc(s.student_name || (s.first_name + ' ' + s.last_name))}</strong></td>
              <td>${this._esc(s.admission_no || '—')}</td>
              <td>${this._esc(s.class_name || '—')}</td>
              <td class="text-center text-success">${s.days_present ?? '—'}</td>
              <td class="text-center text-danger">${s.days_absent ?? '—'}</td>
              <td class="text-center">
                <span class="badge bg-${rate >= 80 ? 'warning' : 'danger'}">${rate.toFixed(1)}%</span>
              </td>
              <td>
                <span class="badge bg-${rate < 50 ? 'danger' : 'warning'}">${rate < 50 ? 'Critical' : 'At Risk'}</span>
              </td>
            </tr>`;
          }).join('')
        : '<tr><td colspan="7" class="text-center text-muted py-4">No chronic absentees found for this period.</td></tr>';
    } catch (e) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-danger text-center py-4">Failed to load.</td></tr>';
    }
  },

  _renderTrendsChart: async function () {
    const canvas = document.getElementById('arTrendsChart');
    if (!canvas) return;
    if (this._trendsChart) { this._trendsChart.destroy(); this._trendsChart = null; }

    try {
      const r = await callAPI('/reports/attendance-rates', 'GET');
      const labels  = r?.data?.labels  ?? r?.labels  ?? [];
      const present = r?.data?.present ?? r?.present ?? [];
      const absent  = r?.data?.absent  ?? r?.absent  ?? [];

      this._trendsChart = new Chart(canvas, {
        type: 'line',
        data: {
          labels,
          datasets: [
            { label: 'Present %', data: present, borderColor: '#198754', backgroundColor: 'rgba(25,135,84,0.1)', fill: true, tension: 0.3 },
            { label: 'Absent %',  data: absent,  borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,0.1)',  fill: true, tension: 0.3 },
          ],
        },
        options: {
          responsive: true,
          plugins: { legend: { position: 'top' } },
          scales: { y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } },
        },
      });
    } catch (e) { console.warn('Trends chart failed:', e); }
  },

  _populateClassFilter: async function () {
    const sel = document.getElementById('arClass');
    if (!sel) return;
    try {
      const r = await callAPI('/academic/classes/list?status=active', 'GET');
      const classes = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      classes.forEach(c => sel.add(new Option(c.name, c.id)));
    } catch (e) { /* optional */ }
  },

  exportCSV: function () {
    if (!this._classData.length) { showNotification('Generate a report first', 'warning'); return; }
    const rows = [['Class','Learners','With present record','With absent record','Selected-period rate']];
    this._classData.forEach(c => rows.push([
      c.class_name || c.name || '', c.total_enrolled || '', c.present_today || '', c.absent_today || '',
      c.attendance_rate != null ? c.attendance_rate + '%' : '',
    ]));
    const csv  = rows.map(r => r.map(v => '"' + String(v).replace(/"/g, '""') + '"').join(',')).join('\n');
    KingswayFileLifecycle.exportText(csv, 'attendance_report_' + new Date().toISOString().slice(0, 10) + '.csv', 'text/csv');
  },

  _esc: function (str) {
    const d = document.createElement('div');
    d.textContent = String(str ?? '');
    return d.innerHTML;
  },
};

document.addEventListener('DOMContentLoaded', () => attendanceReportsController.init());

window.attendanceReportsController = attendanceReportsController;
