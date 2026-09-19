(function () {
  const esc = (v) => { const e = document.createElement('div'); e.textContent = String(v ?? ''); return e.innerHTML; };
  const render = async () => {
    const host = document.getElementById('aiHealthWelfareReviews'); if (!host || !window.API?.system) return;
    try {
      const own = await window.API.system.getAiWelfareReviews('own'); let review = { drafts: [] };
      try { review = await window.API.system.getAiWelfareReviews('review'); } catch (_) {}
      const rows = [...(own?.data?.drafts || own?.drafts || []).map(x => ({ ...x, __review: false })), ...(review?.data?.drafts || review?.drafts || []).map(x => ({ ...x, __review: true }))];
      host.innerHTML = rows.length ? rows.map(x => { const d = x.draft || {}; const approve = x.__review && x.status === 'pending_approval'; return `<div class="col-12"><article class="border rounded p-2"><div class="d-flex justify-content-between"><strong>${esc(d.title || 'Health and welfare review')}</strong><span class="badge text-bg-secondary">${esc(x.status || 'queued')}</span></div><p class="small mt-2 mb-2">${esc(d.body || 'Review is still being generated.')}</p>${approve ? `<button class="btn btn-sm btn-success" data-ai-health-approve="${Number(x.id)}">Approve guidance</button>` : ''}${x.status === 'approved' ? '<div class="small text-info">Approved for staff guidance; health records remain authoritative.</div>' : ''}</article></div>`; }).join('') : '<div class="col-12 small text-muted">No health review has been requested.</div>';
      host.querySelectorAll('[data-ai-health-approve]').forEach(b => b.addEventListener('click', async () => { try { await window.API.system.approveAiWelfareReview(b.dataset.aiHealthApprove); window.showNotification?.('Health guidance approved', 'success'); await render(); } catch (e) { window.showNotification?.(e.message || 'Unable to approve health guidance', 'danger'); } }));
    } catch (_) { host.innerHTML = '<div class="col-12 small text-warning">Health assistance is temporarily unavailable.</div>'; }
  };
  const init = () => { document.getElementById('queueAiHealthWelfare')?.addEventListener('click', async () => { const b = document.getElementById('queueAiHealthWelfare'); b.disabled = true; try { const r = await window.API.system.queueAiWelfareReview(); window.showNotification?.(`Health review queued${r?.job_id ? ` (${r.job_id})` : ''}`, 'info'); await render(); } catch (e) { window.showNotification?.(e.message || 'Unable to queue health review', 'danger'); } finally { b.disabled = false; } }); render(); };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true }); else init();
}());
