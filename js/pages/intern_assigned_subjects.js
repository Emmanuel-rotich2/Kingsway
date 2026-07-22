/**
 * Intern Assigned Subjects Controller
 * Page: intern_assigned_subjects.php
 * Intern-specific view of subjects assigned for internship
 * Integrates with AcademicContext for academic year awareness
 */
const InternAssignedSubjectsController = {
  state: {
    subjects: [],
    currentAcademicYear: null,
    currentTerm: null,
    stats: {
      totalSubjects: 0,
      classes: 0,
      hoursPerWeek: 0,
      syllabusProgress: 0
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
        console.log('AcademicContext changed in intern_assigned_subjects:', event, data);
        if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
          this.loadAssignedSubjects();
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
    await this.loadAssignedSubjects();
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
        await this.loadAssignedSubjects();
      });
    }

    // Term selector
    const termSelect = document.getElementById('termSelect');
    if (termSelect) {
      termSelect.addEventListener('change', async (e) => {
        this.state.currentTerm = e.target.value;
        await this.loadAssignedSubjects();
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

  async loadAssignedSubjects() {
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
      
      if (this.state.currentTerm) {
        params.term_id = this.state.currentTerm;
      }

      const res = await window.API.apiCall('/academic/intern-subjects', 'GET', params);
      
      if (res?.success) {
        this.state.subjects = res.data || [];
        this.renderSubjectsTable();
        this.updateStats();
      } else {
        this.showNotification('Failed to load assigned subjects', 'error');
      }
    } catch (error) {
      console.error('Error loading assigned subjects:', error);
      this.showNotification('Failed to load assigned subjects', 'error');
    }
  },

  renderSubjectsTable() {
    const tbody = document.querySelector('#assignedSubjectsTable tbody');
    if (!tbody) return;

    if (this.state.subjects.length === 0) {
      tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">No subjects assigned to you</td></tr>`;
      return;
    }

    tbody.innerHTML = this.state.subjects.map((subject, index) => {
      const statusBadge = subject.status === 'active' 
        ? '<span class="badge bg-success">Active</span>' 
        : '<span class="badge bg-secondary">Inactive</span>';

      return `
        <tr>
          <td>${index + 1}</td>
          <td><strong>${this.escapeHtml(subject.subject_name)}</strong></td>
          <td>${this.escapeHtml(subject.learning_area || '--')}</td>
          <td>${subject.classes_count || 0}</td>
          <td>${this.escapeHtml(subject.teacher_name || '--')}</td>
          <td>${subject.periods_per_week || 0}</td>
          <td>${statusBadge}</td>
          <td>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary" onclick="InternAssignedSubjectsController.viewSyllabus(${subject.subject_id})" title="View Syllabus">
                <i class="bi bi-journal-text"></i>
              </button>
              <button class="btn btn-outline-info" onclick="InternAssignedSubjectsController.viewSchedule(${subject.subject_id})" title="View Schedule">
                <i class="bi bi-calendar3"></i>
              </button>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  },

  updateStats() {
    document.getElementById('totalSubjectsCount').textContent = this.state.subjects.length;
    document.getElementById('classesCount').textContent = this.state.subjects.reduce((sum, s) => sum + (s.classes_count || 0), 0);
    document.getElementById('hoursPerWeekCount').textContent = this.state.subjects.reduce((sum, s) => sum + (s.periods_per_week || 0), 0);
    
    // Calculate syllabus progress
    const totalStrands = this.state.subjects.reduce((sum, s) => sum + (s.total_strands || 0), 0);
    const completedStrands = this.state.subjects.reduce((sum, s) => sum + (s.completed_strands || 0), 0);
    const progress = totalStrands > 0 ? Math.round((completedStrands / totalStrands) * 100) : 0;
    document.getElementById('syllabusProgress').textContent = progress + '%';
  },

  viewSyllabus(subjectId) {
    // Navigate to syllabus view
    window.location.href = (window.APP_BASE || '') + '/home.php?route=view_syllabus&subject_id=' + subjectId;
  },

  viewSchedule(subjectId) {
    // Navigate to intern schedule filtered by subject
    window.location.href = (window.APP_BASE || '') + '/home.php?route=intern_schedule&subject_id=' + subjectId;
  },

  exportSubjects() {
    if (!this.state.subjects.length) return;
    
    const headers = ['#', 'Subject', 'Learning Area', 'Classes', 'Teacher', 'Periods/Week', 'Status'];
    const rows = this.state.subjects.map((subject, i) => [
      i + 1,
      subject.subject_name,
      subject.learning_area || '--',
      subject.classes_count || 0,
      subject.teacher_name || '--',
      subject.periods_per_week || 0,
      subject.status || 'Active'
    ]);
    
    if (window.PrintManager) {
      window.PrintManager.exportToCSV({
        headers,
        rows
      }, 'intern_assigned_subjects');
    } else {
      // Fallback
      let csv = headers.join(',') + '\n' + 
        rows.map(r => r.map(v => '"' + (v || '') + '"').join(',')).join('\n');
      
      KingswayFileLifecycle.exportText(csv, 'intern_assigned_subjects.csv', 'text/csv');
    }
  },

  async refresh() {
    await this.loadAcademicYears();
    await this.loadAssignedSubjects();
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

document.addEventListener('DOMContentLoaded', () => InternAssignedSubjectsController.init());
