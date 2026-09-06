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


        try {
            await window.AuthContext?.ready();
            if (window.AuthContext && typeof window.AuthContext.isAuthenticated === "function") {
                await window.AuthContext?.ready();
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
            
            // Subscribe to conflict events
            if (typeof ConflictManager !== 'undefined') {
                ConflictManager.subscribe('CONFLICT_DETECTED', (conflict) => {
                    this.handleConflict(conflict);
                });
            }

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

    notify: function(type, message) {
        if (typeof window.showNotification === "function") {
            window.showNotification(message, type);
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
            windowsLoading: document.getElementById("windows-loading"),
            windowsContent: document.getElementById("windows-content"),
        };
    },

    setupEventListeners: function() {
        // Tab switching is handled by onclick attributes in HTML
    },
    
    loadQueueData: async function() {
        try {
            let queueData;
            
            // Try DataStore first for caching
            if (typeof DataStore !== 'undefined') {
                try {
                    queueData = await DataStore.get('admissions', {
                        strategy: 'stale-while-revalidate',
                        ttl: 60000, // 1 minute for fresh queue data
                        storeName: 'admission_queue_cache',
                        endpoint: '/admission/queues',
                        forceRefresh: false
                    });
                } catch (dataStoreError) {
                    console.warn("DataStore failed, falling back to API:", dataStoreError);
                }
            }
            
            // Fallback to direct API call
            if (!queueData) {
                queueData = await this.apiCall('/admission/queues', 'GET');
                
                // Cache in DataStore
                if (typeof DataStore !== 'undefined') {
                    await DataStore.set('admissions', queueData, {
                        ttl: 60000,
                        storeName: 'admission_queue_cache'
                    });
                }
            }

            this.queueData = queueData;

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
                    if (app.status === 'pending' || app.status === 'submitted' || stage === 'application_applied' || stage === 'application_received' || stage === 'application_review') pending++;
                    else if (['interview_scheduling', 'interview_results'].includes(stage)) inReview++;
                    else if (['student_admission_number', 'class_placement', 'fees_payment', 'student_id_generation', 'final_enrollment'].includes(stage)) approved++;
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
        const placementsCount = this.countInQueues(queues, ['placement_pending', 'payment_pending', 'id_generation_pending', 'final_enrollment_pending']);
        const enrollmentCount = this.countInQueues(queues, ['final_enrollment_pending', 'completed']);
        
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
            case 'windows':
                this.loadWindowsTab(contentDiv, loadingDiv);
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
            this.renderEmptyTab(contentDiv, loadingDiv, 'No application intake data available');
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
            this.renderEmptyTab(contentDiv, loadingDiv, 'No applications awaiting completion');
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
                                    return `
                                        <tr>
                                            <td><strong>${this.escapeHtml(app.application_no || '—')}</strong></td>
                                            <td>${this.escapeHtml(app.applicant_name || 'Unknown')}</td>
                                            <td>${docCount}</td>
                                            <td>${verifiedCount}</td>
                                            <td>${docStatus}</td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
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
            contentDiv.innerHTML = '<div class="card border-0 shadow-sm"><div class="card-body text-center py-4"><p class="text-muted">No applicants are currently waiting for interview assignment.</p><button class="btn btn-primary" type="button" onclick="admissionsWorkspaceController.manageInterviewSessions()"><i class="bi bi-calendar2-plus me-1"></i>Manage Interview Sessions</button></div></div>';
            loadingDiv.style.display = 'none';
            contentDiv.style.display = 'block';
            return;
        }
        
        const html = `
            <div class="d-flex justify-content-end mb-3"><button class="btn btn-primary" type="button" onclick="admissionsWorkspaceController.manageInterviewSessions()"><i class="bi bi-calendar2-plus me-1"></i>Manage Interview Sessions</button></div><div class="card border-0 shadow-sm">
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
                                                    ${app.current_stage === 'student_admission_number' ? `
                                                        <button class="btn btn-sm btn-outline-success" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.createStudentAdmissionNumber(${app.id})">
                                                            <i class="bi bi-person-plus"></i> Create Admission No.
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
        
        ['placement_pending', 'payment_pending', 'id_generation_pending', 'final_enrollment_pending'].forEach(queueName => {
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
                                                    ${['class_placement', 'fees_payment'].includes(app.current_stage) ? `
                                                        <button class="btn btn-sm btn-outline-primary" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.recordPayment(${app.id}, ${Number(app.registration_fee_due || 2000)})">
                                                            <i class="bi bi-cash-coin"></i> Payment
                                                        </button>
                                                    ` : ''}
                                                    ${app.current_stage === 'student_id_generation' ? `
                                                        <button class="btn btn-sm btn-outline-success" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.generateStudentIdCard(${app.id})">
                                                            <i class="bi bi-person-vcard"></i> Generate ID
                                                        </button>
                                                    ` : ''}
                                                    ${app.current_stage === 'final_enrollment' ? `
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
            ...(queues.final_enrollment_pending || []),
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
                                                    ${app.current_stage === 'final_enrollment' ? `
                                                        <button class="btn btn-sm btn-outline-success" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.finalApproval(${app.id})">
                                                            <i class="bi bi-person-check"></i> Final Enrollment
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

    // ========================================================================
    // INTAKE WINDOWS TAB
    // ========================================================================

    loadWindowsTab: async function(contentDiv, loadingDiv) {
        try {
            const [windowsResp, yearsResp, termsResp] = await Promise.all([
                this.apiCall('/admission/windows', 'GET'),
                this.apiCall('/academic/years/list', 'GET'),
                this.apiCall('/academic/terms', 'GET'),
            ]);

            const windowsPayload = windowsResp?.data ?? windowsResp ?? {};
            const windows = Array.isArray(windowsPayload)
                ? windowsPayload
                : windowsPayload.windows || windowsPayload.items || [];

            const years = Array.isArray(yearsResp?.data) ? yearsResp.data : (Array.isArray(yearsResp) ? yearsResp : []);
            const terms = Array.isArray(termsResp?.data) ? termsResp.data : (Array.isArray(termsResp) ? termsResp : []);

            // Admission windows may be prepared for the current year or a
            // future year already in planning/registration. Archived years
            // must not be selectable for new intake windows.
            this.windowYears = years.filter((year) => {
                const status = String(year.status || '').toLowerCase();
                return year.is_current || ['planning', 'registration', 'active', 'closing'].includes(status);
            });
            this.windowTerms = terms;
            this.windowRecords = windows;
            this.renderWindowsTab(contentDiv, loadingDiv, windows);
        } catch (error) {
            console.error("Failed to load intake windows:", error);
            this.renderEmptyTab(contentDiv, loadingDiv, 'Failed to load intake windows');
        }
    },

    renderWindowsTab: function(contentDiv, loadingDiv, windows = []) {
        const yearOptions = (this.windowYears || []).map((y) => {
            const status = String(y.status || (y.is_current ? 'active' : 'planning')).toLowerCase();
            const label = status === 'planning' ? 'Upcoming / Planning'
                : status === 'registration' ? 'Registration Open'
                    : status === 'closing' ? 'Closing'
                        : status === 'active' ? 'Active' : this.formatLabel(status);
            return `<option value="${Number(y.id)}">${this.escapeHtml(y.year_code || y.year_name || y.id)} — ${this.escapeHtml(label)}</option>`;
        }).join('');

        const rows = windows.length
            ? windows.map((w) => {
                const now = Date.now();
                const opensAt = w.application_open_at ? new Date(String(w.application_open_at).replace(' ', 'T')).getTime() : null;
                const closesAt = w.application_close_at ? new Date(String(w.application_close_at).replace(' ', 'T')).getTime() : null;
                const effectiveStatus = w.effective_status || (w.status === 'open' && opensAt && opensAt > now ? 'scheduled' : (w.status === 'open' && closesAt && closesAt < now ? 'closed' : w.status));
                const isScheduled = effectiveStatus === 'scheduled';
                const isExpired = effectiveStatus === 'closed' && w.status === 'open' && closesAt && closesAt < now;
                const isOpen = effectiveStatus === 'open';
                const termLabel = (w.term_name ? w.term_name + ' ' : '') + (w.year_code || '');
                let eligible = 'All grades';
                try { const parsed = JSON.parse(w.eligible_grades || '[]'); if (parsed.length) eligible = parsed.join(', '); } catch (_) {}
                return `
                    <tr>
                        <td><strong>${this.escapeHtml(w.label || '—')}</strong></td>
                        <td>${this.escapeHtml(w.year_code || '—')}</td>
                        <td>${this.escapeHtml(w.term_name || '—')}</td>
                        <td>${this.escapeHtml(eligible)}</td>
                        <td>${isScheduled
                            ? '<span class="badge bg-info">Scheduled</span>'
                            : isExpired
                                ? '<span class="badge bg-warning text-dark">Expired</span>'
                                : isOpen
                                    ? '<span class="badge bg-success">Open</span>'
                                    : '<span class="badge bg-secondary">Closed</span>'}</td>
                        <td><small>${this.escapeHtml(this.formatDate(w.application_open_at) || 'Immediately')}<br>to ${this.escapeHtml(this.formatDate(w.application_close_at) || 'Until closed')}</small></td>
                        <td>${Number(w.accepts_new_applications) === 1
                            ? '<span class="badge bg-success">Yes</span>'
                            : '<span class="badge bg-danger">No</span>'}</td>
                        <td class="text-nowrap">
                            <button class="btn btn-sm btn-outline-primary me-1" title="View intake window" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.viewAdmissionWindow(${Number(w.id)})"><i class="bi bi-eye"></i> View</button>
                            <button class="btn btn-sm btn-outline-warning me-1" title="Edit intake window" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.editAdmissionWindow(${Number(w.id)})"><i class="bi bi-pencil"></i> Edit</button>
                            <button class="btn btn-sm ${isOpen ? 'btn-outline-secondary' : 'btn-outline-success'}"
                                onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.toggleAdmissionWindow(${Number(w.id)}, ${isOpen ? "'closed'" : "'open'"})">
                                <i class="bi ${isOpen ? 'bi-x-circle' : 'bi-check-circle'}"></i>
                                ${isOpen ? 'Close' : 'Open'}
                            </button>
                        </td>
                    </tr>
                `;
            }).join('')
            : '<tr><td colspan="8" class="text-center text-muted py-4">No intake windows configured yet.</td></tr>';

        contentDiv.innerHTML = `
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div><h6 class="mb-0 fw-semibold"><i class="bi bi-door-open me-2 text-success"></i>Admission Intake Windows</h6><span class="text-muted small">History of configured application periods.</span></div>
                    <button type="button" class="btn btn-success" onclick="admissionsWorkspaceController.createAdmissionWindow()"><i class="bi bi-plus-circle me-1"></i>Create Window</button>
                </div>
                <div class="modal fade" id="admissionWindowModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header"><h5 class="modal-title" id="admissionWindowModalTitle">Create Admission Window</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body">
                    <form id="admissionWindowForm" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Intake label</label>
                            <input type="text" name="label" class="form-control" placeholder="e.g. August Holiday Intake">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Academic Year <span class="text-danger">*</span></label>
                            <select name="academic_year_id" id="windowYearSelect" class="form-select" required>
                                <option value="">Select Year</option>
                                ${yearOptions}
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Term <span class="text-danger">*</span></label>
                            <select name="academic_year_term_id" id="windowTermSelect" class="form-select" required>
                                <option value="">Select Year first</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Applications open</label>
                            <input type="datetime-local" name="application_open_at" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Applications close</label>
                            <input type="datetime-local" name="application_close_at" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="open">Open</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="accepts_new_applications" value="1" id="windowAcceptsNew" checked>
                                <label class="form-check-label small" for="windowAcceptsNew">Accepts new apps</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Eligible grades</label>
                            <select name="eligible_grades[]" class="form-select" multiple size="3" aria-label="Eligible grades">
                                ${['Playground','PP1','PP2','Grade1','Grade2','Grade3','Grade4','Grade5','Grade6','Grade7','Grade8','Grade9'].map((g) => `<option value="${g}">${g}</option>`).join('')}
                            </select>
                            <small class="text-muted">Leave all unselected to accept every grade.</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Default category</label>
                            <select name="default_admission_category" class="form-select">
                                <option value="">Standard / applicant choice</option>
                                <option value="standard">Standard Admission</option>
                                <option value="nursery_term_1">Nursery Term 1</option>
                                <option value="nursery_term_3">Nursery Term 3</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-plus-circle me-1"></i>Save Window
                            </button>
                        </div>
                        <input type="hidden" name="id" value="">
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Notes (optional)</label>
                            <input type="text" name="notes" class="form-control" placeholder="e.g. Term 1 intake, main entry point">
                        </div>
                    </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0 fw-semibold">Configured Intake Windows</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Label</th>
                                <th>Year</th>
                                <th>Term</th>
                                <th>Eligible Grades</th>
                                <th>Status</th>
                                <th>Application Dates</th>
                                <th>Accepts New</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            </div>
        `;

        loadingDiv.style.display = 'none';
        contentDiv.style.display = 'block';

        const yearSelect = document.getElementById('windowYearSelect');
        const termSelect = document.getElementById('windowTermSelect');
        if (yearSelect) {
            yearSelect.addEventListener('change', () => this.populateWindowTerms(yearSelect.value));
            this.populateWindowTerms(yearSelect.value);
        }
        document.getElementById('admissionWindowForm')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveAdmissionWindow(e.currentTarget);
        });
    },

    populateWindowTerms: function(yearId) {
        const termSelect = document.getElementById('windowTermSelect');
        if (!termSelect) return;
        const terms = (this.windowTerms || []).filter((t) => String(t.year || t.academic_year_id) === String(yearId));
        if (!terms.length) {
            termSelect.innerHTML = '<option value="">No terms for this year</option>';
            return;
        }
        termSelect.innerHTML = '<option value="">Select Term</option>' +
            terms.map((t) =>
                `<option value="${Number(t.id)}">${this.escapeHtml(t.name || t.term_number || 'Term')}</option>`
            ).join('');
    },

    saveAdmissionWindow: async function(form) {
        const formData = new FormData(form);
        const payload = {
            id: formData.get('id') || null,
            label: formData.get('label') || null,
            academic_year_id: formData.get('academic_year_id'),
            academic_year_term_id: formData.get('academic_year_term_id'),
            status: formData.get('status') || 'open',
            accepts_new_applications: formData.has('accepts_new_applications') ? 1 : 0,
            application_open_at: formData.get('application_open_at') || null,
            application_close_at: formData.get('application_close_at') || null,
            eligible_grades: formData.getAll('eligible_grades[]'),
            default_admission_category: formData.get('default_admission_category') || null,
            notes: formData.get('notes') || null,
        };
        if (!payload.academic_year_id || !payload.academic_year_term_id) {
            this.notify('error', 'Select an academic year and term first.');
            return;
        }
        try {
            const resp = await this.apiCall('/admission/windows', 'POST', payload);
            // apiCall() unwraps successful API responses to their data payload
            // ({id: ...}), so do not require a second nested data property.
            const ok = Boolean(resp) && (
                resp.success === true || resp.status === true || resp.status === 'success' ||
                resp.id !== undefined || resp.windows !== undefined || resp.data !== undefined
            );
            if (!ok) {
                throw new Error(resp?.message || 'Failed to save admission window');
            }
            this.notify('success', resp?.message || 'Admission window saved');
            form.reset();
            bootstrap.Modal.getInstance(document.getElementById('admissionWindowModal'))?.hide();
            this.loadWindowsTab(
                document.getElementById('windows-content'),
                document.getElementById('windows-loading'),
            );
        } catch (error) {
            console.error('Failed to save admission window:', error);
            this.notify('error', error.message || 'Failed to save admission window');
        }
    },

    toggleAdmissionWindow: async function(id, status) {
        try {
            const resp = await this.apiCall(`/admission/windows/${id}`, 'PUT', { status });
            const ok = Boolean(resp) && (
                resp.success === true || resp.status === true || resp.status === 'success' ||
                resp.id !== undefined
            );
            if (!ok) {
                throw new Error(resp?.message || 'Failed to update admission window');
            }
            this.notify('success', resp?.message || 'Admission window updated');
            this.loadWindowsTab(
                document.getElementById('windows-content'),
                document.getElementById('windows-loading'),
            );
        } catch (error) {
            console.error('Failed to update admission window:', error);
            this.notify('error', error.message || 'Failed to update admission window');
        }
    },

    createAdmissionWindow: function() {
        const form = document.getElementById('admissionWindowForm');
        if (!form) return;
        form.reset();
        form.querySelector('[name="id"]').value = '';
        const termSelect = form.querySelector('[name="academic_year_term_id"]');
        if (termSelect) termSelect.innerHTML = '<option value="">Select Year first</option>';
        const title = document.getElementById('admissionWindowModalTitle');
        if (title) title.textContent = 'Create Admission Window';
        const button = form.querySelector('[type="submit"]');
        if (button) button.innerHTML = '<i class="bi bi-plus-circle me-1"></i>Save Window';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('admissionWindowModal')).show();
    },

    viewAdmissionWindow: function(id) {
        const w = (this.windowRecords || []).find((row) => Number(row.id) === Number(id));
        if (!w) return;
        let grades = 'All grades';
        try { const parsed = JSON.parse(w.eligible_grades || '[]'); if (parsed.length) grades = parsed.join(', '); } catch (_) {}
        const modalId = 'admissionWindowViewModal';
        document.getElementById(modalId)?.remove();
        document.body.insertAdjacentHTML('beforeend', `<div class="modal fade" id="${modalId}" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Admission Intake Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><dl class="row mb-0"><dt class="col-5">Label</dt><dd class="col-7">${this.escapeHtml(w.label || '')}</dd><dt class="col-5">Academic year</dt><dd class="col-7">${this.escapeHtml(w.year_code || '')}</dd><dt class="col-5">Term</dt><dd class="col-7">${this.escapeHtml(w.term_name || '')}</dd><dt class="col-5">Eligible grades</dt><dd class="col-7">${this.escapeHtml(grades)}</dd><dt class="col-5">Default category</dt><dd class="col-7">${this.escapeHtml(w.default_admission_category || 'Applicant choice')}</dd><dt class="col-5">Applications</dt><dd class="col-7">${this.escapeHtml(this.formatDate(w.application_open_at) || 'Immediately')} to ${this.escapeHtml(this.formatDate(w.application_close_at) || 'Until closed')}</dd><dt class="col-5">Notes</dt><dd class="col-7">${this.escapeHtml(w.notes || '—')}</dd></dl></div></div></div></div>`);
        bootstrap.Modal.getOrCreateInstance(document.getElementById(modalId)).show();
    },

    editAdmissionWindow: function(id) {
        const w = (this.windowRecords || []).find((row) => Number(row.id) === Number(id));
        const form = document.getElementById('admissionWindowForm');
        if (!w || !form) return;
        form.querySelector('[name="id"]').value = w.id;
        form.querySelector('[name="label"]').value = w.label || '';
        form.querySelector('[name="academic_year_id"]').value = w.academic_year_id;
        this.populateWindowTerms(w.academic_year_id);
        form.querySelector('[name="academic_year_term_id"]').value = w.academic_year_term_id;
        form.querySelector('[name="status"]').value = w.status;
        form.querySelector('[name="accepts_new_applications"]').checked = Number(w.accepts_new_applications) === 1;
        form.querySelector('[name="application_open_at"]').value = this.toDateTimeLocal(w.application_open_at);
        form.querySelector('[name="application_close_at"]').value = this.toDateTimeLocal(w.application_close_at);
        form.querySelector('[name="default_admission_category"]').value = w.default_admission_category || '';
        let grades = []; try { grades = JSON.parse(w.eligible_grades || '[]'); } catch (_) {}
        form.querySelectorAll('[name="eligible_grades[]"] option').forEach((option) => { option.selected = grades.includes(option.value); });
        form.querySelector('[name="notes"]').value = w.notes || '';
        const button = form.querySelector('[type="submit"]');
        if (button) button.innerHTML = '<i class="bi bi-save me-1"></i>Update Window';
        const title = document.getElementById('admissionWindowModalTitle');
        if (title) title.textContent = 'Edit Admission Window';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('admissionWindowModal')).show();
    },

    toDateTimeLocal: function(value) {
        return value ? String(value).replace(' ', 'T').slice(0, 16) : '';
    },

    getStatusBadge: function(status) {
        const badges = {
            'submitted': '<span class="badge bg-secondary">Submitted</span>',
            'documents_pending': '<span class="badge bg-warning">Documents Pending</span>',
            'documents_verified': '<span class="badge bg-info">Documents Verified</span>',
            'interview_scheduling': '<span class="badge bg-primary">Interview Scheduling</span>',
            'interview_results': '<span class="badge bg-info">Interview Results</span>',
            'student_admission_number': '<span class="badge bg-primary">Student Admission Number</span>',
            'class_placement': '<span class="badge bg-info">Class / Stream Placement</span>',
            'fees_payment': '<span class="badge bg-warning">Fees Payment</span>',
            'student_id_generation': '<span class="badge bg-primary">ID Generation</span>',
            'final_enrollment': '<span class="badge bg-success">Final Enrollment</span>',
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
        if (app.current_stage === 'final_enrollment' || app.current_stage === 'enrolled' || app.status === 'enrolled') readyItems++;
        
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
    
    viewApplication: async function(applicationId) {
        if (!applicationId || Number.isNaN(Number(applicationId))) {
            this.notify("error", "Invalid application selected");
            return;
        }

        try {
            let payload;
            
            // Try DataStore first for caching
            if (typeof DataStore !== 'undefined') {
                try {
                    payload = await DataStore.get(`admissions:${applicationId}`, {
                        strategy: 'network-first',
                        ttl: 300000, // 5 minutes
                        storeName: 'admission_queue_cache',
                        endpoint: `/admission/application/${applicationId}`,
                        params: { id: applicationId }
                    });
                } catch (dataStoreError) {
                    console.warn("DataStore failed, falling back to API:", dataStoreError);
                }
            }
            
            // Fallback to direct API call
            if (!payload) {
                payload = await this.apiCall(`/admission/application/${applicationId}`, "GET");
                
                // Cache in DataStore
                if (typeof DataStore !== 'undefined') {
                    await DataStore.set(`admissions:${applicationId}`, payload, {
                        ttl: 300000,
                        storeName: 'admission_queue_cache'
                    });
                }
            }

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
            if (['application_received', 'application_review'].includes(payload.application.current_stage)) {
                this.loadReviewAdmissionWindows();
            }
        } catch (error) {
            console.error("Failed to load application details:", error);
            this.notify("error", error.message || "Failed to load application details");
        }
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

        const currentStageCode = stageMeta.current_stage || app.current_stage || "application_received";
        const isReviewStage = ['application_received', 'application_review'].includes(currentStageCode);
        const gender = String(app.gender || '').toLowerCase();
        const gradeOptions = ['Playground', 'PP1', 'PP2', 'Grade1', 'Grade2', 'Grade3', 'Grade4', 'Grade5', 'Grade6', 'Grade7', 'Grade8', 'Grade9'];

        contentElement.innerHTML = `
            <div class="row g-4">
                <div class="col-lg-6">
                    <h6 class="fw-semibold mb-3">Review Applicant Information</h6>
                    ${isReviewStage ? `
                        <form id="applicationReviewForm">
                            <input type="hidden" name="application_id" value="${Number(app.id || 0)}">
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Full Name</label>
                                <input required type="text" name="applicant_name" class="form-control" value="${this.escapeHtml(app.applicant_name || '')}">
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Date of Birth</label>
                                    <input required type="date" name="date_of_birth" class="form-control" value="${this.escapeHtml((app.date_of_birth || '').substring(0,10))}">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Gender</label>
                                    <select required name="gender" class="form-select">
                                        <option value="">Select</option>
                                        <option value="male" ${gender === 'male' ? 'selected' : ''}>Male</option>
                                        <option value="female" ${gender === 'female' ? 'selected' : ''}>Female</option>
                                        <option value="other" ${gender === 'other' ? 'selected' : ''}>Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Grade Applying For</label>
                                    <select required name="grade_applying_for" class="form-select">
                                        <option value="">Select grade</option>
                                        ${gradeOptions.map((grade) => `<option value="${grade}" ${app.grade_applying_for === grade ? 'selected' : ''}>${grade}</option>`).join('')}
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Academic Year</label>
                                    <input required pattern="[0-9]{4}" maxlength="4" type="text" name="academic_year" class="form-control" value="${this.escapeHtml(app.academic_year || '')}">
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Application Window</label>
                                <select name="admission_window_id" id="reviewAdmissionWindowSelect" class="form-select" required>
                                    <option value="">Loading application windows…</option>
                                </select>
                                <div class="form-text">This assigns the application to the selected academic year and term.</div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Previous School</label>
                                <input type="text" name="previous_school" class="form-control" value="${this.escapeHtml(app.previous_school || '')}">
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Application Category</label>
                                    <select name="admission_category" class="form-select">
                                        <option value="standard" ${app.admission_category === 'standard' ? 'selected' : ''}>Standard</option>
                                        <option value="nursery_term_1" ${app.admission_category === 'nursery_term_1' ? 'selected' : ''}>Nursery Term 1</option>
                                        <option value="nursery_term_3" ${app.admission_category === 'nursery_term_3' ? 'selected' : ''}>Nursery Term 3</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small fw-semibold">Application Source</label>
                                    <select name="application_source" class="form-select">
                                        <option value="physical" ${app.application_source === 'physical' ? 'selected' : ''}>Physical</option>
                                        <option value="online" ${app.application_source === 'online' ? 'selected' : ''}>Online</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="has_special_needs" value="1" id="reviewSpecialNeeds" ${Number(app.has_special_needs) === 1 ? 'checked' : ''}>
                                <label class="form-check-label small" for="reviewSpecialNeeds">Applicant has special needs</label>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Special Needs Details</label>
                                <textarea name="special_needs_details" class="form-control" rows="2">${this.escapeHtml(app.special_needs_details || '')}</textarea>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Review Notes</label>
                                <textarea name="review_notes" class="form-control" rows="3" placeholder="Record corrections, verification notes, or the reason for rejection"></textarea>
                            </div>
                            ${this.requiresInterviewGrade(app.grade_applying_for) ? `
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Review Route</label>
                                    <select name="review_route" id="reviewRouteSelect" class="form-select">
                                        <option value="normal">Continue to interview</option>
                                        <option value="skip_interview">Approve and skip interview</option>
                                    </select>
                                    <div class="form-text">Skipping is an authorized exception. Record the reason in Review Notes; it is saved in the workflow audit history.</div>
                                </div>
                            ` : '<div class="alert alert-info small mt-2">This grade does not require an interview. Approval will proceed directly to student admission-number creation.</div>'}
                        </form>
                    ` : `<dl class="row mb-0">
                        <dt class="col-sm-5">Application No</dt><dd class="col-sm-7">${this.escapeHtml(app.application_no || "N/A")}</dd>
                        <dt class="col-sm-5">Name</dt><dd class="col-sm-7">${this.escapeHtml(app.applicant_name || "N/A")}</dd>
                        <dt class="col-sm-5">Date of Birth</dt><dd class="col-sm-7">${this.escapeHtml(this.formatDate(app.date_of_birth))}</dd>
                        <dt class="col-sm-5">Gender</dt><dd class="col-sm-7">${this.escapeHtml(this.formatLabel(app.gender || "N/A"))}</dd>
                        <dt class="col-sm-5">Grade Applying For</dt><dd class="col-sm-7">${this.escapeHtml(app.grade_applying_for || "N/A")}</dd>
                        <dt class="col-sm-5">Status</dt><dd class="col-sm-7">${this.getStatusBadge(app.status)}</dd>
                    </dl>`}
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

    loadReviewAdmissionWindows: async function() {
        const select = document.getElementById('reviewAdmissionWindowSelect');
        if (!select) return;
        try {
            const response = await this.apiCall('/admission/windows', 'GET');
            const payload = response?.windows ? response : (response?.data || response || {});
            const windows = Array.isArray(payload) ? payload : (payload.windows || []);
            const currentTermId = Number(this.currentApplicationData?.application?.target_term_id || 0);
            select.innerHTML = '<option value="">Select application window</option>' + windows.map((window) => {
                const label = window.label || `${window.term_name || 'Term'} ${window.year_code || ''}`;
                const dates = window.application_open_at || window.application_close_at
                    ? ` (${this.formatDate(window.application_open_at) || 'immediate'} – ${this.formatDate(window.application_close_at) || 'until closed'})`
                    : '';
                const selected = Number(window.academic_year_term_id) === currentTermId ? ' selected' : '';
                return `<option value="${Number(window.id)}"${selected}>${this.escapeHtml(label + dates)}</option>`;
            }).join('');
            if (!windows.length) {
                select.innerHTML = '<option value="">No application windows configured</option>';
            }
        } catch (error) {
            select.innerHTML = '<option value="">Unable to load application windows</option>';
            this.notify('error', error.message || 'Unable to load application windows');
        }
    },

    saveApplicationReview: async function(form, nextStage) {
        const formData = new FormData(form);
        const applicationId = Number(formData.get('application_id'));
        if (!applicationId) {
            this.notify('error', 'Application ID is missing');
            return;
        }
        const payload = this.reviewFormPayload(formData);
        if (!payload.applicant_name || !payload.grade_applying_for || !payload.academic_year) {
            this.notify('error', 'Name, grade, and academic year are required');
            return;
        }
        const button = document.getElementById('reviewSaveBtn');
        if (button) button.disabled = true;
        try {
            await this.apiCall(`/admission/application/${applicationId}`, 'PUT', payload);
            const skipInterview = formData.get('review_route') === 'skip_interview';
            if (skipInterview && !String(formData.get('review_notes') || '').trim()) {
                throw new Error('Enter a reason before skipping the interview.');
            }
            const selectedNextStage = skipInterview ? 'student_admission_number' : nextStage;
            await this.apiCall('/admission/advance-workflow-stage', 'POST', {
                application_id: applicationId,
                to_stage: selectedNextStage,
                action: skipInterview ? 'admin_skip_interview' : 'review_application',
                notes: formData.get('review_notes') || null,
                workflow_updates: JSON.stringify({
                    reviewed: true,
                    reviewed_at: new Date().toISOString(),
                    interview_skipped: skipInterview,
                    interview_skip_reason: skipInterview ? formData.get('review_notes') : null
                })
            });
            this.notify('success', 'Application saved and moved to the next stage');
            this.closeWorkspaceModal();
            await this.loadQueueData();
        } catch (error) {
            this.notify('error', error.message || 'Unable to update application');
        } finally {
            if (button) button.disabled = false;
        }
    },

    requiresInterviewGrade: function(grade) {
        const normalized = String(grade || '').replace(/[^a-z0-9]/gi, '').toLowerCase();
        return ['grade4', 'grade5', 'grade6', 'grade7', 'grade8', 'grade9'].includes(normalized);
    },

    rejectApplicationReview: async function(form) {
        const formData = new FormData(form);
        const applicationId = Number(formData.get('application_id'));
        const reason = String(formData.get('review_notes') || '').trim();
        if (!reason) {
            this.notify('error', 'Enter a reason before rejecting the application');
            return;
        }
        try {
            await this.apiCall(`/admission/application/${applicationId}`, 'PUT', this.reviewFormPayload(formData));
            await this.apiCall('/admission/advance-workflow-stage', 'POST', {
                application_id: applicationId,
                to_stage: 'rejected',
                action: 'review_application_rejected',
                notes: reason,
                workflow_updates: JSON.stringify({ rejection_reason: reason, rejected_at: new Date().toISOString() })
            });
            this.notify('success', 'Application rejected');
            this.closeWorkspaceModal();
            await this.loadQueueData();
        } catch (error) {
            this.notify('error', error.message || 'Unable to reject application');
        }
    },

    reviewFormPayload: function(formData) {
        return {
            applicant_name: String(formData.get('applicant_name') || '').trim(),
            date_of_birth: formData.get('date_of_birth') || null,
            gender: formData.get('gender') || null,
            grade_applying_for: String(formData.get('grade_applying_for') || '').trim(),
            academic_year: String(formData.get('academic_year') || '').trim(),
            admission_window_id: formData.get('admission_window_id') || null,
            previous_school: String(formData.get('previous_school') || '').trim() || null,
            admission_category: formData.get('admission_category') || 'standard',
            application_source: formData.get('application_source') || 'physical',
            has_special_needs: formData.get('has_special_needs') ? 1 : 0,
            special_needs_details: String(formData.get('special_needs_details') || '').trim() || null
        };
    },

    renderWorkflowData: function(workflowData) {
        const hiddenInternalFields = new Set(['student_id', 'enrollment_id', 'student_id_card_id']);
        const visibleWorkflowData = Object.fromEntries(
            Object.entries(workflowData || {}).filter(([key]) => !hiddenInternalFields.has(key))
        );
        if (Object.keys(visibleWorkflowData).length === 0) {
            return '<p class="text-muted mb-0">No workflow details recorded.</p>';
        }

        return `
            <dl class="row mb-0">
                ${Object.entries(visibleWorkflowData).map(([key, value]) => `
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
            const payload = await this.apiCall(`/admission/application/${applicationId}`, "GET");
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
        const stageAliases = {
            application: 'application_applied',
            application_submission: 'application_applied',
            documents_upload: 'application_applied',
            document_verification: 'application_received',
            documents_verification: 'application_received',
            class_space_check: 'student_admission_number',
            admission_decision: 'student_admission_number',
            placement_offer: 'student_admission_number',
            fee_payment: 'fees_payment',
            enrollment: 'final_enrollment',
            director_confirmation: 'final_enrollment'
        };
        const currentStage = stageAliases[app.current_stage] || app.current_stage || "application_received";
        const docCount = documents.length;
        const verifiedCount = documents.filter(doc => doc.verification_status === 'verified').length;
        const rejectedCount = documents.filter(doc => doc.verification_status === 'rejected').length;
        const hasRejectedDocs = rejectedCount > 0;
        
        let label, description, waitingFor, nextActionLabel, nextActionMethod, tone, blockingReason;
        
        switch (currentStage) {
            case 'application_applied':
                label = 'Application Applied';
                description = 'Application is at the initial application stage. Documents are collected as part of the application submission and are not uploaded from this administration queue.';
                waitingFor = 'Applicant / Application Submission';
                nextActionLabel = 'Awaiting Application Completion';
                nextActionMethod = null;
                tone = 'warning';
                break;

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
                description = 'Application is being reviewed and may now be approved for interview or student admission-number creation.';
                waitingFor = 'Admissions Office';
                nextActionLabel = 'Review Application';
                nextActionMethod = 'reviewApplication';
                tone = 'info';
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
                    label = 'Interview Passed - Student Creation Pending';
                    description = `Applicant was marked passed by the interviewer${workflowData.interview_score !== null && workflowData.interview_score !== undefined ? ` with supporting score: ${workflowData.interview_score}` : ''}.`;
                    waitingFor = 'Registrar / School Admin';
                    nextActionLabel = 'Create Student Admission Number';
                    nextActionMethod = 'createStudentAdmissionNumber';
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
                
            case 'student_admission_number':
                if (workflowData.student_admission_number_created === true || workflowData.student_admission_number_created === 'true' || workflowData.provisional_student_created === true || workflowData.provisional_student_created === 'true') {
                    label = 'Student Record Created - Awaiting Class Placement';
                    description = `Student record created with admission number ${workflowData.admission_number || 'N/A'}. Assign the learner to a class stream before billing and payment.`;
                    waitingFor = 'Registrar / School Admin';
                    nextActionLabel = 'Place in Class Stream';
                    nextActionMethod = 'completeEnrollment';
                    tone = 'success';
                } else {
                    label = 'Waiting for Student Record Creation';
                    description = 'Admission approved. Create the student admission number before class/stream placement.';
                    waitingFor = 'Registrar / School Admin';
                    nextActionLabel = 'Create Student Admission Number';
                    nextActionMethod = 'createStudentAdmissionNumber';
                    tone = 'warning';
                }
                break;

            case 'class_placement':
                label = 'Class / Stream Placement';
                description = 'Assign the admitted student to the target class stream. This creates the academic enrollment, learning areas, fee obligations, attendance context and boarding assignment where applicable.';
                waitingFor = 'Registrar / School Admin';
                nextActionLabel = 'Place in Class Stream';
                nextActionMethod = 'completeEnrollment';
                tone = 'info';
                break;
                
            case 'fees_payment':
                if (workflowData.payment_status === 'paid') {
                    label = 'Fees Paid - Awaiting ID Generation';
                    description = 'Admission fees have been recorded. Student ID card generation pending.';
                    waitingFor = 'School Admin';
                    nextActionLabel = 'Generate ID Card';
                    nextActionMethod = 'generateStudentIdCard';
                    tone = 'success';
                } else if (app.pending_payment_id) {
                    label = 'Payment Pending Verification';
                    description = `A ${app.pending_payment_reference || 'bank/M-Pesa'} payment of KES ${Number(app.pending_payment_amount || 0).toLocaleString()} is awaiting reconciliation confirmation.`;
                    waitingFor = 'Accounts Office';
                    nextActionLabel = 'Verify Payment';
                    nextActionMethod = 'verifyPayment';
                    tone = 'info';
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
                
            case 'final_enrollment':
                if (workflowData.final_enrollment_done === true || workflowData.final_enrollment_done === 'true') {
                    label = 'Final Enrollment';
                    description = 'Payment and ID generation are complete. Final enrollment is ready.';
                    waitingFor = 'Registrar / School Admin';
                    nextActionLabel = 'Complete Final Enrollment';
                    nextActionMethod = 'finalApproval';
                    tone = 'success';
                } else {
                    label = 'Waiting for Final Enrollment';
                    description = 'Student ID card is generated. Complete final enrollment.';
                    waitingFor = 'School Admin / Authorized Approver';
                    nextActionLabel = 'Complete Final Enrollment';
                    nextActionMethod = 'finalApproval';
                    tone = 'warning';
                }
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

        if (['application_received', 'application_review'].includes(stage)) {
            const nextStage = stage === 'application_received'
                ? 'application_review'
                : (this.requiresInterviewGrade(app.grade_applying_for)
                    ? 'interview_scheduling'
                    : 'student_admission_number');
            return `${closeBtn}
                <button type="button" class="btn btn-outline-danger" onclick="admissionsWorkspaceController.rejectApplicationReview(document.getElementById('applicationReviewForm'))">
                    <i class="bi bi-x-circle me-1"></i>Reject
                </button>
                <button type="button" class="btn btn-success" id="reviewSaveBtn" onclick="admissionsWorkspaceController.saveApplicationReview(document.getElementById('applicationReviewForm'), '${nextStage}')">
                    <i class="bi bi-check-circle me-1"></i>Save &amp; Continue
                </button>`;
        }

        // Single source of truth: derive the next action exactly as startIntake does.
        const comm = this.getApplicationWorkflowCommunication(app, documents, workflowData);
        if (comm && comm.nextActionMethod && comm.nextActionLabel) {
            const method = comm.nextActionMethod;
            const label = comm.nextActionLabel;
            const icon = comm.actionIcon || "bi-arrow-right-circle";
            const btn = `<button type="button" class="btn btn-primary" onclick="admissionsWorkspaceController.${method}(${Number(applicationId)})"><i class="bi ${icon} me-1"></i>${this.escapeHtml(label)}</button>`;
            return closeBtn + btn;
        }

        return closeBtn;
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
            const payload = await this.apiCall(`/admission/application/${applicationId}`, "GET");
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

            const payload = await this.apiCall(`/admission/application/${applicationId}`, "GET");

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
            const payload = await this.apiCall(`/admission/application/${applicationId}`, "GET");
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

    manageInterviewSessions: async function() {
        try {
            const [sessionsResponse, windowsResponse] = await Promise.all([
                this.apiCall('/admission/interview-sessions', 'GET'),
                this.apiCall('/admission/windows', 'GET')
            ]);
            const sessionsPayload = sessionsResponse?.data ?? sessionsResponse ?? {};
            const windowsPayload = windowsResponse?.data ?? windowsResponse ?? {};
            const sessions = Array.isArray(sessionsPayload) ? sessionsPayload : (sessionsPayload.sessions || []);
            const windows = Array.isArray(windowsPayload) ? windowsPayload : (windowsPayload.windows || []);
            const modalId = 'interviewSessionsModal';
            document.getElementById(modalId)?.remove();
            document.body.insertAdjacentHTML('beforeend', `<div class="modal fade" id="${modalId}" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Interview Sessions</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="table-responsive mb-4"><table class="table table-sm align-middle"><thead><tr><th>Intake</th><th>Date</th><th>Time</th><th>Venue</th><th>Assigned</th><th>Status</th></tr></thead><tbody>${sessions.length ? sessions.map((s) => `<tr><td>${this.escapeHtml(s.window_label || '')}</td><td>${this.escapeHtml(s.session_date)}</td><td>${this.escapeHtml(String(s.start_time).slice(0,5))}–${this.escapeHtml(String(s.end_time).slice(0,5))}</td><td>${this.escapeHtml(s.venue)}</td><td>${Number(s.assigned_count || 0)}/${Number(s.capacity || 0)}</td><td>${this.escapeHtml(s.status)}</td></tr>`).join('') : '<tr><td colspan="6" class="text-muted text-center">No sessions created.</td></tr>'}</tbody></table></div><h6>Create session</h6><form id="interviewSessionForm" class="row g-3"><div class="col-md-4"><label class="form-label">Admission window</label><select name="admission_window_id" class="form-select" required><option value="">Select intake</option>${windows.map((w) => `<option value="${Number(w.id)}">${this.escapeHtml(w.label || '')}</option>`).join('')}</select></div><div class="col-md-2"><label class="form-label">Date</label><input name="session_date" type="date" class="form-control" required></div><div class="col-md-2"><label class="form-label">Start</label><input name="start_time" type="time" class="form-control" required></div><div class="col-md-2"><label class="form-label">End</label><input name="end_time" type="time" class="form-control" required></div><div class="col-md-2"><label class="form-label">Capacity</label><input name="capacity" type="number" min="1" value="20" class="form-control" required></div><div class="col-md-6"><label class="form-label">Venue</label><input name="venue" value="Main Office" class="form-control" required></div><div class="col-md-6"><label class="form-label">Notes</label><input name="notes" class="form-control"></div><div class="col-12 text-end"><button class="btn btn-success" type="submit"><i class="bi bi-save me-1"></i>Save Session</button></div></form></div></div></div></div>`);
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById(modalId));
            modal.show();
            document.getElementById('interviewSessionForm')?.addEventListener('submit', async (event) => {
                event.preventDefault();
                try {
                    await this.apiCall('/admission/interview-sessions', 'POST', Object.fromEntries(new FormData(event.currentTarget)));
                    this.notify('success', 'Interview session saved');
                    modal.hide();
                } catch (error) { this.notify('error', error.message || 'Unable to save interview session'); }
            });
        } catch (error) { this.notify('error', error.message || 'Unable to load interview sessions'); }
    },

    scheduleInterview: async function(applicationId) {
        let response;
        try {
            response = await this.apiCall('/admission/interview-sessions', 'GET');
        } catch (error) {
            this.notify('error', error.message || 'Unable to load interview sessions');
            return;
        }
        const payload = response?.data ?? response ?? {};
        const sessions = Array.isArray(payload) ? payload : (payload.sessions || []);
        const available = sessions.filter((session) => ['scheduled', 'full'].includes(session.status) && Number(session.assigned_count || 0) < Number(session.capacity || 0));
        this.showWorkspaceModal(
            '<i class="bi bi-calendar-plus me-2"></i>Schedule Interview',
            `
                <form id="workspaceScheduleInterviewForm" class="row g-3">
                    <input type="hidden" name="application_id" value="${Number(applicationId)}">
                    <div class="col-12"><label class="form-label">Interview session</label><select name="session_id" class="form-select" required><option value="">Select an existing session</option>${available.map((session) => `<option value="${Number(session.id)}">${this.escapeHtml(session.session_date)} ${this.escapeHtml(String(session.start_time).slice(0,5))}–${this.escapeHtml(String(session.end_time).slice(0,5))} · ${this.escapeHtml(session.venue)} · ${Number(session.assigned_count || 0)}/${Number(session.capacity || 0)}</option>`).join('')}</select>${available.length ? '' : '<div class="form-text text-danger">No available sessions exist. Create an interview session for this intake first.</div>'}</div>
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
                        <label class="form-label">Decision</label>
                        <select name="decision" class="form-select" required>
                            <option value="">Select decision...</option>
                            <option value="pass">Pass / Approve</option>
                            <option value="fail">Fail / Reject</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Score (optional)</label>
                        <input type="number" name="score" class="form-control" min="0" max="100">
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
            const payload = await this.apiCall("/admission/placement-classes", "GET");
            const classes = payload.classes || [];

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

    recordPayment: function(applicationId, registrationFee = 2000) {
        const application = this.getAllQueueApplications().find(row => Number(row.id) === Number(applicationId)) || {};
        const reference = application.application_no || application.admission_number || '';
        const phone = application.parent_phone || application.phone || '';
        this.showWorkspaceModal(
            '<i class="bi bi-wallet2 me-2"></i>Admissions Payment',
            `
                <form id="workspacePaymentForm" class="row g-3">
                    <input type="hidden" name="application_id" value="${Number(applicationId)}">
                    <div class="col-12">
                        <div class="alert alert-info small mb-0">Use <strong>${this.escapeHtml(reference || 'the application reference')}</strong> as the payment account. Parents may pay before placement. Cash is not accepted.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Action</label>
                        <select name="payment_action" id="admissionPaymentAction" class="form-select" required>
                            <option value="instructions">Send payment details (SMS + email)</option>
                            <option value="stk">Send M-Pesa STK Push</option>
                            <option value="bank">Record bank / M-Pesa payment for verification</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Amount</label>
                        <div class="form-text mb-1">Registration fee due: KES ${Number(registrationFee).toLocaleString()}. Add school-fee amount above it where applicable.</div>
                        <input type="number" name="amount" class="form-control" min="${Number(registrationFee)}" step="0.01" value="${Number(registrationFee)}" required>
                    </div>
                    <div class="col-md-6" id="admissionPaymentPhoneWrap">
                        <label class="form-label">Parent M-Pesa phone</label>
                        <input type="tel" name="phone" class="form-control" value="${this.escapeHtml(phone)}" placeholder="07XXXXXXXX" required>
                    </div>
                    <div class="col-md-6" id="admissionPaymentMethodWrap" style="display:none">
                        <label class="form-label">Method</label>
                        <select name="method" class="form-select">
                            <option value="bank_transfer">Bank transfer</option>
                            <option value="mpesa">M-Pesa</option>
                        </select>
                    </div>
                    <div class="col-md-6" id="admissionPaymentReferenceWrap" style="display:none">
                        <label class="form-label">Bank / M-Pesa transaction reference</label>
                        <input type="text" name="reference" class="form-control" placeholder="KCB reference or M-Pesa code">
                    </div>
                    <div class="col-md-6" id="admissionPaymentDateWrap" style="display:none">
                        <label class="form-label">Payment date</label>
                        <input type="date" name="payment_date" class="form-control" value="${new Date().toISOString().slice(0, 10)}">
                    </div>
                    <div class="col-12" id="admissionPaymentNotesWrap" style="display:none">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Optional verification notes"></textarea>
                    </div>
                </form>
            `,
            `
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="workspacePaymentForm" class="btn btn-primary">Continue</button>
            `
        );

        const action = document.getElementById('admissionPaymentAction');
        const toggle = () => {
            const bank = action?.value === 'bank';
            const stk = action?.value === 'stk';
            document.getElementById('admissionPaymentPhoneWrap').style.display = stk ? '' : 'none';
            document.getElementById('admissionPaymentMethodWrap').style.display = bank ? '' : 'none';
            document.getElementById('admissionPaymentReferenceWrap').style.display = bank ? '' : 'none';
            document.getElementById('admissionPaymentDateWrap').style.display = bank ? '' : 'none';
            document.getElementById('admissionPaymentNotesWrap').style.display = bank ? '' : 'none';
            document.querySelector('#workspacePaymentForm [name="amount"]').required = bank || stk;
            document.querySelector('#workspacePaymentForm [name="phone"]').required = stk;
            document.querySelector('#workspacePaymentForm [name="reference"]').required = bank;
        };
        action?.addEventListener('change', toggle);
        toggle();
        document.getElementById("workspacePaymentForm")?.addEventListener("submit", (event) => {
            event.preventDefault();
            const data = Object.fromEntries(new FormData(event.currentTarget));
            const mode = data.payment_action;
            delete data.payment_action;
            if (mode === 'instructions') {
                this.runAdmissionAction(this.apiCall('/admission/payment-instructions', 'POST', { application_id: applicationId }), 'Payment details sent by SMS and email');
            } else if (mode === 'stk') {
                this.runAdmissionAction(this.apiCall('/payments/mpesa-stk-push', 'POST', { account_reference: reference, phone: data.phone, amount: data.amount, description: 'Kingsway admission and school fees' }), 'STK Push sent to the parent');
            } else {
                this.runAdmissionAction(this.apiCall('/admission/record-fee-payment', 'POST', data), 'Payment submitted for verification');
            }
        });
    },

    verifyPayment: async function(applicationId) {
        const application = this.getAllQueueApplications().find(row => Number(row.id) === Number(applicationId)) || {};
        const paymentId = Number(application.pending_payment_id || 0);
        if (!paymentId) {
            this.notify('error', 'No pending payment verification was found. Refresh the queue.');
            return;
        }
        this.showWorkspaceModal(
            '<i class="bi bi-shield-check me-2"></i>Verify Payment',
            `<form id="paymentVerificationForm" class="row g-3">
                <div class="col-12"><div class="alert alert-info small mb-0">Match <strong>${this.escapeHtml(application.pending_payment_reference || '')}</strong> against the official KCB statement or M-Pesa reconciliation record before confirming.</div></div>
                <div class="col-md-6"><label class="form-label">Verification source</label><select name="verification_source" class="form-select" required><option value="kcb_statement">KCB bank statement</option><option value="mpesa_reconciliation">M-Pesa reconciliation</option></select></div>
                <div class="col-12"><label class="form-label">Verification note</label><textarea name="verification_notes" class="form-control" rows="2" placeholder="Optional statement date, batch, or reconciliation note"></textarea></div>
            </form>`,
            '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" form="paymentVerificationForm" class="btn btn-success">Confirm matched payment</button>'
        );
        document.getElementById('paymentVerificationForm')?.addEventListener('submit', (event) => {
            event.preventDefault();
            const data = Object.fromEntries(new FormData(event.currentTarget));
            this.runAdmissionAction(this.apiCall('/admission/confirm-fee-payment', 'POST', { application_id: applicationId, payment_id: paymentId, ...data }), 'Payment verified successfully');
        });
    },

    completeEnrollment: async function(applicationId) {
        try {
            const payload = await this.apiCall('/admission/placement-classes', 'GET');
            const classes = payload?.classes || payload?.data?.classes || [];
            if (!classes.length) {
                this.notify('error', 'No class streams are configured for placement. Configure the academic year class streams first.');
                return;
            }
            this.showWorkspaceModal(
                '<i class="bi bi-diagram-3 me-2"></i>Place Student in Class Stream',
                `<form id="workspacePlacementForm" class="row g-3">
                    <input type="hidden" name="application_id" value="${Number(applicationId)}">
                    <div class="col-12">
                        <label class="form-label">Class / Stream</label>
                        <select class="form-select" name="placement_option" required>
                            <option value="">Select class stream...</option>
                            ${classes.map(row => `<option value="${Number(row.id)}:${Number(row.stream_id || 0)}">${this.escapeHtml(`${row.name || 'Class'}${row.stream_name ? ` — ${row.stream_name}` : ''}`)}</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-12"><div class="alert alert-info small mb-0">Placement creates the academic enrollment and admission-linked onboarding records. Fees are recorded at the next stage.</div></div>
                </form>`,
                '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" form="workspacePlacementForm" class="btn btn-primary">Save Placement</button>'
            );
            document.getElementById('workspacePlacementForm')?.addEventListener('submit', (event) => {
                event.preventDefault();
                const selected = String(new FormData(event.currentTarget).get('placement_option') || '').split(':');
                this.runAdmissionAction(
                    this.apiCall('/admission/complete-enrollment', 'POST', { application_id: applicationId, class_id: Number(selected[0]), stream_id: Number(selected[1]) }),
                    'Class placement saved'
                );
            });
        } catch (error) {
            this.notify('error', error.message || 'Unable to load class streams');
        }
    },

    finalApproval: async function(applicationId) {
        let application = {};
        let workflowData = {};
        try {
            const payload = await this.apiCall(`/admission/application/${Number(applicationId)}`, 'GET');
            application = payload?.application || payload || {};
            workflowData = this.parseJsonSafe(payload.workflow_data || application.workflow_data_json || application.data_json || {});
        } catch (error) {
            this.notify('error', error.message || 'Unable to load approval details');
            return;
        }

        const placement = application.assigned_class_name || workflowData.assigned_class_name || workflowData.class_name || 'Not assigned';
        const stream = application.assigned_stream_name || workflowData.assigned_stream_name || workflowData.stream_name || 'Not assigned';
        const parent = [application.parent_first_name, application.parent_last_name].filter(Boolean).join(' ') || application.parent_name || 'Not recorded';
        const paymentStatus = Array.isArray(workflowData.payment_status)
            ? workflowData.payment_status[workflowData.payment_status.length - 1]
            : workflowData.payment_status;
        const fees = paymentStatus === 'paid' ? 'Paid' : (application.status || 'Not confirmed');
        const idReady = workflowData.student_id_card_generated === true || workflowData.student_id_card_generated === 'true' ? 'Generated' : 'Not generated';
        this.showWorkspaceModal(
            '<i class="bi bi-check-circle me-2"></i>Final Approval',
            `
                <form id="workspaceConfirmEnrollmentForm">
                    <input type="hidden" name="application_id" value="${Number(applicationId)}">
                    <div class="row g-2 mb-3">
                        <div class="col-md-6"><div class="small text-muted">Application No.</div><div class="fw-semibold">${this.escapeHtml(application.application_no || '—')}</div></div>
                        <div class="col-md-6"><div class="small text-muted">Applicant</div><div class="fw-semibold">${this.escapeHtml(application.applicant_name || '—')}</div></div>
                        <div class="col-md-6"><div class="small text-muted">Grade</div><div>${this.escapeHtml(application.grade_applying_for || '—')}</div></div>
                        <div class="col-md-6"><div class="small text-muted">Admission No.</div><div>${this.escapeHtml(application.admission_number || workflowData.admission_number || '—')}</div></div>
                        <div class="col-md-6"><div class="small text-muted">Parent / Guardian</div><div>${this.escapeHtml(parent)}</div></div>
                        <div class="col-md-6"><div class="small text-muted">Assigned Class</div><div>${this.escapeHtml(placement)}</div></div>
                        <div class="col-md-6"><div class="small text-muted">Stream</div><div>${this.escapeHtml(stream)}</div></div>
                        <div class="col-md-6"><div class="small text-muted">Fees</div><div>${this.escapeHtml(fees)}</div></div>
                        <div class="col-md-6"><div class="small text-muted">Student ID Card</div><div>${this.escapeHtml(idReady)}</div></div>
                    </div>
                    <div class="alert alert-warning small">Approving will complete final enrollment. Confirm that the applicant, class/stream placement, fees, and ID-card readiness are correct.</div>
                    <label class="form-label">Confirmation Notes</label>
                    <textarea name="notes" class="form-control" rows="3"></textarea>
                </form>
            `,
            `
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="workspaceConfirmEnrollmentForm" class="btn btn-danger">Approve and enroll</button>
            `
        );

        document.getElementById("workspaceConfirmEnrollmentForm")?.addEventListener("submit", (event) => {
            event.preventDefault();
            this.runAdmissionAction(
                this.apiCall("/admission/final-approval", "POST", Object.fromEntries(new FormData(event.currentTarget))),
                "Final approval completed"
            );
        });
    },

    checkClassSpaceAvailability: async function(applicationId) {
        try {
            const payload = await this.apiCall(`/admission/check-class-space/${applicationId}`, "GET");
            
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
            const payload = await this.apiCall(`/admission/application/${applicationId}`, "GET");
            const app = payload?.application || {};
            
            this.showWorkspaceModal(
                '<i class="bi bi-person-check me-2"></i>Admit Student',
                `
                    <div class="alert alert-info mb-3">
                        <h6 class="alert-heading">Confirm Admission Decision</h6>
                        <p class="mb-2">You are about to admit <strong>${this.escapeHtml(app.applicant_name)}</strong> to <strong>${this.escapeHtml(app.grade_applying_for)}</strong>.</p>
                        <p class="mb-0">This will record the admission decision. The next step is creating the student admission number.</p>
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
                    
                    this.notify("success", "Student admitted. Next: create the student admission number.");
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

    createStudentAdmissionNumber: async function(applicationId) {
        try {
            const payload = await this.apiCall(`/admission/create-student-admission-number/${applicationId}`, "POST");
            
            if (payload && payload.admission_number) {
                this.notify("success", `Student admission number created: ${payload.admission_number || 'N/A'}`);
                this.closeWorkspaceModal();
                await this.loadQueueData();
            } else {
                throw new Error(payload.message || "Failed to create student admission number");
            }
        } catch (error) {
            console.error("Failed to create student admission number:", error);
            this.notify("error", error.message || "Failed to create student admission number");
        }
    },

    createProvisionalStudent: async function(applicationId) {
        return this.createStudentAdmissionNumber(applicationId);
    },

    generateStudentIdCard: async function(applicationId) {
        try {
            const payload = await this.apiCall(`/admission/application/${applicationId}`, "GET");
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
    },

    // ========== Draft Management (Phase 2) ==========
    
    saveDraft: async function(formType, formData) {
        if (typeof KingswayDB === 'undefined') {
            console.warn("KingswayDB not available, skipping draft save");
            return;
        }

        try {
            const draft = {
                id: this.generateUUID(),
                module: 'admissions',
                form_type: formType,
                form_data: formData,
                created_at: Date.now(),
                updated_at: Date.now(),
                user_id: this.getCurrentUserId(),
                status: 'draft'
            };

            await KingswayDB.add('offline_drafts', draft);
            this.notify("info", "Draft saved automatically");
        } catch (error) {
            console.error("[Admissions] Failed to save draft:", error);
        }
    },

    loadDraft: async function(formType) {
        if (typeof KingswayDB === 'undefined') {
            return null;
        }

        try {
            const drafts = await KingswayDB.getByIndex('offline_drafts', 'form_type', formType);
            const userDrafts = drafts.filter(d => d.user_id === this.getCurrentUserId());
            
            if (userDrafts.length > 0) {
                const latestDraft = userDrafts.sort((a, b) => b.updated_at - a.updated_at)[0];
                return latestDraft;
            }
            
            return null;
        } catch (error) {
            console.error("[Admissions] Failed to load draft:", error);
            return null;
        }
    },

    // ========== Conflict Resolution (Phase 4) ==========
    
    handleConflict: function(conflict) {
        
        // Show conflict resolution UI
        const conflictMessage = `
            <div class="alert alert-warning" style="position: fixed; top: 20px; right: 20px; z-index: 10000; max-width: 400px;">
                <h5><i class="bi bi-exclamation-triangle"></i> Data Conflict Detected</h5>
                <p>There is a conflict between your offline changes and the server data.</p>
                <p><strong>Entity:</strong> ${conflict.entity_type} #${conflict.entity_id}</p>
                <div class="mt-3">
                    <button class="btn btn-primary btn-sm" onclick="admissionsWorkspaceController.resolveConflict('${conflict.id}', 'keep_server')">Keep Server Version</button>
                    <button class="btn btn-success btn-sm" onclick="admissionsWorkspaceController.resolveConflict('${conflict.id}', 'keep_local')">Keep Your Changes</button>
                </div>
            </div>
        `;
        
        // Insert conflict UI
        const existingAlert = document.querySelector('.alert.warning[style*="position: fixed"]');
        if (existingAlert) {
            existingAlert.remove();
        }
        
        document.body.insertAdjacentHTML('beforeend', conflictMessage);
        
        this.notify("warning", "Data conflict detected. Please check the conflict resolution panel.");
    },

    resolveConflict: async function(conflictId, resolution) {
        if (typeof ConflictManager === 'undefined') {
            return;
        }

        try {
            await ConflictManager.resolveConflict(conflictId, resolution);
            this.notify("success", "Conflict resolved successfully");
            
            // Remove conflict UI
            const conflictAlert = document.querySelector('.alert.warning[style*="position: fixed"]');
            if (conflictAlert) {
                conflictAlert.remove();
            }
            
            await this.loadQueueData();
        } catch (error) {
            console.error("[Admissions] Failed to resolve conflict:", error);
            this.notify("error", error.message || "Failed to resolve conflict");
        }
    },

    // ========== Offline Operations (Phase 3) ==========
    
    handleOfflineOperation: async function(endpoint, method, data, entityInfo) {
        // Check if offline
        if (!navigator.onLine) {
            // Queue operation for sync
            if (typeof SyncQueue !== 'undefined') {
                await SyncQueue.addOperation({
                    module: 'admissions',
                    endpoint: endpoint,
                    method: method,
                    payload: data,
                    entity_type: entityInfo.type,
                    entity_id: entityInfo.id,
                    priority: 5
                });
                this.notify("info", "Operation saved. Will sync when connection is restored.");
                return { queued: true };
            }
            
            // Fallback error
            this.notify("warning", "You are offline. Please check your connection.");
            return { queued: false, offline: true };
        }
        
        // Online - proceed normally
        return { queued: false, offline: false };
    },

    // ========== Utility Functions ==========
    
    generateUUID: function() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    },

    getCurrentUserId: async function() {
        await window.AuthContext?.ready();
        if (typeof SessionManager !== 'undefined' && SessionManager.isAuthenticated()) {
            const user = SessionManager.getCurrentUser();
            return user ? user.id : null;
        }
        await window.AuthContext?.ready();
        if (window.AuthContext && window.AuthContext.isAuthenticated()) {
            const user = window.AuthContext.getUser();
            return user ? user.id : null;
        }
        return null;
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
        window.admissionsWorkspaceController.init();
        return;
    }

    setTimeout(initWhenAPIReady, 100);
}

document.addEventListener("DOMContentLoaded", function () {
    initWhenAPIReady();
});
