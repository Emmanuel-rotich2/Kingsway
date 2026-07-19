/**
 * My Classes Taught Controller
 * Page: my_classes_taught.php
 * Teacher-specific view of classes they teach
 * Integrates with AcademicContext for academic year awareness
 */
const MyClassesController = {
  state: {
    classes: [],
    currentAcademicYear: null,
    currentTerm: null,
    stats: {
      totalClasses: 0,
      totalStudents: 0,
      subjectsTeaching: 0,
      lessonsPerWeek: 0
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
        console.log('AcademicContext changed in my_classes_taught:', event, data);
        if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
          this.loadMyClasses();
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
    await this.loadAcademicYears();
    await this.loadMyClasses();
  },

  bindEvents() {
    // Academic year selector
    const yearSelect = document.getElementById('academicYearSelect');
    if (yearSelect) {
      yearSelect.addEventListener('change', async (e) => {
        this.state.currentAcademicYear = e.target.value;
        if (window.AcademicContext) {
          await window.AcademicContext.setCurrentAcademicYear(e.target.value);
        }
        await this.loadMyClasses();
      });
    }

    // Term selector
    const termSelect = document.getElementById('termSelect');
    if (termSelect) {
      termSelect.addEventListener('change', async (e) => {
        this.state.currentTerm = e.target.value;
        await this.loadMyClasses();
      });
    }
  },

  async loadAcademicYears() {
    try {
      const res = await window.API.apiCall('/academic/years', 'GET');
      if (res?.success) {
        const years = res.data || [];
        const yearSelect = document.getElementById('academicYearSelect');
        if (yearSelect) {
          yearSelect.innerHTML = '<option value="">Select Academic Year</option>' + 
            years.map(year => `<option value="${year.id}">${year.name}</option>`).join('');
          
          // Set current academic year if available
          if (this.state.currentAcademicYear) {
            yearSelect.value = this.state.currentAcademicYear;
          }
        }
      }
    } catch (error) {
      console.error('Error loading academic years:', error);
    }
  },

  async loadMyClasses() {
    try {
      const user = window.AuthContext?.getUser();
      if (!user || !user.id) {
        this.showNotification('User not authenticated', 'error');
        return;
      }

      const params = {
        teacher_id: user.id
      };
      
      if (this.state.currentAcademicYear) {
        params.academic_year_id = this.state.currentAcademicYear;
      }
      
      if (this.state.currentTerm) {
        params.term_id = this.state.currentTerm;
      }

      const res = await window.API.apiCall('/academic/my-classes', 'GET', params);
      
      if (res?.success) {
        this.state.classes = res.data || [];
        this.renderClassesTable();
        this.updateStats();
      } else {
        this.showNotification('Failed to load your classes', 'error');
      }
    } catch (error) {
      console.error('Error loading my classes:', error);
      this.showNotification('Failed to load your classes', 'error');
    }
  },

  renderClassesTable() {
    const tbody = document.querySelector('#myClassesTable tbody');
    if (!tbody) return;

    if (this.state.classes.length === 0) {
      tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">No classes assigned to you</td></tr>`;
      return;
    }

    tbody.innerHTML = this.state.classes.map((classData, index) => {
      const statusBadge = classData.status === 'active' 
        ? '<span class="badge bg-success">Active</span>' 
        : '<span class="badge bg-secondary">Inactive</span>';

      return `
        <tr>
          <td>${index + 1}</td>
          <td><strong>${this.escapeHtml(classData.class_name)}</strong></td>
          <td>${this.escapeHtml(classData.stream_name || '--')}</td>
          <td>${classData.student_count || 0}</td>
          <td>${this.escapeHtml(classData.subject_name || '--')}</td>
          <td>${classData.lessons_per_week || 0}</td>
          <td>${this.escapeHtml(classData.class_teacher_name || '--')}</td>
          <td>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary" onclick="MyClassesController.viewClassDetails(${classData.class_id})" title="View Details">
                <i class="bi bi-eye"></i>
              </button>
              <button class="btn btn-outline-info" onclick="MyClassesController.viewStudents(${classData.class_id})" title="View Students">
                <i class="bi bi-people"></i>
              </button>
              <button class="btn btn-outline-success" onclick="MyClassesController.viewTimetable(${classData.class_id})" title="View Timetable">
                <i class="bi bi-calendar3"></i>
              </button>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  },

  updateStats() {
    document.getElementById('totalClassesCount').textContent = this.state.classes.length;
    document.getElementById('totalStudentsCount').textContent = this.state.classes.reduce((sum, c) => sum + (c.student_count || 0), 0);
    document.getElementById('subjectsTeachingCount').textContent = new Set(this.state.classes.map(c => c.subject_id)).size;
    document.getElementById('lessonsPerWeekCount').textContent = this.state.classes.reduce((sum, c) => sum + (c.lessons_per_week || 0), 0);
  },

  viewClassDetails(classId) {
    // Navigate to class detail page
    window.location.href = (window.APP_BASE || '') + '/home.php?route=manage_classes&id=' + classId;
  },

  viewStudents(classId) {
    // Navigate to students list filtered by class
    window.location.href = (window.APP_BASE || '') + '/home.php?route=all_students&class_id=' + classId;
  },

  viewTimetable(classId) {
    // Navigate to timetable page filtered by class
    window.location.href = (window.APP_BASE || '') + '/home.php?route=manage_timetable&class_id=' + classId;
  },

  exportClasses() {
    if (!this.state.classes.length) return;
    
    const headers = ['#', 'Class', 'Stream', 'Students', 'Subject', 'Lessons/Week', 'Class Teacher', 'Status'];
    const rows = this.state.classes.map((classData, i) => [
      i + 1,
      classData.class_name,
      classData.stream_name || '--',
      classData.student_count || 0,
      classData.subject_name || '--',
      classData.lessons_per_week || 0,
      classData.class_teacher_name || '--',
      classData.status || 'Active'
    ]);
    
    if (window.PrintManager) {
      window.PrintManager.exportToCSV({
        headers,
        rows
      }, 'my_classes');
    } else {
      // Fallback
      let csv = headers.join(',') + '\n' + 
        rows.map(r => r.map(v => '"' + (v || '') + '"').join(',')).join('\n');
      
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
      a.download = 'my_classes.csv';
      a.click();
    }
  },

  async refresh() {
    await this.loadAcademicYears();
    await this.loadMyClasses();
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

document.addEventListener('DOMContentLoaded', () => MyClassesController.init());
