/**
 * Class Placement Controller
 * Handles class capacity management and student placement assignments
 */
const classPlacementController = {
    classes: [],
    placements: [],
    currentTab: 'classes',
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
                    console.warn("classPlacementController: Not authenticated, redirecting to login");
                    window.location.href = `${window.APP_BASE || ""}/index.php`;
                    return;
                }
            } else {
                console.warn("classPlacementController: AuthContext not available");
            }

            this.cacheDom();
            this.setupEventListeners();
            await this.loadClasses();
            await this.loadPlacements();

        } catch (error) {
            console.error("Failed to initialize Class Placement Controller:", error);
            this.showError("classesGrid", error.message || "Failed to initialize class placement page.");
            this.showError("placementsTableBody", error.message || "Failed to initialize class placement page.");
            this.showError("capacityGrid", error.message || "Failed to initialize class placement page.");
        }
    },

    apiCall: function(endpoint, method = "GET", data = null, params = {}) {
        const queryParams =
            params && typeof params === "object" && !Array.isArray(params) ? params : {};

        if (window.API && typeof window.API.callAPI === "function") {
            return window.API.callAPI(endpoint, method, data, queryParams);
        }

        if (window.API && typeof window.API.apiCall === "function") {
            return window.API.apiCall(endpoint, method, data, queryParams);
        }

        throw new Error("API helper not available. Expected window.API.callAPI or window.API.apiCall.");
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

    cacheDom: function() {
        this.dom = {
            classesGrid: document.getElementById("classesGrid"),
            placementsTableBody: document.getElementById("placementsTableBody"),
            capacityGrid: document.getElementById("capacityGrid"),
            placementTabs: document.getElementById("placementTabs"),
            classesTab: document.getElementById("classesTab"),
            placementsTab: document.getElementById("placementsTab"),
            capacityTab: document.getElementById("capacityTab"),
            projectionYear: document.getElementById("projectionYear"),
            projectionClass: document.getElementById("projectionClass"),
            projectionTerm: document.getElementById("projectionTerm"),
            projectionStream: document.getElementById("projectionStream"),
            projectionResult: document.getElementById("projectionResult"),
            projectionResolutionBadge: document.getElementById("projectionResolutionBadge"),
            btnProjectCapacity: document.getElementById("btnProjectCapacity"),
            editPlacementModal: document.getElementById("editPlacementModal"),
            editPlacementForm: document.getElementById("editPlacementForm"),
            editPlacementApplicationId: document.getElementById("editPlacementApplicationId"),
            editPlacementApplicant: document.getElementById("editPlacementApplicant"),
            editPlacementStream: document.getElementById("editPlacementStream"),
            editPlacementRemarks: document.getElementById("editPlacementRemarks"),
        };
    },
    
    loadClasses: async function() {
        try {
            const result = await this.apiCall('/admission/placement-classes', 'GET');
            this.classes = result?.classes || [];

            this.renderClassesGrid();
            this.updateCapacityCards();
        } catch (error) {
            console.error('Failed to load classes:', error);
            this.showError('classesGrid', 'Failed to load classes');
        }
    },
    
    loadPlacements: async function() {
        try {
            const result = await this.apiCall('/admission/queues', 'GET');
            const queues = result?.queues || {};
            const placementApplications = new Map();

            // Get applications from placement and payment queues
            if (queues.placement_pending && Array.isArray(queues.placement_pending)) {
                queues.placement_pending.forEach(app => {
                    app._placementQueue = 'placement_pending';
                    placementApplications.set(String(app.id), app);
                });
            }
            if (queues.payment_pending && Array.isArray(queues.payment_pending)) {
                queues.payment_pending.forEach(app => {
                    const key = String(app.id);
                    const existing = placementApplications.get(key);
                    if (!existing) {
                        app._placementQueue = 'payment_pending';
                        placementApplications.set(key, app);
                        return;
                    }

                    // Retain the placement queue/action but merge payment
                    // totals and verification state from the payment queue.
                    placementApplications.set(key, {
                        ...existing,
                        ...app,
                        _placementQueue: existing._placementQueue
                    });
                });
            }

            this.placements = [...placementApplications.values()];
            this.renderPlacementsTable();
        } catch (error) {
            console.error('Failed to load placements:', error);
            this.showError('placementsTableBody', 'Failed to load placements');
        }
    },
    
    renderClassesGrid: function() {
        const grid = document.getElementById('classesGrid');
        const classSummaries = this.getClassSummaries();
        
        if (classSummaries.length === 0) {
            grid.innerHTML = `
                <div class="col-12 text-center py-4">
                    <div class="text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        No classes found
                    </div>
                </div>
            `;
            return;
        }
        
        grid.innerHTML = classSummaries.map(cls => {
            const capacity = cls.capacity;
            const studentCount = cls.student_count;
            const percentage = capacity > 0 ? Math.round((studentCount / capacity) * 100) : 0;
            
            let capacityColor = 'bg-success';
            if (percentage >= 90) capacityColor = 'bg-danger';
            else if (percentage >= 75) capacityColor = 'bg-warning';
            
            return `
                <div class="col-md-6 col-lg-4">
                    <div class="card capacity-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h6 class="mb-1">${this.escapeHtml(cls.name || '—')}</h6>
                                    <small class="text-muted">${cls.streams.map(stream => `<span class="badge bg-light text-dark me-1">${this.escapeHtml(stream.name)}</span>`).join('')}</small>
                                </div>
                                <span class="badge ${capacityColor}">${percentage}%</span>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <small class="text-muted">Students:</small>
                                    <small class="fw-semibold">${studentCount}/${capacity}</small>
                                </div>
                                <div class="capacity-bar">
                                    <div class="capacity-fill ${capacityColor}" style="width: ${percentage}%"></div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Available:</small>
                                <small class="fw-bold text-success">${capacity - studentCount}</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    },

    getClassSummaries: function() {
        const grouped = new Map();
        this.classes.forEach(row => {
            const key = String(row.id);
            if (!grouped.has(key)) {
                grouped.set(key, {
                    id: row.id,
                    name: row.name,
                    capacity: 0,
                    student_count: 0,
                    streams: []
                });
            }
            const summary = grouped.get(key);
            const streamKey = String(row.academic_year_class_stream_id || `${row.id}-${row.stream_id}`);
            if (!summary.streams.some(stream => stream.key === streamKey)) {
                summary.streams.push({
                    key: streamKey,
                    id: row.stream_id,
                    name: row.stream_name || 'Unnamed stream',
                    capacity: Number(row.capacity || 0),
                    student_count: Number(row.student_count || 0)
                });
                summary.capacity += Number(row.capacity || 0);
                summary.student_count += Number(row.student_count || 0);
            }
            return summary;
        });
        return [...grouped.values()].sort((a, b) => String(a.name).localeCompare(String(b.name)));
    },
    
    renderCapacityGrid: function() {
        const grid = document.getElementById('capacityGrid');
        
        if (this.classes.length === 0) {
            grid.innerHTML = `
                <div class="col-12 text-center py-4">
                    <div class="text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        No capacity data available
                    </div>
                </div>
            `;
            return;
        }
        
        // Sort by capacity percentage
        const sortedClasses = [...this.classes].sort((a, b) => {
            const aPct = a.capacity > 0 ? (a.student_count / a.capacity) * 100 : 0;
            const bPct = b.capacity > 0 ? (b.student_count / b.capacity) * 100 : 0;
            return bPct - aPct;
        });
        
        grid.innerHTML = sortedClasses.map(cls => {
            const capacity = cls.capacity || 30;
            const studentCount = cls.student_count || 0;
            const percentage = capacity > 0 ? Math.round((studentCount / capacity) * 100) : 0;
            
            let capacityColor = 'bg-success';
            if (percentage >= 90) capacityColor = 'bg-danger';
            else if (percentage >= 75) capacityColor = 'bg-warning';
            
            return `
                <div class="col-md-6 col-lg-4">
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">${cls.name || '—'}</h6>
                                <span class="badge ${capacityColor}">${percentage}%</span>
                            </div>
                            <div class="capacity-bar mb-2">
                                <div class="capacity-fill ${capacityColor}" style="width: ${percentage}%"></div>
                            </div>
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">${studentCount} students</small>
                                <small class="text-muted">${capacity - studentCount} available</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    },
    
    renderPlacementsTable: function() {
        const tbody = document.getElementById('placementsTableBody');
        
        if (this.placements.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4">
                        <div class="text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            No placements found
                        </div>
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = this.placements.map(app => {
            const assignedClass = this.extractAssignedClass(app);
            const stream = this.extractStream(app);
            const statusBadge = `${this.getPlacementStatusBadge(app.status)} ${this.getAdmissionPaymentStatusBadge(app)}`;
            
            return `
                <tr>
                    <td><strong>${app.application_no || '—'}</strong></td>
                    <td>${app.applicant_name || 'Unknown'}</td>
                    <td>${app.grade_applying_for || '—'}</td>
                    <td>${app.admission_number || '—'}</td>
                    <td>${assignedClass || '—'}</td>
                    <td>${stream || '—'}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            ${app._placementQueue === 'placement_pending' ? `
                                <button class="btn btn-outline-info" onclick="classPlacementController.editPlacement(${app.id})" title="Assign class stream">
                                    <i class="bi bi-pencil"></i> Assign stream
                                </button>
                            ` : `
                                <span class="text-muted small">Placement completed — awaiting payment</span>
                            `}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    },
    
    extractAssignedClass: function(app) {
        if (app.assigned_class_name) return app.assigned_class_name;
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
    
    extractStream: function(app) {
        if (app.assigned_stream_name) return app.assigned_stream_name;
        if (app.data_json) {
            try {
                const data = JSON.parse(app.data_json);
                return data.stream || '—';
            } catch (e) {
                console.error('Failed to parse placement data:', e);
            }
        }
        return '—';
    },
    
    getPlacementStatusBadge: function(status) {
        const badges = {
            'placement_offered': '<span class="badge bg-primary">Placement Offered</span>',
            'fees_pending': '<span class="badge bg-warning">Fees Pending</span>',
            'enrolled': '<span class="badge bg-success">Enrolled</span>'
        };
        return badges[status] || '<span class="badge bg-secondary">' + status + '</span>';
    },

    getAdmissionPaymentStatusBadge: function(app) {
        const recorded = Number(app.recorded_payment_amount || 0);
        const due = Number(app.registration_fee_due || 0);

        if ((due <= 0 && recorded > 0) || (due > 0 && recorded >= due)) {
            return '<span class="badge bg-success">Paid</span>';
        }
        if (app.pending_payment_id) {
            return '<span class="badge bg-info text-dark">Verification pending</span>';
        }
        if (recorded > 0) {
            return `<span class="badge bg-warning text-dark">Partially paid (KES ${recorded.toLocaleString()})</span>`;
        }
        return '<span class="badge bg-secondary">Not paid</span>';
    },
    
    updateCapacityCards: function() {
        const classSummaries = this.getClassSummaries();
        const totalClasses = classSummaries.length;
        const totalStudents = classSummaries.reduce((sum, cls) => sum + cls.student_count, 0);
        const totalCapacity = classSummaries.reduce((sum, cls) => sum + cls.capacity, 0);
        const avgCapacity = totalCapacity > 0 ? Math.round((totalStudents / totalCapacity) * 100) : 0;
        const pendingPlacement = this.placements.filter(app => app.status === 'placement_offered').length;
        
        document.getElementById('statTotalClasses').textContent = totalClasses;
        document.getElementById('statTotalStudents').textContent = totalStudents;
        document.getElementById('statPendingPlacement').textContent = pendingPlacement;
        document.getElementById('statAvgCapacity').textContent = avgCapacity + '%';
    },
    
    switchTab: function(tabName) {
        this.currentTab = tabName;
        
        // Update tab buttons
        document.querySelectorAll('#placementTabs .nav-link').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.tab === tabName) {
                btn.classList.add('active');
            }
        });
        
        // Show/hide tab content
        document.getElementById('classesTab').style.display = tabName === 'classes' ? 'block' : 'none';
        document.getElementById('placementsTab').style.display = tabName === 'placements' ? 'block' : 'none';
        document.getElementById('capacityTab').style.display = tabName === 'capacity' ? 'block' : 'none';

        if (tabName === 'capacity' && this.dom.projectionYear) {
            this.loadProjectionOptions();
        }
    },

    setupEventListeners: function() {
        // Edit placement form submission
        const editForm = document.getElementById('editPlacementForm');
        if (editForm) {
            editForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.updatePlacement();
            });
        }

        // Populate stream options when the target class changes.
        if (this.dom.projectionClass) {
            this.dom.projectionClass.addEventListener('change', () => {
                this.loadStreamOptions();
            });
        }
    },

    // ---- Admission Stage 5: period-aware, cohort-aware capacity projection ----
    loadProjectionOptions: async function() {
        if (this._projectionOptionsLoaded) return;
        try {
            const years = await this.apiCall("/academic/years/list", "GET");
            if (years) {
                this.dom.projectionYear.innerHTML =
                    '<option value="">Select Year</option>' +
                    (Array.isArray(years) ? years : years.data || []).map(y =>
                        `<option value="${y.id}">${this.escapeHtml(y.year_name || y.year_code || y.id)}</option>`
                    ).join("");
            }
            const classes = await this.apiCall("/academic/classes-list", "GET");
            if (classes) {
                this.dom.projectionClass.innerHTML =
                    '<option value="">Select Class</option>' +
                    (Array.isArray(classes) ? classes : classes.data || []).map(c =>
                        `<option value="${c.id}">${this.escapeHtml(c.name || c.id)}</option>`
                    ).join("");
            }
            this._projectionOptionsLoaded = true;
        } catch (err) {
            console.warn("Could not load projection options:", err);
        }
    },

    loadStreamOptions: async function() {
        const classId = this.dom.projectionClass.value;
        if (!this.dom.projectionStream) return;
        this.dom.projectionStream.innerHTML = '<option value="">Auto</option>';
        if (!classId) return;
        try {
            const res = await this.apiCall(
                `/academic/streams-list?class_id=${classId}`,
                "GET"
            );
            if (res) {
                (Array.isArray(res) ? res : res.data || []).forEach(s => {
                    this.dom.projectionStream.insertAdjacentHTML(
                        "beforeend",
                        `<option value="${s.id}">${this.escapeHtml(s.stream_name || s.name || s.id)}</option>`
                    );
                });
            }
        } catch (err) {
            console.warn("Could not load stream options:", err);
        }
    },

    projectCapacity: async function() {
        const yearId = this.dom.projectionYear.value;
        const classId = this.dom.projectionClass.value;
        if (!yearId || !classId) {
            this.renderProjectionError("Select an academic year and target class.");
            return;
        }

        this.dom.btnProjectCapacity.disabled = true;
        this.dom.btnProjectCapacity.innerHTML =
            '<span class="spinner-border spinner-border-sm"></span> Projecting...';
        this.dom.projectionResult.innerHTML = "";
        if (this.dom.projectionResolutionBadge)
            this.dom.projectionResolutionBadge.style.display = "none";

        const params = {
            target_academic_year_id: yearId,
            target_class_id: classId,
        };
        if (this.dom.projectionTerm.value) params.target_term_id = this.dom.projectionTerm.value;
        if (this.dom.projectionStream.value) params.target_stream_id = this.dom.projectionStream.value;

        try {
            const res = await this.apiCall("/academic/cohort-capacity", "GET", null, params);
            if (!res) {
                this.renderProjectionError("Projection failed.");
                return;
            }
            this.renderProjection(res);
        } catch (err) {
            this.renderProjectionError(err.message || "Projection request failed.");
        } finally {
            this.dom.btnProjectCapacity.disabled = false;
            this.dom.btnProjectCapacity.innerHTML =
                '<i class="bi bi-lightbulb me-1"></i>Project';
        }
    },

    renderProjection: function(d) {
        const statusMap = {
            available: "success",
            limited: "warning",
            full: "danger",
            over_capacity: "danger",
            setup_required: "secondary",
            projected_available: "info",
            projected_limited: "warning",
            projected_full: "danger",
            projected_over_capacity: "danger",
        };
        const status = d.capacity_status || "unknown";
        const cls = statusMap[status] || "secondary";

        if (this.dom.projectionResolutionBadge) {
            this.dom.projectionResolutionBadge.style.display = "inline-block";
            this.dom.projectionResolutionBadge.textContent =
                (d.resolution || "").replace(/_/g, " ");
        }

        const occupancy = d.projected_occupancy ?? d.current_enrollment ?? d.enrolled ?? 0;
        const capacity = d.capacity ?? 0;
        const spaces = d.spaces_available ?? (capacity - occupancy);
        const pct = capacity > 0 ? Math.round((occupancy / capacity) * 100) : 0;
        const confidence = d.confidence || "—";
        const sourceCohort = d.source_cohort_name || d.source_class_name || "—";
        const warnings = Array.isArray(d.warnings) ? d.warnings : [];

        let warningHtml = "";
        if (warnings.length) {
            warningHtml = `<div class="alert alert-warning small mt-3 mb-0">` +
                warnings.map(w => `<div>• ${this.escapeHtml(w)}</div>`).join("") +
                `</div>`;
        }

        this.dom.projectionResult.innerHTML = `
            <div class="alert alert-${cls} d-flex justify-content-between align-items-center mb-2">
                <div>
                    <strong>${this.escapeHtml((d.target_class_name || "Class") + " — " + (d.target_academic_year || ""))}</strong>
                    <div class="small">Status: ${this.escapeHtml(status.replace(/_/g, " "))}</div>
                </div>
                <span class="badge bg-light text-dark">confidence: ${this.escapeHtml(confidence)}</span>
            </div>
            <div class="progress mb-2" style="height:10px;">
                <div class="progress-bar bg-${cls}" style="width:${pct}%"></div>
            </div>
            <div class="row g-2 small">
                <div class="col-6 col-md-3"><strong>Capacity:</strong> ${capacity}</div>
                <div class="col-6 col-md-3"><strong>Projected occupancy:</strong> ${occupancy}</div>
                <div class="col-6 col-md-3"><strong>Spaces available:</strong> ${spaces}</div>
                <div class="col-6 col-md-3"><strong>Source cohort:</strong> ${this.escapeHtml(sourceCohort)}</div>
            </div>
            ${warningHtml}`;
    },

    renderProjectionError: function(msg) {
        if (this.dom.projectionResolutionBadge)
            this.dom.projectionResolutionBadge.style.display = "none";
        this.dom.projectionResult.innerHTML =
            `<div class="alert alert-danger small mb-0">${this.escapeHtml(msg)}</div>`;
    },

    editPlacement: function(applicationId) {
        const application = this.placements.find(app => app.id === applicationId);
        if (!application) {
            showNotification('error', 'Application not found');
            return;
        }

        if (!window.AdmissionPlacementModal) {
            showNotification('error', 'The standard placement modal is unavailable. Please reload the page.');
            return;
        }
        window.AdmissionPlacementModal.open({
            application,
            classes: this.classes,
            apiCall: this.apiCall.bind(this),
            onSuccess: () => this.refreshData()
        });
    },

    populatePlacementStreams: function(selectedStreamId = '') {
        const streamSelect = document.getElementById('editPlacementStream');
        if (!streamSelect) return;

        const streams = this.classes.filter(cls => cls.academic_year_class_stream_id && cls.stream_id);
        streamSelect.innerHTML = '<option value="">Select placement stream</option>';
        const seen = new Set();
        streams.forEach(cls => {
            const placementId = String(cls.academic_year_class_stream_id);
            if (seen.has(placementId)) return;
            seen.add(placementId);
            const option = document.createElement('option');
            option.value = placementId;
            option.dataset.classId = String(cls.id);
            option.dataset.streamId = String(cls.stream_id);
            option.textContent = `${cls.name || 'Class'} ${cls.stream_name || ''}`.trim();
            streamSelect.appendChild(option);
        });
        if (selectedStreamId) {
            const option = [...streamSelect.options].find(item => item.dataset.streamId === String(selectedStreamId));
            if (option) streamSelect.value = option.value;
        }
    },

    extractStreamId: function(app) {
        if (!app || !app.data_json) return '';
        try {
            const data = typeof app.data_json === 'string' ? JSON.parse(app.data_json) : app.data_json;
            return data.assigned_stream_id || data.stream_id || '';
        } catch (e) {
            return '';
        }
    },
    
    updatePlacement: function() {
        const applicationId = document.getElementById('editPlacementApplicationId').value;
        const submitBtn = document.querySelector('#editPlacementForm button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
        
        const streamSelect = document.getElementById('editPlacementStream');
        const selectedStream = streamSelect.options[streamSelect.selectedIndex];
        const classId = Number(selectedStream?.dataset.classId || 0);
        const streamId = Number(selectedStream?.dataset.streamId || 0);
        const placementData = {
            application_id: Number(applicationId),
            academic_year_class_stream_id: Number(selectedStream?.value || 0),
            class_id: classId,
            stream_id: streamId || null,
            remarks: document.getElementById('editPlacementRemarks').value
        };

        if (!classId || !streamId) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Update Placement';
            showNotification('error', 'Select a class before completing placement');
            return;
        }
        
        this.apiCall('/admission/complete-enrollment', 'POST', placementData)
            .then(response => {
                showNotification('success', 'Student placed successfully. Fee obligations and school records were updated.');
                bootstrap.Modal.getInstance(document.getElementById('editPlacementModal')).hide();
                document.getElementById('editPlacementForm').reset();
                this.loadPlacements();
            })
            .catch(error => {
                console.error('Failed to update placement:', error);
                showNotification('error', error?.message || 'Failed to update placement');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Update Placement';
            });
    },
    
    refreshData: function() {
        this.loadClasses();
        this.loadPlacements();
    },
    
    showError: function(elementId, message) {
        const element = document.getElementById(elementId);
        if (element) {
            element.innerHTML = `
                <div class="text-danger">
                    <i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>
                    ${message}
                </div>
            `;
        }
    }
};

window.classPlacementController = classPlacementController;

function initWhenAPIReady() {
    const hasApi =
        window.API &&
        (
            typeof window.API.callAPI === "function" ||
            typeof window.API.apiCall === "function"
        );

    if (hasApi) {
        window.classPlacementController.init();
        return;
    }

    setTimeout(initWhenAPIReady, 100);
}

document.addEventListener("DOMContentLoaded", function () {
    initWhenAPIReady();
});
