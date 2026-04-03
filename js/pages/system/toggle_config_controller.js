class ToggleConfigController {
    constructor(config) {
        this.config = Object.assign({ title: 'Configuration', apiEndpoint: '/system/config' }, config);
        this.allData = []; this.init();
    }
    async init() {
        if (typeof AuthContext !== 'undefined' && !AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE || '') + '/index.php'; return; }
        await this.loadData();
    }
    async loadData() {
        try { const r = await window.API.apiCall(this.config.apiEndpoint, 'GET'); this.allData = r?.data || r || [];
            this.renderSettings(Array.isArray(this.allData) ? this.allData : Object.entries(this.allData).map(([k,v]) => ({key:k,value:v,enabled:!!v})));
        } catch (e) { console.error('Load failed:', e); this.renderSettings([]); }
    }
    renderSettings(items) {
        const c = document.getElementById('settingsContainer'); if (!c) return;
        if (!items.length) { c.innerHTML = '<div class="text-center text-muted py-4">No settings found</div>'; return; }
        c.innerHTML = items.map((item, i) => '<div class="card mb-3 shadow-sm"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="mb-1">'+this.esc(item.name||item.key||'Setting '+(i+1))+'</h6><small class="text-muted">'+this.esc(item.description||'')+'</small></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="toggle_'+i+'" '+(item.enabled||item.is_active?'checked':'')+' onchange="window._toggleCtrl.toggleSetting('+i+',this.checked)"></div></div></div>').join('');
        const el=(id,val)=>{const e=document.getElementById(id);if(e)e.textContent=val;};
        el('statTotal',items.length); el('statEnabled',items.filter(i=>i.enabled||i.is_active).length); el('statDisabled',items.filter(i=>!i.enabled&&!i.is_active).length);
    }
    async toggleSetting(index, enabled) {
        const item = this.allData[index]; if (!item) return;
        try { await window.API.apiCall(this.config.apiEndpoint + '/' + (item.id||item.key), 'PUT', {enabled}); this.notify((enabled?'Enabled':'Disabled')+' successfully','success'); item.enabled=enabled; }
        catch (e) { this.notify(e.message||'Update failed','danger'); }
    }
    esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
    notify(msg,type){const modal=document.getElementById('notificationModal');if(modal){const m=modal.querySelector('.notification-message'),c=modal.querySelector('.modal-content');if(m)m.textContent=msg;if(c)c.className='modal-content notification-'+(type||'info');const b=bootstrap.Modal.getOrCreateInstance(modal);b.show();setTimeout(()=>b.hide(),3000);}}
}