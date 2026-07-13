/**
 * Headteacher Applications Controller
 * Handles the headteacher-focused view of applications for interviews and decisions
 */
const headteacherApplicationsController = {
    applications: [],
    filteredApplications: [],
    initialized: false,

    init: async function() {
        if (this.initialized) return;
        this.initialized = true;

        console.log("headteacherApplicationsController: Initializing...");

        try {
            if (window.AuthContext && typeof window.AuthContext.isAuthenticated === "function") {
                if (!window.AuthContext.isAuthenticated()) {
                    console.warn("headteacherApplicationsController: Not authenticated, redirecting to login");
                    window.location.href = `${window.APP_BASE || ""}/index.php`;
                    return;
                }
            } else {
                console.warn("headteacherApplicationsController: AuthContext not available");
            }

            this.setupEventListeners();
            await this.loadApplications();

            console.log("headteacherApplicationsController: Initialization complete");
        } catch (error) {
            console.error("Failed to initialize Headteacher Applications Controller:", error);
            this.showError(error.message || "Failed to initialize headteacher applications page.");
        }
    },

    loadApplications: async function() {
        document.getElementById('applicationsTableBody').innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="mt-2 text-muted">Loading applications...</div>
                </td>
            </tr>
        `;
        
        try {
            const response = await API.callAPI('/admission/queues', 'GET');
            const payload = response?.data || response || {};
            const reviewApplications = [];
            const queues = payload.queues || {};
            const queueOrder = [
                'review_pending',
                'documents_pending',
                'space_check_pending',
                'interview_pending',
                'decision_pending',
                'final_approval_pending',
                'payment_pending',
                'enrollment_pending',
                'id_generation_pending',
                'completed'
            ];

            queueOrder.forEach(queueName => {
                if (Array.isArray(queues[queueName])) {
                    queues[queueName].forEach(app => {
                        reviewApplications.push({
                            ...app,
                            queue_name: queueName
                        });
                    });
                }
            });

            this.applications = reviewApplications;
            this.applyFilters();
            this.updateSummaryCards(payload.summary || {});
        } catch (error) {
            console.error('Failed to load applications:', error);
            this.showError('Failed to load applications');
        }
    },
    
    updateSummaryCards: function(summary) {
        document.getElementById('statScheduledToday').textContent = summary.total_pending || 0;
        document.getElementById('statPendingInterview').textContent = summary.interview_pending || 0;
        document.getElementById('statCompletedThisWeek').textContent = summary.completed || 0;
        document.getElementById('statAwaitingDecision').textContent = summary.decision_pending || 0;
    },
    
    applyFilters: function() {
        const interviewStatus = document.getElementById('filterInterviewStatus')?.value || '';
        const classFilter = document.getElementById('filterClass')?.value || '';
        const searchTerm = (document.getElementById('searchApplications')?.value || '').toLowerCase();
        
        this.filteredApplications = this.applications.filter(app => {
            if (interviewStatus && app.current_stage !== interviewStatus && app.queue_name !== interviewStatus) return false;
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
                    <td colspan="8" class="text-center py-4">
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
            const statusBadge = this.getInterviewStatusBadge(app.current_stage);
            const interviewDate = this.extractInterviewDate(app);
            const interviewTime = this.extractInterviewTime(app);
            const docStatus = this.getDocumentStatus(app);
            
            const actionButton = ['interview_scheduling', 'interview_results'].includes(app.current_stage)
                ? `<button class="btn btn-outline-success" onclick="headteacherApplicationsController.conductInterview(${app.id})" title="Open Interview">
                                <i class="bi bi-clipboard-check"></i>
                            </button>`
                : '';

            return `
                <tr>
                    <td><strong>${app.application_no || '—'}</strong></td>
                    <td>${app.applicant_name || 'Unknown'}</td>
                    <td>${app.grade_applying_for || '—'}</td>
                    <td>${interviewDate || '—'}</td>
                    <td>${interviewTime || '—'}</td>
                    <td>${statusBadge}</td>
                    <td>${docStatus}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="headteacherApplicationsController.viewApplication(${app.id})" title="View">
                                <i class="bi bi-eye"></i>
                            </button>
                            ${actionButton}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    },
    
    getInterviewStatusBadge: function(stage) {
        const badges = {
            'review_pending': '<span class="badge bg-primary">Review Pending</span>',
            'documents_pending': '<span class="badge bg-warning text-dark">Documents Pending</span>',
            'space_check_pending': '<span class="badge bg-info text-dark">Space Check Pending</span>',
            'interview_scheduling': '<span class="badge bg-warning text-dark">Interview Scheduling</span>',
            'interview_results': '<span class="badge bg-info">Interview Assessment</span>',
            'decision_pending': '<span class="badge bg-success">Decision Pending</span>',
            'final_approval_pending': '<span class="badge bg-danger">Final Approval Pending</span>',
            'payment_pending': '<span class="badge bg-secondary">Payment Follow-up</span>',
            'enrollment_pending': '<span class="badge bg-dark">Enrollment Pending</span>',
            'id_generation_pending': '<span class="badge bg-secondary">ID Pending</span>',
            'completed': '<span class="badge bg-success">Completed</span>'
        };
        return badges[stage] || '<span class="badge bg-secondary">' + stage + '</span>';
    },
    
    extractInterviewDate: function(app) {
        if (app.data_json) {
            try {
                const data = JSON.parse(app.data_json);
                if (data.interview_date) {
                    return this.formatDate(data.interview_date);
                }
            } catch (e) {
                console.error('Failed to parse interview data:', e);
            }
        }
        return '—';
    },
    
    extractInterviewTime: function(app) {
        if (app.data_json) {
            try {
                const data = JSON.parse(app.data_json);
                return data.interview_time || '—';
            } catch (e) {
                console.error('Failed to parse interview data:', e);
            }
        }
        return '—';
    },
    
    getDocumentStatus: function(app) {
        // This would need to check actual document status
        return '<span class="badge bg-success">Verified</span>';
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
    
    setupEventListeners: function() {
        // Filter changes
        document.getElementById('filterInterviewStatus')?.addEventListener('change', () => this.applyFilters());
        document.getElementById('filterClass')?.addEventListener('change', () => this.applyFilters());
        document.getElementById('searchApplications')?.addEventListener('input', this.debounce(() => this.applyFilters(), 300));
        
        // Conduct interview form submission
        const interviewForm = document.getElementById('conductInterviewForm');
        if (interviewForm) {
            interviewForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.submitInterviewResults();
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
                    
                    // Setup conduct interview button
                    const conductBtn = document.getElementById('conductInterviewBtn');
                    conductBtn.onclick = () => {
                        modal.hide();
                        this.conductInterview(applicationId);
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
        const documents = data.documents || [];
        
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
                    </table>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-semibold mb-3">Guardian Information</h6>
                    <table class="table table-sm">
                        <tr><td><strong>Name:</strong></td><td>${app.parent_first_name || ''} ${app.parent_last_name || ''}</td></tr>
                        <tr><td><strong>Phone:</strong></td><td>${app.parent_phone_1 || app.phone_1 || '—'}</td></tr>
                    </table>
                    
                    <h6 class="fw-semibold mb-3 mt-4">Application Status</h6>
                    <div class="mb-2">${this.getInterviewStatusBadge(app.current_stage)}</div>
                </div>
            </div>
            
            <div class="mt-4">
                <h6 class="fw-semibold mb-3">Documents (${documents.length})</h6>
                ${documents.length > 0 ? `
                    <div class="row">
                        ${documents.map(doc => `
                            <div class="col-md-4 mb-2">
                                <div class="card">
                                    <div class="card-body py-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="small">${doc.document_type}</span>
                                            <span class="badge bg-success">${doc.verification_status}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                ` : '<p class="text-muted">No documents uploaded</p>'}
            </div>
        `;
        
        document.getElementById('viewApplicationContent').innerHTML = html;
    },
    
    conductInterview: function(applicationId) {
        document.getElementById('interviewApplicationId').value = applicationId;
        
        // Load applicant details for the modal
        API.callAPI(`/admission/application/${applicationId}`, 'GET')
            .then(response => {
                const payload = response?.data || response || {};
                if (payload.application) {
                    const app = payload.application;
                    const summary = `
                        <strong>Applicant:</strong> ${app.applicant_name}<br>
                        <strong>Grade:</strong> ${app.grade_applying_for}<br>
                        <strong>Application No:</strong> ${app.application_no}
                    `;
                    document.getElementById('applicantSummary').innerHTML = summary;
                    
                    const modal = new bootstrap.Modal(document.getElementById('conductInterviewModal'));
                    modal.show();
                }
            })
            .catch(error => {
                console.error('Failed to load applicant details:', error);
                showNotification('error', 'Failed to load applicant details');
            });
    },
    
    submitInterviewResults: function() {
        const applicationId = document.getElementById('interviewApplicationId').value;
        const submitBtn = document.querySelector('#conductInterviewForm button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
        
        const assessmentData = {
            academic_readiness_score: document.getElementById('academicReadinessScore').value,
            behavior_score: document.getElementById('behaviorScore').value,
            communication_score: document.getElementById('communicationScore').value,
            recommendation: document.getElementById('recommendation').value,
            next_step: document.getElementById('nextStep').value,
            remarks: document.getElementById('interviewRemarks').value
        };
        
        API.callAPI('/admission/record-interview-results', 'POST', {
            application_id: applicationId,
            assessment_data: assessmentData
        })
            .then(response => {
                if (response.success) {
                    showNotification('success', 'Interview results recorded successfully');
                    bootstrap.Modal.getInstance(document.getElementById('conductInterviewModal')).hide();
                    document.getElementById('conductInterviewForm').reset();
                    this.loadApplications();
                } else {
                    showNotification('error', response.message || 'Failed to record interview results');
                }
            })
            .catch(error => {
                console.error('Failed to submit interview results:', error);
                showNotification('error', 'Failed to submit interview results');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Submit Interview Results';
            });
    },
    
    refreshData: function() {
        this.loadApplications();
    },
    
    showError: function(message) {
        document.getElementById('applicationsTableBody').innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-4">
                    <div class="text-danger">
                        <i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>
                        ${message}
                    </div>
                </td>
            </tr>
        `;
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

window.headteacherApplicationsController = headteacherApplicationsController;

function initHeadteacherApplicationsWhenAPIReady() {
    const hasApi = window.API && typeof window.API.callAPI === "function";

    if (hasApi) {
        console.log("API is ready, initializing headteacher applications controller");
        window.headteacherApplicationsController.init();
        return;
    }

    setTimeout(initHeadteacherApplicationsWhenAPIReady, 100);
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initHeadteacherApplicationsWhenAPIReady);
} else {
    initHeadteacherApplicationsWhenAPIReady();
}
