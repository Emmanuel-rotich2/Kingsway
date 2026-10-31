/** Weekly meal-plan grid using CateringController reads and the supported plan write contract. */
const menuPlanningController = {
  state: { plans: [], weekStart: '', request: 0 },
  esc(value) { return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[c]); },
  iso(date) { return date.toISOString().slice(0, 10); },
  monday(value = this.iso(new Date())) { const date = new Date(`${value}T12:00:00`); date.setDate(date.getDate() - ((date.getDay() + 6) % 7)); return this.iso(date); },
  async load() {
    const request = ++this.state.request;
    const start = this.monday(document.getElementById('weekSelect')?.value || this.iso(new Date()));
    this.state.weekStart = start;
    const endDate = new Date(`${start}T12:00:00`); endDate.setDate(endDate.getDate() + 6); const end = this.iso(endDate);
    const cells = [...document.querySelectorAll('.menu-cell')];
    cells.forEach((cell) => { cell.innerHTML = '<span class="spinner-border spinner-border-sm" aria-label="Loading"></span>'; });
    try {
      const response = await window.API.catering.getMenu({ date_from: start, date_to: end });
      if (request !== this.state.request) return;
      const payload = response?.data?.data ?? response?.data ?? response ?? [];
      this.state.plans = Array.isArray(payload) ? payload : [];
      this.render();
    } catch (error) {
      if (request !== this.state.request) return;
      cells.forEach((cell) => { cell.innerHTML = `<span class="text-danger">${this.esc(error?.message || 'Meal plans could not be loaded.')}</span>`; });
    }
  },
  render() {
    const filter = document.getElementById('mealTypeFilter')?.value || '';
    const weekStart = new Date(`${this.state.weekStart}T12:00:00`);
    for (const cell of document.querySelectorAll('.menu-cell')) {
      const day = cell.dataset.day; const mealType = cell.dataset.meal === 'supper' ? 'dinner' : cell.dataset.meal;
      const date = new Date(weekStart); date.setDate(date.getDate() + ['sunday','monday','tuesday','wednesday','thursday','friday','saturday'].indexOf(day));
      const planDate = this.iso(date);
      const plan = this.state.plans.find((row) => String(row.plan_date).slice(0, 10) === planDate && row.meal_type === mealType);
      const hidden = filter && filter !== cell.dataset.meal && !(filter === 'supper' && mealType === 'dinner');
      cell.classList.toggle('d-none', Boolean(hidden));
      cell.innerHTML = plan ? `<div class="menu-content"><strong>${this.esc(plan.menu_item || 'Meal plan')}</strong><div class="small">Planned: ${Number(plan.planned_servings || 0).toLocaleString()}</div><div class="small text-muted">${this.esc(plan.status || 'planned')}</div>${plan.notes ? `<div class="small mt-1">${this.esc(plan.notes)}</div>` : ''}</div>` : '<small class="text-muted">No plan · click to add</small>';
      cell.dataset.planDate = planDate;
      cell.dataset.planMealType = mealType;
    }
    const range = document.getElementById('weekRange'); if (range) { const end = new Date(weekStart); end.setDate(end.getDate() + 6); range.textContent = `${this.esc(this.state.weekStart)} – ${this.esc(this.iso(end))}`; }
  },
  openCell(cell) {
    const modal = document.getElementById('menuModal'); if (!modal) return;
    const plan = this.state.plans.find((row) => String(row.plan_date).slice(0, 10) === cell.dataset.planDate && row.meal_type === cell.dataset.planMealType);
    document.getElementById('menuId').value = plan?.id || '';
    document.getElementById('menuDay').value = cell.dataset.planDate;
    document.getElementById('menuMealType').value = cell.dataset.planMealType;
    document.getElementById('dayDisplay').value = `${cell.dataset.day} · ${cell.dataset.planDate}`;
    document.getElementById('mealDisplay').value = cell.dataset.planMealType;
    document.getElementById('plannedServings').value = plan?.planned_servings ?? '';
    document.getElementById('menuNotes').value = plan?.notes || '';
    window.bootstrap?.Modal?.getOrCreateInstance(modal).show();
  },
  async save() {
    const form = document.getElementById('menuForm'); if (!form?.reportValidity()) return;
    const payload = {
      date: document.getElementById('menuDay').value,
      meal_type: document.getElementById('menuMealType').value,
      expected_count: Number(document.getElementById('plannedServings').value || 0),
      notes: document.getElementById('menuNotes').value.trim() || null,
    };
    try {
      await window.callAPI('/api/students/catering-menu-plan', 'POST', payload);
      window.showNotification?.('Meal plan saved.', 'success');
      window.bootstrap?.Modal?.getInstance(document.getElementById('menuModal'))?.hide();
      await this.load();
    } catch (error) { window.showNotification?.(error?.message || 'Meal plan could not be saved.', 'error'); }
  },
  init() {
    const select = document.getElementById('weekSelect');
    if (select) {
      const today = new Date(); const current = this.monday();
      select.innerHTML = Array.from({ length: 16 }, (_, index) => { const date = new Date(`${current}T12:00:00`); date.setDate(date.getDate() - (8 - index) * 7); const value = this.iso(date); return `<option value="${value}" ${value === current ? 'selected' : ''}>Week of ${value}</option>`; }).join('');
      select.addEventListener('change', () => this.load());
    }
    document.getElementById('mealTypeFilter')?.addEventListener('change', () => this.render());
    document.getElementById('addMenuBtn')?.addEventListener('click', () => this.openCell(document.querySelector('.menu-cell')));
    document.getElementById('saveMenuBtn')?.addEventListener('click', () => this.save());
    document.querySelectorAll('.menu-cell').forEach((cell) => cell.addEventListener('click', () => this.openCell(cell)));
    document.getElementById('printMenuBtn')?.addEventListener('click', () => { if (!window.AuthContext?.canPrint || window.AuthContext.canPrint('catering')) window.print(); });
    if (window.AuthContext?.ready) window.AuthContext.ready().then(() => this.load()); else this.load();
    window.APIRealtime?.register?.('menu_planning', { refresh: () => this.load() }, ['catering']);
  },
};
window.menuPlanningController = menuPlanningController;
document.addEventListener('DOMContentLoaded', () => menuPlanningController.init());
