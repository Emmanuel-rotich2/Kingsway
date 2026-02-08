/**
 * Finance Controller
 * Handles finance management: fees, payments, payroll, budgets, reports
 * Integrates with /api/finance endpoints
 */

const financeController = {
    payments: [],
    payrolls: [],
    fees: [],
    budgets: [],
    filteredData: [],
    currentFilters: {},

    /**
     * Initialize controller
     */
    init: async function() {
        try {
            console.log('Loading finance data...');
            await Promise.all([
                this.loadPayments(),
                this.loadPayrolls(),
                this.loadFeesStructure()
            ]);
            this.checkUserPermissions();
            console.log('Finance management loaded successfully');
        } catch (error) {
            console.error('Error initializing finance controller:', error);
            showNotification('Failed to load finance management', 'error');
        }
    },

    // ============================================================================
    // PAYMENTS
    // ============================================================================

    /**
     * Load payments
     */
    loadPayments: async function() {
        try {
            const response = await API.finance.index();
            this.payments = response.data || response || [];
            this.filteredData = [...this.payments];
            this.renderPaymentsTable();
        } catch (error) {
            console.error('Error loading payments:', error);
            const container = document.getElementById('paymentsContainer');
            if (container) {
                container.innerHTML = '<div class="alert alert-danger">Failed to load payments</div>';
            }
        }
    },

    /**
     * Render payments table
     */
    renderPaymentsTable: function() {
        const container = document.getElementById('paymentsContainer');
        if (!container) return;

        if (this.filteredData.length === 0) {
            container.innerHTML = '<div class="alert alert-info">No payment records found.</div>';
            return;
        }

        let html = `
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead class="table-success">
                        <tr>
                            <th>Receipt No.</th>
                            <th>Student</th>
                            <th>Amount</th>
                            <th>Type</th>
                            <th>Payment Method</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        this.filteredData.forEach(payment => {
            const statusBadge = this.getPaymentStatusBadge(payment.status);
            
            html += `
                <tr>
                    <td><strong>${payment.receipt_no || payment.id}</strong></td>
                    <td>${payment.student_name || 'N/A'}</td>
                    <td>KES ${this.formatCurrency(payment.amount)}</td>
                    <td>${payment.payment_type || 'Fees'}</td>
                    <td><span class="badge bg-info">${payment.payment_method || 'N/A'}</span></td>
                    <td>${this.formatDate(payment.payment_date)}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-info" onclick="financeController.viewPayment(${payment.id})" title="View Details">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-success" onclick="financeController.generateReceipt(${payment.id})" title="Generate Receipt">
                                <i class="bi bi-printer"></i>
                            </button>
                            <button class="btn btn-warning" onclick="financeController.sendNotification(${payment.id})" title="Send Notification" data-permission="finance_notify">
                                <i class="bi bi-bell"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table></div>';
        container.innerHTML = html;
        this.checkUserPermissions();
    },

    /**
     * Get payment status badge
     */
    getPaymentStatusBadge: function(status) {
        const badges = {
            'completed': '<span class="badge bg-success">Completed</span>',
            'pending': '<span class="badge bg-warning">Pending</span>',
            'failed': '<span class="badge bg-danger">Failed</span>',
            'cancelled': '<span class="badge bg-secondary">Cancelled</span>'
        };
        return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
    },

    /**
     * Record payment
     */
    recordPayment: async function() {
        try {
            const data = {
                student_id: prompt('Student ID:'),
                amount: parseFloat(prompt('Amount:')),
                payment_type: prompt('Type (Fees/Transport/Boarding/Other):'),
                payment_method: prompt('Method (Cash/M-Pesa/Bank):'),
                reference_no: prompt('Reference Number:')
            };

            await API.finance.recordPayment(data);
            showNotification('Payment recorded successfully', 'success');
            await this.loadPayments();
        } catch (error) {
            console.error('Error recording payment:', error);
            showNotification('Failed to record payment', 'error');
        }
    },

    /**
     * View payment details
     */
    viewPayment: async function(paymentId) {
        try {
            const payment = await API.finance.get(paymentId);
            alert(`Payment Details:\n\nReceipt: ${payment.receipt_no}\nStudent: ${payment.student_name}\nAmount: KES ${payment.amount}\nMethod: ${payment.payment_method}\nDate: ${payment.payment_date}\nStatus: ${payment.status}`);
        } catch (error) {
            console.error('Error loading payment:', error);
            showNotification('Failed to load payment details', 'error');
        }
    },

    /**
     * Generate receipt
     */
    generateReceipt: async function(paymentId) {
        try {
            const response = await API.finance.generateReceipt(paymentId);
            showNotification('Receipt generated successfully', 'success');
            
            if (response.url || response.file_path) {
                window.open(response.url || response.file_path, '_blank');
            }
        } catch (error) {
            console.error('Error generating receipt:', error);
            showNotification('Failed to generate receipt', 'error');
        }
    },

    /**
     * Send payment notification
     */
    sendNotification: async function(paymentId) {
        try {
            await API.finance.sendNotification({
                payment_id: paymentId,
                notification_type: 'payment_confirmation'
            });
            showNotification('Notification sent successfully', 'success');
        } catch (error) {
            console.error('Error sending notification:', error);
            showNotification('Failed to send notification', 'error');
        }
    },

    // ============================================================================
    // PAYROLL
    // ============================================================================

    /**
     * Load payrolls
     */
    loadPayrolls: async function() {
        try {
            const response = await API.finance.listPayrolls();
            this.payrolls = response.data || response || [];
        } catch (error) {
            console.error('Error loading payrolls:', error);
            this.payrolls = [];
        }
    },

    /**
     * Create draft payroll
     */
    createDraftPayroll: async function() {
        try {
            const data = {
                payroll_month: prompt('Month (1-12):'),
                payroll_year: prompt('Year:'),
                description: prompt('Description:')
            };

            const response = await API.finance.createDraftPayroll(data);
            showNotification('Draft payroll created successfully', 'success');
            await this.loadPayrolls();
        } catch (error) {
            console.error('Error creating payroll:', error);
            showNotification('Failed to create payroll', 'error');
        }
    },

    /**
     * Calculate payroll
     */
    calculatePayroll: async function(payrollId) {
        try {
            await API.finance.calculatePayroll({ payroll_id: payrollId });
            showNotification('Payroll calculated successfully', 'success');
            await this.loadPayrolls();
        } catch (error) {
            console.error('Error calculating payroll:', error);
            showNotification('Failed to calculate payroll', 'error');
        }
    },

    /**
     * Verify payroll
     */
    verifyPayroll: async function(payrollId) {
        try {
            await API.finance.verifyPayroll({
                payroll_id: payrollId,
                verified_by: AuthContext.getUser().user_id
            });
            showNotification('Payroll verified successfully', 'success');
            await this.loadPayrolls();
        } catch (error) {
            console.error('Error verifying payroll:', error);
            showNotification('Failed to verify payroll', 'error');
        }
    },

    /**
     * Approve payroll
     */
    approvePayroll: async function(payrollId) {
        if (!confirm('Approve this payroll for processing?')) return;

        try {
            await API.finance.approvePayroll({
                payroll_id: payrollId,
                approved_by: AuthContext.getUser().user_id
            });
            showNotification('Payroll approved successfully', 'success');
            await this.loadPayrolls();
        } catch (error) {
            console.error('Error approving payroll:', error);
            showNotification('Failed to approve payroll', 'error');
        }
    },

    /**
     * Process payroll
     */
    processPayroll: async function(payrollId) {
        if (!confirm('Process this payroll? This will initiate payments.')) return;

        try {
            await API.finance.processPayroll({ payroll_id: payrollId });
            showNotification('Payroll processing initiated', 'success');
            await this.loadPayrolls();
        } catch (error) {
            console.error('Error processing payroll:', error);
            showNotification('Failed to process payroll', 'error');
        }
    },

    /**
     * View payroll summary
     */
    viewPayrollSummary: async function(payrollId) {
        try {
            const summary = await API.finance.getPayrollSummary(payrollId);
            
            let message = 'Payroll Summary:\n\n';
            message += `Total Staff: ${summary.total_staff || 0}\n`;
            message += `Gross Amount: KES ${this.formatCurrency(summary.gross_amount)}\n`;
            message += `Total Deductions: KES ${this.formatCurrency(summary.total_deductions)}\n`;
            message += `Net Amount: KES ${this.formatCurrency(summary.net_amount)}\n`;
            message += `Status: ${summary.status}\n`;
            
            alert(message);
        } catch (error) {
            console.error('Error loading payroll summary:', error);
            showNotification('Failed to load payroll summary', 'error');
        }
    },

    /**
     * Generate payroll report
     */
    generatePayrollReport: async function() {
        try {
            const data = {
                month: prompt('Month (1-12):'),
                year: prompt('Year:')
            };

            const response = await API.finance.generatePayrollReport(data);
            showNotification('Payroll report generated successfully', 'success');
            
            if (response.url || response.file_path) {
                window.open(response.url || response.file_path, '_blank');
            }
        } catch (error) {
            console.error('Error generating report:', error);
            showNotification('Failed to generate payroll report', 'error');
        }
    },

    // ============================================================================
    // FEES STRUCTURE
    // ============================================================================

    /**
     * Load fees structure
     */
    loadFeesStructure: async function() {
        try {
            const response = await API.finance.getAnnualSummary();
            this.fees = response.data || response || [];
        } catch (error) {
            console.error('Error loading fees structure:', error);
            this.fees = [];
        }
    },

    /**
     * Create annual fees structure
     */
    createFeesStructure: async function() {
        try {
            const data = {
                academic_year: prompt('Academic Year:'),
                class_id: prompt('Class ID:'),
                tuition_fee: parseFloat(prompt('Tuition Fee:')),
                boarding_fee: parseFloat(prompt('Boarding Fee (optional):')) || 0,
                transport_fee: parseFloat(prompt('Transport Fee (optional):')) || 0
            };

            await API.finance.createAnnualStructure(data);
            showNotification('Fees structure created successfully', 'success');
            await this.loadFeesStructure();
        } catch (error) {
            console.error('Error creating fees structure:', error);
            showNotification('Failed to create fees structure', 'error');
        }
    },

    /**
     * Approve fees structure
     */
    approveFeesStructure: async function(structureId) {
        try {
            await API.finance.approveStructure({
                structure_id: structureId,
                approved_by: AuthContext.getUser().user_id
            });
            showNotification('Fees structure approved successfully', 'success');
            await this.loadFeesStructure();
        } catch (error) {
            console.error('Error approving fees structure:', error);
            showNotification('Failed to approve fees structure', 'error');
        }
    },

    /**
     * Activate fees structure
     */
    activateFeesStructure: async function(structureId) {
        try {
            await API.finance.activateStructure({ structure_id: structureId });
            showNotification('Fees structure activated successfully', 'success');
            await this.loadFeesStructure();
        } catch (error) {
            console.error('Error activating fees structure:', error);
            showNotification('Failed to activate fees structure', 'error');
        }
    },

    // ============================================================================
    // BUDGETS
    // ============================================================================

    /**
     * Propose budget
     */
    proposeBudget: async function() {
        try {
            const data = {
                department: prompt('Department:'),
                fiscal_year: prompt('Fiscal Year:'),
                amount: parseFloat(prompt('Budget Amount:')),
                description: prompt('Description:')
            };

            await API.finance.proposeBudget(data);
            showNotification('Budget proposal submitted successfully', 'success');
        } catch (error) {
            console.error('Error proposing budget:', error);
            showNotification('Failed to propose budget', 'error');
        }
    },

    /**
     * Approve budget
     */
    approveBudget: async function(budgetId) {
        try {
            await API.finance.approveBudget({
                budget_id: budgetId,
                approved_by: AuthContext.getUser().user_id
            });
            showNotification('Budget approved successfully', 'success');
        } catch (error) {
            console.error('Error approving budget:', error);
            showNotification('Failed to approve budget', 'error');
        }
    },

    /**
     * Request funds
     */
    requestFunds: async function() {
        try {
            const data = {
                department: prompt('Department:'),
                amount: parseFloat(prompt('Amount:')),
                purpose: prompt('Purpose:')
            };

            await API.finance.requestFunds(data);
            showNotification('Funds request submitted successfully', 'success');
        } catch (error) {
            console.error('Error requesting funds:', error);
            showNotification('Failed to request funds', 'error');
        }
    },

    // ============================================================================
    // REPORTS
    // ============================================================================

    /**
     * View outstanding fees
     */
    viewOutstandingFees: async function() {
        try {
            const response = await API.finance.getOutstandingFees();
            
            let message = 'Outstanding Fees Summary:\n\n';
            message += `Total Outstanding: KES ${this.formatCurrency(response.total_outstanding)}\n`;
            message += `Number of Students: ${response.student_count || 0}\n`;
            
            alert(message);
        } catch (error) {
            console.error('Error loading outstanding fees:', error);
            showNotification('Failed to load outstanding fees', 'error');
        }
    },

    /**
     * Compare yearly collections
     */
    compareYearlyCollections: async function() {
        try {
            const response = await API.finance.compareYearlyCollections();
            
            let message = 'Yearly Collections Comparison:\n\n';
            if (response.data && response.data.length > 0) {
                response.data.forEach(year => {
                    message += `${year.year}: KES ${this.formatCurrency(year.total_collected)}\n`;
                });
            }
            
            alert(message);
        } catch (error) {
            console.error('Error comparing collections:', error);
            showNotification('Failed to compare yearly collections', 'error');
        }
    },

    // ============================================================================
    // UTILITIES
    // ============================================================================

    /**
     * Format currency
     */
    formatCurrency: function(amount) {
        return parseFloat(amount || 0).toLocaleString('en-KE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    },

    /**
     * Format date
     */
    formatDate: function(date) {
        if (!date) return 'N/A';
        return new Date(date).toLocaleDateString('en-GB');
    },

    /**
     * Check user permissions
     */
    checkUserPermissions: function() {
        const currentUser = AuthContext.getUser();
        if (!currentUser || !currentUser.permissions) return;

        document.querySelectorAll('[data-permission]').forEach(btn => {
            const requiredPerm = btn.getAttribute('data-permission');
            if (!currentUser.permissions.includes(requiredPerm)) {
                btn.style.display = 'none';
            }
        });
    },

    /**
     * Show quick actions
     */
    showQuickActions: function() {
        alert('Quick Actions:\n1. Record Payment\n2. Create Payroll\n3. View Outstanding Fees\n4. Generate Reports\n5. Propose Budget');
    },

    /**
     * Export payments
     */
    exportPayments: function() {
        try {
            const csv = this.convertToCSV(this.filteredData);
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `payments_export_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            showNotification('Payments data exported successfully', 'success');
        } catch (error) {
            console.error('Error exporting payments:', error);
            showNotification('Failed to export payments data', 'error');
        }
    },

    /**
     * Convert to CSV
     */
    convertToCSV: function(data) {
        const headers = ['Receipt No', 'Student', 'Amount', 'Type', 'Method', 'Date', 'Status'];
        const rows = data.map(p => [
            p.receipt_no || p.id,
            p.student_name,
            p.amount,
            p.payment_type,
            p.payment_method,
            p.payment_date,
            p.status
        ]);
        
        return [headers, ...rows].map(row => row.join(',')).join('\n');
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('paymentsContainer') || document.getElementById('financeContainer')) {
        financeController.init();
    }
});

// ============================================================================
// Role-Based Finance Controller (Admin/Manager/Operator/Viewer Templates)
// ============================================================================

(function () {
    const FinanceController = {
        view: "manager",
        payments: [],
        expenses: [],
        manualTransactions: [],
        transactions: [],
        filtered: [],
        selected: new Set(),
        currentPage: 1,
        perPage: 10,
        charts: {},
        studentId: null,
        departmentId: null,

        init: async function (options = {}) {
            this.view = options.view || "manager";
            this.perPage = this.view === "admin" ? 12 : 10;
            this.bindFilters();
            this.bindTransactionTypeToggle();

            if (this.view === "viewer") {
                await this.loadViewerData();
                return;
            }

            await this.loadData();

            if (this.view === "operator") {
                await this.loadDepartments();
                await this.loadBudgetSummary();
            }
        },

        safeCall: async function (promise) {
            try {
                return await promise;
            } catch (error) {
                console.warn("FinanceController API call failed:", error?.message || error);
                return null;
            }
        },

        unwrapList: function (resp, keys = []) {
            if (!resp) return [];
            if (Array.isArray(resp)) return resp;
            if (Array.isArray(resp.data)) return resp.data;
            for (const key of keys) {
                if (Array.isArray(resp[key])) return resp[key];
                if (Array.isArray(resp.data?.[key])) return resp.data[key];
            }
            if (Array.isArray(resp.transactions)) return resp.transactions;
            return [];
        },

        bindFilters: function () {
            const search = document.getElementById("financeSearch");
            const typeFilter = document.getElementById("transactionTypeFilter");
            const categoryFilter = document.getElementById("categoryFilter");
            const dateFrom = document.getElementById("dateFromFilter");
            const dateTo = document.getElementById("dateToFilter");

            const handler = () => {
                this.currentPage = 1;
                this.applyFilters();
            };

            if (search) search.addEventListener("input", handler);
            if (typeFilter) typeFilter.addEventListener("change", handler);
            if (categoryFilter) categoryFilter.addEventListener("change", handler);
            if (dateFrom) dateFrom.addEventListener("change", handler);
            if (dateTo) dateTo.addEventListener("change", handler);
        },

        bindTransactionTypeToggle: function () {
            const typeSelect = document.getElementById("transaction_type");
            const studentGroup = document.getElementById("transactionStudentGroup");
            if (!typeSelect || !studentGroup) return;

            const toggle = () => {
                const isIncome = typeSelect.value === "income";
                studentGroup.style.display = isIncome ? "block" : "none";
            };

            typeSelect.addEventListener("change", toggle);
            toggle();
        },

        loadData: async function () {
            const [paymentsRes, expensesRes, manualRes] = await Promise.all([
                this.safeCall(API.finance.getTransactions({ type: "payments", limit: 500 })),
                this.safeCall(API.finance.getTransactions({ type: "expenses", limit: 500 })),
                this.safeCall(API.finance.getTransactions({ type: "transactions", limit: 500 }))
            ]);

            this.payments = this.unwrapList(paymentsRes, ["payments"]);
            this.expenses = this.unwrapList(expensesRes, ["expenses"]);
            this.manualTransactions = this.unwrapList(manualRes, ["transactions"]);

            this.buildTransactions();
            this.populateCategoryFilter();
            this.applyFilters();
            this.renderStats();
            this.renderCharts();
            if (this.view === "admin") {
                this.loadPendingApprovals();
            }
        },

        buildTransactions: function () {
            const normalized = [];

            this.payments.forEach((p) => {
                const amount = parseFloat(p.amount_paid ?? p.amount ?? p.total_amount ?? 0);
                normalized.push({
                    id: p.id,
                    source: "payment",
                    type: "income",
                    category: p.payment_type || p.fee_type || "Payment",
                    description: p.description || `Payment - ${p.student_name || "Student"}`,
                    amount,
                    date: p.payment_date || p.created_at,
                    status: p.status || "completed",
                    recorded_by: p.received_by_name || p.recorded_by_name || p.received_by || "-",
                    raw: p
                });
            });

            this.expenses.forEach((e) => {
                const amount = parseFloat(e.amount ?? 0);
                normalized.push({
                    id: e.id,
                    source: "expense",
                    type: "expense",
                    category: e.expense_category || e.budget_category || "Expense",
                    description: e.description || e.vendor_name || "Expense",
                    amount,
                    date: e.expense_date || e.created_at,
                    status: e.status || "pending",
                    recorded_by: e.recorded_by_name || e.recorded_by || "-",
                    raw: e
                });
            });

            this.manualTransactions.forEach((t) => {
                const amount = parseFloat(t.amount ?? 0);
                normalized.push({
                    id: t.id,
                    source: "manual",
                    type: t.type || "income",
                    category: t.reference_no || "Manual",
                    description: t.notes || "Manual transaction",
                    amount,
                    date: t.transaction_date || t.created_at,
                    status: t.status || "completed",
                    recorded_by: t.processed_by_name || t.processed_by || "-",
                    raw: t
                });
            });

            normalized.sort((a, b) => new Date(b.date || 0) - new Date(a.date || 0));
            this.transactions = normalized;
        },

        populateCategoryFilter: function () {
            const categoryFilter = document.getElementById("categoryFilter");
            const categorySelect = document.getElementById("transaction_category");
            const categories = Array.from(new Set(this.transactions.map(t => t.category).filter(Boolean))).sort();

            if (categoryFilter) {
                const current = categoryFilter.value;
                categoryFilter.innerHTML = '<option value="">All Categories</option>';
                categories.forEach((cat) => {
                    const option = document.createElement("option");
                    option.value = cat;
                    option.textContent = cat;
                    categoryFilter.appendChild(option);
                });
                categoryFilter.value = current || "";
            }

            if (categorySelect) {
                const current = categorySelect.value;
                categorySelect.innerHTML = '<option value="">Select Category</option>';
                categories.forEach((cat) => {
                    const option = document.createElement("option");
                    option.value = cat;
                    option.textContent = cat;
                    categorySelect.appendChild(option);
                });
                categorySelect.value = current || "";
            }
        },

        applyFilters: function () {
            const search = (document.getElementById("financeSearch")?.value || "").toLowerCase();
            const typeFilter = document.getElementById("transactionTypeFilter")?.value || "";
            const categoryFilter = document.getElementById("categoryFilter")?.value || "";
            const dateFrom = document.getElementById("dateFromFilter")?.value || "";
            const dateTo = document.getElementById("dateToFilter")?.value || "";

            this.filtered = this.transactions.filter((t) => {
                if (typeFilter && t.type !== typeFilter) return false;
                if (categoryFilter && t.category !== categoryFilter) return false;
                if (dateFrom && t.date && new Date(t.date) < new Date(dateFrom)) return false;
                if (dateTo && t.date && new Date(t.date) > new Date(dateTo)) return false;

                if (search) {
                    const hay = `${t.category} ${t.description} ${t.type} ${t.status}`.toLowerCase();
                    if (!hay.includes(search)) return false;
                }
                return true;
            });

            this.renderTable();
            this.renderPagination();
        },

        renderTable: function () {
            const tbody = document.getElementById("financeTableBody");
            if (!tbody) return;

            if (this.filtered.length === 0) {
                let colspan = 9;
                if (this.view === "manager") colspan = 7;
                if (this.view === "operator") colspan = 5;
                tbody.innerHTML = `<tr><td colspan="${colspan}" class="loading-row">No transactions found</td></tr>`;
                return;
            }

            const start = (this.currentPage - 1) * this.perPage;
            const pageItems = this.filtered.slice(start, start + this.perPage);

            const rows = pageItems.map((t) => this.renderRow(t)).join("");
            tbody.innerHTML = rows;
        },

        renderRow: function (t) {
            const amount = this.formatCurrency(t.amount);
            const date = this.formatDate(t.date);
            const statusBadge = this.getStatusBadge(t.status);
            const typeBadge = t.type === "income"
                ? '<span class="badge bg-success">Income</span>'
                : '<span class="badge bg-danger">Expense</span>';

            const actions = this.renderActions(t);

            if (this.view === "admin") {
                const key = `${t.source}:${t.id}`;
                return `
                    <tr>
                        <td>
                            <input type="checkbox" class="row-checkbox" data-key="${key}"
                                   onchange="FinanceController.toggleSelection('${t.source}', ${t.id}, this.checked)">
                        </td>
                        <td>${date}</td>
                        <td>${typeBadge}</td>
                        <td>${this.escapeHtml(t.category)}</td>
                        <td>${this.escapeHtml(t.description)}</td>
                        <td class="text-end">${amount}</td>
                        <td>${this.escapeHtml(String(t.recorded_by || '-'))}</td>
                        <td>${statusBadge}</td>
                        <td>${actions}</td>
                    </tr>
                `;
            }

            if (this.view === "manager") {
                return `
                    <tr>
                        <td>${date}</td>
                        <td>${typeBadge}</td>
                        <td>${this.escapeHtml(t.category)}</td>
                        <td>${this.escapeHtml(t.description)}</td>
                        <td class="text-end">${amount}</td>
                        <td>${statusBadge}</td>
                        <td>${actions}</td>
                    </tr>
                `;
            }

            if (this.view === "operator") {
                return `
                    <tr>
                        <td>${date}</td>
                        <td>${typeBadge}</td>
                        <td>${this.escapeHtml(t.category)}</td>
                        <td>${this.escapeHtml(t.description)}</td>
                        <td class="text-end">${amount}</td>
                    </tr>
                `;
            }

            return "";
        },

        renderActions: function (t) {
            const viewBtn = `<button class="btn btn-sm btn-outline-primary me-1" onclick="FinanceController.viewTransaction('${t.source}', ${t.id})">View</button>`;
            const approveBtn = (this.view === "admin" && t.source === "expense" && t.status === "pending")
                ? `<button class="btn btn-sm btn-success me-1" onclick="FinanceController.approveExpense(${t.id})">Approve</button>`
                : "";
            const rejectBtn = (this.view === "admin" && t.source === "expense" && t.status === "pending")
                ? `<button class="btn btn-sm btn-outline-danger" onclick="FinanceController.rejectExpense(${t.id})">Reject</button>`
                : "";
            return `${viewBtn}${approveBtn}${rejectBtn}`;
        },

        renderPagination: function () {
            const paginationInfo = document.getElementById("paginationInfo");
            const paginationControls = document.getElementById("paginationControls");
            if (!paginationInfo || !paginationControls) return;

            const total = this.filtered.length;
            const start = total === 0 ? 0 : (this.currentPage - 1) * this.perPage + 1;
            const end = Math.min(this.currentPage * this.perPage, total);
            paginationInfo.textContent = `Showing ${start} to ${end} of ${total}`;

            const pages = Math.max(1, Math.ceil(total / this.perPage));
            let html = "";

            for (let i = 1; i <= pages; i++) {
                html += `<button class="btn btn-sm ${i === this.currentPage ? 'btn-primary' : 'btn-outline'}" onclick="FinanceController.goToPage(${i})">${i}</button>`;
            }
            paginationControls.innerHTML = html;
        },

        goToPage: function (page) {
            this.currentPage = page;
            this.renderTable();
            this.renderPagination();
        },

        renderStats: function () {
            const revenue = this.transactions
                .filter(t => t.type === "income")
                .reduce((sum, t) => sum + (t.amount || 0), 0);
            const expenses = this.transactions
                .filter(t => t.type === "expense")
                .reduce((sum, t) => sum + (t.amount || 0), 0);
            const pending = this.expenses.filter(e => (e.status || "").toLowerCase() === "pending").length;
            const net = revenue - expenses;

            if (document.getElementById("totalRevenue")) {
                document.getElementById("totalRevenue").textContent = `KES ${this.formatCurrency(revenue)}`;
            }
            if (document.getElementById("totalExpenses")) {
                document.getElementById("totalExpenses").textContent = `KES ${this.formatCurrency(expenses)}`;
            }
            if (document.getElementById("netBalance")) {
                document.getElementById("netBalance").textContent = `KES ${this.formatCurrency(net)}`;
            }
            if (document.getElementById("pendingApprovals")) {
                document.getElementById("pendingApprovals").textContent = pending;
            }
        },

        renderCharts: function () {
            if (!window.Chart) return;

            const monthLabels = this.getRecentMonths(6);
            const revenueSeries = monthLabels.map(m => this.sumForMonth("income", m.key));
            const expenseSeries = monthLabels.map(m => this.sumForMonth("expense", m.key));

            const revenueExpenseCanvas = document.getElementById("revenueExpenseChart");
            if (revenueExpenseCanvas) {
                this.destroyChart("revenueExpense");
                this.charts.revenueExpense = new Chart(revenueExpenseCanvas, {
                    type: "line",
                    data: {
                        labels: monthLabels.map(m => m.label),
                        datasets: [
                            {
                                label: "Income",
                                data: revenueSeries,
                                borderColor: "#22c55e",
                                backgroundColor: "rgba(34,197,94,0.2)",
                                tension: 0.3
                            },
                            {
                                label: "Expense",
                                data: expenseSeries,
                                borderColor: "#ef4444",
                                backgroundColor: "rgba(239,68,68,0.2)",
                                tension: 0.3
                            }
                        ]
                    }
                });
            }

            const expenseBreakdownCanvas = document.getElementById("expenseBreakdownChart");
            if (expenseBreakdownCanvas) {
                const breakdown = this.groupByCategory("expense");
                this.destroyChart("expenseBreakdown");
                this.charts.expenseBreakdown = new Chart(expenseBreakdownCanvas, {
                    type: "doughnut",
                    data: {
                        labels: Object.keys(breakdown),
                        datasets: [{
                            data: Object.values(breakdown),
                            backgroundColor: ["#ef4444", "#f59e0b", "#84cc16", "#10b981", "#6366f1"]
                        }]
                    }
                });
            }

            const categoryCanvas = document.getElementById("categoryChart");
            if (categoryCanvas) {
                const breakdown = this.groupByCategory();
                this.destroyChart("categoryChart");
                this.charts.categoryChart = new Chart(categoryCanvas, {
                    type: "bar",
                    data: {
                        labels: Object.keys(breakdown),
                        datasets: [{
                            label: "Amount",
                            data: Object.values(breakdown),
                            backgroundColor: "#22c55e"
                        }]
                    },
                    options: {
                        plugins: { legend: { display: false } }
                    }
                });
            }
        },

        destroyChart: function (key) {
            if (this.charts[key]) {
                this.charts[key].destroy();
                this.charts[key] = null;
            }
        },

        getRecentMonths: function (count) {
            const months = [];
            const now = new Date();
            for (let i = count - 1; i >= 0; i--) {
                const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
                const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;
                const label = d.toLocaleDateString("en-KE", { month: "short", year: "numeric" });
                months.push({ key, label });
            }
            return months;
        },

        sumForMonth: function (type, monthKey) {
            return this.transactions
                .filter(t => t.type === type && t.date && String(t.date).startsWith(monthKey))
                .reduce((sum, t) => sum + (t.amount || 0), 0);
        },

        groupByCategory: function (type = null) {
            const grouped = {};
            this.transactions.forEach((t) => {
                if (type && t.type !== type) return;
                const key = t.category || "Other";
                grouped[key] = (grouped[key] || 0) + (t.amount || 0);
            });
            return grouped;
        },

        toggleSelection: function (source, id, checked) {
            const key = `${source}:${id}`;
            if (checked) {
                this.selected.add(key);
            } else {
                this.selected.delete(key);
            }
            if (typeof window.updateBulkActions === "function") {
                window.updateBulkActions();
            }
        },

        syncSelectionFromDom: function () {
            this.selected.clear();
            document.querySelectorAll(".row-checkbox:checked").forEach((cb) => {
                const key = cb.getAttribute("data-key");
                if (key) this.selected.add(key);
            });
        },

        bulkApprove: async function () {
            const targets = Array.from(this.selected).filter(k => k.startsWith("expense:"));
            if (targets.length === 0) {
                this.notify("No expenses selected for approval", "warning");
                return;
            }
            for (const key of targets) {
                const id = key.split(":")[1];
                await this.approveExpense(parseInt(id, 10));
            }
            this.selected.clear();
            await this.loadData();
        },

        bulkReject: async function () {
            const targets = Array.from(this.selected).filter(k => k.startsWith("expense:"));
            if (targets.length === 0) {
                this.notify("No expenses selected for rejection", "warning");
                return;
            }
            for (const key of targets) {
                const id = key.split(":")[1];
                await this.rejectExpense(parseInt(id, 10));
            }
            this.selected.clear();
            await this.loadData();
        },

        bulkExport: function () {
            const targets = Array.from(this.selected);
            const rows = this.filtered.filter(t => targets.includes(`${t.source}:${t.id}`));
            this.exportCsv(rows.length ? rows : this.filtered, "finance_export");
        },

        bulkDelete: async function () {
            this.notify("Bulk delete is not enabled for finance records.", "warning");
        },

        loadPendingApprovals: function () {
            const list = document.getElementById("approvalList");
            if (!list) return;
            const pending = this.expenses.filter(e => (e.status || "").toLowerCase() === "pending");

            if (!pending.length) {
                list.innerHTML = '<div class="alert alert-info">No pending expense approvals.</div>';
                return;
            }

            list.innerHTML = pending.map((e) => `
                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                    <div>
                        <strong>${this.escapeHtml(e.description || "Expense")}</strong>
                        <div class="text-muted small">KES ${this.formatCurrency(e.amount)} • ${this.escapeHtml(e.expense_category || "Expense")}</div>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-success me-2" onclick="FinanceController.approveExpense(${e.id})">Approve</button>
                        <button class="btn btn-sm btn-outline-danger" onclick="FinanceController.rejectExpense(${e.id})">Reject</button>
                    </div>
                </div>
            `).join("");
        },

        approveExpense: async function (expenseId) {
            if (!expenseId) return;
            const response = await this.safeCall(API.finance.approveExpense(expenseId));
            if (response) {
                this.notify("Expense approved", "success");
                await this.loadData();
                this.loadPendingApprovals();
            }
        },

        rejectExpense: async function (expenseId) {
            if (!expenseId) return;
            const reason = prompt("Reason for rejection:");
            if (reason === null) return;
            const response = await this.safeCall(API.finance.rejectExpense(expenseId, reason));
            if (response) {
                this.notify("Expense rejected", "success");
                await this.loadData();
                this.loadPendingApprovals();
            }
        },

        viewTransaction: function (source, id) {
            const record = this.transactions.find(t => t.source === source && t.id === id);
            if (!record) return;
            const message = [
                `Type: ${record.type}`,
                `Category: ${record.category}`,
                `Amount: KES ${this.formatCurrency(record.amount)}`,
                `Date: ${this.formatDate(record.date)}`,
                `Status: ${record.status}`
            ].join("\n");
            alert(message);
        },

        exportReport: function () {
            this.exportCsv(this.filtered, "finance_report");
        },

        exportCsv: function (rows, filename) {
            const headers = ["Date", "Type", "Category", "Description", "Amount", "Status", "Source"];
            const dataRows = rows.map(r => [
                this.formatDate(r.date),
                r.type,
                r.category,
                (r.description || "").replace(/\\n/g, " "),
                r.amount,
                r.status,
                r.source
            ]);
            const csv = [headers, ...dataRows].map(r => r.join(",")).join("\n");
            const blob = new Blob([csv], { type: "text/csv" });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = `${filename}_${new Date().toISOString().split("T")[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        },

        saveTransaction: async function () {
            const type = document.getElementById("transaction_type")?.value;
            const category = document.getElementById("transaction_category")?.value;
            const amount = parseFloat(document.getElementById("transaction_amount")?.value || 0);
            const date = document.getElementById("transaction_date")?.value;
            const description = document.getElementById("transaction_description")?.value || "";
            const studentId = document.getElementById("transaction_student_id")?.value || "";

            if (!type || !category || !amount || !date) {
                this.notify("Please fill in all required fields", "warning");
                return;
            }

            try {
                if (type === "expense") {
                    await API.finance.create({
                        type: "expense",
                        description,
                        amount,
                        expense_category: category,
                        expense_date: date,
                        recorded_by: this.currentUserId(),
                        status: "pending"
                    });
                } else {
                    if (studentId) {
                        await API.finance.recordPayment({
                            type: "payment",
                            student_id: parseInt(studentId, 10),
                            amount,
                            payment_method: "cash",
                            payment_date: date,
                            reference_no: category,
                            notes: description
                        });
                    } else {
                        await API.finance.create({
                            type: "transaction",
                            transaction_type: "income",
                            amount,
                            transaction_date: date,
                            payment_method: "cash",
                            reference_no: category,
                            notes: description
                        });
                    }
                }

                this.notify("Transaction saved successfully", "success");
                const modal = document.getElementById("transactionModal");
                if (modal) modal.classList.remove("show");
                await this.loadData();
            } catch (error) {
                console.error("Failed to save transaction:", error);
                this.notify("Failed to save transaction", "error");
            }
        },

        loadDepartments: async function () {
            const select = document.getElementById("request_department");
            if (!select) return;

            const response = await this.safeCall(API.staff.getDepartments());
            const departments = this.unwrapList(response, ["departments"]);
            select.innerHTML = '<option value="">Select Department</option>';

            departments.forEach((dept) => {
                const option = document.createElement("option");
                option.value = dept.id;
                option.textContent = dept.name;
                select.appendChild(option);
            });

            const userDept = AuthContext.getUser()?.department_id || null;
            if (userDept) {
                select.value = userDept;
                this.departmentId = userDept;
            }

            select.addEventListener("change", () => {
                this.departmentId = select.value || null;
                this.loadBudgetSummary();
            });
        },

        loadBudgetSummary: async function () {
            if (!this.departmentId) {
                this.setBudgetDisplay(0, 0, 0);
                return;
            }

            const summary = await this.safeCall(API.finance.getBudgetSummary(this.departmentId));
            if (!summary) {
                this.setBudgetDisplay(0, 0, 0);
                return;
            }

            const available = summary.available ?? 0;
            const utilization = summary.utilization_percent ?? 0;
            const spent = summary.spent ?? 0;
            this.setBudgetDisplay(available, utilization, spent);
        },

        setBudgetDisplay: function (available, utilization, spent) {
            const availableEl = document.getElementById("budgetAvailable");
            const utilizedEl = document.getElementById("budgetUtilized");
            if (availableEl) {
                availableEl.textContent = `KES ${this.formatCurrency(available)}`;
            }
            if (utilizedEl) {
                utilizedEl.textContent = `${utilization}%`;
            }
        },

        submitRequest: async function () {
            const departmentId = document.getElementById("request_department")?.value;
            const item = document.getElementById("request_item")?.value;
            const quantity = document.getElementById("request_quantity")?.value;
            const cost = parseFloat(document.getElementById("request_cost")?.value || 0);
            const justification = document.getElementById("request_justification")?.value;

            if (!departmentId || !item || !quantity || !justification) {
                this.notify("Fill in all required fields", "warning");
                return;
            }

            if (!cost || cost <= 0) {
                this.notify("Estimated cost is required", "warning");
                return;
            }

            const reason = `${item} (Qty: ${quantity}). ${justification}`;
            const requestedBy = this.currentUserId();

            const response = await this.safeCall(API.finance.requestFunds({
                department_id: parseInt(departmentId, 10),
                amount: cost,
                reason,
                requested_by: requestedBy
            }));

            if (response) {
                this.notify("Request submitted successfully", "success");
                const modal = document.getElementById("requestModal");
                if (modal) modal.classList.remove("show");
            }
        },

        loadViewerData: async function () {
            this.studentId = await this.resolveStudentId();
            if (!this.studentId) {
                const statusEl = document.getElementById("feeStatus");
                if (statusEl) {
                    statusEl.innerHTML = '<span class="status-badge overdue">Student not linked to account</span>';
                }
                const list = document.getElementById("paymentHistoryList");
                if (list) list.innerHTML = '<div class="empty-item">Student not linked. Contact administration.</div>';
                return;
            }

            const balance = await this.safeCall(API.finance.getStudentBalance(this.studentId));
            const history = await this.safeCall(API.finance.getStudentPaymentHistory(this.studentId));

            const totalDue = balance?.total_fee ?? history?.summary?.total_due ?? 0;
            const amountPaid = balance?.amount_paid ?? history?.summary?.total_paid ?? 0;
            const balanceDue = balance?.balance ?? history?.summary?.total_balance ?? (totalDue - amountPaid);

            if (document.getElementById("currentTerm")) {
                document.getElementById("currentTerm").textContent = balance?.term_name || "Current Term";
            }
            if (document.getElementById("totalFee")) {
                document.getElementById("totalFee").textContent = `KES ${this.formatCurrency(totalDue)}`;
            }
            if (document.getElementById("amountPaid")) {
                document.getElementById("amountPaid").textContent = `KES ${this.formatCurrency(amountPaid)}`;
            }
            if (document.getElementById("balanceDue")) {
                document.getElementById("balanceDue").textContent = `KES ${this.formatCurrency(balanceDue)}`;
            }

            const statusEl = document.getElementById("feeStatus");
            if (statusEl) {
                if (balanceDue <= 0) {
                    statusEl.innerHTML = '<span class="status-badge paid">✅ Fully Paid</span>';
                } else if (amountPaid > 0) {
                    statusEl.innerHTML = '<span class="status-badge partial">⚠️ Partial Payment</span>';
                } else {
                    statusEl.innerHTML = '<span class="status-badge overdue">❌ Outstanding</span>';
                }
            }

            const historyList = document.getElementById("paymentHistoryList");
            const historyRows = history?.history || [];
            if (historyList) {
                if (!historyRows.length) {
                    historyList.innerHTML = '<div class="empty-item">No payment records found</div>';
                } else {
                    historyList.innerHTML = historyRows.map((record) => `
                        <div class="payment-item">
                            <div>
                                <div class="payment-date">${this.escapeHtml(record.term_name || record.term || record.academic_year || "")}</div>
                                <div class="payment-method">${this.escapeHtml(record.payment_method || "Fees")}</div>
                            </div>
                            <div class="payment-amount">KES ${this.formatCurrency(record.total_paid || record.amount_paid || 0)}</div>
                        </div>
                    `).join("");
                }
            }
        },

        resolveStudentId: async function () {
            const user = AuthContext.getUser() || {};
            const direct = user.student_id || user.studentId || user.linked_student_id || user.student?.id;
            if (direct) return direct;

            const admission = user.admission_no || user.admissionNo || null;
            if (admission) {
                return await this.lookupStudentId(admission);
            }

            const promptValue = prompt("Enter student admission number to load fee details:");
            if (!promptValue) return null;
            return await this.lookupStudentId(promptValue.trim());
        },

        lookupStudentId: async function (admissionNo) {
            const response = await this.safeCall(API.students.list({ search: admissionNo, limit: 10 }));
            const students = this.unwrapList(response, ["students"]);
            if (!students.length) {
                this.notify("Student not found", "warning");
                return null;
            }
            return students[0].id;
        },

        downloadStatement: async function () {
            if (!this.studentId) {
                this.studentId = await this.resolveStudentId();
            }
            if (!this.studentId) return;

            const statement = await this.safeCall(API.finance.getStudentFeeStatement(this.studentId));
            if (!statement) return;

            const obligations = statement.obligations || [];
            const payments = statement.payments || [];
            const rows = [];

            rows.push(["Section", "Date", "Description", "Amount", "Balance"]);
            obligations.forEach((o) => {
                rows.push([
                    "Obligation",
                    o.created_at || "",
                    o.fee_structure_name || "",
                    o.amount || o.total_fee || 0,
                    o.balance || 0
                ]);
            });

            payments.forEach((p) => {
                rows.push([
                    "Payment",
                    p.payment_date || "",
                    p.payment_method || "",
                    p.amount_paid || p.amount || 0,
                    ""
                ]);
            });

            const csv = rows.map(r => r.join(",")).join("\n");
            const blob = new Blob([csv], { type: "text/csv" });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = `fee_statement_${this.studentId}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        },

        getStatusBadge: function (status) {
            const value = String(status || "").toLowerCase();
            if (value === "approved" || value === "completed" || value === "paid") {
                return '<span class="badge bg-success">Approved</span>';
            }
            if (value === "pending") {
                return '<span class="badge bg-warning">Pending</span>';
            }
            if (value === "rejected") {
                return '<span class="badge bg-danger">Rejected</span>';
            }
            return '<span class="badge bg-secondary">Unknown</span>';
        },

        formatCurrency: function (value) {
            return new Intl.NumberFormat("en-KE", { minimumFractionDigits: 0 }).format(value || 0);
        },

        formatDate: function (value) {
            if (!value) return "";
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return value;
            return d.toLocaleDateString("en-KE", { year: "numeric", month: "short", day: "numeric" });
        },

        escapeHtml: function (str) {
            if (!str && str !== 0) return "";
            return String(str).replace(/[&<>"']/g, (m) => ({
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                "\"": "&quot;",
                "'": "&#39;"
            }[m]));
        },

        notify: function (message, type = "info") {
            if (typeof showNotification === "function") {
                showNotification(message, type);
            } else {
                alert(message);
            }
        },

        currentUserId: function () {
            const user = AuthContext.getUser() || {};
            return user.user_id || user.id || null;
        }
    };

    window.FinanceController = FinanceController;
    window.loadPendingApprovals = () => FinanceController.loadPendingApprovals();
    window.bulkApprove = () => FinanceController.bulkApprove();
    window.bulkReject = () => FinanceController.bulkReject();
    window.bulkExport = () => FinanceController.bulkExport();
    window.bulkDelete = () => FinanceController.bulkDelete();
})();
