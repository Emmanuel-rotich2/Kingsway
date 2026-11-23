/**
 * Read-only payroll ledger.
 *
 * This page only lists payroll records produced by the Finance workflow and
 * exposes their detailed payslips. Preparation, approval and disbursement are
 * deliberately not available here.
 */
(function (window, document) {
    'use strict';

    const MONTHS = [
        '',
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December',
    ];

    const payrollLedgerController = {
        rows: [],
        requestSequence: 0,
        searchTimer: null,
        currentPayslip: null,
        payslipSequence: 0,
        canExport: false,
        canPrint: false,
        initialized: false,

        init: async function () {
            if (this.initialized) return;
            this.initialized = true;

            await window.AuthContext?.ready?.();
            if (!window.AuthContext?.isAuthenticated?.()) {
                window.location.href = `${window.APP_BASE || ''}/index.php`;
                return;
            }

            if (window.StaffAccess?.require) {
                const allowed = await window.StaffAccess.require(
                    'staff.payroll.manage',
                    'You do not have permission to view the payroll ledger.'
                );
                if (!allowed) return;
            }

            this.configureCapabilities();
            await this.populateYearOptions();
            this.restoreFilters();
            this.bindEvents();
            this.updateActionState();
            await this.loadLedger();
        },

        configureCapabilities: function () {
            const auth = window.AuthContext;
            const allows = (method, modules) => {
                if (typeof auth?.[method] !== 'function') return true;
                return modules.some((module) => auth[method](module) === true);
            };

            // Payroll has its own fine-grained export/print permissions. Keep
            // the broader finance capability as a compatibility fallback for
            // installations that grant finance-wide access instead.
            this.canExport = allows('canExport', ['finance_payroll', 'finance']);
            this.canPrint = allows('canPrint', ['finance_payroll', 'finance']);
        },

        bindEvents: function () {
            const form = document.getElementById('payrollFilterForm');
            form?.addEventListener('submit', (event) => {
                event.preventDefault();
                this.loadLedger();
            });

            ['payrollYearFilter', 'payrollMonthFilter', 'payrollStatusFilter']
                .forEach((id) => {
                    document.getElementById(id)?.addEventListener('change', () => {
                        this.loadLedger();
                    });
                });

            const search = document.getElementById('payrollSearchFilter');
            search?.addEventListener('input', () => {
                window.clearTimeout(this.searchTimer);
                this.searchTimer = window.setTimeout(() => {
                    this.loadLedger();
                }, 350);
            });

            document.getElementById('clearPayrollFilters')?.addEventListener('click', () => {
                const yearFrom = document.getElementById('payrollYearFromFilter');
                const yearTo = document.getElementById('payrollYearToFilter');
                const monthFrom = document.getElementById('payrollMonthFromFilter');
                const monthTo = document.getElementById('payrollMonthToFilter');
                const status = document.getElementById('payrollStatusFilter');
                const searchInput = document.getElementById('payrollSearchFilter');
                // Reset to "Any" (empty value) for all filters
                if (yearFrom) yearFrom.value = '';
                if (yearTo) yearTo.value = '';
                if (monthFrom) monthFrom.value = '';
                if (monthTo) monthTo.value = '';
                if (status) status.value = '';
                if (searchInput) searchInput.value = '';
                this.loadLedger();
            });

            document.getElementById('exportPayrollCsv')?.addEventListener('click', () => {
                this.exportCsv();
            });
            document.getElementById('printPayrollLedger')?.addEventListener('click', () => {
                this.printLedger();
            });
            document.getElementById('printPayrollPayslip')?.addEventListener('click', () => {
                this.printPayslip();
            });

            // Batch payslip export dropdown
            document.querySelectorAll('[data-batch-format]').forEach((btn) => {
                btn.addEventListener('click', () => this.exportBatchPayslips(btn.dataset.batchFormat));
            });
            document.getElementById('batchByRunPdf')?.addEventListener('click', () => this.exportBatchByRun('pdf'));
            document.getElementById('batchByRunZip')?.addEventListener('click', () => this.exportBatchByRun('zip'));

            document.getElementById('payrollTableBody')?.addEventListener('click', (event) => {
                const button = event.target.closest('[data-action="view-payslip"]');
                if (!button) return;
                this.viewPayslip(button.dataset.id);
            });

            document.getElementById('payrollPayslipModal')?.addEventListener('hidden.bs.modal', () => {
                this.payslipSequence += 1;
                this.currentPayslip = null;
            });
        },

        populateYearOptions: async function () {
            const yearFrom = document.getElementById('payrollYearFromFilter');
            const yearTo = document.getElementById('payrollYearToFilter');
            if (!yearFrom || !yearTo) return;

            // Fetch academic years from the API
            let years = [];
            try {
                const response = await window.API.academic.getAllAcademicYears();
                const data = response?.data ?? response;
                if (Array.isArray(data)) {
                    years = data
                        .map((y) => y.year_code || y.year_name || y.id)
                        .filter((y) => y)
                        .map((y) => String(y).replace(/[^0-9]/g, ''))
                        .filter((y) => y.length === 4)
                        .map((y) => parseInt(y, 10));
                }
            } catch (error) {
                console.warn('Failed to fetch academic years, falling back to calendar years:', error);
            }

            // Fallback to calendar years if no academic years
            if (years.length === 0) {
                const currentYear = new Date().getFullYear();
                for (let year = currentYear - 10; year <= currentYear + 1; year += 1) {
                    years.push(year);
                }
            }

            // Also add years from existing data
            this.rows.forEach((row) => {
                const year = this.toNumber(row.payroll_year ?? row.year);
                if (year) years.push(year);
            });

            // Deduplicate and sort
            years = [...new Set(years)].sort((a, b) => b - a);

            const yearOptions = years.map((year) => new Option(String(year), String(year)));

            [yearFrom, yearTo].forEach((select) => {
                const selected = select.value;
                select.replaceChildren(new Option('Any', ''));
                yearOptions.forEach((opt) => select.append(opt.cloneNode(true)));

                const selectedYearExists = Array.from(select.options).some((option) => option.value === selected);
                select.value = selected === '' || selectedYearExists
                    ? selected
                    : (select === yearFrom ? String(years[years.length - 1] || new Date().getFullYear() - 1) : String(years[0] || new Date().getFullYear()));
            });
        },

        restoreFilters: function () {
            const params = new URLSearchParams(window.location.search);
            const yearFrom = document.getElementById('payrollYearFromFilter');
            const monthFrom = document.getElementById('payrollMonthFromFilter');
            const yearTo = document.getElementById('payrollYearToFilter');
            const monthTo = document.getElementById('payrollMonthToFilter');
            const status = document.getElementById('payrollStatusFilter');
            const search = document.getElementById('payrollSearchFilter');

            const requestedYearFrom = params.get('year_from') || '';
            const requestedMonthFrom = params.get('month_from') || '';
            const requestedYearTo = params.get('year_to') || '';
            const requestedMonthTo = params.get('month_to') || '';
            const requestedStatus = params.get('status') || '';
            const requestedSearch = params.get('search') || '';

            if (yearFrom && requestedYearFrom && Array.from(yearFrom.options).some((option) => option.value === requestedYearFrom)) {
                yearFrom.value = requestedYearFrom;
            }
            if (monthFrom && requestedMonthFrom && Array.from(monthFrom.options).some((option) => option.value === requestedMonthFrom)) {
                monthFrom.value = requestedMonthFrom;
            }
            if (yearTo && requestedYearTo && Array.from(yearTo.options).some((option) => option.value === requestedYearTo)) {
                yearTo.value = requestedYearTo;
            }
            if (monthTo && requestedMonthTo && Array.from(monthTo.options).some((option) => option.value === requestedMonthTo)) {
                monthTo.value = requestedMonthTo;
            }
            if (status && requestedStatus && Array.from(status.options).some((option) => option.value === requestedStatus)) {
                status.value = requestedStatus;
            }
            if (search) search.value = requestedSearch;
        },

        currentFilters: function () {
            return {
                year_from: document.getElementById('payrollYearFromFilter')?.value || '',
                month_from: document.getElementById('payrollMonthFromFilter')?.value || '',
                year_to: document.getElementById('payrollYearToFilter')?.value || '',
                month_to: document.getElementById('payrollMonthToFilter')?.value || '',
                status: document.getElementById('payrollStatusFilter')?.value || '',
                search: document.getElementById('payrollSearchFilter')?.value.trim() || '',
            };
        },

        updateUrl: function () {
            try {
                const filters = this.currentFilters();
                const url = new URL(window.location.href);
                Object.entries(filters).forEach(([key, value]) => {
                    if (value) url.searchParams.set(key, value);
                    else url.searchParams.delete(key);
                });
                window.history.replaceState(window.history.state, '', url.toString());
            } catch (_) {
                // The ledger remains usable when history is unavailable.
            }
        },

        loadLedger: async function () {
            const sequence = ++this.requestSequence;
            const filters = this.currentFilters();
            this.updateUrl();
            this.setBusy(true);
            this.renderLoading();

            try {
                const response = await window.API.finance.getPayrollList(filters);
                if (sequence !== this.requestSequence) return;

                this.rows = this.normalizeRows(response);
                this.populateYearOptions();
                this.renderRows();
                this.updateSummary();
                this.updateActionState();
                this.setBusy(false);
                this.updateResultsSummary();
            } catch (error) {
                if (sequence !== this.requestSequence) return;
                console.error('Payroll ledger load failed:', error);
                this.rows = [];
                this.renderError(error);
                this.updateSummary();
                this.updateActionState();
                this.setBusy(false);
                this.updateResultsSummary('Payroll records could not be loaded.');
            }
        },

        normalizeRows: function (response) {
            if (Array.isArray(response)) return response;
            const payload = response?.data ?? response;
            if (Array.isArray(payload)) return payload;
            const candidates = [
                payload?.payrolls,
                payload?.payroll,
                payload?.payslips,
                payload?.rows,
                response?.payrolls,
                response?.payroll,
                response?.payslips,
                response?.rows,
            ];
            return candidates.find(Array.isArray) || [];
        },

        setBusy: function (busy) {
            const wrapper = document.getElementById('payrollTableWrapper');
            if (wrapper) wrapper.setAttribute('aria-busy', busy ? 'true' : 'false');
            const summary = document.getElementById('payrollResultsSummary');
            if (busy && summary) summary.textContent = 'Loading payroll records…';
        },

        renderLoading: function () {
            const body = document.getElementById('payrollTableBody');
            if (!body) return;
            body.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-4">
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                        Loading payroll records…
                    </td>
                </tr>
            `;
        },

        renderRows: function () {
            const body = document.getElementById('payrollTableBody');
            if (!body) return;

            if (!this.rows.length) {
                this.renderEmpty();
                return;
            }

            body.innerHTML = this.rows.map((row) => {
                const id = row.id ?? row.payslip_id ?? '';
                const name = this.staffName(row);
                const period = this.periodLabel(row);
                const status = this.statusLabel(row);
                const runStatus = this.runStatusLabel(row);
                const basic = this.basicSalary(row);
                const allowances = this.allowances(row);
                const gross = this.grossPay(row);
                const deductions = this.deductions(row);
                const net = this.netPay(row);
                const meta = [
                    row.staff_no || row.staff_number ? `Staff ${row.staff_no || row.staff_number}` : '',
                    row.position || row.designation || '',
                    row.department || '',
                ].filter(Boolean).map((value) => this.escape(value)).join(' · ');

                return `
                    <tr>
                        <th scope="row">
                            <span class="payroll-staff-name">${this.escape(name || 'Unnamed staff member')}</span>
                            ${meta ? `<span class="payroll-staff-meta">${meta}</span>` : ''}
                        </th>
                        <td data-label="Period">${this.escape(period)}</td>
                        <td data-label="Basic salary" class="text-end payroll-money">${this.formatCurrency(basic)}</td>
                        <td data-label="Allowances" class="text-end payroll-money">${this.formatCurrency(allowances)}</td>
                        <td data-label="Gross pay" class="text-end payroll-money">${this.formatCurrency(gross)}</td>
                        <td data-label="Deductions" class="text-end payroll-money">${this.formatCurrency(deductions)}</td>
                        <td data-label="Net pay" class="text-end payroll-money payroll-net-pay">${this.formatCurrency(net)}</td>
                        <td data-label="Status">
                            <span class="payroll-status-stack">
                                <span class="badge ${this.statusBadgeClass(status)}">${this.escape(status)}</span>
                                ${runStatus ? `<span class="payroll-status-detail">${this.escape(runStatus)}</span>` : ''}
                            </span>
                        </td>
                        <td data-label="Payslip" class="text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-action="view-payslip" data-id="${this.escape(id)}" aria-label="View payslip for ${this.escape(name || 'staff member')}">
                                <i class="bi bi-receipt me-1" aria-hidden="true"></i>Payslip
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        },

        renderEmpty: function () {
            const body = document.getElementById('payrollTableBody');
            if (!body) return;
            const workflowUrl = `${window.APP_BASE || ''}/home.php?route=manage_payrolls`;
            body.innerHTML = `
                <tr>
                    <td colspan="9" class="payroll-table-message">
                        <div class="payroll-empty-state">
                            <i class="bi bi-journal-text fs-2 text-muted" aria-hidden="true"></i>
                            <h3>No payroll has been posted for this scope</h3>
                            <p>Payroll records appear here after Finance prepares a payroll run. Preparation, approval and disbursement remain in the Finance workflow.</p>
                            <a class="btn btn-sm btn-outline-secondary" href="${this.escape(workflowUrl)}">
                                <i class="bi bi-arrow-right-circle me-1" aria-hidden="true"></i>Open the payroll workflow
                            </a>
                        </div>
                    </td>
                </tr>
            `;
        },

        renderError: function (error) {
            const body = document.getElementById('payrollTableBody');
            if (!body) return;
            const message = error?.message && !/permission|unauthorized/i.test(error.message)
                ? error.message
                : 'Check your connection or access permissions, then try again.';
            body.innerHTML = `
                <tr>
                    <td colspan="9" class="payroll-table-message">
                        <div class="payroll-error-state">
                            <i class="bi bi-exclamation-triangle fs-2 text-danger" aria-hidden="true"></i>
                            <h3>Payroll records could not be loaded</h3>
                            <p>${this.escape(message)}</p>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-action="retry-payroll">
                                <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>Try again
                            </button>
                        </div>
                    </td>
                </tr>
            `;
            body.querySelector('[data-action="retry-payroll"]')?.addEventListener('click', () => {
                this.loadLedger();
            });
        },

        updateResultsSummary: function (override = '') {
            const summary = document.getElementById('payrollResultsSummary');
            if (!summary) return;
            if (override) {
                summary.textContent = override;
                return;
            }
            const count = this.rows.length;
            const filters = this.currentFilters();
            const scope = [
                filters.year || 'All years',
                filters.month ? MONTHS[Number(filters.month)] : 'All months',
                filters.status ? this.humanize(filters.status) : 'All statuses',
            ].join(' · ');
            summary.textContent = `${count.toLocaleString()} ${count === 1 ? 'record' : 'records'} shown · ${scope}`;
        },

        updateSummary: function () {
            const gross = this.rows.reduce((sum, row) => sum + this.grossPay(row), 0);
            const deductions = this.rows.reduce((sum, row) => sum + this.deductions(row), 0);
            const net = this.rows.reduce((sum, row) => sum + this.netPay(row), 0);

            const count = document.getElementById('payrollRecordCount');
            const grossTotal = document.getElementById('payrollGrossTotal');
            const deductionTotal = document.getElementById('payrollDeductionTotal');
            const netTotal = document.getElementById('payrollNetTotal');
            if (count) count.textContent = this.rows.length.toLocaleString();
            if (grossTotal) grossTotal.textContent = this.formatCurrency(gross);
            if (deductionTotal) deductionTotal.textContent = this.formatCurrency(deductions);
            if (netTotal) netTotal.textContent = this.formatCurrency(net);
        },

        updateActionState: function () {
            const exportButton = document.getElementById('exportPayrollCsv');
            const printButton = document.getElementById('printPayrollLedger');
            const batchExportBtn = document.getElementById('batchPayslipExportBtn');
            if (exportButton) {
                exportButton.hidden = !this.canExport;
                exportButton.disabled = !this.rows.length;
            }
            if (printButton) {
                printButton.hidden = !this.canPrint;
                printButton.disabled = !this.rows.length;
            }
            if (batchExportBtn) {
                const canBatch = this.canExport || this.canPrint;
                batchExportBtn.hidden = !canBatch || !this.rows.length;
            }
        },

        exportCsv: async function () {
            if (!this.canExport) {
                this.notify('You do not have permission to export payroll data.', 'warning');
                return;
            }
            if (!this.rows.length) {
                this.notify('There are no payroll records to export.', 'info');
                return;
            }

            const header = [
                'Staff No.',
                'Staff Member',
                'Position',
                'Department',
                'Period',
                'Basic Salary',
                'Allowances',
                'Gross Pay',
                'Deductions',
                'Net Pay',
                'Status',
                'Payroll Run Status',
            ];
            const lines = [header.map((value) => this.csvCell(value)).join(',')];
            this.rows.forEach((row) => {
                lines.push([
                    row.staff_no || row.staff_number || '',
                    this.staffName(row),
                    row.position || row.designation || '',
                    row.department || '',
                    this.periodLabel(row),
                    this.basicSalary(row).toFixed(2),
                    this.allowances(row).toFixed(2),
                    this.grossPay(row).toFixed(2),
                    this.deductions(row).toFixed(2),
                    this.netPay(row).toFixed(2),
                    this.statusLabel(row),
                    this.runStatusLabel(row),
                ].map((value) => this.csvCell(value)).join(','));
            });

            try {
                await window.KingswayFileLifecycle.exportText(
                    `\ufeff${lines.join('\r\n')}`,
                    `payroll_ledger_${new Date().toISOString().slice(0, 10)}.csv`,
                    'text/csv;charset=utf-8'
                );
            } catch (error) {
                console.error('Payroll CSV export failed:', error);
                this.notify('The payroll ledger could not be exported.', 'error');
            }
        },

        printLedger: function () {
            if (!this.canPrint) {
                this.notify('You do not have permission to print payroll data.', 'warning');
                return;
            }
            if (!this.rows.length) {
                this.notify('There are no payroll records to print.', 'info');
                return;
            }
            window.print();
        },

        viewPayslip: async function (id) {
            const payrollId = Number(id);
            if (!payrollId) return;

            const modalElement = document.getElementById('payrollPayslipModal');
            const body = document.getElementById('payrollPayslipModalBody');
            const printButton = document.getElementById('printPayrollPayslip');
            if (!modalElement || !body) return;

            const sequence = ++this.payslipSequence;
            this.currentPayslip = null;
            body.innerHTML = `
                <div class="text-center py-4" role="status">
                    <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                    Loading payslip…
                </div>
            `;
            if (printButton) printButton.hidden = true;
            window.bootstrap?.Modal.getOrCreateInstance(modalElement).show();

            try {
                const response = await window.API.finance.getDetailedPayslip(payrollId);
                if (sequence !== this.payslipSequence) return;
                const payslip = response?.data ?? response;
                if (!payslip || typeof payslip !== 'object' || !payslip.id) {
                    throw new Error('The detailed payslip is unavailable.');
                }
                this.currentPayslip = payslip;
                body.innerHTML = this.renderPayslip(payslip);
                if (printButton) {
                    printButton.hidden = !this.canPrint || !window.PrintManager?.printDedicatedPayslip;
                }
            } catch (error) {
                if (sequence !== this.payslipSequence) return;
                console.error('Payslip load failed:', error);
                body.innerHTML = `
                    <div class="payroll-error-state">
                        <i class="bi bi-exclamation-triangle fs-2 text-danger" aria-hidden="true"></i>
                        <h3>The payslip could not be loaded</h3>
                        <p>${this.escape(error?.message || 'Try again in a moment.')}</p>
                    </div>
                `;
            }
        },

        renderPayslip: function (data) {
            const school = data.school_profile || {};
            const schoolName = school.school_name || 'Kingsway Preparatory School';
            const period = this.periodLabel(data);
            const status = this.statusLabel(data);
            const basic = this.basicSalary(data);
            const allowances = this.allowances(data);
            const gross = this.grossPay(data);
            const net = this.netPay(data);
            const deductions = this.deductions(data);
            const children = Array.isArray(data.children_deductions) ? data.children_deductions : [];
            const childrenTotal = children.reduce(
                (sum, child) => sum + this.toNumber(child.deducted_amount ?? child.amount),
                0
            );

            const summary = [
                ['Staff member', this.staffName(data)],
                ['Staff number', data.staff_no || data.staff_number || '—'],
                ['Position', data.position || data.designation || '—'],
                ['Department', data.department || '—'],
                ['Pay period', period],
                ['Status', status],
            ];

            const deductionRows = [
                ['PAYE', this.toNumber(data.paye_tax ?? data.paye_deduction)],
                ['NSSF', this.toNumber(data.nssf_deduction ?? data.nssf_contribution)],
                ['SHIF', this.toNumber(data.shif_deduction ?? data.shif_contribution ?? data.nhif_deduction ?? data.nhif_contribution)],
                ['Housing levy', this.toNumber(data.housing_levy)],
                ['Children school fees', childrenTotal],
                ['Other deductions', this.toNumber(data.other_deductions ?? data.other_deductions_total)],
            ].filter(([, amount]) => amount > 0);

            return `
                <div class="payroll-payslip-heading text-center mb-3">
                    <h3 class="h5 mb-1">${this.escape(schoolName)}</h3>
                    <p class="text-muted mb-0">Staff payslip · ${this.escape(period)}</p>
                </div>
                <dl class="payroll-payslip-summary">
                    ${summary.map(([label, value]) => `
                        <div>
                            <dt>${this.escape(label)}</dt>
                            <dd>${this.escape(value || '—')}</dd>
                        </div>
                    `).join('')}
                </dl>
                <div class="table-responsive">
                    <table class="table table-sm align-middle payroll-payslip-table">
                        <caption class="visually-hidden">Payslip earnings and deductions</caption>
                        <thead>
                            <tr>
                                <th scope="col">Item</th>
                                <th scope="col" class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><th scope="row">Basic salary</th><td class="text-end payroll-money">${this.formatCurrency(basic)}</td></tr>
                            <tr><th scope="row">Allowances</th><td class="text-end payroll-money">${this.formatCurrency(allowances)}</td></tr>
                            <tr><th scope="row">Gross pay</th><td class="text-end payroll-money fw-semibold">${this.formatCurrency(gross)}</td></tr>
                            ${deductionRows.map(([label, amount]) => `
                                <tr>
                                    <th scope="row">${this.escape(label)}</th>
                                    <td class="text-end payroll-money text-danger">− ${this.formatCurrency(amount)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                        <tfoot>
                            <tr>
                                <th scope="row">Total deductions</th>
                                <td class="text-end payroll-money">${this.formatCurrency(deductions)}</td>
                            </tr>
                            <tr>
                                <th scope="row">Net pay</th>
                                <td class="text-end payroll-money payroll-net-pay">${this.formatCurrency(net)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                ${children.length ? `
                    <section class="mt-3" aria-labelledby="payrollChildrenDeductionsTitle">
                        <h4 class="h6" id="payrollChildrenDeductionsTitle">Children school fee deductions</h4>
                        <ul class="small text-muted mb-0 ps-3">
                            ${children.map((child) => `<li>${this.escape(child.student_name || 'Child')}${child.class_name ? ` · ${this.escape(child.class_name)}` : ''} — ${this.formatCurrency(this.toNumber(child.deducted_amount ?? child.amount))}</li>`).join('')}
                        </ul>
                    </section>
                ` : ''}
            `;
        },

        printPayslip: async function () {
            if (!this.canPrint) {
                this.notify('You do not have permission to print payslips.', 'warning');
                return;
            }
            if (!this.currentPayslip || !window.PrintManager?.printDedicatedPayslip) {
                this.notify('The payslip is not ready to print.', 'warning');
                return;
            }

            const payrollId = this.currentPayslip.id;
            if (!payrollId) {
                this.notify('The payslip is not ready to print.', 'warning');
                return;
            }

            try {
                // Authoritative payslip: server loads all financial data.
                await window.PrintManager.printDedicatedPayslip({
                    payroll_id: payrollId,
                });
            } catch (error) {
                console.error('Payslip print failed:', error);
                this.notify('The payslip could not be printed.', 'error');
            }
        },

        /**
         * Export filtered payslips as batch in specified format.
         */
        exportBatchPayslips: async function (format) {
            const canExport = this.canExport || this.canPrint;
            if (!canExport) {
                this.notify('You do not have permission to export payslips.', 'warning');
                return;
            }
            if (!this.rows.length) {
                this.notify('There are no payroll records to export.', 'info');
                return;
            }

            const payrollIds = this.rows.map((r) => r.id).filter(Boolean);
            if (!payrollIds.length) {
                this.notify('No valid payslip IDs found.', 'warning');
                return;
            }

            this.setBusy(true);
            try {
                await window.PrintManager.printPayslipsBatch({
                    payroll_ids: payrollIds,
                    format: format,
                });
                this.notify(`Batch payslips exported as ${format.toUpperCase()}.`, 'success');
            } catch (error) {
                console.error('Batch payslip export failed:', error);
                this.notify('Batch payslip export failed: ' + (error.message || 'Unknown error'), 'error');
            } finally {
                this.setBusy(false);
            }
        },

        /**
         * Export payslips for a specific payroll run.
         */
        exportBatchByRun: async function (format) {
            const canExport = this.canExport || this.canPrint;
            if (!canExport) {
                this.notify('You do not have permission to export payslips.', 'warning');
                return;
            }

            // Get the selected payroll run from filters
            const year = document.getElementById('payrollYearFilter')?.value;
            const month = document.getElementById('payrollMonthFilter')?.value;

            if (!year || !month) {
                this.notify('Please select a year and month to identify the payroll run.', 'warning');
                return;
            }

            this.setBusy(true);
            try {
                await window.PrintManager.printPayslipsBatch({
                    year_from: parseInt(year),
                    year_to: parseInt(year),
                    month_from: parseInt(month),
                    month_to: parseInt(month),
                    format: format,
                });
                this.notify(`Payroll run ${MONTHS[parseInt(month)]} ${year} exported as ${format.toUpperCase()}.`, 'success');
            } catch (error) {
                console.error('Batch payslip export by run failed:', error);
                this.notify('Batch payslip export failed: ' + (error.message || 'Unknown error'), 'error');
            } finally {
                this.setBusy(false);
            }
        },

        staffName: function (row) {
            return String(
                row.staff_name
                || `${row.first_name || ''} ${row.last_name || ''}`.trim()
                || ''
            ).trim();
        },

        periodLabel: function (row) {
            if (row.period_display) return String(row.period_display);
            const month = this.toNumber(row.payroll_month ?? row.month);
            const year = this.toNumber(row.payroll_year ?? row.year);
            const monthName = MONTHS[month] || (month ? `Month ${month}` : '');
            if (monthName && year) return `${monthName} ${year}`;
            if (year) return String(year);
            return '—';
        },

        statusLabel: function (row) {
            return this.humanize(row.status || row.payslip_status || row.payment_status || 'unknown');
        },

        runStatusLabel: function (row) {
            const status = row.payroll_run_status;
            return status ? `Run: ${this.humanize(status)}` : '';
        },

        statusBadgeClass: function (status) {
            const normalized = String(status).toLowerCase();
            if (['paid', 'completed'].includes(normalized)) return 'bg-success';
            if (['approved', 'released'].includes(normalized)) return 'bg-primary';
            if (['pending', 'submitted', 'processing'].includes(normalized)) return 'bg-warning text-dark';
            if (['cancelled', 'rejected', 'failed'].includes(normalized)) return 'bg-danger';
            return 'bg-secondary';
        },

        humanize: function (value) {
            return String(value || '')
                .replace(/[_-]+/g, ' ')
                .replace(/\b\w/g, (character) => character.toUpperCase());
        },

        basicSalary: function (row) {
            return this.toNumber(row.basic_salary ?? row.basic);
        },

        allowances: function (row) {
            return this.toNumber(row.allowances ?? row.allowances_total ?? row.total_allowances);
        },

        grossPay: function (row) {
            const raw = row.gross_salary ?? row.gross_pay;
            if (raw !== undefined && raw !== null && raw !== '') {
                return this.toNumber(raw);
            }
            return this.basicSalary(row) + this.allowances(row);
        },

        netPay: function (row) {
            return this.toNumber(row.net_salary ?? row.net_pay);
        },

        deductions: function (row) {
            const explicit = row.total_deductions ?? row.employee_deductions_total;
            if (explicit !== undefined && explicit !== null && explicit !== '') {
                return this.toNumber(explicit);
            }
            const gross = this.grossPay(row);
            const net = this.netPay(row);
            if (gross || net) return Math.max(0, gross - net);

            return this.toNumber(row.paye_tax ?? row.paye_deduction)
                + this.toNumber(row.nssf_deduction ?? row.nssf_contribution)
                + this.toNumber(row.shif_deduction ?? row.shif_contribution ?? row.nhif_deduction ?? row.nhif_contribution)
                + this.toNumber(row.housing_levy)
                + this.toNumber(row.child_fees_deduction ?? row.children_fees_deducted)
                + this.toNumber(row.other_deductions ?? row.other_deductions_total);
        },

        toNumber: function (value) {
            if (value === null || value === undefined || value === '') return 0;
            const number = Number(String(value).replace(/,/g, ''));
            return Number.isFinite(number) ? number : 0;
        },

        formatCurrency: function (value) {
            return `KES ${this.toNumber(value).toLocaleString('en-KE', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })}`;
        },

        csvCell: function (value) {
            let text = String(value ?? '');
            if (/^[=+\-@]/.test(text) && !/^-?\d+(?:\.\d+)?$/.test(text)) {
                text = `'${text}`;
            }
            return /[",\r\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
        },

        escape: function (value) {
            const element = document.createElement('div');
            element.textContent = String(value ?? '');
            return element.innerHTML;
        },

        notify: function (message, type) {
            if (typeof window.showNotification === 'function') {
                window.showNotification(message, type || 'info');
            } else if (typeof window.API?.showNotification === 'function') {
                window.API.showNotification(message, type || 'info');
            }
        },
    };

    window.payrollLedgerController = payrollLedgerController;
    document.addEventListener('DOMContentLoaded', () => {
        payrollLedgerController.init().catch((error) => {
            console.error('Payroll ledger initialization failed:', error);
            payrollLedgerController.setBusy(false);
            payrollLedgerController.renderError(error);
            payrollLedgerController.updateResultsSummary('Payroll records could not be loaded.');
        });
    }, { once: true });
})(window, document);
