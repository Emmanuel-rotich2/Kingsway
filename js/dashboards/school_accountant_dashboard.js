/**
 * School Accountant Dashboard Controller
 *
 * Composes the existing Finance reporting endpoints. No accountant-specific
 * dashboard service or duplicate finance query is introduced.
 */
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

        async apiMethod() {
            const [financialResponse, paymentsResponse] = await Promise.all([
                window.API.dashboard.getAccountantFinancial({}),
                window.API.dashboard.getAccountantPayments({ limit: 10 })
            ]);

            const financial = unwrap(financialResponse) || {};
            const paymentFeed = unwrap(paymentsResponse) || {};
            const fees = financial.fees || {};
            const collections = financial.collections || {};
            const payments = financial.payments || {};
            const expenses = financial.expenses || {};
            const budget = financial.budget || {};
            const trends = Array.isArray(paymentFeed.trends)
                ? paymentFeed.trends
                : [];
            const methods = Array.isArray(payments.by_method)
                ? payments.by_method
                : [];

            return {
                meta: {
                    scope_label: fees.current_term_name
                        || (financial.academic_year
                            ? `Academic year ${financial.academic_year}`
                            : 'Finance')
                },
                cards: {
                    month_collected: Number(collections.month_total || 0),
                    month_transactions: Number(collections.month_count || 0),
                    outstanding_fees: Number(fees.total_outstanding || 0),
                    defaulters: Number(fees.defaulters_count || 0),
                    reconciliation_rate: Number(payments.reconciliation_rate || 0),
                    unmatched_payments: Number(payments.unreconciled_count || 0),
                    pending_expenses: Number(expenses.pending_count || 0)
                },
                charts: {
                    collection_trend: {
                        labels: trends.map((row) => row.month || 'Unknown'),
                        data: trends.map((row) => Number(row.total_collected || 0))
                    },
                    payment_methods: {
                        labels: methods.map((row) => row.payment_method || 'Other'),
                        data: methods.map((row) => Number(row.total_amount || 0))
                    }
                },
                tables: {
                    recent_transactions: Array.isArray(paymentFeed.recent_transactions)
                        ? paymentFeed.recent_transactions
                        : [],
                    attention: [
                        {
                            label: 'Fee defaulters',
                            value: Number(fees.defaulters_count || 0),
                            status: Number(fees.defaulters_count || 0) > 0
                                ? 'attention'
                                : 'good'
                        },
                        {
                            label: 'Unreconciled M-Pesa',
                            value: Number(payments.unreconciled_count || 0),
                            status: Number(payments.unreconciled_count || 0) > 0
                                ? 'critical'
                                : 'good'
                        },
                        {
                            label: 'Pending expenses',
                            value: Number(expenses.pending_count || 0),
                            status: Number(expenses.pending_count || 0) > 0
                                ? 'attention'
                                : 'good'
                        },
                        {
                            label: 'Budget utilisation',
                            value: `${Number(budget.utilization_rate || 0).toFixed(1)}%`,
                            status: Number(budget.utilization_rate || 0) > 100
                                ? 'critical'
                                : 'good'
                        }
                    ]
                }
            };
        },

        cards: [
            {
                id: 'accCollected',
                path: 'cards.month_collected',
                format: 'currency',
                subtitleId: 'accCollectedSub',
                subtitle: (data) => `${Number(data.cards?.month_transactions || 0)} transactions`
            },
            {
                id: 'accOutstanding',
                path: 'cards.outstanding_fees',
                format: 'currency',
                subtitleId: 'accOutstandingSub',
                subtitle: (data) => `${Number(data.cards?.defaulters || 0)} students with arrears`
            },
            {
                id: 'accReconciliation',
                path: 'cards.reconciliation_rate',
                format: 'percent',
                subtitleId: 'accReconciliationSub',
                subtitle: (data) => `${Number(data.cards?.unmatched_payments || 0)} unmatched M-Pesa`
            },
            {
                id: 'accPendingExpenses',
                path: 'cards.pending_expenses',
                subtitleId: 'accPendingExpensesSub',
                subtitle: (data) => `${Number(data.cards?.pending_expenses || 0)} awaiting action`
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
            }
        ],
        tableDefinitions: [
            {
                bodyId: 'accTransactionsBody',
                path: 'tables.recent_transactions',
                emptyText: 'No recent confirmed transactions.',
                columns: [
                    { key: 'reference' },
                    { key: 'student_name' },
                    { key: 'method' },
                    { key: 'amount', format: 'currency' },
                    { key: 'payment_date', format: 'datetime' }
                ]
            },
            {
                bodyId: 'accAlertsBody',
                path: 'tables.attention',
                emptyText: 'No finance attention items.',
                columns: [
                    { key: 'label' },
                    { key: 'value' },
                    {
                        key: 'status',
                        render: (value, row, instance) => instance.badge(value, {
                            good: 'success',
                            attention: 'warning',
                            critical: 'danger'
                        })
                    }
                ]
            }
        ]
    });

    window.SchoolAccountantDashboardController = controller;
    window.schoolAccountantDashboardController = controller;
    DashboardBaseController.boot(controller, 'SchoolAccountantDashboardController');
})();
