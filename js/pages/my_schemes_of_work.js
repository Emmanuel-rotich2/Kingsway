/**
 * My Schemes of Work Controller
 * Page: my_schemes_of_work.php
 * Class Teacher-specific view of their own schemes of work
 * Integrates with AcademicContext for academic year awareness
 */
const MySchemesOfWorkController = {
  state: {
    schemes: [],
    currentAcademicYear: null,
    currentTerm: null,
    stats: {
      total: 0,
      approved: 0,
      pending: 0,
      overdue: 0
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
        console.log('AcademicContext changed in my_schemes_of_work:', event, data);
        if (event === 'yearChanged' || event === 'termChanged' || event === 'initialized' || event === 'refreshed') {
          this.loadSchemes();
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
    await this.loadSchemes();
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
        await this.loadSchemes();
      });
    }

    // Term selector
    const termSelect = document.getElementById('termSelect');
    if (termSelect) {
      termSelect.addEventListener('change', async (e) => {
        this.state.currentTerm = e.target.value;
        await this.loadSchemes();
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

  async loadSchemes() {
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

      const res = await window.API.apiCall('/academic/my-schemes', 'GET', params);
      
      if (res?.success) {
        this.state.schemes = res.data || [];
        this.renderSchemesTable();
        this.updateStats();
      } else {
        this.showNotification('Failed to load schemes of work', 'error');
      }
    } catch (error) {
      console.error('Error loading schemes of work:', error);
      this.showNotification('Failed to load schemes of work', 'error');
    }
  },

  renderSchemesTable() {
    const tbody = document.querySelector('#schemesTable tbody');
    if (!tbody) return;

    if (this.state.schemes.length === 0) {
      tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">No schemes of work found. Click "Create Scheme" to add one.</td></tr>`;
      return;
    }

    tbody.innerHTML = this.state.schemes.map((scheme, index) => {
      const statusBadge = this.getStatusBadge(scheme.status);
      const progress = scheme.progress || 0;
      const progressColor = progress >= 100 ? 'success' : progress >= 50 ? 'primary' : 'warning';
      const lastUpdated = scheme.updated_at ? new Date(scheme.updated_at).toLocaleDateString() : '—';

      return `
        <tr>
          <td>${index + 1}</td>
          <td><strong>${this.escapeHtml(scheme.subject_name)}</strong></td>
          <td>${this.escapeHtml(scheme.class_name || '--')}</td>
          <td>${this.escapeHtml(scheme.term_name || 'Term ' + (scheme.term || '--'))}</td>
          <td>${statusBadge}</td>
          <td>
            <div class="progress" style="height: 20px;">
              <div class="progress-bar bg-${progressColor}" style="width: ${progress}%">${progress}%</div>
            </div>
          </td>
          <td>${lastUpdated}</td>
          <td>
            <div class="btn-group btn-group-sm">
              <button class="btn btn-outline-primary" onclick="MySchemesOfWorkController.viewScheme(${scheme.id})" title="View Scheme">
                <i class="bi bi-eye"></i>
              </button>
              <button class="btn btn-outline-secondary" onclick="MySchemesOfWorkController.editScheme(${scheme.id})" title="Edit Scheme">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-outline-success" onclick="MySchemesOfWorkController.submitForApproval(${scheme.id})" title="Submit for Approval">
                <i class="bi bi-send"></i>
              </button>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  },

  getStatusBadge(status) {
    const statusMap = {
      'draft': '<span class="badge bg-secondary">Draft</span>',
      'pending': '<span class="badge bg-warning">Pending Review</span>',
      'approved': '<span class="badge bg-success">Approved</span>',
      'rejected': '<span class="badge bg-danger">Rejected</span>',
      'overdue': '<span class="badge bg-danger">Overdue</span>'
    };
    return statusMap[status] || '<span class="badge bg-secondary">Unknown</span>';
  },

  updateStats() {
    this.state.stats.total = this.state.schemes.length;
    this.state.stats.approved = this.state.schemes.filter(s => s.status === 'approved').length;
    this.state.stats.pending = this.state.schemes.filter(s => s.status === 'pending').length;
    this.state.stats.overdue = this.state.schemes.filter(s => s.status === 'overdue').length;

    document.getElementById('totalSchemesCount').textContent = this.state.stats.total;
    document.getElementById('approvedSchemesCount').textContent = this.state.stats.approved;
    document.getElementById('pendingSchemesCount').textContent = this.state.stats.pending;
    document.getElementById('overdueSchemesCount').textContent = this.state.stats.overdue;
  },

  createScheme() {
    // Navigate to scheme creation or open modal
    window.location.href = (window.APP_BASE || '') + '/home.php?route=schemes_of_work&action=create';
  },

  viewScheme(id) {
    // Navigate to scheme view
    window.location.href = (window.APP_BASE || '') + '/home.php?route=schemes_of_work&action=view&id=' + id;
  },

  editScheme(id) {
    // Navigate to scheme edit
    window.location.href = (window.APP_BASE || '') + '/home.php?route=schemes_of_work&action=edit&id=' + id;
  },

  async submitForApproval(id) {
    if (!confirm('Submit this scheme for approval?')) return;
    
    try {
      const res = await window.API.apiCall('/academic/schemes-of-work/' + id, 'PUT', { status: 'pending' });
      if (res?.success) {
        this.showNotification('Scheme submitted for approval', 'success');
        await this.loadSchemes();
      } else {
        this.showNotification('Failed to submit scheme', 'error');
      }
    } catch (error) {
      console.error('Error submitting scheme:', error);
      this.showNotification('Failed to submit scheme', 'error');
    }
  },

  exportSchemes() {
    if (!this.state.schemes.length) return;
    
    const headers = ['#', 'Subject', 'Class', 'Term', 'Status', 'Progress', 'Last Updated'];
    const rows = this.state.schemes.map((scheme, i) => [
      i + 1,
      scheme.subject_name,
      scheme.class_name || '--',
      scheme.term_name || 'Term ' + (scheme.term || '--'),
      scheme.status || 'Draft',
      scheme.progress || 0 + '%',
      scheme.updated_at ? new Date(scheme.updated_at).toLocaleDateString() : '--'
    ]);
    
    if (window.PrintManager) {
      window.PrintManager.exportToCSV({
        headers,
        rows
      }, 'my_schemes_of_work');
    } else {
      // Fallback
      let csv = headers.join(',') + '\n' + 
        rows.map(r => r.map(v => '"' + (v || '') + '"').join(',')).join('\n');
      
      KingswayFileLifecycle.exportText(csv, 'my_schemes_of_work.csv', 'text/csv');
    }
  },

  async refresh() {
    await this.loadAcademicYears();
    await this.loadSchemes();
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

document.addEventListener('DOMContentLoaded', () => MySchemesOfWorkController.init());
