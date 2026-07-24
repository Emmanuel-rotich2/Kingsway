(() => {
  const form=document.getElementById('schoolProvisioningForm'); if(!form)return;
  let step=1,runId=null,schoolId=null;
  const payload=()=>Object.fromEntries(new FormData(form).entries());
  const notify=(message,type='danger')=>{const a=document.getElementById('provisionAlert');a.className=`alert alert-${type}`;a.textContent=message;};
  const render=()=>{document.querySelectorAll('[data-step]').forEach(s=>s.hidden=Number(s.dataset.step)!==step);document.getElementById('provisionStepBadge').textContent=`Step ${step} of 10`;document.getElementById('provisionProgress').style.width=`${step*10}%`;document.getElementById('provisionPrevious').disabled=step===1;document.getElementById('provisionNext').textContent=step===10?'Finish':'Next';if(step===10)document.getElementById('provisionReview').textContent=JSON.stringify(payload(),null,2);};
  const start=async()=>{const p=payload();const r=await API.systemAdministration.startProvisioning({name:p.name,code:p.code});runId=r.data?.run_id??r.run_id;schoolId=r.data?.school_id??r.school_id;};
  const save=async()=>{if(!runId)await start();if(step<10)await API.systemAdministration.saveProvisioningStep({run_id:runId,step,payload:payload()});notify('Progress saved.','success');};
  document.getElementById('provisionPrevious').onclick=()=>{step=Math.max(1,step-1);render();};
  document.getElementById('provisionSave').onclick=async()=>{try{await save();}catch(e){notify(e.message);}};
  document.getElementById('provisionNext').onclick=async()=>{try{if(step===1&&!form.reportValidity())return;if(step===10){await API.systemAdministration.finalizeProvisioning({run_id:runId});notify('School initialization completed.','success');return;}await save();step++;render();}catch(e){notify(e.message);}};
  render();
})();
