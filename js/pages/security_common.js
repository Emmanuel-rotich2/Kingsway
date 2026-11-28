/** Shared helpers for the security office pages (gate desk, register, incidents). */
(function () {
  'use strict';
  if (window.SecurityCommon) return;
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
  const api = (path, method = 'GET', data = null, params = {}) =>
    window.API.apiCall('/security/' + path, method, data, params, { checkPermission: false });
  const data = (r) => (r && r.data !== undefined && !Array.isArray(r) ? r.data : r);
  const label = (v) => String(v || '').replace(/_/g, ' ').replace(/^\w/, (m) => m.toUpperCase());
  const time = (dt) => (dt ? String(dt).slice(11, 16) : '');
  const PURPOSE = { parent_visit: 'Parent visiting', learner_pickup: 'Picking up a learner', delivery: 'Delivery', contractor: 'Contractor', official: 'Official', interview: 'Interview / admissions', meeting: 'Meeting', other: 'Other' };
  const nowLocal = () => { const d = new Date(Date.now() - new Date().getTimezoneOffset() * 60000); return d.toISOString().slice(0, 16); };
  const notify = (m, t = 'info') => window.showNotification?.(m, t);

  function photo(url) {
    return window.KingswayFileLifecycle?.resolveUrl ? window.KingswayFileLifecycle.resolveUrl(url) : (url || '');
  }

  /** Wire the shared "Report a security incident" dialog. onDone(ref) after saving. */
  function wireReport(openButton, onDone) {
    const modalEl = document.getElementById('secReportModal');
    if (!modalEl) return;
    openButton?.addEventListener('click', () => {
      document.getElementById('secReportForm').reset();
      document.getElementById('secWhen').value = nowLocal();
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
    document.getElementById('secReportSubmit').addEventListener('click', async (e) => {
      const btn = e.currentTarget;
      const payload = {
        category: document.getElementById('secCategory').value,
        severity: document.getElementById('secSeverity').value,
        occurred_at: document.getElementById('secWhen').value.replace('T', ' '),
        location: document.getElementById('secWhere').value.trim(),
        description: document.getElementById('secDescription').value.trim(),
        persons_involved: document.getElementById('secPersons').value.trim(),
        immediate_action: document.getElementById('secAction').value.trim(),
      };
      if (!payload.location || !payload.description || !payload.occurred_at) { notify('Say where and when it happened, and describe it.', 'warning'); return; }
      btn.disabled = true;
      try {
        const res = data(await api('incidents', 'POST', payload)) || {};
        bootstrap.Modal.getInstance(modalEl)?.hide();
        notify(`Incident reported. Reference ${res.reference_no || ''}.`, 'success');
        onDone?.(res);
      } catch (err) {
        notify(err.message || 'The incident could not be reported.', 'danger');
      } finally { btn.disabled = false; }
    });
  }

  async function exportCsv(filename, head, rows) {
    const cell = (v) => { const t = String(v ?? ''); return /[",\r\n]/.test(t) ? `"${t.replace(/"/g, '""')}"` : t; };
    const lines = [head.join(',')].concat(rows.map((r) => r.map(cell).join(',')));
    try { await window.KingswayFileLifecycle.exportText(`﻿${lines.join('\r\n')}`, filename, 'text/csv;charset=utf-8'); }
    catch (err) { notify(err.message || 'Export failed', 'danger'); }
  }

  window.SecurityCommon = { esc, api, data, label, time, PURPOSE, nowLocal, notify, photo, wireReport, exportCsv };
})();
