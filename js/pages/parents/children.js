/**
 * js/pages/parents/children.js — All children listing.
 */
(function () {
  'use strict';
  var P = window.ParentCommon;

  async function init() {
    if (!(await P.ensureAuth())) return;
    var d = await P.loadDashboard();
    var children = d.children || [];
    var countEl = document.getElementById('ppChildCount');
    if (countEl) countEl.textContent = children.length + ' linked';
    var el = document.getElementById('ppChildrenCards');
    if (!el) return;
    if (!children.length) {
      el.innerHTML = '<div class="col-12"><div class="alert alert-info text-center">No children linked to this account. Contact the school office.</div></div>';
      return;
    }
    el.innerHTML = children.map(function (c) {
      var bal = parseFloat(c.current_balance || 0);
      var bc = bal <= 0 ? 'success' : (bal < 5000 ? 'warning' : 'danger');
      var bt = bal <= 0 ? 'Fees Cleared' : 'KES ' + bal.toLocaleString() + ' Due';
      return '<div class="col-md-6 col-lg-4 mb-3">' +
        '<div class="card border-0 shadow-sm rounded-4 child-card h-100">' +
        '<div class="card-body p-4">' +
        '<div class="d-flex align-items-center mb-3">' +
        '<div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center me-3" style="width:48px;height:48px;font-size:1.4rem;font-weight:700">' +
        P.esc((c.first_name || '?')[0].toUpperCase()) + '</div>' +
        '<div><h6 class="mb-0 fw-bold">' + P.esc(c.first_name + ' ' + c.last_name) + '</h6>' +
        '<small class="text-muted">' + P.esc(c.class_name || '') + ' · ' + P.esc(c.admission_no || '') + '</small></div></div>' +
        '<div class="d-flex justify-content-between align-items-center mb-2">' +
        '<span class="text-muted small">Current balance</span>' +
        '<span class="badge bg-' + bc + ' px-3 py-2">' + bt + '</span></div>' +
        '<div class="d-flex flex-wrap gap-2">' +
        '<a href="' + P.childUrl('results', c.id) + '" class="btn btn-sm btn-outline-success rounded-pill"><i class="bi bi-mortarboard me-1"></i>Results</a>' +
        '<a href="' + P.childUrl('fees', c.id) + '" class="btn btn-sm btn-outline-success rounded-pill"><i class="bi bi-receipt me-1"></i>Fees</a>' +
        '<a href="' + P.childUrl('attendance', c.id) + '" class="btn btn-sm btn-outline-success rounded-pill"><i class="bi bi-calendar-check me-1"></i>Attendance</a>' +
        '</div>' +
        '</div></div>';
    }).join('');
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();