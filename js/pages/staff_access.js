/**
 * Canonical client-side Staff-domain capability context.
 * Server-side controller checks remain authoritative; this file controls UI visibility only.
 */
window.StaffAccess = (() => {
  let context = null;
  let loading = null;

  const legacyAliases = {
    'staff.directory.view': ['staff_view', 'manage_staff_view'],
    'staff.directory.manage': ['staff_create', 'staff_edit', 'manage_staff'],
    'staff.teachers.view': ['staff_view', 'teachers_view'],
    'staff.non_teaching.view': ['staff_view'],
    'staff.attendance.view': ['attendance_staff_view', 'attendance_staff_view_all'],
    'staff.attendance.manage': ['attendance_staff_create', 'attendance_staff_submit', 'attendance_staff_edit'],
    'staff.attendance.self': ['attendance_staff_view_own', 'attendance_staff_edit_own'],
    'staff.leave.request': ['leave_request', 'staff_leave_request'],
    'staff.leave.manage': ['leave_manage', 'staff_leave_manage'],
    'staff.leave.approve': ['leave_approve', 'staff_leave_approve'],
    'staff.payroll.manage': ['payroll_manage', 'manage_payrolls'],
    'staff.payroll.approve': ['payroll_approve'],
    'staff.payroll.process': ['payroll_process'],
    'staff.payslip.self': ['payslip_view_own'],
    'staff.payslip.manage': ['payslip_view_all', 'payslip_generate'],
    'staff.id_cards.view': ['staff_id_card_view'],
    'staff.id_cards.manage': ['staff_id_card_generate'],
    'staff.roles.manage': ['roles_assign', 'staff_role_assign'],
    'staff.teaching_assignments.view': ['teacher_assignments_view'],
    'staff.teaching_assignments.manage': ['teacher_assignments_manage'],
    'staff.performance.view': ['staff_performance_view'],
    'staff.performance.manage': ['staff_performance_manage'],
  };

  function localPermissions() {
    const values = [];
    try {
      const user = window.AuthContext?.getUser?.() || {};
      values.push(...(user.effective_permissions || user.permissions || []));
      const stored = JSON.parse(localStorage.getItem('user_permissions') || '[]');
      values.push(...(Array.isArray(stored) ? stored : []));
    } catch (_) {}
    return new Set(values.map(p => typeof p === 'string' ? p : (p.code || p.name || '')).filter(Boolean));
  }

  async function init(force = false) {
    if (context && !force) return context;
    if (loading && !force) return loading;
    loading = (async () => {
      try {
        const data = await window.API.apiCall('/staff/access-context', 'GET');
        context = data?.data || data || {};
      } catch (error) {
        context = {
          permissions: [...localPermissions()],
          roles: [],
          capabilities: {},
          error: error?.message || 'Unable to load staff access context',
        };
      }
      apply(document);
      document.dispatchEvent(new CustomEvent('staff-access-ready', { detail: context }));
      return context;
    })();
    return loading;
  }

  function can(permission) {
    if (!permission) return true;
    const requested = String(permission).split(',').map(x => x.trim()).filter(Boolean);
    const permissions = new Set([...(context?.permissions || []), ...localPermissions()]);
    if (permissions.has('*')) return true;
    return requested.some(code => {
      if (permissions.has(code)) return true;
      return (legacyAliases[code] || []).some(alias => permissions.has(alias));
    });
  }

  async function require(permission, message = 'You do not have permission to access this staff workflow.') {
    await init();
    if (can(permission)) return true;
    const host = document.querySelector('[data-staff-page]') || document.querySelector('.container-fluid') || document.body;
    host.innerHTML = `<div class="alert alert-danger m-4"><h5 class="alert-heading">Access denied</h5><p class="mb-0">${escapeHtml(message)}</p></div>`;
    return false;
  }

  function apply(root = document) {
    root.querySelectorAll('[data-permission]').forEach(element => {
      const visible = can(element.dataset.permission);
      element.hidden = !visible;
      element.setAttribute('aria-hidden', visible ? 'false' : 'true');
      if ('disabled' in element) element.disabled = !visible;
    });
  }

  function escapeHtml(value) {
    const node = document.createElement('div');
    node.textContent = String(value ?? '');
    return node.innerHTML;
  }

  return { init, ready: init, can, require, apply, getContext: () => context };
})();
