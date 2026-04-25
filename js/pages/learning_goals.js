/**
 * Learning Goals Controller
 * Personal learning objectives CRUD for intern placement.
 * API: GET/POST /staff/learning-goals, PUT/DELETE /staff/learning-goals/{id}
 */

const learningGoalsController = (() => {
  let modal = null;
  let allGoals = [];

  const CATEGORY_COLORS = {
    'Professional': 'primary',
    'Pedagogical': 'success',
    'Subject Knowledge': 'warning',
    'Classroom Management': 'info',
  };

  const STATUS_BADGES = {
    not_started: 'bg-secondary',
    in_progress: 'bg-warning text-dark',
    completed: 'bg-success',
  };

  function show(id) { const el = document.getElementById(id); if (el) el.style.display = ''; }
  function hide(id) { const el = document.getElementById(id); if (el) el.style.display = 'none'; }
  function val(id) { const el = document.getElementById(id); return el ? el.value.trim() : ''; }
  function setVal(id, v) { const el = document.getElementById(id); if (el) el.value = v || ''; }
  function setText(id, v) { const el = document.getElementById(id); if (el) el.textContent = v; }

  function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
  }

  function isOverdue(targetDate, status) {
    if (!targetDate || status === 'completed') return false;
    return new Date(targetDate) < new Date();
  }

  async function load() {
    show('lgLoading'); hide('lgGoalsList'); hide('lgEmpty');
    try {
      const r = await callAPI('/staff/learning-goals', 'GET');
      allGoals = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      hide('lgLoading');
      renderStats();
      if (!allGoals.length) { show('lgEmpty'); return; }
      renderGoals(allGoals);
      show('lgGoalsList');
    } catch (e) {
      hide('lgLoading');
      show('lgEmpty');
      console.error('learning_goals load error', e);
    }
  }

  function renderStats() {
    const total = allGoals.length;
    const inProgress = allGoals.filter(g => g.status === 'in_progress').length;
    const completed = allGoals.filter(g => g.status === 'completed').length;
    const overdue = allGoals.filter(g => isOverdue(g.target_date, g.status)).length;
    setText('lgStatTotal', total);
    setText('lgStatInProgress', inProgress);
    setText('lgStatCompleted', completed);
    setText('lgStatOverdue', overdue);
  }

  function renderGoals(goals) {
    const list = document.getElementById('lgGoalsList');
    if (!list) return;
    list.innerHTML = goals.map(g => {
      const color = CATEGORY_COLORS[g.category] || 'secondary';
      const statusBadge = STATUS_BADGES[g.status] || 'bg-secondary';
      const statusLabel = (g.status || 'not_started').replace(/_/g, ' ');
      const overdue = isOverdue(g.target_date, g.status);
      return `
        <div class="col-md-6 col-lg-4">
          <div class="card border-0 shadow-sm h-100${overdue ? ' border-start border-danger border-3' : ''}">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="badge bg-${color}-subtle text-${color} border border-${color}">${g.category || 'General'}</span>
                <span class="badge ${statusBadge} text-capitalize">${statusLabel}</span>
              </div>
              <p class="mb-2">${g.goal_text || g.goal || g.description || ''}</p>
              <small class="text-muted">
                <i class="bi bi-calendar me-1"></i>Target: ${formatDate(g.target_date)}
                ${overdue ? '<span class="text-danger ms-1">(Overdue)</span>' : ''}
              </small>
            </div>
            <div class="card-footer bg-transparent border-top-0 d-flex gap-2">
              <button class="btn btn-outline-primary btn-sm flex-fill" onclick="learningGoalsController.editGoal(${g.id})">
                <i class="bi bi-pencil me-1"></i>Edit
              </button>
              <button class="btn btn-outline-danger btn-sm" onclick="learningGoalsController.deleteGoal(${g.id})">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </div>
        </div>`;
    }).join('');
  }

  function showAddModal() {
    setVal('lgGoalId', '');
    setVal('lgGoalText', '');
    setVal('lgCategory', 'Professional');
    setVal('lgTargetDate', '');
    setVal('lgStatus', 'not_started');
    document.getElementById('lgModalLabel').textContent = 'Add Learning Goal';
    if (!modal) modal = new bootstrap.Modal(document.getElementById('lgModal'));
    modal.show();
  }

  function editGoal(id) {
    const g = allGoals.find(x => x.id == id);
    if (!g) return;
    setVal('lgGoalId', g.id);
    setVal('lgGoalText', g.goal_text || g.goal || g.description || '');
    setVal('lgCategory', g.category || 'Professional');
    setVal('lgTargetDate', g.target_date ? g.target_date.slice(0, 10) : '');
    setVal('lgStatus', g.status || 'not_started');
    document.getElementById('lgModalLabel').textContent = 'Edit Learning Goal';
    if (!modal) modal = new bootstrap.Modal(document.getElementById('lgModal'));
    modal.show();
  }

  async function saveGoal() {
    const id = val('lgGoalId');
    const goal_text = val('lgGoalText');
    if (!goal_text) { showNotification('Goal description is required.', 'warning'); return; }

    const body = {
      goal_text,
      category: val('lgCategory'),
      target_date: val('lgTargetDate') || null,
      status: val('lgStatus'),
    };

    try {
      if (id) {
        await callAPI(`/staff/learning-goals/${id}`, 'PUT', body);
        showNotification('Goal updated.', 'success');
      } else {
        await callAPI('/staff/learning-goals', 'POST', body);
        showNotification('Goal added.', 'success');
      }
      if (modal) modal.hide();
      await load();
    } catch (e) {
      showNotification('Failed to save goal. Please try again.', 'danger');
      console.error(e);
    }
  }

  async function deleteGoal(id) {
    if (!confirm('Delete this learning goal?')) return;
    try {
      await callAPI(`/staff/learning-goals/${id}`, 'DELETE');
      showNotification('Goal deleted.', 'success');
      await load();
    } catch (e) {
      showNotification('Failed to delete goal.', 'danger');
      console.error(e);
    }
  }

  function init() {
    const addBtn = document.getElementById('lgAddBtn');
    if (addBtn) addBtn.addEventListener('click', showAddModal);

    const firstEntryBtn = document.getElementById('lgFirstEntryBtn');
    if (firstEntryBtn) firstEntryBtn.addEventListener('click', showAddModal);

    const saveBtn = document.getElementById('lgSaveBtn');
    if (saveBtn) saveBtn.addEventListener('click', saveGoal);

    load();
  }

  return { init, showAddModal, editGoal, deleteGoal };
})();

document.addEventListener('DOMContentLoaded', () => learningGoalsController.init());
