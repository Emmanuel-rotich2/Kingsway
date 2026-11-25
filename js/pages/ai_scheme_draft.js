(function(){
  const esc=(v)=>{const d=document.createElement('div');d.textContent=String(v??'');return d.innerHTML;};
  let activeJobId=null, pollTimer=null, pollAttempts=0;
  const setJobState=(message,kind='info')=>{const host=document.getElementById('aiSchemeDrafts');if(!host)return;let state=document.getElementById('aiSchemeDraftsJobState');if(!state){state=document.createElement('div');state.id='aiSchemeDraftsJobState';state.className='small mb-2';state.setAttribute('role','status');host.before(state);}state.className=`small mb-2 ${kind==='danger'?'text-danger':kind==='success'?'text-success':'text-muted'}`;state.textContent=message;};
  const watchJob=(jobId)=>{clearTimeout(pollTimer);activeJobId=Number(jobId)||null;pollAttempts=0;if(!activeJobId){setJobState('CBC scheme draft queued. Refresh this panel to check its status.');return;}setJobState('CBC scheme draft queued. Checking for progress…');const poll=async()=>{pollAttempts+=1;let job=null;try{job=await window.API.nodeJobs?.status?.(activeJobId);}catch(_){}const status=String(job?.status||'').toLowerCase();if(['failed','cancelled','dead_letter'].includes(status)){activeJobId=null;pollTimer=null;pollAttempts=0;setJobState(status==='failed'?'CBC scheme draft could not be prepared. Try again or contact support if it repeats.':'CBC scheme draft was cancelled.',status==='failed'?'danger':'info');await load();return;}setJobState(['running','processing'].includes(status)?'CBC scheme draft is being prepared…':'CBC scheme draft queued. Checking for progress…');const rows=await load();if(!activeJobId)return;const draft=rows.find(row=>Number(row.metadata?.queue_job_id)===activeJobId);if(draft&&['pending_approval','approved'].includes(draft.status)){activeJobId=null;pollTimer=null;pollAttempts=0;setJobState('CBC scheme draft is ready for staff review.','success');return;}if(draft?.status==='failed'){activeJobId=null;pollTimer=null;pollAttempts=0;setJobState('CBC scheme draft could not be prepared. Try again or contact support if it repeats.','danger');return;}if(['done','completed'].includes(status)){activeJobId=null;pollTimer=null;pollAttempts=0;setJobState('The job completed, but its matching CBC scheme draft was not returned. Refresh the panel or contact support.','danger');return;}if(pollAttempts<12)pollTimer=setTimeout(poll,5000);else{activeJobId=null;pollTimer=null;pollAttempts=0;setJobState(`Job status: ${status||'unknown'}. Refresh this panel to check again.`);}};pollTimer=setTimeout(poll,1500);};
  
  let schemeDraftPanel = null;

  const load=async()=>{
    const el=document.getElementById('aiSchemeDrafts');
    if(!el)return;
    try{
      const own=await window.API.academic.getAiSchemeDrafts('own');
      let review={drafts:[]};
      try{review=await window.API.academic.getAiSchemeDrafts('review');}catch(_){}
      const drafts=[...(own?.data?.drafts||own?.drafts||[]).map(x=>({...x,__review:false})),...(review?.data?.drafts||review?.drafts||[]).map(x=>({...x,__review:true}))];
      el.innerHTML=drafts.length?drafts.map(d=>{
        const x=d.draft||{};
        const approve=d.__review&&d.status==='pending_approval';
        return `<div class="col-12"><article class="card border-success-subtle bg-light"><div class="card-body py-2"><div class="d-flex justify-content-between gap-2"><strong>${esc(x.title||'CBC scheme draft')}</strong><span class="badge text-bg-secondary">${esc(d.status||'queued')}</span></div><p class="small mb-2 mt-2">${esc(x.body||'Draft is still being generated.')}</p>${Array.isArray(x.next_steps)&&x.next_steps.length?`<ul class="small mb-2">${x.next_steps.map(s=>`<li>${esc(s)}</li>`).join('')}</ul>`:''}${approve?`<button class="btn btn-sm btn-success" data-ai-approve="${Number(d.id)}">Approve for teacher editing</button>`:''}${d.status==='approved'?'<div class="small text-info">Approved as guidance only. Teacher editing and normal academic submission/approval remain required.</div>':''}</div></article></div>`;
      }).join(''):'<div class="col-12 small text-muted">No assistant drafts yet.</div>';
      el.querySelectorAll('[data-ai-approve]').forEach(b=>b.addEventListener('click',async()=>{
        try{await window.API.academic.approveAiSchemeDraft(b.dataset.aiApprove);window.showNotification?.('CBC draft approved for teacher editing','success');await load();}
        catch(e){window.showNotification?.(e.message||'Unable to approve CBC draft','danger');}
      }));
      return rows;
    }catch(e){
      el.innerHTML='<div class="col-12 small text-warning">Assistant drafts are temporarily unavailable.</div>';
      return [];
    }
  };

  const queue=async()=>{
    const classId=document.getElementById('genClass')?.value;
    const streamId=document.getElementById('genStream')?.value;
    const areaId=document.getElementById('genLearningArea')?.value;
    const strandId=document.getElementById('genStrand')?.value;
    const subId=document.getElementById('genSubStrand')?.value;
    const intent=(await (window.promptAction?.('AI scheme draft','Optional teaching intent for this draft:','Create a practical, learner-centred sequence with assessment opportunities.') ?? Promise.resolve(null)));
    if(intent===null)return;
    if(!streamId||!areaId){
      window.showNotification?.('Select class stream and learning area first','warning');
      return;
    }
    const b=document.getElementById('queueAiSchemeDraft');
    if(b) b.disabled=true;
    
    try{
      // Show the agent task panel
      const panelContainer = document.getElementById('aiSchemeDraftPanel');
      if (panelContainer && window.AgentTaskPanel && window.AI_FEATURES?.shouldUseAgentTaskPanel?.('ai_scheme_draft')) {
        if (window.schemeDraftPanel) {
          window.schemeDraftPanel.destroy();
        }
        window.schemeDraftPanel = new window.AgentTaskPanel({
          container: panelContainer,
          question: `Create a CBC scheme of work for class ${classId}, stream ${streamId}, learning area ${areaId}${strandId?`, strand ${strandId}`:''}${subId?`, sub-strand ${subId}`:''}: ${intent}`,
          route: 'academics',
          module: 'schemes_of_work',
          targetComponent: '#schemeDraftEditor',
          onComplete: (result) => {
            const body = result?.answer?.body || result?.body || result?.draft?.body || (typeof result?.draft === 'string' ? result.draft : '');
            const editor = document.getElementById('schemeDraftEditor');
            if (editor) {
              editor.value = body;
              editor.dispatchEvent(new Event('input', { bubbles: true }));
            }
            window.showNotification?.('Scheme of work draft ready!', 'success');
            load(); // Refresh drafts list
          },
          onError: (error) => {
            window.showNotification?.(error.message || 'Failed to generate scheme of work', 'danger');
          }
        });
        window.schemeDraftPanel.start();
      } else {
        // Fallback to old queue method
        const r=await window.API.academic.queueAiSchemeDraft({
          class_id:Number(classId),
          stream_id:Number(streamId),
          learning_area_id:Number(areaId),
          strand_id:strandId?Number(strandId):0,
          sub_strand_id:subId?Number(subId):0,
          teacher_intent:intent
        });
        const jobId=r?.data?.job_id??r?.job_id;
        window.showNotification?.(`CBC scheme draft queued${jobId?` (job ${jobId})`:''}`,'info');
        watchJob(jobId);
        await load();
      }
    }catch(e){
      window.showNotification?.(e.message||'Unable to queue CBC scheme draft','danger');
    }finally{
      if(b) b.disabled=false;
    }
  };

  const init=()=>{
    document.getElementById('queueAiSchemeDraft')?.addEventListener('click',queue);
    load();
  };
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
