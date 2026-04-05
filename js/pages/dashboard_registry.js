/**
 * Dashboard Registry Controller
 * List, create, edit and delete dashboard registrations
 * Role: System Administrator (ID 2)
 */
const dashboardRegistryController = {
    dashboards: [],
    filters: { search: '', status: '' },
    editingId: null,

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
            const res = await window.API.system.getDashboards();
            this.dashboards = res?.data ?? res ?? [];
        } catch (e) {
            console.error('dashboard_registry: loadData error', e);
            showNotification('Failed to load dashboards', 'error');
        }
    },

    render: function () {
        const container = document.getElementById('mainTable');
        if (!container) return;

        const filtered = this.dashboards.filter(d => {
            const term = this.filters.search.toLowerCase();
            const mSearch = !term ||
                (d.name || d.key || '').toLowerCase().includes(term) ||
                (d.description || '').toLowerCase().includes(term);
            const status = d.status || (d.is_active ? 'active' : 'inactive');
            const mStatus = !this.filters.status || status === this.filters.status;
            return mSearch && mStatus;
        });

        let html = `<div class="mb-3 d-flex gap-2 flex-wrap">
            <input type="text" class="form-control form-control-sm" id="searchFilter"
                placeholder="Search dashboards..." value="${this.esc(this.filters.search)}" style="max-width:250px">
            <select class="form-select form-select-sm" id="statusFilter" style="max-width:150px">
                <option value="">All Status</option>
                <option value="active"${this.filters.status === 'active' ? ' selected' : ''}>Active</option>
                <option value="inactive"${this.filters.status === 'inactive' ? ' selected' : ''}>Inactive</option>
            </select>
            <button class="btn btn-sm btn-primary" id="addBtn">
                <i class="bi bi-plus-lg me-1"></i>Add Dashboard
            </button>
            <button class="btn btn-sm btn-outline-secondary" id="refreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>`;

        if (!filtered.length) {
            html += '<div class="alert alert-info m-3">No dashboards found.</div>';
        } else {
            html += `<div class="table-responsive"><table class="table table-sm table-hover mb-0">
                <thead class="table-dark">
                    <tr><th>Key</th><th>Name</th><th>Role</th><th>Component</th><th>Status</th><th>Actions</th></tr>
                </thead><tbody>`;
            filtered.forEach(d => {
                const status = d.status || (d.is_active ? 'active' : 'inactive');
                html += `<tr>
                    <td><code class="small">${this.esc(d.key || d.dashboard_key || '')}</code></td>
                    <td><strong>${this.esc(d.name || '')}</strong></td>
                    <td><span class="badge bg-secondary">${this.esc(d.role_name || d.role || '')}</span></td>
                    <td class="text-muted small">${this.esc(d.component || d.template || '')}</td>
                    <td><span class="badge bg-${status === 'active' ? 'success' : 'secondary'}">${this.esc(status)}</span></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-warning" onclick="dashboardRegistryController.showEdit(${d.id})" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="dashboardRegistryController.deleteItem(${d.id})" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
            });
            html += `</tbody></table></div>
            <div class="p-2 text-muted small">${filtered.length} of ${this.dashboards.length} dashboards</div>`;
        }

        html += this.renderModal();
        container.innerHTML = html;
        this.bindFilterEvents();
    },

    renderModal: function () {
        const d = this.editingId ? this.dashboards.find(x => x.id === this.editingId) : null;
        return `<div class="modal fade" id="dashboardModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-speedometer2 me-2"></i>${d ? 'Edit' : 'Add'} Dashboard</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="dashboardId" value="${d ? d.id : ''}">
                        <div class="mb-3">
                            <label class="form-label">Dashboard Key <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="dashboardKey" value="${this.esc(d?.key || d?.dashboard_key || '')}" placeholder="e.g. admin_dashboard" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="dashboardName" value="${this.esc(d?.name || '')}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Component / Template</label>
                            <input type="text" class="form-control" id="dashboardComponent" value="${this.esc(d?.component || d?.template || '')}" placeholder="e.g. admin">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="dashboardDesc" rows="2">${this.esc(d?.description || '')}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="dashboardStatus">
                                <option value="active"${(!d || d.status === 'active') ? ' selected' : ''}>Active</option>
                                <option value="inactive"${d?.status === 'inactive' ? ' selected' : ''}>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="dashboardRegistryController.saveItem()">
                            <i class="bi bi-save me-1"></i>Save
                        </button>
                    </div>
                </div>
            </div>
        </div>`;
    },

    showAdd: function () {
        this.editingId = null;
        this.render();
        new bootstrap.Modal(document.getElementById('dashboardModal')).show();
    },

    showEdit: function (id) {
        this.editingId = id;
        this.render();
        new bootstrap.Modal(document.getElementById('dashboardModal')).show();
    },

    saveItem: async function () {
        const id = document.getElementById('dashboardId')?.value;
        const data = {
            key: document.getElementById('dashboardKey')?.value?.trim(),
            name: document.getElementById('dashboardName')?.value?.trim(),
            component: document.getElementById('dashboardComponent')?.value?.trim(),
            description: document.getElementById('dashboardDesc')?.value?.trim(),
            status: document.getElementById('dashboardStatus')?.value
        };
        if (!data.key || !data.name) {
            showNotification('Key and Name are required', 'warning');
            return;
        }
        try {
            if (id) {
                await window.API.system.updateDashboard(id, data);
                showNotification('Dashboard updated', 'success');
            } else {
                await window.API.system.createDashboard(data);
                showNotification('Dashboard created', 'success');
            }
            bootstrap.Modal.getInstance(document.getElementById('dashboardModal'))?.hide();
            this.editingId = null;
            await this.loadData();
            this.render();
        } catch (e) {
            console.error('dashboard_registry: saveItem error', e);
            showNotification(e.message || 'Failed to save dashboard', 'error');
        }
    },

    deleteItem: async function (id) {
        if (!confirm('Delete this dashboard? This cannot be undone.')) return;
        try {
            await window.API.system.deleteDashboard(id);
            showNotification('Dashboard deleted', 'success');
            await this.loadData();
            this.render();
        } catch (e) {
            console.error('dashboard_registry: deleteItem error', e);
            showNotification(e.message || 'Failed to delete dashboard', 'error');
        }
    },

    bindEvents: function () {},

    bindFilterEvents: function () {
        document.getElementById('searchFilter')?.addEventListener('input', e => {
            this.filters.search = e.target.value;
            this.render();
        });
        document.getElementById('statusFilter')?.addEventListener('change', e => {
            this.filters.status = e.target.value;
            this.render();
        });
        document.getElementById('addBtn')?.addEventListener('click', () => this.showAdd());
        document.getElementById('refreshBtn')?.addEventListener('click', async () => {
            await this.loadData();
            this.render();
        });
    },

    esc: function (s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
};

document.addEventListener('DOMContentLoaded', () => dashboardRegistryController.init());
