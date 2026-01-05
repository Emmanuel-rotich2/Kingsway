/**
 * Route Registry Controller
 * Manages application routes for System Administrators
 * Integrates with API.system.* endpoints via api.js
 * 
 * @package App\JS\Pages
 * @since 2025-01-01
 */

const RouteRegistryController = {
    // State
    routes: [],
    filteredRoutes: [],
    currentPage: 1,
    perPage: 10,
    currentFilters: {
        search: '',
        domain: '',
        status: ''
    },

    /**
     * Initialize controller
     */
    init: async function() {
        try {
            // Check authentication
            if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) {
                window.location.href = '/Kingsway/index.php';
                return;
            }

            console.log('🚀 Initializing Route Registry Controller...');
            
            this.setupEventListeners();
            await this.loadRoutes();
            
            console.log('✅ Route Registry Controller initialized');
        } catch (error) {
            console.error('❌ Error initializing Route Registry:', error);
            this.showError('Failed to initialize route registry');
        }
    },

    /**
     * Setup event listeners
     */
    setupEventListeners: function() {
        // Search input
        const searchInput = document.getElementById('searchRoutes');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.currentFilters.search = e.target.value.toLowerCase();
                this.applyFilters();
            });
        }

        // Domain filter
        const domainFilter = document.getElementById('filterDomain');
        if (domainFilter) {
            domainFilter.addEventListener('change', (e) => {
                this.currentFilters.domain = e.target.value;
                this.applyFilters();
            });
        }

        // Status filter
        const statusFilter = document.getElementById('filterStatus');
        if (statusFilter) {
            statusFilter.addEventListener('change', (e) => {
                this.currentFilters.status = e.target.value;
                this.applyFilters();
            });
        }

        // Form submission
        const routeForm = document.getElementById('routeForm');
        if (routeForm) {
            routeForm.addEventListener('submit', (e) => this.saveRoute(e));
        }

        // Modal reset
        const modal = document.getElementById('createRouteModal');
        if (modal) {
            modal.addEventListener('show.bs.modal', (e) => {
                if (!e.relatedTarget || e.relatedTarget.getAttribute('data-bs-toggle') === 'modal') {
                    this.resetForm();
                }
            });
        }
    },

    /**
     * Load routes from API
     */
    loadRoutes: async function() {
        try {
            this.showLoading();
            
            const response = await API.system.getRoutes();
            
            if (response.success) {
                this.routes = response.data || [];
            } else if (Array.isArray(response)) {
                this.routes = response;
            } else {
                this.routes = response.data || response.routes || [];
            }
            
            this.applyFilters();
            
        } catch (error) {
            console.error('Error loading routes:', error);
            this.showError('Failed to load routes');
            this.routes = [];
            this.renderTable();
        }
    },

    /**
     * Apply filters to routes
     */
    applyFilters: function() {
        this.filteredRoutes = this.routes.filter(route => {
            // Search filter
            const matchesSearch = !this.currentFilters.search || 
                (route.name && route.name.toLowerCase().includes(this.currentFilters.search)) ||
                (route.url && route.url.toLowerCase().includes(this.currentFilters.search)) ||
                (route.description && route.description.toLowerCase().includes(this.currentFilters.search));
            
            // Domain filter
            const matchesDomain = !this.currentFilters.domain || 
                route.domain === this.currentFilters.domain;
            
            // Status filter
            const matchesStatus = this.currentFilters.status === '' || 
                route.is_active == this.currentFilters.status;

            return matchesSearch && matchesDomain && matchesStatus;
        });

        this.currentPage = 1;
        this.renderTable();
        this.updateStats();
    },

    /**
     * Render routes table
     */
    renderTable: function() {
        const tbody = document.getElementById('routesTableBody');
        if (!tbody) return;

        if (this.filteredRoutes.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <i class="fas fa-route fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-0">No routes found</p>
                    </td>
                </tr>`;
            return;
        }

        // Pagination
        const start = (this.currentPage - 1) * this.perPage;
        const end = start + this.perPage;
        const pageRoutes = this.filteredRoutes.slice(start, end);

        let html = '';
        pageRoutes.forEach(route => {
            const statusBadge = route.is_active == 1
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-secondary">Inactive</span>';
            
            const domainBadge = route.domain === 'SYSTEM'
                ? '<span class="badge bg-danger">SYSTEM</span>'
                : '<span class="badge bg-primary">SCHOOL</span>';

            html += `
                <tr>
                    <td><code>${route.id}</code></td>
                    <td><strong>${this.escapeHtml(route.name || '')}</strong></td>
                    <td><code class="text-info">${this.escapeHtml(route.url || 'N/A')}</code></td>
                    <td>${domainBadge}</td>
                    <td class="text-muted small">${this.escapeHtml(route.description || '-')}</td>
                    <td>${statusBadge}</td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="RouteRegistryController.editRoute(${route.id})" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-outline-${route.is_active == 1 ? 'warning' : 'success'}" 
                                onclick="RouteRegistryController.toggleStatus(${route.id}, ${route.is_active})" 
                                title="${route.is_active == 1 ? 'Disable' : 'Enable'}">
                                <i class="fas fa-${route.is_active == 1 ? 'ban' : 'check'}"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="RouteRegistryController.deleteRoute(${route.id})" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
        });

        tbody.innerHTML = html;
        this.renderPagination();
    },

    /**
     * Update statistics
     */
    updateStats: function() {
        const countEl = document.getElementById('routeCount');
        if (countEl) {
            countEl.textContent = this.filteredRoutes.length;
        }
    },

    /**
     * Render pagination
     */
    renderPagination: function() {
        const pagination = document.getElementById('routesPagination');
        if (!pagination) return;

        const totalPages = Math.ceil(this.filteredRoutes.length / this.perPage);

        if (totalPages <= 1) {
            pagination.innerHTML = '';
            return;
        }

        let html = '';

        // Previous button
        html += `<li class="page-item ${this.currentPage === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="RouteRegistryController.goToPage(${this.currentPage - 1}); return false;">&laquo;</a>
        </li>`;

        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= this.currentPage - 2 && i <= this.currentPage + 2)) {
                html += `<li class="page-item ${i === this.currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="RouteRegistryController.goToPage(${i}); return false;">${i}</a>
                </li>`;
            } else if (i === this.currentPage - 3 || i === this.currentPage + 3) {
                html += `<li class="page-item disabled"><a class="page-link">...</a></li>`;
            }
        }

        // Next button
        html += `<li class="page-item ${this.currentPage === totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="RouteRegistryController.goToPage(${this.currentPage + 1}); return false;">&raquo;</a>
        </li>`;

        pagination.innerHTML = html;
    },

    /**
     * Go to specific page
     */
    goToPage: function(page) {
        const totalPages = Math.ceil(this.filteredRoutes.length / this.perPage);
        if (page >= 1 && page <= totalPages) {
            this.currentPage = page;
            this.renderTable();
        }
    },

    /**
     * Refresh routes
     */
    refresh: async function() {
        await this.loadRoutes();
        this.showSuccess('Routes refreshed');
    },

    /**
     * Show loading state
     */
    showLoading: function() {
        const tbody = document.getElementById('routesTableBody');
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="text-muted mb-0 mt-2">Loading routes...</p>
                    </td>
                </tr>`;
        }
    },

    /**
     * Edit route
     */
    editRoute: function(id) {
        const route = this.routes.find(r => r.id === id);
        if (!route) {
            this.showError('Route not found');
            return;
        }

        document.getElementById('modalTitle').textContent = 'Edit Route';
        document.getElementById('routeId').value = route.id;
        document.getElementById('routeName').value = route.name || '';
        document.getElementById('routeUrl').value = route.url || '';
        document.getElementById('routeDomain').value = route.domain || 'SCHOOL';
        document.getElementById('routeDescription').value = route.description || '';
        document.getElementById('routeController').value = route.controller || '';
        document.getElementById('routeAction').value = route.action || '';
        document.getElementById('routeStatus').value = route.is_active || 1;

        const modal = new bootstrap.Modal(document.getElementById('createRouteModal'));
        modal.show();
    },

    /**
     * Save route (create or update)
     */
    saveRoute: async function(event) {
        event.preventDefault();

        const routeId = document.getElementById('routeId').value;
        const data = {
            name: document.getElementById('routeName').value.trim(),
            url: document.getElementById('routeUrl').value.trim(),
            domain: document.getElementById('routeDomain').value,
            description: document.getElementById('routeDescription').value.trim(),
            controller: document.getElementById('routeController').value.trim(),
            action: document.getElementById('routeAction').value.trim(),
            is_active: parseInt(document.getElementById('routeStatus').value)
        };

        // Validation
        if (!data.name) {
            this.showError('Route name is required');
            return;
        }

        const btn = document.getElementById('saveRouteBtn');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';

        try {
            let response;
            if (routeId) {
                response = await API.system.updateRoute(routeId, data);
            } else {
                response = await API.system.createRoute(data);
            }

            if (response.success) {
                bootstrap.Modal.getInstance(document.getElementById('createRouteModal')).hide();
                this.showSuccess(routeId ? 'Route updated successfully' : 'Route created successfully');
                await this.loadRoutes();
            } else {
                this.showError(response.message || 'Failed to save route');
            }
        } catch (error) {
            console.error('Error saving route:', error);
            this.showError('Failed to save route: ' + error.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    },

    /**
     * Toggle route status
     */
    toggleStatus: async function(id, currentStatus) {
        try {
            const newStatus = currentStatus == 1 ? 0 : 1;
            const response = await API.system.toggleRouteStatus(id, newStatus);

            if (response.success) {
                this.showSuccess('Route status updated');
                await this.loadRoutes();
            } else {
                this.showError(response.message || 'Failed to update status');
            }
        } catch (error) {
            console.error('Error toggling status:', error);
            this.showError('Failed to update status');
        }
    },

    /**
     * Delete route
     */
    deleteRoute: async function(id) {
        const route = this.routes.find(r => r.id === id);
        if (!route) return;

        if (!confirm(`Are you sure you want to delete the route "${route.name}"?\n\nThis action cannot be undone.`)) {
            return;
        }

        try {
            const response = await API.system.deleteRoute(id);

            if (response.success) {
                this.showSuccess('Route deleted successfully');
                await this.loadRoutes();
            } else {
                this.showError(response.message || 'Failed to delete route');
            }
        } catch (error) {
            console.error('Error deleting route:', error);
            this.showError('Failed to delete route');
        }
    },

    /**
     * Reset form
     */
    resetForm: function() {
        document.getElementById('modalTitle').textContent = 'Create Route';
        document.getElementById('routeForm').reset();
        document.getElementById('routeId').value = '';
        document.getElementById('routeDomain').value = 'SCHOOL';
        document.getElementById('routeStatus').value = '1';
    },

    /**
     * Show success notification
     */
    showSuccess: function(message) {
        if (typeof showNotification === 'function') {
            showNotification(message, 'success');
        } else {
            console.log('✅ ' + message);
            // Fallback to simple alert
            this.showToast(message, 'success');
        }
    },

    /**
     * Show error notification
     */
    showError: function(message) {
        if (typeof showNotification === 'function') {
            showNotification(message, 'error');
        } else {
            console.error('❌ ' + message);
            this.showToast(message, 'danger');
        }
    },

    /**
     * Simple toast notification
     */
    showToast: function(message, type = 'info') {
        // Create toast container if not exists
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
        }

        const toastId = 'toast-' + Date.now();
        const iconMap = {
            success: 'check-circle',
            danger: 'exclamation-triangle',
            warning: 'exclamation-circle',
            info: 'info-circle'
        };

        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-${iconMap[type] || 'info-circle'} me-2"></i>
                        ${this.escapeHtml(message)}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>`;

        container.insertAdjacentHTML('beforeend', toastHtml);
        const toast = new bootstrap.Toast(document.getElementById(toastId));
        toast.show();

        // Remove after hidden
        document.getElementById(toastId).addEventListener('hidden.bs.toast', function() {
            this.remove();
        });
    },

    /**
     * Escape HTML
     */
    escapeHtml: function(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    RouteRegistryController.init();
});
