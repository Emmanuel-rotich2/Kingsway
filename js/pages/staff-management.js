/**
 * Staff Management Controller
 * Handles staff CRUD, assignments, attendance, leaves, payroll, performance
 * Integrates with /api/staff endpoints
 */

const staffManagementController = {
    staff: [],
    departments: [],
    filteredStaff: [],
    currentFilters: {},

    /**
     * Initialize controller
     */
    init: async function() {
        try {
            console.log('Loading staff data...');
            await Promise.all([
                this.loadStaff(),
                this.loadDepartments()
            ]);
            this.checkUserPermissions();
            console.log('Staff management loaded successfully');
        } catch (error) {
            console.error('Error initializing staff controller:', error);
            showNotification('Failed to load staff management', 'error');
        }
    },

    // ============================================================================
    // STAFF CRUD
    // ============================================================================

    /**
     * Load all staff
     */
    loadStaff: async function() {
        try {
            const response = await API.staff.index();
            this.staff = response.data || response || [];
            this.filteredStaff = [...this.staff];
            this.renderStaffTable();
        } catch (error) {
            console.error('Error loading staff:', error);
            const container = document.getElementById('staffContainer');
            if (container) {
                container.innerHTML = '<div class="alert alert-danger">Failed to load staff</div>';
            }
        }
    },

    /**
     * Render staff table
     */
    renderStaffTable: function() {
        const container = document.getElementById('staffContainer');
        if (!container) return;

        if (this.filteredStaff.length === 0) {
            container.innerHTML = '<div class="alert alert-info">No staff members found. Click "Add Staff" to register a new member.</div>';
            return;
        }

        let html = `
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead class="table-success">
                        <tr>
                            <th>Staff ID</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Department</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        this.filteredStaff.forEach(member => {
            const typeBadge = this.getTypeBadge(member.staff_type);
            const statusBadge = this.getStatusBadge(member.status);
            
            html += `
                <tr>
                    <td><strong>${member.staff_id || member.id}</strong></td>
                    <td>${member.first_name} ${member.last_name}</td>
                    <td>${typeBadge}</td>
                    <td>${member.department || 'N/A'}</td>
                    <td>${member.email || 'N/A'}</td>
                    <td>${member.phone || 'N/A'}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-info" onclick="staffManagementController.viewStaff(${member.staff_id || member.id})" title="View Profile">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-warning" onclick="staffManagementController.editStaff(${member.staff_id || member.id})" title="Edit" data-permission="staff_update">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-success" onclick="staffManagementController.viewAssignments(${member.staff_id || member.id})" title="Assignments">
                                <i class="bi bi-clipboard-check"></i>
                            </button>
                            <button class="btn btn-primary" onclick="staffManagementController.viewPayroll(${member.staff_id || member.id})" title="Payroll" data-permission="staff_payroll">
                                <i class="bi bi-cash"></i>
                            </button>
                            <button class="btn btn-danger" onclick="staffManagementController.deleteStaff(${member.staff_id || member.id})" title="Delete" data-permission="staff_delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table></div>';
        container.innerHTML = html;
        this.checkUserPermissions();
    },

    /**
     * Get type badge
     */
    getTypeBadge: function(type) {
        const badges = {
            'teaching': '<span class="badge bg-primary">Teaching</span>',
            'non-teaching': '<span class="badge bg-info">Non-Teaching</span>',
            'admin': '<span class="badge bg-warning">Admin</span>',
            'support': '<span class="badge bg-secondary">Support</span>'
        };
        return badges[type] || '<span class="badge bg-secondary">Other</span>';
    },

    /**
     * Get status badge
     */
    getStatusBadge: function(status) {
        const badges = {
            'active': '<span class="badge bg-success">Active</span>',
            'inactive': '<span class="badge bg-secondary">Inactive</span>',
            'on_leave': '<span class="badge bg-warning">On Leave</span>',
            'suspended': '<span class="badge bg-danger">Suspended</span>',
            'retired': '<span class="badge bg-dark">Retired</span>'
        };
        return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
    },

    /**
     * Show add staff modal
     */
    showAddStaffModal: function() {
        console.log('Add Staff feature coming soon');
    },

    /**
     * View staff profile
     */
    viewStaff: async function(staffId) {
        try {
            const staff = await API.staff.get(staffId);
            const profile = await API.staff.getProfile(staffId);
            
            alert(`Staff Profile:\n\nID: ${staff.staff_id}\nName: ${staff.first_name} ${staff.last_name}\nType: ${staff.staff_type}\nDepartment: ${staff.department}\nEmail: ${staff.email}\nStatus: ${staff.status}`);
        } catch (error) {
            console.error('Error loading staff:', error);
            showNotification('Failed to load staff profile', 'error');
        }
    },

    /**
     * Edit staff
     */
    editStaff: function(staffId) {
        console.log('Edit staff feature coming soon');
    },

    /**
     * Delete staff
     */
    deleteStaff: async function(staffId) {
        if (!confirm('Are you sure you want to delete this staff member? This action cannot be undone.')) {
            return;
        }

        try {
            await API.staff.delete(staffId);
            showNotification('Staff member deleted successfully', 'success');
            await this.loadStaff();
        } catch (error) {
            console.error('Error deleting staff:', error);
            showNotification('Failed to delete staff member', 'error');
        }
    },

    // ============================================================================
    // ASSIGNMENTS
    // ============================================================================

    /**
     * View staff assignments
     */
    viewAssignments: async function(staffId) {
        try {
            const assignments = await API.staff.getAssignments(staffId);
            const current = await API.staff.getCurrentAssignments(staffId);
            const workload = await API.staff.getWorkload(staffId);
            
            let message = 'Staff Assignments:\n\n';
            message += `Total Assignments: ${assignments.length || 0}\n`;
            message += `Current Workload: ${workload.total_hours || 0} hours/week\n\n`;
            
            if (current && current.length > 0) {
                message += 'Current Assignments:\n';
                current.forEach(a => {
                    message += `- ${a.subject_name || a.class_name}: ${a.type}\n`;
                });
            }
            
            alert(message);
        } catch (error) {
            console.error('Error loading assignments:', error);
            showNotification('Failed to load assignments', 'error');
        }
    },

    /**
     * Assign class to staff
     */
    assignClass: async function(staffId) {
        const classId = prompt('Enter Class ID:');
        if (!classId) return;

        try {
            await API.staff.assignClass({
                staff_id: staffId,
                class_id: classId
            });
            showNotification('Class assigned successfully', 'success');
        } catch (error) {
            console.error('Error assigning class:', error);
            showNotification('Failed to assign class', 'error');
        }
    },

    /**
     * Assign subject to staff
     */
    assignSubject: async function(staffId) {
        const subjectId = prompt('Enter Subject ID:');
        if (!subjectId) return;

        try {
            await API.staff.assignSubject({
                staff_id: staffId,
                subject_id: subjectId
            });
            showNotification('Subject assigned successfully', 'success');
        } catch (error) {
            console.error('Error assigning subject:', error);
            showNotification('Failed to assign subject', 'error');
        }
    },

    // ============================================================================
    // ATTENDANCE
    // ============================================================================

    /**
     * Mark staff attendance
     */
    markAttendance: async function() {
        console.log('Use Mark Attendance page for staff attendance');
    },

    /**
     * View staff attendance
     */
    viewAttendance: async function(staffId) {
        try {
            const attendance = await API.staff.getAttendance(staffId);
            
            let message = 'Staff Attendance Summary:\n\n';
            message += `Present Days: ${attendance.present_days || 0}\n`;
            message += `Absent Days: ${attendance.absent_days || 0}\n`;
            message += `Leave Days: ${attendance.leave_days || 0}\n`;
            message += `Attendance Rate: ${attendance.attendance_rate || 0}%\n`;
            
            alert(message);
        } catch (error) {
            console.error('Error loading attendance:', error);
            showNotification('Failed to load attendance', 'error');
        }
    },

    // ============================================================================
    // LEAVES
    // ============================================================================

    /**
     * Apply for leave
     */
    applyLeave: async function(staffId) {
        try {
            const data = {
                staff_id: staffId,
                leave_type: prompt('Leave Type (Annual/Sick/Maternity/Other):'),
                start_date: prompt('Start Date (YYYY-MM-DD):'),
                end_date: prompt('End Date (YYYY-MM-DD):'),
                reason: prompt('Reason:')
            };

            await API.staff.applyLeave(data);
            showNotification('Leave application submitted successfully', 'success');
        } catch (error) {
            console.error('Error applying for leave:', error);
            showNotification('Failed to apply for leave', 'error');
        }
    },

    /**
     * View leave history
     */
    viewLeaves: async function(staffId) {
        try {
            const leaves = await API.staff.listLeaves({ staff_id: staffId });
            
            let message = 'Leave History:\n\n';
            if (leaves.data && leaves.data.length > 0) {
                leaves.data.forEach(leave => {
                    message += `${leave.leave_type}: ${leave.start_date} to ${leave.end_date} - ${leave.status}\n`;
                });
            } else {
                message += 'No leave records found.';
            }
            
            alert(message);
        } catch (error) {
            console.error('Error loading leaves:', error);
            showNotification('Failed to load leave history', 'error');
        }
    },

    /**
     * Approve/Reject leave
     */
    updateLeaveStatus: async function(leaveId, status) {
        try {
            await API.staff.updateLeaveStatus(leaveId, status);
            showNotification(`Leave ${status} successfully`, 'success');
        } catch (error) {
            console.error('Error updating leave status:', error);
            showNotification('Failed to update leave status', 'error');
        }
    },

    // ============================================================================
    // PAYROLL
    // ============================================================================

    /**
     * View staff payroll
     */
    viewPayroll: async function(staffId) {
        try {
            const payslip = await API.staff.getPayslip(staffId);
            const allowances = await API.staff.getAllowances(staffId);
            const deductions = await API.staff.getDeductions(staffId);
            
            let message = 'Payroll Summary:\n\n';
            message += `Basic Salary: ${payslip.basic_salary || 0}\n`;
            message += `Total Allowances: ${allowances.total || 0}\n`;
            message += `Total Deductions: ${deductions.total || 0}\n`;
            message += `Net Salary: ${payslip.net_salary || 0}\n`;
            
            alert(message);
        } catch (error) {
            console.error('Error loading payroll:', error);
            showNotification('Failed to load payroll details', 'error');
        }
    },

    /**
     * Download payslip
     */
    downloadPayslip: async function(staffId) {
        try {
            const month = prompt('Month (1-12):');
            const year = prompt('Year:');
            
            await API.staff.downloadPayslip(staffId, { month, year });
            showNotification('Payslip downloaded successfully', 'success');
        } catch (error) {
            console.error('Error downloading payslip:', error);
            showNotification('Failed to download payslip', 'error');
        }
    },

    /**
     * Download P9 form
     */
    downloadP9: async function(staffId) {
        try {
            const year = prompt('Tax Year:');
            await API.staff.downloadP9(staffId, year);
            showNotification('P9 form downloaded successfully', 'success');
        } catch (error) {
            console.error('Error downloading P9:', error);
            showNotification('Failed to download P9 form', 'error');
        }
    },

    /**
     * Request salary advance
     */
    requestAdvance: async function(staffId) {
        try {
            const data = {
                staff_id: staffId,
                amount: parseFloat(prompt('Advance Amount:')),
                reason: prompt('Reason:')
            };

            await API.staff.requestAdvance(data);
            showNotification('Advance request submitted successfully', 'success');
        } catch (error) {
            console.error('Error requesting advance:', error);
            showNotification('Failed to request advance', 'error');
        }
    },

    // ============================================================================
    // PERFORMANCE
    // ============================================================================

    /**
     * View performance
     */
    viewPerformance: async function(staffId) {
        try {
            const reviews = await API.staff.getPerformanceReviewHistory(staffId);
            const kpi = await API.staff.getAcademicKPISummary(staffId);
            
            let message = 'Performance Summary:\n\n';
            message += `Overall Rating: ${kpi.overall_rating || 'N/A'}\n`;
            message += `Student Pass Rate: ${kpi.pass_rate || 0}%\n`;
            message += `Class Performance: ${kpi.class_performance || 'N/A'}\n`;
            
            alert(message);
        } catch (error) {
            console.error('Error loading performance:', error);
            showNotification('Failed to load performance data', 'error');
        }
    },

    // ============================================================================
    // FILTERS & SEARCH
    // ============================================================================

    /**
     * Search staff
     */
    searchStaff: function(query) {
        const term = query.toLowerCase();
        this.filteredStaff = this.staff.filter(s =>
            (s.first_name || '').toLowerCase().includes(term) ||
            (s.last_name || '').toLowerCase().includes(term) ||
            (s.staff_id || '').toLowerCase().includes(term) ||
            (s.email || '').toLowerCase().includes(term)
        );
        this.renderStaffTable();
    },

    /**
     * Filter by type
     */
    filterByType: function(type) {
        if (type) {
            this.filteredStaff = this.staff.filter(s => s.staff_type === type);
        } else {
            this.filteredStaff = [...this.staff];
        }
        this.renderStaffTable();
    },

    /**
     * Filter by department
     */
    filterByDepartment: function(dept) {
        if (dept) {
            this.filteredStaff = this.staff.filter(s => s.department === dept);
        } else {
            this.filteredStaff = [...this.staff];
        }
        this.renderStaffTable();
    },

    /**
     * Filter by status
     */
    filterByStatus: function(status) {
        if (status) {
            this.filteredStaff = this.staff.filter(s => s.status === status);
        } else {
            this.filteredStaff = [...this.staff];
        }
        this.renderStaffTable();
    },

    // ============================================================================
    // UTILITIES
    // ============================================================================

    /**
     * Load departments
     */
    loadDepartments: async function() {
        try {
            const response = await API.staff.getDepartments();
            this.departments = response.data || response || [];
        } catch (error) {
            console.error('Error loading departments:', error);
            this.departments = [];
        }
    },

    /**
     * Check user permissions
     */
    checkUserPermissions: function() {
        const currentUser = AuthContext.getUser();
        if (!currentUser || !currentUser.permissions) return;

        document.querySelectorAll('[data-permission]').forEach(btn => {
            const requiredPerm = btn.getAttribute('data-permission');
            if (!currentUser.permissions.includes(requiredPerm)) {
                btn.style.display = 'none';
            }
        });
    },

    /**
     * Show quick actions
     */
    showQuickActions: function() {
        alert('Quick Actions:\n1. Mark Attendance\n2. Process Payroll\n3. Review Leave Applications\n4. View Performance Reports\n5. Assign Classes/Subjects');
    },

    /**
     * Export staff data
     */
    exportStaff: function() {
        try {
            const csv = this.convertToCSV(this.filteredStaff);
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `staff_export_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            showNotification('Staff data exported successfully', 'success');
        } catch (error) {
            console.error('Error exporting staff:', error);
            showNotification('Failed to export staff data', 'error');
        }
    },

    /**
     * Convert to CSV
     */
    convertToCSV: function(data) {
        const headers = ['Staff ID', 'First Name', 'Last Name', 'Type', 'Department', 'Email', 'Phone', 'Status'];
        const rows = data.map(s => [
            s.staff_id || s.id,
            s.first_name,
            s.last_name,
            s.staff_type,
            s.department,
            s.email,
            s.phone,
            s.status
        ]);
        
        return [headers, ...rows].map(row => row.join(',')).join('\n');
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('staffContainer')) {
        staffManagementController.init();
    }
});
