/**
 * Student ID Cards Controller
 * Complete ID card management system
 */

console.log("student_id_cards.js loaded successfully");

const StudentIdCardsController = {
    students: [],
    selectedStudents: new Set(),
    currentPreviewStudentId: null,
    metadata: {
        academicYears: [],
        classes: [],
        streams: [],
        schoolProfile: {},
        permissions: {}
    },
    initialized: false,
    dom: {},

    init: async function() {
        if (this.initialized) return;
        this.initialized = true;

        console.log("StudentIdCardsController: Initializing...");

        try {
            if (window.AuthContext && typeof window.AuthContext.isAuthenticated === "function") {
                if (!window.AuthContext.isAuthenticated()) {
                    console.warn("StudentIdCardsController: Not authenticated, redirecting to login");
                    window.location.href = `${window.APP_BASE || ""}/index.php`;
                    return;
                }
            } else {
                console.warn("StudentIdCardsController: AuthContext not available");
            }

            this.cacheDom();
            this.setupEventListeners();
            await this.loadMetadata();
            await this.loadStudents();

            console.log("StudentIdCardsController: Initialization complete");
        } catch (error) {
            console.error("Failed to initialize Student ID Cards Controller:", error);
            this.showError(error.message || "Failed to initialize ID cards page.");
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
        return response.data !== undefined || response.queues !== undefined || Object.keys(response).length > 0;
    },

    unwrapPayload: function(response) {
        if (response && response.data) {
            return response.data;
        }
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

    escapeAttr: function(text) {
        return this.escapeHtml(text).replace(/`/g, "&#96;");
    },

    cacheDom: function() {
        this.dom = {
            refreshBtn: document.getElementById("refreshBtn"),
            generateSelectedBtn: document.getElementById("generateSelectedBtn"),
            printSelectedBtn: document.getElementById("printSelectedBtn"),
            exportBtn: document.getElementById("exportBtn"),

            filterAcademicYear: document.getElementById("filterAcademicYear"),
            filterClass: document.getElementById("filterClass"),
            filterStream: document.getElementById("filterStream"),
            filterGender: document.getElementById("filterGender"),
            filterStudentStatus: document.getElementById("filterStudentStatus"),
            filterCardStatus: document.getElementById("filterCardStatus"),
            searchStudents: document.getElementById("searchStudents"),
            applyFiltersBtn: document.getElementById("applyFiltersBtn"),
            resetFiltersBtn: document.getElementById("resetFiltersBtn"),

            selectAll: document.getElementById("selectAll"),
            headerCheckbox: document.getElementById("headerCheckbox"),
            tableBody: document.getElementById("tableBody"),

            loadingState: document.getElementById("loadingState"),
            errorState: document.getElementById("errorState"),
            forbiddenState: document.getElementById("forbiddenState"),

            // Summary cards
            statTotalStudents: document.getElementById("statTotalStudents"),
            statWithIDs: document.getElementById("statWithIDs"),
            statWithoutIDs: document.getElementById("statWithoutIDs"),
            statPrinted: document.getElementById("statPrinted"),
            statIssued: document.getElementById("statIssued"),
            statLost: document.getElementById("statLost"),
            statExpired: document.getElementById("statExpired"),
            statReplaced: document.getElementById("statReplaced"),

            // Modals
            previewModal: document.getElementById("previewModal"),
            generateModal: document.getElementById("generateModal"),
            renewModal: document.getElementById("renewModal"),
            replaceModal: document.getElementById("replaceModal"),
            issueModal: document.getElementById("issueModal"),
            historyModal: document.getElementById("historyModal"),

            // Preview
            cardFrontPreview: document.getElementById("cardFrontPreview"),
            cardBackPreview: document.getElementById("cardBackPreview"),

            // Forms
            generateForm: document.getElementById("generateForm"),
            renewForm: document.getElementById("renewForm"),
            replaceForm: document.getElementById("replaceForm"),
            issueForm: document.getElementById("issueForm"),

            // History
            historyContent: document.getElementById("historyContent"),
        };
    },

    setupEventListeners: function() {
        this.safeListen("refreshBtn", "click", () => this.loadStudents());
        this.safeListen("generateSelectedBtn", "click", () => this.generateSelected());
        this.safeListen("printSelectedBtn", "click", () => this.printSelected());
        this.safeListen("exportBtn", "click", () => this.exportData());

        this.safeListen("applyFiltersBtn", "click", () => this.loadStudents());
        this.safeListen("resetFiltersBtn", "click", () => this.resetFilters());

        this.safeListen("filterClass", "change", () => {
            this.updateStreamsFilter();
            this.loadStudents();
        });

        this.safeListen("filterAcademicYear", "change", () => this.loadStudents());
        this.safeListen("filterStream", "change", () => this.loadStudents());
        this.safeListen("filterGender", "change", () => this.loadStudents());
        this.safeListen("filterStudentStatus", "change", () => this.loadStudents());
        this.safeListen("filterCardStatus", "change", () => this.loadStudents());

        this.safeListen("searchStudents", "input", this.debounce(() => this.loadStudents(), 400));

        this.safeListen("selectAll", "change", (e) => this.toggleSelectAll(e.target.checked));
        this.safeListen("headerCheckbox", "change", (e) => this.toggleSelectAll(e.target.checked));

        // Form submissions
        if (this.dom.generateForm) {
            this.dom.generateForm.addEventListener("submit", (e) => {
                e.preventDefault();
                this.generateCard(new FormData(this.dom.generateForm));
            });
        }

        if (this.dom.renewForm) {
            this.dom.renewForm.addEventListener("submit", (e) => {
                e.preventDefault();
                this.renewCard(new FormData(this.dom.renewForm));
            });
        }

        if (this.dom.replaceForm) {
            this.dom.replaceForm.addEventListener("submit", (e) => {
                e.preventDefault();
                this.replaceCard(new FormData(this.dom.replaceForm));
            });
        }

        if (this.dom.issueForm) {
            this.dom.issueForm.addEventListener("submit", (e) => {
                e.preventDefault();
                this.markIssued(new FormData(this.dom.issueForm));
            });
        }

        // Preview modal buttons
        this.safeListen("previewGenerateBtn", "click", () => this.showGenerateModal());
        this.safeListen("previewGenerateQRBtn", "click", () => this.generateQRCode());
        this.safeListen("previewPrintBtn", "click", () => this.printSingleCard());
        this.safeListen("previewDownloadBtn", "click", () => this.downloadSingleCard());
        this.safeListen("previewMarkPrintedBtn", "click", () => this.markPrinted());
        this.safeListen("previewMarkIssuedBtn", "click", () => this.showIssueModal());
        this.safeListen("previewRenewBtn", "click", () => this.showRenewModal());
        this.safeListen("previewReplaceBtn", "click", () => this.showReplaceModal());
    },

    safeListen: function(id, event, handler) {
        const element = document.getElementById(id);
        if (!element) {
            console.warn(`Missing element #${id}; listener skipped.`);
            return;
        }
        element.addEventListener(event, handler);
    },

    loadMetadata: async function() {
        try {
            const response = await this.apiCall('/students/id-card-meta', 'GET');
            console.log("ID card metadata response:", response);

            if (!this.isSuccessfulResponse(response)) {
                throw new Error(response?.message || "Failed to load metadata.");
            }

            const data = this.unwrapPayload(response);
            this.metadata.academicYears = data.academic_years || [];
            this.metadata.classes = data.classes || [];
            this.metadata.streams = data.streams || [];
            this.metadata.schoolProfile = data.school_profile || {};
            this.metadata.permissions = data.permissions || {};

            this.populateSelect(this.dom.filterAcademicYear, this.metadata.academicYears, "All Years");
            this.populateSelect(this.dom.filterClass, this.metadata.classes, "All Classes");
            this.updateStreamsFilter();
        } catch (error) {
            console.error('Failed to load metadata:', error);
            this.notify("error", "Failed to load metadata");
        }
    },

    loadStudents: async function() {
        this.setLoading(true);
        this.dom.errorState.classList.add("d-none");
        this.dom.forbiddenState.classList.add("d-none");

        try {
            const params = this.getFilterParams();
            const response = await this.apiCall(`/students/id-cards?${params.toString()}`, 'GET');
            console.log("Students response:", response);

            if (response.success === false) {
                if (response.message && response.message.includes("permission")) {
                    this.dom.forbiddenState.classList.remove("d-none");
                    this.dom.tableBody.innerHTML = '';
                    return;
                }
                throw new Error(response?.message || "Failed to load students");
            }

            if (!this.isSuccessfulResponse(response)) {
                throw new Error(response?.message || "Failed to load students.");
            }

            const students = this.extractList(response);
            this.students = students;
            this.renderSummary();
            this.renderTable();
        } catch (error) {
            console.error('Failed to load students:', error);
            this.dom.errorState.textContent = error.message || "Failed to load students";
            this.dom.errorState.classList.remove("d-none");
            this.dom.tableBody.innerHTML = '';
        } finally {
            this.setLoading(false);
        }
    },

    getFilterParams: function() {
        const params = new URLSearchParams();

        const filters = {
            academic_year: this.dom.filterAcademicYear?.value || "",
            class_id: this.dom.filterClass?.value || "",
            stream_id: this.dom.filterStream?.value || "",
            gender: this.dom.filterGender?.value || "",
            student_status: this.dom.filterStudentStatus?.value || "",
            card_status: this.dom.filterCardStatus?.value || "",
            search: this.dom.searchStudents?.value.trim() || "",
        };

        Object.entries(filters).forEach(([key, val]) => {
            if (val !== "") params.set(key, val);
        });

        return params;
    },

    updateStreamsFilter: function() {
        const classId = this.dom.filterClass?.value || "";
        const filtered = classId
            ? this.metadata.streams.filter(s => String(s.class_id) === String(classId))
            : this.metadata.streams;
        this.populateSelect(this.dom.filterStream, filtered, "All Streams");
    },

    populateSelect: function(select, items, placeholder) {
        if (!select) return;
        select.innerHTML = `<option value="">${placeholder}</option>`;
        (items || []).forEach(item => {
            const option = document.createElement("option");
            option.value = item.id ?? item.year ?? item.year_code ?? item.value ?? "";
            option.textContent = item.name || item.class_name || item.stream_name || item.year_name || item.year_code || item.label || option.value;
            select.appendChild(option);
        });
    },

    renderSummary: function() {
        const summary = this.calculateSummary();

        if (this.dom.statTotalStudents) this.dom.statTotalStudents.textContent = summary.total;
        if (this.dom.statWithIDs) this.dom.statWithIDs.textContent = summary.withIDs;
        if (this.dom.statWithoutIDs) this.dom.statWithoutIDs.textContent = summary.withoutIDs;
        if (this.dom.statPrinted) this.dom.statPrinted.textContent = summary.printed;
        if (this.dom.statIssued) this.dom.statIssued.textContent = summary.issued;
        if (this.dom.statLost) this.dom.statLost.textContent = summary.lost;
        if (this.dom.statExpired) this.dom.statExpired.textContent = summary.expired;
        if (this.dom.statReplaced) this.dom.statReplaced.textContent = summary.replaced;
    },

    calculateSummary: function() {
        return {
            total: this.students.length,
            withIDs: this.students.filter(s => s.card_status && s.card_status !== 'not_generated').length,
            withoutIDs: this.students.filter(s => !s.card_status || s.card_status === 'not_generated').length,
            printed: this.students.filter(s => s.card_status === 'printed').length,
            issued: this.students.filter(s => s.card_status === 'issued').length,
            lost: this.students.filter(s => s.card_status === 'lost').length,
            expired: this.students.filter(s => s.card_status === 'expired').length,
            replaced: this.students.filter(s => s.card_status === 'replaced').length,
        };
    },

    getFullName: function(student) {
        const names = [student.first_name, student.middle_name, student.last_name].filter(n => n);
        return names.join(' ') || 'Unknown';
    },

    renderTable: function() {
        if (!this.students.length) {
            this.dom.tableBody.innerHTML = `
                <tr>
                    <td colspan="15" class="text-center py-4">
                        <div class="text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            No students found
                        </div>
                    </td>
                </tr>`;
            return;
        }

        this.dom.tableBody.innerHTML = this.students.map(student => {
            const studentId = student.id || student.student_id;
            const fullName = this.getFullName(student);
            const statusBadge = this.getStatusBadge(student.card_status);
            const qrBadge = this.getQRBadge(student.qr_token);
            const actions = this.getRowActions(student);

            return `
                <tr>
                    <td><input type="checkbox" class="student-checkbox" data-id="${studentId}"></td>
                    <td><img src="${student.photo_url || KingswayFileLifecycle.assetUrl('students', 'avatar.jpg')}" class="rounded" style="width:40px;height:40px;object-fit:cover;"></td>
                    <td><strong>${this.escapeHtml(student.admission_no || '—')}</strong></td>
                    <td>${this.escapeHtml(fullName)}</td>
                    <td>${this.escapeHtml(student.class_name || '—')}</td>
                    <td>${this.escapeHtml(student.stream_name || '—')}</td>
                    <td>${this.escapeHtml(student.gender || '—')}</td>
                    <td>${this.escapeHtml(student.card_number || '—')}</td>
                    <td>${qrBadge}</td>
                    <td>${statusBadge}</td>
                    <td>${this.escapeHtml(student.issue_date || '—')}</td>
                    <td>${this.escapeHtml(student.expiry_year || '—')}</td>
                    <td>${this.escapeHtml(student.generated_at || '—')}</td>
                    <td>${actions}</td>
                </tr>
            `;
        }).join('');

        // Add checkbox listeners
        document.querySelectorAll('.student-checkbox').forEach(cb => {
            cb.addEventListener('change', (e) => {
                const id = parseInt(e.target.dataset.id);
                if (e.target.checked) {
                    this.selectedStudents.add(id);
                } else {
                    this.selectedStudents.delete(id);
                }
            });
        });
    },

    getStatusBadge: function(status) {
        const badges = {
            'not_generated': '<span class="badge bg-secondary">No ID</span>',
            'generated': '<span class="badge bg-success">Generated</span>',
            'printed': '<span class="badge bg-primary">Printed</span>',
            'issued': '<span class="badge bg-info">Issued</span>',
            'lost': '<span class="badge bg-danger">Lost</span>',
            'damaged': '<span class="badge bg-warning">Damaged</span>',
            'expired': '<span class="badge bg-dark">Expired</span>',
            'replaced': '<span class="badge bg-secondary">Replaced</span>',
            'revoked': '<span class="badge bg-dark">Revoked</span>',
        };
        return badges[status] || badges['not_generated'];
    },

    getQRBadge: function(qrToken) {
        if (qrToken) {
            return '<span class="badge bg-success">QR Generated</span>';
        }
        return '<span class="badge bg-secondary">QR Missing</span>';
    },

    getRowActions: function(student) {
        const studentId = student.id || student.student_id;
        const cardId = student.card_id;
        const status = student.card_status || 'not_generated';

        let actions = '';

        if (status === 'not_generated') {
            actions += `
                <button class="btn btn-sm btn-outline-success" onclick="StudentIdCardsController.showGenerateModalForStudent(${studentId})">
                    <i class="bi bi-plus-circle"></i> Generate
                </button>
            `;
        } else {
            actions += `
                <button class="btn btn-sm btn-outline-primary" onclick="StudentIdCardsController.previewCard(${studentId})">
                    <i class="bi bi-eye"></i> View
                </button>
            `;
        }

        if (status === 'generated') {
            actions += `
                <button class="btn btn-sm btn-outline-info" onclick="StudentIdCardsController.printCard(${studentId})">
                    <i class="bi bi-printer"></i> Print
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="StudentIdCardsController.markPrintedForStudent(${studentId})">
                    <i class="bi bi-check-circle"></i> Mark Printed
                </button>
            `;
        }

        if (status === 'printed') {
            actions += `
                <button class="btn btn-sm btn-outline-info" onclick="StudentIdCardsController.showIssueModalForStudent(${studentId})">
                    <i class="bi bi-check-circle"></i> Mark Issued
                </button>
            `;
        }

        if (status === 'issued') {
            actions += `
                <button class="btn btn-sm btn-outline-warning" onclick="StudentIdCardsController.markLostForStudent(${studentId})">
                    <i class="bi bi-exclamation-triangle"></i> Mark Lost
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="StudentIdCardsController.showRenewModalForStudent(${studentId})">
                    <i class="bi bi-arrow-repeat"></i> Renew
                </button>
            `;
        }

        if (status === 'lost' || status === 'damaged' || status === 'expired') {
            actions += `
                <button class="btn btn-sm btn-outline-danger" onclick="StudentIdCardsController.showReplaceModalForStudent(${studentId})">
                    <i class="bi bi-arrow-repeat"></i> Replace
                </button>
            `;
        }

        if (cardId && status !== 'not_generated') {
            actions += `
                <button class="btn btn-sm btn-outline-dark" onclick="StudentIdCardsController.viewHistory(${studentId})">
                    <i class="bi bi-clock-history"></i> History
                </button>
            `;
        }

        return actions;
    },

    toggleSelectAll: function(checked) {
        document.querySelectorAll('.student-checkbox').forEach(cb => {
            cb.checked = checked;
            const id = parseInt(cb.dataset.id);
            if (checked) {
                this.selectedStudents.add(id);
            } else {
                this.selectedStudents.delete(id);
            }
        });
    },

    resetFilters: function() {
        this.dom.filterAcademicYear.value = "";
        this.dom.filterClass.value = "";
        this.dom.filterStream.value = "";
        this.dom.filterGender.value = "";
        this.dom.filterStudentStatus.value = "";
        this.dom.filterCardStatus.value = "";
        this.dom.searchStudents.value = "";
        this.updateStreamsFilter();
        this.loadStudents();
    },

    setLoading: function(loading) {
        this.dom.loadingState.classList.toggle("d-none", !loading);
    },

    showError: function(message) {
        this.dom.errorState.textContent = message;
        this.dom.errorState.classList.remove("d-none");
    },

    generateCard: async function(formData) {
        const studentId = document.getElementById('generateStudentId').value;
        const data = {
            student_id: studentId,
            academic_year_id: document.getElementById('generateAcademicYear').value,
            issue_date: document.getElementById('generateIssueDate').value,
            expiry_year: document.getElementById('generateExpiryYear').value,
            template_id: document.getElementById('generateTemplate').value,
            generate_qr: document.getElementById('generateQR').checked,
            notes: document.getElementById('generateNotes').value,
        };

        try {
            const response = await this.apiCall('/students/id-card/generate', 'POST', data);
            this.notify("success", "ID card generated successfully");

            if (typeof bootstrap !== "undefined" && this.dom.generateModal) {
                const modal = bootstrap.Modal.getInstance(this.dom.generateModal);
                if (modal) modal.hide();
            }

            await this.loadStudents();
        } catch (error) {
            console.error('Failed to generate card:', error);
            this.notify("error", error.message || "Failed to generate ID card");
        }
    },

    showGenerateModalForStudent: function(studentId) {
        document.getElementById('generateStudentId').value = studentId;
        this.populateSelect(document.getElementById('generateAcademicYear'), this.metadata.academicYears, "Select Year");
        document.getElementById('generateIssueDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('generateExpiryYear').value = new Date().getFullYear() + 2;

        if (typeof bootstrap !== "undefined" && this.dom.generateModal) {
            const modal = bootstrap.Modal.getInstance(this.dom.generateModal);
            if (modal) modal.show();
        }
    },

    markPrintedForStudent: async function(studentId) {
        const student = this.students.find(s => (s.id || s.student_id) === studentId);
        if (!student || !student.card_id) {
            this.notify("error", "No active card found");
            return;
        }

        try {
            const response = await this.apiCall(`/students/id-card-mark-printed/${student.card_id}`, 'POST', {});
            this.notify("success", "Card marked as printed");
            await this.loadStudents();
        } catch (error) {
            console.error('Failed to mark printed:', error);
            this.notify("error", error.message || "Failed to mark card as printed");
        }
    },

    showIssueModalForStudent: function(studentId) {
        const student = this.students.find(s => (s.id || s.student_id) === studentId);
        if (!student || !student.card_id) {
            this.notify("error", "No active card found");
            return;
        }

        document.getElementById('issueStudentId').value = studentId;
        document.getElementById('issueCardId').value = student.card_id;
        document.getElementById('issueCardNo').value = student.card_number || 'N/A';
        document.getElementById('issueIssuedTo').value = this.getFullName(student);
        document.getElementById('issueIssuedBy').value = 'Current User';
        document.getElementById('issueDateTime').value = new Date().toLocaleString();
        document.getElementById('issueConfirm').checked = false;

        if (typeof bootstrap !== "undefined" && this.dom.issueModal) {
            const modal = bootstrap.Modal.getInstance(this.dom.issueModal);
            if (modal) modal.show();
        }
    },

    markLostForStudent: async function(studentId) {
        const student = this.students.find(s => (s.id || s.student_id) === studentId);
        if (!student || !student.card_id) {
            this.notify("error", "No active card found");
            return;
        }

        try {
            const response = await this.apiCall(`/students/id-card-mark-lost/${student.card_id}`, 'POST', {});
            this.notify("success", "Card marked as lost");
            await this.loadStudents();
        } catch (error) {
            console.error('Failed to mark lost:', error);
            this.notify("error", error.message || "Failed to mark card as lost");
        }
    },

    showRenewModalForStudent: function(studentId) {
        const student = this.students.find(s => (s.id || s.student_id) === studentId);
        if (!student || !student.card_id) {
            this.notify("error", "No active card found");
            return;
        }

        document.getElementById('renewStudentId').value = studentId;
        document.getElementById('renewCardId').value = student.card_id;
        document.getElementById('renewOldCardNo').value = student.card_number || 'N/A';
        document.getElementById('renewIssueDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('renewExpiryYear').value = new Date().getFullYear() + 2;

        if (typeof bootstrap !== "undefined" && this.dom.renewModal) {
            const modal = bootstrap.Modal.getInstance(this.dom.renewModal);
            if (modal) modal.show();
        }
    },

    showReplaceModalForStudent: function(studentId) {
        const student = this.students.find(s => (s.id || s.student_id) === studentId);
        if (!student || !student.card_id) {
            this.notify("error", "No active card found");
            return;
        }

        document.getElementById('replaceStudentId').value = studentId;
        document.getElementById('replaceCardId').value = student.card_id;
        document.getElementById('replaceOldCardNo').value = student.card_number || 'N/A';
        document.getElementById('replaceIssueDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('replaceExpiryYear').value = new Date().getFullYear() + 2;

        if (typeof bootstrap !== "undefined" && this.dom.replaceModal) {
            const modal = bootstrap.Modal.getInstance(this.dom.replaceModal);
            if (modal) modal.show();
        }
    },

    viewHistory: async function(studentId) {
        try {
            const response = await this.apiCall(`/students/id-card-history/${studentId}`, 'GET');
            const data = this.unwrapPayload(response);

            // Render history in modal
            this.dom.historyContent.innerHTML = this.renderHistory(data);

            if (typeof bootstrap !== "undefined" && this.dom.historyModal) {
                const modal = bootstrap.Modal.getInstance(this.dom.historyModal);
                if (modal) modal.show();
            }
        } catch (error) {
            console.error('Failed to load history:', error);
            this.notify("error", "Failed to load card history");
        }
    },

    renderHistory: function(history) {
        if (!history || !history.length) {
            return '<div class="text-center text-muted py-4">No history available</div>';
        }

        return `
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>From Status</th>
                        <th>To Status</th>
                        <th>Remarks</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    ${history.map(h => `
                        <tr>
                            <td>${this.escapeHtml(h.action)}</td>
                            <td>${this.escapeHtml(h.from_status || '—')}</td>
                            <td>${this.escapeHtml(h.to_status || '—')}</td>
                            <td>${this.escapeHtml(h.remarks || '—')}</td>
                            <td>${this.escapeHtml(h.performed_at)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    },

    generateSelected: async function() {
        if (this.selectedStudents.size === 0) {
            this.notify("warning", "Please select students first");
            return;
        }

        try {
            // Show generation modal with options
            this.showBulkGenerateModal();
        } catch (error) {
            console.error('Failed to show bulk generation modal:', error);
            this.notify("error", error.message || "Failed to show bulk generation options");
        }
    },

    showBulkGenerateModal: function() {
        // Create or update bulk generation modal
        let modal = document.getElementById('bulkGenerateModal');
        if (!modal) {
            const modalHTML = `
                <div class="modal fade" id="bulkGenerateModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Generate Bulk ID Cards</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Number of selected students: <strong>${this.selectedStudents.size}</strong></label>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Card Sides</label>
                                    <div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="bulkIncludeFront" checked>
                                            <label class="form-check-label" for="bulkIncludeFront">Front Side</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="bulkIncludeBack" checked>
                                            <label class="form-check-label" for="bulkIncludeBack">Back Side</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Print Layout</label>
                                    <select class="form-select" id="bulkPrintMode">
                                        <option value="a4_sheet">A4 Sheet (Front + Back per row)</option>
                                        <option value="direct_card">Direct ID-Card Printer</option>
                                    </select>
                                </div>
                                <div class="alert alert-info">
                                    <small>
                                        <strong>A4 Sheet:</strong> Generates one PDF with all students arranged in rows (front | back).<br>
                                        <strong>Direct Card:</strong> Opens print dialog for direct card printer (requires browser print dialog).
                                    </small>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" onclick="StudentIdCardsController.generateBulkPDF()">Generate PDF</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            modal = document.getElementById('bulkGenerateModal');
        }

        if (typeof bootstrap !== "undefined") {
            const bootstrapModal = new bootstrap.Modal(modal);
            bootstrapModal.show();
        }
    },

    generateBulkPDF: async function() {
        try {
            const includeFront = document.getElementById('bulkIncludeFront').checked;
            const includeBack = document.getElementById('bulkIncludeBack').checked;
            const printMode = document.getElementById('bulkPrintMode').value;

            if (!includeFront && !includeBack) {
                this.notify("warning", "Please select at least one card side");
                return;
            }

            const response = await this.apiCall('/students/id-card/generate-bulk-pdf', 'POST', {
                student_ids: Array.from(this.selectedStudents),
                print_mode: printMode,
                include_front: includeFront,
                include_back: includeBack
            });

            const data = this.unwrapPayload(response);
            
            if (data && data.pdf_url) {
                this.notify("success", `Bulk PDF generated for ${data.student_count} students`);
                
                // Close modal
                const modal = document.getElementById('bulkGenerateModal');
                if (modal && typeof bootstrap !== "undefined") {
                    const bootstrapModal = bootstrap.Modal.getInstance(modal);
                    if (bootstrapModal) bootstrapModal.hide();
                }

                // Open PDF in new tab
                window.open(data.pdf_url, '_blank');
                
                // Reload students to update status
                await this.loadStudents();
            } else {
                this.notify("error", response.message || "Failed to generate bulk PDF");
            }
        } catch (error) {
            console.error('Failed to generate bulk PDF:', error);
            this.notify("error", error.message || "Failed to generate bulk PDF");
        }
    },

    previewCard: async function(studentId) {
        try {
            this.currentPreviewStudentId = studentId;
            const response = await this.apiCall(`/students/id-card-details/${studentId}`, 'GET');
            const data = this.unwrapPayload(response);

            if (!data.student?.qr_code_path) {
                try {
                    const qrResult = await this.ensureStudentQrCode(studentId);
                    if (qrResult) {
                        data.student.qr_code_path = qrResult.qr_code_path || qrResult.qr_code_url || qrResult.qr_path || data.student.qr_code_path;
                    }
                } catch (qrError) {
                    console.error('Failed to auto-generate QR code:', qrError);
                    this.notify("warning", "Preview loaded, but QR code generation failed. Use Generate QR to retry.");
                }
            }

            this.renderCardPreview(data);

            // Show modal - create new instance if needed
            if (typeof bootstrap !== "undefined" && this.dom.previewModal) {
                let modal = bootstrap.Modal.getInstance(this.dom.previewModal);
                if (!modal) {
                    modal = new bootstrap.Modal(this.dom.previewModal);
                }
                modal.show();
            } else {
                console.error('Bootstrap or preview modal not available');
                this.notify("error", "Modal component not available");
            }
        } catch (error) {
            console.error('Failed to load card preview:', error);
            this.notify("error", "Failed to load card preview");
        }
    },

    printCard: async function(studentId) {
        // Single-card print: fetch server-rendered HTML (CR80, QR as data URI,
        // front|back side-by-side) and open the OS print dialog.
        const modeSelect = document.getElementById('printModeDirect');
        const printMode = modeSelect?.value || 'direct_card';
        await this.openServerPrintHtml(
            '/students/id-card/print-single',
            { student_id: studentId, side: 'both', print_mode: printMode },
            `ID Card - ${studentId}`
        );
    },

    renderCardPreview: function(data) {
        const student = data.student || {};
        const school = data.school_settings || data.school_profile || {};
        const appBase = window.APP_BASE || "";
        const photo = this.resolveAssetUrl(student.photo_url, `${appBase}/uploads/students/avatar.jpg`);
        const logo = this.resolveAssetUrl(school.school_logo || school.logo_url, `${appBase}/uploads/school_assets/official_school_logo.png`);
        const fullName = this.getFullName(student);
        const qrCodePath = this.resolveAssetUrl(student.qr_code_path || data.qr_code_path, "");
        const cardNumber = student.card_number || "Not generated";
        const issueDate = this.formatDisplayDate(student.issue_date || student.generated_at);
        const expiryYear = student.expiry_year || "—";
        const schoolName = school.school_name || "Kingsway Preparatory Academy";
        const schoolAddress = school.school_address || "Londiani, Kenya";
        const schoolPhone = school.school_phone || "";
        const schoolEmail = school.school_email || "";
        const schoolMotto = school.school_motto || "Education for Excellence";
        const headteacher = school.headteacher_name || "Headteacher";

        this.dom.cardFrontPreview.innerHTML = `
            <div class="id-card-preview-front">
                <div class="id-card-header">
                    <img src="${this.escapeAttr(logo)}" class="id-card-logo" alt="School Logo">
                    <div>
                        <div class="id-card-school-name">${this.escapeHtml(schoolName)}</div>
                        <div class="id-card-school-meta">${this.escapeHtml(schoolAddress)}</div>
                    </div>
                </div>
                <div class="id-card-title-strip">Student Identity Card</div>
                <div class="id-card-front-body">
                    <div class="id-card-photo-wrap">
                        <img src="${this.escapeAttr(photo)}" class="id-card-photo" alt="Student Photo">
                    </div>
                    <div>
                        <div class="id-card-name">${this.escapeHtml(fullName)}</div>
                        <div class="id-card-detail-grid">
                            <span class="id-card-detail-label">Adm No</span>
                            <span class="id-card-detail-value">${this.escapeHtml(student.admission_no || "—")}</span>
                            <span class="id-card-detail-label">Gender</span>
                            <span class="id-card-detail-value">${this.escapeHtml(student.gender || "—")}</span>
                            <span class="id-card-detail-label">Acad. Year</span>
                            <span class="id-card-detail-value">${this.escapeHtml(student.academic_year || "—")}</span>
                        </div>
                    </div>
                </div>
                <div class="id-card-footer-strip">
                    <span>${this.escapeHtml(schoolMotto)}</span>
                    <span>${this.escapeHtml(schoolPhone || schoolEmail || "Official School ID")}</span>
                </div>
            </div>
        `;

        this.dom.cardBackPreview.innerHTML = `
            <div class="id-card-preview-back">
                <div class="id-card-back-qr-panel">
                    ${qrCodePath ? `<img src="${this.escapeAttr(qrCodePath)}" class="id-card-qr" alt="QR Code">` : '<div class="id-card-qr-placeholder">Generate QR</div>'}
                    <div class="text-center fw-bold text-success">Scan to verify</div>
                </div>
                <div>
                    <div class="id-card-back-title">Card Details</div>
                    <div class="id-card-back-detail"><span>Card No</span><span>${this.escapeHtml(cardNumber)}</span></div>
                    <div class="id-card-back-detail"><span>Issued</span><span>${this.escapeHtml(issueDate)}</span></div>
                    <div class="id-card-back-detail"><span>Expires</span><span>${this.escapeHtml(String(expiryYear))}</span></div>
                    <div class="id-card-back-detail"><span>Phone</span><span>${this.escapeHtml(schoolPhone || "—")}</span></div>
                    <div class="id-card-back-detail"><span>Email</span><span>${this.escapeHtml(schoolEmail || "—")}</span></div>
                    <div class="id-card-back-detail"><span>Address</span><span>${this.escapeHtml(schoolAddress)}</span></div>
                    <div class="id-card-footer">
                        This card remains the property of ${this.escapeHtml(schoolName)}. If found, please return it to the school office.<br>
                        Authorized by: ${this.escapeHtml(headteacher)}
                    </div>
                </div>
            </div>
        `;
    },

    resolveAssetUrl: function(path, fallback = "") {
        if (!path) return fallback;

        const value = String(path).trim();
        if (/^data:image\//i.test(value)) return value;
        if (/^https?:\/\//i.test(value) || value.startsWith("//")) return value;
        if (/^[a-z][a-z0-9+.-]*:/i.test(value)) return fallback;

        const appBase = window.APP_BASE || "";
        return `${appBase}/${value.replace(/^\/+/, "")}`;
    },

    formatDisplayDate: function(value) {
        if (!value) return "—";
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return String(value);
        return date.toLocaleDateString("en-KE", { year: "numeric", month: "short", day: "2-digit" });
    },
    printSingleCard: async function() {
        if (!this.currentPreviewStudentId) {
            this.notify("warning", "No card preview is available.");
            return;
        }

        const selectedMode =
            document.getElementById("printModeDirect")?.value
            || "direct_card";
        const side =
            document.getElementById("printSideSelect")?.value
            || "both";
        const printerMode = selectedMode === "a4_sheet"
            ? "a4_pdf"
            : selectedMode;

        try {
            await window.PrintManager.printSingleStudentIdCard(
                this.currentPreviewStudentId,
                {
                    printerMode,
                    side,
                    filename: `student_id_${this.currentPreviewStudentId}`,
                },
            );
            this.notify("success", "Student ID-card PDF is ready.");
        } catch (error) {
            console.error("Student ID-card printing failed:", error);
            this.notify(
                "error",
                error.message || "Unable to print the student ID card.",
            );
        }
    },
    downloadSingleCard: async function() {
        return this.printSingleCard();
    },
    printSelected: async function() {
        const studentIds = Array.from(this.selectedStudents);

        if (!studentIds.length) {
            this.notify("warning", "Select at least one student.");
            return;
        }

        const includeFront =
            document.getElementById("bulkIncludeFront")?.checked
            ?? true;
        const includeBack =
            document.getElementById("bulkIncludeBack")?.checked
            ?? true;

        if (!includeFront && !includeBack) {
            this.notify("warning", "Select at least one card side.");
            return;
        }

        const selectedMode =
            document.getElementById("bulkPrintMode")?.value
            || "a4_sheet";
        const printerMode = selectedMode === "a4_sheet"
            ? "a4_pdf"
            : selectedMode;
        const side = includeFront && includeBack
            ? "both"
            : (includeFront ? "front" : "back");

        try {
            await window.PrintManager.printBulkStudentIdCards(
                studentIds,
                {
                    printerMode,
                    side,
                    chunkSize: 100,
                    filename: `student_id_cards_${new Date()
                        .toISOString()
                        .slice(0, 10)}`,
                },
            );
            this.notify(
                "success",
                `ID-card PDF ready for ${studentIds.length} student(s).`,
            );
        } catch (error) {
            console.error("Bulk ID-card printing failed:", error);
            this.notify(
                "error",
                error.message || "Unable to generate ID cards.",
            );
        }
    },

    /**
     * Open server-rendered print HTML in a dedicated print window and trigger
     * the browser/system print dialog. The OS printer driver handles the job,
     * including any installed ID-card printer selected by the user.
     */

    exportData: function() {
        if (!this.students.length) {
            this.notify("warning", "No data to export");
            return;
        }

        const columns = [
            { key: 'admission_no', label: 'Admission No' },
            { key: 'full_name', label: 'Name' },
            { key: 'class_name', label: 'Class' },
            { key: 'stream_name', label: 'Stream' },
            { key: 'gender', label: 'Gender' },
            { key: 'card_number', label: 'Card Number' },
            { key: 'card_status', label: 'Status' },
            { key: 'issue_date', label: 'Issue Date' },
            { key: 'expiry_year', label: 'Expiry Year' }
        ];

        window.PrintManager.exportToCSV({
            columns: columns,
            rows: this.students,
            filename: 'student_id_cards'
        });
    },

    showGenerateModal: function() {
        // Populate academic years and show modal
        this.populateSelect(document.getElementById('generateAcademicYear'), this.metadata.academicYears, "Select Year");
        document.getElementById('generateIssueDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('generateExpiryYear').value = new Date().getFullYear() + 2;

        if (typeof bootstrap !== "undefined" && this.dom.generateModal) {
            const modal = bootstrap.Modal.getInstance(this.dom.generateModal);
            if (modal) modal.show();
        }
    },

    showRenewModal: function() {
        if (typeof bootstrap !== "undefined" && this.dom.renewModal) {
            const modal = bootstrap.Modal.getInstance(this.dom.renewModal);
            if (modal) modal.show();
        }
    },

    showReplaceModal: function() {
        if (typeof bootstrap !== "undefined" && this.dom.replaceModal) {
            const modal = bootstrap.Modal.getInstance(this.dom.replaceModal);
            if (modal) modal.show();
        }
    },

    showIssueModal: function() {
        if (typeof bootstrap !== "undefined" && this.dom.issueModal) {
            const modal = bootstrap.Modal.getInstance(this.dom.issueModal);
            if (modal) modal.show();
        }
    },

    ensureStudentQrCode: async function(studentId) {
        if (!studentId) return null;

        const response = await this.apiCall('/students/qr-code-generate-enhanced', 'POST', { student_id: studentId });
        const payload = this.unwrapPayload(response);
        return payload.data || payload;
    },

    generateQRCode: async function() {
        if (!this.currentPreviewStudentId) {
            this.notify("error", "Open a student ID preview before generating a QR code");
            return;
        }

        try {
            const qrResult = await this.ensureStudentQrCode(this.currentPreviewStudentId);
            this.notify("success", "Student QR code generated successfully");
            await this.previewCard(this.currentPreviewStudentId);
        } catch (error) {
            console.error('Failed to generate QR code:', error);
            this.notify("error", error.message || "Failed to generate student QR code");
        }
    },

    markPrinted: async function() {
        this.notify("info", "Mark printed endpoint - to be implemented");
    },

    markIssued: async function(formData) {
        const cardId = document.getElementById('issueCardId').value;
        if (!cardId) {
            this.notify("error", "No card ID found");
            return;
        }

        try {
            const response = await this.apiCall(`/students/id-card-mark-issued/${cardId}`, 'POST', {});
            this.notify("success", "Card marked as issued");

            if (typeof bootstrap !== "undefined" && this.dom.issueModal) {
                const modal = bootstrap.Modal.getInstance(this.dom.issueModal);
                if (modal) modal.hide();
            }

            await this.loadStudents();
        } catch (error) {
            console.error('Failed to mark issued:', error);
            this.notify("error", error.message || "Failed to mark card as issued");
        }
    },

    renewCard: async function(formData) {
        const cardId = document.getElementById('renewCardId').value;
        if (!cardId) {
            this.notify("error", "No card ID found");
            return;
        }

        try {
            const response = await this.apiCall(`/students/id-card-renew/${cardId}`, 'POST', {});
            this.notify("success", "Card renewed successfully");

            if (typeof bootstrap !== "undefined" && this.dom.renewModal) {
                const modal = bootstrap.Modal.getInstance(this.dom.renewModal);
                if (modal) modal.hide();
            }

            await this.loadStudents();
        } catch (error) {
            console.error('Failed to renew card:', error);
            this.notify("error", error.message || "Failed to renew card");
        }
    },

    replaceCard: async function(formData) {
        const cardId = document.getElementById('replaceCardId').value;
        const reason = document.getElementById('replaceReason').value;
        if (!cardId) {
            this.notify("error", "No card ID found");
            return;
        }

        try {
            const response = await this.apiCall(`/students/id-card-replace/${cardId}`, 'POST', { reason });
            this.notify("success", "Card replaced successfully");

            if (typeof bootstrap !== "undefined" && this.dom.replaceModal) {
                const modal = bootstrap.Modal.getInstance(this.dom.replaceModal);
                if (modal) modal.hide();
            }

            await this.loadStudents();
        } catch (error) {
            console.error('Failed to replace card:', error);
            this.notify("error", error.message || "Failed to replace card");
        }
    },

    debounce: function(fn, delay) {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    }
};

window.StudentIdCardsController = StudentIdCardsController;

function initWhenAPIReady() {
    const hasApi =
        window.API &&
        (
            typeof window.API.callAPI === "function" ||
            typeof window.API.apiCall === "function"
        );

    if (hasApi) {
        console.log("API is ready, initializing student ID cards controller");
        window.StudentIdCardsController.init();
        return;
    }

    console.log("API not ready yet, waiting...");
    setTimeout(initWhenAPIReady, 100);
}

document.addEventListener("DOMContentLoaded", function () {
    console.log("DOM loaded, waiting for API to be ready");
    initWhenAPIReady();
});
