(function(){
  const esc=(v)=>{const d=document.createElement('div');d.textContent=String(v??'');return d.innerHTML;};
  let activeJobId=null, pollTimer=null, pollAttempts=0;
  const setJobState=(message,kind='info')=>{const host=document.getElementById('aiAssessmentDrafts');if(!host)return;let state=document.getElementById('aiAssessmentDraftsJobState');if(!state){state=document.createElement('div');state.id='aiAssessmentDraftsJobState';state.className='small mb-2';state.setAttribute('role','status');host.before(state);}state.className=`small mb-2 ${kind==='danger'?'text-danger':kind==='success'?'text-success':'text-muted'}`;state.textContent=message;};
  const watchJob=(jobId)=>{clearTimeout(pollTimer);activeJobId=Number(jobId)||null;pollAttempts=0;if(!activeJobId){setJobState('Assessment draft queued. Refresh this panel to check its status.');return;}setJobState('Assessment draft queued. Checking for progress…');const poll=async()=>{pollAttempts+=1;let job=null;try{job=await window.API.nodeJobs?.status?.(activeJobId);}catch(_){}const status=String(job?.status||'').toLowerCase();if(['failed','cancelled','dead_letter'].includes(status)){activeJobId=null;pollTimer=null;pollAttempts=0;setJobState(status==='failed'?'Assessment draft could not be prepared. Try again or contact support if it repeats.':'Assessment draft was cancelled.',status==='failed'?'danger':'info');await load();return;}setJobState(['running','processing'].includes(status)?'Assessment draft is being prepared…':'Assessment draft queued. Checking for progress…');const rows=await load();if(!activeJobId)return;const draft=rows.find(row=>Number(row.metadata?.queue_job_id)===activeJobId);if(draft&&['pending_approval','approved'].includes(draft.status)){activeJobId=null;pollTimer=null;pollAttempts=0;setJobState('Assessment draft is ready for staff review.','success');return;}if(draft?.status==='failed'){activeJobId=null;pollTimer=null;pollAttempts=0;setJobState('Assessment draft could not be prepared. Try again or contact support if it repeats.','danger');return;}if(['done','completed'].includes(status)){activeJobId=null;pollTimer=null;pollAttempts=0;setJobState('The job completed, but its matching Assessment draft was not returned. Refresh the panel or contact support.','danger');return;}if(pollAttempts<12)pollTimer=setTimeout(poll,5000);else{activeJobId=null;pollTimer=null;pollAttempts=0;setJobState(`Job status: ${status||'unknown'}. Refresh this panel to check again.`);}};pollTimer=setTimeout(poll,1500);};
  let aiDraftCache=null;
  let assessmentDraftPanel = null;
  const loadReferencesComposed=async()=>{try{const r=await window.API.nodeViews.resolve('academic','ai-assessment-draft-references');aiDraftCache=r?.data?.sources||r?.sources||{};}catch(e){console.warn('AI assessment draft Node view failed; using direct reads:',e);aiDraftCache=null;}};
  const load=async()=>{
    const el=document.getElementById('aiAssessmentDrafts');
    if(!el)return;
    const outcomeId=Number(document.getElementById('faOutcome')?.value||0);
    try{
      const own=aiDraftCache?.own!==undefined?aiDraftCache.own:await window.API.academic.getAiAssessmentDrafts('own');
      let review={drafts:[]};
      try{review=aiDraftCache?.review!==undefined?aiDraftCache.review:await window.API.academic.getAiAssessmentDrafts('review');}catch(_){}
      const all=[...(own?.data?.drafts||own?.drafts||[]).map(x=>({...x,__review:false})),...(review?.data?.drafts||review?.drafts||[]).map(x=>({...x,__review:true}))];
      const rows=all.filter(x=>x.metadata?.subject_type==='academic_assessment_draft'&&(Number(x.metadata?.queue_job_id)===activeJobId||!outcomeId||Number(x.subject_id)===outcomeId));
      el.innerHTML=rows.length?rows.map(d=>{
        const x=d.draft||{};
        const approve=d.__review&&d.status==='pending_approval';
        return `<article class="border rounded p-2"><div class="d-flex justify-content-between"><strong>${esc(x.title||'Assessment draft')}</strong><span class="badge text-bg-secondary">${esc(d.status||'queued')}</span></div><p class="small mt-2 mb-2">${esc(x.body||'Draft is still being generated.')}</p>${Array.isArray(x.items)&&x.items.length?`<div class="small fw-semibold mb-1">Suggested questions</div><ol class="small mb-2">${x.items.map(i=>`<li>${esc(i.question||'')} <span class="text-muted">(${esc(i.type||'question')}${i.marks?`, ${Number(i.marks)} marks`:''})</span></li>`).join('')}</ol>`:''}${Array.isArray(x.next_steps)&&x.next_steps.length?`<ul class="small mb-2">${x.next_steps.map(s=>`<li>${esc(s)}</li>`).join('')}</ul>`:''}${approve?`<button class="btn btn-sm btn-success" data-ai-approve="${Number(d.id)}">Approve for teacher editing</button>`:''}${d.status==='approved'?'<div class="small text-info">Approved guidance only. Enter and publish the official assessment through the existing workflow.</div>':''}</article>`;
      }).join(''):'<div class="small text-muted">No assistant assessment draft for this outcome.</div>';
      el.querySelectorAll('[data-ai-approve]').forEach(b=>b.addEventListener('click',async()=>{
        try{await window.API.academic.approveAiAssessmentDraft(b.dataset.aiApprove);window.showNotification?.('Assessment draft approved for teacher editing','success');await load();}
        catch(e){window.showNotification?.(e.message||'Unable to approve assessment draft','danger');}
      }));
      return rows;
    }catch(e){
      el.innerHTML='<div class="small text-warning">Assistant assessment drafts are temporarily unavailable.</div>';
      return [];
    }
  };

  const queue=async()=>{
    const outcomeId=Number(document.getElementById('faOutcome')?.value||0);
    const areaId=Number(document.getElementById('faSubject')?.value||0);
    if(!outcomeId||!areaId){
      window.showNotification?.('Select a learning area and CBC learning outcome first','warning');
      return;
    }
    const type=document.getElementById('faType')?.selectedOptions?.[0]?.textContent?.trim()||'';
    const intent=(await (window.promptAction?.('AI assessment draft','Optional assessment intent:','Create a fair, age-appropriate assessment with clear evidence of the outcome.') ?? Promise.resolve(null)));
    if(intent===null)return;
    const b=document.getElementById('queueAiAssessmentDraft');
    if(b) b.disabled=true;
    
    try{
      // Show the agent task panel
      const panelContainer = document.getElementById('aiAssessmentDraftPanel');
      if (panelContainer && window.AgentTaskPanel && window.AI_FEATURES?.shouldUseAgentTaskPanel?.('ai_assessment_draft')) {
        if (window.assessmentDraftPanel) {
          window.assessmentDraftPanel.destroy();
        }
        window.assessmentDraftPanel = new window.AgentTaskPanel({
          container: panelContainer,
          tool: 'drafts.create',
          question: `Create an assessment draft for outcome ${outcomeId}, learning area ${areaId}: ${intent}`,
          route: 'academics',
          module: 'assessments',
          targetComponent: '#assessmentDraftEditor',
          componentId: 'assessment-draft',
          onComplete: (result) => {
            const body = result?.answer?.body || result?.body || result?.draft?.body || (typeof result?.draft === 'string' ? result.draft : '');
            const editor = document.getElementById('assessmentDraftEditor');
            if (editor) {
              editor.value = body;
              editor.dispatchEvent(new Event('input', { bubbles: true }));
            }
            window.showNotification?.('Assessment draft ready!', 'success');
            load(); // Refresh drafts list
          },
          onError: (error) => {
            window.showNotification?.(error.message || 'Failed to generate assessment draft', 'danger');
          }
        });
        window.assessmentDraftPanel.start();
      } else {
        // Fallback to old queue method
        const r=await window.API.academic.queueAiAssessmentDraft({
          learning_area_id:areaId,
          learning_outcome_id:outcomeId,
          term_name:document.getElementById('faTerm')?.selectedOptions?.[0]?.textContent||'',
          assessment_type:type,
          max_marks:Number(document.getElementById('faMaxMarks')?.value||100),
          teacher_intent:intent
        });
        const jobId=r?.data?.job_id??r?.job_id;
        window.showNotification?.(`Assessment draft queued${jobId?` (job ${jobId})`:''}`,'info');
        aiDraftCache=null;
        watchJob(jobId);
        await load();
      }
    }catch(e){
      window.showNotification?.(e.message||'Unable to queue assessment draft','danger');
    }finally{
      if(b) b.disabled=false;
    }
  };

  const init=async()=>{
    document.getElementById('queueAiAssessmentDraft')?.addEventListener('click',queue);
    document.getElementById('faOutcome')?.addEventListener('change',load);
    await loadReferencesComposed();
    load();
  };
  window.APIRealtime?.register?.('ai_assessment_draft',{refresh:async()=>{await loadReferencesComposed();await load();}},['academic']);
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
