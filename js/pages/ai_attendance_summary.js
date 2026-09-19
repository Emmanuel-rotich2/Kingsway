(function () {
  const esc = (value) => { const el = document.createElement('div'); el.textContent = String(value ?? ''); return el.innerHTML; };
  const today = () => new Date().toISOString().slice(0, 10);
  const range = () => {
    const period = document.getElementById('arPeriod')?.value;
    const now = new Date();
    const to = today();
    const from = period === 'this_week'
      ? new Date(now.getFullYear(), now.getMonth(), now.getDate() - 6).toISOString().slice(0, 10)
      : period === 'this_term'
        ? new Date(now.getFullYear(), now.getMonth(), now.getDate() - 90).toISOString().slice(0, 10)
        : new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
    return { date_from: document.getElementById('arDateFrom')?.value || from, date_to: document.getElementById('arDateTo')?.value || to };
  };
  const render = async () => {
    const host = document.getElementById('aiAttendanceSummaries');
    if (!host || !window.API?.attendance) return;
    try {
      const own = await window.API.attendance.getAiExceptionSummaries('own');
      let review = { drafts: [] };
      try { review = await window.API.attendance.getAiExceptionSummaries('review'); } catch (_) { /* reviewer access is optional */ }
      const rows = [
        ...(own?.data?.drafts || own?.drafts || []).map((draft) => ({ ...draft, __review: false })),
        ...(review?.data?.drafts || review?.drafts || []).map((draft) => ({ ...draft, __review: true })),
      ];
      const exceptionRows = rows.filter((draft) => draft.metadata?.subject_type === 'attendance_exception_summary');
      host.innerHTML = exceptionRows.length ? exceptionRows.map((draft) => {
        const body = draft.draft || {};
        const approve = draft.__review && draft.status === 'pending_approval';
        return `<div class="col-12"><article class="border rounded p-2"><div class="d-flex justify-content-between"><strong>${esc(body.title || 'Attendance exception summary')}</strong><span class="badge text-bg-secondary">${esc(draft.status || 'queued')}</span></div><p class="small mt-2 mb-2">${esc(body.body || 'Summary is still being generated.')}</p>${Array.isArray(body.next_steps) && body.next_steps.length ? `<ul class="small mb-2">${body.next_steps.map((step) => `<li>${esc(step)}</li>`).join('')}</ul>` : ''}${approve ? `<button class="btn btn-sm btn-success me-2" data-ai-attendance-approve="${Number(draft.id)}">Approve guidance</button>` : ''}${draft.status === 'approved' ? `<div class="small text-info mb-2">Approved for staff guidance. Attendance records remain authoritative.</div><button class="btn btn-sm btn-outline-primary" data-ai-parent-followup="${Number(draft.id)}">Draft parent follow-up</button>` : ''}</article></div>`;
      }).join('') : '<div class="col-12 small text-muted">No attendance exception summary has been requested today.</div>';
      host.querySelectorAll('[data-ai-attendance-approve]').forEach((button) => button.addEventListener('click', async () => {
        try {
          await window.API.attendance.approveAiExceptionSummary(button.dataset.aiAttendanceApprove);
          window.showNotification?.('Attendance guidance approved', 'success');
          await render();
        } catch (error) { window.showNotification?.(error.message || 'Unable to approve attendance guidance', 'danger'); }
      }));
      host.querySelectorAll('[data-ai-parent-followup]').forEach((button) => button.addEventListener('click', async () => {
        const draft = rows.find((item) => Number(item.id) === Number(button.dataset.aiParentFollowup));
        const body = draft?.draft || {};
        try {
          const result = await window.API.apiCall('/communications/ai-message-draft-queue', 'POST', { purpose: 'Attendance follow-up', audience: 'selected_parents', tone: 'respectful and supportive', facts: `Use placeholders such as [learner name], [class], and [date]. Approved staff guidance: ${body.body || ''}`, deadline: '', channel: 'sms' });
          window.showNotification?.(`Parent follow-up queued${result?.job_id ? ` (job ${result.job_id})` : ''}. Complete recipient review in Communications.`, 'info');
        } catch (error) { window.showNotification?.(error.message || 'Unable to queue parent follow-up', 'danger'); }
      }));
      const latenessHost = document.getElementById('aiAttendanceLatenessReviews');
      if (latenessHost) {
        const lateness = rows.filter((draft) => draft.metadata?.subject_type === 'attendance_lateness_pattern');
        latenessHost.innerHTML = lateness.length ? lateness.map((draft) => {
          const body = draft.draft || {};
          const approve = draft.__review && draft.status === 'pending_approval';
          return `<div class="col-12"><article class="border rounded p-2"><div class="d-flex justify-content-between"><strong>${esc(body.title || 'Lateness pattern review')}</strong><span class="badge text-bg-secondary">${esc(draft.status || 'queued')}</span></div><p class="small mt-2 mb-2">${esc(body.body || 'Review is still being generated.')}</p>${approve ? `<button class="btn btn-sm btn-success" data-ai-attendance-approve="${Number(draft.id)}">Approve guidance</button>` : ''}</article></div>`;
        }).join('') : '';
      }
    } catch (_) { host.innerHTML = '<div class="col-12 small text-warning">Attendance assistance is temporarily unavailable.</div>'; }
  };
  const queue = async () => {
    const button = document.getElementById('queueAiAttendanceSummary');
    if (button) button.disabled = true;
    try {
      const result = await window.API.attendance.queueAiExceptionSummary(today());
      window.showNotification?.(`Attendance summary queued${result?.job_id ? ` (job ${result.job_id})` : ''}`, 'info');
      for (let index = 0; index < 6; index += 1) {
        await new Promise((resolve) => setTimeout(resolve, index === 0 ? 1000 : 2500));
        await render();
      }
    } catch (error) { window.showNotification?.(error.message || 'Unable to queue attendance summary', 'danger'); }
    finally { if (button) button.disabled = false; }
  };
  const queueLateness = async () => {
    const button = document.getElementById('queueAiAttendanceLateness');
    if (button) button.disabled = true;
    try {
      const result = await window.API.attendance.queueAiLatenessPattern(range());
      window.showNotification?.(`Lateness review queued${result?.job_id ? ` (job ${result.job_id})` : ''}`, 'info');
      for (let index = 0; index < 6; index += 1) { await new Promise((resolve) => setTimeout(resolve, index === 0 ? 1000 : 2500)); await render(); }
    } catch (error) { window.showNotification?.(error.message || 'Unable to queue lateness review', 'danger'); }
    finally { if (button) button.disabled = false; }
  };
  const init = () => { document.getElementById('queueAiAttendanceSummary')?.addEventListener('click', queue); document.getElementById('queueAiAttendanceLateness')?.addEventListener('click', queueLateness); render(); };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true }); else init();
})();
