/**
 * js/pages/parents/transport.js — School transport view.
 */
(function () {
  'use strict';
  var P = window.ParentCommon;

  function renderTransport(data, el) {
    if (!data || !data.route_name) {
      el.innerHTML = '<div class="alert alert-info">This learner has no active transport assignment.</div>';
      return;
    }
    el.innerHTML = '<div class="row g-3">' +
      '<div class="col-md-7"><div class="pp-card bg-success-subtle border-0 h-100"><div class="pp-card-body">' +
      '<span class="badge bg-success mb-2">' + P.esc(data.status || 'active') + '</span>' +
      '<h4 class="fw-bold">' + P.esc(data.route_name) + '</h4>' +
      '<div class="row g-3 mt-1">' +
      '<div class="col-6"><small class="text-muted d-block">Pickup point</small><strong>' + P.esc(data.pickup_stop || 'Not set') + '</strong><div class="small">' + P.esc(data.pickup_time || '—') + '</div></div>' +
      '<div class="col-6"><small class="text-muted d-block">Drop-off point</small><strong>' + P.esc(data.dropoff_stop || 'Not set') + '</strong><div class="small">' + P.esc(data.dropoff_time || '—') + '</div></div>' +
      '</div></div></div></div>' +
      '<div class="col-md-5"><div class="pp-card border h-100"><div class="pp-card-body">' +
      '<h6 class="fw-bold">Vehicle and driver</h6>' +
      '<p class="mb-2"><i class="bi bi-bus-front me-2 text-success"></i>' + P.esc(data.registration_number || 'Vehicle pending') + '</p>' +
      '<p class="mb-0"><i class="bi bi-person-badge me-2 text-success"></i>' + P.esc(data.driver_name || 'Driver pending') + '</p>' +
      '</div></div></div></div>';
  }

  P.childPage({
    contentId: 'ppTransportContent',
    defaultTab: 'transport',
    loadTab: function (tab, child, el) {
      P.apiFetch('/student-transport/' + child.id, 'GET')
        .then(function (r) { renderTransport(r.data !== undefined ? r.data : r, el); })
        .catch(function (e) { P.showError(el, e.message); });
    },
  });
})();