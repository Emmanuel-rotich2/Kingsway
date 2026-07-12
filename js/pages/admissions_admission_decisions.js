/**
 * Admission Decisions Controller
 * Handles headteacher decision-making for admission applications
 */
const admissionDecisionsController = {
    applications: [],
    filteredApplications: [],
    
    init: function() {
        console.log('Initializing Admission Decisions Controller');
        this.loadApplications();
        this.setupEventListeners();
    },
    
    loadApplications: function() {
        document.getElementById('applicationsGrid').innerHTML = `
            <div class="col-12 text-center py-4">
                <div class="spinner-border text-success" role="status"></div>
                <div class="mt-2 text-muted">Loading applications...</div>
            </div>
        `;
        
        API.callAPI('/admission/queues', 'GET')
            .then(response => {
                if (response.success && response.data) {
                    // Focus on placement queue (interviews completed, awaiting decision)
                    const decisionApplications = [];
                    const queues = response.data.queues || {};
                    
                    // Add applications from placement queue
                    if (queues.placement_pending && Array.isArray(queues.placement_pending)) {
                        queues.placement_pending.forEach(app => {
                            decisionApplications.push({
                                ...app,
                                queue_name: 'placement_pending',
                                decision_status: 'pending'
                            });
                        });
                    }
                    
                    this.applications = decisionApplications;
                    this.applyFilters();
                    this.updateSummaryCards();
                }
            })
            .catch(error => {
                console.error('Failed to load applications:', error);
                this.showError('Failed to load applications');
            });
    },
    
    updateSummaryCards: function() {
        const pending = this.applications.filter(app => app.decision_status === 'pending').length;
        const approved = this.applications.filter(app => app.decision_status === 'approved').length;
        const rejected = this.applications.filter(app => app.decision_status === 'rejected').length;
        const waitlisted = this.applications.filter(app => app.decision_status === 'waitlisted').length;
        
        document.getElementById('statPendingDecision').textContent = pending;
        document.getElementById('statApproved').textContent = approved;
        document.getElementById('statRejected').textContent = rejected;
        document.getElementById('statWaitlisted').textContent = waitlisted;
    },
    
    applyFilters: function() {
        const decisionStatus = document.getElementById('filterDecisionStatus').value;
        const classFilter = document.getElementById('filterClass').value;
        const searchTerm = document.getElementById('searchApplications').value.toLowerCase();
        
        this.filteredApplications = this.applications.filter(app => {
            if (decisionStatus && app.decision_status !== decisionStatus) return false;
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
        
        this.renderApplicationsGrid();
    },
    
    renderApplicationsGrid: function() {
        const grid = document.getElementById('applicationsGrid');
        
        if (this.filteredApplications.length === 0) {
            grid.innerHTML = `
                <div class="col-12 text-center py-4">
                    <div class="text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        No applications found
                    </div>
                </div>
            `;
            return;
        }
        
        grid.innerHTML = this.filteredApplications.map(app => {
            const interviewScore = this.extractInterviewScore(app);
            const interviewDate = this.extractInterviewDate(app);
            
            return `
                <div class="col-md-6 col-lg-4">
                    <div class="card decision-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h6 class="mb-1">${app.applicant_name || 'Unknown'}</h6>
                                    <small class="text-muted">${app.application_no || '—'}</small>
                                </div>
                                <span class="badge bg-info">${app.grade_applying_for || '—'}</span>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <small class="text-muted">Interview Score:</small>
                                    <small class="fw-semibold">${interviewScore || '—'}</small>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <small class="text-muted">Interview Date:</small>
                                    <small>${interviewDate || '—'}</small>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted">Documents:</small>
                                    <small class="text-success">Verified</small>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-primary flex-grow-1" onclick="admissionDecisionsController.viewApplication(${app.id})">
                                    <i class="bi bi-eye me-1"></i>View
                                </button>
                                <button class="btn btn-sm btn-success flex-grow-1" onclick="admissionDecisionsController.makeDecision(${app.id})">
                                    <i class="bi bi-check-square me-1"></i>Decide
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
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
        document.getElementById('filterDecisionStatus').addEventListener('change', () => this.applyFilters());
        document.getElementById('filterClass').addEventListener('change', () => this.applyFilters());
        document.getElementById('searchApplications').addEventListener('input', this.debounce(() => this.applyFilters(), 300));
        
        // Decision type change - show/hide conditions
        document.getElementById('decision').addEventListener('change', function() {
            const conditionsGroup = document.getElementById('conditionsGroup');
            const decision = this.value;
            
            if (decision === 'approved' || decision === 'waitlisted' || decision === 'more_info_required') {
                conditionsGroup.style.display = 'block';
            } else {
                conditionsGroup.style.display = 'none';
            }
        });
        
        // Make decision form submission
        const decisionForm = document.getElementById('makeDecisionForm');
        if (decisionForm) {
            decisionForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.submitDecision();
            });
        }
    },
    
    viewApplication: function(applicationId) {
        API.callAPI(`/admission/application/${applicationId}`, 'GET')
            .then(response => {
                if (response.success && response.data) {
                    this.renderApplicationDetails(response.data);
                    const modal = new bootstrap.Modal(document.getElementById('viewApplicationModal'));
                    modal.show();
                    
                    // Setup make decision button
                    const makeDecisionBtn = document.getElementById('makeDecisionBtn');
                    makeDecisionBtn.onclick = () => {
                        modal.hide();
                        this.makeDecision(applicationId);
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
        const workflowData = data.workflow_data || {};
        
        let interviewResultsHtml = '';
        if (workflowData.interview_score !== undefined) {
            interviewResultsHtml = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between mb-1">
                            <small>Academic Readiness:</small>
                            <small class="fw-semibold">${workflowData.academic_readiness_score || '—'}</small>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <small>Behavior/Social:</small>
                            <small class="fw-semibold">${workflowData.behavior_score || '—'}</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between mb-1">
                            <small>Communication:</small>
                            <small class="fw-semibold">${workflowData.communication_score || '—'}</small>
                        </div>
                        <div class="d-flex justify-content-between">
                            <small>Overall Score:</small>
                            <small class="fw-bold text-primary">${workflowData.interview_score || '—'}/100</small>
                        </div>
                    </div>
                </div>
                ${workflowData.interview_remarks ? `<div class="mt-2"><small class="text-muted">Remarks: ${workflowData.interview_remarks}</small></div>` : ''}
            `;
        }
        
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
                        <tr><td><strong>Email:</strong></td><td>${app.parent_email || '—'}</td></tr>
                    </table>
                    
                    <h6 class="fw-semibold mb-3 mt-4">Application Status</h6>
                    <div class="mb-2"><span class="badge bg-info">${app.current_stage || '—'}</span></div>
                    <div class="small text-muted">Status: ${app.status || '—'}</div>
                </div>
            </div>
            
            ${interviewResultsHtml ? `
                <div class="mt-4">
                    <h6 class="fw-semibold mb-3">Interview Results</h6>
                    <div class="card">
                        <div class="card-body">
                            ${interviewResultsHtml}
                        </div>
                    </div>
                </div>
            ` : ''}
            
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
    
    makeDecision: function(applicationId) {
        document.getElementById('decisionApplicationId').value = applicationId;
        
        // Load applicant details for the modal
        API.callAPI(`/admission/application/${applicationId}`, 'GET')
            .then(response => {
                if (response.success && response.data) {
                    const app = response.data.application;
                    const workflowData = response.data.workflow_data || {};
                    
                    const summary = `
                        <strong>Applicant:</strong> ${app.applicant_name}<br>
                        <strong>Grade:</strong> ${app.grade_applying_for}<br>
                        <strong>Application No:</strong> ${app.application_no}<br>
                        <strong>Interview Score:</strong> ${workflowData.interview_score || '—'}/100
                    `;
                    document.getElementById('applicantSummary').innerHTML = summary;
                    
                    // Show interview results if available
                    if (workflowData.interview_score !== undefined) {
                        document.getElementById('interviewResultsCard').style.display = 'block';
                        document.getElementById('interviewResultsContent').innerHTML = `
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>Academic Readiness:</small>
                                        <small class="fw-semibold">${workflowData.academic_readiness_score || '—'}</small>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <small>Behavior/Social:</small>
                                        <small class="fw-semibold">${workflowData.behavior_score || '—'}</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>Communication:</small>
                                        <small class="fw-semibold">${workflowData.communication_score || '—'}</small>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <small>Overall Score:</small>
                                        <small class="fw-bold text-primary">${workflowData.interview_score || '—'}/100</small>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        document.getElementById('interviewResultsCard').style.display = 'none';
                    }
                    
                    const modal = new bootstrap.Modal(document.getElementById('makeDecisionModal'));
                    modal.show();
                }
            })
            .catch(error => {
                console.error('Failed to load applicant details:', error);
                showNotification('error', 'Failed to load applicant details');
            });
    },
    
    submitDecision: function() {
        const applicationId = document.getElementById('decisionApplicationId').value;
        const submitBtn = document.querySelector('#makeDecisionForm button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
        
        const decisionData = {
            decision: document.getElementById('decision').value,
            recommended_class: document.getElementById('recommendedClass').value,
            conditions: document.getElementById('decisionConditions').value,
            remarks: document.getElementById('decisionRemarks').value
        };
        
        // Call the placement offer API for approved decisions
        if (decisionData.decision === 'approved') {
            API.callAPI('/admission/generate-placement-offer', 'POST', {
                application_id: applicationId,
                assigned_class_id: decisionData.recommended_class
            })
                .then(response => {
                    if (response.success) {
                        showNotification('success', 'Admission decision recorded successfully');
                        bootstrap.Modal.getInstance(document.getElementById('makeDecisionModal')).hide();
                        document.getElementById('makeDecisionForm').reset();
                        this.loadApplications();
                    } else {
                        showNotification('error', response.message || 'Failed to record decision');
                    }
                })
                .catch(error => {
                    console.error('Failed to submit decision:', error);
                    showNotification('error', 'Failed to submit decision');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Submit Decision';
                });
        } else {
            // For other decisions, we would need a dedicated decision API endpoint
            // For now, show a message that this needs to be implemented
            showNotification('warning', 'Decision type requires additional API endpoint');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Submit Decision';
        }
    },
    
    refreshData: function() {
        this.loadApplications();
    },
    
    showError: function(message) {
        document.getElementById('applicationsGrid').innerHTML = `
            <div class="col-12 text-center py-4">
                <div class="text-danger">
                    <i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>
                    ${message}
                </div>
            </div>
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
