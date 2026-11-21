/**
 * My Subject Syllabus Controller
 * Page: my_subject_syllabus.php
 * Teacher-specific view of syllabus coverage for assigned subjects
 * Integrates with AcademicContext for academic year awareness
 */
const MySyllabusController = {
  state: {
    syllabus: [],
    subjects: [],
    currentAcademicYear: null,
    currentTerm: null,
    selectedSubject: null,
    stats: {
      totalStrands: 0,
      completed: 0,
      inProgress: 0,
      coveragePercent: 0
    }
  },

  async init() {
    await window.AuthContext?.ready();
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    
    
    this.bindEvents();
    await this.loadReferenceData();
    // The shared year/term selector (view only: it never changes the
    // school's current year). Current year and running term by default.
    await this.mountYearFilter();
  },

  async mountYearFilter() {
    const mounted = await window.AcademicYearFilter?.mount(document.getElementById('academicYearSelect'), {
      termSelect: document.getElementById('termSelect'),
      allTermsLabel: 'All terms',
      allTermsDefault: false,
      onChange: (yearId, year, termId) => {
        this.state.currentAcademicYear = yearId;
        this.state.currentTerm = termId;
        this.loadSyllabus();
      },
    });
    if (!mounted) await this.loadSyllabus();
  },

  bindEvents() {
    // Subject selector
    const subjectSelect = document.getElementById('subjectSelect');
    if (subjectSelect) {
      subjectSelect.addEventListener('change', (e) => {
        this.state.selectedSubject = e.target.value;
        this.loadSyllabus();
      });
    }


  },

  async loadReferenceData() {
    try {
      const user = window.AuthContext?.getUser();
      if (!user || !user.id) return;

      // Load teacher's assigned subjects
      const params = { teacher_id: user.id };
      this.state.subjects = await window.API.academic.getMySubjects(params) || [];
      const subjectSelect = document.getElementById('subjectSelect');
      if (subjectSelect) {
        subjectSelect.innerHTML = '<option value="">Select Subject</option>' + 
          this.state.subjects.map(subject => `<option value="${subject.subject_id}">${subject.subject_name}</option>`).join('');
      }

      // The year/term selects belong to AcademicYearFilter (mountYearFilter).
    } catch (error) {
      console.error('Error loading reference data:', error);
    }
  },

  async loadSyllabus() {
    try {
      const user = window.AuthContext?.getUser();
      if (!user || !user.id) {
        this.showNotification('User not authenticated', 'error');
        return;
      }

      const params = {
        teacher_id: user.id
      };
      
      if (this.state.selectedSubject) {
        params.subject_id = this.state.selectedSubject;
      }
      
      if (this.state.currentAcademicYear) {
        params.academic_year_id = this.state.currentAcademicYear;
      }
      
      if (this.state.currentTerm) {
        params.term_id = this.state.currentTerm;
      }

      this.state.syllabus = await window.API.academic.getMySyllabus(params) || [];
      this.renderSyllabusTable();
      this.updateStats();
    } catch (error) {
      console.error('Error loading syllabus:', error);
      this.showNotification('Failed to load syllabus', 'error');
    }
  },

  renderSyllabusTable() {
    const tbody = document.querySelector('#syllabusTable tbody');
    if (!tbody) return;

    if (this.state.syllabus.length === 0) {
      tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">No syllabus entries found</td></tr>`;
      return;
    }

    tbody.innerHTML = this.state.syllabus.map((entry, index) => {
      const statusBadge = this.getStatusBadge(entry.status);

      return `
        <tr>
          <td>${index + 1}</td>
          <td><strong>${this.escapeHtml(entry.strand || '--')}</strong></td>
          <td>${this.escapeHtml(entry.sub_strand || '--')}</td>
          <td><small>${this.escapeHtml((entry.indicators || '').substring(0, 80))}${(entry.indicators || '').length > 80 ? '...' : ''}</small></td>
          <td><small>${this.escapeHtml((entry.assessment_criteria || '').substring(0, 80))}${(entry.assessment_criteria || '').length > 80 ? '...' : ''}</small></td>
          <td>${statusBadge}</td>
          <td>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary" data-kw-onclick="MySyllabusController.viewDetails(${entry.id})" title="View Details">
                <i class="bi bi-eye"></i>
              </button>
              <button class="btn btn-outline-success" data-kw-onclick="MySyllabusController.markComplete(${entry.id})" title="Mark Complete" data-year-write>
                <i class="bi bi-check-circle"></i>
              </button>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  },

  getStatusBadge(status) {
    switch (status) {
      case 'completed':
        return '<span class="badge bg-success">Completed</span>';
      case 'in_progress':
        return '<span class="badge bg-warning">In Progress</span>';
      case 'not_started':
        return '<span class="badge bg-secondary">Not Started</span>';
      default:
        return '<span class="badge bg-secondary">--</span>';
    }
  },

  updateStats() {
    document.getElementById('totalStrandsCount').textContent = this.state.syllabus.length;
    document.getElementById('completedCount').textContent = this.state.syllabus.filter(s => s.status === 'completed').length;
    document.getElementById('inProgressCount').textContent = this.state.syllabus.filter(s => s.status === 'in_progress').length;
    
    const coverage = this.state.syllabus.length > 0 
      ? Math.round((this.state.syllabus.filter(s => s.status === 'completed').length / this.state.syllabus.length) * 100)
      : 0;
    document.getElementById('coveragePercent').textContent = coverage + '%';
  },

  async viewDetails(entryId) {
    // Show detailed view of the syllabus entry
    const entry = this.state.syllabus.find(s => s.id === entryId);
    if (!entry) return;

    const details = `
      Strand: ${entry.strand || '--'}
      Sub-Strand: ${entry.sub_strand || '--'}
      Competency Indicators: ${entry.indicators || '--'}
      Assessment Criteria: ${entry.assessment_criteria || '--'}
      Status: ${entry.status || '--'}
    `;

    await window.infoDialog('Notice', details);
  },

  async markComplete(entryId) {
    const entry = this.state.syllabus.find(s => s.id === entryId);
    if (!entry) return;
    if (!(await window.confirmAction('Confirm', 'Mark this syllabus entry as complete?'))) return;

    entry.status = 'completed';
    this.renderSyllabusTable();
    this.updateStats();
    this.showNotification('Syllabus entry marked as complete', 'success');
  },

  exportSyllabus() {
    if (!this.state.syllabus.length) return;
    
    const headers = ['#', 'Strand', 'Sub-Strand', 'Competency Indicators', 'Assessment Criteria', 'Status'];
    const rows = this.state.syllabus.map((entry, i) => [
      i + 1,
      entry.strand || '--',
      entry.sub_strand || '--',
      entry.indicators || '--',
      entry.assessment_criteria || '--',
      entry.status || '--'
    ]);
    
    if (window.PrintManager) {
      window.PrintManager.exportToCSV({
        headers,
        rows
      }, 'my_syllabus');
    } else {
      // Fallback
      let csv = headers.join(',') + '\n' + 
        rows.map(r => r.map(v => '"' + (v || '') + '"').join(',')).join('\n');
      
      KingswayFileLifecycle.exportText(csv, 'my_syllabus.csv', 'text/csv');
    }
  },

  async refresh() {
    await this.loadReferenceData();
    await this.loadSyllabus();
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

document.addEventListener('DOMContentLoaded', () => MySyllabusController.init());

window.MySyllabusController = MySyllabusController;
