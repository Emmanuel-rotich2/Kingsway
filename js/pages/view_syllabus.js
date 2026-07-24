/**
 * View Syllabus Controller
 * Page: view_syllabus.php
 * Read-only view of curriculum syllabus for interns
 * Integrates with AcademicContext for academic year awareness
 */
const ViewSyllabusController = {
  state: {
    syllabus: [],
    learningAreas: [],
    currentAcademicYear: null,
    stats: {
      totalLearningAreas: 0,
      totalStrands: 0,
      totalSubStrands: 0,
      totalCompetencies: 0
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
        console.log('AcademicContext changed in view_syllabus:', event, data);
        if (event === 'yearChanged' || event === 'initialized' || event === 'refreshed') {
          this.loadSyllabus();
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
    await this.loadSyllabus();
  },

  bindEvents() {
    // Grade level filter
    const gradeFilter = document.getElementById('gradeLevelFilter');
    if (gradeFilter) {
      gradeFilter.addEventListener('change', () => {
        this.applyFilters();
      });
    }

    // Learning area filter
    const areaFilter = document.getElementById('learningAreaFilter');
    if (areaFilter) {
      areaFilter.addEventListener('change', () => {
        this.applyFilters();
      });
    }

    // Search input
    const searchInput = document.getElementById('searchSyllabus');
    if (searchInput) {
      searchInput.addEventListener('input', (e) => {
        this.filterSyllabus(e.target.value);
      });
    }
  },

  async loadSyllabus() {
    try {
      const params = {};
      
      if (this.state.currentAcademicYear) {
        params.academic_year_id = this.state.currentAcademicYear;
      }

      const res = await window.API.apiCall('/academic/syllabus', 'GET', params);
      
      if (res?.success) {
        this.state.syllabus = res.data || [];
        this.renderSyllabusTable();
        this.updateStats();
        this.populateLearningAreaFilter();
      } else {
        this.showNotification('Failed to load syllabus', 'error');
      }
    } catch (error) {
      console.error('Error loading syllabus:', error);
      this.showNotification('Failed to load syllabus', 'error');
    }
  },

  populateLearningAreaFilter() {
    const learningAreas = new Set(this.state.syllabus.map(s => s.learning_area));
    const areaFilter = document.getElementById('learningAreaFilter');
    if (areaFilter) {
      areaFilter.innerHTML = '<option value="">All Learning Areas</option>' + 
        Array.from(learningAreas).map(area => `<option value="${area}">${area}</option>`).join('');
    }
  },

  renderSyllabusTable() {
    const tbody = document.querySelector('#syllabusTable tbody');
    if (!tbody) return;

    if (this.state.syllabus.length === 0) {
      tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">No syllabus entries found</td></tr>`;
      return;
    }

    tbody.innerHTML = this.state.syllabus.map((entry, index) => {
      return `
        <tr>
          <td>${index + 1}</td>
          <td><span class="badge bg-primary">${entry.grade_level || '--'}</span></td>
          <td><strong>${this.escapeHtml(entry.learning_area || '--')}</strong></td>
          <td>${this.escapeHtml(entry.strand || '--')}</td>
          <td>${this.escapeHtml(entry.sub_strand || '--')}</td>
          <td><small>${this.escapeHtml((entry.indicators || '').substring(0, 80))}${(entry.indicators || '').length > 80 ? '...' : ''}</small></td>
          <td><small>${this.escapeHtml((entry.assessment_criteria || '').substring(0, 80))}${(entry.assessment_criteria || '').length > 80 ? '...' : ''}</small></td>
          <td>
            <button class="btn btn-sm btn-outline-primary" onclick="ViewSyllabusController.viewDetails(${entry.id})" title="View Details">
              <i class="bi bi-eye"></i>
            </button>
          </td>
        </tr>
      `;
    }).join('');
  },

  updateStats() {
    const learningAreas = new Set(this.state.syllabus.map(s => s.learning_area)).size;
    const strands = new Set(this.state.syllabus.map(s => s.strand)).size;
    const subStrands = new Set(this.state.syllabus.filter(s => s.sub_strand).map(s => s.sub_strand)).size;
    const competencies = this.state.syllabus.filter(s => s.indicators).length;

    document.getElementById('totalLearningAreas').textContent = learningAreas;
    document.getElementById('totalStrands').textContent = strands;
    document.getElementById('totalSubStrands').textContent = subStrands;
    document.getElementById('totalCompetencies').textContent = competencies;
  },

  filterSyllabus(searchTerm) {
    const filtered = this.state.syllabus.filter(entry => {
      const searchLower = searchTerm.toLowerCase();
      return (entry.learning_area && entry.learning_area.toLowerCase().includes(searchLower)) ||
             (entry.strand && entry.strand.toLowerCase().includes(searchLower)) ||
             (entry.sub_strand && entry.sub_strand.toLowerCase().includes(searchLower)) ||
             (entry.indicators && entry.indicators.toLowerCase().includes(searchLower));
    });
    
    this.renderFilteredSyllabus(filtered);
  },

  applyFilters() {
    const gradeLevel = document.getElementById('gradeLevelFilter')?.value || '';
    const learningArea = document.getElementById('learningAreaFilter')?.value || '';
    
    let filtered = [...this.state.syllabus];
    
    if (gradeLevel) {
      filtered = filtered.filter(s => s.grade_level === gradeLevel);
    }
    
    if (learningArea) {
      filtered = filtered.filter(s => s.learning_area === learningArea);
    }
    
    this.renderFilteredSyllabus(filtered);
  },

  renderFilteredSyllabus(filtered) {
    const tbody = document.querySelector('#syllabusTable tbody');
    if (!tbody) return;

    if (filtered.length === 0) {
      tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">No syllabus entries match your filters</td></tr>`;
      return;
    }

    tbody.innerHTML = filtered.map((entry, index) => {
      return `
        <tr>
          <td>${index + 1}</td>
          <td><span class="badge bg-primary">${entry.grade_level || '--'}</span></td>
          <td><strong>${this.escapeHtml(entry.learning_area || '--')}</strong></td>
          <td>${this.escapeHtml(entry.strand || '--')}</td>
          <td>${this.escapeHtml(entry.sub_strand || '--')}</td>
          <td><small>${this.escapeHtml((entry.indicators || '').substring(0, 80))}${(entry.indicators || '').length > 80 ? '...' : ''}</small></td>
          <td><small>${this.escapeHtml((entry.assessment_criteria || '').substring(0, 80))}${(entry.assessment_criteria || '').length > 80 ? '...' : ''}</small></td>
          <td>
            <button class="btn btn-sm btn-outline-primary" onclick="ViewSyllabusController.viewDetails(${entry.id})" title="View Details">
              <i class="bi bi-eye"></i>
            </button>
          </td>
        </tr>
      `;
    }).join('');
  },

  viewDetails(entryId) {
    // Show detailed view of the syllabus entry
    const entry = this.state.syllabus.find(s => s.id === entryId);
    if (!entry) return;

    const details = `
      Grade Level: ${entry.grade_level || '--'}
      Learning Area: ${entry.learning_area || '--'}
      Strand: ${entry.strand || '--'}
      Sub-Strand: ${entry.sub_strand || '--'}
      Competency Indicators: ${entry.indicators || '--'}
      Assessment Criteria: ${entry.assessment_criteria || '--'}
    `;

    alert(details);
  },

  exportSyllabus() {
    if (!this.state.syllabus.length) return;
    
    const headers = ['#', 'Grade Level', 'Learning Area', 'Strand', 'Sub-Strand', 'Competency Indicators', 'Assessment Criteria'];
    const rows = this.state.syllabus.map((entry, i) => [
      i + 1,
      entry.grade_level || '--',
      entry.learning_area || '--',
      entry.strand || '--',
      entry.sub_strand || '--',
      entry.indicators || '--',
      entry.assessment_criteria || '--'
    ]);
    
    if (window.PrintManager) {
      window.PrintManager.exportToCSV({
        headers,
        rows
      }, 'curriculum_syllabus');
    } else {
      // Fallback
      let csv = headers.join(',') + '\n' + 
        rows.map(r => r.map(v => '"' + (v || '') + '"').join(',')).join('\n');
      
      KingswayFileLifecycle.exportText(csv, 'curriculum_syllabus.csv', 'text/csv');
    }
  },

  async refresh() {
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

document.addEventListener('DOMContentLoaded', () => ViewSyllabusController.init());
