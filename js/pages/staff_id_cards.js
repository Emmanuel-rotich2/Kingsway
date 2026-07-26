/**
 * Staff Security Passes Controller
 *
 * Route compatibility:
 * - Page: pages/staff_id_cards.php
 * - API namespace: window.API.staff
 * - Database registry: staff_id_cards
 *
 * The UI intentionally uses "Security Pass" terminology because staff wear a
 * portrait lanyard pass. Existing route, permission and database identifiers
 * remain unchanged to avoid parallel architecture.
 */
const StaffSecurityPassesController = {
    initialized: false,
    initializationPromise: null,
    eventsBound: false,

    state: {
        passes: [],
        filteredPasses: [],
        selectedStaffIds: new Set(),
        preview: {
            staffId: null,
            passId: null,
            mode: null,
            document: null,
            documentUrl: '',
            documentHtml: '',
            files: [],
        },
    },

    async init() {
        if (this.initializationPromise) {
            return this.initializationPromise;
        }

        this.initializationPromise = this._initialize();

        try {
            await this.initializationPromise;
            return this;
        } catch (error) {
            this.initializationPromise = null;
            throw error;
        }
    },

    async _initialize() {
        if (this.initialized) {
            return this;
        }

        console.log('[StaffSecurityPassesController] Initializing...');

        if (window.AuthContext?.ready) {
            await window.AuthContext.ready();
        }

        if (!window.AuthContext?.isAuthenticated?.()) {
            this.notify('Please log in to access staff security passes.', 'error');
            window.setTimeout(() => {
                window.location.replace(`${window.APP_BASE || ''}/index.php`);
            }, 800);
            return this;
        }

        if (!window.StaffAccessController) {
            throw new Error('StaffAccessController is unavailable.');
        }

        const allowed = await window.StaffAccessController.require(
            'staff.id_cards.view',
            'You do not have permission to view staff security passes.',
        );

        if (!allowed) {
            return this;
        }

        this.assertApiContract();
        this.bindEvents();
        await this.loadPasses({
            throwOnError: true,
        });

        this.initialized = true;
        console.log('[StaffSecurityPassesController] Initialized successfully');

        return this;
    },

    assertApiContract() {
        const requiredMethods = [
            'getIdCards',
            'generateIdCard',
            'generateBulkIdCards',
            'previewBulkIdCards',
            'printSingleIdCard',
            'issueIdCard',
        ];

        if (!window.API?.staff) {
            throw new Error('The canonical Staff API namespace is unavailable.');
        }

        const missingMethods = requiredMethods.filter(
            (method) => typeof window.API.staff[method] !== 'function',
        );

        if (missingMethods.length) {
            throw new Error(
                `Missing Staff API method(s): ${missingMethods.join(', ')}`,
            );
        }
    },

    bindEvents() {
        if (this.eventsBound) {
            return;
        }

        this.eventsBound = true;

        document.getElementById('generateOnePassBtn')
            ?.addEventListener('click', () => this.openGenerateModal());

        document.getElementById('generateBulkPassesBtn')
            ?.addEventListener('click', () => this.openBulkModal());

        document.getElementById('refreshPassesBtn')
            ?.addEventListener('click', () => void this.loadPasses());

        document.getElementById('passSearch')
            ?.addEventListener('input', () => this.applyFilters());

        document.getElementById('passStatusFilter')
            ?.addEventListener('change', () => this.applyFilters());

        document.getElementById('passDepartmentFilter')
            ?.addEventListener('change', () => this.applyFilters());

        document.getElementById('selectAllVisiblePasses')
            ?.addEventListener('change', (event) => {
                this.selectAllVisible(Boolean(event.target.checked));
            });

        document.getElementById('staffSecurityPassesBody')
            ?.addEventListener('change', (event) => {
                const checkbox = event.target.closest('[data-pass-select]');
                if (!checkbox) return;

                const staffId = Number(checkbox.dataset.staffId);
                if (!staffId) return;

                if (checkbox.checked) {
                    this.state.selectedStaffIds.add(staffId);
                } else {
                    this.state.selectedStaffIds.delete(staffId);
                }

                this.syncSelectionControls();
            });

        document.getElementById('staffSecurityPassesBody')
            ?.addEventListener('click', (event) => {
                const button = event.target.closest(
                    '[data-pass-action][data-staff-id]',
                );

                if (!button) return;

                const staffId = Number(button.dataset.staffId);
                if (!staffId) return;

                switch (button.dataset.passAction) {
                    case 'generate':
                    case 'regenerate':
                        this.openGenerateModal(staffId);
                        break;
                    case 'preview':
                        void this.previewSingle(staffId);
                        break;
                    case 'print':
                        void this.printSingle(staffId);
                        break;
                    case 'issue':
                        void this.issuePass(staffId);
                        break;
                    default:
                        break;
                }
            });

        document.getElementById('generateSecurityPassSubmitBtn')
            ?.addEventListener('click', () => void this.generateSingle());

        document.getElementById('generateBulkSecurityPassesSubmitBtn')
            ?.addEventListener('click', () => void this.generateBulk());

        document.getElementById('previewBulkSecurityPassesBtn')
            ?.addEventListener('click', () => void this.previewSelected());

        document.getElementById('previewSelectedPassesBtn')
            ?.addEventListener('click', () => void this.previewSelected());

        document.getElementById('printSelectedPassesBtn')
            ?.addEventListener('click', () => void this.printSelected());

        document.getElementById('regenerateSelectedPassesBtn')
            ?.addEventListener('click', () => this.openBulkModal());

        document.getElementById('openSecurityPassDocumentBtn')
            ?.addEventListener('click', () => this.openCurrentDocument());

        document.getElementById('printSecurityPassDocumentBtn')
            ?.addEventListener('click', () => void this.printCurrentDocument());

        document.getElementById('issueSecurityPassBtn')
            ?.addEventListener('click', () => {
                if (this.state.preview.staffId) {
                    void this.issuePass(this.state.preview.staffId);
                }
            });
    },

    async loadPasses(options = {}) {
        const throwOnError = options.throwOnError === true;

        this.renderLoading();

        try {
            const response = await window.API.staff.getIdCards();

            if (!Array.isArray(response)) {
                throw new Error('Staff security-pass endpoint returned an invalid payload.');
            }

            this.state.passes = response
                .map((record) => this.normalizePassRecord(record))
                .sort((left, right) => left.fullName.localeCompare(
                    right.fullName,
                    undefined,
                    { sensitivity: 'base' },
                ));

            this.removeInvalidSelections();
            this.populateStaffSelect();
            this.populateDepartmentFilter();
            this.applyFilters();
        } catch (error) {
            console.error(
                '[StaffSecurityPassesController] Failed to load passes:',
                error,
            );

            this.state.passes = [];
            this.state.filteredPasses = [];
            this.renderError(
                error?.message || 'Unable to load staff security passes.',
            );
            this.renderStatistics();
            this.notify(
                error?.message || 'Unable to load staff security passes.',
                'error',
            );

            if (throwOnError) {
                throw error;
            }
        }
    },

    normalizePassRecord(record) {
        const passId = record.id === null || record.id === undefined
            ? null
            : Number(record.id);

        const staffId = Number(record.staff_id);

        if (!staffId) {
            throw new Error('A staff security-pass row is missing staff_id.');
        }

        return {
            passId: Number.isFinite(passId) && passId > 0 ? passId : null,
            staffId,
            staffNo: String(record.staff_no || '').trim(),
            firstName: String(record.first_name || '').trim(),
            lastName: String(record.last_name || '').trim(),
            fullName: [record.first_name, record.last_name]
                .map((value) => String(value || '').trim())
                .filter(Boolean)
                .join(' '),
            departmentName: String(record.department_name || '').trim(),
            position: String(record.position || '').trim(),
            email: String(record.email || '').trim(),
            phone: String(record.phone || '').trim(),
            profilePictureUrl: String(record.profile_pic_url || '').trim(),
            passNumber: String(record.card_number || '').trim(),
            generatedAt: record.generated_at || null,
            issuedAt: record.issued_at || null,
            databaseStatus: record.status || null,
            status: this.resolvePassStatus(record),
            metadata: record.metadata || null,
            source: record,
        };
    },

    resolvePassStatus(record) {
        if (record.id === null || record.id === undefined) {
            return 'missing';
        }

        if (String(record.status || '').toLowerCase() === 'revoked') {
            return 'revoked';
        }

        if (
            String(record.status || '').toLowerCase() === 'issued'
            || record.issued_at
        ) {
            if (
                record.issued_at
                && String(record.status || '').toLowerCase() !== 'issued'
            ) {
                console.warn(
                    '[StaffSecurityPassesController] Pass status mismatch:',
                    record,
                );
            }

            return 'issued';
        }

        return 'generated';
    },

    applyFilters() {
        const searchTerm = String(
            document.getElementById('passSearch')?.value || '',
        ).trim().toLowerCase();

        const status = document.getElementById('passStatusFilter')?.value || '';
        const department = document.getElementById('passDepartmentFilter')?.value || '';

        this.state.filteredPasses = this.state.passes.filter((pass) => {
            const searchText = [
                pass.fullName,
                pass.staffNo,
                pass.departmentName,
                pass.position,
                pass.passNumber,
                pass.email,
                pass.phone,
            ].join(' ').toLowerCase();

            return (!searchTerm || searchText.includes(searchTerm))
                && (!status || pass.status === status)
                && (!department || pass.departmentName === department);
        });

        this.render();
    },

    render() {
        this.renderStatistics();
        this.renderTable();
        this.syncSelectionControls();
        window.StaffAccessController.apply(document);
    },

    renderStatistics() {
        const generated = this.state.passes.filter(
            (pass) => pass.status === 'generated',
        ).length;

        const issued = this.state.passes.filter(
            (pass) => pass.status === 'issued',
        ).length;

        const attention = this.state.passes.filter(
            (pass) => pass.status === 'missing' || pass.status === 'revoked',
        ).length;

        this.setText('passTotalCount', this.state.passes.length);
        this.setText('passGeneratedCount', generated);
        this.setText('passIssuedCount', issued);
        this.setText('passAttentionCount', attention);
    },

    renderTable() {
        const body = document.getElementById('staffSecurityPassesBody');
        if (!body) return;

        if (!this.state.filteredPasses.length) {
            body.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4">
                        <i class="bi bi-person-x fs-3 text-muted d-block mb-2"></i>
                        <span class="fw-semibold">No staff records match the current filters.</span>
                    </td>
                </tr>
            `;
            this.setText('passResultsSummary', '0 records');
            return;
        }

        body.innerHTML = this.state.filteredPasses
            .map((pass) => this.renderRow(pass))
            .join('');

        const count = this.state.filteredPasses.length;
        this.setText(
            'passResultsSummary',
            `${count} staff record${count === 1 ? '' : 's'}`,
        );

        window.StaffAccessController.apply(body);
    },

    renderRow(pass) {
        const checked = this.state.selectedStaffIds.has(pass.staffId)
            ? 'checked'
            : '';

        const contact = pass.email || pass.phone || 'No contact recorded';

        return `
            <tr data-staff-id="${pass.staffId}">
                <td class="text-center">
                    <input
                        type="checkbox"
                        class="form-check-input"
                        data-pass-select
                        data-staff-id="${pass.staffId}"
                        aria-label="Select ${this.escapeHtml(pass.fullName)}"
                        ${checked}
                    >
                </td>
                <td>
                    <div class="fw-semibold">${this.escapeHtml(pass.fullName || 'Unnamed staff')}</div>
                    <div class="small text-muted">${this.escapeHtml(contact)}</div>
                </td>
                <td>${this.valueOrDash(pass.staffNo)}</td>
                <td>${this.valueOrDash(pass.departmentName)}</td>
                <td>${this.valueOrDash(pass.position)}</td>
                <td>${pass.passNumber
                    ? `<span class="fw-semibold">${this.escapeHtml(pass.passNumber)}</span>`
                    : '<span class="text-muted">Not generated</span>'}
                </td>
                <td>${this.renderStatusBadge(pass.status)}</td>
                <td class="text-end">${this.renderActions(pass)}</td>
            </tr>
        `;
    },

    renderStatusBadge(status) {
        const config = {
            missing: ['bg-warning text-dark', 'Missing'],
            generated: ['bg-info text-dark', 'Generated'],
            issued: ['bg-success', 'Issued'],
            revoked: ['bg-secondary', 'Revoked'],
        };

        const [badgeClass, label] = config[status] || config.missing;
        return `<span class="badge ${badgeClass}">${label}</span>`;
    },

    renderActions(pass) {
        if (pass.status === 'missing') {
            return `
                <button
                    type="button"
                    class="btn btn-sm btn-primary"
                    data-pass-action="generate"
                    data-staff-id="${pass.staffId}"
                    data-permission="staff.id_cards.manage"
                >
                    <i class="bi bi-plus-circle me-1"></i>
                    Generate
                </button>
            `;
        }

        if (pass.status === 'revoked') {
            return `
                <button
                    type="button"
                    class="btn btn-sm btn-outline-warning"
                    data-pass-action="regenerate"
                    data-staff-id="${pass.staffId}"
                    data-permission="staff.id_cards.manage"
                    title="Generate a replacement security pass"
                >
                    <i class="bi bi-arrow-repeat me-1"></i>
                    Replace
                </button>
            `;
        }

        const issueButton = pass.status === 'generated'
            ? `
                <button
                    type="button"
                    class="btn btn-outline-success"
                    data-pass-action="issue"
                    data-staff-id="${pass.staffId}"
                    data-permission="staff.id_cards.manage"
                    title="Mark security pass as issued"
                >
                    <i class="bi bi-shield-check"></i>
                </button>
            `
            : '';

        return `
            <div class="btn-group btn-group-sm" role="group" aria-label="Security pass actions">
                <button
                    type="button"
                    class="btn btn-outline-info"
                    data-pass-action="preview"
                    data-staff-id="${pass.staffId}"
                    title="Preview security pass"
                >
                    <i class="bi bi-eye"></i>
                </button>
                <button
                    type="button"
                    class="btn btn-outline-dark"
                    data-pass-action="print"
                    data-staff-id="${pass.staffId}"
                    title="Print security pass"
                >
                    <i class="bi bi-printer"></i>
                </button>
                <button
                    type="button"
                    class="btn btn-outline-warning"
                    data-pass-action="regenerate"
                    data-staff-id="${pass.staffId}"
                    data-permission="staff.id_cards.manage"
                    title="Regenerate security pass"
                >
                    <i class="bi bi-arrow-repeat"></i>
                </button>
                ${issueButton}
            </div>
        `;
    },

    populateStaffSelect() {
        const select = document.getElementById('securityPassStaffId');
        if (!select) return;

        select.innerHTML = `
            <option value="">Select staff</option>
            ${this.state.passes.map((pass) => `
                <option value="${pass.staffId}">
                    ${this.escapeHtml(
                        `${pass.staffNo || 'No staff no.'} — ${pass.fullName}`,
                    )}
                </option>
            `).join('')}
        `;
    },

    populateDepartmentFilter() {
        const select = document.getElementById('passDepartmentFilter');
        if (!select) return;

        const currentValue = select.value;
        const departments = Array.from(new Set(
            this.state.passes
                .map((pass) => pass.departmentName)
                .filter(Boolean),
        )).sort((left, right) => left.localeCompare(right));

        select.innerHTML = `
            <option value="">All departments</option>
            ${departments.map((department) => `
                <option value="${this.escapeHtml(department)}">
                    ${this.escapeHtml(department)}
                </option>
            `).join('')}
        `;

        if (departments.includes(currentValue)) {
            select.value = currentValue;
        }
    },

    openGenerateModal(staffId = null) {
        if (!window.StaffAccessController.can('staff.id_cards.manage')) {
            this.notify(
                'You do not have permission to generate staff security passes.',
                'error',
            );
            return;
        }

        const staffSelect = document.getElementById('securityPassStaffId');
        if (staffSelect) {
            staffSelect.value = staffId ? String(staffId) : '';
        }

        this.getModal('generateStaffSecurityPassModal').show();
    },

    openBulkModal() {
        const selected = this.selectedPasses();

        if (!selected.length) {
            this.notify('Select one or more staff records first.', 'warning');
            return;
        }

        const summary = document.getElementById('bulkSecurityPassSummary');
        if (summary) {
            const names = selected.slice(0, 5).map((pass) => pass.fullName);
            summary.textContent = `${selected.length} selected: ${names.join(', ')}${selected.length > 5 ? '…' : ''}`;
        }

        this.getModal('bulkStaffSecurityPassModal').show();
    },

    async generateSingle() {
        const staffId = Number(
            document.getElementById('securityPassStaffId')?.value,
        );
        const side = document.getElementById('securityPassSide')?.value || 'both';
        const printMode = document.getElementById('securityPassPrintMode')?.value || 'direct_card';

        if (!staffId) {
            this.notify('Select a staff member.', 'warning');
            return;
        }

        const button = document.getElementById('generateSecurityPassSubmitBtn');
        this.setButtonBusy(button, 'Generating...');

        try {
            const response = await window.API.staff.generateIdCard({
                staff_id: staffId,
                format: 'pdf',
                side,
                print_mode: printMode,
            });

            this.hideModal('generateStaffSecurityPassModal');
            await this.loadPasses();

            const pass = this.findPass(staffId);
            this.showPreview(response, pass, 'single');
            this.notify('Staff security pass generated successfully.', 'success');
        } catch (error) {
            console.error('[StaffSecurityPassesController] Generation failed:', error);
            this.notify(
                error?.message || 'Unable to generate the staff security pass.',
                'error',
            );
        } finally {
            this.restoreButton(button);
        }
    },

    async generateBulk() {
        const selected = this.selectedPasses();
        const includeFront = document.getElementById('bulkSecurityPassFront')?.checked ?? true;
        const includeBack = document.getElementById('bulkSecurityPassBack')?.checked ?? true;
        const printMode = document.getElementById('bulkSecurityPassPrintMode')?.value || 'a4_pdf';

        if (!selected.length) {
            this.notify('Select one or more staff records.', 'warning');
            return;
        }

        if (!includeFront && !includeBack) {
            this.notify('Select at least one pass side.', 'warning');
            return;
        }

        const button = document.getElementById('generateBulkSecurityPassesSubmitBtn');
        this.setButtonBusy(button, 'Generating...');

        try {
            const response = await window.API.staff.generateBulkIdCards({
                staff_ids: selected.map((pass) => pass.staffId),
                print_mode: printMode,
                include_front: includeFront,
                include_back: includeBack,
            });

            this.hideModal('bulkStaffSecurityPassModal');
            await this.loadPasses();
            this.showPreview(response, null, 'bulk', selected.length);
            this.notify(
                `${selected.length} staff security pass${selected.length === 1 ? '' : 'es'} generated.`,
                'success',
            );
        } catch (error) {
            console.error('[StaffSecurityPassesController] Bulk generation failed:', error);
            this.notify(
                error?.message || 'Unable to generate staff security passes.',
                'error',
            );
        } finally {
            this.restoreButton(button);
        }
    },

    async previewSingle(staffId) {
        const pass = this.findPass(staffId);

        if (!pass || pass.status === 'missing') {
            this.notify('Generate the security pass before previewing it.', 'warning');
            return false;
        }

        if (pass.status === 'revoked') {
            this.notify(
                'This security pass is revoked. Generate a replacement before previewing or printing.',
                'warning',
            );
            return false;
        }

        this.clearPreviewState();

        try {
            const response = await window.API.staff.printSingleIdCard({
                staff_id: staffId,
                side: 'both',
                print_mode: 'direct_card',
            });

            this.showPreview(response, pass, 'single');
            return true;
        } catch (error) {
            this.notify(
                error?.message || 'Unable to prepare the security-pass preview.',
                'error',
            );
            return false;
        }
    },

    async printSingle(staffId) {
        const prepared = await this.previewSingle(staffId);

        if (prepared) {
            await this.printCurrentDocument();
        }
    },

    async previewSelected() {
        const selected = this.selectedPasses().filter(
            (pass) => pass.status === 'generated' || pass.status === 'issued',
        );

        if (!selected.length) {
            this.notify(
                'None of the selected staff members has a generated security pass.',
                'warning',
            );
            return false;
        }

        if (selected.length === 1) {
            return this.previewSingle(selected[0].staffId);
        }

        const includeFront = document.getElementById('bulkSecurityPassFront')?.checked ?? true;
        const includeBack = document.getElementById('bulkSecurityPassBack')?.checked ?? true;
        const printMode = document.getElementById('bulkSecurityPassPrintMode')?.value || 'a4_pdf';

        if (!includeFront && !includeBack) {
            this.notify('Select at least one pass side.', 'warning');
            return false;
        }

        this.clearPreviewState();

        try {
            const response = await window.API.staff.previewBulkIdCards({
                staff_ids: selected.map((pass) => pass.staffId),
                print_mode: printMode,
                include_front: includeFront,
                include_back: includeBack,
            });

            this.hideModal('bulkStaffSecurityPassModal');
            this.showPreview(response, null, 'bulk', selected.length);
            return true;
        } catch (error) {
            this.notify(
                error?.message || 'Unable to prepare the bulk pass preview.',
                'error',
            );
            return false;
        }
    },

    async printSelected() {
        const prepared = await this.previewSelected();

        if (prepared) {
            await this.printCurrentDocument();
        }
    },

    async issuePass(staffId) {
        const pass = this.findPass(staffId);

        if (!pass || pass.status === 'missing') {
            this.notify('Generate the security pass before issuing it.', 'warning');
            return;
        }

        if (pass.status === 'issued') {
            this.notify('This security pass is already issued.', 'info');
            return;
        }

        try {
            await window.API.staff.issueIdCard({ staff_id: staffId });
            this.hideModal('staffSecurityPassPreviewModal');
            await this.loadPasses();
            this.notify('Staff security pass marked as issued.', 'success');
        } catch (error) {
            this.notify(
                error?.message || 'Unable to issue the staff security pass.',
                'error',
            );
        }
    },

    showPreview(response, pass = null, mode = 'single', bulkCount = 0) {
        const documentData = this.extractDocument(response);

        this.state.preview = {
            staffId: pass?.staffId || null,
            passId: pass?.passId || null,
            mode,
            document: documentData.payload,
            documentUrl: documentData.url,
            documentHtml: documentData.html,
            files: documentData.files,
        };

        this.setText(
            'staffSecurityPassPreviewTitle',
            mode === 'bulk'
                ? 'Bulk Staff Security Pass Preview'
                : 'Staff Security Pass Preview',
        );

        this.setText(
            'staffSecurityPassPreviewSubtitle',
            mode === 'bulk'
                ? `${bulkCount} portrait staff passes prepared for printing.`
                : 'Review the portrait lanyard pass before printing or issuing.',
        );

        this.renderPreviewMetadata(pass, mode, bulkCount, documentData);
        this.renderPreviewDocument(documentData);

        const issueButton = document.getElementById('issueSecurityPassBtn');
        if (issueButton) {
            const canIssue = mode === 'single'
                && pass?.status === 'generated'
                && window.StaffAccessController.can('staff.id_cards.manage');

            issueButton.hidden = !canIssue;
            issueButton.disabled = !canIssue;
        }

        this.getModal('staffSecurityPassPreviewModal').show();
        window.StaffAccessController.apply(document);
    },

    extractDocument(response) {
        let payload = response?.document || response || {};

        if (payload?.data && typeof payload.data === 'object') {
            payload = payload.data;
        }

        const files = Array.isArray(payload.files)
            ? payload.files.filter(Boolean)
            : (payload.file ? [payload.file] : []);
        const firstFile = files[0] || {};
        const rawUrl = firstFile.download_url
            || firstFile.url
            || payload.download_url
            || payload.pdf_url
            || payload.view_url
            || payload.file_url
            || '';
        const html = String(
            payload.preview_html
            || payload.html
            || '',
        );

        return {
            payload,
            files,
            url: rawUrl ? this.resolveUrl(rawUrl) : '',
            html,
            filename: String(
                firstFile.filename
                || payload.file_name
                || payload.filename
                || '',
            ),
            totalCards: Number(
                payload.total_passes
                || payload.total_cards
                || payload.staff_count
                || payload.count
                || 0,
            ),
            estimatedPages: Number(payload.estimated_pages || 0),
        };
    },

    renderPreviewDocument(documentData) {
        const frame = document.getElementById('staffSecurityPassPreviewFrame');
        const openButton = document.getElementById('openSecurityPassDocumentBtn');
        const printButton = document.getElementById('printSecurityPassDocumentBtn');

        if (!frame || !openButton || !printButton) return;

        delete frame.dataset.loaded;
        frame.removeAttribute('src');
        frame.srcdoc = '';
        frame.addEventListener('load', () => {
            frame.dataset.loaded = 'true';
        }, { once: true });

        /*
         * The embedded preview uses server-rendered HTML produced from the
         * same PrintService templates and styles as the PDF. The secure PDF
         * URL remains available to Open and Print through DownloadService.
         */
        if (documentData.html) {
            frame.srcdoc = documentData.html;
        } else if (documentData.url) {
            frame.src = documentData.url;
        } else {
            frame.srcdoc = `
                <!doctype html>
                <html lang="en">
                    <body>
                        <p>The server did not return a preview document.</p>
                    </body>
                </html>
            `;
        }

        openButton.disabled = !documentData.url;
        printButton.disabled = documentData.files.length === 0;
    },

    renderPreviewMetadata(pass, mode, bulkCount, documentData) {
        const container = document.getElementById('staffSecurityPassPreviewMeta');
        if (!container) return;

        if (mode === 'bulk') {
            container.innerHTML = `
                <div class="text-muted small">Batch</div>
                <div class="fw-semibold mb-3">${bulkCount} staff passes</div>
                <div class="text-muted small">Document</div>
                <div class="fw-semibold mb-3">${this.escapeHtml(documentData.filename || 'Generated PDF')}</div>
                <div class="text-muted small">Estimated pages</div>
                <div class="fw-semibold mb-3">${this.escapeHtml(documentData.estimatedPages || '—')}</div>
                <div class="text-muted small">Format</div>
                <div class="fw-semibold">Portrait lanyard security passes</div>
            `;
            return;
        }

        if (!pass) {
            container.innerHTML = '<span class="text-muted">No staff metadata available.</span>';
            return;
        }

        container.innerHTML = `
            <div class="text-muted small">Staff</div>
            <div class="fw-semibold mb-3">${this.escapeHtml(pass.fullName)}</div>
            <div class="text-muted small">Staff No.</div>
            <div class="fw-semibold mb-3">${this.escapeHtml(pass.staffNo || 'Not assigned')}</div>
            <div class="text-muted small">Department</div>
            <div class="fw-semibold mb-3">${this.escapeHtml(pass.departmentName || 'Not assigned')}</div>
            <div class="text-muted small">Position</div>
            <div class="fw-semibold mb-3">${this.escapeHtml(pass.position || 'Not assigned')}</div>
            <div class="text-muted small">Pass No.</div>
            <div class="fw-semibold mb-3">${this.escapeHtml(pass.passNumber || 'Not generated')}</div>
            ${this.renderStatusBadge(pass.status)}
        `;
    },

    openCurrentDocument() {
        const file = this.state.preview.files[0] || this.state.preview.documentUrl;

        if (!file) {
            this.notify('No secure security-pass document is available.', 'warning');
            return;
        }

        if (typeof window.KingswayFileLifecycle?.open !== 'function') {
            this.notify('The canonical file lifecycle client is unavailable.', 'error');
            return;
        }

        window.KingswayFileLifecycle.open(file);
    },

    async printCurrentDocument() {
        if (!this.state.preview.files.length) {
            this.notify('No security-pass PDF is ready for printing.', 'warning');
            return;
        }

        if (typeof window.PrintManager?.handleGeneratedFiles !== 'function') {
            this.notify('The canonical PrintManager is unavailable.', 'error');
            return;
        }

        try {
            await window.PrintManager.handleGeneratedFiles(
                {
                    ...this.state.preview.document,
                    files: this.state.preview.files,
                },
                {
                    download: false,
                    filename: this.state.preview.files[0]?.filename
                        || 'staff_security_pass.pdf',
                },
            );
        } catch (error) {
            console.error('[StaffSecurityPassesController] Secure print failed:', error);
            this.notify(
                error?.message || 'Unable to open the secure print document.',
                'error',
            );
        }
    },

    clearPreviewState() {
        this.state.preview = {
            staffId: null,
            passId: null,
            mode: null,
            document: null,
            documentUrl: '',
            documentHtml: '',
            files: [],
        };

        const frame = document.getElementById('staffSecurityPassPreviewFrame');
        if (frame) {
            delete frame.dataset.loaded;
            frame.removeAttribute('src');
            frame.srcdoc = '';
        }
    },

    selectAllVisible(checked) {
        this.state.filteredPasses.forEach((pass) => {
            if (checked) {
                this.state.selectedStaffIds.add(pass.staffId);
            } else {
                this.state.selectedStaffIds.delete(pass.staffId);
            }
        });

        this.renderTable();
        this.syncSelectionControls();
    },

    syncSelectionControls() {
        const selected = this.selectedPasses();
        const printableCount = selected.filter(
            (pass) => pass.status === 'generated' || pass.status === 'issued',
        ).length;

        this.setText('selectedPassesCount', selected.length);

        const previewButton = document.getElementById('previewSelectedPassesBtn');
        const printButton = document.getElementById('printSelectedPassesBtn');
        const regenerateButton = document.getElementById('regenerateSelectedPassesBtn');

        if (previewButton) previewButton.disabled = printableCount === 0;
        if (printButton) printButton.disabled = printableCount === 0;
        if (regenerateButton) regenerateButton.disabled = selected.length === 0;

        const visibleIds = this.state.filteredPasses.map((pass) => pass.staffId);
        const selectedVisible = visibleIds.filter(
            (staffId) => this.state.selectedStaffIds.has(staffId),
        ).length;

        const selectAll = document.getElementById('selectAllVisiblePasses');
        if (selectAll) {
            selectAll.checked = visibleIds.length > 0
                && selectedVisible === visibleIds.length;
            selectAll.indeterminate = selectedVisible > 0
                && selectedVisible < visibleIds.length;
        }

        window.StaffAccessController.apply(document);
    },

    selectedPasses() {
        return this.state.passes.filter(
            (pass) => this.state.selectedStaffIds.has(pass.staffId),
        );
    },

    findPass(staffId) {
        return this.state.passes.find(
            (pass) => pass.staffId === Number(staffId),
        ) || null;
    },

    removeInvalidSelections() {
        const validIds = new Set(this.state.passes.map((pass) => pass.staffId));

        Array.from(this.state.selectedStaffIds).forEach((staffId) => {
            if (!validIds.has(staffId)) {
                this.state.selectedStaffIds.delete(staffId);
            }
        });
    },

    renderLoading() {
        const body = document.getElementById('staffSecurityPassesBody');
        if (!body) return;

        body.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-4">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    Loading staff security passes...
                </td>
            </tr>
        `;
    },

    renderError(message) {
        const body = document.getElementById('staffSecurityPassesBody');
        if (!body) return;

        body.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-4 text-danger">
                    <i class="bi bi-exclamation-triangle fs-3 d-block mb-2"></i>
                    <span class="fw-semibold">${this.escapeHtml(message)}</span>
                </td>
            </tr>
        `;
        this.setText('passResultsSummary', 'Unable to load records');
    },

    formatDate(value) {
        if (!value) return '—';

        const date = new Date(`${String(value).slice(0, 10)}T00:00:00`);
        if (Number.isNaN(date.getTime())) return String(value);

        return date.toLocaleDateString('en-KE', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    },

    getModal(elementId) {
        const element = document.getElementById(elementId);

        if (!element) {
            throw new Error(`Modal #${elementId} was not found.`);
        }

        if (typeof window.bootstrap?.Modal !== 'function') {
            throw new Error('Bootstrap Modal is unavailable.');
        }

        return window.bootstrap.Modal.getOrCreateInstance(element);
    },

    hideModal(elementId) {
        const element = document.getElementById(elementId);
        if (!element || typeof window.bootstrap?.Modal !== 'function') return;

        window.bootstrap.Modal.getInstance(element)?.hide();
    },

    setButtonBusy(button, label) {
        if (!button) return;

        button.dataset.originalHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = `
            <span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
            ${this.escapeHtml(label)}
        `;
    },

    restoreButton(button) {
        if (!button) return;

        if (button.dataset.originalHtml) {
            button.innerHTML = button.dataset.originalHtml;
            delete button.dataset.originalHtml;
        }

        button.disabled = false;
        window.StaffAccessController.apply(document);
    },

    resolveUrl(value) {
        const url = String(value || '').trim();
        if (!url) return '';

        if (/^(https?:|blob:|data:)/i.test(url)) {
            return url;
        }

        const base = String(window.APP_BASE || '').replace(/\/$/, '');
        return `${base}/${url.replace(/^\//, '')}`;
    },

    valueOrDash(value) {
        const text = String(value || '').trim();
        return text
            ? this.escapeHtml(text)
            : '<span class="text-muted">—</span>';
    },

    setText(elementId, value) {
        const element = document.getElementById(elementId);
        if (element) element.textContent = String(value);
    },

    notify(message, type = 'info') {
        if (typeof window.API?.showNotification === 'function') {
            window.API.showNotification(message, type);
            return;
        }

        const alertClass = type === 'error' ? 'danger' : type;
        const alert = document.createElement('div');
        alert.className = `alert alert-${alertClass} alert-dismissible fade show`;
        alert.setAttribute('role', 'alert');
        alert.innerHTML = `
            ${this.escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        document.body.prepend(alert);
        window.setTimeout(() => alert.remove(), 5000);
    },

    escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = String(value ?? '');
        return element.innerHTML;
    },
};

window.StaffSecurityPassesController = StaffSecurityPassesController;
// Compatibility alias for existing route references while terminology migrates.
window.StaffIdCardsController = StaffSecurityPassesController;

function initializeStaffSecurityPassesController() {
    void StaffSecurityPassesController.init().catch((error) => {
        console.error(
            '[StaffSecurityPassesController] Page initialization failed:',
            error,
        );
    });
}

if (window.__APP_BOOTED__) {
    initializeStaffSecurityPassesController();
} else {
    window.addEventListener(
        'kingsway:ready',
        initializeStaffSecurityPassesController,
        { once: true },
    );
}
