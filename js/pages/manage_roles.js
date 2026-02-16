/**
 * Manage Roles Page Controller
 * Full CRUD for roles with permissions matrix
 */
const ManageRolesController = {
    roles: [],
    filteredRoles: [],
    permissions: [],
    currentPage: 1,
    perPage: 15,
    editingRoleId: null,

    init: async function () {
        this.attachListeners();
        await this.loadData();
    },

    loadData: async function () {
        try {
            await Promise.all([this.loadRoles(), this.loadPermissions()]);
        } catch (e) {
            console.error('Init error:', e);
        }
    },

    // ==================== DATA LOADING ====================

    loadRoles: async function () {
        try {
            const resp = await API.users.getRoles();
            const data = resp?.data || resp || [];
            this.roles = Array.isArray(data) ? data : data.roles || [];
            this.applyFilters();
            this.updateStats();
        } catch (e) {
            console.error('Error loading roles:', e);
            this.roles = [];
            this.renderTable();
        }
    },

    loadPermissions: async function () {
        try {
            const resp = await API.users.getPermissions();
            const data = resp?.data || resp || [];
            this.permissions = Array.isArray(data) ? data : data.permissions || [];
        } catch (e) {
            console.error('Error loading permissions:', e);
            this.permissions = [];
        }
    },

    // ==================== STATS ====================

    updateStats: function () {
        const total = this.roles.length;
        const active = this.roles.filter(r => (r.status || 'active') === 'active').length;
        const custom = this.roles.filter(r => r.is_custom === 1 || r.is_custom === true || r.type === 'custom').length;
        const totalUsers = this.roles.reduce((sum, r) => sum + (parseInt(r.user_count) || 0), 0);

        const el = (id, val) => { const e = document.getElementById(id); if (e) e.textContent = val; };
        el('totalRoles', total);
        el('activeRoles', active);
        el('customRoles', custom);
        el('totalUsers', totalUsers);
    },

    // ==================== FILTERING ====================

    applyFilters: function () {
        const search = (document.getElementById('searchRoles')?.value || '').toLowerCase();
        const status = document.getElementById('statusFilter')?.value || '';
        const type = document.getElementById('typeFilter')?.value || '';

        this.filteredRoles = this.roles.filter(r => {
            if (search) {
                const name = (r.name || r.role_name || '').toLowerCase();
                const desc = (r.description || '').toLowerCase();
                if (!name.includes(search) && !desc.includes(search)) return false;
            }
            if (status && (r.status || 'active') !== status) return false;
            if (type === 'system' && (r.is_custom === 1 || r.is_custom === true)) return false;
            if (type === 'custom' && !r.is_custom) return false;
            return true;
        });

        this.currentPage = 1;
        this.renderTable();
    },

    // ==================== TABLE RENDERING ====================

    renderTable: function () {
        const tbody = document.querySelector('#rolesTable tbody');
        if (!tbody) return;

        if (this.filteredRoles.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No roles found</td></tr>';
            this.renderPagination();
            return;
        }

        const start = (this.currentPage - 1) * this.perPage;
        const pageRoles = this.filteredRoles.slice(start, start + this.perPage);

        tbody.innerHTML = pageRoles.map(r => {
            const name = r.name || r.role_name || '-';
            const desc = r.description || '-';
            const isCustom = r.is_custom === 1 || r.is_custom === true || r.type === 'custom';
            const typeBadge = isCustom
                ? '<span class="badge bg-info">Custom</span>'
                : '<span class="badge bg-secondary">System</span>';
            const userCount = parseInt(r.user_count) || 0;
            const permCount = parseInt(r.permission_count) || 0;
            const status = r.status || 'active';
            const statusBadge = status === 'active'
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-warning">Inactive</span>';
            const roleId = r.id || r.role_id;

            return `<tr>
                <td><strong>${this.esc(name)}</strong></td>
                <td><small>${this.esc(desc.substring(0, 60))}${desc.length > 60 ? '...' : ''}</small></td>
                <td>${typeBadge}</td>
                <td><span class="badge bg-primary">${userCount}</span></td>
                <td><span class="badge bg-dark">${permCount}</span></td>
                <td>${statusBadge}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-info" onclick="ManageRolesController.viewRole(${roleId})" title="View">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-warning" onclick="ManageRolesController.editRole(${roleId})" title="Edit" data-permission="roles_manage">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-primary" onclick="ManageRolesController.managePermissions(${roleId})" title="Permissions" data-permission="roles_manage">
                            <i class="bi bi-shield-lock"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="ManageRolesController.deleteRole(${roleId})" title="Delete" data-permission="roles_manage">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>`;
        }).join('');

        this.renderPagination();
    },

    renderPagination: function () {
        const container = document.getElementById('pagination');
        if (!container) return;

        const totalPages = Math.ceil(this.filteredRoles.length / this.perPage);
        if (totalPages <= 1) { container.innerHTML = ''; return; }

        let html = `<li class="page-item ${this.currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="ManageRolesController.goToPage(${this.currentPage - 1}); return false;">&laquo;</a></li>`;

        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= this.currentPage - 2 && i <= this.currentPage + 2)) {
                html += `<li class="page-item ${i === this.currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="ManageRolesController.goToPage(${i}); return false;">${i}</a></li>`;
            } else if (i === this.currentPage - 3 || i === this.currentPage + 3) {
                html += '<li class="page-item disabled"><a class="page-link">...</a></li>';
            }
        }

        html += `<li class="page-item ${this.currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="ManageRolesController.goToPage(${this.currentPage + 1}); return false;">&raquo;</a></li>`;

        container.innerHTML = html;
    },

    goToPage: function (page) {
        const totalPages = Math.ceil(this.filteredRoles.length / this.perPage);
        if (page >= 1 && page <= totalPages) {
            this.currentPage = page;
            this.renderTable();
        }
    },

    // ==================== CRUD OPERATIONS ====================

    showAddModal: function () {
        this.editingRoleId = null;
        document.getElementById('roleModalTitle').textContent = 'Add New Role';
        document.getElementById('roleForm').reset();
        document.getElementById('roleId').value = '';
        document.getElementById('status').value = 'active';
        this.clearPermissionChecks();
        this.restoreModalForm();
        new bootstrap.Modal(document.getElementById('roleModal')).show();
    },

    editRole: async function (roleId) {
        const role = this.roles.find(r => (r.id || r.role_id) == roleId);
        if (!role) { this.notify('Role not found', 'error'); return; }

        this.editingRoleId = roleId;
        this.restoreModalForm();
        document.getElementById('roleModalTitle').textContent = 'Edit Role: ' + (role.name || role.role_name);
        document.getElementById('roleId').value = roleId;
        document.getElementById('roleName').value = role.name || role.role_name || '';
        document.getElementById('description').value = role.description || '';
        document.getElementById('status').value = role.status || 'active';

        this.clearPermissionChecks();
        try {
            const resp = await window.API.apiCall(`/users/roles-get/${roleId}`, 'GET');
            const roleData = resp?.data || resp;
            const perms = roleData?.permissions || [];
            perms.forEach(p => {
                const code = p.permission_code || p.code || p;
                const el = document.getElementById(code);
                if (el) el.checked = true;
            });
            this.updateModuleChecks();
        } catch (e) {
            console.warn('Could not load role permissions:', e);
        }

        const saveBtn = document.getElementById('saveRoleBtn');
        if (saveBtn) saveBtn.style.display = '';
        new bootstrap.Modal(document.getElementById('roleModal')).show();
    },

    saveRole: async function () {
        const name = document.getElementById('roleName').value.trim();
        if (!name) { this.notify('Role name is required', 'error'); return; }

        const selectedPermissions = [];
        document.querySelectorAll('.permission-check:checked').forEach(cb => {
            selectedPermissions.push(cb.id);
        });

        const data = {
            name: name,
            description: document.getElementById('description').value.trim(),
            status: document.getElementById('status').value,
            permissions: selectedPermissions
        };

        const btn = document.getElementById('saveRoleBtn');
        const origText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';

        try {
            const roleId = document.getElementById('roleId').value;

            if (roleId) {
                await window.API.apiCall(`/users/user/${roleId}`, 'PUT', { ...data, id: roleId, _action: 'update_role' });
            } else {
                await window.API.apiCall('/users/user', 'POST', { ...data, _action: 'create_role' });
            }

            bootstrap.Modal.getInstance(document.getElementById('roleModal')).hide();
            this.notify(roleId ? 'Role updated successfully' : 'Role created successfully', 'success');
            await this.loadRoles();
        } catch (e) {
            console.error('Save role error:', e);
            this.notify('Failed to save role: ' + (e.message || 'Unknown error'), 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = origText;
        }
    },

    deleteRole: async function (roleId) {
        const role = this.roles.find(r => (r.id || r.role_id) == roleId);
        const roleName = role ? (role.name || role.role_name) : 'this role';

        if (!confirm(`Are you sure you want to delete "${roleName}"? This cannot be undone.`)) return;

        try {
            await window.API.apiCall(`/users/user/${roleId}`, 'DELETE', { _action: 'delete_role', role_id: roleId });
            this.notify('Role deleted successfully', 'success');
            await this.loadRoles();
        } catch (e) {
            console.error('Delete role error:', e);
            this.notify('Failed to delete role: ' + (e.message || 'Unknown error'), 'error');
        }
    },

    viewRole: async function (roleId) {
        const role = this.roles.find(r => (r.id || r.role_id) == roleId);
        if (!role) return;

        try {
            const resp = await window.API.apiCall(`/users/roles-get/${roleId}`, 'GET');
            const roleData = resp?.data || resp || role;
            const perms = roleData?.permissions || [];
            const users = roleData?.users || [];

            const name = roleData.name || roleData.role_name || role.name || role.role_name || '-';
            const desc = roleData.description || role.description || 'No description';

            let permHtml = '';
            if (perms.length === 0) {
                permHtml = '<p class="text-muted">No permissions assigned</p>';
            } else {
                permHtml = '<ul class="list-unstyled mb-0">' + perms.map(p =>
                    `<li class="mb-1"><i class="bi bi-check-circle text-success me-1"></i><small>${this.esc(p.permission_name || p.permission_code || p.name || p)}</small></li>`
                ).join('') + '</ul>';
            }

            let userHtml = '';
            if (users.length === 0) {
                userHtml = '<p class="text-muted">No users assigned</p>';
            } else {
                userHtml = '<ul class="list-unstyled mb-0">' + users.map(u =>
                    `<li class="mb-1"><i class="bi bi-person me-1"></i><small>${this.esc(u.username || u.name || ((u.first_name || '') + ' ' + (u.last_name || '')).trim())}</small></li>`
                ).join('') + '</ul>';
            }

            const html = `
                <div class="mb-3">
                    <h5>${this.esc(name)}</h5>
                    <p class="text-muted mb-2">${this.esc(desc)}</p>
                    <span class="badge bg-${(roleData.status || 'active') === 'active' ? 'success' : 'warning'} me-2">${roleData.status || 'active'}</span>
                    <span class="badge bg-primary me-2">${perms.length} permissions</span>
                    <span class="badge bg-info">${users.length} users</span>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2">Permissions</h6>
                        <div style="max-height:250px;overflow-y:auto">${permHtml}</div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2">Users with this Role</h6>
                        <div style="max-height:250px;overflow-y:auto">${userHtml}</div>
                    </div>
                </div>`;

            this._showViewModal(name, html);
        } catch (e) {
            console.error('View role error:', e);
            this.notify('Failed to load role details', 'error');
        }
    },

    _showViewModal: function (title, html) {
        const modalEl = document.getElementById('roleModal');
        const body = modalEl.querySelector('.modal-body');
        const saveBtn = document.getElementById('saveRoleBtn');

        if (!this._originalFormHtml) {
            this._originalFormHtml = body.innerHTML;
        }

        document.getElementById('roleModalTitle').textContent = 'Role Details: ' + title;
        body.innerHTML = html;
        if (saveBtn) saveBtn.style.display = 'none';

        const modal = new bootstrap.Modal(modalEl);
        modal.show();

        const self = this;
        modalEl.addEventListener('hidden.bs.modal', function restore() {
            self.restoreModalForm();
            modalEl.removeEventListener('hidden.bs.modal', restore);
        });
    },

    restoreModalForm: function () {
        if (this._originalFormHtml) {
            const body = document.querySelector('#roleModal .modal-body');
            body.innerHTML = this._originalFormHtml;
            this.reattachPermissionListeners();
        }
        const saveBtn = document.getElementById('saveRoleBtn');
        if (saveBtn) saveBtn.style.display = '';
    },

    managePermissions: async function (roleId) {
        this.editRole(roleId);
    },

    // ==================== PERMISSION CHECKBOX HELPERS ====================

    clearPermissionChecks: function () {
        document.querySelectorAll('.permission-check').forEach(cb => cb.checked = false);
        document.querySelectorAll('.module-check').forEach(cb => { cb.checked = false; cb.indeterminate = false; });
    },

    toggleModule: function (moduleCheckbox) {
        const module = moduleCheckbox.dataset.module;
        const checked = moduleCheckbox.checked;
        document.querySelectorAll(`.permission-check[data-module="${module}"]`).forEach(cb => {
            cb.checked = checked;
        });
    },

    updateModuleChecks: function () {
        document.querySelectorAll('.module-check').forEach(mc => {
            const module = mc.dataset.module;
            const allInModule = document.querySelectorAll(`.permission-check[data-module="${module}"]`);
            const checkedInModule = document.querySelectorAll(`.permission-check[data-module="${module}"]:checked`);
            mc.checked = allInModule.length > 0 && allInModule.length === checkedInModule.length;
            mc.indeterminate = checkedInModule.length > 0 && checkedInModule.length < allInModule.length;
        });
    },

    reattachPermissionListeners: function () {
        document.querySelectorAll('.module-check').forEach(mc => {
            mc.addEventListener('change', () => this.toggleModule(mc));
        });
        document.querySelectorAll('.permission-check').forEach(cb => {
            cb.addEventListener('change', () => this.updateModuleChecks());
        });
    },

    // ==================== EXPORT ====================

    exportRoles: function () {
        if (this.filteredRoles.length === 0) { this.notify('No data to export', 'warning'); return; }

        const headers = ['Role Name', 'Description', 'Type', 'Users', 'Permissions', 'Status'];
        const rows = this.filteredRoles.map(r => [
            '"' + (r.name || r.role_name || '').replace(/"/g, '""') + '"',
            '"' + (r.description || '').replace(/"/g, '""') + '"',
            (r.is_custom === 1 || r.is_custom === true) ? 'Custom' : 'System',
            r.user_count || 0,
            r.permission_count || 0,
            r.status || 'active'
        ]);

        const csv = [headers.join(','), ...rows.map(r => r.join(','))].join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'roles_export.csv';
        a.click();
        URL.revokeObjectURL(url);
    },

    // ==================== EVENT LISTENERS ====================

    attachListeners: function () {
        document.getElementById('addRoleBtn')?.addEventListener('click', () => this.showAddModal());
        document.getElementById('saveRoleBtn')?.addEventListener('click', () => this.saveRole());
        document.getElementById('exportBtn')?.addEventListener('click', () => this.exportRoles());

        document.getElementById('searchRoles')?.addEventListener('keyup', () => {
            clearTimeout(this._searchTimeout);
            this._searchTimeout = setTimeout(() => this.applyFilters(), 300);
        });
        document.getElementById('statusFilter')?.addEventListener('change', () => this.applyFilters());
        document.getElementById('typeFilter')?.addEventListener('change', () => this.applyFilters());

        this.reattachPermissionListeners();
    },

    // ==================== UTILITIES ====================

    esc: function (text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    notify: function (message, type) {
        if (typeof showNotification === 'function') {
            showNotification(message, type);
        } else if (window.API?.showNotification) {
            window.API.showNotification(message, type);
        } else {
            alert((type === 'error' ? 'Error: ' : '') + message);
        }
    }
};

document.addEventListener('DOMContentLoaded', () => ManageRolesController.init());
