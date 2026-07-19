/**
 * View Teaching Materials Controller
 * Page: view_teaching_materials.php
 * Intern-specific read-only view of teaching materials
 * Integrates with AcademicContext for academic year awareness
 */
const ViewTeachingMaterialsController = {
  state: {
    materials: [],
    subjects: [],
    currentAcademicYear: null,
    currentTerm: null,
    stats: {
      total: 0,
      worksheets: 0,
      presentations: 0,
      others: 0
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
        console.log('AcademicContext changed in view_teaching_materials:', event, data);
        if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
          this.loadMaterials();
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
    await this.loadMaterials();
  },

  bindEvents() {
    // Search input with debouncing
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
      let timeout;
      searchInput.addEventListener('input', (e) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => this.loadMaterials(), 300);
      });
    }

    // Subject filter
    const subjectFilter = document.getElementById('subjectFilter');
    if (subjectFilter) {
      subjectFilter.addEventListener('change', () => this.loadMaterials());
    }

    // Type filter
    const typeFilter = document.getElementById('typeFilter');
    if (typeFilter) {
      typeFilter.addEventListener('change', () => this.loadMaterials());
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

  async loadMaterials() {
    try {
      const params = this.buildParams();
      const res = await window.API.apiCall('/academic/resources?type=material' + params, 'GET');
      
      if (res?.success) {
        this.state.materials = res.data || [];
        this.renderMaterialsGrid();
        this.updateStats();
      } else {
        this.showNotification('Failed to load teaching materials', 'error');
      }
    } catch (error) {
      console.error('Error loading teaching materials:', error);
      this.showNotification('Failed to load teaching materials', 'error');
    }
  },

  buildParams() {
    const search = document.getElementById('searchInput')?.value.trim() || '';
    const subject = document.getElementById('subjectFilter')?.value || '';
    const type = document.getElementById('typeFilter')?.value || '';
    
    const parts = [];
    if (search) parts.push('search=' + encodeURIComponent(search));
    if (subject) parts.push('subject=' + encodeURIComponent(subject));
    if (type) parts.push('resource_type=' + encodeURIComponent(type));
    
    return parts.length ? '&' + parts.join('&') : '';
  },

  renderMaterialsGrid() {
    const grid = document.getElementById('materialsGrid');
    if (!grid) return;

    if (this.state.materials.length === 0) {
      grid.innerHTML = `<div class="text-center py-4 text-muted">No teaching materials found.</div>`;
      return;
    }

    grid.innerHTML = `<div class="row row-cols-1 row-cols-md-3 g-3">${this.state.materials.map(material => this.renderMaterialCard(material)).join('')}</div>`;
  },

  renderMaterialCard(material) {
    const icon = this.getTypeIcon(material.resource_type || material.type || 'Other');
    const color = this.getTypeColor(material.resource_type || material.type || 'Other');
    const size = material.file_size ? this.formatSize(material.file_size) : '—';
    const date = material.created_at ? new Date(material.created_at).toLocaleDateString() : '—';

    return `
      <div class="col">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex align-items-start gap-3">
              <div class="flex-shrink-0">
                <span class="badge ${color} p-2 fs-4"><i class="bi ${icon}"></i></span>
              </div>
              <div class="flex-grow-1 overflow-hidden">
                <h6 class="card-title mb-1 text-truncate" title="${this.escapeHtml(material.title || '')}">${this.escapeHtml(material.title || 'Untitled')}</h6>
                <div class="text-muted small mb-1">
                  <i class="bi bi-book me-1"></i>${this.escapeHtml(material.subject_name || material.learning_area || '—')}
                  &nbsp;·&nbsp;
                  <i class="bi bi-people me-1"></i>${this.escapeHtml(material.class_name || '—')}
                </div>
                <div class="text-muted small">
                  <i class="bi bi-person me-1"></i>${this.escapeHtml(material.uploaded_by_name || material.uploaded_by || '—')}
                  &nbsp;·&nbsp;
                  <i class="bi bi-calendar me-1"></i>${date}
                  &nbsp;·&nbsp; ${size}
                </div>
              </div>
            </div>
          </div>
          <div class="card-footer bg-transparent border-top-0 pt-0 pb-2 px-3">
            <button class="btn btn-sm btn-outline-primary w-100" onclick="ViewTeachingMaterialsController.download(${material.id})">
              <i class="bi bi-download me-1"></i> Download
            </button>
          </div>
        </div>
      </div>
    `;
  },

  getTypeIcon(type) {
    const icons = {
      'Worksheet': 'bi-file-earmark-text',
      'Notes': 'bi-journal-text',
      'Presentation': 'bi-file-earmark-slides',
      'Video': 'bi-camera-video',
      'Past Paper': 'bi-file-earmark-ruled',
      'Other': 'bi-file-earmark',
    };
    return icons[type] || 'bi-file-earmark';
  },

  getTypeColor(type) {
    const colors = {
      'Worksheet': 'bg-primary bg-opacity-10 text-primary',
      'Notes': 'bg-success bg-opacity-10 text-success',
      'Presentation': 'bg-warning bg-opacity-10 text-warning',
      'Video': 'bg-danger bg-opacity-10 text-danger',
      'Past Paper': 'bg-info bg-opacity-10 text-info',
      'Other': 'bg-secondary bg-opacity-10 text-secondary',
    };
    return colors[type] || 'bg-secondary bg-opacity-10 text-secondary';
  },

  formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
  },

  updateStats() {
    this.state.stats.total = this.state.materials.length;
    this.state.stats.worksheets = this.state.materials.filter(m => (m.resource_type || m.type) === 'Worksheet').length;
    this.state.stats.presentations = this.state.materials.filter(m => (m.resource_type || m.type) === 'Presentation').length;
    this.state.stats.others = this.state.materials.filter(m => !['Worksheet', 'Presentation'].includes(m.resource_type || m.type)).length;

    document.getElementById('totalMaterialsCount').textContent = this.state.stats.total;
    document.getElementById('worksheetsCount').textContent = this.state.stats.worksheets;
    document.getElementById('presentationsCount').textContent = this.state.stats.presentations;
    document.getElementById('othersCount').textContent = this.state.stats.others;
  },

  download(id) {
    window.location.href = (window.APP_BASE || '') + '/api/academic/resources/' + id + '/download';
  },

  filter() {
    this.loadMaterials();
  },

  async refresh() {
    await this.loadSubjects();
    await this.loadMaterials();
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

document.addEventListener('DOMContentLoaded', () => ViewTeachingMaterialsController.init());
