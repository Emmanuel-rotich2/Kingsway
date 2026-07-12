/**
 * Academic Applications Controller
 * Handles Deputy Academic view for class placement and academic assessment
 */
const academicApplicationsController = {
    applications: [],
    filteredApplications: [],
    classes: [],
    
    init: function() {
        console.log('Initializing Academic Applications Controller');
        this.loadClasses();
        this.loadApplications();
        this.setupEventListeners();
    },
    
    loadClasses: function() {
        API.callAPI('/admission/placement-classes', 'GET')
            .then(response => {
                if (response.success && response.data) {
                    this.classes = response.data.classes || [];
                    this.populateClassDropdown();
                }
            })
            .catch(error => {
                console.error('Failed to load classes:', error);
            });
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
    },
    
    loadApplications: function() {
        document.getElementById('applicationsTableBody').innerHTML = `
            <tr>
                <td colspan="7" class="text-center py-4">
                    <div class="spinner-border text-warning" role="status"></div>
                    <div class="mt-2 text-muted">Loading applications...</div>
                </td>
            </tr>
        `;
        
        API.callAPI('/admission/queues', 'GET')
            .then(response => {
                if (response.success && response.data) {
                    // Focus on placement queue
                    const placementApplications = [];
                    const queues = response.data.queues || {};
                    
                    if (queues.placement_pending && Array.isArray(queues.placement_pending)) {
                        queues.placement_pending.forEach(app => {
                            placementApplications.push({
                                ...app,
                                placement_status: 'pending'
                            });
                        });
                    }
                    
                    this.applications = placementApplications;
                    this.applyFilters();
                    this.updateSummaryCards(response.data.summary || {});
                }
            })
            .catch(error => {
                console.error('Failed to load applications:', error);
                this.showError('Failed to load applications');
            });
    },
    
    updateSummaryCards: function(summary) {
        document.getElementById('statPendingPlacement').textContent = summary.placement_pending || 0;
        document.getElementById('statPlaced').textContent = '0'; // Would need to track placed applications
        document.getElementById('statPlacementTests').textContent = '0'; // Would need placement test data
        document.getElementById('statCapacity').textContent = '85%'; // Would calculate from actual class data
    },
    
    applyFilters: function() {
        const placementStatus = document.getElementById('filterPlacementStatus').value;
        const classFilter = document.getElementById('filterClass').value;
        const searchTerm = document.getElementById('searchApplications').value.toLowerCase();
        
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
            const placementStatusBadge = this.getPlacementStatusBadge(app.placement_status);
            const recommendedClass = this.extractRecommendedClass(app);
            
            return `
                <tr>
                    <td><strong>${app.application_no || '—'}</strong></td>
                    <td>${app.applicant_name || 'Unknown'}</td>
                    <td>${app.grade_applying_for || '—'}</td>
                    <td>${interviewScore || '—'}</td>
                    <td>${placementStatusBadge}</td>
                    <td>${recommendedClass || '—'}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="academicApplicationsController.viewApplication(${app.id})" title="View">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-outline-warning" onclick="academicApplicationsController.makePlacement(${app.id})" title="Make Placement">
                                <i class="bi bi-award"></i>
                            </button>
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
            'pending': '<span class="badge bg-warning">Pending</span>',
            'recommended': '<span class="badge bg-info">Recommended</span>',
            'approved': '<span class="badge bg-primary">Approved</span>',
            'assigned': '<span class="badge bg-success">Assigned</span>'
        };
        return badges[status] || '<span class="badge bg-secondary">' + status + '</span>';
    },
    
    setupEventListeners: function() {
        // Filter changes
        document.getElementById('filterPlacementStatus').addEventListener('change', () => this.applyFilters());
        document.getElementById('filterClass').addEventListener('change', () => this.applyFilters());
        document.getElementById('searchApplications').addEventListener('input', this.debounce(() => this.applyFilters(), 300));
        
        // Class selection change - show capacity
        document.getElementById('recommendedClass').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const capacityCard = document.getElementById('classCapacityCard');
            
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
                if (response.success && response.data) {
                    this.renderApplicationDetails(response.data);
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
                if (response.success && response.data) {
                    const app = response.data.application;
                    const workflowData = response.data.workflow_data || {};
                    
                    const summary = `
                        <strong>Applicant:</strong> ${app.applicant_name}<br>
                        <strong>Applied Grade:</strong> ${app.grade_applying_for}<br>
                        <strong>Previous School:</strong> ${app.previous_school || '—'}<br>
                        <strong>Interview Score:</strong> ${workflowData.interview_score || '—'}/100
                    `;
                    document.getElementById('applicantSummary').innerHTML = summary;
                    
                    // Academic background
                    document.getElementById('academicBackground').innerHTML = `
                        <div class="row">
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between mb-1">
                                    <small>Previous School:</small>
                                    <small class="fw-semibold">${app.previous_school || '—'}</small>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <small>Applied Grade:</small>
                                    <small class="fw-semibold">${app.grade_applying_for || '—'}</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between mb-1">
                                    <small>Interview Score:</small>
                                    <small class="fw-bold text-primary">${workflowData.interview_score || '—'}/100</small>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <small>Interview Recommendation:</small>
                                    <small class="fw-semibold">${workflowData.recommendation || '—'}</small>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Set default class to applied grade
                    const classSelect = document.getElementById('recommendedClass');
                    // Find class matching applied grade
                    for (let i = 0; i < classSelect.options.length; i++) {
                        if (classSelect.options[i].text.includes(app.grade_applying_for)) {
                            classSelect.selectedIndex = i;
                            // Trigger change event to show capacity
                            classSelect.dispatchEvent(new Event('change'));
                            break;
                        }
                    }
                    
                    const modal = new bootstrap.Modal(document.getElementById('classPlacementModal'));
                    modal.show();
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
                if (response.success) {
                    showNotification('success', 'Class placement recorded successfully');
                    bootstrap.Modal.getInstance(document.getElementById('classPlacementModal')).hide();
                    document.getElementById('classPlacementForm').reset();
                    this.loadApplications();
                } else {
                    showNotification('error', response.message || 'Failed to record placement');
                }
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
