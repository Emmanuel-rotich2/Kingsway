/**
 * Admissions Workspace Controller
 * Unified tabbed interface for managing the complete admissions workflow
 */

const admissionsWorkspaceController = {
    currentTab: 'applications',
    queueData: null,
    initialized: false,
    dom: {},
    pendingStageSkip: null,
    workspaceModalParentState: null,

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

            const canManageRequirements = window.AuthContext?.hasPermission?.('*') ||
                window.AuthContext?.hasPermission?.('admission_manage') ||
                window.AuthContext?.hasPermission?.('admission_applications_edit');
            if (!canManageRequirements) {
                document.getElementById('admissionRequirementsTabNav')?.remove();
                document.getElementById('tab-requirements')?.remove();
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

    navigateApplication: function(offset) {
        const applications = this.getAllQueueApplications();
        const currentIndex = applications.findIndex((application) => Number(application.id) === Number(this.currentApplicationId));
        const nextIndex = (currentIndex < 0 ? 0 : currentIndex) + Number(offset);
        if (nextIndex < 0 || nextIndex >= applications.length) return;
        this.viewApplication(Number(applications[nextIndex].id));
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
            case 'requirements':
                this.loadRequirementsTab(contentDiv, loadingDiv);
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
                                    const applicantName = String(app.applicant_name || 'Unknown').trim();
                                    const applicantInitials = applicantName.split(/\s+/).filter(Boolean).slice(0, 2).map(part => part.charAt(0).toUpperCase()).join('') || '?';
                                    const applicantPhotoUrl = window.KingswayFileLifecycle?.resolveUrl?.(app.passport_photo_url);
                                    const applicantPhoto = applicantPhotoUrl && !/^\d+$/.test(applicantPhotoUrl)
                                        ? `<img src="${this.escapeHtml(applicantPhotoUrl)}" alt="" class="rounded-circle border" style="width:38px;height:38px;object-fit:cover" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">`
                                        : '';
                                    
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
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    ${applicantPhoto}
                                                    <span class="rounded-circle bg-success-subtle text-success fw-semibold align-items-center justify-content-center" style="width:38px;height:38px;display:${applicantPhoto ? 'none' : 'flex'}">${this.escapeHtml(applicantInitials)}</span>
                                                    <div><strong>${this.escapeHtml(applicantName)}</strong><small class="d-block text-muted">${this.escapeHtml(app.gender || '')}</small></div>
                                                </div>
                                            </td>
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
        const uniqueApplications = Array.from(new Map(applications.map(app => [String(app.id), app])).values());
        
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
                                ${uniqueApplications.map(app => {
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
        const applicationsById = new Map();
        const queuePriority = ['placement_pending', 'payment_pending', 'id_generation_pending', 'final_enrollment_pending'];
        queuePriority.forEach(queueName => {
            if (!Array.isArray(queues[queueName])) return;
            queues[queueName].forEach(app => {
                const key = String(app.id);
                const existing = applicationsById.get(key);
                if (!existing) {
                    applicationsById.set(key, { ...app, queue_name: queueName });
                    return;
                }

                // Placement rows contain the placement action, while payment
                // rows contain verified/pending payment totals. Keep the
                // higher-priority queue action and merge the payment fields.
                applicationsById.set(key, {
                    ...existing,
                    ...app,
                    queue_name: existing.queue_name
                });
            });
        });
        const applications = [...applicationsById.values()];
        
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
                                                    ${app.current_stage === 'class_placement' ? `
                                                        <button class="btn btn-sm btn-outline-success" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.completeEnrollment(${app.id})">
                                                            <i class="bi bi-diagram-3"></i> Place student
                                                        </button>
                                                    ` : ''}
                                                    ${app.current_stage === 'fees_payment' ? `
                                                        <button class="btn btn-sm btn-outline-primary" onclick="event.preventDefault(); event.stopPropagation(); admissionsWorkspaceController.recordPayment(${app.id}, ${Number(app.registration_fee_due || 0)})">
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
                                    const applicantName = String(app.applicant_name || 'Unknown').trim();
                                    const applicantInitials = applicantName.split(/\s+/).filter(Boolean).slice(0, 2).map(part => part.charAt(0).toUpperCase()).join('') || '?';
                                    const applicantPhotoUrl = window.KingswayFileLifecycle?.resolveUrl?.(app.passport_photo_url);
                                    const applicantPhoto = applicantPhotoUrl && !/^\d+$/.test(applicantPhotoUrl)
                                        ? `<img src="${this.escapeHtml(applicantPhotoUrl)}" alt="${this.escapeHtml(applicantName)}" class="rounded-circle border" style="width:42px;height:42px;object-fit:cover" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">`
                                        : '';
                                    return `
                                        <tr>
                                            <td><strong>${this.escapeHtml(app.application_no || '—')}</strong></td>
                                            <td><div class="d-flex align-items-center gap-2"><div class="position-relative">${applicantPhoto}<span class="rounded-circle bg-success-subtle text-success fw-semibold align-items-center justify-content-center" style="width:42px;height:42px;display:${applicantPhoto ? 'none' : 'flex'}">${this.escapeHtml(applicantInitials)}</span></div><div><strong>${this.escapeHtml(applicantName)}</strong><small class="d-block text-muted">${this.escapeHtml(app.gender || '')}</small></div></div></td>
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

    loadRequirementsTab: async function(contentDiv, loadingDiv) {
        try {
            const response = await this.apiCall('/admission/requirements', 'GET');
            const rows = response?.requirements || response?.data?.requirements || response?.data || [];
            this.renderRequirementsTab(contentDiv, loadingDiv, Array.isArray(rows) ? rows : []);
        } catch (error) {
            console.error('Failed to load admission requirements:', error);
            this.renderEmptyTab(contentDiv, loadingDiv, error.message || 'Failed to load admission requirements');
        }
    },

    renderRequirementsTab: function(contentDiv, loadingDiv, rows) {
        const gradeOptions = ['','Playgroup','PP1','PP2','Grade1','Grade2','Grade3','Grade4','Grade5','Grade6','Grade7','Grade8','Grade9'];
        const tableRows = rows.length ? rows.map(row => `<tr>
            <td>${this.escapeHtml(row.grade_code || 'All grades')}</td>
            <td>${this.escapeHtml(this.formatLabel(row.gender_code || 'all'))}</td>
            <td>${this.escapeHtml(this.formatLabel(row.student_type_code || 'all'))}</td>
            <td><strong>${this.escapeHtml(row.title || '')}</strong><br><small class="text-muted">${this.escapeHtml(row.description || '')}</small></td>
            <td>${Number(row.display_order || 0)}</td>
            <td>${Number(row.is_active) === 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'}</td>
            <td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="admissionsWorkspaceController.editRequirement(${Number(row.id)})"><i class="bi bi-pencil"></i> Edit</button></td>
        </tr>`).join('') : '<tr><td colspan="7" class="text-center text-muted py-4">No admission requirements configured.</td></tr>';
        contentDiv.innerHTML = `<div class="card border-0 shadow-sm"><div class="card-header bg-white d-flex flex-wrap gap-2 justify-content-between align-items-center"><div><h6 class="mb-0 fw-semibold"><i class="bi bi-list-check me-2 text-success"></i>Admission Requirements</h6><small class="text-muted">Configure requirements by grade, gender, and day/boarding category.</small></div><div class="d-flex gap-2"><button class="btn btn-outline-secondary btn-sm" onclick="admissionsWorkspaceController.exportRequirements()"><i class="bi bi-download me-1"></i>CSV</button><button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print / PDF</button><button class="btn btn-success" onclick="admissionsWorkspaceController.editRequirement()"><i class="bi bi-plus-circle me-1"></i>Add requirement</button></div></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Grade</th><th>Gender</th><th>Category</th><th>Requirement</th><th>Order</th><th>Status</th><th></th></tr></thead><tbody>${tableRows}</tbody></table></div></div><div class="modal fade" id="admissionRequirementModal" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><form class="modal-content" id="admissionRequirementForm"><div class="modal-header bg-success text-white"><h5 class="modal-title">Admission Requirement</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="id" id="admissionRequirementId"><div class="row g-3"><div class="col-md-6"><label class="form-label">Grade / class</label><select class="form-select" name="grade_code" id="admissionRequirementGrade">${gradeOptions.map(g => `<option value="${g}">${g || 'All grades'}</option>`).join('')}</select></div><div class="col-md-3"><label class="form-label">Gender</label><select class="form-select" name="gender_code" id="admissionRequirementGender"><option value="all">All</option><option value="female">Girls</option><option value="male">Boys</option></select></div><div class="col-md-3"><label class="form-label">Category</label><select class="form-select" name="student_type_code" id="admissionRequirementType"><option value="all">All</option><option value="day">Day scholar</option><option value="weekly">Weekly boarder</option><option value="boarder">Full boarder</option></select></div><div class="col-12"><label class="form-label">Title</label><input class="form-control" name="title" id="admissionRequirementTitle" required></div><div class="col-12"><label class="form-label">Instructions</label><textarea class="form-control" name="description" id="admissionRequirementDescription" rows="4" required></textarea></div><div class="col-md-6"><label class="form-label">Display order</label><input class="form-control" type="number" min="0" name="display_order" id="admissionRequirementOrder" value="0"></div><div class="col-md-6 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="admissionRequirementActive" checked><label class="form-check-label" for="admissionRequirementActive">Active</label></div></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Save requirement</button></div></form></div></div>`;
        this.requirementRows = rows;
        loadingDiv.style.display = 'none';
        contentDiv.style.display = 'block';
        document.getElementById('admissionRequirementForm')?.addEventListener('submit', async event => {
            event.preventDefault();
            const data = Object.fromEntries(new FormData(event.currentTarget));
            data.is_active = document.getElementById('admissionRequirementActive').checked ? 1 : 0;
            try { await this.apiCall(data.id ? `/admission/requirements/${data.id}` : '/admission/requirements', data.id ? 'PUT' : 'POST', data); this.notify('success', 'Admission requirement saved'); bootstrap.Modal.getInstance(document.getElementById('admissionRequirementModal'))?.hide(); await this.loadCurrentTab(); }
            catch (error) { this.notify('error', error.message || 'Unable to save admission requirement'); }
        });
    },

    editRequirement: function(id = 0) {
        const row = (this.requirementRows || []).find(item => Number(item.id) === Number(id));
        document.getElementById('admissionRequirementId').value = row?.id || '';
        document.getElementById('admissionRequirementGrade').value = row?.grade_code || '';
        document.getElementById('admissionRequirementGender').value = row?.gender_code || 'all';
        document.getElementById('admissionRequirementType').value = row?.student_type_code || 'all';
        document.getElementById('admissionRequirementTitle').value = row?.title || '';
        document.getElementById('admissionRequirementDescription').value = row?.description || '';
        document.getElementById('admissionRequirementOrder').value = row?.display_order || 0;
        document.getElementById('admissionRequirementActive').checked = row ? Number(row.is_active) === 1 : true;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('admissionRequirementModal')).show();
    },

    exportRequirements: function() {
        const rows = this.requirementRows || [];
        const csvEscape = value => `"${String(value ?? '').replace(/"/g, '""')}"`;
        const csv = [
            ['Grade', 'Gender', 'Category', 'Requirement', 'Instructions', 'Display order', 'Status'],
            ...rows.map(row => [row.grade_code || 'All grades', this.formatLabel(row.gender_code || 'all'), this.formatLabel(row.student_type_code || 'all'), row.title || '', row.description || '', row.display_order || 0, Number(row.is_active) === 1 ? 'Active' : 'Inactive'])
        ].map(row => row.map(csvEscape).join(',')).join('\n');
        if (window.KingswayFileLifecycle?.exportText) {
            window.KingswayFileLifecycle.exportText(csv, 'admission_requirements.csv', 'text/csv');
            return;
        }
        const link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
        link.download = 'admission_requirements.csv';
        link.click();
        URL.revokeObjectURL(link.href);
    },

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
                        <div class="col-12"><hr class="my-1"><div class="small text-muted fw-semibold">Window-controlled workflow calendar</div></div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Interviews start</label>
                            <input type="datetime-local" name="interview_start_at" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Interviews end</label>
                            <input type="datetime-local" name="interview_end_at" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Interview grades deadline</label>
                            <input type="datetime-local" name="interview_results_deadline_at" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Admissions start</label>
                            <input type="datetime-local" name="admission_start_at" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Admissions end</label>
                            <input type="datetime-local" name="admission_end_at" class="form-control">
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
            interview_start_at: formData.get('interview_start_at') || null,
            interview_end_at: formData.get('interview_end_at') || null,
            interview_results_deadline_at: formData.get('interview_results_deadline_at') || null,
            admission_start_at: formData.get('admission_start_at') || null,
            admission_end_at: formData.get('admission_end_at') || null,
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
        document.body.insertAdjacentHTML('beforeend', `<div class="modal fade" id="${modalId}" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Admission Intake Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><dl class="row mb-0"><dt class="col-5">Label</dt><dd class="col-7">${this.escapeHtml(w.label || '')}</dd><dt class="col-5">Academic year</dt><dd class="col-7">${this.escapeHtml(w.year_code || '')}</dd><dt class="col-5">Term</dt><dd class="col-7">${this.escapeHtml(w.term_name || '')}</dd><dt class="col-5">Eligible grades</dt><dd class="col-7">${this.escapeHtml(grades)}</dd><dt class="col-5">Default category</dt><dd class="col-7">${this.escapeHtml(w.default_admission_category || 'Applicant choice')}</dd><dt class="col-5">Applications</dt><dd class="col-7">${this.escapeHtml(this.formatDate(w.application_open_at) || 'Immediately')} to ${this.escapeHtml(this.formatDate(w.application_close_at) || 'Until closed')}</dd><dt class="col-5">Interview period</dt><dd class="col-7">${this.escapeHtml(this.formatDate(w.interview_start_at) || 'Not set')} to ${this.escapeHtml(this.formatDate(w.interview_end_at) || 'Not set')}</dd><dt class="col-5">Interview grades deadline</dt><dd class="col-7">${this.escapeHtml(this.formatDate(w.interview_results_deadline_at) || 'Not set')}</dd><dt class="col-5">Admission period</dt><dd class="col-7">${this.escapeHtml(this.formatDate(w.admission_start_at) || 'Not set')} to ${this.escapeHtml(this.formatDate(w.admission_end_at) || 'Not set')}</dd><dt class="col-5">Notes</dt><dd class="col-7">${this.escapeHtml(w.notes || '—')}</dd></dl></div></div></div></div>`);
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
        form.querySelector('[name="interview_start_at"]').value = this.toDateTimeLocal(w.interview_start_at);
        form.querySelector('[name="interview_end_at"]').value = this.toDateTimeLocal(w.interview_end_at);
        form.querySelector('[name="interview_results_deadline_at"]').value = this.toDateTimeLocal(w.interview_results_deadline_at);
        form.querySelector('[name="admission_start_at"]').value = this.toDateTimeLocal(w.admission_start_at);
        form.querySelector('[name="admission_end_at"]').value = this.toDateTimeLocal(w.admission_end_at);
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
        const recorded = Number(app.recorded_payment_amount || 0);
        const due = Number(app.registration_fee_due || 0);
        const hasPendingVerification = Boolean(app.pending_payment_id);
        const workflowStatus = workflowData.payment_status || '';

        if ((due <= 0 && recorded > 0) || (due > 0 && recorded >= due) || workflowStatus === 'paid' || workflowData.last_payment_recorded_at || workflowData.last_admission_payment_id) {
            return '<span class="badge bg-success">Paid</span>';
        }
        if (hasPendingVerification) {
            return '<span class="badge bg-info text-dark">Verification pending</span>';
        }
        if (recorded > 0) {
            return `<span class="badge bg-warning text-dark">Partially paid (KES ${recorded.toLocaleString()})</span>`;
        }
        return '<span class="badge bg-secondary">Not paid</span>';
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
        // The workspace is the single admissions surface. The shared form
        // controller owns the modal, while this controller owns the queues.
        if (window.newApplicationsController?.showNewApplicationModal) {
            window.newApplicationsController.showNewApplicationModal();
            return;
        }
        this.notify('error', 'The admission application form is not available. Please reload the workspace.');
    },
    
    fetchApplicationSnapshot: async function(applicationId) {
        const response = await this.apiCall(`/admission/application/${Number(applicationId)}`, 'GET');
        // API responses are normalized by api.js in most deployments, while
        // older deployments still wrap the payload in data. Accept both
        // shapes here, but always return the same application snapshot.
        return response?.data?.application ? response.data : (response?.application ? response : (response?.data || response));
    },

    viewApplication: async function(applicationId) {
        if (!applicationId || Number.isNaN(Number(applicationId))) {
            this.notify("error", "Invalid application selected");
            return;
        }

        try {
            if (Number(this.currentApplicationId || 0) !== Number(applicationId)) {
                this.pendingStageSkip = null;
            }
            // Application details are the workflow authority. Do not open a
            // modal from a cached queue projection: payments, interview
            // assessments, document verification and waivers can change it.
            const payload = await this.fetchApplicationSnapshot(applicationId);

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
                this.renderApplicationActionFooter(applicationId, {
                    ...payload.application,
                    stage_contract: payload.stage_contract,
                    available_actions: payload.available_actions
                }, documents, workflowData)
            );
            void this.loadAiDraftsForApplication(applicationId);
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
        const passportDocument = documents.find((doc) => doc.document_type === 'passport_photo');
        const passportPhotoUrl = String(passportDocument?.file_url || passportDocument?.download_url || passportDocument?.document_path || '')
            .replace(/^https?:\/\/[^/]+/i, "");
        const documentsHtml = documents.length
            ? documents.map((doc) => {
                const status = doc.verification_status || "pending";
                const fileUrl = String(doc.file_url || doc.download_url || doc.document_path || "")
                    .replace(/^https?:\/\/[^/]+/i, "");
                return `
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div>
                            <i class="bi bi-file-earmark me-2"></i>
                            ${this.escapeHtml(this.formatLabel(doc.document_type || "Document"))}
                            ${Number(doc.is_mandatory) === 1 ? '<span class="badge bg-danger ms-1">Required</span>' : ""}
                            ${fileUrl ? `
                                <div class="small mt-1">
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" data-kw-document-preview data-application-id="${Number(app.id || 0)}" data-document-id="${Number(doc.id || 0)}" data-url="${this.escapeHtml(fileUrl)}" data-label="${this.escapeHtml(this.formatLabel(doc.document_type || 'Document'))}">
                                        <i class="bi bi-eye me-1"></i>View inside system
                                    </button>
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
        const studentType = String(app.student_type_code || '').trim().toLowerCase();
        const gradeOptions = ['Playground', 'PP1', 'PP2', 'Grade1', 'Grade2', 'Grade3', 'Grade4', 'Grade5', 'Grade6', 'Grade7', 'Grade8', 'Grade9'];

        contentElement.innerHTML = `${window.KingswayDetailModal?.profileHeader(app, passportPhotoUrl)}
            ${this.renderStageWorkspacePanel(app, documents, workflowData, currentStageCode, payload.stage_contract || {})}
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
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Birth Certificate Number</label>
                                <input type="text" name="birth_certificate_no" class="form-control" maxlength="64" autocomplete="off" value="${this.escapeHtml(app.birth_certificate_no || '')}">
                                <div class="form-text">Permanent learner identity anchor used to prevent duplicate applications.</div>
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
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Student category</label>
                                    <select name="student_type_code" class="form-select" required>
                                        <option value="" ${studentType ? '' : 'selected'}>Not supplied — select category</option>
                                        <option value="day" ${studentType === 'day' ? 'selected' : ''}>Day scholar</option>
                                        <option value="boarder" ${studentType === 'boarder' ? 'selected' : ''}>Full boarder</option>
                                    </select>
                                </div>
                                <div class="col-md-8"><div class="alert alert-info small mb-0">Interview and admission dates are controlled by the selected intake window. A learner-specific reporting date is assigned only after a successful interview and placement.</div></div>
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
                                    <select required name="academic_year" id="reviewAcademicYearSelect" class="form-select">
                                        <option value="${this.escapeHtml(app.academic_year || '')}">${this.escapeHtml(app.academic_year || 'Loading academic years…')}</option>
                                    </select>
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
                            ${!this.requiresInterviewGrade(app.grade_applying_for) ? '<div class="alert alert-info small mt-2">This grade does not require an interview. Approval will proceed directly to student admission-number creation.</div>' : ''}
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
                <div class="col-12">
                    <details class="border rounded-3 p-3 bg-light">
                        <summary class="fw-semibold">Additional workflow data</summary>
                        <div class="mt-3">${this.renderWorkflowData(workflowData)}</div>
                    </details>
                </div>
            </div>

            <div id="admissionsAiDraftPanel" class="mt-4"></div>
        `;
    },

    renderCanonicalStageActions: function(contract, stage, id, button) {
        const actions = Array.isArray(contract?.actions) ? contract.actions : [];
        const methods = {
            'review-application': stage === 'application_received' ? 'startApplicationReview' : 'reviewApplication',
            'verify-documents': 'verifyDocuments',
            'pause-review': 'pauseApplicationReview',
            'reject-application': 'rejectApplicationFromModal',
            'schedule-interview': 'scheduleInterview',
            'record-interview': 'conductInterview',
            'admit-student': 'makeDecision',
            'create-student-admission-number': 'createStudentAdmissionNumber',
            'record-payment': 'recordPayment',
            'verify-payment': 'verifyPayment',
            'complete-enrollment': 'completeEnrollment',
            'generate-id-card': 'generateStudentIdCard',
            'final-enrollment': 'finalApproval'
        };
        return actions.map((action) => {
            const method = methods[action.code];
            if (!method || typeof this[method] !== 'function') return '';
            const tone = action.code === 'reject-application' ? 'outline-danger' : action.code === 'pause-review' ? 'warning' : 'primary';
            return button(method, action.label, 'arrow-right-circle', tone);
        }).join('');
    },

    renderStageWorkspacePanel: function(app, documents, workflowData, stage, contract = {}) {
        const id = Number(app.id || 0);
        const stageLabels = {
            application_received: 'Review pending', application_review: 'Under review',
            interview_scheduling: 'Interview scheduling', interview_results: 'Interview assessment',
            student_admission_number: 'Student record creation', class_placement: 'Class and stream placement',
            fees_payment: 'Fees, transport and uniform payments', student_id_generation: 'Student ID generation',
            final_enrollment: 'Final enrollment', enrolled: 'Enrolled'
        };
        const label = stageLabels[stage] || this.formatLabel(stage);
        const button = (method, text, icon, tone = 'primary') =>
            `<button type="button" class="btn btn-${tone} btn-sm" onclick="admissionsWorkspaceController.${method}(${id})"><i class="bi bi-${icon} me-1"></i>${this.escapeHtml(text)}</button>`;
        const skipButton = button('openStageSkipModal', 'Skip stage with reason', 'skip-forward', 'outline-warning');

        if (['application_received', 'application_review'].includes(stage)) {
            const pending = documents.filter((doc) => String(doc.verification_status || 'pending') === 'pending').length;
            const rejected = documents.filter((doc) => String(doc.verification_status || '') === 'rejected').length;
            return `<section class="border border-primary-subtle rounded-3 p-3 mb-4 bg-primary bg-opacity-10">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <div><div class="text-uppercase small fw-bold text-primary">Admissions review workspace</div><h5 class="mb-1">${this.escapeHtml(label)}</h5><p class="small mb-0">Inspect the learner information and submitted documents before deciding the next stage.</p></div>
                    <span class="badge text-bg-${stage === 'application_review' ? 'primary' : 'warning'}">${stage === 'application_review' ? 'Reviewer action required' : 'Awaiting reviewer'}</span>
                </div>
                <div class="row g-2 mt-3"><div class="col-md-4"><div class="bg-white rounded-2 p-2 small"><strong>${documents.length}</strong> documents submitted<br><span class="text-muted">${pending} pending verification</span></div></div><div class="col-md-4"><div class="bg-white rounded-2 p-2 small"><strong>${rejected}</strong> rejected / missing<br><span class="text-muted">parent correction may be required</span></div></div><div class="col-md-4"><div class="bg-white rounded-2 p-2 small"><strong>Next:</strong> ${this.escapeHtml(this.requiresInterviewGrade(app.grade_applying_for) ? 'Interview scheduling' : 'Student admission number')}</div></div></div>
                <div class="d-flex flex-wrap gap-2 mt-3">${this.renderCanonicalStageActions(contract, stage, id, button)}${stage === 'application_review' ? button('saveApplicationReviewFromModal', 'Approve and continue', 'check-circle', 'success') : ''}${skipButton}</div>
            </section>`;
        }

        const stageAction = {
            interview_scheduling: button('scheduleInterview', 'Schedule interview', 'calendar-plus'),
            interview_results: button('conductInterview', 'Record interview results', 'clipboard-check', 'info'),
            student_admission_number: button('createStudentAdmissionNumber', 'Create student admission number', 'person-vcard', 'success'),
            class_placement: button('completeEnrollment', 'Place in class and stream', 'diagram-3', 'success'),
            fees_payment: button('recordPayment', 'Record / reconcile payment', 'cash-coin', 'success'),
            student_id_generation: button('generateStudentIdCard', 'Generate student ID', 'credit-card'),
            final_enrollment: button('finalApproval', 'Complete final enrollment', 'check2-circle', 'success'),
            enrolled: '<span class="badge text-bg-success p-2"><i class="bi bi-check-circle me-1"></i>Enrollment complete — no intake action pending</span>'
        }[stage] || '';
        const actionButtons = this.renderCanonicalStageActions(contract, stage, id, button) || stageAction;
        const stageRail = Array.isArray(contract.stages) && contract.stages.length
            ? `<div class="d-flex flex-wrap gap-1 mt-3 admission-stage-rail">${contract.stages.map((item) => `<span class="badge ${item.state === 'current' ? 'text-bg-primary' : item.state === 'skipped' ? 'text-bg-warning' : item.state === 'completed' ? 'text-bg-success' : 'text-bg-light text-dark border'}" title="${this.escapeHtml(item.name || item.code)}">${this.escapeHtml(String(item.sequence || ''))}. ${this.escapeHtml(item.name || this.formatLabel(item.code))}</span>`).join('')}</div>`
            : '';
        const next = Array.isArray(contract.next_stages) && contract.next_stages.length
            ? `<div class="small text-muted mt-2"><strong>Next permitted stage:</strong> ${this.escapeHtml(contract.next_stages.map((item) => this.formatLabel(item)).join(' / '))}</div>`
            : '';
        return `<section class="border rounded-3 p-3 mb-4 bg-light"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2"><div><div class="text-uppercase small fw-bold text-muted">Current workflow stage</div><h5 class="mb-1">${this.escapeHtml(contract.current_stage_name || label)}</h5><p class="small text-muted mb-0">Actions and stage state are supplied by the admissions workflow snapshot.</p></div><span class="badge text-bg-secondary">${this.escapeHtml(contract.current_stage_name || label)}</span></div>${stageRail}${next}<div class="d-flex flex-wrap gap-2 mt-3">${actionButtons}${stage !== 'enrolled' ? skipButton : ''}</div></section>`;
    },

    openStageSkipModal: function(applicationId) {
        const app = this.currentApplicationData?.application || {};
        const reviewForm = document.getElementById('applicationReviewForm');
        this.pendingSkipReviewPayload = reviewForm ? {
            applicationId: Number(applicationId),
            payload: this.reviewFormPayload(new FormData(reviewForm)),
            reviewNotes: String(new FormData(reviewForm).get('review_notes') || '')
        } : null;
        const stages = ['application_applied','application_received','application_review','interview_scheduling','interview_results','student_admission_number','class_placement','fees_payment','student_id_generation','final_enrollment'];
        const current = app.current_stage || 'application_received';
        const index = stages.indexOf(current);
        const destinations = stages.slice(index + 1);
        if (!destinations.length) return this.notify('error', 'There is no later stage to skip to.');
        this.showWorkspaceChildModal('<i class="bi bi-skip-forward me-2"></i>Skip admission stage', `<form id="stageSkipForm" class="row g-3"><input type="hidden" name="application_id" value="${Number(applicationId)}"><div class="col-12 alert alert-warning small mb-0"><strong>Current stage:</strong> ${this.escapeHtml(this.formatLabel(current))}<br>The workflow exception and any financial relief will be saved now and carried into later fee generation.</div><div class="col-md-6"><label class="form-label">Continue at</label><select name="destination_stage" class="form-select" required>${destinations.map((stage) => `<option value="${stage}">${this.escapeHtml(this.formatLabel(stage))}</option>`).join('')}</select></div><div class="col-md-6"><label class="form-label">Exception type</label><select name="reason_code" class="form-select" required><option value="sponsored">Sponsored learner</option><option value="exempted">Exempted</option><option value="special_case">Special case</option><option value="administrative_exception">Administrative exception</option><option value="other">Other</option></select></div><div class="col-md-6"><label class="form-label">Admission / registration fee</label><select name="registration_fee_waived" class="form-select"><option value="0">Keep due</option><option value="1">Waive completely</option></select></div><div class="col-md-6"><label class="form-label">School-fee relief</label><select name="school_fee_waiver_type" id="schoolFeeWaiverType" class="form-select"><option value="none">Keep full school fees due</option><option value="percentage">Waive percentage</option><option value="fixed">Waive fixed amount</option><option value="full">Waive all school fees</option></select></div><div class="col-md-6" id="schoolFeeWaiverValueWrap"><label class="form-label">Relief value</label><input type="number" name="school_fee_waiver_value" class="form-control" min="0" step="0.01" placeholder="Percentage or KES amount"><div class="form-text">For percentage, enter 50 for half. For fixed, enter the KES amount.</div></div><div class="col-12"><label class="form-label">Reason and authorization note</label><textarea name="reason" class="form-control" rows="4" required placeholder="Explain the sponsorship/exception, approval authority and any conditions."></textarea></div></form>`, '<button type="button" class="btn btn-secondary" onclick="admissionsWorkspaceController.restoreWorkspaceModal()">Back to Application Details</button><button type="submit" form="stageSkipForm" class="btn btn-warning">Save exception and return</button>');
        const skipForm = document.getElementById('stageSkipForm');
        if (skipForm) {
            const registration = skipForm.elements.registration_fee_waived;
            registration?.insertAdjacentHTML('afterend', '<select name="registration_fee_waiver_type" class="form-select mt-2"><option value="none">No registration-fee relief</option><option value="full">Full registration-fee waiver</option><option value="percentage">Percentage registration-fee waiver</option><option value="fixed">Fixed registration-fee waiver</option></select><input name="registration_fee_waiver_value" type="number" min="0" step="0.01" class="form-control mt-2" placeholder="Registration percentage or KES amount">');
            const placementWrap = document.createElement('div');
            placementWrap.className = 'col-12 d-none';
            placementWrap.id = 'skipPlacementWrap';
            placementWrap.innerHTML = '<label class="form-label">Class / stream required for automated placement</label><select name="placement_option" class="form-select"><option value="">Loading active class streams…</option></select><div class="form-text">The transaction will not guess a class or stream.</div>';
            skipForm.querySelector('[name="reason"]')?.closest('.col-12')?.before(placementWrap);
            const destination = skipForm.elements.destination_stage;
            const loadPlacementChoices = async () => {
                const needsPlacement = ['class_placement', 'fees_payment', 'student_id_generation', 'final_enrollment'].includes(destination?.value);
                placementWrap.classList.toggle('d-none', !needsPlacement);
                if (!needsPlacement || placementWrap.dataset.loaded === '1') return;
                try {
                    const response = await this.apiCall('/admission/placement-classes', 'GET');
                    const rows = response?.classes || response?.data?.classes || [];
                    const select = placementWrap.querySelector('select');
                    select.innerHTML = rows.length ? '<option value="">Select class / stream</option>' + rows.filter(row => row.academic_year_class_stream_id && row.stream_id).map(row => `<option value="${Number(row.academic_year_class_stream_id)}:${Number(row.id)}:${Number(row.stream_id)}">${this.escapeHtml(`${row.name || 'Class'}${row.stream_name ? ` — ${row.stream_name}` : ''}`)}</option>`).join('') : '<option value="">No active class streams configured</option>';
                    placementWrap.dataset.loaded = '1';
                } catch (error) { placementWrap.querySelector('select').innerHTML = '<option value="">Unable to load class streams</option>'; }
            };
            destination?.addEventListener('change', loadPlacementChoices);
            loadPlacementChoices();
        }
        document.getElementById('schoolFeeWaiverType')?.addEventListener('change', (event) => {
            const wrap = document.getElementById('schoolFeeWaiverValueWrap');
            if (wrap) wrap.classList.toggle('d-none', event.target.value === 'none' || event.target.value === 'full');
        });
        document.getElementById('schoolFeeWaiverType')?.dispatchEvent(new Event('change'));
        document.getElementById('stageSkipForm')?.addEventListener('submit', (event) => {
            event.preventDefault();
            const formData = Object.fromEntries(new FormData(event.currentTarget));
            const placementParts = String(formData.placement_option || '').split(':');
            formData.placement = placementParts.length === 3 && placementParts[0] ? {
                academic_year_class_stream_id: Number(placementParts[0]),
                class_id: Number(placementParts[1]),
                stream_id: Number(placementParts[2])
            } : {};
            delete formData.placement_option;
            this.executeSkipOrchestration(formData);
        });
    },

    executeSkipOrchestration: async function(request) {
        this.showWorkspaceChildModal('<i class="bi bi-shuffle me-2"></i>Running skip transaction', '<div id="skipOrchestrationProgress" class="small">Preparing workflow operations…</div>', '<button type="button" class="btn btn-secondary" onclick="admissionsWorkspaceController.restoreWorkspaceModal()">Back to Application Details</button>');
        const render = (payload) => {
            const target = document.getElementById('skipOrchestrationProgress');
            if (!target) return;
            const steps = Array.isArray(payload?.progress) ? payload.progress : [];
            target.innerHTML = `<div class="alert ${payload?.status === 'blocked' ? 'alert-warning' : payload?.status === 'failed' ? 'alert-danger' : 'alert-info'}">${this.escapeHtml(payload?.blocked_reason || 'The system is executing each required operation in order.')}</div><div class="list-group">${steps.map(step => `<div class="list-group-item d-flex justify-content-between align-items-center"><span><i class="bi ${step.status === 'completed' || step.status === 'completed_with_waiver' ? 'bi-check-circle text-success' : step.status === 'skipped' ? 'bi-skip-forward text-warning' : step.status === 'running' ? 'bi-arrow-repeat text-primary' : 'bi-hourglass text-muted'} me-2"></i>${this.escapeHtml(step.label)}</span><span class="badge ${step.status === 'completed' || step.status === 'completed_with_waiver' ? 'text-bg-success' : step.status === 'skipped' ? 'text-bg-warning' : step.status === 'running' ? 'text-bg-primary' : 'text-bg-light text-dark'}">${this.escapeHtml(this.formatLabel(step.status))}</span></div>`).join('')}</div>`;
        };
        try {
            if (this.pendingSkipReviewPayload?.applicationId === Number(request.application_id)) {
                await this.apiCall(`/admission/application/${Number(request.application_id)}`, 'PUT', this.pendingSkipReviewPayload.payload);
                await this.apiCall('/admission/save-review-draft', 'POST', { application_id: Number(request.application_id), review_notes: this.pendingSkipReviewPayload.reviewNotes });
                this.pendingSkipReviewPayload = null;
            }
            let payload = null;
            for (let attempt = 0; attempt < 20; attempt += 1) {
                const response = await this.apiCall('/admission/orchestrate-skip', 'POST', request);
                payload = response?.data || response || {};
                render(payload);
                if (payload.status === 'completed') {
                    this.notify('success', 'Skip transaction completed and all required stages were processed.');
                    await this.loadQueueData();
                    await this.viewApplication(Number(request.application_id));
                    return;
                }
                if (payload.status === 'blocked' || payload.status === 'failed') return;
            }
            throw new Error('The skip transaction exceeded its safe retry limit. Reopen it to resume from the saved checkpoint.');
        } catch (error) {
            render({ status: 'failed', blocked_reason: error.message, progress: [] });
            this.notify('error', error.message || 'Skip transaction failed');
        }
    },

    startApplicationReview: async function(applicationId) {
        try {
            await this.apiCall('/admission/advance-workflow-stage', 'POST', { application_id: Number(applicationId), to_stage: 'application_review', action: 'start_application_review', notes: 'Application review started' });
            this.notify('success', 'Application review started');
            await this.viewApplication(applicationId);
            await this.loadQueueData();
        } catch (error) { this.notify('error', error.message || 'Unable to start application review'); }
    },

    saveApplicationReviewFromModal: function() {
        const form = document.getElementById('applicationReviewForm');
        if (form) this.saveApplicationReview(form, this.requiresInterviewGrade(form.elements.grade_applying_for?.value) ? 'interview_scheduling' : 'student_admission_number');
    },

    saveApplicationReviewDraftFromModal: async function() {
        const form = document.getElementById('applicationReviewForm');
        if (!form) return;
        const formData = new FormData(form);
        const applicationId = Number(formData.get('application_id'));
        try {
            await this.apiCall(`/admission/application/${applicationId}`, 'PUT', this.reviewFormPayload(formData));
            await this.apiCall('/admission/save-review-draft', 'POST', { application_id: applicationId, review_notes: String(formData.get('review_notes') || '') });
            this.notify('success', 'Review saved. The workflow has not advanced.');
        } catch (error) {
            this.notify('error', error.message || 'Unable to save the review');
        }
    },

    rejectApplicationFromModal: function() {
        const form = document.getElementById('applicationReviewForm');
        if (form) this.rejectApplicationReview(form);
    },

    pauseApplicationReview: async function(applicationId) {
        const form = document.getElementById('applicationReviewForm');
        const notes = String(form?.elements.review_notes?.value || '').trim();
        if (!notes) { this.notify('error', 'Enter the missing information or correction request in Review Notes first.'); form?.elements.review_notes?.focus(); return; }
        try {
            await this.apiCall('/admission/pause-review', 'POST', { application_id: Number(applicationId), notes });
            this.notify('success', 'Application paused and the parent notification was queued');
            this.closeWorkspaceModal();
            await this.loadQueueData();
        } catch (error) { this.notify('error', error.message || 'Unable to pause application review'); }
    },

    loadReviewAdmissionWindows: async function() {
        const select = document.getElementById('reviewAdmissionWindowSelect');
        if (!select) return;
        const yearSelect = document.getElementById('reviewAcademicYearSelect');
        try {
            const response = await this.apiCall('/admission/windows', 'GET');
            const payload = response?.windows ? response : (response?.data || response || {});
            const windows = Array.isArray(payload) ? payload : (payload.windows || []);
            const currentTermId = Number(this.currentApplicationData?.application?.target_term_id || 0);
            const currentYear = String(this.currentApplicationData?.application?.academic_year || '');
            const years = new Map();
            windows.forEach((window) => {
                const code = String(window.year_code || window.year_name || '').trim();
                const match = code.match(/\d{4}/);
                const value = String(match ? match[0] : (window.academic_year_id || '')).trim();
                if (value && !years.has(value)) years.set(value, code || value);
            });
            if (yearSelect) {
                if (currentYear && !years.has(currentYear)) years.set(currentYear, currentYear);
                yearSelect.innerHTML = Array.from(years.entries()).map(([value, label]) =>
                    `<option value="${this.escapeHtml(value)}"${value === currentYear ? ' selected' : ''}>${this.escapeHtml(label)}</option>`
                ).join('') || '<option value="">No academic years configured</option>';
            }
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
            select.addEventListener('change', () => {
                const selectedWindow = windows.find((window) => String(window.id) === String(select.value));
                if (!selectedWindow || !yearSelect) return;
                const code = String(selectedWindow.year_code || selectedWindow.year_name || '').trim();
                const match = code.match(/\d{4}/);
                const value = String(match ? match[0] : (selectedWindow.academic_year_id || '')).trim();
                if (value && Array.from(yearSelect.options).some((option) => option.value === value)) {
                    yearSelect.value = value;
                }
            });
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
            await this.apiCall('/admission/save-review-draft', 'POST', { application_id: applicationId, review_notes: String(formData.get('review_notes') || '') });
            if (this.pendingStageSkip && Number(this.pendingStageSkip.application_id) === applicationId) {
                await this.apiCall('/admission/skip-stage', 'POST', this.pendingStageSkip);
                this.pendingStageSkip = null;
            } else {
                await this.apiCall('/admission/advance-workflow-stage', 'POST', {
                    application_id: applicationId,
                    to_stage: nextStage,
                    action: 'review_application',
                    notes: formData.get('review_notes') || null,
                    workflow_updates: JSON.stringify({
                        reviewed: true,
                        reviewed_at: new Date().toISOString(),
                        interview_skipped: false,
                        interview_skip_reason: null
                    })
                });
            }
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
            birth_certificate_no: String(formData.get('birth_certificate_no') || '').trim() || null,
            gender: formData.get('gender') || null,
            grade_applying_for: String(formData.get('grade_applying_for') || '').trim(),
            academic_year: String(formData.get('academic_year') || '').trim(),
            student_type_code: formData.get('student_type_code') || null,
            admission_window_id: formData.get('admission_window_id') || null,
            previous_school: String(formData.get('previous_school') || '').trim() || null,
            admission_category: formData.get('admission_category') || 'standard',
            application_source: formData.get('application_source') || 'physical',
            has_special_needs: formData.get('has_special_needs') ? 1 : 0,
            special_needs_details: String(formData.get('special_needs_details') || '').trim() || null,
            review_notes: String(formData.get('review_notes') || '').trim() || null
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

        const latestValue = (value) => {
            if (!Array.isArray(value)) return value;
            const meaningful = value.filter((item) => item !== null && item !== undefined && item !== '');
            return meaningful.length ? meaningful[meaningful.length - 1] : null;
        };
        const renderValue = (key, value) => {
            if (key === 'assessment_items' && Array.isArray(value)) {
                const unique = new Map();
                value.forEach((item) => {
                    if (!item || typeof item !== 'object') return;
                    const itemKey = item.learning_area_id || item.learning_area_name || JSON.stringify(item);
                    unique.set(String(itemKey), item);
                });
                const rows = [...unique.values()];
                return rows.length
                    ? `<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Learning area</th><th>Score</th><th>Grade</th><th>Level</th></tr></thead><tbody>${rows.map((item) => `<tr><td>${this.escapeHtml(item.learning_area_name || '—')}</td><td>${this.escapeHtml(item.score ?? '—')}/${this.escapeHtml(item.max_score ?? 100)}</td><td>${this.escapeHtml(item.grade_code || '—')}</td><td>${this.escapeHtml(item.performance_level || '—')}</td></tr>`).join('')}</tbody></table></div>`
                    : 'No assessment items recorded';
            }
            const display = latestValue(value);
            if (display === null || display === undefined || display === '') return '—';
            if (typeof display === 'boolean') return display ? 'Yes' : 'No';
            if (typeof display === 'object') return this.escapeHtml(JSON.stringify(display));
            return this.escapeHtml(String(display));
        };

        return `
            <dl class="row mb-0">
                ${Object.entries(visibleWorkflowData).map(([key, value]) => `
                    <dt class="col-sm-5">${this.escapeHtml(this.formatLabel(key))}</dt>
                    <dd class="col-sm-7">${renderValue(key, value)}</dd>
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

    showWorkspaceChildModal: function(title, bodyHtml, footerHtml = "") {
        const modalElement = document.getElementById("admissionsWorkspaceApplicationModal");
        if (!modalElement) {
            this.notify("error", "Workspace modal is not available");
            return null;
        }

        // Keep the currently open application details view intact. Child
        // workflows (documents, upload, interview, payments, etc.) must return
        // to their parent view instead of closing the entire workspace.
        if (!this.workspaceModalParentState && modalElement.classList.contains('show')) {
            this.workspaceModalParentState = {
                title: modalElement.querySelector('.modal-title')?.innerHTML || '',
                body: document.getElementById('admissionsWorkspaceApplicationContent')?.innerHTML || '',
                footer: modalElement.querySelector('.modal-footer')?.innerHTML || '',
                scrollTop: document.getElementById('admissionsWorkspaceApplicationContent')?.scrollTop || 0
            };
        }

        const restoreOnHidden = () => {
            if (this.workspaceModalParentState) {
                this.restoreWorkspaceModal();
            }
        };
        modalElement.addEventListener('hidden.bs.modal', restoreOnHidden, { once: true });
        this.showWorkspaceModal(title, bodyHtml, footerHtml || '<button type="button" class="btn btn-secondary" onclick="admissionsWorkspaceController.restoreWorkspaceModal()">Back to Application Details</button>');
        return modalElement;
    },

    restoreWorkspaceModal: function() {
        const state = this.workspaceModalParentState;
        if (!state) {
            this.closeWorkspaceModal();
            return;
        }
        const modalElement = document.getElementById("admissionsWorkspaceApplicationModal");
        if (!modalElement) return;
        const titleElement = modalElement.querySelector('.modal-title');
        const bodyElement = document.getElementById('admissionsWorkspaceApplicationContent');
        const footerElement = modalElement.querySelector('.modal-footer');
        if (titleElement) titleElement.innerHTML = state.title;
        if (bodyElement) {
            bodyElement.innerHTML = state.body;
            bodyElement.scrollTop = state.scrollTop;
        }
        if (footerElement) footerElement.innerHTML = state.footer;
        this.workspaceModalParentState = null;
        bootstrap.Modal.getOrCreateInstance(modalElement).show();
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

    methodForStageAction: function(actionCode) {
        return {
            'review-application': 'reviewApplication',
            'schedule-interview': 'scheduleInterview',
            'record-interview': 'conductInterview',
            'admit-student': 'makeDecision',
            'create-student-admission-number': 'createStudentAdmissionNumber',
            'record-payment': 'recordPayment',
            'verify-payment': 'verifyPayment',
            'complete-enrollment': 'completeEnrollment',
            'generate-id-card': 'generateStudentIdCard',
            'final-enrollment': 'finalApproval'
        }[actionCode] || null;
    },

    renderWorkflowContext: function(snapshot, purpose = 'Current workflow context') {
        const app = snapshot?.application || {};
        const contract = snapshot?.stage_contract || {};
        const parent = [app.parent_first_name, app.parent_last_name].filter(Boolean).join(' ') || 'Not recorded';
        const relief = app.financial_relief || {};
        const reliefText = relief.registration_fee_waived || relief.school_fee_waiver_type && relief.school_fee_waiver_type !== 'none'
            ? 'Approved financial relief applies — confirm the displayed due amount before posting.'
            : 'No approved financial relief recorded.';
        return `<div class="admission-workflow-context border rounded-3 bg-light p-3 mb-3">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-2"><strong>${this.escapeHtml(purpose)}</strong><span class="badge text-bg-primary">${this.escapeHtml(contract.current_stage_name || this.formatLabel(app.current_stage || '—'))}</span></div>
            <div class="row g-2 small">
                <div class="col-md-4"><span class="text-muted d-block">Learner</span><strong>${this.escapeHtml(app.applicant_name || '—')}</strong></div>
                <div class="col-md-4"><span class="text-muted d-block">Application</span><strong>${this.escapeHtml(app.application_no || '—')}</strong></div>
                <div class="col-md-4"><span class="text-muted d-block">Grade / student type</span><strong>${this.escapeHtml(`${app.grade_applying_for || '—'} · ${app.student_type_code || 'Not recorded'}`)}</strong></div>
                <div class="col-md-4"><span class="text-muted d-block">Parent / guardian</span>${this.escapeHtml(parent)}</div>
                <div class="col-md-4"><span class="text-muted d-block">Academic year / term</span>${this.escapeHtml(`${app.academic_year || '—'} · ${app.target_term_id || '—'}`)}</div>
                <div class="col-md-4"><span class="text-muted d-block">Financial treatment</span>${this.escapeHtml(reliefText)}</div>
            </div>
        </div>`;
    },

    startIntake: async function(applicationId) {
        try {
            const payload = await this.fetchApplicationSnapshot(applicationId);
            const app = payload?.application || {};
            const workflowData = this.parseJsonSafe(app.workflow_data_json || app.data_json || payload.workflow_data || {});
            const documents = Array.isArray(payload.documents) ? payload.documents : [];
            const currentStage = app.current_stage || payload?.stage_metadata?.current_stage || "application_received";
            
            // Get workflow communication
            const contractAction = Array.isArray(payload.stage_contract?.actions)
                ? payload.stage_contract.actions.find((action) => action.allowed !== false)
                : null;
            const workflowComm = contractAction
                ? {
                    nextActionLabel: contractAction.label,
                    nextActionMethod: this.methodForStageAction(contractAction.code),
                    blockingReason: null,
                    label: payload.stage_contract.current_stage_name || 'Workflow action',
                    description: 'Continue using the action authorized by the current workflow snapshot.',
                    waitingFor: 'Admissions workflow'
                }
                : this.getApplicationWorkflowCommunication(app, documents, workflowData);
            
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
                if (workflowData.payment_status === 'waived') {
                    label = 'Payment Requirement Waived - Awaiting ID Generation';
                    description = 'Approved financial relief covers the admission payment requirement. No money was received or posted; generate the student ID card.';
                    waitingFor = 'School Admin';
                    nextActionLabel = 'Generate ID Card';
                    nextActionMethod = 'generateStudentIdCard';
                    tone = 'success';
                } else if (workflowData.payment_status === 'paid') {
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
        const applications = this.getAllQueueApplications();
        const currentIndex = applications.findIndex((application) => Number(application.id) === Number(applicationId));
        const previousBtn = `<button type="button" class="btn btn-outline-secondary" ${currentIndex <= 0 ? 'disabled' : ''} onclick="admissionsWorkspaceController.navigateApplication(-1)"><i class="bi bi-chevron-left me-1"></i>Previous</button>`;
        const nextBtn = `<button type="button" class="btn btn-outline-secondary" ${currentIndex < 0 || currentIndex >= applications.length - 1 ? 'disabled' : ''} onclick="admissionsWorkspaceController.navigateApplication(1)">Next<i class="bi bi-chevron-right ms-1"></i></button>`;
        const navigation = `<span class="small text-muted me-auto">${currentIndex >= 0 ? currentIndex + 1 : 0} of ${applications.length}</span>${previousBtn}${nextBtn}`;
        const stage = app.current_stage || "application_received";
        if (app.status === 'enrolled' || stage === 'enrolled') {
            return `${navigation}${closeBtn}`;
        }
        const aiBtn = ['application_received', 'application_review'].includes(stage)
            ? `<button type="button" class="btn btn-outline-success" onclick="admissionsWorkspaceController.queueFollowupDraft(${Number(applicationId)})"><i class="bi bi-stars me-1"></i>Draft follow-up</button>` : '';
        const aiInterviewBtn = ['interview_scheduling', 'interview_results'].includes(stage)
            ? `<button type="button" class="btn btn-outline-primary" onclick="admissionsWorkspaceController.queueInterviewPreparation(${Number(applicationId)})"><i class="bi bi-person-video3 me-1"></i>Prepare interview</button>` : '';
        const aiPlacementBtn = stage === 'class_placement'
            ? `<button type="button" class="btn btn-outline-primary" onclick="admissionsWorkspaceController.queuePlacementReview(${Number(applicationId)})"><i class="bi bi-mortarboard me-1"></i>Review placement</button>` : '';

        if (['application_received', 'application_review'].includes(stage)) {
            const nextStage = stage === 'application_received'
                ? 'application_review'
                : (this.requiresInterviewGrade(app.grade_applying_for)
                    ? 'interview_scheduling'
                    : 'student_admission_number');
            return `${navigation}${closeBtn}
                ${aiBtn}${aiInterviewBtn}${aiPlacementBtn}
                <button type="button" class="btn btn-outline-primary" onclick="admissionsWorkspaceController.saveApplicationReviewDraftFromModal()">
                    <i class="bi bi-save me-1"></i>Save review
                </button>
                <button type="button" class="btn btn-outline-danger" onclick="admissionsWorkspaceController.rejectApplicationReview(document.getElementById('applicationReviewForm'))">
                    <i class="bi bi-x-circle me-1"></i>Reject
                </button>
                <button type="button" class="btn btn-success" id="reviewSaveBtn" onclick="admissionsWorkspaceController.saveApplicationReview(document.getElementById('applicationReviewForm'), '${nextStage}')">
                    <i class="bi bi-check-circle me-1"></i>Save &amp; Continue
                </button>`;
        }

        // The API stage contract is authoritative. The legacy communication
        // resolver remains only as a compatibility fallback for older API
        // deployments that do not yet return stage_contract.
        const contractAction = Array.isArray(app.stage_contract?.actions)
            ? app.stage_contract.actions.find((action) => action.allowed !== false)
            : null;
        if (contractAction) {
            const method = this.methodForStageAction(contractAction.code);
            if (method && typeof this[method] === 'function') {
                const btn = `<button type="button" class="btn btn-primary" onclick="admissionsWorkspaceController.${method}(${Number(applicationId)})"><i class="bi bi-arrow-right-circle me-1"></i>${this.escapeHtml(contractAction.label)}</button>`;
                return navigation + closeBtn + aiBtn + aiInterviewBtn + aiPlacementBtn + btn;
            }
        }
        const comm = this.getApplicationWorkflowCommunication(app, documents, workflowData);
        if (comm && comm.nextActionMethod && comm.nextActionLabel) {
            const btn = `<button type="button" class="btn btn-primary" onclick="admissionsWorkspaceController.${comm.nextActionMethod}(${Number(applicationId)})"><i class="bi bi-arrow-right-circle me-1"></i>${this.escapeHtml(comm.nextActionLabel)}</button>`;
            return navigation + closeBtn + aiBtn + aiInterviewBtn + aiPlacementBtn + btn;
        }

        return navigation + closeBtn + aiBtn + aiInterviewBtn + aiPlacementBtn;
    },

    queueInterviewPreparation: async function(applicationId) {
        try {
            const response = await this.apiCall('/admission/ai-interview-preparation-queue', 'POST', { application_id: Number(applicationId) });
            this.notify('info', `Interview preparation queued${response?.job_id ? ` (${response.job_id})` : ''}.`);
            await this.pollAiDraftsForApplication(applicationId);
        } catch (error) { this.notify('error', error.message || 'Unable to queue interview preparation'); }
    },

    queuePlacementReview: async function(applicationId) {
        try {
            const response = await this.apiCall('/admission/ai-placement-review-queue', 'POST', { application_id: Number(applicationId) });
            this.notify('info', `Placement review queued${response?.job_id ? ` (${response.job_id})` : ''}.`);
            await this.pollAiDraftsForApplication(applicationId);
        } catch (error) { this.notify('error', error.message || 'Unable to queue placement review'); }
    },

    queueFollowupDraft: async function(applicationId) {
        const button = document.querySelector('#admissionsWorkspaceApplicationModal button[onclick*="queueFollowupDraft"]');
        if (button) button.disabled = true;
        try {
            const response = await this.apiCall('/admission/ai-followup-draft-queue', 'POST', {
                application_id: Number(applicationId),
                channel: 'email'
            });
            this.notify('info', `Follow-up draft queued${response?.job_id ? ` (job ${response.job_id})` : ''}.`);
            await this.pollAiDraftsForApplication(applicationId);
        } catch (error) {
            this.notify('error', error.message || 'Unable to queue follow-up draft');
        } finally {
            if (button) button.disabled = false;
        }
    },

    pollAiDraftsForApplication: async function(applicationId) {
        for (let attempt = 0; attempt < 10; attempt += 1) {
            await new Promise((resolve) => setTimeout(resolve, attempt === 0 ? 1200 : 2500));
            await this.loadAiDraftsForApplication(applicationId);
            const panel = document.getElementById('admissionsAiDraftPanel');
            if (panel && panel.querySelector('[data-ai-draft-status="pending_approval"], [data-ai-draft-status="approved"]')) return;
        }
    },

    loadAiDraftsForApplication: async function(applicationId) {
        const panel = document.getElementById('admissionsAiDraftPanel');
        if (!panel) return;
        try {
            const own = await this.apiCall('/admission/ai-drafts?scope=own', 'GET');
            const ownDrafts = Array.isArray(own?.drafts) ? own.drafts : [];
            const matching = ownDrafts.filter((draft) => Number(draft.subject_id) === Number(applicationId));
            let reviewDrafts = [];
            try {
                const review = await this.apiCall('/admission/ai-drafts?scope=review', 'GET');
                reviewDrafts = (Array.isArray(review?.drafts) ? review.drafts : [])
                    .filter((draft) => Number(draft.subject_id) === Number(applicationId));
            } catch (_) {
                // Review access is optional; the operator can still see own drafts.
            }
            const drafts = [
                ...matching.map((draft) => ({ ...draft, __review: false })),
                ...reviewDrafts.map((draft) => ({ ...draft, __review: true }))
            ];
            if (!drafts.length) {
                panel.innerHTML = '<div class="alert alert-light border small mb-0"><i class="bi bi-stars me-1"></i>AI follow-up drafts will appear here after generation.</div>';
                return;
            }
            panel.innerHTML = `<div class="border rounded-3 p-3 bg-light">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0"><i class="bi bi-stars text-success me-1"></i>Staff-assistant drafts</h6>
                    <small class="text-muted">Human approval required</small>
                </div>
                ${drafts.map((draft) => {
                    const body = draft.draft || {};
                    const canApprove = draft.__review === true && draft.status === 'pending_approval';
                    const approvalKind = {
                        'admissions.application_followup_draft': 'followup',
                        'admissions.interview_preparation': 'interview',
                        'admissions.placement_review': 'placement'
                    }[draft.workflow_id] || '';
                    return `<article class="card border-0 shadow-sm mb-2" data-ai-draft-status="${this.escapeHtml(draft.status || '')}">
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between gap-2"><strong>${this.escapeHtml(body.title || 'Untitled draft')}</strong><span class="badge text-bg-secondary">${this.escapeHtml(draft.status || '')}</span></div>
                            <p class="small mb-2 mt-2">${this.escapeHtml(body.body || '')}</p>
                            ${Array.isArray(body.next_steps) && body.next_steps.length ? `<ul class="small mb-2">${body.next_steps.map((step) => `<li>${this.escapeHtml(step)}</li>`).join('')}</ul>` : ''}
                            ${canApprove && approvalKind ? `<button type="button" class="btn btn-sm btn-success" onclick="admissionsWorkspaceController.approveAiDraft(${Number(draft.id)}, ${Number(applicationId)}, '${approvalKind}')">Approve draft</button>` : ''}
                        </div>
                    </article>`;
                }).join('')}
            </div>`;
        } catch (error) {
            panel.innerHTML = '<div class="alert alert-warning small mb-0">AI draft status is temporarily unavailable.</div>';
        }
    },

    approveAiDraft: async function(draftId, applicationId, approvalKind = '') {
        try {
            const endpointByKind = {
                followup: '/admission/ai-followup-draft-approve',
                interview: '/admission/ai-interview-preparation-approve',
                placement: '/admission/ai-placement-review-approve'
            };
            const endpoint = endpointByKind[approvalKind];
            if (!endpoint) throw new Error('This admissions AI workflow cannot be approved from this screen.');
            await this.apiCall(`${endpoint}/${Number(draftId)}`, 'POST', {});
            this.notify('success', 'AI draft approved for staff use.');
            await this.loadAiDraftsForApplication(applicationId);
        } catch (error) {
            this.notify('error', error.message || 'Unable to approve AI draft');
        }
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
            const payload = await this.fetchApplicationSnapshot(applicationId);
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

            const payload = await this.fetchApplicationSnapshot(applicationId);

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
                                <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none text-break" data-kw-document-preview data-application-id="${Number(this.currentApplicationId || this.currentVerificationApplicationId || 0)}" data-document-id="${Number(doc.id || 0)}" data-url="${this.escapeHtml(String(doc.file_url || doc.download_url || doc.document_path).replace(/^https?:\/\/[^/]+/i, ""))}" data-label="${this.escapeHtml(this.formatLabel(doc.document_type || 'Document'))}">
                                    <i class="bi bi-eye me-1"></i>View inside system
                                </button>
                                ${doc.document_type === 'passport_photo' ? `
                                    <div><img src="${this.escapeHtml(window.KingswayFileLifecycle?.resolveUrl?.(doc.file_url || doc.download_url || doc.document_path))}" alt="Passport photo" class="rounded border mt-2" style="width:72px;height:88px;object-fit:cover;" onerror="this.onerror=null;this.src=KingswayFileLifecycle.avatarUrl()"></div>
                                ` : ''}
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
            const payload = await this.fetchApplicationSnapshot(applicationId);
            const documents = Array.isArray(payload.documents) ? payload.documents : [];

            if (documents.length === 0) {
                this.showWorkspaceChildModal(
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

            this.showWorkspaceChildModal(
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
                                                <button type="button" class="btn btn-link btn-sm p-0" data-kw-document-preview data-application-id="${Number(this.currentApplicationId || this.currentVerificationApplicationId || 0)}" data-document-id="${Number(doc.id || 0)}" data-url="${this.escapeHtml(String(doc.file_url || doc.download_url || doc.document_path).replace(/^https?:\/\/[^/]+/i, ""))}" data-label="${this.escapeHtml(this.formatLabel(doc.document_type || 'Document'))}">
                                                    View inside system
                                                </button>
                                            </div>
                                        ` : ""}
                                        <textarea class="form-control form-control-sm mt-2" id="document-verification-notes-${Number(doc.id)}" rows="2" placeholder="Verification note or missing correction requested"></textarea>
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
            const notes = String(document.getElementById(`document-verification-notes-${numericDocumentId}`)?.value || '').trim();
            await this.apiCall("/admission/verify-document", "POST", {
                document_id: documentId,
                status,
                notes: notes || (status === "verified" ? "Verified from admissions workspace" : "Rejected from admissions workspace")
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
        let applicationResponse;
        try {
            [response, applicationResponse] = await Promise.all([
                this.apiCall('/admission/interview-sessions', 'GET'),
                this.fetchApplicationSnapshot(applicationId)
            ]);
        } catch (error) {
            this.notify('error', error.message || 'Unable to load interview scheduling details');
            return;
        }
        const payload = response?.data ?? response ?? {};
        const sessions = Array.isArray(payload) ? payload : (payload.sessions || []);
        const available = sessions.filter((session) => ['scheduled', 'full'].includes(session.status) && Number(session.assigned_count || 0) < Number(session.capacity || 0));
        const applicationPayload = applicationResponse?.data ?? applicationResponse ?? {};
        const application = applicationPayload.application || {};
        const learningAreas = applicationPayload.interview_learning_areas || [];
        this.showWorkspaceModal(
            '<i class="bi bi-calendar-plus me-2"></i>Schedule Interview',
            `
                <form id="workspaceScheduleInterviewForm" class="row g-3">
                    <input type="hidden" name="application_id" value="${Number(applicationId)}">
                    ${this.renderWorkflowContext(applicationPayload, 'Interview scheduling context')}
                    <div class="col-12"><label class="form-label fw-semibold">Interview session <span class="text-danger">*</span></label><select name="session_id" class="form-select" required><option value="">Select an existing session</option>${available.map((session) => `<option value="${Number(session.id)}">${this.escapeHtml(session.window_label || '')} · ${this.escapeHtml(session.session_date)} ${this.escapeHtml(String(session.start_time).slice(0,5))}–${this.escapeHtml(String(session.end_time).slice(0,5))} · ${this.escapeHtml(session.venue || '')} · ${Number(session.assigned_count || 0)}/${Number(session.capacity || 0)}</option>`).join('')}</select>${available.length ? '<div class="form-text">The selected session supplies the interview date, time, venue and interviewer.</div>' : '<div class="form-text text-danger">No available sessions exist. Create an interview session for this intake first.</div>'}</div>
                    <div class="col-12"><label class="form-label fw-semibold" for="workspaceInterviewLearningAreas">Learning areas / subjects to be tested <span class="text-danger">*</span></label><select id="workspaceInterviewLearningAreas" name="learning_area_ids[]" class="form-select" multiple size="6" required aria-multiselectable="true">${learningAreas.length ? learningAreas.map((area) => `<option value="${Number(area.id)}">${this.escapeHtml(area.name || '')}</option>`).join('') : '<option disabled>No curriculum areas configured for the applicant\'s current class</option>'}</select><div class="form-text">Select 1–3 learning areas from the applicant’s current class curriculum. Click an area to select or deselect it.</div></div>
                </form>
            `,
            `
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-primary" onclick="admissionsWorkspaceController.manageInterviewSessions()"><i class="bi bi-calendar2-week me-1"></i>Manage sessions</button>
                <button type="submit" form="workspaceScheduleInterviewForm" class="btn btn-primary">Schedule</button>
            `
        );

        const learningAreaSelect = document.getElementById('workspaceInterviewLearningAreas');
        learningAreaSelect?.addEventListener('mousedown', (event) => {
            const option = event.target.closest('option');
            if (!option || option.disabled) return;
            event.preventDefault();
            if (!option.selected && learningAreaSelect.selectedOptions.length >= 3) {
                this.notify('error', 'Select at most three learning areas.');
                return;
            }
            option.selected = !option.selected;
        });

        document.getElementById("workspaceScheduleInterviewForm")?.addEventListener("submit", (event) => {
            event.preventDefault();
            const submitButton = document.querySelector('button[form="workspaceScheduleInterviewForm"]');
            if (submitButton?.disabled) return;
            const formData = new FormData(event.currentTarget);
            const learningAreaIds = [...(learningAreaSelect?.selectedOptions || [])]
                .map((option) => Number(option.value))
                .filter(Boolean);
            if (learningAreaIds.length < 1 || learningAreaIds.length > 3) {
                this.notify('error', 'Select between one and three learning areas from the applicant\'s current class.');
                return;
            }
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Scheduling…';
            }
            this.runAdmissionAction(
                this.apiCall("/admission/schedule-interview", "POST", {
                    application_id: Number(formData.get('application_id')),
                    session_id: Number(formData.get('session_id')),
                    learning_area_ids: learningAreaIds,
                    reason: 'Interview session selected by admissions staff'
                }),
                "Interview scheduled successfully"
            ).finally(() => {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Schedule';
                }
            });
        });
    },

    conductInterview: async function(applicationId) {
        let app = {};
        let assessmentItems = [];
        let snapshotPayload = {};
        try {
            snapshotPayload = await this.fetchApplicationSnapshot(applicationId);
            app = snapshotPayload.application || {};
            assessmentItems = snapshotPayload.assessment_items || snapshotPayload.interview_learning_areas || [];
        } catch (error) {
            this.notify("error", error.message || "Unable to load interview assessment details");
            return;
        }
        const rows = assessmentItems.length ? assessmentItems : [{ learning_area_name: "Interview assessment", max_score: 100 }];
        this.showWorkspaceModal(
            '<i class="bi bi-clipboard-check me-2"></i>Record Interview Assessment',
            `
                <form id="workspaceInterviewResultForm" class="row g-3">
                    <input type="hidden" name="application_id" value="${Number(applicationId)}">
                    ${this.renderWorkflowContext(snapshotPayload, 'Interview assessment context')}
                    <div class="col-12"><h6 class="fw-semibold mb-0">Assessment Scores (0-100)</h6></div>
                    ${rows.map(item => `<div class="col-md-6"><label class="form-label">${this.escapeHtml(item.learning_area_name || item.name || 'Assessment')}</label><input type="number" class="form-control workspace-assessment-score" min="0" max="${Number(item.max_score || 100)}" data-learning-area-id="${this.escapeHtml(item.learning_area_id || item.id || '')}" data-learning-area-name="${this.escapeHtml(item.learning_area_name || item.name || 'Assessment')}" placeholder="0-${Number(item.max_score || 100)}" required></div>`).join('')}
                    <div class="col-12 d-flex justify-content-between border-top pt-3"><strong>Overall Score:</strong><span id="workspaceInterviewOverallScore" class="fw-bold">—</span></div>
                    <div class="col-12">
                        <label class="form-label">Recommendation <span class="text-danger">*</span></label>
                        <select name="recommendation" class="form-select" required>
                            <option value="">Select recommendation...</option>
                            <option value="recommended">Recommended for admission</option>
                            <option value="conditional">Conditional / waitlist</option>
                            <option value="placement_test_required">Placement test required</option>
                            <option value="not_recommended">Not recommended</option>
                        </select>
                        <div class="form-text">The system derives the next workflow stage from this recommendation.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Interview Notes</label>
                        <textarea name="remarks" class="form-control" rows="3"></textarea>
                    </div>
                    <div id="workspaceInterviewResultError" class="col-12 alert alert-danger d-none mb-0"></div>
                </form>
            `,
            `
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="workspaceInterviewResultForm" class="btn btn-info">Save Results</button>
            `
        );

        document.getElementById("workspaceInterviewResultForm")?.addEventListener("submit", (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const items = [...form.querySelectorAll('.workspace-assessment-score')].map(input => ({
                learning_area_id: Number(input.dataset.learningAreaId || 0) || null,
                learning_area_name: input.dataset.learningAreaName || 'Assessment',
                max_score: Number(input.max || 100),
                score: Number(input.value)
            }));
            const error = form.querySelector('#workspaceInterviewResultError');
            if (!items.length || items.some(item => !Number.isFinite(item.score) || item.score < 0 || item.score > item.max_score) || !form.elements.recommendation.value) {
                if (error) { error.textContent = 'Select a recommendation and enter a valid score for every tested learning area.'; error.classList.remove('d-none'); }
                return;
            }
            const data = {
                application_id: applicationId,
                assessment_data: {
                    assessment_items: items,
                    score: Math.round(items.reduce((sum, item) => sum + item.score, 0) / items.length),
                    recommendation: form.elements.recommendation.value,
                    remarks: form.elements.remarks.value
                }
            };
            this.runAdmissionAction(
                this.apiCall("/admission/record-interview-results", "POST", data),
                "Interview assessment recorded"
            );
        });
        document.querySelectorAll('#workspaceInterviewResultForm .workspace-assessment-score').forEach(input => input.addEventListener('input', () => {
            const values = [...document.querySelectorAll('#workspaceInterviewResultForm .workspace-assessment-score')].map(item => Number(item.value)).filter(Number.isFinite);
            const target = document.getElementById('workspaceInterviewOverallScore');
            if (target) target.textContent = values.length ? `${Math.round(values.reduce((sum, value) => sum + value, 0) / values.length)}/100` : '—';
        }));
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

    recordPayment: async function(applicationId, registrationFee = 0) {
        let application = this.getAllQueueApplications().find(row => Number(row.id) === Number(applicationId)) || {};
        if (!window.AdmissionPaymentModal) return this.notify('error', 'The standard admissions payment modal is unavailable. Please reload the page.');

        // Queue data may be served from a short-lived cache and older queue
        // rows may not contain the charge field. Re-resolve the application
        // before opening any payment modal so the canonical existing-parent
        // amount is never replaced by a zero fallback.
        try {
            const snapshot = await this.fetchApplicationSnapshot(applicationId);
            const detail = snapshot?.application || {};
            application = { ...application, ...detail };
        } catch (error) {
            console.warn('Could not refresh admission payment amount:', error);
        }

        const resolvedFee = Number(application.registration_fee_due) > 0
            ? Number(application.registration_fee_due)
            : Number(registrationFee) > 0
                ? Number(registrationFee)
                : 0;
        if (resolvedFee <= 0) {
            const relief = application.financial_relief || {};
            const hasApprovedRelief = relief.registration_fee_waived
                || ['full', 'percentage', 'fixed'].includes(String(relief.school_fee_waiver_type || ''));
            if (hasApprovedRelief) {
                return this.notify('info', 'No admission balance is due because approved financial relief covers the current obligation. No payment transaction is required.');
            }
            if (application.admission_fee_configured === false) {
                return this.notify('error', 'No admission fee has been configured for this academic year and intake. Accounts must configure the charge before payment can be recorded.');
            }
            return this.notify('info', 'There is currently no admission balance due for this application. No payment transaction was found or created.');
        }

        window.AdmissionPaymentModal.open({ application: { ...application, id: applicationId }, registrationFee: resolvedFee, apiCall: this.apiCall.bind(this), onSuccess: () => this.loadQueueData() });
    },

    verifyPayment: async function(applicationId) {
        let application;
        try {
            const snapshot = await this.fetchApplicationSnapshot(applicationId);
            application = snapshot?.application || {};
        } catch (error) {
            this.notify('error', error.message || 'Unable to refresh payment state');
            return;
        }
        const paymentId = Number(application.pending_payment_id || 0);
        if (!paymentId) {
            this.notify('error', 'No pending payment verification was found. Refresh the queue.');
            return;
        }
        const paymentMethod = String(application.pending_payment_method || '').toLowerCase();
        const isMpesa = paymentMethod === 'mpesa';
        const verificationSource = isMpesa ? 'mpesa_reconciliation' : 'kcb_statement';
        const verificationLabel = isMpesa ? 'Verified M-Pesa callback/reconciliation' : 'Matched imported KCB statement';
        const relief = application.financial_relief || {};
        const registrationReliefType = String(relief.registration_fee_waiver_type || (relief.registration_fee_waived ? 'full' : 'none'));
        const schoolReliefType = String(relief.school_fee_waiver_type || 'none');
        this.showWorkspaceModal(
            '<i class="bi bi-shield-check me-2"></i>Verify Payment',
            `<form id="paymentVerificationForm" class="row g-3">
                <div class="col-12">${this.renderWorkflowContext({ application, stage_contract: { current_stage_name: this.formatLabel(application.current_stage || '') } }, 'Payment verification context')}<div class="alert alert-info small mb-2">The system will match <strong>${this.escapeHtml(application.pending_payment_reference || '')}</strong> against the official ${isMpesa ? 'M-Pesa callback/reconciliation record' : 'imported KCB statement record'} using the exact reference, amount, school account and transaction status. A receipt or note alone cannot confirm money received.</div><div class="alert alert-warning small mb-0"><strong>Until a match is found:</strong> this payment remains pending, the learner balance is unchanged, no fee ledger entry is posted, and the application cannot advance from Fees Payment.</div></div>
                <div class="col-12"><details class="border rounded-3 p-3" id="verifyPaymentFinancialReliefDetails"><summary class="fw-semibold"><i class="bi bi-shield-check me-1"></i>Authorize fee relief instead of payment</summary><div class="small text-muted mt-2">Use this only when the school has approved sponsorship or an exemption. Relief is recorded as an authorized concession; it is never represented as money received.</div><div class="row g-3 mt-1"><div class="col-md-6"><label class="form-label">Registration fee</label><select id="verifyRegistrationReliefType" class="form-select"><option value="none" ${registrationReliefType === 'none' ? 'selected' : ''}>Keep registration fee due</option><option value="full" ${registrationReliefType === 'full' ? 'selected' : ''}>Waive registration fee completely</option><option value="percentage" ${registrationReliefType === 'percentage' ? 'selected' : ''}>Waive a percentage</option><option value="fixed" ${registrationReliefType === 'fixed' ? 'selected' : ''}>Waive a fixed KES amount</option></select></div><div class="col-md-6"><label class="form-label">School fees</label><select id="verifySchoolReliefType" class="form-select"><option value="none" ${schoolReliefType === 'none' ? 'selected' : ''}>No school-fee relief</option><option value="percentage" ${schoolReliefType === 'percentage' ? 'selected' : ''}>Waive a percentage</option><option value="fixed" ${schoolReliefType === 'fixed' ? 'selected' : ''}>Waive a fixed KES amount</option><option value="full" ${schoolReliefType === 'full' ? 'selected' : ''}>Waive all school fees</option></select></div><div class="col-md-6"><label class="form-label">Registration relief value</label><input id="verifyRegistrationReliefValue" type="number" min="0" step="0.01" class="form-control" value="${this.escapeHtml(relief.registration_fee_waiver_value || '')}" placeholder="Percentage or KES amount"><div class="form-text">Required for percentage or fixed relief.</div></div><div class="col-md-6"><label class="form-label">School-fee relief value</label><input id="verifySchoolReliefValue" type="number" min="0" step="0.01" class="form-control" value="${this.escapeHtml(relief.school_fee_waiver_value || '')}" placeholder="Percentage or KES amount"><div class="form-text">For example, enter 50 for half of school fees.</div></div><div class="col-md-6"><label class="form-label">Exception type</label><select id="verifyReliefReasonCode" class="form-select"><option value="sponsored">Sponsored learner</option><option value="exempted">Exempted</option><option value="special_case">Special case</option><option value="administrative_exception">Administrative exception</option><option value="other">Other</option></select></div><div class="col-12"><label class="form-label">Authorization reason <span class="text-danger">*</span></label><textarea id="verifyReliefReason" class="form-control" rows="2" placeholder="Record who authorized the concession and why."></textarea></div><div class="col-12 d-flex justify-content-end"><button type="button" class="btn btn-outline-success" id="saveVerifyPaymentRelief"><i class="bi bi-shield-check me-1"></i>Save and apply approved relief</button></div></div></details></div>
                <div class="col-md-6"><label class="form-label">Verification source</label><select name="verification_source" class="form-select" required><option value="${verificationSource}">${verificationLabel}</option></select></div>
                <div class="col-12"><label class="form-label">Verification note</label><textarea name="verification_notes" class="form-control" rows="2" placeholder="Optional statement batch, reconciliation date, or finance review note"></textarea></div>
                <div class="col-12"><div id="paymentVerificationResult" class="alert d-none mb-0" role="alert" aria-live="polite"></div></div>
            </form>`,
            '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" form="paymentVerificationForm" class="btn btn-success">Confirm matched payment</button>'
        );
        document.getElementById('saveVerifyPaymentRelief')?.addEventListener('click', async (event) => {
            const registrationType = document.getElementById('verifyRegistrationReliefType')?.value || 'none';
            const schoolType = document.getElementById('verifySchoolReliefType')?.value || 'none';
            const registrationValue = Number(document.getElementById('verifyRegistrationReliefValue')?.value || 0);
            const schoolValue = Number(document.getElementById('verifySchoolReliefValue')?.value || 0);
            const reason = String(document.getElementById('verifyReliefReason')?.value || '').trim();
            const result = document.getElementById('paymentVerificationResult');
            if (registrationType === 'none' && schoolType === 'none') return this.notify('error', 'Select registration-fee or school-fee relief first.');
            if (!reason) return this.notify('error', 'Enter the authorization reason for the relief.');
            if ((registrationType === 'percentage' && registrationValue > 100) || (schoolType === 'percentage' && schoolValue > 100)) return this.notify('error', 'Percentage relief cannot exceed 100.');
            const button = event.currentTarget;
            button.disabled = true;
            try {
                const response = await this.apiCall('/admission/apply-financial-relief', 'POST', {
                    application_id: applicationId,
                    registration_fee_waived: registrationType !== 'none' ? 1 : 0,
                    registration_fee_waiver_type: registrationType,
                    registration_fee_waiver_value: registrationValue,
                    school_fee_waiver_type: schoolType,
                    school_fee_waiver_value: schoolValue,
                    reason_code: document.getElementById('verifyReliefReasonCode')?.value || 'sponsored',
                    reason
                });
                if (result) {
                    result.className = 'alert alert-success mb-0';
                    result.textContent = 'Approved relief was saved and applied. The concession is not a payment. Refreshing the workflow context…';
                }
                this.notify('success', response?.advanced_to_id_generation
                    ? 'Financial relief applied. No payment was required, so the application advanced to ID Generation.'
                    : 'Financial relief saved and applied.');
                await this.loadQueueData();
                setTimeout(() => this.closeWorkspaceModal(), 700);
            } catch (error) {
                this.notify('error', error.message || 'Unable to save financial relief.');
                button.disabled = false;
            }
        });
        document.getElementById('paymentVerificationForm')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.currentTarget;
            const submitButton = document.querySelector('button[type="submit"][form="paymentVerificationForm"]');
            const result = document.getElementById('paymentVerificationResult');
            const data = Object.fromEntries(new FormData(event.currentTarget));
            if (submitButton) submitButton.disabled = true;
            if (result) {
                result.className = 'alert alert-info mb-0';
                result.textContent = 'Checking the official reconciliation record…';
            }
            try {
                await this.apiCall('/admission/confirm-fee-payment', 'POST', { application_id: applicationId, payment_id: paymentId, ...data });
                if (result) {
                    result.className = 'alert alert-success mb-0';
                    result.textContent = 'Matched successfully. The payment was verified, posted to the fee ledger, and the workflow may advance.';
                }
                this.notify('success', 'Payment verified successfully');
                await this.loadQueueData();
                setTimeout(() => this.closeWorkspaceModal(), 700);
            } catch (error) {
                if (result) {
                    result.className = 'alert alert-danger mb-0';
                    result.textContent = `${error.message || 'No official payment match was found.'} No ledger entry was posted and the application remains at Fees Payment.`;
                }
                this.notify('error', error.message || 'Payment could not be verified');
                if (submitButton) submitButton.disabled = false;
            }
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
            const snapshot = await this.fetchApplicationSnapshot(applicationId);
            const application = snapshot?.application || { id: applicationId };
            window.AdmissionPlacementModal.open({
                application,
                classes,
                apiCall: this.apiCall.bind(this),
                onSuccess: () => this.loadQueueData()
            });
        } catch (error) {
            this.notify('error', error.message || 'Unable to load class streams');
        }
    },

    finalApproval: async function(applicationId) {
        let application = {};
        let workflowData = {};
        let snapshotPayload = {};
        try {
            snapshotPayload = await this.fetchApplicationSnapshot(applicationId);
            application = snapshotPayload?.application || {};
            workflowData = this.parseJsonSafe(snapshotPayload.workflow_data || application.workflow_data_json || application.data_json || {});
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
                    ${this.renderWorkflowContext(snapshotPayload, 'Final enrollment context')}
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
            const payload = await this.fetchApplicationSnapshot(applicationId);
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
            const payload = await this.fetchApplicationSnapshot(applicationId);
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
                    ${this.renderWorkflowContext(payload, 'Student ID generation context')}
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
