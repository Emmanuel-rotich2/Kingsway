/**
 * DataTable Component - Reusable table renderer with sorting, filtering, pagination
 * Works with any API endpoint, respects user permissions
 */

class DataTable {
    constructor(containerId, options = {}) {
        this.containerId = containerId;
        this.container = document.getElementById(containerId);
        
        // Configuration
        this.apiEndpoint = options.apiEndpoint;
        this.columns = options.columns || [];
        this.pageSize = options.pageSize || 10;
        this.sortBy = options.sortBy || 'id';
        this.sortOrder = options.sortOrder || 'asc';
        this.onRowAction = options.onRowAction || null;
        this.rowActions = options.rowActions || [];
        this.filters = options.filters || {};
        this.searchFields = options.searchFields || ['id', 'name'];
        this.permissions = options.permissions || {};
        this.formatters = options.formatters || {};
        this.bulkActions = options.bulkActions || [];
        this.dataField = options.dataField || null;
        // When true, skip front-end permission validation before calling API (useful for public dashboard widgets)
        this.checkPermission =
          options.checkPermission !== undefined
            ? options.checkPermission
            : false;
        
        // State
        this.data = [];
        this.filteredData = [];
        this.currentPage = 1;
        this.totalPages = 1;
        this.selectedRows = new Set();
        
        // Initialize
        this.init();
    }

    async init() {
        this.render();
        await this.loadData();
    }

    render() {
        if (!this.container) {
            console.error(`Container #${this.containerId} not found`);
            return;
        }

        this.container.innerHTML = `
            <div class="data-table-wrapper">
                <!-- Bulk Actions Bar -->
                ${this.bulkActions.length > 0 ? this.renderBulkActionsBar() : ''}
                
                <!-- Table -->
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="${this.containerId}-table">
                        <thead>
                            ${this.renderTableHeader()}
                        </thead>
                        <tbody id="${this.containerId}-body">
                            <tr><td colspan="100%" class="text-center text-muted py-4">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        <small class="text-muted">
                            Showing ${this.pageSize} of ${this.filteredData.length} records
                        </small>
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0" id="${this.containerId}-pagination"></ul>
                    </nav>
                </div>
            </div>
        `;

        this.attachEventListeners();
    }

    renderBulkActionsBar() {
        return `
            <div class="alert alert-info mb-3 d-none" id="${this.containerId}-bulk-bar">
                <div class="d-flex justify-content-between align-items-center">
                    <span><strong id="${this.containerId}-selected-count">0</strong> selected</span>
                    <div>
                        ${this.bulkActions.map(action => `
                            <button class="btn btn-sm btn-${action.variant || 'secondary'} bulk-action" 
                                    data-action="${action.id}">
                                ${action.label}
                            </button>
                        `).join('')}
                    </div>
                </div>
            </div>
        `;
    }

    renderTableHeader() {
        return `
            <tr>
                ${this.bulkActions.length > 0 ? '<th style="width: 40px"><input type="checkbox" id="' + this.containerId + '-select-all" class="form-check-input"></th>' : ''}
                ${this.columns.map(col => `
                    <th class="sortable-header ${col.sortable !== false ? 'cursor-pointer' : ''}" 
                        data-field="${col.field}"
                        title="${col.sortable !== false ? 'Click to sort' : ''}">
                        ${col.label}
                        ${col.sortable !== false ? '<i class="bi bi-arrow-down-up ms-1" style="font-size: 0.8rem;"></i>' : ''}
                    </th>
                `).join('')}
                ${this.rowActions.length > 0 ? '<th style="width: 120px; text-align: center;">Actions</th>' : ''}
            </tr>
        `;
    }

    async loadData() {
        try {
          const params = {
            page: this.currentPage,
            per_page: this.pageSize,
            sort_by: this.sortBy,
            sort_order: this.sortOrder,
            ...this.filters,
          };

          // Add a small console debug so we can see what the server returned for dashboard widgets
          // Normalize endpoint to ensure it starts with a leading slash (prevents concatenation bugs)
          let endpoint = this.apiEndpoint || "";
          if (!endpoint.startsWith("/")) {
            endpoint = "/" + endpoint;
            console.warn(
              `[DataTable:${this.containerId}] Normalized apiEndpoint to '${endpoint}' (added leading slash)`
            );
          }

          console.debug(
            `[DataTable:${this.containerId}] Loading from ${endpoint} params:`,
            params,
            "checkPermission:",
            this.checkPermission
          );

          const data = await window.API.apiCall(endpoint, "GET", null, params, {
            checkPermission: this.checkPermission,
          });

          // Log the raw payload for debugging; this is helpful when endpoints wrap data differently
          console.debug(`[DataTable:${this.containerId}] Raw response:`, data);

          // Handle different response formats
          if (Array.isArray(data)) {
            this.data = data;
          } else if (data.data && Array.isArray(data.data)) {
            this.data = data.data;
          } else if (
            data.data &&
            this.dataField &&
            Array.isArray(data.data[this.dataField])
          ) {
            // Support responses like { data: { pending_approvals: [...] } }
            this.data = data.data[this.dataField];
          } else if (this.dataField && Array.isArray(data[this.dataField])) {
            // Support top-level fields like { recent: [...], pending: [...] }
            this.data = data[this.dataField];
          } else if (data.items && Array.isArray(data.items)) {
            this.data = data.items;
          } else if (data.recent && Array.isArray(data.recent)) {
            this.data = data.recent; // legacy shape from admissions/pending
          } else if (data.pending && Array.isArray(data.pending)) {
            this.data = data.pending; // legacy shape from system/pending-approvals
          } else {
            this.data = [];
          }

          this.filteredData = [...this.data];
          this.totalPages = Math.ceil(this.filteredData.length / this.pageSize);
          this.renderTableBody();
          this.renderPagination();
        } catch (error) {
            console.error('Failed to load data:', error);
            this.renderError('Failed to load data. Please try again.');
        }
    }

    renderTableBody() {
        const tbody = document.getElementById(`${this.containerId}-body`);
        const start = (this.currentPage - 1) * this.pageSize;
        const end = start + this.pageSize;
        const pageData = this.filteredData.slice(start, end);

        if (pageData.length === 0) {
            tbody.innerHTML = '<tr><td colspan="100%" class="text-center text-muted py-4">No records found</td></tr>';
            return;
        }

        tbody.innerHTML = pageData.map(row => this.renderTableRow(row)).join('');
        this.attachRowEventListeners();
    }

    renderTableRow(row) {
        return `
            <tr data-row-id="${row.id || row.ID || ''}">
                ${this.bulkActions.length > 0 ? `
                    <td style="width: 40px">
                        <input type="checkbox" class="form-check-input row-checkbox" value="${row.id || row.ID || ''}">
                    </td>
                ` : ''}
                ${this.columns.map(col => `
                    <td>${this.formatCellValue(row, col)}</td>
                `).join('')}
                ${this.rowActions.length > 0 ? `<td>${this.renderRowActions(row)}</td>` : ''}
            </tr>
        `;
    }

    formatCellValue(row, col) {
        let value = this.getNestedValue(row, col.field);

        // Apply custom formatter if exists
        if (this.formatters[col.field]) {
            return this.formatters[col.field](value, row);
        }

        // Default formatters
        if (col.type === 'date') {
            return value ? new Date(value).toLocaleDateString() : '-';
        }
        if (col.type === 'datetime') {
            return value ? new Date(value).toLocaleString() : '-';
        }
        if (col.type === 'currency') {
            return value ? `KES ${parseFloat(value).toFixed(2)}` : 'KES 0.00';
        }
        if (col.type === 'boolean') {
            return value ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>';
        }
        if (col.type === 'badge' && col.badgeMap) {
            const badgeClass = col.badgeMap[value] || 'secondary';
            return `<span class="badge bg-${badgeClass}">${value}</span>`;
        }
        if (col.type === 'percentage') {
            return value ? `${parseFloat(value).toFixed(1)}%` : '0%';
        }

        return value ? value.toString().substring(0, col.maxLength || 50) : '-';
    }

    getNestedValue(obj, path) {
        return path.split('.').reduce((current, prop) => current?.[prop], obj);
    }

    renderRowActions(row) {
        return `
            <div class="btn-group btn-group-sm">
                ${this.rowActions.map(action => {
                    // Check permission
                    if (action.permission && !AuthContext.hasPermission(action.permission)) {
                        return '';
                    }
                    
                    // Check visibility condition
                    if (action.visible && !action.visible(row)) {
                        return '';
                    }

                    const variant = action.variant || 'info';
                    const title = action.label || action.id;
                    
                    return `
                        <button class="btn btn-${variant} row-action" 
                                data-action="${action.id}" 
                                data-row-id="${row.id || row.ID || ''}"
                                title="${title}"
                                data-bs-toggle="tooltip">
                            <i class="bi ${action.icon || 'bi-pencil'}"></i>
                        </button>
                    `;
                }).join('')}
            </div>
        `;
    }

    renderPagination() {
        const pagination = document.getElementById(`${this.containerId}-pagination`);
        if (!pagination) return;

        let html = '';

        // Previous button
        html += `
            <li class="page-item ${this.currentPage === 1 ? 'disabled' : ''}">
                <button class="page-link pagination-btn" data-page="${Math.max(1, this.currentPage - 1)}">
                    Previous
                </button>
            </li>
        `;

        // Page numbers
        const startPage = Math.max(1, this.currentPage - 2);
        const endPage = Math.min(this.totalPages, this.currentPage + 2);

        if (startPage > 1) {
            html += `<li class="page-item"><button class="page-link pagination-btn" data-page="1">1</button></li>`;
            if (startPage > 2) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `
                <li class="page-item ${i === this.currentPage ? 'active' : ''}">
                    <button class="page-link pagination-btn" data-page="${i}">${i}</button>
                </li>
            `;
        }

        if (endPage < this.totalPages) {
            if (endPage < this.totalPages - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            html += `<li class="page-item"><button class="page-link pagination-btn" data-page="${this.totalPages}">${this.totalPages}</button></li>`;
        }

        // Next button
        html += `
            <li class="page-item ${this.currentPage === this.totalPages ? 'disabled' : ''}">
                <button class="page-link pagination-btn" data-page="${Math.min(this.totalPages, this.currentPage + 1)}">
                    Next
                </button>
            </li>
        `;

        pagination.innerHTML = html;
    }

    attachEventListeners() {
        // Sort headers
        document.querySelectorAll(`#${this.containerId} .sortable-header`).forEach(header => {
            if (header.classList.contains('cursor-pointer')) {
                header.addEventListener('click', (e) => this.handleSort(e));
            }
        });

        // Pagination
        document.querySelectorAll(`#${this.containerId}-pagination .pagination-btn`).forEach(btn => {
            btn.addEventListener('click', (e) => this.handlePagination(e));
        });

        // Bulk actions
        if (this.bulkActions.length > 0) {
            const selectAllCheckbox = document.getElementById(`${this.containerId}-select-all`);
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', (e) => this.toggleSelectAll(e));
            }

            document.querySelectorAll(`#${this.containerId} .bulk-action`).forEach(btn => {
                btn.addEventListener('click', (e) => this.handleBulkAction(e));
            });
        }
    }

    attachRowEventListeners() {
        // Row checkboxes
        document.querySelectorAll(`#${this.containerId} .row-checkbox`).forEach(checkbox => {
            checkbox.addEventListener('change', (e) => this.toggleRowSelection(e));
        });

        // Row actions
        document.querySelectorAll(`#${this.containerId} .row-action`).forEach(btn => {
            btn.addEventListener('click', (e) => this.handleRowAction(e));
        });
    }

    handleSort(e) {
        const field = e.currentTarget.dataset.field;
        
        if (this.sortBy === field) {
            this.sortOrder = this.sortOrder === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortBy = field;
            this.sortOrder = 'asc';
        }

        this.currentPage = 1;
        this.loadData();
    }

    handlePagination(e) {
        e.preventDefault();
        const page = parseInt(e.currentTarget.dataset.page);
        this.currentPage = page;
        this.renderTableBody();
        this.renderPagination();
    }

    toggleSelectAll(e) {
        const checkboxes = document.querySelectorAll(`#${this.containerId} .row-checkbox`);
        checkboxes.forEach(checkbox => {
            checkbox.checked = e.target.checked;
            if (e.target.checked) {
                this.selectedRows.add(checkbox.value);
            } else {
                this.selectedRows.delete(checkbox.value);
            }
        });
        this.updateBulkActionsBar();
    }

    toggleRowSelection(e) {
        if (e.target.checked) {
            this.selectedRows.add(e.target.value);
        } else {
            this.selectedRows.delete(e.target.value);
        }
        this.updateBulkActionsBar();
    }

    updateBulkActionsBar() {
        const bulkBar = document.getElementById(`${this.containerId}-bulk-bar`);
        const selectedCount = document.getElementById(`${this.containerId}-selected-count`);
        
        if (!bulkBar) return;

        if (this.selectedRows.size > 0) {
            bulkBar.classList.remove('d-none');
            selectedCount.textContent = this.selectedRows.size;
        } else {
            bulkBar.classList.add('d-none');
        }
    }

    handleBulkAction(e) {
        const actionId = e.currentTarget.dataset.action;
        const rowIds = Array.from(this.selectedRows);
        
        if (this.onRowAction) {
            this.onRowAction(actionId, rowIds);
        }
    }

    async handleRowAction(e) {
        const actionId = e.currentTarget.dataset.action;
        const rowId = e.currentTarget.dataset.rowId;
        const row = this.data.find(r => (r.id || r.ID) == rowId);

        if (this.onRowAction) {
            this.onRowAction(actionId, [rowId], row);
        }
    }

    renderError(message) {
        const tbody = document.getElementById(`${this.containerId}-body`);
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="100%" class="alert alert-danger mb-0">${message}</td></tr>`;
        }
    }

    // Public methods
    async refresh() {
        this.currentPage = 1;
        await this.loadData();
    }

    async applyFilters(filters) {
        this.filters = { ...this.filters, ...filters };
        this.currentPage = 1;
        await this.loadData();
    }

    search(query) {
        if (!query) {
            this.filteredData = [...this.data];
        } else {
            query = query.toLowerCase();
            this.filteredData = this.data.filter(row => {
                return this.searchFields.some(field => {
                    const value = this.getNestedValue(row, field);
                    return value && value.toString().toLowerCase().includes(query);
                });
            });
        }
        
        this.currentPage = 1;
        this.totalPages = Math.ceil(this.filteredData.length / this.pageSize);
        this.renderTableBody();
        this.renderPagination();
    }

    getSelectedRows() {
        return this.data.filter(row => this.selectedRows.has((row.id || row.ID).toString()));
    }

    clearSelection() {
        this.selectedRows.clear();
        document.querySelectorAll(`#${this.containerId} .row-checkbox`).forEach(cb => cb.checked = false);
        document.getElementById(`${this.containerId}-select-all`).checked = false;
        this.updateBulkActionsBar();
    }
}

// Export for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DataTable;
}
