/**
 * Asset Depreciation Controller
 * Straight-line depreciation schedule for all fixed assets.
 * API: /inventory/assets, /inventory/depreciation
 */

const depreciationController = {

  _data: [],
  _filtered: [],

  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    await this._loadData();
  },

  _loadData: async function () {
    const tbody = document.getElementById('dpTableBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></td></tr>';
    try {
      const [rAssets, rDep] = await Promise.allSettled([
        callAPI('/inventory/assets', 'GET'),
        callAPI('/inventory/depreciation', 'GET'),
      ]);

      const assets   = this._extract(rAssets);
      const depData  = this._extract(rDep);

      // Merge depreciation data onto assets
      const depMap = {};
      depData.forEach(d => { depMap[d.asset_id || d.id] = d; });

      this._data = assets.map(a => {
        const dep         = depMap[a.id] || {};
        const cost        = Number(a.purchase_price || a.cost || 0);
        const rate        = Number(a.depreciation_rate || dep.rate || 20) / 100;
        const annual      = cost * rate;
        const years       = a.purchase_date ? (new Date().getFullYear() - new Date(a.purchase_date).getFullYear()) : 0;
        const accumulated = Math.min(cost, annual * Math.max(years, 1));
        const bookValue   = Math.max(0, cost - accumulated);
        return { ...a, annual_dep: annual, accumulated_dep: accumulated, computed_book_value: bookValue };
      });
      this._filtered = [...this._data];

      this._computeStats();
      this._populateFilters();
      this._render(this._filtered);
    } catch (e) {
      tbody.innerHTML = `<tr><td colspan="9" class="text-danger text-center py-4">Failed to load depreciation data.</td></tr>`;
    }
  },

  _computeStats: function () {
    const total    = this._data.length;
    const original = this._data.reduce((s, a) => s + Number(a.purchase_price || a.cost || 0), 0);
    const current  = this._data.reduce((s, a) => s + (a.current_value ?? a.computed_book_value ?? 0), 0);
    const dep      = original - current;
    this._set('dpStatAssets',    total);
    this._set('dpStatOriginal',  'KES ' + original.toLocaleString());
    this._set('dpStatCurrentVal','KES ' + Math.max(0, current).toLocaleString());
    this._set('dpStatTotalDep',  'KES ' + Math.max(0, dep).toLocaleString());
  },

  _populateFilters: function () {
    const categories = [...new Set(this._data.map(a => a.category).filter(Boolean))];
    const catSel = document.getElementById('dpCategory');
    if (catSel) {
      catSel.innerHTML = '<option value="">All Categories</option>' +
        categories.map(c => `<option value="${this._esc(c)}">${this._esc(c)}</option>`).join('');
    }

    const years = [...new Set(this._data.map(a => a.purchase_date ? new Date(a.purchase_date).getFullYear() : null).filter(Boolean))].sort((a,b) => b-a);
    const yearSel = document.getElementById('dpYear');
    if (yearSel) {
      yearSel.innerHTML = '<option value="">All Years</option>' +
        years.map(y => `<option value="${y}">${y}</option>`).join('');
    }
  },

  filter: function () {
    const cat  = document.getElementById('dpCategory')?.value || '';
    const year = document.getElementById('dpYear')?.value || '';
    this._filtered = this._data.filter(a => {
      const matchCat  = !cat  || (a.category || '') === cat;
      const matchYear = !year || (a.purchase_date || '').startsWith(year);
      return matchCat && matchYear;
    });
    this._render(this._filtered);
  },

  _render: function (items) {
    const tbody = document.getElementById('dpTableBody');
    if (!tbody) return;
    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No assets found.</td></tr>';
      return;
    }
    tbody.innerHTML = items.map(a => {
      const cost      = Number(a.purchase_price || a.cost || 0);
      const bookVal   = Number(a.current_value ?? a.computed_book_value ?? 0);
      const depRate   = Number(a.depreciation_rate || 20);
      const annualDep = Number(a.annual_dep || cost * depRate / 100);
      const pctLeft   = cost > 0 ? Math.round((bookVal / cost) * 100) : 0;
      const barCls    = pctLeft > 50 ? 'bg-success' : pctLeft > 25 ? 'bg-warning' : 'bg-danger';
      return `<tr>
        <td class="text-muted small">${this._esc(a.asset_code || a.id || '—')}</td>
        <td class="fw-semibold">${this._esc(a.name || a.asset_name || '—')}</td>
        <td>${this._esc(a.category || '—')}</td>
        <td>${this._esc(a.purchase_date || '—')}</td>
        <td>KES ${cost.toLocaleString()}</td>
        <td>${depRate}%</td>
        <td>KES ${annualDep.toLocaleString(undefined,{maximumFractionDigits:0})}</td>
        <td class="fw-bold">KES ${Math.max(0,bookVal).toLocaleString(undefined,{maximumFractionDigits:0})}</td>
        <td>
          <div class="d-flex align-items-center gap-2">
            <div class="progress flex-grow-1" style="height:6px;">
              <div class="progress-bar ${barCls}" style="width:${pctLeft}%"></div>
            </div>
            <span class="small fw-semibold">${pctLeft}%</span>
          </div>
        </td>
      </tr>`;
    }).join('');
  },

  exportCSV: function () {
    if (!this._filtered.length) { showNotification('No data to export.', 'warning'); return; }
    const header = ['Asset Code','Name','Category','Purchase Date','Original Cost','Dep Rate %','Annual Dep','Book Value','% Remaining'];
    const rows = [header.join(','), ...this._filtered.map(a => {
      const cost    = Number(a.purchase_price || a.cost || 0);
      const bv      = Math.max(0, Number(a.current_value ?? a.computed_book_value ?? 0));
      const pct     = cost > 0 ? Math.round((bv/cost)*100) : 0;
      const annual  = Number(a.annual_dep || cost * Number(a.depreciation_rate||20) / 100);
      return [`"${a.asset_code||''}"`,`"${a.name||''}"`,`"${a.category||''}"`,`"${a.purchase_date||''}"`,
        cost, Number(a.depreciation_rate||20), annual.toFixed(0), bv.toFixed(0), pct].join(',');
    })];
    const blob = new Blob([rows.join('\n')], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `depreciation_schedule_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
  },

  _extract: function (settled) {
    if (settled.status !== 'fulfilled') return [];
    const r = settled.value;
    return Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
  },

  _set: (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => depreciationController.init());
