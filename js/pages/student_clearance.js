/**
 * Student clearance page.
 *
 * Lists the learners who left in the selected year (shared year filter,
 * default current) with their outcome, final fee position and the five
 * clearance checks. Opening a learner starts the clearance (finance is set
 * from the fee account) and records each department's decision. Once every
 * mandatory check is cleared the exit document is issued as a PDF: the school
 * leaving certificate (graduates, withdrawals) or the transfer letter
 * (transfers, with the receiving school); a transfer is then completed with
 * the NEMIS release date.
 */
var StudentClearanceController = {
  state: { yearId: null, data: null, current: null, request: 0 },

  api(path, method = 'GET', data = null, params = null) { return window.callAPI(path, method, data, params); },
  unwrap(r) { return r?.data?.data ?? r?.data ?? r; },
  esc(v) { return String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]); },
  el(id) { return document.getElementById(id); },
  money(v) { return `KES ${Math.abs(Number(v || 0)).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`; },
  stateLabel: { not_started: 'Not started', in_progress: 'In progress', blocked: 'Blocked', cleared: 'Cleared' },
  statusLabel: { not_started: 'Not started', pending: 'Pending', cleared: 'Cleared', blocked: 'Blocked' },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    ['clrOutcome', 'clrState'].forEach((id) => this.el(id)?.addEventListener('change', () => this.load()));
    let t = null;
    this.el('clrSearch')?.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => this.load(), 300); });
    this.el('clrExportBtn')?.addEventListener('click', () => this.exportCSV());
    this.el('clrPrintBtn')?.addEventListener('click', () => this.printList());
    this.el('clrCertificateBtn')?.addEventListener('click', () => this.openDocument());
    this.el('clrDocForm')?.addEventListener('submit', (e) => { e.preventDefault(); this.issueDocument(); });
    this.el('clrTransferBtn')?.addEventListener('click', () => this.completeTransfer());
    this.el('clrTable')?.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-clr-open]');
      if (btn) this.open(Number(btn.dataset.clrOpen));
    });
    this.el('clrModalBody')?.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-clr-decide]');
      if (btn) this.decide(btn.dataset.clrType, btn.dataset.clrDecide);
    });
    const yearSelect = this.el('clrYear');
    const mounted = window.AcademicYearFilter && yearSelect
      ? await window.AcademicYearFilter.mount(yearSelect, { onChange: (yearId) => { this.state.yearId = yearId; this.load(); } })
      : null;
    if (!mounted) await this.load();
    window.APIRealtime?.register?.('student_clearance', { refresh: () => this.load() }, ['students', 'finance']);
  },

  params() {
    const p = {};
    if (this.state.yearId) p.academic_year_id = this.state.yearId;
    const outcome = this.el('clrOutcome')?.value; if (outcome) p.outcome = outcome;
    const st = this.el('clrState')?.value; if (st) p.state = st;
    const q = this.el('clrSearch')?.value?.trim(); if (q) p.search = q;
    return p;
  },

  async load() {
    const req = ++this.state.request;
    const body = this.el('clrTable')?.querySelector('tbody');
    try {
      const r = await this.api('/api/students/clearance-leavers', 'GET', null, this.params());
      if (req !== this.state.request) return;
      if (r?.success === false) throw new Error(r.message);
      this.state.data = this.unwrap(r) || {};
      this.render();
    } catch (e) {
      if (body) body.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${this.esc(e?.message || 'Clearance could not be loaded.')}</td></tr>`;
    }
  },

  render() {
    const d = this.state.data || {};
    const s = d.summary || {};
    const y = d.year || {};
    this.el('clrPeriod').textContent = y.code ? `Learners who left in ${y.code}. Settle each check before issuing the leaving certificate.` : '';
    const fig = (label, value, cls = '') => `<div class="col-6 col-md"><div class="card"><div class="card-body text-center"><h6>${label}</h6><h3 class="mb-0 ${cls}">${value}</h3></div></div></div>`;
    this.el('clrSummary').innerHTML = [
      fig('Leavers', Number(s.leavers || 0)),
      fig('Not started', Number(s.not_started || 0)),
      fig('In progress', Number(s.in_progress || 0)),
      fig('Blocked', Number(s.blocked || 0), Number(s.blocked) ? 'text-danger' : ''),
      fig('Cleared', Number(s.cleared || 0)),
      fig('Still owed', this.money(s.owing), Number(s.owing) ? 'text-danger' : ''),
      fig('Overpaid, to refund', this.money(s.overpaid)),
    ].join('');
    const body = this.el('clrTable').querySelector('tbody');
    const list = d.learners || [];
    body.innerHTML = list.length ? list.map((l) => {
      const dots = l.checks.map((c) => `<span class="clr-dot ${c.status === 'not_started' ? '' : c.status}" title="${this.esc(c.label)}: ${this.esc(this.statusLabel[c.status] || c.status)}"></span>`).join('');
      const tone = l.state === 'cleared' ? 'bg-success' : l.state === 'blocked' ? 'bg-danger' : l.state === 'in_progress' ? 'bg-warning' : 'bg-secondary';
      return `<tr>
        <td><strong>${this.esc(l.name)}</strong><div class="small text-muted">${this.esc(l.admission_no)}</div></td>
        <td>${this.esc(l.outcome)}</td>
        <td>${this.esc(l.class)}</td>
        <td class="text-end ${l.balance > 0 ? 'clr-owed' : (l.balance < 0 ? 'clr-credit' : '')}">${this.esc(l.balance_label)}</td>
        <td>${dots}</td>
        <td><span class="badge ${tone}">${this.esc(this.stateLabel[l.state] || l.state)}</span></td>
        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-secondary" data-clr-open="${l.student_id}">${l.state === 'not_started' ? 'Start clearance' : 'Open'}</button></td>
      </tr>`;
    }).join('') : `<tr><td colspan="7" class="text-center text-muted py-4">No learner left in ${this.esc(y.code || 'this year')} with these filters.</td></tr>`;
  },

  async open(studentId) {
    const l = (this.state.data?.learners || []).find((x) => x.student_id === studentId);
    if (!l) return;
    try {
      const r = await this.api('/api/students/clearance-start', 'POST', { student_id: studentId, academic_year_id: this.state.data.year.id });
      if (r?.success === false) throw new Error(r.message);
      this.state.current = this.unwrap(r);
    } catch (e) {
      window.showNotification?.(e?.message || 'The clearance could not be opened.', 'error');
      return;
    }
    const nemis = this.el('clrNemisDate');
    if (nemis) nemis.value = '';
    this.renderModal();
    bootstrap.Modal.getOrCreateInstance(this.el('clrModal')).show();
    this.load();
  },

  renderModal() {
    const l = this.state.current;
    if (!l) return;
    this.el('clrModalTitle').textContent = `Clearance: ${l.name}`;
    this.el('clrModalMeta').textContent = `${l.admission_no}, ${l.outcome}, ${l.class}. ${l.balance_label}.`;
    this.el('clrModalBody').innerHTML = l.checks.map((c) => `
      <div class="clr-check" data-check="${c.type}">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div><strong>${this.esc(c.label)}</strong>${c.mandatory ? '' : ' <small class="text-muted">(optional)</small>'}
            <div class="small text-muted">${this.esc(this.statusLabel[c.status] || c.status)}${c.note ? `: ${this.esc(c.note)}` : ''}${c.checked_at ? `, ${this.esc(c.checked_at)}` : ''}</div></div>
          <div class="d-flex gap-1" data-year-write>
            <button type="button" class="btn btn-sm ${c.status === 'cleared' ? 'btn-success' : 'btn-outline-success'}" data-clr-decide="cleared" data-clr-type="${c.type}">Cleared</button>
            <button type="button" class="btn btn-sm ${c.status === 'blocked' ? 'btn-danger' : 'btn-outline-danger'}" data-clr-decide="blocked" data-clr-type="${c.type}">Blocked</button>
          </div>
        </div>
        <input type="text" class="form-control form-control-sm mt-2" id="clrNote_${c.type}" placeholder="${c.type === 'finance' ? 'How was it settled? (paid, refunded, transferred, written off)' : 'Note (required when blocked)'}" value="">
      </div>`).join('');
    const label = l.document_label || 'School leaving certificate';
    const doc = l.document;
    const btn = this.el('clrCertificateBtn');
    btn.disabled = !l.certificate_ready;
    btn.title = l.certificate_ready ? '' : 'Every mandatory check must be cleared first.';
    btn.innerHTML = `<i class="bi bi-${l.document_type === 'transfer_letter' ? 'envelope-paper' : 'award'} me-1"></i>${doc ? 'Reprint' : 'Issue'} ${this.esc(label.toLowerCase())}`;
    this.el('clrDocStatus').innerHTML = doc
      ? `<strong>${this.esc(label)}</strong> <span class="badge bg-success">Issued</span>
         <div class="small text-muted">Serial ${this.esc(doc.serial_no)}, issued ${this.esc(this.date(doc.issued_at))}, printed ${Number(doc.print_count || 0)} time${Number(doc.print_count) === 1 ? '' : 's'}.</div>`
      : `<strong>${this.esc(label)}</strong> <span class="badge bg-secondary">Not issued</span>
         <div class="small text-muted">${l.certificate_ready ? 'Every mandatory check is cleared. Issue it below.' : 'Issued once every mandatory check is cleared.'}</div>`;
    const transfer = this.el('clrTransfer');
    const transferring = l.outcome_key === 'transferring';
    const transferred = l.outcome_key === 'transferred';
    transfer.classList.toggle('d-none', !(transferring || transferred));
    this.el('clrTransferForm').classList.toggle('d-none', !transferring);
    this.el('clrTransferDone').classList.toggle('d-none', !transferred);
    if (transferred) {
      this.el('clrTransferDone').innerHTML = `<strong>Transfer completed</strong><div class="small text-muted">${l.nemis_released_at ? `Released on NEMIS on ${this.esc(this.date(l.nemis_released_at))}.` : 'No NEMIS release date was recorded.'}</div>`;
    } else if (transferring) {
      const done = this.el('clrTransferBtn');
      if (done) {
        done.disabled = !l.can_complete_transfer;
        done.title = l.can_complete_transfer ? '' : 'Clear every mandatory check and issue the transfer letter first.';
      }
      const nemis = this.el('clrNemisDate');
      if (nemis && !nemis.value) nemis.value = new Date().toISOString().slice(0, 10);
    }
    window.YearMode?.refresh?.(this.el('clrModal'));
  },

  date(v) {
    if (!v) return '';
    const t = new Date(String(v).replace(' ', 'T'));
    return Number.isNaN(t.getTime()) ? String(v) : t.toLocaleDateString('en-KE', { day: 'numeric', month: 'long', year: 'numeric' });
  },

  async decide(type, status) {
    const l = this.state.current;
    if (!l) return;
    const note = this.el(`clrNote_${type}`)?.value?.trim() || null;
    try {
      const r = await this.api('/api/students/clearance-decide', 'POST', { student_id: l.student_id, academic_year_id: this.state.data.year.id, type, status, note });
      if (r?.success === false) throw new Error(r.message);
      this.state.current = this.unwrap(r);
      this.renderModal();
      this.load();
    } catch (e) {
      window.showNotification?.(e?.message || 'The decision could not be saved.', 'error');
    }
  },

  /** Opens the issue form with what the document will print and the stored report. */
  async openDocument() {
    const l = this.state.current;
    if (!l || !l.certificate_ready) return;
    if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('students')) return window.showNotification?.('You do not have permission to print.', 'error');
    let ctx;
    try {
      const r = await this.api('/api/students/clearance-document', 'GET', null, { student_id: l.student_id, academic_year_id: this.state.data.year.id });
      if (r?.success === false) throw new Error(r.message);
      ctx = this.unwrap(r) || {};
    } catch (e) {
      window.showNotification?.(e?.message || 'The document could not be loaded.', 'error');
      return;
    }
    const f = ctx.facts || {};
    const def = ctx.defaults || {};
    const transfer = l.document_type === 'transfer_letter';
    this.el('clrDocTitle').textContent = l.document_label || 'School leaving certificate';
    this.el('clrDocMeta').textContent = ctx.document?.serial_no ? `Reprint of ${ctx.document.serial_no}` : 'A serial number is assigned when it is issued.';
    const fact = (k, v, warn = false) => `<div class="col-6 col-md-4 clr-fact"><dl class="mb-0"><dt>${k}</dt><dd class="${warn ? 'text-danger' : ''}">${this.esc(v || 'Not recorded')}</dd></dl></div>`;
    this.el('clrDocFacts').innerHTML = [
      fact('Learner', f.full_name), fact('Admission no.', f.admission_no), fact('UPI (NEMIS) no.', f.upi, !f.upi),
      fact('Assessment no.', f.assessment_number), fact('Date of birth', this.date(f.dob), !f.dob),
      fact('Admitted', [this.date(f.admission_date) || f.admission_year, f.admission_class].filter(Boolean).join(', ')),
      fact('Leaving grade', [f.leaving_class, f.leaving_stream].filter(Boolean).join(' ')), fact('Academic year', f.academic_year),
    ].join('');
    this.el('clrDocDestination').classList.toggle('d-none', !transfer);
    const dest = ctx.learner?.destination || l.destination || {};
    const set = (id, v) => { const x = this.el(id); if (x) x.value = v ?? ''; };
    set('clrDestSchool', dest.school); set('clrDestCode', dest.school_code); set('clrDestCounty', dest.county);
    set('clrDestSubCounty', dest.sub_county); set('clrDestReason', dest.reason);
    set('clrDocLeaving', String(def.leaving_date || '').slice(0, 10));
    set('clrDocAcademic', def.academic_report); set('clrDocConduct', def.conduct); set('clrDocCo', def.co_curricular);
    this.el('clrDocSubmit').innerHTML = `<i class="bi bi-file-earmark-pdf me-1"></i>${ctx.document ? 'Reprint' : 'Issue'} and open PDF`;
    bootstrap.Modal.getOrCreateInstance(this.el('clrDocModal')).show();
  },

  async issueDocument() {
    const l = this.state.current;
    if (!l) return;
    const v = (id) => this.el(id)?.value?.trim() || '';
    const transfer = l.document_type === 'transfer_letter';
    const missing = [];
    if (transfer && !v('clrDestSchool')) missing.push('the receiving school');
    if (!v('clrDocAcademic')) missing.push('the academic ability report');
    if (!v('clrDocConduct')) missing.push('the conduct report');
    if (missing.length) return window.showNotification?.(`Fill in ${missing.join(', ')}.`, 'warning');
    const payload = {
      student_id: l.student_id,
      academic_year_id: this.state.data.year.id,
      leaving_date: v('clrDocLeaving') || null,
      academic_report: v('clrDocAcademic'),
      conduct: v('clrDocConduct'),
      co_curricular: v('clrDocCo') || null,
    };
    if (transfer) Object.assign(payload, {
      destination_school: v('clrDestSchool'), destination_school_code: v('clrDestCode') || null,
      destination_county: v('clrDestCounty') || null, destination_sub_county: v('clrDestSubCounty') || null,
      reason: v('clrDestReason') || null,
    });
    const submit = this.el('clrDocSubmit');
    submit.disabled = true;
    try {
      const r = await this.api('/api/students/clearance-document-issue', 'POST', payload);
      if (r?.success === false) throw new Error(r.message);
      const out = this.unwrap(r) || {};
      bootstrap.Modal.getOrCreateInstance(this.el('clrDocModal')).hide();
      window.showNotification?.(`${l.document_label} ${out.serial_no || ''} issued.`, 'success');
      const url = out.url || out.download_url;
      if (url) window.PrintManager?.openDocument ? window.PrintManager.openDocument(url, { title: l.document_label }) : window.open(url, '_blank', 'noopener');
      await this.refreshCurrent();
    } catch (e) {
      window.showNotification?.(e?.message || 'The document could not be issued.', 'error');
    } finally {
      submit.disabled = false;
    }
  },

  async completeTransfer() {
    const l = this.state.current;
    if (!l || !l.can_complete_transfer) return;
    const released = this.el('clrNemisDate')?.value || null;
    const ok = await window.confirmAction('Complete transfer', `Complete the transfer of ${l.name}? The learner's enrolment and status become transferred.`, { confirmText: 'Complete transfer' });
    if (!ok) return;
    try {
      const r = await this.api('/api/students/clearance-transfer-complete', 'POST', { student_id: l.student_id, academic_year_id: this.state.data.year.id, nemis_released_at: released });
      if (r?.success === false) throw new Error(r.message);
      this.state.current = this.unwrap(r);
      this.renderModal();
      window.showNotification?.('Transfer completed.', 'success');
      this.load();
    } catch (e) {
      window.showNotification?.(e?.message || 'The transfer could not be completed.', 'error');
    }
  },

  /** Re-reads the open learner (document serial, transfer state) and the list. */
  async refreshCurrent() {
    const l = this.state.current;
    if (!l) return;
    try {
      const r = await this.api('/api/students/clearance-start', 'POST', { student_id: l.student_id, academic_year_id: this.state.data.year.id });
      if (r?.success !== false) { this.state.current = this.unwrap(r); this.renderModal(); }
    } catch (e) { /* the list reload below still shows the change */ }
    this.load();
  },

  exportCSV() {
    if (window.AuthContext?.canExport && !window.AuthContext.canExport('students')) return window.showNotification?.('You do not have permission to export.', 'error');
    const q = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const d = this.state.data || {};
    const checks = Object.values(d.checks || {}).map((c) => c.label);
    const lines = [['Learner', 'Admission no', 'Left as', 'Class', 'Fee account', ...checks, 'Clearance'].map(q).join(',')];
    (d.learners || []).forEach((l) => lines.push([l.name, l.admission_no, l.outcome, l.class, l.balance_label, ...l.checks.map((c) => this.statusLabel[c.status] || c.status), this.stateLabel[l.state]].map(q).join(',')));
    window.KingswayFileLifecycle?.exportText?.('﻿' + lines.join('\r\n'), `student-clearance_${(d.year?.code || '').replace(/[^0-9]+/g, '-')}.csv`, 'text/csv;charset=utf-8');
  },

  printList() {
    if (window.AuthContext?.canPrint && !window.AuthContext.canPrint('students')) return window.showNotification?.('You do not have permission to print.', 'error');
    window.print();
  },
};

window.StudentClearanceController = StudentClearanceController;
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => StudentClearanceController.init());
else StudentClearanceController.init();
