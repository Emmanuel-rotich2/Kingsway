/**
 * Intern Assigned Classes Controller
 * Page: intern_assigned_classes.php
 * Intern-specific view of classes assigned for internship
 * Year-aware through the shared AcademicYearFilter
 */
const InternAssignedClassesController = {
  state: {
    classes: [],
    currentAcademicYear: null,
    stats: {
      totalClasses: 0,
      observations: 0,
      hoursPerWeek: 0,
      mentorAssigned: false
    }
  },

  async init() {
    await window.AuthContext?.ready();
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    
    // Year: the shared filter (current year by default, kept in the URL),
    // which loads the classes now and on every change. Class attachments
    // are per year, so there is no term filter.
    const yearSelect = document.getElementById('academicYearSelect');
    const mounted = window.AcademicYearFilter && yearSelect
      ? await window.AcademicYearFilter.mount(yearSelect, {
          onChange: (yearId) => { this.state.currentAcademicYear = yearId; this.loadAssignedClasses(); },
        })
      : null;
    if (!mounted) await this.loadAssignedClasses();
  },

  async loadAssignedClasses() {
    try {
      const user = window.AuthContext?.getUser();
      if (!user || !user.id) {
        this.showNotification('User not authenticated', 'error');
        return;
      }

      const params = {
        intern_id: user.id
      };
      
      if (this.state.currentAcademicYear) {
        params.academic_year_id = this.state.currentAcademicYear;
      }
      

      this.state.classes = await window.API.academic.getInternClasses(params) || [];
      this.renderClassesTable();
      this.updateStats();
    } catch (error) {
      console.error('Error loading assigned classes:', error);
      this.showNotification('Failed to load assigned classes', 'error');
    }
  },

  renderClassesTable() {
    const tbody = document.querySelector('#assignedClassesTable tbody');
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
          <td>${this.escapeHtml(classData.subject_name || '--')}</td>
          <td>${this.escapeHtml(classData.teacher_name || '--')}</td>
          <td>${classData.periods_per_week || 0}</td>
          <td>${statusBadge}</td>
          <td>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary" data-kw-onclick="InternAssignedClassesController.viewSchedule(${classData.class_id})" title="View Schedule">
                <i class="bi bi-calendar3"></i>
              </button>
              <button class="btn btn-outline-info" data-kw-onclick="InternAssignedClassesController.viewObservations(${classData.class_id})" title="View Observations">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  },

  updateStats() {
    document.getElementById('totalClassesCount').textContent = this.state.classes.length;
    document.getElementById('observationsCount').textContent = this.state.classes.reduce((sum, c) => sum + (c.observations_count || 0), 0);
    document.getElementById('hoursPerWeekCount').textContent = this.state.classes.reduce((sum, c) => sum + (c.periods_per_week || 0), 0);
    
    // Check if mentor is assigned
    const hasMentor = this.state.classes.some(c => c.mentor_id);
    document.getElementById('mentorStatus').textContent = hasMentor ? 'Assigned' : 'Not Assigned';
    document.getElementById('mentorStatus').className = hasMentor ? 'text-info mb-0' : 'text-warning mb-0';
  },

  viewSchedule(classId) {
    // Navigate to intern schedule filtered by class
    window.location.href = (window.APP_BASE || '') + '/home.php?route=intern_schedule&class_id=' + classId;
  },

  viewObservations(classId) {
    // Navigate to observation schedule filtered by class
    window.location.href = (window.APP_BASE || '') + '/home.php?route=observation_schedule&class_id=' + classId;
  },

  exportClasses() {
    if (!this.state.classes.length) return;
    
    const headers = ['#', 'Class', 'Stream', 'Subject', 'Teacher', 'Periods/Week', 'Status'];
    const rows = this.state.classes.map((classData, i) => [
      i + 1,
      classData.class_name,
      classData.stream_name || '--',
      classData.subject_name || '--',
      classData.teacher_name || '--',
      classData.periods_per_week || 0,
      classData.status || 'Active'
    ]);
    
    if (window.PrintManager) {
      window.PrintManager.exportToCSV({
        headers,
        rows
      }, 'intern_assigned_classes');
    } else {
      // Fallback
      let csv = headers.join(',') + '\n' + 
        rows.map(r => r.map(v => '"' + (v || '') + '"').join(',')).join('\n');
      
      KingswayFileLifecycle.exportText(csv, 'intern_assigned_classes.csv', 'text/csv');
    }
  },

  async refresh() {
    await this.loadAssignedClasses();
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

document.addEventListener('DOMContentLoaded', () => InternAssignedClassesController.init());

window.InternAssignedClassesController = InternAssignedClassesController;
