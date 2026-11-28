/** Shared helpers for the support operations pages (drivers, kitchen, boarding). */
(function () {
  'use strict';
  if (window.OpsCommon) return;
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
  const data = (r) => (r && r.data !== undefined && !Array.isArray(r) ? r.data : r);
  const api = (path, method = 'GET', body = null, params = {}) =>
    window.API.apiCall(path, method, body, params, { checkPermission: false });
  const notify = (m, t = 'info') => window.showNotification?.(m, t);
  const label = (v) => String(v || '').replace(/_/g, ' ').replace(/^\w/, (m) => m.toUpperCase());
  const today = () => new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
  const kes = (v) => 'KES ' + Number(v || 0).toLocaleString('en-KE', { maximumFractionDigits: 2 });
  const num = (v) => Number(v || 0).toLocaleString('en-KE');
  function date(d) {
    if (!d) return '';
    const x = new Date(String(d).slice(0, 10) + 'T00:00:00');
    return Number.isNaN(x.getTime()) ? String(d) : x.toLocaleDateString('en-KE', { day: 'numeric', month: 'short', year: 'numeric' });
  }
  const hm = (t) => (t ? String(t).slice(0, 5) : '');
  /** Whole days from today to a Y-m-d date (negative = past). */
  function daysUntil(d) {
    if (!d) return null;
    const ms = new Date(String(d).slice(0, 10) + 'T00:00:00') - new Date(today() + 'T00:00:00');
    return Math.round(ms / 86400000);
  }
  /** "in 12 days" / "today" / "3 days ago" and a tone for it. */
  function due(d, warnDays = 14) {
    const n = daysUntil(d);
    if (n === null) return { text: 'Not recorded', tone: 'is-muted' };
    if (n < 0) return { text: `${-n} day${n === -1 ? '' : 's'} overdue`, tone: 'is-stop' };
    if (n === 0) return { text: 'Due today', tone: 'is-stop' };
    return { text: `in ${n} day${n === 1 ? '' : 's'}`, tone: n <= warnDays ? 'is-warn' : 'is-ok' };
  }

  async function exportCsv(filename, head, rows) {
    const cell = (v) => { const t = String(v ?? ''); return /[",\r\n]/.test(t) ? `"${t.replace(/"/g, '""')}"` : t; };
    const lines = [head.join(',')].concat(rows.map((r) => r.map(cell).join(',')));
    try { await window.KingswayFileLifecycle.exportText(`﻿${lines.join('\r\n')}`, filename, 'text/csv;charset=utf-8'); }
    catch (err) { notify(err.message || 'Export failed', 'danger'); }
  }

  /** Wire a role="tablist" whose tabs have aria-controls; onChange(id). */
  function tabs(list, onChange) {
    if (!list) return;
    const all = [...list.querySelectorAll('[role="tab"]')];
    const select = (tab, focus) => {
      all.forEach((t) => {
        const on = t === tab;
        t.setAttribute('aria-selected', String(on));
        t.tabIndex = on ? 0 : -1;
        document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
      });
      if (focus) tab.focus();
      onChange?.(tab.getAttribute('aria-controls'));
    };
    list.addEventListener('click', (e) => { const t = e.target.closest('[role="tab"]'); if (t) select(t); });
    list.addEventListener('keydown', (e) => {
      const i = all.indexOf(document.activeElement);
      if (i < 0) return;
      const next = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: all.length - 1 }[e.key];
      if (next === undefined) return;
      e.preventDefault();
      select(all[(next + all.length) % all.length], true);
    });
  }

  const canExport = () => window.AuthContext?.canExport?.() !== false;

  window.OpsCommon = { esc, data, api, notify, label, today, kes, num, date, hm, daysUntil, due, exportCsv, tabs, canExport };
})();
