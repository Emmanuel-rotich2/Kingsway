/**
 * Development Progress Controller
 * Professional development milestones tracker.
 * API: GET /staff/development-progress
 */

const developmentProgressController = (() => {
  function show(id) { const el = document.getElementById(id); if (el) el.style.display = ''; }
  function hide(id) { const el = document.getElementById(id); if (el) el.style.display = 'none'; }
  function setText(id, v) { const el = document.getElementById(id); if (el) el.textContent = v; }

  function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
  }

  function statusBadge(status) {
    const map = {
      completed: 'bg-success',
      in_progress: 'bg-warning text-dark',
      pending: 'bg-secondary',
      not_started: 'bg-light text-dark border',
    };
    return `<span class="badge ${map[status] || 'bg-secondary'}">${(status || 'pending').replace(/_/g, ' ')}</span>`;
  }

  function weeksRemaining(endDate) {
    if (!endDate) return '—';
    const ms = new Date(endDate) - new Date();
    if (ms <= 0) return '0';
    return Math.ceil(ms / (7 * 24 * 3600 * 1000));
  }

  async function load() {
    show('dpLoading'); hide('dpContent'); hide('dpEmpty');
    try {
      const r = await callAPI('/staff/development-progress', 'GET');
      const data = r?.data || r || {};
      const milestones = Array.isArray(data.milestones) ? data.milestones : (Array.isArray(data) ? data : []);
      const meta = data.meta || data.summary || {};

      hide('dpLoading');

      const completed = milestones.filter(m => m.status === 'completed').length;
      const inProgress = milestones.filter(m => m.status === 'in_progress').length;
      const pending = milestones.filter(m => !m.status || m.status === 'pending' || m.status === 'not_started').length;
      const pct = milestones.length ? Math.round((completed / milestones.length) * 100) : 0;

      setText('dpStatCompleted', completed);
      setText('dpStatInProgress', inProgress);
      setText('dpStatPending', pending);
      setText('dpStatWeeksLeft', meta.weeks_remaining !== undefined ? meta.weeks_remaining : weeksRemaining(meta.end_date || data.end_date));
      setText('dpOverallPct', pct + '%');

      const bar = document.getElementById('dpProgressBar');
      if (bar) { bar.style.width = pct + '%'; bar.setAttribute('aria-valuenow', pct); }

      if (!milestones.length) { show('dpEmpty'); return; }
      renderMilestones(milestones);
      show('dpContent');
    } catch (e) {
      hide('dpLoading');
      show('dpEmpty');
      console.error('development_progress load error', e);
    }
  }

  function renderMilestones(milestones) {
    const tbody = document.getElementById('dpTableBody');
    if (!tbody) return;
    tbody.innerHTML = milestones.map((m, i) => `
      <tr>
        <td class="text-muted small">${i + 1}</td>
        <td>
          <div class="fw-semibold">${m.title || m.name || m.milestone || '—'}</div>
          ${m.description ? `<small class="text-muted">${m.description}</small>` : ''}
        </td>
        <td><span class="badge bg-primary-subtle text-primary border border-primary">${m.category || m.type || '—'}</span></td>
        <td class="text-nowrap small">${formatDate(m.due_date || m.target_date)}</td>
        <td>${statusBadge(m.status)}</td>
        <td>
          ${m.status !== 'completed'
            ? `<button class="btn btn-outline-success btn-sm" onclick="developmentProgressController.markComplete(${m.id})">
                <i class="bi bi-check-circle me-1"></i>Complete
               </button>`
            : `<span class="text-muted small"><i class="bi bi-check-circle-fill text-success me-1"></i>${formatDate(m.completed_at)}</span>`
          }
        </td>
      </tr>
    `).join('');
  }

  async function markComplete(id) {
    if (!confirm('Mark this milestone as completed?')) return;
    try {
      await callAPI(`/staff/development-progress/${id}`, 'PUT', { status: 'completed', completed_at: new Date().toISOString().slice(0, 10) });
      showNotification('Milestone marked as completed.', 'success');
      await load();
    } catch (e) {
      showNotification('Failed to update milestone.', 'danger');
      console.error(e);
    }
  }

  function init() {
    load();
  }

  return { init, markComplete };
})();

document.addEventListener('DOMContentLoaded', () => developmentProgressController.init());
