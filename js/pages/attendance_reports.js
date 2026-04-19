/**
 * Attendance Reports Controller
 * Provides attendance statistics by class, chronic absentees, and trends.
 * API: /api/attendance/*  and  /api/reports/*
 */

const attendanceReportsController = {

  _classData:   [],
  _chronicData: [],
  _trendsChart: null,

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    this._bindTabs();
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

    await Promise.all([
      this._loadSummary(params),
      this._loadClassBreakdown(params),
    ]);
  },

  _loadSummary: async function (params) {
    try {
      const r = await callAPI('/attendance/academic-summary?' + new URLSearchParams(params).toString(), 'GET');
      const d = r?.data ?? r ?? {};
      const setEl = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
      setEl('arStatTotal',   d.total_enrolled    ?? '—');
      setEl('arStatRate',    d.attendance_rate != null ? d.attendance_rate + '%' : '—');
      setEl('arStatAbsent',  d.absent_today      ?? '—');
      setEl('arStatChronic', d.chronic_count     ?? '—');
    } catch (e) { console.warn('Summary load failed:', e); }
  },

  _loadClassBreakdown: async function (params) {
    const tbody = document.getElementById('arClassTableBody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';
    try {
      const qs = new URLSearchParams(params).toString();
      const r  = await callAPI('/attendance/academic-summary?' + qs, 'GET');
      const byClass = Array.isArray(r?.data?.by_class) ? r.data.by_class
                    : Array.isArray(r?.by_class)        ? r.by_class
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
      await this._loadClassBreakdownFallback(params, tbody);
    }
  },

  _loadClassBreakdownFallback: async function (params, tbody) {
    try {
      const classRes = await callAPI('/academic/classes?status=active', 'GET');
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
      const r = await callAPI('/academic/classes?status=active', 'GET');
      const classes = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      classes.forEach(c => sel.add(new Option(c.name, c.id)));
    } catch (e) { /* optional */ }
  },

  exportCSV: function () {
    if (!this._classData.length) { showNotification('Generate a report first', 'warning'); return; }
    const rows = [['Class','Enrolled','Present Today','Absent Today','Attendance Rate']];
    this._classData.forEach(c => rows.push([
      c.class_name || c.name || '', c.total_enrolled || '', c.present_today || '', c.absent_today || '',
      c.attendance_rate != null ? c.attendance_rate + '%' : '',
    ]));
    const csv  = rows.map(r => r.map(v => '"' + String(v).replace(/"/g, '""') + '"').join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const a    = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'attendance_report_' + new Date().toISOString().slice(0, 10) + '.csv';
    a.click();
  },

  _esc: function (str) {
    const d = document.createElement('div');
    d.textContent = String(str ?? '');
    return d.innerHTML;
  },
};

document.addEventListener('DOMContentLoaded', () => attendanceReportsController.init());
