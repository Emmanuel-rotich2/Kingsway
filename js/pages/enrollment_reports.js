/**
 * Enrollment Reports Controller
 * Comprehensive admissions and enrollment reporting dashboard
 */

console.log("enrollment_reports.js loaded successfully");

const enrollmentReportsController = {
    applications: [],
    charts: {},
    initialized: false,
    dom: {},

    init: async function() {
        if (this.initialized) return;
        this.initialized = true;

        console.log("enrollmentReportsController: Initializing...");

        try {
            if (window.AuthContext && typeof window.AuthContext.isAuthenticated === "function") {
                if (!window.AuthContext.isAuthenticated()) {
                    console.warn("enrollmentReportsController: Not authenticated, redirecting to login");
                    window.location.href = `${window.APP_BASE || ""}/index.php`;
                    return;
                }
            } else {
                console.warn("enrollmentReportsController: AuthContext not available");
            }

            this.cacheDom();
            this.setupEventListeners();
            await this.loadReportData();

            console.log("enrollmentReportsController: Initialization complete");
        } catch (error) {
            console.error("Failed to initialize Enrollment Reports Controller:", error);
            this.showError("Failed to load enrollment reports");
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
        if (!response) return false;
        if (response.success === false || response.status === false) return false;
        if (response.success === true || response.status === true) return true;
        // Accept responses with queues, data, or any meaningful content
        return response.queues !== undefined || response.data !== undefined || Object.keys(response).length > 0;
    },

    unwrapPayload: function(response) {
        // Handle both response.data and direct response objects
        if (response && response.data) {
            return response.data;
        }
        // If response has queues directly, return the whole response
        if (response && response.queues) {
            return response;
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
            reportsTableBody: document.getElementById("reportsTableBody"),
            filterAcademicYear: document.getElementById("filterAcademicYear"),
            filterTerm: document.getElementById("filterTerm"),
            filterReportType: document.getElementById("filterReportType"),
            filterDateFrom: document.getElementById("filterDateFrom"),
            filterDateTo: document.getElementById("filterDateTo"),
            statusChart: document.getElementById("statusChart"),
            classChart: document.getElementById("classChart"),
            genderChart: document.getElementById("genderChart"),
            trendChart: document.getElementById("trendChart"),
            statTotalApplications: document.getElementById("statTotalApplications"),
            statApproved: document.getElementById("statApproved"),
            statWaitlisted: document.getElementById("statWaitlisted"),
            statEnrolled: document.getElementById("statEnrolled"),
        };
    },
    
    loadReportData: async function() {
        if (this.dom.reportsTableBody) {
            this.dom.reportsTableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <div class="spinner-border text-info" role="status"></div>
                        <div class="mt-2 text-muted">Loading report data...</div>
                    </td>
                </tr>
            `;
        }

        try {
            const response = await this.apiCall('/admission/queues', 'GET');
            console.log("Enrollment reports response:", response);

            if (!this.isSuccessfulResponse(response)) {
                throw new Error(response?.message || "Failed to load report data.");
            }

            const payload = this.unwrapPayload(response);
            console.log("Payload:", payload);

            // Collect all applications from all queues
            const allApplications = [];
            const queues = payload?.queues || response?.queues || {};

            Object.keys(queues).forEach(queueName => {
                if (Array.isArray(queues[queueName])) {
                    queues[queueName].forEach(app => {
                        allApplications.push({
                            ...app,
                            queue_name: queueName
                        });
                    });
                }
            });

            this.applications = allApplications;
            console.log("Applications loaded:", this.applications);
            this.updateSummaryCards();
            this.renderReportsTable();
            this.initCharts();
        } catch (error) {
            console.error('Failed to load report data:', error);
            this.showError('Failed to load report data');
        }
    },
    
    updateSummaryCards: function() {
        const total = this.applications.length;
        const approved = this.applications.filter(app => app.status === 'placement_offered' || app.status === 'fees_pending').length;
        const waitlisted = this.applications.filter(app => app.status === 'waitlisted').length;
        const enrolled = this.applications.filter(app => app.status === 'enrolled').length;

        if (this.dom.statTotalApplications) this.dom.statTotalApplications.textContent = total;
        if (this.dom.statApproved) this.dom.statApproved.textContent = approved;
        if (this.dom.statWaitlisted) this.dom.statWaitlisted.textContent = waitlisted;
        if (this.dom.statEnrolled) this.dom.statEnrolled.textContent = enrolled;
    },
    
    renderReportsTable: function() {
        const tbody = this.dom.reportsTableBody;
        if (!tbody) return;

        if (this.applications.length === 0) {
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

        tbody.innerHTML = this.applications.map(app => {
            const statusBadge = this.getStatusBadge(app.status);

            return `
                <tr>
                    <td><strong>${app.application_no || '—'}</strong></td>
                    <td>${app.applicant_name || 'Unknown'}</td>
                    <td>${app.grade_applying_for || '—'}</td>
                    <td>${this.formatGender(app.gender)}</td>
                    <td>${statusBadge}</td>
                    <td>${this.formatDate(app.created_at)}</td>
                    <td><span class="badge bg-secondary">${app.current_stage || '—'}</span></td>
                </tr>
            `;
        }).join('');
    },
    
    getStatusBadge: function(status) {
        const badges = {
            'submitted': '<span class="badge bg-secondary">Submitted</span>',
            'documents_pending': '<span class="badge bg-warning">Documents Pending</span>',
            'documents_verified': '<span class="badge bg-info">Documents Verified</span>',
            'placement_offered': '<span class="badge bg-primary">Placement Offered</span>',
            'fees_pending': '<span class="badge bg-warning">Fees Pending</span>',
            'enrolled': '<span class="badge bg-success">Enrolled</span>',
            'cancelled': '<span class="badge bg-danger">Cancelled</span>'
        };
        return badges[status] || '<span class="badge bg-secondary">' + status + '</span>';
    },
    
    formatGender: function(gender) {
        const genders = {
            'male': 'Male',
            'female': 'Female',
            'other': 'Other'
        };
        return genders[gender] || gender || '—';
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
    
    initCharts: function() {
        // Check if Chart.js is available
        if (typeof Chart === 'undefined') {
            console.warn('Chart.js not available, skipping chart initialization');
            return;
        }
        
        this.destroyCharts();
        
        // Status Distribution Chart
        if (this.dom.statusChart) {
            const statusData = this.getStatusDistribution();
            this.charts.status = new Chart(this.dom.statusChart, {
                type: 'doughnut',
                data: {
                    labels: statusData.labels,
                    datasets: [{
                        data: statusData.values,
                        backgroundColor: [
                            '#6c757d',
                            '#ffc107',
                            '#17a2b8',
                            '#198754',
                            '#fd7e14',
                            '#0d6efd',
                            '#dc3545'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        // Class Distribution Chart
        if (this.dom.classChart) {
            const classData = this.getClassDistribution();
            this.charts.class = new Chart(this.dom.classChart, {
                type: 'bar',
                data: {
                    labels: classData.labels,
                    datasets: [{
                        label: 'Applications',
                        data: classData.values,
                        backgroundColor: '#0d6efd'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }
        
        // Gender Distribution Chart
        if (this.dom.genderChart) {
            const genderData = this.getGenderDistribution();
            this.charts.gender = new Chart(this.dom.genderChart, {
                type: 'pie',
                data: {
                    labels: genderData.labels,
                    datasets: [{
                        data: genderData.values,
                        backgroundColor: ['#0d6efd', '#e91e63', '#9c27b0']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        
        // Monthly Trend Chart
        if (this.dom.trendChart) {
            const trendData = this.getMonthlyTrend();
            this.charts.trend = new Chart(this.dom.trendChart, {
                type: 'line',
                data: {
                    labels: trendData.labels,
                    datasets: [{
                        label: 'Applications',
                        data: trendData.values,
                        borderColor: '#198754',
                        backgroundColor: 'rgba(25, 135, 84, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }
    },
    
    getStatusDistribution: function() {
        const statusCounts = {};
        this.applications.forEach(app => {
            const status = app.status || 'unknown';
            statusCounts[status] = (statusCounts[status] || 0) + 1;
        });
        
        return {
            labels: Object.keys(statusCounts),
            values: Object.values(statusCounts)
        };
    },
    
    getClassDistribution: function() {
        const classCounts = {};
        this.applications.forEach(app => {
            const grade = app.grade_applying_for || 'unknown';
            classCounts[grade] = (classCounts[grade] || 0) + 1;
        });
        
        // Sort by grade
        const sortedClasses = Object.keys(classCounts).sort();
        
        return {
            labels: sortedClasses,
            values: sortedClasses.map(cls => classCounts[cls])
        };
    },
    
    getGenderDistribution: function() {
        const genderCounts = { male: 0, female: 0, other: 0 };
        this.applications.forEach(app => {
            const gender = app.gender || 'other';
            if (genderCounts[gender] !== undefined) {
                genderCounts[gender]++;
            } else {
                genderCounts.other++;
            }
        });
        
        return {
            labels: ['Male', 'Female', 'Other'],
            values: [genderCounts.male, genderCounts.female, genderCounts.other]
        };
    },
    
    getMonthlyTrend: function() {
        const monthCounts = {};
        this.applications.forEach(app => {
            if (app.created_at) {
                const date = new Date(app.created_at);
                const monthKey = date.toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
                monthCounts[monthKey] = (monthCounts[monthKey] || 0) + 1;
            }
        });
        
        // Sort by date
        const sortedMonths = Object.keys(monthCounts).sort((a, b) => new Date(a) - new Date(b));
        
        return {
            labels: sortedMonths,
            values: sortedMonths.map(month => monthCounts[month])
        };
    },
    
    destroyCharts: function() {
        Object.values(this.charts).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
        this.charts = {};
    },
    
    setupEventListeners: function() {
        // Filter changes
        if (this.dom.filterAcademicYear) {
            this.dom.filterAcademicYear.addEventListener('change', () => this.applyFilters());
        }
        if (this.dom.filterTerm) {
            this.dom.filterTerm.addEventListener('change', () => this.applyFilters());
        }
        if (this.dom.filterReportType) {
            this.dom.filterReportType.addEventListener('change', () => this.applyFilters());
        }
        if (this.dom.filterDateFrom) {
            this.dom.filterDateFrom.addEventListener('change', () => this.applyFilters());
        }
        if (this.dom.filterDateTo) {
            this.dom.filterDateTo.addEventListener('change', () => this.applyFilters());
        }
    },
    
    applyFilters: function() {
        // Re-render data with filters applied
        this.renderReportsTable();
        this.initCharts();
    },
    
    refreshData: function() {
        this.loadReportData();
    },
    
    exportReport: function() {
        // Export functionality would be implemented here
        showNotification('info', 'Report export functionality would be implemented with dedicated API endpoint');
    },
    
    showError: function(message) {
        if (this.dom.reportsTableBody) {
            this.dom.reportsTableBody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <div class="text-danger">
                            <i class="bi bi-exclamation-triangle fs-1 d-block mb-2"></i>
                            ${message}
                        </div>
                    </td>
                </tr>
            `;
        }
    }
};

window.enrollmentReportsController = enrollmentReportsController;

function initWhenAPIReady() {
    const hasApi =
        window.API &&
        (
            typeof window.API.callAPI === "function" ||
            typeof window.API.apiCall === "function"
        );

    if (hasApi) {
        console.log("API is ready, initializing enrollment reports controller");
        window.enrollmentReportsController.init();
        return;
    }

    console.log("API not ready yet, waiting...");
    setTimeout(initWhenAPIReady, 100);
}

document.addEventListener("DOMContentLoaded", function () {
    console.log("DOM loaded, waiting for API to be ready");
    initWhenAPIReady();
});
