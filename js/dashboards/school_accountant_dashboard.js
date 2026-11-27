(() => {
    'use strict';

    // The base controller renders tables and wires navigation. Its own methods
    // win over same-named definition keys, so table behaviour is added in
    // afterRender (a hook the base deliberately leaves to each dashboard).
    const collator = new Intl.Collator('en', { numeric: true, sensitivity: 'base' });

    const TRIAGE_REASONS = {
        needs_review: 'Flagged by the school accountant for manual review',
        duplicate: 'Marked as a duplicate by the school accountant',
        conflict: 'Marked as a conflict by the school accountant',
    };

    const number = (value, fallback = 0) => {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : fallback;
    };

    // Text-node escaping is not attribute-safe: quote characters must also be
    // encoded before a value is placed inside an HTML attribute.
    const escapeAttr = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    const firstArray = (...candidates) => candidates.find(Array.isArray) || [];

    // Composed sources are already unwrapped by Node; direct API fallbacks may
    // still carry one or two transport `data` envelopes.
    const normalizeSource = (source) => {
        let value = source;
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
        return value && typeof value === 'object' ? value : {};
    };

    const parseDate = (value) => {
        if (!value) return null;
        const date = new Date(String(value).replace(' ', 'T'));
        return Number.isNaN(date.getTime()) ? null : date;
    };

    const formatShortDate = (value) => {
        const date = parseDate(value);
        return date
            ? date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })
            : '—';
    };

    const formatLongDate = (value) => {
        const date = parseDate(value);
        return date
            ? date.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
            : '—';
    };

    const ageInDays = (value) => {
        const date = parseDate(value);
        if (!date) return null;
        return Math.max(0, Math.floor((Date.now() - date.getTime()) / 86400000));
    };

    const prettyMethod = (value) => {
        const method = String(value || '').toLowerCase();
        if (['mpesa', 'm-pesa', 'mobile_money'].includes(method)) return 'M-Pesa';
        if (['bank', 'bank_transfer', 'bank transfer'].includes(method)) return 'Bank transfer';
        if (method === 'cash') return 'Cash';
        return value ? String(value).replace(/_/g, ' ') : 'Unknown';
    };

    const buildDailyChart = (rows, dailyTarget) => {
        const ordered = rows
            .slice()
            .sort((a, b) => String(a.date || '').localeCompare(String(b.date || '')));
        const labels = [];
        const collected = [];
        const target = [];
        let runningTotal = 0;

        ordered.forEach((row) => {
            const day = Number(String(row.date || '').slice(-2)) || 1;
            runningTotal += number(row.total_amount);
            labels.push(formatShortDate(row.date));
            collected.push(Math.round(runningTotal));
            target.push(Math.round(dailyTarget * day));
        });

        return {
            labels,
            datasets: [
                {
                    label: 'Collected to date',
                    data: collected,
                    borderColor: '#0e7d61',
                    backgroundColor: 'rgba(14, 125, 97, 0.14)',
                    pointBackgroundColor: '#0e7d61',
                    fill: true,
                    tension: 0.3,
                },
                {
                    label: 'Run-rate target to date',
                    data: target,
                    borderColor: '#b45309',
                    backgroundColor: 'transparent',
                    borderDash: [6, 4],
                    pointRadius: 0,
                    fill: false,
                    tension: 0,
                },
            ],
        };
    };

    const buildAttention = ({ unmatchedRows, defaulterRows, pendingRows, payroll, position, fees, financePayments, ctrl }) => {
        const items = [];
        const unmatchedAmount = unmatchedRows.reduce((sum, row) => sum + number(row.amount), 0);
        const defaulterCount = number(fees.defaulters_count, defaulterRows.length);
        const topDefaulter = defaulterRows[0] || null;
        const pendingCount = pendingRows.length || number(fees.pending_count, 0);

        if (unmatchedRows.length > 0) {
            items.push({
                tone: 'warning',
                icon: 'bi-phone-flip',
                title: `${unmatchedRows.length} M-Pesa ${unmatchedRows.length === 1 ? 'exception' : 'exceptions'}`,
                detail: `${ctrl.formatValue(unmatchedAmount, 'currency')} not linked to a ledger payment. Triage without allocating money.`,
                action: 'Open reconciliation',
                route: 'mpesa_reconciliation',
            });
        }

        if (defaulterCount > 0) {
            items.push({
                tone: 'danger',
                icon: 'bi-person-x',
                title: `${defaulterCount} learners with overdue fees`,
                detail: topDefaulter
                    ? `Largest balance: ${topDefaulter.student_name} · ${ctrl.formatValue(topDefaulter.amount_owed, 'currency')}.`
                    : 'Review the learner fee ledger for the current academic year.',
                action: 'View fee accounts',
                route: 'student_fees',
                remind: topDefaulter && topDefaulter.student_id
                    ? { id: topDefaulter.student_id, name: topDefaulter.student_name }
                    : null,
            });
        }

        if (pendingCount > 0) {
            items.push({
                tone: 'info',
                icon: 'bi-hourglass-split',
                title: `${pendingCount} expenses awaiting review`,
                detail: 'Accountant view is read-only here; an authorized approver makes the decision.',
                action: 'View queue',
                route: 'transaction_approvals',
            });
        }

        const drafts = number(payroll.pending_approval);
        if (drafts > 0) {
            items.push({
                tone: 'secondary',
                icon: 'bi-people',
                title: `${drafts} payroll ${drafts === 1 ? 'draft' : 'drafts'}`,
                detail: `Current-month payroll has ${number(payroll.payslip_count)} live payslips.`,
                action: 'Open payroll',
                route: 'payroll',
            });
        }

        const postingGap = number(position.posting_gap);
        if (Math.abs(postingGap) > 0.01) {
            items.push({
                tone: 'secondary',
                icon: 'bi-journal-check',
                title: 'Allocation and cash posting differ',
                detail: `${ctrl.formatValue(Math.abs(postingGap), 'currency')} ${postingGap > 0 ? 'allocated above cash received' : 'received above ledger allocation'}. Confirm the posting position before reporting.`,
                action: 'Financial reports',
                route: 'finance_reports',
            });
        }

        if (!items.length && number(financePayments.reconciliation_rate, 0) >= 100) {
            items.push({
                tone: 'success',
                icon: 'bi-check2-circle',
                title: 'No open finance exceptions',
                detail: 'Reconciliation, arrears follow-up and approvals are clear for this scope.',
                action: 'Financial reports',
                route: 'finance_reports',
            });
        }

        return items;
    };

    const softRead = async (promise, label) => {
        try {
            return await promise;
        } catch (error) {
            console.warn(`[SchoolAccountantDashboardController] ${label} unavailable`, error);
            return {};
        }
    };

    const controller = DashboardBaseController.create({
        controllerName: 'SchoolAccountantDashboardController',
        rootId: 'accountantDashboard',
        refreshButtonId: 'accountantDashboardRefresh',
        stateId: 'accountantDashboardState',
        scopeId: 'accountantDashboardScope',
        lastUpdatedId: 'accountantDashboardLastUpdated',
        defaultPeriod: 'year',

        // Finance is not a five-minute-old report. Cache off: every refresh
        // reads the authoritative PHP endpoints through the composed view.
        viewSpec: {
            entity: 'dashboards',
            view: 'school-accountant-references',
            service: 'php',
            cacheTtlSeconds: 0,
            input: (filters) => filters,
        },

        tableState: { search: {}, sort: {} },
        actionsBound: false,

        composeFromSources(sources = {}, ctrl, filters = {}) {
            const financial = normalizeSource(sources.financial);
            const paymentsFeed = normalizeSource(sources.payments);
            const defaulterFeed = normalizeSource(sources.defaulters);
            const unmatchedFeed = normalizeSource(sources.unmatched);
            const ageingFeed = normalizeSource(sources.ageing);
            const classFeed = normalizeSource(sources.classCollection);
            const feeTypeFeed = normalizeSource(sources.feeTypes);
            const dailyFeed = normalizeSource(sources.daily);
            const cash = normalizeSource(sources.cash);
            const payroll = normalizeSource(sources.payroll);
            const pendingFeed = normalizeSource(sources.pendingExpenses);

            const position = financial.position || {};
            const fees = financial.fees || {};
            const financePayments = financial.payments || {};
            const ageingBlock = ageingFeed.arrears_ageing || {};

            const unmatchedRows = firstArray(unmatchedFeed.transactions).map((row) => ({
                ...row,
                id: row.id,
                phone_number: row.phone_number || row.mpesa_code || '—',
                amount: number(row.amount),
                age_days: ageInDays(row.transaction_date),
                status: row.matching_status || row.status || 'unmatched',
            }));
            const defaulterRows = firstArray(defaulterFeed.top_defaulters).map((row) => ({
                ...row,
                student_name: row.student_name || 'Unknown learner',
                class_name: row.class_name || '—',
                amount_owed: number(row.balance ?? number(row.total_due) - number(row.total_paid)),
                days_overdue: number(row.days_overdue),
            }));
            const ageingRows = firstArray(ageingBlock.buckets, ageingFeed.buckets).map((row) => ({
                bucket: row.bucket || 'Unknown',
                student_count: number(row.student_count),
                outstanding: number(row.outstanding),
            }));
            const classRows = firstArray(classFeed.pivot_by_class).map((row) => ({
                class_name: row.class_name || 'Unknown class',
                student_count: number(row.student_count),
                total_due: number(row.total_due),
                total_paid: number(row.total_paid),
                collection_rate: number(row.collection_rate),
            }));
            const dailyRows = firstArray(dailyFeed.pivot_daily);
            const feeTypeRows = firstArray(feeTypeFeed.pivot_by_fee_type);
            const methodRows = firstArray(financePayments.by_method);
            const pendingRows = firstArray(pendingFeed.expenses).map((row) => ({
                ...row,
                expense_number: row.expense_number || '—',
                description: row.description || '—',
                amount: number(row.amount),
                created_at: row.expense_date || row.created_at || '',
            }));
            const transactionRows = firstArray(paymentsFeed.recent_transactions).map((row) => ({
                ...row,
                reference: row.reference || `Payment ${row.id || ''}`.trim(),
                student_name: row.student_name || '—',
                class_name: row.class_name || '—',
                payment_method: prettyMethod(row.method || row.payment_method),
                amount: number(row.amount),
            }));

            const dailyTarget = number(position.term_billed) / 30;
            const unreconciledAmount = unmatchedRows.reduce((sum, row) => sum + number(row.amount), 0);
            const allocated = number(position.allocated);
            const cashReceived = number(position.cash_received);
            const budgetAllocated = number(financial.budget?.total_allocated);
            const budgetSpent = number(financial.budget?.total_spent);
            const reconciliationRate = financePayments.reconciliation_rate ?? null;

            return {
                meta: {
                    scope_label: position.scope_label
                        || financial.scope_label
                        || (financial.academic_year ? `Academic year ${financial.academic_year}` : 'Current academic year'),
                    as_of: position.as_of || financial.as_of || null,
                    data_sources_text: 'Learner fee ledger, confirmed payments, M-Pesa transactions, posted journal and live payslips.',
                    filters,
                },
                position,
                periodData: {
                    termName: position.term_name || financial.current_term_name || '',
                    collections: financial.collections || {},
                },
                cards: {
                    billed: number(position.billed),
                    allocated,
                    cash_received: cashReceived,
                    outstanding: number(position.outstanding),
                    credit_balance: number(position.credit_balance),
                    allocation_rate: number(position.collection_rate),
                    defaulters_count: number(fees.defaulters_count),
                    unreconciled_count: unmatchedRows.length,
                    unreconciled_amount: unreconciledAmount,
                    posting_gap: number(position.posting_gap),
                },
                charts: {
                    daily: buildDailyChart(dailyRows, dailyTarget),
                    methods: {
                        labels: methodRows.map((row) => prettyMethod(row.payment_method)),
                        datasets: [{
                            label: 'Confirmed receipts',
                            data: methodRows.map((row) => number(row.total_amount)),
                            backgroundColor: ['#0e7d61', '#2563eb', '#b45309', '#7c3aed', '#b71c1c'],
                        }],
                    },
                },
                chartNotes: {
                    dailyTarget,
                    dailyRows: dailyRows.length,
                    methods: methodRows.length
                        ? 'Confirmed payments in the selected scope only'
                        : 'No confirmed payment methods recorded in this scope',
                    feeTypes: feeTypeRows.length,
                },
                tables: {
                    ageing: ageingRows,
                    classes: classRows,
                    unreconciled: unmatchedRows,
                    defaulters: defaulterRows,
                    transactions: transactionRows,
                    pendingExpenses: pendingRows,
                },
                attention: buildAttention({
                    unmatchedRows,
                    defaulterRows,
                    pendingRows,
                    payroll,
                    position,
                    fees,
                    financePayments,
                    ctrl,
                }),
                widgets: {
                    budget: {
                        allocated: budgetAllocated,
                        spent: budgetSpent,
                        rate: budgetAllocated > 0 ? (budgetSpent / budgetAllocated) * 100 : null,
                    },
                    expenses: {
                        count: number(financial.expenses?.total_count),
                        amount: number(financial.expenses?.total_amount),
                        pending: pendingRows.length || number(financial.expenses?.pending_count),
                    },
                    reconciliation: {
                        rate: reconciliationRate,
                        status: financePayments.reconciliation_status || (reconciliationRate === null ? 'no_confirmed_payments' : 'unknown'),
                        unmatched: number(financePayments.unreconciled_count),
                        amount: number(financePayments.unreconciled_total),
                        queue: unmatchedRows.length,
                    },
                    cash,
                    payroll,
                },
            };
        },

        async apiMethod(filters = {}) {
            const financial = await window.API.dashboard.getAccountantFinancial(filters);
            const sources = await Promise.all([
                softRead(window.API.dashboard.getAccountantPayments({ ...filters, limit: 15 }), 'payments feed'),
                softRead(window.API.dashboard.getAccountantFinancial({ ...filters, pivot: 'top-defaulters', limit: 10 }), 'defaulters'),
                softRead(window.API.dashboard.getAccountantUnmatchedPayments({ ...filters, limit: 20 }), 'M-Pesa exceptions'),
                softRead(window.API.dashboard.getAccountantFinancial({ ...filters, pivot: 'arrears-ageing' }), 'receivables ageing'),
                softRead(window.API.dashboard.getAccountantFinancial({ ...filters, pivot: 'pivot-class' }), 'class collections'),
                softRead(window.API.dashboard.getAccountantFinancial({ ...filters, pivot: 'pivot-fee-type' }), 'fee types'),
                softRead(window.API.dashboard.getAccountantFinancial({ ...filters, pivot: 'pivot-daily' }), 'daily collections'),
                softRead(window.API.dashboard.getAccountantCashPosition(), 'cash position'),
                softRead(window.API.dashboard.getAccountantPayrollSummary(), 'payroll snapshot'),
                softRead(window.API.dashboard.getAccountantPendingExpenses({ ...filters, limit: 10 }), 'pending expenses'),
            ]);

            return this.composeFromSources({
                financial,
                payments: sources[0],
                defaulters: sources[1],
                unmatched: sources[2],
                ageing: sources[3],
                classCollection: sources[4],
                feeTypes: sources[5],
                daily: sources[6],
                cash: sources[7],
                payroll: sources[8],
                pendingExpenses: sources[9],
            }, this, filters);
        },

        cards: [
            {
                id: 'accBilled',
                path: 'cards.billed',
                format: 'currency',
                subtitleId: 'accBilledSub',
                subtitle: (data) => `${data.meta?.scope_label || 'Current academic year'}`,
            },
            {
                id: 'accAllocated',
                path: 'cards.allocated',
                format: 'currency',
                subtitleId: 'accAllocatedSub',
                subtitle: (data) => `${number(data.cards?.allocation_rate).toFixed(1)}% of billed obligations`,
            },
            {
                id: 'accCashReceived',
                path: 'cards.cash_received',
                format: 'currency',
                subtitleId: 'accCashReceivedSub',
                subtitle: () => 'Confirmed payments inside the scope',
            },
            {
                id: 'accOutstanding',
                path: 'cards.outstanding',
                format: 'currency',
                subtitleId: 'accOutstandingSub',
                subtitle: (data) => `${number(data.cards?.defaulters_count)} learners with overdue balances`,
            },
            {
                id: 'accCreditBalance',
                path: 'cards.credit_balance',
                format: 'currency',
                subtitleId: 'accCreditBalanceSub',
                subtitle: (data) => number(data.cards?.posting_gap) > 0
                    ? 'Allocations exceed cash posted — confirm posting'
                    : number(data.cards?.posting_gap) < 0
                        ? 'Cash received ahead of allocation'
                        : 'Cash received and ledger allocation agree',
            },
            {
                id: 'accUnreconciled',
                path: 'cards.unreconciled_count',
                format: 'number',
                subtitleId: 'accUnreconciledSub',
                subtitle: (data, instance) => data.cards?.unreconciled_count > 0
                    ? `${instance.formatValue(data.cards.unreconciled_amount, 'currency')} in the triage queue`
                    : 'No M-Pesa rows awaiting triage',
            },
        ],

        chartDefinitions: [
            {
                id: 'accCollectionChart',
                path: 'charts.daily',
                label: 'Collections',
                type: 'line',
                fill: true,
                emptyText: 'No confirmed payments are dated in the current month.',
            },
            {
                id: 'accMethodChart',
                path: 'charts.methods',
                label: 'Confirmed receipts',
                type: 'doughnut',
                showLegend: true,
                emptyText: 'No confirmed payment methods are recorded in this scope.',
            },
        ],

        tableDefinitions: [
            {
                bodyId: 'accAgeingBody',
                path: 'tables.ageing',
                emptyText: 'No outstanding fee balances in this academic year.',
                columns: [
                    { key: 'bucket' },
                    { key: 'student_count', format: 'number' },
                    { key: 'outstanding', format: 'currency' },
                ],
            },
            {
                bodyId: 'accClassBody',
                path: 'tables.classes',
                emptyText: 'No class collections recorded for this academic year.',
                columns: [
                    { key: 'class_name' },
                    { key: 'student_count', format: 'number' },
                    { key: 'total_due', format: 'currency' },
                    { key: 'total_paid', format: 'currency' },
                    { key: 'collection_rate', format: 'percent' },
                ],
            },
            {
                bodyId: 'accUnreconciledBody',
                path: 'tables.unreconciled',
                emptyText: 'No unreconciled M-Pesa payments.',
                columns: [
                    { key: 'phone_number' },
                    { key: 'amount', format: 'currency' },
                    {
                        key: 'age_days',
                        format: 'number',
                        value: (row) => row.age_days ?? '',
                    },
                    { key: 'transaction_date', format: 'datetime' },
                    {
                        key: 'status',
                        render: (value, row, instance) => instance.badge(prettyStatus(value || 'unmatched'), {
                            unmatched: 'warning text-dark',
                            needs_review: 'info text-dark',
                            duplicate: 'secondary',
                            conflict: 'danger',
                            reversed: 'dark',
                            pending: 'warning text-dark',
                            processed: 'info text-dark',
                            matched: 'success',
                            reconciled: 'success',
                        }),
                    },
                    {
                        key: 'action',
                        render: (value, row, instance) => {
                            if (!row.id) return '<span class="text-muted">—</span>';
                            return `
                                <div class="finance-action-stack">
                                    <button type="button" class="btn btn-sm btn-outline-warning"
                                        data-mpesa-id="${escapeAttr(row.id)}"
                                        data-mpesa-action="needs_review"
                                        aria-label="Flag M-Pesa ${escapeAttr(row.mpesa_code || row.id)} for review">Review</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                        data-mpesa-id="${escapeAttr(row.id)}"
                                        data-mpesa-action="duplicate"
                                        aria-label="Mark M-Pesa ${escapeAttr(row.mpesa_code || row.id)} as duplicate">Duplicate</button>
                                </div>`;
                        },
                    },
                ],
            },
            {
                bodyId: 'accDefaultersBody',
                path: 'tables.defaulters',
                emptyText: 'No fee defaulters in this academic year.',
                columns: [
                    { key: 'student_name' },
                    { key: 'class_name' },
                    { key: 'amount_owed', format: 'currency' },
                    { key: 'days_overdue', format: 'number' },
                    {
                        key: 'action',
                        render: (value, row, instance) => row.student_id
                            ? `<button type="button" class="btn btn-sm btn-outline-primary"
                                    data-remind-student="${escapeAttr(row.student_id)}"
                                    data-remind-name="${escapeAttr(row.student_name)}"
                                    aria-label="Send fee reminder to ${escapeAttr(row.student_name)}">Remind</button>`
                            : '<span class="text-muted">—</span>',
                    },
                ],
            },
            {
                bodyId: 'accTransactionsBody',
                path: 'tables.transactions',
                emptyText: 'No confirmed payments in the selected period.',
                columns: [
                    { key: 'reference' },
                    { key: 'student_name' },
                    { key: 'class_name' },
                    { key: 'payment_method' },
                    { key: 'amount', format: 'currency' },
                    { key: 'payment_date', format: 'datetime' },
                ],
            },
            {
                bodyId: 'accPendingExpensesBody',
                path: 'tables.pendingExpenses',
                emptyText: 'No expenses awaiting review.',
                columns: [
                    { key: 'expense_number' },
                    { key: 'description' },
                    { key: 'amount', format: 'currency' },
                    { key: 'created_at', format: 'datetime' },
                ],
            },
        ],

        bindTableActions() {
            if (this.actionsBound) return;
            const root = document.getElementById(this.rootId);
            if (!root) return;
            this.actionsBound = true;

            root.addEventListener('input', (event) => {
                const input = event.target.closest('[data-table-search]');
                if (!input) return;
                const bodyId = input.dataset.tableSearch;
                this.tableState.search[bodyId] = input.value.trim().toLocaleLowerCase();
                this.applySearch(bodyId);
                const visible = Array.from(document.querySelectorAll(`#${bodyId} tr[data-table-row]`))
                    .filter((row) => !row.hidden).length;
                this.announce(`${visible} ${visible === 1 ? 'row matches' : 'rows match'} “${input.value.trim()}”.`);
            });

            // Invoker commands dispatch one custom event per header activation.
            // The hidden status region announces the result once; the table
            // itself must never be a live region.
            // `command` does not bubble: it is dispatched on the table named by
            // commandfor, so each sortable table gets its own listener.
            root.querySelectorAll('[data-sort-key][commandfor]').forEach((button) => {
                const table = document.getElementById(button.getAttribute('commandfor'));
                if (!table || table.dataset.sortBound === 'true') return;
                table.dataset.sortBound = 'true';
                table.addEventListener('command', (event) => {
                    if (event.command !== '--sort') return;
                    const source = event.source?.closest?.('[data-sort-key]');
                    if (source) this.handleSortClick(source);
                });
            });

            root.addEventListener('click', (event) => {
                const reminder = event.target.closest('[data-remind-student]');
                if (reminder) {
                    event.preventDefault();
                    void this.sendFeeReminder(reminder);
                    return;
                }

                const triage = event.target.closest('[data-mpesa-id][data-mpesa-action]');
                if (triage) {
                    event.preventDefault();
                    void this.triageMpesaException(triage);
                }
            });

            // Commands are newly available. In browsers without them the
            // `command` event never fires, so keep the same behaviour reachable
            // by mouse, touch and keyboard through a click fallback.
            if (!('commandForElement' in HTMLButtonElement.prototype)) {
                root.addEventListener('click', (event) => {
                    const sortButton = event.target.closest('[data-sort-key]');
                    if (!sortButton) return;
                    this.handleSortClick(sortButton);
                });
            }
        },

        handleSortClick(button) {
            const table = button.closest('table');
            const body = table?.querySelector('tbody');
            if (!body) return;

            const key = button.dataset.sortKey;
            const current = this.tableState.sort[body.id];
            const direction = current?.key === key && current.direction === 'asc' ? 'desc' : 'asc';
            this.tableState.sort[body.id] = { key, direction };
            this.applySort(body.id);
            const rowCount = body.querySelectorAll('tr[data-table-row]').length;
            this.announce(`Sorted by ${button.textContent.trim()} ${direction === 'asc' ? 'ascending' : 'descending'}, ${rowCount} ${rowCount === 1 ? 'row' : 'rows'}.`);
        },

        applySort(bodyId) {
            const body = document.getElementById(bodyId);
            if (!body) return;

            const table = body.closest('table');
            table?.querySelectorAll('th[aria-sort]').forEach((header) => header.removeAttribute('aria-sort'));

            const state = this.tableState.sort[bodyId];
            if (!state?.key) return;

            const rows = Array.from(body.querySelectorAll('tr[data-table-row]'));
            const indexed = rows.map((row, index) => {
                const cell = row.querySelector(`[data-sort-cell="${state.key}"]`);
                return {
                    row,
                    index,
                    value: cell?.dataset.value ?? cell?.textContent?.trim() ?? '',
                    numeric: cell?.dataset.numeric === 'true',
                };
            });

            const direction = state.direction === 'desc' ? -1 : 1;
            indexed.sort((a, b) => {
                let comparison;
                if (a.numeric && b.numeric) {
                    comparison = number(a.value) - number(b.value);
                } else {
                    comparison = collator.compare(String(a.value), String(b.value));
                }
                return (comparison * direction) || (a.index - b.index);
            });

            // Move existing rows rather than rebuilding them: focus, hidden
            // state and event listeners stay intact. moveBefore is the stable
            // DOM primitive where available; insertBefore is the fallback.
            indexed.forEach(({ row }, targetIndex) => {
                const reference = body.children[targetIndex] || null;
                if (reference === row) return;
                if (typeof body.moveBefore === 'function') {
                    body.moveBefore(row, reference);
                } else {
                    body.insertBefore(row, reference);
                }
            });

            const activeHeader = table?.querySelector(`[data-sort-key="${state.key}"]`)?.closest('th');
            if (activeHeader) {
                activeHeader.setAttribute('aria-sort', direction === 1 ? 'ascending' : 'descending');
            }
        },

        applySearch(bodyId) {
            const body = document.getElementById(bodyId);
            if (!body) return;

            const term = this.tableState.search[bodyId] || '';
            let visible = 0;
            body.querySelectorAll('tr[data-table-row]').forEach((row) => {
                const matches = !term || row.textContent.toLocaleLowerCase().includes(term);
                row.hidden = !matches;
                if (matches) visible += 1;
            });

            const emptyRow = body.querySelector('tr[data-table-empty-search]');
            if (emptyRow) emptyRow.hidden = !(term && visible === 0);
        },

        decorateTables(data) {
            (this.tableDefinitions || []).forEach((definition) => {
                const body = document.getElementById(definition.bodyId);
                if (!body) return;

                const rows = this.getPath(data, definition.path);
                const tableRows = Array.isArray(rows) ? rows : [];
                body.querySelectorAll('tr[data-table-empty-search]').forEach((row) => row.remove());

                Array.from(body.children).forEach((rowElement, rowIndex) => {
                    const row = tableRows[rowIndex];
                    if (!row) return;
                    rowElement.dataset.tableRow = 'true';

                    definition.columns.forEach((column, columnIndex) => {
                        const cell = rowElement.children[columnIndex];
                        if (!cell) return;
                        const rawValue = typeof column.value === 'function'
                            ? column.value(row, this)
                            : this.getPath(row, column.key);
                        const sortValue = typeof column.sortValue === 'function'
                            ? column.sortValue(row, this)
                            : rawValue;
                        cell.dataset.sortCell = column.key || String(columnIndex);
                        cell.dataset.value = String(sortValue ?? '');
                        cell.dataset.numeric = ['currency', 'number', 'percent'].includes(column.format) ? 'true' : 'false';
                    });
                });

                const emptyRow = document.createElement('tr');
                emptyRow.dataset.tableEmptySearch = 'true';
                emptyRow.hidden = true;
                emptyRow.innerHTML = `<td colspan="${definition.columns.length}" class="text-center text-muted py-3">No rows match this filter.</td>`;
                body.appendChild(emptyRow);

                this.applySearch(definition.bodyId);
                this.applySort(definition.bodyId);
            });
        },

        afterRender(data) {
            this.bindTableActions();
            this.decorateTables(data);
            this.renderAttention(data.attention || []);
            this.renderWidgets(data.widgets || {});
            this.renderNotes(data);
        },

        renderAttention(items) {
            const host = document.getElementById('accNeedsAttention');
            if (!host) return;

            host.replaceChildren();
            if (!items.length) {
                host.innerHTML = '<p class="text-muted small mb-0">No open finance items in this scope.</p>';
                return;
            }

            items.forEach((item) => {
                const card = document.createElement('article');
                card.className = `finance-attention-card is-${item.tone || 'secondary'}`;
                card.innerHTML = `
                    <span class="finance-attention-icon" aria-hidden="true"><i class="bi ${this.escapeHtml(item.icon || 'bi-info-circle')}"></i></span>
                    <span class="finance-attention-copy">
                        <strong>${this.escapeHtml(item.title)}</strong>
                        <span>${this.escapeHtml(item.detail)}</span>
                    </span>
                    <span class="finance-attention-actions">
                        ${item.remind?.id
                            ? `<button type="button" class="btn btn-sm btn-outline-primary"
                                    data-remind-student="${escapeAttr(item.remind.id)}"
                                    data-remind-name="${escapeAttr(item.remind.name)}">Remind</button>`
                            : ''}
                        ${item.route
                            ? `<button type="button" class="btn btn-sm btn-outline-dark" data-route="${escapeAttr(item.route)}">${this.escapeHtml(item.action || 'Open')}</button>`
                            : ''}
                    </span>`;
                host.appendChild(card);
            });
        },

        renderWidgets(widgets) {
            const budget = widgets.budget || {};
            const budgetHost = document.getElementById('accBudgetUtil');
            if (budgetHost) {
                if (!number(budget.allocated)) {
                    budgetHost.innerHTML = '<p class="text-muted small mb-0">No approved or active budget is recorded for this academic year.</p>';
                } else {
                    const rate = number(budget.rate);
                    const barClass = rate > 100 ? 'bg-danger' : rate > 80 ? 'bg-warning' : 'bg-success';
                    budgetHost.innerHTML = `
                        <div class="finance-widget-line">
                            <strong class="finance-widget-value">${this.escapeHtml(this.formatValue(rate, 'percent'))}</strong>
                            <span class="finance-widget-meta">${this.escapeHtml(this.formatValue(budget.spent, 'currency'))} of ${this.escapeHtml(this.formatValue(budget.allocated, 'currency'))}</span>
                        </div>
                        <div class="progress finance-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100"
                            aria-valuenow="${Math.round(rate)}" aria-label="Budget utilisation">
                            <div class="progress-bar ${barClass}" style="width:${Math.max(0, Math.min(rate, 100))}%"></div>
                        </div>`;
                }
            }

            const expenses = widgets.expenses || {};
            const expenseHost = document.getElementById('accExpenses');
            if (expenseHost) {
                expenseHost.innerHTML = `
                    <div class="finance-widget-line">
                        <strong class="finance-widget-value">${this.escapeHtml(this.formatValue(expenses.amount, 'currency'))}</strong>
                        <span class="finance-widget-meta">${number(expenses.count)} recorded expenses</span>
                    </div>
                    <p class="finance-widget-note mb-0">${number(expenses.pending) > 0
                        ? `${number(expenses.pending)} awaiting review — approval remains with an authorized approver.`
                        : 'No expenses awaiting review.'}</p>`;
            }

            const reconciliation = widgets.reconciliation || {};
            const reconciliationHost = document.getElementById('accReconciliation');
            if (reconciliationHost) {
                const unscored = reconciliation.rate === null || reconciliation.status === 'no_confirmed_payments';
                reconciliationHost.innerHTML = `
                    <div class="finance-widget-line">
                        <strong class="finance-widget-value">${unscored ? 'Not scored' : this.escapeHtml(this.formatValue(reconciliation.rate, 'percent'))}</strong>
                        <span class="finance-widget-meta">${unscored ? 'No confirmed payments in this scope' : 'of confirmed payments reconciled'}</span>
                    </div>
                    <p class="finance-widget-note mb-0">${number(reconciliation.queue)} rows in the triage queue · ${this.escapeHtml(this.formatValue(reconciliation.amount, 'currency'))} unlinked.</p>`;
            }

            const cash = widgets.cash || {};
            const cashHost = document.getElementById('accCashPosition');
            if (cashHost) {
                const counts = cash.account_counts || {};
                const unknown = Object.entries(cash.unposted_accounts || {})
                    .filter(([, count]) => number(count) > 0)
                    .map(([kind, count]) => `${number(count)} ${prettyMethod(kind).toLowerCase()}`);
                cashHost.innerHTML = `
                    <div class="finance-widget-line">
                        <strong class="finance-widget-value">${this.escapeHtml(this.formatValue(cash.total, 'currency'))}</strong>
                        <span class="finance-widget-meta">Posted balance across ${number(counts.bank) + number(counts.cash) + number(counts.mpesa)} linked accounts</span>
                    </div>
                    <div class="finance-widget-pairs">
                        <span>Bank <strong>${this.escapeHtml(this.formatValue(cash.bank_balance, 'currency'))}</strong></span>
                        <span>Cash <strong>${this.escapeHtml(this.formatValue(cash.cash_on_hand, 'currency'))}</strong></span>
                        <span>M-Pesa <strong>${this.escapeHtml(this.formatValue(cash.mpesa_float, 'currency'))}</strong></span>
                    </div>
                    ${unknown.length
                        ? `<p class="finance-widget-warning mb-0">Excluded as unknown: ${this.escapeHtml(unknown.join(', '))} ${unknown.length === 1 ? 'account' : 'accounts'} without a ledger link.</p>`
                        : ''}
                    <p class="finance-widget-note mb-0">${this.escapeHtml(cash.basis || 'Posted journal lines only.')}</p>`;
            }

            const payroll = widgets.payroll || {};
            const payrollHost = document.getElementById('accPayroll');
            if (payrollHost) {
                const monthName = number(payroll.month) && number(payroll.year)
                    ? new Date(number(payroll.year), number(payroll.month) - 1, 1).toLocaleString('en-GB', { month: 'long', year: 'numeric' })
                    : 'Current month';
                const deductions = number(payroll.paye) + number(payroll.nssf) + number(payroll.nhif_shif);
                payrollHost.innerHTML = `
                    <div class="finance-widget-line">
                        <strong class="finance-widget-value">${this.escapeHtml(this.formatValue(payroll.net_payroll, 'currency'))}</strong>
                        <span class="finance-widget-meta">Net payroll · ${this.escapeHtml(monthName)}</span>
                    </div>
                    <div class="finance-widget-pairs">
                        <span>Drafts <strong>${number(payroll.pending_approval)}</strong></span>
                        <span>Paid <strong>${number(payroll.paid_count)}</strong></span>
                        <span>Payslips <strong>${number(payroll.payslip_count)}</strong></span>
                        <span>Statutory <strong>${this.escapeHtml(this.formatValue(deductions, 'currency'))}</strong></span>
                    </div>
                    <p class="finance-widget-note mb-0">Gross ${this.escapeHtml(this.formatValue(payroll.gross_payroll, 'currency'))} · live payslips only.</p>`;
            }
        },

        renderNotes(data) {
            const sources = document.getElementById('accDataSource');
            if (sources) sources.textContent = data.meta?.data_sources_text || 'Source: school finance records';

            const asOf = document.getElementById('accDataAsOf');
            if (asOf) {
                const widgets = data.widgets || {};
                asOf.textContent = `Server ${formatLongDate(data.meta?.as_of)} · cash ${formatLongDate(widgets.cash?.as_of)} · payroll ${formatLongDate(widgets.payroll?.as_of)}`;
            }

            const definition = document.getElementById('accDefinitionNote');
            if (definition) {
                const bases = data.position?.bases || {};
                const parts = [
                    bases.billed && `Billed: ${bases.billed}`,
                    bases.allocated && `Allocated: ${bases.allocated}`,
                    bases.cash_received && `Cash received: ${bases.cash_received}`,
                    bases.outstanding && `Outstanding: ${bases.outstanding}`,
                ].filter(Boolean);
                const sentence = parts.length
                    ? `${parts.join(' ')} All figures are for the selected academic year; cash and ledger allocation are shown separately because they can differ in timing.`
                    : 'Billed, allocated, cash received and outstanding are reported separately because they come from different records.';
                const filters = data.meta?.filters || {};
                const periodLabels = {
                    today: 'Today', week: 'This week', month: 'This month', term: 'This term',
                };
                const periodName = periodLabels[filters.period] || 'Selected period';
                const scopeNote = filters.period && filters.period !== 'year' && filters.date_from && filters.date_to
                    ? ` ${periodName} (${formatShortDate(filters.date_from)}–${formatShortDate(filters.date_to)}) narrows the tables and dated charts only; the KPI cards above stay on the academic-year basis.`
                    : '';
                definition.textContent = sentence + scopeNote;
            }

            const target = number(data.chartNotes?.dailyTarget);
            const dailyNote = document.getElementById('accDailyTargetNote');
            if (dailyNote) {
                const termName = data.periodData?.termName ? ` (${data.periodData.termName})` : '';
                dailyNote.textContent = target > 0
                    ? `Run-rate target: ${this.formatValue(target, 'currency')} per day (current-term billed obligations${termName} ÷ 30). Lines show cumulative collections against that target.`
                    : 'No current-term billed obligations are available to calculate a run-rate target.';
            }

            const methodNote = document.getElementById('accMethodNote');
            if (methodNote && data.chartNotes?.methods) methodNote.textContent = data.chartNotes.methods;
        },

        announce(message) {
            const status = document.getElementById('accTableStatus');
            if (status) status.textContent = message;
        },

        async sendFeeReminder(button) {
            const studentId = button.dataset.remindStudent;
            if (!studentId) return;
            const name = button.dataset.remindName || 'the learner';
            button.disabled = true;
            try {
                await window.API.dashboard.sendAccountantFeeReminder(studentId);
                const message = `Fee reminder sent for ${name}.`;
                if (typeof window.showNotification === 'function') window.showNotification(message, 'success');
                this.announce(message);
            } catch (error) {
                const message = error?.message || 'Unable to send the fee reminder.';
                if (typeof window.showNotification === 'function') window.showNotification(message, 'error');
                this.announce(message);
            } finally {
                button.disabled = false;
            }
        },

        async triageMpesaException(button) {
            const mpesaId = button.dataset.mpesaId;
            const action = button.dataset.mpesaAction;
            const reason = TRIAGE_REASONS[action];
            if (!mpesaId || !reason) return;

            button.disabled = true;
            try {
                await window.API.payments.resolveMpesaException(mpesaId, action, reason);
                const message = `M-Pesa exception marked ${prettyStatus(action).toLowerCase()}. No money was allocated.`;
                if (typeof window.showNotification === 'function') window.showNotification(message, 'success');
                this.announce(message);
                await this.loadDashboard({ force: true, silent: true });
            } catch (error) {
                const message = error?.message || 'Unable to update the M-Pesa exception.';
                if (typeof window.showNotification === 'function') window.showNotification(message, 'error');
                this.announce(message);
                button.disabled = false;
            }
        },
    });

    function prettyStatus(value) {
        return String(value || 'unmatched')
            .replace(/_/g, ' ')
            .replace(/\b\w/g, (letter) => letter.toUpperCase());
    }

    window.SchoolAccountantDashboardController = controller;
    window.schoolAccountantDashboardController = controller;
    DashboardBaseController.boot(controller, 'SchoolAccountantDashboardController');
})();
