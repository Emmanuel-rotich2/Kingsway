/**
 * Feature Flags Controller
 * Enable/disable system modules and feature policies
 * Role: System Administrator (ID 2)
 */
const featureFlagsController = {
    policies: [],
    filters: { search: '' },

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
            const res = await window.API.system.getPolicies();
            this.policies = res?.data ?? res ?? [];
        } catch (e) {
            console.error('feature_flags: loadData error', e);
            showNotification('Failed to load feature flags', 'error');
        }
    },

    toggle: async function (id, currentEnabled) {
        const policy = this.policies.find(p => p.id === id);
        if (!policy) return;
        const newEnabled = !currentEnabled;
        try {
            await window.API.system.updatePolicy(id, {
                ...policy,
                is_enabled: newEnabled ? 1 : 0,
                status: newEnabled ? 'active' : 'inactive'
            });
            policy.is_enabled = newEnabled ? 1 : 0;
            policy.status = newEnabled ? 'active' : 'inactive';
            showNotification(`Feature "${policy.name || policy.key}" ${newEnabled ? 'enabled' : 'disabled'}`, 'success');
            this.render();
        } catch (e) {
            console.error('feature_flags: toggle error', e);
            showNotification(e.message || 'Failed to update feature flag', 'error');
        }
    },

    render: function () {
        const container = document.getElementById('mainTable');
        if (!container) return;

        const term = this.filters.search.toLowerCase();
        const filtered = this.policies.filter(p =>
            !term ||
            (p.name || p.key || '').toLowerCase().includes(term) ||
            (p.description || '').toLowerCase().includes(term) ||
            (p.module || '').toLowerCase().includes(term)
        );

        const enabledCount = this.policies.filter(p => p.is_enabled || p.status === 'active').length;

        let html = `<div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 bg-light">
                    <div class="card-body text-center">
                        <h4 class="mb-0">${this.policies.length}</h4>
                        <small class="text-muted">Total Policies</small>
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
                        <h4 class="mb-0 text-secondary">${this.policies.length - enabledCount}</h4>
                        <small class="text-muted">Disabled</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="mb-3 d-flex gap-2">
            <input type="text" class="form-control form-control-sm" id="searchFilter"
                placeholder="Search feature flags..." value="${this.esc(this.filters.search)}" style="max-width:280px">
            <button class="btn btn-sm btn-outline-secondary" id="refreshBtn">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>`;

        if (!filtered.length) {
            html += '<div class="alert alert-info">No feature flags found.</div>';
        } else {
            // Group by module
            const modules = [...new Set(filtered.map(p => p.module || 'General'))].sort();

            modules.forEach(mod => {
                const modPolicies = filtered.filter(p => (p.module || 'General') === mod);
                html += `<div class="card mb-3 shadow-sm">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-toggles me-2"></i>${this.esc(mod)}</h6>
                    </div>
                    <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Feature</th><th>Description</th><th>Type</th><th class="text-end">Enabled</th></tr>
                        </thead><tbody>`;

                modPolicies.forEach(p => {
                    const isEnabled = !!(p.is_enabled || p.status === 'active');
                    html += `<tr>
                        <td>
                            <strong>${this.esc(p.name || p.key || '')}</strong>
                            ${p.key ? `<br><code class="small text-muted">${this.esc(p.key)}</code>` : ''}
                        </td>
                        <td class="text-muted small">${this.esc(p.description || '')}</td>
                        <td><span class="badge bg-info text-dark">${this.esc(p.type || p.policy_type || 'flag')}</span></td>
                        <td class="text-end">
                            <div class="form-check form-switch d-inline-block mb-0">
                                <input class="form-check-input feature-toggle" type="checkbox"
                                    id="flag_${p.id}" ${isEnabled ? 'checked' : ''}
                                    data-policy-id="${p.id}" data-enabled="${isEnabled ? 1 : 0}"
                                    style="cursor:pointer">
                            </div>
                        </td>
                    </tr>`;
                });

                html += `</tbody></table></div></div>`;
            });

            html += `<small class="text-muted">${filtered.length} of ${this.policies.length} policies</small>`;
        }

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
        document.querySelectorAll('.feature-toggle').forEach(chk => {
            chk.addEventListener('change', e => {
                const id = parseInt(e.target.dataset.policyId);
                const wasEnabled = e.target.dataset.enabled === '1';
                this.toggle(id, wasEnabled);
            });
        });
    },

    esc: function (s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
};

document.addEventListener('DOMContentLoaded', () => featureFlagsController.init());
