/**
 * Pending Admission Approvals Controller
 * Handles final approval workflow for placement and fee-paid applications
 */
const pendingApprovalsController = {
    applications: [],
    filteredApplications: [],
    
    init: function() {
        console.log('Initializing Pending Admission Approvals Controller');
        this.loadApplications();
        this.setupEventListeners();
    },
    
    loadApplications: function() {
        document.getElementById('approvalsGrid').innerHTML = `
            <div class="col-12 text-center py-4">
                <div class="spinner-border text-danger" role="status"></div>
                <div class="mt-2 text-muted">Loading pending approvals...</div>
            </div>
        `;
        
        API.callAPI('/admission/queues', 'GET')
            .then(response => {
                if (response.success && response.data) {
                    // Focus on enrollment queue (placement done, fees paid, awaiting final approval)
                    const approvalApplications = [];
                    const queues = response.data.queues || {};
                    
                    // Add applications from enrollment queue
                    if (queues.enrollment_pending && Array.isArray(queues.enrollment_pending)) {
                        queues.enrollment_pending.forEach(app => {
                            approvalApplications.push({
                                ...app,
                                approval_status: 'ready'
                            });
                        });
                    }
                    
                    // Also check payment queue for approved payments
                    if (queues.payment_pending && Array.isArray(queues.payment_pending)) {
                        queues.payment_pending.forEach(app => {
                            const workflowData = app.data_json ? JSON.parse(app.data_json) : {};
                            if (workflowData.payment_status === 'paid') {
                                approvalApplications.push({
                                    ...app,
                                    approval_status: 'ready'
                                });
                            }
                        });
                    }
                    
                    this.applications = approvalApplications;
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
        const pending = this.applications.filter(app => app.approval_status === 'ready').length;
        const approvedToday = this.applications.filter(app => {
            if (app.approval_status === 'approved' && app.approved_at) {
                const approvedDate = new Date(app.approved_at);
                const today = new Date();
                return approvedDate.toDateString() === today.toDateString();
            }
            return false;
        }).length;
        
        document.getElementById('statPendingApproval').textContent = pending;
        document.getElementById('statApprovedToday').textContent = approvedToday;
        document.getElementById('statAvgProcessingTime').textContent = '2.5 days'; // Would calculate from actual data
        document.getElementById('statApprovalRate').textContent = '92%'; // Would calculate from actual data
    },
    
    applyFilters: function() {
        const approvalStatus = document.getElementById('filterApprovalStatus').value;
        const classFilter = document.getElementById('filterClass').value;
        const searchTerm = document.getElementById('searchApplications').value.toLowerCase();
        
        this.filteredApplications = this.applications.filter(app => {
            if (approvalStatus && app.approval_status !== approvalStatus) return false;
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
        
        this.renderApprovalsGrid();
    },
    
    renderApprovalsGrid: function() {
        const grid = document.getElementById('approvalsGrid');
        
        if (this.filteredApplications.length === 0) {
            grid.innerHTML = `
                <div class="col-12 text-center py-4">
                    <div class="text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        No pending approvals found
                    </div>
                </div>
            `;
            return;
        }
        
        grid.innerHTML = this.filteredApplications.map(app => {
            const approvalStatusClass = app.approval_status === 'ready' ? 'ready' : 'review';
            const readinessPercent = this.calculateReadinessPercentage(app);
            const assignedClass = this.extractAssignedClass(app);
            const paymentStatus = this.extractPaymentStatus(app);
            
            return `
                <div class="col-md-6 col-lg-4">
                    <div class="card approval-card ${approvalStatusClass} h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h6 class="mb-1">${app.applicant_name || 'Unknown'}</h6>
                                    <small class="text-muted">${app.application_no || '—'}</small>
                                </div>
                                <span class="badge bg-${app.approval_status === 'ready' ? 'success' : 'warning'}">${app.approval_status === 'ready' ? 'Ready' : 'Review'}</span>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <small class="text-muted">Grade:</small>
                                    <small>${app.grade_applying_for || '—'}</small>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <small class="text-muted">Assigned Class:</small>
                                    <small class="fw-semibold">${assignedClass || '—'}</small>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <small class="text-muted">Payment:</small>
                                    <small class="${paymentStatus === 'paid' ? 'text-success' : 'text-warning'}">${paymentStatus || '—'}</small>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted">Readiness:</small>
                                    <small class="fw-bold text-primary">${readinessPercent}%</small>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-primary flex-grow-1" onclick="pendingApprovalsController.viewApplication(${app.id})">
                                    <i class="bi bi-eye me-1"></i>View
                                </button>
                                <button class="btn btn-sm btn-success flex-grow-1" onclick="pendingApprovalsController.finalApproval(${app.id})">
                                    <i class="bi bi-check-circle me-1"></i>Approve
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    },
    
    calculateReadinessPercentage: function(app) {
        const workflowData = app.data_json ? JSON.parse(app.data_json) : {};
        let readyItems = 0;
        let totalItems = 5;
        
        if (workflowData.documents_verified) readyItems++;
        if (workflowData.interview_completed) readyItems++;
        if (workflowData.placement_done) readyItems++;
        if (workflowData.payment_status === 'paid') readyItems++;
        if (workflowData.class_assigned) readyItems++;
        
        return Math.round((readyItems / totalItems) * 100);
    },
    
    extractAssignedClass: function(app) {
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
    
    extractPaymentStatus: function(app) {
        if (app.data_json) {
            try {
                const data = JSON.parse(app.data_json);
                return data.payment_status || 'pending';
            } catch (e) {
                console.error('Failed to parse payment data:', e);
            }
        }
        return 'pending';
    },
    
    setupEventListeners: function() {
        // Filter changes
        document.getElementById('filterApprovalStatus').addEventListener('change', () => this.applyFilters());
        document.getElementById('filterClass').addEventListener('change', () => this.applyFilters());
        document.getElementById('searchApplications').addEventListener('input', this.debounce(() => this.applyFilters(), 300));
        
        // Decision type change - show/hide conditions
        document.getElementById('finalDecision').addEventListener('change', function() {
            const conditionsGroup = document.getElementById('conditionsGroup');
            const decision = this.value;
            
            if (decision === 'conditional' || decision === 'request_info') {
                conditionsGroup.style.display = 'block';
            } else {
                conditionsGroup.style.display = 'none';
            }
        });
        
        // Final approval form submission
        const approvalForm = document.getElementById('finalApprovalForm');
        if (approvalForm) {
            approvalForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.submitFinalApproval();
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
                    
                    // Setup final approval button
                    const finalApprovalBtn = document.getElementById('finalApprovalBtn');
                    finalApprovalBtn.onclick = () => {
                        modal.hide();
                        this.finalApproval(applicationId);
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
        
        const readinessItems = [
            { label: 'Documents Verified', status: workflowData.documents_verified },
            { label: 'Interview Completed', status: workflowData.interview_completed },
            { label: 'Placement Done', status: workflowData.placement_done },
            { label: 'Payment Received', status: workflowData.payment_status === 'paid' },
            { label: 'Class Assigned', status: workflowData.class_assigned }
        ];
        
        const readinessChecklist = readinessItems.map(item => `
            <div class="d-flex align-items-center mb-2">
                <i class="bi bi-${item.status ? 'check-circle text-success' : 'circle text-muted'} me-2"></i>
                <span class="${item.status ? 'text-decoration-line-through text-muted' : ''}">${item.label}</span>
            </div>
        `).join('');
        
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
                    <h6 class="fw-semibold mb-3">Placement Information</h6>
                    <table class="table table-sm">
                        <tr><td><strong>Assigned Class:</strong></td><td>${workflowData.assigned_class_name || '—'}</td></tr>
                        <tr><td><strong>Stream:</strong></td><td>${workflowData.stream || '—'}</td></tr>
                        <tr><td><strong>Interview Score:</strong></td><td>${workflowData.interview_score || '—'}/100</td></tr>
                        <tr><td><strong>Payment Status:</strong></td><td><span class="${workflowData.payment_status === 'paid' ? 'text-success' : 'text-warning'}">${workflowData.payment_status || 'pending'}</span></td></tr>
                    </table>
                    
                    <h6 class="fw-semibold mb-3 mt-4">Application Status</h6>
                    <div class="mb-2"><span class="badge bg-info">${app.current_stage || '—'}</span></div>
                </div>
            </div>
            
            <div class="mt-4">
                <h6 class="fw-semibold mb-3">Admission Readiness</h6>
                <div class="card">
                    <div class="card-body">
                        ${readinessChecklist}
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('viewApplicationContent').innerHTML = html;
    },
    
    finalApproval: function(applicationId) {
        document.getElementById('finalApprovalApplicationId').value = applicationId;
        
        // Load applicant details for the modal
        API.callAPI(`/admission/application/${applicationId}`, 'GET')
            .then(response => {
                if (response.success && response.data) {
                    const app = response.data.application;
                    const workflowData = response.data.workflow_data || {};
                    
                    const summary = `
                        <strong>Applicant:</strong> ${app.applicant_name}<br>
                        <strong>Grade:</strong> ${app.grade_applying_for}<br>
                        <strong>Assigned Class:</strong> ${workflowData.assigned_class_name || '—'}<br>
                        <strong>Application No:</strong> ${app.application_no}
                    `;
                    document.getElementById('approvalApplicantSummary').innerHTML = summary;
                    
                    // Render readiness checklist
                    const readinessItems = [
                        { label: 'Documents Verified', status: workflowData.documents_verified },
                        { label: 'Interview Completed', status: workflowData.interview_completed },
                        { label: 'Placement Done', status: workflowData.placement_done },
                        { label: 'Payment Received', status: workflowData.payment_status === 'paid' },
                        { label: 'Class Assigned', status: workflowData.class_assigned }
                    ];
                    
                    document.getElementById('readinessChecklist').innerHTML = readinessItems.map(item => `
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-${item.status ? 'check-circle text-success' : 'circle text-muted'} me-2"></i>
                            <span class="${item.status ? 'text-decoration-line-through text-muted' : ''}">${item.label}</span>
                        </div>
                    `).join('');
                    
                    // Set default enrollment date to next Monday
                    const nextMonday = new Date();
                    nextMonday.setDate(nextMonday.getDate() + ((1 + 7 - nextMonday.getDay()) % 7 || 7));
                    document.getElementById('enrollmentStartDate').value = nextMonday.toISOString().split('T')[0];
                    
                    const modal = new bootstrap.Modal(document.getElementById('finalApprovalModal'));
                    modal.show();
                }
            })
            .catch(error => {
                console.error('Failed to load applicant details:', error);
                showNotification('error', 'Failed to load applicant details');
            });
    },
    
    submitFinalApproval: function() {
        const applicationId = document.getElementById('finalApprovalApplicationId').value;
        const submitBtn = document.querySelector('#finalApprovalForm button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
        
        const approvalData = {
            decision: document.getElementById('finalDecision').value,
            enrollment_start_date: document.getElementById('enrollmentStartDate').value,
            conditions: document.getElementById('approvalConditions').value,
            remarks: document.getElementById('approvalRemarks').value
        };
        
        // Call the enrollment completion API for approved decisions
        if (approvalData.decision === 'approve') {
            API.callAPI('/admission/complete-enrollment', 'POST', {
                application_id: applicationId,
                enrollment_start_date: approvalData.enrollment_start_date,
                remarks: approvalData.remarks
            })
                .then(response => {
                    if (response.success) {
                        showNotification('success', 'Admission approved successfully');
                        bootstrap.Modal.getInstance(document.getElementById('finalApprovalModal')).hide();
                        document.getElementById('finalApprovalForm').reset();
                        this.loadApplications();
                    } else {
                        showNotification('error', response.message || 'Failed to approve admission');
                    }
                })
                .catch(error => {
                    console.error('Failed to submit approval:', error);
                    showNotification('error', 'Failed to submit approval');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Submit Approval';
                });
        } else {
            // For other decisions, we would need a dedicated API endpoint
            showNotification('warning', 'Decision type requires additional API endpoint');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Submit Approval';
        }
    },
    
    refreshData: function() {
        this.loadApplications();
    },
    
    showError: function(message) {
        document.getElementById('approvalsGrid').innerHTML = `
            <div class="col-12 text-center py-4">
                <div class="text-danger">
                    <i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>
                    ${message}
                </div>
            </div>
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
