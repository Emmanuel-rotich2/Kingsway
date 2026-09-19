/**
 * js/pages/parents/account.js — Account settings (read-only contact + security).
 */
(function () {
  'use strict';
  var P = window.ParentCommon;

  async function init() {
    if (!(await P.ensureAuth())) return;
    try {
      var d = await P.loadDashboard();
      var parent = d.parent || {};
      var name = [parent.first_name, parent.last_name].filter(Boolean).join(' ').trim();
      var nameEl = document.getElementById('ppAccName');
      var emailEl = document.getElementById('ppAccEmail');
      var phoneEl = document.getElementById('ppAccPhone');
      if (nameEl) nameEl.textContent = name || '—';
      if (emailEl) emailEl.textContent = parent.email || 'Not provided';
      if (phoneEl) phoneEl.textContent = parent.phone_1 || parent.phone || 'Not provided';
    } catch (e) {
      var el = document.getElementById('ppAccName');
      if (el) el.textContent = '—';
    }
    var logoutBtn = document.getElementById('btnLogoutInline');
    if (logoutBtn) logoutBtn.addEventListener('click', function () { P.logout(); });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();