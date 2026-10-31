/**
 * External bootstraps for role-routed page shells.
 *
 * Soft navigation intentionally does not execute inline scripts from page
 * fragments. Keep these PageShell maps in one persistent script so the same
 * routes initialize after a full load and after fragment navigation.
 */
(function (window, document) {
  'use strict';

  const routeOptions = {
    manage_boarding: {
      loadingId: 'boarding-loading', contentId: 'boarding-content',
      templateDir: '/pages/boarding/', module: 'Boarding', scriptSrc: '/js/pages/boarding.js',
      levels: [
        { file: 'admin_boarding.php', test: () => PageShell.hasAny(['boarding_manage', 'boarding_admin', 'boarding_delete']) || PageShell.hasRole(['system_administrator', 'director', 'director_owner', 'headteacher', 'school_administrator']) },
        { file: 'manager_boarding.php', test: () => PageShell.hasAny(['boarding_edit', 'boarding_assign', 'boarding_roll_call']) || PageShell.hasRole(['boarding_master', 'matron_housemother', 'deputy_head_discipline', 'deputy_head_academic']) },
        { file: 'operator_boarding.php', test: () => PageShell.hasAny(['boarding_view', 'boarding_view_all']) || PageShell.hasRole(['class_teacher', 'subject_teacher', 'school_counselor_chaplain', 'support_staff']) },
        { file: 'viewer_boarding.php', test: () => PageShell.hasAny(['boarding_view_own']) || PageShell.hasRole(['parent', 'student']) },
      ],
    },
    manage_finance: {
      loadingId: 'manage-finance-loading', contentId: 'manage-finance-content',
      templateDir: '/pages/finance/', module: 'Finance', scriptSrc: '/js/pages/finance.js',
      afterLoad: (container) => {
        if (!PageShell.hasRole(['director', 'director_owner']) || !container) return;
        container.querySelectorAll('[data-hide-for-director="true"]').forEach((el) => el.remove());
        const heading = container.querySelector('h1, h2, h3');
        if (heading) heading.textContent = 'Finance Oversight';
      },
      levels: [
        { file: 'manager_finance.php', test: () => PageShell.hasRole(['director', 'director_owner']) },
        { file: 'admin_finance.php', test: () => PageShell.hasAny(['finance_manage', 'finance_admin', 'finance_delete', 'finance_approve']) || PageShell.hasRole(['system_administrator', 'headteacher', 'school_administrator']) },
        { file: 'manager_finance.php', test: () => PageShell.hasAny(['finance_edit', 'finance_create', 'finance_approve_payments']) || PageShell.hasRole(['school_accountant', 'accountant', 'bursar', 'finance_manager']) },
        { file: 'operator_finance.php', test: () => PageShell.hasAny(['finance_view', 'finance_view_all']) || PageShell.hasRole(['class_teacher', 'subject_teacher', 'intern_student_teacher', 'teacher']) },
        { file: 'viewer_finance.php', test: () => PageShell.hasAny(['finance_view_own', 'fees_view']) || PageShell.hasRole(['parent', 'student']) },
      ],
    },
    manage_transport: {
      loadingId: 'transport-loading', contentId: 'transport-content',
      templateDir: '/pages/transport/', module: 'Transport', scriptSrc: '/js/pages/transport_admin.js',
      levels: [
        { file: 'admin_transport.php', test: () => PageShell.hasAny(['transport_manage', 'transport_admin', 'transport_delete']) || PageShell.hasRole(['system_administrator', 'director', 'director_owner', 'headteacher', 'school_administrator']) },
        { file: 'manager_transport.php', test: () => PageShell.hasAny(['transport_edit', 'transport_create', 'transport_assign']) || PageShell.hasRole(['driver', 'school_administrative_officer']) },
        { file: 'operator_transport.php', test: () => PageShell.hasAny(['transport_view', 'transport_view_all']) || PageShell.hasRole(['class_teacher', 'support_staff']) },
        { file: 'viewer_transport.php', test: () => PageShell.hasAny(['transport_view_own']) || PageShell.hasRole(['parent', 'student']) },
      ],
    },
    manage_fee_structure: {
      loadingId: 'fee-structure-loading', contentId: 'fee-structure-content',
      templateDir: '/pages/fee_structure/', module: 'Fee Structure',
      afterLoad: async (contentEl, match) => {
        const scripts = {
          'admin_fee_structure.php': ['js/components/fee_structure_matrix.js', 'js/pages/fee_structure_admin.js'],
          'accountant_fee_structure.php': ['js/components/fee_structure_matrix.js', 'js/pages/fee_structure_accountant.js'],
          'viewer_fee_structure.php': ['js/components/fee_structure_matrix.js', 'js/pages/fee_structure_viewer.js'],
        }[match.file] || [];
        for (const path of scripts) {
          const src = `${window.APP_BASE || ''}/${path}`;
          const requested = new URL(src, location.href);
          const existing = [...document.scripts].find((script) => {
            if (!script.src) return false;
            try {
              const loaded = new URL(script.src, location.href);
              return loaded.origin === requested.origin && loaded.pathname === requested.pathname;
            } catch (_) {
              return false;
            }
          });
          if (existing) {
            if (existing.dataset.kingswayScriptState === 'loading') {
              await new Promise((resolve, reject) => {
                existing.addEventListener('load', resolve, { once: true });
                existing.addEventListener('error', () => reject(new Error(`Script load failed: ${src}`)), { once: true });
              });
            }
            continue;
          }
          const script = document.createElement('script');
          script.src = src;
          script.async = false;
          script.dataset.kingswayScriptState = 'loading';
          await new Promise((resolve, reject) => {
            script.addEventListener('load', () => {
              script.dataset.kingswayScriptState = 'loaded';
              resolve();
            }, { once: true });
            script.addEventListener('error', () => {
              script.dataset.kingswayScriptState = 'error';
              reject(new Error(`Script load failed: ${src}`));
            }, { once: true });
            document.body.appendChild(script);
          });
        }
      },
      levels: [
        { file: 'admin_fee_structure.php', test: () => PageShell.hasAny(['fee_structure_manage', 'fee_structure_admin', 'fee_structure_delete', 'fee_structure_create']) || PageShell.hasRole(['system_administrator', 'director', 'director_owner', 'school_administrator', 'school_admin']) },
        { file: 'accountant_fee_structure.php', test: () => PageShell.hasAny(['fee_structure_edit', 'fee_structure_view_financial']) || PageShell.hasRole(['school_accountant', 'accountant', 'bursar']) },
        { file: 'viewer_fee_structure.php', test: () => PageShell.hasAny(['fee_structure_view', 'fee_structure_view_own', 'fees_view']) || PageShell.hasRole(['headteacher', 'deputy_head_academic', 'deputy_head_discipline', 'class_teacher', 'subject_teacher', 'parent', 'student']) },
      ],
    },
    manage_library: {
      loadingId: 'library-loading', contentId: 'library-content',
      templateDir: '/pages/library/', module: 'Library',
      afterLoad: () => { if (typeof window.libraryController !== 'undefined') window.libraryController.init(); },
      levels: [
        { file: 'admin_library.php', test: () => PageShell.hasAny(['library.manage', 'library.issue', 'library.create']) },
        { file: 'viewer_library.php', test: () => PageShell.hasAny(['library.view', 'library_view']) || PageShell.hasRole(['parent', 'student']) },
      ],
    },
    all_staff: {
      loadingId: 'staff-list-loading', contentId: 'staff-list-content',
      templateDir: '/pages/staff/', module: 'Staff',
      levels: [
        { file: 'admin_staff.php', test: () => PageShell.hasAny(['staff_manage', 'staff_admin', 'staff_delete', 'staff_view_all']) || PageShell.hasRole(['system_administrator', 'director', 'director_owner', 'school_administrator', 'school_administrative_officer']) },
        { file: 'manager_staff.php', test: () => PageShell.hasAny(['staff_edit', 'staff_view_department']) || PageShell.hasRole(['headteacher', 'deputy_head_academic', 'deputy_head_discipline', 'hod_talent_development']) },
        { file: 'operator_staff.php', test: () => PageShell.hasAny(['staff_view', 'staff_view_directory']) || PageShell.hasRole(['class_teacher', 'subject_teacher', 'intern_student_teacher']) },
        { file: 'viewer_staff.php', test: () => PageShell.hasAny(['staff_view_own', 'staff_view_contacts']) || PageShell.hasRole(['parent', 'student', 'support_staff']) },
      ],
    },
  };

  function boot(route) {
    const options = routeOptions[String(route || '')];
    if (options && window.PageShell?.loadRoleTemplate) window.PageShell.loadRoleTemplate(options);
  }

  window.addEventListener('kingsway:page-loaded', (event) => boot(event.detail?.route));
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => boot(window.REQUESTED_ROUTE));
  } else {
    boot(window.REQUESTED_ROUTE);
  }
})(window, document);
