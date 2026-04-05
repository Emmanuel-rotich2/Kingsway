/**
 * Sidebar Menus Controller
 * Manage sidebar menu items (CRUD)
 * Role: System Administrator (ID 2)
 */
const sidebarMenusController = {
    items: [],
    filters: { search: '', status: '' },
    editingId: null,

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
            const res = await window.API.system.getSidebarMenus();
            this.items = res?.data ?? res ?? [];
        } catch (e) {
            console.error('sidebar_menus: loadData error', e);
            showNotification('Failed to load sidebar menus', 'error');
        }
    },

    render: function () {
        const container = document.getElementById('mainTable');
        if (!container) return;

        const filtered = this.items.filter(item => {
            const term = this.filters.search.toLowerCase();
            const matchSearch = !term ||
                (item.label || item.name || '').toLowerCase().includes(term) ||
                (item.route || item.url || '').toLowerCase().includes(term);
            const matchStatus = !this.filters.status || (item.status || (item.is_active ? 'active' : 'inactive')) === this.filters.status;
            return matchSearch && matchStatus;
        });

        let html = `<div class="mb-3 d-flex gap-2 flex-wrap">
            <input type="text" class="form-control form-control-sm" id="searchFilter"
                placeholder="Search menus..." value="${this.esc(this.filters.search)}" style="max-width:250px">
            <select class="form-select form-select-sm" id="statusFilter" style="max-width:160px">
                <option value="">All Status</option>
                <option value="active"${this.filters.status === 'active' ? ' selected' : ''}>Active</option>
                <option value="inactive"${this.filters.status === 'inactive' ? ' selected' : ''}>Inactive</option>
            </select>
            <button class="btn btn-sm btn-primary" id="addBtn">
                <i class="bi bi-plus-lg me-1"></i>Add Menu Item
            </button>
            <button class="btn btn-sm btn-outline-secondary" id="refreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
        <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead class="table-dark">
                <tr><th>Label</th><th>Icon</th><th>Route/URL</th><th>Order</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>`;

        if (!filtered.length) {
            html += '<tr><td colspan="6" class="text-center text-muted py-3">No menu items found</td></tr>';
        } else {
            filtered.forEach(item => {
                const label = item.label || item.name || item.title || '--';
                const status = item.status || (item.is_active ? 'active' : 'inactive');
                const badgeClass = status === 'active' ? 'success' : 'secondary';
                html += `<tr>
                    <td><strong>${this.esc(label)}</strong></td>
                    <td><i class="${this.esc(item.icon || '')}"></i> <small class="text-muted">${this.esc(item.icon || '')}</small></td>
                    <td><code class="small">${this.esc(item.route || item.url || '--')}</code></td>
                    <td>${item.sort_order ?? item.order ?? '--'}</td>
                    <td><span class="badge bg-${badgeClass}">${this.esc(status)}</span></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-warning" onclick="sidebarMenusController.showEdit(${item.id})" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="sidebarMenusController.deleteItem(${item.id})" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
            });
        }

        html += `</tbody></table></div>
        <small class="text-muted">${filtered.length} of ${this.items.length} items</small>
        ${this.renderModal()}`;

        container.innerHTML = html;
        this.bindFilterEvents();
    },

    renderModal: function () {
        const item = this.editingId ? this.items.find(i => i.id === this.editingId) : null;
        return `<div class="modal fade" id="menuModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${item ? 'Edit' : 'Add'} Menu Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="menuItemId" value="${item ? item.id : ''}">
                        <div class="mb-3">
                            <label class="form-label">Label <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="menuLabel" value="${this.esc(item?.label || item?.name || '')}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Icon Class</label>
                            <input type="text" class="form-control" id="menuIcon" value="${this.esc(item?.icon || '')}" placeholder="e.g. bi bi-house">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Route / URL</label>
                            <input type="text" class="form-control" id="menuRoute" value="${this.esc(item?.route || item?.url || '')}" placeholder="e.g. dashboard">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sort Order</label>
                            <input type="number" class="form-control" id="menuOrder" value="${item?.sort_order ?? item?.order ?? 0}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="menuStatus">
                                <option value="active"${(!item || item.status === 'active') ? ' selected' : ''}>Active</option>
                                <option value="inactive"${item?.status === 'inactive' ? ' selected' : ''}>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="sidebarMenusController.saveItem()">
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
        const modal = new bootstrap.Modal(document.getElementById('menuModal'));
        modal.show();
    },

    showEdit: function (id) {
        this.editingId = id;
        this.render();
        const modal = new bootstrap.Modal(document.getElementById('menuModal'));
        modal.show();
    },

    saveItem: async function () {
        const id = document.getElementById('menuItemId')?.value;
        const data = {
            label: document.getElementById('menuLabel')?.value?.trim(),
            icon: document.getElementById('menuIcon')?.value?.trim(),
            route: document.getElementById('menuRoute')?.value?.trim(),
            sort_order: parseInt(document.getElementById('menuOrder')?.value) || 0,
            status: document.getElementById('menuStatus')?.value
        };
        if (!data.label) { showNotification('Label is required', 'warning'); return; }
        try {
            if (id) {
                await window.API.system.updateSidebarMenu(id, data);
                showNotification('Menu item updated', 'success');
            } else {
                await window.API.system.createSidebarMenu(data);
                showNotification('Menu item created', 'success');
            }
            bootstrap.Modal.getInstance(document.getElementById('menuModal'))?.hide();
            this.editingId = null;
            await this.loadData();
            this.render();
        } catch (e) {
            console.error('sidebar_menus: saveItem error', e);
            showNotification(e.message || 'Failed to save', 'error');
        }
    },

    deleteItem: async function (id) {
        if (!confirm('Delete this menu item? This action cannot be undone.')) return;
        try {
            await window.API.system.deleteSidebarMenu(id);
            showNotification('Menu item deleted', 'success');
            await this.loadData();
            this.render();
        } catch (e) {
            console.error('sidebar_menus: deleteItem error', e);
            showNotification(e.message || 'Failed to delete', 'error');
        }
    },

    bindEvents: function () {
        // handled after render via bindFilterEvents
    },

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

document.addEventListener('DOMContentLoaded', () => sidebarMenusController.init());
