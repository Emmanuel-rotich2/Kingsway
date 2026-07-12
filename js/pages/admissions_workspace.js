/**
 * Admissions Workspace Controller
 * Unified tabbed interface for managing the complete admissions workflow
 */

const admissionsWorkspaceController = {
    currentTab: 'applications',
    queueData: null,
    initialized: false,
    dom: {},

    init: async function() {
        if (this.initialized) return;
        this.initialized = true;

        console.log("admissionsWorkspaceController: Initializing...");

        try {
            if (window.AuthContext && typeof window.AuthContext.isAuthenticated === "function") {
                if (!window.AuthContext.isAuthenticated()) {
                    console.warn("admissionsWorkspaceController: Not authenticated, redirecting to login");
                    window.location.href = `${window.APP_BASE || ""}/index.php`;
                    return;
                }
            } else {
                console.warn("admissionsWorkspaceController: AuthContext not available");
            }

            this.cacheDom();
            this.setupEventListeners();
            await this.loadQueueData();

            console.log("admissionsWorkspaceController: Initialization complete");
        } catch (error) {
            console.error("Failed to initialize Admissions Workspace Controller:", error);
            this.showError("Failed to load admissions data");
        }
    },

    apiCall: function(endpoint, method = "GET", data = null, params = {}, options = {}) {
        if (window.API && typeof window.API.callAPI === "function") {
            return window.API.callAPI(endpoint, method, data);
        }

        if (window.API && typeof window.API.apiCall === "function") {
            return window.API.apiCall(endpoint, method, data, params, options);
        }

        throw new Error("API helper not available. Expected window.API.callAPI or window.API.apiCall.");
    },

    isSuccessfulResponse: function(response) {
        if (!response) return false;
        if (response.success === false || response.status === false) return false;
        if (response.success === true || response.status === true) return true;
        return response.data !== undefined || response.queues !== undefined || response.application !== undefined;
    },

    unwrapPayload: function(response) {
        // Handle both response.data and direct response objects
        if (response && response.data) {
            return response.data;
        }
        return response || {};
    },

    extractList: function(response) {
        const payload = this.unwrapPayload(response);
        if (Array.isArray(payload)) {
            return payload;
        }
        if (payload.data && Array.isArray(payload.data)) {
            return payload.data;
        }
        if (payload.items && Array.isArray(payload.items)) {
            return payload.items;
        }
        if (payload.list && Array.isArray(payload.list)) {
            return payload.list;
        }
        return [];
    },

    notify: function(type, message) {
        if (typeof window.showNotification === "function") {
            window.showNotification(type, message);
            return;
        }

        if (window.API && typeof window.API.showNotification === "function") {
            window.API.showNotification(message, type);
            return;
        }

        console.log(`[${type.toUpperCase()}] ${message}`);
    },

    escapeHtml: function(text) {
        if (!text) return "";
        const div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML;
    },

    parseJsonSafe: function(value) {
        if (!value) return {};

        if (typeof value === "object") return value;

        try {
            return JSON.parse(value);
        } catch (error) {
            console.warn("Invalid JSON payload:", value, error);
            return {};
        }
    },

    cacheDom: function() {
        this.dom = {
            admissionsTabs: document.getElementById("admissionsTabs"),
            admissionsTabContent: document.getElementById("admissionsTabContent"),
            summaryCards: document.getElementById("summaryCards"),
            tabApplications: document.getElementById("tab-applications"),
            tabDocuments: document.getElementById("tab-documents"),
            tabInterviews: document.getElementById("tab-interviews"),
            tabDecisions: document.getElementById("tab-decisions"),
            tabPlacements: document.getElementById("tab-placements"),
            tabEnrollment: document.getElementById("tab-enrollment"),
            applicationsLoading: document.getElementById("applications-loading"),
            applicationsContent: document.getElementById("applications-content"),
            documentsLoading: document.getElementById("documents-loading"),
            documentsContent: document.getElementById("documents-content"),
            interviewsLoading: document.getElementById("interviews-loading"),
            interviewsContent: document.getElementById("interviews-content"),
            decisionsLoading: document.getElementById("decisions-loading"),
            decisionsContent: document.getElementById("decisions-content"),
            placementsLoading: document.getElementById("placements-loading"),
            placementsContent: document.getElementById("placements-content"),
            enrollmentLoading: document.getElementById("enrollment-loading"),
            enrollmentContent: document.getElementById("enrollment-content"),
        };
    },

    setupEventListeners: function() {
        // Tab switching is handled by onclick attributes in HTML
    },
    
    loadQueueData: async function() {
        try {
            const response = await this.apiCall('/admission/queues', 'GET');
            console.log("Admissions workspace response:", response);

            if (!this.isSuccessfulResponse(response)) {
                throw new Error(response?.message || "Failed to load admissions data.");
            }

            this.queueData = this.unwrapPayload(response);
            console.log("Queue data loaded:", this.queueData);

            this.updateSummaryCards();
            this.updateTabBadges();
            this.loadCurrentTab();
        } catch (error) {
            console.error('Failed to load queue data:', error);
            this.showError('Failed to load admissions data');
        }
    },

    updateSummaryCards: function() {
        if (!this.queueData) return;
        
        const queues = this.queueData.queues || {};
        const summary = this.queueData.summary || {};
        
        // Calculate statistics from all queues
        let total = 0;
        let pending = 0;
        let inReview = 0;
        let approved = 0;
        let rejected = 0;
        let enrolled = 0;
        
        Object.keys(queues).forEach(queueName => {
            if (Array.isArray(queues[queueName])) {
                queues[queueName].forEach(app => {
                    total++;
                    const stage = app.current_stage || app.status;
                    if (app.status === 'pending' || app.status === 'submitted' || stage === 'application_received' || stage === 'application_review') pending++;
                    else if (['documents_upload', 'documents_verification', 'class_space_check', 'interview_scheduling', 'interview_results'].includes(stage)) inReview++;
                    else if (['admission_decision', 'provisional_student_creation', 'fees_payment', 'student_id_generation', 'final_approval', 'enrollment'].includes(stage)) approved++;
                    else if (app.status === 'rejected') rejected++;
                    else if (app.status === 'enrolled') enrolled++;
                });
            }
        });
        
        document.getElementById('statTotal').textContent = total;
        document.getElementById('statPending').textContent = pending;
        document.getElementById('statInReview').textContent = inReview;
        document.getElementById('statApproved').textContent = approved;
        document.getElementById('statRejected').textContent = rejected;
        document.getElementById('statEnrolled').textContent = enrolled;
    },
    
    updateTabBadges: function() {
        if (!this.queueData) return;
        
        const queues = this.queueData.queues || {};
        
        // Calculate badge counts for each tab
        const applicationsCount = this.getAllQueueApplications().length;
        const documentsCount = this.countInQueues(queues, ['documents_pending']);
        const interviewsCount = this.countInQueues(queues, ['space_check_pending', 'interview_pending']);
        const decisionsCount = this.countInQueues(queues, ['decision_pending']);
        const placementsCount = this.countInQueues(queues, ['payment_pending', 'id_generation_pending', 'final_approval_pending']);
        const enrollmentCount = this.countInQueues(queues, ['enrollment_pending', 'completed']);
        
        document.getElementById('tabBadgeApplications').textContent = applicationsCount;
        document.getElementById('tabBadgeDocuments').textContent = documentsCount;
        document.getElementById('tabBadgeInterviews').textContent = interviewsCount;
        document.getElementById('tabBadgeDecisions').textContent = decisionsCount;
        document.getElementById('tabBadgePlacements').textContent = placementsCount;
        document.getElementById('tabBadgeEnrollment').textContent = enrollmentCount;
    },
    
    countInQueues: function(queues, queueNames) {
        let count = 0;
        queueNames.forEach(name => {
            if (Array.isArray(queues[name])) {
                count += queues[name].length;
            }
        });
        return count;
    },

    getAllQueueApplications: function() {
        const queues = this.queueData?.queues || {};
        const seen = new Set();
        const applications = [];

        Object.entries(queues).forEach(([queueName, rows]) => {
            if (!Array.isArray(rows)) return;

            rows.forEach((app) => {
                const key = String(app.id);
                if (seen.has(key)) return;
                seen.add(key);
                applications.push({ ...app, queue_name: queueName });
            });
        });

        return applications;
    },
    
    switchTab: function(tabName) {
        this.currentTab = tabName;
        
        // Update tab buttons
        document.querySelectorAll('#admissionsTabs .nav-link').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.tab === tabName) {
                btn.classList.add('active');
            }
        });
        
        // Show/hide tab content
        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.style.display = 'none';
        });
        document.getElementById('tab-' + tabName).style.display = 'block';
        
        // Load content for the new tab
        this.loadCurrentTab();
    },
    
    loadCurrentTab: function() {
        const tabName = this.currentTab;
        const loadingDiv = document.getElementById(tabName + '-loading');
        const contentDiv = document.getElementById(tabName + '-content');
        
        if (!loadingDiv || !contentDiv) return;
        
        // Show loading
        loadingDiv.style.display = 'block';
        contentDiv.style.display = 'none';
        
        // Load content based on tab
        switch(tabName) {
            case 'applications':
                this.loadApplicationsTab(contentDiv, loadingDiv);
                break;
            case 'documents':
                this.loadDocumentsTab(contentDiv, loadingDiv);
                break;
            case 'interviews':
                this.loadInterviewsTab(contentDiv, loadingDiv);
                break;
            case 'decisions':
                this.loadDecisionsTab(contentDiv, loadingDiv);
                break;
            case 'placements':
                this.loadPlacementsTab(contentDiv, loadingDiv);
                break;
            case 'enrollment':
                this.loadEnrollmentTab(contentDiv, loadingDiv);
                break;
        }
    },
    
    loadApplicationsTab: function(contentDiv, loadingDiv) {
        if (!this.queueData) {
            this.renderEmptyTab(contentDiv, loadingDiv, 'No applications data available');
            return;
        }
        
        const applications = this.getAllQueueApplications();
        
        if (applications.length === 0) {
            this.renderEmptyTab(contentDiv, loadingDiv, 'No applications found');
            return;
        }
        
        const html = `
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Application No</th>
                                    <th>Applicant Name</th>
                                    <th>Grade</th>
                                    <th>Application Type</th>
                                    <th>Current Workflow Position</th>
                                    <th>Waiting For</th>
                                    <th>Next Required Action</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${applications.map(app => {
                                    const workflowData = this.parseJsonSafe(app.workflow_data_json || app.data_json || '{}');
                                    const docCount = Number(app.doc_count || 0);
                                    const verifiedCount = Number(app.verified_count || 0);
                                    const hasRejectedDocs = (app.rejected_count || 0) > 0;
                                    
                                    // Mock documents array for communication helper
                                    const mockDocuments = [];
                                    for (let i = 0; i < docCount; i++) {
                                        mockDocuments.push({
                                            verification_status: i < verifiedCount ? 'verified' : (hasRejectedDocs && i === verifiedCount ? 'rejected' : 'pending')
                                        });
                                    }
                                    
                                    const workflowComm = this.getApplicationWorkflowCommunication(app, mockDocuments, workflowData);
                                    
                                    return `
                                        <tr>
                                            <td><strong>${this.escapeHtml(app.application_no || '—')}</strong></td>
                                            <td>${this.escapeHtml(app.applicant_name || 'Unknown')}</td>
                                            <td>${this.escapeHtml(app.grade_applying_for || '—')}</td>
                                            <td>${this.escapeHtml(this.formatLabel(app.application_source || 'physical'))}</td>
                                            <td>
                                                <span class="badge bg-${workflowComm.tone}">${this.escapeHtml(workflowComm.label)}</span>
                                            </td>
                                            <td>${this.escapeHtml(workflowComm.waitingFor)}</td>
                                            <td>${this.escapeHtml(workflowComm.nextActionLabel)}</td>
                                            <td>${this.formatDate(app.updated_at)}</td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    ${this.renderWorkflowActionButton(app, workflowComm)}
                                                    <button class="btn btn-sm btn-outline-primary" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.viewApplication(${app.id})">
                                                        <i class="bi bi-eye"></i> View
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
        
        contentDiv.innerHTML = html;
        loadingDiv.style.display = 'none';
        contentDiv.style.display = 'block';
    },

    renderWorkflowActionButton: function(app, workflowComm) {
        // Don't show action button if enrolled or rejected
        if (workflowComm.stage === 'enrolled' || workflowComm.stage === 'rejected') {
            return '';
        }
        
        // Show the appropriate action button based on workflow communication
        const actionMethod = workflowComm.nextActionMethod;
        const actionLabel = workflowComm.nextActionLabel;
        
        // Only show if it's not just "view"
        if (actionMethod === 'viewApplication') {
            return '';
        }
        
        return `
            <button class="btn btn-sm btn-outline-success" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.${actionMethod}(${app.id})">
                <i class="bi bi-arrow-right"></i> ${this.escapeHtml(actionLabel)}
            </button>
        `;
    },
    
    loadDocumentsTab: function(contentDiv, loadingDiv) {
        if (!this.queueData) {
            this.renderEmptyTab(contentDiv, loadingDiv, 'No documents data available');
            return;
        }
        
        const queues = this.queueData.queues || {};
        const applications = [];
        
        ['documents_pending'].forEach(queueName => {
            if (Array.isArray(queues[queueName])) {
                queues[queueName].forEach(app => applications.push(app));
            }
        });
        
        if (applications.length === 0) {
            this.renderEmptyTab(contentDiv, loadingDiv, 'No documents pending verification');
            return;
        }
        
        const html = `
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Application No</th>
                                    <th>Applicant Name</th>
                                    <th>Uploaded</th>
                                    <th>Verified</th>
                                    <th>Documents Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${applications.map(app => {
                                    const docCount = Number(app.doc_count || 0);
                                    const verifiedCount = Number(app.verified_count || 0);
                                    const docStatus = this.getDocumentsStatus(app);
                                    const hasMissingDocs = docCount === 0 || verifiedCount < docCount;
                                    return `
                                        <tr>
                                            <td><strong>${this.escapeHtml(app.application_no || '—')}</strong></td>
                                            <td>${this.escapeHtml(app.applicant_name || 'Unknown')}</td>
                                            <td>${docCount}</td>
                                            <td>${verifiedCount}</td>
                                            <td>${docStatus}</td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.uploadDocuments(${app.id})">
                                                        <i class="bi bi-upload"></i> Upload
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.verifyDocuments(${app.id})">
                                                        <i class="bi bi-check-circle"></i> Verify
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-secondary" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.viewApplication(${app.id})">
                                                        <i class="bi bi-eye"></i> View
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
        
        contentDiv.innerHTML = html;
        loadingDiv.style.display = 'none';
        contentDiv.style.display = 'block';
    },
    
    loadInterviewsTab: function(contentDiv, loadingDiv) {
        if (!this.queueData) {
            this.renderEmptyTab(contentDiv, loadingDiv, 'No interviews data available');
            return;
        }
        
        const queues = this.queueData.queues || {};
        const applications = [];
        
        ['space_check_pending', 'interview_pending'].forEach(queueName => {
            if (Array.isArray(queues[queueName])) {
                queues[queueName].forEach(app => applications.push(app));
            }
        });
        
        if (applications.length === 0) {
            this.renderEmptyTab(contentDiv, loadingDiv, 'No interviews scheduled or pending');
            return;
        }
        
        const html = `
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Application No</th>
                                    <th>Applicant Name</th>
                                    <th>Interview Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${applications.map(app => {
                                    const interviewDate = this.extractInterviewDate(app);
                                    return `
                                        <tr>
                                            <td><strong>${this.escapeHtml(app.application_no || '—')}</strong></td>
                                            <td>${this.escapeHtml(app.applicant_name || 'Unknown')}</td>
                                            <td>${interviewDate || 'Not scheduled'}</td>
                                            <td>${this.getStatusBadge(app.current_stage || app.status)}</td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    ${app.current_stage === 'class_space_check' ? `
                                                        <button class="btn btn-sm btn-outline-success" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.checkClassSpaceAvailability(${app.id})">
                                                            <i class="bi bi-building-check"></i> Check Space
                                                        </button>
                                                    ` : ''}
                                                    ${app.current_stage === 'interview_scheduling' ? `
                                                        <button class="btn btn-sm btn-outline-primary" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.scheduleInterview(${app.id})">
                                                            <i class="bi bi-calendar-plus"></i> Schedule
                                                        </button>
                                                    ` : ''}
                                                    ${app.current_stage === 'interview_results' ? `
                                                        <button class="btn btn-sm btn-outline-info" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.conductInterview(${app.id})">
                                                            <i class="bi bi-clipboard-check"></i> Record
                                                        </button>
                                                    ` : ''}
                                                    <button class="btn btn-sm btn-outline-secondary" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.viewApplication(${app.id})">
                                                        <i class="bi bi-eye"></i> View
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
        
        contentDiv.innerHTML = html;
        loadingDiv.style.display = 'none';
        contentDiv.style.display = 'block';
    },
    
    loadDecisionsTab: function(contentDiv, loadingDiv) {
        if (!this.queueData) {
            this.renderEmptyTab(contentDiv, loadingDiv, 'No decisions data available');
            return;
        }
        
        const queues = this.queueData.queues || {};
        const applications = queues.decision_pending || [];
        
        if (applications.length === 0) {
            this.renderEmptyTab(contentDiv, loadingDiv, 'No applications awaiting decision');
            return;
        }
        
        const html = `
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Application No</th>
                                    <th>Applicant Name</th>
                                    <th>Interview Score</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${applications.map(app => {
                                    const interviewScore = this.extractInterviewScore(app);
                                    return `
                                        <tr>
                                            <td><strong>${this.escapeHtml(app.application_no || '—')}</strong></td>
                                            <td>${this.escapeHtml(app.applicant_name || 'Unknown')}</td>
                                            <td>${interviewScore || '—'}</td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    ${app.current_stage === 'admission_decision' ? `
                                                        <button class="btn btn-sm btn-outline-primary" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.admitStudent(${app.id})">
                                                            <i class="bi bi-check-square"></i> Admit
                                                        </button>
                                                    ` : ''}
                                                    ${app.current_stage === 'provisional_student_creation' ? `
                                                        <button class="btn btn-sm btn-outline-success" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.createProvisionalStudent(${app.id})">
                                                            <i class="bi bi-person-plus"></i> Create Student
                                                        </button>
                                                    ` : ''}
                                                    <button class="btn btn-sm btn-outline-secondary" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.viewApplication(${app.id})">
                                                        <i class="bi bi-eye"></i> View
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
        
        contentDiv.innerHTML = html;
        loadingDiv.style.display = 'none';
        contentDiv.style.display = 'block';
    },
    
    loadPlacementsTab: function(contentDiv, loadingDiv) {
        if (!this.queueData) {
            this.renderEmptyTab(contentDiv, loadingDiv, 'No placements data available');
            return;
        }
        
        const queues = this.queueData.queues || {};
        const applications = [];
        
        ['payment_pending', 'id_generation_pending', 'final_approval_pending'].forEach(queueName => {
            if (Array.isArray(queues[queueName])) {
                queues[queueName].forEach(app => applications.push(app));
            }
        });
        
        if (applications.length === 0) {
            this.renderEmptyTab(contentDiv, loadingDiv, 'No placements pending');
            return;
        }
        
        const html = `
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Application No</th>
                                    <th>Applicant Name</th>
                                    <th>Assigned Class</th>
                                    <th>Payment Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${applications.map(app => {
                                    const assignedClass = this.extractAssignedClass(app);
                                    const paymentStatus = this.extractPaymentStatus(app);
                                    return `
                                        <tr>
                                            <td><strong>${this.escapeHtml(app.application_no || '—')}</strong></td>
                                            <td>${this.escapeHtml(app.applicant_name || 'Unknown')}</td>
                                            <td>${assignedClass || '—'}</td>
                                            <td>${paymentStatus}</td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    ${app.current_stage === 'fees_payment' ? `
                                                        <button class="btn btn-sm btn-outline-primary" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.recordPayment(${app.id})">
                                                            <i class="bi bi-cash-coin"></i> Payment
                                                        </button>
                                                    ` : ''}
                                                    ${app.current_stage === 'student_id_generation' ? `
                                                        <button class="btn btn-sm btn-outline-success" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.generateStudentIdCard(${app.id})">
                                                            <i class="bi bi-person-vcard"></i> Generate ID
                                                        </button>
                                                    ` : ''}
                                                    ${app.current_stage === 'final_approval' ? `
                                                        <button class="btn btn-sm btn-outline-danger" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.finalApproval(${app.id})">
                                                            <i class="bi bi-check-circle"></i> Final Approval
                                                        </button>
                                                    ` : ''}
                                                    <button class="btn btn-sm btn-outline-secondary" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.viewApplication(${app.id})">
                                                        <i class="bi bi-eye"></i> View
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
        
        contentDiv.innerHTML = html;
        loadingDiv.style.display = 'none';
        contentDiv.style.display = 'block';
    },
    
    loadEnrollmentTab: function(contentDiv, loadingDiv) {
        if (!this.queueData) {
            this.renderEmptyTab(contentDiv, loadingDiv, 'No enrollment data available');
            return;
        }
        
        const queues = this.queueData.queues || {};
        const applications = [
            ...(queues.enrollment_pending || []),
            ...(queues.completed || [])
        ];
        
        if (applications.length === 0) {
            this.renderEmptyTab(contentDiv, loadingDiv, 'No applications pending enrollment');
            return;
        }
        
        const html = `
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Application No</th>
                                    <th>Applicant Name</th>
                                    <th>Readiness</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${applications.map(app => {
                                    const readiness = this.calculateReadiness(app);
                                    return `
                                        <tr>
                                            <td><strong>${this.escapeHtml(app.application_no || '—')}</strong></td>
                                            <td>${this.escapeHtml(app.applicant_name || 'Unknown')}</td>
                                            <td>${readiness}%</td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    ${app.current_stage === 'enrollment' ? `
                                                        <button class="btn btn-sm btn-outline-success" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.completeEnrollment(${app.id})">
                                                            <i class="bi bi-person-check"></i> Enroll
                                                        </button>
                                                    ` : ''}
                                                    ${app.current_stage === 'enrolled' ? this.getStatusBadge('enrolled') : ''}
                                                    <button class="btn btn-sm btn-outline-secondary" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.viewApplication(${app.id})">
                                                        <i class="bi bi-eye"></i> View
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
        
        contentDiv.innerHTML = html;
        loadingDiv.style.display = 'none';
        contentDiv.style.display = 'block';
    },
    
    renderEmptyTab: function(contentDiv, loadingDiv, message) {
        contentDiv.innerHTML = `
            <div class="text-center py-4">
                <div class="text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    ${message}
                </div>
            </div>
        `;
        loadingDiv.style.display = 'none';
        contentDiv.style.display = 'block';
    },
    
    getStatusBadge: function(status) {
        const badges = {
            'submitted': '<span class="badge bg-secondary">Submitted</span>',
            'documents_pending': '<span class="badge bg-warning">Documents Pending</span>',
            'documents_verified': '<span class="badge bg-info">Documents Verified</span>',
            'class_space_check': '<span class="badge bg-info">Space Check</span>',
            'interview_scheduling': '<span class="badge bg-primary">Interview Scheduling</span>',
            'interview_results': '<span class="badge bg-info">Interview Results</span>',
            'admission_decision': '<span class="badge bg-warning">Admission Decision</span>',
            'provisional_student_creation': '<span class="badge bg-primary">Student Creation</span>',
            'fees_payment': '<span class="badge bg-warning">Fees Payment</span>',
            'student_id_generation': '<span class="badge bg-primary">ID Generation</span>',
            'final_approval': '<span class="badge bg-danger">Final Approval</span>',
            'enrollment': '<span class="badge bg-warning">Enrollment</span>',
            'enrolled': '<span class="badge bg-success">Enrolled</span>',
            'rejected': '<span class="badge bg-danger">Rejected</span>'
        };
        return badges[status] || '<span class="badge bg-secondary">' + this.escapeHtml(status || "unknown") + '</span>';
    },
    
    getDocumentsStatus: function(app) {
        const docCount = Number(app.doc_count || 0);
        const verifiedCount = Number(app.verified_count || 0);

        if (docCount === 0) {
            return '<span class="badge bg-secondary">Not Uploaded</span>';
        }

        if (verifiedCount >= docCount) {
            return '<span class="badge bg-success">Verified</span>';
        }

        const workflowData = this.parseJsonSafe(app.data_json);
        if (workflowData.documents_verified) {
            return '<span class="badge bg-success">Verified</span>';
        } else if (workflowData.documents_uploaded) {
            return '<span class="badge bg-warning">Pending Verification</span>';
        }
        return '<span class="badge bg-warning">Pending Verification</span>';
    },
    
    extractInterviewDate: function(app) {
        const workflowData = this.parseJsonSafe(app.data_json);
        if (workflowData.interview_date) {
            return this.formatDate(workflowData.interview_date);
        }
        return '—';
    },
    
    extractInterviewScore: function(app) {
        const workflowData = this.parseJsonSafe(app.data_json);
        const score = workflowData.assessment_score ?? workflowData.interview_score ?? workflowData.overall_score;
        return score !== undefined && score !== null && score !== "" ? score + '/100' : '—';
    },
    
    extractAssignedClass: function(app) {
        const workflowData = this.parseJsonSafe(app.data_json);
        return workflowData.assigned_class_name || workflowData.recommended_class || workflowData.assigned_class_id || '—';
    },
    
    extractPaymentStatus: function(app) {
        const workflowData = this.parseJsonSafe(app.data_json);
        const status = workflowData.payment_status || 'pending';
        return status === 'paid' || workflowData.last_payment_recorded_at || workflowData.last_admission_payment_id
            ? '<span class="badge bg-success">Paid</span>'
            : '<span class="badge bg-warning">Pending</span>';
    },
    
    calculateReadiness: function(app) {
        const workflowData = this.parseJsonSafe(app.data_json);
        let readyItems = 0;
        let totalItems = 5;
        
        if (app.status === 'documents_verified' || app.verified_count > 0 || workflowData.documents_verified) readyItems++;
        if (workflowData.assessment_score !== undefined || workflowData.interview_completed || app.current_stage !== 'interview_results') readyItems++;
        if (workflowData.assigned_class_id || workflowData.placement_done) readyItems++;
        if (workflowData.payment_status === 'paid' || workflowData.last_payment_recorded_at || workflowData.last_admission_payment_id) readyItems++;
        if (app.current_stage === 'enrollment' || app.current_stage === 'enrolled' || app.status === 'enrolled') readyItems++;
        
        return Math.round((readyItems / totalItems) * 100);
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

    refreshAll: function() {
        this.loadQueueData();
    },
    
    newApplication: function() {
        // Navigate to new applications page
        window.location.href = window.APP_BASE + '/home.php?route=new_applications';
    },
    
    viewApplication: function(applicationId) {
        if (!applicationId || Number.isNaN(Number(applicationId))) {
            this.notify("error", "Invalid application selected");
            return;
        }

        this.apiCall(`/admission/application/${applicationId}`, "GET")
            .then((response) => {
                if (!this.isSuccessfulResponse(response)) {
                    throw new Error(response?.message || "Failed to load application details");
                }

                const payload = this.unwrapPayload(response);
                if (!payload?.application) {
                    throw new Error("Application details were not returned");
                }

                this.renderApplicationDetails(payload);
                const contentElement = document.getElementById("admissionsWorkspaceApplicationContent");

                // Store current application ID for actions
                this.currentApplicationId = applicationId;
                this.currentApplicationData = payload;

                const documents = Array.isArray(payload.documents) ? payload.documents : [];
                const workflowData = this.parseJsonSafe(payload.workflow_data || payload.application.workflow_data_json || payload.application.data_json || {});

                this.showWorkspaceModal(
                    '<i class="bi bi-person-badge me-2"></i>Application Details',
                    contentElement?.innerHTML || "",
                    this.renderApplicationActionFooter(applicationId, payload.application, documents, workflowData)
                );
            })
            .catch((error) => {
                console.error("Failed to load application details:", error);
                this.notify("error", error.message || "Failed to load application details");
            });
    },

    renderApplicationDetails: function(payload) {
        const modalElement = document.getElementById("admissionsWorkspaceApplicationModal");
        const contentElement = document.getElementById("admissionsWorkspaceApplicationContent");
        if (!modalElement || !contentElement) return;

        const app = payload.application || {};
        const documents = Array.isArray(payload.documents) ? payload.documents : [];
        const workflowData = payload.workflow_data || {};
        const stageMeta = payload.stage_metadata || {};
        const parentName = [app.parent_first_name, app.parent_last_name].filter(Boolean).join(" ") || "N/A";
        const currentStage = stageMeta.display_name || this.formatLabel(stageMeta.current_stage || app.current_stage || "N/A");
        const documentsHtml = documents.length
            ? documents.map((doc) => {
                const status = doc.verification_status || "pending";
                const fileUrl = doc.file_url || doc.download_url || doc.document_path || "";
                return `
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div>
                            <i class="bi bi-file-earmark me-2"></i>
                            ${this.escapeHtml(this.formatLabel(doc.document_type || "Document"))}
                            ${Number(doc.is_mandatory) === 1 ? '<span class="badge bg-danger ms-1">Required</span>' : ""}
                            ${fileUrl ? `
                                <div class="small mt-1">
                                    <a href="${this.escapeHtml(fileUrl)}" target="_blank" rel="noopener" class="text-decoration-none">
                                        <i class="bi bi-box-arrow-up-right me-1"></i>Open document
                                    </a>
                                </div>
                            ` : ""}
                        </div>
                        ${this.getStatusBadge(status)}
                    </div>
                `;
            }).join("")
            : '<p class="text-muted mb-0">No documents uploaded.</p>';

        contentElement.innerHTML = `
            <div class="row g-4">
                <div class="col-lg-6">
                    <h6 class="fw-semibold mb-3">Applicant Information</h6>
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Application No</dt>
                        <dd class="col-sm-7">${this.escapeHtml(app.application_no || "N/A")}</dd>
                        <dt class="col-sm-5">Name</dt>
                        <dd class="col-sm-7">${this.escapeHtml(app.applicant_name || "N/A")}</dd>
                        <dt class="col-sm-5">Date of Birth</dt>
                        <dd class="col-sm-7">${this.escapeHtml(this.formatDate(app.date_of_birth))}</dd>
                        <dt class="col-sm-5">Gender</dt>
                        <dd class="col-sm-7">${this.escapeHtml(this.formatLabel(app.gender || "N/A"))}</dd>
                        <dt class="col-sm-5">Grade Applying For</dt>
                        <dd class="col-sm-7">${this.escapeHtml(app.grade_applying_for || "N/A")}</dd>
                        <dt class="col-sm-5">Status</dt>
                        <dd class="col-sm-7">${this.getStatusBadge(app.status)}</dd>
                    </dl>
                </div>
                <div class="col-lg-6">
                    <h6 class="fw-semibold mb-3">Parent / Guardian</h6>
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Name</dt>
                        <dd class="col-sm-7">${this.escapeHtml(parentName)}</dd>
                        <dt class="col-sm-5">Phone</dt>
                        <dd class="col-sm-7">${this.escapeHtml(app.phone_1 || app.parent_phone_1 || "N/A")}</dd>
                        <dt class="col-sm-5">Email</dt>
                        <dd class="col-sm-7">${this.escapeHtml(app.parent_email || "N/A")}</dd>
                        <dt class="col-sm-5">Current Stage</dt>
                        <dd class="col-sm-7">${this.escapeHtml(currentStage)}</dd>
                        <dt class="col-sm-5">Created</dt>
                        <dd class="col-sm-7">${this.escapeHtml(this.formatDate(app.created_at))}</dd>
                    </dl>
                </div>
            </div>

            <hr>

            <div class="row g-4">
                <div class="col-lg-6">
                    <h6 class="fw-semibold mb-3">Documents (${documents.length})</h6>
                    ${documentsHtml}
                </div>
                <div class="col-lg-6">
                    <h6 class="fw-semibold mb-3">Workflow Data</h6>
                    ${this.renderWorkflowData(workflowData)}
                </div>
            </div>
        `;
    },

    renderWorkflowData: function(workflowData) {
        if (!workflowData || Object.keys(workflowData).length === 0) {
            return '<p class="text-muted mb-0">No workflow details recorded.</p>';
        }

        return `
            <dl class="row mb-0">
                ${Object.entries(workflowData).map(([key, value]) => `
                    <dt class="col-sm-5">${this.escapeHtml(this.formatLabel(key))}</dt>
                    <dd class="col-sm-7">${this.escapeHtml(typeof value === "object" ? JSON.stringify(value) : value || "N/A")}</dd>
                `).join("")}
            </dl>
        `;
    },

    formatLabel: function(value) {
        if (!value) return "N/A";
        return String(value).replace(/_/g, " ").replace(/\b\w/g, (letter) => letter.toUpperCase());
    },
    
    showWorkspaceModal: function(title, bodyHtml, footerHtml = "") {
        const modalElement = document.getElementById("admissionsWorkspaceApplicationModal");
        if (!modalElement) {
            this.notify("error", "Workspace modal is not available");
            return null;
        }

        const titleElement = modalElement.querySelector(".modal-title");
        const bodyElement = document.getElementById("admissionsWorkspaceApplicationContent");
        const footerElement = modalElement.querySelector(".modal-footer");

        if (titleElement) {
            titleElement.innerHTML = title;
        }
        if (bodyElement) {
            bodyElement.innerHTML = bodyHtml;
        }
        if (footerElement) {
            footerElement.innerHTML = footerHtml || '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>';
        }

        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        modal.show();
        return modalElement;
    },

    closeWorkspaceModal: function() {
        const modalElement = document.getElementById("admissionsWorkspaceApplicationModal");
        const modal = modalElement ? bootstrap.Modal.getInstance(modalElement) : null;
        if (modal) modal.hide();
    },

    runAdmissionAction: async function(actionPromise, successMessage) {
        try {
            await actionPromise;
            this.notify("success", successMessage);
            this.closeWorkspaceModal();
            await this.loadQueueData();
        } catch (error) {
            console.error("Admission action failed:", error);
            this.notify("error", error.message || "Admission action failed");
        }
    },

    reviewApplication: function(applicationId) {
        this.viewApplication(applicationId);
    },

    startIntake: async function(applicationId) {
        try {
            const payload = this.unwrapPayload(await this.apiCall(`/admission/application/${applicationId}`, "GET"));
            const app = payload?.application || {};
            const workflowData = this.parseJsonSafe(app.workflow_data_json || app.data_json || payload.workflow_data || {});
            const documents = Array.isArray(payload.documents) ? payload.documents : [];
            const currentStage = app.current_stage || payload?.stage_metadata?.current_stage || "application_received";
            
            // Get workflow communication
            const workflowComm = this.getApplicationWorkflowCommunication(app, documents, workflowData);
            
            // Route to appropriate action based on current stage and data
            this.routeToWorkflowAction(applicationId, currentStage, workflowComm, app, documents, workflowData);
        } catch (error) {
            console.error("Failed to start intake:", error);
            this.notify("error", error.message || "Failed to start intake");
        }
    },

    getApplicationWorkflowCommunication: function(app = {}, documents = [], workflowData = {}) {
        documents = Array.isArray(documents) ? documents : [];
        workflowData = workflowData || {};
        const currentStage = app.current_stage || "application_received";
        const docCount = documents.length;
        const verifiedCount = documents.filter(doc => doc.verification_status === 'verified').length;
        const rejectedCount = documents.filter(doc => doc.verification_status === 'rejected').length;
        const hasRejectedDocs = rejectedCount > 0;
        
        let label, description, waitingFor, nextActionLabel, nextActionMethod, tone, blockingReason;
        
        switch (currentStage) {
            case 'application_received':
                label = 'Waiting for Application Review';
                description = 'Application has been received and awaits initial review.';
                waitingFor = 'School Admin / Admissions Office';
                nextActionLabel = 'Review Application';
                nextActionMethod = 'reviewApplication';
                tone = 'info';
                break;
                
            case 'application_review':
                label = 'Application Under Review';
                description = 'Application is being reviewed for completeness and basic requirements.';
                waitingFor = 'Admissions Office';
                nextActionLabel = 'Upload Documents';
                nextActionMethod = 'uploadDocuments';
                tone = 'info';
                break;
                
            case 'documents_upload':
                if (docCount === 0) {
                    label = 'Waiting for Document Upload';
                    description = 'Admission documents have not been uploaded yet.';
                    waitingFor = 'School Admin / Admissions Office';
                    nextActionLabel = 'Upload Documents';
                    nextActionMethod = 'uploadDocuments';
                    tone = 'warning';
                } else if (hasRejectedDocs) {
                    label = 'Documents Rejected - Corrections Required';
                    description = 'Some documents were rejected. Upload corrected versions.';
                    waitingFor = 'School Admin / Admissions Office';
                    nextActionLabel = 'Upload Corrected Documents';
                    nextActionMethod = 'uploadDocuments';
                    tone = 'danger';
                    blockingReason = 'Document rejection requires corrections before proceeding.';
                } else {
                    label = 'Documents Uploaded - Pending Verification';
                    description = 'Documents have been uploaded and must be verified.';
                    waitingFor = 'Admissions Office';
                    nextActionLabel = 'Verify Documents';
                    nextActionMethod = 'verifyDocuments';
                    tone = 'info';
                }
                break;
                
            case 'documents_verification':
                if (verifiedCount < docCount) {
                    label = 'Waiting for Document Verification';
                    description = `Documents have been uploaded. ${verifiedCount} of ${docCount} documents verified.`;
                    waitingFor = 'Admissions Office';
                    nextActionLabel = 'Verify Documents';
                    nextActionMethod = 'verifyDocuments';
                    tone = 'warning';
                } else {
                    label = 'Documents Verified - Ready for Space Check';
                    description = 'All mandatory documents have been verified. Next: check class space availability.';
                    waitingFor = 'Admissions Office / Academic Office';
                    nextActionLabel = 'Check Space Availability';
                    nextActionMethod = 'checkClassSpaceAvailability';
                    tone = 'success';
                }
                break;
                
            case 'class_space_check':
                if (workflowData.space_available === true || workflowData.space_available === 'true') {
                    label = 'Class Space Confirmed';
                    description = `Class space is available. ${workflowData.available_spaces || 0} slots available.`;
                    waitingFor = 'Admissions Office';
                    nextActionLabel = 'Schedule Interview';
                    nextActionMethod = 'scheduleInterview';
                    tone = 'success';
                } else {
                    label = 'No Class Space Available';
                    description = workflowData.space_message || 'No space available in the applied class.';
                    waitingFor = 'Academic Office';
                    nextActionLabel = 'Review Alternatives';
                    nextActionMethod = 'viewApplication';
                    tone = 'danger';
                    blockingReason = 'No space available in the applied class for this admission period.';
                }
                break;
                
            case 'interview_scheduling':
                label = 'Waiting for Interview Scheduling';
                description = 'Class space is available. Schedule interview date, time, and venue.';
                waitingFor = 'Admissions Office';
                nextActionLabel = 'Schedule Interview';
                nextActionMethod = 'scheduleInterview';
                tone = 'info';
                break;
                
            case 'interview_results':
                if (workflowData.interview_passed === true || workflowData.interview_passed === 'true') {
                    label = 'Interview Passed - Pending Decision';
                    description = `Applicant passed interview with score: ${workflowData.interview_score || 'N/A'}`;
                    waitingFor = 'Admissions Office / Director';
                    nextActionLabel = 'Admit Student';
                    nextActionMethod = 'admitStudent';
                    tone = 'success';
                } else if (workflowData.interview_passed === false || workflowData.interview_passed === 'false') {
                    label = 'Interview Failed - Application Rejected';
                    description = `Applicant failed interview. Reason: ${workflowData.rejection_reason || 'Not provided'}`;
                    waitingFor = 'None';
                    nextActionLabel = 'View Application';
                    nextActionMethod = 'viewApplication';
                    tone = 'danger';
                    blockingReason = 'Interview failure - application cannot proceed.';
                } else {
                    label = 'Waiting for Interview Results';
                    description = 'Interview has been scheduled. Record results after interview is conducted.';
                    waitingFor = 'Interview Panel / Admissions Office';
                    nextActionLabel = 'Record Results';
                    nextActionMethod = 'conductInterview';
                    tone = 'warning';
                }
                break;
                
            case 'admission_decision':
                if (workflowData.admission_approved === true || workflowData.admission_approved === 'true') {
                    label = 'Admission Approved - Awaiting Student Creation';
                    description = 'Applicant has been admitted. Provisional student record creation pending.';
                    waitingFor = 'Registrar / School Admin';
                    nextActionLabel = 'Create Student Record';
                    nextActionMethod = 'createProvisionalStudent';
                    tone = 'success';
                } else {
                    label = 'Waiting for Admission Decision';
                    description = 'Applicant passed the interview. Confirm admission decision.';
                    waitingFor = 'Admissions Office / Director';
                    nextActionLabel = 'Admit Student';
                    nextActionMethod = 'admitStudent';
                    tone = 'warning';
                }
                break;
                
            case 'provisional_student_creation':
                if (workflowData.provisional_student_created === true || workflowData.provisional_student_created === 'true') {
                    label = 'Student Record Created - Awaiting Fees Payment';
                    description = `Provisional student record created. Admission No: ${workflowData.admission_number || 'N/A'}`;
                    waitingFor = 'Accounts Office';
                    nextActionLabel = 'Record Payment';
                    nextActionMethod = 'recordPayment';
                    tone = 'success';
                } else {
                    label = 'Waiting for Student Record Creation';
                    description = 'Admission approved. Create provisional student record in the system.';
                    waitingFor = 'Registrar / School Admin';
                    nextActionLabel = 'Create Student Record';
                    nextActionMethod = 'createProvisionalStudent';
                    tone = 'warning';
                }
                break;
                
            case 'fees_payment':
                if (workflowData.payment_status === 'paid') {
                    label = 'Fees Paid - Awaiting ID Generation';
                    description = 'Admission fees have been recorded. Student ID card generation pending.';
                    waitingFor = 'School Admin';
                    nextActionLabel = 'Generate ID Card';
                    nextActionMethod = 'generateStudentIdCard';
                    tone = 'success';
                } else {
                    label = 'Waiting for Fees Payment';
                    description = 'Student record has been created provisionally. Record admission fees payment.';
                    waitingFor = 'Accounts Office';
                    nextActionLabel = 'Record Payment';
                    nextActionMethod = 'recordPayment';
                    tone = 'warning';
                }
                break;
                
            case 'student_id_generation':
                if (workflowData.student_id_card_generated === true || workflowData.student_id_card_generated === 'true') {
                    label = 'Student ID Generated - Awaiting Final Approval';
                    description = 'Student identity card has been generated. Final approval pending.';
                    waitingFor = 'Director / Authorized Approver';
                    nextActionLabel = 'Final Approval';
                    nextActionMethod = 'finalApproval';
                    tone = 'success';
                } else {
                    label = 'Waiting for Student ID Generation';
                    description = 'Fees are paid. Generate the student identity card.';
                    waitingFor = 'School Admin';
                    nextActionLabel = 'Generate ID Card';
                    nextActionMethod = 'generateStudentIdCard';
                    tone = 'warning';
                }
                break;
                
            case 'final_approval':
                if (workflowData.final_approval_done === true || workflowData.final_approval_done === 'true') {
                    label = 'Final Approval Complete - Ready for Enrollment';
                    description = 'Final approval is complete. Student ready for enrollment assignment.';
                    waitingFor = 'Registrar / School Admin';
                    nextActionLabel = 'Complete Enrollment';
                    nextActionMethod = 'completeEnrollment';
                    tone = 'success';
                } else {
                    label = 'Waiting for Final Approval';
                    description = 'Student ID card is generated. Final approval required before enrollment.';
                    waitingFor = 'Director / Authorized Approver';
                    nextActionLabel = 'Final Approval';
                    nextActionMethod = 'finalApproval';
                    tone = 'warning';
                }
                break;
                
            case 'enrollment':
                label = 'Enrollment in Progress';
                description = 'Final approval complete. Assign class, dormitory if boarder, registers, and learning areas.';
                waitingFor = 'Registrar / School Admin';
                nextActionLabel = 'Complete Enrollment';
                nextActionMethod = 'completeEnrollment';
                tone = 'info';
                break;
                
            case 'enrolled':
                label = 'Enrolled';
                description = 'Student has been fully enrolled. No intake action is pending.';
                waitingFor = 'None';
                nextActionLabel = 'View Student';
                nextActionMethod = 'viewApplication';
                tone = 'success';
                break;
                
            case 'rejected':
                label = 'Rejected';
                description = workflowData.rejection_reason || 'Application was rejected.';
                waitingFor = 'None';
                nextActionLabel = 'View Application';
                nextActionMethod = 'viewApplication';
                tone = 'danger';
                blockingReason = 'Application rejected - workflow cannot continue.';
                break;
                
            default:
                label = `Stage: ${currentStage}`;
                description = 'Application is currently being processed.';
                waitingFor = 'Admissions Office';
                nextActionLabel = 'Review Application';
                nextActionMethod = 'viewApplication';
                tone = 'info';
        }
        
        return {
            stage: currentStage,
            label,
            description,
            waitingFor,
            nextActionLabel,
            nextActionMethod,
            tone,
            blockingReason
        };
    },

    routeToWorkflowAction: function(applicationId, currentStage, workflowComm, app, documents, workflowData) {
        // If there's a blocking reason, show it and don't continue
        if (workflowComm.blockingReason) {
            this.showWorkflowMessageModal(applicationId, {
                title: workflowComm.label,
                message: workflowComm.description,
                blockingReason: workflowComm.blockingReason,
                waitingFor: workflowComm.waitingFor,
                showAction: false
            });
            return;
        }
        
        // Route to the appropriate action method
        const actionMethod = workflowComm.nextActionMethod;
        
        // Check if the method exists in the controller
        if (typeof this[actionMethod] === 'function') {
            // For methods that need applicationId
            this[actionMethod](applicationId);
        } else {
            console.warn(`Action method ${actionMethod} not found, falling back to viewApplication`);
            this.viewApplication(applicationId);
        }
    },

    showWorkflowMessageModal: function(applicationId, options) {
        const { title, message, blockingReason, waitingFor, showAction = true, actionLabel = 'Continue', actionMethod = 'viewApplication' } = options;
        
        let alertClass = 'alert-info';
        if (blockingReason) alertClass = 'alert-danger';
        
        const html = `
            <div class="${alertClass} mb-3">
                <h6 class="alert-heading">${this.escapeHtml(title)}</h6>
                <p class="mb-2">${this.escapeHtml(message)}</p>
                ${blockingReason ? `<p class="mb-0"><strong>Blocking:</strong> ${this.escapeHtml(blockingReason)}</p>` : ''}
                ${waitingFor && waitingFor !== 'None' ? `<p class="mb-0"><small class="text-muted">Waiting for: ${this.escapeHtml(waitingFor)}</small></p>` : ''}
            </div>
        `;
        
        const footer = showAction ? `
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary" onclick="admissionsWorkspaceController.${actionMethod}(${applicationId})">${this.escapeHtml(actionLabel)}</button>
        ` : `
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        `;
        
        this.showWorkspaceModal('<i class="bi bi-info-circle me-2"></i>Workflow Status', html, footer);
    },

    renderApplicationActionFooter: function(applicationId, app = {}, documents = [], workflowData = {}) {
        const closeBtn = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>';
        const stage = app.current_stage || "application_received";

        // Single source of truth: derive the next action exactly as startIntake does.
        const comm = this.getApplicationWorkflowCommunication(app, documents, workflowData);
        if (comm && comm.nextActionMethod && comm.nextActionLabel) {
            const method = comm.nextActionMethod;
            const label = comm.nextActionLabel;
            const icon = comm.actionIcon || "bi-arrow-right-circle";
            const btn = `<button type="button" class="btn btn-primary" onclick="admissionsWorkspaceController.${method}(${Number(applicationId)})"><i class="bi ${icon} me-1"></i>${this.escapeHtml(label)}</button>`;
            return closeBtn + btn;
        }

        // Fallback: older/local states not yet mapped (should not normally hit).
        const buttons = [closeBtn];
        if (["application", "application_submission", "document_verification"].includes(stage)) {
            buttons.push(`<button type="button" class="btn btn-primary" onclick="admissionsWorkspaceController.uploadDocuments(${Number(applicationId)})"><i class="bi bi-upload me-1"></i>Upload Documents</button>`);
        }
        if (stage === "interview_scheduling") {
            buttons.push(`<button type="button" class="btn btn-primary" onclick="admissionsWorkspaceController.scheduleInterview(${Number(applicationId)})"><i class="bi bi-calendar-plus me-1"></i>Schedule Interview</button>`);
        }
        if (stage === "interview_assessment") {
            buttons.push(`<button type="button" class="btn btn-info" onclick="admissionsWorkspaceController.conductInterview(${Number(applicationId)})"><i class="bi bi-clipboard-check me-1"></i>Record Interview</button>`);
        }
        if (stage === "placement_offer") {
            buttons.push(`<button type="button" class="btn btn-success" onclick="admissionsWorkspaceController.generatePlacement(${Number(applicationId)})"><i class="bi bi-award me-1"></i>Generate Placement</button>`);
        }
        if (stage === "fee_payment") {
            buttons.push(`<button type="button" class="btn btn-primary" onclick="admissionsWorkspaceController.recordPayment(${Number(applicationId)})"><i class="bi bi-cash-coin me-1"></i>Record Payment</button>`);
        }
        if (stage === "enrollment") {
            buttons.push(`<button type="button" class="btn btn-success" onclick="admissionsWorkspaceController.completeEnrollment(${Number(applicationId)})"><i class="bi bi-person-check me-1"></i>Complete Enrollment</button>`);
        }
        if (stage === "director_confirmation") {
            buttons.push(`<button type="button" class="btn btn-danger" onclick="admissionsWorkspaceController.finalApproval(${Number(applicationId)})"><i class="bi bi-check-circle me-1"></i>Confirm Enrollment</button>`);
        }

        return buttons.join("");
    },

    getAdmissionDocumentTypes: function() {
        return [
            { value: "birth_certificate", label: "Birth Certificate" },
            { value: "immunization_card", label: "Immunization Card" },
            { value: "passport_photo", label: "Passport Photo" },
            { value: "progress_report", label: "Previous School Report" },
            { value: "leaving_certificate", label: "Leaving Certificate" },
            { value: "parent_id", label: "Parent / Guardian ID" },
            { value: "medical_records", label: "Medical Records" },
            { value: "transfer_letter", label: "Transfer Letter" },
            { value: "behavior_report", label: "Behavior Report" },
            { value: "nemis_upi", label: "NEMIS / UPI Document" },
            { value: "other", label: "Other" }
        ];
    },

    uploadDocuments: async function(applicationId) {
        let existingDocuments = [];

        try {
            const payload = this.unwrapPayload(
                await this.apiCall(`/admission/application/${applicationId}`, "GET")
            );
            existingDocuments = Array.isArray(payload.documents) ? payload.documents : [];
            this.currentApplicationId = applicationId;
            this.currentApplicationData = payload;
        } catch (error) {
            console.warn("Could not load existing admission documents:", error);
        }

        const uploadedTypes = new Set(
            existingDocuments
                .map((doc) => doc.document_type)
                .filter(Boolean)
        );

        this.showWorkspaceModal(
            '<i class="bi bi-upload me-2"></i>Upload Admission Documents',
            `
                <form id="workspaceUploadDocumentsForm">
                    <input type="hidden" name="application_id" value="${Number(applicationId)}">

                    <div class="alert alert-info small mb-3">
                        Select each document type, choose its file, then submit all selected documents once.
                        Already uploaded documents are marked below.
                    </div>

                    <div class="table-responsive border rounded">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 45%;">Document Type</th>
                                    <th style="width: 55%;">File</th>
                                </tr>
                            </thead>
                            <tbody id="workspaceUploadDocumentsRows">
                                ${this.renderAdmissionDocumentUploadRows(uploadedTypes)}
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <button type="button" class="btn btn-outline-secondary btn-sm"
                            onclick="admissionsWorkspaceController.addAdmissionDocumentUploadRow()">
                            <i class="bi bi-plus-circle me-1"></i>Add Another Row
                        </button>

                        <div id="workspaceUploadDocumentStatus" class="small text-muted"></div>
                    </div>

                    <div id="workspaceUploadPreview" class="mt-3"></div>
                </form>

                <div class="mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-semibold mb-0">Saved Documents</h6>
                        <span class="badge bg-secondary" id="workspaceUploadedDocumentCount">${existingDocuments.length}</span>
                    </div>

                    <div id="workspaceUploadedDocumentsList">
                        ${this.renderUploadedDocumentsList(existingDocuments)}
                    </div>
                </div>
            `,
            `
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>

                <button type="button" class="btn btn-success" onclick="admissionsWorkspaceController.previewAdmissionDocumentUploads(${Number(applicationId)})">
                    Preview Files
                </button>

                <button type="submit" form="workspaceUploadDocumentsForm" class="btn btn-primary">
                    <i class="bi bi-cloud-upload me-1"></i>Submit Documents
                </button>
            `
        );

        document.getElementById("workspaceUploadDocumentsForm")?.addEventListener("submit", (event) => {
            event.preventDefault();
            this.saveAdmissionDocumentsBatch(applicationId, event.currentTarget);
        });

        document.getElementById("workspaceUploadDocumentsRows")?.addEventListener("change", () => {
            this.previewAdmissionDocumentUploads(applicationId, false);
        });
    },

    renderAdmissionDocumentUploadRows: function(uploadedTypes = new Set()) {
        const documentTypes = this.getAdmissionDocumentTypes();

        return documentTypes.map((docType) => {
            const alreadyUploaded = uploadedTypes.has(docType.value);

            return `
                <tr class="${alreadyUploaded ? "table-success" : ""}">
                    <td>
                        <select name="document_type[]" class="form-select form-select-sm workspace-document-type"
                            ${alreadyUploaded ? "disabled" : ""}>
                            <option value="">Select document...</option>
                            ${documentTypes.map((option) => `
                                <option value="${this.escapeHtml(option.value)}"
                                    ${option.value === docType.value ? "selected" : ""}>
                                    ${this.escapeHtml(option.label)}
                                </option>
                            `).join("")}
                        </select>

                        ${alreadyUploaded ? `
                            <div class="small text-success mt-1">
                                <i class="bi bi-check-circle me-1"></i>Already uploaded
                            </div>
                        ` : ""}
                    </td>

                    <td>
                        <input type="file"
                            name="document[]"
                            class="form-control form-control-sm workspace-document-file"
                            ${alreadyUploaded ? "disabled" : ""}>

                        ${alreadyUploaded ? `
                            <div class="small text-muted mt-1">
                                Upload disabled because this document already exists.
                            </div>
                        ` : ""}
                    </td>
                </tr>
            `;
        }).join("");
    },

    addAdmissionDocumentUploadRow: function() {
        const rowsElement = document.getElementById("workspaceUploadDocumentsRows");
        if (!rowsElement) return;

        const documentTypes = this.getAdmissionDocumentTypes();

        rowsElement.insertAdjacentHTML("beforeend", `
            <tr>
                <td>
                    <select name="document_type[]" class="form-select form-select-sm workspace-document-type">
                        <option value="">Select document...</option>
                        ${documentTypes.map((option) => `
                            <option value="${this.escapeHtml(option.value)}">
                                ${this.escapeHtml(option.label)}
                            </option>
                        `).join("")}
                    </select>
                </td>

                <td>
                    <input type="file" name="document[]" class="form-control form-control-sm workspace-document-file">
                </td>
            </tr>
        `);
    },

    collectAdmissionDocumentUploadRows: function(form) {
        const rows = Array.from(form.querySelectorAll("#workspaceUploadDocumentsRows tr"));
        const uploadRows = [];

        rows.forEach((row) => {
            const typeInput = row.querySelector(".workspace-document-type");
            const fileInput = row.querySelector(".workspace-document-file");

            if (!typeInput || !fileInput || typeInput.disabled || fileInput.disabled) return;

            const documentType = typeInput.value;
            const file = fileInput.files?.[0];

            if (documentType && file) {
                uploadRows.push({ documentType, file });
            }
        });

        return uploadRows;
    },

    getAdmissionDocumentTypeLabel: function(documentType) {
        const match = this.getAdmissionDocumentTypes().find((type) => type.value === documentType);
        return match ? match.label : this.formatLabel(documentType || "Document");
    },

    buildAdmissionDocumentPreviewName: function(applicationId, documentType, file) {
        const application = this.currentApplicationData?.application || {};
        const applicantName = application.applicant_name || "Applicant";
        const applicationNo = application.application_no || `Application_${Number(applicationId)}`;
        const documentLabel = this.getAdmissionDocumentTypeLabel(documentType);
        const extension = file?.name && file.name.includes(".") ? file.name.split(".").pop() : "";
        const baseName = `${applicantName}_${documentLabel}_${applicationNo}`
            .trim()
            .replace(/[^a-zA-Z0-9]+/g, "_")
            .replace(/^_+|_+$/g, "")
            .slice(0, 140);

        return extension ? `${baseName}.${extension.toLowerCase()}` : baseName;
    },

    previewAdmissionDocumentUploads: function(applicationId, showEmptyWarning = true) {
        const form = document.getElementById("workspaceUploadDocumentsForm");
        const previewElement = document.getElementById("workspaceUploadPreview");
        const statusElement = document.getElementById("workspaceUploadDocumentStatus");
        if (!form || !previewElement) return [];

        const uploadRows = this.collectAdmissionDocumentUploadRows(form);

        if (uploadRows.length === 0) {
            previewElement.innerHTML = "";
            if (showEmptyWarning) {
                this.notify("warning", "Select at least one document type and file to preview.");
                if (statusElement) {
                    statusElement.className = "small text-warning";
                    statusElement.textContent = "No files selected for preview.";
                }
            }
            return [];
        }

        previewElement.innerHTML = `
            <div class="border rounded p-3 bg-light">
                <h6 class="fw-semibold mb-2">Files Ready For Upload</h6>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Document Type</th>
                                <th>Original File</th>
                                <th>Will Be Saved As</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${uploadRows.map((row) => `
                                <tr>
                                    <td>${this.escapeHtml(this.getAdmissionDocumentTypeLabel(row.documentType))}</td>
                                    <td>${this.escapeHtml(row.file.name)}</td>
                                    <td class="text-break">${this.escapeHtml(this.buildAdmissionDocumentPreviewName(applicationId, row.documentType, row.file))}</td>
                                </tr>
                            `).join("")}
                        </tbody>
                    </table>
                </div>
            </div>
        `;

        if (statusElement) {
            statusElement.className = "small text-muted";
            statusElement.textContent = `${uploadRows.length} file(s) ready. Click Submit Documents to upload.`;
        }

        return uploadRows;
    },

    saveAdmissionDocumentsBatch: async function(applicationId, form) {
        const submitButton = document.querySelector('button[form="workspaceUploadDocumentsForm"]');
        const statusElement = document.getElementById("workspaceUploadDocumentStatus");
        const initialDocumentCount = Number(document.getElementById("workspaceUploadedDocumentCount")?.textContent || 0);

        const uploadRows = this.previewAdmissionDocumentUploads(applicationId, false);

        if (uploadRows.length === 0) {
            this.notify("warning", "Select at least one document type and file before submitting.");
            if (statusElement) {
                statusElement.className = "small text-warning";
                statusElement.textContent = "No new documents selected.";
            }
            return;
        }

        const selectedTypes = uploadRows.map((row) => row.documentType);
        const duplicateTypes = selectedTypes.filter((type, index) => selectedTypes.indexOf(type) !== index);

        if (duplicateTypes.length > 0) {
            this.notify("warning", "You selected the same document type more than once.");
            if (statusElement) {
                statusElement.className = "small text-warning";
                statusElement.textContent = "Remove duplicate document types before submitting.";
            }
            return;
        }

        try {
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Submitting...';
            }

            if (statusElement) {
                statusElement.className = "small text-muted";
                statusElement.textContent = `Uploading ${uploadRows.length} document(s)...`;
            }

            let uploadedCount = 0;

            for (const row of uploadRows) {
                const formData = new FormData();
                formData.append("application_id", Number(applicationId));
                formData.append("document_type", row.documentType);
                formData.append("document", row.file);

                await this.apiCall(
                    "/admission/upload-document",
                    "POST",
                    formData,
                    {},
                    { isFile: true }
                );

                uploadedCount++;

                if (statusElement) {
                    statusElement.textContent = `Uploaded ${uploadedCount} of ${uploadRows.length} document(s)...`;
                }
            }

            const payload = this.unwrapPayload(
                await this.apiCall(`/admission/application/${applicationId}`, "GET")
            );

            const documents = Array.isArray(payload.documents) ? payload.documents : [];
            if (documents.length < initialDocumentCount + uploadedCount) {
                throw new Error("Upload response completed, but saved documents were not found on the application record. Please try again.");
            }

            const listElement = document.getElementById("workspaceUploadedDocumentsList");
            const countElement = document.getElementById("workspaceUploadedDocumentCount");

            if (listElement) {
                listElement.innerHTML = this.renderUploadedDocumentsList(documents);
            }

            if (countElement) {
                countElement.textContent = documents.length;
            }

            const uploadedTypes = new Set(
                documents.map((doc) => doc.document_type).filter(Boolean)
            );

            const rowsElement = document.getElementById("workspaceUploadDocumentsRows");
            if (rowsElement) {
                rowsElement.innerHTML = this.renderAdmissionDocumentUploadRows(uploadedTypes);
            }

            if (statusElement) {
                statusElement.className = "small text-success";
                statusElement.textContent = `${uploadedCount} document(s) submitted successfully. Closing...`;
            }

            this.notify("success", "Admission documents submitted successfully.");
            this.closeWorkspaceModal();
            await this.loadQueueData();
        } catch (error) {
            console.error("Document upload failed:", error);

            if (statusElement) {
                statusElement.className = "small text-danger";
                statusElement.textContent = error.message || "Document upload failed";
            }

            this.notify("error", error.message || "Document upload failed");
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="bi bi-cloud-upload me-1"></i>Submit Documents';
            }
        }
    },

    renderUploadedDocumentsList: function(documents) {
        if (!Array.isArray(documents) || documents.length === 0) {
            return '<div class="text-muted small border rounded p-3">No documents saved yet.</div>';
        }

        return `
            <div class="list-group">
                ${documents.map((doc) => `
                    <div class="list-group-item d-flex justify-content-between align-items-start gap-3">
                        <div class="min-w-0">
                            <div class="fw-semibold">${this.escapeHtml(this.formatLabel(doc.document_type || "Document"))}</div>
                            ${doc.file_url || doc.download_url || doc.document_path ? `
                                <a href="${this.escapeHtml(doc.file_url || doc.download_url || doc.document_path)}"
                                    target="_blank"
                                    rel="noopener"
                                    class="small text-break">
                                    ${this.escapeHtml(doc.display_name || doc.document_path || "Open saved document")}
                                </a>
                            ` : '<small class="text-muted">Path recorded</small>'}
                        </div>
                        ${this.getStatusBadge(doc.verification_status || "pending")}
                    </div>
                `).join("")}
            </div>
        `;
    },

    verifyDocuments: async function(applicationId) {
        try {
            const payload = this.unwrapPayload(await this.apiCall(`/admission/application/${applicationId}`, "GET"));
            const documents = Array.isArray(payload.documents) ? payload.documents : [];

            if (documents.length === 0) {
                this.showWorkspaceModal(
                    '<i class="bi bi-file-earmark-check me-2"></i>Verify Documents',
                    '<div class="alert alert-warning mb-0">No documents have been uploaded for this application. Upload documents first, then verify them.</div>',
                    `
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="admissionsWorkspaceController.uploadDocuments(${Number(applicationId)})">
                            <i class="bi bi-upload me-1"></i>Upload Documents
                        </button>
                    `
                );
                return;
            }

            this.currentVerificationApplicationId = applicationId;

            this.showWorkspaceModal(
                '<i class="bi bi-file-earmark-check me-2"></i>Verify Documents',
                `
                    <div class="list-group">
                        ${documents.map((doc) => `
                            <div class="list-group-item" id="document-verification-row-${Number(doc.id)}">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <div>
                                        <div class="fw-semibold">${this.escapeHtml(this.formatLabel(doc.document_type || "Document"))}</div>
                                        <small class="${doc.verification_status === "verified" ? "text-success" : doc.verification_status === "rejected" ? "text-danger" : "text-muted"}" id="document-verification-status-${Number(doc.id)}">Status: ${this.escapeHtml(this.formatLabel(doc.verification_status || "pending"))}</small>
                                        ${doc.file_url || doc.download_url || doc.document_path ? `
                                            <div class="small mt-1">
                                                <a href="${this.escapeHtml(doc.file_url || doc.download_url || doc.document_path)}" target="_blank" rel="noopener">
                                                    Open uploaded file
                                                </a>
                                            </div>
                                        ` : ""}
                                    </div>
                                    <div class="btn-group btn-group-sm" id="document-verification-actions-${Number(doc.id)}">
                                        ${doc.verification_status === "verified" ? '<span class="badge bg-success">Verified</span>' : doc.verification_status === "rejected" ? '<span class="badge bg-danger">Rejected</span>' : `
                                            <button class="btn btn-outline-success" onclick="admissionsWorkspaceController.setDocumentVerification(${Number(doc.id)}, 'verified', ${Number(applicationId)})">
                                                Verify
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="admissionsWorkspaceController.setDocumentVerification(${Number(doc.id)}, 'rejected', ${Number(applicationId)})">
                                                Reject
                                            </button>
                                        `}
                                    </div>
                                </div>
                            </div>
                        `).join("")}
                    </div>
                `
            );
        } catch (error) {
            console.error("Failed to load documents:", error);
            this.notify("error", error.message || "Failed to load documents");
        }
    },

    setDocumentVerification: async function(documentId, status, applicationId = null) {
        const numericDocumentId = Number(documentId);
        const statusElement = document.getElementById(`document-verification-status-${numericDocumentId}`);
        const actionsElement = document.getElementById(`document-verification-actions-${numericDocumentId}`);
        const buttons = actionsElement ? Array.from(actionsElement.querySelectorAll("button")) : [];
        const previousStatusText = statusElement?.textContent || "";

        buttons.forEach((button) => {
            button.disabled = true;
        });
        if (statusElement) {
            statusElement.textContent = `Status: ${status === "verified" ? "Verifying..." : "Rejecting..."}`;
        }

        try {
            await this.apiCall("/admission/verify-document", "POST", {
                document_id: documentId,
                status,
                notes: status === "verified" ? "Verified from admissions workspace" : "Rejected from admissions workspace"
            });

            if (statusElement) {
                statusElement.textContent = `Status: ${this.formatLabel(status)}`;
                statusElement.classList.remove("text-muted", "text-success", "text-danger");
                statusElement.classList.add(status === "verified" ? "text-success" : "text-danger");
            }

            if (actionsElement) {
                actionsElement.innerHTML = status === "verified"
                    ? '<span class="badge bg-success">Verified</span>'
                    : '<span class="badge bg-danger">Rejected</span>';
            }

            this.notify("success", status === "verified" ? "Document verified" : "Document rejected");
            await this.loadQueueData();
        } catch (error) {
            console.error("Admission action failed:", error);
            if (statusElement) {
                statusElement.textContent = previousStatusText;
            }
            buttons.forEach((button) => {
                button.disabled = false;
            });
            this.notify("error", error.message || "Admission action failed");
        }
    },

    scheduleInterview: function(applicationId) {
        this.showWorkspaceModal(
            '<i class="bi bi-calendar-plus me-2"></i>Schedule Interview',
            `
                <form id="workspaceScheduleInterviewForm" class="row g-3">
                    <input type="hidden" name="application_id" value="${Number(applicationId)}">
                    <div class="col-md-4">
                        <label class="form-label">Date</label>
                        <input type="date" name="interview_date" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Time</label>
                        <input type="time" name="interview_time" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Venue</label>
                        <input type="text" name="venue" class="form-control" value="Main Office" required>
                    </div>
                </form>
            `,
            `
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="workspaceScheduleInterviewForm" class="btn btn-primary">Schedule</button>
            `
        );

        document.getElementById("workspaceScheduleInterviewForm")?.addEventListener("submit", (event) => {
            event.preventDefault();
            this.runAdmissionAction(
                this.apiCall("/admission/schedule-interview", "POST", Object.fromEntries(new FormData(event.currentTarget))),
                "Interview scheduled successfully"
            );
        });
    },

    conductInterview: function(applicationId) {
        this.showWorkspaceModal(
            '<i class="bi bi-clipboard-check me-2"></i>Record Interview Results',
            `
                <form id="workspaceInterviewResultForm" class="row g-3">
                    <input type="hidden" name="application_id" value="${Number(applicationId)}">
                    <div class="col-md-4">
                        <label class="form-label">Score</label>
                        <input type="number" name="score" class="form-control" min="0" max="100" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </form>
            `,
            `
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="workspaceInterviewResultForm" class="btn btn-info">Save Results</button>
            `
        );

        document.getElementById("workspaceInterviewResultForm")?.addEventListener("submit", (event) => {
            event.preventDefault();
            const data = Object.fromEntries(new FormData(event.currentTarget));
            this.runAdmissionAction(
                this.apiCall("/admission/record-interview-results", "POST", data),
                "Interview results recorded"
            );
        });
    },

    makeDecision: function(applicationId) {
        this.generatePlacement(applicationId);
    },

    generatePlacement: async function(applicationId) {
        try {
            const response = await this.apiCall("/admission/placement-classes", "GET");
            const payload = this.unwrapPayload(response);
            const classes = payload.classes || payload.data?.classes || [];

            this.showWorkspaceModal(
                '<i class="bi bi-award me-2"></i>Generate Placement Offer',
                `
                    <form id="workspacePlacementForm" class="row g-3">
                        <input type="hidden" name="application_id" value="${Number(applicationId)}">
                        <div class="col-md-8">
                            <label class="form-label">Assigned Class</label>
                            <select name="assigned_class_id" class="form-select" required>
                                <option value="">Select class...</option>
                                ${classes.map((cls) => `
                                    <option value="${Number(cls.id)}">${this.escapeHtml(cls.name)}${cls.capacity ? ` (${Number(cls.student_count || 0)}/${Number(cls.capacity)})` : ""}</option>
                                `).join("")}
                            </select>
                        </div>
                    </form>
                `,
                `
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="workspacePlacementForm" class="btn btn-success">Generate Offer</button>
                `
            );

            document.getElementById("workspacePlacementForm")?.addEventListener("submit", (event) => {
                event.preventDefault();
                this.runAdmissionAction(
                    this.apiCall("/admission/generate-placement-offer", "POST", Object.fromEntries(new FormData(event.currentTarget))),
                    "Placement offer generated"
                );
            });
        } catch (error) {
            console.error("Failed to load placement classes:", error);
            this.notify("error", error.message || "Failed to load placement classes");
        }
    },

    recordPayment: function(applicationId) {
        this.showWorkspaceModal(
            '<i class="bi bi-cash-coin me-2"></i>Record Admission Payment',
            `
                <form id="workspacePaymentForm" class="row g-3">
                    <input type="hidden" name="application_id" value="${Number(applicationId)}">
                    <div class="col-md-4">
                        <label class="form-label">Amount</label>
                        <input type="number" name="amount" class="form-control" min="1" step="0.01" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Method</label>
                        <select name="method" class="form-select" required>
                            <option value="">Select method...</option>
                            <option value="cash">Cash</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="bank">Bank</option>
                            <option value="card">Card</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Reference</label>
                        <input type="text" name="reference" class="form-control">
                    </div>
                </form>
            `,
            `
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="workspacePaymentForm" class="btn btn-primary">Record Payment</button>
            `
        );

        document.getElementById("workspacePaymentForm")?.addEventListener("submit", (event) => {
            event.preventDefault();
            this.runAdmissionAction(
                this.apiCall("/admission/record-fee-payment", "POST", Object.fromEntries(new FormData(event.currentTarget))),
                "Payment recorded"
            );
        });
    },

    completeEnrollment: function(applicationId) {
        if (!confirm("Complete enrollment and create the student record for this application?")) {
            return;
        }

        this.runAdmissionAction(
            this.apiCall("/admission/complete-enrollment", "POST", { application_id: applicationId }),
            "Enrollment completed"
        );
    },

    finalApproval: function(applicationId) {
        this.showWorkspaceModal(
            '<i class="bi bi-check-circle me-2"></i>Confirm Enrollment',
            `
                <form id="workspaceConfirmEnrollmentForm">
                    <input type="hidden" name="application_id" value="${Number(applicationId)}">
                    <label class="form-label">Confirmation Notes</label>
                    <textarea name="notes" class="form-control" rows="3"></textarea>
                </form>
            `,
            `
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="workspaceConfirmEnrollmentForm" class="btn btn-danger">Confirm</button>
            `
        );

        document.getElementById("workspaceConfirmEnrollmentForm")?.addEventListener("submit", (event) => {
            event.preventDefault();
            this.runAdmissionAction(
                this.apiCall("/admission/confirm-enrollment", "POST", Object.fromEntries(new FormData(event.currentTarget))),
                "Enrollment confirmed"
            );
        });
    },

    checkClassSpaceAvailability: async function(applicationId) {
        try {
            const response = await this.apiCall(`/admission/check-class-space/${applicationId}`, "GET");
            const payload = this.unwrapPayload(response);
            
            const spaceData = payload.space_check || payload;
            const spaceAvailable = spaceData.space_available;
            const availableSpaces = spaceData.available_spaces || 0;
            const spaceMessage = spaceData.space_message || '';
            const classId = spaceData.class_id;
            const capacity = spaceData.capacity || 0;
            const currentCount = spaceData.current_count || 0;
            
            const alertClass = spaceAvailable ? 'alert-success' : 'alert-danger';
            const actionLabel = spaceAvailable ? 'Confirm Space Available' : 'Review Alternatives';
            const actionMethod = spaceAvailable ? 'confirmClassSpaceAvailability' : 'viewApplication';
            
            this.showWorkspaceModal(
                '<i class="bi bi-building me-2"></i>Class Space Availability',
                `
                    <div class="${alertClass} mb-3">
                        <h6 class="alert-heading">${spaceAvailable ? 'Space Available' : 'No Space Available'}</h6>
                        <p class="mb-2">${this.escapeHtml(spaceMessage)}</p>
                        <hr>
                        <div class="row">
                            <div class="col-6"><strong>Class Capacity:</strong> ${capacity}</div>
                            <div class="col-6"><strong>Current Students:</strong> ${currentCount}</div>
                            <div class="col-6"><strong>Available Spaces:</strong> ${availableSpaces}</div>
                            <div class="col-6"><strong>Class ID:</strong> ${classId || 'N/A'}</div>
                        </div>
                    </div>
                    ${spaceAvailable ? `
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="confirmSpaceCheck" checked>
                            <label class="form-check-label" for="confirmSpaceCheck">
                                Confirm that class space is available for this admission
                            </label>
                        </div>
                        <textarea id="spaceCheckNotes" class="form-control mb-3" rows="2" placeholder="Additional notes (optional)"></textarea>
                    ` : ''}
                `,
                `
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    ${spaceAvailable ? `
                        <button type="button" class="btn btn-success" onclick="admissionsWorkspaceController.confirmClassSpaceAvailability(${applicationId}, ${classId}, ${availableSpaces})">
                            ${this.escapeHtml(actionLabel)}
                        </button>
                    ` : `
                        <button type="button" class="btn btn-primary" onclick="admissionsWorkspaceController.viewApplication(${applicationId})">
                            ${this.escapeHtml(actionLabel)}
                        </button>
                    `}
                `
            );
        } catch (error) {
            console.error("Failed to check class space:", error);
            this.notify("error", error.message || "Failed to check class space availability");
        }
    },

    confirmClassSpaceAvailability: async function(applicationId, classId, availableSpaces) {
        const notes = document.getElementById('spaceCheckNotes')?.value || '';
        const confirmSpaceCheck = document.getElementById('confirmSpaceCheck')?.checked;
        
        if (!confirmSpaceCheck) {
            this.notify("error", "Please confirm that class space is available");
            return;
        }
        
        try {
            const workflowUpdates = JSON.stringify({
                space_checked: true,
                space_available: true,
                available_spaces: availableSpaces,
                class_checked_id: classId,
                space_checked_at: new Date().toISOString()
            });
            
            await this.apiCall(`/admission/check-class-space/${applicationId}`, "POST", {
                application_id: applicationId,
                available: true,
                notes: notes
            });

            this.notify("success", "Class space confirmed. Next: schedule interview.");
            this.closeWorkspaceModal();
            await this.loadQueueData();
        } catch (error) {
            console.error("Failed to confirm class space:", error);
            this.notify("error", error.message || "Failed to confirm class space");
        }
    },

    admitStudent: async function(applicationId) {
        try {
            const payload = this.unwrapPayload(await this.apiCall(`/admission/application/${applicationId}`, "GET"));
            const app = payload?.application || {};
            
            this.showWorkspaceModal(
                '<i class="bi bi-person-check me-2"></i>Admit Student',
                `
                    <div class="alert alert-info mb-3">
                        <h6 class="alert-heading">Confirm Admission Decision</h6>
                        <p class="mb-2">You are about to admit <strong>${this.escapeHtml(app.applicant_name)}</strong> to <strong>${this.escapeHtml(app.grade_applying_for)}</strong>.</p>
                        <p class="mb-0">This will create a provisional student record and move to the fees payment stage.</p>
                    </div>
                    <form id="workspaceAdmitStudentForm">
                        <input type="hidden" name="application_id" value="${Number(applicationId)}">
                        <div class="mb-3">
                            <label class="form-label">Admission Notes</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Any special conditions or notes for this admission"></textarea>
                        </div>
                    </form>
                `,
                `
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="workspaceAdmitStudentForm" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i>Admit Student
                    </button>
                `
            );

            document.getElementById("workspaceAdmitStudentForm")?.addEventListener("submit", async (event) => {
                event.preventDefault();
                const formData = Object.fromEntries(new FormData(event.currentTarget));
                
                try {
                    const workflowUpdates = JSON.stringify({
                        admission_approved: true,
                        admission_approved_at: new Date().toISOString(),
                        admission_notes: formData.notes
                    });
                    
                    await this.apiCall(`/admission/admit-student/${applicationId}`, "POST", {
                        application_id: applicationId,
                        notes: formData.notes
                    });
                    
                    this.notify("success", "Student admitted. Next: create provisional student record.");
                    this.closeWorkspaceModal();
                    await this.loadQueueData();
                } catch (error) {
                    console.error("Failed to admit student:", error);
                    this.notify("error", error.message || "Failed to admit student");
                }
            });
        } catch (error) {
            console.error("Failed to load application for admission:", error);
            this.notify("error", error.message || "Failed to load application");
        }
    },

    createProvisionalStudent: async function(applicationId) {
        try {
            const response = await this.apiCall(`/admission/create-provisional-student/${applicationId}`, "POST");
            const payload = this.unwrapPayload(response);
            
            if (payload.success) {
                this.notify("success", `Provisional student record created. Admission No: ${payload.admission_number || 'N/A'}`);
                this.closeWorkspaceModal();
                await this.loadQueueData();
            } else {
                throw new Error(payload.message || "Failed to create provisional student");
            }
        } catch (error) {
            console.error("Failed to create provisional student:", error);
            this.notify("error", error.message || "Failed to create provisional student record");
        }
    },

    generateStudentIdCard: async function(applicationId) {
        try {
            const payload = this.unwrapPayload(await this.apiCall(`/admission/application/${applicationId}`, "GET"));
            const app = payload?.application || {};
            const workflowData = this.parseJsonSafe(app.workflow_data_json || '{}');
            
            // Check if student ID exists
            const studentId = workflowData.student_id || app.enrolled_student_id;
            
            if (!studentId) {
                this.notify("error", "Student record not found. Cannot generate ID card.");
                return;
            }
            
            this.showWorkspaceModal(
                '<i class="bi bi-credit-card me-2"></i>Generate Student ID Card',
                `
                    <div class="alert alert-info mb-3">
                        <h6 class="alert-heading">Student ID Card Generation</h6>
                        <p class="mb-2">Generate ID card for <strong>${this.escapeHtml(app.applicant_name)}</strong></p>
                        <p class="mb-0">Admission No: ${this.escapeHtml(workflowData.admission_number || 'N/A')}</p>
                    </div>
                    <form id="workspaceGenerateIdCardForm">
                        <input type="hidden" name="application_id" value="${Number(applicationId)}">
                        <input type="hidden" name="student_id" value="${Number(studentId)}">
                        <div class="mb-3">
                            <label class="form-label">Expected Expiry Year</label>
                            <input type="number" name="expiry_year" class="form-control" value="${new Date().getFullYear() + 1}" min="${new Date().getFullYear()}" max="${new Date().getFullYear() + 5}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Any special notes for ID card generation"></textarea>
                        </div>
                    </form>
                `,
                `
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="workspaceGenerateIdCardForm" class="btn btn-primary">
                        <i class="bi bi-credit-card me-1"></i>Generate ID Card
                    </button>
                `
            );

            document.getElementById("workspaceGenerateIdCardForm")?.addEventListener("submit", async (event) => {
                event.preventDefault();
                const formData = Object.fromEntries(new FormData(event.currentTarget));
                
                try {
                    const workflowUpdates = JSON.stringify({
                        student_id_card_generated: true,
                        student_id_card_generated_at: new Date().toISOString(),
                        expiry_year: formData.expiry_year
                    });
                    
                    await this.apiCall(`/admission/generate-student-id-card/${applicationId}`, "POST", {
                        application_id: applicationId,
                        notes: formData.notes
                    });
                    
                    this.notify("success", "Student ID card generated. Next: final approval.");
                    this.closeWorkspaceModal();
                    await this.loadQueueData();
                } catch (error) {
                    console.error("Failed to generate ID card:", error);
                    this.notify("error", error.message || "Failed to generate student ID card");
                }
            });
        } catch (error) {
            console.error("Failed to prepare ID card generation:", error);
            this.notify("error", error.message || "Failed to prepare ID card generation");
        }
    },
    
    showError: function(message) {
        // Show error in all tabs
        ['applications', 'documents', 'interviews', 'decisions', 'placements', 'enrollment'].forEach(tab => {
            const contentDiv = document.getElementById(tab + '-content');
            const loadingDiv = document.getElementById(tab + '-loading');
            if (contentDiv && loadingDiv) {
                loadingDiv.style.display = 'none';
                contentDiv.style.display = 'block';
                contentDiv.innerHTML = `
                    <div class="text-center py-4">
                        <div class="text-danger">
                            <i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>
                            ${message}
                        </div>
                    </div>
                `;
            }
        });
    }
};

window.admissionsWorkspaceController = admissionsWorkspaceController;

function initWhenAPIReady() {
    const hasApi =
        window.API &&
        (
            typeof window.API.callAPI === "function" ||
            typeof window.API.apiCall === "function"
        );

    if (hasApi) {
        console.log("API is ready, initializing admissions workspace controller");
        window.admissionsWorkspaceController.init();
        return;
    }

    console.log("API not ready yet, waiting...");
    setTimeout(initWhenAPIReady, 100);
}

document.addEventListener("DOMContentLoaded", function () {
    console.log("DOM loaded, waiting for API to be ready");
    initWhenAPIReady();
});
