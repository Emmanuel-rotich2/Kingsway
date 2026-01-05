/**
 * Role Definitions Controller
 * Manages system roles for System Administrators
 * Integrates with API.system.* endpoints via api.js
 * 
 * @package App\JS\Pages
 * @since 2025-01-01
 */

const RoleDefinitionsController = {
    // State
    roles: [],
    filteredRoles: [],
    permissions: [],
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

            console.log('🚀 Initializing Role Definitions Controller...');
            
            this.setupEventListeners();
            await this.loadRoles();
            await this.loadPermissions();
            
            console.log('✅ Role Definitions Controller initialized');
        } catch (error) {
            console.error('❌ Error initializing Role Definitions:', error);
            this.showError('Failed to initialize role definitions');
        }
    },

    /**
     * Setup event listeners
     */
    setupEventListeners: function() {
        // Search input
        const searchInput = document.getElementById('searchRoles');
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
        const roleForm = document.getElementById('roleForm');
        if (roleForm) {
            roleForm.addEventListener('submit', (e) => this.saveRole(e));
        }

        // Color picker sync
        const colorPicker = document.getElementById('roleColor');
        const colorText = document.getElementById('roleColorText');
        if (colorPicker && colorText) {
            colorPicker.addEventListener('input', () => {
                colorText.value = colorPicker.value;
            });
            colorText.addEventListener('input', () => {
                colorPicker.value = colorText.value;
            });
        }

        // Icon preview
        const iconInput = document.getElementById('roleIcon');
        if (iconInput) {
            iconInput.addEventListener('input', () => this.updateIconPreview());
        }

        // Modal reset
        const modal = document.getElementById('createRoleModal');
        if (modal) {
            modal.addEventListener('show.bs.modal', (e) => {
                if (!e.relatedTarget || e.relatedTarget.getAttribute('data-bs-toggle') === 'modal') {
                    this.resetForm();
                }
            });
        }
    },

    /**
     * Load roles from API
     */
    loadRoles: async function() {
        try {
            this.showLoading();
            
            const response = await API.system.getRoles();
            
            if (response.success) {
                this.roles = response.data || [];
            } else if (Array.isArray(response)) {
                this.roles = response;
            } else {
                this.roles = response.data || response.roles || [];
            }
            
            this.applyFilters();
            this.updateStats();
            
        } catch (error) {
            console.error('Error loading roles:', error);
            this.showError('Failed to load roles');
            this.roles = [];
            this.renderGrid();
        }
    },

    /**
     * Load permissions from API
     */
    loadPermissions: async function() {
        try {
            const response = await API.system.getPermissions();
            
            if (response.success) {
                this.permissions = response.data || [];
            } else if (Array.isArray(response)) {
                this.permissions = response;
            } else {
                this.permissions = response.data || [];
            }
        } catch (error) {
            console.error('Error loading permissions:', error);
            this.permissions = [];
        }
    },

    /**
     * Apply filters to roles
     */
    applyFilters: function() {
        this.filteredRoles = this.roles.filter(role => {
            // Search filter
            const matchesSearch = !this.currentFilters.search || 
                (role.name && role.name.toLowerCase().includes(this.currentFilters.search)) ||
                (role.display_name && role.display_name.toLowerCase().includes(this.currentFilters.search)) ||
                (role.description && role.description.toLowerCase().includes(this.currentFilters.search));
            
            // Domain filter
            const matchesDomain = !this.currentFilters.domain || 
                role.domain === this.currentFilters.domain;
            
            // Status filter
            const matchesStatus = this.currentFilters.status === '' || 
                role.is_active == this.currentFilters.status;

            return matchesSearch && matchesDomain && matchesStatus;
        });

        this.renderGrid();
        this.updateStats();
    },

    /**
     * Render roles as grid cards
     */
    renderGrid: function() {
        const container = document.getElementById('rolesGrid');
        if (!container) return;

        if (this.filteredRoles.length === 0) {
            container.innerHTML = `
                <div class="col-12 text-center py-5">
                    <i class="fas fa-user-tag fa-3x text-muted mb-3"></i>
                    <p class="text-muted mb-0">No roles found</p>
                </div>`;
            return;
        }

        let html = '';
        this.filteredRoles.forEach(role => {
            const statusBadge = role.is_active == 1
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-secondary">Inactive</span>';
            
            const domainBadge = role.domain === 'SYSTEM'
                ? '<span class="badge bg-danger">SYSTEM</span>'
                : '<span class="badge bg-primary">SCHOOL</span>';

            const color = role.color || '#0d6efd';
            const icon = role.icon || 'fas fa-user';

            html += `
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="card h-100 border-0 shadow-sm role-card" style="border-left: 4px solid ${color} !important;">
                        <div class="card-body">
                            <div class="d-flex align-items-start mb-3">
                                <div class="icon-circle me-3" style="background-color: ${color}20; color: ${color};">
                                    <i class="${icon}"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 fw-bold">${this.escapeHtml(role.display_name || role.name)}</h6>
                                    <small class="text-muted">${this.escapeHtml(role.name)}</small>
                                </div>
                            </div>
                            <p class="text-muted small mb-3">${this.escapeHtml(role.description || 'No description')}</p>
                            <div class="d-flex gap-2 mb-3">
                                ${domainBadge}
                                ${statusBadge}
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-top-0 pt-0">
                            <div class="btn-group btn-group-sm w-100">
                                <button class="btn btn-outline-primary" onclick="RoleDefinitionsController.editRole(${role.id})" title="Edit">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </button>
                                <button class="btn btn-outline-info" onclick="RoleDefinitionsController.viewPermissions(${role.id})" title="Permissions">
                                    <i class="fas fa-key me-1"></i>Perms
                                </button>
                                <button class="btn btn-outline-${role.is_active == 1 ? 'warning' : 'success'}" 
                                    onclick="RoleDefinitionsController.toggleStatus(${role.id}, ${role.is_active})" 
                                    title="${role.is_active == 1 ? 'Disable' : 'Enable'}">
                                    <i class="fas fa-${role.is_active == 1 ? 'ban' : 'check'}"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>`;
        });

        container.innerHTML = html;
    },

    /**
     * Update statistics
     */
    updateStats: function() {
        const totalEl = document.getElementById('totalRoles');
        const activeEl = document.getElementById('activeRoles');
        const systemEl = document.getElementById('systemRoles');
        const schoolEl = document.getElementById('schoolRoles');

        if (totalEl) totalEl.textContent = this.roles.length;
        if (activeEl) activeEl.textContent = this.roles.filter(r => r.is_active == 1).length;
        if (systemEl) systemEl.textContent = this.roles.filter(r => r.domain === 'SYSTEM').length;
        if (schoolEl) schoolEl.textContent = this.roles.filter(r => r.domain === 'SCHOOL').length;
    },

    /**
     * Refresh roles
     */
    refresh: async function() {
        await this.loadRoles();
        this.showSuccess('Roles refreshed');
    },

    /**
     * Show loading state
     */
    showLoading: function() {
        const container = document.getElementById('rolesGrid');
        if (container) {
            container.innerHTML = `
                <div class="col-12 text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2">Loading roles...</p>
                </div>`;
        }
    },

    /**
     * Edit role
     */
    editRole: function(id) {
        const role = this.roles.find(r => r.id === id);
        if (!role) {
            this.showError('Role not found');
            return;
        }

        document.getElementById('modalTitle').textContent = 'Edit Role';
        document.getElementById('roleId').value = role.id;
        document.getElementById('roleName').value = role.name || '';
        document.getElementById('roleDisplayName').value = role.display_name || '';
        document.getElementById('roleDomain').value = role.domain || 'SCHOOL';
        document.getElementById('roleDescription').value = role.description || '';
        document.getElementById('roleIcon').value = role.icon || 'fas fa-user';
        document.getElementById('roleColor').value = role.color || '#0d6efd';
        document.getElementById('roleColorText').value = role.color || '#0d6efd';
        document.getElementById('roleStatus').value = role.is_active || 1;

        this.updateIconPreview();

        const modal = new bootstrap.Modal(document.getElementById('createRoleModal'));
        modal.show();
    },

    /**
     * View role permissions
     */
    viewPermissions: function(id) {
        const role = this.roles.find(r => r.id === id);
        if (!role) {
            this.showError('Role not found');
            return;
        }

        // Navigate to role permissions page or open modal
        window.location.href = `/Kingsway/home.php?route=role_permission_matrix&role_id=${id}`;
    },

    /**
     * Save role (create or update)
     */
    saveRole: async function(event) {
        event.preventDefault();

        const roleId = document.getElementById('roleId').value;
        const data = {
            name: document.getElementById('roleName').value.trim(),
            display_name: document.getElementById('roleDisplayName').value.trim(),
            domain: document.getElementById('roleDomain').value,
            description: document.getElementById('roleDescription').value.trim(),
            icon: document.getElementById('roleIcon').value.trim(),
            color: document.getElementById('roleColor').value,
            is_active: parseInt(document.getElementById('roleStatus').value)
        };

        // Validation
        if (!data.name) {
            this.showError('Role name is required');
            return;
        }

        const btn = document.getElementById('saveRoleBtn');
        const originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';

        try {
            let response;
            if (roleId) {
                response = await API.system.updateRole(roleId, data);
            } else {
                response = await API.system.createRole(data);
            }

            if (response.success) {
                bootstrap.Modal.getInstance(document.getElementById('createRoleModal')).hide();
                this.showSuccess(roleId ? 'Role updated successfully' : 'Role created successfully');
                await this.loadRoles();
            } else {
                this.showError(response.message || 'Failed to save role');
            }
        } catch (error) {
            console.error('Error saving role:', error);
            this.showError('Failed to save role: ' + error.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    },

    /**
     * Toggle role status
     */
    toggleStatus: async function(id, currentStatus) {
        try {
            const newStatus = currentStatus == 1 ? 0 : 1;
            const response = await API.system.toggleRoleStatus(id, newStatus);

            if (response.success) {
                this.showSuccess('Role status updated');
                await this.loadRoles();
            } else {
                this.showError(response.message || 'Failed to update status');
            }
        } catch (error) {
            console.error('Error toggling status:', error);
            this.showError('Failed to update status');
        }
    },

    /**
     * Update icon preview
     */
    updateIconPreview: function() {
        const iconInput = document.getElementById('roleIcon');
        const preview = document.getElementById('iconPreview');
        if (iconInput && preview) {
            preview.className = iconInput.value || 'fas fa-user';
        }
    },

    /**
     * Reset form
     */
    resetForm: function() {
        document.getElementById('modalTitle').textContent = 'Create Role';
        document.getElementById('roleForm').reset();
        document.getElementById('roleId').value = '';
        document.getElementById('roleDomain').value = 'SCHOOL';
        document.getElementById('roleStatus').value = '1';
        document.getElementById('roleColor').value = '#0d6efd';
        document.getElementById('roleColorText').value = '#0d6efd';
        this.updateIconPreview();
    },

    /**
     * Show success notification
     */
    showSuccess: function(message) {
        if (typeof showNotification === 'function') {
            showNotification(message, 'success');
        } else {
            console.log('✅ ' + message);
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

// Add some CSS for icon-circle if not exists
const style = document.createElement('style');
style.textContent = `
    .icon-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }
    .role-card {
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .role-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
    }
`;
document.head.appendChild(style);

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    RoleDefinitionsController.init();
});
