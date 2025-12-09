/**
 * Complete Academic Management System - Production Ready
 * Uses centralized API.academic methods from api.js
 * Green/White Professional Design
 * NO assumptions - works with ACTUAL database facts
 */

const academicsManager = {
    // State management
    state: {
        classes: [],
        subjects: [],
        years: [],
        terms: [],
        streams: [],
        schedules: [],
        curriculumUnits: [],
        topics: [],
        lessonPlans: [],
        teachers: [],
        currentYear: null,
        currentTerm: null
    },
    // Initialize
    async init() {
        await this.loadStatistics();
        await this.loadAcademicYears();
        await this.loadTerms();
        await this.loadClasses();
        await this.loadSubjects();
        await this.loadStreams();
        await this.loadTeachers();
        this.setupEventListeners();
    },

    // ==================== UTILITY FUNCTIONS ====================
    showToast(message, type = 'info', title = 'Notification') {
        const toastEl = document.getElementById('academicToast');
        const toastIcon = document.getElementById('toastIcon');
        const toastTitle = document.getElementById('toastTitle');
        const toastBody = document.getElementById('toastBody');

        const icons = {
            success: 'bi-check-circle-fill text-success',
            error: 'bi-x-circle-fill text-danger',
            warning: 'bi-exclamation-triangle-fill text-warning',
            info: 'bi-info-circle-fill text-info'
        };

        toastIcon.className = `bi ${icons[type]} me-2`;
        toastTitle.textContent = title;
        toastBody.textContent = message;

        const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
        toast.show();
    },

    formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    },

    // ==================== STATISTICS & DASHBOARD ====================
    async loadStatistics() {
        try {
            // Use centralized API.academic methods
            const [classesRes, subjectsRes, yearsRes] = await Promise.all([
                window.API.academic.listClasses(),
                window.API.academic.listLearningAreas(),
                window.API.academic.listYears()
            ]);

            // Ensure responses are arrays before using array methods
            // Handle potential nested data structure from backend
            const classes = Array.isArray(classesRes) ? classesRes : (Array.isArray(classesRes?.data) ? classesRes.data : []);
            const subjects = Array.isArray(subjectsRes) ? subjectsRes : (Array.isArray(subjectsRes?.data) ? subjectsRes.data : []);
            const years = Array.isArray(yearsRes) ? yearsRes : (Array.isArray(yearsRes?.data) ? yearsRes.data : []);

            const totalStudents = classes.reduce((sum, cls) => sum + (parseInt(cls.student_count) || 0), 0);
            const currentYear = years.find(y => y.is_current)?.year_code || '-';

            document.getElementById('totalClasses').textContent = classes.length;
            document.getElementById('totalSubjects').textContent = subjects.length;
            document.getElementById('totalStudents').textContent = totalStudents;
            document.getElementById('activeYear').textContent = currentYear;
        } catch (error) {
            console.error('Failed to load statistics:', error);
        }
    },

    // ==================== ACADEMIC YEARS ====================
    async loadAcademicYears() {
        try {
            const response = await window.API.academic.listYears();
            // Handle both flat array and nested data structure
            const data = Array.isArray(response) ? response : (Array.isArray(response?.data) ? response.data : []);
            
            if (data && data.length > 0) {
                this.state.years = data;
                this.state.currentYear = data.find(y => y.is_current);
                this.renderAcademicYears();
                this.populateYearFilters();
            }
        } catch (error) {
            this.showToast('Failed to load academic years', 'error');
            console.error(error);
        }
    },

    renderAcademicYears() {
        const container = document.getElementById('yearsContainer');
        if (!this.state.years.length) {
            container.innerHTML = '<p class="text-muted text-center py-4">No academic years found. Click "Add Year" to create one.</p>';
            return;
        }

        container.innerHTML = `
            <div class="row">
                ${this.state.years.map(year => `
                    <div class="col-md-6 mb-3">
                        <div class="card ${year.is_current ? 'border-success' : ''}">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="card-title">
                                            ${year.year_code}
                                            ${year.is_current ? '<span class="badge badge-academic ms-2">Current</span>' : ''}
                                        </h5>
                                        <h6 class="text-muted">${year.year_name}</h6>
                                        <p class="mb-1"><small><i class="bi bi-calendar"></i> ${this.formatDate(year.start_date)} - ${this.formatDate(year.end_date)}</small></p>
                                        <p class="mb-1"><small><i class="bi bi-people"></i> ${year.total_students || 0} students | ${year.total_classes || 0} classes</small></p>
                                        <p class="mb-0"><small class="badge ${year.status === 'active' ? 'bg-success' : 'bg-secondary'}">${year.status}</small></p>
                                    </div>
                                    <div class="btn-group-vertical">
                                        ${!year.is_current && year.status === 'active' ? `
                                            <button class="btn btn-sm btn-success" onclick="academicsManager.setCurrentYear(${year.id})" title="Set as current">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                        ` : ''}
                                        <button class="btn btn-sm btn-outline-primary" onclick="academicsManager.editYear(${year.id})" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="academicsManager.deleteYear(${year.id})" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    },

    showYearModal(yearId = null) {
        const year = yearId ? this.state.years.find(y => y.id === yearId) : null;
        const isEdit = !!year;
        const currentYear = this.state.currentYear;
        const hasCurrentYear = !!currentYear;

        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">${isEdit ? 'Edit' : 'Create New'} Academic Year</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ${!isEdit && hasCurrentYear ? `
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i> <strong>Important:</strong>
                                <p class="mb-2">Before creating a new academic year, ensure:</p>
                                <ul class="mb-2">
                                    <li>Current year (${currentYear.year_code}) is properly <strong>closed/archived</strong></li>
                                    <li>All student <strong>promotions</strong> are completed</li>
                                    <li>All <strong>final reports</strong> are generated and distributed</li>
                                    <li><strong>Fee structures</strong> are updated for the new year</li>
                                    <li>Previous year data is <strong>archived</strong></li>
                                </ul>
                                <small class="text-muted">💡 Tip: Use the Year Transition Workflow for a guided process</small>
                            </div>
                        ` : ''}
                        ${isEdit && year?.is_current ? `
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> You are editing the <strong>current active year</strong>. Changes will take effect immediately.
                            </div>
                        ` : ''}
                        <form id="yearForm">
                            <div class="mb-3">
                                <label class="form-label">Year Code * <small class="text-muted">(e.g., 2025)</small></label>
                                <input type="text" class="form-control" id="yearCode" value="${year?.year_code || ''}" placeholder="2025" required ${isEdit ? 'readonly' : ''}>
                                ${!isEdit ? '<small class="form-text text-muted">Format: YYYY (will auto-create 3 terms)</small>' : ''}
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Year Name *</label>
                                <input type="text" class="form-control" id="yearName" value="${year?.year_name || ''}" placeholder="e.g., 2024 Academic Year" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Start Date *</label>
                                    <input type="date" class="form-control" id="startDate" value="${year?.start_date || ''}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">End Date *</label>
                                    <input type="date" class="form-control" id="endDate" value="${year?.end_date || ''}" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Registration Start</label>
                                    <input type="date" class="form-control" id="regStart" value="${year?.registration_start || ''}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Registration End</label>
                                    <input type="date" class="form-control" id="regEnd" value="${year?.registration_end || ''}">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status *</label>
                                <select class="form-select" id="yearStatus" required>
                                    <option value="planning" ${(year?.status === 'planning' || !year) ? 'selected' : ''}>Planning - Setting up the year</option>
                                    <option value="registration" ${year?.status === 'registration' ? 'selected' : ''}>Registration - Open for student enrollment</option>
                                    <option value="active" ${year?.status === 'active' ? 'selected' : ''}>Active - Year is ongoing</option>
                                    <option value="closing" ${year?.status === 'closing' ? 'selected' : ''}>Closing - Preparing to end year</option>
                                    <option value="archived" ${year?.status === 'archived' ? 'selected' : ''}>Archived - Year completed</option>
                                </select>
                                <small class="form-text text-muted">
                                    ${!isEdit ? 'New years start in "Planning" status' : 'Update status as year progresses'}
                                </small>
                            </div>
                            ${!isEdit ? `
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> <strong>Auto-Generated:</strong>
                                    <p class="mb-0">Creating this year will automatically generate:</p>
                                    <ul class="mb-0">
                                        <li>3 Terms (Term 1, 2, 3) with equal duration</li>
                                        <li>Academic calendar structure</li>
                                        <li>Initial system configurations</li>
                                    </ul>
                                </div>
                            ` : ''}
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-academic-primary" onclick="academicsManager.saveYear(${yearId})">
                            <i class="bi bi-save"></i> ${isEdit ? 'Update' : 'Create'} Year
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        modal.addEventListener('hidden.bs.modal', () => modal.remove());
    },

    async saveYear(yearId = null) {
        const data = {
            year_code: document.getElementById('yearCode').value.trim(),
            year_name: document.getElementById('yearName').value.trim(),
            start_date: document.getElementById('startDate').value,
            end_date: document.getElementById('endDate').value,
            registration_start: document.getElementById('regStart').value || null,
            registration_end: document.getElementById('regEnd').value || null,
            status: document.getElementById('yearStatus').value
        };

        // Validation
        if (!data.year_code || !data.year_name || !data.start_date || !data.end_date) {
            this.showToast('Please fill in all required fields', 'warning');
            return;
        }

        // Validate year code format (should be 4 digits)
        if (!/^\d{4}$/.test(data.year_code)) {
            this.showToast('Year code must be a 4-digit year (e.g., 2025)', 'warning');
            return;
        }

        // Validate dates
        const startDate = new Date(data.start_date);
        const endDate = new Date(data.end_date);
        
        if (endDate <= startDate) {
            this.showToast('End date must be after start date', 'warning');
            return;
        }

        // Check if year duration is reasonable (between 9-12 months)
        const durationMonths = (endDate - startDate) / (1000 * 60 * 60 * 24 * 30);
        if (durationMonths < 9 || durationMonths > 13) {
            if (!confirm(`Academic year duration is ${Math.round(durationMonths)} months. Kenyan academic years typically run for 9-12 months. Continue anyway?`)) {
                return;
            }
        }

        // Warn if creating year without closing current year
        if (!yearId && this.state.currentYear && this.state.currentYear.status !== 'archived') {
            const currentYearCode = this.state.currentYear.year_code;
            if (!confirm(`⚠️ WARNING: Current year (${currentYearCode}) is still ${this.state.currentYear.status}.\n\nBest Practice:\n1. Close/archive the current year first\n2. Complete all student promotions\n3. Generate final reports\n4. Then create the new year\n\nContinue creating new year anyway?`)) {
                return;
            }
        }

        try {
            let response;
            if (yearId) {
                response = await window.API.academic.updateYear(yearId, data);
            } else {
                response = await window.API.academic.createYear(data);
            }

            // If no error thrown, operation succeeded
            const message = yearId 
                ? `Academic year ${data.year_code} updated successfully` 
                : `Academic year ${data.year_code} created successfully with 3 terms`;
            this.showToast(message, 'success');
            bootstrap.Modal.getInstance(document.querySelector('.modal')).hide();
            await this.loadAcademicYears();
            await this.loadTerms(); // Refresh terms to show newly created ones
            await this.loadStatistics();
        } catch (error) {
            this.showToast(`Failed to ${yearId ? 'update' : 'create'} year: ${error.message}`, 'error');
        }
    },

    editYear(yearId) {
        this.showYearModal(yearId);
    },

    async deleteYear(yearId) {
        if (!confirm('Are you sure you want to delete this academic year? This action cannot be undone.')) {
            return;
        }

        try {
            const response = await window.API.academic.deleteYear(yearId);
            // If no error thrown, operation succeeded
            this.showToast('Academic year deleted successfully', 'success');
            await this.loadAcademicYears();
            await this.loadStatistics();
        } catch (error) {
            this.showToast(`Failed to delete year: ${error.message}`, 'error');
        }
    },

    async setCurrentYear(yearId) {
        try {
            const response = await window.API.academic.setCurrentYear(yearId);
            // If no error thrown, operation succeeded
            this.showToast('Current academic year updated successfully', 'success');
            await this.loadAcademicYears();
            await this.loadStatistics();
        } catch (error) {
            this.showToast(`Failed to set current year: ${error.message}`, 'error');
        }
    },

    populateYearFilters() {
        const select = document.getElementById('filterTermsByYear');
        if (select) {
            select.innerHTML = '<option value="">All Academic Years</option>' +
                this.state.years.map(y => `<option value="${y.id}">${y.year_code}</option>`).join('');
        }
    },

    // ==================== TERMS ====================
    async loadTerms() {
        try {
            const response = await window.API.academic.listTerms();
            // Handle both flat array and nested data structure
            const data = Array.isArray(response) ? response : (Array.isArray(response?.data) ? response.data : []);
            
            if (data) {
                this.state.terms = data;
                this.renderTerms();
            }
        } catch (error) {
            this.showToast('Failed to load terms', 'error');
            console.error(error);
        }
    },

    renderTerms(filteredTerms = null) {
        const container = document.getElementById('termsContainer');
        const terms = filteredTerms || this.state.terms;

        if (!terms.length) {
            container.innerHTML = '<p class="text-muted text-center py-4">No terms found.</p>';
            return;
        }

        container.innerHTML = `
            <table class="table table-academic table-hover">
                <thead>
                    <tr>
                        <th>Term</th>
                        <th>Academic Year</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${terms.map(term => `
                        <tr>
                            <td><strong>Term ${term.term_number}</strong><br><small class="text-muted">${term.name}</small></td>
                            <td>${term.year || '-'}</td>
                            <td>${this.formatDate(term.start_date)}</td>
                            <td>${this.formatDate(term.end_date)}</td>
                            <td><span class="badge ${term.status === 'current' ? 'bg-success' : term.status === 'upcoming' ? 'bg-info' : 'bg-secondary'}">${term.status}</span></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="academicsManager.editTerm(${term.id})" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="academicsManager.deleteTerm(${term.id})" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    },

    showTermModal(termId = null) {
        const term = termId ? this.state.terms.find(t => t.id === termId) : null;
        const isEdit = !!term;

        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">${isEdit ? 'Edit' : 'Add'} Term</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="termForm">
                            <div class="mb-3">
                                <label class="form-label">Term Name *</label>
                                <input type="text" class="form-control" id="termName" value="${term?.name || ''}" placeholder="e.g., First Term" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Term Number *</label>
                                <select class="form-select" id="termNumber" required>
                                    <option value="1" ${term?.term_number === 1 ? 'selected' : ''}>1</option>
                                    <option value="2" ${term?.term_number === 2 ? 'selected' : ''}>2</option>
                                    <option value="3" ${term?.term_number === 3 ? 'selected' : ''}>3</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Academic Year *</label>
                                <select class="form-select" id="termYear" required>
                                    ${this.state.years.map(y => `
                                        <option value="${y.year}" ${term?.year === y.year ? 'selected' : ''}>${y.year_code}</option>
                                    `).join('')}
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Start Date *</label>
                                    <input type="date" class="form-control" id="termStartDate" value="${term?.start_date || ''}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">End Date *</label>
                                    <input type="date" class="form-control" id="termEndDate" value="${term?.end_date || ''}" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="termStatus">
                                    <option value="upcoming" ${term?.status === 'upcoming' ? 'selected' : ''}>Upcoming</option>
                                    <option value="current" ${term?.status === 'current' ? 'selected' : ''}>Current</option>
                                    <option value="completed" ${term?.status === 'completed' ? 'selected' : ''}>Completed</option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-academic-primary" onclick="academicsManager.saveTerm(${termId})">
                            <i class="bi bi-save"></i> Save Term
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        modal.addEventListener('hidden.bs.modal', () => modal.remove());
    },

    async saveTerm(termId = null) {
        const data = {
            name: document.getElementById('termName').value.trim(),
            term_number: parseInt(document.getElementById('termNumber').value),
            year: document.getElementById('termYear').value,
            start_date: document.getElementById('termStartDate').value,
            end_date: document.getElementById('termEndDate').value,
            status: document.getElementById('termStatus').value
        };

        if (!data.name || !data.year || !data.start_date || !data.end_date) {
            this.showToast('Please fill in all required fields', 'warning');
            return;
        }

        try {
            let response;
            if (termId) {
                response = await window.API.academic.updateTerm(termId, data);
            } else {
                response = await window.API.academic.createTerm(data);
            }

            // If no error thrown, operation succeeded
            this.showToast(`Term ${termId ? 'updated' : 'created'} successfully`, 'success');
            bootstrap.Modal.getInstance(document.querySelector('.modal')).hide();
            await this.loadTerms();
        } catch (error) {
            this.showToast(`Failed to ${termId ? 'update' : 'create'} term: ${error.message}`, 'error');
        }
    },

    editTerm(termId) {
        this.showTermModal(termId);
    },

    async deleteTerm(termId) {
        if (!confirm('Are you sure you want to delete this term?')) return;

        try {
            const response = await window.API.academic.deleteTerm(termId);
            // If no error thrown, operation succeeded
            this.showToast('Term deleted successfully', 'success');
            await this.loadTerms();
        } catch (error) {
            this.showToast(`Failed to delete term: ${error.message}`, 'error');
        }
    },

    filterTermsByYear(yearId) {
        if (!yearId) {
            this.renderTerms();
            return;
        }
        const year = this.state.years.find(y => y.id === parseInt(yearId));
        const filtered = this.state.terms.filter(t => t.year === year.year);
        this.renderTerms(filtered);
    },

    filterTermsByStatus(status) {
        if (!status) {
            this.renderTerms();
            return;
        }
        const filtered = this.state.terms.filter(t => t.status === status);
        this.renderTerms(filtered);
    },

    // ==================== CLASSES ====================
    async loadClasses() {
        try {
            const response = await window.API.academic.listClasses();
            // Handle both flat array and nested data structure
            const data = Array.isArray(response) ? response : (Array.isArray(response?.data) ? response.data : []);
            
            if (data) {
                this.state.classes = data;
                this.renderClasses();
                this.populateLevelFilters();
            }
        } catch (error) {
            this.showToast('Failed to load classes', 'error');
            console.error(error);
        }
    },

    renderClasses(filteredClasses = null) {
        const container = document.getElementById('classesContainer');
        const classes = filteredClasses || this.state.classes;

        if (!classes.length) {
            container.innerHTML = '<p class="text-muted text-center py-4">No classes found.</p>';
            return;
        }

        container.innerHTML = `
            <table class="table table-academic table-hover">
                <thead>
                    <tr>
                        <th>Class Name</th>
                        <th>Level</th>
                        <th>Class Teacher</th>
                        <th>Capacity</th>
                        <th>Students</th>
                        <th>Streams</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${classes.map(cls => `
                        <tr>
                            <td><strong>${cls.name}</strong></td>
                            <td>${cls.level_name || '-'}</td>
                            <td>${cls.class_teacher_name || 'Not assigned'}</td>
                            <td>${cls.capacity || '-'}</td>
                            <td><span class="badge badge-academic">${cls.student_count || 0}</span></td>
                            <td>${cls.stream_count || 0}</td>
                            <td><span class="badge ${cls.status === 'active' ? 'bg-success' : 'bg-secondary'}">${cls.status}</span></td>
                            <td>
                                <button class="btn btn-sm btn-outline-info" onclick="academicsManager.viewClassDetails(${cls.id})" title="View Details">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick="academicsManager.editClass(${cls.id})" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="academicsManager.deleteClass(${cls.id})" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    },

    async viewClassDetails(classId) {
        try {
            const response = await window.API.academic.getClass(classId);
            if (!response || typeof response !== 'object') {
                this.showToast('Failed to load class details', 'error');
                return;
            }

            const cls = response;
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title"><i class="bi bi-journal-bookmark"></i> ${cls.name} Details</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Class Name:</strong> ${cls.name}</p>
                                    <p><strong>Level:</strong> ${cls.level_name || '-'}</p>
                                    <p><strong>Class Teacher:</strong> ${cls.class_teacher_name || 'Not assigned'}</p>
                                    <p><strong>Room Number:</strong> ${cls.room_number || '-'}</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Capacity:</strong> ${cls.capacity || '-'}</p>
                                    <p><strong>Current Students:</strong> ${cls.student_count || 0}</p>
                                    <p><strong>Academic Year:</strong> ${cls.academic_year || '-'}</p>
                                    <p><strong>Status:</strong> <span class="badge ${cls.status === 'active' ? 'bg-success' : 'bg-secondary'}">${cls.status}</span></p>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-academic-primary" onclick="academicsManager.editClass(${cls.id}); bootstrap.Modal.getInstance(document.querySelector('.modal')).hide();">
                                <i class="bi bi-pencil"></i> Edit Class
                            </button>
                        </div>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            modal.addEventListener('hidden.bs.modal', () => modal.remove());
        } catch (error) {
            this.showToast('Failed to load class details', 'error');
        }
    },

    showClassModal(classId = null) {
        const cls = classId ? this.state.classes.find(c => c.id === classId) : null;
        const isEdit = !!cls;

        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">${isEdit ? 'Edit' : 'Add'} Class</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="classForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Class Name *</label>
                                    <input type="text" class="form-control" id="className" value="${cls?.name || ''}" placeholder="e.g., Grade 1A" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Level *</label>
                                    <select class="form-select" id="classLevel" required>
                                        <option value="">Select Level...</option>
                                        <!-- Will be populated from levels -->
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Class Teacher</label>
                                    <select class="form-select" id="classTeacher">
                                        <option value="">Select Teacher...</option>
                                        <!-- Will be populated from teachers -->
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Capacity</label>
                                    <input type="number" class="form-control" id="classCapacity" value="${cls?.capacity || ''}" placeholder="e.g., 40">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Room Number</label>
                                    <input type="text" class="form-control" id="roomNumber" value="${cls?.room_number || ''}" placeholder="e.g., A101">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Academic Year</label>
                                    <input type="text" class="form-control" id="academicYear" value="${cls?.academic_year || this.state.currentYear?.year || ''}">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="classStatus">
                                    <option value="active" ${cls?.status === 'active' ? 'selected' : ''}>Active</option>
                                    <option value="inactive" ${cls?.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-academic-primary" onclick="academicsManager.saveClass(${classId})">
                            <i class="bi bi-save"></i> Save Class
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        modal.addEventListener('hidden.bs.modal', () => modal.remove());

        // TODO: Populate teachers and levels dropdowns
    },

    async saveClass(classId = null) {
        const data = {
            name: document.getElementById('className').value.trim(),
            level_id: document.getElementById('classLevel').value || null,
            teacher_id: document.getElementById('classTeacher').value || null,
            capacity: document.getElementById('classCapacity').value || null,
            room_number: document.getElementById('roomNumber').value.trim() || null,
            academic_year: document.getElementById('academicYear').value || null,
            status: document.getElementById('classStatus').value
        };

        if (!data.name) {
            this.showToast('Please enter a class name', 'warning');
            return;
        }

        try {
            let response;
            if (classId) {
                response = await window.API.academic.updateClass(classId, data);
            } else {
                response = await window.API.academic.createClass(data);
            }

            // If no error thrown, operation succeeded
            this.showToast(`Class ${classId ? 'updated' : 'created'} successfully`, 'success');
            bootstrap.Modal.getInstance(document.querySelector('.modal')).hide();
            await this.loadClasses();
            await this.loadStatistics();
        } catch (error) {
            this.showToast(`Failed to ${classId ? 'update' : 'create'} class: ${error.message}`, 'error');
        }
    },

    editClass(classId) {
        this.showClassModal(classId);
    },

    async deleteClass(classId) {
        if (!confirm('Are you sure you want to delete this class?')) return;

        try {
            const response = await window.API.academic.deleteClass(classId);
            // If no error thrown, operation succeeded
            this.showToast('Class deleted successfully', 'success');
            await this.loadClasses();
            await this.loadStatistics();
        } catch (error) {
            this.showToast(`Failed to delete class: ${error.message}`, 'error');
        }
    },

    searchClasses(searchTerm) {
        if (!searchTerm) {
            this.renderClasses();
            return;
        }
        const filtered = this.state.classes.filter(cls =>
            cls.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            (cls.level_name && cls.level_name.toLowerCase().includes(searchTerm.toLowerCase())) ||
            (cls.level_code && cls.level_code.toLowerCase().includes(searchTerm.toLowerCase()))
        );
        this.renderClasses(filtered);
    },

    filterClassesByLevel(levelId) {
        if (!levelId) {
            this.renderClasses();
            return;
        }
        const filtered = this.state.classes.filter(cls => cls.level_id === parseInt(levelId));
        this.renderClasses(filtered);
    },

    populateLevelFilters() {
        const select = document.getElementById('filterByLevel');
        if (select && this.state.classes.length) {
            const levels = [...new Set(this.state.classes.map(c => JSON.stringify({ id: c.level_id, name: c.level_name })))].map(JSON.parse);
            select.innerHTML = '<option value="">All Levels</option>' +
                levels.map(l => `<option value="${l.id}">${l.name}</option>`).join('');
        }
    },

    // ==================== LEARNING AREAS (SUBJECTS) ====================
    async loadSubjects() {
        try {
            const response = await window.API.academic.listLearningAreas();
            // Handle both flat array and nested data structure
            const data = Array.isArray(response) ? response : (Array.isArray(response?.data) ? response.data : []);
            
            if (data) {
                this.state.subjects = data;
                this.renderSubjects();
            }
        } catch (error) {
            this.showToast('Failed to load learning areas', 'error');
            console.error(error);
        }
    },

    renderSubjects(filteredSubjects = null) {
        const container = document.getElementById('subjectsContainer');
        const subjects = filteredSubjects || this.state.subjects;

        if (!subjects.length) {
            container.innerHTML = '<p class="text-muted text-center py-4">No learning areas found.</p>';
            return;
        }

        container.innerHTML = `
            <table class="table table-academic table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Category</th>
                        <th>Levels</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${subjects.map(subject => `
                        <tr>
                            <td><strong>${subject.name}</strong></td>
                            <td><code>${subject.code}</code></td>
                            <td><span class="badge ${subject.is_optional ? 'bg-info' : 'bg-success'}">${subject.is_optional ? 'Optional' : 'Core'}</span></td>
                            <td>${subject.levels || 'All'}</td>
                            <td><span class="badge ${subject.status === 'active' ? 'bg-success' : 'bg-secondary'}">${subject.status}</span></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="academicsManager.editSubject(${subject.id})" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="academicsManager.deleteSubject(${subject.id})" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    },

    showSubjectModal(subjectId = null) {
        const subject = subjectId ? this.state.subjects.find(s => s.id === subjectId) : null;
        const isEdit = !!subject;

        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">${isEdit ? 'Edit' : 'Add'} Learning Area</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="subjectForm">
                            <div class="mb-3">
                                <label class="form-label">Subject Name *</label>
                                <input type="text" class="form-control" id="subjectName" value="${subject?.name || ''}" placeholder="e.g., Mathematics" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Subject Code *</label>
                                <input type="text" class="form-control" id="subjectCode" value="${subject?.code || ''}" placeholder="e.g., MATH" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" id="subjectDesc" rows="3">${subject?.description || ''}</textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Levels (comma-separated)</label>
                                <input type="text" class="form-control" id="subjectLevels" value="${subject?.levels || ''}" placeholder="e.g., 1,2,3,4,5,6">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" id="subjectCategory">
                                    <option value="0" ${subject?.is_optional === 0 ? 'selected' : ''}>Core (Mandatory)</option>
                                    <option value="1" ${subject?.is_optional === 1 ? 'selected' : ''}>Optional (Elective)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" id="subjectStatus">
                                    <option value="active" ${subject?.status === 'active' ? 'selected' : ''}>Active</option>
                                    <option value="inactive" ${subject?.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-academic-primary" onclick="academicsManager.saveSubject(${subjectId})">
                            <i class="bi bi-save"></i> Save Learning Area
                        </button>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        modal.addEventListener('hidden.bs.modal', () => modal.remove());
    },

    async saveSubject(subjectId = null) {
        const data = {
            name: document.getElementById('subjectName').value.trim(),
            code: document.getElementById('subjectCode').value.trim(),
            description: document.getElementById('subjectDesc').value.trim() || null,
            levels: document.getElementById('subjectLevels').value.trim() || null,
            is_optional: parseInt(document.getElementById('subjectCategory').value),
            status: document.getElementById('subjectStatus').value
        };

        if (!data.name || !data.code) {
            this.showToast('Please fill in all required fields', 'warning');
            return;
        }

        try {
            let response;
            if (subjectId) {
                response = await window.API.academic.updateLearningArea(subjectId, data);
            } else {
                response = await window.API.academic.createLearningArea(data);
            }

            // If no error thrown, operation succeeded
            this.showToast(`Learning area ${subjectId ? 'updated' : 'created'} successfully`, 'success');
            bootstrap.Modal.getInstance(document.querySelector('.modal')).hide();
            await this.loadSubjects();
            await this.loadStatistics();
        } catch (error) {
            this.showToast(`Failed to ${subjectId ? 'update' : 'create'} learning area: ${error.message}`, 'error');
        }
    },

    editSubject(subjectId) {
        this.showSubjectModal(subjectId);
    },

    async deleteSubject(subjectId) {
        if (!confirm('Are you sure you want to delete this learning area?')) return;

        try {
            const response = await window.API.academic.deleteLearningArea(subjectId);
            // If no error thrown, operation succeeded
            this.showToast('Learning area deleted successfully', 'success');
            await this.loadSubjects();
            await this.loadStatistics();
        } catch (error) {
            this.showToast(`Failed to delete learning area: ${error.message}`, 'error');
        }
    },

    searchSubjects(searchTerm) {
        if (!searchTerm) {
            this.renderSubjects();
            return;
        }
        const filtered = this.state.subjects.filter(s =>
            s.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            s.code.toLowerCase().includes(searchTerm.toLowerCase())
        );
        this.renderSubjects(filtered);
    },

    filterSubjectsByStatus(status) {
        if (!status) {
            this.renderSubjects();
            return;
        }
        const filtered = this.state.subjects.filter(s => s.status === status);
        this.renderSubjects(filtered);
    },

    // ==================== STREAMS ====================
    async loadStreams() {
        try {
            const response = await window.API.academic.listStreams();
            // Handle both flat array and nested data structure
            const data = Array.isArray(response) ? response : (Array.isArray(response?.data) ? response.data : []);
            
            if (data) {
                this.state.streams = data;
                this.renderStreams();
            }
        } catch (error) {
            this.showToast('Failed to load streams', 'error');
            console.error(error);
        }
    },

    renderStreams() {
        const container = document.getElementById('streamsContainer');
        if (!this.state.streams.length) {
            container.innerHTML = '<p class="text-muted text-center py-4">No streams found. Click "Add Stream" to create one.</p>';
            return;
        }

        container.innerHTML = `
            <table class="table table-academic table-hover">
                <thead>
                    <tr>
                        <th>Class</th>
                        <th>Stream Name</th>
                        <th>Stream Teacher</th>
                        <th>Capacity</th>
                        <th>Current Students</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${this.state.streams.map(stream => `
                        <tr>
                            <td>${stream.class_name || '-'}</td>
                            <td><strong>${stream.stream_name}</strong></td>
                            <td>${stream.teacher_name || 'Not assigned'}</td>
                            <td>${stream.capacity || '-'}</td>
                            <td><span class="badge badge-academic">${stream.current_students || 0}</span></td>
                            <td><span class="badge ${stream.status === 'active' ? 'bg-success' : 'bg-secondary'}">${stream.status}</span></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="academicsManager.editStream(${stream.id})" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    },

    showStreamModal(streamId = null) {
        this.showToast('Stream creation modal - Coming soon', 'info');
        // TODO: Implement stream creation modal
    },

    editStream(streamId) {
        this.showToast('Stream editing - Coming soon', 'info');
        // TODO: Implement stream editing
    },

    // ==================== TEACHERS ====================
    async loadTeachers() {
        try {
            // Load teachers list from API
            // TODO: Implement when teacher endpoint is available
            this.state.teachers = [];
        } catch (error) {
            console.error('Failed to load teachers:', error);
        }
    },

    async loadTeacherDetails(teacherId) {
        if (!teacherId) {
            document.getElementById('teacherDetailsContainer').innerHTML = '';
            return;
        }

        try {
            const [classesRes, subjectsRes, scheduleRes] = await Promise.all([
                window.API.academic.getTeacherClasses(teacherId),
                window.API.academic.getTeacherSubjects(teacherId),
                window.API.academic.getTeacherSchedule(teacherId)
            ]);

            this.renderTeacherDetails(teacherId, classesRes.data, subjectsRes.data, scheduleRes.data);
        } catch (error) {
            this.showToast('Failed to load teacher details', 'error');
        }
    },

    renderTeacherDetails(teacherId, classes, subjects, schedule) {
        const container = document.getElementById('teacherDetailsContainer');
        container.innerHTML = `
            <div class="row">
                <div class="col-md-4">
                    <div class="card academic-card mb-3">
                        <div class="card-body">
                            <h6 class="card-title">Assigned Classes</h6>
                            <ul class="list-unstyled">
                                ${classes?.length ? classes.map(c => `<li><i class="bi bi-journal-bookmark"></i> ${c.name}</li>`).join('') : '<li class="text-muted">No classes assigned</li>'}
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card academic-card mb-3">
                        <div class="card-body">
                            <h6 class="card-title">Assigned Subjects</h6>
                            <ul class="list-unstyled">
                                ${subjects?.length ? subjects.map(s => `<li><i class="bi bi-book"></i> ${s.name}</li>`).join('') : '<li class="text-muted">No subjects assigned</li>'}
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card academic-card mb-3">
                        <div class="card-body">
                            <h6 class="card-title">Schedule</h6>
                            <div id="teacherSchedule">
                                ${schedule?.length ? '<p class="text-muted">Schedule details...</p>' : '<p class="text-muted">No schedule assigned</p>'}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },

    // ==================== QUICK ACTIONS ====================
    showQuickActions() {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><i class="bi bi-lightning-fill"></i> Quick Actions</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="list-group list-group-flush">
                            <button class="list-group-item list-group-item-action" onclick="academicsManager.showYearModal(); bootstrap.Modal.getInstance(document.querySelector('.modal')).hide();">
                                <i class="bi bi-calendar-plus text-success"></i> Add New Academic Year
                            </button>
                            <button class="list-group-item list-group-item-action" onclick="academicsManager.showTermModal(); bootstrap.Modal.getInstance(document.querySelector('.modal')).hide();">
                                <i class="bi bi-calendar-week text-success"></i> Add New Term
                            </button>
                            <button class="list-group-item list-group-item-action" onclick="academicsManager.showClassModal(); bootstrap.Modal.getInstance(document.querySelector('.modal')).hide();">
                                <i class="bi bi-journal-bookmark text-success"></i> Add New Class
                            </button>
                            <button class="list-group-item list-group-item-action" onclick="academicsManager.showSubjectModal(); bootstrap.Modal.getInstance(document.querySelector('.modal')).hide();">
                                <i class="bi bi-book text-success"></i> Add New Learning Area
                            </button>
                            <button class="list-group-item list-group-item-action" onclick="academicsManager.showStreamModal(); bootstrap.Modal.getInstance(document.querySelector('.modal')).hide();">
                                <i class="bi bi-diagram-3 text-success"></i> Add New Stream
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        modal.addEventListener('hidden.bs.modal', () => modal.remove());
    },

    // ==================== EVENT LISTENERS ====================
    setupEventListeners() {
        // Tab change events
        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', (e) => {
                const target = e.target.getAttribute('data-bs-target');
                if (target === '#schedulesTab') this.loadSchedules();
                if (target === '#curriculumTab') this.loadCurriculum();
            });
        });
    },

    // ==================== PLACEHOLDERS FOR FUTURE IMPLEMENTATION ====================
    async loadSchedules() {
        this.showToast('Schedules/Timetable module - Implementation in progress', 'info');
    },

    async loadCurriculum() {
        this.showToast('Curriculum module - Implementation in progress', 'info');
    },

    showScheduleModal() {
        this.showToast('Schedule creation - Implementation in progress', 'info');
    },

    showCurriculumUnitModal() {
        this.showToast('Curriculum unit creation - Implementation in progress', 'info');
    },

    showTopicModal() {
        this.showToast('Topic creation - Implementation in progress', 'info');
    },

    showLessonPlanModal() {
        this.showToast('Lesson plan creation - Implementation in progress', 'info');
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    // Wait for AuthContext to be available and token to be set
    const initWhenReady = () => {
        if (typeof AuthContext === 'undefined') {
            console.warn('AuthContext not loaded yet, waiting...');
            setTimeout(initWhenReady, 100);
            return;
        }
        
        if (!AuthContext.isAuthenticated()) {
            console.error('User not authenticated - cannot initialize academicsManager');
            return;
        }
        
        const token = localStorage.getItem('token');
        if (!token) {
            console.error('No token found in localStorage');
            return;
        }
        
        console.log('✓ AuthContext ready, token present - initializing academicsManager');
        academicsManager.init();
    };
    
    initWhenReady();
});
