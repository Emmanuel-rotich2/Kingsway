/**
 * js/pages/parents/health.js — Health & Welfare (aggregate summary only;
 * never raw health records).
 */
(function () {
  'use strict';
  var P = window.ParentCommon;

  function section(label, value, tone) {
    return '<div class="col-md-4 mb-3"><div class="pp-kpi-card bg-' + (tone || 'success') + '-subtle text-' + (tone || 'success') + ' h-100"><div><small class="text-muted">' + label + '</small><div class="fw-bold fs-5">' + value + '</div></div></div></div>';
  }

  function renderHealth(d, el) {
    var s = d.summary || {};
    var emerg = !!d.emergency_flags;
    var html = '';
    if (emerg) {
      html += '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i><strong>Health attention flagged.</strong> This learner has one or more active health flags. The school nurse is aware and follows the authorized care plan.</div>';
    }
    html += '<div class="row g-2">' +
      section('Health records on file', s.health_records || 0, 'info') +
      section('Active allergies', s.active_allergies || 0, 'danger') +
      section('Active conditions', s.active_conditions || 0, 'warning') +
      section('Active medications', s.active_medications || 0, 'info') +
      section('Emergency flags', emerg ? 'Yes' : 'None', emerg ? 'danger' : 'success') +
      '</div>';
    html += '<div class="alert alert-success small mt-3"><i class="bi bi-shield-check me-2"></i>Health details are managed by the school nurse and are only shared in the summarized form above for your peace of mind. For urgent concerns, contact the school office as listed on the Contact page.</div>';
    el.innerHTML = html;
  }

  P.childPage({
    contentId: 'ppHealthContent',
    defaultTab: 'health',
    loadTab: function (tab, child, el) {
      P.apiFetch('/student-health/' + child.id, 'GET')
        .then(function (resp) { renderHealth(resp.data !== undefined ? resp.data : resp, el); })
        .catch(function (e) { P.showError(el, e.message); });
    },
  });

  document.getElementById('btnHealthPrint').addEventListener('click', function () { P.printSection(); });
})();