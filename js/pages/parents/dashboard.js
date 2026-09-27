/**
 * js/pages/parents/dashboard.js — Family dashboard.
 */
(function () {
  'use strict';
  var P = window.ParentCommon;

  function childApplication(applications, id) {
    return (applications || []).find(function (a) { return String(a.enrolled_student_id || '') === String(id || ''); }) || null;
  }

  function renderChildrenCards(children, applications) {
    var el = document.getElementById('ppChildrenCards');
    if (!el) return;
    if (!children.length) {
      el.innerHTML = '<div class="col-12"><div class="alert alert-info text-center">No children linked to this account. Contact the school office.</div></div>';
      return;
    }
    el.innerHTML = children.map(function (c) {
      var application = childApplication(applications, c.id);
      var registrationDue = application ? parseFloat(application.registration_fee_due || 0) : 0;
      var bal = parseFloat(c.current_balance || 0) + registrationDue;
      var bc = bal <= 0 ? 'success' : (bal < 5000 ? 'warning' : 'danger');
      var bt = bal <= 0 ? 'Fees Cleared' : 'KES ' + bal.toLocaleString() + ' Due';
      return '<div class="col-md-6 col-lg-4 mb-3">' +
        '<a class="card border-0 shadow-sm rounded-4 child-card h-100 text-decoration-none text-dark" href="' + P.childUrl('fees', c.id) + '">' +
        '<div class="card-body p-4">' +
        '<div class="d-flex align-items-center mb-3">' +
        '<div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;font-size:1.4rem;font-weight:700">' +
        P.esc((c.first_name || '?')[0].toUpperCase()) + '</div>' +
        '<div><h6 class="mb-0 fw-bold">' + P.esc(c.first_name + ' ' + c.last_name) + '</h6>' +
        '<small class="text-muted">' + P.esc(c.class_name || '') + ' · ' + P.esc(c.admission_no || '') + '</small></div></div>' +
        '<div class="d-flex justify-content-between align-items-center">' +
        '<span class="text-muted small">' + (registrationDue > 0 ? 'Total due' : 'Current balance') + '</span>' +
        '<span class="badge bg-' + bc + ' px-3 py-2">' + bt + '</span></div>' +
        (application && application.status !== 'enrolled' ? '<div class="small mt-2"><span class="badge bg-warning-subtle text-warning-emphasis">' + P.esc(application.status_label || 'Admission in progress') + '</span><br><strong>Current:</strong> ' + P.esc(application.current_stage_name || 'Application received') + '<br><span class="text-muted"><strong>Next:</strong> ' + P.esc((application.stages || []).find(function (s) { return s.state === 'todo'; })?.name || 'Awaiting finalisation') + '</span></div>' : '') +
        '</div></a></div>';
    }).join('');
  }

  function renderKpis(children, applications) {
    var el = document.getElementById('ppKpis');
    if (!el) return;
    var total = children.reduce(function (s, c) {
      var app = childApplication(applications, c.id);
      return s + parseFloat(c.current_balance || 0) + (app ? parseFloat(app.registration_fee_due || 0) : 0);
    }, 0);
    var cleared = children.filter(function (c) { return parseFloat(c.current_balance || 0) <= 0; }).length;
    var items = [
      ['bi-people-fill', 'Children linked', children.length, 'primary'],
      ['bi-wallet2', 'Family fee balance', 'KES ' + total.toLocaleString(), 'warning'],
      ['bi-check-circle-fill', 'Accounts cleared', cleared + ' of ' + children.length, 'success'],
      ['bi-chat-dots-fill', 'School connection', 'Open', 'info'],
    ];
    el.innerHTML = items.map(function (k) {
      return '<div class="col-6 col-xl-3">' +
        '<div class="pp-kpi-card bg-' + k[3] + '-subtle text-' + k[3] + '">' +
        '<div class="kpi-icon bg-' + k[3] + '-subtle text-' + k[3] + '"><i class="bi ' + k[0] + '"></i></div>' +
        '<div><div class="small text-muted">' + k[1] + '</div><div class="fw-bold">' + k[2] + '</div></div></div></div>';
    }).join('');
  }

  async function init() {
    if (!(await P.ensureAuth())) return;
    var d = await P.loadDashboard();
    var children = d.children || [];
    var applications = [];
    try {
      var response = await P.apiFetch('/admission-applications', 'GET');
      var data = response && response.data !== undefined ? response.data : response;
      applications = data && data.applications ? data.applications : [];
    } catch (_) { /* dashboard remains usable */ }
    renderKpis(children, applications);
    renderChildrenCards(children, applications);
    P.storeGuardian(d.parent || {});
    var heading = document.getElementById('ppChildrenHeading');
    if (heading) heading.textContent = 'My children';
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
