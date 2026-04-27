/**
 * Year-End Rollover Controller
 * API: GET  /academic/year-rollover-status
 *      POST /academic/year-rollover   { step: 'fee_carryover' | 'create_new_year' | ... }
 */
const yearRolloverController = {
  _status: null,
  _running: false,

  // Ordered rollover steps with descriptions and RBAC guards
  _steps: [
    {
      key:   'fee_carryover',
      label: 'Fee Carryover',
      desc:  'Copy outstanding fee balances to new year. Generate credit notes for overpayments.',
      icon:  'bi-cash-coin',
      color: 'warning',
    },
    {
      key:   'staff_reassignment',
      label: 'Staff Reassignment Review',
      desc:  'Mark current class assignments as completed. Admin will confirm new year assignments via Manage Staff.',
      icon:  'bi-people-fill',
      color: 'info',
    },
    {
      key:   'create_new_year',
      label: 'Create New Academic Year',
      desc:  'Create the next academic year record and 3 term records (status: planning/upcoming).',
      icon:  'bi-calendar-plus',
      color: 'primary',
    },
    {
      key:   'archive_old_year',
      label: 'Archive Current Year',
      desc:  'Set current academic year to archived. All historical data is preserved.',
      icon:  'bi-archive-fill',
      color: 'secondary',
    },
    {
      key:   'activate_new_year',
      label: 'Activate New Year',
      desc:  'Set the new year as current and activate Term 1. Finance can now bill students for the new year.',
      icon:  'bi-play-circle-fill',
      color: 'success',
    },
  ],

  init: async function () {
    if (!AuthContext.isAuthenticated()) return;
    this._canRollover = AuthContext.hasPermission('academic.manage') || AuthContext.hasPermission('system.admin');
    await this._loadStatus();
  },

  _loadStatus: async function () {
    try {
      const r = await callAPI('/academic/year-rollover-status', 'GET');
      this._status = r?.data || r;
      this._renderPreflight();
      this._renderSteps();
      this._renderLog();
    } catch (e) {
      showNotification(e.message || 'Failed to load rollover status.', 'danger');
    }
  },

  _renderPreflight: function () {
    const s = this._status;
    if (!s) return;

    const yearBadge = document.getElementById('yrCurrentYearBadge');
    if (yearBadge) yearBadge.textContent = s.current_year?.year_name || '—';

    const setCheck = (iconId, statusId, ok, trueText, falseText) => {
      const icon = document.getElementById(iconId);
      const stat = document.getElementById(statusId);
      if (icon) { icon.style.color = ok ? 'green' : 'red'; icon.className = 'bi ' + (ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill') + ' fs-3 mb-1'; }
      if (stat) stat.textContent = ok ? trueText : falseText;
    };

    setCheck('yrTermsIcon',      'yrTermsStatus',      s.all_terms_complete,          'All Closed ✓', 'Incomplete Terms');
    setCheck('yrResultsIcon',    'yrResultsStatus',    s.pending_results === 0,       'Finalised ✓',  `${s.pending_results} Pending`);
    setCheck('yrPromotionsIcon', 'yrPromotionsStatus', s.pending_promotions === 0,    'Done ✓',       `${s.pending_promotions} Pending`);

    // Fees with students outstanding — warning, not blocking
    const feesIcon = document.getElementById('yrFeesIcon');
    const feesStatus = document.getElementById('yrFeesStatus');
    if (feesIcon) {
      feesIcon.style.color = s.students_with_fees > 0 ? 'orange' : 'green';
      feesIcon.className = 'bi bi-cash-coin fs-3 mb-1';
    }
    if (feesStatus) feesStatus.textContent = s.students_with_fees > 0 ? `${s.students_with_fees} students` : 'All Cleared ✓';
  },

  _renderSteps: function () {
    const container = document.getElementById('yrStepsList');
    if (!container) return;

    const s = this._status;
    const completedSteps = (s?.rollover_log || []).filter(l => l.status === 'completed').map(l => l.step);
    const ready = s?.ready_for_rollover;

    if (!this._canRollover) {
      container.innerHTML = '<div class="list-group-item text-center text-muted py-4"><i class="bi bi-lock me-2"></i>You do not have permission to execute rollover steps.</div>';
      return;
    }

    container.innerHTML = this._steps.map((step, i) => {
      const done = completedSteps.includes(step.key);
      const prevDone = i === 0 ? true : completedSteps.includes(this._steps[i - 1].key);
      const canRun  = !done && prevDone && (step.key === 'fee_carryover' ? ready : true);

      return `<div class="list-group-item d-flex align-items-center gap-3 py-3">
        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
             style="width:42px;height:42px;background:var(--bs-${done ? 'success' : step.color}-bg-subtle)">
          <i class="bi ${step.icon} text-${done ? 'success' : step.color} fs-5"></i>
        </div>
        <div class="flex-grow-1">
          <div class="fw-semibold">${step.label}</div>
          <div class="text-muted small">${step.desc}</div>
        </div>
        <div class="flex-shrink-0">
          ${done
            ? `<span class="badge bg-success"><i class="bi bi-check2 me-1"></i>Done</span>`
            : canRun
              ? `<button class="btn btn-sm btn-${step.color}" onclick="yearRolloverController.runStep('${step.key}')">
                   <i class="bi bi-play-fill me-1"></i>Run
                 </button>`
              : `<span class="badge bg-secondary">Waiting</span>`
          }
        </div>
      </div>`;
    }).join('');

    // Warning if not ready for first step
    if (!ready) {
      const warning = document.createElement('div');
      warning.className = 'list-group-item list-group-item-warning small';
      warning.innerHTML = `<i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Not ready:</strong> All terms must be closed, results finalised, and student promotions completed
        before starting the rollover. Use <strong>Term Transition</strong> and <strong>Student Promotion</strong> pages first.`;
      container.prepend(warning);
    }
  },

  _renderLog: function () {
    const tbody = document.getElementById('yrLogBody');
    if (!tbody) return;
    const log = (this._status?.rollover_log || []);
    if (!log.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">No rollover activity yet.</td></tr>';
      return;
    }
    const colors = {completed:'success',failed:'danger',in_progress:'warning',pending:'secondary',skipped:'info'};
    tbody.innerHTML = log.map(l => `<tr>
      <td>${this._esc(l.step || '—')}</td>
      <td><span class="badge bg-${colors[l.status] || 'secondary'}">${l.status || '—'}</span></td>
      <td class="text-center">${l.students_promoted || '—'}</td>
      <td class="text-center">${l.students_retained || '—'}</td>
      <td class="text-center">${l.fee_balances_carried || '—'}</td>
      <td class="text-center">${l.credit_notes_created || '—'}</td>
      <td class="small">${l.performed_at ? new Date(l.performed_at).toLocaleString('en-KE') : '—'}</td>
    </tr>`).join('');
  },

  runStep: async function (step) {
    if (this._running) return;

    const stepDef = this._steps.find(s => s.key === step);
    const confirmMsg = `Run step: "${stepDef?.label || step}"?\n\nThis action modifies database records. Ensure all prerequisite steps are complete.`;
    if (!confirm(confirmMsg)) return;

    this._running = true;
    try {
      const r = await callAPI('/academic/year-rollover', 'POST', { step });
      const result = r?.data || r;
      let msg = `Step "${stepDef?.label || step}" completed.`;
      if (result.fee_balances_carried)   msg += ` ${result.fee_balances_carried} balances carried.`;
      if (result.credit_notes_created)   msg += ` ${result.credit_notes_created} credit notes created.`;
      if (result.new_year_code)          msg += ` New year ${result.new_year_code} created.`;
      if (result.activated_year)         msg += ` Year ${result.activated_year} is now active.`;
      if (result.archived_year)          msg += ` Year ${result.archived_year} archived.`;
      if (result.note)                   msg += ' ' + result.note;
      showNotification(msg, 'success');
      await this._loadStatus();
    } catch (e) {
      showNotification(e.message || 'Step failed.', 'danger');
    } finally {
      this._running = false;
    }
  },

  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};
