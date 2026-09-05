(() => {
    const unwrap = (response) => {
        let value = response;
        for (let depth = 0; depth < 4; depth += 1) {
            if (
                value
                && typeof value === 'object'
                && !Array.isArray(value)
                && Object.prototype.hasOwnProperty.call(value, 'data')
            ) {
                value = value.data;
                continue;
            }
            break;
        }
        return value;
    };

    const controller = DashboardBaseController.create({
        controllerName: 'SchoolAccountantDashboardController',
        rootId: 'accountantDashboard',
        refreshButtonId: 'accountantDashboardRefresh',
        stateId: 'accountantDashboardState',
        scopeId: 'accountantDashboardScope',
        lastUpdatedId: 'accountantDashboardLastUpdated',

        async apiMethod(filters = {}) {
            const period = filters.period;
            const [financialResponse, paymentsResponse, defaultersResponse, unmatchedResponse] = await Promise.all([
                window.API.dashboard.getAccountantFinancial(filters),
                window.API.dashboard.getAccountantPayments({ ...filters, limit: 15 }),
                window.API.dashboard.getAccountantFinancial({ ...filters, pivot: 'top-defaulters', limit: 10 }),
                window.API.dashboard.getAccountantUnmatchedPayments({ ...filters, limit: 20 })
            ]);

            const financial = unwrap(financialResponse) || {};
            const paymentFeed = unwrap(paymentsResponse) || {};
            const defaulterFeed = unwrap(defaultersResponse) || {};
            const unmatchedFeed = unwrap(unmatchedResponse) || {};
            const fees = financial.fees || {};
            const collections = financial.collections || {};
            const payments = financial.payments || {};
            const budget = financial.budget || {};
            const expenses = financial.expenses || {};
            const trends = Array.isArray(paymentFeed.trends)
                ? paymentFeed.trends
                : [];
            const methods = Array.isArray(payments.by_method)
                ? payments.by_method
                : [];
            const methodTotal = (names) => methods
                .filter((row) => names.includes(String(row.payment_method || row.method || '').toLowerCase()))
                .reduce((sum, row) => sum + Number(row.total_amount || 0), 0);
            const methodCount = (names) => methods
                .filter((row) => names.includes(String(row.payment_method || row.method || '').toLowerCase()))
                .reduce((sum, row) => sum + Number(row.transaction_count || 0), 0);
            const recentTransactions = (Array.isArray(paymentFeed.recent_transactions)
                ? paymentFeed.recent_transactions
                : []).map((row) => ({
                    ...row,
                    payment_method: row.payment_method || row.method || '—'
                }));
            const histogramBands = [
                { label: 'Below KES 1,000', min: 0, max: 1000 },
                { label: 'KES 1,000–4,999', min: 1000, max: 5000 },
                { label: 'KES 5,000–9,999', min: 5000, max: 10000 },
                { label: 'KES 10,000–19,999', min: 10000, max: 20000 },
                { label: 'KES 20,000+', min: 20000, max: Infinity }
            ];

            const periodKey = period || 'month';
            const collectedByPeriod = {
                today: Number(collections.today_total || 0),
                week: Number(collections.week_total || 0),
                month: Number(collections.month_total || 0),
                term: Number(fees.term_collected || 0),
                year: Number(fees.total_collected || 0)
            };
            const countByPeriod = {
                today: Number(collections.today_count || 0),
                week: Number(collections.week_count || 0),
                month: Number(collections.month_count || 0),
                term: Number(fees.term_collected_count || collections.month_count || 0),
                year: Number(fees.total_collected_count || collections.month_count || 0)
            };

            return {
                meta: {
                    scope_label: fees.current_term_name
                        || (financial.academic_year
                            ? `Academic year ${financial.academic_year}`
                            : 'Finance')
                },
                cards: {
                    current_term_collected: collectedByPeriod[periodKey] ?? collectedByPeriod.month,
                    total_outstanding: Number(fees.total_outstanding || 0),
                    mpesa_total: methodTotal(['mpesa', 'm-pesa']),
                    bank_total: methodTotal(['bank', 'bank_transfer', 'bank transfer']),
                    cash_total: methodTotal(['cash']),
                    unreconciled_count: Array.isArray(unmatchedFeed.transactions)
                        ? unmatchedFeed.transactions.length
                        : Number(payments.unreconciled_count || 0),
                    month_count: countByPeriod[periodKey] ?? countByPeriod.month,
                    defaulters_count: Number(fees.defaulters_count || 0),
                    mpesa_count: methodCount(['mpesa', 'm-pesa']),
                    bank_count: methodCount(['bank', 'bank_transfer', 'bank transfer']),
                    cash_count: methodCount(['cash'])
                },
                charts: {
                    collection_trend: {
                        labels: trends.map((r) => r.month || 'Unknown'),
                        datasets: [{
                            label: 'Collections',
                            data: trends.map((r) => Number(r.total_collected || 0)),
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, 0.14)',
                            pointBackgroundColor: '#2563eb',
                            fill: true,
                            tension: 0.35
                        }]
                    },
                    payment_methods: {
                        labels: methods.map((r) => r.payment_method || 'Other'),
                        datasets: [{
                            label: 'Payments',
                            data: methods.map((r) => Number(r.total_amount || 0)),
                            backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444']
                        }]
                    },
                    by_period: {
                        labels: ['Today', 'This week', 'This month', 'Current term', 'Academic year'],
                        datasets: [{
                            label: 'Collected',
                            data: [
                                Number(collections.today_total || 0),
                                Number(collections.week_total || 0),
                                Number(collections.month_total || 0),
                                Number(fees.term_collected || 0),
                                Number(fees.total_collected || 0)
                            ],
                            backgroundColor: '#7c3aed',
                            borderRadius: 5
                        }]
                    },
                    fee_status: {
                        labels: ['Collected', 'Outstanding'],
                        datasets: [{
                            label: 'Fee position',
                            data: [
                                Number(fees.total_collected || 0),
                                Number(fees.total_outstanding || 0)
                            ],
                            backgroundColor: ['#10b981', '#ef4444']
                        }]
                    },
                    payment_histogram: {
                        labels: histogramBands.map((band) => band.label),
                        datasets: [{
                            label: 'Recent transactions',
                            data: histogramBands.map((band) => recentTransactions.filter((transaction) => {
                                const amount = Number(transaction.amount || 0);
                                return amount >= band.min && amount < band.max;
                            }).length),
                            backgroundColor: '#0ea5e9',
                            borderRadius: 4,
                            barPercentage: 1,
                            categoryPercentage: 1
                        }]
                    }
                },
                tables: {
                    recent_transactions: recentTransactions,
                    unreconciled: Array.isArray(unmatchedFeed.transactions)
                        ? unmatchedFeed.transactions
                        : [],
                    defaulters: (Array.isArray(defaulterFeed.top_defaulters)
                        ? defaulterFeed.top_defaulters
                        : []).map((row) => ({
                            ...row,
                            amount_owed: Number(row.balance || 0)
                        }))
                },
                widgets: {
                    budget: {
                        allocated: Number(budget.total_allocated || 0),
                        spent: Number(budget.total_spent || 0),
                        rate: Number(budget.utilization_rate || 0)
                    },
                    expenses: {
                        count: Number(expenses.total_count || 0),
                        pending: Number(expenses.pending_count || 0),
                        amount: Number(expenses.total_amount || 0)
                    },
                    reconciliation: {
                        rate: Number(payments.reconciliation_rate || 0),
                        unmatched: Number(payments.unreconciled_count || 0),
                        amount: Number(payments.unreconciled_total || 0)
                    }
                }
            };
        },

        cards: [
            {
                id: 'accCollected',
                path: 'cards.current_term_collected',
                format: 'currency',
                subtitleId: 'accCollectedSub',
                subtitle: (d) => `${Number(d.cards?.month_count || 0)} transactions this period`
            },
            {
                id: 'accOutstanding',
                path: 'cards.total_outstanding',
                format: 'currency',
                subtitleId: 'accOutstandingSub',
                subtitle: (d) => `${Number(d.cards?.defaulters_count || 0)} students with arrears`
            },
            {
                id: 'accMpesa',
                path: 'cards.mpesa_total',
                format: 'currency',
                subtitleId: 'accMpesaSub',
                subtitle: (d) => `${Number(d.cards?.mpesa_count || 0)} M-Pesa payments`
            },
            {
                id: 'accBank',
                path: 'cards.bank_total',
                format: 'currency',
                subtitleId: 'accBankSub',
                subtitle: (d) => `${Number(d.cards?.bank_count || 0)} bank payments`
            },
            {
                id: 'accCash',
                path: 'cards.cash_total',
                format: 'currency',
                subtitleId: 'accCashSub',
                subtitle: (d) => `${Number(d.cards?.cash_count || 0)} cash payments`
            },
            {
                id: 'accUnreconciled',
                path: 'cards.unreconciled_count',
                format: 'number',
                subtitleId: 'accUnreconciledSub',
                subtitle: (d) => {
                    const count = Number(d.cards?.unreconciled_count || 0);
                    return count > 0 ? `${count} require attention` : 'All reconciled';
                },
                value: (d) => Number(d.cards?.unreconciled_count || 0)
            }
        ],

        chartDefinitions: [
            {
                id: 'accCollectionChart',
                path: 'charts.collection_trend',
                label: 'Collections',
                type: 'line',
                fill: true
            },
            {
                id: 'accMethodChart',
                path: 'charts.payment_methods',
                label: 'Payments',
                type: 'doughnut',
                showLegend: true
            },
            {
                id: 'accLevelChart',
                path: 'charts.by_period',
                label: 'Collected',
                type: 'bar'
            },
            {
                id: 'accFeeStatusChart',
                path: 'charts.fee_status',
                label: 'Fee position',
                type: 'pie',
                showLegend: true
            },
            {
                id: 'accPaymentHistogram',
                path: 'charts.payment_histogram',
                label: 'Recent transactions',
                type: 'bar'
            }
        ],

        tableDefinitions: [
            {
                bodyId: 'accTransactionsBody',
                path: 'tables.recent_transactions',
                emptyText: 'No recent payments.',
                columns: [
                    { key: 'reference' },
                    { key: 'student_name' },
                    { key: 'class_name' },
                    { key: 'payment_method' },
                    { key: 'amount', format: 'currency' },
                    { key: 'payment_date', format: 'datetime' }
                ]
            },
            {
                bodyId: 'accUnreconciledBody',
                path: 'tables.unreconciled',
                emptyText: 'No unreconciled M-Pesa payments.',
                columns: [
                    { key: 'phone_number' },
                    { key: 'amount', format: 'currency' },
                    { key: 'transaction_date', format: 'datetime' },
                    {
                        key: 'status',
                        render: (value, row, instance) => instance.badge(value || 'pending', {
                            pending: 'warning',
                            matched: 'success',
                            failed: 'danger'
                        })
                    }
                ]
            },
            {
                bodyId: 'accDefaultersBody',
                path: 'tables.defaulters',
                emptyText: 'No fee defaulters.',
                columns: [
                    { key: 'student_name' },
                    { key: 'class_name' },
                    { key: 'amount_owed', format: 'currency' },
                    { key: 'days_overdue', format: 'number' }
                ]
            }
        ],

        afterRender(data) {
            const widgets = data.widgets || {};
            const budget = widgets.budget || {};
            const expenses = widgets.expenses || {};
            const reconciliation = widgets.reconciliation || {};

            const budgetUtil = document.getElementById('accBudgetUtil');
            if (budgetUtil) {
                const rate = Number(budget.rate || 0);
                const spent = Number(budget.spent || 0);
                const allocated = Number(budget.allocated || 0);
                const barClass = rate > 100 ? 'bg-danger' : rate > 80 ? 'bg-warning' : 'bg-success';
                budgetUtil.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold fs-5">${this.formatValue(rate, 'percent')}</span>
                        <span class="text-muted small">${this.formatValue(spent, 'currency')} of ${this.formatValue(allocated, 'currency')}</span>
                    </div>
                    <div class="progress" style="height:12px;">
                        <div class="progress-bar ${barClass}" role="progressbar" style="width:${Math.min(rate, 100)}%"></div>
                    </div>`;
            }

            const expensesEl = document.getElementById('accExpenses');
            if (expensesEl) {
                expensesEl.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold fs-5">${this.formatValue(expenses.amount, 'currency')}</span>
                        <span class="text-muted small">${Number(expenses.count || 0)} expenses</span>
                    </div>
                    <span class="badge bg-${Number(expenses.pending || 0) > 0 ? 'warning text-dark' : 'success'}">${Number(expenses.pending || 0)} pending approval</span>`;
            }

            const reconciliationEl = document.getElementById('accReconciliation');
            if (reconciliationEl) {
                const rate = Number(reconciliation.rate || 0);
                reconciliationEl.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold fs-5">${this.formatValue(rate, 'percent')}</span>
                        <span class="text-muted small">reconciled</span>
                    </div>
                    <div class="small text-muted">${Number(reconciliation.unmatched || 0)} unmatched · ${this.formatValue(reconciliation.amount, 'currency')}</div>`;
            }
        }
    });

    window.SchoolAccountantDashboardController = controller;
    window.schoolAccountantDashboardController = controller;
    DashboardBaseController.boot(controller, 'SchoolAccountantDashboardController');
})();
