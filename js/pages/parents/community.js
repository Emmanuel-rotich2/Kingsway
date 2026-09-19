/**
 * js/pages/parents/community.js — PTA & parent community.
 */
(function () {
  'use strict';
  var P = window.ParentCommon;

  async function init() {
    if (!(await P.ensureAuth())) return;
    await P.loadDashboard();
    var el = document.getElementById('ppCommunityContent');
    if (!el) return;
    try {
      var resp = await P.apiFetch('/community', 'GET');
      var d = resp.data !== undefined ? resp.data : resp;
      var meetings = d.meetings || [];
      var countEl = document.getElementById('ppMeetingCount');
      if (countEl) countEl.textContent = meetings.length + ' upcoming';
      var html = '';
      if (d.is_representative) {
        html += '<div class="col-12"><div class="alert alert-success"><i class="bi bi-person-hearts me-2"></i>You are registered as a PTA representative: <strong>' +
          P.esc((d.memberships || []).map(function (m) { return m.role; }).join(', ')) + '</strong></div></div>';
      }
      if (meetings.length) {
        html += meetings.map(function (m) {
          return '<div class="col-md-6 col-xl-4 mb-3"><div class="pp-card h-100"><div class="pp-card-body">' +
            '<span class="badge bg-success-subtle text-success mb-2">' + P.esc(m.type || 'parent meeting') + '</span>' +
            '<h5 class="fw-bold">' + P.esc(m.title) + '</h5>' +
            '<p class="small text-muted mb-2"><i class="bi bi-calendar3 me-1"></i>' + P.esc(m.meeting_date) + ' ' + P.esc(m.start_time || '') + '</p>' +
            '<p class="small mb-0">' + P.esc(m.venue || 'Venue to be confirmed') + '</p>' +
            '<p class="small text-muted mt-2 mb-0">' + P.esc(m.description || m.purpose || '') + '</p>' +
            '</div></div></div>';
        }).join('');
      } else {
        html += '<div class="col-12"><div class="alert alert-info">There are no upcoming PTA or parent meetings at the moment. School notices and invitations will appear here.</div></div>';
      }
      el.innerHTML = html;
    } catch (e) {
      P.showError(el, 'Unable to load parent community information: ' + e.message);
    }
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();