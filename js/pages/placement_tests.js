/**
 * Placement Tests Controller
 * Manage academic placement tests and assessments
 */

console.log("placement_tests.js loaded successfully");

const placementTestsController = {
    tests: [],
    filteredTests: [],
    learningAreas: [],
    initialized: false,
    dom: {},

    init: async function() {
        if (this.initialized) return;
        this.initialized = true;

        console.log("placementTestsController: Initializing...");

        try {
            if (window.AuthContext && typeof window.AuthContext.isAuthenticated === "function") {
                if (!window.AuthContext.isAuthenticated()) {
                    console.warn("placementTestsController: Not authenticated, redirecting to login");
                    window.location.href = `${window.APP_BASE || ""}/index.php`;
                    return;
                }
            } else {
                console.warn("placementTestsController: AuthContext not available");
            }

            this.cacheDom();
            this.setupEventListeners();
            await this.loadLearningAreas();
            await this.loadTests();

            console.log("placementTestsController: Initialization complete");
        } catch (error) {
            console.error("Failed to initialize Placement Tests Controller:", error);
            this.showError("Failed to load placement tests");
        }
    },

    apiCall: function(endpoint, method = "GET", data = null) {
        if (window.API && typeof window.API.callAPI === "function") {
            return window.API.callAPI(endpoint, method, data);
        }

        if (window.API && typeof window.API.apiCall === "function") {
            return window.API.apiCall(endpoint, method, data);
        }

        throw new Error("API helper not available. Expected window.API.callAPI or window.API.apiCall.");
    },

    isSuccessfulResponse: function(response) {
        // Accept responses with success === true OR responses with data
        return (response && response.success === true) || (response && (response.data || response.queues));
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
            testsGrid: document.getElementById("testsGrid"),
            filterTestStatus: document.getElementById("filterTestStatus"),
            filterSubject: document.getElementById("filterSubject"),
            subjectArea: document.getElementById("subjectArea"),
            searchTests: document.getElementById("searchTests"),
            createTestForm: document.getElementById("createTestForm"),
            recordResultsForm: document.getElementById("recordResultsForm"),
            statTotalTests: document.getElementById("statTotalTests"),
            statPending: document.getElementById("statPending"),
            statCompleted: document.getElementById("statCompleted"),
            statAvgScore: document.getElementById("statAvgScore"),
        };
    },

    setupEventListeners: function() {
        this.safeListen("filterTestStatus", "change", () => this.applyFilters());
        this.safeListen("filterSubject", "change", () => this.applyFilters());
        this.safeListen(
            "searchTests",
            "input",
            this.debounce(() => this.applyFilters(), 300),
        );
        this.safeListen("createPlacementTestBtn", "click", () => this.showCreateTestModal());

        if (this.dom.createTestForm) {
            this.dom.createTestForm.addEventListener("submit", (event) => {
                event.preventDefault();
                this.createTest(new FormData(this.dom.createTestForm));
            });
        }

        if (this.dom.recordResultsForm) {
            this.dom.recordResultsForm.addEventListener("submit", (event) => {
                event.preventDefault();
                this.submitResults(new FormData(this.dom.recordResultsForm));
            });
        }
    },

    safeListen: function (id, event, handler) {
        const element = document.getElementById(id);

        if (!element) {
            console.warn(`Missing element #${id}; listener skipped.`);
            return;
        }

        element.addEventListener(event, handler);
    },

    loadLearningAreas: async function() {
        try {
            const response = await this.apiCall("/academic/learning-areas/list", "GET");
            const learningAreas = this.extractList(response)
                .map((area) => ({
                    id: area.id,
                    code: area.code || area.subject_code || "",
                    name: area.name || area.subject_name || area.title || "",
                    status: area.status || "active",
                }))
                .filter((area) => area.name && String(area.status).toLowerCase() !== "inactive")
                .sort((a, b) => a.name.localeCompare(b.name));

            this.learningAreas = learningAreas;
            console.log("Learning areas loaded:", this.learningAreas);
            this.populateSubjectDropdowns();
        } catch (error) {
            console.error("Failed to load learning areas:", error);
            this.learningAreas = [];
            this.populateSubjectDropdowns();
            this.notify("error", "Failed to load learning areas");
        }
    },

    populateSubjectDropdowns: function() {
        const optionsHtml = this.learningAreas.length
            ? this.learningAreas.map((area) => {
                const value = this.escapeHtml(area.name);
                const code = area.code ? ` (${this.escapeHtml(area.code)})` : "";
                return `<option value="${value}">${this.escapeHtml(area.name)}${code}</option>`;
            }).join("")
            : '<option value="General">General Assessment</option>';

        if (this.dom.filterSubject) {
            this.dom.filterSubject.innerHTML = `
                <option value="">All Learning Areas</option>
                ${optionsHtml}
            `;
        }

        if (this.dom.subjectArea) {
            this.dom.subjectArea.innerHTML = optionsHtml;
        }
    },

    loadTests: async function() {
        if (this.dom.testsGrid) {
            this.dom.testsGrid.innerHTML = `
                <div class="col-12 text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="mt-2 text-muted">Loading placement tests...</div>
                </div>
            `;
        }

        try {
            // Load placement tests from the database
            // For now, we'll simulate this with admission applications that require placement tests
            const response = await this.apiCall('/admission/queues', 'GET');
            console.log("Placement tests response:", response);

            if (!this.isSuccessfulResponse(response)) {
                throw new Error(response?.message || "Failed to load placement tests.");
            }

            const payload = this.unwrapPayload(response);
            console.log("Payload:", payload);

            // Convert placement queue applications to test records
            const testRecords = [];
            const queues = payload?.queues || response?.queues || {};

            if (queues.placement_pending && Array.isArray(queues.placement_pending)) {
                queues.placement_pending.forEach(app => {
                    // Check if this application requires a placement test
                    let workflowData = {};
                    try {
                        workflowData = app.data_json ? JSON.parse(app.data_json) : {};
                    } catch (error) {
                        console.warn("Invalid placement test data_json for application:", app.id, error);
                        workflowData = {};
                    }

                    if (workflowData.placement_test_required || app.grade_applying_for === 'Grade2' || app.grade_applying_for === 'Grade3') {
                        testRecords.push({
                            id: app.id,
                            test_code: `PT-${app.application_no}`,
                            test_date: app.created_at ? app.created_at.split(' ')[0] : null,
                            subject_area: 'General',
                            applicant_name: app.applicant_name,
                            application_no: app.application_no,
                            grade_applying_for: app.grade_applying_for,
                            score: workflowData.placement_test_score || null,
                            max_score: 100,
                            recommendation: workflowData.placement_recommendation || null,
                            status: workflowData.placement_test_score ? 'completed' : 'pending'
                        });
                    }
                });
            }

            this.tests = testRecords;
            console.log("Placement tests loaded:", this.tests);
            this.applyFilters();
            this.updateSummaryCards();
        } catch (error) {
            console.error('Failed to load placement tests:', error);
            this.showError('Failed to load placement tests');
        }
    },

    updateSummaryCards: function() {
        const total = this.tests.length;
        const pending = this.tests.filter(t => t.status === 'pending').length;
        const completed = this.tests.filter(t => t.status === 'completed').length;
        const completedTests = this.tests.filter(t => t.status === 'completed' && t.score !== null);
        const avgScore = completedTests.length > 0
            ? Math.round(completedTests.reduce((sum, t) => sum + (t.score || 0), 0) / completedTests.length)
            : 0;

        if (this.dom.statTotalTests) this.dom.statTotalTests.textContent = total;
        if (this.dom.statPending) this.dom.statPending.textContent = pending;
        if (this.dom.statCompleted) this.dom.statCompleted.textContent = completed;
        if (this.dom.statAvgScore) this.dom.statAvgScore.textContent = avgScore + '%';
    },

    applyFilters: function() {
        const statusFilter = this.dom.filterTestStatus ? this.dom.filterTestStatus.value : '';
        const subjectFilter = this.dom.filterSubject ? this.dom.filterSubject.value : '';
        const searchTerm = this.dom.searchTests ? this.dom.searchTests.value.toLowerCase() : '';

        this.filteredTests = this.tests.filter(test => {
            if (statusFilter && test.status !== statusFilter) return false;
            if (subjectFilter && test.subject_area !== subjectFilter) return false;
            if (searchTerm) {
                const searchStr = `${test.test_code} ${test.applicant_name} ${test.application_no}`.toLowerCase();
                if (!searchStr.includes(searchTerm)) return false;
            }
            return true;
        });

        this.renderTestsGrid();
    },

    renderTestsGrid: function() {
        if (!this.dom.testsGrid) return;

        if (this.filteredTests.length === 0) {
            this.dom.testsGrid.innerHTML = `
                <div class="col-12 text-center py-4">
                    <div class="text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        No placement tests found
                    </div>
                </div>
            `;
            return;
        }

        this.dom.testsGrid.innerHTML = this.filteredTests.map(test => `
            <div class="col-md-6 col-lg-4">
                <div class="test-card card mb-3 ${test.status}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="card-title mb-1">${this.escapeHtml(test.test_code)}</h6>
                                <small class="text-muted">${this.escapeHtml(test.applicant_name)}</small>
                            </div>
                            <span class="badge ${this.getStatusBadgeClass(test.status)}">${this.escapeHtml(test.status)}</span>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">
                                <i class="bi bi-person me-1"></i>${this.escapeHtml(test.application_no)}
                            </small>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">
                                <i class="bi bi-book me-1"></i>${this.escapeHtml(test.grade_applying_for)}
                            </small>
                        </div>
                        ${test.score !== null ? `
                            <div class="mb-2">
                                <small class="text-muted">
                                    <i class="bi bi-graph-up me-1"></i>Score: ${test.score}/${test.max_score}
                                </small>
                            </div>
                        ` : ''}
                        <div class="d-flex gap-2 mt-3">
                            ${test.status === 'pending' ? `
                                <button class="btn btn-sm btn-primary flex-grow-1" onclick="window.placementTestsController.showRecordResultsModal(${test.id})">
                                    <i class="bi bi-clipboard-check me-1"></i>Record Results
                                </button>
                            ` : `
                                <button class="btn btn-sm btn-outline-secondary flex-grow-1" onclick="window.placementTestsController.viewTestDetails(${test.id})">
                                    <i class="bi bi-eye me-1"></i>View Details
                                </button>
                            `}
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    },

    getStatusBadgeClass: function(status) {
        const classes = {
            'scheduled': 'bg-info',
            'completed': 'bg-success',
            'pending': 'bg-warning'
        };
        return classes[status] || 'bg-secondary';
    },

    showCreateTestModal: function() {
        const modal = document.getElementById('createTestModal');
        if (modal) {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }
    },

    createTest: async function(formData) {
        const submitBtn = document.querySelector('#createTestForm button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating...';
        }

        try {
            const data = {
                test_name: formData.get('testName'),
                test_date: formData.get('testDate'),
                subject_area: formData.get('subjectArea'),
                max_score: document.getElementById("createMaxScore")?.value || 100,
            };

            // TODO: Implement actual API endpoint
            // const response = await this.apiCall('/admission/placement-test', 'POST', data);

            this.notify("warning", "Placement test creation requires additional API endpoint");

            const modal = document.getElementById('createTestModal');
            if (modal) {
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) bsModal.hide();
            }

            if (this.dom.createTestForm) {
                this.dom.createTestForm.reset();
            }
        } catch (error) {
            console.error('Failed to create test:', error);
            this.notify("error", "Failed to create placement test");
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-plus-circle me-1"></i>Create Test';
            }
        }
    },

    showRecordResultsModal: function(testId) {
        const test = this.tests.find(t => t.id === testId);
        if (!test) {
            this.notify("error", "Test not found");
            return;
        }

        // Populate modal with test details
        document.getElementById('recordTestId').value = test.id;
        document.getElementById("resultMaxScore").value = test.max_score || 100;

        const summaryDiv = document.getElementById('testSummary');
        if (summaryDiv) {
            summaryDiv.innerHTML = `
                <strong>Test:</strong> ${this.escapeHtml(test.test_code)}<br>
                <strong>Applicant:</strong> ${this.escapeHtml(test.applicant_name)}<br>
                <strong>Grade:</strong> ${this.escapeHtml(test.grade_applying_for)}
            `;
        }

        const modal = document.getElementById('recordResultsModal');
        if (modal) {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }
    },

    submitResults: async function(formData) {
        const submitBtn = document.querySelector('#recordResultsForm button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
        }

        try {
            const testId = formData.get('recordTestId');
            const score = parseFloat(formData.get('scoreObtained'));
            const maxScore = parseFloat(document.getElementById("resultMaxScore")?.value) || 100;
            const percentage = Math.round((score / maxScore) * 100);

            const data = {
                test_id: testId,
                score: score,
                percentage: percentage,
                recommendation: formData.get('testRecommendation'),
                recommended_class: formData.get('recommendedClass'),
            };

            // TODO: Implement actual API endpoint
            // const response = await this.apiCall(`/admission/placement-test/${testId}/record-results`, 'POST', data);

            this.notify("warning", "Placement test results recording requires additional API endpoint");

            const modal = document.getElementById('recordResultsModal');
            if (modal) {
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) bsModal.hide();
            }

            if (this.dom.recordResultsForm) {
                this.dom.recordResultsForm.reset();
            }

            await this.loadTests();
        } catch (error) {
            console.error('Failed to submit results:', error);
            this.notify("error", "Failed to record test results");
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Record Results';
            }
        }
    },

    viewTestDetails: function(testId) {
        const test = this.tests.find(t => t.id === testId);
        if (!test) {
            this.notify("error", "Test not found");
            return;
        }

        // For now, just show an alert. In production, this would open a details modal
        alert(`Test Details:\n\nCode: ${test.test_code}\nApplicant: ${test.applicant_name}\nGrade: ${test.grade_applying_for}\nScore: ${test.score}/${test.max_score}\nStatus: ${test.status}\nRecommendation: ${test.recommendation || 'N/A'}`);
    },

    showError: function(message) {
        if (this.dom.testsGrid) {
            this.dom.testsGrid.innerHTML = `
                <div class="col-12 text-center py-4">
                    <div class="text-danger">
                        <i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>
                        ${message}
                    </div>
                </div>
            `;
        }
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

window.placementTestsController = placementTestsController;

function initWhenAPIReady() {
    const hasApi =
        window.API &&
        (
            typeof window.API.callAPI === "function" ||
            typeof window.API.apiCall === "function"
        );

    if (hasApi) {
        console.log("API is ready, initializing placement tests controller");
        window.placementTestsController.init();
        return;
    }

    console.log("API not ready yet, waiting...");
    setTimeout(initWhenAPIReady, 100);
}

document.addEventListener("DOMContentLoaded", function () {
    console.log("DOM loaded, waiting for API to be ready");
    initWhenAPIReady();
});
