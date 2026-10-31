/** Admin review of legacy academic content with unresolved school context. */
const academicContentReconciliationController = {
  items: [],
  esc(value) {
    const node = document.createElement('span');
    node.textContent = String(value ?? '');
    return node.innerHTML;
  },

  async init() {
    await window.AuthContext?.ready?.();
    if (window.AuthContext && !window.AuthContext.isAuthenticated()) {
      window.location.href = `${window.APP_BASE || ''}/index.php`;
      return;
    }
    document.getElementById('acrRefresh')?.addEventListener('click', () => this.load());
    await this.load();
  },

  async load() {
    const body = document.querySelector('#acrTable tbody');
    if (!body) return;
    body.innerHTML = '<tr><td colspan="5" class="text-center py-4">Loading reconciliation queue…</td></tr>';
    try {
      const response = await window.API.apiCall('/academic/legacy-content-reconciliation', 'GET');
      const payload = response?.data || response || {};
      this.items = Array.isArray(payload.items) ? payload.items : [];
      this.render();
    } catch (error) {
      console.error('Could not load academic content reconciliation queue:', error);
      body.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">${this.esc(error.message || 'Could not load reconciliation items.')}</td></tr>`;
    }
  },

  render() {
    const body = document.querySelector('#acrTable tbody');
    if (!body) return;
    if (!this.items.length) {
      body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No unresolved academic content.</td></tr>';
      return;
    }
    body.innerHTML = this.items.map((item, index) => {
      const candidates = Array.isArray(item.candidates) ? item.candidates : [];
      const weeks = Array.isArray(item.available_weeks) ? item.available_weeks : [];
      const contextOptions = candidates.map((candidate, candidateIndex) => `<option value="${candidateIndex}">${this.esc([candidate.class_name, candidate.stream_name].filter(Boolean).join(' — ') || 'Candidate context')}</option>`).join('');
      const weekOptions = weeks.map((week) => `<option value="${Number(week.id)}">Week ${this.esc(week.week_number)} (${this.esc(week.week_start)} – ${this.esc(week.week_end)})</option>`).join('');
      const contextControls = candidates.length
        ? `<select class="form-select form-select-sm mb-1" data-candidate>${contextOptions}</select><select class="form-select form-select-sm" data-week>${weekOptions}</select>`
        : '<span class="text-muted">No provable context available</span>';
      const canResolve = candidates.length > 0 && weeks.length > 0;
      return `<tr data-item-index="${index}"><td>${this.esc(String(item.content_type || 'content').replaceAll('_', ' '))}</td><td>${this.esc(item.title || item.content_title || `${item.content_type || 'Record'} #${item.content_id || item.id}`)}</td><td>${this.esc(item.reason || item.status || 'Legacy context requires review')}</td><td>${contextControls}</td><td><button class="btn btn-sm btn-success" type="button" data-resolve ${canResolve ? '' : 'disabled'}>Resolve</button></td></tr>`;
    }).join('');
    body.querySelectorAll('[data-resolve]').forEach((button) => button.addEventListener('click', (event) => this.resolve(event.currentTarget.closest('tr'))));
  },

  async resolve(row) {
    const item = this.items[Number(row?.dataset.itemIndex)];
    const candidateIndex = Number(row?.querySelector('[data-candidate]')?.value);
    const candidate = item?.candidates?.[candidateIndex];
    const weekId = Number(row?.querySelector('[data-week]')?.value || 0);
    if (!item || !candidate || !weekId) return;
    const payload = {
      queue_id: Number(item.id),
      stream_learning_area_id: Number(candidate.stream_learning_area_id),
      calendar_week_id: weekId,
    };
    if (item.content_type === 'lesson_plan') payload.scheme_of_work_id = Number(item.scheme_of_work_id || item.content_id);
    try {
      await window.API.apiCall('/academic/resolve-legacy-academic-content', 'POST', payload);
      window.showNotification?.('Academic content resolved.', 'success');
      await this.load();
    } catch (error) {
      window.showNotification?.(error.message || 'Could not resolve this content item.', 'error');
    }
  },
};

window.academicContentReconciliationController = academicContentReconciliationController;
document.addEventListener('DOMContentLoaded', () => academicContentReconciliationController.init().catch((error) => {
  console.error('Academic content reconciliation initialization failed:', error);
}));
