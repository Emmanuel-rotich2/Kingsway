/**
 * Academic Applications Controller
 * Handles Deputy Academic view for class placement and academic assessment
 */
const academicApplicationsController = {
    applications: [],
    filteredApplications: [],
    classes: [],
    placementTests: [],
    initialized: false,

    init: async function() {
        if (this.initialized) return;
        this.initialized = true;


        try {
            await window.AuthContext?.ready();
            if (window.AuthContext && typeof window.AuthContext.isAuthenticated === "function") {
                await window.AuthContext?.ready();
                if (!window.AuthContext.isAuthenticated()) {
                    console.warn("academicApplicationsController: Not authenticated, redirecting to login");
                    window.location.href = `${window.APP_BASE || ""}/index.php`;
                    return;
                }
            } else {
                console.warn("academicApplicationsController: AuthContext not available");
            }

            this.setupEventListeners();
            await Promise.all([this.loadClasses(), this.loadPlacementTests()]);
            await this.loadApplications();

        } catch (error) {
            console.error("Failed to initialize Academic Applications Controller:", error);
            this.showError(error.message || "Failed to initialize academic applications page.");
        }
    },

    loadPlacementTests: async function() {
        try {
            const response = await API.callAPI('/admission/placement-tests', 'GET');
            const payload = response?.data || response || {};
            this.placementTests = payload.tests || payload.placement_tests || [];
        } catch (error) {
            console.error('Failed to load placement tests:', error);
            this.placementTests = [];
        }
    },

    loadClasses: async function() {
        try {
            const response = await API.callAPI('/admission/placement-classes', 'GET');
            const payload = response?.data || response || {};
            this.classes = payload.classes || [];
            this.populateClassDropdown();
        } catch (error) {
            console.error('Failed to load classes:', error);
        }
    },
    
    populateClassDropdown: function() {
        const select = document.getElementById('recommendedClass');
        if (!select) return;
        
        select.innerHTML = '<option value="">Select Class</option>';
        this.classes.forEach(cls => {
            const option = document.createElement('option');
            option.value = cls.id;
            option.textContent = `${cls.name} (${cls.student_count}/${cls.capacity || '∞'})`;
            option.dataset.capacity = cls.capacity;
            option.dataset.studentCount = cls.student_count;
            select.appendChild(option);
        });

        this.populateFilterClasses();
    },

    populateFilterClasses: function() {
        const filterSelect = document.getElementById('filterClass');
        if (!filterSelect) return;
        const currentVal = filterSelect.value;
        filterSelect.innerHTML = '<option value="">All Classes</option>';
        this.classes.forEach(cls => {
            const option = document.createElement('option');
            option.value = cls.name || cls.class_name || cls.id;
            option.textContent = cls.name || cls.class_name || cls.id;
            filterSelect.appendChild(option);
        });
        if (currentVal) filterSelect.value = currentVal;
    },
    
    loadApplications: async function() {
        document.getElementById('applicationsTableBody').innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-4">
                    <div class="spinner-border text-warning" role="status"></div>
                    <div class="mt-2 text-muted">Loading applications...</div>
                </td>
            </tr>
        `;

        try {
            const response = await API.callAPI('/admission/queues', 'GET');
            const payload = response?.data || response || {};
            const queues = payload.queues || {};
            const allApplications = Array.isArray(queues.all_applications)
                ? queues.all_applications
                : this.combineQueues(queues);

            this.applications = allApplications.map(app => ({
                ...app,
                placement_status: app.current_stage || app.status || 'unknown'
            }));
            this.populateStageFilter();
            this.applyFilters();
            this.updateSummaryCards(payload.summary || {});
        } catch (error) {
            console.error('Failed to load applications:', error);
            this.showError('Failed to load applications');
        }
    },

    combineQueues: function(queues) {
        const rows = new Map();
        Object.entries(queues).forEach(([queueName, queueRows]) => {
            if (!Array.isArray(queueRows)) return;
            queueRows.forEach(app => rows.set(String(app.id), { ...app, queue_name: queueName }));
        });
        return [...rows.values()];
    },

    populateStageFilter: function() {
        const select = document.getElementById('filterPlacementStatus');
        if (!select) return;
        const selected = select.value;
        const stages = [...new Set(this.applications.map(app => app.current_stage || app.status).filter(Boolean))];
        stages.sort((a, b) => this.stageOrder(a) - this.stageOrder(b));
        select.innerHTML = '<option value="">All Workflow Stages</option>' + stages
            .map(stage => `<option value="${this.escapeHtml(stage)}">${this.escapeHtml(this.stageLabel(stage))}</option>`)
            .join('');
        if (stages.includes(selected)) select.value = selected;
    },
    
    updateSummaryCards: function(summary) {
        const placedStages = ['fees_payment', 'student_id_generation', 'final_enrollment', 'enrolled'];
        const placed = this.applications.filter(app => app.assigned_class_name || placedStages.includes(app.current_stage)).length;
        const testedApplicationIds = new Set(this.placementTests.map(test => String(test.application_id)));
        const testsRequired = this.applications.filter(app => {
            const match = String(app.grade_applying_for || '').match(/Grade\s*(\d+)/i);
            const requiresTest = match && Number(match[1]) >= 4 && Number(match[1]) <= 9;
            return requiresTest && !['enrolled', 'rejected'].includes(app.current_stage) && !testedApplicationIds.has(String(app.id));
        }).length;
        const uniqueStreams = new Map();
        this.classes.forEach(row => {
            const key = String(row.academic_year_class_stream_id || `${row.id}-${row.stream_id || ''}`);
            if (!uniqueStreams.has(key)) uniqueStreams.set(key, row);
        });
        const capacity = [...uniqueStreams.values()].reduce((total, row) => total + Number(row.capacity || 0), 0);
        const students = [...uniqueStreams.values()].reduce((total, row) => total + Number(row.student_count || 0), 0);
        const capacityPercentage = capacity > 0 ? Math.round((students / capacity) * 100) : 0;

        document.getElementById('statPendingPlacement').textContent = summary.placement_pending || 0;
        document.getElementById('statPlaced').textContent = placed;
        document.getElementById('statPlacementTests').textContent = testsRequired;
        document.getElementById('statCapacity').textContent = `${capacityPercentage}%`;
    },
    
    applyFilters: function() {
        const placementStatus = document.getElementById('filterPlacementStatus')?.value || '';
        const classFilter = document.getElementById('filterClass')?.value || '';
        const searchTerm = (document.getElementById('searchApplications')?.value || '').toLowerCase();
        
        this.filteredApplications = this.applications.filter(app => {
            if (placementStatus && app.placement_status !== placementStatus) return false;
            if (classFilter && app.grade_applying_for !== classFilter) return false;
            if (searchTerm) {
                const searchFields = [
                    app.applicant_name,
                    app.application_no
                ].join(' ').toLowerCase();
                if (!searchFields.includes(searchTerm)) return false;
            }
            return true;
        });
        
        this.renderApplicationsTable();
    },
    
    renderApplicationsTable: function() {
        const tbody = document.getElementById('applicationsTableBody');
        
        if (this.filteredApplications.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <div class="text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            No applications found
                        </div>
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = this.filteredApplications.map(app => {
            const interviewScore = this.extractInterviewScore(app);
            const placementStatusBadge = this.getPlacementStatusBadge(app.current_stage || app.status);
            const recommendedClass = this.extractRecommendedClass(app);
            const canOpenPlacement = (app.current_stage === 'class_placement') && this.canManagePlacement();
            
            return `
                <tr>
                    <td><strong>${this.escapeHtml(app.application_no || '—')}</strong></td>
                    <td>${this.escapeHtml(app.applicant_name || 'Unknown')}</td>
                    <td>${this.escapeHtml(app.grade_applying_for || '—')}</td>
                    <td>${interviewScore || '—'}</td>
                    <td>${placementStatusBadge}</td>
                    <td>${this.escapeHtml(recommendedClass || '—')}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="academicApplicationsController.viewApplication(${app.id})" title="View">
                                <i class="bi bi-eye"></i>
                            </button>
                            ${canOpenPlacement ? `<button class="btn btn-outline-warning" onclick="academicApplicationsController.openPlacementWorkspace()" title="Open class placement workspace"><i class="bi bi-award"></i></button>` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    },
    
    extractInterviewScore: function(app) {
        if (app.data_json) {
            try {
                const data = JSON.parse(app.data_json);
                if (data.interview_score !== undefined) {
                    return data.interview_score + '/100';
                }
            } catch (e) {
                console.error('Failed to parse interview data:', e);
            }
        }
        return '—';
    },
    
    extractRecommendedClass: function(app) {
        if (app.data_json) {
            try {
                const data = JSON.parse(app.data_json);
                return data.assigned_class_name || data.recommended_class || '—';
            } catch (e) {
                console.error('Failed to parse placement data:', e);
            }
        }
        return '—';
    },
    
    getPlacementStatusBadge: function(status) {
        const badges = {
            'application_applied': 'secondary',
            'application_received': 'info',
            'application_review': 'info',
            'interview_scheduling': 'warning',
            'interview_results': 'warning',
            'student_admission_number': 'primary',
            'class_placement': 'primary',
            'fees_payment': 'warning',
            'student_id_generation': 'info',
            'final_enrollment': 'primary',
            'enrolled': 'success',
            'rejected': 'danger',
            'cancelled': 'dark'
        };
        return `<span class="badge bg-${badges[status] || 'secondary'}">${this.escapeHtml(this.stageLabel(status))}</span>`;
    },

    stageLabel: function(stage) {
        const labels = {
            application_applied: 'Applied', application_received: 'Received', application_review: 'Under Review',
            interview_scheduling: 'Interview Scheduling', interview_results: 'Interview Results',
            student_admission_number: 'Admission Number', class_placement: 'Class Placement',
            fees_payment: 'Fees / Transport / Uniform', student_id_generation: 'ID Generation',
            final_enrollment: 'Final Enrollment', enrolled: 'Enrolled', rejected: 'Rejected', cancelled: 'Cancelled'
        };
        return labels[stage] || String(stage || 'Unknown').replace(/_/g, ' ').replace(/\b\w/g, char => char.toUpperCase());
    },

    stageOrder: function(stage) {
        return ['application_applied', 'application_received', 'application_review', 'interview_scheduling',
            'interview_results', 'student_admission_number', 'class_placement', 'fees_payment',
            'student_id_generation', 'final_enrollment', 'enrolled', 'rejected', 'cancelled'].indexOf(stage);
    },

    canManagePlacement: function() {
        return window.AuthContext?.hasPermission?.('admission_manage')
            || window.AuthContext?.hasPermission?.('admission_applications_edit')
            || window.AuthContext?.hasRole?.('Deputy Head - Academic')
            || window.AuthContext?.hasRole?.('School Administrator');
    },

    openPlacementWorkspace: function() {
        window.location.href = `${window.APP_BASE || ''}/home.php?route=admissions_class_placement`;
    },

    escapeHtml: function(value) {
        const node = document.createElement('div');
        node.textContent = String(value ?? '');
        return node.innerHTML;
    },
    
    setupEventListeners: function() {
        // Filter changes
        document.getElementById('filterPlacementStatus')?.addEventListener('change', () => this.applyFilters());
        document.getElementById('filterClass')?.addEventListener('change', () => this.applyFilters());
        document.getElementById('searchApplications')?.addEventListener('input', this.debounce(() => this.applyFilters(), 300));

        // Class selection change - show capacity
        document.getElementById('recommendedClass')?.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const capacityCard = document.getElementById('classCapacityCard');
            if (!capacityCard) return;
            
            if (selectedOption.value && selectedOption.dataset.capacity) {
                const capacity = parseInt(selectedOption.dataset.capacity);
                const studentCount = parseInt(selectedOption.dataset.studentCount) || 0;
                const percentage = capacity > 0 ? Math.round((studentCount / capacity) * 100) : 0;
                
                document.getElementById('classCapacityText').textContent = `${studentCount}/${capacity} (${percentage}%)`;
                document.getElementById('classCapacityFill').style.width = percentage + '%';
                
                // Color based on capacity
                const fill = document.getElementById('classCapacityFill');
                if (percentage >= 90) {
                    fill.className = 'capacity-fill bg-danger';
                } else if (percentage >= 75) {
                    fill.className = 'capacity-fill bg-warning';
                } else {
                    fill.className = 'capacity-fill bg-success';
                }
                
                capacityCard.style.display = 'block';
            } else {
                capacityCard.style.display = 'none';
            }
        });
        
        // Class placement form submission
        const placementForm = document.getElementById('classPlacementForm');
        if (placementForm) {
            placementForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.submitPlacement();
            });
        }
    },
    
    viewApplication: function(applicationId) {
        API.callAPI(`/admission/application/${applicationId}`, 'GET')
            .then(response => {
                const payload = response?.data || response || {};
                if (payload.application) {
                    this.renderApplicationDetails(payload);
                    const modal = new bootstrap.Modal(document.getElementById('viewApplicationModal'));
                    modal.show();
                    
                    // Setup make placement button
                    const makePlacementBtn = document.getElementById('makePlacementBtn');
                    makePlacementBtn.onclick = () => {
                        modal.hide();
                        this.makePlacement(applicationId);
                    };
                }
            })
            .catch(error => {
                console.error('Failed to load application details:', error);
                showNotification('error', 'Failed to load application details');
            });
    },
    
    renderApplicationDetails: function(data) {
        const app = data.application;
        const workflowData = data.workflow_data || {};
        
        const html = `
            <div class="row">
                <div class="col-md-6">
                    <h6 class="fw-semibold mb-3">Applicant Information</h6>
                    <table class="table table-sm">
                        <tr><td><strong>Application No:</strong></td><td>${app.application_no || '—'}</td></tr>
                        <tr><td><strong>Name:</strong></td><td>${app.applicant_name || '—'}</td></tr>
                        <tr><td><strong>Date of Birth:</strong></td><td>${this.formatDate(app.date_of_birth)}</td></tr>
                        <tr><td><strong>Gender:</strong></td><td>${app.gender || '—'}</td></tr>
                        <tr><td><strong>Grade Applying For:</strong></td><td>${app.grade_applying_for || '—'}</td></tr>
                        <tr><td><strong>Previous School:</strong></td><td>${app.previous_school || '—'}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-semibold mb-3">Guardian Information</h6>
                    <table class="table table-sm">
                        <tr><td><strong>Name:</strong></td><td>${app.parent_first_name || ''} ${app.parent_last_name || ''}</td></tr>
                        <tr><td><strong>Phone:</strong></td><td>${app.parent_phone_1 || app.phone_1 || '—'}</td></tr>
                    </table>
                    
                    <h6 class="fw-semibold mb-3 mt-4">Academic Assessment</h6>
                    <div class="mb-2">
                        <small class="text-muted">Interview Score:</small>
                        <div class="fw-bold text-primary">${workflowData.interview_score || '—'}/100</div>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted">Placement Status:</small>
                        <div>${this.getPlacementStatusBadge('pending')}</div>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('viewApplicationContent').innerHTML = html;
    },
    
    makePlacement: function(applicationId) {
        document.getElementById('placementApplicationId').value = applicationId;
        
        // Load applicant details for the modal
        API.callAPI(`/admission/application/${applicationId}`, 'GET')
            .then(response => {
                const payload = response?.data || response || {};
                if (payload.application) {
                    const app = payload.application;
                    const workflowData = payload.workflow_data || {};
                    
                    if (!window.AdmissionPlacementModal) throw new Error('The standard placement modal is unavailable. Please reload the page.');
                    window.AdmissionPlacementModal.open({
                        application: app,
                        classes: this.classes,
                        apiCall: API.callAPI.bind(API),
                        onSuccess: () => this.loadApplications()
                    });
                }
            })
            .catch(error => {
                console.error('Failed to load applicant details:', error);
                showNotification('error', 'Failed to load applicant details');
            });
    },
    
    submitPlacement: function() {
        const applicationId = document.getElementById('placementApplicationId').value;
        const submitBtn = document.querySelector('#classPlacementForm button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
        
        const placementData = {
            assigned_class_id: document.getElementById('recommendedClass').value,
            placement_type: document.getElementById('placementType').value,
            remarks: document.getElementById('placementRemarks').value
        };
        
        API.callAPI('/admission/generate-placement-offer', 'POST', {
            application_id: applicationId,
            ...placementData
        })
            .then(response => {
                showNotification('success', 'Class placement recorded successfully');
                bootstrap.Modal.getInstance(document.getElementById('classPlacementModal')).hide();
                document.getElementById('classPlacementForm').reset();
                this.loadApplications();
            })
            .catch(error => {
                console.error('Failed to submit placement:', error);
                showNotification('error', 'Failed to submit placement');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Submit Placement';
            });
    },
    
    refreshData: function() {
        this.loadApplications();
    },
    
    showError: function(message) {
        document.getElementById('applicationsTableBody').innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-4">
                    <div class="text-danger">
                        <i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>
                        ${message}
                    </div>
                </td>
            </tr>
        `;
    },
    
    formatDate: function(dateString) {
        if (!dateString) return '—';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    },
    
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
};

window.academicApplicationsController = academicApplicationsController;

function initAcademicApplicationsWhenAPIReady() {
    const hasApi = window.API && typeof window.API.callAPI === "function";

    if (hasApi) {
        window.academicApplicationsController.init();
        return;
    }

    setTimeout(initAcademicApplicationsWhenAPIReady, 100);
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initAcademicApplicationsWhenAPIReady);
} else {
    initAcademicApplicationsWhenAPIReady();
}
