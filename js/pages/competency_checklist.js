/**
 * Competency Checklist Controller
 * Teaching competency self-assessment; intern rates, mentor validates.
 * API: GET /staff/competency-checklist, PUT /staff/competency-checklist/{id}
 */

const competencyChecklistController = (() => {
  const DOMAINS = ['Planning', 'Delivery', 'Assessment', 'Classroom Management', 'Professional Conduct'];
  const RATING_LABELS = { 1: 'Beginner', 2: 'Developing', 3: 'Proficient', 4: 'Expert' };
  let items = [];

  function show(id) { const el = document.getElementById(id); if (el) el.style.display = ''; }
  function hide(id) { const el = document.getElementById(id); if (el) el.style.display = 'none'; }

  function ratingColor(r) {
    if (r >= 4) return 'success';
    if (r >= 3) return 'primary';
    if (r >= 2) return 'warning';
    return 'danger';
  }

  async function load() {
    show('ccLoading'); hide('ccContent'); hide('ccEmpty');
    try {
      const r = await callAPI('/staff/competency-checklist', 'GET');
      items = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      hide('ccLoading');
      if (!items.length) { show('ccEmpty'); return; }
      renderDomains();
      show('ccContent');
    } catch (e) {
      hide('ccLoading');
      show('ccEmpty');
      console.error('competency_checklist load error', e);
    }
  }

  function renderDomains() {
    const container = document.getElementById('ccDomainList');
    if (!container) return;

    // Group by domain
    const grouped = {};
    DOMAINS.forEach(d => { grouped[d] = []; });
    items.forEach(item => {
      const domain = item.domain || item.category || 'Other';
      if (!grouped[domain]) grouped[domain] = [];
      grouped[domain].push(item);
    });

    container.innerHTML = Object.entries(grouped).filter(([, its]) => its.length > 0).map(([domain, its]) => `
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-light border-bottom py-2 d-flex align-items-center">
          <i class="bi bi-bookmark-fill me-2 text-primary"></i>
          <span class="fw-semibold">${domain}</span>
          <span class="ms-auto badge bg-secondary">${its.length} items</span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light" style="font-size:.8rem;">
                <tr>
                  <th>Competency</th>
                  <th style="width:200px;">Self-Rating (1–4)</th>
                  <th style="width:150px;">Mentor Validated</th>
                  <th style="width:120px;">Evidence</th>
                </tr>
              </thead>
              <tbody>
                ${its.map(item => renderItem(item)).join('')}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    `).join('');
  }

  function renderItem(item) {
    const selfRating = item.self_rating || item.rating || 0;
    const mentorValidated = item.mentor_validated || item.validated || false;
    const mentorRating = item.mentor_rating || 0;
    const color = ratingColor(selfRating);

    const ratingButtons = [1, 2, 3, 4].map(n => `
      <button class="btn btn-sm ${selfRating == n ? 'btn-' + ratingColor(n) : 'btn-outline-' + ratingColor(n)} py-0 px-2"
              onclick="competencyChecklistController.setRating(${item.id}, ${n})"
              title="${RATING_LABELS[n]}">
        ${n}
      </button>
    `).join('');

    const validationBadge = mentorValidated
      ? `<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Validated${mentorRating ? ' (' + mentorRating + '/4)' : ''}</span>`
      : `<span class="badge bg-light text-dark border">Pending</span>`;

    return `
      <tr id="ccItem_${item.id}">
        <td>
          <div class="fw-semibold small">${item.competency || item.name || item.title || '—'}</div>
          ${item.description ? `<div class="text-muted" style="font-size:.75rem;">${item.description}</div>` : ''}
        </td>
        <td>
          <div class="btn-group btn-group-sm" role="group">
            ${ratingButtons}
          </div>
          ${selfRating ? `<div class="text-muted" style="font-size:.7rem;">${RATING_LABELS[selfRating] || ''}</div>` : ''}
        </td>
        <td>${validationBadge}</td>
        <td>
          <small class="text-muted">${item.evidence || '—'}</small>
        </td>
      </tr>
    `;
  }

  async function setRating(id, rating) {
    const item = items.find(i => i.id == id);
    if (item) item.self_rating = rating;
    renderDomains(); // re-render to update button styles
    // Debounced save happens via saveAll or auto-save
  }

  async function saveAll() {
    const changed = items.filter(i => i.self_rating);
    if (!changed.length) { showNotification('No ratings to save.', 'info'); return; }

    let saved = 0;
    for (const item of changed) {
      try {
        await callAPI(`/staff/competency-checklist/${item.id}`, 'PUT', { self_rating: item.self_rating });
        saved++;
      } catch (e) {
        console.error('save rating error for item', item.id, e);
      }
    }
    showNotification(`${saved} rating(s) saved.`, 'success');
  }

  function init() {
    const saveAllBtn = document.getElementById('ccSaveAllBtn');
    if (saveAllBtn) saveAllBtn.addEventListener('click', saveAll);
    load();
  }

  return { init, setRating, saveAll };
})();

document.addEventListener('DOMContentLoaded', () => competencyChecklistController.init());
