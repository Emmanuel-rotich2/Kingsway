/**
 * Staff Access Controller
 *
 * Canonical client-side capability context for Staff-domain pages.
 * Server-side permission checks remain authoritative.
 *
 * Uses window.API.staff.getAccessContext() from js/api.js.
 */
const StaffAccessController = {
    initialized: false,
    initializationPromise: null,

    state: {
        context: null,
    },

    legacyAliases: {
        'staff.directory.view': ['staff_view', 'manage_staff_view'],
        'staff.directory.manage': [
            'staff_create',
            'staff_edit',
            'staff_update',
            'manage_staff',
        ],
        'staff.teachers.view': ['staff_view', 'teachers_view'],
        'staff.non_teaching.view': ['staff_view'],
        'staff.attendance.view': [
            'attendance_staff_view',
            'attendance_staff_view_all',
        ],
        'staff.attendance.manage': [
            'attendance_staff_create',
            'attendance_staff_submit',
            'attendance_staff_edit',
        ],
        'staff.attendance.self': [
            'attendance_staff_view_own',
            'attendance_staff_edit_own',
        ],
        'staff.leave.request': ['leave_request', 'staff_leave_request'],
        'staff.leave.manage': ['leave_manage', 'staff_leave_manage'],
        'staff.leave.approve': ['leave_approve', 'staff_leave_approve'],
        'staff.appointments.view': ['staff_appointments_view'],
        'staff.appointments.approve': [
            'staff_appointments_approve',
            'staff_appointments_manage',
            'staff_lifecycle_approve',
        ],
        'staff.appointments.onboard': [
            'staff_onboarding_manage',
            'staff_create',
            'staff_update',
        ],
        'staff.onboarding.view': [
            'staff_onboarding_view',
            'staff_onboarding_manage',
            'staff_appointments_onboard',
        ],
        'staff.onboarding.manage': [
            'staff_onboarding_manage',
            'staff_appointments_onboard',
            'staff_create',
            'staff_update',
        ],
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
    },

    capabilityAliases: {
        'staff.directory.view': 'staff_directory_view',
        'staff.directory.manage': 'staff_directory_manage',
        'staff.teachers.view': 'teachers_view',
        'staff.non_teaching.view': 'non_teaching_view',
        'staff.lifecycle.view': 'staff_lifecycle_view',
        'staff.appointments.view': 'staff_appointments_view',
        'staff.appointments.approve': 'staff_appointments_approve',
        'staff.appointments.onboard': 'staff_appointments_onboard',
        'staff.onboarding.view': 'staff_onboarding_view',
        'staff.onboarding.manage': 'staff_onboarding_manage',
        'staff.attendance.manage': 'attendance_manage',
        'staff.attendance.self': 'attendance_self',
        'staff.leave.manage': 'leave_manage',
        'staff.leave.approve': 'leave_approve',
        'staff.payroll.manage': 'payroll_manage',
        'staff.payroll.approve': 'payroll_approve',
        'staff.payslip.self': 'payslip_self',
        'staff.id_cards.manage': 'id_cards_manage',
        'staff.roles.manage': 'role_assignments_manage',
        'staff.teaching_assignments.manage': 'teaching_assignments_manage',
        'staff.performance.view': 'staff_performance_view',
    },

    async init(force = false) {
        if (this.initializationPromise && !force) {
            return this.initializationPromise;
        }

        this.initializationPromise = this._initialize(force);

        try {
            return await this.initializationPromise;
        } finally {
            this.initializationPromise = null;
        }
    },

    async _initialize(force = false) {
        if (this.initialized && !force) {
            return this.state.context;
        }

        if (window.AuthContext?.ready) {
            await window.AuthContext.ready();
        }

        if (!window.AuthContext?.isAuthenticated?.()) {
            window.location.replace(`${window.APP_BASE || ''}/index.php`);
            return null;
        }

        if (!window.API?.staff?.getAccessContext) {
            throw new Error('Staff access-context API is unavailable.');
        }

        try {
            const response = await window.API.staff.getAccessContext();
            this.state.context = this.normalizeContext(response);
        } catch (error) {
            console.error('[StaffAccessController] Access context failed:', error);

            // UI fallback only. Every Staff endpoint still enforces permissions.
            this.state.context = {
                permissions: Array.from(this.localPermissions()),
                roles: [],
                capabilities: {},
                load_error: error?.message || 'Unable to load staff access context',
            };
        }

        this.initialized = true;
        this.apply(document);

        document.dispatchEvent(new CustomEvent('staff-access-ready', {
            detail: this.state.context,
        }));

        return this.state.context;
    },

    normalizePermission(value) {
        if (typeof value === 'string') {
            return value.trim();
        }

        if (!value || typeof value !== 'object') {
            return '';
        }

        return String(
            value.code
            || value.name
            || value.permission_code
            || '',
        ).trim();
    },

    normalizeContext(response) {
        const payload = response?.data?.data || response?.data || response || {};

        return {
            ...payload,
            permissions: Array.isArray(payload.permissions)
                ? payload.permissions
                    .map((permission) => this.normalizePermission(permission))
                    .filter(Boolean)
                : [],
            roles: Array.isArray(payload.roles) ? payload.roles : [],
            capabilities: payload.capabilities
                && typeof payload.capabilities === 'object'
                ? payload.capabilities
                : {},
        };
    },

    localPermissions() {
        const values = [];

        try {
            const user = window.AuthContext?.getUser?.() || {};
            const userPermissions = user.effective_permissions || user.permissions || [];

            if (Array.isArray(userPermissions)) {
                values.push(...userPermissions);
            }

            const storedPermissions = JSON.parse(
                localStorage.getItem('user_permissions') || '[]',
            );

            if (Array.isArray(storedPermissions)) {
                values.push(...storedPermissions);
            }
        } catch (error) {
            console.warn(
                '[StaffAccessController] Local permission read failed:',
                error,
            );
        }

        return new Set(
            values
                .map((permission) => this.normalizePermission(permission))
                .filter(Boolean),
        );
    },

    can(permission) {
        if (!permission) {
            return true;
        }

        const requestedPermissions = String(permission)
            .split(',')
            .map((value) => value.trim())
            .filter(Boolean);

        const permissions = new Set([
            ...(this.state.context?.permissions || []),
            ...this.localPermissions(),
        ]);

        if (permissions.has('*')) {
            return true;
        }

        return requestedPermissions.some((permissionCode) => {
            const capability = this.capabilityAliases[permissionCode];

            if (
                capability
                && this.state.context?.capabilities?.[capability] === true
            ) {
                return true;
            }

            if (permissions.has(permissionCode)) {
                return true;
            }

            return (this.legacyAliases[permissionCode] || [])
                .some((alias) => permissions.has(alias));
        });
    },

    async require(
        permission,
        message = 'You do not have permission to access this staff workflow.',
    ) {
        await this.init();

        if (this.can(permission)) {
            return true;
        }

        const host = document.querySelector('[data-staff-page]')
            || document.querySelector('.container-fluid')
            || document.body;

        host.innerHTML = `
            <div class="alert alert-danger m-4" role="alert">
                <h5 class="alert-heading">Access denied</h5>
                <p class="mb-0">${this.escapeHtml(message)}</p>
            </div>
        `;

        return false;
    },

    apply(root = document) {
        if (!root?.querySelectorAll) {
            return;
        }

        root.querySelectorAll('[data-permission]').forEach((element) => {
            const allowed = this.can(element.dataset.permission);

            element.hidden = !allowed;
            element.setAttribute('aria-hidden', allowed ? 'false' : 'true');

            // Permission handling may disable a denied control. It deliberately
            // never enables controls because page controllers own workflow state.
            if (!allowed && 'disabled' in element) {
                element.disabled = true;
                element.dataset.permissionDisabled = 'true';
            } else if (allowed && element.dataset.permissionDisabled === 'true') {
                // Remove only the permission marker. The page controller remains
                // the sole owner of whether an allowed control is enabled.
                delete element.dataset.permissionDisabled;
            }
        });
    },

    escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = String(value ?? '');
        return element.innerHTML;
    },

    getContext() {
        return this.state.context;
    },
};

window.StaffAccessController = StaffAccessController;
window.StaffAccess = StaffAccessController;
