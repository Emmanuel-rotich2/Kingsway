/**
 * Manage Subjects Controller
 * Page: manage_subjects.php
 * Manages learning areas (subjects) and curriculum units
 * Integrates with AcademicContext for academic year awareness
 */
const ManageSubjectsController = {
  state: {
    subjects: [],
    curriculumUnits: [],
    currentAcademicYear: null,
    stats: {
      totalSubjects: 0,
      coreSubjects: 0,
      optionalSubjects: 0,
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
        console.log('AcademicContext changed in manage_subjects:', event, data);
        if (event === 'yearChanged' || event === 'initialized' || event === 'refreshed') {
          this.loadSubjects();
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
    await this.loadSubjects();
  },

  bindEvents() {
    // Subject form submission
    const subjectForm = document.getElementById('addSubjectForm');
    if (subjectForm) {
      subjectForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.saveSubject();
      });
    }

    // Curriculum unit form submission
    const unitForm = document.getElementById('addCurriculumUnitForm');
    if (unitForm) {
      unitForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.saveCurriculumUnit();
      });
    }

    // Search input
    const searchInput = document.getElementById('searchSubjects');
    if (searchInput) {
      searchInput.addEventListener('input', (e) => {
        this.filterSubjects(e.target.value);
      });
    }

    // Filter dropdowns
    const categoryFilter = document.getElementById('categoryFilter');
    if (categoryFilter) {
      categoryFilter.addEventListener('change', () => {
        this.applyFilters();
      });
    }

    const levelFilter = document.getElementById('levelFilter');
    if (levelFilter) {
      levelFilter.addEventListener('change', () => {
        this.applyFilters();
      });
    }

    const statusFilter = document.getElementById('subjectStatusFilter');
    if (statusFilter) {
      statusFilter.addEventListener('change', () => {
        this.applyFilters();
      });
    }
  },

  async loadSubjects() {
    try {
      const res = await window.API.academic.listLearningAreas();
      
      if (res?.success) {
        this.state.subjects = res.data || [];
        this.renderSubjectsTable();
        this.updateStats();
      } else {
        this.showNotification('Failed to load subjects', 'error');
      }
    } catch (error) {
      console.error('Error loading subjects:', error);
      this.showNotification('Failed to load subjects', 'error');
    }
  },

  async loadCurriculumUnits() {
    try {
      const res = await window.API.academic.listCurriculumUnits();
      
      if (res?.success) {
        const payload = res.data?.data || res.data || {};
        this.state.curriculumUnits = payload.units || payload.curriculum_units || (Array.isArray(payload) ? payload : []);
        this.renderCurriculumUnitsTable();
      } else {
        this.showNotification('Failed to load curriculum units', 'error');
      }
    } catch (error) {
      console.error('Error loading curriculum units:', error);
      this.showNotification('Failed to load curriculum units', 'error');
    }
  },

  renderSubjectsTable() {
    const tbody = document.querySelector('#subjectsTable tbody');
    if (!tbody) return;

    if (this.state.subjects.length === 0) {
      tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">No subjects found</td></tr>`;
      return;
    }

    tbody.innerHTML = this.state.subjects.map((subject, index) => {
      const statusBadge = subject.status === 'active' 
        ? '<span class="badge bg-success">Active</span>' 
        : '<span class="badge bg-secondary">Inactive</span>';
      
      const optionalBadge = subject.is_optional 
        ? '<span class="badge bg-info">Optional</span>' 
        : '<span class="badge bg-primary">Core</span>';

      return `
        <tr>
          <td>${index + 1}</td>
          <td><strong>${this.escapeHtml(subject.name)}</strong></td>
          <td>${this.escapeHtml(subject.code || '--')}</td>
          <td>${optionalBadge}</td>
          <td>${subject.levels || '--'}</td>
          <td>${statusBadge}</td>
          <td>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary" onclick="ManageSubjectsController.editSubject(${subject.id})" title="Edit">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-outline-info" onclick="ManageSubjectsController.viewUnits(${subject.id})" title="View Units">
                <i class="bi bi-journal-text"></i>
              </button>
              <button class="btn btn-outline-danger" onclick="ManageSubjectsController.deleteSubject(${subject.id})" title="Delete">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  },

  renderCurriculumUnitsTable() {
    const tbody = document.querySelector('#curriculumUnitsTable tbody');
    if (!tbody) return;

    if (this.state.curriculumUnits.length === 0) {
      tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">No curriculum units found</td></tr>`;
      return;
    }

    tbody.innerHTML = this.state.curriculumUnits.map((unit, index) => {
      const statusBadge = unit.status === 'active' 
        ? '<span class="badge bg-success">Active</span>' 
        : '<span class="badge bg-secondary">Inactive</span>';

      return `
        <tr>
          <td>${index + 1}</td>
          <td><strong>${this.escapeHtml(unit.name)}</strong></td>
          <td>${unit.learning_area_name || '--'}</td>
          <td>${unit.duration || '--'} hrs</td>
          <td>${unit.order_sequence || '--'}</td>
          <td>${statusBadge}</td>
          <td>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary" onclick="ManageSubjectsController.editCurriculumUnit(${unit.id})" title="Edit">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-outline-danger" onclick="ManageSubjectsController.deleteCurriculumUnit(${unit.id})" title="Delete">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  },

  updateStats() {
    document.getElementById('totalSubjectsCount').textContent = this.state.subjects.length;
    document.getElementById('coreSubjectsCount').textContent = this.state.subjects.filter(s => !s.is_optional).length;
    document.getElementById('optionalSubjectsCount').textContent = this.state.subjects.filter(s => s.is_optional).length;
    
    // Count teachers assigned (simplified - would need API call for accurate count)
    document.getElementById('teachersAssignedCount').textContent = this.state.subjects.filter(s => s.teacher_count > 0).length;
  },

  showSubjectModal() {
    const modal = document.getElementById('subjectModal');
    if (modal) {
      const form = document.getElementById('subjectForm');
      if (form) {
        form.reset();
        delete form.dataset.editId;
      }
      new bootstrap.Modal(modal).show();
    }
  },

  showCurriculumUnitModal() {
    const modal = document.getElementById('curriculumUnitModal');
    if (modal) {
      const form = document.getElementById('curriculumUnitForm');
      if (form) {
        form.reset();
        delete form.dataset.editId;
        // Populate learning area dropdown
        this.populateLearningAreaDropdown(form.querySelector('#unitSubject'));
      }
      new bootstrap.Modal(modal).show();
    }
  },

  async populateLearningAreaDropdown(select) {
    if (!select) return;
    
    try {
      const res = await window.API.academic.listLearningAreas();
      if (res?.success) {
        select.innerHTML = '<option value="">Select Learning Area</option>' + 
          res.data.map(subject => `<option value="${subject.id}">${subject.name}</option>`).join('');
      }
    } catch (error) {
      console.error('Error loading learning areas for dropdown:', error);
    }
  },

  async saveSubject() {
    const form = document.getElementById('subjectForm');
    if (!form) return;

    const category = document.getElementById('subjectCategory')?.value || '';
    const data = {
      name: document.getElementById('subjectName')?.value?.trim() || '',
      code: document.getElementById('subjectCode')?.value?.trim() || '',
      description: document.getElementById('subjectDescription')?.value?.trim() || '',
      status: 'active'
    };

    if (!data.name || !data.code) {
      this.showNotification('Subject name and code are required', 'error');
      return;
    }

    try {
      const editId = form.dataset.editId;
      let res;
      if (editId) {
        res = await window.API.academic.updateLearningArea(editId, data);
      } else {
        res = await window.API.academic.createLearningArea(data);
      }

      if (res?.success) {
        this.showNotification(editId ? 'Subject updated' : 'Subject created', 'success');
        const modal = bootstrap.Modal.getInstance(document.getElementById('subjectModal'));
        if (modal) modal.hide();
        form.reset();
        delete form.dataset.editId;
        await this.loadSubjects();
      } else {
        this.showNotification(res?.message || 'Operation failed', 'error');
      }
    } catch (error) {
      console.error('Error saving subject:', error);
      this.showNotification('Failed to save subject', 'error');
    }
  },

  async saveCurriculumUnit() {
    const form = document.getElementById('curriculumUnitForm');
    if (!form) return;

    const data = {
      learning_area_id: document.getElementById('unitSubject')?.value || '',
      name: document.getElementById('unitName')?.value?.trim() || '',
      description: document.getElementById('unitTopics')?.value?.trim() || '',
      learning_outcomes: document.getElementById('unitObjectives')?.value?.trim() || '',
      suggested_resources: document.getElementById('unitResources')?.value?.trim() || '',
      duration: document.getElementById('unitDuration')?.value || null,
      order_sequence: document.getElementById('unitSequence')?.value || 1,
      status: document.getElementById('unitStatus')?.value || 'active'
    };

    if (!data.learning_area_id || !data.name) {
      this.showNotification('Subject and unit name are required', 'error');
      return;
    }

    try {
      const editId = form.dataset.editId;
      let res;
      if (editId) {
        res = await window.API.academic.updateCurriculumUnit(editId, data);
      } else {
        res = await window.API.academic.createCurriculumUnit(data);
      }

      if (res?.success) {
        this.showNotification(editId ? 'Curriculum unit updated' : 'Curriculum unit created', 'success');
        const modal = bootstrap.Modal.getInstance(document.getElementById('curriculumUnitModal'));
        if (modal) modal.hide();
        form.reset();
        delete form.dataset.editId;
        await this.loadCurriculumUnits();
      } else {
        this.showNotification(res?.message || 'Operation failed', 'error');
      }
    } catch (error) {
      console.error('Error saving curriculum unit:', error);
      this.showNotification('Failed to save curriculum unit', 'error');
    }
  },

  async editSubject(subjectId) {
    try {
      const res = await window.API.academic.getLearningArea(subjectId);
      if (res?.success && res.data) {
        const subject = res.data;
        const form = document.getElementById('subjectForm');
        if (form) {
          form.dataset.editId = subjectId;
          document.getElementById('subjectName').value = subject.name || '';
          document.getElementById('subjectCode').value = subject.code || '';
          document.getElementById('subjectDescription').value = subject.description || '';
          
          const modal = new bootstrap.Modal(document.getElementById('subjectModal'));
          modal.show();
        }
      }
    } catch (error) {
      console.error('Error loading subject for edit:', error);
    }
  },

  async editCurriculumUnit(unitId) {
    try {
      const res = await window.API.academic.getCurriculumUnit(unitId);
      if (res?.success && res.data) {
        const unit = res.data;
        const form = document.getElementById('curriculumUnitForm');
        if (form) {
          form.dataset.editId = unitId;
          await this.populateLearningAreaDropdown(document.getElementById('unitSubject'));
          document.getElementById('unitName').value = unit.name || '';
          document.getElementById('unitSubject').value = unit.learning_area_id || '';
          document.getElementById('unitDuration').value = unit.duration || '';
          document.getElementById('unitSequence').value = unit.order_sequence || 1;
          document.getElementById('unitObjectives').value = unit.learning_outcomes || '';
          document.getElementById('unitTopics').value = unit.description || '';
          document.getElementById('unitResources').value = unit.suggested_resources || '';
          document.getElementById('unitStatus').value = unit.status || 'active';
          
          const modal = new bootstrap.Modal(document.getElementById('curriculumUnitModal'));
          modal.show();
        }
      }
    } catch (error) {
      console.error('Error loading curriculum unit for edit:', error);
    }
  },

  async deleteSubject(subjectId) {
    if (!confirm('Are you sure you want to delete this subject? This cannot be undone.')) return;
    try {
      const res = await window.API.academic.deleteLearningArea(subjectId);
      if (res?.success) {
        this.showNotification('Subject deleted', 'success');
        await this.loadSubjects();
      } else {
        this.showNotification(res?.message || 'Failed to delete', 'error');
      }
    } catch (error) {
      console.error('Error deleting subject:', error);
      this.showNotification('Failed to delete subject', 'error');
    }
  },

  async deleteCurriculumUnit(unitId) {
    if (!confirm('Are you sure you want to delete this curriculum unit? This cannot be undone.')) return;
    try {
      const res = await window.API.academic.deleteCurriculumUnit(unitId);
      if (res?.success) {
        this.showNotification('Curriculum unit deleted', 'success');
        await this.loadCurriculumUnits();
      } else {
        this.showNotification(res?.message || 'Failed to delete', 'error');
      }
    } catch (error) {
      console.error('Error deleting curriculum unit:', error);
      this.showNotification('Failed to delete curriculum unit', 'error');
    }
  },

  viewUnits(subjectId) {
    // Load curriculum units filtered by subject
    // For now, just load all units and let the user filter
    this.loadCurriculumUnits();
    
    // Switch to curriculum units tab if available
    const unitsTab = document.querySelector('#curriculum-units-tab');
    if (unitsTab) {
      unitsTab.click();
    }
  },

  filterSubjects(searchTerm) {
    const filtered = this.state.subjects.filter(subject => {
      const searchLower = searchTerm.toLowerCase();
      return subject.name.toLowerCase().includes(searchLower) ||
             (subject.code && subject.code.toLowerCase().includes(searchLower)) ||
             (subject.description && subject.description.toLowerCase().includes(searchLower));
    });
    
    this.renderFilteredSubjects(filtered);
  },

  applyFilters() {
    const category = document.getElementById('categoryFilter')?.value || '';
    const level = document.getElementById('levelFilter')?.value || '';
    const status = document.getElementById('subjectStatusFilter')?.value || '';
    
    let filtered = [...this.state.subjects];
    
    if (category === 'core') {
      filtered = filtered.filter(s => !s.is_optional);
    } else if (category === 'optional') {
      filtered = filtered.filter(s => s.is_optional);
    }
    
    if (level && s.levels) {
      filtered = filtered.filter(s => s.levels.toLowerCase().includes(level.toLowerCase()));
    }
    
    if (status) {
      filtered = filtered.filter(s => s.status === status);
    }
    
    this.renderFilteredSubjects(filtered);
  },

  renderFilteredSubjects(filtered) {
    const tbody = document.querySelector('#subjectsTable tbody');
    if (!tbody) return;

    if (filtered.length === 0) {
      tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">No subjects match your filters</td></tr>`;
      return;
    }

    tbody.innerHTML = filtered.map((subject, index) => {
      const statusBadge = subject.status === 'active' 
        ? '<span class="badge bg-success">Active</span>' 
        : '<span class="badge bg-secondary">Inactive</span>';
      
      const optionalBadge = subject.is_optional 
        ? '<span class="badge bg-info">Optional</span>' 
        : '<span class="badge bg-primary">Core</span>';

      return `
        <tr>
          <td>${index + 1}</td>
          <td><strong>${this.escapeHtml(subject.name)}</strong></td>
          <td>${this.escapeHtml(subject.code || '--')}</td>
          <td>${optionalBadge}</td>
          <td>${subject.levels || '--'}</td>
          <td>${statusBadge}</td>
          <td>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary" onclick="ManageSubjectsController.editSubject(${subject.id})" title="Edit">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-outline-info" onclick="ManageSubjectsController.viewUnits(${subject.id})" title="View Units">
                <i class="bi bi-journal-text"></i>
              </button>
              <button class="btn btn-outline-danger" onclick="ManageSubjectsController.deleteSubject(${subject.id})" title="Delete">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  },

  exportSubjects() {
    if (!this.state.subjects.length) return;

    if (!window.PrintManager) {
      this.showNotification('PrintManager not available', 'error');
      return;
    }

    window.PrintManager.exportToCSV({
      filename: 'subjects',
      columns: [
        { key: 'name', label: 'Subject Name' },
        { key: 'code', label: 'Code' },
        { key: 'type', label: 'Type' },
        { key: 'levels', label: 'Levels' },
        { key: 'status', label: 'Status' }
      ],
      rows: this.state.subjects.map((subject) => ({
        ...subject,
        type: subject.is_optional ? 'Optional' : 'Core'
      }))
    });
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

document.addEventListener('DOMContentLoaded', () => ManageSubjectsController.init());
