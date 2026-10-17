const paymentIntegrationSettingsController = {
  rows: [], routes: [], options: {}, modal: null, routeModal: null, permissionsModal: null, permissionAccountId: 0, routeId: 0,
  esc(value) { const node = document.createElement('div'); node.textContent = String(value ?? ''); return node.innerHTML; },
  el(id) { return document.getElementById(id); },
  async init() {
    await window.AuthContext?.ready();
    if (!this.el('pisKind')) { const kind = document.createElement('input'); kind.type = 'hidden'; kind.id = 'pisKind'; this.el('pisForm').prepend(kind); }
    this.modal = new bootstrap.Modal(this.el('pisModal')); this.permissionsModal = new bootstrap.Modal(this.el('pisPermissionsModal'));
    this.prepareBankAccountModal();
    this.ensureRouteModal(); this.ensureConfirmModal(); this.bindRouteRules(); this.ensurePosPanel();
    const openRoute = this.openRoute.bind(this);
    this.openRoute = (accountId = 0, routeId = 0) => { openRoute(accountId, routeId); const route = routeId ? this.routes.find(x => Number(x.id) === routeId) : null; this.el('pisRouteName').value = route?.display_name || (route ? `${route.provider_code} ${route.purpose} collection point` : ''); };
    const createRoute = window.API.finance.savePaymentCollectionRoute, updateRoute = window.API.finance.updatePaymentCollectionRoute;
    window.API.finance.savePaymentCollectionRoute = data => createRoute({ ...data, display_name: this.el('pisRouteName').value.trim() });
    window.API.finance.updatePaymentCollectionRoute = (id, data) => updateRoute(id, { ...data, display_name: this.el('pisRouteName').value.trim() });
    this.el('pisNew').onclick = () => this.openAccount(); this.el('pisAddCollection').onclick = () => this.openRoute();
    this.el('pisRevokeKcbToken').onclick = () => this.revokeKcbToken();
    this.el('pisForm').onsubmit = event => { event.preventDefault(); this.saveAccount(); }; this.el('pisNext').onclick = () => this.next(); this.el('pisBack').onclick = () => this.previous();
    this.el('pisPermissionsSave').onclick = () => this.savePermissions(); this.el('pisAddPurpose').onclick = () => this.addPurpose();
    await this.loadOptions(); await this.load();
  },
  async loadOptions() {
    const response = await window.API.finance.getFinancialAccountSetupOptions(); this.options = response?.data || response || {};
    this.fillSelect('pisProvider', this.options.providers || [], 'code', 'display_name', true); this.fillSelect('pisLedger', this.options.ledger_accounts || [], 'account_code', x => `${x.account_code} · ${x.account_name}`); this.renderPurposes(); this.renderChannels();
    this.fillSelect('pisRouteAccount', this.options.settlement_accounts || [], 'id', x => `${x.account_name} · ${x.account_identifier}`); this.fillSelect('pisRouteSettlement', this.options.settlement_accounts || [], 'id', x => `${x.account_name} · ${x.account_identifier}`); this.fillSelect('pisRouteProvider', this.options.providers || [], 'code', 'display_name');
    this.fillSelect('pisPosSettlement', this.options.settlement_accounts || [], 'id', x => `${x.account_name} · ${x.account_identifier}`);
  },
  fillSelect(id, rows, value, label, optional = false) { const select = this.el(id); if (!select) return; select.innerHTML = (optional ? '<option value="">Manual / no provider</option>' : '<option value="">Select…</option>') + rows.map(row => `<option value="${this.esc(row[value])}">${this.esc(typeof label === 'function' ? label(row) : row[label])}</option>`).join(''); },
  ensureConfirmModal() { if (this.confirmModal) return; const modal = document.createElement('div'); modal.id = 'pisConfirmModal'; modal.className = 'modal fade'; modal.innerHTML = '<div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="pisConfirmTitle">Please confirm</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="pisConfirmMessage"></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger" id="pisConfirmOk">Confirm</button></div></div></div>'; document.body.appendChild(modal); this.confirmModal = new bootstrap.Modal(modal); this.confirmResolve = null; this.el('pisConfirmOk').onclick = () => { this.confirmModal.hide(); this.confirmResolve?.(true); this.confirmResolve = null; }; modal.addEventListener('hidden.bs.modal', () => { if (this.confirmResolve) { this.confirmResolve(false); this.confirmResolve = null; } }); },
  confirmAction(title, message, confirmLabel = 'Confirm') { return new Promise(resolve => { this.confirmResolve = resolve; this.el('pisConfirmTitle').textContent = title; this.el('pisConfirmMessage').textContent = message; this.el('pisConfirmOk').textContent = confirmLabel; this.confirmModal.show(); }); },
  showRouteAlert(message) { const alert = this.el('pisRouteAlert'); if (!alert) return; alert.textContent = message || ''; alert.classList.toggle('d-none', !message); },
  box(item, type, checked = false) { return `<div class="col-md-6"><label class="border rounded p-2 d-flex gap-2 align-items-start h-100"><input class="form-check-input mt-1 pis-${type}" type="checkbox" value="${this.esc(item.code)}" ${checked ? 'checked' : ''}><span><strong>${this.esc(item.name)}</strong><small class="d-block text-muted">${this.esc(item.code)}</small></span></label></div>`; },
  renderPurposes() { const core = ['fees', 'transport', 'uniforms'], rows = this.options.purposes || []; this.el('pisCorePurposes').innerHTML = rows.filter(x => core.includes(x.code)).map(x => this.box(x, 'purpose', true)).join(''); this.el('pisOtherPurposes').innerHTML = rows.filter(x => !core.includes(x.code)).map(x => this.box(x, 'purpose', true)).join(''); },
  renderChannels() { const target = this.el('pisChannels'); if (!target) return; target.innerHTML = (this.options.channels || []).map(x => this.box(x, 'channel')).join(''); },
  addPurpose() {
    if (this.el('pisCustomPurposeRow')) { this.el('pisCustomPurposeName').focus(); return; }
    this.el('pisOtherPurposes').insertAdjacentHTML('beforeend', '<div class="col-12" id="pisCustomPurposeRow"><div class="input-group input-group-sm"><input class="form-control" id="pisCustomPurposeName" placeholder="Purpose name (e.g. School trips)"><input class="form-control" id="pisCustomPurposeCode" placeholder="Code (e.g. school_trips)"><button class="btn btn-outline-primary" type="button" id="pisCustomPurposeSave">Add</button><button class="btn btn-outline-secondary" type="button" id="pisCustomPurposeCancel">Cancel</button></div><div class="form-text">The code must start with a letter and use lowercase letters, numbers or underscores.</div></div>');
    this.el('pisCustomPurposeCancel').onclick = () => this.el('pisCustomPurposeRow')?.remove();
    this.el('pisCustomPurposeSave').onclick = () => {
      const name = this.el('pisCustomPurposeName').value.trim();
      const code = this.el('pisCustomPurposeCode').value.trim().toLowerCase();
      if (!name) return showNotification('Enter the purpose name.', 'error');
      if (!/^[a-z][a-z0-9_]{1,39}$/.test(code)) return showNotification('Use a code like school_trips.', 'error');
      this.el('pisCustomPurposeRow')?.remove();
      this.el('pisOtherPurposes').insertAdjacentHTML('beforeend', this.box({ code, name }, 'purpose', true));
      this.customPurposes = this.customPurposes || {};
      this.customPurposes[code] = name;
    };
  },
  prepareBankAccountModal() { const modal = this.el('pisModal'); modal.querySelector('.pis-wizard-pane[data-step="1"]')?.remove(); modal.querySelector('.pis-wizard-pane[data-step="4"]')?.remove(); [...modal.querySelectorAll('.pis-wizard-pane')].forEach((pane, index) => { pane.dataset.step = String(index + 1); }); const steps = [...modal.querySelectorAll('.pis-step')]; steps[0]?.remove(); steps[steps.length - 1]?.remove(); const remaining = [...modal.querySelectorAll('.pis-step')]; remaining.forEach((step, index) => { step.textContent = index === 0 ? '1. Account details' : '2. Transaction permissions'; }); modal.querySelector('.pis-wizard-steps')?.classList.add('d-none'); modal.querySelector('#pisSettlementField')?.classList.add('d-none'); modal.querySelector('#pisRouteConfig')?.classList.add('d-none'); modal.querySelector('#pisChannels')?.closest('.border')?.remove(); modal.querySelector('#pisProvider')?.closest('.col-md-6')?.remove(); },
  setStep(step) { this.step = Math.max(1, Math.min(2, step)); document.querySelectorAll('#pisModal .pis-wizard-pane').forEach(x => x.classList.toggle('d-none', Number(x.dataset.step) !== this.step)); this.el('pisBack').classList.toggle('d-none', this.step === 1); this.el('pisNext').classList.toggle('d-none', this.step === 2); this.el('pisSave').classList.toggle('d-none', this.step !== 2); },
  next() { if (this.step === 1 && !this.validateAccount()) return; if (this.step === 2 && !this.validateAccountPermissions()) return; this.setStep(this.step + 1); },
  previous() { this.setStep(this.step - 1); },
  showAccountAlert(message) { const target = this.el('pisMessage'); if (!target) return; target.innerHTML = message ? `<div class="alert alert-danger">${this.esc(message)}</div>` : ''; },
  validateAccount() { if (!this.el('pisName').value.trim() || !this.el('pisIdentifier').value.trim() || !this.el('pisLedger').value) { this.showAccountAlert('Enter the account name, account number and ledger account.'); return false; } this.showAccountAlert(''); return true; },
  validateAccountPermissions() { if (!document.querySelector('.pis-purpose:checked')) { this.showAccountAlert('Select at least one transaction purpose for this bank account.'); return false; } this.showAccountAlert(''); return true; },
  openAccount(row = null) { this.editing = row; this.customPurposes = {}; this.el('pisForm').reset(); this.el('pisId').value = row?.id || ''; this.el('pisKind').value = row?.account_kind || 'bank'; this.el('pisName').value = row?.account_name || ''; this.el('pisIdentifier').value = row?.account_identifier || ''; this.el('pisBank').value = row?.bank_name || ''; this.el('pisCurrency').value = row?.currency || 'KES'; this.el('pisLedger').value = row?.ledger_code || ''; this.check('.pis-purpose', row?.purposes); this.check('.pis-channel', row?.channels); this.el('pisModalTitle').textContent = row ? 'Edit bank account' : 'Add bank account'; this.el('pisDetailsHeading').textContent = 'Bank account details'; this.el('pisDetailsHelp').textContent = 'These are details of the real account that holds money. Collection points are configured separately.'; this.el('pisBankField').classList.remove('d-none'); this.el('pisSettlementField').classList.add('d-none'); this.el('pisRouteConfig').classList.add('d-none'); this.setStep(1); this.modal.show(); },
  check(selector, value) { if (value == null) return; const values = String(value).split(','); document.querySelectorAll(selector).forEach(x => x.checked = values.includes(x.value)); },
  async saveAccount() { if (!this.validateAccount() || !this.validateAccountPermissions()) return; const data = { account_mode: 'real_account', collection_purposes: [], account_name: this.el('pisName').value.trim(), account_kind: this.el('pisKind').value || 'bank', account_identifier: this.el('pisIdentifier').value.trim(), bank_name: this.el('pisBank').value.trim(), currency: this.el('pisCurrency').value.trim().toUpperCase(), ledger_code: this.el('pisLedger').value, purposes: [...document.querySelectorAll('.pis-purpose:checked')].map(x => x.value), custom_purposes: this.customPurposes }; try { const id = Number(this.el('pisId').value); if (id) await window.API.finance.updateFinancialAccount(id, data); else await window.API.finance.createFinancialAccount(data); this.modal.hide(); await this.load(); } catch (error) { this.showAccountAlert(error.message || 'Unable to save bank account'); } },
  renderTable() { const body = this.el('pisBody'); body.innerHTML = this.rows.map((row, index) => { const purposes = String(row.purposes || '').split(',').filter(Boolean); return `<tr><td><strong>${this.esc(row.account_name)}</strong><small class="d-block text-muted">Real ${this.esc(row.account_kind || 'bank')} account · ${this.esc(row.status)}</small></td><td>${this.esc(row.bank_name || 'Bank')}<code class="d-block">${this.esc(row.account_identifier)}</code></td><td>${this.esc(row.ledger_code || '—')}</td><td>${purposes.map(p => `<span class="badge bg-light text-dark me-1">${this.esc(p)}</span>`).join('') || '<span class="text-muted">None</span>'}</td><td class="text-nowrap"><button class="btn btn-sm btn-outline-primary" data-edit-account="${index}">Edit account</button><button class="btn btn-sm btn-outline-success ms-1" data-add-route="${row.id}">Add collection point</button></td></tr>`; }).join('') || '<tr><td colspan="5" class="text-center text-muted py-4">No real school accounts configured.</td></tr>'; const routesBody = this.el('pisRoutesBody'); routesBody.innerHTML = this.routes.map(route => `<tr><td><strong>${this.esc(route.display_name || `${route.provider_code} ${route.purpose} collection point`)}</strong><small class="d-block text-muted">Route ${this.esc(route.purpose)}</small></td><td>${this.esc(route.provider_code)}<code class="d-block">${this.esc(route.account_identifier)}</code><small class="text-muted">${this.esc(route.collection_product)}</small></td><td><span class="badge bg-light text-dark">${this.esc(route.purpose)}</span><small class="d-block text-muted">${this.esc(route.reference_policy)} · priority ${Number(route.priority ?? 100)}</small></td><td>${this.esc(route.route_channels || 'None')}</td><td>${this.esc(route.settlement_account_name || 'Not selected')}<code class="d-block">${this.esc(route.settlement_account_identifier || '')}</code></td><td>${Number(route.show_on_fee_structure) ? '<span class="badge bg-success-subtle text-success">Printed</span>' : '<span class="text-muted">Hidden</span>'}</td><td class="text-nowrap"><button class="btn btn-sm btn-outline-secondary" data-edit-route="${route.id}">Edit point</button><button class="btn btn-sm btn-outline-danger ms-1" data-delete-route="${route.id}">Remove</button></td></tr>`).join('') || '<tr><td colspan="7" class="text-center text-muted py-4">No collection points configured.</td></tr>'; document.querySelectorAll('[data-edit-account]').forEach(x => x.onclick = () => this.openAccount(this.rows[Number(x.dataset.editAccount)])); document.querySelectorAll('[data-add-route]').forEach(x => x.onclick = () => this.openRoute(Number(x.dataset.addRoute))); document.querySelectorAll('[data-edit-route]').forEach(x => x.onclick = () => this.openRoute(0, Number(x.dataset.editRoute))); document.querySelectorAll('[data-delete-route]').forEach(x => x.onclick = () => this.deleteRoute(Number(x.dataset.deleteRoute))); },
  async load() { const [accounts, routes] = await Promise.all([window.API.finance.getFinancialAccounts(), window.API.finance.getPaymentCollectionRoutes()]); this.rows = (accounts?.data || accounts || {}).accounts || []; this.routes = (routes?.data || routes || {}).routes || []; this.renderTable(); await this.loadPosTerminals(); },
  ensureRouteModal() { const modal = document.createElement('div'); modal.id = 'pisRouteModal'; modal.className = 'modal fade'; modal.innerHTML = '<div class="modal-dialog modal-lg modal-dialog-scrollable"><form class="modal-content" id="pisRouteForm"><div class="modal-header"><div><h5 class="modal-title" id="pisRouteTitle">Add collection point</h5><div class="small text-muted">Configure one parent-facing payment door and where its funds settle.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="alert alert-danger d-none" id="pisRouteAlert"></div><input type="hidden" id="pisRouteId"><div class="alert alert-info small">This is not a bank account. It receives a specific incoming payment and settles into the selected real account.</div><div class="row g-3"><div class="col-12"><label class="form-label">Settlement account <span class="text-danger">*</span></label><select class="form-select" id="pisRouteAccount" required></select><div class="form-text">The real school account that receives the money.</div></div><div class="col-md-6"><label class="form-label">Collection point name <span class="text-danger">*</span></label><input class="form-control" id="pisRouteName" placeholder="KCB Fees Paybill" required></div><div class="col-md-6"><label class="form-label">Identifier <span class="text-danger">*</span></label><input class="form-control" id="pisRouteIdentifier" placeholder="Paybill number, Buni identifier or account number" required><div class="form-text" id="pisRouteIdentifierHelp"></div></div><div class="col-12 d-none" id="pisRoutePayheroBox"><div class="border rounded p-3 bg-light"><div class="d-flex justify-content-between align-items-center mb-2"><h6 class="mb-0">PayHero collection channel</h6><span class="small text-muted" id="pisRouteChannelStatus">Loading PayHero channels…</span></div><div class="btn-group btn-group-sm mb-2" role="group" aria-label="PayHero channel mode"><input type="radio" class="btn-check" name="pisRouteChannelMode" id="pisRouteChannelModeExisting" value="existing" checked><label class="btn btn-outline-secondary" for="pisRouteChannelModeExisting">Use an existing channel</label><input type="radio" class="btn-check" name="pisRouteChannelMode" id="pisRouteChannelModeNew" value="new"><label class="btn btn-outline-secondary" for="pisRouteChannelModeNew">Register a new channel</label></div><div id="pisRouteChannelExisting"><div class="row g-2 align-items-center"><div class="col-md-9"><select class="form-select form-select-sm" id="pisRouteChannelPick"><option value="">Loading channels…</option></select></div><div class="col-md-3 text-end"><button type="button" class="btn btn-sm btn-outline-secondary" id="pisRouteChannelRefresh">Refresh</button></div></div><div class="form-text">Choosing a channel fills the identifier, name and collection product.</div></div><div id="pisRouteChannelNew" class="d-none"><div class="row g-2"><div class="col-md-3"><label class="form-label small">Channel type</label><select class="form-select form-select-sm" id="pisRouteChannelType"><option value="bank">Bank</option><option value="paybill">Paybill</option><option value="till">Till</option></select></div><div class="col-md-4 d-none" id="pisRouteChannelBankField"><label class="form-label small">Bank</label><select class="form-select form-select-sm" id="pisRouteChannelBank"><option value="">Loading banks…</option></select></div><div class="col-md-3"><label class="form-label small" id="pisRouteChannelShortLabel">Paybill number</label><input class="form-control form-control-sm" id="pisRouteChannelShort" placeholder="e.g. 522522"></div><div class="col-md-3" id="pisRouteChannelAccountField"><label class="form-label small">School account number</label><input class="form-control form-control-sm" id="pisRouteChannelAccount" placeholder="e.g. 1130991288"></div><div class="col-md-6 d-none" id="pisRouteChannelDescField"><label class="form-label small">Description (optional)</label><input class="form-control form-control-sm" id="pisRouteChannelDesc" placeholder="e.g. KCB fees paybill"></div><div class="col-md-6 d-flex align-items-center"><button type="button" class="btn btn-sm btn-primary" id="pisRouteChannelCreate">Register channel</button><span class="small ms-2" id="pisRouteChannelMessage"></span></div></div></div></div></div><div class="col-md-6"><label class="form-label">Provider <span class="text-danger">*</span></label><select class="form-select" id="pisRouteProvider" required></select></div><div class="col-md-6" id="pisRouteProductBlock"><label class="form-label">Collection product <span class="text-danger">*</span></label><select class="form-select" id="pisRouteProduct"><option value="paybill">M-Pesa Paybill / Lipa Karo</option><option value="till">M-Pesa Business Till</option><option value="buni">KCB Buni</option><option value="bank_collection">Bank collection</option></select></div><div class="col-md-6"><label class="form-label">Incoming purpose <span class="text-danger">*</span></label><select class="form-select" id="pisRoutePurpose"><option value="fees">School fees</option><option value="transport">Transport</option><option value="uniforms">Uniforms</option></select></div><div class="col-md-6"><label class="form-label">Reference policy <span class="text-danger">*</span></label><select class="form-select" id="pisRoutePolicy"><option value="admission_no">Learner admission number</option><option value="payment_reference">Generated payment reference</option><option value="transport_reference">Transport payment reference</option><option value="uniform_reference">Uniform payment reference</option></select></div><div class="col-md-6"><label class="form-label">Fallback priority</label><input class="form-control" type="number" min="0" id="pisRoutePriority" value="100"><div class="form-text">Lower number is tried first when a purpose is paid through several rails (e.g. Buni 10, PayHero 20, Daraja 30).</div></div><div class="col-12" id="pisRouteChannelsBlock"><label class="form-label">Supported channels for this collection point</label><div id="pisRouteChannels" class="row g-2"></div><div class="form-text">These channels belong to this payment door, not to the settlement bank account. Cash is unavailable for fees.</div></div><div class="col-12"><div class="border rounded p-3"><h6>Parent-facing display</h6><div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="pisRouteShow"><label class="form-check-label" for="pisRouteShow">Show this collection point on printed fee structures</label></div><div class="row g-3"><div class="col-md-8"><label class="form-label">Display title</label><input class="form-control" id="pisRouteDisplayTitle" placeholder="LIPA KARO DETAILS / BANK PAYMENT"></div><div class="col-md-4"><label class="form-label">Display order</label><input class="form-control" type="number" min="0" id="pisRouteDisplayOrder" value="0"></div><div class="col-md-6"><label class="form-label">Reference label</label><input class="form-control" id="pisRouteDisplayLabel" placeholder="ACCOUNT NO."></div><div class="col-md-6"><label class="form-label">Reference value</label><input class="form-control" id="pisRouteDisplayValue" placeholder="Admission number or fixed reference"></div><div class="col-12"><label class="form-label">Payment instructions</label><textarea class="form-control" id="pisRouteInstructions" rows="3" placeholder="Paybill 522123 and use the learner admission number."></textarea></div></div></div></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-success">Save collection point</button></div></form></div></div>'; document.body.appendChild(modal); this.routeModal = new bootstrap.Modal(modal); this.el('pisRouteForm').onsubmit = event => { event.preventDefault(); this.saveRoute(); }; },
  bindRouteRules() {
    if (!this.el('pisRouteReferenceHelp')) {
      const help = document.createElement('div');
      help.id = 'pisRouteReferenceHelp';
      help.className = 'form-text text-primary fw-semibold mt-1';
      this.el('pisRoutePolicy')?.parentElement?.appendChild(help);
    }
    ['pisRouteProvider', 'pisRouteProduct', 'pisRoutePurpose'].forEach(id => {
      this.el(id)?.addEventListener('change', () => this.applyRouteRules());
    });
    this.el('pisRouteAccount')?.addEventListener('change', () => this.applyRouteChannelRules());
    this.el('pisRouteChannelType')?.addEventListener('change', () => this.applyRouteChannelRules());
    this.el('pisRouteChannelBank')?.addEventListener('change', () => this.applyRouteChannelRules());
    this.el('pisRouteChannelModeExisting')?.addEventListener('change', () => this.applyRouteChannelRules());
    this.el('pisRouteChannelModeNew')?.addEventListener('change', () => this.applyRouteChannelRules());
    this.el('pisRouteChannelPick')?.addEventListener('change', () => {
      const channel = (this.payheroChannels || []).find(entry => String(entry.id) === this.el('pisRouteChannelPick').value);
      if (!channel) return;
      this.el('pisRouteIdentifier').value = String(channel.id);
      this.el('pisRouteProduct').value = channel.channel_type === 'bank' ? 'bank_collection' : (channel.channel_type === 'till' ? 'till' : 'paybill');
      if (!this.el('pisRouteName').value.trim()) this.el('pisRouteName').value = `${channel.description || channel.channel_type} collection point`;
      this.renderRouteChannels();
    });
    this.el('pisRouteChannelRefresh')?.addEventListener('click', () => this.loadPayheroChannels(true));
    this.el('pisRouteChannelCreate')?.addEventListener('click', () => this.registerPayheroChannel());
  },
  applyRouteRules() {
    const provider = this.el('pisRouteProvider')?.value || '';
    const purpose = this.el('pisRoutePurpose')?.value || 'fees';
    const product = this.el('pisRouteProduct')?.value || '';
    const policy = this.el('pisRoutePolicy');
    const help = this.el('pisRouteReferenceHelp');
    if (policy) {
      // Parent-facing contract: fees use KPS1; transport uses T-KPS1;
      // uniforms use U-KPS1. The backend stores the canonical form.
      policy.value = purpose === 'fees' ? 'admission_no' : (purpose === 'transport' ? 'transport_reference' : 'uniform_reference');
      policy.disabled = true;
    }
    if (provider === 'kcb_buni') {
      this.el('pisRouteProduct').value = 'buni';
      this.el('pisRouteIdentifier').placeholder = 'KCB Buni account / collection identifier';
    } else if (provider === 'payhero') {
      if (!['paybill', 'till', 'bank_collection'].includes(product)) this.el('pisRouteProduct').value = 'paybill';
      this.el('pisRouteIdentifier').placeholder = 'PayHero channel id';
    } else if (product === 'paybill' || product === 'till') {
      this.el('pisRouteIdentifier').placeholder = 'M-Pesa Paybill or Till number';
    } else {
      this.el('pisRouteIdentifier').placeholder = 'Bank collection account or identifier';
    }
    const identifier = this.el('pisRouteIdentifier');
    const identifierHelp = this.el('pisRouteIdentifierHelp');
    if (identifier) identifier.readOnly = provider === 'payhero';
    if (identifierHelp) identifierHelp.textContent = provider === 'payhero' ? 'Filled from the PayHero channel you choose below.' : '';
    this.el('pisRouteProductBlock')?.classList.toggle('d-none', provider === 'payhero');
    this.el('pisRouteChannelsBlock')?.classList.toggle('d-none', provider === 'payhero');
    this.el('pisRoutePayheroBox')?.classList.toggle('d-none', provider !== 'payhero');
    if (provider === 'payhero') this.loadPayheroChannels();
    if (help) {
      help.textContent = purpose === 'fees'
        ? 'Parents enter the learner admission number only, for example KPS1.'
        : purpose === 'transport'
          ? 'Parents enter T-KPS1. The system matches it as canonical TRN-KPS1.'
          : 'Parents enter U-KPS1. Uniform accumulated payments may use UC-KPS1.';
    }
    this.renderRouteChannels();
  },
  openRoute(accountId = 0, routeId = 0) { const route = routeId ? this.routes.find(x => Number(x.id) === routeId) : null; this.routeId = routeId; this.el('pisRouteForm').reset(); this.showRouteAlert(''); this.el('pisRouteId').value = routeId || ''; this.el('pisRouteAccount').value = accountId || route?.financial_account_id || ''; this.el('pisRouteName').value = route?.display_name || (route ? `${route.provider_code} ${route.purpose} collection point` : ''); this.el('pisRouteIdentifier').value = route?.account_identifier || ''; this.el('pisRouteProvider').value = route?.provider_code || ''; this.el('pisRouteProduct').value = route?.collection_product || 'paybill'; this.el('pisRoutePurpose').value = route?.purpose || 'fees'; this.el('pisRoutePolicy').value = route?.reference_policy || 'admission_no'; this.el('pisRoutePriority').value = route?.priority ?? 100; this.el('pisRouteShow').checked = Number(route?.show_on_fee_structure) === 1; this.el('pisRouteDisplayTitle').value = route?.display_title || ''; this.el('pisRouteDisplayOrder').value = route?.display_order || 0; this.el('pisRouteDisplayLabel').value = route?.display_reference_label || ''; this.el('pisRouteDisplayValue').value = route?.display_reference_value || ''; this.el('pisRouteInstructions').value = route?.display_instructions || ''; this.renderRouteChannels(route?.route_channels); this.el('pisRouteTitle').textContent = route ? 'Edit collection point' : 'Add collection point'; this.applyRouteRules(); this.routeModal.show(); },
  renderRouteChannels(existing = '') { const selected = String(existing || '').split(',').filter(Boolean); const provider = this.el('pisRouteProvider')?.value || ''; const product = this.el('pisRouteProduct')?.value || ''; const allowed = provider === 'kcb_buni' || product === 'buni' ? ['buni_ipn'] : (provider === 'payhero' ? ['mpesa_stk'] : (product === 'bank_collection' ? ['bank_transfer'] : ['mpesa_stk', 'mpesa_c2b'])); const values = selected.length ? selected : allowed; this.el('pisRouteChannels').innerHTML = (this.options.channels || []).map(channel => { const enabled = allowed.includes(channel.code); const checked = enabled && values.includes(channel.code); return `<div class="col-md-6"><label class="border rounded p-2 d-flex gap-2 ${enabled ? '' : 'opacity-50'}"><input class="form-check-input pis-route-channel" type="checkbox" value="${this.esc(channel.code)}" ${checked ? 'checked' : ''} ${enabled ? '' : 'disabled'}><span>${this.esc(channel.name)}<small class="d-block text-muted">${this.esc(channel.code)}</small></span></label></div>`; }).join(''); },
  async loadPayheroChannels(force = false) {
    const pick = this.el('pisRouteChannelPick');
    if (!pick) return;
    if (this.payheroChannels && !force) {
      const identifier = String(this.el('pisRouteIdentifier')?.value || '');
      if (identifier && this.payheroChannels.some(channel => String(channel.id) === identifier)) pick.value = identifier;
      this.applyRouteChannelRules();
      return;
    }
    const status = this.el('pisRouteChannelStatus');
    status.textContent = 'Loading PayHero channels…';
    status.className = 'small text-muted';
    try {
      const response = await window.API.payments.payheroChannels();
      const channels = (response?.data || response || {}).channels || [];
      this.payheroChannels = channels;
      pick.innerHTML = '<option value="">Choose a channel…</option>' + channels.map(channel => `<option value="${this.esc(channel.id)}">#${this.esc(channel.id)} · ${this.esc(channel.short_code)}${channel.description ? ' · ' + this.esc(channel.description) : ''} · ${this.esc(channel.channel_type)}</option>`).join('');
      status.textContent = channels.length ? `${channels.length} active channel${channels.length === 1 ? '' : 's'} on the PayHero account.` : 'No channels yet — register the first one.';
      const identifier = String(this.el('pisRouteIdentifier')?.value || '');
      if (identifier && channels.some(channel => String(channel.id) === identifier)) pick.value = identifier;
      else if (!channels.length) this.el('pisRouteChannelModeNew').checked = true;
    } catch (error) {
      this.payheroChannels = null;
      status.textContent = error.message || 'Unable to load PayHero channels.';
      status.className = 'small text-danger';
    }
    try {
      if (!this.payheroBanks) {
        const response = await window.API.payments.payheroBankPaybills();
        this.payheroBanks = (response?.data || response || {}).bank_paybills || [];
        this.el('pisRouteChannelBank').innerHTML = '<option value="">Choose the bank…</option>' + this.payheroBanks.map(bank => `<option value="${this.esc(bank.paybill)}" data-name="${this.esc(bank.name)}">${this.esc(bank.name)} · ${this.esc(bank.paybill)}</option>`).join('');
      }
    } catch (error) { /* Manual entry still works when the bank list is unavailable. */ }
    this.applyRouteChannelRules();
  },
  applyRouteChannelRules() {
    const registering = !!this.el('pisRouteChannelModeNew')?.checked;
    this.el('pisRouteChannelExisting')?.classList.toggle('d-none', registering);
    this.el('pisRouteChannelNew')?.classList.toggle('d-none', !registering);
    const type = this.el('pisRouteChannelType')?.value || 'bank';
    const isBank = type === 'bank';
    this.el('pisRouteChannelBankField')?.classList.toggle('d-none', !isBank);
    this.el('pisRouteChannelAccountField')?.classList.toggle('d-none', type === 'till');
    this.el('pisRouteChannelDescField')?.classList.toggle('d-none', isBank);
    const label = this.el('pisRouteChannelShortLabel');
    if (label) label.textContent = isBank ? 'Bank paybill number' : (type === 'till' ? 'Till number' : 'Paybill number');
    const bankSelect = this.el('pisRouteChannelBank');
    const option = bankSelect?.options?.[bankSelect.selectedIndex];
    if (isBank && option?.value) {
      this.el('pisRouteChannelShort').value = option.value;
      this.el('pisRouteChannelDesc').value = option.dataset.name || '';
      const account = this.rows.find(row => Number(row.id) === Number(this.el('pisRouteAccount')?.value || 0));
      if (account && !this.el('pisRouteChannelAccount').value.trim()) this.el('pisRouteChannelAccount').value = account.account_identifier || '';
    }
  },
  async registerPayheroChannel() {
    const message = this.el('pisRouteChannelMessage');
    const button = this.el('pisRouteChannelCreate');
    const payload = { channel_type: this.el('pisRouteChannelType').value, short_code: this.el('pisRouteChannelShort').value.trim(), account_number: this.el('pisRouteChannelAccount').value.trim(), description: this.el('pisRouteChannelDesc').value.trim() };
    message.textContent = 'Registering…';
    message.className = 'small ms-2 text-muted';
    button.disabled = true;
    try {
      const response = await window.API.payments.registerPayheroChannel(payload);
      const channel = response?.data || response || {};
      message.textContent = `Channel #${channel.id || ''} registered.`;
      message.className = 'small ms-2 text-success';
      showNotification('PayHero channel registered.', 'success');
      this.payheroChannels = null;
      if (channel.id) this.el('pisRouteChannelModeExisting').checked = true;
      await this.loadPayheroChannels(true);
      if (channel.id) {
        this.el('pisRouteIdentifier').value = String(channel.id);
        this.el('pisRouteChannelPick').value = String(channel.id);
        this.el('pisRouteProduct').value = channel.channel_type === 'bank' ? 'bank_collection' : (channel.channel_type === 'till' ? 'till' : 'paybill');
        if (!this.el('pisRouteName').value.trim()) this.el('pisRouteName').value = `${channel.description || channel.channel_type || 'PayHero'} collection point`;
        this.applyRouteChannelRules();
      }
    } catch (error) {
      message.textContent = error.message || 'Registration failed.';
      message.className = 'small ms-2 text-danger';
    } finally {
      button.disabled = false;
    }
  },
  async saveRoute() { this.showRouteAlert(''); const channels = [...document.querySelectorAll('.pis-route-channel:checked')].map(x => x.value); const data = { id: Number(this.el('pisRouteId').value || 0), financial_account_id: Number(this.el('pisRouteAccount').value || 0), account_identifier: this.el('pisRouteIdentifier').value.trim(), provider_code: this.el('pisRouteProvider').value, purpose: this.el('pisRoutePurpose').value, collection_product: this.el('pisRouteProduct').value, reference_policy: this.el('pisRoutePolicy').value, priority: Number(this.el('pisRoutePriority').value || 100), settlement_financial_account_id: Number(this.el('pisRouteAccount').value || 0), channels, show_on_fee_structure: this.el('pisRouteShow').checked ? 1 : 0, display_order: Number(this.el('pisRouteDisplayOrder').value || 0), display_title: this.el('pisRouteDisplayTitle').value.trim(), display_reference_label: this.el('pisRouteDisplayLabel').value.trim(), display_reference_value: this.el('pisRouteDisplayValue').value.trim(), display_instructions: this.el('pisRouteInstructions').value.trim() }; if (!data.financial_account_id || !data.account_identifier || !data.provider_code || !channels.length) return this.showRouteAlert('Choose the settlement account, provider, identifier and at least one channel.'); try { if (data.id) await window.API.finance.updatePaymentCollectionRoute(data.id, data); else await window.API.finance.savePaymentCollectionRoute(data); this.routeModal.hide(); await this.load(); } catch (error) { this.showRouteAlert(error.message || 'Unable to save collection point'); } },
  async deleteRoute(id) { if (!(await this.confirmAction('Remove this collection point?', 'Existing transactions remain recorded, but new payments will no longer route through it.', 'Remove'))) return; try { await window.API.finance.deletePaymentCollectionRoute(id); await this.load(); } catch (error) { showNotification(error.message || 'Unable to remove collection point', 'error'); } },
  ensurePosPanel() { if (this.el('pisPosPanel')) return; const panel = document.createElement('div'); panel.id = 'pisPosPanel'; panel.className = 'card shadow-sm mt-4'; panel.innerHTML = '<div class="card-header"><strong>Uniform store POS / PDQ terminals</strong><div class="small text-muted">Card payments are recorded here and settled into the selected Cooperative uniform account.</div></div><div class="card-body"><form id="pisPosForm" class="row g-2"><div class="col-md-3"><input class="form-control form-control-sm" id="pisPosName" placeholder="Terminal name" required></div><div class="col-md-3"><input class="form-control form-control-sm" id="pisPosProvider" placeholder="POS provider" required></div><div class="col-md-2"><input class="form-control form-control-sm" id="pisPosMerchant" placeholder="Merchant ID"></div><div class="col-md-2"><input class="form-control form-control-sm" id="pisPosTerminal" placeholder="Terminal ID"></div><div class="col-md-2"><select class="form-select form-select-sm" id="pisPosSettlement" required><option value="">Settlement account</option></select></div><div class="col-md-10"><input class="form-control form-control-sm" id="pisPosLocation" placeholder="Store/location"></div><div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Add terminal</button></div></form><div id="pisPosList" class="table-responsive mt-3"></div></div>'; this.el('paymentIntegrationSettingsPage').appendChild(panel); this.el('pisPosForm').onsubmit = async event => { event.preventDefault(); try { await window.API.finance.createPaymentPosTerminal({ terminal_name: this.el('pisPosName').value.trim(), provider_name: this.el('pisPosProvider').value.trim(), merchant_id: this.el('pisPosMerchant').value.trim(), terminal_id: this.el('pisPosTerminal').value.trim(), settlement_financial_account_id: Number(this.el('pisPosSettlement').value || 0), store_location: this.el('pisPosLocation').value.trim() }); event.target.reset(); await this.loadPosTerminals(); } catch (error) { showNotification(error.message || 'Unable to save POS terminal', 'error'); } }; },
  async loadPosTerminals() { try { const response = await window.API.finance.getPaymentPosTerminals(); const rows = (response?.data || response || {}).terminals || []; this.el('pisPosList').innerHTML = rows.map(row => `<div class="border rounded p-2 mb-2 small"><strong>${this.esc(row.terminal_name)}</strong> · ${this.esc(row.provider_name)} · terminal ${this.esc(row.terminal_id || '—')} · settles to ${this.esc(row.settlement_account_name || '—')} <span class="badge ${row.status === 'active' ? 'bg-success' : 'bg-warning text-dark'}">${this.esc(row.status)}</span></div>`).join('') || '<div class="text-muted small">No POS terminals configured.</div>'; } catch (error) { this.el('pisPosList').textContent = 'Unable to load POS terminals.'; } },
  async openPermissions(id) { this.permissionAccountId = id; const response = await window.API.finance.getFinancialAccountPermissions(id), rows = (response?.data || response || {}).permissions || []; this.el('pisPermissionsBody').innerHTML = rows.map(row => `<div class="row align-items-center border-bottom py-2"><div class="col">${this.esc(row.name)}</div><div class="col-auto"><label class="form-check"><input class="form-check-input pis-receive" type="checkbox" data-role="${row.role_id}" ${Number(row.can_receive) ? 'checked' : ''}> Receive</label></div><div class="col-auto"><label class="form-check"><input class="form-check-input pis-disburse" type="checkbox" data-role="${row.role_id}" ${Number(row.can_disburse) ? 'checked' : ''}> Disburse</label></div></div>`).join(''); this.permissionsModal.show(); },
  async savePermissions() { const permissions = [...document.querySelectorAll('.pis-receive')].map(x => ({ role_id: +x.dataset.role, can_receive: x.checked ? 1 : 0, can_disburse: document.querySelector(`.pis-disburse[data-role="${x.dataset.role}"]`)?.checked ? 1 : 0 })); try { await window.API.finance.setFinancialAccountPermissions(this.permissionAccountId, permissions); this.permissionsModal.hide(); } catch (error) { showNotification(error.message || 'Unable to save permissions', 'error'); } },
  async revokeKcbToken() {
    if (!(await this.confirmAction('Revoke the KCB OAuth access token?', 'Use this only for credential rotation or a security incident. New requests will obtain a fresh token.', 'Revoke token'))) return;
    const button = this.el('pisRevokeKcbToken');
    button.disabled = true;
    try {
      await window.API.finance.revokeKcbAccessToken();
      showNotification('The current KCB access token was revoked.', 'success');
    } catch (error) {
      showNotification(error.message || 'KCB token revocation failed.', 'error');
    } finally {
      button.disabled = false;
    }
  }
};
window.paymentIntegrationSettingsController = paymentIntegrationSettingsController;
document.addEventListener('DOMContentLoaded', () => paymentIntegrationSettingsController.init().catch(error => { document.getElementById('pisMessage').innerHTML = `<div class="alert alert-danger">${paymentIntegrationSettingsController.esc(error.message)}</div>`; }));
