/** AI operations summaries for the transport administration page. */
(function () {
  'use strict';

  const esc = (value) => { const node = document.createElement('span'); node.textContent = String(value ?? ''); return node.innerHTML; };
  const host = () => document.getElementById('aiTransportSummaries');

  async function load() {
    const target = host();
    if (!target || !window.API?.transport) return;
    try {
      const own = await window.API.transport.getAiOperationsSummaries('own');
      let review = { drafts: [] };
      try { review = await window.API.transport.getAiOperationsSummaries('review'); } catch (_) { /* Review access is optional. */ }
      const drafts = [
        ...(own?.data?.drafts || own?.drafts || []).map((item) => ({ ...item, canReview: false })),
        ...(review?.data?.drafts || review?.drafts || []).map((item) => ({ ...item, canReview: true })),
      ].filter((item) => item.metadata?.subject_type === 'transport_operations_summary');
      target.innerHTML = drafts.length ? drafts.map((item) => {
        const content = item.draft || {};
        const canApprove = item.canReview && item.status === 'pending_approval';
        return `<div class="col-12"><article class="border rounded p-2">
          <div class="d-flex justify-content-between"><strong>${esc(content.title || 'Transport operations review')}</strong><span class="badge text-bg-secondary">${esc(item.status || 'queued')}</span></div>
          <p class="small mt-2 mb-2">${esc(content.body || 'Summary is being prepared for review.')}</p>
          ${Array.isArray(content.next_steps) && content.next_steps.length ? `<ul class="small mb-2">${content.next_steps.map((step) => `<li>${esc(step)}</li>`).join('')}</ul>` : ''}
          ${canApprove ? `<button class="btn btn-sm btn-success" data-transport-summary-approve="${Number(item.id)}">Approve guidance</button>` : ''}
          ${item.status === 'approved' ? '<div class="small text-info">Approved for staff guidance. Transport records remain authoritative.</div>' : ''}
        </article></div>`;
      }).join('') : '<div class="col-12 small text-muted">No transport operations summary has been requested today.</div>';
      target.querySelectorAll('[data-transport-summary-approve]').forEach((button) => button.addEventListener('click', async () => {
        button.disabled = true;
        try {
          await window.API.transport.approveAiOperationsSummary(button.dataset.transportSummaryApprove);
          window.showNotification?.('Transport guidance approved.', 'success');
          await load();
        } catch (error) {
          window.showNotification?.(error.message || 'Unable to approve transport guidance.', 'danger');
          button.disabled = false;
        }
      }));
    } catch (error) {
      target.innerHTML = `<div class="col-12 small text-warning">${esc(error.message || 'Transport assistance is temporarily unavailable.')}</div>`;
    }
  }

  async function init() {
    const button = document.getElementById('queueAiTransportSummary');
    button?.addEventListener('click', async () => {
      button.disabled = true;
      try {
        await window.API.transport.queueAiOperationsSummary();
        window.showNotification?.('Transport operations summary queued for review.', 'success');
        await load();
      } catch (error) {
        window.showNotification?.(error.message || 'Unable to queue the transport summary.', 'danger');
      } finally { button.disabled = false; }
    });
    await load();
    window.APIRealtime?.register?.('ai_transport_summary', { refresh: load }, ['transport', 'ai']);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();
