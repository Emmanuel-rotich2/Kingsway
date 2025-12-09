/**
 * Students Management Controller
 * Handles student CRUD, admission workflow, promotions, ID cards, parents, medical, discipline
 * Integrates with /api/students endpoints
 */

const studentsManagementController = {
    students: [],
    classes: [],
    streams: [],
    filteredStudents: [],
    currentFilters: {},

    /**
     * Initialize controller
     */
    init: async function() {
        try {
            console.log('Loading students data...');
            await Promise.all([
                this.loadStudents(),
                this.loadClasses()
            ]);
            this.checkUserPermissions();
            console.log('Students management loaded successfully');
        } catch (error) {
            console.error('Error initializing students controller:', error);
            showNotification('Failed to load students management', 'error');
        }
    },

    // ============================================================================
    // STUDENTS CRUD
    // ============================================================================

    /**
     * Load all students
     */
    loadStudents: async function(filters = {}) {
        try {
            const response = await API.students.index();
            this.students = response.data || response || [];
            this.filteredStudents = [...this.students];
            this.renderStudentsTable();
        } catch (error) {
            console.error('Error loading students:', error);
            const container = document.getElementById('studentsContainer');
            if (container) {
                container.innerHTML = '<div class="alert alert-danger">Failed to load students</div>';
            }
        }
    },

    /**
     * Render students table
     */
    renderStudentsTable: function() {
        const container = document.getElementById('studentsContainer');
        if (!container) return;

        if (this.filteredStudents.length === 0) {
            container.innerHTML = '<div class="alert alert-info">No students found. Click "Add Student" to register a new student.</div>';
            return;
        }

        let html = `
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead class="table-primary">
                        <tr>
                            <th>Photo</th>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Gender</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        this.filteredStudents.forEach(student => {
            const photoUrl = student.photo_url || '/images/students/default-avatar.png';
            const statusBadge = this.getStatusBadge(student.status);
            
            html += `
                <tr>
                    <td><img src="${photoUrl}" alt="${student.first_name}" class="rounded-circle" width="40" height="40"></td>
                    <td><strong>${student.student_id || student.admission_number}</strong></td>
                    <td>${student.first_name} ${student.last_name}</td>
                    <td><span class="badge bg-info">${student.class_name || 'N/A'}</span></td>
                    <td>${student.gender || 'N/A'}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-info" onclick="studentsManagementController.viewStudent(${student.student_id || student.id})" title="View Profile">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-warning" onclick="studentsManagementController.editStudent(${student.student_id || student.id})" title="Edit" data-permission="students_update">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-success" onclick="studentsManagementController.generateIdCard(${student.student_id || student.id})" title="Generate ID Card" data-permission="students_id_card">
                                <i class="bi bi-credit-card"></i>
                            </button>
                            <button class="btn btn-danger" onclick="studentsManagementController.deleteStudent(${student.student_id || student.id})" title="Delete" data-permission="students_delete">
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
     * Get status badge
     */
    getStatusBadge: function(status) {
        const badges = {
            'active': '<span class="badge bg-success">Active</span>',
            'inactive': '<span class="badge bg-secondary">Inactive</span>',
            'suspended': '<span class="badge bg-danger">Suspended</span>',
            'graduated': '<span class="badge bg-primary">Graduated</span>',
            'transferred': '<span class="badge bg-warning">Transferred</span>'
        };
        return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
    },

    /**
     * Show add student modal
     */
    showAddStudentModal: function() {
        console.log('Add Student feature coming soon. Use admission workflow instead.');
    },

    /**
     * Create student
     */
    createStudent: async function(formData) {
        try {
            await API.students.create(formData);
            showNotification('Student created successfully', 'success');
            await this.loadStudents();
        } catch (error) {
            console.error('Error creating student:', error);
            showNotification('Failed to create student: ' + (error.message || 'Unknown error'), 'error');
        }
    },

    /**
     * View student profile
     */
    viewStudent: async function(studentId) {
        try {
            const student = await API.students.get(studentId);
            const profile = await API.students.getProfile(studentId);
            
            alert(`Student Profile:\n\nID: ${student.student_id}\nName: ${student.first_name} ${student.last_name}\nClass: ${student.class_name}\nStatus: ${student.status}\n\nClick on Edit to modify details.`);
        } catch (error) {
            console.error('Error loading student:', error);
            showNotification('Failed to load student profile', 'error');
        }
    },

    /**
     * Edit student
     */
    editStudent: function(studentId) {
        console.log('Edit student feature coming soon');
    },

    /**
     * Delete student
     */
    deleteStudent: async function(studentId) {
        if (!confirm('Are you sure you want to delete this student? This action cannot be undone.')) {
            return;
        }

        try {
            await API.students.delete(studentId);
            showNotification('Student deleted successfully', 'success');
            await this.loadStudents();
        } catch (error) {
            console.error('Error deleting student:', error);
            showNotification('Failed to delete student: ' + (error.message || 'Unknown error'), 'error');
        }
    },

    // ============================================================================
    // ADMISSION WORKFLOW
    // ============================================================================

    /**
     * Start admission workflow
     */
    startAdmissionWorkflow: async function() {
        const firstName = prompt('Enter student first name:');
        if (!firstName) return;
        
        const lastName = prompt('Enter student last name:');
        if (!lastName) return;

        try {
            const data = {
                first_name: firstName,
                last_name: lastName,
                date_of_birth: prompt('Date of birth (YYYY-MM-DD):'),
                gender: prompt('Gender (Male/Female):'),
                class_id: prompt('Class ID:')
            };

            const response = await API.students.startAdmissionWorkflow(data);
            showNotification('Admission workflow started successfully', 'success');
            alert('Next steps: 1. Verify documents 2. Conduct interview 3. Approve admission 4. Complete registration');
        } catch (error) {
            console.error('Error starting admission:', error);
            showNotification('Failed to start admission workflow', 'error');
        }
    },

    /**
     * Verify documents
     */
    verifyDocuments: async function(studentId) {
        try {
            await API.students.verifyDocuments(studentId, {
                documents_verified: true,
                verified_by: AuthContext.getUser().user_id
            });
            showNotification('Documents verified successfully', 'success');
        } catch (error) {
            console.error('Error verifying documents:', error);
            showNotification('Failed to verify documents', 'error');
        }
    },

    /**
     * Conduct interview
     */
    conductInterview: async function(studentId) {
        try {
            await API.students.conductInterview(studentId, {
                interview_passed: true,
                interviewed_by: AuthContext.getUser().user_id,
                notes: prompt('Interview notes:')
            });
            showNotification('Interview conducted successfully', 'success');
        } catch (error) {
            console.error('Error conducting interview:', error);
            showNotification('Failed to conduct interview', 'error');
        }
    },

    /**
     * Approve admission
     */
    approveAdmission: async function(studentId) {
        try {
            await API.students.approveAdmission(studentId, {
                approved: true,
                approved_by: AuthContext.getUser().user_id
            });
            showNotification('Admission approved successfully', 'success');
        } catch (error) {
            console.error('Error approving admission:', error);
            showNotification('Failed to approve admission', 'error');
        }
    },

    // ============================================================================
    // ID CARDS & QR CODES
    // ============================================================================

    /**
     * Generate ID card for single student
     */
    generateIdCard: async function(studentId) {
        try {
            const response = await API.students.generateIdCard(studentId);
            showNotification('ID card generated successfully', 'success');
            
            // Open ID card in new window if URL provided
            if (response.url || response.file_path) {
                window.open(response.url || response.file_path, '_blank');
            }
        } catch (error) {
            console.error('Error generating ID card:', error);
            showNotification('Failed to generate ID card', 'error');
        }
    },

    /**
     * Generate ID cards for entire class
     */
    generateClassIdCards: async function() {
        const classId = prompt('Enter Class ID:');
        if (!classId) return;

        try {
            const response = await API.students.generateClassIdCards(classId);
            showNotification(`ID cards generated for ${response.count || 0} students`, 'success');
        } catch (error) {
            console.error('Error generating class ID cards:', error);
            showNotification('Failed to generate class ID cards', 'error');
        }
    },

    /**
     * Generate QR code
     */
    generateQrCode: async function(studentId) {
        try {
            const response = await API.students.generateQrCode(studentId);
            showNotification('QR code generated successfully', 'success');
        } catch (error) {
            console.error('Error generating QR code:', error);
            showNotification('Failed to generate QR code', 'error');
        }
    },

    // ============================================================================
    // PROMOTIONS
    // ============================================================================

    /**
     * Promote single student
     */
    promoteSingle: async function(studentId) {
        const newClassId = prompt('Enter new Class ID:');
        if (!newClassId) return;

        try {
            await API.students.promoteSingle({
                student_id: studentId,
                new_class_id: newClassId,
                academic_year: new Date().getFullYear()
            });
            showNotification('Student promoted successfully', 'success');
            await this.loadStudents();
        } catch (error) {
            console.error('Error promoting student:', error);
            showNotification('Failed to promote student', 'error');
        }
    },

    /**
     * Promote entire class
     */
    promoteEntireClass: async function() {
        const classId = prompt('Enter Class ID to promote:');
        if (!classId) return;

        const newClassId = prompt('Enter new Class ID:');
        if (!newClassId) return;

        if (!confirm(`Promote all students from class ${classId} to class ${newClassId}?`)) {
            return;
        }

        try {
            await API.students.promoteEntireClass({
                current_class_id: classId,
                new_class_id: newClassId,
                academic_year: new Date().getFullYear()
            });
            showNotification('Class promoted successfully', 'success');
            await this.loadStudents();
        } catch (error) {
            console.error('Error promoting class:', error);
            showNotification('Failed to promote class', 'error');
        }
    },

    // ============================================================================
    // BULK OPERATIONS
    // ============================================================================

    /**
     * Bulk upload students
     */
    bulkUpload: function() {
        console.log('Use Import Existing Students page for bulk operations');
    },

    /**
     * Export students data
     */
    exportStudents: function() {
        try {
            const csv = this.convertToCSV(this.filteredStudents);
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `students_export_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            showNotification('Students data exported successfully', 'success');
        } catch (error) {
            console.error('Error exporting students:', error);
            showNotification('Failed to export students data', 'error');
        }
    },

    /**
     * Convert to CSV
     */
    convertToCSV: function(data) {
        const headers = ['Student ID', 'First Name', 'Last Name', 'Class', 'Gender', 'Status'];
        const rows = data.map(s => [
            s.student_id || s.admission_number,
            s.first_name,
            s.last_name,
            s.class_name,
            s.gender,
            s.status
        ]);
        
        return [headers, ...rows].map(row => row.join(',')).join('\n');
    },

    // ============================================================================
    // FILTERS & SEARCH
    // ============================================================================

    /**
     * Search students
     */
    searchStudents: function(query) {
        const term = query.toLowerCase();
        this.filteredStudents = this.students.filter(s =>
            (s.first_name || '').toLowerCase().includes(term) ||
            (s.last_name || '').toLowerCase().includes(term) ||
            (s.student_id || '').toLowerCase().includes(term) ||
            (s.admission_number || '').toLowerCase().includes(term)
        );
        this.renderStudentsTable();
    },

    /**
     * Filter by class
     */
    filterByClass: function(classId) {
        if (classId) {
            this.filteredStudents = this.students.filter(s => s.class_id == classId);
        } else {
            this.filteredStudents = [...this.students];
        }
        this.renderStudentsTable();
    },

    /**
     * Filter by status
     */
    filterByStatus: function(status) {
        if (status) {
            this.filteredStudents = this.students.filter(s => s.status === status);
        } else {
            this.filteredStudents = [...this.students];
        }
        this.renderStudentsTable();
    },

    /**
     * Filter by gender
     */
    filterByGender: function(gender) {
        if (gender) {
            this.filteredStudents = this.students.filter(s => s.gender === gender);
        } else {
            this.filteredStudents = [...this.students];
        }
        this.renderStudentsTable();
    },

    // ============================================================================
    // UTILITIES
    // ============================================================================

    /**
     * Load classes
     */
    loadClasses: async function() {
        try {
            const response = await API.academic.listClasses();
            this.classes = response.data || response || [];
            this.populateClassFilters();
        } catch (error) {
            console.error('Error loading classes:', error);
            this.classes = [];
        }
    },

    /**
     * Populate class filters
     */
    populateClassFilters: function() {
        const classFilter = document.getElementById('classFilter');
        if (!classFilter) return;

        classFilter.innerHTML = '<option value="">All Classes</option>';
        this.classes.forEach(cls => {
            classFilter.innerHTML += `<option value="${cls.class_id || cls.id}">${cls.class_name}</option>`;
        });
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
        alert('Quick Actions:\n1. Start Admission\n2. Generate Class ID Cards\n3. Promote Entire Class\n4. Export Students Data\n5. Import Students');
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('studentsContainer')) {
        studentsManagementController.init();
    }
});
