/**
 * My Subjects Overview Controller
 * Page: my_subjects_overview.php
 * Teacher-specific view of assigned subjects
 * Integrates with AcademicContext for academic year awareness
 */
const MySubjectsController = {
  state: {
    subjects: [],
    currentAcademicYear: null,
    currentTerm: null,
    stats: {
      totalSubjects: 0,
      classesTeaching: 0,
      lessonsPerWeek: 0,
      pendingPlans: 0
    }
  },

  async init() {
    await window.AuthContext?.ready();
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    
    
    this.bindEvents();
    // The shared year/term selector (view only: it never changes the
    // school's current year). Current year and running term by default.
    await this.mountYearFilter();
  },

  bindEvents() {

  },

  async mountYearFilter() {
    const mounted = await window.AcademicYearFilter?.mount(document.getElementById('academicYearSelect'), {
      termSelect: document.getElementById('termSelect'),
      allTermsLabel: 'All terms',
      allTermsDefault: false,
      onChange: (yearId, year, termId) => {
        this.state.currentAcademicYear = yearId;
        this.state.currentTerm = termId;
        this.loadMySubjects();
      },
    });
    if (!mounted) await this.loadMySubjects();
  },


  async loadMySubjects() {
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

      this.state.subjects = await window.API.academic.getMySubjects(params) || [];
      this.renderSubjectsTable();
      this.updateStats();
    } catch (error) {
      console.error('Error loading my subjects:', error);
      this.showNotification('Failed to load your subjects', 'error');
    }
  },

  renderSubjectsTable() {
    const tbody = document.querySelector('#mySubjectsTable tbody');
    if (!tbody) return;

    if (this.state.subjects.length === 0) {
      tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">No subjects assigned to you</td></tr>`;
      return;
    }

    tbody.innerHTML = this.state.subjects.map((subject, index) => {
      const schemeStatusBadge = this.getSchemeStatusBadge(subject.scheme_status);
      const lessonPlansBadge = this.getLessonPlansBadge(subject.lesson_plans_count, subject.required_plans);
      const statusBadge = subject.status === 'active' 
        ? '<span class="badge bg-success">Active</span>' 
        : '<span class="badge bg-secondary">Inactive</span>';

      return `
        <tr>
          <td>${index + 1}</td>
          <td><strong>${this.escapeHtml(subject.subject_name)}</strong></td>
          <td>${subject.classes_count || 0} classes</td>
          <td>${subject.lessons_per_week || 0}</td>
          <td>${schemeStatusBadge}</td>
          <td>${lessonPlansBadge}</td>
          <td>${statusBadge}</td>
          <td>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary" data-kw-onclick="MySubjectsController.viewSubject(${subject.id})" title="View Details">
                <i class="bi bi-eye"></i>
              </button>
              <button class="btn btn-outline-info" data-kw-onclick="MySubjectsController.viewSyllabus(${subject.id})" title="View Syllabus">
                <i class="bi bi-journal-text"></i>
              </button>
              <button class="btn btn-outline-success" data-kw-onclick="MySubjectsController.manageSchemes(${subject.id})" title="Manage Schemes">
                <i class="bi bi-file-earmark-text"></i>
              </button>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  },

  getSchemeStatusBadge(status) {
    switch (status) {
      case 'approved':
        return '<span class="badge bg-success">Approved</span>';
      case 'submitted':
        return '<span class="badge bg-info">Submitted</span>';
      case 'draft':
        return '<span class="badge bg-warning">Draft</span>';
      default:
        return '<span class="badge bg-secondary">Not Started</span>';
    }
  },

  getLessonPlansBadge(current, required) {
    if (!required) return '<span class="badge bg-secondary">N/A</span>';
    
    const percentage = Math.round((current / required) * 100);
    
    if (percentage >= 100) {
      return '<span class="badge bg-success">Complete</span>';
    } else if (percentage >= 50) {
      return `<span class="badge bg-warning">${percentage}%</span>`;
    } else {
      return `<span class="badge bg-danger">${percentage}%</span>`;
    }
  },

  updateStats() {
    document.getElementById('totalSubjectsCount').textContent = this.state.subjects.length;
    document.getElementById('classesTeachingCount').textContent = this.state.subjects.reduce((sum, s) => sum + (s.classes_count || 0), 0);
    document.getElementById('lessonsPerWeekCount').textContent = this.state.subjects.reduce((sum, s) => sum + (s.lessons_per_week || 0), 0);
    document.getElementById('pendingPlansCount').textContent = this.state.subjects.filter(s => s.scheme_status !== 'approved').length;
  },

  viewSubject(subjectId) {
    // Navigate to subject detail page
    window.location.href = (window.APP_BASE || '') + '/home.php?route=manage_subjects&id=' + subjectId;
  },

  viewSyllabus(subjectId) {
    // Navigate to syllabus view
    window.location.href = (window.APP_BASE || '') + '/home.php?route=my_subject_syllabus&subject_id=' + subjectId;
  },

  manageSchemes(subjectId) {
    // Navigate to schemes of work management
    window.location.href = (window.APP_BASE || '') + '/home.php?route=schemes_of_work&subject_id=' + subjectId;
  },

  exportSubjects() {
    if (!this.state.subjects.length) return;
    
    const headers = ['#', 'Subject', 'Classes', 'Lessons/Week', 'Scheme Status', 'Lesson Plans', 'Status'];
    const rows = this.state.subjects.map((subject, i) => [
      i + 1,
      subject.subject_name,
      subject.classes_count || 0,
      subject.lessons_per_week || 0,
      subject.scheme_status || 'Not Started',
      `${subject.lesson_plans_count || 0}/${subject.required_plans || 0}`,
      subject.status || 'Active'
    ]);
    
    if (window.PrintManager) {
      window.PrintManager.exportToCSV({
        headers,
        rows
      }, 'my_subjects');
    } else {
      // Fallback
      let csv = headers.join(',') + '\n' + 
        rows.map(r => r.map(v => '"' + (v || '') + '"').join(',')).join('\n');
      
      KingswayFileLifecycle.exportText(csv, 'my_subjects.csv', 'text/csv');
    }
  },

  async refresh() {
    await this.loadMySubjects();
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

document.addEventListener('DOMContentLoaded', () => MySubjectsController.init());

window.MySubjectsController = MySubjectsController;
