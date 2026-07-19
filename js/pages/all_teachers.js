/**
 * All Teachers Controller
 * Page: all_teachers.php
 * Academic-specific view of all teaching staff
 * Integrates with AcademicContext for academic year awareness
 */
const AllTeachersController = {
  state: {
    teachers: [],
    departments: [],
    subjects: [],
    currentAcademicYear: null,
    stats: {
      totalTeachers: 0,
      classTeachers: 0,
      hods: 0
    }
  },

  async init() {
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    
    // Initialize Academic Context if available
    if (window.AcademicContext) {
      // Subscribe to context changes
      window.AcademicContext.subscribe((context, event, data) => {
        console.log('AcademicContext changed in all_teachers:', event, data);
        if (event === 'yearChanged' || event === 'initialized' || event === 'refreshed') {
          this.loadTeachers();
        }
      });
      
      // Ensure context is loaded
      if (!window.AcademicContext.isLoaded()) {
        await window.AcademicContext.init();
      }
      
      // Get current academic context
      this.state.currentAcademicYear = window.AcademicContext.getAcademicYearId();
    }
    
    this.bindEvents();
    await this.loadTeachers();
    await this.loadFilters();
  },

  bindEvents() {
    // Search input
    const searchInput = document.getElementById('searchTeacher');
    if (searchInput) {
      searchInput.addEventListener('input', (e) => {
        this.filterTeachers(e.target.value);
      });
    }

    // Department filter
    const deptFilter = document.getElementById('filterDepartment');
    if (deptFilter) {
      deptFilter.addEventListener('change', () => {
        this.applyFilters();
      });
    }

    // Subject filter
    const subjectFilter = document.getElementById('filterSubject');
    if (subjectFilter) {
      subjectFilter.addEventListener('change', () => {
        this.applyFilters();
      });
    }

    // Export button
    const exportBtn = document.getElementById('exportTeachers');
    if (exportBtn) {
      exportBtn.addEventListener('click', () => {
        this.exportTeachers();
      });
    }
  },

  async loadTeachers() {
    try {
      const academicYearId = this.state.currentAcademicYear;
      const params = academicYearId ? { academic_year_id: academicYearId } : {};
      
      const res = await window.API.apiCall('/staff/teachers', 'GET', params);
      
      if (res?.success) {
        this.state.teachers = res.data || [];
        this.renderTeachersTable();
        this.updateStats();
      } else {
        this.showNotification('Failed to load teachers', 'error');
      }
    } catch (error) {
      console.error('Error loading teachers:', error);
      this.showNotification('Failed to load teachers', 'error');
    }
  },

  async loadFilters() {
    try {
      // Load departments
      const deptRes = await window.API.apiCall('/staff/departments', 'GET');
      if (deptRes?.success) {
        this.state.departments = deptRes.data || [];
        const deptFilter = document.getElementById('filterDepartment');
        if (deptFilter) {
          deptFilter.innerHTML = '<option value="">All Departments</option>' + 
            this.state.departments.map(dept => `<option value="${dept.id}">${dept.name}</option>`).join('');
        }
      }

      // Load subjects
      const subjectRes = await window.API.apiCall('/academic/subjects', 'GET');
      if (subjectRes?.success) {
        this.state.subjects = subjectRes.data || [];
        const subjectFilter = document.getElementById('filterSubject');
        if (subjectFilter) {
          subjectFilter.innerHTML = '<option value="">All Subjects</option>' + 
            this.state.subjects.map(subject => `<option value="${subject.id}">${subject.name}</option>`).join('');
        }
      }
    } catch (error) {
      console.error('Error loading filters:', error);
    }
  },

  renderTeachersTable() {
    const tbody = document.querySelector('#teachersTable tbody');
    if (!tbody) return;

    if (this.state.teachers.length === 0) {
      tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">No teachers found</td></tr>`;
      return;
    }

    tbody.innerHTML = this.state.teachers.map((teacher, index) => {
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

    tbody.innerHTML = filtered.map((teacher, index) => {
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
    // Navigate to staff detail page
    window.location.href = (window.APP_BASE || '') + '/home.php?route=manage_staff&id=' + teacherId;
  },

  viewAssignments(teacherId) {
    // Navigate to subject assignments page filtered by teacher
    window.location.href = (window.APP_BASE || '') + '/home.php?route=assign_subjects_to_teachers&teacher_id=' + teacherId;
  },

  viewWorkload(teacherId) {
    // Navigate to teacher workload page
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
    
    if (window.PrintManager) {
      window.PrintManager.exportToCSV({
        headers,
        rows
      }, 'teachers');
    } else {
      // Fallback
      let csv = headers.join(',') + '\n' + 
        rows.map(r => r.map(v => '"' + (v || '') + '"').join(',')).join('\n');
      
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
      a.download = 'teachers.csv';
      a.click();
    }
  },

  escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  },

  showNotification(message, type = 'info') {
    if (typeof showNotification === 'function') {
      showNotification(message, type);
    } else {
      // Fallback notification
      const container = document.querySelector('.container-fluid') || document.body;
      const alert = document.createElement('div');
      alert.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
      alert.style.zIndex = '9999';
      alert.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
      container.appendChild(alert);
      setTimeout(() => alert.remove(), 4000);
    }
  }
};

document.addEventListener('DOMContentLoaded', () => AllTeachersController.init());
