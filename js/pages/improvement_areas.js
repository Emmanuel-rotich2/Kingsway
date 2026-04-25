/**
 * Improvement Areas Controller
 * Development areas identified for intern by mentor (read-only for intern).
 * API: GET /staff/improvement-areas
 */

const improvementAreasController = (() => {
  function show(id) { const el = document.getElementById(id); if (el) el.style.display = ''; }
  function hide(id) { const el = document.getElementById(id); if (el) el.style.display = 'none'; }
  function setText(id, v) { const el = document.getElementById(id); if (el) el.textContent = v; }

  function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
  }

  function priorityBadge(priority) {
    const map = {
      high: 'bg-danger',
      medium: 'bg-warning text-dark',
      low: 'bg-success',
    };
    return `<span class="badge ${map[(priority || '').toLowerCase()] || 'bg-secondary'}">${priority || 'Normal'}</span>`;
  }

  function statusBadge(status) {
    const map = {
      resolved: 'bg-success',
      in_progress: 'bg-warning text-dark',
      pending: 'bg-secondary',
    };
    return `<span class="badge ${map[(status || '').toLowerCase()] || 'bg-secondary'}">${(status || 'pending').replace(/_/g, ' ')}</span>`;
  }

  async function load() {
    show('iaLoading'); hide('iaContent'); hide('iaEmpty');
    try {
      const r = await callAPI('/staff/improvement-areas', 'GET');
      const areas = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      hide('iaLoading');

      const resolved = areas.filter(a => (a.status || '').toLowerCase() === 'resolved').length;
      const pending = areas.filter(a => (a.status || '').toLowerCase() !== 'resolved').length;
      setText('iaStatTotal', areas.length);
      setText('iaStatResolved', resolved);
      setText('iaStatPending', pending);

      if (!areas.length) { show('iaEmpty'); return; }
      renderAreas(areas);
      show('iaContent');
    } catch (e) {
      hide('iaLoading');
      show('iaEmpty');
      console.error('improvement_areas load error', e);
    }
  }

  function renderAreas(areas) {
    const tbody = document.getElementById('iaTableBody');
    if (!tbody) return;
    tbody.innerHTML = areas.map(a => `
      <tr>
        <td>
          <div class="fw-semibold">${a.area || a.title || a.name || '—'}</div>
          ${a.description ? `<small class="text-muted">${a.description}</small>` : ''}
        </td>
        <td>${priorityBadge(a.priority)}</td>
        <td class="text-nowrap small text-muted">${formatDate(a.identified_on || a.created_at || a.date)}</td>
        <td class="small">${a.action_plan || a.action || '—'}</td>
        <td>${statusBadge(a.status)}</td>
      </tr>
    `).join('');
  }

  function init() {
    load();
  }

  return { init };
})();

document.addEventListener('DOMContentLoaded', () => improvementAreasController.init());
