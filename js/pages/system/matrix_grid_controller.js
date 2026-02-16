class MatrixGridController {
    constructor(config) {
        this.config = Object.assign({ title: 'Matrix', apiEndpoint: '/system/matrix', rowLabel: 'Role', colLabel: 'Permission' }, config);
        this.allData = { rows: [], columns: [], matrix: {} }; this.init();
    }
    async init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = '/Kingsway/index.php'; return; }
        document.getElementById('searchInput')?.addEventListener('input', () => this.filterData());
        await this.loadData();
    }
    async loadData() {
        try { const r = await window.API.apiCall(this.config.apiEndpoint, 'GET'); const d = r?.data || r || {};
            this.allData = { rows: d.rows||d.roles||[], columns: d.columns||d.permissions||d.entities||[], matrix: d.matrix||d.mappings||{} };
            this.renderStats(); this.renderMatrix(this.allData.rows);
        } catch (e) { console.error('Load failed:', e); this.renderMatrix([]); }
    }
    renderStats() {
        const el=(id,val)=>{const e=document.getElementById(id);if(e)e.textContent=val;};
        el('statRows',this.allData.rows.length); el('statCols',this.allData.columns.length);
        el('statActive',Object.keys(this.allData.matrix).length); el('statTotal',this.allData.rows.length*this.allData.columns.length);
    }
    renderMatrix(rows) {
        const c = document.getElementById('matrixContainer'); if (!c) return;
        if (!rows.length || !this.allData.columns.length) { c.innerHTML = '<div class="text-center text-muted py-4">No data available</div>'; return; }
        let h = '<div class="table-responsive"><table class="table table-bordered table-sm mb-0"><thead class="table-light"><tr><th>'+this.esc(this.config.rowLabel)+'</th>';
        this.allData.columns.slice(0,20).forEach(col => { h += '<th class="text-center" style="font-size:0.75rem;writing-mode:vertical-rl;min-width:40px">'+this.esc(typeof col==='string'?col:col.name||'')+'</th>'; });
        h += '</tr></thead><tbody>';
        rows.forEach(row => {
            const rn = typeof row==='string'?row:row.name||row.title||''; const ri = typeof row==='string'?row:row.id||'';
            h += '<tr><td class="fw-bold">'+this.esc(rn)+'</td>';
            this.allData.columns.slice(0,20).forEach(col => {
                const ci = typeof col==='string'?col:col.id||col.name||''; const chk = this.isChk(ri,ci);
                h += '<td class="text-center"><input type="checkbox" class="form-check-input" '+(chk?'checked':'')+' onchange="window._matrixCtrl.toggleCell(\''+ri+'\',\''+ci+'\',this.checked)"></td>';
            });
            h += '</tr>';
        });
        h += '</tbody></table></div>'; c.innerHTML = h;
    }
    isChk(r,c) { const m=this.allData.matrix; if(Array.isArray(m[r]))return m[r].includes(c); if(m[r]&&typeof m[r]==='object')return!!m[r][c]; return false; }
    async toggleCell(r,c,en) { try { await window.API.apiCall(this.config.apiEndpoint+'/toggle','POST',{row:r,column:c,enabled:en}); } catch(e) { this.notify(e.message||'Update failed','danger'); } }
    filterData() {
        const s=(document.getElementById('searchInput')?.value||'').toLowerCase();
        this.renderMatrix(this.allData.rows.filter(r=>{const n=typeof r==='string'?r:r.name||'';return!s||n.toLowerCase().includes(s);}));
    }
    exportCSV() {
        const h=[this.config.rowLabel,...this.allData.columns.map(c=>typeof c==='string'?c:c.name)];
        const rows=this.allData.rows.map(r=>{const ri=typeof r==='string'?r:r.id||r.name;return[typeof r==='string'?r:r.name,...this.allData.columns.map(c=>{const ci=typeof c==='string'?c:c.id||c.name;return this.isChk(ri,ci)?'Yes':'No';})];});
        let csv=h.join(',')+'\n'+rows.map(r=>r.map(v=>'"'+v+'"').join(',')).join('\n');
        const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv'}));a.download=this.config.title.toLowerCase().replace(/\s+/g,'_')+'_matrix.csv';a.click();
    }
    esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
    notify(msg,type){const modal=document.getElementById('notificationModal');if(modal){const m=modal.querySelector('.notification-message'),c=modal.querySelector('.modal-content');if(m)m.textContent=msg;if(c)c.className='modal-content notification-'+(type||'info');const b=bootstrap.Modal.getOrCreateInstance(modal);b.show();setTimeout(()=>b.hide(),3000);}}
}