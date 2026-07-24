document.addEventListener('DOMContentLoaded', async () => { if (window.StaffAccess) await StaffAccess.require('staff.teaching_assignments.view'); });
/**
 * Assign Class Teachers Controller
 * Page: assign_class_teachers.php
 * Dedicated page for assigning teachers to classes as class teachers
 * Integrates with AcademicContext for academic year awareness
 */
const AssignClassTeachersController = {
  state: {
    assignments: [],
    classes: [],
    streams: [],
    teachers: [],
    academicYears: [],
    currentAcademicYear: null,
    stats: {
      totalClasses: 0,
      assignedTeachers: 0,
      unassignedClasses: 0,
      totalTeachers: 0
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
        console.log('AcademicContext changed in assign_class_teachers:', event, data);
        if (event === 'yearChanged' || event === 'initialized' || event === 'refreshed') {
          this.loadAssignments();
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
    await this.loadReferenceData();
    await this.loadAssignments();
  },

  bindEvents() {
    // Search input
    const searchInput = document.getElementById('searchAssignments');
    if (searchInput) {
      searchInput.addEventListener('input', (e) => {
        this.filterAssignments(e.target.value);
      });
    }

    // Grade level filter
    const gradeFilter = document.getElementById('gradeLevelFilter');
    if (gradeFilter) {
      gradeFilter.addEventListener('change', () => {
        this.applyFilters();
      });
    }

    // Assignment status filter
    const statusFilter = document.getElementById('assignmentStatusFilter');
    if (statusFilter) {
      statusFilter.addEventListener('change', () => {
        this.applyFilters();
      });
    }
  },

  async loadReferenceData() {
    try {
      // Load classes
      const classesRes = await window.API.apiCall('/academic/classes', 'GET');
      if (classesRes?.success) {
        this.state.classes = classesRes.data || [];
      }

      // Load teachers
      const teachersRes = await window.API.apiCall('/staff/teachers', 'GET');
      if (teachersRes?.success) {
        this.state.teachers = teachersRes.data || [];
      }

      // Load academic years
      const yearsRes = await window.API.apiCall('/academic/years', 'GET');
      if (yearsRes?.success) {
        this.state.academicYears = yearsRes.data || [];
      }

      // Populate dropdowns
      this.populateDropdowns();
    } catch (error) {
      console.error('Error loading reference data:', error);
    }
  },

  populateDropdowns() {
    // Populate class dropdown
    const classSelect = document.getElementById('assignmentClassId');
    if (classSelect) {
      classSelect.innerHTML = '<option value="">Select Class</option>' + 
        this.state.classes.map(cls => `<option value="${cls.id}">${cls.name}</option>`).join('');
    }

    // Populate teacher dropdown
    const teacherSelect = document.getElementById('assignmentTeacherId');
    if (teacherSelect) {
      teacherSelect.innerHTML = '<option value="">Select Teacher</option>' + 
        this.state.teachers.map(teacher => 
          `<option value="${teacher.id}">${teacher.first_name} ${teacher.last_name}</option>`
        ).join('');
    }

    // Populate academic year dropdown
    const yearSelect = document.getElementById('assignmentAcademicYearId');
    if (yearSelect) {
      yearSelect.innerHTML = '<option value="">Select Academic Year</option>' + 
        this.state.academicYears.map(year => `<option value="${year.id}">${year.name}</option>`).join('');
      
      // Set current academic year if available
      if (this.state.currentAcademicYear) {
        yearSelect.value = this.state.currentAcademicYear;
      }
    }
  },

  async loadAssignments() {
    try {
      const academicYearId = this.state.currentAcademicYear;
      const params = academicYearId ? { academic_year_id: academicYearId } : {};
      
      const res = await window.API.apiCall('/academic/class-teachers', 'GET', params);
      
      if (res?.success) {
        this.state.assignments = res.data || [];
        this.renderAssignmentsTable();
        this.updateStats();
      } else {
        this.showNotification('Failed to load assignments', 'error');
      }
    } catch (error) {
      console.error('Error loading assignments:', error);
      this.showNotification('Failed to load assignments', 'error');
    }
  },

  renderAssignmentsTable() {
    const tbody = document.querySelector('#assignmentsTable tbody');
    if (!tbody) return;

    if (this.state.assignments.length === 0) {
      tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">No assignments found</td></tr>`;
      return;
    }

    tbody.innerHTML = this.state.assignments.map((assignment, index) => {
      const statusBadge = assignment.teacher_id 
        ? '<span class="badge bg-success">Assigned</span>' 
        : '<span class="badge bg-warning">Unassigned</span>';

      return `
        <tr>
          <td>${index + 1}</td>
          <td><strong>${this.escapeHtml(assignment.class_name || '--')}</strong></td>
          <td>${this.escapeHtml(assignment.stream_name || '--')}</td>
          <td>${this.escapeHtml(assignment.teacher_name || 'Not assigned')}</td>
          <td>${this.escapeHtml(assignment.teacher_email || '--')}</td>
          <td>${assignment.assigned_date || '--'}</td>
          <td>${statusBadge}</td>
          <td>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary" onclick="AssignClassTeachersController.editAssignment(${assignment.id})" title="Edit">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-outline-danger" onclick="AssignClassTeachersController.removeAssignment(${assignment.id})" title="Remove">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  },

  updateStats() {
    document.getElementById('totalClassesCount').textContent = this.state.classes.length;
    document.getElementById('assignedTeachersCount').textContent = this.state.assignments.filter(a => a.teacher_id).length;
    document.getElementById('unassignedClassesCount').textContent = this.state.classes.length - this.state.assignments.filter(a => a.teacher_id).length;
    document.getElementById('totalTeachersCount').textContent = this.state.teachers.length;
  },

  showAssignModal() {
    const modal = document.getElementById('assignTeacherModal');
    if (modal) {
      const form = document.getElementById('assignTeacherForm');
      if (form) {
        form.reset();
        delete form.dataset.editId;
        
        // Set current academic year if available
        if (this.state.currentAcademicYear) {
          const yearSelect = document.getElementById('assignmentAcademicYearId');
          if (yearSelect) {
            yearSelect.value = this.state.currentAcademicYear;
          }
        }
      }
      new bootstrap.Modal(modal).show();
    }
  },

  async saveAssignment() {
    const form = document.getElementById('assignTeacherForm');
    if (!form) return;

    const data = {
      class_id: document.getElementById('assignmentClassId').value,
      stream_id: document.getElementById('assignmentStreamId').value || null,
      teacher_id: document.getElementById('assignmentTeacherId').value,
      academic_year_id: document.getElementById('assignmentAcademicYearId').value
    };

    if (!data.class_id || !data.teacher_id || !data.academic_year_id) {
      this.showNotification('Please fill all required fields', 'error');
      return;
    }

    try {
      const editId = form.dataset.editId;
      let res;
      if (editId) {
        res = await window.API.apiCall(`/academic/class-teachers/${editId}`, 'PUT', data);
      } else {
        res = await window.API.apiCall('/academic/class-teachers', 'POST', data);
      }

      if (res?.success) {
        this.showNotification(editId ? 'Assignment updated' : 'Assignment created', 'success');
        const modal = bootstrap.Modal.getInstance(document.getElementById('assignTeacherModal'));
        if (modal) modal.hide();
        form.reset();
        delete form.dataset.editId;
        await this.loadAssignments();
      } else {
        this.showNotification(res?.message || 'Operation failed', 'error');
      }
    } catch (error) {
      console.error('Error saving assignment:', error);
      this.showNotification('Failed to save assignment', 'error');
    }
  },

  async editAssignment(assignmentId) {
    try {
      const res = await window.API.apiCall(`/academic/class-teachers/${assignmentId}`, 'GET');
      if (res?.success && res.data) {
        const assignment = res.data;
        const form = document.getElementById('assignTeacherForm');
        if (form) {
          form.dataset.editId = assignmentId;
          document.getElementById('assignmentClassId').value = assignment.class_id || '';
          document.getElementById('assignmentStreamId').value = assignment.stream_id || '';
          document.getElementById('assignmentTeacherId').value = assignment.teacher_id || '';
          document.getElementById('assignmentAcademicYearId').value = assignment.academic_year_id || '';
          
          const modal = new bootstrap.Modal(document.getElementById('assignTeacherModal'));
          modal.show();
        }
      }
    } catch (error) {
      console.error('Error loading assignment for edit:', error);
    }
  },

  async removeAssignment(assignmentId) {
    if (!confirm('Are you sure you want to remove this class teacher assignment?')) return;
    try {
      const res = await window.API.apiCall(`/academic/class-teachers/${assignmentId}`, 'DELETE');
      if (res?.success) {
        this.showNotification('Assignment removed', 'success');
        await this.loadAssignments();
      } else {
        this.showNotification(res?.message || 'Failed to remove', 'error');
      }
    } catch (error) {
      console.error('Error removing assignment:', error);
      this.showNotification('Failed to remove assignment', 'error');
    }
  },

  filterAssignments(searchTerm) {
    const filtered = this.state.assignments.filter(assignment => {
      const searchLower = searchTerm.toLowerCase();
      return (assignment.class_name && assignment.class_name.toLowerCase().includes(searchLower)) ||
             (assignment.stream_name && assignment.stream_name.toLowerCase().includes(searchLower)) ||
             (assignment.teacher_name && assignment.teacher_name.toLowerCase().includes(searchLower));
    });
    
    this.renderFilteredAssignments(filtered);
  },

  applyFilters() {
    const gradeLevel = document.getElementById('gradeLevelFilter')?.value || '';
    const status = document.getElementById('assignmentStatusFilter')?.value || '';
    
    let filtered = [...this.state.assignments];
    
    if (gradeLevel) {
      filtered = filtered.filter(a => a.grade_level === gradeLevel);
    }
    
    if (status === 'assigned') {
      filtered = filtered.filter(a => a.teacher_id);
    } else if (status === 'unassigned') {
      filtered = filtered.filter(a => !a.teacher_id);
    }
    
    this.renderFilteredAssignments(filtered);
  },

  renderFilteredAssignments(filtered) {
    const tbody = document.querySelector('#assignmentsTable tbody');
    if (!tbody) return;

    if (filtered.length === 0) {
      tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">No assignments match your filters</td></tr>`;
      return;
    }

    tbody.innerHTML = filtered.map((assignment, index) => {
      const statusBadge = assignment.teacher_id 
        ? '<span class="badge bg-success">Assigned</span>' 
        : '<span class="badge bg-warning">Unassigned</span>';

      return `
        <tr>
          <td>${index + 1}</td>
          <td><strong>${this.escapeHtml(assignment.class_name || '--')}</strong></td>
          <td>${this.escapeHtml(assignment.stream_name || '--')}</td>
          <td>${this.escapeHtml(assignment.teacher_name || 'Not assigned')}</td>
          <td>${this.escapeHtml(assignment.teacher_email || '--')}</td>
          <td>${assignment.assigned_date || '--'}</td>
          <td>${statusBadge}</td>
          <td>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary" onclick="AssignClassTeachersController.editAssignment(${assignment.id})" title="Edit">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-outline-danger" onclick="AssignClassTeachersController.removeAssignment(${assignment.id})" title="Remove">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  },

  exportAssignments() {
    if (!this.state.assignments.length) return;
    
    const headers = ['#', 'Class', 'Stream', 'Class Teacher', 'Teacher Email', 'Assigned Date', 'Status'];
    const rows = this.state.assignments.map((assignment, i) => [
      i + 1,
      assignment.class_name || '--',
      assignment.stream_name || '--',
      assignment.teacher_name || 'Not assigned',
      assignment.teacher_email || '--',
      assignment.assigned_date || '--',
      assignment.teacher_id ? 'Assigned' : 'Unassigned'
    ]);
    
    if (window.PrintManager) {
      window.PrintManager.exportToCSV({
        headers,
        rows
      }, 'class_teacher_assignments');
    } else {
      // Fallback
      let csv = headers.join(',') + '\n' + 
        rows.map(r => r.map(v => '"' + (v || '') + '"').join(',')).join('\n');
      
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
      a.download = 'class_teacher_assignments.csv';
      a.click();
    }
  },

  async refresh() {
    await this.loadReferenceData();
    await this.loadAssignments();
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

document.addEventListener('DOMContentLoaded', () => AssignClassTeachersController.init());
