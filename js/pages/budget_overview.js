/**
 * Budget Overview Page Controller
 * Manages department budgets: summary stats, charts, table, add/edit modal, CSV export.
 */

(function () {
    "use strict";

    function showToast(message, type = 'success') {
        const el = document.createElement('div');
        el.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible position-fixed top-0 end-0 m-3`;
        el.style.zIndex = '9999';
        el.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 4000);
    }

    function kes(v) {
        return 'KES ' + Number(v || 0).toLocaleString('en-KE', { minimumFractionDigits: 2 });
    }

    function esc(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function statusBadge(status) {
        const map = { approved: 'success', proposed: 'warning', rejected: 'danger', draft: 'secondary' };
        return `<span class="badge bg-${map[status] || 'secondary'}">${esc(status || 'unknown')}</span>`;
    }

    const Controller = {
        data: [],
        charts: { bar: null, doughnut: null },

        init: async function () {
            if (!AuthContext.isAuthenticated()) {
                window.location.href = '/Kingsway/index.php';
                return;
            }
            this.bindEvents();
            await this.loadData();
        },

        loadData: async function () {
            try {
                const [summaryRes, proposalsRes] = await Promise.all([
                    window.API.finance.getDepartmentBudgetsSummary({}),
                    window.API.finance.getDepartmentBudgetsProposals({})
                ]);
                const summary = (summaryRes && summaryRes.data) ? summaryRes.data : (Array.isArray(summaryRes) ? summaryRes : []);
                const proposals = (proposalsRes && proposalsRes.data) ? proposalsRes.data : (Array.isArray(proposalsRes) ? proposalsRes : []);
                // Merge proposals into main data (proposals not already in summary)
                const summaryIds = new Set(summary.map(r => String(r.id)));
                this.data = [...summary, ...proposals.filter(p => !summaryIds.has(String(p.id)))];
                this.populateFilters();
                this.render();
            } catch (error) {
                console.error('Budget overview load error:', error);
                showToast('Failed to load budget data.', 'error');
            }
        },

        populateFilters: function () {
            const years = [...new Set(this.data.map(r => r.financial_year).filter(Boolean))].sort().reverse();
            const depts = [...new Set(this.data.map(r => r.department).filter(Boolean))].sort();

            const fyEl = document.getElementById('financialYear');
            const deptEl = document.getElementById('department');
            if (fyEl) {
                const cur = fyEl.value;
                fyEl.innerHTML = '<option value="">All Years</option>' +
                    years.map(y => `<option value="${esc(y)}"${y === cur ? ' selected' : ''}>${esc(y)}</option>`).join('');
            }
            if (deptEl) {
                const cur = deptEl.value;
                deptEl.innerHTML = '<option value="">All Departments</option>' +
                    depts.map(d => `<option value="${esc(d)}"${d === cur ? ' selected' : ''}>${esc(d)}</option>`).join('');
            }
        },

        getFiltered: function () {
            const fy = (document.getElementById('financialYear') || {}).value || '';
            const dept = (document.getElementById('department') || {}).value || '';
            const status = (document.getElementById('statusFilter') || {}).value || '';
            return this.data.filter(r =>
                (!fy || r.financial_year === fy) &&
                (!dept || r.department === dept) &&
                (!status || r.status === status)
            );
        },

        render: function () {
            const rows = this.getFiltered();
            this.renderStats(rows);
            this.renderTable(rows);
            this.renderCharts(rows);
        },

        renderStats: function (rows) {
            const totalBudget = rows.reduce((s, r) => s + Number(r.allocated_amount || 0), 0);
            const totalSpent  = rows.reduce((s, r) => s + Number(r.spent_amount || 0), 0);
            const remaining   = totalBudget - totalSpent;
            const utilization = totalBudget > 0 ? ((totalSpent / totalBudget) * 100).toFixed(1) : '0.0';
            const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
            setText('totalBudget', kes(totalBudget));
            setText('totalSpent',  kes(totalSpent));
            setText('remaining',   kes(remaining));
            setText('utilization', utilization + '%');
        },

        renderTable: function (rows) {
            const tbody = document.querySelector('#budgetTable');
            if (!tbody) return;

            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No budget records found.</td></tr>';
                this.renderFooter([]);
                return;
            }

            tbody.innerHTML = rows.map(r => {
                const alloc = Number(r.allocated_amount || 0);
                const spent = Number(r.spent_amount || 0);
                const rem   = alloc - spent;
                const pct   = alloc > 0 ? ((spent / alloc) * 100).toFixed(1) : '0.0';
                const barCls = pct > 90 ? 'bg-danger' : pct > 70 ? 'bg-warning' : 'bg-success';
                const approveBtn = r.status === 'proposed'
                    ? `<button class="btn btn-sm btn-outline-success approve-btn" data-id="${esc(r.id)}">Approve</button>`
                    : '';
                return `<tr>
                    <td>${esc(r.financial_year)}</td>
                    <td>${esc(r.department)}</td>
                    <td>${esc(r.category)}</td>
                    <td class="text-end">${kes(alloc)}</td>
                    <td class="text-end">${kes(spent)}</td>
                    <td class="text-end${rem < 0 ? ' text-danger fw-semibold' : ''}">${kes(rem)}</td>
                    <td style="min-width:120px">
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:6px">
                                <div class="progress-bar ${barCls}" style="width:${Math.min(Number(pct), 100)}%"></div>
                            </div>
                            <small class="text-nowrap">${pct}%</small>
                        </div>
                    </td>
                    <td>${statusBadge(r.status)}</td>
                    <td class="text-nowrap">
                        <button class="btn btn-sm btn-outline-primary me-1 edit-btn" data-id="${esc(r.id)}">Edit</button>
                        ${approveBtn}
                    </td>
                </tr>`;
            }).join('');

            this.renderFooter(rows);
            this.bindTableActions();
        },

        renderFooter: function (rows) {
            const alloc = rows.reduce((s, r) => s + Number(r.allocated_amount || 0), 0);
            const spent = rows.reduce((s, r) => s + Number(r.spent_amount || 0), 0);
            const rem   = alloc - spent;
            const pct   = alloc > 0 ? ((spent / alloc) * 100).toFixed(1) : '0.0';
            const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
            setText('totalAllocated',  kes(alloc));
            setText('footerSpent',     kes(spent));
            setText('footerRemaining', kes(rem));
            setText('footerPercent',   pct + '%');
        },

        renderCharts: function (rows) {
            const ChartJS = (typeof window !== 'undefined' && window.Chart) || (typeof Chart !== 'undefined' ? Chart : null);
            if (!ChartJS) return;

            const deptMap = {};
            rows.forEach(r => {
                const d = r.department || 'Unknown';
                if (!deptMap[d]) deptMap[d] = { budget: 0, spent: 0 };
                deptMap[d].budget += Number(r.allocated_amount || 0);
                deptMap[d].spent  += Number(r.spent_amount || 0);
            });
            const labels  = Object.keys(deptMap);
            const budgets = labels.map(d => deptMap[d].budget);
            const spents  = labels.map(d => deptMap[d].spent);
            const palette = ['#4e79a7','#f28e2b','#e15759','#76b7b2','#59a14f','#edc948','#b07aa1','#ff9da7','#9c755f','#bab0ac'];

            const barCanvasEl = document.getElementById('budgetVsActualChart');
            if (barCanvasEl) {
                if (this.charts.bar) this.charts.bar.destroy();
                this.charts.bar = new ChartJS(barCanvasEl.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            { label: 'Budget',       data: budgets, backgroundColor: '#4e79a7' },
                            { label: 'Actual Spend', data: spents,  backgroundColor: '#e15759' }
                        ]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { position: 'top' } },
                        scales: { y: { ticks: { callback: v => 'KES ' + Number(v).toLocaleString() } } }
                    }
                });
            }

            const doughCanvasEl = document.getElementById('departmentChart');
            if (doughCanvasEl) {
                if (this.charts.doughnut) this.charts.doughnut.destroy();
                this.charts.doughnut = new ChartJS(doughCanvasEl.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels,
                        datasets: [{ data: spents, backgroundColor: palette.slice(0, labels.length) }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { position: 'right' },
                            tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${kes(ctx.parsed)}` } }
                        }
                    }
                });
            }
        },

        bindEvents: function () {
            ['financialYear', 'department', 'statusFilter'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.addEventListener('change', () => this.render());
            });
            const addBtn = document.getElementById('addBudgetBtn');
            if (addBtn) addBtn.addEventListener('click', () => this.openModal());
            const exportBtn = document.getElementById('exportBtn');
            if (exportBtn) exportBtn.addEventListener('click', () => this.exportCSV());
            const saveBtn = document.getElementById('saveBudgetBtn');
            if (saveBtn) saveBtn.addEventListener('click', () => this.saveBudget());
        },

        bindTableActions: function () {
            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const row = this.data.find(r => String(r.id) === String(btn.dataset.id));
                    if (row) this.openModal(row);
                });
            });
            document.querySelectorAll('.approve-btn').forEach(btn => {
                btn.addEventListener('click', () => this.approveBudget(btn.dataset.id));
            });
        },

        openModal: function (row = null) {
            const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val || ''; };
            const setTxt = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
            setTxt('budgetModalTitle', row ? 'Edit Budget' : 'Add Budget');
            setVal('budgetId',          row ? row.id : '');
            setVal('fyear',             row ? row.financial_year : '');
            setVal('category',          row ? row.category : '');
            setVal('budgetDepartment',  row ? row.department : '');
            setVal('amount',            row ? row.allocated_amount : '');
            setVal('budgetDescription', row ? row.description : '');
            setVal('budgetStatus',      row ? row.status : 'proposed');
            const modalEl = document.getElementById('budgetModal');
            if (modalEl && window.bootstrap) new window.bootstrap.Modal(modalEl).show();
        },

        saveBudget: async function () {
            const getVal = id => (document.getElementById(id) || {}).value || '';
            const id = getVal('budgetId');
            const payload = {
                financial_year:   getVal('fyear'),
                category:         getVal('category'),
                department:       getVal('budgetDepartment'),
                allocated_amount: getVal('amount'),
                description:      getVal('budgetDescription'),
                status:           getVal('budgetStatus')
            };
            if (id) payload.id = id;
            try {
                await window.API.finance.proposeDepartmentBudget(payload);
                showToast(id ? 'Budget updated successfully.' : 'Budget proposed successfully.');
                const modalEl = document.getElementById('budgetModal');
                if (modalEl && window.bootstrap) window.bootstrap.Modal.getInstance(modalEl)?.hide();
                await this.loadData();
            } catch (err) {
                console.error('Save budget error:', err);
                showToast('Failed to save budget.', 'error');
            }
        },

        approveBudget: async function (id) {
            if (!confirm('Approve this budget entry?')) return;
            try {
                await window.API.finance.approveDepartmentBudget({ id });
                showToast('Budget approved successfully.');
                await this.loadData();
            } catch (err) {
                console.error('Approve budget error:', err);
                showToast('Approval failed.', 'error');
            }
        },

        exportCSV: function () {
            const rows = this.getFiltered();
            const headers = ['Financial Year', 'Department', 'Category', 'Allocated (KES)', 'Spent (KES)', 'Remaining (KES)', 'Utilization %', 'Status'];
            const lines = [headers.join(',')];
            rows.forEach(r => {
                const alloc = Number(r.allocated_amount || 0);
                const spent = Number(r.spent_amount || 0);
                const rem   = alloc - spent;
                const pct   = alloc > 0 ? ((spent / alloc) * 100).toFixed(1) : '0.0';
                lines.push([r.financial_year, r.department, r.category, alloc.toFixed(2), spent.toFixed(2), rem.toFixed(2), pct, r.status].join(','));
            });
            const blob = new Blob([lines.join('\n')], { type: 'text/csv' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `budget_overview_${new Date().toISOString().slice(0,10)}.csv`;
            a.click();
        }
    };

    document.addEventListener('DOMContentLoaded', () => Controller.init());
})();
