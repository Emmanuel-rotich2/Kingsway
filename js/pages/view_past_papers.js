/**
 * View Past Papers Controller
 * Page: view_past_papers.php
 * Intern-specific read-only view of past exam papers
 * Integrates with AcademicContext for academic year awareness
 */
const ViewPastPapersController = {
  state: {
    papers: [],
    subjects: [],
    years: [],
    currentAcademicYear: null,
    currentTerm: null,
    stats: {
      total: 0,
      midterm: 0,
      endterm: 0,
      mock: 0
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
        console.log('AcademicContext changed in view_past_papers:', event, data);
        if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
          this.loadPapers();
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
    await this.loadSubjects();
    await this.loadYears();
    await this.loadPapers();
  },

  bindEvents() {
    // Search input with debouncing
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
      let timeout;
      searchInput.addEventListener('input', (e) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => this.loadPapers(), 300);
      });
    }

    // Subject filter
    const subjectFilter = document.getElementById('subjectFilter');
    if (subjectFilter) {
      subjectFilter.addEventListener('change', () => this.loadPapers());
    }

    // Year filter
    const yearFilter = document.getElementById('yearFilter');
    if (yearFilter) {
      yearFilter.addEventListener('change', () => this.loadPapers());
    }

    // Type filter
    const typeFilter = document.getElementById('typeFilter');
    if (typeFilter) {
      typeFilter.addEventListener('change', () => this.loadPapers());
    }
  },

  async loadSubjects() {
    try {
      const res = await window.API.apiCall('/academic/learning-areas/list', 'GET');
      if (res?.success) {
        this.state.subjects = res.data || [];
        const subjectSelect = document.getElementById('subjectFilter');
        if (subjectSelect) {
          subjectSelect.innerHTML = '<option value="">All Subjects</option>' + 
            this.state.subjects.map(subject => `<option value="${subject.id}">${subject.name}</option>`).join('');
        }
      }
    } catch (error) {
      console.error('Error loading subjects:', error);
    }
  },

  async loadYears() {
    try {
      const res = await window.API.apiCall('/academic/years', 'GET');
      if (res?.success) {
        this.state.years = res.data || [];
        const yearSelect = document.getElementById('yearFilter');
        if (yearSelect) {
          yearSelect.innerHTML = '<option value="">All Years</option>' + 
            this.state.years.map(year => `<option value="${year.id}">${year.year_code || year.year_name}</option>`).join('');
        }
      }
    } catch (error) {
      console.error('Error loading years:', error);
    }
  },

  async loadPapers() {
    try {
      const params = this.buildParams();
      const res = await window.API.apiCall('/academic/resources?type=past_paper' + params, 'GET');
      
      if (res?.success) {
        this.state.papers = res.data || [];
        this.renderPapersTable();
        this.updateStats();
      } else {
        this.showNotification('Failed to load past papers', 'error');
      }
    } catch (error) {
      console.error('Error loading past papers:', error);
      this.showNotification('Failed to load past papers', 'error');
    }
  },

  buildParams() {
    const search = document.getElementById('searchInput')?.value.trim() || '';
    const subject = document.getElementById('subjectFilter')?.value || '';
    const year = document.getElementById('yearFilter')?.value || '';
    const type = document.getElementById('typeFilter')?.value || '';
    
    const parts = [];
    if (search) parts.push('search=' + encodeURIComponent(search));
    if (subject) parts.push('subject=' + encodeURIComponent(subject));
    if (year) parts.push('year=' + encodeURIComponent(year));
    if (type) parts.push('exam_type=' + encodeURIComponent(type));
    
    return parts.length ? '&' + parts.join('&') : '';
  },

  renderPapersTable() {
    const tbody = document.querySelector('#papersTable tbody');
    if (!tbody) return;

    if (this.state.papers.length === 0) {
      tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted py-4">No past papers found.</td></tr>`;
      return;
    }

    tbody.innerHTML = this.state.papers.map((paper, index) => {
      const date = paper.created_at ? new Date(paper.created_at).toLocaleDateString() : '—';
      const typeBadge = this.getTypeBadge(paper.exam_type || paper.type || '—');

      return `
        <tr>
          <td>${index + 1}</td>
          <td><strong>${this.escapeHtml(paper.title || 'Untitled')}</strong></td>
          <td>${this.escapeHtml(paper.subject_name || paper.learning_area || '—')}</td>
          <td>${this.escapeHtml(paper.exam_year || paper.year || '—')}</td>
          <td>${this.escapeHtml(paper.class_level || '—')}</td>
          <td>${typeBadge}</td>
          <td>${this.escapeHtml(paper.uploaded_by_name || paper.uploaded_by || '—')}</td>
          <td>${date}</td>
          <td>
            <button class="btn btn-sm btn-outline-success" onclick="ViewPastPapersController.download(${paper.id})">
              <i class="bi bi-download"></i> Download
            </button>
          </td>
        </tr>
      `;
    }).join('');
  },

  getTypeBadge(type) {
    const typeMap = {
      'Mid-Term': '<span class="badge bg-success">Mid-Term</span>',
      'End-Term': '<span class="badge bg-info">End-Term</span>',
      'Mock': '<span class="badge bg-warning">Mock</span>',
      'KNEC': '<span class="badge bg-danger">KNEC</span>'
    };
    return typeMap[type] || '<span class="badge bg-secondary">—</span>';
  },

  updateStats() {
    this.state.stats.total = this.state.papers.length;
    this.state.stats.midterm = this.state.papers.filter(p => (p.exam_type || p.type) === 'Mid-Term').length;
    this.state.stats.endterm = this.state.papers.filter(p => (p.exam_type || p.type) === 'End-Term').length;
    this.state.stats.mock = this.state.papers.filter(p => ['Mock', 'KNEC'].includes(p.exam_type || p.type)).length;

    document.getElementById('totalPapersCount').textContent = this.state.stats.total;
    document.getElementById('midtermCount').textContent = this.state.stats.midterm;
    document.getElementById('endtermCount').textContent = this.state.stats.endterm;
    document.getElementById('mockCount').textContent = this.state.stats.mock;
  },

  download(id) {
    window.location.href = (window.APP_BASE || '') + '/api/academic/resources/download/' + id;
  },

  filter() {
    this.loadPapers();
  },

  async refresh() {
    await this.loadSubjects();
    await this.loadYears();
    await this.loadPapers();
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

document.addEventListener('DOMContentLoaded', () => ViewPastPapersController.init());
