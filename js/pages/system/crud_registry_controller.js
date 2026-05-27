class CrudRegistryController {
    constructor(config) {
        this.config = Object.assign({
            title: 'Registry',
            apiEndpoint: '/system/registry',
            columns: ['#', 'Name', 'Status', 'Actions'],
            canCreate: true,
            canEdit: true,
            canDelete: true
        }, config);
        this.allData = [];
        this.visibleData = [];
        this.init();
    }

    async init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }

        this.setupEventListeners();
        await this.loadData();
    }

    setupEventListeners() {
        document.getElementById('searchInput')?.addEventListener('input', () => this.filterData());
        document.getElementById('statusFilter')?.addEventListener('change', () => this.filterData());
    }

    async loadData() {
        try {
            const response = await window.API.apiCall(this.config.apiEndpoint, 'GET');
            this.allData = this.unwrapList(response);
            this.renderStats(this.allData);
            this.renderTable(this.allData);
        } catch (error) {
            console.error('Load failed:', error);
            this.allData = [];
            this.visibleData = [];
            this.renderStats([]);
            this.renderError(error.message || 'Load failed');
        }
    }

    renderStats(data) {
        const items = Array.isArray(data) ? data : [];
        const setText = (id, value) => {
            const element = document.getElementById(id);
            if (element) element.textContent = value;
        };

        setText('statTotal', items.length);
        setText('statActive', items.filter(item => this.getStatus(item) === 'active').length);
        setText('statInactive', items.filter(item => this.getStatus(item) === 'inactive').length);
        setText('statRecent', items.filter(item => {
            const date = item.created_at || item.updated_at;
            if (!date) return false;
            const timestamp = new Date(date).getTime();
            return Number.isFinite(timestamp) && (Date.now() - timestamp) < 604800000;
        }).length);
    }

    renderTable(items) {
        const tbody = document.querySelector('#dataTable tbody');
        if (!tbody) return;

        this.visibleData = Array.isArray(items) ? items : [];
        const colspan = this.getColumnCount();

        if (!this.visibleData.length) {
            tbody.innerHTML = '<tr><td colspan="' + colspan + '" class="text-center text-muted py-4">No records found</td></tr>';
            return;
        }

        tbody.innerHTML = this.visibleData.map((item, index) => {
            const status = this.getStatus(item);
            const badge = status === 'active' ? 'success' : status === 'inactive' ? 'secondary' : 'info';
            const createdAt = item.created_at ? new Date(item.created_at).toLocaleDateString() : '--';

            return '<tr>'
                + '<td>' + (index + 1) + '</td>'
                + '<td><strong>' + this.esc(item.name || item.title || item.key || item.email || '--') + '</strong></td>'
                + '<td>' + this.esc(item.description || item.value || item.url || item.endpoint || '--') + '</td>'
                + '<td><span class="badge bg-' + badge + '">' + this.esc(status) + '</span></td>'
                + '<td>' + this.esc(createdAt) + '</td>'
                + '<td>' + this.renderActions(index) + '</td>'
                + '</tr>';
        }).join('');
    }

    filterData() {
        const search = (document.getElementById('searchInput')?.value || '').toLowerCase();
        const status = document.getElementById('statusFilter')?.value || '';

        const filtered = this.allData.filter(item => {
            if (search && !JSON.stringify(item).toLowerCase().includes(search)) return false;
            if (status && this.getStatus(item) !== status) return false;
            return true;
        });

        this.renderTable(filtered);
    }

    showAddModal() {
        if (!this.config.canCreate) {
            this.notify('Adding records is not supported on this page', 'warning');
            return;
        }

        const title = document.getElementById('formModalTitle');
        if (title) title.textContent = 'Add ' + this.config.title;

        document.getElementById('recordForm')?.reset();
        const id = document.getElementById('recordId');
        if (id) id.value = '';

        const modal = document.getElementById('formModal');
        if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    editRecord(index) {
        if (!this.config.canEdit) {
            this.notify('Editing records is not supported on this page', 'warning');
            return;
        }

        const item = this.visibleData[index];
        if (!item) return;

        const title = document.getElementById('formModalTitle');
        if (title) title.textContent = 'Edit ' + this.config.title;

        const id = document.getElementById('recordId');
        if (id) id.value = item.id || '';

        const name = document.getElementById('recordName');
        if (name) name.value = item.name || item.title || item.key || '';

        const description = document.getElementById('recordDescription');
        if (description) description.value = item.description || item.value || item.url || '';

        const status = document.getElementById('recordStatus');
        if (status) status.value = this.getStatus(item) === '--' ? 'active' : this.getStatus(item);

        const modal = document.getElementById('formModal');
        if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    async saveRecord() {
        const id = document.getElementById('recordId')?.value;
        if (id && !this.config.canEdit) {
            this.notify('Editing records is not supported on this page', 'warning');
            return;
        }
        if (!id && !this.config.canCreate) {
            this.notify('Adding records is not supported on this page', 'warning');
            return;
        }

        const data = {
            name: document.getElementById('recordName')?.value,
            description: document.getElementById('recordDescription')?.value,
            status: document.getElementById('recordStatus')?.value
        };

        if (!data.name) {
            this.notify('Name is required', 'warning');
            return;
        }

        try {
            await window.API.apiCall(id ? this.config.apiEndpoint + '/' + encodeURIComponent(id) : this.config.apiEndpoint, id ? 'PUT' : 'POST', data);
            this.notify('Saved', 'success');
            bootstrap.Modal.getInstance(document.getElementById('formModal'))?.hide();
            await this.loadData();
        } catch (error) {
            this.notify(error.message || 'Save failed', 'danger');
        }
    }

    async deleteRecord(index) {
        if (!this.config.canDelete) {
            this.notify('Deleting records is not supported on this page', 'warning');
            return;
        }

        const item = this.visibleData[index];
        if (!item?.id) {
            this.notify('Record ID is missing', 'warning');
            return;
        }
        if (!confirm('Delete this record?')) return;

        try {
            await window.API.apiCall(this.config.apiEndpoint + '/' + encodeURIComponent(item.id), 'DELETE');
            this.notify('Deleted', 'success');
            await this.loadData();
        } catch (error) {
            this.notify(error.message || 'Delete failed', 'danger');
        }
    }

    exportCSV() {
        if (!this.allData.length) return;

        const headers = this.config.columns;
        const rows = this.allData.map(item => Object.values(item).slice(0, headers.length));
        const csv = headers.join(',') + '\n' + rows.map(row => row.map(value => this.csv(value)).join(',')).join('\n');
        const link = document.createElement('a');

        link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
        link.download = this.config.title.toLowerCase().replace(/\s+/g, '_') + '.csv';
        link.click();
    }

    unwrapList(response) {
        const payload = response?.data?.data ?? response?.data ?? response;
        if (Array.isArray(payload)) return payload;
        if (Array.isArray(payload?.items)) return payload.items;
        if (Array.isArray(payload?.records)) return payload.records;
        if (Array.isArray(payload?.rows)) return payload.rows;
        if (payload && typeof payload === 'object') return Object.values(payload).filter(value => value && typeof value === 'object');
        return [];
    }

    renderError(message) {
        const tbody = document.querySelector('#dataTable tbody');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="' + this.getColumnCount() + '" class="text-center text-danger py-4">' + this.esc(message) + '</td></tr>';
        }
        this.notify(message, 'danger');
    }

    getStatus(item) {
        if (item?.status) return String(item.status).toLowerCase();
        if (item?.is_active === 1 || item?.is_active === true || item?.enabled === true) return 'active';
        if (item?.is_active === 0 || item?.is_active === false || item?.enabled === false) return 'inactive';
        return '--';
    }

    renderActions(index) {
        const actions = [];
        if (this.config.canEdit) {
            actions.push('<button class="btn btn-sm btn-outline-primary me-1" onclick="window._crudCtrl.editRecord(' + index + ')"><i class="fas fa-edit"></i></button>');
        }
        if (this.config.canDelete) {
            actions.push('<button class="btn btn-sm btn-outline-danger" onclick="window._crudCtrl.deleteRecord(' + index + ')"><i class="fas fa-trash"></i></button>');
        }
        return actions.length ? actions.join('') : '<span class="text-muted small">View only</span>';
    }

    getColumnCount() {
        return document.querySelectorAll('#dataTable thead th').length || this.config.columns.length || 1;
    }

    csv(value) {
        return '"' + String(value ?? '').replace(/"/g, '""') + '"';
    }

    esc(value) {
        return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    notify(message, type) {
        const modal = document.getElementById('notificationModal');
        if (!modal) return;

        const messageElement = modal.querySelector('.notification-message');
        const content = modal.querySelector('.modal-content');
        if (messageElement) messageElement.textContent = message;
        if (content) content.className = 'modal-content notification-' + (type || 'info');

        const bootstrapModal = bootstrap.Modal.getOrCreateInstance(modal);
        bootstrapModal.show();
        setTimeout(() => bootstrapModal.hide(), 3000);
    }
}
