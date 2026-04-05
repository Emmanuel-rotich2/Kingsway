/**
 * Role Permission Matrix Controller
 * Compact matrix: rows=roles, columns=permission modules, cells=count
 * Role: System Administrator (ID 2)
 */
const rolePermissionMatrixController = {
    roles: [],
    permissions: [],
    rolePermissions: {},
    filters: { search: '' },

    init: async function () {
        if (!AuthContext.isAuthenticated()) { window.location.href = '/'; return; }
        if (!AuthContext.hasPermission('system_view')) {
            const el = document.getElementById('mainTable');
            if (el) el.innerHTML = '<div class="alert alert-danger m-3">Access denied</div>';
            return;
        }
        await this.loadData();
        this.render();
        this.bindEvents();
    },

    loadData: async function () {
        try {
            const [rolesRes, permsRes] = await Promise.all([
                window.API.system.getRoles(),
                window.API.system.getPermissions()
            ]);
            this.roles = rolesRes?.data ?? rolesRes ?? [];
            this.permissions = permsRes?.data ?? permsRes ?? [];
        } catch (e) {
            console.error('role_permission_matrix: loadData error', e);
            showNotification('Failed to load matrix data', 'error');
        }
    },

    loadRolePermissions: async function (roleId) {
        if (this.rolePermissions[roleId] !== undefined) return;
        try {
            const res = await window.API.system.getRolePermissions(roleId);
            const perms = res?.data ?? res ?? [];
            this.rolePermissions[roleId] = new Set(
                perms.map(p => p.permission_id ?? p.id)
            );
        } catch (e) {
            console.error('role_permission_matrix: loadRolePermissions error', roleId, e);
            this.rolePermissions[roleId] = new Set();
        }
    },

    getModules: function () {
        return [...new Set(this.permissions.map(p => p.module).filter(Boolean))].sort();
    },

    render: function () {
        const container = document.getElementById('mainTable');
        if (!container) return;

        const term = this.filters.search.toLowerCase();
        const filteredRoles = this.roles.filter(r =>
            !term || (r.name || r.role_name || '').toLowerCase().includes(term)
        );
        const modules = this.getModules();

        if (!filteredRoles.length || !modules.length) {
            container.innerHTML = '<div class="alert alert-info m-3">No data to display. ' +
                (!filteredRoles.length ? 'No roles match filter.' : 'No permission modules found.') + '</div>';
            return;
        }

        // Build module -> permissions map
        const modPerms = {};
        modules.forEach(m => {
            modPerms[m] = this.permissions.filter(p => p.module === m).map(p => p.id);
        });

        let html = `<div class="mb-3 d-flex gap-2">
            <input type="text" class="form-control form-control-sm" id="searchFilter"
                placeholder="Filter roles..." value="${this.esc(this.filters.search)}" style="max-width:240px">
            <button class="btn btn-sm btn-outline-secondary" id="refreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
        <div class="table-responsive">
        <table class="table table-sm table-bordered table-hover align-middle" style="font-size:0.82rem">
            <thead class="table-dark">
                <tr>
                    <th class="text-nowrap">Role</th>
                    ${modules.map(m => `<th class="text-center text-nowrap" title="${this.esc(m)}">${this.esc(m)}</th>`).join('')}
                    <th class="text-center">Total</th>
                </tr>
            </thead>
            <tbody>`;

        filteredRoles.forEach(role => {
            const roleId = role.id ?? role.role_id;
            const assignedSet = this.rolePermissions[roleId] ?? new Set();
            let rowTotal = 0;
            const cells = modules.map(m => {
                const count = modPerms[m].filter(pid => assignedSet.has(pid)).length;
                rowTotal += count;
                const total = modPerms[m].length;
                const pct = total ? Math.round((count / total) * 100) : 0;
                const cls = count === 0 ? 'text-muted' : count === total ? 'text-success fw-bold' : 'text-primary';
                return `<td class="text-center ${cls}" title="${count}/${total} (${pct}%)">${count > 0 ? count : '-'}</td>`;
            });

            html += `<tr>
                <td class="text-nowrap fw-semibold">${this.esc(role.name || role.role_name || '')}</td>
                ${cells.join('')}
                <td class="text-center fw-bold">${rowTotal}</td>
            </tr>`;
        });

        const totalPerms = this.permissions.length;
        html += `</tbody>
            <tfoot class="table-light">
                <tr>
                    <td class="fw-semibold">Permissions per module</td>
                    ${modules.map(m => `<td class="text-center text-muted small">${(modPerms[m]||[]).length}</td>`).join('')}
                    <td class="text-center text-muted small">${totalPerms}</td>
                </tr>
            </tfoot>
        </table></div>
        <small class="text-muted">${filteredRoles.length} of ${this.roles.length} roles &bull; ${modules.length} modules &bull; ${totalPerms} permissions</small>`;

        container.innerHTML = html;
        this.bindFilterEvents();
    },

    bindEvents: function () {
        // handled after render via bindFilterEvents
    },

    bindFilterEvents: function () {
        document.getElementById('searchFilter')?.addEventListener('input', e => {
            this.filters.search = e.target.value;
            this.render();
        });
        document.getElementById('refreshBtn')?.addEventListener('click', async () => {
            this.rolePermissions = {};
            await this.loadData();
            await this.prefetchRolePermissions();
            this.render();
        });
    },

    prefetchRolePermissions: async function () {
        await Promise.all(this.roles.map(r => this.loadRolePermissions(r.id ?? r.role_id)));
    },

    esc: function (s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
};

document.addEventListener('DOMContentLoaded', async () => {
    await rolePermissionMatrixController.init();
    // Prefetch all role permissions for matrix display
    if (rolePermissionMatrixController.roles.length) {
        await rolePermissionMatrixController.prefetchRolePermissions();
        rolePermissionMatrixController.render();
    }
});
