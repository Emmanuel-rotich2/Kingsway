/**
 * Role Navigation Config Controller
 * Assign sidebar items to roles
 * Role: System Administrator (ID 2)
 */
const roleNavigationConfigController = {
    roles: [],
    menuItems: [],
    assignments: [],
    selectedRoleId: null,
    filters: { search: '' },

    init: async function () {
        if (!AuthContext.isAuthenticated()) { window.location.href = '/'; return; }
        if (!AuthContext.hasPermission('system_view')) {
            const el = document.getElementById('mainTable');
            if (el) el.innerHTML = '<div class="alert alert-danger">Access denied</div>';
            return;
        }
        await this.loadData();
        this.render();
    },

    loadData: async function () {
        try {
            const [rolesRes, menusRes] = await Promise.all([
                window.API.system.getRoles(),
                window.API.system.getSidebarMenus()
            ]);
            this.roles = rolesRes?.data ?? rolesRes ?? [];
            this.menuItems = menusRes?.data ?? menusRes ?? [];
        } catch (e) {
            console.error('role_navigation_config: loadData error', e);
            showNotification('Failed to load navigation config', 'error');
        }
    },

    loadAssignments: async function (roleId) {
        try {
            const res = await window.API.system.getRoleSidebarAssignments(roleId);
            this.assignments = res?.data ?? res ?? [];
        } catch (e) {
            console.error('role_navigation_config: loadAssignments error', e);
            this.assignments = [];
        }
    },

    render: function () {
        const container = document.getElementById('mainTable');
        if (!container) return;

        const roleOptions = this.roles.map(r =>
            `<option value="${r.id || r.role_id}"${this.selectedRoleId == (r.id || r.role_id) ? ' selected' : ''}>${this.esc(r.name || r.role_name || '')}</option>`
        ).join('');

        let assignedIds = new Set(this.assignments.map(a => a.menu_item_id || a.id));

        const term = this.filters.search.toLowerCase();
        const filtered = this.menuItems.filter(m =>
            !term || (m.label || m.name || '').toLowerCase().includes(term) ||
            (m.route || m.url || '').toLowerCase().includes(term)
        );

        let html = `<div class="mb-3 d-flex gap-2 flex-wrap align-items-end">
            <div>
                <label class="form-label mb-1 small">Select Role</label>
                <select class="form-select form-select-sm" id="roleSelect" style="min-width:200px">
                    <option value="">-- Choose a Role --</option>
                    ${roleOptions}
                </select>
            </div>
            <input type="text" class="form-control form-control-sm" id="searchFilter"
                placeholder="Filter menu items..." value="${this.esc(this.filters.search)}" style="max-width:220px">
            <button class="btn btn-sm btn-outline-secondary" id="refreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>`;

        if (!this.selectedRoleId) {
            html += '<div class="alert alert-info">Select a role to view and manage its navigation assignments.</div>';
        } else if (!filtered.length) {
            html += '<div class="alert alert-info">No menu items found.</div>';
        } else {
            html += `<div class="table-responsive"><table class="table table-sm table-hover">
                <thead class="table-dark"><tr><th>Assigned</th><th>Label</th><th>Icon</th><th>Route</th></tr></thead>
                <tbody>`;
            filtered.forEach(item => {
                const isAssigned = assignedIds.has(item.id);
                html += `<tr>
                    <td class="text-center">
                        <input type="checkbox" class="form-check-input menu-assign-chk"
                            data-item-id="${item.id}" ${isAssigned ? 'checked' : ''}>
                    </td>
                    <td>${this.esc(item.label || item.name || '')}</td>
                    <td><i class="${this.esc(item.icon || '')}"></i></td>
                    <td><code class="small">${this.esc(item.route || item.url || '')}</code></td>
                </tr>`;
            });
            html += `</tbody></table></div>
            <small class="text-muted">${filtered.length} of ${this.menuItems.length} items shown</small>`;
        }

        container.innerHTML = html;
        this.bindFilterEvents();
    },

    toggleAssignment: async function (itemId, assign) {
        if (!this.selectedRoleId) return;
        try {
            if (assign) {
                await window.API.system.assignMenuToRole(this.selectedRoleId, itemId);
                showNotification('Menu item assigned', 'success');
            } else {
                await window.API.system.revokeMenuFromRole(this.selectedRoleId, itemId);
                showNotification('Menu item removed', 'success');
            }
            await this.loadAssignments(this.selectedRoleId);
        } catch (e) {
            console.error('role_navigation_config: toggleAssignment error', e);
            showNotification(e.message || 'Failed to update assignment', 'error');
            // revert checkbox
            await this.loadAssignments(this.selectedRoleId);
            this.render();
        }
    },

    bindFilterEvents: function () {
        document.getElementById('roleSelect')?.addEventListener('change', async e => {
            this.selectedRoleId = e.target.value || null;
            if (this.selectedRoleId) {
                await this.loadAssignments(this.selectedRoleId);
            } else {
                this.assignments = [];
            }
            this.render();
        });
        document.getElementById('searchFilter')?.addEventListener('input', e => {
            this.filters.search = e.target.value;
            this.render();
        });
        document.getElementById('refreshBtn')?.addEventListener('click', async () => {
            await this.loadData();
            if (this.selectedRoleId) await this.loadAssignments(this.selectedRoleId);
            this.render();
        });
        document.querySelectorAll('.menu-assign-chk').forEach(chk => {
            chk.addEventListener('change', e => {
                this.toggleAssignment(parseInt(e.target.dataset.itemId), e.target.checked);
            });
        });
    },

    esc: function (s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
};

document.addEventListener('DOMContentLoaded', () => roleNavigationConfigController.init());
