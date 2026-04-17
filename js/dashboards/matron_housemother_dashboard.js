/**
 * Boarding Master / Matron Dashboard Controller
 * Role: Matron/Housemother (Role ID 18)
 *
 * Data sources (all via window.API.boarding.*):
 *   boarding.getStats()          → /boarding/stats
 *   boarding.getOccupancy()      → /boarding/occupancy
 *   boarding.getExeats(params)   → /boarding/exeats
 *   boarding.getRollCalls(params) → /boarding/roll-call
 */

const boardingDashboardController = {
    data: {
        stats: {},
        occupancy: [],
        exeats: [],
        rollCall: [],
    },

    occupancyChart: null,
    refreshInterval: 30 * 60 * 1000,
    _refreshTimer: null,

    init: async function () {
        if (!AuthContext.isAuthenticated()) {
            window.location.href = (window.APP_BASE || '') + '/index.php';
            return;
        }
        await this.loadAll();
        this.renderAll();
        this._initOccupancyChart();
        this.bindEvents();
        this.updateElement('lastUpdated', new Date().toLocaleTimeString());
        this._setupAutoRefresh();
    },

    loadAll: async function () {
        await Promise.allSettled([
            this.loadStats(),
            this.loadOccupancy(),
            this.loadExeats(),
            this.loadRollCall(),
        ]);
    },

    loadStats: async function () {
        try {
            const res = await window.API.boarding.getStats();
            this.data.stats = res?.data ?? res ?? {};
        } catch (e) {
            console.warn('[Boarding Dashboard] loadStats failed:', e);
            this.data.stats = {};
        }
    },

    loadOccupancy: async function () {
        try {
            const res = await window.API.boarding.getOccupancy();
            const raw = res?.data ?? res ?? [];
            this.data.occupancy = Array.isArray(raw) ? raw : [];
        } catch (e) {
            console.warn('[Boarding Dashboard] loadOccupancy failed:', e);
            this.data.occupancy = [];
        }
    },

    loadExeats: async function () {
        try {
            const res = await window.API.boarding.getExeats({ status: 'pending' });
            const raw = res?.data ?? res ?? [];
            this.data.exeats = Array.isArray(raw) ? raw : [];
        } catch (e) {
            console.warn('[Boarding Dashboard] loadExeats failed:', e);
            this.data.exeats = [];
        }
    },

    loadRollCall: async function () {
        try {
            const today = new Date().toISOString().split('T')[0];
            const res = await window.API.boarding.getRollCalls({ date: today });
            const raw = res?.data ?? res ?? [];
            this.data.rollCall = Array.isArray(raw) ? raw : [];
        } catch (e) {
            console.warn('[Boarding Dashboard] loadRollCall failed:', e);
            this.data.rollCall = [];
        }
    },

    renderAll: function () {
        this.renderKPIs();
        this.renderExeatsTable();
        this.renderRollCallTable();
    },

    renderKPIs: function () {
        const s = this.data.stats;
        const occ = this.data.occupancy;

        const totalBoarders =
            s.total_boarders ??
            s.occupied ??
            occ.reduce((acc, d) => acc + parseInt(d.occupied ?? d.current_occupancy ?? 0, 10), 0);

        const totalCapacity =
            s.total_capacity ??
            s.capacity ??
            occ.reduce((acc, d) => acc + parseInt(d.capacity ?? 0, 10), 0);

        this.updateElement('totalBoarders', totalBoarders || '--');
        this.updateElement('boardersCapacity', `of ${totalCapacity || '--'} capacity`);

        const rollTotal = this.data.rollCall.length;
        const rollPresent = this.data.rollCall.filter(
            (r) => (r.status ?? '').toLowerCase() === 'present'
        ).length;

        if (rollTotal > 0) {
            const rate = Math.round((rollPresent / rollTotal) * 100);
            this.updateElement('rollCallRate', `${rate}%`);
            this.updateElement('rollCallSub', `${rollPresent} / ${rollTotal} present`);
        } else {
            this.updateElement('rollCallRate', 'Not marked');
            this.updateElement('rollCallSub', 'Roll call pending');
        }

        this.updateElement('pendingExeats', this.data.exeats.length);
        this.updateElement('disciplineCases', s.discipline_cases_week ?? s.discipline_cases ?? '--');
    },

    renderExeatsTable: function () {
        const container = document.getElementById('exeatsContainer');
        if (!container) return;

        if (!this.data.exeats.length) {
            container.innerHTML =
                '<div class="text-center py-4 text-muted"><i class="bi bi-check-circle me-2 text-success"></i>No pending exeat requests</div>';
            return;
        }

        let html =
            '<div class="table-responsive"><table class="table table-sm table-hover mb-0">' +
            '<thead class="table-light"><tr>' +
            '<th>Student</th><th>Exit Date</th><th>Return</th><th>Reason</th><th>Action</th>' +
            '</tr></thead><tbody>';

        this.data.exeats.forEach((e) => {
            const exitDate = e.exit_date ? new Date(e.exit_date).toLocaleDateString() : '--';
            const returnDate = e.return_date ? new Date(e.return_date).toLocaleDateString() : '--';
            const reason = this._escape(e.reason ?? '');
            const name = this._escape(e.student_name ?? e.name ?? '');

            html += `<tr>
                <td class="small align-middle">${name}</td>
                <td class="small align-middle">${exitDate}</td>
                <td class="small align-middle">${returnDate}</td>
                <td class="small align-middle text-truncate" style="max-width:110px" title="${reason}">${reason}</td>
                <td class="align-middle">
                    <button class="btn btn-success btn-sm approve-exeat py-0 px-2"
                            data-id="${e.id}"
                            style="font-size:0.72rem">Approve</button>
                    <button class="btn btn-outline-danger btn-sm reject-exeat py-0 px-2 ms-1"
                            data-id="${e.id}"
                            style="font-size:0.72rem">Reject</button>
                </td>
            </tr>`;
        });

        html += '</tbody></table></div>';
        container.innerHTML = html;

        container.querySelectorAll('.approve-exeat').forEach((btn) => {
            btn.addEventListener('click', () => this._approveExeat(btn.dataset.id));
        });
        container.querySelectorAll('.reject-exeat').forEach((btn) => {
            btn.addEventListener('click', () => this._rejectExeat(btn.dataset.id));
        });
    },

    renderRollCallTable: function () {
        const container = document.getElementById('rollCallContainer');
        if (!container) return;

        if (!this.data.rollCall.length) {
            container.innerHTML =
                '<div class="text-center py-4 text-muted">' +
                '<i class="bi bi-info-circle me-2"></i>Roll call not yet marked for tonight. ' +
                '<a href="home.php?route=boarding_roll_call">Mark now</a></div>';
            return;
        }

        let html =
            '<div class="table-responsive"><table class="table table-sm table-hover mb-0">' +
            '<thead class="table-light"><tr>' +
            '<th>Dormitory</th><th>Student</th><th>Status</th>' +
            '</tr></thead><tbody>';

        this.data.rollCall.slice(0, 12).forEach((r) => {
            const status = (r.status ?? 'unknown').toLowerCase();
            const badgeClass = status === 'present' ? 'bg-success' : 'bg-danger';
            const dorm = this._escape(r.dormitory_name ?? r.dorm_name ?? r.dorm ?? '');
            const student = this._escape(r.student_name ?? r.name ?? '');

            html += `<tr>
                <td class="small">${dorm}</td>
                <td class="small">${student}</td>
                <td><span class="badge ${badgeClass} text-capitalize" style="font-size:0.65rem">${status}</span></td>
            </tr>`;
        });

        html += '</tbody></table>';

        if (this.data.rollCall.length > 12) {
            html += `<div class="text-center py-2 text-muted small">
                Showing 12 of ${this.data.rollCall.length} —
                <a href="home.php?route=boarding_roll_call">View All</a>
            </div>`;
        }

        html += '</div>';
        container.innerHTML = html;
    },

    _initOccupancyChart: function () {
        const canvas = document.getElementById('occupancyChart');
        if (!canvas) return;

        if (typeof Chart === 'undefined') {
            canvas.parentElement.innerHTML =
                '<div class="text-center py-4 text-muted">Chart.js not loaded</div>';
            return;
        }

        if (!this.data.occupancy.length) {
            canvas.parentElement.innerHTML =
                '<div class="text-center py-4 text-muted">No dormitory data available</div>';
            return;
        }

        if (this.occupancyChart) {
            this.occupancyChart.destroy();
            this.occupancyChart = null;
        }

        const labels = this.data.occupancy.map(
            (d) => d.dormitory_name ?? d.name ?? `Dorm ${d.id}`
        );
        const occupiedData = this.data.occupancy.map((d) =>
            parseInt(d.occupied ?? d.current_occupancy ?? 0, 10)
        );
        const capacityData = this.data.occupancy.map((d) =>
            parseInt(d.capacity ?? 0, 10)
        );

        this.occupancyChart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Occupied',
                        data: occupiedData,
                        backgroundColor: 'rgba(156,33,176,0.75)',
                        borderColor: '#9c27b0',
                        borderWidth: 1,
                        borderRadius: 4,
                    },
                    {
                        label: 'Capacity',
                        data: capacityData,
                        backgroundColor: 'rgba(200,200,200,0.35)',
                        borderColor: '#bbb',
                        borderWidth: 1,
                        borderRadius: 4,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { mode: 'index', intersect: false },
                },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                },
            },
        });
    },

    _approveExeat: async function (id) {
        try {
            await window.API.boarding.approveExeat(id);
            showNotification('Exeat approved successfully', 'success');
            await this._refreshExeats();
        } catch (err) {
            console.error('[Boarding Dashboard] approveExeat failed:', err);
            showNotification('Failed to approve exeat', 'error');
        }
    },

    _rejectExeat: async function (id) {
        const reason = prompt('Reason for rejection (optional):') ?? '';
        try {
            await window.API.boarding.rejectExeat(id, reason);
            showNotification('Exeat rejected', 'info');
            await this._refreshExeats();
        } catch (err) {
            console.error('[Boarding Dashboard] rejectExeat failed:', err);
            showNotification('Failed to reject exeat', 'error');
        }
    },

    _refreshExeats: async function () {
        await this.loadExeats();
        this.renderExeatsTable();
        this.renderKPIs();
    },

    bindEvents: function () {
        document.getElementById('refreshDashboard')?.addEventListener('click', async () => {
            await this.loadAll();
            this.renderAll();
            this._initOccupancyChart();
            this.updateElement('lastUpdated', new Date().toLocaleTimeString());
        });
    },

    _setupAutoRefresh: function () {
        if (this._refreshTimer) clearInterval(this._refreshTimer);
        this._refreshTimer = setInterval(async () => {
            await this.loadAll();
            this.renderAll();
            this._initOccupancyChart();
            this.updateElement('lastUpdated', new Date().toLocaleTimeString());
        }, this.refreshInterval);
    },

    updateElement: function (id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    },

    _escape: function (str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    },
};

document.addEventListener('DOMContentLoaded', () => boardingDashboardController.init());
