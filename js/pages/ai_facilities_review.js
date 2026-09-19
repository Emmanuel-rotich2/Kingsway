(function () {
  'use strict';
  const esc = (value) => { const el = document.createElement('div'); el.textContent = String(value ?? ''); return el.innerHTML; };
  const render = async () => {
    const host = document.getElementById('aiFacilitiesReviews');
    if (!host || !window.API?.maintenance) return;
    try {
      const own = await window.API.maintenance.getAiFacilitiesReviews({ scope: 'own' });
      let review = { data: { drafts: [] } };
      try { review = await window.API.maintenance.getAiFacilitiesReviews({ scope: 'review' }); } catch (_) {}
      const rows = [
        ...(own?.data?.drafts || own?.drafts || []).map((item) => ({ ...item, __review: false })),
        ...(review?.data?.drafts || review?.drafts || []).map((item) => ({ ...item, __review: true })),
      ].filter((item) => item.metadata?.subject_type === 'maintenance_facilities_review');
      host.innerHTML = rows.length ? rows.map((draft) => {
        const body = draft.draft || {};
        const approve = draft.__review && draft.status === 'pending_approval';
        return `<div class="col-12"><article class="border rounded p-2"><div class="d-flex justify-content-between"><strong>${esc(body.title || 'Facilities maintenance review')}</strong><span class="badge text-bg-secondary">${esc(draft.status || 'queued')}</span></div><p class="small mt-2 mb-2">${esc(body.body || 'Review is still being generated.')}</p>${Array.isArray(body.next_steps) && body.next_steps.length ? `<ul class="small mb-2">${body.next_steps.map((step) => `<li>${esc(step)}</li>`).join('')}</ul>` : ''}${approve ? `<button class="btn btn-sm btn-success" data-ai-facilities-approve="${Number(draft.id)}">Approve guidance</button>` : ''}${draft.status === 'approved' ? '<div class="small text-info">Approved for staff guidance. Maintenance records and controls remain authoritative.</div>' : ''}</article></div>`;
      }).join('') : '<div class="col-12 small text-muted">No facilities maintenance review has been requested.</div>';
      host.querySelectorAll('[data-ai-facilities-approve]').forEach((button) => button.addEventListener('click', async () => {
        try { await window.API.maintenance.approveAiFacilitiesReview(button.dataset.aiFacilitiesApprove); window.showNotification?.('Maintenance guidance approved', 'success'); await render(); }
        catch (error) { window.showNotification?.(error.message || 'Unable to approve maintenance guidance', 'danger'); }
      }));
    } catch (_) { host.innerHTML = '<div class="col-12 small text-warning">Maintenance assistance is temporarily unavailable.</div>'; }
  };
  const init = () => {
    document.getElementById('queueAiFacilitiesReview')?.addEventListener('click', async () => {
      const button = document.getElementById('queueAiFacilitiesReview'); button.disabled = true;
      try { const result = await window.API.maintenance.queueAiFacilitiesReview(); window.showNotification?.(`Maintenance review queued${result?.job_id ? ` (job ${result.job_id})` : ''}`, 'info'); for (let i = 0; i < 6; i += 1) { await new Promise((resolve) => setTimeout(resolve, i === 0 ? 1000 : 2500)); await render(); } }
      catch (error) { window.showNotification?.(error.message || 'Unable to queue maintenance review', 'danger'); }
      finally { button.disabled = false; }
    });
    render();
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true }); else init();
})();
