/**
 * library_issue_return.js — Library Issue & Return Controller (standalone page).
 * Issues library books to students/staff and processes returns.
 */
const libraryIssueReturnController = {
  state: { books: [], issues: [], borrowers: { student: [], staff: [] } },

  API: (method, endpoint, data, params, opts) => window.callAPI(endpoint, method, data, params, opts),

  notify(msg, type = 'success') {
    if (window.showNotification) { showNotification(msg, type); return; }
    const el = document.createElement('div');
    el.className = `alert alert-${type} position-fixed top-0 end-0 m-3 shadow`;
    el.style.zIndex = 99999;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
  },

  esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); },

  fmtDate(v) {
    if (!v) return '\u2014';
    const dt = new Date(String(v).replace(' ', 'T'));
    if (isNaN(dt)) return this.esc(v);
    return dt.toLocaleDateString();
  },

  norm(r) { return Array.isArray(r) ? r : (r?.data || r?.items || r?.issues || r?.books || r || []); },

  async load() {
    try {
      const [books, issues] = await Promise.all([
        this.API('GET', '/library/books', null, { available_only: '1', status: 'active' }).catch(() => []),
        this.API('GET', '/library/issues', null, { status: 'issued' }).catch(() => []),
      ]);
      this.state.books = this.norm(books);
      this.state.issues = this.norm(issues);
      this.renderBooks();
      this.renderIssues();
    } catch (e) {
      document.getElementById('lirBody').innerHTML = `<tr><td colspan="6" class="text-center py-3 text-danger">${this.esc(e.message || 'Load failed')}</td></tr>`;
    }
  },

  renderBooks() {
    const sel = document.getElementById('lirBook');
    const current = sel.value;
    sel.innerHTML = '<option value="">Select book…</option>';
    this.state.books.forEach(b => {
      const opt = document.createElement('option');
      opt.value = b.id;
      opt.textContent = `${b.title} (${b.available_copies} available)`;
      sel.appendChild(opt);
    });
    sel.value = current;
    if (!this.state.books.length) sel.innerHTML = '<option value="">No books available</option>';
  },

  async loadBorrowers() {
    const type = document.getElementById('lirType').value;
    const sel = document.getElementById('lirBorrower');
    sel.innerHTML = '<option value="">Loading…</option>';
    try {
      if (type === 'student') {
        if (!this.state.borrowers.student.length) {
          const r = await this.API('GET', '/students/student', null, { limit: 300 });
          const payload = r?.data ?? r ?? {};
          this.state.borrowers.student = Array.isArray(payload?.students) ? payload.students : (Array.isArray(payload) ? payload : []);
        }
        sel.innerHTML = '<option value="">Select student…</option>';
        this.state.borrowers.student.forEach(s => {
          const opt = document.createElement('option');
          opt.value = s.id;
          opt.textContent = `${s.first_name || s.full_name || ''} ${s.last_name || ''} ${s.admission_no ? '(' + s.admission_no + ')' : ''}`.trim();
          sel.appendChild(opt);
        });
      } else {
        if (!this.state.borrowers.staff.length) {
          const r = await this.API('GET', '/staff', null, { limit: 300 });
          const payload = r?.data ?? r ?? {};
          this.state.borrowers.staff = Array.isArray(payload?.staff) ? payload.staff : (Array.isArray(payload?.data) ? payload.data : (Array.isArray(payload) ? payload : []));
        }
        sel.innerHTML = '<option value="">Select staff…</option>';
        this.state.borrowers.staff.forEach(s => {
          const opt = document.createElement('option');
          opt.value = s.id;
          opt.textContent = `${s.first_name || s.full_name || ''} ${s.last_name || ''} ${s.staff_no ? '(' + s.staff_no + ')' : ''}`.trim();
          sel.appendChild(opt);
        });
      }
    } catch (e) {
      sel.innerHTML = '<option value="">Failed to load borrowers</option>';
    }
  },

  onTypeChange() { this.loadBorrowers(); },

  async issue() {
    const bookId = document.getElementById('lirBook').value;
    const type = document.getElementById('lirType').value;
    const borrowerId = document.getElementById('lirBorrower').value;
    const dueDate = document.getElementById('lirDue').value;
    if (!bookId || !borrowerId) { this.notify('Select a book and borrower.', 'warning'); return; }
    try {
      await this.API('POST', '/library/issues', {
        book_id: Number(bookId),
        borrower_type: type,
        borrower_id: Number(borrowerId),
        due_date: dueDate || undefined,
      });
      this.notify('Book issued successfully.', 'success');
      await this.load();
    } catch (e) { this.notify(e.message || 'Failed to issue book.', 'danger'); }
  },

  async returnBook(issueId) {
    if (!confirm('Confirm return of this book?')) return;
    try {
      await this.API('PUT', `/library/issues/${issueId}/return`, {});
      this.notify('Book returned successfully.', 'success');
      await this.load();
    } catch (e) { this.notify(e.message || 'Failed to process return.', 'danger'); }
  },

  renderIssues() {
    const body = document.getElementById('lirBody');
    if (!this.state.issues.length) { body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No active loans.</td></tr>'; return; }
    body.innerHTML = this.state.issues.map(i => `<tr>
      <td class="small fw-semibold">${this.esc(i.book_title || '\u2014')}</td>
      <td class="small">${this.esc(i.borrower_name || '\u2014')} <span class="badge bg-light text-dark">${this.esc(i.borrower_type || '')}</span></td>
      <td class="small text-muted">${this.esc(i.borrower_ref || '\u2014')}</td>
      <td class="small">${this.fmtDate(i.issued_date)}</td>
      <td class="small ${Number(i.days_overdue || 0) > 0 ? 'text-danger fw-semibold' : ''}">${this.fmtDate(i.due_date)}${Number(i.days_overdue || 0) > 0 ? ` (${this.esc(i.days_overdue)}d overdue)` : ''}</td>
      <td class="text-end"><button class="btn btn-sm btn-outline-primary" data-kw-onclick="libraryIssueReturnController.returnBook(${i.id})" data-year-write>Return</button></td>
    </tr>`).join('');
  },

  setupEventListeners() {
    const due = document.getElementById('lirDue');
    const defaultDue = new Date(Date.now() + 14 * 86400000);
    due.value = defaultDue.toISOString().slice(0, 10);
  },

  async init() {
    if (window.AuthContext?.ready) await window.AuthContext.ready();
    this.setupEventListeners();
    await Promise.all([this.load(), this.loadBorrowers()]);
  },
};

window.libraryIssueReturnController = libraryIssueReturnController;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => libraryIssueReturnController.init().catch(() => {}));
} else {
  libraryIssueReturnController.init().catch(() => {});
}
