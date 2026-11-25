(function () {
  'use strict';
  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  let timer;
  const msg = (text, kind = 'success') => { document.getElementById('trackMessage').innerHTML = text ? `<div class="alert alert-${kind} mt-3">${esc(text)}</div>` : ''; };

  async function load() {
    try {
      const data = await API.commerce.stockUnits({q:document.getElementById('trackSearch').value,status:document.getElementById('trackStatus').value}), summary=data.summary||{};
      document.getElementById('trackIdentify').hidden = !data.can_manage;
      document.getElementById('trackMetrics').innerHTML = [['Identified',summary.total_units],['In stock',summary.in_stock],['Reserved',summary.reserved],['Dispatched',summary.dispatched],['Exceptions',summary.exceptions]].map(x=>`<article><small>${x[0]}</small><strong>${x[1]||0}</strong></article>`).join('');
      document.getElementById('trackVariance').innerHTML = (data.variance||[]).map(v=>`<div class="variance-alert"><strong>Reconciliation difference:</strong> ${esc(v.title)} · ${esc(v.variant_name)} · ${esc(v.size_label)} — aggregate ${v.aggregate_available}, identified ${v.identified_available}, variance ${v.variance}.</div>`).join('');
      document.getElementById('trackUnits').innerHTML = (data.units||[]).map(u=>`<article class="unit-card"><span><strong>${esc(u.product_title)}</strong><small class="d-block">${esc(u.variant_name||'Standard')} · ${esc(u.size_label)} · receipt ${esc(u.receipt_reference)}</small><code>${esc(u.unit_code)}</code><small class="d-block">${u.order_reference?'Order '+esc(u.order_reference):'No order'} · last handled by ${esc(u.last_actor||'Unknown')}</small></span><span><span class="status-pill ${esc(u.status)}">${esc(u.status.replaceAll('_',' '))}</span><button class="btn btn-sm btn-link d-block" data-events="${u.id}">Timeline</button></span></article>`).join('') || '<div class="empty-state">No units match this search.</div>';
      document.querySelectorAll('[data-events]').forEach(button=>{button.onclick=()=>timeline(Number(button.dataset.events));});
    } catch(error){msg(error.message,'danger');}
  }

  async function timeline(id){try{const data=await API.commerce.stockUnitEvents(id);const text=(data.events||[]).map(e=>`${e.created_at} — ${e.event_type.replaceAll('_',' ')} by ${e.actor_name}${e.order_reference?' · '+e.order_reference:''}`).join('\n')||'No events';window.infoDialog?.('Stock unit history', text);}catch(error){msg(error.message,'danger');}}

  document.getElementById('trackIdentify').onclick=async()=>{if(!(await window.confirmAction?.('Identify stock units','Generate an identity and printable label for every current aggregate-stock unit that has no identity?',{confirmText:'Identify'})))return;try{const result=await API.commerce.identifyExistingStock();if(!result.unit_count){msg(result.message||'All stock is already identified.');return load();}const labels=await API.commerce.stockLabels(result.units.map(u=>u.id));msg(`${result.unit_count} existing units identified. Opening labels are ready.`);const popup=window.open('','_blank','noopener,noreferrer');if(popup){popup.document.write(`<html><head><title>Opening stock labels</title><style>.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.label{border:1px dashed;padding:8px;text-align:center;break-inside:avoid}.label img{width:75px}.label svg{width:100%;height:48px}</style></head><body><button data-kw-onclick="print()">Print</button><div class="grid">${(labels.labels||[]).map(l=>`<div class="label"><b>Kingsway Preparatory School</b><small>${esc(l.product_title)} · ${esc(l.size_label)}</small><br><img src="${l.qr_data_uri}">${l.barcode_svg}<small>${esc(l.unit_code)}</small></div>`).join('')}</div></body></html>`);popup.document.close();}load();}catch(error){msg(error.message,'danger');}};
  document.getElementById('trackSearch').oninput=()=>{clearTimeout(timer);timer=setTimeout(load,250);};
  document.getElementById('trackStatus').onchange=load;
  load();
})();
