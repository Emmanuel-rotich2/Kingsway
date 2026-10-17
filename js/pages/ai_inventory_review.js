const aiInventoryReviewController = {
  state: { referenceCache: null },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("inventory", "ai-inventory-review-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("AI Inventory Review Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  async render() {
    const host = document.getElementById('aiInventoryReviews');
    if (!host || !window.API?.inventory) return;
    try {
      const cache = this.state.referenceCache;
      let own = cache?.reviews !== undefined ? cache.reviews : await window.API.inventory.getAiReplenishmentReviews('own');
      let review = { drafts: [] };
      try { review = cache?.reviews !== undefined ? { drafts: cache.reviews.drafts || [] } : await window.API.inventory.getAiReplenishmentReviews('review'); } catch (_) {}
      const rows = [...(own?.data?.drafts || own?.drafts || []).map((x) => ({ ...x, __review: false })), ...(review?.data?.drafts || review?.drafts || []).map((x) => ({ ...x, __review: true }))].filter((x) => x.metadata?.subject_type === 'inventory_replenishment_review');
      host.innerHTML = rows.length ? rows.map((draft) => { const body = draft.draft || {}; const approve = draft.__review && draft.status === 'pending_approval'; return `<div class="col-12"><article class="border rounded p-2"><div class="d-flex justify-content-between"><strong>${esc(body.title || 'Inventory replenishment review')}</strong><span class="badge text-bg-secondary">${esc(draft.status || 'queued')}</span></div><p class="small mt-2 mb-2">${esc(body.body || 'Review is still being generated.')}</p>${Array.isArray(body.next_steps) && body.next_steps.length ? `<ul class="small mb-2">${body.next_steps.map((step) => `<li>${esc(step)}</li>`).join('')}</ul>` : ''}${approve ? `<button class="btn btn-sm btn-success" data-ai-inventory-approve="${Number(draft.id)}">Approve guidance</button>` : ''}${draft.status === 'approved' ? '<div class="small text-info">Approved for staff guidance. Stock and procurement workflows remain authoritative.</div>' : ''}</article></div>`; }).join('') : '<div class="col-12 small text-muted">No inventory replenishment review has been requested.</div>';
      host.querySelectorAll('[data-ai-inventory-approve]').forEach((button) => button.addEventListener('click', async () => { try { await window.API.inventory.approveAiReplenishmentReview(button.dataset.aiInventoryApprove); window.showNotification?.('Inventory guidance approved', 'success'); await render(); } catch (error) { window.showNotification?.(error.message || 'Unable to approve inventory guidance', 'danger'); } }));
    } catch (_) { host.innerHTML = '<div class="col-12 small text-warning">Inventory assistance is temporarily unavailable.</div>'; }
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    await this.loadReferencesComposed();
    await this.render();
    window.APIRealtime?.register?.("ai_inventory_review", {
      refresh: async () => {
        this.state.referenceCache = null;
        await this.loadReferencesComposed();
        await this.render();
      }
    }, ["inventory"]);
  },
};

const esc = (value) => { const el = document.createElement('div'); el.textContent = String(value ?? ''); return el.innerHTML; };

const init = () => {
  document.getElementById('queueAiInventoryReview')?.addEventListener('click', async () => {
    const button = document.getElementById('queueAiInventoryReview');
    button.disabled = true;
    try {
      const result = await window.API.inventory.queueAiReplenishmentReview();
      window.showNotification?.(`Inventory review queued${result?.job_id ? ` (job ${result.job_id})` : ''}`, 'info');
      for (let i = 0; i < 6; i += 1) { await new Promise((resolve) => setTimeout(resolve, i === 0 ? 1000 : 2500)); await aiInventoryReviewController.render(); }
    } catch (error) { window.showNotification?.(error.message || 'Unable to queue inventory review', 'danger'); } finally { button.disabled = false; }
  });
  window.RealtimeJobWake?.onDomain('ai', () => aiInventoryReviewController.render());
  aiInventoryReviewController.init();
};

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true }); else init();