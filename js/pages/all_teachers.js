/**
 * All Teachers Controller
 * Handles all_teachers.php
 * Uses existing api.js JWT authentication
 */
const AllTeachersController = {
    state: {
        teachers: [],
        departments: [],
        subjects: [],
        currentAcademicYear: null
    },

    async init() {
        if (typeof AuthContext !== 'undefined') {
            await AuthContext.ready();
        }

        if (!AuthContext?.isAuthenticated()) {
            window.location.href = (window.APP_BASE || "") + "/index.php";
            return;
        }

        if (!AuthContext.canView('staff')) {
            showNotification('You do not have permission to view teachers', 'error');
            return;
        }

        this.bindEvents();
        await this.loadTeachers();
        await this.loadFilters();
    },

    bindEvents() {
        document.getElementById('searchTeacher')?.addEventListener('input', (e) => {
            this.filterTeachers(e.target.value);
        });

        document.getElementById('filterDepartment')?.addEventListener('change', () => {
            this.applyFilters();
        });

        document.getElementById('filterSubject')?.addEventListener('change', () => {
            this.applyFilters();
        });

        document.getElementById('exportTeachers')?.addEventListener('click', () => {
            if (AuthContext.canExport('staff')) {
                this.exportTeachers();
            } else {
                showNotification('You do not have permission to export teachers', 'error');
            }
        });
    },

    async loadTeachers() {
        try {
            const response = await window.API.staff.getTeachers({});
            const normalized = AppState.normalizeResponse(response);
            
            if (normalized.success) {
                this.state.teachers = Array.isArray(normalized.data) ? normalized.data : [];
                this.renderTeachersTable();
                this.updateStats();
            } else {
                showNotification('Failed to load teachers', 'error');
            }
        } catch (error) {
            console.error('Error loading teachers:', error);
            showNotification('Failed to load teachers', 'error');
        }
    },

    async loadFilters() {
        try {
            const deptRes = await window.API.staff.getDepartments();
            const deptNormalized = AppState.normalizeResponse(deptRes);
            if (deptNormalized.success) {
                this.state.departments = Array.isArray(deptNormalized.data) ? deptNormalized.data : [];
                this.populateDepartmentDropdown();
            }

            const subjectRes = await window.API.academic.listSubjects();
            const subjectNormalized = AppState.normalizeResponse(subjectRes);
            if (subjectNormalized.success) {
                this.state.subjects = Array.isArray(subjectNormalized.data) ? subjectNormalized.data : [];
                this.populateSubjectDropdown();
            }
        } catch (error) {
            console.error('Error loading filters:', error);
        }
    },

    populateDepartmentDropdown() {
        const select = document.getElementById('filterDepartment');
        if (!select) return;
        select.innerHTML = '<option value="">All Departments</option>' + 
            this.state.departments.map(dept => `<option value="${dept.id}">${dept.name}</option>`).join('');
    },

    populateSubjectDropdown() {
        const select = document.getElementById('filterSubject');
        if (!select) return;
        select.innerHTML = '<option value="">All Subjects</option>' + 
            this.state.subjects.map(subject => `<option value="${subject.id}">${subject.name}</option>`).join('');
    },

    renderTeachersTable() {
        const tbody = document.querySelector('#teachersTable tbody');
        if (!tbody) return;

        if (this.state.teachers.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">No teachers found</td></tr>`;
            return;
        }

        tbody.innerHTML = this.state.teachers.map((teacher) => {
            const statusBadge = teacher.status === 'active' 
                ? '<span class="badge bg-success">Active</span>' 
                : '<span class="badge bg-secondary">Inactive</span>';

            const photoUrl = teacher.photo_url 
                ? `<img src="${teacher.photo_url}" alt="${teacher.first_name}" class="rounded-circle" width="40" height="40">`
                : `<div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" width="40" height="40"><i class="bi bi-person text-white"></i></div>`;

            return `
                <tr>
                    <td>${photoUrl}</td>
                    <td>
                        <strong>${this.escapeHtml(teacher.first_name + ' ' + teacher.last_name)}</strong>
                        <br><small class="text-muted">${teacher.email || '--'}</small>
                    </td>
                    <td>${teacher.employee_id || '--'}</td>
                    <td>${teacher.department_name || '--'}</td>
                    <td>${teacher.subjects_count || 0} subjects</td>
                    <td>${teacher.role_name || '--'}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="AllTeachersController.viewTeacher(${teacher.id})" title="View">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-outline-info" onclick="AllTeachersController.viewAssignments(${teacher.id})" title="View Assignments">
                                <i class="bi bi-journal-text"></i>
                            </button>
                            <button class="btn btn-outline-warning" onclick="AllTeachersController.viewWorkload(${teacher.id})" title="View Workload">
                                <i class="bi bi-bar-chart"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    },

    updateStats() {
        document.getElementById('totalTeachers').textContent = this.state.teachers.length;
        document.getElementById('classTeachers').textContent = this.state.teachers.filter(t => t.is_class_teacher).length;
        document.getElementById('hods').textContent = this.state.teachers.filter(t => t.is_hod).length;
    },

    filterTeachers(searchTerm) {
        const filtered = this.state.teachers.filter(teacher => {
            const searchLower = searchTerm.toLowerCase();
            const fullName = (teacher.first_name + ' ' + teacher.last_name).toLowerCase();
            return fullName.includes(searchLower) ||
                   (teacher.email && teacher.email.toLowerCase().includes(searchLower)) ||
                   (teacher.employee_id && teacher.employee_id.toLowerCase().includes(searchLower));
        });
        
        this.renderFilteredTeachers(filtered);
    },

    applyFilters() {
        const deptId = document.getElementById('filterDepartment')?.value || '';
        const subjectId = document.getElementById('filterSubject')?.value || '';
        
        let filtered = [...this.state.teachers];
        
        if (deptId) {
            filtered = filtered.filter(t => t.department_id == deptId);
        }
        
        if (subjectId) {
            filtered = filtered.filter(t => t.subject_ids && t.subject_ids.includes(parseInt(subjectId)));
        }
        
        this.renderFilteredTeachers(filtered);
    },

    renderFilteredTeachers(filtered) {
        const tbody = document.querySelector('#teachersTable tbody');
        if (!tbody) return;

        if (filtered.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">No teachers match your filters</td></tr>`;
            return;
        }

        tbody.innerHTML = filtered.map((teacher) => {
            const statusBadge = teacher.status === 'active' 
                ? '<span class="badge bg-success">Active</span>' 
                : '<span class="badge bg-secondary">Inactive</span>';

            const photoUrl = teacher.photo_url 
                ? `<img src="${teacher.photo_url}" alt="${teacher.first_name}" class="rounded-circle" width="40" height="40">`
                : `<div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" width="40" height="40"><i class="bi bi-person text-white"></i></div>`;

            return `
                <tr>
                    <td>${photoUrl}</td>
                    <td>
                        <strong>${this.escapeHtml(teacher.first_name + ' ' + teacher.last_name)}</strong>
                        <br><small class="text-muted">${teacher.email || '--'}</small>
                    </td>
                    <td>${teacher.employee_id || '--'}</td>
                    <td>${teacher.department_name || '--'}</td>
                    <td>${teacher.subjects_count || 0} subjects</td>
                    <td>${teacher.role_name || '--'}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="AllTeachersController.viewTeacher(${teacher.id})" title="View">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-outline-info" onclick="AllTeachersController.viewAssignments(${teacher.id})" title="View Assignments">
                                <i class="bi bi-journal-text"></i>
                            </button>
                            <button class="btn btn-outline-warning" onclick="AllTeachersController.viewWorkload(${teacher.id})" title="View Workload">
                                <i class="bi bi-bar-chart"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    },

    viewTeacher(teacherId) {
        window.location.href = (window.APP_BASE || '') + '/home.php?route=manage_staff&id=' + teacherId;
    },

    viewAssignments(teacherId) {
        window.location.href = (window.APP_BASE || '') + '/home.php?route=assign_subjects_to_teachers&teacher_id=' + teacherId;
    },

    viewWorkload(teacherId) {
        window.location.href = (window.APP_BASE || '') + '/home.php?route=teacher_workload&teacher_id=' + teacherId;
    },

    exportTeachers() {
        if (!this.state.teachers.length) return;
        
        const headers = ['#', 'Name', 'Employee ID', 'Department', 'Subjects', 'Role', 'Status'];
        const rows = this.state.teachers.map((teacher, i) => [
            i + 1,
            teacher.first_name + ' ' + teacher.last_name,
            teacher.employee_id || '--',
            teacher.department_name || '--',
            teacher.subjects_count || 0,
            teacher.role_name || '--',
            teacher.status || '--'
        ]);
        
        let csv = headers.join(',') + '\n' + 
            rows.map(r => r.map(v => '"' + (v || '') + '"').join(',')).join('\n');
        
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
        a.download = 'teachers.csv';
        a.click();
    },

    escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
};

document.addEventListener('DOMContentLoaded', () => AllTeachersController.init());