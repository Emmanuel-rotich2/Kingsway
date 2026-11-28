/** Visitor register: history of visits with filters, CSV and print. */
(() => {
  'use strict';
  const root = document.getElementById('visitorRegisterPage');
  if (!root) return;
  const S = window.SecurityCommon;
  const { esc, api, data, PURPOSE } = S;
  const $ = (id) => document.getElementById(id);
  let rows = [];
  let timer = null;
  const STATUS = { on_site: ['On site', 'is-amber'], left: ['Left', 'is-clear'], denied: ['Turned away', 'is-stop'] };
  const dt = (v) => (v ? String(v).slice(0, 16) : '');

  async function load() {
    const p = {};
    if ($('vrFrom').value) p.from = $('vrFrom').value;
    if ($('vrTo').value) p.to = $('vrTo').value;
    if ($('vrStatus').value) p.status = $('vrStatus').value;
    if ($('vrPurpose').value) p.purpose = $('vrPurpose').value;
    if ($('vrQ').value.trim()) p.q = $('vrQ').value.trim();
    try {
      rows = data(await api('visits', 'GET', null, p)) || [];
      $('vrCount').textContent = `${rows.length} visit${rows.length === 1 ? '' : 's'}${rows.length >= 500 ? ' (latest 500; narrow the dates to see more)' : ''}`;
      $('vrBody').innerHTML = rows.length ? rows.map((v) => {
        const [st, cls] = STATUS[v.status] || [v.status, ''];
        const seeing = v.student ? `${esc(v.student.full_name)} (${esc(v.student.class_name)})` : esc(v.host_name || '');
        return `<tr><td>${esc(dt(v.checked_in_at))}</td><td>${esc(dt(v.checked_out_at))}</td>
          <td><strong>${esc(v.visitor_name)}</strong>${v.organisation ? `<div class="small text-muted">${esc(v.organisation)}</div>` : ''}${v.phone ? `<div class="small text-muted">${esc(v.phone)}</div>` : ''}</td>
          <td>${esc(v.id_number || '')}</td><td>${esc(PURPOSE[v.purpose] || v.purpose)}${v.denial_reason ? `<div class="small text-danger-emphasis">${esc(v.denial_reason)}</div>` : ''}</td>
          <td>${seeing}</td><td>${esc([v.badge_no, v.vehicle_reg].filter(Boolean).join(', '))}</td><td><span class="gate-tag ${cls}">${esc(st)}</span></td></tr>`;
      }).join('') : '<tr><td colspan="8" class="text-muted p-3">No visits for these filters.</td></tr>';
    } catch (e) {
      $('vrBody').innerHTML = `<tr><td colspan="8" class="text-danger p-3">${esc(e.message || 'The register could not be loaded.')}</td></tr>`;
    }
  }

  async function init() {
    await window.AuthContext?.ready?.();
    const today = new Date(Date.now() - new Date().getTimezoneOffset() * 60000);
    $('vrTo').value = today.toISOString().slice(0, 10);
    $('vrFrom').value = new Date(today.getTime() - 6 * 86400000).toISOString().slice(0, 10);
    ['vrFrom', 'vrTo', 'vrStatus', 'vrPurpose'].forEach((id) => $(id).addEventListener('change', load));
    $('vrQ').addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 300); });
    $('vrCsv').addEventListener('click', () => S.exportCsv('visitor_register.csv',
      ['Signed in', 'Signed out', 'Visitor', 'Organisation', 'Phone', 'ID number', 'Reason', 'Seeing', 'Badge', 'Vehicle', 'Status', 'Turned away because'],
      rows.map((v) => [v.checked_in_at, v.checked_out_at, v.visitor_name, v.organisation, v.phone, v.id_number, PURPOSE[v.purpose] || v.purpose, v.student ? v.student.full_name : v.host_name, v.badge_no, v.vehicle_reg, v.status, v.denial_reason])));
    $('vrPrint').addEventListener('click', () => window.print());
    await load();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
