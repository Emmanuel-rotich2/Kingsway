/**
 * js/pages/parents/applications.js — Admission applications tracker.
 *
 * Lists every application submitted by the signed-in parent together with its
 * current workflow stage and progress along the admissions path. Provides the
 * mandatory CSV export and print actions for the data shown on this page.
 */
(function () {
  'use strict';
  var P = window.ParentCommon;

  var STATUS_BADGES = {
    enrolled: 'bg-success',
    submitted: 'bg-info',
    documents_pending: 'bg-warning text-dark',
    documents_verified: 'bg-primary',
    placement_offered: 'bg-primary',
    fees_pending: 'bg-warning text-dark',
    waitlisted: 'bg-warning text-dark',
    placement_test_required: 'bg-warning text-dark',
    cancelled: 'bg-danger',
  };

  function badgeFor(application) {
    return STATUS_BADGES[application.status] || 'bg-secondary';
  }

  function fmtDate(value) {
    var d = String(value || '');
    return d.length >= 10 ? d.substring(0, 10) : d;
  }

  function renderStepper(application) {
    var stages = application.stages || [];
    if (!stages.length) return '';
    return '<div class="pp-app-stepper d-flex align-items-center gap-1 overflow-auto py-2">' +
      stages.map(function (s, i) {
        var cls = 'pp-app-dot';
        if (s.state === 'done') cls += ' done';
        else if (s.state === 'current') cls += ' current';
        var icon = s.state === 'done' ? '<i class="bi bi-check-lg"></i>' : (s.state === 'current' ? '<i class="bi bi-record-circle"></i>' : '');
        return '<div class="pp-app-stage flex-shrink-0 d-flex align-items-center" title="' + P.esc(s.name) + '">' +
          '<span class="' + cls + '">' + icon + '</span>' +
          '<small class="text-muted ms-1">' + P.esc(s.name) + '</small>' +
          (i < stages.length - 1 ? '<span class="pp-app-connector mx-1"></span>' : '') +
          '</div>';
      }).join('') + '</div>';
  }

  function renderApplicationCard(application) {
    var meta = [];
    if (application.grade_applying_for) meta.push('<span><i class="bi bi-mortarboard me-1 text-muted"></i>' + P.esc(application.grade_applying_for) + '</span>');
    if (application.target_term) meta.push('<span><i class="bi bi-calendar-event me-1 text-muted"></i>' + P.esc(application.target_term) + '</span>');
    if (application.gender) meta.push('<span><i class="bi bi-person me-1 text-muted"></i>' + P.esc(application.gender) + '</span>');
    var lastAction = (application.current_stage_name || '') ? 'Step ' + application.current_step + ' of ' + application.total_steps + ' — ' + P.esc(application.current_stage_name) : 'Received — under review';
    var activity = application.last_action
      ? '<div class="small text-muted mt-2"><i class="bi bi-clock-history me-1"></i>Last update: ' + P.esc(application.last_action.replace(/_/g, ' ')) + (application.last_action_at ? ' · ' + fmtDate(application.last_action_at) : '') + '</div>'
      : '';
    return '<div class="card border-0 shadow-sm rounded-4 mb-3">' +
      '<div class="card-body p-4">' +
      '<div class="d-flex justify-content-between flex-wrap gap-2 mb-2">' +
      '<div><h5 class="fw-bold mb-0">' + P.esc(application.applicant_name) + '</h5>' +
      '<small class="text-muted">Ref: ' + P.esc(application.application_no) + ' · Applied ' + fmtDate(application.created_at) + '</small></div>' +
      '<span class="badge ' + badgeFor(application) + ' align-self-start px-3 py-2">' + P.esc(application.status_label || application.status) + '</span>' +
      '</div>' +
      '<div class="d-flex flex-wrap gap-3 small text-muted mb-3">' + meta.join('') + '</div>' +
      '<div class="d-flex justify-content-between align-items-center mb-1">' +
      '<strong class="small text-success">' + P.esc(lastAction) + '</strong>' +
      '<span class="badge bg-success px-3 py-2">' + (application.progress_percent || 0) + '%</span>' +
      '</div>' +
      '<div class="progress mb-2" style="height:8px">' +
      '<div class="progress-bar bg-success" role="progressbar" style="width:' + (application.progress_percent || 0) + '%" aria-valuenow="' + (application.progress_percent || 0) + '" aria-valuemin="0" aria-valuemax="100"></div>' +
      '</div>' +
      renderStepper(application) +
      activity +
      '</div></div>';
  }

  function renderPage(data) {
    var list = document.getElementById('ppApplicationsList');
    var kpiEl = document.getElementById('ppAppKpis');
    var apps = data.applications || [];
    window.__ppApplications = apps;

    if (kpiEl) {
      var inProgress = apps.filter(function (a) { return a.status !== 'enrolled' && a.status !== 'cancelled'; }).length;
      var enrolled = apps.filter(function (a) { return a.status === 'enrolled'; }).length;
      var awaiting = apps.filter(function (a) { return a.status === 'fees_pending' || a.status === 'placement_offered'; }).length;
      var kpis = [
        ['bi-clipboard2-check', 'Total applications', apps.length, 'primary'],
        ['bi-hourglass-split', 'In progress', inProgress, 'warning'],
        ['bi-person-check', 'Enrolled', enrolled, 'success'],
        ['bi-cash-stack', 'Awaiting payment', awaiting, 'info'],
      ];
      kpiEl.innerHTML = kpis.map(function (k) {
        return '<div class="col-6 col-xl-3"><div class="pp-kpi-card bg-' + k[3] + '-subtle text-' + k[3] + '">' +
          '<div class="kpi-icon bg-' + k[3] + '-subtle text-' + k[3] + '"><i class="bi ' + k[0] + '"></i></div>' +
          '<div><div class="small text-muted">' + k[1] + '</div><div class="fw-bold">' + k[2] + '</div></div></div></div>';
      }).join('');
    }

    if (!list) return;
    if (!apps.length) {
      list.innerHTML =
        '<div class="card border-0 shadow-sm rounded-4">' +
        '<div class="card-body text-center p-5">' +
        '<i class="bi bi-clipboard2-plus display-4 text-success"></i>' +
        '<h5 class="fw-bold mt-3">No admission applications yet</h5>' +
        '<p class="text-muted mb-3">Applications you submit will appear here with live progress through the admissions journey.</p>' +
        '<button class="btn btn-success rounded-pill px-4" type="button" id="btnEmptyApply"><i class="bi bi-person-plus me-2"></i>Apply for Admission</button>' +
        '</div></div>';
      var applyBtn = document.getElementById('btnEmptyApply');
      if (applyBtn) applyBtn.addEventListener('click', function () { P.openApplyAdmissionModal(); });
      return;
    }
    list.innerHTML = apps.map(renderApplicationCard).join('');
  }

  function buildCsv(applications) {
    var headers = ['Application No', 'Applicant Name', 'Grade', 'Status', 'Current Stage', 'Progress %', 'Preferred Term', 'Date Applied', 'Last Activity'];
    var rows = applications.map(function (a) {
      return [
        a.application_no,
        a.applicant_name,
        a.grade_applying_for,
        a.status_label || a.status,
        a.current_stage_name || '',
        String(a.progress_percent || 0),
        a.target_term || '',
        fmtDate(a.created_at),
        a.last_action ? a.last_action.replace(/_/g, ' ') + (a.last_action_at ? ' (' + fmtDate(a.last_action_at) + ')' : '') : '',
      ];
    });
    var csv = P.csvFromHeaders(headers, rows);
    P.exportCsv('kingsway_admission_applications.csv', csv);
  }

  function init() {
    P.apiFetch('/admission-applications', 'GET')
      .then(function (resp) {
        var d = resp.data !== undefined ? resp.data : resp;
        renderPage(d);
      })
      .catch(function (err) {
        var list = document.getElementById('ppApplicationsList');
        if (list) P.showError(list, err.message || 'Failed to load applications.');
      });
  }

  if (!(P && typeof P.ensureAuth === 'function')) return;

  (async function run() {
    if (!(await P.ensureAuth())) return;
    await P.loadDashboard();
    init();
  })();

  document.addEventListener('click', function (e) {
    var exportBtn = e.target.closest('#btnExportApplications');
    if (exportBtn) {
      var apps = window.__ppApplications || [];
      if (apps.length) buildCsv(apps);
    }
    var printBtn = e.target.closest('#btnPrintApplications');
    if (printBtn) P.printSection();
  });
})();