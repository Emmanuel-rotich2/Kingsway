/**
 * My Vehicle Page Controller
 * For bus drivers: view assigned vehicle details, maintenance, issues, fuel log, documents.
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

    function fmtDate(d) {
        if (!d) return '--';
        return new Date(d).toLocaleDateString('en-KE', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function statusBadge(status, map) {
        const defaults = { active: 'success', inactive: 'secondary', pending: 'warning', resolved: 'success', open: 'danger', scheduled: 'info' };
        const cls = (map || defaults)[String(status).toLowerCase()] || 'secondary';
        return `<span class="badge bg-${cls}">${esc(status || 'Unknown')}</span>`;
    }

    const Controller = {
        vehicle: null,
        driverId: null,

        init: async function () {
            await window.AuthContext?.ready();
            if (!AuthContext.isAuthenticated()) {
                window.location.href = (window.APP_BASE || '') + '/index.php';
                return;
            }
            this.bindModalEvents();
            await this.loadData();
        },

        loadData: async function () {
            try {
                const response = await window.API.transport.getMyVehicle();
                this.vehicle = response?.data ?? response ?? null;

                if (!this.vehicle || !this.vehicle.id) {
                    this.vehicle = null;
                    showToast('No vehicle assigned to your account.', 'warning');
                    this.renderEmpty();
                    return;
                }
                this.driverId = this.vehicle.driver_id || null;

                this.render();
            } catch (error) {
                console.error('My vehicle load error:', error);
                showToast('Failed to load vehicle data.', 'error');
            }
        },

        render: function () {
            this.renderVehicleInfo();
            this.renderStats();
            this.renderMaintenanceTab();
            this.renderIssuesTab();
            this.renderFuelTab();
            this.renderDocumentsTab();
        },

        renderEmpty: function () {
            ['vehicleReg','vehicleModel','capacity','vehicleType','vehicleYear','currentMileage'].forEach(id => {
                const el = document.getElementById(id); if (el) el.textContent = '--';
            });
            const documents = document.getElementById('documentsBody');
            if (documents) documents.innerHTML = '<div class="list-group-item text-muted text-center py-3">No vehicle is assigned to your account.</div>';
        },

        renderVehicleInfo: function () {
            const v = this.vehicle;
            const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val || '--'; };
            setText('vehicleReg',     v.registration || v.reg_number || v.plate_number);
            setText('vehicleModel',   v.model || v.make_model || `${v.make || ''} ${v.model_name || ''}`.trim());
            setText('capacity',       v.capacity ? v.capacity + ' seats' : '--');
            setText('vehicleType',    v.vehicle_type || v.type);
            setText('vehicleYear',    v.year || v.manufacture_year);
            setText('currentMileage', v.mileage ? Number(v.mileage).toLocaleString() + ' km' : '--');

            const statusEl = document.getElementById('vehicleStatus');
            if (statusEl) statusEl.innerHTML = statusBadge(v.status || 'active');

            const imgEl = document.getElementById('vehicleImage');
            if (imgEl) {
                if (v.image_url || v.photo_url) {
                    imgEl.src = v.image_url || v.photo_url;
                    imgEl.alt = v.registration || 'Vehicle';
                } else {
                    imgEl.src = (window.APP_BASE || '') + '/assets/images/bus-placeholder.png';
                    imgEl.alt = 'No image';
                    imgEl.onerror = () => { imgEl.style.display = 'none'; };
                }
            }
        },

        renderStats: function () {
            const v = this.vehicle;
            const stats = v.stats || {};
            const setText = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val || '--'; };
            setText('tripsMonth',  stats.trips_this_month ?? (v.trips_month || '--'));
            setText('kmMonth',     stats.km_this_month ? Number(stats.km_this_month).toLocaleString() + ' km' : (v.km_month ? Number(v.km_month).toLocaleString() + ' km' : '--'));
            setText('fuelCost',    stats.fuel_cost_month ? kes(stats.fuel_cost_month) : (v.fuel_cost_month ? kes(v.fuel_cost_month) : '--'));
            setText('nextService', fmtDate(stats.next_service_date || v.next_service_date));
        },

        renderMaintenanceTab: function () {
            const records = this.vehicle.maintenance_records || this.vehicle.maintenance || [];
            const container = document.querySelector('#vehicleTabContent [data-tab="maintenance"] table tbody, #maintenanceBody');
            if (!container) return;

            if (!records.length) {
                container.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No maintenance records found.</td></tr>';
                return;
            }
            container.innerHTML = records.map(m => `<tr>
                <td>${fmtDate(m.date || m.service_date)}</td>
                <td>${esc(m.type || m.service_type)}</td>
                <td>${esc(m.description || m.notes || '--')}</td>
                <td class="text-end">${m.cost ? kes(m.cost) : '--'}</td>
                <td>${statusBadge(m.status || 'completed')}</td>
            </tr>`).join('');
        },

        renderIssuesTab: function () {
            const issues = this.vehicle.issues || this.vehicle.reported_issues || [];
            const container = document.querySelector('#issuesBody');
            if (!container) return;

            if (!issues.length) {
                container.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No issues reported.</td></tr>';
                return;
            }
            const issueMap = { open: 'danger', pending: 'warning', resolved: 'success', closed: 'secondary' };
            container.innerHTML = issues.map(i => `<tr>
                <td>${fmtDate(i.reported_date || i.created_at)}</td>
                <td>${esc(i.title || i.issue_type || 'Issue')}</td>
                <td>${esc(i.description || '--')}</td>
                <td>${esc(i.priority || '--')}</td>
                <td>${statusBadge(i.status || 'open', issueMap)}</td>
            </tr>`).join('');
        },

        renderFuelTab: function () {
            const entries = this.vehicle.fuel_log || this.vehicle.fuel_records || [];
            const container = document.querySelector('#fuelBody');
            if (!container) return;

            if (!entries.length) {
                container.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No fuel entries found.</td></tr>';
                return;
            }
            container.innerHTML = entries.map(f => `<tr>
                <td>${fmtDate(f.date || f.fill_date)}</td>
                <td class="text-end">${esc(f.litres || f.quantity || '--')}</td>
                <td class="text-end">${f.cost_per_litre ? kes(f.cost_per_litre) : '--'}</td>
                <td class="text-end">${f.total_cost ? kes(f.total_cost) : '--'}</td>
                <td>${esc(f.odometer ? Number(f.odometer).toLocaleString() + ' km' : '--')}</td>
            </tr>`).join('');
        },

        renderDocumentsTab: function () {
            const docs = this.vehicle.documents || this.vehicle.vehicle_documents || [];
            const container = document.getElementById('documentsBody');
            if (!container) return;

            if (!docs.length) {
                const insuranceExpiry = this.vehicle.insurance_expiry || this.vehicle.insurance_expiry_date;
                container.innerHTML = insuranceExpiry
                    ? `<div class="list-group-item d-flex justify-content-between align-items-center"><strong>Insurance Certificate</strong><small class="text-muted">Expires: ${esc(fmtDate(insuranceExpiry))}</small></div>`
                    : '<div class="list-group-item text-muted text-center py-3">No vehicle documents are recorded.</div>';
                return;
            }
            const docTypeIcon = t => {
                const icons = { insurance: 'bi-shield-check', inspection: 'bi-clipboard-check', license: 'bi-card-text', logbook: 'bi-book' };
                return icons[String(t).toLowerCase()] || 'bi-file-earmark';
            };
            container.innerHTML = docs.map(d => {
                    const expired = d.expiry_date && new Date(d.expiry_date) < new Date();
                    return `<div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi ${docTypeIcon(d.type || d.document_type)} me-2 text-primary"></i>
                            <strong>${esc(d.name || d.document_type || 'Document')}</strong>
                            ${d.document_number ? `<small class="text-muted ms-2">${esc(d.document_number)}</small>` : ''}
                        </div>
                        <div class="text-end">
                            <small class="${expired ? 'text-danger fw-semibold' : 'text-muted'}">
                                ${d.expiry_date ? (expired ? 'Expired: ' : 'Expires: ') + fmtDate(d.expiry_date) : ''}
                            </small>
                            ${d.file_url ? `<a href="${esc(d.file_url)}" target="_blank" class="btn btn-sm btn-outline-secondary ms-2">View</a>` : ''}
                        </div>
                    </div>`;
                }).join('');
        },

        logMaintenance: async function () {
            const getVal = id => (document.getElementById(id) || {}).value || '';
            const payload = {
                vehicle_id:   this.vehicle ? this.vehicle.id : null,
                service_type: getVal('maintenanceType'),
                description:  getVal('maintenanceDescription'),
                cost:         getVal('maintenanceCost'),
                date:         getVal('maintenanceDate') || new Date().toISOString().slice(0, 10)
            };
            if (!payload.service_type) { showToast('Service type is required.', 'warning'); return; }
            try {
                await window.API.transport.index({ action: 'log_maintenance', ...payload });
                showToast('Maintenance logged successfully.');
                const modalEl = document.getElementById('logMaintenanceModal');
                if (modalEl && window.bootstrap) window.bootstrap.Modal.getInstance(modalEl)?.hide();
                await this.loadData();
            } catch (err) {
                console.error('Log maintenance error:', err);
                showToast('Failed to log maintenance.', 'error');
            }
        },

        reportIssue: async function () {
            const getVal = id => (document.getElementById(id) || {}).value || '';
            const payload = {
                vehicle_id:  this.vehicle ? this.vehicle.id : null,
                driver_id:   this.driverId,
                title:       getVal('issueTitle'),
                description: getVal('issueDescription'),
                priority:    getVal('issuePriority') || 'medium'
            };
            if (!payload.title) { showToast('Issue title is required.', 'warning'); return; }
            try {
                await window.API.transport.index({ action: 'report_issue', ...payload });
                showToast('Issue reported successfully.');
                const modalEl = document.getElementById('reportIssueModal');
                if (modalEl && window.bootstrap) window.bootstrap.Modal.getInstance(modalEl)?.hide();
                await this.loadData();
            } catch (err) {
                console.error('Report issue error:', err);
                showToast('Failed to report issue.', 'error');
            }
        },

        bindModalEvents: function () {
            const logBtn = document.getElementById('logMaintenanceBtn');
            if (logBtn) {
                logBtn.addEventListener('click', () => {
                    const modalEl = document.getElementById('logMaintenanceModal');
                    if (modalEl && window.bootstrap) new window.bootstrap.Modal(modalEl).show();
                });
            }

            const reportBtn = document.getElementById('reportIssueBtn');
            if (reportBtn) {
                reportBtn.addEventListener('click', () => {
                    const modalEl = document.getElementById('reportIssueModal');
                    if (modalEl && window.bootstrap) new window.bootstrap.Modal(modalEl).show();
                });
            }

            const saveMainBtn = document.getElementById('saveMaintenanceBtn');
            if (saveMainBtn) saveMainBtn.addEventListener('click', () => this.logMaintenance());

            const saveIssueBtn = document.getElementById('saveIssueBtn');
            if (saveIssueBtn) saveIssueBtn.addEventListener('click', () => this.reportIssue());
        }
    };

    window.MyVehicleController = Controller;
    window.APIRealtime?.register?.('MyVehicleController', Controller);
    document.addEventListener('DOMContentLoaded', () => Controller.init());
})();
