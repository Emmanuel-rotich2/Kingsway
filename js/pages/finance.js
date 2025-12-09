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
