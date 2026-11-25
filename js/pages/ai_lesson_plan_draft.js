(function(){
  const esc=(v)=>{const d=document.createElement('div');d.textContent=String(v??'');return d.innerHTML;};
  let activeJobId=null, pollTimer=null, pollAttempts=0;
  const setJobState=(message,kind='info')=>{const host=document.getElementById('aiLessonPlanDrafts');if(!host)return;let state=document.getElementById('aiLessonPlanDraftsJobState');if(!state){state=document.createElement('div');state.id='aiLessonPlanDraftsJobState';state.className='small mb-2';state.setAttribute('role','status');host.before(state);}state.className=`small mb-2 ${kind==='danger'?'text-danger':kind==='success'?'text-success':'text-muted'}`;state.textContent=message;};
  const watchJob=(jobId)=>{clearTimeout(pollTimer);activeJobId=Number(jobId)||null;pollAttempts=0;if(!activeJobId){setJobState('Lesson-plan draft queued. Refresh this panel to check its status.');return;}setJobState('Lesson-plan draft queued. Checking for progress…');const poll=async()=>{pollAttempts+=1;let job=null;try{job=await window.API.nodeJobs?.status?.(activeJobId);}catch(_){}const status=String(job?.status||'').toLowerCase();if(['failed','cancelled','dead_letter'].includes(status)){activeJobId=null;pollTimer=null;pollAttempts=0;setJobState(status==='failed'?'Lesson-plan draft could not be prepared. Try again or contact support if it repeats.':'Lesson-plan draft was cancelled.',status==='failed'?'danger':'info');await load();return;}setJobState(['running','processing'].includes(status)?'Lesson-plan draft is being prepared…':'Lesson-plan draft queued. Checking for progress…');const rows=await load();if(!activeJobId)return;const draft=rows.find(row=>Number(row.metadata?.queue_job_id)===activeJobId);if(draft&&['pending_approval','approved'].includes(draft.status)){activeJobId=null;pollTimer=null;pollAttempts=0;setJobState('Lesson-plan draft is ready for staff review.','success');return;}if(draft?.status==='failed'){activeJobId=null;pollTimer=null;pollAttempts=0;setJobState('Lesson-plan draft could not be prepared. Try again or contact support if it repeats.','danger');return;}if(['done','completed'].includes(status)){activeJobId=null;pollTimer=null;pollAttempts=0;setJobState('The job completed, but its matching Lesson plan draft was not returned. Refresh the panel or contact support.','danger');return;}if(pollAttempts<12)pollTimer=setTimeout(poll,5000);else{activeJobId=null;pollTimer=null;pollAttempts=0;setJobState(`Job status: ${status||'unknown'}. Refresh this panel to check again.`);}};pollTimer=setTimeout(poll,1500);};
  
  let lessonPlanPanel = null;

  const load=async()=>{
    const el=document.getElementById('aiLessonPlanDrafts');
    if(!el)return;
    const schemeId=Number(document.getElementById('lpScheme')?.value||0);
    try{
      const own=await window.API.academic.getAiLessonPlanDrafts('own');
      let review={drafts:[]};
      try{review=await window.API.academic.getAiLessonPlanDrafts('review');}catch(_){}
      const all=[...(own?.data?.drafts||own?.drafts||[]).map(x=>({...x,__review:false})),...(review?.data?.drafts||review?.drafts||[]).map(x=>({...x,__review:true}))];
      const rows=all.filter(x=>x.metadata?.subject_type==='academic_lesson_plan_draft'&&(Number(x.metadata?.queue_job_id)===activeJobId||!schemeId||Number(x.subject_id)===schemeId));
      el.innerHTML=rows.length?rows.map(d=>{
        const x=d.draft||{};
        const approve=d.__review&&d.status==='pending_approval';
        return `<article class="border rounded p-2"><div class="d-flex justify-content-between"><strong>${esc(x.title||'Lesson-plan draft')}</strong><span class="badge text-bg-secondary">${esc(d.status||'queued')}</span></div><p class="small mt-2 mb-2">${esc(x.body||'Draft is still being generated.')}</p>${Array.isArray(x.next_steps)&&x.next_steps.length?`<ul class="small mb-2">${x.next_steps.map(s=>`<li>${esc(s)}</li>`).join('')}</ul>`:''}${approve?`<button class="btn btn-sm btn-success" data-ai-approve="${Number(d.id)}">Approve for teacher editing</button>`:''}${d.status==='approved'?'<div class="small text-info">Approved guidance only. Use the form above to edit and submit the official lesson plan.</div>':''}</article>`;
      }).join(''):'<div class="small text-muted">No assistant draft for this scheme row.</div>';
      el.querySelectorAll('[data-ai-approve]').forEach(b=>b.addEventListener('click',async()=>{
        try{await window.API.academic.approveAiLessonPlanDraft(b.dataset.aiApprove);window.showNotification?.('Lesson-plan draft approved for teacher editing','success');await load();}
        catch(e){window.showNotification?.(e.message||'Unable to approve lesson-plan draft','danger');}
      }));
      return rows;
    }catch(e){
      el.innerHTML='<div class="small text-warning">Lesson-plan assistance is temporarily unavailable.</div>';
      return [];
    }
  };

  const queue=async()=>{
    const schemeId=Number(document.getElementById('lpScheme')?.value||0);
    if(!schemeId){
      window.showNotification?.('Select an approved scheme row first','warning');
      return;
    }
    const intent=await (window.promptAction?.('AI lesson plan draft','Optional teaching intent for this lesson:','Create an inclusive lesson with clear activities and formative assessment.') ?? Promise.resolve(null));
    if(intent===null)return;
    
    const b=document.getElementById('queueAiLessonPlanDraft');
    if(b) b.disabled=true;
    
    try{
      // Show the agent task panel
      const panelContainer = document.getElementById('aiLessonPlanDraftPanel');
      if (panelContainer && window.AgentTaskPanel && window.AI_FEATURES?.shouldUseAgentTaskPanel?.('ai_lesson_plan_draft')) {
        if (window.lessonPlanPanel) {
          window.lessonPlanPanel.destroy();
        }
        window.lessonPlanPanel = new window.AgentTaskPanel({
          container: panelContainer,
          tool: 'drafts.create',
          question: `Create a lesson plan for scheme ${schemeId}: ${intent}`,
          route: 'academics',
          module: 'lesson_plans',
          targetComponent: '#lessonPlanEditor',
          componentId: 'lesson-plan-draft',
          onComplete: (result) => {
            const body = result?.answer?.body || result?.body || result?.draft?.body || (typeof result?.draft === 'string' ? result.draft : '');
            const editor = document.getElementById('lessonPlanEditor');
            if (editor) {
              editor.value = body;
              editor.dispatchEvent(new Event('input', { bubbles: true }));
            }
            window.showNotification?.('Lesson plan draft ready!', 'success');
            load(); // Refresh drafts list
          },
          onError: (error) => {
            window.showNotification?.(error.message || 'Failed to generate lesson plan', 'danger');
          }
        });
        window.lessonPlanPanel.start();
      } else {
        // Fallback to old queue method
        const r=await window.API.academic.queueAiLessonPlanDraft({scheme_of_work_id:schemeId,teacher_intent:intent});
        const jobId=r?.data?.job_id??r?.job_id;
        window.showNotification?.(`Lesson-plan draft queued${jobId?` (job ${jobId})`:''}`,'info');
        watchJob(jobId);
        await load();
      }
    }catch(e){
      window.showNotification?.(e.message||'Unable to queue lesson-plan draft','danger');
    }finally{
      if(b) b.disabled=false;
    }
  };

  const init=()=>{
    document.getElementById('queueAiLessonPlanDraft')?.addEventListener('click',queue);
    document.getElementById('lpScheme')?.addEventListener('change',load);
    window.RealtimeJobWake?.onDomain('ai', load);
    load();
  };
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
