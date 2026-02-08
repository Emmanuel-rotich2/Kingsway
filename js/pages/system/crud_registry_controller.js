class CrudRegistryController {
    constructor(config) {
        this.config = Object.assign({ title: 'Registry', apiEndpoint: '/system/registry', columns: ['#','Name','Status','Actions'] }, config);
        this.allData = []; this.init();
    }
    async init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = '/Kingsway/index.php'; return; }
        this.setupEventListeners(); await this.loadData();
    }
    setupEventListeners() {
        document.getElementById('searchInput')?.addEventListener('input', () => this.filterData());
        document.getElementById('statusFilter')?.addEventListener('change', () => this.filterData());
    }
    async loadData() {
        try { const r = await window.API.apiCall(this.config.apiEndpoint, 'GET'); this.allData = r?.data || r || []; this.renderStats(this.allData); this.renderTable(this.allData); }
        catch (e) { console.error('Load failed:', e); this.renderTable([]); }
    }
    renderStats(data) {
        const items = Array.isArray(data) ? data : [];
        const el = (id, val) => { const e = document.getElementById(id); if (e) e.textContent = val; };
        el('statTotal', items.length);
        el('statActive', items.filter(i => (i.status||'') === 'active' || i.is_active === 1).length);
        el('statInactive', items.filter(i => (i.status||'') === 'inactive' || i.is_active === 0).length);
        el('statRecent', items.filter(i => { const d = i.created_at || i.updated_at; if(!d) return false; return (Date.now() - new Date(d).getTime()) < 604800000; }).length);
    }
    renderTable(items) {
        const tbody = document.querySelector('#dataTable tbody');
        if (!items.length) { tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No records found</td></tr>'; return; }
        tbody.innerHTML = items.map((item, i) => {
            const st = item.status || (item.is_active===1?'active':item.is_active===0?'inactive':'--');
            const bg = st==='active'?'success':st==='inactive'?'secondary':'info';
            return '<tr><td>'+(i+1)+'</td><td><strong>'+this.esc(item.name||item.title||item.key||'--')+'</strong></td><td>'+this.esc(item.description||item.value||'--')+'</td><td><span class="badge bg-'+bg+'">'+this.esc(st)+'</span></td><td>'+(item.created_at?new Date(item.created_at).toLocaleDateString():'--')+'</td><td><button class="btn btn-sm btn-outline-primary me-1" onclick="window._crudCtrl.editRecord('+i+')"><i class="fas fa-edit"></i></button><button class="btn btn-sm btn-outline-danger" onclick="window._crudCtrl.deleteRecord(\''+item.id+'\')"><i class="fas fa-trash"></i></button></td></tr>';
        }).join('');
    }
    filterData() {
        const s = (document.getElementById('searchInput')?.value||'').toLowerCase();
        const st = document.getElementById('statusFilter')?.value||'';
        this.renderTable(this.allData.filter(item => { if(s&&!JSON.stringify(item).toLowerCase().includes(s))return false; if(st&&(item.status||'')!==st)return false; return true; }));
    }
    showAddModal() {
        const t=document.getElementById('formModalTitle'); if(t)t.textContent='Add '+this.config.title;
        document.getElementById('recordForm')?.reset(); const id=document.getElementById('recordId'); if(id)id.value='';
        const m=document.getElementById('formModal'); if(m)new bootstrap.Modal(m).show();
    }
    editRecord(index) {
        const item=this.allData[index]; if(!item)return;
        const t=document.getElementById('formModalTitle'); if(t)t.textContent='Edit '+this.config.title;
        const id=document.getElementById('recordId'); if(id)id.value=item.id||'';
        const n=document.getElementById('recordName'); if(n)n.value=item.name||item.title||'';
        const d=document.getElementById('recordDescription'); if(d)d.value=item.description||'';
        const s=document.getElementById('recordStatus'); if(s)s.value=item.status||'active';
        const m=document.getElementById('formModal'); if(m)new bootstrap.Modal(m).show();
    }
    async saveRecord() {
        const id=document.getElementById('recordId')?.value;
        const data={name:document.getElementById('recordName')?.value,description:document.getElementById('recordDescription')?.value,status:document.getElementById('recordStatus')?.value};
        if(!data.name){this.notify('Name is required','warning');return;}
        try{await window.API.apiCall(id?this.config.apiEndpoint+'/'+id:this.config.apiEndpoint,id?'PUT':'POST',data);this.notify('Saved','success');bootstrap.Modal.getInstance(document.getElementById('formModal'))?.hide();await this.loadData();}
        catch(e){this.notify(e.message||'Save failed','danger');}
    }
    async deleteRecord(id) {
        if(!confirm('Delete this record?'))return;
        try{await window.API.apiCall(this.config.apiEndpoint+'/'+id,'DELETE');this.notify('Deleted','success');await this.loadData();}
        catch(e){this.notify(e.message||'Delete failed','danger');}
    }
    exportCSV() {
        if(!this.allData.length)return; const h=this.config.columns;
        const rows=this.allData.map(item=>Object.values(item).slice(0,h.length));
        let csv=h.join(',')+'\n'+rows.map(r=>r.map(v=>'"'+(v||'')+'"').join(',')).join('\n');
        const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv'}));a.download=this.config.title.toLowerCase().replace(/\s+/g,'_')+'.csv';a.click();
    }
    esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
    notify(msg,type){const modal=document.getElementById('notificationModal');if(modal){const m=modal.querySelector('.notification-message'),c=modal.querySelector('.modal-content');if(m)m.textContent=msg;if(c)c.className='modal-content notification-'+(type||'info');const b=bootstrap.Modal.getOrCreateInstance(modal);b.show();setTimeout(()=>b.hide(),3000);}}
}