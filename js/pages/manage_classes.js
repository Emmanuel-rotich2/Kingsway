/**
 * Manage Classes Controller
 * Page: manage_classes.php
 * Manages classes, streams, and class teacher assignments
 * Integrates with AcademicContext for academic year awareness
 */
const ManageClassesController = {
  state: {
    classes: [],
    streams: [],
    classTeachers: [],
    currentAcademicYear: null,
    currentTerm: null,
    stats: {
      totalClasses: 0,
      activeStreams: 0,
      studentsEnrolled: 0,
      teachersAssigned: 0
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
        console.log('AcademicContext changed in manage_classes:', event, data);
        if (event === 'yearChanged' || event === 'initialized' || event === 'refreshed') {
          this.loadClasses();
        }
      });
      
      // Ensure context is loaded
      if (!window.AcademicContext.isLoaded()) {
        await window.AcademicContext.init();
      }
      
      // Get current academic context
      this.state.currentAcademicYear = window.AcademicContext.getAcademicYearId();
      this.state.currentTerm = window.AcademicContext.getTermId();
    }
    
    this.bindEvents();
    await this.loadClasses();
  },

  bindEvents() {
    // Class form submission
    const classForm = document.getElementById('addClassForm');
    if (classForm) {
      classForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.saveClass();
      });
    }

    // Stream form submission
    const streamForm = document.getElementById('addStreamForm');
    if (streamForm) {
      streamForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.saveStream();
      });
    }

    // Class teacher form submission
    const teacherForm = document.getElementById('assignClassTeacherForm');
    if (teacherForm) {
      teacherForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.assignClassTeacher();
      });
    }

    // Tab navigation
    const tabs = document.querySelectorAll('#classesTabs button[data-bs-toggle="tab"]');
    tabs.forEach(tab => {
      tab.addEventListener('shown.bs.tab', (e) => {
        const target = e.target.getAttribute('data-bs-target');
        this.loadTabContent(target);
      });
    });
  },

  async loadClasses() {
    try {
      const academicYearId = this.state.currentAcademicYear;
      const params = academicYearId ? { academic_year_id: academicYearId } : {};
      
      const res = await window.API.apiCall('/academic/classes', 'GET', params);
      
      if (res?.success) {
        this.state.classes = res.data || [];
        this.renderClassesTable();
        this.updateStats();
      } else {
        this.showNotification('Failed to load classes', 'error');
      }
    } catch (error) {
      console.error('Error loading classes:', error);
      this.showNotification('Failed to load classes', 'error');
    }
  },

  async loadStreams() {
    try {
      const res = await window.API.apiCall('/academic/class-streams', 'GET');
      
      if (res?.success) {
        this.state.streams = res.data || [];
        this.renderStreamsTable();
        this.updateStats();
      } else {
        this.showNotification('Failed to load streams', 'error');
      }
    } catch (error) {
      console.error('Error loading streams:', error);
      this.showNotification('Failed to load streams', 'error');
    }
  },

  async loadClassTeachers() {
    try {
      const academicYearId = this.state.currentAcademicYear;
      const params = academicYearId ? { academic_year_id: academicYearId } : {};
      
      const res = await window.API.apiCall('/academic/class-teachers', 'GET', params);
      
      if (res?.success) {
        this.state.classTeachers = res.data || [];
        this.renderClassTeachersTable();
        this.updateStats();
      } else {
        this.showNotification('Failed to load class teachers', 'error');
      }
    } catch (error) {
      console.error('Error loading class teachers:', error);
      this.showNotification('Failed to load class teachers', 'error');
    }
  },

  loadTabContent(target) {
    switch (target) {
      case '#streams':
        this.loadStreams();
        break;
      case '#class-teachers':
        this.loadClassTeachers();
        break;
      case '#timetables':
        this.loadTimetables();
        break;
      default:
        this.loadClasses();
    }
  },

  async loadTimetables() {
    // Redirect to timetable page
    window.location.href = (window.APP_BASE || '') + '/home.php?route=manage_timetable';
  },

  renderClassesTable() {
    const tbody = document.querySelector('#classesTable tbody');
    if (!tbody) return;

    if (this.state.classes.length === 0) {
      tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">No classes found</td></tr>`;
      return;
    }

    tbody.innerHTML = this.state.classes.map((cls, index) => {
      const statusBadge = cls.status === 'active' 
        ? '<span class="badge bg-success">Active</span>' 
        : '<span class="badge bg-secondary">Inactive</span>';

      return `
        <tr>
          <td>${index + 1}</td>
          <td><strong>${this.escapeHtml(cls.name)}</strong></td>
          <td>${cls.level_name || '--'}</td>
          <td>${cls.capacity || '--'}</td>
          <td>${cls.current_students || 0}</td>
          <td>${statusBadge}</td>
          <td>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary" onclick="ManageClassesController.editClass(${cls.id})" title="Edit">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-outline-info" onclick="ManageClassesController.viewStreams(${cls.id})" title="View Streams">
                <i class="bi bi-diagram-3"></i>
              </button>
              <button class="btn btn-outline-danger" onclick="ManageClassesController.deleteClass(${cls.id})" title="Delete">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  },

  renderStreamsTable() {
    const tbody = document.querySelector('#streamsTable tbody');
    if (!tbody) return;

    if (this.state.streams.length === 0) {
      tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">No streams found</td></tr>`;
      return;
    }

    tbody.innerHTML = this.state.streams.map((stream, index) => {
      const statusBadge = stream.status === 'active' 
        ? '<span class="badge bg-success">Active</span>' 
        : '<span class="badge bg-secondary">Inactive</span>';

      return `
        <tr>
          <td>${index + 1}</td>
          <td><strong>${this.escapeHtml(stream.stream_name)}</strong></td>
          <td>${stream.class_name || '--'}</td>
          <td>${stream.capacity || '--'}</td>
          <td>${stream.current_students || 0}</td>
          <td>${statusBadge}</td>
          <td>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary" onclick="ManageClassesController.editStream(${stream.id})" title="Edit">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-outline-danger" onclick="ManageClassesController.deleteStream(${stream.id})" title="Delete">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  },

  renderClassTeachersTable() {
    const tbody = document.querySelector('#classTeachersTable tbody');
    if (!tbody) return;

    if (this.state.classTeachers.length === 0) {
      tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">No class teachers assigned</td></tr>`;
      return;
    }

    tbody.innerHTML = this.state.classTeachers.map((assignment, index) => {
      return `
        <tr>
          <td>${index + 1}</td>
          <td><strong>${this.escapeHtml(assignment.class_name)}</strong></td>
          <td>${assignment.stream_name || '--'}</td>
          <td>${assignment.teacher_name || '--'}</td>
          <td>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary" onclick="ManageClassesController.editClassTeacher(${assignment.id})" title="Edit">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-outline-danger" onclick="ManageClassesController.removeClassTeacher(${assignment.id})" title="Remove">
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
    document.getElementById('activeStreamsCount').textContent = this.state.streams.filter(s => s.status === 'active').length;
    
    const totalStudents = this.state.classes.reduce((sum, cls) => sum + (cls.current_students || 0), 0);
    document.getElementById('studentsEnrolledCount').textContent = totalStudents;
    
    document.getElementById('teachersAssignedCount').textContent = this.state.classTeachers.length;
  },

  showClassModal() {
    const modal = document.getElementById('addClassModal');
    if (modal) {
      const form = document.getElementById('addClassForm');
      if (form) {
        form.reset();
        delete form.dataset.editId;
      }
      new bootstrap.Modal(modal).show();
    }
  },

  showStreamModal() {
    const modal = document.getElementById('addStreamModal');
    if (modal) {
      const form = document.getElementById('addStreamForm');
      if (form) {
        form.reset();
        delete form.dataset.editId;
        // Populate class dropdown
        this.populateClassDropdown(form.querySelector('#streamClassId'));
      }
      new bootstrap.Modal(modal).show();
    }
  },

  showClassTeacherModal() {
    const modal = document.getElementById('assignClassTeacherModal');
    if (modal) {
      const form = document.getElementById('assignClassTeacherForm');
      if (form) {
        form.reset();
        delete form.dataset.editId;
        // Populate dropdowns
        this.populateClassDropdown(form.querySelector('#teacherClassId'));
        this.populateTeacherDropdown(form.querySelector('#teacherId'));
      }
      new bootstrap.Modal(modal).show();
    }
  },

  async populateClassDropdown(select) {
    if (!select) return;
    
    try {
      const res = await window.API.apiCall('/academic/classes', 'GET');
      if (res?.success) {
        select.innerHTML = '<option value="">Select Class</option>' + 
          res.data.map(cls => `<option value="${cls.id}">${cls.name}</option>`).join('');
      }
    } catch (error) {
      console.error('Error loading classes for dropdown:', error);
    }
  },

  async populateTeacherDropdown(select) {
    if (!select) return;
    
    try {
      const res = await window.API.apiCall('/staff/teachers', 'GET');
      if (res?.success) {
        select.innerHTML = '<option value="">Select Teacher</option>' + 
          res.data.map(teacher => `<option value="${teacher.id}">${teacher.first_name} ${teacher.last_name}</option>`).join('');
      }
    } catch (error) {
      console.error('Error loading teachers for dropdown:', error);
    }
  },

  async saveClass() {
    const form = document.getElementById('addClassForm');
    if (!form) return;

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    // Add academic year if available
    if (this.state.currentAcademicYear) {
      data.academic_year_id = this.state.currentAcademicYear;
    }

    try {
      const editId = form.dataset.editId;
      let res;
      if (editId) {
        res = await window.API.apiCall(`/academic/classes/${editId}`, 'PUT', data);
      } else {
        res = await window.API.apiCall('/academic/classes', 'POST', data);
      }

      if (res?.success) {
        this.showNotification(editId ? 'Class updated' : 'Class created', 'success');
        const modal = bootstrap.Modal.getInstance(document.getElementById('addClassModal'));
        if (modal) modal.hide();
        form.reset();
        delete form.dataset.editId;
        await this.loadClasses();
      } else {
        this.showNotification(res?.message || 'Operation failed', 'error');
      }
    } catch (error) {
      console.error('Error saving class:', error);
      this.showNotification('Failed to save class', 'error');
    }
  },

  async saveStream() {
    const form = document.getElementById('addStreamForm');
    if (!form) return;

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries);

    try {
      const editId = form.dataset.editId;
      let res;
      if (editId) {
        res = await window.API.apiCall(`/academic/class-streams/${editId}`, 'PUT', data);
      } else {
        res = await window.API.apiCall('/academic/class-streams', 'POST', data);
      }

      if (res?.success) {
        this.showNotification(editId ? 'Stream updated' : 'Stream created', 'success');
        const modal = bootstrap.Modal.getInstance(document.getElementById('addStreamModal'));
        if (modal) modal.hide();
        form.reset();
        delete form.dataset.editId;
        await this.loadStreams();
      } else {
        this.showNotification(res?.message || 'Operation failed', 'error');
      }
    } catch (error) {
      console.error('Error saving stream:', error);
      this.showNotification('Failed to save stream', 'error');
    }
  },

  async assignClassTeacher() {
    const form = document.getElementById('assignClassTeacherForm');
    if (!form) return;

    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    // Add academic year if available
    if (this.state.currentAcademicYear) {
      data.academic_year_id = this.state.currentAcademicYear;
    }

    try {
      const res = await window.API.apiCall('/academic/class-teachers', 'POST', data);

      if (res?.success) {
        this.showNotification('Class teacher assigned', 'success');
        const modal = bootstrap.Modal.getInstance(document.getElementById('assignClassTeacherModal'));
        if (modal) modal.hide();
        form.reset();
        await this.loadClassTeachers();
      } else {
        this.showNotification(res?.message || 'Operation failed', 'error');
      }
    } catch (error) {
      console.error('Error assigning class teacher:', error);
      this.showNotification('Failed to assign class teacher', 'error');
    }
  },

  async editClass(classId) {
    try {
      const res = await window.API.apiCall(`/academic/classes/${classId}`, 'GET');
      if (res?.success && res.data) {
        const cls = res.data;
        const form = document.getElementById('addClassForm');
        if (form) {
          form.dataset.editId = classId;
          const fields = ['name', 'level_id', 'capacity', 'room_number'];
          fields.forEach((field) => {
            const input = form.querySelector(`[name="${field}"]`);
            if (input && cls[field]) input.value = cls[field];
          });
          const modal = new bootstrap.Modal(document.getElementById('addClassModal'));
          modal.show();
        }
      }
    } catch (error) {
      console.error('Error loading class for edit:', error);
    }
  },

  async editStream(streamId) {
    try {
      const res = await window.API.apiCall(`/academic/class-streams/${streamId}`, 'GET');
      if (res?.success && res.data) {
        const stream = res.data;
        const form = document.getElementById('addStreamForm');
        if (form) {
          form.dataset.editId = streamId;
          const fields = ['stream_name', 'capacity', 'class_id'];
          fields.forEach((field) => {
            const input = form.querySelector(`[name="${field}"]`);
            if (input && stream[field]) input.value = stream[field];
          });
          const modal = new bootstrap.Modal(document.getElementById('addStreamModal'));
          modal.show();
        }
      }
    } catch (error) {
      console.error('Error loading stream for edit:', error);
    }
  },

  async editClassTeacher(assignmentId) {
    try {
      const res = await window.API.apiCall(`/academic/class-teachers/${assignmentId}`, 'GET');
      if (res?.success && res.data) {
        const assignment = res.data;
        const form = document.getElementById('assignClassTeacherForm');
        if (form) {
          form.dataset.editId = assignmentId;
          const fields = ['class_id', 'stream_id', 'teacher_id'];
          fields.forEach((field) => {
            const input = form.querySelector(`[name="${field}"]`);
            if (input && assignment[field]) input.value = assignment[field];
          });
          const modal = new bootstrap.Modal(document.getElementById('assignClassTeacherModal'));
          modal.show();
        }
      }
    } catch (error) {
      console.error('Error loading class teacher for edit:', error);
    }
  },

  async deleteClass(classId) {
    if (!confirm('Are you sure you want to delete this class? This cannot be undone.')) return;
    try {
      const res = await window.API.apiCall(`/academic/classes/${classId}`, 'DELETE');
      if (res?.success) {
        this.showNotification('Class deleted', 'success');
        await this.loadClasses();
      } else {
        this.showNotification(res?.message || 'Failed to delete', 'error');
      }
    } catch (error) {
      console.error('Error deleting class:', error);
      this.showNotification('Failed to delete class', 'error');
    }
  },

  async deleteStream(streamId) {
    if (!confirm('Are you sure you want to delete this stream? This cannot be undone.')) return;
    try {
      const res = await window.API.apiCall(`/academic/class-streams/${streamId}`, 'DELETE');
      if (res?.success) {
        this.showNotification('Stream deleted', 'success');
        await this.loadStreams();
      } else {
        this.showNotification(res?.message || 'Failed to delete', 'error');
      }
    } catch (error) {
      console.error('Error deleting stream:', error);
      this.showNotification('Failed to delete stream', 'error');
    }
  },

  async removeClassTeacher(assignmentId) {
    if (!confirm('Are you sure you want to remove this class teacher assignment?')) return;
    try {
      const res = await window.API.apiCall(`/academic/class-teachers/${assignmentId}`, 'DELETE');
      if (res?.success) {
        this.showNotification('Class teacher removed', 'success');
        await this.loadClassTeachers();
      } else {
        this.showNotification(res?.message || 'Failed to remove', 'error');
      }
    } catch (error) {
      console.error('Error removing class teacher:', error);
      this.showNotification('Failed to remove class teacher', 'error');
    }
  },

  viewStreams(classId) {
    // Switch to streams tab and filter by class
    const streamsTab = document.querySelector('#streams-tab');
    if (streamsTab) {
      streamsTab.click();
      // Store filter in state for filtering
      this.state.filterClassId = classId;
    }
  },

  exportClasses() {
    if (!this.state.classes.length) return;
    
    const headers = ['#', 'Class Name', 'Level', 'Capacity', 'Current Students', 'Status'];
    const rows = this.state.classes.map((cls, i) => [
      i + 1,
      cls.name,
      cls.level_name || '--',
      cls.capacity || '--',
      cls.current_students || 0,
      cls.status || '--'
    ]);
    
    let csv = headers.join(',') + '\n' + 
      rows.map(r => r.map(v => '"' + (v || '') + '"').join(',')).join('\n');
    
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
    a.download = 'classes.csv';
    a.click();
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

document.addEventListener('DOMContentLoaded', () => ManageClassesController.init());
