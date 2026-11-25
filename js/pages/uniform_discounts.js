(function () {
  'use strict';
  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const money = v => `KES ${Number(v || 0).toLocaleString()}`;
  const msg = (text, kind = 'success') => { document.getElementById('discountMessage').innerHTML = text ? `<div class="alert alert-${kind} mt-3">${esc(text)}</div>` : ''; };

  async function load() {
    try {
      const data = await API.commerce.discounts();
      document.getElementById('discountForm').hidden = !data.can_create;
      document.getElementById('discountProduct').innerHTML = '<option value="">All products</option>' + data.products.map(p => `<option value="${p.id}">${esc(p.title)}</option>`).join('');
      document.getElementById('discountList').innerHTML = (data.campaigns || []).map(c => `<article class="campaign-card"><span><strong>${esc(c.code)} · ${esc(c.name)}</strong><small class="d-block">${c.discount_type === 'percentage' ? c.discount_value + '%' : money(c.discount_value)} · ${esc(c.channel)} · ${esc(c.starts_at)} to ${esc(c.ends_at)}</small><small class="d-block">${c.redemptions} uses · ${money(c.discount_cost)} given · proposed by ${esc(c.creator_name)}</small></span><span><span class="status-pill ${esc(c.status)}">${esc(c.status.replaceAll('_', ' '))}</span>${data.can_approve && c.status === 'pending_approval' ? `<button class="btn btn-sm btn-success d-block mt-2" data-decide="${c.id}" data-value="active">Approve</button><button class="btn btn-sm btn-outline-danger d-block mt-1" data-decide="${c.id}" data-value="rejected">Reject</button>` : ''}${data.can_approve && c.status === 'active' ? `<button class="btn btn-sm btn-outline-warning d-block mt-2" data-decide="${c.id}" data-value="paused">Pause</button>` : ''}</span></article>`).join('') || '<div class="empty-state">No offers have been proposed.</div>';
      document.querySelectorAll('[data-decide]').forEach(button => { button.onclick = () => decide(button); });
    } catch (error) { msg(error.message, 'danger'); }
  }

  async function decide(button) {
    try { await API.commerce.decideDiscount(button.dataset.decide, {decision: button.dataset.value, note: (await window.promptAction?.('Decision note', 'Add a decision note (optional):', '', { promptLabel: 'Note' })) || ''}); msg('Offer decision recorded.'); load(); }
    catch (error) { msg(error.message, 'danger'); }
  }

  document.getElementById('discountForm').onsubmit = async event => {
    event.preventDefault();
    try {
      await API.commerce.createDiscount({code:document.getElementById('discountCode').value,name:document.getElementById('discountName').value,discount_type:document.getElementById('discountType').value,discount_value:Number(document.getElementById('discountValue').value),minimum_order:Number(document.getElementById('discountMinimum').value||0),maximum_discount:Number(document.getElementById('discountMaximum').value||0)||null,channel:document.getElementById('discountChannel').value,product_id:Number(document.getElementById('discountProduct').value)||null,starts_at:document.getElementById('discountStarts').value.replace('T',' '),ends_at:document.getElementById('discountEnds').value.replace('T',' '),redemption_limit:Number(document.getElementById('discountLimit').value)||null,per_buyer_limit:Number(document.getElementById('discountBuyerLimit').value)||null,description:document.getElementById('discountDescription').value});
      msg('Offer submitted for School Administrator approval.'); event.target.reset(); load();
    } catch (error) { msg(error.message, 'danger'); }
  };
  load();
})();
