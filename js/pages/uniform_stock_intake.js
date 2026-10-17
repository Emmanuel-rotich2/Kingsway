const uniformStockIntakeController = {
  state: { data: null, lineNo: 0, referenceCache: null },

  async loadReferencesComposed() {
    try {
      const response = await window.API.nodeViews.read("inventory", "uniform-stock-intake-references");
      this.state.referenceCache = response?.data?.sources || response?.sources || {};
    } catch (error) {
      console.warn("Uniform Stock Intake Node view failed; using direct reads:", error);
      this.state.referenceCache = null;
    }
  },

  esc(v) { return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&', '<': '<', '>': '>', '"': '"', "'": "'" }[c])); },

  async init() {
    try {
      await window.AuthContext?.ready();
      if (!AuthContext.isAuthenticated()) { window.location.href = (window.APP_BASE||'')+'/index.php'; return; }
      await this.loadReferencesComposed();
      const cache = this.state.referenceCache;
      this.state.data = cache?.data !== undefined ? cache.data : await API.commerce.stockIntakeOptions();
      document.getElementById('intakeSupplier').innerHTML = '<option value="">Not linked</option>' + this.state.data.suppliers.map(x => `<option value="${x.id}">${this.esc(x.name)}</option>`).join('');
      document.getElementById('intakePo').innerHTML = '<option value="">Not linked</option>' + this.state.data.purchase_orders.map(x => `<option value="${x.id}">${this.esc(x.order_number)} · ${this.esc(x.status)}</option>`).join('');
      addLine();
    } catch (e) { msg(e.message, 'danger'); }
  },
};

const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&', '<': '<', '>': '>', '"': '"', "'": "'" }[c]));
const msg = (t, k = 'success') => document.getElementById('intakeMessage').innerHTML = t ? `<div class="alert alert-${k} mt-3">${esc(t)}</div>` : '';
let lineNo = 0;

function addLine() {
  const id = ++uniformStockIntakeController.state.lineNo, row = document.createElement('div');
  row.className = 'receipt-line'; row.dataset.line = id;
  const products = uniformStockIntakeController.state.data?.products || [];
  row.innerHTML = `<div class="uniform-field"><label>Product</label><select data-product required><option value="">Choose product</option>${products.map(p => `<option value="${p.id}">${esc(p.title)}</option>`).join('')}</select></div><div class="uniform-field"><label>Variant / size</label><select data-size required><option value="">Choose after product</option></select></div><div class="uniform-field"><label>Quantity</label><input data-qty type="number" min="1" max="1000" value="1" required></div><div class="uniform-field"><label>Unit cost</label><input data-cost type="number" min="0" step="0.01" value="0" required></div><button type="button" class="btn btn-outline-danger" data-remove><i class="bi bi-trash"></i></button>`;
  document.getElementById('intakeLines').appendChild(row);
  row.querySelector('[data-product]').onchange = () => {
    const product = uniformStockIntakeController.state.data.products.find(p => Number(p.id) === Number(row.querySelector('[data-product]').value));
    row.querySelector('[data-size]').innerHTML = '<option value="">Choose variant / size</option>' + (product?.sizes || []).map(s => `<option value="${s.size_id}" data-variant="${s.variant_id||''}">${esc((s.variant_name||'Standard') + ' · ' + (s.size_label||s.size))}</option>`).join('');
  };
  row.querySelector('[data-remove]').onclick = () => row.remove();
}

function printLabels(labels) {
  const popup = window.open('', '_blank', 'noopener,noreferrer');
  if (!popup) { msg('Allow pop-ups to print the generated labels.', 'warning'); return; }
  popup.document.write(`<html><head><title>Uniform Unit Labels</title><style>body{font-family:Arial}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}.label{border:1px dashed #444;padding:8px;text-align:center;break-inside:avoid}.label img{width:76px;height:76px}.label svg{width:100%;height:50px}small{display:block}@media print{button{display:none}}</style></head><body><button onclick="print()">Print labels</button><div class="grid">${labels.map(l => `<div class="label"><strong>Kingsway Preparatory School</strong><small>${esc(l.product_title)} · ${esc(l.variant_name||'Standard')} · ${esc(l.size_label)}</small><img src="${l.qr_data_uri}">${l.barcode_svg}<small>${esc(l.unit_code)}</small></div>`).join('')}</div></body></html>`);
  popup.document.close();
}

function notify(t, type = 'success') {
  if (window.showNotification) { showNotification(t, type); return; }
  const el = document.createElement('div');
  el.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible position-fixed top-0 end-0 m-3`;
  el.style.zIndex = '9999';
  el.innerHTML = esc(t) + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
  document.body.appendChild(el);
  setTimeout(() => el.remove(), 3000);
}

document.getElementById('intakeAdd').onclick = addLine;
document.getElementById('intakeForm').onsubmit = async e => {
  e.preventDefault();
  try {
    const lines = [...document.querySelectorAll('[data-line]')].map(row => {
      const size = row.querySelector('[data-size]').selectedOptions[0];
      return { product_id: Number(row.querySelector('[data-product]').value), variant_id: Number(size?.dataset.variant || 0) || null, size_id: Number(size?.value), quantity: Number(row.querySelector('[data-qty]').value), unit_cost: Number(row.querySelector('[data-cost]').value) };
    });
    const result = await API.commerce.receiveStock({ supplier_id: Number(document.getElementById('intakeSupplier').value) || null, purchase_order_id: Number(document.getElementById('intakePo').value) || null, supplier_reference: document.getElementById('intakeSupplierRef').value, delivery_note: document.getElementById('intakeDelivery').value, notes: document.getElementById('intakeNotes').value, lines });
    const labels = await API.commerce.stockLabels(result.units.map(u => u.id));
    notify(`${result.unit_count} units received under ${result.receipt_reference}. Labels are ready to print.`);
    printLabels(labels.labels || []);
    document.getElementById('intakeLines').innerHTML = '';
    e.target.reset();
    addLine();
  } catch (error) { notify(error.message, 'danger'); }
};

const uniformStockIntakeControllerInstance = uniformStockIntakeController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => uniformStockIntakeControllerInstance.init().catch(() => {}));
} else {
  uniformStockIntakeControllerInstance.init().catch(() => {});
}

window.uniformStockIntakeController = uniformStockIntakeControllerInstance;