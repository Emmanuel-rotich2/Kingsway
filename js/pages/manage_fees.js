/**
 * Manage Fees Page Controller
 * Handles fee structures, student fees, and defaulters.
 * Uses window.API.finance.* exclusively.
 */

const feesController = {
    feeStructures: [],
    studentFees: [],
    defaulters: [],
    tab: 'structures',

    init: async function() {
        if (!AuthContext.isAuthenticated()) { window.location.href = '/'; return; }
        if (!AuthContext.hasPermission('finance_view')) {
            (document.getElementById('feesContainer') || document.body).insertAdjacentHTML('afterbegin',
                '<div class="alert alert-danger m-3">Access denied: finance_view required</div>'); return;
        }
        this.renderLayout();
        await this.loadFeeStructures();
        this.bindTabEvents();
    },

    renderLayout: function() {
        const container = document.getElementById('feesContainer') || document.querySelector('.card-body') || document.body;
        const canCreate = AuthContext.hasPermission('finance_create');
        container.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <ul class="nav nav-tabs" id="feesTabs">
                <li class="nav-item"><a class="nav-link active" href="#" data-tab="structures">Fee Structures</a></li>
                <li class="nav-item"><a class="nav-link" href="#" data-tab="student-fees">Student Fees</a></li>
                <li class="nav-item"><a class="nav-link" href="#" data-tab="defaulters">Defaulters</a></li>
            </ul>
            ${canCreate ? '<button class="btn btn-primary btn-sm" id="addFeeStructBtn"><i class="bi bi-plus-lg me-1"></i>Add Fee</button>' : ''}
        </div>
        <div id="feesTabContent"></div>`;
    },

    loadFeeStructures: async function() {
        try {
            let res;
            if (window.API.finance?.getFeeStructures) {
                res = await window.API.finance.getFeeStructures();
            } else if (window.API.finance?.getFees) {
                res = await window.API.finance.getFees();
            } else {
                res = await apiCall('/finance/fee-structures', 'GET');
            }
            this.feeStructures = res?.data ?? res ?? [];
            this.renderFeeStructures();
        } catch(e) { showNotification('Failed to load fee structures', 'error'); }
    },

    loadStudentFees: async function() {
        try {
            let res;
            if (window.API.finance?.getStudentFees) {
                res = await window.API.finance.getStudentFees();
            } else {
                res = await apiCall('/finance/student-fees', 'GET');
            }
            this.studentFees = res?.data ?? res ?? [];
            this.renderStudentFees();
        } catch(e) { showNotification('Failed to load student fees', 'error'); }
    },

    loadDefaulters: async function() {
        try {
            let res;
            if (window.API.finance?.getFeeDefaulters) {
                res = await window.API.finance.getFeeDefaulters();
            } else {
                res = await apiCall('/finance/fee-defaulters', 'GET');
            }
            this.defaulters = res?.data ?? res ?? [];
            this.renderDefaulters();
        } catch(e) { showNotification('Failed to load defaulters', 'error'); }
    },

    renderFeeStructures: function() {
        const c = document.getElementById('feesTabContent');
        if (!c) return;
        const canEdit = AuthContext.hasPermission('finance_update');
        if (!this.feeStructures.length) { c.innerHTML = '<div class="alert alert-info">No fee structures found</div>'; return; }
        let html = `<div class="table-responsive"><table class="table table-hover mb-0">
            <thead class="table-dark"><tr><th>Name</th><th>Class</th><th>Term</th><th>Amount (KES)</th><th>Due Date</th>
            ${canEdit ? '<th>Actions</th>' : ''}</tr></thead><tbody>`;
        this.feeStructures.forEach(fs => {
            const amt = parseFloat(fs.amount ?? 0);
            html += `<tr>
                <td>${fs.name ?? fs.fee_name ?? ''}</td>
                <td>${fs.class_name ?? fs.grade ?? 'All Classes'}</td>
                <td>${fs.term_name ?? fs.term ?? ''}</td>
                <td class="fw-bold">KES ${amt.toLocaleString('en-KE', {minimumFractionDigits:2})}</td>
                <td>${fs.due_date ? new Date(fs.due_date).toLocaleDateString() : ''}</td>
                ${canEdit ? `<td>
                    <button class="btn btn-sm btn-outline-primary edit-fee" data-id="${fs.id}" data-name="${fs.name??''}" data-amount="${fs.amount??0}">Edit</button>
                    <button class="btn btn-sm btn-outline-danger ms-1 del-fee" data-id="${fs.id}"><i class="bi bi-trash"></i></button>
                </td>` : ''}
            </tr>`;
        });
        html += '</tbody></table></div>';
        c.innerHTML = html;
        this.bindFeeEvents(canEdit);
    },

    renderStudentFees: function() {
        const c = document.getElementById('feesTabContent');
        if (!c) return;
        if (!this.studentFees.length) { c.innerHTML = '<div class="alert alert-info">No student fee records</div>'; return; }
        let html = `<div class="table-responsive"><table class="table table-hover mb-0">
            <thead class="table-dark"><tr><th>Student</th><th>Class</th><th>Fee Type</th><th>Amount</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead><tbody>`;
        this.studentFees.forEach(f => {
            const amt = parseFloat(f.amount_due ?? f.amount ?? 0);
            const paid = parseFloat(f.amount_paid ?? 0);
            const bal = amt - paid;
            const sc = bal <= 0 ? 'success' : bal < amt ? 'warning' : 'danger';
            html += `<tr>
                <td>${f.student_name ?? ''}</td><td>${f.class_name ?? f.grade ?? ''}</td>
                <td>${f.fee_type ?? f.fee_name ?? ''}</td>
                <td>KES ${amt.toLocaleString()}</td>
                <td>KES ${paid.toLocaleString()}</td>
                <td class="fw-bold text-${sc}">KES ${Math.abs(bal).toLocaleString()}</td>
                <td><span class="badge bg-${sc}">${bal<=0?'Paid':'Owing'}</span></td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        c.innerHTML = html;
    },

    renderDefaulters: function() {
        const c = document.getElementById('feesTabContent');
        if (!c) return;
        if (!this.defaulters.length) { c.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>No defaulters!</div>'; return; }
        let html = `<div class="table-responsive"><table class="table table-hover mb-0">
            <thead class="table-dark"><tr><th>Student</th><th>Class</th><th>Amount Owed</th><th>Days Overdue</th></tr></thead><tbody>`;
        this.defaulters.forEach(d => {
            html += `<tr>
                <td>${d.student_name ?? ''}</td><td>${d.class_name ?? d.grade ?? ''}</td>
                <td class="text-danger fw-bold">KES ${parseFloat(d.balance ?? d.amount_owed ?? 0).toLocaleString()}</td>
                <td>${d.days_overdue ?? '—'}</td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        c.innerHTML = html;
    },

    bindFeeEvents: function(canEdit) {
        document.querySelectorAll('.del-fee').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                if (!confirm('Delete this fee structure?')) return;
                try {
                    if (window.API.finance?.deleteFeeStructure) {
                        await window.API.finance.deleteFeeStructure(e.currentTarget.dataset.id);
                    } else {
                        await apiCall(`/finance/fee-structures/${e.currentTarget.dataset.id}`, 'DELETE');
                    }
                    showNotification('Fee structure deleted', 'success');
                    await this.loadFeeStructures();
                } catch(err) { showNotification('Failed to delete', 'error'); }
            });
        });
    },

    bindTabEvents: function() {
        document.querySelectorAll('[data-tab]').forEach(link => {
            link.addEventListener('click', async (e) => {
                e.preventDefault();
                document.querySelectorAll('[data-tab]').forEach(l => l.classList.remove('active'));
                e.target.classList.add('active');
                this.tab = e.target.dataset.tab;
                if (this.tab === 'structures') await this.loadFeeStructures();
                else if (this.tab === 'student-fees') await this.loadStudentFees();
                else if (this.tab === 'defaulters') await this.loadDefaulters();
            });
        });
        document.getElementById('addFeeStructBtn')?.addEventListener('click', () => {
            showNotification('Add fee structure form — modal implementation needed', 'info');
        });
    }
};
document.addEventListener('DOMContentLoaded', () => feesController.init());
