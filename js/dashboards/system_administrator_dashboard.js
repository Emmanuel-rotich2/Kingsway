(() => {
  const root=document.getElementById('systemAdminDashboard'); if(!root)return;
  const state=document.getElementById('systemDashboardState');
  const esc=v=>String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  async function load(){
    state.className='alert alert-info'; state.textContent='Loading real system metrics…'; state.hidden=false; document.getElementById('systemMetricCards').hidden=true;
    try{
      const response=await API.systemAdministration.getDashboard(); const d=response.data??response;
      document.getElementById('metricEnabledUsers').textContent=d.enabled_users??0;
      document.getElementById('metricActiveSessions').textContent=d.active_sessions??0;
      document.getElementById('metricFailedLogins').textContent=d.failed_logins_24h??0;
      document.getElementById('metricIncidents').textContent=d.open_incidents??0;
      document.getElementById('metricJobs').textContent=d.pending_jobs??0;
      document.getElementById('metricDbLatency').textContent=d.database?.latency_ms??'—';
      document.getElementById('systemGeneratedAt').textContent=d.generated_at?`Generated ${new Date(d.generated_at).toLocaleString()}`:'';
      const rows=d.recent_activity??[]; document.getElementById('systemActivityRows').innerHTML=rows.length?rows.map(r=>`<tr><td>${esc(r.created_at)}</td><td>${esc(r.user_id)}</td><td>${esc(r.action)}</td><td>${esc(r.resource_type)} ${esc(r.resource_id)}</td><td>${esc(r.status)}</td><td>${esc(r.ip_address)}</td></tr>`).join(''):'<tr><td colspan="6" class="text-center text-muted py-4">No system activity recorded.</td></tr>';
      state.hidden=true; document.getElementById('systemMetricCards').hidden=false;
    }catch(e){state.className='alert alert-danger';state.innerHTML=`Unable to load system metrics. ${esc(e.message)} <button class="btn btn-sm btn-outline-danger ms-2" id="retrySystemDashboard">Retry</button>`;document.getElementById('retrySystemDashboard').onclick=load;}
  }
  document.getElementById('refreshSystemDashboard').onclick=load; load();
})();
