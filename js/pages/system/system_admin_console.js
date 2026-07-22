(() => {
  const root=document.querySelector('[data-system-console]'); if(!root)return;
  const mode=root.dataset.mode, registry=root.dataset.registry||'', tbody=root.querySelector('tbody'), state=root.querySelector('[data-state]');
  const esc=v=>String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const renderRows=rows=>{if(!rows.length){tbody.innerHTML=`<tr><td colspan="8" class="text-center text-muted py-4">No records found.</td></tr>`;return;}const keys=Object.keys(rows[0]).slice(0,7);root.querySelector('thead').innerHTML=`<tr>${keys.map(k=>`<th>${esc(k.replaceAll('_',' '))}</th>`).join('')}<th>Actions</th></tr>`;tbody.innerHTML=rows.map(r=>`<tr>${keys.map(k=>`<td>${esc(typeof r[k]==='object'?JSON.stringify(r[k]):r[k])}</td>`).join('')}<td>${mode==='sessions'?`<button class="btn btn-sm btn-outline-danger" data-revoke="${r.id}">Revoke</button>`:''}</td></tr>`).join('');};
  async function load(){state.className='alert alert-info';state.textContent='Loading…';try{let res;if(mode==='accounts')res=await API.systemAdministration.getAccounts();else if(mode==='sessions')res=await API.systemAdministration.getSessions();else res=await API.systemAdministration.listRegistry(registry);renderRows(res.data??res??[]);state.hidden=true;}catch(e){state.hidden=false;state.className='alert alert-danger';state.textContent=e.message;}}
  tbody.addEventListener('click',async e=>{const b=e.target.closest('[data-revoke]');if(!b)return;if(!confirm('Revoke this session?'))return;await API.systemAdministration.revokeSession(Number(b.dataset.revoke));load();});
  root.querySelector('[data-refresh]').onclick=load; load();
})();
