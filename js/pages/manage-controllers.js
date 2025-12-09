/**
 * Management Pages Controllers
 * Controllers for all manage_* pages (students, teachers, staff, payroll, admissions)
 * This file provides controllers for the newly refactored HTML-only pages
 */

// ============================================================================
// MANAGE STUDENTS CONTROLLER
// ============================================================================

const manageStudentsController = {
    students: [],
    filteredStudents: [],
    classes: [],

    init: async function() {
        try {
            const response = await API.academic.classes.index();
            this.classes = response.data || response || [];
            this.populateClassFilter();
            await this.loadStudents();
            this.setupEventListeners();
        } catch (error) {
            console.error('Error initializing manage students:', error);
            showNotification('Failed to load students data', 'error');
        }
    },

    loadStudents: async function() {
        try {
            const response = await API.students.index();
            this.students = response.data || response || [];
            this.filteredStudents = [...this.students];
            this.renderTable();
        } catch (error) {
            console.error('Error loading students:', error);
            showNotification('Failed to load students', 'error');
        }
    },

    populateClassFilter: function() {
        const classFilter = document.getElementById('classFilter');
        if (!classFilter) return;
        classFilter.innerHTML = '<option value="">-- All Classes --</option>';
        this.classes.forEach(cls => {
            classFilter.innerHTML += `<option value="${cls.class_id}">${cls.class_name}</option>`;
        });
    },

    renderTable: function() {
        const container = document.getElementById('studentsTableContainer');
        if (!container) return;

        if (this.filteredStudents.length === 0) {
            container.innerHTML = '<p class="text-muted text-center">No students found</p>';
            return;
        }

        let html = `
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr><th>Adm. No.</th><th>Name</th><th>Class</th><th>Email</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
        `;

        this.filteredStudents.forEach(student => {
            const badges = {
                'active': 'success', 'pending': 'warning', 'inactive': 'secondary', 'suspended': 'danger'
            };
            const badge = badges[student.status] || 'secondary';
            html += `
                <tr>
                    <td>${student.admission_number || 'N/A'}</td>
                    <td>${student.first_name} ${student.last_name}</td>
                    <td>${student.class_name || 'Unassigned'}</td>
                    <td>${student.email || 'N/A'}</td>
                    <td><span class="badge bg-${badge}">${student.status}</span></td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="manageStudentsController.showEditForm(${student.student_id})"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-danger" onclick="manageStudentsController.deleteStudent(${student.student_id})"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table>';
        container.innerHTML = html;
    },

    search: function(query) {
        const term = query.toLowerCase().trim();
        this.filteredStudents = this.students.filter(s => 
            (s.first_name + ' ' + s.last_name).toLowerCase().includes(term) ||
            (s.admission_number || '').toLowerCase().includes(term)
        );
        this.renderTable();
    },

    filterByClass: function(classId) {
        this.filteredStudents = classId ? this.students.filter(s => s.class_id == classId) : [...this.students];
        this.renderTable();
    },

    filterByStatus: function(status) {
        this.filteredStudents = status ? this.students.filter(s => s.status === status) : [...this.students];
        this.renderTable();
    },

    showCreateForm: function() {
        const modal = new bootstrap.Modal(document.getElementById('studentModal'));
        document.getElementById('studentModalLabel').textContent = 'Add Student';
        document.getElementById('studentForm').reset();
        document.getElementById('studentId').value = '';
        const classSelect = document.getElementById('classSelect');
        classSelect.innerHTML = '<option value="">-- Select Class --</option>';
        this.classes.forEach(cls => {
            classSelect.innerHTML += `<option value="${cls.class_id}">${cls.class_name}</option>`;
        });
        modal.show();
    },

    showEditForm: async function(studentId) {
        try {
            const student = await API.students.get(studentId);
            const modal = new bootstrap.Modal(document.getElementById('studentModal'));
            document.getElementById('studentModalLabel').textContent = 'Edit Student';
            document.getElementById('studentId').value = student.student_id;
            document.getElementById('firstName').value = student.first_name;
            document.getElementById('lastName').value = student.last_name;
            document.getElementById('email').value = student.email || '';
            const classSelect = document.getElementById('classSelect');
            classSelect.innerHTML = '<option value="">-- Select Class --</option>';
            this.classes.forEach(cls => {
                classSelect.innerHTML += `<option value="${cls.class_id}" ${cls.class_id == student.class_id ? 'selected' : ''}>${cls.class_name}</option>`;
            });
            document.getElementById('statusSelect').value = student.status;
            modal.show();
        } catch (error) {
            console.error('Error loading student:', error);
            showNotification('Failed to load student data', 'error');
        }
    },

    setupEventListeners: function() {
        const form = document.getElementById('studentForm');
        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const studentId = document.getElementById('studentId').value;
                const formData = {
                    first_name: document.getElementById('firstName').value,
                    last_name: document.getElementById('lastName').value,
                    email: document.getElementById('email').value,
                    class_id: document.getElementById('classSelect').value,
                    status: document.getElementById('statusSelect').value
                };
                try {
                    if (studentId) {
                        await API.students.update(studentId, formData);
                        showNotification('Student updated successfully', 'success');
                    } else {
                        await API.students.create(formData);
                        showNotification('Student created successfully', 'success');
                    }
                    await this.loadStudents();
                    bootstrap.Modal.getInstance(document.getElementById('studentModal')).hide();
                } catch (error) {
                    showNotification('Failed to save student', 'error');
                }
            });
        }
    },

    deleteStudent: async function(studentId) {
        if (!confirm('Are you sure you want to delete this student?')) return;
        try {
            await API.students.delete(studentId);
            showNotification('Student deleted successfully', 'success');
            await this.loadStudents();
        } catch (error) {
            showNotification('Failed to delete student', 'error');
        }
    }
};

// ============================================================================
// MANAGE TEACHERS CONTROLLER
// ============================================================================

const manageTeachersController = {
    teachers: [],
    filteredTeachers: [],

    init: async function() {
        try {
            await this.loadTeachers();
            this.setupEventListeners();
        } catch (error) {
            console.error('Error initializing manage teachers:', error);
        }
    },

    loadTeachers: async function() {
        const response = await API.staff.index();
        this.teachers = (response.data || response || []).filter(s => s.staff_type === 'teaching');
        this.filteredTeachers = [...this.teachers];
        this.renderTable();
    },

    renderTable: function() {
        const container = document.getElementById('teachersTableContainer');
        if (!container) return;
        if (this.filteredTeachers.length === 0) {
            container.innerHTML = '<p class="text-muted text-center">No teachers found</p>';
            return;
        }
        let html = '<table class="table table-striped"><thead class="table-info"><tr><th>Staff No.</th><th>Name</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        this.filteredTeachers.forEach(t => {
            html += `<tr>
                <td>${t.staff_number || 'N/A'}</td>
                <td>${t.first_name} ${t.last_name}</td>
                <td>${t.email || 'N/A'}</td>
                <td><span class="badge bg-${t.status === 'active' ? 'success' : 'secondary'}">${t.status}</span></td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="manageTeachersController.showEditForm(${t.staff_id})"><i class="bi bi-pencil"></i></button>
                </td>
            </tr>`;
        });
        html += '</tbody></table>';
        container.innerHTML = html;
    },

    search: function(query) {
        const term = query.toLowerCase();
        this.filteredTeachers = this.teachers.filter(t => (t.first_name + ' ' + t.last_name).toLowerCase().includes(term));
        this.renderTable();
    },

    filterByDepartment: function(dept) {
        this.filteredTeachers = dept ? this.teachers.filter(t => t.department === dept) : [...this.teachers];
        this.renderTable();
    },

    filterByStatus: function(status) {
        this.filteredTeachers = status ? this.teachers.filter(t => t.status === status) : [...this.teachers];
        this.renderTable();
    },

    showCreateForm: function() {
        const modal = new bootstrap.Modal(document.getElementById('teacherModal'));
        document.getElementById('teacherForm').reset();
        modal.show();
    },

    showEditForm: async function(id) {
        const teacher = await API.staff.get(id);
        document.getElementById('teacherId').value = teacher.staff_id;
        document.getElementById('firstName').value = teacher.first_name;
        document.getElementById('lastName').value = teacher.last_name;
        document.getElementById('email').value = teacher.email || '';
        new bootstrap.Modal(document.getElementById('teacherModal')).show();
    },

    setupEventListeners: function() {
        const form = document.getElementById('teacherForm');
        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = {
                    first_name: document.getElementById('firstName').value,
                    last_name: document.getElementById('lastName').value,
                    email: document.getElementById('email').value,
                    staff_type: 'teaching'
                };
                const id = document.getElementById('teacherId').value;
                if (id) {
                    await API.staff.update(id, formData);
                } else {
                    await API.staff.create(formData);
                }
                await this.loadTeachers();
                bootstrap.Modal.getInstance(document.getElementById('teacherModal')).hide();
            });
        }
    }
};

// Initialize controllers based on page
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('studentsTableContainer')) manageStudentsController.init();
    if (document.getElementById('teachersTableContainer')) manageTeachersController.init();
});
