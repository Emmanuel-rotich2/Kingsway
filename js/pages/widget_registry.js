/**
 * Widget Registry Controller
 * Manage dashboard widgets (CRUD)
 * Role: System Administrator (ID 2)
 */
const widgetRegistryController = {
    widgets: [],
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
            const res = await window.API.system.getWidgets();
            this.widgets = res?.data ?? res ?? [];
        } catch (e) {
            console.error('widget_registry: loadData error', e);
            showNotification('Failed to load widgets', 'error');
        }
    },

    render: function () {
        const container = document.getElementById('mainTable');
        if (!container) return;

        const filtered = this.widgets.filter(w => {
            const term = this.filters.search.toLowerCase();
            const mSearch = !term ||
                (w.name || w.key || '').toLowerCase().includes(term) ||
                (w.type || '').toLowerCase().includes(term) ||
                (w.description || '').toLowerCase().includes(term);
            const status = w.status || (w.is_active ? 'active' : 'inactive');
            const mStatus = !this.filters.status || status === this.filters.status;
            return mSearch && mStatus;
        });

        let html = `<div class="mb-3 d-flex gap-2 flex-wrap">
            <input type="text" class="form-control form-control-sm" id="searchFilter"
                placeholder="Search widgets..." value="${this.esc(this.filters.search)}" style="max-width:250px">
            <select class="form-select form-select-sm" id="statusFilter" style="max-width:150px">
                <option value="">All Status</option>
                <option value="active"${this.filters.status === 'active' ? ' selected' : ''}>Active</option>
                <option value="inactive"${this.filters.status === 'inactive' ? ' selected' : ''}>Inactive</option>
            </select>
            <button class="btn btn-sm btn-primary" id="addBtn">
                <i class="bi bi-plus-lg me-1"></i>Add Widget
            </button>
            <button class="btn btn-sm btn-outline-secondary" id="refreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>`;

        if (!filtered.length) {
            html += '<div class="alert alert-info m-3">No widgets found.</div>';
        } else {
            html += `<div class="table-responsive"><table class="table table-sm table-hover mb-0">
                <thead class="table-dark">
                    <tr><th>Key</th><th>Name</th><th>Type</th><th>Permission</th><th>Status</th><th>Actions</th></tr>
                </thead><tbody>`;
            filtered.forEach(w => {
                const status = w.status || (w.is_active ? 'active' : 'inactive');
                html += `<tr>
                    <td><code class="small">${this.esc(w.key || w.widget_key || '')}</code></td>
                    <td><strong>${this.esc(w.name || '')}</strong></td>
                    <td><span class="badge bg-info text-dark">${this.esc(w.type || 'chart')}</span></td>
                    <td class="text-muted small"><code>${this.esc(w.permission || w.required_permission || '')}</code></td>
                    <td><span class="badge bg-${status === 'active' ? 'success' : 'secondary'}">${this.esc(status)}</span></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-warning" onclick="widgetRegistryController.showEdit(${w.id})" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="widgetRegistryController.deleteItem(${w.id})" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
            });
            html += `</tbody></table></div>
            <div class="p-2 text-muted small">${filtered.length} of ${this.widgets.length} widgets</div>`;
        }

        html += this.renderModal();
        container.innerHTML = html;
        this.bindFilterEvents();
    },

    renderModal: function () {
        const w = this.editingId ? this.widgets.find(x => x.id === this.editingId) : null;
        return `<div class="modal fade" id="widgetModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-puzzle me-2"></i>${w ? 'Edit' : 'Add'} Widget</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="widgetId" value="${w ? w.id : ''}">
                        <div class="mb-3">
                            <label class="form-label">Widget Key <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="widgetKey" value="${this.esc(w?.key || w?.widget_key || '')}" placeholder="e.g. student_count" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="widgetName" value="${this.esc(w?.name || '')}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select class="form-select" id="widgetType">
                                <option value="chart"${(!w || w.type === 'chart') ? ' selected' : ''}>Chart</option>
                                <option value="stat"${w?.type === 'stat' ? ' selected' : ''}>Stat Card</option>
                                <option value="table"${w?.type === 'table' ? ' selected' : ''}>Table</option>
                                <option value="list"${w?.type === 'list' ? ' selected' : ''}>List</option>
                                <option value="custom"${w?.type === 'custom' ? ' selected' : ''}>Custom</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Required Permission</label>
                            <input type="text" class="form-control" id="widgetPermission" value="${this.esc(w?.permission || w?.required_permission || '')}" placeholder="e.g. finance.view">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="widgetDesc" rows="2">${this.esc(w?.description || '')}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="widgetStatus">
                                <option value="active"${(!w || w.status === 'active') ? ' selected' : ''}>Active</option>
                                <option value="inactive"${w?.status === 'inactive' ? ' selected' : ''}>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="widgetRegistryController.saveItem()">
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
        new bootstrap.Modal(document.getElementById('widgetModal')).show();
    },

    showEdit: function (id) {
        this.editingId = id;
        this.render();
        new bootstrap.Modal(document.getElementById('widgetModal')).show();
    },

    saveItem: async function () {
        const id = document.getElementById('widgetId')?.value;
        const data = {
            key: document.getElementById('widgetKey')?.value?.trim(),
            name: document.getElementById('widgetName')?.value?.trim(),
            type: document.getElementById('widgetType')?.value,
            permission: document.getElementById('widgetPermission')?.value?.trim(),
            description: document.getElementById('widgetDesc')?.value?.trim(),
            status: document.getElementById('widgetStatus')?.value
        };
        if (!data.key || !data.name) {
            showNotification('Key and Name are required', 'warning');
            return;
        }
        try {
            if (id) {
                await window.API.system.updateWidget(id, data);
                showNotification('Widget updated', 'success');
            } else {
                await window.API.system.createWidget(data);
                showNotification('Widget created', 'success');
            }
            bootstrap.Modal.getInstance(document.getElementById('widgetModal'))?.hide();
            this.editingId = null;
            await this.loadData();
            this.render();
        } catch (e) {
            console.error('widget_registry: saveItem error', e);
            showNotification(e.message || 'Failed to save widget', 'error');
        }
    },

    deleteItem: async function (id) {
        if (!confirm('Delete this widget? This cannot be undone.')) return;
        try {
            await window.API.system.deleteWidget(id);
            showNotification('Widget deleted', 'success');
            await this.loadData();
            this.render();
        } catch (e) {
            console.error('widget_registry: deleteItem error', e);
            showNotification(e.message || 'Failed to delete widget', 'error');
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

document.addEventListener('DOMContentLoaded', () => widgetRegistryController.init());
