/**
 * Permission Registry Controller
 * Lists all permissions, filterable by module/role
 * Role: System Administrator (ID 2)
 */
const permissionRegistryController = {
    permissions: [],
    filters: { module: '', search: '' },

    init: async function () {
        if (!AuthContext.isAuthenticated()) { window.location.href = '/'; return; }
        if (!AuthContext.hasPermission('system_view')) {
            const el = document.getElementById('mainTable');
            if (el) el.innerHTML = '<div class="alert alert-danger">Access denied</div>';
            return;
        }
        await this.loadData();
        this.render();
        this.bindEvents();
    },

    loadData: async function () {
        try {
            const res = await window.API.system.getPermissions();
            this.permissions = res?.data ?? res ?? [];
        } catch (e) {
            console.error('permission_registry: loadData error', e);
            showNotification('Failed to load permissions', 'error');
        }
    },

    render: function () {
        const container = document.getElementById('mainTable');
        if (!container) return;

        if (!this.permissions.length) {
            container.innerHTML = '<div class="alert alert-info">No permissions found</div>';
            return;
        }

        const modules = [...new Set(this.permissions.map(p => p.module).filter(Boolean))].sort();

        const filtered = this.permissions.filter(p => {
            const matchModule = !this.filters.module || p.module === this.filters.module;
            const term = this.filters.search.toLowerCase();
            const matchSearch = !term ||
                (p.code || '').toLowerCase().includes(term) ||
                (p.description || '').toLowerCase().includes(term) ||
                (p.entity || '').toLowerCase().includes(term);
            return matchModule && matchSearch;
        });

        let html = `<div class="mb-3 d-flex gap-2 flex-wrap">
            <input type="text" class="form-control form-control-sm" id="searchFilter"
                placeholder="Search permissions..." value="${this.esc(this.filters.search)}" style="max-width:280px">
            <select class="form-select form-select-sm" id="moduleFilter" style="max-width:200px">
                <option value="">All Modules</option>
                ${modules.map(m => `<option value="${this.esc(m)}"${this.filters.module === m ? ' selected' : ''}>${this.esc(m)}</option>`).join('')}
            </select>
            <button class="btn btn-sm btn-outline-secondary" id="refreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
        <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead class="table-dark">
                <tr><th>Code</th><th>Module</th><th>Entity</th><th>Action</th><th>Description</th></tr>
            </thead>
            <tbody>`;

        filtered.forEach(p => {
            html += `<tr>
                <td><code>${this.esc(p.code ?? '')}</code></td>
                <td><span class="badge bg-secondary">${this.esc(p.module ?? '')}</span></td>
                <td>${this.esc(p.entity ?? '')}</td>
                <td>${this.esc(p.action ?? '')}</td>
                <td class="text-muted small">${this.esc(p.description ?? '')}</td>
            </tr>`;
        });

        html += `</tbody></table></div>
        <small class="text-muted">${filtered.length} of ${this.permissions.length} permissions</small>`;

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
        document.getElementById('moduleFilter')?.addEventListener('change', e => {
            this.filters.module = e.target.value;
            this.render();
        });
        document.getElementById('refreshBtn')?.addEventListener('click', async () => {
            await this.loadData();
            this.render();
        });
    },

    esc: function (s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
};

document.addEventListener('DOMContentLoaded', () => permissionRegistryController.init());
