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

        console.log("classPlacementController: Initializing...");

        try {
            if (window.AuthContext && typeof window.AuthContext.isAuthenticated === "function") {
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

            console.log("classPlacementController: Initialization complete");
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

    isSuccessfulResponse: function(response) {
        // Accept responses with success === true OR responses with data
        return (response && response.success === true) || (response && (response.data || response.classes || response.queues));
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
            editPlacementClass: document.getElementById("editPlacementClass"),
            editPlacementStream: document.getElementById("editPlacementStream"),
            editPlacementRemarks: document.getElementById("editPlacementRemarks"),
        };
    },
    
    loadClasses: async function() {
        try {
            const response = await this.apiCall('/admission/placement-classes', 'GET');
            console.log("Class placement response:", response);

            if (!this.isSuccessfulResponse(response)) {
                throw new Error(response?.message || "Failed to load classes.");
            }

            const payload = this.unwrapPayload(response);
            // Handle both response.data.classes and response.classes
            this.classes = payload?.classes || response?.classes || [];
            console.log("Classes loaded:", this.classes);

            this.renderClassesGrid();
            this.renderCapacityGrid();
            this.updateCapacityCards();
        } catch (error) {
            console.error('Failed to load classes:', error);
            this.showError('classesGrid', 'Failed to load classes');
        }
    },
    
    loadPlacements: async function() {
        try {
            const response = await this.apiCall('/admission/queues', 'GET');
            console.log("Placements response:", response);

            if (!this.isSuccessfulResponse(response)) {
                throw new Error(response?.message || "Failed to load placements.");
            }

            const payload = this.unwrapPayload(response);
            const placementApplications = [];
            const queues = payload?.queues || response?.queues || {};

            // Get applications from placement and payment queues
            if (queues.placement_pending && Array.isArray(queues.placement_pending)) {
                queues.placement_pending.forEach(app => {
                    placementApplications.push(app);
                });
            }
            if (queues.payment_pending && Array.isArray(queues.payment_pending)) {
                queues.payment_pending.forEach(app => {
                    placementApplications.push(app);
                });
            }

            this.placements = placementApplications;
            console.log("Placements loaded:", this.placements);
            this.renderPlacementsTable();
        } catch (error) {
            console.error('Failed to load placements:', error);
            this.showError('placementsTableBody', 'Failed to load placements');
        }
    },
    
    renderClassesGrid: function() {
        const grid = document.getElementById('classesGrid');
        
        if (this.classes.length === 0) {
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
        
        grid.innerHTML = this.classes.map(cls => {
            const capacity = cls.capacity || 30;
            const studentCount = cls.student_count || 0;
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
                                    <h6 class="mb-1">${cls.name || '—'}</h6>
                                    <small class="text-muted">ID: ${cls.id}</small>
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
                    <td colspan="7" class="text-center py-4">
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
            const statusBadge = this.getPlacementStatusBadge(app.status);
            
            return `
                <tr>
                    <td><strong>${app.application_no || '—'}</strong></td>
                    <td>${app.applicant_name || 'Unknown'}</td>
                    <td>${app.grade_applying_for || '—'}</td>
                    <td>${assignedClass || '—'}</td>
                    <td>${stream || '—'}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-info" onclick="classPlacementController.editPlacement(${app.id})" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
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
    
    extractStream: function(app) {
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
    
    updateCapacityCards: function() {
        const totalClasses = this.classes.length;
        const totalStudents = this.classes.reduce((sum, cls) => sum + (cls.student_count || 0), 0);
        const totalCapacity = this.classes.reduce((sum, cls) => sum + (cls.capacity || 0), 0);
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
            if (years && years.success) {
                this.dom.projectionYear.innerHTML =
                    '<option value="">Select Year</option>' +
                    (years.data || []).map(y =>
                        `<option value="${y.id}">${this.escapeHtml(y.year_name || y.year_code || y.id)}</option>`
                    ).join("");
            }
            const classes = await this.apiCall("/academic/classes-list", "GET");
            if (classes && classes.success) {
                this.dom.projectionClass.innerHTML =
                    '<option value="">Select Class</option>' +
                    (classes.data || []).map(c =>
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
            if (res && res.success) {
                (res.data || []).forEach(s => {
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
            if (!res || !res.success) {
                this.renderProjectionError(res && res.message ? res.message : "Projection failed.");
                return;
            }
            this.renderProjection(res.data || res);
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
        
        document.getElementById('editPlacementApplicationId').value = applicationId;
        document.getElementById('editPlacementApplicant').value = application.applicant_name || 'Unknown';
        
        // Populate class dropdown
        const classSelect = document.getElementById('editPlacementClass');
        classSelect.innerHTML = '<option value="">Select Class</option>';
        this.classes.forEach(cls => {
            const option = document.createElement('option');
            option.value = cls.id;
            option.textContent = cls.name;
            classSelect.appendChild(option);
        });
        
        // Set current class if available
        const assignedClass = this.extractAssignedClass(application);
        if (assignedClass && assignedClass !== '—') {
            for (let i = 0; i < classSelect.options.length; i++) {
                if (classSelect.options[i].text === assignedClass) {
                    classSelect.selectedIndex = i;
                    break;
                }
            }
        }
        
        const modal = new bootstrap.Modal(document.getElementById('editPlacementModal'));
        modal.show();
    },
    
    updatePlacement: function() {
        const applicationId = document.getElementById('editPlacementApplicationId').value;
        const submitBtn = document.querySelector('#editPlacementForm button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
        
        const placementData = {
            assigned_class_id: document.getElementById('editPlacementClass').value,
            stream: document.getElementById('editPlacementStream').value,
            remarks: document.getElementById('editPlacementRemarks').value
        };
        
        this.apiCall('/admission/generate-placement-offer', 'POST', {
            application_id: applicationId,
            ...placementData
        })
            .then(response => {
                if (response.success) {
                    showNotification('success', 'Placement updated successfully');
                    bootstrap.Modal.getInstance(document.getElementById('editPlacementModal')).hide();
                    document.getElementById('editPlacementForm').reset();
                    this.loadPlacements();
                } else {
                    showNotification('error', response.message || 'Failed to update placement');
                }
            })
            .catch(error => {
                console.error('Failed to update placement:', error);
                showNotification('error', 'Failed to update placement');
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
        console.log("API is ready, initializing class placement controller");
        window.classPlacementController.init();
        return;
    }

    console.log("API not ready yet, waiting...");
    setTimeout(initWhenAPIReady, 100);
}

document.addEventListener("DOMContentLoaded", function () {
    console.log("DOM loaded, waiting for API to be ready");
    initWhenAPIReady();
});
