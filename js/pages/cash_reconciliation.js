/**
 * Cash Reconciliation Controller
 * Daily cash count vs system-recorded collections.
 * API: /payments/collections, /finance/cash-reconciliation
 */

const cashReconcController = {

  _systemTotal: 0,
  _viewModal: null,

  _denominations: [
    { id: 'crDenom1000', sub: 'crSub1000', value: 1000 },
    { id: 'crDenom500',  sub: 'crSub500',  value: 500  },
    { id: 'crDenom200',  sub: 'crSub200',  value: 200  },
    { id: 'crDenom100',  sub: 'crSub100',  value: 100  },
    { id: 'crDenom50',   sub: 'crSub50',   value: 50   },
    { id: 'crDenom20',   sub: 'crSub20',   value: 20   },
    { id: 'crDenom10',   sub: 'crSub10',   value: 10   },
    { id: 'crDenom5',    sub: 'crSub5',    value: 5    },
    { id: 'crDenom1',    sub: 'crSub1',    value: 1    },
  ],

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    this._viewModal = new bootstrap.Modal(document.getElementById('crViewModal'));
    const today = new Date().toISOString().split('T')[0];
    const dateEl = document.getElementById('crDate');
    if (dateEl) dateEl.value = today;
    await Promise.all([this.loadDay(today), this._loadHistory()]);
  },

  loadDay: async function (date) {
    if (!date) { showNotification('Please select a date.', 'warning'); return; }

    this._set('crSystemTotal',   '—');
    this._set('crPhysicalTotal', '—');
    this._set('crVariance',      '—');
    this._set('crStatus',        '—');

    const systemTable = document.getElementById('crSystemTable');
    if (systemTable) systemTable.innerHTML = '<tr><td colspan="3" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';

    try {
      const [rCollections, rReconciled] = await Promise.allSettled([
        callAPI('/payments/collections?method=cash&date=' + date, 'GET'),
        callAPI('/finance/cash-reconciliation?date=' + date, 'GET'),
      ]);

      const collections = this._extract(rCollections);
      const reconciled  = this._extract(rReconciled);
      const reconRecord = Array.isArray(reconciled) ? reconciled[0] : reconciled;

      this._systemTotal = collections.reduce((s, c) => s + Number(c.amount || 0), 0);
      this._set('crSystemTotal', 'KES ' + this._systemTotal.toLocaleString());

      // Show form section
      const form = document.getElementById('crFormSection');
      if (form) form.style.display = '';

      // Render system transactions
      if (systemTable) {
        if (!collections.length) {
          systemTable.innerHTML = '<tr><td colspan="3" class="text-center py-3 text-muted">No cash transactions found for this date.</td></tr>';
        } else {
          systemTable.innerHTML = collections.map(c => `
            <tr>
              <td class="text-muted small">${this._esc(c.time || c.created_at?.split('T')[1]?.substring(0,5) || '—')}</td>
              <td>${this._esc(c.description || c.student_name || c.purpose || '—')}</td>
              <td class="text-end fw-semibold">KES ${Number(c.amount||0).toLocaleString()}</td>
            </tr>`).join('') +
            `<tr class="table-primary fw-bold">
              <td colspan="2">System Total</td>
              <td class="text-end">KES ${this._systemTotal.toLocaleString()}</td>
            </tr>`;
        }
      }

      // If already reconciled, show status + physical total
      if (reconRecord && reconRecord.physical_count) {
        const physical = Number(reconRecord.physical_count);
        const variance = physical - this._systemTotal;
        this._set('crPhysicalTotal', 'KES ' + physical.toLocaleString());
        this._setVariance(variance);
        this._set('crStatus', reconRecord.status || 'Submitted');
      } else {
        this._set('crStatus', 'Pending');
      }
    } catch (e) {
      if (systemTable) systemTable.innerHTML = `<tr><td colspan="3" class="text-danger text-center py-3">Failed: ${this._esc(e.message)}</td></tr>`;
    }
  },

  computePhysicalTotal: function () {
    let total = 0;
    this._denominations.forEach(d => {
      const count = Number(document.getElementById(d.id)?.value || 0);
      const sub   = count * d.value;
      total += sub;
      const subEl = document.getElementById(d.sub);
      if (subEl) subEl.textContent = sub.toLocaleString(undefined, { minimumFractionDigits: 2 });
    });

    const totalEl = document.getElementById('crPhysicalTotalRow');
    if (totalEl) totalEl.textContent = total.toLocaleString(undefined, { minimumFractionDigits: 2 });

    this._set('crPhysicalTotal', 'KES ' + total.toLocaleString());
    const variance = total - this._systemTotal;
    this._setVariance(variance);
  },

  _setVariance: function (variance) {
    const varEl = document.getElementById('crVariance');
    if (!varEl) return;
    varEl.textContent = (variance >= 0 ? '+' : '') + 'KES ' + Math.abs(variance).toLocaleString();
    varEl.className = 'fs-3 fw-bold ' + (variance === 0 ? 'text-success' : variance > 0 ? 'text-info' : 'text-danger');
  },

  submitReconciliation: async function () {
    const date = document.getElementById('crDate')?.value;
    if (!date) { showNotification('Please select a date.', 'warning'); return; }

    let physicalTotal = 0;
    this._denominations.forEach(d => {
      physicalTotal += Number(document.getElementById(d.id)?.value || 0) * d.value;
    });

    if (!physicalTotal) { showNotification('Please enter the physical cash count.', 'warning'); return; }

    const notes = document.getElementById('crNotes')?.value || '';
    const denomBreakdown = {};
    this._denominations.forEach(d => {
      denomBreakdown[d.value] = Number(document.getElementById(d.id)?.value || 0);
    });

    try {
      await callAPI('/finance/cash-reconciliation', 'POST', {
        date,
        system_total:    this._systemTotal,
        physical_count:  physicalTotal,
        variance:        physicalTotal - this._systemTotal,
        notes,
        denomination_breakdown: denomBreakdown,
      });
      showNotification('Reconciliation submitted successfully.', 'success');
      await Promise.all([this.loadDay(date), this._loadHistory()]);
    } catch (e) {
      showNotification(e.message || 'Submission failed.', 'danger');
    }
  },

  _loadHistory: async function () {
    const tbody = document.getElementById('crHistoryTable');
    if (!tbody) return;
    try {
      const r = await callAPI('/finance/cash-reconciliation', 'GET');
      const items = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);

      if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">No reconciliation history yet.</td></tr>';
        return;
      }

      const statusCls = { submitted: 'success', pending: 'warning', discrepancy: 'danger' };
      tbody.innerHTML = items.map(h => {
        const variance = Number(h.variance || 0);
        const status   = (h.status || 'pending').toLowerCase();
        return `<tr>
          <td>${this._esc(h.date || '—')}</td>
          <td class="text-end">KES ${Number(h.system_total||0).toLocaleString()}</td>
          <td class="text-end">KES ${Number(h.physical_count||0).toLocaleString()}</td>
          <td class="text-end fw-bold ${variance===0?'text-success':variance>0?'text-info':'text-danger'}">
            ${variance>=0?'+':''}KES ${Math.abs(variance).toLocaleString()}
          </td>
          <td class="text-center"><span class="badge bg-${statusCls[status]||'secondary'}">${this._esc(h.status||'—')}</span></td>
          <td>${this._esc(h.submitted_by || h.created_by || '—')}</td>
          <td class="text-center">
            <button class="btn btn-sm btn-outline-primary" onclick="cashReconcController.viewDetail(${h.id})">View</button>
          </td>
        </tr>`;
      }).join('');
    } catch (e) {
      tbody.innerHTML = `<tr><td colspan="7" class="text-danger text-center py-3">Failed to load history.</td></tr>`;
    }
  },

  viewDetail: async function (id) {
    const body = document.getElementById('crViewModalBody');
    if (body) body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    this._viewModal.show();
    try {
      const r = await callAPI('/finance/cash-reconciliation/' + id, 'GET');
      const d = r?.data ?? r ?? {};
      if (body) {
        body.innerHTML = `
          <div class="row g-3">
            <div class="col-md-4"><strong>Date:</strong> ${this._esc(d.date||'—')}</div>
            <div class="col-md-4"><strong>System Total:</strong> KES ${Number(d.system_total||0).toLocaleString()}</div>
            <div class="col-md-4"><strong>Physical Count:</strong> KES ${Number(d.physical_count||0).toLocaleString()}</div>
            <div class="col-md-4"><strong>Variance:</strong> KES ${Number(d.variance||0).toLocaleString()}</div>
            <div class="col-md-4"><strong>Submitted By:</strong> ${this._esc(d.submitted_by||'—')}</div>
            <div class="col-md-4"><strong>Status:</strong> ${this._esc(d.status||'—')}</div>
            ${d.notes ? `<div class="col-12"><strong>Notes:</strong><p class="mt-1">${this._esc(d.notes)}</p></div>` : ''}
          </div>`;
      }
    } catch (e) {
      if (body) body.innerHTML = `<div class="alert alert-danger">Failed to load details: ${this._esc(e.message)}</div>`;
    }
  },

  _extract: function (settled) {
    if (settled.status !== 'fulfilled') return [];
    const r = settled.value;
    return Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
  },

  _set: (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => cashReconcController.init());
