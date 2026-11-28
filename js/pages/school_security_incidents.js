/** School security incidents: list, report, and the response trail. */
(() => {
  'use strict';
  const root = document.getElementById('secIncidentsPage');
  if (!root) return;
  const S = window.SecurityCommon;
  const { esc, api, data, label, notify } = S;
  const $ = (id) => document.getElementById(id);
  let rows = [];
  let current = null;

  const SEV = { critical: 'is-stop', high: 'is-amber', medium: 'is-muted', low: 'is-muted' };
  const STATUS = { reported: 'is-amber', responding: 'is-amber', escalated: 'is-stop', contained: 'is-muted', resolved: 'is-clear', closed: 'is-clear' };
  const ACTION_LABEL = { note: 'Note', responding: 'Responding', contained: 'Contained', escalated: 'Escalated', police_notified: 'Police notified', parent_notified: 'Parent notified', resolved: 'Resolved', closed: 'Closed', reopened: 'Reopened' };

  async function load() {
    const params = {};
    ['siStatus', 'siSeverity'].forEach((id, i) => { const v = $(id).value; if (v) params[i ? 'severity' : 'status'] = v; });
    if ($('siFrom').value) params.from = $('siFrom').value;
    if ($('siTo').value) params.to = $('siTo').value;
    try {
      rows = data(await api('incidents', 'GET', null, params)) || [];
      $('siBody').innerHTML = rows.length ? rows.map((r) => `<tr class="${r.severity === 'critical' ? 'is-critical' : r.severity === 'high' ? 'is-high' : ''}" data-id="${Number(r.id)}" tabindex="0" role="button" aria-label="Open ${esc(r.reference_no)}">
        <td><strong>${esc(r.reference_no)}</strong></td><td>${esc(label(r.category))}</td><td>${esc(r.location)}</td>
        <td>${esc(String(r.occurred_at).slice(0, 16))}</td><td><span class="gate-tag ${SEV[r.severity] || ''}">${esc(label(r.severity))}</span></td>
        <td><span class="gate-tag ${STATUS[r.status] || ''}">${esc(label(r.status))}</span></td><td>${esc(r.responder_name || '—')}</td></tr>`).join('')
        : '<tr><td colspan="7" class="text-muted p-3">No incidents for these filters.</td></tr>';
    } catch (e) {
      $('siBody').innerHTML = `<tr><td colspan="7" class="text-danger p-3">${esc(e.message || 'Incidents could not be loaded.')}</td></tr>`;
    }
  }

  function renderDetail(r) {
    current = r;
    $('siDetailTitle').textContent = `${r.reference_no}: ${label(r.category)}`;
    $('siDetailBody').innerHTML = `
      <div class="d-flex flex-wrap gap-2 mb-2"><span class="gate-tag ${SEV[r.severity] || ''}">${esc(label(r.severity))}</span><span class="gate-tag ${STATUS[r.status] || ''}">${esc(label(r.status))}</span>${r.police_ob_no ? `<span class="gate-tag is-muted">Police OB ${esc(r.police_ob_no)}</span>` : ''}</div>
      <dl class="row mb-3">
        <dt class="col-sm-3">Where</dt><dd class="col-sm-9">${esc(r.location)}</dd>
        <dt class="col-sm-3">When</dt><dd class="col-sm-9">${esc(String(r.occurred_at).slice(0, 16))}</dd>
        <dt class="col-sm-3">Reported by</dt><dd class="col-sm-9">${esc(r.reported_by_name || '—')}</dd>
        ${r.persons_involved ? `<dt class="col-sm-3">People involved</dt><dd class="col-sm-9">${esc(r.persons_involved)}</dd>` : ''}
        ${r.immediate_action ? `<dt class="col-sm-3">First action</dt><dd class="col-sm-9">${esc(r.immediate_action)}</dd>` : ''}
        ${r.resolution ? `<dt class="col-sm-3">Resolution</dt><dd class="col-sm-9">${esc(r.resolution)}</dd>` : ''}
      </dl>
      <p style="white-space:pre-wrap;max-width:72ch">${esc(r.description)}</p>
      <h3 class="h6 mt-3">Response</h3>
      <ol class="gate-trail">${(r.actions || []).map((a) => `<li><strong>${esc(ACTION_LABEL[a.action_type] || a.action_type)}</strong> <span class="text-muted small">${esc(String(a.acted_at).slice(0, 16))}, ${esc(a.acted_by_name || '')}</span>${a.notes ? `<div>${esc(a.notes)}</div>` : ''}</li>`).join('')}</ol>`;
    const open = !['resolved', 'closed'].includes(r.status);
    const btn = (action, text, cls = 'btn-outline-secondary') => `<button class="btn ${cls} btn-sm" type="button" data-act="${action}">${text}</button>`;
    $('siActions').innerHTML = r.can_respond ? `
      <label class="form-label small" for="siNote">Note for this step</label>
      <textarea class="form-control mb-2" id="siNote" rows="2" maxlength="1000"></textarea>
      <div class="d-flex flex-wrap gap-2">
        ${r.status === 'closed' ? '' : btn('note', 'Add note')}
        ${open ? btn('responding', 'I am responding', 'btn-gate') + btn('contained', 'Contained') + btn('escalated', 'Escalate to leadership') + btn('police_notified', 'Police notified') + btn('parent_notified', 'Parent notified') : ''}
        ${r.can_close && open ? btn('resolved', 'Resolve', 'btn-success') : ''}
        ${r.can_close && r.status === 'resolved' ? btn('closed', 'Close') : ''}
        ${r.can_close && ['resolved', 'closed'].includes(r.status) ? btn('reopened', 'Reopen') : ''}
      </div>` : '<span class="text-muted small">The security team and school leadership are handling this.</span>';
  }

  async function openDetail(id) {
    try {
      renderDetail(data(await api(`incidents/${Number(id)}`)));
      bootstrap.Modal.getOrCreateInstance($('siDetail')).show();
    } catch (e) { notify(e.message || 'The incident could not be opened.', 'danger'); }
  }

  async function act(action) {
    const payload = { action_type: action, notes: $('siNote')?.value.trim() || '' };
    if (action === 'police_notified') {
      const ob = await window.promptAction?.('Police notified', 'Police OB number (if given):', '');
      if (ob === null) return;
      payload.police_ob_no = ob;
    }
    if (['note', 'escalated', 'resolved'].includes(action) && !payload.notes) { notify('Add a note describing what was done.', 'warning'); return; }
    try {
      renderDetail(data(await api(`incident-actions/${Number(current.id)}`, 'POST', payload)));
      notify(`${ACTION_LABEL[action]} recorded.`, 'success');
      load();
    } catch (e) { notify(e.message || 'Could not record this step.', 'danger'); }
  }

  async function init() {
    await window.AuthContext?.ready?.();
    ['siStatus', 'siSeverity', 'siFrom', 'siTo'].forEach((id) => $(id).addEventListener('change', load));
    $('siBody').addEventListener('click', (e) => { const tr = e.target.closest('tr[data-id]'); if (tr) openDetail(tr.dataset.id); });
    $('siBody').addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { const tr = e.target.closest('tr[data-id]'); if (tr) { e.preventDefault(); openDetail(tr.dataset.id); } } });
    $('siActions').addEventListener('click', (e) => { const b = e.target.closest('[data-act]'); if (b) act(b.dataset.act); });
    $('siCsv').addEventListener('click', () => S.exportCsv('security_incidents.csv', ['Reference', 'What', 'Where', 'When', 'Seriousness', 'Status', 'Handled by', 'Resolution'],
      rows.map((r) => [r.reference_no, label(r.category), r.location, r.occurred_at, r.severity, r.status, r.responder_name, r.resolution])));
    $('siPrint').addEventListener('click', () => window.print());
    S.wireReport($('siReport'), () => load());
    await load();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
