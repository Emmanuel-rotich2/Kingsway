/**
 * Reflection Journal Controller
 * Personal teaching reflection journal (CRUD).
 * API: GET /staff/reflection-journal, POST /staff/reflection-journal
 */

const reflectionJournalController = (() => {
  let modal = null;
  let entries = [];

  function show(id) { const el = document.getElementById(id); if (el) el.style.display = ''; }
  function hide(id) { const el = document.getElementById(id); if (el) el.style.display = 'none'; }
  function val(id) { const el = document.getElementById(id); return el ? el.value.trim() : ''; }
  function setVal(id, v) { const el = document.getElementById(id); if (el) el.value = v || ''; }
  function setText(id, v) { const el = document.getElementById(id); if (el) el.textContent = v; }

  function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
  }

  function isThisWeek(dateStr) {
    const d = new Date(dateStr);
    const now = new Date();
    const weekStart = new Date(now);
    weekStart.setDate(now.getDate() - now.getDay() + 1);
    weekStart.setHours(0, 0, 0, 0);
    const weekEnd = new Date(weekStart);
    weekEnd.setDate(weekStart.getDate() + 6);
    return d >= weekStart && d <= weekEnd;
  }

  function isThisMonth(dateStr) {
    const d = new Date(dateStr);
    const now = new Date();
    return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
  }

  function truncate(text, len) {
    if (!text) return '—';
    return text.length > len ? text.slice(0, len) + '…' : text;
  }

  async function load() {
    show('rjLoading'); hide('rjEntriesList'); hide('rjEmpty');
    try {
      const r = await callAPI('/staff/reflection-journal', 'GET');
      entries = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      hide('rjLoading');

      const thisWeek = entries.filter(e => isThisWeek(e.date || e.entry_date || e.created_at)).length;
      const thisMonth = entries.filter(e => isThisMonth(e.date || e.entry_date || e.created_at)).length;
      setText('rjStatTotal', entries.length);
      setText('rjStatThisWeek', thisWeek);
      setText('rjStatThisMonth', thisMonth);

      if (!entries.length) { show('rjEmpty'); return; }
      renderEntries(entries);
      show('rjEntriesList');
    } catch (e) {
      hide('rjLoading');
      show('rjEmpty');
      console.error('reflection_journal load error', e);
    }
  }

  function renderEntries(entries) {
    const list = document.getElementById('rjEntriesList');
    if (!list) return;
    list.innerHTML = entries.map(e => {
      const date = e.date || e.entry_date || e.created_at;
      const lessonClass = e.lesson_class || e.class || e.lesson || '';
      const wentWell = e.went_well || e.what_went_well || '';
      const improve = e.improve || e.what_to_improve || e.what_could_be_improved || '';
      const nextSteps = e.next_steps || e.actions || '';
      return `
        <div class="col-md-6 col-lg-4">
          <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-2">
              <span class="fw-semibold small"><i class="bi bi-calendar me-1 text-muted"></i>${formatDate(date)}</span>
              <span class="badge bg-primary-subtle text-primary border border-primary small">${lessonClass || 'General'}</span>
            </div>
            <div class="card-body small">
              ${wentWell ? `<p class="mb-1"><span class="text-success fw-semibold"><i class="bi bi-check-circle me-1"></i>Went well:</span> ${truncate(wentWell, 120)}</p>` : ''}
              ${improve ? `<p class="mb-1"><span class="text-warning fw-semibold"><i class="bi bi-arrow-up-circle me-1"></i>Improve:</span> ${truncate(improve, 120)}</p>` : ''}
              ${nextSteps ? `<p class="mb-0"><span class="text-primary fw-semibold"><i class="bi bi-arrow-right-circle me-1"></i>Next steps:</span> ${truncate(nextSteps, 100)}</p>` : ''}
            </div>
          </div>
        </div>`;
    }).join('');
  }

  function showWriteModal() {
    setVal('rjDate', new Date().toISOString().slice(0, 10));
    setVal('rjLessonClass', '');
    setVal('rjWentWell', '');
    setVal('rjImprove', '');
    setVal('rjNextSteps', '');
    if (!modal) modal = new bootstrap.Modal(document.getElementById('rjModal'));
    modal.show();
  }

  async function saveEntry() {
    const date = val('rjDate');
    const lesson_class = val('rjLessonClass');
    if (!date || !lesson_class) {
      showNotification('Date and lesson/class are required.', 'warning');
      return;
    }

    const body = {
      date,
      lesson_class,
      went_well: val('rjWentWell'),
      improve: val('rjImprove'),
      next_steps: val('rjNextSteps'),
    };

    try {
      await callAPI('/staff/reflection-journal', 'POST', body);
      showNotification('Journal entry saved.', 'success');
      if (modal) modal.hide();
      await load();
    } catch (e) {
      showNotification('Failed to save entry.', 'danger');
      console.error(e);
    }
  }

  function init() {
    const writeBtn = document.getElementById('rjWriteBtn');
    if (writeBtn) writeBtn.addEventListener('click', showWriteModal);

    const firstBtn = document.getElementById('rjFirstEntryBtn');
    if (firstBtn) firstBtn.addEventListener('click', showWriteModal);

    const saveBtn = document.getElementById('rjSaveBtn');
    if (saveBtn) saveBtn.addEventListener('click', saveEntry);

    load();
  }

  return { init, showWriteModal };
})();

document.addEventListener('DOMContentLoaded', () => reflectionJournalController.init());
