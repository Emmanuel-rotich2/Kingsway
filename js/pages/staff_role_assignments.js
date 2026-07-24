/**
 * Staff Role Assignments Controller
 * Handles staff_role_assignments.php
 * Uses existing api.js JWT authentication
 */
const StaffRoleAssignmentsController = {
    staff: [],
    roles: [],
    selected: null,
    assigned: [],

    async init() {
        if (typeof AuthContext !== 'undefined') {
            await AuthContext.ready();
        }

        if (!AuthContext?.isAuthenticated()) {
            window.location.href = (window.APP_BASE || "") + "/index.php";
            return;
        }

        if (!AuthContext.canManage('staff')) {
            showNotification('You do not have permission to manage staff roles', 'error');
            return;
        }

        this.bindEvents();
        await Promise.all([this.loadStaff(), this.loadRoles()]);
    },

    bindEvents() {
        document.getElementById('roleStaffSearch')?.addEventListener('input', () => this.renderStaff());
        document.getElementById('assignRoleBtn')?.addEventListener('click', () => this.assign());
        document.getElementById('refreshRolesBtn')?.addEventListener('click', () => this.selected && this.select(this.selected.id));
    },

    async loadStaff() {
        try {
            const response = await window.API.staff.list({ limit: 500 });
            this.staff = this.extractStaffList(response);
            this.renderStaff();
        } catch (error) {
            this.notify(error.message, 'error');
        }
    },

    extractStaffList(response) {
        if (!response) return [];
        if (Array.isArray(response)) return response;
        if (Array.isArray(response.staff)) return response.staff;
        if (Array.isArray(response.data?.staff)) return response.data.staff;
        if (Array.isArray(response.data)) return response.data;
        return [];
    },

    async loadRoles() {
        try {
            this.roles = await window.API.staff.getAvailableRoles();
            this.renderRoleSelect();
        } catch (error) {
            this.notify(error.message, 'error');
        }
    },

    renderStaff() {
        const q = (document.getElementById('roleStaffSearch')?.value || '').toLowerCase();
        const rows = this.staff.filter(s => `${s.staff_no || ''} ${s.first_name || ''} ${s.last_name || ''} ${s.position || ''}`.toLowerCase().includes(q));
        document.getElementById('roleStaffList').innerHTML = rows.length ? rows.map(s => `<button class="list-group-item list-group-item-action ${this.selected?.id == s.id ? 'active' : ''}" onclick="StaffRoleAssignmentsController.select(${s.id})"><div class="fw-semibold">${this.esc((s.first_name || '') + ' ' + (s.last_name || ''))}</div><small>${this.esc(s.staff_no || '')} · ${this.esc(s.position || '')}</small></button>`).join('') : '<div class="p-4 text-center text-muted">No staff found.</div>';
    },

    renderRoleSelect() {
        const assignedIds = new Set(this.assigned.map(r => Number(r.role_id)));
        document.getElementById('availableRoleId').innerHTML = '<option value="">Select role</option>' + this.roles.filter(r => !assignedIds.has(Number(r.id))).map(r => `<option value="${r.id}">${this.esc(r.name)}</option>`).join('');
    },

    async select(id) {
        this.selected = this.staff.find(s => Number(s.id) === Number(id));
        this.renderStaff();
        document.getElementById('roleAssignmentEmpty').hidden = true;
        document.getElementById('roleAssignmentPanel').hidden = false;
        document.getElementById('selectedStaffName').textContent = `${this.selected?.first_name || ''} ${this.selected?.last_name || ''}`.trim();
        document.getElementById('selectedStaffMeta').textContent = `${this.selected?.staff_no || ''} · ${this.selected?.position || ''}`;
        try {
            this.assigned = await window.API.staff.getRoleAssignments(id);
            this.renderAssigned();
        } catch (error) {
            this.notify(error.message, 'error');
        }
    },

    renderAssigned() {
        const box = document.getElementById('assignedRoles');
        box.innerHTML = this.assigned.length ? this.assigned.map(r => `<span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle p-2">${this.esc(r.name)} <button class="btn btn-link btn-sm p-0 ms-1 text-danger" title="Remove" onclick="StaffRoleAssignmentsController.revoke(${r.role_id})"><i class="bi bi-x-circle"></i></button></span>`).join('') : '<span class="text-muted">No roles assigned.</span>';
        this.renderRoleSelect();
    },

    async assign() {
        if (!this.selected) return;
        const roleId = Number(document.getElementById('availableRoleId').value);
        if (!roleId) return this.notify('Select a role', 'error');
        try {
            await window.API.staff.assignStaffRole({ staff_id: this.selected.id, role_id: roleId });
            await this.select(this.selected.id);
            this.notify('Role assigned', 'success');
        } catch (error) {
            this.notify(error.message, 'error');
        }
    },

    async revoke(roleId) {
        if (!this.selected || !confirm('Remove this role from the selected staff member?')) return;
        try {
            await window.API.staff.revokeStaffRole(this.selected.id, roleId);
            await this.select(this.selected.id);
            this.notify('Role removed', 'success');
        } catch (error) {
            this.notify(error.message, 'error');
        }
    },

    notify(m, t = 'info') {
        window.API?.showNotification?.(m, t) || alert(m);
    },

    esc(v) {
        const d = document.createElement('div');
        d.textContent = String(v ?? '');
        return d.innerHTML;
    }
};

document.addEventListener('DOMContentLoaded', () => StaffRoleAssignmentsController.init());