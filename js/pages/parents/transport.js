/**
 * js/pages/parents/transport.js — School transport view.
 *
 * Parents who already have an active transport entitlement see a "Pay
 * Transport" button that opens the payment modal (provider is a system
 * decision: Daraja). Learners who are not subscribed yet get a "Subscribe
 * Transport" request that the school transport office acts on.
 */
(function () {
  'use strict';
  var P = window.ParentCommon;

  function routeCard(data) {
    return '<div class="pp-card bg-success-subtle border-0 h-100"><div class="pp-card-body">' +
      '<span class="badge bg-success mb-2">' + P.esc(data.status || 'active') + '</span>' +
      '<h4 class="fw-bold">' + P.esc(data.route_name) + '</h4>' +
      '<div class="row g-3 mt-1">' +
      '<div class="col-6"><small class="text-muted d-block">Pickup point</small><strong>' + P.esc(data.pickup_stop || 'Not set') + '</strong><div class="small">' + P.esc(data.pickup_time || '—') + '</div></div>' +
      '<div class="col-6"><small class="text-muted d-block">Drop-off point</small><strong>' + P.esc(data.dropoff_stop || 'Not set') + '</strong><div class="small">' + P.esc(data.dropoff_time || '—') + '</div></div>' +
      '</div>' +
      '<hr>' +
      '<p class="mb-0 small"><i class="bi bi-bus-front me-2 text-success"></i>' + P.esc(data.registration_number || 'Vehicle pending') +
      ' &nbsp; <i class="bi bi-person-badge ms-2 me-2 text-success"></i>' + P.esc(data.driver_name || 'Driver pending') + '</p>' +
      '</div></div>';
  }

  function emptyRouteCard() {
    return '<div class="pp-card bg-light border-0 h-100"><div class="pp-card-body text-center py-5">' +
      '<i class="bi bi-bus-front display-4 text-muted"></i>' +
      '<h6 class="fw-bold mt-3">No transport assignment yet</h6>' +
      '<p class="small text-muted mb-0">The subscription request below lets the school office set up transport for this learner.</p>' +
      '</div></div>';
  }

  function actionCard(data) {
    if (data && data.subscribed) {
      var due = parseFloat(data.default_amount_due || 0);
      return '<div class="pp-card border h-100"><div class="pp-card-body text-center py-4">' +
        '<i class="bi bi-cash-coin display-5 text-success"></i>' +
        '<h6 class="fw-bold mt-2">' + (due > 0 ? 'KES ' + due.toLocaleString() + ' due' : 'Transport subscribed') + '</h6>' +
        '<p class="small text-muted mb-3">' + (due > 0 ? 'Pay the outstanding transport fee for this learner.' : 'No outstanding transport fee.') + '</p>' +
        (due > 0
          ? '<button class="btn btn-success rounded-pill px-4" type="button" id="btnPayTransport"><i class="bi bi-phone me-2"></i>Pay Transport</button>'
          : '<span class="text-success fw-semibold"><i class="bi bi-check-circle me-1"></i>All settled</span>') +
        '</div></div>';
    }
    return '<div class="pp-card border h-100"><div class="pp-card-body text-center py-4">' +
      '<i class="bi bi-sign-turn-right display-5 text-muted"></i>' +
      '<h6 class="fw-bold mt-2">Not subscribed yet</h6>' +
      '<p class="small text-muted mb-3">Request a subscription and the school transport office will arrange the route for this learner.</p>' +
      '<button class="btn btn-outline-success rounded-pill px-4" type="button" id="btnSubscribeTransport"><i class="bi bi-bus-front me-2"></i>Subscribe Transport</button>' +
      '</div></div>';
  }

  function showStatus(el, message, success) {
    if (!el) return;
    el.innerHTML = '<div class="alert ' + (success ? 'alert-success' : 'alert-danger') + ' small mt-3 mb-0"><i class="bi ' +
      (success ? 'bi-check-circle' : 'bi-exclamation-triangle') + ' me-2"></i>' + P.esc(message) + '</div>';
  }

  function wireButtons(data, child) {
    var children = [];
    try { children = JSON.parse(sessionStorage.getItem('pp_children') || '[]'); } catch (_) {}

    var payBtn = document.getElementById('btnPayTransport');
    if (payBtn) payBtn.addEventListener('click', function () {
      P.openMpesaModal({ children: children, studentId: child.id, purpose: 'transport', amount: data.default_amount_due });
    });

    var subBtn = document.getElementById('btnSubscribeTransport');
    if (subBtn) subBtn.addEventListener('click', function () {
      if (!window.confirm('Request transport subscription for ' + child.first_name + ' ' + child.last_name + '? The school office will confirm the route and charges.')) return;
      subBtn.disabled = true;
      subBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending request...';
      P.requestTransportSubscription(child.id)
        .then(function () {
          subBtn.disabled = false;
          subBtn.textContent = 'Subscribe Transport';
          var el = document.getElementById('ppTransportContent');
          showStatus(el, 'Subscription request sent. The school transport office will contact you.', true);
        })
        .catch(function (e) {
          subBtn.disabled = false;
          subBtn.textContent = 'Subscribe Transport';
          var el = document.getElementById('ppTransportContent');
          showStatus(el, e.message || 'Could not send the request. Please try again.', false);
        });
    });
  }

  function renderTransport(data, child, el) {
    var hasAssignment = !!(data && data.route_name);
    el.innerHTML = '<div class="row g-3">' +
      '<div class="col-lg-7">' + (hasAssignment ? routeCard(data) : emptyRouteCard()) + '</div>' +
      '<div class="col-lg-5">' + actionCard(data || {}) + '</div>' +
      '</div>';
    wireButtons(data || {}, child);
  }

  P.childPage({
    contentId: 'ppTransportContent',
    defaultTab: 'transport',
    loadTab: function (tab, child, el) {
      P.apiFetch('/student-transport/' + child.id, 'GET')
        .then(function (r) { renderTransport(r.data !== undefined ? r.data : r, child, el); })
        .catch(function (e) { P.showError(el, e.message); });
    },
  });
})();