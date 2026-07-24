/**
 * Teacher Workload Controller
 * Handles teacher_workload.php
 * Uses existing api.js JWT authentication
 */
const TeacherWorkloadController = {
    state: {
        teachers: [],
        departments: [],
        workloadData: [],
        currentFilters: {
            search: '',
            department: null,
            workload: null
        }
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
            showNotification('You do not have permission to view teacher workload', 'error');
            return;
        }

        this.bindEvents();
        await this.loadInitialData();
    },

    async loadInitialData() {
        await Promise.all([
            this.loadTeachers(),
            this.loadDepartments()
        ]);
    },

    async loadTeachers() {
        try {
            const response = await window.API.staff.getTeachers({});
            const normalized = AppState.normalizeResponse(response);
            
            if (normalized.success) {
                this.state.teachers = Array.isArray(normalized.data) ? normalized.data : [];
                await this.loadWorkloadData();
                this.render();
            } else {
                showNotification('Failed to load teachers', 'error');
            }
        } catch (error) {
            console.error('Error loading teachers:', error);
            showNotification('Failed to load teachers', 'error');
        }
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

    async loadWorkloadData() {
        try {
            const workloadResponse = await window.API.staff.getWorkload();
            const normalized = AppState.normalizeResponse(workloadResponse);
            
            if (normalized.success) {
                this.state.workloadData = Array.isArray(normalized.data) ? normalized.data : [];
                this.mergeWorkloadData();
            }
        } catch (error) {
            console.error('Error loading workload data:', error);
            // Fallback: create empty workload data
            this.state.workloadData = this.state.teachers.map(t => ({
                staff_id: t.id,
                lessons_per_week: 0,
                subjects_count: 0,
                classes_count: 0,
                workload_status: 'underloaded'
            }));
        }
    },

    mergeWorkloadData() {
        // Merge workload data with teacher data
        this.state.teachers = this.state.teachers.map(teacher => {
            const workload = this.state.workloadData.find(w => w.staff_id === teacher.id) || {};
            return {
                ...teacher,
                lessons_per_week: workload.lessons_per_week || 0,
                subjects_count: workload.subjects_count || 0,
                classes_count: workload.classes_count || 0,
                workload_status: this.calculateWorkloadStatus(workload.lessons_per_week || 0)
            };
        });
    },

    calculateWorkloadStatus(lessonsPerWeek) {
        if (lessonsPerWeek > 30) return 'overloaded';
        if (lessonsPerWeek < 15) return 'underloaded';
        return 'optimal';
    },

    populateDepartmentDropdown() {
        const select = document.getElementById('filterDepartment');
        if (!select) return;
        select.innerHTML = '<option value="">All Departments</option>' + 
            this.state.departments.map(dept => `<option value="${dept.id}">${dept.name}</option>`).join('');
    },

    bindEvents() {
        document.getElementById('searchTeacher')?.addEventListener('input', (e) => {
            this.state.currentFilters.search = e.target.value;
            this.applyFilters();
        });

        document.getElementById('filterDepartment')?.addEventListener('change', (e) => {
            this.state.currentFilters.department = e.target.value || null;
            this.applyFilters();
        });

        document.getElementById('filterWorkload')?.addEventListener('change', (e) => {
            this.state.currentFilters.workload = e.target.value || null;
            this.applyFilters();
        });

        document.getElementById('exportWorkload')?.addEventListener('click', () => {
            if (AuthContext.canExport('staff')) {
                this.exportWorkload();
            } else {
                showNotification('You do not have permission to export workload data', 'error');
            }
        });
    },

    applyFilters() {
        let filtered = [...this.state.teachers];

        if (this.state.currentFilters.search) {
            const search = this.state.currentFilters.search.toLowerCase();
            filtered = filtered.filter(teacher => {
                const name = `${teacher.first_name || ''} ${teacher.last_name || ''}`.toLowerCase();
                return name.includes(search);
            });
        }

        if (this.state.currentFilters.department) {
            filtered = filtered.filter(teacher => 
                teacher.department_id == this.state.currentFilters.department
            );
        }

        if (this.state.currentFilters.workload) {
            filtered = filtered.filter(teacher => 
                teacher.workload_status === this.state.currentFilters.workload
            );
        }

        this.renderFilteredTeachers(filtered);
    },

    render() {
        this.renderStats();
        this.renderChart();
        this.renderTable();
    },

    renderStats() {
        const total = this.state.teachers.length;
        const totalLessons = this.state.teachers.reduce((sum, t) => sum + (t.lessons_per_week || 0), 0);
        const avgLessons = total ? Math.round(totalLessons / total) : 0;
        const overloaded = this.state.teachers.filter(t => t.workload_status === 'overloaded').length;
        const underloaded = this.state.teachers.filter(t => t.workload_status === 'underloaded').length;

        document.getElementById('totalTeachers').textContent = total;
        document.getElementById('avgLessons').textContent = avgLessons;
        document.getElementById('overloaded').textContent = overloaded;
        document.getElementById('underloaded').textContent = underloaded;
    },

    renderChart() {
        if (!window.Chart) return;

        const ctx = document.getElementById('workloadChart');
        if (!ctx) return;

        const labels = this.state.teachers.map(t => 
            `${t.first_name} ${t.last_name}`.trim()
        );
        const data = this.state.teachers.map(t => t.lessons_per_week || 0);
        const colors = this.state.teachers.map(t => {
            if (t.workload_status === 'overloaded') return 'rgba(220, 53, 69, 0.8)';
            if (t.workload_status === 'underloaded') return 'rgba(255, 193, 7, 0.8)';
            return 'rgba(25, 135, 84, 0.8)';
        });

        // Destroy existing chart if it exists
        if (this.workloadChart) {
            this.workloadChart.destroy();
        }

        this.workloadChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Lessons per Week',
                    data,
                    backgroundColor: colors,
                    borderColor: colors.map(c => c.replace('0.8', '1')),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Lessons per Week'
                        }
                    }
                }
            }
        });
    },

    renderTable() {
        this.renderFilteredTeachers(this.state.teachers);
    },

    renderFilteredTeachers(teachers) {
        const tbody = document.querySelector('#workloadTable tbody');
        if (!tbody) return;

        if (teachers.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No teachers found</td></tr>';
            return;
        }

        tbody.innerHTML = teachers.map((teacher) => {
            const statusBadge = this.getWorkloadBadge(teacher.workload_status);
            
            return `
                <tr>
                    <td><strong>${this.escapeHtml(teacher.first_name + ' ' + teacher.last_name)}</strong></td>
                    <td>${teacher.department_name || '-'}</td>
                    <td>${teacher.subjects_count || 0} subjects</td>
                    <td>${teacher.classes_count || 0} classes</td>
                    <td>${teacher.lessons_per_week || 0}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="TeacherWorkloadController.viewDetails(${teacher.id})" title="View Details">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    },

    getWorkloadBadge(status) {
        const badges = {
            'overloaded': '<span class="badge bg-danger">Overloaded</span>',
            'optimal': '<span class="badge bg-success">Optimal</span>',
            'underloaded': '<span class="badge bg-warning">Underloaded</span>'
        };
        return badges[status] || badges['optimal'];
    },

    viewDetails(teacherId) {
        const teacher = this.state.teachers.find(t => t.id === teacherId);
        if (!teacher) return;

        alert(`Teacher: ${teacher.first_name} ${teacher.last_name}\nLessons/Week: ${teacher.lessons_per_week}\nSubjects: ${teacher.subjects_count}\nClasses: ${teacher.classes_count}\nStatus: ${teacher.workload_status}`);
    },

    exportWorkload() {
        if (!this.state.teachers.length) {
            showNotification('No data to export', 'warning');
            return;
        }

        const headers = ['Teacher', 'Department', 'Subjects', 'Classes', 'Lessons/Week', 'Status'];
        const rows = this.state.teachers.map(t => [
            `${t.first_name} ${t.last_name}`,
            t.department_name || '-',
            t.subjects_count || 0,
            t.classes_count || 0,
            t.lessons_per_week || 0,
            t.workload_status || '-'
        ]);

        let csv = headers.join(',') + '\n' + 
            rows.map(r => r.map(v => '"' + (v || '') + '"').join(',')).join('\n');

        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
        a.download = 'teacher_workload.csv';
        a.click();
    },

    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
};

document.addEventListener('DOMContentLoaded', () => TeacherWorkloadController.init());