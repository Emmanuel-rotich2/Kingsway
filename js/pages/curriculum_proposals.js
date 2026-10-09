(() => {
  'use strict';
  const state = { context: null, proposals: [], history: [], strands: [], subStrands: [], admin: false };
  const el = id => document.getElementById(id);
  const esc = value => { const node=document.createElement('span'); node.textContent=value??''; return node.innerHTML; };
  const rows = value => Array.isArray(value) ? value : (value?.data || []);
  const api = (path, method='GET', body=null) => window.API.apiCall(`/academic/${path}`, method, body);

  async function init() {
    await window.AuthContext?.ready?.();
    state.admin = (window.AuthContext?.getRoles?.() || []).some(role => {
      const name=String(typeof role==='object'?(role.name||role.role_name||''):role).toLowerCase();
      return name==='school administrator' || Number(role?.id||role)===4;
    });
    document.querySelectorAll('.admin-source').forEach(node => node.classList.toggle('d-none', !state.admin));
    bind();
    await loadContext();
    await Promise.all([loadProposals(), loadHistory()]);
  }

  function bind() {
    el('newProposalBtn')?.addEventListener('click', openProposal);
    el('yearFilter')?.addEventListener('change', async () => { await loadContext(Number(el('yearFilter').value)); await Promise.all([loadProposals(),loadHistory()]); });
    ['statusFilter','entityFilter'].forEach(id => el(id)?.addEventListener('change',loadProposals));
    ['historyArea','historyEntity'].forEach(id => el(id)?.addEventListener('change',loadHistory));
    ['proposalEntity','proposalAction','proposalArea','proposalGrade'].forEach(id => el(id)?.addEventListener('change', refreshFormChoices));
    el('proposalTarget')?.addEventListener('change', prefillTarget);
    el('saveDraftBtn')?.addEventListener('click',()=>save(false));
    el('saveSubmitBtn')?.addEventListener('click',()=>save(true));
    document.querySelectorAll('#reviewModal [data-decision]').forEach(btn => btn.addEventListener('click',()=>review(btn.dataset.decision)));
    document.querySelector('[data-bs-target="#historyPane"]')?.addEventListener('shown.bs.tab',loadHistory);
  }

  async function loadContext(yearId=0) {
    const suffix=yearId?`?academic_year_id=${yearId}`:'';
    state.context=await api(`curriculum-proposal-context${suffix}`);
    const c=state.context?.data||state.context;
    const scope=c.scope||{};
    el('yearFilter').innerHTML=(c.years||[]).map(y=>`<option value="${y.id}" ${Number(y.id)===Number(scope.academic_year_id)?'selected':''}>${esc(y.year_name)}${y.is_current?' (Current)':''}</option>`).join('');
    el('proposalTerm').innerHTML='<option value="">Whole academic year</option>'+(c.terms||[]).map(t=>`<option value="${t.id}">${esc(t.term_name)}</option>`).join('');
    fillAreaSelects(c.learning_areas||[]);
    if (scope.restricted) {
      el('scopeTitle').textContent=`${(scope.contexts||[]).length} assigned teaching context${(scope.contexts||[]).length===1?'':'s'}`;
      el('scopeChips').innerHTML=(scope.contexts||[]).map(x=>`<span class="cw-scope-chip"><i class="bi bi-book"></i>${esc(x.learning_area_name)} · ${esc(x.class_name||x.grade_level)}</span>`).join('') || '<div class="alert alert-warning mb-0">No curriculum assignment exists for this academic year. Ask the academic office to configure your class or subject assignment.</div>';
    } else {
      el('scopeTitle').textContent='School-wide curriculum oversight';
      el('scopeChips').innerHTML='<span class="cw-scope-chip"><i class="bi bi-shield-check"></i>All learning areas and grade levels</span>';
    }
  }

  function fillAreaSelects(areas) {
    const options='<option value="">Select learning area</option>'+areas.map(a=>`<option value="${a.id}">${esc(a.name)} (${esc(a.code)})</option>`).join('');
    el('proposalArea').innerHTML=options;
    el('historyArea').innerHTML='<option value="">All assigned learning areas</option>'+options.replace('<option value="">Select learning area</option>','');
  }

  async function loadProposals() {
    const params=new URLSearchParams({academic_year_id:el('yearFilter')?.value||''});
    if(el('statusFilter')?.value)params.set('status',el('statusFilter').value);
    if(el('entityFilter')?.value)params.set('entity_type',el('entityFilter').value);
    state.proposals=rows(await api(`curriculum-proposals?${params}`));
    renderProposals();
  }

  function renderProposals() {
    el('proposalCount').textContent=state.proposals.length;
    if(!state.proposals.length){el('proposalCards').innerHTML='<div class="col-12"><div class="cw-empty"><i class="bi bi-journal-check fs-2 d-block mb-2"></i>No proposals match this view.</div></div>';return;}
    el('proposalCards').innerHTML=state.proposals.map(p=>{
      const proposed=p.proposed_data||{}; const original=p.original_snapshot||{};
      const action={create:'Add',update:'Modify',remove:'Retire'}[p.change_action]||p.change_action;
      const review=state.admin&&p.status==='submitted'?`<button class="btn btn-sm btn-success" data-review="${p.id}"><i class="bi bi-check2-square"></i> Review</button>`:'';
      const submit=p.status==='draft'?`<button class="btn btn-sm btn-outline-success" data-submit="${p.id}">Submit</button>`:'';
      return `<div class="col-xl-6"><article class="cw-card ${esc(p.status)} h-100"><div class="d-flex justify-content-between gap-2"><div><div class="small text-uppercase text-muted fw-bold">${esc(p.entity_type.replace('_',' '))} · ${esc(action)}</div><h5 class="mb-1">${esc(proposed.name||original.name||p.learning_area_name)}</h5><div class="small text-muted">${esc(p.learning_area_name||'')} · ${esc(p.grade_level)} · ${esc(p.academic_year||'')}</div></div><span class="badge align-self-start text-bg-${statusColor(p.status)}">${esc(p.status)}</span></div><p class="my-3">${esc(p.rationale)}</p><div class="cw-diff mb-3">${esc(diffText(original,proposed,p.change_action))}</div><div class="d-flex justify-content-between align-items-center"><small class="text-muted">${esc(p.proposer_name||'')} · ${esc(p.change_source)}</small><div class="d-flex gap-2">${submit}${review}</div></div>${p.review_notes?`<div class="alert alert-light border mt-3 mb-0"><strong>Review:</strong> ${esc(p.review_notes)}</div>`:''}</article></div>`;
    }).join('');
    document.querySelectorAll('[data-submit]').forEach(b=>b.addEventListener('click',()=>submitProposal(Number(b.dataset.submit))));
    document.querySelectorAll('[data-review]').forEach(b=>b.addEventListener('click',()=>openReview(Number(b.dataset.review))));
  }

  async function loadHistory() {
    const params=new URLSearchParams();
    if(el('yearFilter')?.value)params.set('academic_year_id',el('yearFilter').value);
    if(el('historyArea')?.value)params.set('learning_area_id',el('historyArea').value);
    if(el('historyEntity')?.value)params.set('entity_type',el('historyEntity').value);
    state.history=rows(await api(`curriculum-history?${params}`)); renderHistory();
  }

  function renderHistory(){
    if(!state.history.length){el('historyTimeline').innerHTML='<div class="cw-empty">No governed changes have been approved in this period yet. Existing curriculum becomes the baseline when its first proposal is approved.</div>';return;}
    el('historyTimeline').innerHTML=state.history.map(v=>{const s=v.snapshot||{},teaching=v.event_type==='teaching_assignment';return `<article class="cw-event cw-card"><div class="d-flex justify-content-between flex-wrap gap-2"><div><div class="small fw-bold text-success text-uppercase">${esc(v.academic_year||'Legacy baseline')} ${v.term_name?'· '+esc(v.term_name):''}</div><h5>${esc(s.name||v.learning_area_name)}</h5></div><div><span class="badge text-bg-light">${teaching?'Teaching period':'Version '+v.version_number}</span> <span class="badge text-bg-${v.lifecycle_status==='removed'?'danger':v.lifecycle_status==='inactive'?'secondary':'success'}">${esc(v.lifecycle_status)}</span></div></div><p class="mb-2">${esc(v.rationale||'Original state before governed history')}</p><div class="small text-muted">${teaching?'Assignment record':esc(v.change_source)+(v.source_reference?' · '+esc(v.source_reference):'')+' · changed by '+esc(v.changed_by_name||'legacy import')+(v.approved_by_name?' · approved by '+esc(v.approved_by_name):'')}</div></article>`;}).join('');
  }

  function openProposal(){
    el('proposalForm').reset(); el('proposalId').value='';
    el('proposalGrade').innerHTML=''; refreshGrades(); refreshFormChoices();
    bootstrap.Modal.getOrCreateInstance(el('proposalModal')).show();
  }

  function refreshGrades(){
    const area=Number(el('proposalArea').value); const scope=(state.context?.data||state.context)?.scope||{};
    const grades=scope.restricted?[...new Set((scope.contexts||[]).filter(x=>Number(x.learning_area_id)===area).map(x=>x.grade_level))]:['PlayGroup','PP1','PP2',...Array.from({length:9},(_,i)=>`Grade ${i+1}`)];
    el('proposalGrade').innerHTML='<option value="">Select grade</option>'+grades.map(g=>`<option>${esc(g)}</option>`).join('');
  }

  async function refreshFormChoices(event){
    if(event?.target?.id==='proposalArea')refreshGrades();
    const entity=el('proposalEntity').value, action=el('proposalAction').value;
    const createOption=el('proposalAction').querySelector('option[value="create"]');
    createOption.disabled=entity==='learning_area';
    if(entity==='learning_area'&&action==='create')el('proposalAction').value='update';
    document.querySelectorAll('.proposed-field').forEach(n=>n.classList.toggle('d-none',action==='remove'));
    el('targetWrap').classList.toggle('d-none',action==='create'); el('parentStrandWrap').classList.toggle('d-none',!(entity==='sub_strand'&&action==='create'));
    const area=el('proposalArea').value,grade=el('proposalGrade').value;
    if(!area||!grade)return;
    state.strands=rows(await api(`strands?learning_area_id=${area}&grade_level=${encodeURIComponent(grade)}&academic_year_id=${el('yearFilter').value}`));
    el('proposalParentStrand').innerHTML='<option value="">Select strand</option>'+state.strands.map(s=>`<option value="${s.id}">${esc(s.name)}</option>`).join('');
    let targets=state.strands;
    if(entity==='learning_area')targets=((state.context?.data||state.context)?.learning_areas||[]);
    if(entity==='sub_strand'){state.subStrands=rows(await api(`sub-strands?learning_area_id=${area}&grade_level=${encodeURIComponent(grade)}&academic_year_id=${el('yearFilter').value}`));targets=state.subStrands;}
    el('proposalTarget').innerHTML='<option value="">Select existing item</option>'+targets.map(x=>`<option value="${x.id}">${esc(x.code||'')} · ${esc(x.name)}</option>`).join('');
  }

  function prefillTarget(){
    const id=Number(el('proposalTarget').value), entity=el('proposalEntity').value;
    const source=entity==='strand'?state.strands:entity==='sub_strand'?state.subStrands:((state.context?.data||state.context)?.learning_areas||[]);
    const item=source.find(x=>Number(x.id)===id);if(!item)return;
    el('proposalCode').value=item.code||'';el('proposalName').value=item.name||'';el('proposalDescription').value=item.description||'';
    if(entity==='sub_strand')el('proposalParentStrand').value=item.strand_id||'';
  }

  function payload(){return {entity_type:el('proposalEntity').value,change_action:el('proposalAction').value,target_entity_id:Number(el('proposalTarget').value)||null,learning_area_id:Number(el('proposalArea').value),grade_level:el('proposalEntity').value==='learning_area'?'All':el('proposalGrade').value,academic_year_id:Number(el('yearFilter').value),academic_year_term_id:Number(el('proposalTerm').value)||null,rationale:el('proposalRationale').value.trim(),change_source:el('proposalSource').value,source_reference:el('proposalReference').value.trim(),proposed_data:{strand_id:Number(el('proposalParentStrand').value)||undefined,code:el('proposalCode').value.trim(),name:el('proposalName').value.trim(),description:el('proposalDescription').value.trim(),status:'active'}};}
  async function save(andSubmit){try{const id=Number(el('proposalId').value);const result=await api(id?`curriculum-proposals/${id}`:'curriculum-proposals',id?'PUT':'POST',payload());const proposal=result?.data||result;if(andSubmit)await api('curriculum-proposals-submit','POST',{proposal_id:proposal.id});bootstrap.Modal.getInstance(el('proposalModal'))?.hide();window.showNotification?.(andSubmit?'Proposal submitted for review.':'Draft saved.','success');await loadProposals();}catch(e){window.showNotification?.(e.message||'Could not save proposal.','error');}}
  async function submitProposal(id){try{await api('curriculum-proposals-submit','POST',{proposal_id:id});window.showNotification?.('Proposal submitted.','success');await loadProposals();}catch(e){window.showNotification?.(e.message||'Could not submit proposal.','error');}}
  function openReview(id){const p=state.proposals.find(x=>Number(x.id)===id);el('reviewProposalId').value=id;el('reviewNotes').value='';el('reviewSummary').textContent=`${p.change_action.toUpperCase()} ${p.entity_type.replace('_',' ')}\n${diffText(p.original_snapshot||{},p.proposed_data||{},p.change_action)}\nReason: ${p.rationale}`;bootstrap.Modal.getOrCreateInstance(el('reviewModal')).show();}
  async function review(decision){try{await api('curriculum-proposals-review','POST',{proposal_id:Number(el('reviewProposalId').value),decision,review_notes:el('reviewNotes').value.trim()});bootstrap.Modal.getInstance(el('reviewModal'))?.hide();window.showNotification?.(`Proposal ${decision}.`,'success');await Promise.all([loadProposals(),loadHistory()]);}catch(e){window.showNotification?.(e.message||'Review could not be recorded.','error');}}
  function diffText(before,after,action){if(action==='create')return `New: ${after.code||''} ${after.name||''}`.trim();if(action==='remove')return `Retire: ${before.code||''} ${before.name||''}`.trim();return Object.keys(after).filter(k=>String(after[k]??'')!==String(before[k]??'')).map(k=>`${k}: ${before[k]??'—'} → ${after[k]??'—'}`).join('\n')||'No field changes';}
  function statusColor(status){return {submitted:'warning',draft:'secondary',approved:'success',rejected:'danger',withdrawn:'dark'}[status]||'secondary';}
  document.readyState==='loading'?document.addEventListener('DOMContentLoaded',init):init();
})();
