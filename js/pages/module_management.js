/**
 * Module Management Controller
 * Enable/disable application modules via school config
 * Role: System Administrator (ID 2)
 */
const moduleManagementController = {
    config: null,
    filters: { search: '' },

    // Known modules derived from the system architecture
    MODULES: [
        { key: 'students',      label: 'Students',      icon: 'person-badge',      desc: 'Student profiles, records, reports' },
        { key: 'admissions',    label: 'Admissions',    icon: 'door-open',         desc: 'Application intake and workflow' },
        { key: 'academics',     label: 'Academics',     icon: 'book',              desc: 'Classes, streams, timetables' },
        { key: 'assessments',   label: 'Assessments',   icon: 'clipboard-check',   desc: 'Exams, grades, report cards' },
        { key: 'attendance',    label: 'Attendance',    icon: 'calendar-check',    desc: 'Daily and period attendance' },
        { key: 'discipline',    label: 'Discipline',    icon: 'shield-exclamation', desc: 'Incidents and counseling' },
        { key: 'finance',       label: 'Finance',       icon: 'cash-stack',        desc: 'Fees, invoices, payments, reports' },
        { key: 'payroll',       label: 'Payroll',       icon: 'wallet2',           desc: 'Staff payroll and deductions' },
        { key: 'scheduling',    label: 'Scheduling',    icon: 'calendar3',         desc: 'Timetables, events, exams' },
        { key: 'transport',     label: 'Transport',     icon: 'bus-front',         desc: 'Routes, vehicles, drivers' },
        { key: 'communications', label: 'Communications', icon: 'chat-dots',       desc: 'Messaging and notifications' },
        { key: 'boarding',      label: 'Boarding',      icon: 'house-door',        desc: 'Dormitories, meals, chapel' },
        { key: 'inventory',     label: 'Inventory',     icon: 'boxes',             desc: 'Stock, suppliers, categories' },
        { key: 'activities',    label: 'Activities',    icon: 'trophy',            desc: 'Clubs, events, sport' },
        { key: 'reporting',     label: 'Reporting',     icon: 'bar-chart',         desc: 'System-wide reports and exports' },
        { key: 'staff',         label: 'Staff',         icon: 'people',            desc: 'Staff management and contracts' },
        { key: 'system',        label: 'System',        icon: 'gear',              desc: 'System administration core' }
    ],

    init: async function () {
        if (!AuthContext.isAuthenticated()) { window.location.href = '/'; return; }
        if (!AuthContext.hasPermission('system_view')) {
            const el = document.getElementById('mainTable');
            if (el) el.innerHTML = '<div class="alert alert-danger m-3">Access denied</div>';
            return;
        }
        await this.loadData();
        this.render();
        this.bindEvents();
    },

    loadData: async function () {
        try {
            const res = await window.API.system.getSchoolConfig();
            const raw = res?.data ?? res ?? {};
            // Look for a modules config key
            this.config = raw.modules || raw.module_config || raw;
        } catch (e) {
            console.error('module_management: loadData error', e);
            showNotification('Failed to load module configuration', 'error');
            this.config = {};
        }
    },

    isEnabled: function (moduleKey) {
        if (!this.config) return true; // default enabled
        const val = this.config[`module_${moduleKey}`] ?? this.config[moduleKey];
        if (val === undefined || val === null) return true; // default enabled
        return val === 1 || val === '1' || val === true || val === 'true' || val === 'enabled';
    },

    toggle: async function (moduleKey, currentEnabled) {
        const newEnabled = !currentEnabled;
        const payload = { [`module_${moduleKey}`]: newEnabled ? 1 : 0 };
        try {
            await window.API.system.updateSchoolConfig(payload);
            if (!this.config) this.config = {};
            this.config[`module_${moduleKey}`] = newEnabled ? 1 : 0;
            const mod = this.MODULES.find(m => m.key === moduleKey);
            showNotification(`Module "${mod?.label || moduleKey}" ${newEnabled ? 'enabled' : 'disabled'}`, 'success');
            this.render();
        } catch (e) {
            console.error('module_management: toggle error', e);
            showNotification(e.message || 'Failed to update module', 'error');
        }
    },

    render: function () {
        const container = document.getElementById('mainTable');
        if (!container) return;

        const term = this.filters.search.toLowerCase();
        const filtered = this.MODULES.filter(m =>
            !term || m.label.toLowerCase().includes(term) || m.desc.toLowerCase().includes(term) || m.key.toLowerCase().includes(term)
        );

        const enabledCount = this.MODULES.filter(m => this.isEnabled(m.key)).length;

        let html = `<div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 bg-light">
                    <div class="card-body text-center">
                        <h4 class="mb-0">${this.MODULES.length}</h4>
                        <small class="text-muted">Total Modules</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 bg-success bg-opacity-10">
                    <div class="card-body text-center">
                        <h4 class="mb-0 text-success">${enabledCount}</h4>
                        <small class="text-muted">Enabled</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 bg-secondary bg-opacity-10">
                    <div class="card-body text-center">
                        <h4 class="mb-0 text-secondary">${this.MODULES.length - enabledCount}</h4>
                        <small class="text-muted">Disabled</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="mb-3 d-flex gap-2">
            <input type="text" class="form-control form-control-sm" id="searchFilter"
                placeholder="Search modules..." value="${this.esc(this.filters.search)}" style="max-width:280px">
            <button class="btn btn-sm btn-outline-secondary" id="refreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
        <div class="row g-3">`;

        filtered.forEach(mod => {
            const enabled = this.isEnabled(mod.key);
            html += `<div class="col-md-4 col-sm-6">
                <div class="card h-100 border-${enabled ? 'success' : 'secondary'} border-opacity-${enabled ? '50' : '25'}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <i class="bi bi-${this.esc(mod.icon)} fs-4 text-${enabled ? 'success' : 'secondary'} mb-2 d-block"></i>
                                <h6 class="mb-1">${this.esc(mod.label)}</h6>
                                <p class="text-muted small mb-0">${this.esc(mod.desc)}</p>
                            </div>
                            <div class="form-check form-switch ms-2">
                                <input class="form-check-input module-toggle" type="checkbox"
                                    id="mod_${mod.key}" ${enabled ? 'checked' : ''}
                                    data-module="${mod.key}" data-enabled="${enabled ? 1 : 0}"
                                    style="cursor:pointer" ${mod.key === 'system' ? 'disabled title="System module cannot be disabled"' : ''}>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="badge bg-${enabled ? 'success' : 'secondary'} bg-opacity-75">
                                ${enabled ? 'Enabled' : 'Disabled'}
                            </span>
                            <code class="small text-muted ms-2">${mod.key}</code>
                        </div>
                    </div>
                </div>
            </div>`;
        });

        html += `</div>`;
        container.innerHTML = html;
        this.bindFilterEvents();
    },

    bindEvents: function () {},

    bindFilterEvents: function () {
        document.getElementById('searchFilter')?.addEventListener('input', e => {
            this.filters.search = e.target.value;
            this.render();
        });
        document.getElementById('refreshBtn')?.addEventListener('click', async () => {
            await this.loadData();
            this.render();
        });
        document.querySelectorAll('.module-toggle').forEach(chk => {
            chk.addEventListener('change', e => {
                const key = e.target.dataset.module;
                const wasEnabled = e.target.dataset.enabled === '1';
                this.toggle(key, wasEnabled);
            });
        });
    },

    esc: function (s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
};

document.addEventListener('DOMContentLoaded', () => moduleManagementController.init());
