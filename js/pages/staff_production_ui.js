/**
 * Staff Management Controller
 * Handles manage_staff.php
 * Uses existing api.js JWT authentication
 */
const StaffProductionUI = {
    initialized: false,
    initializationPromise: null,
    eventsBound: false,
    staffLoadPromise: null,
    departmentsLoadPromise: null,
    rolesLoadPromise: null,

    state: {
        staff: [],
        filteredStaff: [],
        departments: [],
        roles: [],
        currentImportBatch: null,
        pagination: null,
        currentFilters: {
            search: '',
            department: null,
            staff_type_id: null,
            status: null
        }
    },

    async init() {
        if (this.initializationPromise) {
            return this.initializationPromise;
        }

        this.initializationPromise = this._initialize();

        try {
            await this.initializationPromise;
            return this;
        } catch (error) {
            this.initializationPromise = null;
            throw error;
        }
    },

    async _initialize() {
        if (this.initialized) {
            return this;
        }

        console.log('[StaffProductionUI] Initializing...');

        if (window.AuthContext?.ready) {
            await window.AuthContext.ready();
        } else if (window.AuthContext?.initialize) {
            await window.AuthContext.initialize();
        }

        if (window.StaffAccess?.init) {
            await window.StaffAccess.init();
        }

        if (!window.API?.staff) {
            throw new Error('Staff API is unavailable.');
        }

        if (!window.AuthContext?.isAuthenticated?.()) {
            this.showToast('Please log in to access this page', 'error', 'Authentication Required');
            window.setTimeout(() => {
                window.location.replace(`${window.APP_BASE || ''}/index.php`);
            }, 800);
            return this;
        }

        if (!this.canViewDirectory()) {
            this.showToast('You do not have permission to view staff', 'error', 'Access denied');
            this.renderAccessDenied();
            return this;
        }

        this.setupEventListeners();
        this.applyAccessUi();
        await this.loadInitialData();
        this.applyAccessUi();
        this.applyPageContext();

        this.initialized = true;
        console.log('[StaffProductionUI] Initialized successfully');

        return this;
    },

    canViewDirectory() {
        return (window.StaffAccess && StaffAccess.can('staff.directory.view')) || window.AuthContext?.canView?.('staff');
    },

    canManageDirectory() {
        return (window.StaffAccess && StaffAccess.can('staff.directory.manage')) || window.AuthContext?.canCreate?.('staff') || window.AuthContext?.canEdit?.('staff');
    },

    canDeleteDirectory() {
        return window.AuthContext?.canDelete?.('staff');
    },

    canExportDirectory() {
        return window.AuthContext?.canExport?.('staff');
    },

    showToast(message, type = 'info', title = 'Notification') {
        if (window.showNotification) {
            window.showNotification(message, type);
            return;
        }

        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type === 'success' ? 'success' : type === 'error' ? 'danger' : type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            <strong>${this.escapeHtml(title)}</strong> ${this.escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.insertBefore(alertDiv, document.body.firstChild);
        window.setTimeout(() => alertDiv.remove(), 4000);
    },

    renderAccessDenied() {
        const host = document.querySelector('[data-staff-directory-page]');
        if (!host) return;
        host.innerHTML = `
            <div class="alert alert-danger m-4">
                <h5 class="alert-heading">Access denied</h5>
                <p class="mb-0">You do not have permission to view staff.</p>
            </div>
        `;
    },

    applyAccessUi() {
        const addBtn = document.getElementById('addStaffBtn');
        if (addBtn) addBtn.hidden = !this.canManageDirectory();

        const exportBtn = document.getElementById('exportStaffBtn');
        if (exportBtn) exportBtn.hidden = !this.canExportDirectory();

        const importBtn = document.getElementById('importStaffBtn');
        if (importBtn) importBtn.hidden = !this.canManageDirectory();

        this.applyRoleLayout();

        if (window.StaffAccess) StaffAccess.apply(document);
    },

    getRoles() {
        const contextRoles = window.StaffAccess?.getContext?.().roles || [];
        const user = window.AuthContext?.getUser?.() || {};
        const userRoles = user.roles || user.role_names || (user.role ? [user.role] : []);
        return [...contextRoles, ...userRoles]
            .map(role => String(role.name || role.role_name || role).toLowerCase().replace(/_/g, ' '))
            .filter(Boolean);
    },

    hasRole(fragment) {
        return this.getRoles().some(role => role.includes(fragment));
    },

    getRoleMode() {
        if (this.canManageDirectory() || this.hasRole('school administrator') || this.hasRole('system administrator')) {
            return 'operations';
        }

        if (this.hasRole('director')) return 'director';
        if (this.hasRole('headteacher')) return 'headteacher';
        if (this.hasRole('deputy head')) return 'deputy';
        if (this.hasRole('accountant') || this.hasRole('bursar')) return 'finance';

        return 'directory';
    },

    getLayoutConfig() {
        const configs = {
            operations: {
                title: 'Staff Operations',
                description: 'Full HR directory with onboarding, employment, and record actions',
                cards: ['total', 'active', 'teaching', 'non_teaching'],
                columns: ['staff_no', 'name', 'department', 'type', 'position', 'contact', 'status', 'actions'],
                actions: ['view', 'edit', 'delete']
            },
            director: {
                title: 'Staff Oversight',
                description: 'Leadership view of staffing levels, appointments, and workforce status',
                cards: ['total', 'active', 'teaching', 'on_leave'],
                columns: ['name', 'department', 'type', 'position', 'contact', 'status', 'actions'],
                actions: ['view', 'performance', 'workload']
            },
            headteacher: {
                title: 'Teaching Staff Oversight',
                description: 'Academic leadership view focused on teachers, workload, attendance, and performance',
                cards: ['teaching', 'active', 'on_leave', 'departments'],
                columns: ['name', 'department', 'type', 'position', 'status', 'actions'],
                actions: ['view', 'performance', 'workload']
            },
            deputy: {
                title: 'Staff Oversight',
                description: 'Oversight view for teaching staff, performance, attendance, and discipline workflows',
                cards: ['teaching', 'active', 'on_leave'],
                columns: ['name', 'department', 'type', 'position', 'status', 'actions'],
                actions: ['view', 'performance']
            },
            finance: {
                title: 'Staff Payroll Directory',
                description: 'Payroll-focused staff view with employment and payment readiness context',
                cards: ['total', 'active', 'payroll_ready', 'missing_payroll'],
                columns: ['staff_no', 'name', 'department', 'position', 'payroll', 'status', 'actions'],
                actions: ['view']
            },
            directory: {
                title: 'Staff Directory',
                description: 'Read-only contact and department directory',
                cards: ['total', 'active'],
                columns: ['name', 'department', 'position', 'status'],
                actions: []
            }
        };

        return configs[this.getRoleMode()] || configs.directory;
    },

    applyPageChrome() {
        const config = this.getLayoutConfig();
        const header = document.querySelector('[data-staff-directory-page] .page-header h4');
        const description = document.querySelector('[data-staff-directory-page] .page-header p');
        if (header) {
            header.innerHTML = `<i class="fas fa-chalkboard-teacher me-2"></i>${this.escapeHtml(config.title)}`;
        }
        if (description) {
            description.textContent = config.description;
        }
    },

    applyRoleLayout() {
        const container = document.querySelector('[data-staff-directory-page]');
        if (container) {
            container.dataset.roleMode = this.getRoleMode();
        }

        this.applyPageChrome();
        this.renderTableHeader();
    },

    async loadInitialData() {
        await Promise.all([
            this.loadStaff(),
            this.loadDepartments(),
            this.loadRoles()
        ]);
    },

    async loadStaff(options = {}) {
        const force = options.force === true;

        if (this.staffLoadPromise && !force) {
            return this.staffLoadPromise;
        }

        const request = this._loadStaff();
        this.staffLoadPromise = request;

        try {
            return await request;
        } finally {
            if (this.staffLoadPromise === request) {
                this.staffLoadPromise = null;
            }
        }
    },

    async _loadStaff() {
        try {
            console.log('[StaffProductionUI] Loading staff directory...');
            const response = await window.API.staff.index({
                page: 1,
                limit: 100,
                search: this.state.currentFilters.search,
                department_id: this.state.currentFilters.department,
                staff_type_id: this.state.currentFilters.staff_type_id,
                status: this.state.currentFilters.status
            });

            this.state.staff = this.extractStaffList(response);
            this.state.pagination = response?.pagination || response?.data?.pagination || null;
            this.state.filteredStaff = [...this.state.staff];
            this.render();
            return this.state.staff;
        } catch (error) {
            if (error.code === 'PERMISSION_DENIED') {
                this.showToast('You do not have permission to view staff', 'error');
            } else {
                console.error('[StaffProductionUI] Failed to load staff:', error);
                this.showToast(error?.message || 'Failed to load staff', 'error');
            }
            this.renderLoadError(error);
            return [];
        }
    },

    extractStaffList(data) {
        if (!data) return [];
        if (Array.isArray(data)) return data;
        if (Array.isArray(data.staff)) return data.staff;
        if (Array.isArray(data.data?.staff)) return data.data.staff;
        if (Array.isArray(data.data)) return data.data;
        return [];
    },

    renderLoadError(error) {
        this.applyRoleLayout();
        const row = document.getElementById('staffStatsRow');
        if (row) row.innerHTML = '';

        const tbody = document.getElementById('staffTableBody');
        if (!tbody) return;

        const columns = this.getLayoutConfig().columns.length || 1;
        const message = error?.message || 'Unable to load staff records.';
        tbody.innerHTML = `
            <tr>
                <td colspan="${columns}" class="text-center text-danger py-4">
                    <div class="fw-semibold mb-1">Unable to load staff records</div>
                    <div class="small">${this.escapeHtml(message)}</div>
                </td>
            </tr>
        `;
    },

    async loadDepartments(options = {}) {
        const force = options.force === true;

        if (this.departmentsLoadPromise && !force) {
            return this.departmentsLoadPromise;
        }

        const request = this._loadDepartments();
        this.departmentsLoadPromise = request;

        try {
            return await request;
        } finally {
            if (this.departmentsLoadPromise === request) {
                this.departmentsLoadPromise = null;
            }
        }
    },

    async _loadDepartments() {
        try {
            const response = await window.API.staff.getDepartments();
            this.state.departments = this.extractList(response, 'departments');
            this.populateDepartmentDropdown();
            return this.state.departments;
        } catch (error) {
            console.error('[StaffProductionUI] Failed to load departments:', error);
            return [];
        }
    },

    async loadRoles(options = {}) {
        if (!this.canManageDirectory() || !window.StaffAccess?.can?.('staff.roles.manage')) {
            return [];
        }

        const force = options.force === true;

        if (this.rolesLoadPromise && !force) {
            return this.rolesLoadPromise;
        }

        const request = this._loadRoles();
        this.rolesLoadPromise = request;

        try {
            return await request;
        } finally {
            if (this.rolesLoadPromise === request) {
                this.rolesLoadPromise = null;
            }
        }
    },

    async _loadRoles() {
        try {
            const response = await window.API.staff.getAvailableRoles();
            this.state.roles = this.extractList(response, 'roles');
            this.populateRoleDropdown();
            return this.state.roles;
        } catch (error) {
            console.error('[StaffProductionUI] Failed to load roles:', error);
            return [];
        }
    },

    extractList(data, key) {
        if (!data) return [];
        if (Array.isArray(data)) return data;
        if (Array.isArray(data[key])) return data[key];
        if (Array.isArray(data.data?.[key])) return data.data[key];
        if (Array.isArray(data.data)) return data.data;
        return [];
    },

    populateDepartmentDropdown() {
        const filterSelect = document.getElementById('filterDepartment');
        const formSelect = document.getElementById('department');
        
        if (filterSelect) {
            filterSelect.innerHTML = '<option value="">All Departments</option>' +
                this.state.departments.map(dept => 
                    `<option value="${dept.id}">${dept.name}</option>`
                ).join('');
        }
        
        if (formSelect) {
            formSelect.innerHTML = '<option value="">Select Department</option>' +
                this.state.departments.map(dept => 
                    `<option value="${dept.id}">${dept.name}</option>`
                ).join('');
        }
    },

    populateRoleDropdown() {
        const roleSelect = document.getElementById('roleId');
        if (!roleSelect) return;

        roleSelect.innerHTML = '<option value="">Select Role</option>' +
            this.state.roles.map(role =>
                `<option value="${Number(role.id)}">${this.escapeHtml(role.name || role.role_name || '')}</option>`
            ).join('');
    },

    setupEventListeners() {
        if (this.eventsBound) {
            return;
        }

        this.eventsBound = true;

        document.getElementById('searchStaff')?.addEventListener('input', (e) => {
            this.state.currentFilters.search = e.target.value;
            this.applyFilters();
        });

        document.getElementById('filterDepartment')?.addEventListener('change', (e) => {
            this.state.currentFilters.department = e.target.value || null;
            this.applyFilters();
        });

        document.getElementById('filterStaffType')?.addEventListener('change', (e) => {
            this.state.currentFilters.staff_type_id = e.target.value || null;
            this.applyFilters();
        });

        document.getElementById('filterStatus')?.addEventListener('change', (e) => {
            this.state.currentFilters.status = e.target.value || null;
            this.applyFilters();
        });

        document.getElementById('resetFilters')?.addEventListener('click', () => {
            this.resetFilters();
        });

        document.getElementById('addStaffBtn')?.addEventListener('click', () => {
            if (this.canManageDirectory()) {
                this.showAddModal();
            } else {
                this.showToast('You do not have permission to add staff', 'error');
            }
        });

        document.getElementById('saveStaffBtn')?.addEventListener('click', () => {
            this.saveStaff();
        });

        document.getElementById('exportStaffBtn')?.addEventListener('click', () => {
            if (this.canExportDirectory()) {
                this.exportStaff();
            } else {
                this.showToast('You do not have permission to export staff', 'error');
            }
        });

        document.getElementById('importStaffBtn')?.addEventListener('click', () => {
            this.showImportModal();
        });

        document.getElementById('downloadStaffCsvTemplate')?.addEventListener('click', () => {
            void this.downloadImportTemplate('csv');
        });

        document.getElementById('downloadStaffExcelTemplate')?.addEventListener('click', () => {
            void this.downloadImportTemplate('xlsx');
        });

        document.getElementById('staffImportFile')?.addEventListener('change', () => {
            const input = document.getElementById('staffImportFile');
            const button = document.getElementById('validateStaffImportBtn');
            if (button) button.disabled = !input?.files?.length;
        });

        document.getElementById('validateStaffImportBtn')?.addEventListener('click', () => {
            void this.validateStaffImport();
        });

        document.getElementById('commitStaffImportBtn')?.addEventListener('click', () => {
            void this.commitStaffImport();
        });

        document.getElementById('staffImportRows')?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-import-errors]');
            if (!button) return;
            try {
                alert(JSON.parse(button.dataset.importErrors).join('\n'));
            } catch (_) {
                alert(button.dataset.importErrors || 'Validation errors found.');
            }
        });

        document.getElementById('staffTableBody')?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-staff-action]');
            if (!button) return;

            const staffId = Number(button.dataset.staffId || 0);
            if (!staffId) return;

            const action = button.dataset.staffAction;
            if (action === 'view') {
                void this.viewStaff(staffId);
            } else if (action === 'edit') {
                void this.editStaff(staffId);
            } else if (action === 'delete') {
                void this.deleteStaff(staffId);
            } else if (action === 'performance') {
                this.openRoute('staff_performance', staffId);
            } else if (action === 'workload') {
                this.openRoute('teacher_workload', staffId);
            }
        });

        document.getElementById('editFromViewBtn')?.addEventListener('click', () => {
            const staffId = Number(document.getElementById('editFromViewBtn')?.dataset.staffId || 0);
            if (!staffId) return;
            bootstrap.Modal.getInstance(document.getElementById('staffViewModal'))?.hide();
            void this.editStaff(staffId);
        });
    },

    bindEvents() {
        this.setupEventListeners();
    },

    applyPageContext() {
        const context = window.STAFF_PAGE_CONTEXT || {};

        if (context.mode === 'create' && this.canManageDirectory()) {
            window.setTimeout(() => this.showAddModal(), 100);
        }
    },

    applyFilters() {
        let filtered = [...this.state.staff];

        if (this.state.currentFilters.search) {
            const search = this.state.currentFilters.search.toLowerCase();
            filtered = filtered.filter(staff => {
                const name = `${staff.first_name || ''} ${staff.last_name || ''}`.toLowerCase();
                const staffNo = (staff.staff_no || '').toLowerCase();
                const email = (staff.email || '').toLowerCase();
                return name.includes(search) || staffNo.includes(search) || email.includes(search);
            });
        }

        if (this.state.currentFilters.department) {
            filtered = filtered.filter(staff => 
                staff.department_id == this.state.currentFilters.department
            );
        }

        if (this.state.currentFilters.staff_type_id) {
            filtered = filtered.filter(staff => 
                staff.staff_type_id == this.state.currentFilters.staff_type_id
            );
        }

        if (this.state.currentFilters.status) {
            filtered = filtered.filter(staff => 
                staff.status === this.state.currentFilters.status
            );
        }

        this.state.filteredStaff = filtered;
        this.render();
    },

    resetFilters() {
        this.state.currentFilters = {
            search: '',
            department: null,
            staff_type_id: null,
            status: null
        };

        document.getElementById('searchStaff').value = '';
        document.getElementById('filterDepartment').value = '';
        document.getElementById('filterStaffType').value = '';
        document.getElementById('filterStatus').value = '';

        this.applyFilters();
    },

    render() {
        this.applyRoleLayout();
        this.renderStats();
        this.renderTable();
    },

    renderStats() {
        const total = Number(this.state.pagination?.total || this.state.staff.length);
        const active = this.state.staff.filter(s => s.status === 'active').length;
        const teaching = this.state.staff.filter(s => s.staff_type_id == 1).length;
        const nonTeaching = this.state.staff.filter(s => s.staff_type_id == 2).length;
        const onLeave = this.state.staff.filter(s => s.status === 'on_leave').length;
        const departments = new Set(this.state.staff.map(s => s.department_id || s.department_name).filter(Boolean)).size;
        const payrollReady = this.state.staff.filter(s => s.status === 'active' && (s.salary || s.bank_account || s.bank_name)).length;
        const missingPayroll = this.state.staff.filter(s => s.status === 'active' && !(s.salary || s.bank_account || s.bank_name)).length;

        const metrics = {
            total: { value: total, label: 'Total Staff', icon: 'bi-people-fill', tone: 'primary' },
            active: { value: active, label: 'Active Staff', icon: 'bi-person-check-fill', tone: 'success' },
            teaching: { value: teaching, label: 'Teaching Staff', icon: 'bi-mortarboard-fill', tone: 'info' },
            non_teaching: { value: nonTeaching, label: 'Non-Teaching', icon: 'bi-tools', tone: 'warning', textClass: 'text-dark' },
            on_leave: { value: onLeave, label: 'On Leave', icon: 'bi-calendar-x-fill', tone: 'secondary' },
            departments: { value: departments, label: 'Departments', icon: 'bi-diagram-3-fill', tone: 'dark' },
            payroll_ready: { value: payrollReady, label: 'Payroll Ready', icon: 'bi-cash-coin', tone: 'success' },
            missing_payroll: { value: missingPayroll, label: 'Payroll Gaps', icon: 'bi-exclamation-triangle-fill', tone: 'danger' }
        };

        const cards = this.getLayoutConfig().cards;
        const row = document.getElementById('staffStatsRow');
        if (!row) return;

        row.innerHTML = cards.map(key => {
            const metric = metrics[key] || metrics.total;
            const textClass = metric.textClass || 'text-white';
            return `
                <div class="col-md-${cards.length > 3 ? '3' : '4'} mb-3" data-staff-card="${this.escapeHtml(key)}">
                    <div class="card bg-${this.escapeHtml(metric.tone)} ${textClass}">
                        <div class="card-body d-flex align-items-center gap-3">
                            <i class="bi ${this.escapeHtml(metric.icon)} fs-2"></i>
                            <div>
                                <h3 class="mb-0">${Number(metric.value || 0)}</h3>
                                <p class="mb-0">${this.escapeHtml(metric.label)}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    },

    renderTableHeader() {
        const header = document.getElementById('staffTableHead');
        if (!header) return;
        const labels = {
            staff_no: 'Staff No',
            name: 'Name',
            department: 'Department',
            type: 'Type',
            position: 'Position',
            contact: 'Contact',
            payroll: 'Payroll',
            status: 'Status',
            actions: 'Actions'
        };

        header.innerHTML = this.getLayoutConfig().columns
            .map(column => `<th class="${column === 'actions' ? 'text-end' : ''}">${labels[column] || column}</th>`)
            .join('');
    },

    renderTable() {
        const tbody = document.getElementById('staffTableBody');
        if (!tbody) return;
        const columns = this.getLayoutConfig().columns;

        if (this.state.filteredStaff.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${columns.length}" class="text-center text-muted py-4">No staff found</td></tr>`;
            return;
        }

        tbody.innerHTML = this.state.filteredStaff.map((staff) => {
            return `
                <tr>
                    ${columns.map(column => this.renderStaffCell(staff, column)).join('')}
                </tr>
            `;
        }).join('');
    },

    renderStaffCell(staff, column) {
        const fullName = staff.full_name || `${staff.first_name || ''} ${staff.last_name || ''}`.trim() || 'Unnamed staff';
        const position = staff.display_position || staff.position || staff.role_name || staff.staff_category_name || '-';
        const cells = {
            staff_no: `<td>${this.escapeHtml(staff.staff_no || '-')}</td>`,
            name: `<td><strong>${this.escapeHtml(fullName)}</strong>${this.getRoleMode() === 'operations' ? `<br><small class="text-muted">${this.escapeHtml(staff.email || '-')}</small>` : ''}</td>`,
            department: `<td>${this.escapeHtml(staff.department_name || '-')}</td>`,
            type: `<td>${this.renderStaffType(staff)}</td>`,
            position: `<td>${this.escapeHtml(position)}</td>`,
            contact: `<td>${this.renderContact(staff)}</td>`,
            payroll: `<td>${this.renderPayrollState(staff)}</td>`,
            status: `<td>${this.renderStatusBadge(staff)}</td>`,
            actions: `<td class="text-end">${this.renderActionButtons(staff)}</td>`,
        };
        return cells[column] || '';
    },

    renderContact(staff) {
        const parts = [];
        if (staff.phone) parts.push(`<div>${this.escapeHtml(staff.phone)}</div>`);
        if (staff.email) parts.push(`<small class="text-muted">${this.escapeHtml(staff.email)}</small>`);
        return parts.length ? parts.join('') : '-';
    },

    renderPayrollState(staff) {
        const ready = Boolean(staff.salary || staff.bank_account || staff.bank_name);
        return ready
            ? '<span class="badge bg-success">Ready</span>'
            : '<span class="badge bg-warning text-dark">Incomplete</span>';
    },

    renderStaffType(staff) {
        const typeMap = { 1: 'Teaching', 2: 'Non-Teaching', 3: 'Admin' };
        const typeName = typeMap[staff.staff_type_id] || 'Unknown';
        const colorMap = { 1: 'primary', 2: 'info', 3: 'warning' };
        const color = colorMap[staff.staff_type_id] || 'secondary';
        return `<span class="badge bg-${color}">${this.escapeHtml(typeName)}</span>`;
    },

    renderStatusBadge(staff) {
        const statusMap = {
            'active': 'success',
            'inactive': 'secondary',
            'on_leave': 'warning'
        };
        const color = statusMap[staff.status] || 'secondary';
        return `<span class="badge bg-${color}">${this.escapeHtml(staff.status || 'Unknown')}</span>`;
    },

    renderActionButtons(staff) {
        const buttons = [];
        const actions = new Set(this.getLayoutConfig().actions);

        if (actions.has('view')) {
            buttons.push(`<button type="button" class="btn btn-sm btn-outline-info me-1" data-staff-action="view" data-staff-id="${Number(staff.id)}" title="View staff profile">
                <i class="fas fa-eye"></i>
            </button>`);
        }

        if (actions.has('edit') && this.canManageDirectory()) {
            buttons.push(`<button type="button" class="btn btn-sm btn-outline-warning me-1" data-staff-action="edit" data-staff-id="${Number(staff.id)}" title="Edit staff record">
                <i class="fas fa-pen"></i>
            </button>`);
        }

        if (actions.has('performance')) {
            buttons.push(`<button type="button" class="btn btn-sm btn-outline-primary me-1" data-staff-action="performance" data-staff-id="${Number(staff.id)}" title="Performance">
                <i class="fas fa-chart-line"></i>
            </button>`);
        }

        if (actions.has('workload')) {
            buttons.push(`<button type="button" class="btn btn-sm btn-outline-secondary me-1" data-staff-action="workload" data-staff-id="${Number(staff.id)}" title="Workload">
                <i class="fas fa-calendar-week"></i>
            </button>`);
        }

        if (actions.has('delete') && this.canDeleteDirectory()) {
            buttons.push(`<button type="button" class="btn btn-sm btn-outline-danger" data-staff-action="delete" data-staff-id="${Number(staff.id)}" title="Delete staff">
                <i class="fas fa-trash"></i>
            </button>`);
        }

        return buttons.length ? `<div class="btn-group btn-group-sm">${buttons.join('')}</div>` : '<span class="text-muted">-</span>';
    },

    openRoute(route, staffId) {
        window.location.href = `${window.APP_BASE || ''}/home.php?route=${encodeURIComponent(route)}&staff_id=${encodeURIComponent(staffId)}`;
    },

    async showImportModal() {
        if (!this.canManageDirectory()) {
            this.showToast('You do not have permission to import staff', 'error');
            return;
        }

        this.setImportState('Download a template, complete the staff rows, then upload the file for validation.', 'info');
        document.getElementById('staffImportFile').value = '';
        document.getElementById('validateStaffImportBtn').disabled = true;
        document.getElementById('commitStaffImportBtn').disabled = true;
        document.getElementById('staffImportPreviewCard')?.classList.add('d-none');
        this.state.currentImportBatch = null;

        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('staffImportModal'));
        modal.show();

        await this.loadImportReferenceData();
    },

    async downloadImportTemplate(type) {
        try {
            if (type === 'xlsx') {
                await window.API.staffMigration.downloadTemplateXlsx();
            } else {
                await window.API.staffMigration.downloadTemplate();
            }
        } catch (error) {
            console.error('[StaffProductionUI] Staff import template download failed:', error);
            this.setImportState(error?.message || 'Template download failed.', 'danger');
        }
    },

    async loadImportReferenceData() {
        const host = document.getElementById('staffImportReference');
        if (!host) return;

        host.textContent = 'Loading reference values...';
        try {
            const response = await window.API.staffMigration.referenceData();
            const data = this.extractPayload(response) || {};
            const departments = data.departments || [];
            const roles = data.roles || [];
            const types = data.staff_types || [];
            const categories = data.staff_categories || [];

            host.innerHTML = `
                <div class="mb-2"><span class="fw-semibold">Department codes:</span> ${departments.map(item => this.escapeHtml(`${item.code} - ${item.name}`)).join(', ') || '-'}</div>
                <div class="mb-2"><span class="fw-semibold">Roles:</span> ${roles.map(item => this.escapeHtml(item.name)).join(', ') || '-'}</div>
                <div class="mb-2"><span class="fw-semibold">Staff types:</span> ${types.map(item => this.escapeHtml(item.name)).join(', ') || '-'}</div>
                <div><span class="fw-semibold">Categories:</span> ${categories.map(item => this.escapeHtml(`${item.name} (${item.staff_type})`)).join(', ') || '-'}</div>
            `;
        } catch (error) {
            host.innerHTML = `<span class="text-danger">${this.escapeHtml(error?.message || 'Reference data failed to load.')}</span>`;
        }
    },

    async validateStaffImport() {
        const input = document.getElementById('staffImportFile');
        const file = input?.files?.[0];
        if (!file) {
            this.setImportState('Select a completed CSV or Excel file first.', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('file', file);

        this.setImportState('Uploading and validating staff file...', 'info');
        try {
            const response = await window.API.staffMigration.stage(formData);
            const detail = this.extractPayload(response);
            this.renderImportPreview(detail);
            this.setImportState('Validation completed. Fix any invalid rows before committing.', detail?.can_commit ? 'success' : 'warning');
        } catch (error) {
            console.error('[StaffProductionUI] Staff import validation failed:', error);
            this.setImportState(error?.message || 'Staff import validation failed.', 'danger');
        }
    },

    async commitStaffImport() {
        const batchId = this.state.currentImportBatch;
        if (!batchId) {
            this.setImportState('Validate a file before committing import.', 'warning');
            return;
        }
        if (!confirm('Create staff records and user accounts from this validated import?')) {
            return;
        }

        this.setImportState('Committing staff import. Please wait...', 'info');
        try {
            const response = await window.API.staffMigration.commit(batchId);
            const detail = this.extractPayload(response);
            this.renderImportPreview(detail.batch || detail);
            this.setImportState('Staff import completed. Account invitation emails were queued.', 'success');
            await this.loadStaff({ force: true });
        } catch (error) {
            console.error('[StaffProductionUI] Staff import commit failed:', error);
            this.setImportState(error?.message || 'Staff import failed. No partial records were kept.', 'danger');
        }
    },

    renderImportPreview(detail) {
        if (!detail) return;

        const batch = detail.batch || detail;
        const rows = detail.rows || batch.rows || [];
        this.state.currentImportBatch = Number(batch.id || 0) || this.state.currentImportBatch;
        document.getElementById('staffImportPreviewCard')?.classList.remove('d-none');

        const summary = document.getElementById('staffImportSummary');
        if (summary) {
            summary.innerHTML = [
                ['Total', batch.total_rows],
                ['Valid', batch.valid_rows],
                ['Invalid', batch.invalid_rows],
                ['Status', batch.status]
            ].map(([label, value]) => `
                <div class="col-sm-3">
                    <div class="border rounded p-2">
                        <div class="text-muted small">${this.escapeHtml(label)}</div>
                        <div class="fw-semibold">${this.escapeHtml(value ?? '-')}</div>
                    </div>
                </div>
            `).join('');
        }

        const tbody = document.getElementById('staffImportRows');
        if (tbody) {
            tbody.innerHTML = rows.length ? rows.map(row => {
                const data = row.data || {};
                const errors = row.errors || [];
                const staffName = `${data.first_name || ''} ${data.last_name || ''}`.trim();
                return `
                    <tr>
                        <td>${this.escapeHtml(row.row_number || '-')}</td>
                        <td>${this.escapeHtml(staffName || '-')}</td>
                        <td>${this.escapeHtml(data.email || '-')}</td>
                        <td>${this.escapeHtml(data.department_code || '-')}</td>
                        <td>${errors.length
                            ? `<button type="button" class="btn btn-sm btn-outline-danger" data-import-errors="${this.escapeHtml(JSON.stringify(errors))}">${errors.length} errors</button>`
                            : '<span class="badge bg-success">Valid</span>'}
                        </td>
                    </tr>
                `;
            }).join('') : '<tr><td colspan="5" class="text-center text-muted py-3">No rows found.</td></tr>';
        }

        const commit = document.getElementById('commitStaffImportBtn');
        if (commit) commit.disabled = !detail.can_commit;
    },

    setImportState(message, type = 'info') {
        const state = document.getElementById('staffImportState');
        if (!state) return;
        state.className = `alert alert-${type}`;
        state.textContent = message;
    },

    showAddModal() {
        if (!this.canManageDirectory()) {
            this.showToast('You do not have permission to add staff', 'error');
            return;
        }
        document.getElementById('staffModalTitle').textContent = 'Add Staff';
        document.getElementById('staffId').value = '';
        document.getElementById('staffForm').reset();
        this.setValue('contractType', 'permanent');
        this.setValue('status', 'active');
        
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('staffModal'));
        modal.show();
    },

    async saveStaff() {
        if (!this.canManageDirectory()) {
            this.showToast('You do not have permission to save staff', 'error');
            return;
        }
        const staffId = document.getElementById('staffId').value;
        const data = {
            staff_no: this.getValue('staffNo') || undefined,
            first_name: this.getValue('firstName'),
            last_name: this.getValue('lastName'),
            email: this.getValue('email'),
            phone: this.getValue('phone'),
            department_id: this.getValue('department') || null,
            staff_type_id: this.getValue('staff_type_id') || null,
            role_id: this.getValue('roleId') || undefined,
            position: this.getValue('position'),
            employment_date: this.getValue('employmentDate') || null,
            contract_type: this.getValue('contractType') || 'permanent',
            status: this.getValue('status') || 'active',
            gender: this.getValue('gender') || null,
            date_of_birth: this.getValue('dateOfBirth') || null,
            marital_status: this.getValue('maritalStatus') || null,
            tsc_no: this.getValue('tscNo') || null,
            kra_pin: this.getValue('kraPin') || null,
            nssf_no: this.getValue('nssfNo') || null,
            nhif_no: this.getValue('nhifNo') || null,
            bank_name: this.getValue('bankName') || null,
            bank_account: this.getValue('bankAccount') || null,
            salary: this.getValue('salary') || null,
            address: this.getValue('address') || null
        };

        const requiredForCreate = {
            first_name: 'First name',
            last_name: 'Last name',
            email: 'Email',
            phone: 'Phone',
            department_id: 'Department',
            staff_type_id: 'Staff type',
            role_id: 'System role',
            position: 'Job title / position',
            employment_date: 'Employment date',
            kra_pin: 'KRA PIN',
            nssf_no: 'NSSF number',
            nhif_no: 'NHIF/SHIF number',
            bank_name: 'Bank name',
            bank_account: 'Bank account',
            salary: 'Basic salary'
        };

        if (!staffId) {
            const missing = Object.entries(requiredForCreate)
                .filter(([field]) => !data[field])
                .map(([, label]) => label);

            if (missing.length) {
                this.showToast(`Required fields missing: ${missing.join(', ')}`, 'warning');
                return;
            }
        }

        try {
            if (staffId) {
                await window.API.staff.update(staffId, data);
                this.showToast('Staff updated successfully', 'success');
            } else {
                await window.API.staff.create(data);
                this.showToast('Staff created successfully', 'success');
            }

            const modal = bootstrap.Modal.getInstance(document.getElementById('staffModal'));
            modal.hide();
            
            await this.loadStaff();
        } catch (error) {
            console.error('Error saving staff:', error);
            this.showToast(error?.message || 'Failed to save staff', 'error');
        }
    },

    async viewStaff(staffId) {
        try {
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('staffViewModal'));
            const title = document.getElementById('staffViewModalTitle');
            const body = document.getElementById('staffViewModalBody');
            if (title) title.textContent = 'Staff Profile';
            if (body) {
                body.innerHTML = `
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                `;
            }
            modal.show();

            const staff = await this.getStaffRecord(staffId);
            this.renderStaffProfile(staff);
        } catch (error) {
            console.error('Error loading staff details:', error);
            this.showToast(error?.message || 'Failed to load staff details', 'error');
        }
    },

    async editStaff(staffId) {
        if (!this.canManageDirectory()) {
            this.showToast('You do not have permission to edit staff', 'error');
            return;
        }
        try {
            const staff = await this.getStaffRecord(staffId);
            this.populateEditForm(staff);
            document.getElementById('staffModalTitle').textContent = `Edit ${staff.full_name || 'Staff Record'}`;

            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('staffModal'));
            modal.show();
        } catch (error) {
            console.error('Error loading staff for edit:', error);
            this.showToast(error?.message || 'Failed to load staff details', 'error');
        }
    },

    async getStaffRecord(staffId) {
        const response = await window.API.staff.get(staffId);
        const data = this.extractPayload(response);
        const staff = Array.isArray(data) ? data[0] : data;
        if (!staff || !staff.id) {
            throw new Error('Staff record was not returned by the API.');
        }
        return this.normalizeStaffRecord(staff);
    },

    extractPayload(response) {
        if (!response) return null;
        if (response.data?.data) return response.data.data;
        if (response.data) return response.data;
        return response;
    },

    normalizeStaffRecord(staff) {
        const firstName = staff.first_name || staff.staff_first_name || '';
        const lastName = staff.last_name || staff.staff_last_name || '';
        return {
            ...staff,
            first_name: firstName,
            last_name: lastName,
            full_name: staff.full_name || `${firstName} ${lastName}`.trim(),
            display_position: staff.display_position || staff.position || staff.role_name || staff.staff_category_name || staff.staff_type_name || 'Staff'
        };
    },

    populateEditForm(staff) {
        const normalized = this.normalizeStaffRecord(staff);
        this.setValue('staffId', normalized.id);
        this.setValue('staffNo', normalized.staff_no || '');
        this.setValue('firstName', normalized.first_name || '');
        this.setValue('lastName', normalized.last_name || '');
        this.setValue('email', normalized.email || '');
        this.setValue('phone', normalized.phone || '');
        this.setValue('department', normalized.department_id || '');
        this.setValue('staff_type_id', normalized.staff_type_id || '');
        this.setValue('roleId', normalized.role_id || '');
        this.setValue('position', normalized.raw_position || normalized.display_position || '');
        this.setValue('employmentDate', normalized.employment_date || '');
        this.setValue('contractType', normalized.contract_type || 'permanent');
        this.setValue('status', normalized.status || 'active');
        this.setValue('gender', normalized.gender || '');
        this.setValue('dateOfBirth', normalized.date_of_birth || '');
        this.setValue('maritalStatus', normalized.marital_status || '');
        this.setValue('tscNo', normalized.tsc_no || '');
        this.setValue('kraPin', normalized.kra_pin || '');
        this.setValue('nssfNo', normalized.nssf_no || '');
        this.setValue('nhifNo', normalized.nhif_no || '');
        this.setValue('bankName', normalized.bank_name || '');
        this.setValue('bankAccount', normalized.bank_account || '');
        this.setValue('salary', normalized.salary || '');
        this.setValue('address', normalized.address || '');
    },

    renderStaffProfile(staff) {
        const normalized = this.normalizeStaffRecord(staff);
        const title = document.getElementById('staffViewModalTitle');
        const body = document.getElementById('staffViewModalBody');
        const editButton = document.getElementById('editFromViewBtn');
        if (title) title.textContent = normalized.full_name || 'Staff Profile';
        if (editButton) {
            editButton.dataset.staffId = normalized.id;
            editButton.hidden = !this.canManageDirectory();
        }
        if (!body) return;

        body.innerHTML = `
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="border rounded p-3 h-100">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width:56px;height:56px;font-weight:700;">
                                ${this.escapeHtml(this.initials(normalized.full_name))}
                            </div>
                            <div>
                                <div class="fw-semibold fs-5">${this.escapeHtml(normalized.full_name || 'Unnamed staff')}</div>
                                <div class="text-muted">${this.escapeHtml(normalized.staff_no || '-')}</div>
                            </div>
                        </div>
                        ${this.detailRow('Job Title', normalized.display_position)}
                        ${this.detailRow('Department', normalized.department_name)}
                        ${this.detailRow('Staff Type', normalized.staff_type_name || this.getStaffTypeName(normalized.staff_type_id))}
                        ${this.detailRow('Status', normalized.status)}
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="border rounded p-3 h-100">
                        <h6 class="mb-3">Contact & Employment</h6>
                        ${this.detailRow('Email', normalized.email)}
                        ${this.detailRow('Phone', normalized.phone)}
                        ${this.detailRow('Employment Date', normalized.employment_date)}
                        ${this.detailRow('Contract Type', normalized.contract_type)}
                        ${this.detailRow('Address', normalized.address)}
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="border rounded p-3 h-100">
                        <h6 class="mb-3">Compliance & Payroll</h6>
                        ${this.detailRow('KRA PIN', normalized.kra_pin)}
                        ${this.detailRow('NSSF No', normalized.nssf_no)}
                        ${this.detailRow('NHIF/SHIF No', normalized.nhif_no)}
                        ${this.detailRow('Bank', normalized.bank_name)}
                        ${this.detailRow('Bank Account', normalized.bank_account)}
                        ${this.detailRow('Salary', this.formatCurrency(normalized.salary))}
                    </div>
                </div>
            </div>
        `;
    },

    detailRow(label, value) {
        return `
            <div class="d-flex justify-content-between gap-3 border-bottom py-2">
                <span class="text-muted">${this.escapeHtml(label)}</span>
                <span class="text-end fw-medium">${this.escapeHtml(value || '-')}</span>
            </div>
        `;
    },

    getValue(id) {
        return document.getElementById(id)?.value?.trim() || '';
    },

    setValue(id, value) {
        const element = document.getElementById(id);
        if (element) element.value = value ?? '';
    },

    initials(name) {
        return String(name || 'S')
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map(part => part.charAt(0).toUpperCase())
            .join('') || 'S';
    },

    formatCurrency(value) {
        const amount = Number(value || 0);
        if (!amount) return '-';
        return new Intl.NumberFormat('en-KE', {
            style: 'currency',
            currency: 'KES',
            maximumFractionDigits: 0
        }).format(amount);
    },

    async deleteStaff(staffId) {
        if (!this.canDeleteDirectory()) {
            this.showToast('You do not have permission to delete staff', 'error');
            return;
        }
        if (!confirm('Are you sure you want to delete this staff member?')) return;

        try {
            await window.API.staff.delete(staffId);
            this.showToast('Staff deleted successfully', 'success');
            await this.loadStaff();
        } catch (error) {
            console.error('Error deleting staff:', error);
            this.showToast(error?.message || 'Failed to delete staff', 'error');
        }
    },

    exportStaff() {
        if (!this.state.filteredStaff.length) {
            this.showToast('No data to export', 'warning');
            return;
        }

        const headers = ['Staff No', 'Name', 'Email', 'Department', 'Type', 'Position', 'Status'];
        const rows = this.state.filteredStaff.map(staff => [
            staff.staff_no || '',
            `${staff.first_name} ${staff.last_name}`,
            staff.email || '',
            staff.department_name || '',
            this.getStaffTypeName(staff.staff_type_id),
            staff.position || '',
            staff.status || ''
        ]);

        let csv = headers.join(',') + '\n' + 
            rows.map(row => row.map(cell => `"${cell}"`).join(',')).join('\n');

        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
        a.download = 'staff_export.csv';
        a.click();
    },

    getStaffTypeName(typeId) {
        const typeMap = { 1: 'Teaching', 2: 'Non-Teaching', 3: 'Admin' };
        return typeMap[typeId] || 'Unknown';
    },

    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
};

window.StaffProductionUI = StaffProductionUI;
window.staffProductionController = StaffProductionUI;

function initializeStaffProductionUI() {
    void StaffProductionUI.init().catch((error) => {
        console.error('[StaffProductionUI] Page initialization failed:', error);
    });
}

if (window.__APP_BOOTED__) {
    initializeStaffProductionUI();
} else {
    window.addEventListener(
        'kingsway:ready',
        initializeStaffProductionUI,
        { once: true }
    );
}
