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
    staffRequestSequence: 0,
    departmentsLoadPromise: null,
    rolesLoadPromise: null,
    searchTimer: null,
    managingStaffIds: [],
    selectedRoleIds: [],

    state: {
        staff: [],
        filteredStaff: [],
        departments: [],
        roles: [],
        staffTypes: [],
        staffCategories: [],
        positions: [],
        pagination: null,
        currentPage: 1,
        pageSize: 25,
        selectedStaffIds: new Set(),
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

        return this;
    },

    canViewDirectory() {
        return (window.StaffAccess && StaffAccess.can('staff.directory.view')) || window.AuthContext?.canView?.('staff');
    },

    canManageDirectory() {
        return (window.StaffAccess && StaffAccess.can('staff.directory.manage')) || window.AuthContext?.canCreate?.('staff') || window.AuthContext?.canEdit?.('staff');
    },

    canManageStaffRoles() {
        return this.canManageDirectory() && Boolean(window.StaffAccess?.can?.('staff.roles.manage'));
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

        const printBtn = document.getElementById('printStaffBtn');
        if (printBtn) printBtn.hidden = !(window.AuthContext?.canPrint?.('staff') ?? true);

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
                columns: ['staff_no', 'name', 'department', 'roles', 'contact', 'actions'],
                actions: ['view', 'edit']
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
            this.loadRoles(),
            this.loadStaffClassifications()
        ]);
        if (this.canManageDirectory()) await this.loadSetupCatalogs();
    },

    async loadStaff(options = {}) {
        const force = options.force === true;
        if (Number.isInteger(options.page) && options.page > 0) this.state.currentPage = options.page;

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
        const requestSequence = ++this.staffRequestSequence;
        try {
            const response = await window.API.staff.index({
                page: this.state.currentPage,
                limit: this.state.pageSize,
                search: this.state.currentFilters.search,
                department_id: this.state.currentFilters.department,
                staff_type_id: this.state.currentFilters.staff_type_id,
                status: this.state.currentFilters.status
            });

            if (requestSequence !== this.staffRequestSequence) return this.state.staff;

            this.state.staff = this.extractStaffList(response);
            const payload = response?.data?.data || response?.data || response || {};
            this.state.pagination = payload?.pagination || response?.pagination || null;
            if (this.state.pagination?.page) this.state.currentPage = Number(this.state.pagination.page);
            this.state.filteredStaff = [...this.state.staff];
            this.render();
            return this.state.staff;
        } catch (error) {
            if (requestSequence !== this.staffRequestSequence) return this.state.staff;
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

    async loadStaffClassifications() {
        if (!this.canManageDirectory()) return [];
        try {
            const response = await window.API.staff.getClassificationOptions();
            const payload = response?.data || response || {};
            this.state.staffTypes = Array.isArray(payload.staff_types) ? payload.staff_types : [];
            this.state.staffCategories = Array.isArray(payload.staff_categories) ? payload.staff_categories : [];
            this.populateStaffClassificationDropdowns();
            return this.state.staffCategories;
        } catch (error) {
            console.error('[StaffProductionUI] Failed to load staff classifications:', error);
            return [];
        }
    },

    async loadSetupCatalogs() {
        try {
            const [departments, positions] = await Promise.all([
                window.API.staff.getDepartmentCatalog(), window.API.staff.getPositions()
            ]);
            this.setupDepartments = this.extractList(departments, 'departments');
            this.setupPositions = this.extractList(positions, 'positions');
            this.fillSetupSelect('staffPositionType', this.state.staffTypes, 'Any type');
            this.fillSetupSelect('staffPositionCategory', this.state.staffCategories, 'Any category');
            this.fillSetupSelect('staffPositionRoles', this.state.roles, 'Any role');
            this.fillSetupSelect('staffPositionDefaultRoles', this.state.roles, 'Select roles');
            this.filterPositionDefaultRoles();
            this.filterSetupCategories();
            this.renderSetupCatalogs();
            this.bindSetupCatalogEvents();
        } catch (error) {
            console.error('[StaffProductionUI] Failed to load staff setup catalogues:', error);
        }
    },

    fillSetupSelect(id, rows, placeholder) {
        const select = document.getElementById(id);
        if (!select) return;
        const value = select.value;
        select.innerHTML = (select.multiple ? '' : `<option value="">${placeholder}</option>`) + rows.map(row =>
            `<option value="${Number(row.id)}">${this.escapeHtml(row.name || row.category_name || '')}</option>`
        ).join('');
        select.value = value;
    },

    renderSetupCatalogs() {
        const departments = document.getElementById('staffDepartmentsList');
        if (departments) departments.innerHTML = this.setupDepartments.map(row => `<tr><td>${this.escapeHtml(row.name)}</td><td>${this.escapeHtml(row.code)}</td><td>${row.status === 'active' ? 'Active' : 'Inactive'}</td><td class="text-end"><button class="btn btn-sm btn-outline-primary" data-edit-department="${Number(row.id)}">Edit</button></td></tr>`).join('') || '<tr><td colspan="4" class="text-muted">No departments found.</td></tr>';
        const positions = document.getElementById('staffPositionsList');
        if (positions) positions.innerHTML = this.setupPositions.map(row => {
            const defaults = String(row.default_role_ids || '').split(',').map(Number).filter(Boolean).map(id => this.state.roles.find(role => Number(role.id) === id)?.name).filter(Boolean);
            const scope = [row.staff_type, row.staff_category, row.system_roles ? `Roles: ${row.system_roles}` : ''].filter(Boolean).join(' · ');
            return `<tr><td>${this.escapeHtml(row.name)}</td><td>${this.escapeHtml(scope || 'Any staff classification')}<small class="d-block text-muted">${defaults.length ? `Default for: ${this.escapeHtml(defaults.join(', '))}` : 'No default role assigned'}</small></td><td>${Number(row.is_active) ? 'Active' : 'Inactive'}</td><td class="text-end"><button class="btn btn-sm btn-outline-primary" data-edit-position="${Number(row.id)}">Edit</button></td></tr>`;
        }).join('') || '<tr><td colspan="4" class="text-muted">No positions configured.</td></tr>';
    },

    bindSetupCatalogEvents() {
        if (this.setupEventsBound) return;
        this.setupEventsBound = true;
        document.getElementById('exportStaffSetupBtn')?.addEventListener('click', () => {
            if (window.AuthContext?.canExport && !window.AuthContext.canExport('staff')) return this.showToast('You do not have permission to export staff setup data.', 'error');
            const rows = [['kind','name','code_or_scope','status']];
            this.setupDepartments.forEach(row => rows.push(['department', row.name, row.code, row.status]));
            this.setupPositions.forEach(row => rows.push(['position', row.name, [row.staff_type, row.staff_category, row.system_roles].filter(Boolean).join(' | '), Number(row.is_active) ? 'active' : 'inactive']));
            const csv = rows.map(row => row.map(value => `"${String(value ?? '').replaceAll('"','""')}"`).join(',')).join('\r\n');
            window.KingswayFileLifecycle?.exportText(csv, 'staff_setup.csv', 'text/csv');
        });
        document.getElementById('printStaffSetupBtn')?.addEventListener('click', () => {
            if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('staff')) return this.showToast('You do not have permission to print staff setup data.', 'error');
            document.body.classList.add('staff-setup-printing');
            window.print();
            window.setTimeout(() => document.body.classList.remove('staff-setup-printing'), 1000);
        });
        document.getElementById('staffDepartmentForm')?.addEventListener('submit', async event => {
            event.preventDefault();
            const id = Number(this.getValue('staffDepartmentId') || 0);
            const payload = { name: this.getValue('staffDepartmentName'), code: this.getValue('staffDepartmentCode'), is_active: document.getElementById('staffDepartmentActive')?.checked ?? true };
            try { if (id) await window.API.staff.updateDepartment(id, payload); else await window.API.staff.createDepartment(payload); this.resetDepartmentEditor(); await this.loadDepartments(); await this.loadSetupCatalogs(); this.showToast('Department saved.', 'success'); }
            catch (error) { this.showToast(error.message || 'Could not save department.', 'error'); }
        });
        document.getElementById('staffDepartmentsList')?.addEventListener('click', event => {
            const row = this.setupDepartments.find(item => Number(item.id) === Number(event.target.closest('[data-edit-department]')?.dataset.editDepartment));
            if (!row) return;
            this.setValue('staffDepartmentId', row.id); this.setValue('staffDepartmentName', row.name); this.setValue('staffDepartmentCode', row.code);
            document.getElementById('staffDepartmentActive').checked = row.status === 'active';
        });
        document.getElementById('resetStaffDepartmentForm')?.addEventListener('click', () => this.resetDepartmentEditor());
        document.getElementById('staffPositionForm')?.addEventListener('submit', async event => {
            event.preventDefault();
            const id = Number(this.getValue('staffPositionId') || 0);
            const payload = { name: this.getValue('staffPositionName'), staff_type_id: this.getValue('staffPositionType') || null, staff_category_id: this.getValue('staffPositionCategory') || null, role_ids: [...(document.getElementById('staffPositionRoles')?.selectedOptions || [])].map(option => Number(option.value)), default_role_ids: [...(document.getElementById('staffPositionDefaultRoles')?.selectedOptions || [])].map(option => Number(option.value)), is_active: document.getElementById('staffPositionActive')?.checked ? 1 : 0 };
            try { if (id) await window.API.staff.updatePosition(id, payload); else await window.API.staff.createPosition(payload); this.resetPositionEditor(); await this.loadStaffClassifications(); await this.loadSetupCatalogs(); this.showToast('Position saved.', 'success'); }
            catch (error) { this.showToast(error.message || 'Could not save position.', 'error'); }
        });
        document.getElementById('staffPositionType')?.addEventListener('change', () => this.filterSetupCategories());
        document.getElementById('staffPositionRoles')?.addEventListener('change', () => this.filterPositionDefaultRoles());
        document.getElementById('staffPositionsList')?.addEventListener('click', event => {
            const row = this.setupPositions.find(item => Number(item.id) === Number(event.target.closest('[data-edit-position]')?.dataset.editPosition));
            if (!row) return;
            this.setValue('staffPositionId', row.id); this.setValue('staffPositionName', row.name); this.setValue('staffPositionType', row.staff_type_id || ''); this.setValue('staffPositionCategory', row.staff_category_id || ''); this.filterSetupCategories();
            const linkedRoles = String(row.role_ids || '').split(',').map(value => value.trim());
            [...document.getElementById('staffPositionRoles').options].forEach(option => { option.selected = linkedRoles.includes(option.value); });
            this.filterPositionDefaultRoles();
            const defaultRoles = String(row.default_role_ids || '').split(',').map(value => value.trim());
            [...document.getElementById('staffPositionDefaultRoles').options].forEach(option => { option.selected = defaultRoles.includes(option.value); });
            document.getElementById('staffPositionActive').checked = Number(row.is_active) === 1;
        });
        document.getElementById('resetStaffPositionForm')?.addEventListener('click', () => this.resetPositionEditor());
    },

    resetDepartmentEditor() { document.getElementById('staffDepartmentForm')?.reset(); this.setValue('staffDepartmentId', ''); const active=document.getElementById('staffDepartmentActive'); if(active) active.checked=true; },
    resetPositionEditor() { document.getElementById('staffPositionForm')?.reset(); this.setValue('staffPositionId', ''); document.getElementById('staffPositionActive').checked=true; },
    filterSetupCategories() {
        const select = document.getElementById('staffPositionCategory');
        if (!select) return;
        const typeId = Number(this.getValue('staffPositionType') || 0);
        const current = select.value;
        const categories = this.state.staffCategories.filter(item => !typeId || Number(item.staff_type_id) === typeId);
        select.innerHTML = '<option value="">Any category</option>' + categories.map(item => `<option value="${Number(item.id)}">${this.escapeHtml(item.name)}</option>`).join('');
        if (categories.some(item => String(item.id) === current)) select.value = current;
    },

    filterPositionDefaultRoles() {
        const related = document.getElementById('staffPositionRoles');
        const defaults = document.getElementById('staffPositionDefaultRoles');
        if (!related || !defaults) return;
        const allowed = new Set([...related.selectedOptions].map(option => option.value));
        [...defaults.options].forEach(option => {
            const keep = allowed.has(option.value);
            option.hidden = !keep;
            option.disabled = !keep;
            if (!keep) option.selected = false;
        });
    },

    populateStaffClassificationDropdowns(selectedType = null, selectedCategory = null) {
        const typeSelect = document.getElementById('staff_type_id');
        const categorySelect = document.getElementById('staff_category_id');
        if (!typeSelect || !categorySelect) return;
        const typeValue = selectedType ?? typeSelect.value;
        const categoryValue = selectedCategory ?? categorySelect.value;
        typeSelect.innerHTML = '<option value="">Select Type</option>' + this.state.staffTypes.map(type =>
            `<option value="${Number(type.id)}">${this.escapeHtml(type.name)}</option>`
        ).join('');
        typeSelect.value = String(typeValue || '');
        const matching = this.state.staffCategories.filter(category => Number(category.staff_type_id) === Number(typeSelect.value));
        categorySelect.innerHTML = '<option value="">Select category</option>' + matching.map(category =>
            `<option value="${Number(category.id)}">${this.escapeHtml(category.name)}</option>`
        ).join('');
        categorySelect.value = matching.some(category => String(category.id) === String(categoryValue)) ? String(categoryValue) : '';
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
            window.clearTimeout(this.searchTimer);
            this.searchTimer = window.setTimeout(() => this.applyFilters(), 250);
        });

        document.getElementById('filterDepartment')?.addEventListener('change', (e) => {
            this.state.currentFilters.department = e.target.value || null;
            this.applyFilters();
        });

        document.getElementById('filterStaffType')?.addEventListener('change', (e) => {
            this.state.currentFilters.staff_type_id = e.target.value || null;
            this.applyFilters();
        });
        document.getElementById('staff_type_id')?.addEventListener('change', () => {
            this.populateStaffClassificationDropdowns(document.getElementById('staff_type_id').value, '');
        });

        document.getElementById('filterStatus')?.addEventListener('change', (e) => {
            this.state.currentFilters.status = e.target.value || null;
            this.applyFilters();
        });

        document.getElementById('resetFilters')?.addEventListener('click', () => {
            this.resetFilters();
        });
        document.getElementById('staffTablePagination')?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-staff-page]');
            if (!button || button.disabled) return;
            this.state.selectedStaffIds.clear();
            void this.loadStaff({ force: true, page: Number(button.dataset.staffPage) });
        });
        document.getElementById('staffTablePagination')?.addEventListener('change', (event) => {
            if (event.target.id !== 'staffPageSize') return;
            const size = Number(event.target.value);
            if (![10, 25, 50, 100].includes(size)) return;
            this.state.pageSize = size;
            this.state.selectedStaffIds.clear();
            void this.loadStaff({ force: true, page: 1 });
        });
        document.getElementById('staffTableHead')?.addEventListener('change', (event) => {
            if (event.target.id !== 'selectAllStaffOnPage') return;
            for (const staff of this.state.staff) {
                if (event.target.checked && staff.user_id) this.state.selectedStaffIds.add(Number(staff.id));
                else this.state.selectedStaffIds.delete(Number(staff.id));
            }
            this.renderTable();
        });
        document.getElementById('staffTableBody')?.addEventListener('change', (event) => {
            const checkbox = event.target.closest('[data-select-staff]');
            if (!checkbox) return;
            const staffId = Number(checkbox.value);
            if (checkbox.checked) this.state.selectedStaffIds.add(staffId);
            else this.state.selectedStaffIds.delete(staffId);
            this.renderBulkActions();
            this.syncSelectAllCheckbox();
        });
        document.getElementById('bulkActivateStaffBtn')?.addEventListener('click', () => void this.setSelectedAccountStatus('active'));
        document.getElementById('bulkDeactivateStaffBtn')?.addEventListener('click', () => void this.setSelectedAccountStatus('inactive'));
        document.getElementById('bulkManageStaffRolesBtn')?.addEventListener('click', () => void this.openRoleManager([...this.state.selectedStaffIds]));
        document.getElementById('bulkResetStaffPasswordsBtn')?.addEventListener('click', () => void this.resetSelectedPasswords([...this.state.selectedStaffIds]));
        document.getElementById('clearStaffSelectionBtn')?.addEventListener('click', () => {
            this.state.selectedStaffIds.clear();
            this.renderTable();
        });
        document.getElementById('saveStaffRolesBtn')?.addEventListener('click', () => void this.saveManagedRoles());
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

        document.getElementById('printStaffBtn')?.addEventListener('click', () => {
            if (window.AuthContext?.canPrint?.('staff') === false) {
                this.showToast('You do not have permission to print staff records.', 'error');
                return;
            }
            document.body.classList.add('staff-directory-printing');
            window.addEventListener('afterprint', () => document.body.classList.remove('staff-directory-printing'), { once: true });
            window.print();
        });

        document.getElementById('staffTableBody')?.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-staff-action]');
            if (!button) return;

            const action = button.dataset.staffAction;
            if (action === 'resend-invitation') {
                const userId = Number(button.dataset.userId || 0);
                if (!userId) return;
                button.disabled = true;
                void window.API.staffMigration.resendInvitation(userId).then(() => {
                    showNotification('A replacement staff invitation was queued', NOTIFICATION_TYPES.SUCCESS);
                    void this.loadStaff();
                }).catch(error => {
                    showNotification(error.message || 'Could not queue invitation', NOTIFICATION_TYPES.ERROR);
                    button.disabled = false;
                });
                return;
            }
            if (action === 'resend-setup-otp') {
                const userId = Number(button.dataset.userId || 0);
                if (!userId) return;
                button.disabled = true;
                void window.API.staffMigration.resendSetupOtp(userId).then(() => {
                    showNotification('A new setup verification code was sent', NOTIFICATION_TYPES.SUCCESS);
                    void this.loadStaff();
                }).catch(error => {
                    showNotification(error.message || 'Could not resend the verification code', NOTIFICATION_TYPES.ERROR);
                    button.disabled = false;
                });
                return;
            }
            if (action === 'cancel-invitation') {
                const userId = Number(button.dataset.userId || 0);
                if (!userId) return;
                const confirmed = await window.confirmAction(
                    'Cancel staff invitation',
                    'The pending setup link will stop working and queued invitation emails will be cancelled.',
                    { confirmText: 'Cancel invitation', danger: true },
                );
                if (!confirmed) return;
                button.disabled = true;
                void window.API.staffMigration.cancelInvitation(userId).then(() => {
                    showNotification('Staff invitation cancelled', NOTIFICATION_TYPES.SUCCESS);
                    void this.loadStaff();
                }).catch(error => {
                    showNotification(error.message || 'Could not cancel invitation', NOTIFICATION_TYPES.ERROR);
                    button.disabled = false;
                });
                return;
            }
            const staffId = Number(button.dataset.staffId || 0);
            if (!staffId) return;

            if (action === 'view') {
                void this.viewStaff(staffId);
            } else if (action === 'edit') {
                void this.editStaff(staffId);
            } else if (action === 'manage-roles') {
                void this.openRoleManager([staffId]);
            } else if (action === 'activate-account') {
                void this.setAccountStatus([staffId], 'active');
            } else if (action === 'deactivate-account') {
                void this.setAccountStatus([staffId], 'inactive');
            } else if (action === 'reset-password') {
                void this.sendPasswordReset(staffId);
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
        this.state.currentPage = 1;
        this.state.selectedStaffIds.clear();
        void this.loadStaff({ force: true, page: 1 });
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
        const selectable = this.canManageStaffRoles() && this.getRoleMode() === 'operations';
        const labels = {
            staff_no: 'Staff No',
            name: 'Name',
            department: 'Department',
            roles: 'Roles',
            type: 'Type',
            position: 'Employment Position',
            contact: 'Contacts',
            account: 'Account & invitation',
            source: 'Staff source',
            payroll: 'Payroll',
            status: 'Status',
            actions: 'Actions'
        };

        header.innerHTML = `${selectable ? '<th scope="col" class="text-center"><input class="form-check-input" type="checkbox" id="selectAllStaffOnPage" aria-label="Select all staff on this page"></th>' : ''}${this.getLayoutConfig().columns
            .map(column => `<th class="${column === 'actions' ? 'text-end staff-actions-column' : ''}">${labels[column] || column}</th>`)
            .join('')}`;
    },

    renderTable() {
        const tbody = document.getElementById('staffTableBody');
        if (!tbody) return;
        const columns = this.getLayoutConfig().columns;
        const selectable = this.canManageStaffRoles() && this.getRoleMode() === 'operations';

        if (this.state.filteredStaff.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${columns.length + (selectable ? 1 : 0)}" class="text-center text-muted py-4">No staff found</td></tr>`;
            this.renderBulkActions();
            this.renderPagination();
            return;
        }

        tbody.innerHTML = this.state.filteredStaff.map((staff) => {
            return `
                <tr>
                    ${selectable ? `<td class="text-center"><input class="form-check-input" type="checkbox" data-select-staff value="${Number(staff.id)}" aria-label="Select ${this.escapeHtml(staff.full_name || `${staff.first_name || ''} ${staff.last_name || ''}`)}" ${staff.user_id ? '' : 'disabled title="Staff account has not been created"'} ${this.state.selectedStaffIds.has(Number(staff.id)) ? 'checked' : ''}></td>` : ''}
                    ${columns.map(column => this.renderStaffCell(staff, column)).join('')}
                </tr>
            `;
        }).join('');
        this.renderBulkActions();
        this.renderPagination();
        this.syncSelectAllCheckbox();
    },

    renderStaffCell(staff, column) {
        const fullName = staff.full_name || `${staff.first_name || ''} ${staff.last_name || ''}`.trim() || 'Unnamed staff';
        const position = staff.display_position || staff.position || '-';
        const cells = {
            staff_no: `<td>${this.escapeHtml(staff.staff_no || '-')}</td>`,
            name: `<td><strong>${this.escapeHtml(fullName)}</strong>${this.getRoleMode() === 'operations' ? `<br>${this.renderAccountStatusBadge(staff)}` : ''}</td>`,
            source: `<td>${this.renderEmploymentSource(staff)}</td>`,
            department: `<td>${this.escapeHtml(staff.department_name || '-')}</td>`,
            roles: `<td>${this.renderRoleNames(staff)}</td>`,
            type: `<td>${this.renderStaffType(staff)}</td>`,
            position: `<td>${this.escapeHtml(position)}</td>`,
            contact: `<td>${this.renderContact(staff)}</td>`,
            account: `<td>${this.renderInvitationState(staff)}</td>`,
            payroll: `<td>${this.renderPayrollState(staff)}</td>`,
            status: `<td>${this.renderStatusBadge(staff)}</td>`,
            actions: `<td class="text-end staff-actions-column">${this.renderActionButtons(staff)}</td>`,
        };
        return cells[column] || '';
    },

    renderContact(staff) {
        const parts = [];
        if (staff.phone) parts.push(`<div>${this.escapeHtml(staff.phone)}</div>`);
        if (staff.email) parts.push(`<small class="text-muted">${this.escapeHtml(staff.email)}</small>`);
        return parts.length ? parts.join('') : '-';
    },

    renderRoleNames(staff) {
        const raw = staff.role_names || staff.role_name || '';
        const roles = Array.isArray(raw) ? raw : String(raw).split(',');
        const names = [...new Set(roles.map(role => String(role?.name || role?.role_name || role || '').trim()).filter(Boolean))];
        return names.length
            ? names.map(name => `<span class="badge bg-light text-dark border me-1 mb-1">${this.escapeHtml(name)}</span>`).join('')
            : '<span class="text-muted">-</span>';
    },

    renderInvitationState(staff) {
        if (!staff.user_id) return '<span class="badge bg-secondary">No account</span>';
        const profileComplete = Number(staff.profile_completed) === 1;
        const profile = profileComplete ? 'Profile complete' : 'Profile pending';
        const delivery = staff.invitation_delivery_status || 'not_queued';
        const status = staff.invitation_status || 'not_sent';
        const canResendOtp = status === 'accepted' && !profileComplete;
        const invitationActions = Number(staff.setup_required) === 1
            ? `<div class="dropdown d-inline-block ms-1"><button type="button" class="btn btn-sm btn-link p-0" data-bs-toggle="dropdown" aria-label="Staff invitation actions"><i class="bi bi-three-dots-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" type="button" data-staff-action="resend-invitation" data-user-id="${Number(staff.user_id)}">Resend setup link</button></li>${status === 'pending' ? `<li><button class="dropdown-item text-danger" type="button" data-staff-action="cancel-invitation" data-user-id="${Number(staff.user_id)}">Cancel invitation</button></li>` : ''}</ul></div>`
            : canResendOtp
                ? `<div class="dropdown d-inline-block ms-1"><button type="button" class="btn btn-sm btn-link p-0" data-bs-toggle="dropdown" aria-label="Staff setup actions"><i class="bi bi-three-dots-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><button class="dropdown-item" type="button" data-staff-action="resend-setup-otp" data-user-id="${Number(staff.user_id)}">Resend verification code</button></li></ul></div>`
                : '';
        return `<span class="small">Invitation: ${this.escapeHtml(status)}<br>Email: ${this.escapeHtml(delivery)} · ${profile}</span>${invitationActions}`;
    },

    renderEmploymentSource(staff) {
        return this.escapeHtml(this.sourceLabel(staff.employment_source));
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
        const staffId = Number(staff.id);
        const actions = new Set(this.getLayoutConfig().actions);
        const menu = [];
        if (actions.has('view')) menu.push(`<li><button class="dropdown-item" type="button" data-staff-action="view" data-staff-id="${staffId}"><i class="bi bi-eye me-2"></i>View profile</button></li>`);
        if (actions.has('edit') && this.canManageDirectory()) menu.push(`<li><button class="dropdown-item" type="button" data-staff-action="edit" data-staff-id="${staffId}"><i class="bi bi-pencil me-2"></i>Edit staff details</button></li>`);
        if (this.canManageStaffRoles() && staff.user_id) {
            menu.push(`<li><button class="dropdown-item" type="button" data-staff-action="manage-roles" data-staff-id="${staffId}"><i class="bi bi-person-lock me-2"></i>Manage system roles</button></li>`);
            {
                const active = staff.user_status === 'active';
                const accountAction = active ? 'deactivate-account' : 'activate-account';
                const accountLabel = active ? 'Deactivate account' : (staff.user_status === 'pending' ? 'Account setup pending' : 'Activate account');
                menu.push(`<li><button class="dropdown-item ${active ? 'text-danger' : 'text-success'}" type="button" data-staff-action="${accountAction}" data-staff-id="${staffId}" ${staff.user_status === 'pending' && !active ? 'disabled' : ''}><i class="bi ${active ? 'bi-person-x' : 'bi-person-check'} me-2"></i>${accountLabel}</button></li>`);
                if (Number(staff.setup_required) === 1) {
                    menu.push(`<li><button class="dropdown-item" type="button" data-staff-action="resend-invitation" data-user-id="${Number(staff.user_id)}"><i class="bi bi-envelope-arrow-up me-2"></i>Resend setup invitation</button></li>`);
                    if (staff.invitation_status === 'pending') menu.push(`<li><button class="dropdown-item text-danger" type="button" data-staff-action="cancel-invitation" data-user-id="${Number(staff.user_id)}"><i class="bi bi-x-circle me-2"></i>Cancel setup invitation</button></li>`);
                } else if (staff.invitation_status === 'accepted' && Number(staff.profile_completed) !== 1) {
                    menu.push(`<li><button class="dropdown-item" type="button" data-staff-action="resend-setup-otp" data-user-id="${Number(staff.user_id)}"><i class="bi bi-shield-lock me-2"></i>Resend setup verification code</button></li>`);
                } else if (staff.email) {
                    menu.push(`<li><button class="dropdown-item" type="button" data-staff-action="reset-password" data-staff-id="${staffId}"><i class="bi bi-key me-2"></i>Send password reset link</button></li>`);
                }
            }
        }
        if (actions.has('performance')) menu.push(`<li><button class="dropdown-item" type="button" data-staff-action="performance" data-staff-id="${staffId}"><i class="bi bi-graph-up me-2"></i>Performance</button></li>`);
        if (actions.has('workload')) menu.push(`<li><button class="dropdown-item" type="button" data-staff-action="workload" data-staff-id="${staffId}"><i class="bi bi-calendar-week me-2"></i>Workload</button></li>`);
        if (actions.has('delete') && this.canDeleteDirectory()) menu.push(`<li><hr class="dropdown-divider"></li><li><button class="dropdown-item text-danger" type="button" data-staff-action="delete" data-staff-id="${staffId}"><i class="bi bi-trash me-2"></i>Deactivate staff record</button></li>`);
        if (!menu.length) return '<span class="text-muted">—</span>';
        return `<div class="dropdown"><button class="btn btn-sm btn-light border" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for ${this.escapeHtml(staff.full_name || 'staff member')}"><i class="bi bi-three-dots-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end">${menu.join('')}</ul></div>`;
    },

    renderAccountStatusBadge(staff) {
        if (!staff.user_id) return '<span class="badge text-bg-light border">No account</span>';
        const status = String(staff.user_status || 'pending');
        const tone = status === 'active' ? 'success' : status === 'inactive' ? 'secondary' : status === 'suspended' ? 'danger' : 'warning text-dark';
        return `<span class="badge bg-${tone}">Account ${this.escapeHtml(status)}</span>`;
    },

    renderBulkActions() {
        const host = document.getElementById('staffBulkActions');
        if (!host) return;
        const selected = this.state.selectedStaffIds.size;
        const visible = this.canManageStaffRoles() && this.getRoleMode() === 'operations' && selected > 0;
        host.classList.toggle('d-none', !visible);
        host.classList.toggle('d-flex', visible);
        const count = document.getElementById('staffSelectionCount');
        if (count) count.textContent = `${selected} selected`;
        const activate = document.getElementById('bulkActivateStaffBtn');
        if (activate) activate.disabled = visible && this.state.staff.some(row => this.state.selectedStaffIds.has(Number(row.id)) && row.user_status === 'pending');
    },

    syncSelectAllCheckbox() {
        const checkbox = document.getElementById('selectAllStaffOnPage');
        if (!checkbox) return;
        const selectableRows = this.state.staff.filter(row => Boolean(row.user_id));
        const selectedOnPage = selectableRows.filter(row => this.state.selectedStaffIds.has(Number(row.id))).length;
        checkbox.checked = selectableRows.length > 0 && selectedOnPage === selectableRows.length;
        checkbox.indeterminate = selectedOnPage > 0 && selectedOnPage < selectableRows.length;
    },

    renderPagination() {
        const host = document.getElementById('staffTablePagination');
        if (!host) return;
        const page = Number(this.state.pagination?.page || this.state.currentPage || 1);
        const limit = Number(this.state.pagination?.limit || this.state.pageSize);
        const total = Number(this.state.pagination?.total || 0);
        const totalPages = Math.max(1, Number(this.state.pagination?.total_pages || Math.ceil(total / limit) || 1));
        const from = total ? ((page - 1) * limit) + 1 : 0;
        const to = Math.min(page * limit, total);
        host.innerHTML = `<div class="small text-muted">Showing ${from}–${to} of ${total}</div><div class="d-flex align-items-center gap-2"><label class="small text-muted" for="staffPageSize">Rows</label><select id="staffPageSize" class="form-select form-select-sm" style="width:auto"><option value="10" ${limit === 10 ? 'selected' : ''}>10</option><option value="25" ${limit === 25 ? 'selected' : ''}>25</option><option value="50" ${limit === 50 ? 'selected' : ''}>50</option><option value="100" ${limit === 100 ? 'selected' : ''}>100</option></select><div class="btn-group btn-group-sm" role="group" aria-label="Staff table pages"><button class="btn btn-outline-secondary" type="button" data-staff-page="${Math.max(1, page - 1)}" ${page <= 1 ? 'disabled' : ''}>Previous</button><span class="btn btn-outline-secondary disabled">${page} / ${totalPages}</span><button class="btn btn-outline-secondary" type="button" data-staff-page="${Math.min(totalPages, page + 1)}" ${page >= totalPages ? 'disabled' : ''}>Next</button></div></div>`;
    },

    async setAccountStatus(staffIds, status) {
        if (!this.canManageStaffRoles()) return this.showToast('You do not have permission to manage staff accounts.', 'error');
        if (!staffIds.length) return;
        if (status === 'active' && this.state.staff.some(row => staffIds.includes(Number(row.id)) && row.user_status === 'pending')) {
            return this.showToast('Pending invitations must complete setup before their accounts can be activated.', 'warning');
        }
        const actionText = status === 'active' ? 'activate' : 'deactivate';
        const confirmed = await window.confirmAction(
            `${status === 'active' ? 'Activate' : 'Deactivate'} staff account${staffIds.length === 1 ? '' : 's'}`,
            `This will ${actionText} ${staffIds.length} selected staff account${staffIds.length === 1 ? '' : 's'}.`,
            { confirmText: status === 'active' ? 'Activate' : 'Deactivate', danger: status !== 'active' },
        );
        if (!confirmed) return;
        try {
            const response = await window.API.staff.manageStaffAccounts({ staff_ids: staffIds, action: 'set_status', status });
            this.showToast(response?.message || `Selected accounts ${status}.`, 'success');
            this.state.selectedStaffIds.clear();
            await this.loadStaff({ force: true });
        } catch (error) {
            this.showToast(error?.message || `Could not ${actionText} selected accounts.`, 'error');
        }
    },

    setSelectedAccountStatus(status) {
        return this.setAccountStatus([...this.state.selectedStaffIds], status);
    },

    async resetSelectedPasswords(staffIds) {
        if (!this.canManageStaffRoles()) return this.showToast('You do not have permission to manage staff accounts.', 'error');
        const ids = [...new Set(staffIds.map(Number).filter(id => id > 0))];
        if (!ids.length) return;
        const confirmed = await window.confirmAction(
            'Send password reset links',
            `Send secure password reset instructions to ${ids.length} selected staff account${ids.length === 1 ? '' : 's'} by email?`,
            { confirmText: 'Send reset links' },
        );
        if (!confirmed) return;
        let sent = 0;
        const failed = [];
        for (const id of ids) {
            try {
                await window.API.staff.requestStaffPasswordReset(id);
                sent++;
            } catch (error) {
                failed.push(id);
                console.warn(`Password reset request failed for staff record ${id}`, error);
            }
        }
        this.state.selectedStaffIds.clear();
        this.renderTable();
        if (failed.length) {
            this.showToast(`${sent} reset link${sent === 1 ? '' : 's'} sent; ${failed.length} could not be sent.`, 'warning');
        } else {
            this.showToast(`Password reset instructions sent to ${sent} staff account${sent === 1 ? '' : 's'}.`, 'success');
        }
    },

    async openRoleManager(staffIds) {
        if (!this.canManageStaffRoles()) return this.showToast('You do not have permission to manage staff roles.', 'error');
        const ids = [...new Set(staffIds.map(Number).filter(id => id > 0))];
        if (!ids.length) return;
        if (!this.state.roles.length) await this.loadRoles({ force: true });
        if (!this.state.roles.length) return this.showToast('No assignable school roles are available.', 'error');
        this.managingStaffIds = ids;
        let selected = [];
        let primaryRoleId = 0;
        if (ids.length === 1) {
            const response = await window.API.staff.getRoleAssignments(ids[0]);
            const rows = this.extractList(response, 'roles');
            const payload = response?.data?.assignments || response?.assignments;
            const assignments = Array.isArray(payload) ? payload : rows;
            selected = assignments.map(role => Number(role.role_id || role.id)).filter(Boolean);
            primaryRoleId = Number(assignments.find(role => Number(role.is_primary) === 1)?.role_id || assignments.find(role => Number(role.is_primary) === 1)?.id || 0);
        }
        this.selectedRoleIds = selected;
        this.selectedPrimaryRoleId = primaryRoleId;
        const title = document.getElementById('staffRoleManagerTitle');
        const description = document.getElementById('staffRoleManagerDescription');
        if (title) title.textContent = ids.length === 1 ? 'Manage staff roles' : `Set roles for ${ids.length} staff members`;
        if (description) description.textContent = ids.length === 1
            ? 'Choose the dashboard role. Other selected roles remain available as secondary roles.'
            : 'Choose the primary dashboard role for all selected staff. Other selected roles remain secondary.';
        const choices = document.getElementById('staffRoleManagerChoices');
        if (choices) choices.innerHTML = this.state.roles.map(role => {
            const roleId = Number(role.id);
            const checked = selected.includes(roleId);
            return `<div class="list-group-item d-flex gap-2 align-items-start"><input class="form-check-input mt-1" type="checkbox" aria-label="Assign ${this.escapeHtml(role.name || role.role_name || '')}" data-managed-role="${roleId}" ${checked ? 'checked' : ''}><input class="form-check-input mt-1" type="radio" name="managedPrimaryRole" aria-label="Make ${this.escapeHtml(role.name || role.role_name || '')} primary" data-managed-primary="${roleId}" ${primaryRoleId === roleId ? 'checked' : ''} ${checked ? '' : 'disabled'}><span><span class="fw-semibold">${this.escapeHtml(role.name || role.role_name || '')}</span>${role.description ? `<small class="d-block text-muted">${this.escapeHtml(role.description)}</small>` : ''}</span></div>`;
        }).join('');
        choices?.querySelectorAll('[data-managed-role]').forEach(input => input.addEventListener('change', () => {
            const radio = choices.querySelector(`[data-managed-primary="${input.dataset.managedRole}"]`);
            if (radio) {
                radio.disabled = !input.checked;
                if (input.checked && !choices.querySelector('[data-managed-primary]:checked')) radio.checked = true;
                if (!input.checked && radio.checked) radio.checked = false;
            }
        }));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('staffRoleManagerModal')).show();
    },

    async saveManagedRoles() {
        const roleIds = [...document.querySelectorAll('[data-managed-role]:checked')].map(input => Number(input.dataset.managedRole)).filter(id => id > 0);
        if (!roleIds.length) return this.showToast('Select at least one role.', 'warning');
        const primaryRoleId = Number(document.querySelector('[data-managed-primary]:checked')?.dataset.managedPrimary || 0);
        if (!roleIds.includes(primaryRoleId)) return this.showToast('Choose which selected role determines the staff dashboard.', 'warning');
        const staffIds = this.managingStaffIds || [];
        if (!staffIds.length) return;
        try {
            await window.API.staff.manageStaffAccounts({ staff_ids: staffIds, action: 'set_roles', role_ids: roleIds, primary_role_id: primaryRoleId });
            bootstrap.Modal.getInstance(document.getElementById('staffRoleManagerModal'))?.hide();
            this.state.selectedStaffIds.clear();
            this.showToast('Staff roles updated.', 'success');
            await this.loadStaff({ force: true });
        } catch (error) {
            this.showToast(error?.message || 'Could not update staff roles.', 'error');
        }
    },

    async sendPasswordReset(staffId) {
        if (!this.canManageStaffRoles()) return this.showToast('You do not have permission to manage staff accounts.', 'error');
        const staff = this.state.staff.find(row => Number(row.id) === Number(staffId));
        if (!staff?.email || !window.API?.staff?.requestStaffPasswordReset) return this.showToast('A staff account email is unavailable.', 'error');
        const confirmed = await window.confirmAction('Send password reset link', `Send a secure password reset link to ${staff.email}?`, { confirmText: 'Send reset link' });
        if (!confirmed) return;
        try {
            await window.API.staff.requestStaffPasswordReset(staffId);
            this.showToast('Password reset link requested for the staff member.', 'success');
        } catch (error) {
            this.showToast(error?.message || 'Could not send the password reset link.', 'error');
        }
    },

    openRoute(route, staffId) {
        window.location.href = `${window.APP_BASE || ''}/home.php?route=${encodeURIComponent(route)}&staff_id=${encodeURIComponent(staffId)}`;
    },

    showAddModal() {
        if (!this.canManageDirectory()) {
            this.showToast('You do not have permission to add staff', 'error');
            return;
        }
        document.getElementById('staffModalTitle').textContent = 'Add Staff';
        document.getElementById('staffId').value = '';
        document.getElementById('staffForm').reset();
        this.populateStaffClassificationDropdowns('', '');
        this.setValue('contractType', 'permanent');
        
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
            first_name: this.getValue('firstName'),
            last_name: this.getValue('lastName'),
            email: this.getValue('email'),
            department_id: this.getValue('department') || null,
            staff_type_id: this.getValue('staff_type_id') || null,
            staff_category_id: this.getValue('staff_category_id') || null,
            role_id: this.getValue('roleId') || undefined,
            contract_type: this.getValue('contractType') || 'permanent'
        };

        const requiredForCreate = {
            first_name: 'First name',
            last_name: 'Last name',
            email: 'Email',
            department_id: 'Department',
            staff_type_id: 'Staff type',
            staff_category_id: 'Staff category',
            role_id: 'System role',
            contract_type: 'Contract type'
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
            display_position: staff.display_position || staff.position || ''
        };
    },

    populateEditForm(staff) {
        const normalized = this.normalizeStaffRecord(staff);
        this.setValue('staffId', normalized.id);
        this.setValue('firstName', normalized.first_name || '');
        this.setValue('lastName', normalized.last_name || '');
        this.setValue('email', normalized.email || '');
        this.setValue('department', normalized.department_id || '');
        this.setValue('staff_type_id', normalized.staff_type_id || '');
        this.populateStaffClassificationDropdowns(normalized.staff_type_id || '', normalized.staff_category_id || '');
        this.setValue('roleId', normalized.role_id || '');
        this.setValue('contractType', normalized.contract_type || 'permanent');
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
                        ${this.detailRow('Employment Position', normalized.display_position || 'Not set')}
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
                        ${this.detailRow('Salary', 'Managed from the primary role in Payroll')}
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
        if (!(await window.confirmAction('Confirm Deletion', 'Are you sure you want to delete this staff member?', { confirmText: 'Delete', danger: true }))) return;

        try {
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

        const headers = ['Staff No', 'Name', 'Department', 'Roles', 'Contacts'];
        const rows = this.state.filteredStaff.map(staff => [
            staff.staff_no || '',
            `${staff.first_name} ${staff.last_name}`,
            staff.department_name || '',
            (Array.isArray(staff.role_names) ? staff.role_names.map(role => role.name || role).join(', ') : staff.role_names || staff.role_name || ''),
            [staff.phone, staff.email].filter(Boolean).join(' / ')
        ]);

        const csvCell = value => `"${String(value ?? '').replace(/"/g, '""')}"`;
        const csv = [headers, ...rows].map(row => row.map(csvCell).join(',')).join('\r\n');
        if (window.KingswayFileLifecycle?.exportText) {
            window.KingswayFileLifecycle.exportText(csv, 'staff_export.csv', 'text/csv');
        }
    },

    sourceLabel(source) {
        const labels = {
            existing_import: 'Existing · spreadsheet import',
            existing_manual: 'Existing · manual record',
            new_online_hire: 'New hire · online applicant',
            new_walk_in_hire: 'New hire · walk-in applicant',
            new_school_entered_hire: 'New hire · school-entered',
        };
        return labels[source] || 'Existing · manual record';
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
