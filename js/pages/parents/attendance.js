/**
 * js/pages/parents/attendance.js — Attendance view.
 */
(function () {
  'use strict';
  var P = window.ParentCommon;

  function renderAttendance(data, el) {
    var summary = data.summary || {};
    var recent = data.recent || [];
    var monthly = data.monthly || [];
    var pct = data.percentage || 0;
    var total = parseInt(summary.total_days || 0);
    var present = parseInt(summary.days_present || 0);
    var absent = parseInt(summary.days_absent || 0);
    var late = parseInt(summary.days_late || 0);
    var html = '<div class="row g-3 mb-4">' +
      '<div class="col-md-3"><div class="pp-kpi-card bg-success-subtle text-success"><div><div class="small text-muted">Attendance</div><div class="fw-bold fs-5">' + pct + '%</div></div></div></div>' +
      '<div class="col-md-3"><div class="pp-kpi-card bg-primary-subtle text-primary"><div><div class="small text-muted">Present</div><div class="fw-bold fs-5">' + present + '</div></div></div></div>' +
      '<div class="col-md-3"><div class="pp-kpi-card bg-danger-subtle text-danger"><div><div class="small text-muted">Absent</div><div class="fw-bold fs-5">' + absent + '</div></div></div></div>' +
      '<div class="col-md-3"><div class="pp-kpi-card bg-warning-subtle text-warning"><div><div class="small text-muted">Late</div><div class="fw-bold fs-5">' + late + '</div></div></div></div></div>';
    if (monthly.length) {
      html += '<h6 class="text-muted mb-2">Monthly Breakdown</h6><div class="table-responsive mb-4"><table class="pp-table table-sm"><thead><tr><th>Month</th><th>Total</th><th>Present</th><th>Absent</th><th>Late</th><th>%</th></tr></thead><tbody>';
      monthly.forEach(function (m) {
        var mpct = m.total_days > 0 ? Math.round(100 * m.days_present / m.total_days) : 0;
        html += '<tr><td>' + P.esc(m.month) + '</td><td>' + m.total_days + '</td><td>' + m.days_present + '</td><td>' + m.days_absent + '</td><td>' + m.days_late + '</td><td><span class="badge bg-' + (mpct >= 90 ? 'success' : (mpct >= 75 ? 'warning' : 'danger')) + '">' + mpct + '%</span></td></tr>';
      });
      html += '</tbody></table></div>';
    }
    if (recent.length) {
      html += '<h6 class="text-muted mb-2">Recent Activity</h6><div class="table-responsive"><table class="pp-table table-sm"><thead><tr><th>Date</th><th>Status</th><th>Reason</th></tr></thead><tbody>';
      recent.forEach(function (r) {
        var sc = r.status === 'present' ? 'success' : (r.status === 'late' ? 'warning' : 'danger');
        html += '<tr><td>' + (r.date || '').substring(0, 10) + '</td><td><span class="badge bg-' + sc + '">' + P.esc(r.status || '') + '</span></td><td>' + P.esc(r.absence_reason || '-') + '</td></tr>';
      });
      html += '</tbody></table></div>';
    }
    if (!total) html = '<div class="alert alert-info">No attendance records found for the current term.</div>';
    el.innerHTML = html;
  }

  P.childPage({
    contentId: 'ppAttendanceContent',
    defaultTab: 'attendance',
    loadTab: function (tab, child, el) {
      P.apiFetch('/student-attendance/' + child.id, 'GET')
        .then(function (r) { renderAttendance(r.data !== undefined ? r.data : r, el); })
        .catch(function (e) { P.showError(el, e.message); });
    },
  });
})();