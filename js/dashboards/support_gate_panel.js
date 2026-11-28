/** Support staff dashboard: "Today at the gate" for security staff only. */
(function () {
  'use strict';
  async function init() {
    const panel = document.getElementById('supportGatePanel');
    if (!panel) return;
    await window.AuthContext?.ready?.();
    const roles = (window.AuthContext?.getRoles?.() || []).map((r) => String(r?.name || r).toLowerCase());
    if (!roles.some((r) => r.includes('security'))) return;
    try {
      const res = await window.API.apiCall('/security/summary', 'GET', null, {}, { checkPermission: false });
      const s = (res && res.data !== undefined && !Array.isArray(res) ? res.data : res) || {};
      const n = (v) => Number(v || 0);
      document.getElementById('supportGateFigures').textContent =
        `${n(s.on_site)} on site now, ${n(s.visitors_today)} visitors today, ${n(s.exeats_out)} learners out on exeat, ${n(s.open_incidents)} open incidents.`;
      panel.hidden = false;
    } catch (_) { /* not a gate user: keep the panel hidden */ }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
