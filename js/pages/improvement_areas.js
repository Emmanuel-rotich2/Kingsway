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
    show('iaLoading'); hide('iaContent'); hide('iaEmpty'); hide('iaError');
    try {
      const r = await callAPI('/staff/improvement-areas', 'GET');
      const areas = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      hide('iaLoading');

      const recommendations = areas.filter(a => String(a.recommendations || '').trim()).length;
      const ratings = areas.map(a => Number(a.rating || 0)).filter(n => n > 0);
      setText('iaStatTotal', areas.length);
      setText('iaStatResolved', recommendations);
      setText('iaStatPending', ratings.length ? (ratings.reduce((sum, n) => sum + n, 0) / ratings.length).toFixed(1) : '—');

      if (!areas.length) { show('iaEmpty'); return; }
      renderAreas(areas);
      show('iaContent');
    } catch (e) {
      hide('iaLoading');
      show('iaError');
      console.error('improvement_areas load error', e);
    }
  }

  function renderAreas(areas) {
    const tbody = document.getElementById('iaTableBody');
    if (!tbody) return;
    tbody.innerHTML = areas.map(a => `
      <tr>
        <td>
          <div class="fw-semibold">${thisEsc(a.areas_for_improvement)}</div>
          ${a.strengths ? `<small class="text-muted">Strengths: ${thisEsc(a.strengths)}</small>` : ''}
        </td>
        <td class="text-nowrap small text-muted">${thisEsc(formatDate(a.observation_date))}</td>
        <td>${a.rating ? `${thisEsc(a.rating)}` : '—'}</td>
        <td class="small">${thisEsc(a.recommendations || '—')}</td>
        <td>${thisEsc(a.status || '—')}</td>
      </tr>
    `).join('');
  }

  function thisEsc(value) {
    const el = document.createElement('span');
    el.textContent = String(value ?? '');
    return el.innerHTML;
  }

  function init() {
    load();
  }

  return { init };
})();

document.addEventListener('DOMContentLoaded', () => improvementAreasController.init());

window.improvementAreasController = improvementAreasController;
