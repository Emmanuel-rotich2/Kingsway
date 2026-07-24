/**
 * Staff Management Controller
 * Handles manage_staff.php
 * Uses existing api.js JWT authentication
 */
const StaffProductionUI = {
    state: {
        staff: [],
        filteredStaff: [],
        departments: [],
        currentFilters: {
            search: '',
            department: null,
            staff_type_id: null,
            status: null
        }
    },

    async init() {
        // Wait for AuthContext to be ready
        if (typeof AuthContext !== 'undefined') {
            await AuthContext.ready();
        }

        // Check authentication
        if (!AuthContext?.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }

        // Check permissions
        if (!AuthContext.canView('staff')) {
            showNotification('You do not have permission to view staff', 'error');
            return;
        }

        this.bindEvents();
        await this.loadInitialData();
    },

    async loadInitialData() {
        await Promise.all([
            this.loadStaff(),
            this.loadDepartments()
        ]);
    },

    async loadStaff() {
        try {
            const response = await window.API.staff.index({
                search: this.state.currentFilters.search,
                department_id: this.state.currentFilters.department,
                staff_type_id: this.state.currentFilters.staff_type_id,
                status: this.state.currentFilters.status
            });

            const normalized = AppState.normalizeResponse(response);
            
            if (normalized.success) {
                this.state.staff = this.extractStaffList(normalized.data);
                this.state.filteredStaff = [...this.state.staff];
                this.render();
            } else {
                showNotification(normalized.message || 'Failed to load staff', 'error');
            }
        } catch (error) {
            if (error.code === 'PERMISSION_DENIED') {
                showNotification('You do not have permission to view staff', 'error');
            } else {
                console.error('Error loading staff:', error);
                showNotification('Failed to load staff', 'error');
            }
        }
    },

    extractStaffList(data) {
        if (!data) return [];
        if (Array.isArray(data)) return data;
        if (Array.isArray(data.staff)) return data.staff;
        if (Array.isArray(data.data?.staff)) return data.data.staff;
        if (Array.isArray(data.data)) return data.data;
        return [];
    },

    async loadDepartments() {
        try {
            const response = await window.API.staff.getDepartments();
            const normalized = AppState.normalizeResponse(response);
            
            if (normalized.success) {
                this.state.departments = Array.isArray(normalized.data) ? normalized.data : [];
                this.populateDepartmentDropdown();
            }
        } catch (error) {
            console.error('Error loading departments:', error);
        }
    },

    populateDepartmentDropdown() {
        const filterSelect = document.getElementById('filterDepartment');
        const formSelect = document.getElementById('department');
        
        if (filterSelect) {
            filterSelect.innerHTML = '<option value="">All Departments</option>' +
                this.state.departments.map(dept => 
                    `<option value="${dept.id}">${dept.name}</option>`
                ).join('');
        }
        
        if (formSelect) {
            formSelect.innerHTML = '<option value="">Select Department</option>' +
                this.state.departments.map(dept => 
                    `<option value="${dept.id}">${dept.name}</option>`
                ).join('');
        }
    },

    bindEvents() {
        document.getElementById('searchStaff')?.addEventListener('input', (e) => {
            this.state.currentFilters.search = e.target.value;
            this.applyFilters();
        });

        document.getElementById('filterDepartment')?.addEventListener('change', (e) => {
            this.state.currentFilters.department = e.target.value || null;
            this.applyFilters();
        });

        document.getElementById('filterStaffType')?.addEventListener('change', (e) => {
            this.state.currentFilters.staff_type_id = e.target.value || null;
            this.applyFilters();
        });

        document.getElementById('filterStatus')?.addEventListener('change', (e) => {
            this.state.currentFilters.status = e.target.value || null;
            this.applyFilters();
        });

        document.getElementById('resetFilters')?.addEventListener('click', () => {
            this.resetFilters();
        });

        document.getElementById('addStaffBtn')?.addEventListener('click', () => {
            if (AuthContext.canCreate('staff')) {
                this.showAddModal();
            } else {
                showNotification('You do not have permission to add staff', 'error');
            }
        });

        document.getElementById('saveStaffBtn')?.addEventListener('click', () => {
            this.saveStaff();
        });

        document.getElementById('exportStaffBtn')?.addEventListener('click', () => {
            if (AuthContext.canExport('staff')) {
                this.exportStaff();
            } else {
                showNotification('You do not have permission to export staff', 'error');
            }
        });
    },

    applyFilters() {
        let filtered = [...this.state.staff];

        if (this.state.currentFilters.search) {
            const search = this.state.currentFilters.search.toLowerCase();
            filtered = filtered.filter(staff => {
                const name = `${staff.first_name || ''} ${staff.last_name || ''}`.toLowerCase();
                const staffNo = (staff.staff_no || '').toLowerCase();
                const email = (staff.email || '').toLowerCase();
                return name.includes(search) || staffNo.includes(search) || email.includes(search);
            });
        }

        if (this.state.currentFilters.department) {
            filtered = filtered.filter(staff => 
                staff.department_id == this.state.currentFilters.department
            );
        }

        if (this.state.currentFilters.staff_type_id) {
            filtered = filtered.filter(staff => 
                staff.staff_type_id == this.state.currentFilters.staff_type_id
            );
        }

        if (this.state.currentFilters.status) {
            filtered = filtered.filter(staff => 
                staff.status === this.state.currentFilters.status
            );
        }

        this.state.filteredStaff = filtered;
        this.render();
    },

    resetFilters() {
        this.state.currentFilters = {
            search: '',
            department: null,
            staff_type_id: null,
            status: null
        };

        document.getElementById('searchStaff').value = '';
        document.getElementById('filterDepartment').value = '';
        document.getElementById('filterStaffType').value = '';
        document.getElementById('filterStatus').value = '';

        this.applyFilters();
    },

    render() {
        this.renderStats();
        this.renderTable();
    },

    renderStats() {
        const total = this.state.staff.length;
        const active = this.state.staff.filter(s => s.status === 'active').length;
        const teaching = this.state.staff.filter(s => s.staff_type_id == 1).length;
        const nonTeaching = this.state.staff.filter(s => s.staff_type_id == 2).length;

        document.getElementById('totalStaff').textContent = total;
        document.getElementById('activeStaff').textContent = active;
        document.getElementById('teachingStaff').textContent = teaching;
        document.getElementById('nonTeachingStaff').textContent = nonTeaching;
    },

    renderTable() {
        const tbody = document.getElementById('staffTableBody');
        if (!tbody) return;

        if (this.state.filteredStaff.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No staff found</td></tr>';
            return;
        }

        tbody.innerHTML = this.state.filteredStaff.map((staff) => {
            return `
                <tr>
                    <td>${staff.staff_no || '-'}</td>
                    <td>
                        <strong>${this.escapeHtml(staff.first_name + ' ' + staff.last_name)}</strong>
                        <br><small class="text-muted">${staff.email || '-'}</small>
                    </td>
                    <td>${staff.department_name || '-'}</td>
                    <td>${this.renderStaffType(staff)}</td>
                    <td>${staff.position || '-'}</td>
                    <td>${this.renderStatusBadge(staff)}</td>
                    <td>${this.renderActionButtons(staff)}</td>
                </tr>
            `;
        }).join('');
    },

    renderStaffType(staff) {
        const typeMap = { 1: 'Teaching', 2: 'Non-Teaching', 3: 'Admin' };
        const typeName = typeMap[staff.staff_type_id] || 'Unknown';
        const colorMap = { 1: 'primary', 2: 'info', 3: 'warning' };
        const color = colorMap[staff.staff_type_id] || 'secondary';
        return `<span class="badge bg-${color}">${typeName}</span>`;
    },

    renderStatusBadge(staff) {
        const statusMap = {
            'active': 'success',
            'inactive': 'secondary',
            'on_leave': 'warning'
        };
        const color = statusMap[staff.status] || 'secondary';
        return `<span class="badge bg-${color}">${staff.status || 'Unknown'}</span>`;
    },

    renderActionButtons(staff) {
        const buttons = [];

        buttons.push(`<button class="btn btn-sm btn-outline-info me-1" onclick="StaffProductionUI.viewStaff(${staff.id})" title="View">
            <i class="bi bi-eye"></i>
        </button>`);

        if (AuthContext.canEdit('staff')) {
            buttons.push(`<button class="btn btn-sm btn-outline-warning me-1" onclick="StaffProductionUI.editStaff(${staff.id})" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>`);
        }

        if (AuthContext.canDelete('staff')) {
            buttons.push(`<button class="btn btn-sm btn-outline-danger" onclick="StaffProductionUI.deleteStaff(${staff.id})" title="Delete">
                <i class="bi bi-trash"></i>
            </button>`);
        }

        return `<div class="btn-group btn-group-sm">${buttons.join('')}</div>`;
    },

    showAddModal() {
        document.getElementById('staffModalTitle').textContent = 'Add Staff';
        document.getElementById('staffId').value = '';
        document.getElementById('staffForm').reset();
        
        const modal = new bootstrap.Modal(document.getElementById('staffModal'));
        modal.show();
    },

    async saveStaff() {
        const staffId = document.getElementById('staffId').value;
        const data = {
            first_name: document.getElementById('firstName').value,
            last_name: document.getElementById('lastName').value,
            email: document.getElementById('email').value,
            phone: document.getElementById('phone').value,
            department_id: document.getElementById('department').value,
            staff_type_id: document.getElementById('staff_type_id').value,
            position: document.getElementById('position').value,
            status: document.getElementById('status').value
        };

        try {
            if (staffId) {
                await window.API.staff.update(staffId, data);
                showNotification('Staff updated successfully', 'success');
            } else {
                await window.API.staff.create(data);
                showNotification('Staff created successfully', 'success');
            }

            const modal = bootstrap.Modal.getInstance(document.getElementById('staffModal'));
            modal.hide();
            
            await this.loadStaff();
        } catch (error) {
            console.error('Error saving staff:', error);
            showNotification('Failed to save staff', 'error');
        }
    },

    async viewStaff(staffId) {
        try {
            const response = await window.API.staff.get(staffId);
            const normalized = AppState.normalizeResponse(response);
            
            if (normalized.success) {
                // Show staff details - implement detail view
                console.log('Staff details:', normalized.data);
            }
        } catch (error) {
            console.error('Error loading staff details:', error);
            showNotification('Failed to load staff details', 'error');
        }
    },

    async editStaff(staffId) {
        try {
            const response = await window.API.staff.get(staffId);
            const normalized = AppState.normalizeResponse(response);
            
            if (normalized.success) {
                this.populateEditForm(normalized.data);
                document.getElementById('staffModalTitle').textContent = 'Edit Staff';
                
                const modal = new bootstrap.Modal(document.getElementById('staffModal'));
                modal.show();
            }
        } catch (error) {
            console.error('Error loading staff for edit:', error);
            showNotification('Failed to load staff details', 'error');
        }
    },

    populateEditForm(staff) {
        document.getElementById('staffId').value = staff.id;
        document.getElementById('firstName').value = staff.first_name || '';
        document.getElementById('lastName').value = staff.last_name || '';
        document.getElementById('email').value = staff.email || '';
        document.getElementById('phone').value = staff.phone || '';
        document.getElementById('department').value = staff.department_id || '';
        document.getElementById('staff_type_id').value = staff.staff_type_id || '';
        document.getElementById('position').value = staff.position || '';
        document.getElementById('status').value = staff.status || 'active';
    },

    async deleteStaff(staffId) {
        if (!confirm('Are you sure you want to delete this staff member?')) return;

        try {
            await window.API.staff.delete(staffId);
            showNotification('Staff deleted successfully', 'success');
            await this.loadStaff();
        } catch (error) {
            console.error('Error deleting staff:', error);
            showNotification('Failed to delete staff', 'error');
        }
    },

    exportStaff() {
        if (!this.state.filteredStaff.length) {
            showNotification('No data to export', 'warning');
            return;
        }

        const headers = ['Staff No', 'Name', 'Email', 'Department', 'Type', 'Position', 'Status'];
        const rows = this.state.filteredStaff.map(staff => [
            staff.staff_no || '',
            `${staff.first_name} ${staff.last_name}`,
            staff.email || '',
            staff.department_name || '',
            this.getStaffTypeName(staff.staff_type_id),
            staff.position || '',
            staff.status || ''
        ]);

        let csv = headers.join(',') + '\n' + 
            rows.map(row => row.map(cell => `"${cell}"`).join(',')).join('\n');

        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
        a.download = 'staff_export.csv';
        a.click();
    },

    getStaffTypeName(typeId) {
        const typeMap = { 1: 'Teaching', 2: 'Non-Teaching', 3: 'Admin' };
        return typeMap[typeId] || 'Unknown';
    },

    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    StaffProductionUI.init().catch(error => {
        console.error('Failed to initialize StaffProductionUI:', error);
    });
});