/**
 * Mentor Notes Controller
 * Notes and guidance from mentorship sessions (read-only for intern).
 * API: GET /staff/mentor-notes
 */

const mentorNotesController = (() => {
  let allNotes = [];

  function show(id) { const el = document.getElementById(id); if (el) el.style.display = ''; }
  function hide(id) { const el = document.getElementById(id); if (el) el.style.display = 'none'; }

  function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
  }

  function typeBadge(type) {
    const map = {
      scheduled: 'bg-primary',
      informal: 'bg-secondary',
      observation_debrief: 'bg-info text-dark',
    };
    return `<span class="badge ${map[type] || 'bg-light text-dark border'}">${(type || 'General').replace(/_/g, ' ')}</span>`;
  }

  async function load(typeFilter) {
    show('mnLoading'); hide('mnNotesList'); hide('mnEmpty');
    try {
      const query = typeFilter ? `?type=${encodeURIComponent(typeFilter)}` : '';
      const r = await callAPI(`/staff/mentor-notes${query}`, 'GET');
      allNotes = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      hide('mnLoading');
      if (!allNotes.length) { show('mnEmpty'); return; }
      renderNotes(allNotes);
      show('mnNotesList');
    } catch (e) {
      hide('mnLoading');
      show('mnEmpty');
      console.error('mentor_notes load error', e);
    }
  }

  function renderNotes(notes) {
    const list = document.getElementById('mnNotesList');
    if (!list) return;
    list.innerHTML = notes.map(n => {
      const date = n.date || n.session_date || n.created_at;
      const type = n.type || n.session_type || n.note_type || '';
      const content = n.content || n.notes || n.note || '';
      const actionItems = Array.isArray(n.action_items) ? n.action_items : (n.action_items ? [n.action_items] : []);
      const mentor = n.mentor_name || n.mentor || '';
      return `
        <div class="col-md-6">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-2">
              <div>
                <span class="fw-semibold small"><i class="bi bi-calendar me-1 text-muted"></i>${formatDate(date)}</span>
                ${mentor ? `<span class="text-muted small ms-2">by ${mentor}</span>` : ''}
              </div>
              ${typeBadge(type)}
            </div>
            <div class="card-body">
              <p class="mb-3 small">${content || '—'}</p>
              ${actionItems.length ? `
                <div>
                  <div class="fw-semibold small mb-1 text-primary"><i class="bi bi-arrow-right-circle me-1"></i>Action Items:</div>
                  <ul class="list-unstyled mb-0 small">
                    ${actionItems.map(item => `<li><i class="bi bi-check2 text-success me-1"></i>${item}</li>`).join('')}
                  </ul>
                </div>` : ''}
            </div>
          </div>
        </div>`;
    }).join('');
  }

  function applyFilter(type) {
    if (!type) {
      renderNotes(allNotes);
    } else {
      const filtered = allNotes.filter(n => (n.type || n.session_type || '') === type);
      if (!filtered.length) {
        const list = document.getElementById('mnNotesList');
        if (list) list.innerHTML = '<div class="col-12 text-center text-muted py-4">No notes for this session type.</div>';
      } else {
        renderNotes(filtered);
      }
    }
  }

  function init() {
    const typeFilter = document.getElementById('mnTypeFilter');
    if (typeFilter) {
      typeFilter.addEventListener('change', () => {
        const v = typeFilter.value;
        if (!allNotes.length) return;
        applyFilter(v);
      });
    }
    load();
  }

  return { init };
})();

document.addEventListener('DOMContentLoaded', () => mentorNotesController.init());
