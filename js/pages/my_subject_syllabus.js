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
    if (!window.AuthContext?.isAuthenticated()) {
      window.location.href = (window.APP_BASE || "") + "/index.php";
      return;
    }
    
    // Initialize Academic Context if available
    if (window.AcademicContext) {
      // Subscribe to context changes
      window.AcademicContext.subscribe((context, event, data) => {
        console.log('AcademicContext changed in my_subject_syllabus:', event, data);
        if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
          this.loadSyllabus();
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
    await this.loadReferenceData();
    await this.loadSyllabus();
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

    // Academic year selector
    const yearSelect = document.getElementById('academicYearSelect');
    if (yearSelect) {
      yearSelect.addEventListener('change', async (e) => {
        this.state.currentAcademicYear = e.target.value;
        if (window.AcademicContext) {
          await window.AcademicContext.setCurrentAcademicYear(e.target.value);
        }
        await this.loadSyllabus();
      });
    }

    // Term selector
    const termSelect = document.getElementById('termSelect');
    if (termSelect) {
      termSelect.addEventListener('change', async (e) => {
        this.state.currentTerm = e.target.value;
        await this.loadSyllabus();
      });
    }
  },

  async loadReferenceData() {
    try {
      const user = window.AuthContext?.getUser();
      if (!user || !user.id) return;

      // Load teacher's assigned subjects
      const params = { teacher_id: user.id };
      const res = await window.API.apiCall('/academic/my-subjects', 'GET', params);
      
      if (res?.success) {
        this.state.subjects = res.data || [];
        const subjectSelect = document.getElementById('subjectSelect');
        if (subjectSelect) {
          subjectSelect.innerHTML = '<option value="">Select Subject</option>' + 
            this.state.subjects.map(subject => `<option value="${subject.subject_id}">${subject.subject_name}</option>`).join('');
        }
      }

      // Load academic years
      const yearsRes = await window.API.apiCall('/academic/years', 'GET');
      if (yearsRes?.success) {
        const years = yearsRes.data || [];
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

      const res = await window.API.apiCall('/academic/my-syllabus', 'GET', params);
      
      if (res?.success) {
        this.state.syllabus = res.data || [];
        this.renderSyllabusTable();
        this.updateStats();
      } else {
        this.showNotification('Failed to load syllabus', 'error');
      }
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
              <button class="btn btn-outline-primary" onclick="MySyllabusController.viewDetails(${entry.id})" title="View Details">
                <i class="bi bi-eye"></i>
              </button>
              <button class="btn btn-outline-success" onclick="MySyllabusController.markComplete(${entry.id})" title="Mark Complete">
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

  viewDetails(entryId) {
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

    alert(details);
  },

  async markComplete(entryId) {
    if (!confirm('Mark this syllabus entry as complete?')) return;
    
    try {
      const res = await window.API.apiCall(`/academic/syllabus/${entryId}`, 'PUT', { status: 'completed' });
      
      if (res?.success) {
        this.showNotification('Syllabus entry marked as complete', 'success');
        await this.loadSyllabus();
      } else {
        this.showNotification(res?.message || 'Failed to update', 'error');
      }
    } catch (error) {
      console.error('Error marking complete:', error);
      this.showNotification('Failed to update', 'error');
    }
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
      
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
      a.download = 'my_syllabus.csv';
      a.click();
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
