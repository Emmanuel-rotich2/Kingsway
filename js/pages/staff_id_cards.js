const StaffIdCardsController = {
  cards: [], staff: [], previewHtml: '',
  async init() {
    if (!(await StaffAccess.require('staff.id_cards.view'))) return;
    this.bind(); await Promise.all([this.loadStaff(), this.loadCards()]);
    const expiry = document.getElementById('cardExpiry'); if (expiry) { const d=new Date(); d.setFullYear(d.getFullYear()+2); expiry.value=d.toISOString().slice(0,10); }
  },
  bind() {
    document.getElementById('newStaffCardBtn')?.addEventListener('click',()=>bootstrap.Modal.getOrCreateInstance('#generateStaffCardModal').show());
    document.getElementById('generateStaffCardSubmit')?.addEventListener('click',()=>this.generate());
    document.getElementById('refreshCardsBtn')?.addEventListener('click',()=>this.loadCards());
    document.getElementById('cardStatus')?.addEventListener('change',()=>this.render());
    document.getElementById('cardSearch')?.addEventListener('input',()=>this.render());
    document.getElementById('printStaffCardBtn')?.addEventListener('click',()=>this.printPreview());
  },
  async loadStaff() {
    try { const data=await API.staff.list({limit:500}); this.staff=this.staffList(data); const sel=document.getElementById('cardStaffId'); sel.innerHTML='<option value="">Select staff</option>'+this.staff.map(s=>`<option value="${s.id}">${this.esc(s.staff_no||'')} — ${this.esc((s.first_name||'')+' '+(s.last_name||''))}</option>`).join(''); } catch(e){ this.notify(e.message,'error'); }
  },
  staffList(data) { return Array.isArray(data)?data:(data?.staff||data?.data?.staff||data?.items||data?.data||[]); },
  async loadCards() {
    const body=document.getElementById('staffCardsBody'); body.innerHTML='<tr><td colspan="7" class="text-center py-4"><span class="spinner-border spinner-border-sm"></span></td></tr>';
    try { const data=await API.staff.getIdCards(); this.cards=Array.isArray(data)?data:(data?.items||[]); this.render(); } catch(e){ body.innerHTML=`<tr><td colspan="7" class="text-danger text-center py-4">${this.esc(e.message)}</td></tr>`; }
  },
  render() {
    const q=(document.getElementById('cardSearch')?.value||'').toLowerCase(), status=document.getElementById('cardStatus')?.value||'';
    const rows=this.cards.filter(c=>(!status||c.status===status)&&(!q||`${c.first_name} ${c.last_name} ${c.staff_no} ${c.card_number}`.toLowerCase().includes(q)));
    document.getElementById('cardTotal').textContent=this.cards.length; document.getElementById('cardGenerated').textContent=this.cards.filter(c=>c.status==='generated').length; document.getElementById('cardIssued').textContent=this.cards.filter(c=>c.status==='issued').length; document.getElementById('cardExpired').textContent=this.cards.filter(c=>c.status==='expired'||(c.expires_at&&new Date(c.expires_at)<new Date())).length;
    document.getElementById('staffCardsBody').innerHTML=rows.length?rows.map(c=>`<tr><td><strong>${this.esc(c.first_name+' '+c.last_name)}</strong><br><small>${this.esc(c.position||'')}</small></td><td>${this.esc(c.staff_no)}</td><td>${this.esc(c.department_name||'—')}</td><td>${this.esc(c.card_number)}</td><td>${this.esc(c.expires_at||'—')}</td><td><span class="badge bg-${c.status==='issued'?'success':c.status==='generated'?'primary':'secondary'}">${this.esc(c.status)}</span></td><td class="text-end"><button class="btn btn-sm btn-outline-primary" onclick="StaffIdCardsController.regenerate(${c.staff_id})" data-permission="staff.id_cards.manage">Regenerate</button> ${c.status!=='issued'?`<button class="btn btn-sm btn-success" onclick="StaffIdCardsController.issue(${c.staff_id})" data-permission="staff.id_cards.manage">Issue</button>`:''}</td></tr>`).join(''):'<tr><td colspan="7" class="text-center text-muted py-4">No staff ID cards found.</td></tr>'; StaffAccess.apply();
  },
  async generate(staffId=null) {
    staffId=staffId||Number(document.getElementById('cardStaffId').value); if(!staffId)return this.notify('Select a staff member','error');
    try { const payload={staff_id:staffId,expires_at:document.getElementById('cardExpiry')?.value||null,format:document.getElementById('cardFormat')?.value||'html',side:document.getElementById('cardSide')?.value||'both'}; const result=await API.staff.generateIdCard(payload); this.previewHtml=typeof result?.document==='string'?result.document:(result?.document?.html||`<pre>${this.esc(JSON.stringify(result,null,2))}</pre>`); document.getElementById('staffCardPreviewBody').innerHTML=this.previewHtml; bootstrap.Modal.getInstance(document.getElementById('generateStaffCardModal'))?.hide(); bootstrap.Modal.getOrCreateInstance(document.getElementById('staffCardPreviewModal')).show(); await this.loadCards(); this.notify('Staff ID card generated','success'); } catch(e){this.notify(e.message,'error');}
  },
  regenerate(id){document.getElementById('cardStaffId').value=id;this.generate(id);},
  async issue(staffId){try{await API.staff.issueIdCard({staff_id:staffId});await this.loadCards();this.notify('Card marked as issued','success');}catch(e){this.notify(e.message,'error');}},
  printPreview(){const w=window.open('','_blank');w.document.write(`<html><head><title>Staff ID Card</title></head><body>${this.previewHtml}</body></html>`);w.document.close();w.focus();w.print();},
  notify(m,t='info'){window.API?.showNotification?.(m,t)||alert(m);}, esc(v){const d=document.createElement('div');d.textContent=String(v??'');return d.innerHTML;}
};
document.addEventListener('DOMContentLoaded',()=>StaffIdCardsController.init());
