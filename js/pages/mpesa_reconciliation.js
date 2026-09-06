const MpesaReconciliationController = {
    state: {
        transactions: [],
        filtered: [],
        lookup: { parents: [], selectedParentId: null, selectedStudentId: null },
    },

    async init() {
        await window.AuthContext?.ready?.();
        if (!window.AuthContext?.isAuthenticated()) return;
        this.bindEvents();
        await this.loadData();
    },

    bindEvents() {
        const search = document.getElementById('searchTxn');
        if (search) search.addEventListener('input', () => this.applyFilters());

        const dtFrom = document.getElementById('filterDateFrom');
        if (dtFrom) dtFrom.addEventListener('change', () => this.applyFilters());

        const dtTo = document.getElementById('filterDateTo');
        if (dtTo) dtTo.addEventListener('change', () => this.applyFilters());
    },

    async loadData() {
        try {
            this.showLoading();
            const res = await window.API.apiCall('/payments/unmatched-mpesa', 'GET');
            const txns = res?.transactions ?? res?.data ?? [];
            this.state.transactions = Array.isArray(txns) ? txns : [];
            this.state.filtered = [...this.state.transactions];
            this.updateStats();
            this.renderTable();
        } catch (err) {
            console.error('Error loading unmatched transactions:', err);
            this.showNotification('Failed to load transactions', 'error');
        }
    },

    updateStats() {
        const all = this.state.transactions;
        const total = all.length;
        const totalAmount = all.reduce((s, t) => s + parseFloat(t.amount || 0), 0);
        const el = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
        el('kpiUnmatched', total);
        el('kpiTotalAmount', `KES ${totalAmount.toLocaleString(undefined, { minimumFractionDigits: 2 })}`);
        el('kpiReconciledToday', '--');
    },

    applyFilters() {
        const search = (document.getElementById('searchTxn')?.value || '').toLowerCase();
        const from = document.getElementById('filterDateFrom')?.value || '';
        const to = document.getElementById('filterDateTo')?.value || '';

        let f = [...this.state.transactions];
        if (search) {
            f = f.filter(t =>
                (t.mpesa_code || '').toLowerCase().includes(search) ||
                (t.phone_number || '').includes(search) ||
                (t.first_name || '').toLowerCase().includes(search) ||
                (t.last_name || '').toLowerCase().includes(search) ||
                (t.bill_ref_number || '').toLowerCase().includes(search)
            );
        }
        if (from) f = f.filter(t => (t.transaction_date || '').slice(0, 10) >= from);
        if (to) f = f.filter(t => (t.transaction_date || '').slice(0, 10) <= to);

        this.state.filtered = f;
        this.renderTable();
    },

    renderTable() {
        const tbody = document.querySelector('#reconTable tbody');
        if (!tbody) return;
        if (this.state.filtered.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No unmatched transactions found</td></tr>';
            return;
        }
        tbody.innerHTML = this.state.filtered.map((t, i) => `
            <tr>
                <td>${i + 1}</td>
                <td><code>${this.esc(t.mpesa_code || '--')}</code></td>
                <td><small>${this.esc((t.transaction_date || '').slice(0, 16).replace('T', ' '))}</small></td>
                <td>${this.esc([t.first_name, t.middle_name, t.last_name].filter(Boolean).join(' ') || '--')}</td>
                <td>${this.esc(t.phone_number || '--')}</td>
                <td class="text-end fw-bold">${parseFloat(t.amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</td>
                <td><small>${this.esc(t.bill_ref_number || '--')}</small></td>
                <td>
                    <button class="btn btn-sm btn-success" onclick="MpesaReconciliationController.openReconcile(${i})">
                        <i class="bi bi-check-circle"></i> Reconcile
                    </button>
                </td>
            </tr>
        `).join('');
    },

    async openReconcile(index) {
        const t = this.state.filtered[index];
        if (!t) return;

        const payerName = [t.first_name, t.middle_name, t.last_name].filter(Boolean).join(' ') || 'Unknown';
        const phone = t.phone_number || '';
        this.state.lookup = { parents: [], selectedParentId: null, selectedStudentId: null };

        this.showModal('Reconcile M-Pesa Transaction', `
            <div class="mb-3 p-3 bg-light rounded">
                <div class="row">
                    <div class="col-md-6">
                        <small class="text-muted">M-Pesa Code</small>
                        <p class="fw-bold mb-1">${this.esc(t.mpesa_code)}</p>
                        <small class="text-muted">Amount</small>
                        <p class="fw-bold mb-1 text-success">KES ${parseFloat(t.amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</p>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Payer</small>
                        <p class="mb-1">${this.esc(payerName)}</p>
                        <small class="text-muted">Phone</small>
                        <p class="mb-1">${this.esc(phone || 'N/A')}</p>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">Find Parent <span class="text-muted fw-normal">(phone number or ID number)</span></label>
                <div class="input-group">
                    <input type="text" class="form-control" id="lookupParent" value="${this.esc(phone)}" placeholder="e.g. 07XX XXX XXX or 12345678">
                    <button class="btn btn-outline-primary" onclick="MpesaReconciliationController.lookupParent()">
                        <i class="bi bi-search"></i> Find
                    </button>
                </div>
                <div id="lookupResults" class="mt-2"></div>
            </div>

            <form id="reconcileForm">
                <input type="hidden" name="mpesa_id" value="${t.id}">
                <input type="hidden" name="student_id" id="selectedStudentId">
                <div class="mb-3" id="childrenSection" style="display:none;">
                    <label class="form-label fw-semibold">Select Child to Credit</label>
                    <div id="childrenList"></div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Allocate To</label>
                        <select class="form-select" name="account_type" required>
                            <option value="school_fees">School Fees</option>
                            <option value="transport">Transport Account</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Notes (optional)</label>
                        <textarea class="form-control" name="notes" rows="1" placeholder="Reconciliation notes..."></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-success w-100">
                    <i class="bi bi-check-circle me-1"></i> Reconcile & Allocate
                </button>
            </form>
        `, () => {
            document.getElementById('reconcileForm')?.addEventListener('submit', async (e) => {
                e.preventDefault();
                await this.doReconcile(e.target);
            });
            const input = document.getElementById('lookupParent');
            input?.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { e.preventDefault(); this.lookupParent(); }
            });
            if (phone) setTimeout(() => this.lookupParent(), 300);
        });
    },

    async lookupParent() {
        const query = document.getElementById('lookupParent')?.value?.trim();
        if (!query) return;

        const resultsDiv = document.getElementById('lookupResults');
        const childrenSection = document.getElementById('childrenSection');
        if (!resultsDiv) return;

        resultsDiv.innerHTML = '<div class="text-muted"><div class="spinner-border spinner-border-sm me-1"></div>Searching...</div>';
        if (childrenSection) childrenSection.style.display = 'none';
        this.state.lookup = { parents: [], selectedParentId: null, selectedStudentId: null };

        try {
            const res = await window.API.apiCall(`/payments/lookup-by-phone?phone=${encodeURIComponent(query)}`, 'GET');
            const parents = res?.parents ?? [];
            this.state.lookup.parents = parents;

            if (parents.length === 0) {
                resultsDiv.innerHTML = '<div class="alert alert-warning py-2 mb-0"><i class="bi bi-person-x me-1"></i>No parent found for this phone number or ID</div>';
                return;
            }

            if (parents.length === 1) {
                this.selectParent(parents[0].parent_id);
                return;
            }

            resultsDiv.innerHTML =
                '<div class="alert alert-info py-2 mb-2"><small>' + parents.length + ' parents match. Select one:</small></div>' +
                parents.map(p => `
                    <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center rounded mb-1" style="cursor:pointer;"
                         onclick="MpesaReconciliationController.selectParent(${p.parent_id})">
                        <div>
                            <strong>${this.esc([p.parent_first_name, p.parent_last_name].filter(Boolean).join(' '))}</strong>
                            ${p.national_id_no ? `<small class="text-muted ms-2">ID: ${this.esc(p.national_id_no)}</small>` : ''}
                        </div>
                        <small class="text-muted">${this.esc(p.parent_phone || '')}</small>
                    </div>
                `).join('');
        } catch (err) {
            resultsDiv.innerHTML = '<div class="alert alert-danger py-2 mb-0">Lookup failed</div>';
        }
    },

    selectParent(parentId) {
        const parent = (this.state.lookup.parents || []).find(p => p.parent_id === parentId);
        if (!parent) return;
        this.state.lookup.selectedParentId = parentId;
        this.state.lookup.selectedStudentId = null;

        const children = parent.children || [];
        const fullName = [parent.parent_first_name, parent.parent_last_name].filter(Boolean).join(' ');
        const resultsDiv = document.getElementById('lookupResults');
        const childrenSection = document.getElementById('childrenSection');
        const childrenList = document.getElementById('childrenList');
        if (!resultsDiv || !childrenSection || !childrenList) return;

        resultsDiv.innerHTML = `
            <div class="alert alert-success py-2 mb-0 d-flex align-items-center">
                <i class="bi bi-person-check-fill me-2"></i>
                <div>
                    <strong>${this.esc(fullName)}</strong>
                    ${parent.national_id_no ? `<small class="ms-2">ID: ${this.esc(parent.national_id_no)}</small>` : ''}
                    ${parent.parent_phone ? `<small class="ms-2">${this.esc(parent.parent_phone)}</small>` : ''}
                    <small class="d-block text-muted">${children.length} linked child(ren)</small>
                </div>
            </div>`;

        childrenList.innerHTML = children.map(c => {
            const cls = [c.class_name, c.stream_name].filter(Boolean).join(' - ');
            return `
                <label class="list-group-item d-flex align-items-center rounded mb-1" style="cursor:pointer;">
                    <input class="form-check-input me-2" type="radio" name="childChoice" value="${c.student_id}"
                           onchange="MpesaReconciliationController.pickChild(${c.student_id})">
                    <div>
                        <strong>${this.esc([c.first_name, c.last_name].filter(Boolean).join(' '))}</strong>
                        <small class="text-muted ms-2">${this.esc(c.admission_no || 'N/A')}${cls ? ' · ' + this.esc(cls) : ''}</small>
                        ${c.relationship ? `<small class="d-block text-muted">${this.esc(c.relationship)}</small>` : ''}
                    </div>
                </label>`;
        }).join('');

        childrenSection.style.display = 'block';
    },

    pickChild(studentId) {
        this.state.lookup.selectedStudentId = studentId;
        const hidden = document.getElementById('selectedStudentId');
        if (hidden) hidden.value = studentId;
    },

    async doReconcile(form) {
        const data = {};
        new FormData(form).forEach((v, k) => { data[k] = v; });

        if (!data.student_id) {
            this.showNotification('Please select a child to credit', 'warning');
            return;
        }

        try {
            await window.API.apiCall('/payments/reconcile-mpesa', 'POST', data);
            this.showNotification('Transaction reconciled and allocated to ' + (data.account_type === 'transport' ? 'transport account' : 'school fees'), 'success');
            bootstrap.Modal.getInstance(document.getElementById('dynamicModal'))?.hide();
            await this.loadData();
        } catch (err) {
            this.showNotification(err.message || 'Error during reconciliation', 'error');
        }
    },

    clearFilters() {
        ['searchTxn', 'filterDateFrom', 'filterDateTo'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        this.state.filtered = [...this.state.transactions];
        this.renderTable();
    },

    refresh() {
        this.loadData();
    },

    showLoading() {
        const tbody = document.querySelector('#reconTable tbody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4"><div class="spinner-border spinner-border-sm text-success me-2"></div>Loading...</td></tr>';
    },

    esc(str) {
        if (!str && str !== 0) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    },

    showNotification(msg, type = 'info') {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
        alert.style.zIndex = '9999';
        alert.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        document.body.appendChild(alert);
        setTimeout(() => alert.remove(), 4000);
    },

    showModal(title, bodyHtml, onShow) {
        let modal = document.getElementById('dynamicModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'dynamicModal';
            modal.className = 'modal fade';
            modal.tabIndex = -1;
            modal.innerHTML = '<div class="modal-dialog modal-dialog-scrollable modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"></div></div></div>';
            document.body.appendChild(modal);
        }
        modal.querySelector('.modal-title').textContent = title;
        modal.querySelector('.modal-body').innerHTML = bodyHtml;
        new bootstrap.Modal(modal).show();
        if (onShow) setTimeout(onShow, 300);
    },
};

document.addEventListener('DOMContentLoaded', () => MpesaReconciliationController.init());

window.MpesaReconciliationController = MpesaReconciliationController;
