/**
 * js/pages/parents/activities.js — Clubs & Activities.
 */
(function () {
  'use strict';
  var P = window.ParentCommon;
  var _activities = [];

  function renderActivities(data, el) {
    _activities = data.activities || [];
    if (!_activities.length) {
      el.innerHTML = '<div class="alert alert-info">This learner has not yet joined any club or activity. Contact the school office to explore co-curricular options.</div>';
      return;
    }
    var html = _activities.map(function (a) {
      var cat = a.category_name ? '<span class="badge bg-success-subtle text-success">' + P.esc(a.category_name) + '</span>' : '';
      var roleBadge = '<span class="badge bg-primary-subtle text-primary">' + P.esc(a.role || 'member') + '</span>';
      var statusBadge = '<span class="badge bg-' + (a.participant_status === 'active' ? 'success' : 'secondary') + '">' + P.esc(a.participant_status || 'active') + '</span>';
      return '<div class="col-md-6 mb-3"><div class="pp-card h-100"><div class="pp-card-body">' +
        '<div class="d-flex justify-content-between align-items-start gap-2">' +
        '<h5 class="fw-bold mb-1">' + P.esc(a.title || '') + '</h5>' + cat + '</div>' +
        '<div class="small text-muted mb-2">' + roleBadge + ' ' + statusBadge +
        (a.joined_at ? ' · joined ' + P.esc((a.joined_at).substring(0, 10)) : '') + '</div>' +
        (a.description ? '<p class="small mb-2">' + P.esc(a.description) + '</p>' : '') +
        (a.activity_status ? '<small class="text-muted d-block">Activity status: ' + P.esc(a.activity_status) + '</small>' : '') +
        (a.notes ? '<small class="text-muted d-block">Notes: ' + P.esc(a.notes) + '</small>' : '') +
        '</div></div></div>';
    }).join('');
    el.innerHTML = '<div class="row g-3">' + html + '</div>';
  }

  P.childPage({
    contentId: 'ppActivitiesContent',
    defaultTab: 'activities',
    loadTab: function (tab, child, el) {
      P.apiFetch('/student-activities/' + child.id, 'GET')
        .then(function (resp) { renderActivities(resp.data !== undefined ? resp.data : resp, el); })
        .catch(function (e) { P.showError(el, e.message); });
    },
  });

  document.getElementById('btnActivitiesCsv').addEventListener('click', function () {
    if (!_activities.length) return;
    var rows = _activities.map(function (a) {
      return [a.title, a.category_name || '', a.role || 'member', a.participant_status || 'active', (a.joined_at || '').substring(0, 10), a.activity_status || '', a.description || ''];
    });
    P.exportCsv('activities.csv', P.csvFromHeaders(['Activity', 'Category', 'Role', 'Status', 'Joined', 'Activity status', 'Description'], rows));
  });

  document.getElementById('btnActivitiesPrint').addEventListener('click', function () { P.printSection(); });
})();