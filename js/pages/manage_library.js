/**
 * Library Management Controller
 * Handles books catalogue, issue/return, overdue tracking, fines.
 * API base: /api/library/*
 */

const libraryController = {

  _categories: [],
  _isAdminView: false,

  // ── INIT ───────────────────────────────────────────────────────────

  init: function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    const canManage = AuthContext.hasPermission('library.manage') ||
                      AuthContext.hasPermission('library.issue')  ||
                      AuthContext.hasPermission('library.create');
    this._isAdminView = canManage;

    this.loadStats();
    this.loadCategories().then(() => {
      if (canManage) {
        this.loadBooks();
        this.loadIssues();
        this.populateIssueBooksDropdown();
        this.loadBorrowerList();
        this.loadFines();
        this._bindTabEvents();
        // Set default due date 14 days from now
        const dd = document.getElementById('issueDueDate');
        if (dd) {
          const d = new Date(); d.setDate(d.getDate() + 14);
          dd.value = d.toISOString().split('T')[0];
        }
      } else {
        this.viewerSearch();
        this._populateCategoryDropdown('#viewerCategory');
      }
    });
  },

  _bindTabEvents: function () {
    document.querySelectorAll('#libraryTabs button[data-bs-toggle="tab"]').forEach(btn => {
      btn.addEventListener('shown.bs.tab', e => {
        const target = e.target.getAttribute('data-bs-target');
        if (target === '#tabIssues')     this.loadIssues();
        if (target === '#tabOverdue')    this.loadOverdue();
        if (target === '#tabFines')      this.loadFines();
        if (target === '#tabCategories') this.loadCategoriesTab();
      });
    });
  },

  // ── STATS ──────────────────────────────────────────────────────────

  loadStats: async function () {
    try {
      const r = await callAPI('/library/summary', 'GET');
      const d = r?.data ?? r ?? {};
      this._set('libTotalBooks',    d.total_books      ?? '—');
      this._set('libAvailCopies',   d.available_copies ?? '—');
      this._set('libIssuedCount',   d.currently_issued ?? '—');
      this._set('libOverdueCount',  d.overdue_items    ?? '—');
      this._set('libCatCount',      d.categories       ?? '—');
      this._set('libPendingFines',  d.pending_fines_kes != null ? 'KES ' + Number(d.pending_fines_kes).toLocaleString() : '—');
    } catch (e) { console.warn('Stats failed:', e); }
  },

  // ── CATEGORIES ─────────────────────────────────────────────────────

  loadCategories: async function () {
    try {
      const r = await callAPI('/library/categories', 'GET');
      this._categories = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._populateCategoryDropdown('#bookCatFilter');
      this._populateCategoryDropdown('#bookCategory');
      return this._categories;
    } catch (e) { return []; }
  },

  loadCategoriesTab: async function () {
    const container = document.getElementById('categoriesTableContainer');
    if (!container) return;
    if (!this._categories.length) await this.loadCategories();
    if (!this._categories.length) {
      container.innerHTML = '<div class="alert alert-info">No categories found.</div>';
      return;
    }
    const rows = this._categories.map(c =>
      `<tr><td>${this._esc(c.name)}</td><td>${this._esc(c.description || '—')}</td><td>${c.book_count ?? 0}</td></tr>`
    ).join('');
    container.innerHTML = `<div class="table-responsive"><table class="table table-sm table-hover">
      <thead class="table-light"><tr><th>Name</th><th>Description</th><th>Books</th></tr></thead>
      <tbody>${rows}</tbody></table></div>`;
  },

  _populateCategoryDropdown: function (selector) {
    document.querySelectorAll(selector).forEach(el => {
      const keep = el.options[0];
      el.innerHTML = '';
      el.appendChild(keep);
      this._categories.forEach(c => {
        const o = document.createElement('option');
        o.value = c.id; o.textContent = c.name;
        el.appendChild(o);
      });
    });
  },

  showAddCategoryModal: function () {
    document.getElementById('catName').value = '';
    document.getElementById('catDesc').value = '';
    document.getElementById('catError').classList.add('d-none');
    new bootstrap.Modal(document.getElementById('addCategoryModal')).show();
  },

  saveCategory: async function () {
    const name = document.getElementById('catName').value.trim();
    const errEl = document.getElementById('catError');
    errEl.classList.add('d-none');
    if (!name) { errEl.textContent = 'Name is required'; errEl.classList.remove('d-none'); return; }
    try {
      await callAPI('/library/categories', 'POST', { name, description: document.getElementById('catDesc').value });
      bootstrap.Modal.getInstance(document.getElementById('addCategoryModal')).hide();
      await this.loadCategories();
      this.loadCategoriesTab();
      showNotification('Category added', 'success');
    } catch (e) { errEl.textContent = e.message || 'Error'; errEl.classList.remove('d-none'); }
  },

  // ── BOOKS ──────────────────────────────────────────────────────────

  loadBooks: async function () {
    const container = document.getElementById('booksTableContainer');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    try {
      const params = new URLSearchParams();
      const s = document.getElementById('bookSearch')?.value;
      const c = document.getElementById('bookCatFilter')?.value;
      const st = document.getElementById('bookStatusFilter')?.value;
      if (s)  params.set('search', s);
      if (c)  params.set('category_id', c);
      if (st) params.set('status', st);

      const r = await callAPI('/library/books?' + params.toString(), 'GET');
      const books = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);

      if (!books.length) {
        container.innerHTML = '<div class="alert alert-info text-center">No books found. Add your first book.</div>';
        return;
      }
      const rows = books.map(b => {
        const avail = parseInt(b.available_copies ?? 0);
        const sc    = avail > 0 ? 'success' : 'danger';
        return `<tr>
          <td>${this._esc(b.isbn || '—')}</td>
          <td class="fw-semibold">${this._esc(b.title)}</td>
          <td>${this._esc(b.author)}</td>
          <td>${this._esc(b.category_name || '—')}</td>
          <td>${b.total_copies ?? 0}</td>
          <td><span class="badge bg-${sc}">${avail}</span></td>
          <td>${this._esc(b.location_shelf || '—')}</td>
          <td><span class="badge bg-${b.status === 'active' ? 'success' : 'secondary'}">${b.status}</span></td>
          <td>
            <button class="btn btn-sm btn-outline-primary me-1" onclick="libraryController.editBook(${b.id})">
              <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger" onclick="libraryController.deleteBook(${b.id}, '${this._esc(b.title)}')">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>`;
      }).join('');
      container.innerHTML = `<div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
          <thead class="table-light"><tr>
            <th>ISBN</th><th>Title</th><th>Author</th><th>Category</th>
            <th>Total</th><th>Available</th><th>Shelf</th><th>Status</th><th></th>
          </tr></thead>
          <tbody>${rows}</tbody>
        </table></div>`;
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed to load books: ${this._esc(e.message)}</div>`;
    }
  },

  showAddBookModal: function () {
    document.getElementById('addBookModalTitle').textContent = 'Add Book';
    document.getElementById('bookFormId').value  = '';
    ['bookTitle','bookIsbn','bookAuthor','bookPublisher','bookEdition','bookYear','bookShelf','bookDesc']
      .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    document.getElementById('bookCopies').value  = '1';
    document.getElementById('bookStatus').value  = 'active';
    document.getElementById('bookCategory').value = '';
    document.getElementById('bookFormError').classList.add('d-none');
    new bootstrap.Modal(document.getElementById('addBookModal')).show();
  },

  editBook: async function (id) {
    try {
      const r = await callAPI('/library/books/' + id, 'GET');
      const b = r?.data ?? r;
      document.getElementById('addBookModalTitle').textContent = 'Edit Book';
      document.getElementById('bookFormId').value      = b.id;
      document.getElementById('bookTitle').value       = b.title     || '';
      document.getElementById('bookIsbn').value        = b.isbn      || '';
      document.getElementById('bookAuthor').value      = b.author    || '';
      document.getElementById('bookPublisher').value   = b.publisher || '';
      document.getElementById('bookEdition').value     = b.edition   || '';
      document.getElementById('bookYear').value        = b.publication_year || '';
      document.getElementById('bookCategory').value    = b.category_id || '';
      document.getElementById('bookCopies').value      = b.total_copies || 1;
      document.getElementById('bookShelf').value       = b.location_shelf || '';
      document.getElementById('bookStatus').value      = b.status    || 'active';
      document.getElementById('bookDesc').value        = b.description || '';
      document.getElementById('bookFormError').classList.add('d-none');
      new bootstrap.Modal(document.getElementById('addBookModal')).show();
    } catch (e) { showNotification('Failed to load book: ' + e.message, 'danger'); }
  },

  saveBook: async function () {
    const id  = document.getElementById('bookFormId').value;
    const errEl = document.getElementById('bookFormError');
    errEl.classList.add('d-none');
    const payload = {
      title:            document.getElementById('bookTitle').value.trim(),
      isbn:             document.getElementById('bookIsbn').value.trim(),
      author:           document.getElementById('bookAuthor').value.trim(),
      publisher:        document.getElementById('bookPublisher').value.trim(),
      edition:          document.getElementById('bookEdition').value.trim(),
      publication_year: document.getElementById('bookYear').value || null,
      category_id:      document.getElementById('bookCategory').value || null,
      total_copies:     document.getElementById('bookCopies').value,
      location_shelf:   document.getElementById('bookShelf').value.trim(),
      status:           document.getElementById('bookStatus').value,
      description:      document.getElementById('bookDesc').value.trim(),
    };
    if (!payload.title || !payload.author) {
      errEl.textContent = 'Title and Author are required';
      errEl.classList.remove('d-none'); return;
    }
    const btn = document.getElementById('saveBookBtn');
    btn.disabled = true; btn.textContent = 'Saving…';
    try {
      if (id) {
        await callAPI('/library/books/' + id, 'PUT', payload);
        showNotification('Book updated', 'success');
      } else {
        await callAPI('/library/books', 'POST', payload);
        showNotification('Book added', 'success');
      }
      bootstrap.Modal.getInstance(document.getElementById('addBookModal')).hide();
      this.loadStats();
      this.loadBooks();
      this.populateIssueBooksDropdown();
    } catch (e) {
      errEl.textContent = e.message || 'Save failed';
      errEl.classList.remove('d-none');
    } finally { btn.disabled = false; btn.textContent = 'Save Book'; }
  },

  deleteBook: async function (id, title) {
    if (!confirm(`Remove "${title}" from the library catalogue?`)) return;
    try {
      await callAPI('/library/books/' + id, 'DELETE');
      showNotification('Book removed', 'success');
      this.loadStats(); this.loadBooks();
    } catch (e) { showNotification('Error: ' + e.message, 'danger'); }
  },

  // ── ISSUES ─────────────────────────────────────────────────────────

  loadIssues: async function () {
    const container = document.getElementById('issuesTableContainer');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    try {
      const r = await callAPI('/library/issues?status=issued', 'GET');
      this._renderIssuesTable(container, Array.isArray(r?.data) ? r.data : [], false);
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed: ${this._esc(e.message)}</div>`;
    }
  },

  loadOverdue: async function () {
    const container = document.getElementById('overdueTableContainer');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-danger"></div></div>';
    try {
      const r = await callAPI('/library/overdue', 'GET');
      this._renderIssuesTable(container, Array.isArray(r?.data) ? r.data : [], true);
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed: ${this._esc(e.message)}</div>`;
    }
  },

  _renderIssuesTable: function (container, issues, isOverdue) {
    if (!issues.length) {
      container.innerHTML = `<div class="alert alert-${isOverdue ? 'success' : 'info'} text-center">${isOverdue ? 'No overdue items.' : 'No active loans.'}</div>`;
      return;
    }
    const rows = issues.map(i => {
      const od = parseInt(i.days_overdue ?? 0);
      return `<tr class="${od > 0 ? 'table-warning' : ''}">
        <td>${this._esc(i.book_title)}</td>
        <td>${this._esc(i.isbn || '—')}</td>
        <td>${this._esc(i.borrower_name || '—')}</td>
        <td><span class="badge bg-secondary">${i.borrower_type}</span></td>
        <td>${this._esc(i.issued_date || '—')}</td>
        <td>${this._esc(i.due_date || '—')}</td>
        <td>${od > 0 ? `<span class="badge bg-danger">${od} day${od!==1?'s':''}</span>` : '<span class="badge bg-success">On time</span>'}</td>
        <td>
          <button class="btn btn-sm btn-success" onclick="libraryController.returnBook(${i.id}, '${this._esc(i.book_title)}')">
            <i class="bi bi-box-arrow-in-left me-1"></i>Return
          </button>
        </td>
      </tr>`;
    }).join('');
    container.innerHTML = `<div class="table-responsive"><table class="table table-sm table-hover align-middle">
      <thead class="table-light"><tr>
        <th>Book</th><th>ISBN</th><th>Borrower</th><th>Type</th>
        <th>Issued</th><th>Due</th><th>Days Overdue</th><th></th>
      </tr></thead>
      <tbody>${rows}</tbody>
    </table></div>`;
  },

  // Issue modal helpers

  showIssueModal: function () {
    document.getElementById('issueError').classList.add('d-none');
    this.loadBorrowerList();
    new bootstrap.Modal(document.getElementById('issueBookModal')).show();
  },

  populateIssueBooksDropdown: async function () {
    const sel = document.getElementById('issueBookId');
    if (!sel) return;
    try {
      const r = await callAPI('/library/books?available_only=1', 'GET');
      const books = Array.isArray(r?.data) ? r.data : [];
      sel.innerHTML = '<option value="">— Select available book —</option>';
      books.forEach(b => {
        const o = document.createElement('option');
        o.value = b.id;
        o.textContent = `${b.title} (${b.available_copies} avail.)`;
        sel.appendChild(o);
      });
    } catch (e) { console.warn('Could not load books for issue dropdown', e); }
  },

  loadBorrowerList: async function () {
    const type = document.getElementById('issueBorrowerType')?.value || 'student';
    const sel  = document.getElementById('issueBorrowerId');
    if (!sel) return;
    sel.innerHTML = '<option value="">Loading…</option>';
    try {
      const endpoint = type === 'student' ? '/students/list' : '/staff/list';
      const r = await callAPI(endpoint, 'GET').catch(() => null);
      const list = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      sel.innerHTML = '<option value="">— Select borrower —</option>';
      list.forEach(p => {
        const o = document.createElement('option');
        o.value = p.id;
        o.textContent = `${p.first_name} ${p.last_name}` + (p.admission_no ? ` (${p.admission_no})` : '') + (p.staff_id ? ` (${p.staff_id})` : '');
        sel.appendChild(o);
      });
    } catch (e) { sel.innerHTML = '<option value="">Could not load list</option>'; }
  },

  submitIssue: async function () {
    const errEl = document.getElementById('issueError');
    errEl.classList.add('d-none');
    const payload = {
      book_id:       document.getElementById('issueBookId').value,
      borrower_type: document.getElementById('issueBorrowerType').value,
      borrower_id:   document.getElementById('issueBorrowerId').value,
      due_date:      document.getElementById('issueDueDate').value,
    };
    if (!payload.book_id || !payload.borrower_id) {
      errEl.textContent = 'Select a book and borrower'; errEl.classList.remove('d-none'); return;
    }
    try {
      await callAPI('/library/issues', 'POST', payload);
      bootstrap.Modal.getInstance(document.getElementById('issueBookModal')).hide();
      showNotification('Book issued successfully', 'success');
      this.loadStats(); this.loadIssues(); this.populateIssueBooksDropdown();
    } catch (e) { errEl.textContent = e.message || 'Issue failed'; errEl.classList.remove('d-none'); }
  },

  returnBook: async function (issueId, title) {
    if (!confirm(`Confirm return of "${title}"?`)) return;
    try {
      const r = await callAPI('/library/issues/' + issueId + '/return', 'PUT', {});
      showNotification((r?.message || 'Book returned'), 'success');
      this.loadStats(); this.loadIssues(); this.loadOverdue(); this.populateIssueBooksDropdown();
    } catch (e) { showNotification('Error: ' + e.message, 'danger'); }
  },

  // ── FINES ──────────────────────────────────────────────────────────

  loadFines: async function () {
    const container = document.getElementById('finesTableContainer');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    const status = document.getElementById('fineStatusFilter')?.value || '';
    try {
      const r = await callAPI('/library/fines' + (status ? '?status=' + status : ''), 'GET');
      const fines = Array.isArray(r?.data) ? r.data : [];
      if (!fines.length) {
        container.innerHTML = '<div class="alert alert-info text-center">No fines found.</div>'; return;
      }
      const rows = fines.map(f => {
        const sc = f.fine_status === 'paid' ? 'success' : f.fine_status === 'waived' ? 'secondary' : 'danger';
        return `<tr>
          <td>${this._esc(f.book_title)}</td>
          <td>${this._esc(f.borrower_name || '—')}</td>
          <td>${f.days_overdue} day${f.days_overdue!=='1'?'s':''}</td>
          <td class="fw-bold">KES ${Number(f.fine_amount).toLocaleString()}</td>
          <td>${this._esc(f.due_date || '—')}</td>
          <td><span class="badge bg-${sc}">${f.fine_status}</span></td>
          <td>${f.fine_status === 'pending' ? `
            <button class="btn btn-sm btn-success me-1" onclick="libraryController.payFine(${f.id})">Pay</button>
            <button class="btn btn-sm btn-outline-secondary" onclick="libraryController.waiveFine(${f.id})">Waive</button>
          ` : '—'}</td>
        </tr>`;
      }).join('');
      container.innerHTML = `<div class="table-responsive"><table class="table table-sm table-hover align-middle">
        <thead class="table-light"><tr>
          <th>Book</th><th>Borrower</th><th>Days Late</th><th>Fine</th><th>Due Date</th><th>Status</th><th></th>
        </tr></thead>
        <tbody>${rows}</tbody>
      </table></div>`;
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed: ${this._esc(e.message)}</div>`;
    }
  },

  payFine: async function (id) {
    if (!confirm('Mark this fine as paid?')) return;
    try {
      await callAPI('/library/fines/' + id + '/pay', 'PUT', {});
      showNotification('Fine marked as paid', 'success');
      this.loadStats(); this.loadFines();
    } catch (e) { showNotification('Error: ' + e.message, 'danger'); }
  },

  waiveFine: async function (id) {
    const reason = prompt('Reason for waiving this fine (optional):') || '';
    try {
      await callAPI('/library/fines/' + id + '/waive', 'PUT', { reason });
      showNotification('Fine waived', 'success');
      this.loadFines();
    } catch (e) { showNotification('Error: ' + e.message, 'danger'); }
  },

  // ── VIEWER SEARCH ──────────────────────────────────────────────────

  viewerSearch: async function () {
    const container = document.getElementById('viewerBooksGrid');
    if (!container) return;
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    try {
      const params = new URLSearchParams();
      const s  = document.getElementById('viewerSearch')?.value;
      const c  = document.getElementById('viewerCategory')?.value;
      const av = document.getElementById('viewerAvailOnly')?.checked;
      if (s)  params.set('search', s);
      if (c)  params.set('category_id', c);
      if (av) params.set('available_only', '1');

      const r     = await callAPI('/library/books?' + params.toString(), 'GET');
      const books = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);

      if (!books.length) {
        container.innerHTML = '<div class="alert alert-info text-center col-12">No books match your search.</div>';
        return;
      }
      const cards = books.map(b => {
        const avail = parseInt(b.available_copies ?? 0);
        const sc    = avail > 0 ? 'success' : 'danger';
        const label = avail > 0 ? `${avail} available` : 'Not available';
        return `<div class="col-sm-6 col-md-4 col-lg-3 mb-3">
          <div class="card border-0 shadow-sm h-100 rounded-3">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="badge bg-light text-secondary">${this._esc(b.category_name || 'General')}</span>
                <span class="badge bg-${sc}">${label}</span>
              </div>
              <h6 class="fw-bold mb-1">${this._esc(b.title)}</h6>
              <p class="text-muted small mb-1">${this._esc(b.author)}</p>
              ${b.isbn ? `<p class="text-muted small mb-0">ISBN: ${this._esc(b.isbn)}</p>` : ''}
              ${b.location_shelf ? `<p class="text-muted small mb-0"><i class="bi bi-geo-alt me-1"></i>${this._esc(b.location_shelf)}</p>` : ''}
            </div>
          </div>
        </div>`;
      }).join('');
      container.innerHTML = `<div class="row">${cards}</div>`;
    } catch (e) {
      container.innerHTML = `<div class="alert alert-danger">Failed to load catalogue: ${this._esc(e.message)}</div>`;
    }
  },

  // ── UTILS ──────────────────────────────────────────────────────────

  _set: function (id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  },

  _esc: function (s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  },
};
